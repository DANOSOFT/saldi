<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/crmFunctions.php --- ver 5.1.1 --- 2026.11.11 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261020 ASR CRM redesign (prototype_crm.html v1.1): schema, the data behind debitor/crm.php and the actions of debitor/crmApi.php.
// 20261028 ASR The UI calls deals "sager" (tab Sager, Ny sag); code and tables keep the name deal.
// 20261029 ASR Meetings with colleagues: a time and a length, colleagues on a meeting or a deal (bell items for them), a warning when a colleague already has a meeting then (with the next free time offered), calendar invitations by mail (.ics, sent only when the user ticks it) and a personal calendar feed.
//
// 20261110 CDX/ASR Include CRM-only leads and promote them when preparing the first sales document.
// 20261110 CDX/ASR Translate lead creation and continuation within the activity customer picker.
// 20261110 CDX/ASR Move schema and legacy-data updates to the migration helper; runtime only checks readiness.
// 20261110 CDX/ASR Serialize document linking and retain connection-local inserted ids.
// 20261111 CDX/ASR Use legacy access checks and CRM-local assignment notices without the design branch.
//
// Data model (existing unless marked new):
//   historik     konto_id (customer), ansat_id (our employee = owner), kontakt_id (customer's contact person, ansatte),
//                notat, notedate, kontaktet (date of the entry), kontaktes (follow-up date), completed, dokument,
//                type (new: note | opkald | mail | moede | status | deal)
//   adresser     status (id from grupper art='DebInfo' box3/box4), kontoansvarlig (owner), kontaktet/kontaktes
//                (kept in step: latest entry / earliest open follow-up, so the old lists still show them)
//   grupper SKN  status groups: box1 name, box2 status ids (tab separated), kodenr order,
//                box3 status a customer is offered when a deal is won, box4 when lost (new use, optional)
//   crm_deals    new table: deals per customer (title, amount, stage = a status id, expected close, owner, state,
//                ordre_id = the offer/order made from the deal); shown on the customer's card and in the drawer
//   crm_meetings new table (20261029): a follow-up of type moede with a time - start_time 'HH:MM', duration in
//                minutes, seq (the calendar invitation's SEQUENCE), invite_team / invite_contact (invitations sent)
//   crm_participants new table: the colleagues on a meeting (kind 'h', ref_id = historik.id) or a deal ('d')
//   crm_ical     new table: a user's secret calendar feed link (api/crm/ical.php)
//
// Which customers the page loads (crm_customer_scope): those with a status in one of the status groups, a CRM entry,
// an open deal or a next-contact date - at most CRM_MAX_CUSTOMERS, the most recently active first. In most companies
// every customer has a status, so without the cap the page would load the whole customer file. Customers outside
// the loaded set are reached through the server-side search (Ny opfølgning / Ny sag).
//
// Open follow-up: kontaktes set and completed false. Rows written by the old pages have completed NULL; such a row
// counts as open only while it is the customer's newest row WITH a date (the old model kept one "next contact" per
// customer: a newer dated row replaced it, a dateless note did not). crm_open_sql() is the one definition, and
// crm_migrate_legacy_followups() makes the old rows explicit once. 20261024 ASR after the customer-perspective review
// (CRM_review_consolidated_2026-10-23_EN.md): author_id on historik, soft-deleted deals, amounts with ore, currency
// conversion, scope ordered by open work first.

require_once __DIR__ . '/crmLeadFunctions.php';
require_once __DIR__ . '/crmNotifications.php';

if (!function_exists('crm_is_mysql')) {

function crm_is_mysql(): bool
{
	global $db_type;
	return in_array($db_type, array('mysql', 'mysqli'), true);
}

function crm_col_exists(string $table, string $col): bool
{
	$schema = crm_is_mysql() ? " and table_schema = DATABASE()" : " and table_schema = current_schema()";
	return (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = '$table' and column_name = '$col'$schema", __FILE__ . " linje " . __LINE__));
}

/** Read-only gate for a session opened before the deployment's login migration has run. */
function crm_schema_ready(): bool
{
	$required = array(
		'historik' => array('completed', 'type', 'author_id', 'completed_at'),
		'crm_deals' => array('id', 'ordre_id'),
		'crm_meetings' => array('historik_id', 'start_time', 'duration', 'seq', 'invite_team', 'invite_contact'),
		'crm_participants' => array('id', 'kind', 'ref_id', 'ansat_id'),
		'crm_ical' => array('bruger_id', 'token', 'created'),
		'crm_notifications' => array('id', 'event_key', 'bruger_id', 'title', 'body', 'url', 'created', 'expires', 'seen'),
	);
	$scope = crm_is_mysql() ? 'table_schema = DATABASE()' : 'table_schema = current_schema()';
	$q = db_select("select table_name, column_name from information_schema.columns where $scope and table_name in ('historik', 'crm_deals', 'crm_meetings', 'crm_participants', 'crm_ical', 'crm_notifications')", __FILE__ . ' linje ' . __LINE__);
	$columns = array();
	while ($row = db_fetch_array($q)) {
		$columns[$row['table_name']][] = $row['column_name'];
	}
	foreach ($required as $table => $names) {
		if (array_diff($names, $columns[$table] ?? array())) {
			return false;
		}
	}
	return true;
}

/**
 * The one definition of "open follow-up", used by the page, the bell and the sync of adresser.kontaktes:
 * completed FALSE, or a legacy NULL row with a date that is the customer's newest dated row (see opdat_func/crmSchema.php).
 */
function crm_open_sql(string $h = 'h'): string
{
	return "($h.kontaktes is not null and ($h.completed = FALSE or ($h.completed is null and $h.id = "
		. "(select max(hx.id) from historik hx where hx.konto_id = $h.konto_id and hx.kontaktes is not null))))";
}

// ------------------------------------------------------------------ encoding (the JSON is always UTF-8)

function crm_out($s): string
{
	global $db_encode;
	$s = (string) $s;
	return ($db_encode === 'UTF8' || $s === '') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-15');
}

function crm_in($s): string
{
	global $db_encode;
	$s = trim((string) $s);
	if ($db_encode !== 'UTF8') {
		$s = mb_convert_encoding($s, 'ISO-8859-15', 'UTF-8');
	}
	return db_escape_string($s);
}

/** crm_in() without the SQL escaping: for values that are not going into a query. */
function crm_in_raw($s): string
{
	global $db_encode;
	$s = trim((string) $s);
	return ($db_encode !== 'UTF8') ? mb_convert_encoding($s, 'ISO-8859-15', 'UTF-8') : $s;
}

function crm_date($v): ?string
{
	$v = trim((string) $v);
	return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function crm_bool($v): bool
{
	return in_array($v, array('t', 'true', true, 1, '1'), true);
}

function crm_ids(array $ids): string
{
	$ids = array_values(array_unique(array_map('intval', $ids)));
	return $ids ? implode(',', $ids) : '0';
}

// ------------------------------------------------------------------ texts (tekster.csv 9000-9393; 7300-7693 on the redesign branch, moved when the CRM came to the master line)

/**
 * The CRM's texts: id => Danish text. The Danish text is the key the page and javascript/crm.js use; the user's
 * language comes from tekster.csv (or the company's own text in the tekster table). New texts: next free id here and
 * the same id in importfiler/tekster.csv.
 *
 * @return array<int, string>
 */
function crm_text_ids(): array
{
	return array(
		9000 => 'i dag',
		9001 => 'i morgen',
		9002 => 'i går',
		9003 => 'for %s dage siden',
		9004 => '%s kr',
		9005 => 'Ingen status',
		9006 => 'Dig',
		9007 => 'dig',
		9008 => 'Note',
		9009 => 'Opkald',
		9010 => 'Mail',
		9011 => 'Møde',
		9012 => 'Status',
		9013 => 'Sag',
		9014 => 'Kunne ikke gemme (%s). Prøv igen.',
		9015 => 'Opfølgning',
		9016 => '%s forsinket',
		9017 => 'Sager',
		9018 => 'Kalender',
		9019 => 'Kunder',
		9020 => 'Aktivitet',
		9021 => 'Forsinket',
		9022 => 'I dag',
		9023 => 'I morgen',
		9024 => 'Resten af ugen',
		9025 => 'Næste uge',
		9026 => 'Senere',
		9027 => 'Markér som ikke gjort',
		9028 => 'Markér som gjort',
		9029 => 'Gjort',
		9030 => 'Udsæt',
		9031 => 'Åbn',
		9032 => 'Kunde · kontakt',
		9033 => 'Ansvarlig',
		9034 => 'Dato',
		9035 => 'Ingen åbne opfølgninger',
		9036 => 'Prøv at fjerne filtrene.',
		9037 => 'Opret en med »Ny opfølgning« eller fra en kunde.',
		9038 => 'Skjul %s gjort de sidste 14 dage',
		9039 => 'Vis %s gjort de sidste 14 dage',
		9040 => 'Alle statusser',
		9041 => 'ny',
		9042 => '%s år',
		9043 => '%s d',
		9044 => '%s åbne sager',
		9045 => 'Ingen opfølgning',
		9046 => 'I %s siden %s',
		9047 => 'Ingen statusser at vise',
		9049 => '%s kunder',
		9050 => 'åbne sager %s',
		9051 => '%s med forsinket opfølgning',
		9052 => '%s uden opfølgning',
		9053 => 'Statusgruppe',
		9054 => 'Vundet',
		9055 => 'Tabt',
		9056 => 'Ny opfølgning %s',
		9057 => '+%s mere',
		9058 => 'Uge %s',
		9059 => 'Forrige',
		9060 => 'Næste',
		9061 => 'Åben',
		9062 => 'Træk for at flytte',
		9063 => 'Måned',
		9064 => 'Uge',
		9065 => 'Alle',
		9066 => 'Uden gruppe',
		9067 => 'Alle i %s',
		9068 => 'Kunde',
		9069 => 'Kontaktperson',
		9070 => 'Seneste kontakt',
		9071 => 'Næste opfølgning',
		9072 => 'Omsætning i år',
		9073 => 'Ingen',
		9074 => 'Ingen kunder',
		9075 => 'Ingen kunder matcher filtrene.',
		9077 => 'Opfølgning %s · gjort',
		9078 => 'Opfølgning %s',
		9079 => 'Ukendt',
		9080 => 'Ret',
		9081 => 'Slet',
		9082 => 'I går',
		9083 => 'Ingen aktivitet',
		9084 => 'Ingen henvendelser matcher filtrene.',
		9085 => 'Om 1 uge',
		9086 => 'Om 2 uger',
		9087 => 'Om 1 måned',
		9088 => 'med',
		9089 => '(ingen)',
		9090 => 'Hvad blev der talt om?',
		9091 => 'Vælg dato',
		9092 => 'til',
		9093 => 'mig',
		9094 => 'Markér opfølgningen fra %s som gjort',
		9095 => 'Gem',
		9096 => 'Kunde %s',
		9097 => 'ansvarlig: %s',
		9098 => 'Luk (Esc)',
		9099 => 'Opret sag',
		9100 => 'Opret tilbud',
		9101 => 'Opret ordre',
		9102 => 'Debitorkort',
		9103 => 'Jobkort',
		9104 => 'Kunde siden',
		9105 => 'Åbne poster',
		9106 => '%s forfalden',
		9107 => 'Historik',
		9108 => 'Sager og ordrer',
		9109 => 'Kontaktpersoner',
		9110 => 'forsinket',
		9111 => 'Ingen historik endnu',
		9112 => 'Skriv den første note ovenfor.',
		9113 => 'Ny note',
		9114 => 'Ingen kontaktpersoner',
		9115 => '+ Tilføj kontaktperson på debitorkortet',
		9116 => 'forventet lukket %s',
		9117 => 'Vundet %s',
		9118 => 'Tabt %s',
		9119 => 'Ingen sager',
		9120 => '+ Ny sag',
		9121 => 'Åbne tilbud og ordrer',
		9122 => 'delvist leveret',
		9123 => 'sag: %s',
		9124 => 'Ingen åbne tilbud eller ordrer.',
		9125 => '+ Opret tilbud',
		9126 => '+ Opret ordre',
		9127 => 'Tilbud kan ikke oprettes, fordi »Anvend hurtigfakturering« er slået til (Indstillinger → Ordrer).',
		9128 => 'Gemt',
		9129 => 'opfølgning %s',
		9130 => 'forrige opfølgning markeret som gjort',
		9131 => 'Søg kunde, kundenummer eller kontaktperson',
		9132 => 'Opret kunden under Debitor → Konti.',
		9133 => 'Ny opfølgning',
		9134 => 'Vælg kunden – du kan også søge på kontaktperson eller kundenummer.',
		9135 => 'Skift kunde',
		9136 => 'Faktura %s',
		9137 => 'Tilbud %s',
		9138 => 'Ordre %s',
		9139 => 'faktureret',
		9140 => 'ordre',
		9141 => 'tilbud',
		9142 => 'Brug beløbet fra tilbuddet: %s',
		9143 => 'Brug beløbet fra ordren: %s',
		9144 => 'Ordren er faktureret – markér sagen som vundet',
		9145 => 'Intet tilbud eller ordre til sagen endnu',
		9146 => 'Ret sag',
		9147 => 'Ny sag',
		9148 => 'Titel',
		9149 => 'Fx Saldi Plus, 3 brugere',
		9151 => 'Forventet lukket',
		9152 => 'Stadie',
		9153 => 'Mig',
		9154 => 'Slet sag',
		9155 => 'Annullér',
		9157 => 'vundet',
		9158 => 'tabt',
		9159 => 'Sagen er gemt',
		9160 => 'Sag oprettet i %s',
		9161 => 'Hvilken kunde gælder sagen?',
		9162 => 'Pris',
		9163 => 'Valgte en konkurrent',
		9164 => 'Intet behov',
		9165 => 'Forkert tidspunkt',
		9166 => 'Ingen svar',
		9167 => 'Hvorfor blev »%s« tabt?',
		9168 => 'Valgfrit. Over tid viser det, hvorfor I taber sager.',
		9169 => 'Bemærkning',
		9170 => 'Spring over',
		9171 => 'Markér som tabt',
		9172 => 'Ret %s fra %s',
		9173 => 'Type',
		9174 => 'Med',
		9175 => 'Noten er rettet',
		9176 => 'Slet %s fra %s?',
		9177 => 'Slettet',
		9178 => '%s markeret som gjort',
		9179 => '%s er åben igen',
		9180 => 'Om 2 dage',
		9181 => 'Næste mandag',
		9182 => 'Udsat til %s',
		9183 => 'ingen status',
		9184 => 'Opfølgning om 1 uge',
		9185 => 'Følg op – %s',
		9186 => 'Opfølgning sat til %s',
		9187 => 'Sæt %s som %s',
		9188 => 'vundet sag',
		9190 => 'Gruppenavn',
		9192 => 'Fortrudt',
		9194 => 'Der er ingen medarbejdere under Indstillinger → Stamdata, så opfølgninger kan ikke fordeles.',
		9195 => 'Ny gruppe',
		9197 => '%s flyttet til %s',
		9198 => 'Sag oprettet: »%s«',
		9199 => 'genåbnet',
		9200 => 'Sag »%s«: %s',
		9203 => 'Søg',
		9206 => 'Tilpas CRM',
		9207 => 'Statusgrupper styrer filteret, statuslinjen under Kunder og tavlerne under Sager (én tavle pr. gruppe). Gælder hele firmaet.',
		9208 => '+ Tilføj statusgruppe',
		9209 => 'Fortryd',
		9210 => 'Viser de %s senest aktive kunder. Andre kunder findes med søgningen under »Ny opfølgning« og »Ny sag«.',
		9211 => 'Kunden er måske ikke blandt de %s viste. Søg via »Ny opfølgning«.',
		// 9212 'CRM' is the bell type label (notif_types(), through findtekst)
		9213 => 'Opfølgninger i dag',
		9214 => '%s opfølgninger i dag · %s forsinkede',
		9215 => 'Ny opfølgning til dig',
		9216 => 'Sag tildelt dig',
		9217 => 'Sag vundet',
		9218 => 'Sag tabt',
		9219 => 'fra %s',
		9220 => 'Hvis opfølgninger og kunder',
		9221 => 'Alle opfølgninger og kunder i regnskabet',
		9222 => 'Kun dem, du er ansvarlig for. Valget huskes til næste gang.',
		9223 => 'Mine',
		9231 => 'Statusser',
		9232 => 'Statusgrupper',
		9233 => '+ Tilføj status',
		9234 => 'Ny status',
		9235 => 'Statusnavn',
		9236 => '%s i brug',
		9237 => 'Bruges af %s kunder og sager – kan ikke slettes',
		9238 => 'Slet status',
		9239 => 'Slet gruppe',
		9240 => 'Der er ingen kundestatusser endnu.',
		9241 => 'Giv alle statusser et navn.',
		9242 => 'Statusser og statusgrupper er gemt',
		9243 => 'Ny kunde',
		// 20261024 ASR after the customer-perspective review
		9244 => 'Du er logget ud – genindlæs siden og prøv igen.',
		9245 => 'Genindlæs',
		9246 => 'Du har ikke rettighed til at ændre i CRM.',
		9247 => 'Statussen er i brug og kan ikke slettes.',
		9248 => 'Beløbet kunne ikke læses – skriv fx 12.500,50.',
		9249 => 'Ingen forbindelse – ændringen blev ikke gemt.',
		9250 => 'Kunne ikke gemme. Prøv igen.',
		9251 => '%s opfølgninger fra det gamle CRM er mere end 90 dage forsinkede.',
		9252 => 'Markér dem som gjort',
		9253 => 'Ikke nu',
		9254 => '%s opfølgninger fra det gamle CRM (mere end 90 dage forsinkede) markeret som gjort',
		9255 => 'slettet',
		9256 => 'Slet sagen »%s«?',
		9257 => 'Sagen, dens beløb og koblingen til tilbud eller ordre forsvinder fra CRM. Du kan fortryde fra beskeden nederst.',
		9258 => 'Kunden står ikke på tavlen – sæt status til %s',
		9259 => 'Opsæt statusser først (tandhjulet øverst til højre).',
		9260 => 'Markér %s åbne sager som %s',
		9261 => 'Flyt også sagen til %s',
		9262 => '– vælg –',
		9263 => 'Vælg, hvem opfølgningen er til.',
		9264 => 'Din bruger er ikke knyttet til en medarbejder: »Mine« virker ikke, dine noter får ingen afsender, og du skal selv vælge, hvem en opfølgning er til. Bed en administrator knytte brugeren under Indstillinger → Brugere.',
		9265 => 'Beløb (kr, ekskl. moms)',
		9266 => 'ekskl. moms',
		9267 => 'Søg blandt alle kunder',
		9268 => 'Fundet uden for listen',
		9269 => 'Markér sagen som %s',
		9270 => 'Mandag',
		9271 => 'Sag »%s« slettet',
		9272 => 'Genåbn',
		9273 => '%s kunder har en status, der ikke er med i en statusgruppe, og vises derfor ikke i CRM.',
		9274 => 'Ansvarlig: %s → %s',
		9275 => 'Der er ingen noter eller opkald endnu.',
		9276 => 'Du kan se CRM, men ændringer kræver skriveadgang til Debitor → Konti.',
		9277 => 'Faktureret – markér vundet?',
		9278 => 'Flyt %s åbne opfølgninger til %s',
		9279 => 'Flyt ned',
		9280 => 'Flyt op',
		9281 => 'Forhandling',
		9282 => 'Forslag: en salgstavle med Henvendelse › Tilbud sendt › Forhandling, hvor Vundet giver status Kunde og Tabt giver status Tabt.',
		9283 => 'Henvendelse',
		9284 => 'Hver gruppe bliver en tavle under Sager. Vundet → og Tabt → er den status, kunden får, når kortet trækkes til Vundet eller Tabt.',
		9285 => 'Hvilken sag gælder ordren?',
		9286 => 'Hvilken sag gælder tilbuddet?',
		9287 => 'Ingen sag',
		9288 => 'Ingen kontakt endnu',
		9289 => 'Ingen opfølgninger matcher »%s«.',
		9290 => 'Ingen statusgrupper endnu.',
		9291 => 'Knyt til sag',
		9292 => 'Knyttet til sagen »%s«',
		9293 => 'Luk',
		9294 => 'Opfølgningerne er flyttet',
		9295 => 'Opsæt statusgrupper',
		9296 => 'Opsæt statusser og statusgrupper under tandhjulet.',
		9297 => 'Salg',
		9298 => 'Salgstavlen er oprettet',
		9299 => 'Seneste kontakt %s',
		9300 => 'Skjul %s kunder uden opfølgning',
		9301 => 'Start med en salgstavle',
		9302 => 'Statusserne er de samme som feltet Status på debitorkortet. Rækkefølgen her er rækkefølgen på tavlen.',
		9303 => 'Til',
		9304 => 'Tilbud sendt',
		9305 => 'Tøm feltet for at fjerne påmindelsen – noten bliver stående',
		9306 => 'Vis %s kunder uden opfølgning',
		9307 => 'Vis alle',
		9308 => 'Viser de 10 nyeste.',
		9309 => 'ingen',
		9310 => 'Åbn tandhjulet',
		9311 => 'Vis 1 kunde uden opfølgning',
		9312 => 'Skjul 1 kunde uden opfølgning',
		9313 => 'Flyt opfølgningen til %s',
		9314 => '1 kunde har en status, der ikke er med i en statusgruppe, og vises derfor ikke i CRM.',
		9315 => 'Din opfølgning er flyttet til %s',
		9316 => 'Kunder kommer med i CRM, når de har en status i en statusgruppe, en note eller en sag. Søg efter andre kunder med søgefeltet.',
		9318 => 'lukket',
		9319 => '(lukket kontakt)',
		9320 => '(tidligere medarbejder)',
		9321 => 'Indstillingerne er ændret af en anden. Genindlæs siden.',
		9322 => 'Gem eller annullér ændringerne i tandhjulet først.',
		9323 => '%s (Saldi)',
		9324 => '%s har et møde %s–%s (%s)',
		9325 => '%s har inviteret dig til et møde med %s.',
		9326 => '%s invitationer kunne ikke sendes – tjek mailopsætningen under Indstillinger',
		9327 => '%s invitationer sendt',
		9328 => '%s min',
		9329 => '%s t',
		9330 => '1 invitation sendt',
		9331 => 'Abonnement',
		9332 => 'Abonnér',
		9333 => 'Aflyst: møde med %s %s',
		9334 => 'Andre kalendere → + → Fra webadresse, indsæt linket.',
		9335 => 'Arkiv → Nyt kalenderabonnement, indsæt linket.',
		9336 => 'Book møde',
		9337 => 'Brug %s, hvor alle er ledige',
		9338 => 'Deltagere: %s',
		9339 => 'Din bruger er ikke knyttet til en medarbejder, så der er ingen møder at vise. Bed en administrator knytte brugeren under Indstillinger → Brugere.',
		9340 => 'Dit kalenderlink',
		9341 => 'Du er inviteret til et møde',
		9342 => 'Du er sat på sagen »%s«',
		9343 => 'Du har et møde %s–%s (%s)',
		9344 => 'Emne',
		9345 => 'Fra',
		9346 => 'Fx gennemgang af tilbuddet',
		9347 => 'Hvilken kunde er mødet med?',
		9348 => 'Ingen andre medarbejdere',
		9349 => 'Ingen ledig tid senere samme dag for alle.',
		9350 => 'Invitation: møde med %s %s',
		9351 => 'Kalenderabonnement slået fra',
		9352 => 'Kalenderabonnement: nyt link',
		9353 => 'Kolleger',
		9354 => 'Kolleger på sagen',
		9355 => 'Kontaktperson: %s',
		9356 => 'Kopiér',
		9357 => 'Lav mit link',
		9358 => 'Lav nyt link',
		9359 => 'Linket er kopieret',
		9360 => 'Linket er personligt: den, der har det, kan se dine møder. Er det kommet ud, så lav et nyt – det gamle holder op med at virke.',
		9361 => 'Med et personligt link kan Google Kalender, Outlook eller Apple Kalender vise dine møder fra CRM – dem, du er ansvarlig for eller deltager i. Kalenderen henter selv ændringerne; Google kan være op til et døgn om det.',
		9362 => 'Møde %s kl. %s',
		9363 => 'Møde %s · gjort',
		9364 => 'Møde booket %s kl. %s',
		9365 => 'Møde med %s',
		9366 => 'Mødet er flyttet til %s',
		9367 => 'Mødet er gemt',
		9368 => 'Mødet er tilbage',
		9369 => 'Mødet med %s %s er aflyst.',
		9370 => 'Nyt møde',
		9371 => 'Nyt møde %s',
		9372 => 'Næste møde',
		9373 => 'Optaget på det tidspunkt',
		9374 => 'Ret møde',
		9375 => 'Saldi CRM – %s',
		9376 => 'Se dine møder i Google Kalender, Outlook eller Apple Kalender',
		9377 => 'Se dine møder i din egen kalender',
		9378 => 'Send aflysning til dem, der fik en invitation',
		9379 => 'Send kalenderinvitation til deltagerne på mail',
		9380 => 'Send også invitation til %s (%s)',
		9381 => 'Send ændringen til deltagerne',
		9382 => 'Send ændringen til deltagerne på mail',
		9383 => 'Slet møde',
		9384 => 'Slet mødet %s kl. %s?',
		9385 => 'Slå fra',
		9386 => 'Tilføj kalender → Abonner fra nettet, indsæt linket.',
		9387 => 'Uden mailadresse: %s',
		9388 => 'Varighed',
		9389 => 'Vælg, hvem der er ansvarlig for mødet.',
		9390 => 'aflysning sendt',
		9391 => 'invitation sendt',
		9392 => 'Åbn direkte',
		9393 => 'Ændringen er sendt',
		9394 => 'Adresse',
		9395 => 'Adresse 2',
		9396 => 'By',
		9397 => 'CVR-nummer',
		9398 => 'CVR-nummeret findes allerede på en kunde. Find kunden via søgningen i CRM.',
		9399 => 'Debitorgruppe',
		9400 => 'Der er ingen debitorgrupper. Opret en under Indstillinger, og prøv igen.',
		9401 => 'Dette lead er allerede oprettet som debitor. Genindlæs siden for at se debitorkortet.',
		9402 => 'Du har ikke rettighed til at oprette tilbud og ordrer.',
		9403 => 'E-mail',
		9404 => 'Et felt indeholder for mange tegn. Forkort teksten og prøv igen.',
		9405 => 'Forlad uden at gemme ændringerne?',
		9406 => 'Gruppen bestemmer blandt andet kundens moms.',
		9407 => 'Kunden eller sagen er ændret. Genindlæs siden og prøv igen.',
		9408 => 'Kunden gemmes som lead i CRM. Den oprettes først som debitor, når du opretter det første tilbud eller den første ordre.',
		9409 => 'Land',
		9410 => 'Lead',
		9411 => 'Lead gemt',
		9412 => 'Lead oprettet',
		9413 => 'Lead · ikke oprettet som debitor',
		9414 => 'Navn',
		9415 => 'Opret debitor og ordre',
		9416 => 'Opret debitor og tilbud',
		9417 => 'Opret et lead med »Ny kunde« under Kunder.',
		9418 => 'Opret lead',
		9419 => 'Oprettet',
		9420 => 'Postnummer',
		9421 => 'Ret lead',
		9422 => 'Sag: %s',
		9423 => 'Sagen har allerede et tilbud eller en ordre. Genindlæs siden for at åbne det.',
		9424 => 'Skriv en gyldig e-mailadresse.',
		9425 => 'Skriv et navn på kunden.',
		9426 => 'Telefon',
		9427 => 'Vælg en debitorgruppe. Hvis listen er tom, skal en debitorgruppe oprettes under Indstillinger.',
		9428 => '»%s« er et lead. Når du fortsætter, oprettes kunden automatisk i debitorkartoteket. Historik og sager bevares.',
		9429 => 'Oprettet som debitor %s',
		9430 => 'Opret ny kunde',
		9431 => 'Vælg en eksisterende kunde, eller opret en ny.',
		9432 => 'Prøv en anden søgning.',
		9433 => 'Opret og fortsæt',
		9434 => 'CRM-opdateringen er ikke klar. Log ud og ind igen.',
		9435 => 'Tilbuddet/ordren er oprettet, men kunne ikke knyttes til sagen. Genindlæs CRM for at kontrollere sagen.',
		9436 => 'CRM-notifikationer',
		9437 => 'Ingen notifikationer',
		9438 => 'Markér alle som læst',
		9439 => 'Ulæst',
		9440 => '%s ulæste notifikationer',
		9441 => 'Du har ikke rettighed til at se CRM.',
	);
}

function crm_lang(): int
{
	global $sprog_id;
	$l = (int) $sprog_id;
	return ($l >= 1 && $l <= 3) ? $l : 1;
}

/**
 * Danish text => text in the user's language, read once per session (one query + one pass over tekster.csv instead
 * of a findtekst() call per text).
 *
 * @return array<string, string>
 */
function crm_texts(?int $lang = null): array
{
	static $cache = array();
	$lang = ($lang !== null && $lang >= 1 && $lang <= 3) ? $lang : crm_lang();
	if (isset($cache[$lang])) {
		return $cache[$lang];
	}
	$ids = crm_text_ids();
	// The Danish texts are part of the key, so a reworded text is picked up without logging in again.
	$cacheKey = 'crm_txt_' . $lang . '_' . md5(implode("\n", $ids));
	if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
		return $cache[$lang] = $_SESSION[$cacheKey];
	}
	$have = array();
	if (function_exists('tbl_exists') && tbl_exists('tekster')) {
		$list = "'" . implode("','", array_keys($ids)) . "'";
		$q = db_select("select tekst_id, tekst from tekster where sprog_id = '$lang' and tekst_id in ($list)", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$txt = trim((string) $r['tekst']);
			if ($txt !== '' && $txt !== '-' && strpos($txt, 'Tekst nr:') !== 0) {
				$have[(int) $r['tekst_id']] = crm_out($txt);
			}
		}
	}
	$file = __DIR__ . '/../importfiler/tekster.csv';
	if (count($have) < count($ids) && is_file($file)) {
		foreach (file($file) as $line) {
			$a = explode("\t", rtrim($line, "\r\n"));
			$id = (int) $a[0];
			if (isset($ids[$id]) && !isset($have[$id]) && isset($a[$lang]) && $a[$lang] !== '' && $a[$lang] !== '-') {
				$have[$id] = $a[$lang];
			}
		}
	}
	$out = array();
	foreach ($ids as $id => $da) {
		$out[$da] = isset($have[$id]) ? $have[$id] : $da;
	}
	$_SESSION[$cacheKey] = $out;
	return $cache[$lang] = $out;
}

/** Text in the user's language (UTF-8), '%s' filled in order. */
function crm_t(string $da, ...$args): string
{
	return crm_tl(crm_lang(), $da, ...$args);
}

/** crm_t() in a given language - the recipient's, for notifications written for someone else. */
function crm_tl(int $lang, string $da, ...$args): string
{
	$texts = crm_texts($lang);
	$s = isset($texts[$da]) ? $texts[$da] : $da;
	foreach ($args as $arg) {
		$p = strpos($s, '%s');
		if ($p === false) {
			break;
		}
		$s = substr_replace($s, (string) $arg, $p, 2);
	}
	return $s;
}

/** crm_t() for HTML: escaped, non-ASCII as numeric entities so it is right whatever charset the page has. */
function crm_h(string $da): string
{
	return mb_encode_numericentity(htmlspecialchars(crm_t($da), ENT_QUOTES, 'UTF-8'), array(0x80, 0x10FFFF, 0, 0x10FFFF), 'UTF-8');
}

// ------------------------------------------------------------------ CRM notifications
// Assignment events and the daily follow-up summary appear inside CRM. They use dedicated tenant tables,
// independent of a global notification center, and only reach users who may read CRM.

/** UTF-8 to the company's charset, unescaped; storage helpers escape SQL values. */
function crm_to_db(string $s): string
{
	global $db_encode;
	return ($db_encode === 'UTF8' || $s === '') ? $s : mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
}

function crm_user_for_ansat(int $ansatId): int
{
	if ($ansatId <= 0) {
		return 0;
	}
	$r = db_fetch_array(db_select("select id from brugere where ansat_id = $ansatId order by id limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (int) $r['id'] : 0;
}

function crm_ansat_for_user(int $brugerId): int
{
	if ($brugerId <= 0) {
		return 0;
	}
	$r = db_fetch_array(db_select("select ansat_id from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
	return $r ? (int) $r['ansat_id'] : 0;
}

/** The acting user's name for a notification body (UTF-8). */
function crm_actor_name(): string
{
	global $ansat_navn, $brugernavn_raw, $brugernavn;
	$n = trim((string) $ansat_navn);
	if ($n === '') {
		$n = trim((string) (isset($brugernavn_raw) ? $brugernavn_raw : $brugernavn));
	}
	return crm_out($n);
}

/**
 * A CRM notification for the user linked to employee $ansatId, excluding the acting user and users without access.
 * $key identifies the event; refreshing it retains the recipient's read state.
 */
function crm_notify(int $ansatId, string $key, string $title, string $body, string $link, ?string $expires): void
{
	global $bruger_id;
	if (!crm_notifications_ready()) {
		return;
	}
	$user = crm_user_for_ansat($ansatId);
	if ($user <= 0 || $user === (int) $bruger_id) {
		return;
	}
	// Recipient permissions must come from that user's rights, never the acting user's session.
	$u = db_fetch_array(db_select("select rettigheder from brugere where id = $user", __FILE__ . " linje " . __LINE__));
	if (!$u || !crm_legacy_access((string) $u['rettigheder'], 6)) {
		return;
	}
	crm_notification_upsert($key, $user, $title, $body, $link, $expires);
}

/** Remove the notifications of an event that is over (follow-up done or deleted, deal deleted or reopened). */
function crm_notif_drop(string $keyLike): void
{
	if (!crm_notifications_ready()) {
		return;
	}
	$k = db_escape_string($keyLike);
	db_modify("delete from crm_notifications where event_key like '$k'", __FILE__ . " linje " . __LINE__);
}

/** "Ny opfølgning til dig": a follow-up whose owner is another employee than the acting user. */
function crm_notify_followup(int $hid, int $cid, int $owner, string $due, string $note, bool $moved = false): void
{
	if ($moved) {
		// review R64: a postponed follow-up is a change, not a new one, and the owner should see it again
		$u = crm_user_for_ansat($owner);
		crm_notification_unread('crm:fu:' . $hid, $u);
	}
	$c = db_fetch_array(db_select("select firmanavn from adresser where id = $cid", __FILE__ . " linje " . __LINE__));
	$body = crm_out($c ? (string) $c['firmanavn'] : '') . ': ' . mb_substr(trim($note), 0, 120)
		. ' · ' . crm_t('opfølgning %s', date('d-m-Y', strtotime($due))) . ' · ' . crm_t('fra %s', crm_actor_name());
	crm_notify($owner, 'crm:fu:' . $hid, $moved ? crm_t('Din opfølgning er flyttet til %s', date('d-m-Y', strtotime($due))) : crm_t('Ny opfølgning til dig'), $body, '/debitor/crm.php?tab=follow&drawer=' . $cid, date('Y-m-d', strtotime($due . ' +30 days')));
}

/** "Sag tildelt dig" / "Sag vundet" / "Sag tabt" for the deal's owner. */
function crm_notify_deal(array $d, string $what): void
{
	$id = (int) $d['id'];
	$cid = (int) $d['konto_id'];
	$c = db_fetch_array(db_select("select firmanavn from adresser where id = $cid", __FILE__ . " linje " . __LINE__));
	$amount = round((float) $d['amount']);
	$body = crm_out((string) $d['title']) . ' · ' . crm_out($c ? (string) $c['firmanavn'] : '')
		. ($amount ? ' · ' . crm_t('%s kr', number_format($amount, 0, ',', '.')) : '') . ' · ' . crm_t('fra %s', crm_actor_name());
	$title = $what === 'won' ? crm_t('Sag vundet') : ($what === 'lost' ? crm_t('Sag tabt') : crm_t('Sag tildelt dig'));
	crm_notify((int) $d['ansat_id'], "crm:deal:$id:$what", $title, $body, '/debitor/crm.php?tab=pipe&drawer=' . $cid . '&dtab=deals', date('Y-m-d', strtotime('+30 days')));
}

/**
 * The daily "Opfølgninger i dag" item when a user loads CRM. One item per day
 * (key crm:due:<date>), expired the day after; none when nothing is due. Needs the Debitor -> Konti right.
 */
function crm_notif_refresh(int $brugerId, string $rettigheder, int $sprogId): void
{
	if ($brugerId <= 0 || !crm_legacy_access($rettigheder, 6) || !crm_notifications_ready()) {
		return;
	}
	$ansat = crm_ansat_for_user($brugerId);
	if ($ansat <= 0) {
		return;
	}
	$today = date('Y-m-d');
	$key = 'crm:due:' . $today;
	// the same open rule as the page (crm_open_sql), closed customers left out as on the page; "today" without the overdue ones (review R63)
	$r = db_fetch_array(db_select("select count(*) as n, sum(case when h.kontaktes < '$today' then 1 else 0 end) as late from historik h "
		. "join adresser a on a.id = h.konto_id and (a.lukket is null or a.lukket != 'on') "
		. "where (h.ansat_id = $ansat" . (tbl_exists('crm_participants') ? " or exists (select 1 from crm_participants p where p.kind = 'h' and p.ref_id = h.id and p.ansat_id = $ansat)" : '')
		. ") and h.kontaktes <= '$today' and " . crm_open_sql('h'), __FILE__ . " linje " . __LINE__));
	$n = $r ? (int) $r['n'] : 0;
	if ($n <= 0) {
		db_modify("delete from crm_notifications where event_key = '$key' and bruger_id = $brugerId", __FILE__ . " linje " . __LINE__);
		return;
	}
	$late = (int) $r['late'];
	crm_notification_upsert($key, $brugerId, crm_tl($sprogId, 'Opfølgninger i dag'),
		crm_tl($sprogId, '%s opfølgninger i dag · %s forsinkede', $n - $late, $late), '/debitor/crm.php?tab=follow', $today);
}

// ------------------------------------------------------------------ reference data

function crm_statuses(bool $withUse = false): array
{
	$out = array();
	$r = db_fetch_array(db_select("select box3, box4 from grupper where art = 'DebInfo'", __FILE__ . " linje " . __LINE__));
	if ($r) {
		$ids = explode(chr(9), (string) $r['box3']);
		$names = explode(chr(9), (string) $r['box4']);
		foreach ($ids as $i => $id) {
			$id = trim($id);
			if ($id !== '') {
				$out[] = array('id' => $id, 'n' => crm_out(isset($names[$i]) ? trim($names[$i]) : $id));
			}
		}
	}
	if ($withUse && $out) {
		// customers with the status + open deals in it: such a status cannot be deleted from the gear panel
		$use = crm_status_use();
		foreach ($out as $k => $s) {
			$out[$k]['used'] = isset($use[$s['id']]) ? $use[$s['id']] : 0;
		}
	}
	return $out;
}

/** @return array<string, int> status id => customers having it + open deals in it */
function crm_status_use(): array
{
	$use = array();
	$q = db_select("select status, count(*) as n from adresser where art in ('D', 'L') and status is not null and status != '' group by status", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$use[trim((string) $r['status'])] = (int) $r['n'];
	}
	if (tbl_exists('crm_deals')) {
		$q = db_select("select stage, count(*) as n from crm_deals where state = 'open' group by stage", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$k = trim((string) $r['stage']);
			$use[$k] = (isset($use[$k]) ? $use[$k] : 0) + (int) $r['n'];
		}
	}
	return $use;
}

/**
 * Save the gear panel's statuses (grupper DebInfo box3 ids / box4 names, the list debitorkort.php also edits).
 * New ones arrive as 'new:<n>' and get the next free number. A status in use is never removed.
 *
 * @return array<string, string>|string map 'new:<n>' => id, or an error code
 */
function crm_save_statuses(array $list)
{
	$old = array();
	foreach (crm_statuses() as $s) {
		$old[$s['id']] = $s['n'];
	}
	$max = function_exists('get_settings_value') ? (int) get_settings_value('crm_status_max', 'crm', 0) : 0;   // review R49: numbers are never reused
	foreach (array_keys($old) as $id) {
		if (ctype_digit((string) $id)) $max = max($max, (int) $id);
	}
	$ids = $names = $map = array();
	foreach ($list as $s) {
		$s = (array) $s;
		$id = (string) ($s['id'] ?? '');
		$name = trim(str_replace(array(chr(9), "\r", "\n"), ' ', (string) ($s['n'] ?? '')));
		if ($name === '') return 'status_name';
		if (strpos($id, 'new:') === 0) {
			$map[$id] = (string) (++$max);
			$id = $map[$id];
		} elseif (!isset($old[$id])) {
			continue;
		}
		$ids[] = $id;
		$names[] = crm_in_raw(mb_substr($name, 0, 60));
	}
	$use = crm_status_use();
	foreach (array_keys($old) as $id) {
		if (!in_array((string) $id, $ids, true) && !empty($use[$id])) return 'status_used';
	}
	if (function_exists('update_settings_value')) update_settings_value('crm_status_max', 'crm', (string) $max, 'CRM: highest status number given out (never reused)');
	$box3 = db_escape_string(implode(chr(9), $ids));
	$box4 = db_escape_string(implode(chr(9), $names));
	if (db_fetch_array(db_select("select id from grupper where art = 'DebInfo'", __FILE__ . " linje " . __LINE__))) {
		db_modify("update grupper set box3 = '$box3', box4 = '$box4' where art = 'DebInfo'", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into grupper (art, beskrivelse, kodenr, box3, box4) values ('DebInfo', 'Debitorinfo', '1', '$box3', '$box4')", __FILE__ . " linje " . __LINE__);
	}
	return $map;
}

function crm_groups(): array
{
	$out = array();
	$q = db_select("select id, box1, box2, box3, box4 from grupper where art = 'SKN' order by kodenr, id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[] = array(
			'id'   => (int) $r['id'],
			'n'    => crm_out($r['box1']),
			's'    => array_values(array_filter(array_map('trim', explode(chr(9), (string) $r['box2'])), 'strlen')),
			'won'  => trim((string) $r['box3']),
			'lost' => trim((string) $r['box4']),
		);
	}
	return $out;
}

function crm_own_id(): int
{
	$r = db_fetch_array(db_select("select id from adresser where art = 'S'", __FILE__ . " linje " . __LINE__));
	return $r ? (int) $r['id'] : 0;
}

function crm_employees(): array
{
	$out = array();
	$own = crm_own_id();
	$q = db_select("select id, navn, initialer from ansatte where konto_id = $own and (lukket is null or lukket != 'on') order by navn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		// m: the employee has a mail address a calendar invitation can go to (the address itself stays on the server)
		$out[] = array('id' => (int) $r['id'], 'n' => crm_out($r['navn']), 'i' => crm_out($r['initialer']), 'm' => crm_emp_mail((int) $r['id']) !== '');
	}
	return $out;
}

/** An employee's mail address: the employee's own, else the address of the user linked to the employee. */
function crm_emp_mail(int $ansatId): string
{
	static $cache = array();
	if ($ansatId <= 0) {
		return '';
	}
	if (!isset($cache[$ansatId])) {
		$r = db_fetch_array(db_select("select email from ansatte where id = $ansatId", __FILE__ . " linje " . __LINE__));
		$m = $r ? trim((string) $r['email']) : '';
		if (!filter_var($m, FILTER_VALIDATE_EMAIL)) {
			$u = db_fetch_array(db_select("select email from brugere where ansat_id = $ansatId and email is not null and email != '' order by id limit 1", __FILE__ . " linje " . __LINE__));
			$m = $u ? trim((string) $u['email']) : '';
		}
		$cache[$ansatId] = filter_var($m, FILTER_VALIDATE_EMAIL) ? $m : '';
	}
	return $cache[$ansatId];
}

/** A fingerprint of the statuses and status groups: a save made from an older view of them is refused (review R50). */
function crm_settings_rev(): string
{
	return substr(md5(json_encode(crm_statuses()) . json_encode(crm_groups())), 0, 12);
}

function crm_status_zero(): bool
{
	static $z = null;
	if ($z === null) {
		$z = false;
		foreach (crm_statuses() as $s) {
			if ($s['id'] === '0') $z = true;
		}
	}
	return $z;
}

function crm_employee_name(int $id): string
{
	$r = db_fetch_array(db_select("select navn from ansatte where id = $id", __FILE__ . " linje " . __LINE__));
	return $r ? crm_out($r['navn']) : '';
}

// ------------------------------------------------------------------ customers

/**
 * @param string $where extra condition on adresser a
 * @return array<int, array<string, mixed>> keyed by id
 */
function crm_customers(string $where, int $limit = 0, bool $byActivity = false): array
{
	$out = array();
	// When the cap applies (review R53): customers with open work first (an open follow-up or an open deal), then the
	// most recently active by entry OR order/invoice, then by name. Otherwise by name.
	$join = '';
	$order = "a.firmanavn";
	if ($byActivity) {
		$join = " left join (select konto_id, max(kontaktet) as d from historik group by konto_id) hx on hx.konto_id = a.id"
			. " left join (select konto_id, max(ordredate) as d from ordrer where art in ('DO', 'DK') group by konto_id) ox on ox.konto_id = a.id"
			. " left join (select distinct h.konto_id from historik h where " . crm_open_sql('h') . ") fx on fx.konto_id = a.id"
			. " left join (select distinct konto_id from crm_deals where state = 'open') dx on dx.konto_id = a.id";
		$order = "case when fx.konto_id is not null or dx.konto_id is not null then 0 else 1 end, "
			. "greatest(coalesce(hx.d, '1900-01-01'), coalesce(ox.d, '1900-01-01')) desc, a.firmanavn";
	}
	$qtxt = "select a.id, a.kontonr, a.firmanavn, a.bynavn, a.tlf, a.email, a.status, a.kontoansvarlig, a.oprettet, a.lukket, a.art, a.addr1, a.addr2, a.postnr, a.land, a.cvrnr "
		. "from adresser a$join where a.art in ('D', 'L') and $where order by $order" . ($limit > 0 ? " limit $limit" : "");
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['id']] = array(
			'id'    => (int) $r['id'],
			'nr'    => crm_out($r['kontonr']),
			'lead'  => $r['art'] === 'L',
			'addr1' => crm_out($r['addr1']), 'addr2' => crm_out($r['addr2']),
			'zip'   => crm_out($r['postnr']), 'land' => crm_out($r['land']), 'cvr' => crm_out($r['cvrnr']),
			'n'     => crm_out($r['firmanavn']),
			'by'    => crm_out($r['bynavn']),
			'tlf'   => crm_out($r['tlf']),
			'mail'  => crm_out($r['email']),
			'st'    => (trim((string) $r['status']) === '0' && !crm_status_zero()) ? '' : trim((string) $r['status']),
			'own'   => (int) $r['kontoansvarlig'],
			'since' => (string) $r['oprettet'],
			'closed' => trim((string) $r['lukket']) === 'on',
			'rev'   => 0, 'inv' => 0, 'late' => 0,
			'stSince' => (string) $r['oprettet'],
			'ppl'   => array(),
			'docs'  => array(),
		);
	}
	if (!$out) {
		return $out;
	}
	$ids = crm_ids(array_keys($out));
	$q = db_select("select id, konto_id, navn, tlf, mobil, email from ansatte where konto_id in ($ids) and (lukket is null or lukket != 'on') order by posnr, navn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['konto_id']]['ppl'][] = array('id' => (int) $r['id'], 'n' => crm_out($r['navn']), 'tlf' => crm_out($r['mobil'] ?: $r['tlf']), 'mail' => crm_out($r['email']));
	}
	$year = date('Y') . '-01-01';
	// revenue this year in the company's currency (ordrer.sum is in the document's currency; valutakurs is per 100), excl. VAT (review R42)
	$q = db_select("select konto_id, sum(sum * coalesce(nullif(valutakurs, 0), 100) / 100) as s from ordrer where art in ('DO', 'DK') and status >= 3 and fakturadate >= '$year' and konto_id in ($ids) group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['konto_id']]['rev'] = round((float) $r['s']);
	}
	$today = date('Y-m-d');
	// review R43: the balance of the unmatched items (invoices minus payments/credit notes, in the company's currency) and how many invoices are overdue
	$q = db_select("select konto_id, sum(case when amount > 0 then 1 else 0 end) as n, sum(case when amount > 0 and forfaldsdate < '$today' then 1 else 0 end) as late, "
		. "sum(amount * coalesce(nullif(valutakurs, 0), 100) / 100) as bal from openpost where konto_id in ($ids) and (udlignet is null or udlignet != '1') group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['konto_id']]['inv'] = (int) $r['n'];
		$out[(int) $r['konto_id']]['late'] = (int) $r['late'];
		$out[(int) $r['konto_id']]['bal'] = round((float) $r['bal'], 2);
	}
	// open offers (status 0) and orders (1-2), newest first, for the drawer
	$q = db_select("select id, konto_id, ordrenr, status, sum * coalesce(nullif(valutakurs, 0), 100) / 100 as sum, ordredate from ordrer where art = 'DO' and status < 3 and konto_id in ($ids) order by id desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (count($out[(int) $r['konto_id']]['docs']) < 10) {
			$out[(int) $r['konto_id']]['docs'][] = array('id' => (int) $r['id'], 'nr' => (int) $r['ordrenr'], 'st' => (int) $r['status'], 'sum' => round((float) $r['sum'], 2), 'date' => (string) $r['ordredate']);
		}
	}
	// last real contact over the whole history, not only the loaded window (review R55)
	$q = db_select("select konto_id, max(kontaktet) as d from historik where konto_id in ($ids) and (type is null or type not in ('status', 'deal')) group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['konto_id']]['last'] = (string) $r['d'];
	}
	$q = db_select("select konto_id, max(kontaktet) as d from historik where type = 'status' and konto_id in ($ids) group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['d']) {
			$out[(int) $r['konto_id']]['stSince'] = (string) $r['d'];
		}
	}
	return $out;
}

/**
 * Replace the CRM's own entry at the top of the session's navigation stack (includes/stdFunc/navStack.php) with the
 * page's current place. The stack only records page requests, and the CRM changes tab and drawer without one, so
 * without this the shell's came-from chip on an order leads to the CRM start instead of the customer.
 */
function crm_nav_place(string $query): bool
{
	if (!function_exists('_nav_read') || !function_exists('_nav_write')) {
		return false;
	}
	parse_str($query, $in);
	$clean = array();
	foreach (array('inframe', 'popup', 'tab', 'bgrp', 'drawer', 'dtab', 'grp', 'cal') as $k) {
		if (isset($in[$k]) && is_string($in[$k]) && preg_match('/^[A-Za-z0-9_-]{1,20}$/', $in[$k])) {
			$clean[$k] = $in[$k];
		}
	}
	$stack = _nav_read();
	if (!$stack) {
		return false;
	}
	$top = (string) end($stack);
	$path = (string) parse_url($top, PHP_URL_PATH);
	if (basename($path) !== 'crm.php') {
		return false;
	}
	$stack[count($stack) - 1] = $path . ($clean ? '?' . http_build_query($clean) : '');
	_nav_write($stack);
	return true;
}

/** The customers the CRM shows: with a status, an entry, a deal or a next-contact date. */
if (!defined('CRM_MAX_CUSTOMERS')) {
	define('CRM_MAX_CUSTOMERS', 1000);
}

/** The customers the CRM shows: a status in a status group, an entry, an open deal or a next-contact date. */
function crm_customer_scope(): string
{
	$inGroups = array();
	foreach (crm_groups() as $g) {
		foreach ($g['s'] as $id) {
			$inGroups[] = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $id);
		}
	}
	$inGroups = array_values(array_unique(array_filter($inGroups, 'strlen')));
	return "(a.lukket is null or a.lukket != 'on') and ("
		. "a.art = 'L' or "
		. ($inGroups ? "a.status in ('" . implode("','", $inGroups) . "') or " : '')
		. "a.kontaktes is not null or exists (select 1 from historik h where h.konto_id = a.id) "
		. "or exists (select 1 from crm_deals d where d.konto_id = a.id and d.state = 'open'))";
}

function crm_search(string $term): array
{
	$t = crm_in($term);
	$where = "(a.lukket is null or a.lukket != 'on')";
	if ($t !== '') {
		$where .= " and (a.firmanavn like '%$t%' or a.kontonr like '$t%' or a.id in (select konto_id from ansatte where navn like '%$t%'))";
	}
	return array_values(crm_customers($where, 10));
}

// ------------------------------------------------------------------ entries and deals

/** The window the page loads history in; the drawer fetches the rest for one customer (action 'customer', review R55). */
function crm_hist_from(): string
{
	return date('Y-m-d', strtotime('-2 years'));
}

function crm_history(array $custIds, bool $all = false): array
{
	$out = array();
	if (!$custIds) {
		return $out;
	}
	$ids = crm_ids($custIds);
	$from = crm_hist_from();
	$window = $all ? '' : "and (kontaktet >= '$from' or (kontaktes is not null and completed is null) or completed = FALSE) ";
	$q = db_select("select id, konto_id, kontakt_id, ansat_id, author_id, notat, kontaktet, kontaktes, completed, completed_at, type, dokument from historik "
		. "where konto_id in ($ids) $window"
		. "order by konto_id, kontaktet, id", __FILE__ . " linje " . __LINE__);
	$rows = array();
	while ($r = db_fetch_array($q)) {
		$rows[] = $r;
	}
	// newest dated row per customer, for the legacy rule (crm_open_sql) - over all rows, not only the ones loaded above
	$latest = array();
	$q = db_select("select konto_id, max(id) as id from historik where konto_id in ($ids) and kontaktes is not null group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$latest[(int) $r['konto_id']] = (int) $r['id'];
	}
	foreach ($rows as $r) {
		$type = trim((string) $r['type']);
		$due = $r['kontaktes'] ? (string) $r['kontaktes'] : null;
		if ($r['completed'] === null || $r['completed'] === '') {
			$done = $due !== null && isset($latest[(int) $r['konto_id']]) && $latest[(int) $r['konto_id']] !== (int) $r['id'];
		} else {
			$done = crm_bool($r['completed']);
		}
		$out[] = array(
			'id'   => (int) $r['id'],
			'cid'  => (int) $r['konto_id'],
			'type' => $type !== '' ? $type : 'note',
			'by'   => (int) $r['ansat_id'],
			'au'   => (int) $r['author_id'] ?: (int) $r['ansat_id'],
			'to'   => (int) $r['kontakt_id'],
			'date' => (string) $r['kontaktet'],
			'note' => crm_out($r['notat']),
			'due'  => $due,
			'done' => $done,
			'doneAt' => (string) $r['completed_at'],
			'file' => crm_out($r['dokument']),
		);
	}
	// meetings: time, length, colleagues and whether invitations went out (tm/dur/with/inv only on those rows)
	$mt = crm_meetings_for(array_map(function ($h) { return $h['id']; }, $out));
	foreach ($out as $k => $h) {
		if (isset($mt[$h['id']])) {
			$out[$k] += $mt[$h['id']];
		}
	}
	return $out;
}

/**
 * @param int[] $hids historik ids
 * @return array<int, array{tm: string, dur: int, with: int[], inv: int, seq: int}> keyed by historik id
 */
function crm_meetings_for(array $hids): array
{
	$out = array();
	if (!$hids || !tbl_exists('crm_meetings')) {
		return $out;
	}
	foreach (array_chunk($hids, 2000) as $chunk) {
		$ids = crm_ids($chunk);
		$q = db_select("select historik_id, start_time, duration, seq, invite_team, invite_contact from crm_meetings where historik_id in ($ids)", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$out[(int) $r['historik_id']] = array('tm' => (string) $r['start_time'], 'dur' => (int) $r['duration'], 'with' => array(),
				'inv' => ((int) $r['invite_team'] ? 1 : 0) + ((int) $r['invite_contact'] ? 2 : 0), 'seq' => (int) $r['seq']);
		}
		if ($out) {
			$q = db_select("select ref_id, ansat_id from crm_participants where kind = 'h' and ref_id in ($ids) order by id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (isset($out[(int) $r['ref_id']])) {
					$out[(int) $r['ref_id']]['with'][] = (int) $r['ansat_id'];
				}
			}
		}
	}
	return $out;
}

function crm_deal_from(): string
{
	return date('Y-m-d', strtotime('-1 year'));
}

/** $cid > 0: all deals of that customer, however old (the drawer, review R55). */
function crm_deals(int $cid = 0): array
{
	$out = array();
	$from = crm_deal_from();
	$which = $cid > 0 ? "d.konto_id = $cid and d.state in ('open', 'won', 'lost')" : "(d.state = 'open' or (d.state in ('won', 'lost') and d.closed >= '$from'))";
	// deleted deals (state 'deleted', review R30) are kept for undo but never shown; document sums in the company's currency
	$q = db_select("select d.*, o.ordrenr as doc_nr, o.fakturanr as doc_fnr, o.status as doc_status, o.sum * coalesce(nullif(o.valutakurs, 0), 100) / 100 as doc_sum, o.art as doc_art from crm_deals d "
		. "left join ordrer o on o.id = d.ordre_id where $which order by d.id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		// an invoiced document is named by its invoice number (review R38)
		$doc = ($r['ordre_id'] && $r['doc_nr'] !== null) ? array('id' => (int) $r['ordre_id'], 'nr' => (int) $r['doc_nr'], 'fnr' => trim((string) $r['doc_fnr']), 'st' => (int) $r['doc_status'], 'sum' => round((float) $r['doc_sum'], 2)) : null;
		$out[] = array(
			'id' => (int) $r['id'], 'cid' => (int) $r['konto_id'], 't' => crm_out($r['title']), 'v' => round((float) $r['amount'], 2),
			'st' => trim((string) $r['stage']), 'state' => (string) $r['state'], 'close' => (string) $r['expected_close'],
			'own' => (int) $r['ansat_id'], 'since' => (string) ($r['stage_since'] ?: $r['created']), 'closed' => (string) $r['closed'],
			'reason' => crm_out($r['lost_reason']),
			'doc' => $doc,
			'with' => array(),
		);
	}
	// the colleagues on each deal (20261029)
	if ($out && tbl_exists('crm_participants')) {
		$pos = array();
		foreach ($out as $k => $d) {
			$pos[$d['id']] = $k;
		}
		$q = db_select("select ref_id, ansat_id from crm_participants where kind = 'd' and ref_id in (" . crm_ids(array_keys($pos)) . ") order by id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (isset($pos[(int) $r['ref_id']])) {
				$out[$pos[(int) $r['ref_id']]]['with'][] = (int) $r['ansat_id'];
			}
		}
	}
	return $out;
}

/**
 * Everything the page needs. $extraId: a customer the page must show even when it is outside the loaded set (the
 * drawer named in the query, e.g. the customer just created from Kunder -> Ny kunde; review R54).
 */
function crm_dataset(int $extraId = 0): array
{
	global $ansat_id, $bruger_id, $rettigheder;
	if (!crm_can_read()) {
		return array();
	}
	crm_notif_refresh((int) $bruger_id, (string) $rettigheder, crm_lang());
	$notifications = crm_notifications_dataset();
	$customers = crm_customers(crm_customer_scope(), CRM_MAX_CUSTOMERS + 1, true);
	$truncated = count($customers) > CRM_MAX_CUSTOMERS;
	if ($truncated) {
		array_pop($customers);
	}
	$deals = crm_deals();
	// customers referenced by deals but outside the scope (closed accounts) are still needed for the cards
	$missing = array_diff(array_map(function ($d) { return $d['cid']; }, $deals), array_keys($customers));
	if ($extraId > 0 && !isset($customers[$extraId])) {
		$missing[] = $extraId;
	}
	if ($missing) {
		$customers += crm_customers("a.id in (" . crm_ids($missing) . ")");
	}
	$jobs = (bool) db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box7 = 'on'", __FILE__ . " linje " . __LINE__));
	// customers whose status is a real status but in no status group: they stay out of the CRM (review R44)
	$grouped = array();
	foreach (crm_groups() as $g) {
		foreach ($g['s'] as $s) $grouped[$s] = true;
	}
	$loose = array();
	foreach (crm_statuses() as $s) {
		if (!isset($grouped[$s['id']])) $loose[] = db_escape_string($s['id']);
	}
	$ungrouped = 0;
	if ($loose) {
		$r = db_fetch_array(db_select("select count(*) as n from adresser where art = 'D' and (lukket is null or lukket != 'on') and status in ('" . implode("','", $loose) . "')", __FILE__ . " linje " . __LINE__));
		$ungrouped = $r ? (int) $r['n'] : 0;
	}
	return array(
		'ungrouped' => $ungrouped,
		'rev'       => crm_settings_rev(),
		'histFrom'  => crm_hist_from(),
		'dealFrom'  => crm_deal_from(),
		'today'     => date('Y-m-d'),
		'me'        => (int) $ansat_id,
		'ical'      => crm_ical_url(),
		'canWrite'  => crm_can_write(),
		'canOrder'  => crm_can_order(),
		'notifications' => $notifications['notifications'],
		'notificationUnread' => $notifications['notificationUnread'],
		'debtorGroups' => crm_debtor_groups(),
		'truncated' => $truncated,
		'max'       => CRM_MAX_CUSTOMERS,
		'jobs'      => $jobs,
		'lang'      => crm_lang(),
		'txt'       => crm_texts(),
		'quick'     => (bool) db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4 = 'on'", __FILE__ . " linje " . __LINE__)),
		'emps'      => crm_employees(),
		'statuses'  => crm_statuses(true),
		'groups'    => crm_groups(),
		'customers' => array_values($customers),
		'hist'      => crm_history(array_keys($customers)),
		'deals'     => $deals,
	);
}

// ------------------------------------------------------------------ meetings and colleagues (20261029)
// A meeting is a follow-up of type moede (historik: kontaktes = the day, ansat_id = the responsible) with a row in
// crm_meetings (time, length). The colleagues on a meeting or a deal are in crm_participants. Calendar invitations
// are only sent when the user ticks it; they are queued during the action and sent by crm_flush_mail() after the
// transaction is committed (debitor/crmApi.php), so a rolled back action never mails anyone.

/** 'HH:MM' or null. */
function crm_time($v): ?string
{
	return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim((string) $v), $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
}

function crm_min(string $hhmm): int
{
	$p = explode(':', $hhmm);
	return (int) $p[0] * 60 + (int) ($p[1] ?? 0);
}

function crm_hhmm(int $min): string
{
	$min = max(0, min(24 * 60, $min));
	return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
}

/** "30-10-2026 10:00-11:00" */
function crm_meeting_when(string $date, string $start, int $dur): string
{
	return date('d-m-Y', strtotime($date)) . ' ' . $start . '–' . crm_hhmm(crm_min($start) + $dur);
}

/** Employee ids from the client, kept to the company's active employees; $not (the responsible) left out. */
function crm_emp_ids($list, int $not = 0): array
{
	$valid = array();
	foreach (crm_employees() as $e) {
		$valid[$e['id']] = true;
	}
	$out = array();
	foreach ((array) $list as $id) {
		$id = (int) $id;
		if ($id > 0 && $id !== $not && isset($valid[$id])) {
			$out[$id] = $id;
		}
	}
	return array_values($out);
}

/** @return int[] the colleagues on a meeting (kind 'h', historik id) or a deal ('d') */
function crm_participants(string $kind, int $ref): array
{
	$kind = $kind === 'd' ? 'd' : 'h';
	$out = array();
	if (!tbl_exists('crm_participants')) {
		return $out;
	}
	$q = db_select("select ansat_id from crm_participants where kind = '$kind' and ref_id = $ref order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[] = (int) $r['ansat_id'];
	}
	return $out;
}

/** Replace the colleagues; returns [added, removed]. */
function crm_set_participants(string $kind, int $ref, array $ids): array
{
	$kind = $kind === 'd' ? 'd' : 'h';
	$old = crm_participants($kind, $ref);
	$add = array_values(array_diff($ids, $old));
	$del = array_values(array_diff($old, $ids));
	if ($del) {
		db_modify("delete from crm_participants where kind = '$kind' and ref_id = $ref and ansat_id in (" . crm_ids($del) . ")", __FILE__ . " linje " . __LINE__);
	}
	foreach ($add as $a) {
		db_modify("insert into crm_participants (kind, ref_id, ansat_id) values ('$kind', $ref, " . (int) $a . ")", __FILE__ . " linje " . __LINE__);
	}
	return array($add, $del);
}

/** Everyone on a meeting: the responsible first, then the colleagues. */
function crm_meeting_people(int $hid): array
{
	$r = db_fetch_array(db_select("select ansat_id from historik where id = $hid", __FILE__ . " linje " . __LINE__));
	return array_values(array_unique(array_filter(array_merge(array($r ? (int) $r['ansat_id'] : 0), crm_participants('h', $hid)))));
}

/**
 * The meetings that overlap [start, start + dur) on $date for any of $ids (responsible or colleague), not done and
 * not $except - and the first start from the asked time on, in quarter hours until 18:00, when none of them is busy.
 *
 * @return array{conflicts: array<int, array{who: int, s: string, e: string, n: string}>, free: ?string}
 */
function crm_busy(string $date, string $start, int $dur, array $ids, int $except = 0): array
{
	$res = array('conflicts' => array(), 'free' => $start);
	if (!$ids || !tbl_exists('crm_meetings')) {
		return $res;
	}
	$rows = array();
	$q = db_select("select h.id, h.ansat_id, m.start_time, m.duration, a.firmanavn from historik h join crm_meetings m on m.historik_id = h.id "
		. "left join adresser a on a.id = h.konto_id where h.kontaktes = '$date' and (h.completed is null or h.completed = FALSE) and h.id <> $except", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[(int) $r['id']] = $r;
	}
	if (!$rows) {
		return $res;
	}
	$with = array();
	$q = db_select("select ref_id, ansat_id from crm_participants where kind = 'h' and ref_id in (" . crm_ids(array_keys($rows)) . ")", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$with[(int) $r['ref_id']][] = (int) $r['ansat_id'];
	}
	$want = array_flip($ids);
	$busy = array();
	foreach ($rows as $hid => $r) {
		$s = crm_min((string) $r['start_time']);
		$e = $s + (int) $r['duration'];
		foreach (array_unique(array_merge(array((int) $r['ansat_id']), isset($with[$hid]) ? $with[$hid] : array())) as $p) {
			if (isset($want[$p])) {
				$busy[] = array($s, $e, $p, crm_out($r['firmanavn']));
			}
		}
	}
	$s0 = crm_min($start);
	$e0 = $s0 + $dur;
	usort($busy, function ($a, $b) { return $a[0] - $b[0]; });
	foreach ($busy as $b) {
		if ($b[0] < $e0 && $s0 < $b[1]) {
			$res['conflicts'][] = array('who' => $b[2], 's' => crm_hhmm($b[0]), 'e' => crm_hhmm($b[1]), 'n' => $b[3]);
		}
	}
	$res['free'] = null;
	$limit = max(18 * 60, $e0);
	for ($t = $s0; $t + $dur <= $limit; $t = (intdiv($t, 15) + 1) * 15) {
		$ok = true;
		foreach ($busy as $b) {
			if ($b[0] < $t + $dur && $t < $b[1]) {
				$ok = false;
				break;
			}
		}
		if ($ok) {
			$res['free'] = crm_hhmm($t);
			break;
		}
	}
	return $res;
}

/** "Du er inviteret til et møde" / "Mødet er flyttet til ..." in the bell of someone on a meeting (key crm:mt:<hid>:<ansat>). */
function crm_notify_meeting(int $hid, int $ansat, bool $moved = false): void
{
	$h = db_fetch_array(db_select("select h.konto_id, h.notat, h.kontaktes, m.start_time, m.duration from historik h join crm_meetings m on m.historik_id = h.id where h.id = $hid", __FILE__ . " linje " . __LINE__));
	if (!$h || !$h['kontaktes']) {
		return;
	}
	$key = "crm:mt:$hid:$ansat";
	$u = crm_user_for_ansat($ansat);
	if ($moved) {
		// a moved meeting is shown as unread again
		crm_notification_unread($key, $u);
	}
	$cid = (int) $h['konto_id'];
	$c = db_fetch_array(db_select("select firmanavn from adresser where id = $cid", __FILE__ . " linje " . __LINE__));
	$when = crm_meeting_when((string) $h['kontaktes'], (string) $h['start_time'], (int) $h['duration']);
	$body = crm_out($c ? (string) $c['firmanavn'] : '') . ': ' . mb_substr(trim(crm_out($h['notat'])), 0, 120) . ' · ' . $when . ' · ' . crm_t('fra %s', crm_actor_name());
	crm_notify($ansat, $key, $moved ? crm_t('Mødet er flyttet til %s', $when) : crm_t('Du er inviteret til et møde'), $body,
		'/debitor/crm.php?tab=cal&drawer=' . $cid, date('Y-m-d', strtotime($h['kontaktes'] . ' +30 days')));
}

/** "Du er sat på sagen »...«" for a colleague added to a deal (key crm:dm:<id>:<ansat>). */
function crm_notify_deal_member(int $id, int $ansat): void
{
	$d = db_fetch_array(db_select("select d.title, d.konto_id, d.amount, a.firmanavn from crm_deals d left join adresser a on a.id = d.konto_id where d.id = $id", __FILE__ . " linje " . __LINE__));
	if (!$d) {
		return;
	}
	$amount = round((float) $d['amount']);
	$body = crm_out((string) $d['title']) . ' · ' . crm_out((string) $d['firmanavn'])
		. ($amount ? ' · ' . crm_t('%s kr', number_format($amount, 0, ',', '.')) : '') . ' · ' . crm_t('fra %s', crm_actor_name());
	crm_notify($ansat, "crm:dm:$id:$ansat", crm_t('Du er sat på sagen »%s«', crm_out((string) $d['title'])), $body,
		'/debitor/crm.php?tab=pipe&drawer=' . (int) $d['konto_id'] . '&dtab=deals', date('Y-m-d', strtotime('+30 days')));
}

// ------------------------------------------------------------------ calendar files (.ics)

function crm_ics_text(string $s): string
{
	return str_replace(array('\\', ';', ',', "\r\n", "\n", "\r"), array('\\\\', '\;', '\,', '\n', '\n', ''), $s);
}

/** A content line folded at 75 octets (RFC 5545 3.1), never inside a UTF-8 character. */
function crm_ics_line(string $line): string
{
	$out = '';
	while (strlen($line) > 75) {
		$cut = 75;
		while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
			$cut--;
		}
		$out .= substr($line, 0, $cut) . "\r\n ";
		$line = substr($line, $cut);
	}
	return $out . $line . "\r\n";
}

function crm_ics_cn(string $name): string
{
	return '"' . str_replace(array('"', "\r", "\n"), '', $name) . '"';
}

/** Local date + 'HH:MM' (+ minutes) as UTC, e.g. 20261030T090000Z. */
function crm_ics_utc(string $date, string $hhmm, int $plus = 0): string
{
	$d = new DateTime($date . ' ' . $hhmm, new DateTimeZone(date_default_timezone_get()));
	if ($plus) {
		$d->modify('+' . $plus . ' minutes');
	}
	$d->setTimezone(new DateTimeZone('UTC'));
	return $d->format('Ymd\THis\Z');
}

function crm_ics_uid(int $hid): string
{
	global $db;
	return 'crm-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $db) . '-' . $hid . '@saldi.dk';
}

/**
 * One meeting as a VEVENT (UTF-8 lines). $h: historik row joined with crm_meetings and the customer's name;
 * $att: email => name of the attendees listed in it.
 */
function crm_ics_event(array $h, int $seq, array $att, string $status, string $organizer): string
{
	$date = (string) $h['kontaktes'];
	$start = (string) $h['start_time'];
	$dur = (int) $h['duration'];
	$firm = crm_out((string) $h['firmanavn']);
	$note = trim(crm_out((string) $h['notat']));
	$names = array();
	foreach (crm_meeting_people((int) $h['id']) as $a) {
		$names[] = crm_employee_name($a);
	}
	$desc = $note . "\n\n" . crm_t('Deltagere: %s', implode(', ', array_filter($names)));
	if (!empty($h['kontakt_navn'])) {
		$desc .= "\n" . crm_t('Kontaktperson: %s', crm_out((string) $h['kontakt_navn']));
	}
	$s = crm_ics_line('BEGIN:VEVENT')
		. crm_ics_line('UID:' . crm_ics_uid((int) $h['id']))
		. crm_ics_line('SEQUENCE:' . $seq)
		. crm_ics_line('DTSTAMP:' . gmdate('Ymd\THis\Z'))
		. crm_ics_line('DTSTART:' . crm_ics_utc($date, $start))
		. crm_ics_line('DTEND:' . crm_ics_utc($date, $start, $dur))
		. crm_ics_line('SUMMARY:' . crm_ics_text(crm_t('Møde med %s', $firm)))
		. crm_ics_line('DESCRIPTION:' . crm_ics_text($desc))
		. crm_ics_line('ORGANIZER;CN=' . crm_ics_cn($organizer) . ':mailto:kan_ikke_besvares@saldi.dk');
	foreach ($att as $mail => $name) {
		$s .= crm_ics_line('ATTENDEE;CN=' . crm_ics_cn($name) . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=FALSE:mailto:' . $mail);
	}
	return $s . crm_ics_line('STATUS:' . $status) . crm_ics_line('TRANSP:OPAQUE') . crm_ics_line('END:VEVENT');
}

function crm_meeting_row(int $hid): ?array
{
	$h = db_fetch_array(db_select("select h.id, h.konto_id, h.kontakt_id, h.ansat_id, h.notat, h.kontaktes, m.start_time, m.duration, m.seq, m.invite_team, m.invite_contact, "
		. "a.firmanavn, k.navn as kontakt_navn, k.email as kontakt_mail from historik h join crm_meetings m on m.historik_id = h.id "
		. "left join adresser a on a.id = h.konto_id left join ansatte k on k.id = h.kontakt_id and k.konto_id = h.konto_id where h.id = $hid", __FILE__ . " linje " . __LINE__));
	return ($h && $h['kontaktes']) ? $h : null;
}

/**
 * Calendar invitations for meeting $hid by mail: METHOD REQUEST (new or changed) or CANCEL. $team: the people on the
 * meeting (or only $only, e.g. the colleagues just taken off); $contact: the customer's contact person. Queued here,
 * sent by crm_flush_mail(). Every send raises the SEQUENCE, so the calendars replace the earlier version.
 *
 * @return int mails queued
 */
function crm_queue_invites(int $hid, string $method, bool $team, bool $contact, ?array $only = null): int
{
	$h = crm_meeting_row($hid);
	if (!$h) {
		return 0;
	}
	$method = $method === 'CANCEL' ? 'CANCEL' : 'REQUEST';
	$to = array();
	if ($team) {
		foreach ($only === null ? crm_meeting_people($hid) : $only as $a) {
			$m = crm_emp_mail((int) $a);
			if ($m !== '') $to[strtolower($m)] = array($m, crm_employee_name((int) $a));
		}
	}
	$km = trim((string) $h['kontakt_mail']);
	if ($contact && filter_var($km, FILTER_VALIDATE_EMAIL)) {
		$to[strtolower($km)] = array($km, crm_out((string) $h['kontakt_navn']));
	}
	if (!$to) {
		return 0;
	}
	// the attendees: everyone on the meeting with an address (+ the contact when invited); a cancel lists its recipients
	$att = array();
	if ($method === 'REQUEST') {
		foreach (crm_meeting_people($hid) as $a) {
			$m = crm_emp_mail($a);
			if ($m !== '') $att[$m] = crm_employee_name($a);
		}
		if (($contact || (int) $h['invite_contact']) && filter_var($km, FILTER_VALIDATE_EMAIL)) {
			$att[$km] = crm_out((string) $h['kontakt_navn']);
		}
	}
	foreach ($to as $r) {
		$att[$r[0]] = $r[1];
	}
	$seq = (int) $h['seq'] + 1;
	$flags = '';
	if ($method === 'REQUEST') {
		$flags = ($team && $only === null ? ', invite_team = 1' : '') . ($contact ? ', invite_contact = 1' : '');
	}
	db_modify("update crm_meetings set seq = $seq$flags where historik_id = $hid", __FILE__ . " linje " . __LINE__);
	$actor = crm_actor_name();
	$organizer = $actor !== '' ? crm_t('%s (Saldi)', $actor) : 'Saldi';
	$ics = crm_ics_line('BEGIN:VCALENDAR') . crm_ics_line('PRODID:-//Saldi//CRM//DA') . crm_ics_line('VERSION:2.0')
		. crm_ics_line('CALSCALE:GREGORIAN') . crm_ics_line('METHOD:' . $method)
		. crm_ics_event($h, $seq, $att, $method === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED', $organizer) . crm_ics_line('END:VCALENDAR');
	$firm = crm_out((string) $h['firmanavn']);
	$when = crm_meeting_when((string) $h['kontaktes'], (string) $h['start_time'], (int) $h['duration']);
	if ($method === 'CANCEL') {
		$subject = crm_t('Aflyst: møde med %s %s', $firm, $when);
		$text = crm_t('Mødet med %s %s er aflyst.', $firm, $when);
	} else {
		$subject = crm_t('Invitation: møde med %s %s', $firm, $when);
		$text = crm_t('%s har inviteret dig til et møde med %s.', $actor !== '' ? $actor : 'Saldi', $firm) . "\n" . $when . "\n\n" . trim(crm_out((string) $h['notat']));
	}
	foreach ($to as $r) {
		$GLOBALS['crm_mail_queue'][] = array($r[0], $subject, $text, $ics);
	}
	return count($to);
}

/**
 * Send the queued invitations through the company's mail setup (as includes/userFunctions.php user_send_mail()),
 * the .ics as the text/calendar part Google, Outlook and Apple read as an invitation.
 *
 * @return array{sent: int, failed: int}
 */
function crm_flush_mail(): array
{
	global $charset;
	$res = array('sent' => 0, 'failed' => 0);
	$queue = isset($GLOBALS['crm_mail_queue']) ? $GLOBALS['crm_mail_queue'] : array();
	$GLOBALS['crm_mail_queue'] = array();
	if (!$queue) {
		return $res;
	}
	$autoload = '';
	foreach (array(__DIR__ . '/../../vendor/autoload.php', __DIR__ . '/../vendor/autoload.php') as $candidate) {
		if (file_exists($candidate)) {
			$autoload = $candidate;
			break;
		}
	}
	if ($autoload === '') {
		$res['failed'] = count($queue);
		return $res;
	}
	require_once $autoload;
	$firm = db_fetch_array(db_select("select * from adresser where art = 'S'", __FILE__ . " linje " . __LINE__));
	$firmName = $firm ? crm_out((string) $firm['firmanavn']) : 'Saldi';
	$firmMail = $firm ? trim((string) $firm['email']) : '';
	foreach ($queue as $m) {
		list($to, $subject, $text, $ics) = $m;
		try {
			$mail = new PHPMailer\PHPMailer\PHPMailer();
			$mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
			$mail->IsSMTP();
			$mail->CharSet = 'UTF-8';
			$mail->Host = ($firm && $firm['felt_1']) ? (string) $firm['felt_1'] : 'localhost';
			if ($firm && $firm['felt_2']) {
				$mail->SMTPAuth = true;
				$mail->Username = (string) $firm['felt_2'];
				$mail->Password = (string) $firm['felt_3'];
				if (!empty($firm['felt_4'])) {
					$mail->SMTPSecure = strtolower((string) $firm['felt_4']);
				}
			} else {
				$mail->SMTPAuth = false;
			}
			$mail->Timeout = 10;
			$mail->From = 'kan_ikke_besvares@saldi.dk';
			$mail->FromName = $firmName;
			if ($firmMail !== '' && filter_var($firmMail, FILTER_VALIDATE_EMAIL)) {
				$mail->AddReplyTo($firmMail, $firmName);
			}
			$mail->AddAddress($to);
			$mail->IsHTML(true);
			$mail->Subject = $subject;
			$mail->Body = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
			$mail->AltBody = $text;
			$mail->Ical = $ics;
			$mail->Send() ? $res['sent']++ : $res['failed']++;
		} catch (\Throwable $e) {
			$res['failed']++;
		}
	}
	return $res;
}

// ------------------------------------------------------------------ personal calendar feed (api/crm/ical.php)

/** This company's id in the master database (regnskab.id), part of the feed link. */
function crm_regnskab_id(): int
{
	global $db;
	$r = db_fetch_array(db_select("select id from regnskab where db = '" . db_escape_string((string) $db) . "'", __FILE__ . " linje " . __LINE__, true));
	return $r ? (int) $r['id'] : 0;
}

function crm_base_url(): string
{
	$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
	$host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
	$dir = rtrim(str_replace('\\', '/', dirname(dirname((string) (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/x/x')))), '/');
	return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/** The current user's secret feed link, '' when none is made. */
function crm_ical_url(): string
{
	global $bruger_id;
	if ((int) $bruger_id <= 0 || !tbl_exists('crm_ical')) {
		return '';
	}
	$r = db_fetch_array(db_select("select token from crm_ical where bruger_id = " . (int) $bruger_id, __FILE__ . " linje " . __LINE__));
	$rid = $r ? crm_regnskab_id() : 0;
	return $rid ? crm_base_url() . '/api/crm/ical.php?r=' . $rid . '&k=' . (int) $bruger_id . '-' . $r['token'] : '';
}

/** 'new': a new link (an older one stops working), 'off': no link. Returns the link. */
function crm_ical_set(string $mode): string
{
	global $bruger_id;
	$u = (int) $bruger_id;
	if ($u <= 0) {
		return '';
	}
	db_modify("delete from crm_ical where bruger_id = $u", __FILE__ . " linje " . __LINE__);
	if ($mode === 'new') {
		db_modify("insert into crm_ical (bruger_id, token, created) values ($u, '" . bin2hex(random_bytes(24)) . "', '" . date('Y-m-d') . "')", __FILE__ . " linje " . __LINE__);
	}
	if (function_exists('audit_log')) {
		audit_log('crm_ical', $mode === 'new' ? crm_t('Kalenderabonnement: nyt link') : crm_t('Kalenderabonnement slået fra'));
	}
	return crm_ical_url();
}

/** The feed: the meetings of employee $ansat (responsible or colleague) from 60 days back, as a calendar file. */
function crm_ical_feed(int $ansat, string $company): string
{
	$from = date('Y-m-d', strtotime('-60 days'));
	$q = db_select("select h.id from historik h join crm_meetings m on m.historik_id = h.id where h.kontaktes >= '$from' and (h.ansat_id = $ansat "
		. "or exists (select 1 from crm_participants p where p.kind = 'h' and p.ref_id = h.id and p.ansat_id = $ansat)) order by h.kontaktes, m.start_time", __FILE__ . " linje " . __LINE__);
	$ids = array();
	while ($r = db_fetch_array($q)) {
		$ids[] = (int) $r['id'];
	}
	$s = crm_ics_line('BEGIN:VCALENDAR') . crm_ics_line('PRODID:-//Saldi//CRM//DA') . crm_ics_line('VERSION:2.0') . crm_ics_line('CALSCALE:GREGORIAN')
		. crm_ics_line('METHOD:PUBLISH') . crm_ics_line('X-WR-CALNAME:' . crm_ics_text(crm_t('Saldi CRM – %s', $company)))
		. crm_ics_line('REFRESH-INTERVAL;VALUE=DURATION:PT1H') . crm_ics_line('X-PUBLISHED-TTL:PT1H');
	foreach ($ids as $hid) {
		$h = crm_meeting_row($hid);
		if ($h) {
			$s .= crm_ics_event($h, (int) $h['seq'], array(), 'CONFIRMED', 'Saldi');
		}
	}
	return $s . crm_ics_line('END:VCALENDAR');
}

// ------------------------------------------------------------------ actions (debitor/crmApi.php)

/** adresser.kontaktet / kontaktes follow the entries, so the old customer lists stay right. */
function crm_sync_customer(int $cid): void
{
	$r = db_fetch_array(db_select("select max(kontaktet) as d from historik where konto_id = $cid and (type is null or type not in ('status', 'deal'))", __FILE__ . " linje " . __LINE__));
	$last = $r && $r['d'] ? "'" . $r['d'] . "'" : 'NULL';
	$r = db_fetch_array(db_select("select min(h.kontaktes) as d from historik h where h.konto_id = $cid and " . crm_open_sql('h'), __FILE__ . " linje " . __LINE__));
	$next = $r && $r['d'] ? "'" . $r['d'] . "'" : 'NULL';
	db_modify("update adresser set kontaktet = $last, kontaktes = $next where id = $cid", __FILE__ . " linje " . __LINE__);
}

/** Legacy rows that are open by the rule become explicit (FALSE) before a new dated row would make them count as done. */
function crm_materialize_open(int $cid): void
{
	db_modify("update historik set completed = FALSE where konto_id = $cid and completed is null and kontaktes is not null and id = "
		. "(select m from (select max(hx.id) as m from historik hx where hx.konto_id = $cid and hx.kontaktes is not null) t)", __FILE__ . " linje " . __LINE__);
}

/** $by = the follow-up's owner (ansat_id, as the old pages read it); $author = who wrote it (author_id, review R4). */
function crm_insert_entry(int $cid, string $type, int $by, int $to, string $note, ?string $due, bool $done = false, string $date = '', int $author = 0): int
{
	$date = crm_date($date) ?: date('Y-m-d');
	$type = in_array($type, array('note', 'opkald', 'mail', 'moede', 'status', 'deal'), true) ? $type : 'note';
	$dueSql = $due ? "'$due'" : 'NULL';
	$doneSql = $due ? ($done ? 'TRUE' : 'FALSE') : 'NULL';
	$author = $author ?: $by;
	$authorSql = $author ? (string) $author : 'NULL';
	db_modify("insert into historik (konto_id, kontakt_id, ansat_id, author_id, notat, notedate, kontaktet, kontaktes, completed, type) values "
		. "($cid, $to, $by, $authorSql, '" . crm_in($note) . "', '" . date('Y-m-d') . "', '$date', $dueSql, $doneSql, '$type')", __FILE__ . " linje " . __LINE__);
	return crm_last_id('historik', $cid);
}

/** The id of the row this connection just inserted - not a colleague's inserted meanwhile (review R74). */
function crm_last_id(string $table, int $cid): int
{
	// currval of the table's own sequence: this connection's last insert there (lastval() could be another table's)
	$r = db_fetch_array(db_select(crm_is_mysql() ? "select last_insert_id() as id" : "select currval(pg_get_serial_sequence('$table', 'id')) as id", __FILE__ . " linje " . __LINE__));
	if ($r && (int) $r['id'] > 0) {
		return (int) $r['id'];
	}
	return 0;
}

/**
 * Link a customer's document once and record its history while holding the case row lock.
 * Both callers (the CRM API and order creation) must own a transaction through commit/rollback.
 *
 * @return array{id?: int, hid?: int, error?: string}
 */
function crm_link_document(int $cid, int $dealId, int $orderId, int $author): array
{
	if (!crm_can_write()) {
		return array('error' => 'noaccess');
	}
	$deal = db_fetch_array(db_select("select title, ordre_id, state from crm_deals where id = $dealId and konto_id = $cid for update", __FILE__ . ' linje ' . __LINE__));
	if (!$deal || $deal['state'] !== 'open') {
		return array('error' => 'fields');
	}
	if ((int) $deal['ordre_id'] > 0) {
		return array('error' => 'linked');
	}
	$order = db_fetch_array(db_select("select ordrenr, status from ordrer where id = $orderId and konto_id = $cid and art = 'DO'", __FILE__ . ' linje ' . __LINE__));
	if (!$order) {
		return array('error' => 'fields');
	}
	db_modify("update crm_deals set ordre_id = $orderId where id = $dealId and konto_id = $cid", __FILE__ . ' linje ' . __LINE__);
	$document = crm_t((int) $order['status'] === 0 ? 'Tilbud %s' : 'Ordre %s', (int) $order['ordrenr']);
	$historyId = crm_insert_entry($cid, 'deal', $author, 0, crm_t('Sag »%s«: %s', crm_out($deal['title']), $document), null);
	return array('id' => $dealId, 'hid' => $historyId);
}

function crm_status_name(string $id): string
{
	foreach (crm_statuses() as $s) {
		if ($s['id'] === $id) {
			return $s['n'];
		}
	}
	return $id;
}

/**
 * @return array<string, mixed> result for the client (ids for undo)
 */
function crm_action(string $action, array $in): array
{
	global $ansat_id;
	if (!crm_can_read() || ($action !== 'customer' && !crm_can_write())) {
		return array('error' => 'noaccess');
	}
	$me = (int) $ansat_id;
	switch ($action) {
		case 'save_lead':
			return crm_save_lead($in, $me);

		case 'prepare_document':
			return crm_prepare_document($in, $me);

		case 'add_entry':
			$cid = (int) $in['cid'];
			$due = crm_date($in['due'] ?? '');
			if ($due) {
				// a legacy follow-up the user chose NOT to close stays open, although this row becomes the newest dated one
				crm_materialize_open($cid);
			}
			$closed = array();
			foreach ((array) ($in['close'] ?? array()) as $hid) {
				$hid = (int) $hid;
				db_modify("update historik set completed = TRUE, completed_at = '" . date('Y-m-d') . "' where id = $hid and konto_id = $cid", __FILE__ . " linje " . __LINE__);
				crm_notif_drop('crm:fu:' . $hid);
				$closed[] = $hid;
			}
			$owner = $due && !empty($in['owner']) ? (int) $in['owner'] : $me;
			$id = crm_insert_entry($cid, (string) ($in['type'] ?? 'note'), $owner, (int) ($in['to'] ?? 0), (string) ($in['note'] ?? ''), $due, false, '', $me);
			crm_sync_customer($cid);
			if ($due && $owner !== $me) {
				crm_notify_followup($id, $cid, $owner, $due, (string) ($in['note'] ?? ''));
			}
			return array('id' => $id, 'closed' => $closed);

		case 'reopen_many':
			// undo of close_old / of the follow-ups closed by add_entry
			$ids = array();
			foreach ((array) ($in['ids'] ?? array()) as $hid) {
				if ((int) $hid > 0) $ids[] = (int) $hid;
			}
			if ($ids) {
				db_modify("update historik set completed = FALSE, completed_at = NULL where id in (" . crm_ids($ids) . ") and kontaktes is not null", __FILE__ . " linje " . __LINE__);
				$q = db_select("select distinct konto_id from historik where id in (" . crm_ids($ids) . ")", __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) crm_sync_customer((int) $r['konto_id']);
			}
			return array('n' => count($ids));

		case 'close_old':
			// review R23: one click closes the follow-ups from the old CRM that are more than 90 days overdue (returns the ids for undo)
			$limit = date('Y-m-d', strtotime('-90 days'));
			$ids = $cids = array();
			$q = db_select("select h.id, h.konto_id from historik h where h.kontaktes < '$limit' and " . crm_open_sql('h'), __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$ids[] = (int) $r['id'];
				$cids[(int) $r['konto_id']] = true;
			}
			if ($ids) {
				db_modify("update historik set completed = TRUE, completed_at = '" . date('Y-m-d') . "' where id in (" . crm_ids($ids) . ")", __FILE__ . " linje " . __LINE__);
				foreach ($ids as $hid) crm_notif_drop('crm:fu:' . $hid);
				foreach (array_keys($cids) as $c) crm_sync_customer($c);
				if (function_exists('audit_log')) {
					audit_log('crm_close_old', crm_t('%s opfølgninger fra det gamle CRM (mere end 90 dage forsinkede) markeret som gjort', count($ids)));
				}
			}
			return array('ids' => $ids);

		case 'update_entry':
			$hid = (int) $in['id'];
			$type = in_array($in['type'] ?? '', array('note', 'opkald', 'mail', 'moede'), true) ? $in['type'] : 'note';
			$set = "notat = '" . crm_in($in['note'] ?? '') . "', type = '$type', kontakt_id = " . (int) ($in['to'] ?? 0);
			if (crm_date($in['date'] ?? '') && crm_date($in['date']) <= date('Y-m-d')) $set .= ", kontaktet = '" . crm_date($in['date']) . "'";   // review R10
			$old = db_fetch_array(db_select("select konto_id, ansat_id, kontaktes, completed from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			if (!$old) return array('error' => 'gone');
			// review R15: an open follow-up can get another owner, another date, or no date (the note stays, the reminder goes)
			$newOwner = isset($in['owner']) ? (int) $in['owner'] : (int) $old['ansat_id'];
			if (isset($in['owner']) && $newOwner > 0) $set .= ", ansat_id = $newOwner";
			$newDue = null;
			if (array_key_exists('due', $in)) {
				$newDue = crm_date($in['due'] ?? '');
				$set .= $newDue ? ", kontaktes = '$newDue', completed = " . (crm_bool($old['completed']) ? 'TRUE' : 'FALSE') : ", kontaktes = NULL, completed = NULL, completed_at = NULL";
			}
			db_modify("update historik set $set where id = $hid", __FILE__ . " linje " . __LINE__);
			crm_sync_customer((int) $old['konto_id']);
			$due = array_key_exists('due', $in) ? $newDue : ($old['kontaktes'] ? (string) $old['kontaktes'] : null);
			if (!$due || $newOwner !== (int) $old['ansat_id']) {
				crm_notif_drop('crm:fu:' . $hid);
			}
			if ($due && $newOwner > 0 && $newOwner !== (int) $old['ansat_id'] && $newOwner !== $me) {
				crm_notify_followup($hid, (int) $old['konto_id'], $newOwner, $due, (string) ($in['note'] ?? ''));
			}
			return array('id' => $hid);

		case 'set_owner':
			// review R15: the customer's responsible employee (adresser.kontoansvarlig), from the drawer
			$cid = (int) $in['cid'];
			$to = (int) ($in['owner'] ?? 0);
			$r = db_fetch_array(db_select("select kontoansvarlig from adresser where id = $cid and art in ('D', 'L')", __FILE__ . " linje " . __LINE__));
			if (!$r) return array('error' => 'customer');
			db_modify("update adresser set kontoansvarlig = " . ($to > 0 ? $to : 'NULL') . " where id = $cid", __FILE__ . " linje " . __LINE__);
			$from = (int) $r['kontoansvarlig'];
			$hid = crm_insert_entry($cid, 'status', $me, 0, crm_t('Ansvarlig: %s → %s', $from ? crm_employee_name($from) : crm_t('ingen'), $to ? crm_employee_name($to) : crm_t('ingen')), null);
			return array('from' => $from, 'hid' => $hid);

		case 'unset_owner':
			// undo of set_owner: the old responsible back and the logged line removed
			$cid = (int) $in['cid'];
			$to = (int) ($in['owner'] ?? 0);
			db_modify("update adresser set kontoansvarlig = " . ($to > 0 ? $to : 'NULL') . " where id = $cid and art in ('D', 'L')", __FILE__ . " linje " . __LINE__);
			if (!empty($in['remove'])) db_modify("delete from historik where id = " . (int) $in['remove'] . " and konto_id = $cid and type = 'status'", __FILE__ . " linje " . __LINE__);
			return array('ok' => 1);

		case 'move_followups':
			// review R15: open follow-ups handed to another employee (suggested after a change of responsible); old owners returned for undo
			$cid = (int) $in['cid'];
			$to = (int) ($in['owner'] ?? 0);
			if ($to <= 0) return array('error' => 'fields');
			$moved = array();
			foreach ((array) ($in['ids'] ?? array()) as $hid) {
				$hid = (int) $hid;
				$r = db_fetch_array(db_select("select ansat_id, kontaktes, notat from historik where id = $hid and konto_id = $cid", __FILE__ . " linje " . __LINE__));
				if (!$r) continue;
				db_modify("update historik set ansat_id = $to where id = $hid", __FILE__ . " linje " . __LINE__);
				$moved[] = array('id' => $hid, 'from' => (int) $r['ansat_id']);
				if ($to !== (int) $r['ansat_id']) {
					crm_notif_drop('crm:fu:' . $hid);
				}
				if ($to !== $me && $r['kontaktes']) crm_notify_followup($hid, $cid, $to, (string) $r['kontaktes'], crm_out($r['notat']));
			}
			return array('moved' => $moved);

		case 'unmove_followups':
			foreach ((array) ($in['moved'] ?? array()) as $m) {
				$m = (array) $m;
				$hid = (int) ($m['id'] ?? 0);
				if ($hid <= 0) continue;
				db_modify("update historik set ansat_id = " . (int) ($m['from'] ?? 0) . " where id = $hid", __FILE__ . " linje " . __LINE__);
				crm_notif_drop('crm:fu:' . $hid);
			}
			return array('ok' => 1);

		case 'link_doc':
			return crm_link_document((int) $in['cid'], (int) $in['deal'], (int) $in['doc'], $me);

		case 'unlink_doc':
			$cid = (int) $in['cid'];
			$id = (int) $in['deal'];
			db_modify("update crm_deals set ordre_id = NULL where id = $id and konto_id = $cid", __FILE__ . " linje " . __LINE__);
			if (!empty($in['remove'])) db_modify("delete from historik where id = " . (int) $in['remove'] . " and konto_id = $cid and type = 'deal'", __FILE__ . " linje " . __LINE__);
			return array('id' => $id);

		case 'starter':
			// review R45: a suggested sales board for a company with no status groups yet - only on the user's click, never by itself
			if (crm_groups()) return array('error' => 'exists');
			$names = array(crm_t('Henvendelse'), crm_t('Tilbud sendt'), crm_t('Forhandling'), crm_t('Kunde'), crm_t('Tabt'));
			$list = array();
			foreach (crm_statuses() as $s) $list[] = array('id' => $s['id'], 'n' => $s['n']);
			foreach ($names as $i => $n) $list[] = array('id' => 'new:' . ($i + 1), 'n' => $n);
			$map = crm_save_statuses($list);
			if (is_string($map)) return array('error' => $map);
			$s = implode(chr(9), array($map['new:1'], $map['new:2'], $map['new:3']));
			db_modify("insert into grupper (art, beskrivelse, box1, box2, box3, box4, kodenr) values ('SKN', 'Status knapper (CRM)', '" . crm_in(crm_t('Salg')) . "', '$s', '" . $map['new:4'] . "', '" . $map['new:5'] . "', '1')", __FILE__ . " linje " . __LINE__);
			update_settings_value("antalStatusKnapper", "crmvisning", 1, "Number of status buttons");
			if (function_exists('audit_log')) audit_log('crm_starter', crm_t('Salg') . ': ' . implode(' > ', $names));
			return array('n' => 1);

		case 'toggle_done':
			$hid = (int) $in['id'];
			$done = !empty($in['done']);
			db_modify("update historik set completed = " . ($done ? "TRUE, completed_at = '" . date('Y-m-d') . "'" : 'FALSE, completed_at = NULL') . " where id = $hid", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select konto_id from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			if ($r) crm_sync_customer((int) $r['konto_id']);
			if ($done) {
				crm_notif_drop('crm:fu:' . $hid);
				crm_notif_drop('crm:mt:' . $hid . ':%');
			}
			return array('id' => $hid);

		case 'set_due':
			$hid = (int) $in['id'];
			$due = crm_date($in['due'] ?? '');
			if (!$due) return array('error' => 'date');
			db_modify("update historik set kontaktes = '$due', completed = FALSE where id = $hid", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select konto_id, ansat_id, notat from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			$mt = crm_meetings_for(array($hid));
			if ($r) {
				crm_sync_customer((int) $r['konto_id']);
				if (isset($mt[$hid])) {
					// a meeting keeps its time; everyone on it sees the new day in the bell. New invitations are offered by the page (inv), never sent by a drag
					foreach (crm_meeting_people($hid) as $p) crm_notify_meeting($hid, $p, true);
				} elseif ((int) $r['ansat_id'] !== $me) {
					crm_notify_followup($hid, (int) $r['konto_id'], (int) $r['ansat_id'], $due, crm_out($r['notat']), true);
				}
			}
			return array('id' => $hid, 'inv' => isset($mt[$hid]) ? $mt[$hid]['inv'] : 0);

		case 'delete_entry':
			$hid = (int) $in['id'];
			$r = db_fetch_array(db_select("select * from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			if (!$r) return array('error' => 'gone');
			$mt = crm_meetings_for(array($hid));
			$mrow = null;
			if (isset($mt[$hid])) {
				$m = $mt[$hid];
				// a cancellation only when the user ticked it in the confirmation (pre-ticked when invitations went out)
				$cancelled = !empty($in['cancel']) && $m['inv'] ? crm_queue_invites($hid, 'CANCEL', (bool) ($m['inv'] & 1), (bool) ($m['inv'] & 2)) : 0;
				$mrow = array('tm' => $m['tm'], 'dur' => $m['dur'], 'with' => $m['with'], 'inv' => $m['inv'], 'cancelled' => $cancelled);
				db_modify("delete from crm_participants where kind = 'h' and ref_id = $hid", __FILE__ . " linje " . __LINE__);
				db_modify("delete from crm_meetings where historik_id = $hid", __FILE__ . " linje " . __LINE__);
				crm_notif_drop('crm:mt:' . $hid . ':%');
			}
			db_modify("delete from historik where id = $hid", __FILE__ . " linje " . __LINE__);
			crm_sync_customer((int) $r['konto_id']);
			crm_notif_drop('crm:fu:' . $hid);
			return array('row' => array('mt' => $mrow, 'cid' => (int) $r['konto_id'], 'type' => (string) $r['type'], 'by' => (int) $r['ansat_id'], 'au' => (int) $r['author_id'], 'to' => (int) $r['kontakt_id'],
				'note' => crm_out($r['notat']), 'due' => $r['kontaktes'], 'done' => ($r['completed'] === null || $r['completed'] === '') ? null : crm_bool($r['completed']),
				'date' => (string) $r['kontaktet'], 'file' => crm_out($r['dokument'])));

		case 'restore_entry':
			$row = (array) $in['row'];
			$cid = (int) $row['cid'];
			$id = crm_insert_entry($cid, (string) $row['type'], (int) $row['by'], (int) $row['to'], (string) $row['note'], crm_date($row['due'] ?? ''), !empty($row['done']), (string) $row['date'], (int) ($row['au'] ?? 0));
			if ($id) {
				// the restored row keeps its attachment and its original open/done state (NULL = legacy, review R24)
				$file = crm_in($row['file'] ?? '');
				$doneSql = !isset($row['done']) || $row['done'] === null ? 'NULL' : (!empty($row['done']) ? 'TRUE' : 'FALSE');
				db_modify("update historik set dokument = " . ($file !== '' ? "'$file'" : 'NULL') . ", completed = " . (crm_date($row['due'] ?? '') ? $doneSql : 'NULL') . " where id = $id", __FILE__ . " linje " . __LINE__);
				$m = isset($row['mt']) && is_array($row['mt']) ? $row['mt'] : null;
				if ($m && crm_time($m['tm'] ?? '') && crm_date($row['due'] ?? '')) {
					$inv = (int) ($m['inv'] ?? 0);
					db_modify("insert into crm_meetings (historik_id, start_time, duration, seq, invite_team, invite_contact) values ($id, '" . crm_time($m['tm']) . "', "
						. max(5, min(720, (int) ($m['dur'] ?? 60))) . ", 0, " . ($inv & 1 ? 1 : 0) . ", " . ($inv & 2 ? 1 : 0) . ")", __FILE__ . " linje " . __LINE__);
					crm_set_participants('h', $id, crm_emp_ids($m['with'] ?? array(), (int) $row['by']));
				}
			}
			crm_sync_customer($cid);
			return array('id' => $id);

		case 'set_status':
			$cid = (int) $in['cid'];
			$to = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $in['status']);
			$r = db_fetch_array(db_select("select status from adresser where id = $cid and art in ('D', 'L')", __FILE__ . " linje " . __LINE__));
			if (!$r) return array('error' => 'customer');
			$from = trim((string) $r['status']);
			db_modify("update adresser set status = '$to' where id = $cid", __FILE__ . " linje " . __LINE__);
			if (!empty($in['remove'])) {
				// undo: the logged move goes away with it
				db_modify("delete from historik where id = " . (int) $in['remove'] . " and konto_id = $cid and type = 'status'", __FILE__ . " linje " . __LINE__);
				return array('from' => $from);
			}
			$note = ($from !== '' ? crm_status_name($from) : crm_t('Ingen status')) . ' → ' . ($to !== '' ? crm_status_name($to) : crm_t('Ingen status'));
			if (!empty($in['how'])) $note .= ' (' . trim((string) $in['how']) . ')';
			$id = crm_insert_entry($cid, 'status', $me, 0, $note, null);
			return array('id' => $id, 'from' => $from);

		case 'save_deal':
			$id = (int) ($in['id'] ?? 0);
			$cid = (int) $in['cid'];
			$title = crm_in($in['t'] ?? '');
			if ($title === '' || !$cid) return array('error' => 'fields');
			// the page sends a canonical decimal ("12500.50"); anything else is refused instead of guessed (review R41)
			$vIn = trim((string) ($in['v'] ?? '0'));
			if ($vIn === '') $vIn = '0';
			if (!preg_match('/^-?\d{1,13}(\.\d{1,2})?$/', $vIn)) return array('error' => 'amount');
			$amount = round((float) $vIn, 2);
			$stage = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($in['st'] ?? ''));
			$close = crm_date($in['close'] ?? '');
			$closeSql = $close ? "'$close'" : 'NULL';
			$own = (int) ($in['own'] ?? $me);
			$today = date('Y-m-d');
			$prevOwn = $me;
			if ($id) {
				$p = db_fetch_array(db_select("select ansat_id from crm_deals where id = $id", __FILE__ . " linje " . __LINE__));
				$prevOwn = $p ? (int) $p['ansat_id'] : $me;
				db_modify("update crm_deals set konto_id = $cid, title = '$title', amount = $amount, expected_close = $closeSql, ansat_id = $own where id = $id", __FILE__ . " linje " . __LINE__);
			} else {
				db_modify("insert into crm_deals (konto_id, title, amount, stage, expected_close, ansat_id, state, stage_since, created) values "
					. "($cid, '$title', $amount, '$stage', $closeSql, $own, 'open', '$today', '$today')", __FILE__ . " linje " . __LINE__);
				$id = crm_last_id('crm_deals', $cid);
				crm_insert_entry($cid, 'deal', $me, 0, crm_t('Sag oprettet: »%s«', trim((string) $in['t'])), null);
				$prevOwn = 0;
			}
			if ($own !== $prevOwn) crm_notif_drop("crm:deal:$id:owner");
			if ($own !== $me && $own !== $prevOwn) {
				crm_notify_deal(array('id' => $id, 'konto_id' => $cid, 'title' => crm_in_raw($in['t'] ?? ''), 'amount' => $amount, 'ansat_id' => $own), 'owner');
			}
			if (array_key_exists('with', $in)) {
				list($add, $del) = crm_set_participants('d', $id, crm_emp_ids($in['with'], $own));
				foreach ($add as $a) if ($a !== $me) crm_notify_deal_member($id, $a);
				foreach ($del as $a) crm_notif_drop("crm:dm:$id:$a");
			}
			return array('id' => $id);

		case 'move_deal':
			$id = (int) $in['id'];
			$d = db_fetch_array(db_select("select * from crm_deals where id = $id", __FILE__ . " linje " . __LINE__));
			if (!$d) return array('error' => 'deal');
			$before = array('stage' => $d['stage'], 'state' => $d['state'], 'stage_since' => $d['stage_since'], 'closed' => $d['closed'], 'lost_reason' => crm_out($d['lost_reason']));
			$today = date('Y-m-d');
			if (!empty($in['state'])) {
				$state = in_array($in['state'], array('won', 'lost', 'open'), true) ? $in['state'] : 'open';
				$reason = crm_in($in['reason'] ?? '');
				db_modify("update crm_deals set state = '$state', closed = " . ($state === 'open' ? 'NULL' : "'$today'") . ", lost_reason = '$reason' where id = $id", __FILE__ . " linje " . __LINE__);
				$label = $state === 'won' ? crm_t('vundet') : ($state === 'lost' ? crm_t('tabt') . (trim((string) ($in['reason'] ?? '')) !== '' ? ' – ' . trim((string) $in['reason']) : '') : crm_t('genåbnet'));
				if ($state === 'open') {
					crm_notif_drop("crm:deal:$id:won");
					crm_notif_drop("crm:deal:$id:lost");
				} elseif ((int) $d['ansat_id'] !== $me) {
					crm_notify_deal($d, $state);
				}
			} else {
				$stage = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $in['st']);
				db_modify("update crm_deals set stage = '$stage', stage_since = '$today' where id = $id", __FILE__ . " linje " . __LINE__);
				$label = crm_status_name((string) $d['stage']) . ' → ' . crm_status_name($stage);
			}
			$hid = crm_insert_entry((int) $d['konto_id'], 'deal', $me, 0, crm_t('Sag »%s«: %s', crm_out($d['title']), $label), null);
			return array('id' => $id, 'hid' => $hid, 'before' => $before);

		case 'restore_deal':
			$id = (int) $in['id'];
			$b = (array) $in['before'];
			$stage = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($b['stage'] ?? ''));
			$state = in_array($b['state'] ?? '', array('won', 'lost', 'open'), true) ? $b['state'] : 'open';
			$since = crm_date($b['stage_since'] ?? '');
			$closed = crm_date($b['closed'] ?? '');
			db_modify("update crm_deals set stage = '$stage', state = '$state', stage_since = " . ($since ? "'$since'" : 'NULL') . ", closed = " . ($closed ? "'$closed'" : 'NULL') . ", lost_reason = '" . crm_in($b['lost_reason'] ?? '') . "' where id = $id", __FILE__ . " linje " . __LINE__);
			if (!empty($in['remove'])) {
				db_modify("delete from historik where id = " . (int) $in['remove'] . " and type = 'deal'", __FILE__ . " linje " . __LINE__);
			}
			if ($state === 'open') {
				crm_notif_drop("crm:deal:$id:won");
				crm_notif_drop("crm:deal:$id:lost");
			}
			return array('id' => $id);

		case 'delete_deal':
			// soft: state 'deleted' (never shown), logged in the history, undone with restore_deal (review R30)
			$id = (int) $in['id'];
			$d = db_fetch_array(db_select("select * from crm_deals where id = $id", __FILE__ . " linje " . __LINE__));
			if (!$d) return array('error' => 'deal');
			$before = array('stage' => $d['stage'], 'state' => $d['state'], 'stage_since' => $d['stage_since'], 'closed' => $d['closed'], 'lost_reason' => crm_out($d['lost_reason']));
			db_modify("update crm_deals set state = 'deleted', closed = '" . date('Y-m-d') . "' where id = $id", __FILE__ . " linje " . __LINE__);
			crm_notif_drop("crm:deal:$id:%");
			crm_notif_drop("crm:dm:$id:%");
			$hid = crm_insert_entry((int) $d['konto_id'], 'deal', $me, 0, crm_t('Sag »%s«: %s', crm_out($d['title']), crm_t('slettet')), null);
			return array('id' => $id, 'hid' => $hid, 'before' => $before);

		case 'save_meeting':
			// 20261029 a meeting with a time, colleagues and (when ticked) calendar invitations; $in['id'] edits one
			$hid = (int) ($in['id'] ?? 0);
			$cid = (int) ($in['cid'] ?? 0);
			$date = crm_date($in['date'] ?? '');
			$start = crm_time($in['start'] ?? '');
			$dur = max(5, min(720, (int) ($in['dur'] ?? 60)));
			$owner = (int) ($in['owner'] ?? 0) ?: $me;
			if (!$cid || !$date || !$start || $owner <= 0) return array('error' => 'fields');
			$with = crm_emp_ids($in['with'] ?? array(), $owner);
			$to = (int) ($in['to'] ?? 0);
			$note = trim((string) ($in['note'] ?? '')) !== '' ? (string) $in['note'] : crm_t('Møde');
			$team = !empty($in['invite']);
			$contact = !empty($in['inviteContact']) && $to > 0;
			if ($hid) {
				$old = db_fetch_array(db_select("select h.konto_id, h.ansat_id, h.kontaktes, m.start_time, m.duration from historik h join crm_meetings m on m.historik_id = h.id where h.id = $hid", __FILE__ . " linje " . __LINE__));
				if (!$old) return array('error' => 'gone');
				$cid = (int) $old['konto_id'];
				$before = crm_meeting_people($hid);
				db_modify("update historik set notat = '" . crm_in($note) . "', kontaktes = '$date', ansat_id = $owner, kontakt_id = $to, type = 'moede' where id = $hid", __FILE__ . " linje " . __LINE__);
				db_modify("update crm_meetings set start_time = '$start', duration = $dur where historik_id = $hid", __FILE__ . " linje " . __LINE__);
				crm_set_participants('h', $hid, $with);
				$moved = (string) $old['kontaktes'] !== $date || (string) $old['start_time'] !== $start || (int) $old['duration'] !== $dur;
				$now = crm_meeting_people($hid);
				foreach ($now as $p) {
					if (!in_array($p, $before, true)) crm_notify_meeting($hid, $p);
					elseif ($moved) crm_notify_meeting($hid, $p, true);
				}
				$gone = array_values(array_diff($before, $now));
				foreach ($gone as $p) crm_notif_drop("crm:mt:$hid:$p");
				$mails = 0;
				if ($team && $gone) $mails += crm_queue_invites($hid, 'CANCEL', true, false, $gone);
				if ($team || $contact) $mails += crm_queue_invites($hid, 'REQUEST', $team, $contact);
				crm_sync_customer($cid);
				return array('id' => $hid, 'mails' => $mails);
			}
			crm_materialize_open($cid);
			$hid = crm_insert_entry($cid, 'moede', $owner, $to, $note, $date, false, '', $me);
			if (!$hid) return array('error' => 'fields');
			db_modify("insert into crm_meetings (historik_id, start_time, duration, seq, invite_team, invite_contact) values ($hid, '$start', $dur, 0, 0, 0)", __FILE__ . " linje " . __LINE__);
			crm_set_participants('h', $hid, $with);
			crm_sync_customer($cid);
			foreach (crm_meeting_people($hid) as $p) crm_notify_meeting($hid, $p);
			$mails = ($team || $contact) ? crm_queue_invites($hid, 'REQUEST', $team, $contact) : 0;
			return array('id' => $hid, 'mails' => $mails);

		case 'send_invites':
			// the invitations again after a move (offered in the page's message, never sent by the move itself)
			$hid = (int) ($in['id'] ?? 0);
			$mt = crm_meetings_for(array($hid));
			if (!isset($mt[$hid])) return array('error' => 'gone');
			$inv = $mt[$hid]['inv'] ?: 1;
			return array('id' => $hid, 'mails' => crm_queue_invites($hid, 'REQUEST', (bool) ($inv & 1), (bool) ($inv & 2)));

		case 'customer':
			// one customer with its history, for a drawer on a customer outside the loaded set (picker hit, bell link; review R53/R77)
			$cid = (int) $in['id'];
			$c = crm_customers("a.id = $cid");
			if (!isset($c[$cid])) return array('error' => 'customer');
			return array('customer' => $c[$cid], 'hist' => crm_history(array($cid), true), 'deals' => crm_deals($cid));

		case 'save_groups':
			if (!empty($in['rev']) && (string) $in['rev'] !== crm_settings_rev()) return array('error' => 'stale');   // review R50
			// statuses first (new ones get their number), then the groups that may point at them
			$map = array();
			if (isset($in['statuses'])) {
				$map = crm_save_statuses((array) $in['statuses']);
				if (is_string($map)) return array('error' => $map);
			}
			$valid = array_map(function ($s) { return $s['id']; }, crm_statuses());
			$fix = function ($x) use ($map, $valid) {
				$x = (string) $x;
				if (isset($map[$x])) $x = $map[$x];
				$x = preg_replace('/[^0-9A-Za-z_-]/', '', $x);
				return in_array($x, $valid, true) ? $x : '';
			};
			$keep = array();
			$pos = 1;
			foreach ((array) $in['groups'] as $g) {
				$g = (array) $g;
				$name = crm_in($g['n'] ?? '');
				$s = implode(chr(9), array_values(array_filter(array_map($fix, (array) ($g['s'] ?? array())), 'strlen')));
				$won = $fix($g['won'] ?? '');
				$lost = $fix($g['lost'] ?? '');
				$gid = (int) ($g['id'] ?? 0);
				if ($gid > 0) {
					db_modify("update grupper set box1 = '$name', box2 = '$s', box3 = '$won', box4 = '$lost', kodenr = '$pos' where id = $gid and art = 'SKN'", __FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into grupper (art, beskrivelse, box1, box2, box3, box4, kodenr) values ('SKN', 'Status knapper (CRM)', '$name', '$s', '$won', '$lost', '$pos')", __FILE__ . " linje " . __LINE__);
					$r = db_fetch_array(db_select("select max(id) as id from grupper where art = 'SKN'", __FILE__ . " linje " . __LINE__));
					$gid = (int) $r['id'];
				}
				$keep[] = $gid;
				$pos++;
			}
			db_modify("delete from grupper where art = 'SKN' and id not in (" . crm_ids($keep) . ")", __FILE__ . " linje " . __LINE__);
			update_settings_value("antalStatusKnapper", "crmvisning", count($keep), "Number of status buttons");
			if (function_exists('audit_log')) audit_log('crm_groups', crm_t('Statusser og statusgrupper er gemt') . ' (' . count($keep) . ')');
			return array('n' => count($keep));
	}
	return array('error' => 'action');
}

} // function_exists

<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/crmFunctions.php --- lap 5.0.0 --- 2026.10.20 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261020 ASR CRM redesign (prototype_crm.html v1.1): schema, the data behind debitor/crm.php and the actions
//              of debitor/crmApi.php.
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
//
// Which customers the page loads (crm_customer_scope): those with a status in one of the status groups, a CRM entry,
// an open deal or a next-contact date - at most CRM_MAX_CUSTOMERS, the most recently active first. In most companies
// every customer has a status, so without the cap the page would load the whole customer file. Customers outside
// the loaded set are reached through the server-side search (Ny opfølgning / Ny handel).
//
// Open follow-up: kontaktes set and completed false. Rows written by the old pages have completed NULL; such a row
// counts as open only while it is the customer's newest row WITH a date (the old model kept one "next contact" per
// customer: a newer dated row replaced it, a dateless note did not). crm_open_sql() is the one definition, and
// crm_migrate_legacy_followups() makes the old rows explicit once. 20261024 ASR after the customer-perspective review
// (CRM_review_consolidated_2026-10-23_EN.md): author_id on historik, soft-deleted deals, amounts with ore, currency
// conversion, scope ordered by open work first.

if (!function_exists('crm_ensure_schema')) {

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

/**
 * Idempotent; called from includes/betweenUpdates.php at login and from debitor/crm.php (first use without a new login).
 */
function crm_ensure_schema(): void
{
	if (!crm_col_exists('historik', 'completed')) {
		db_modify("ALTER TABLE historik ADD COLUMN completed boolean", __FILE__ . " linje " . __LINE__);
	}
	if (!crm_col_exists('historik', 'type')) {
		db_modify("ALTER TABLE historik ADD COLUMN type varchar(10)", __FILE__ . " linje " . __LINE__);
	}
	if (!crm_col_exists('crm_deals', 'id')) {
		if (crm_is_mysql()) {
			db_modify("CREATE TABLE crm_deals (
				id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				konto_id INT NOT NULL,
				title VARCHAR(120) NOT NULL,
				amount DECIMAL(15,2) DEFAULT 0,
				stage VARCHAR(10),
				expected_close DATE NULL,
				ansat_id INT NULL,
				state VARCHAR(10) NOT NULL DEFAULT 'open',
				stage_since DATE NULL,
				created DATE NULL,
				closed DATE NULL,
				lost_reason TEXT,
				INDEX crm_deals_konto_idx (konto_id)
			)", __FILE__ . " linje " . __LINE__);
		} else {
			db_modify("CREATE TABLE crm_deals (
				id SERIAL PRIMARY KEY,
				konto_id integer NOT NULL,
				title varchar(120) NOT NULL,
				amount numeric(15,2) DEFAULT 0,
				stage varchar(10),
				expected_close date,
				ansat_id integer,
				state varchar(10) NOT NULL DEFAULT 'open',
				stage_since date,
				created date,
				closed date,
				lost_reason text
			)", __FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX crm_deals_konto_idx ON crm_deals (konto_id)", __FILE__ . " linje " . __LINE__);
		}
	}
	// 20261020 ASR the offer/order made from a deal (ordre.php?..&crm_deal=<id> sets it at creation)
	if (!crm_col_exists('crm_deals', 'ordre_id')) {
		db_modify("ALTER TABLE crm_deals ADD COLUMN ordre_id integer", __FILE__ . " linje " . __LINE__);
	}
	// 20261024 ASR review R4: who wrote the entry (ansat_id stays the follow-up's owner, as the old pages read it)
	if (!crm_col_exists('historik', 'author_id')) {
		db_modify("ALTER TABLE historik ADD COLUMN author_id integer", __FILE__ . " linje " . __LINE__);
	}
	// 20261026 ASR review R13/R16: when a follow-up was marked done (the "done in the last 14 days" list, the timeline)
	if (!crm_col_exists('historik', 'completed_at')) {
		db_modify("ALTER TABLE historik ADD COLUMN completed_at date", __FILE__ . " linje " . __LINE__);
	}
	// 20261024 ASR review R56: historik is read per customer on every CRM request; the table had no index on konto_id
	if (!crm_is_mysql()) {
		if (!db_fetch_array(db_select("select indexname from pg_indexes where tablename = 'historik' and indexname = 'historik_konto_idx'", __FILE__ . " linje " . __LINE__))) {
			db_modify("CREATE INDEX historik_konto_idx ON historik (konto_id)", __FILE__ . " linje " . __LINE__);
		}
	} elseif (!db_fetch_array(db_select("show index from historik where Key_name = 'historik_konto_idx'", __FILE__ . " linje " . __LINE__))) {
		db_modify("CREATE INDEX historik_konto_idx ON historik (konto_id)", __FILE__ . " linje " . __LINE__);
	}
	crm_migrate_legacy_followups();
}

/**
 * 20261024 ASR review R22. Rows written by the old CRM pages have completed NULL. The old model kept one "next contact"
 * per customer: a newer row WITH a next-contact date replaced it, a dateless note did not. Once, we make that explicit:
 * every NULL row with a date that is not the customer's newest dated row becomes done (TRUE). The newest dated row stays
 * NULL and is read as open by crm_open_sql(); later deletes and restores can then no longer flip old rows between open
 * and done. Guarded by a settings flag so it runs once per company.
 */
function crm_migrate_legacy_followups(): void
{
	if (!function_exists('get_settings_value') || !function_exists('update_settings_value')) {
		return;
	}
	if ((string) get_settings_value('crm_legacy_followups', 'crm', '') === '1') {
		return;
	}
	if (crm_is_mysql()) {
		db_modify("update historik h join (select konto_id, max(id) as m from historik where kontaktes is not null group by konto_id) x on x.konto_id = h.konto_id "
			. "set h.completed = TRUE where h.completed is null and h.kontaktes is not null and h.id <> x.m", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("update historik h set completed = TRUE where h.completed is null and h.kontaktes is not null "
			. "and h.id <> (select max(h2.id) from historik h2 where h2.konto_id = h.konto_id and h2.kontaktes is not null)", __FILE__ . " linje " . __LINE__);
	}
	update_settings_value('crm_legacy_followups', 'crm', '1', 'CRM: legacy follow-ups made explicit (review R22)');
}

/**
 * The one definition of "open follow-up", used by the page, the bell and the sync of adresser.kontaktes:
 * completed FALSE, or a legacy NULL row with a date that is the customer's newest dated row (see the migration above).
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

// ------------------------------------------------------------------ texts (tekster.csv 7300-7509)

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
		7300 => 'i dag',
		7301 => 'i morgen',
		7302 => 'i går',
		7303 => 'for %s dage siden',
		7304 => '%s kr',
		7305 => 'Ingen status',
		7306 => 'Dig',
		7307 => 'dig',
		7308 => 'Note',
		7309 => 'Opkald',
		7310 => 'Mail',
		7311 => 'Møde',
		7312 => 'Status',
		7313 => 'Handel',
		7314 => 'Kunne ikke gemme (%s). Prøv igen.',
		7315 => 'Opfølgning',
		7316 => '%s forsinket',
		7317 => 'Pipeline',
		7318 => 'Kalender',
		7319 => 'Kunder',
		7320 => 'Aktivitet',
		7321 => 'Forsinket',
		7322 => 'I dag',
		7323 => 'I morgen',
		7324 => 'Resten af ugen',
		7325 => 'Næste uge',
		7326 => 'Senere',
		7327 => 'Markér som ikke gjort',
		7328 => 'Markér som gjort',
		7329 => 'Gjort',
		7330 => 'Udsæt',
		7331 => 'Åbn',
		7332 => 'Kunde · kontakt',
		7333 => 'Ansvarlig',
		7334 => 'Dato',
		7335 => 'Ingen åbne opfølgninger',
		7336 => 'Prøv at fjerne filtrene.',
		7337 => 'Opret en med »Ny opfølgning« eller fra en kunde.',
		7338 => 'Skjul %s gjort de sidste 14 dage',
		7339 => 'Vis %s gjort de sidste 14 dage',
		7340 => 'Alle statusser',
		7341 => 'ny',
		7342 => '%s år',
		7343 => '%s d',
		7344 => '%s åbne handler',
		7345 => 'Ingen opfølgning',
		7346 => 'I %s siden %s',
		7347 => 'Ingen statusser at vise',
		7348 => 'Kundestatusser oprettes på debitorkortet (feltet Status). Statusgrupper sættes op under tandhjulet.',
		7349 => '%s kunder',
		7350 => 'åbne handler %s',
		7351 => '%s med forsinket opfølgning',
		7352 => '%s uden opfølgning',
		7353 => 'Statusgruppe',
		7354 => 'Vundet',
		7355 => 'Tabt',
		7356 => 'Ny opfølgning %s',
		7357 => '+%s mere',
		7358 => 'Uge %s',
		7359 => 'Forrige',
		7360 => 'Næste',
		7361 => 'Åben',
		7362 => 'Træk for at flytte',
		7363 => 'Måned',
		7364 => 'Uge',
		7365 => 'Alle',
		7366 => 'Uden gruppe',
		7367 => 'Alle i %s',
		7368 => 'Kunde',
		7369 => 'Kontaktperson',
		7370 => 'Seneste kontakt',
		7371 => 'Næste opfølgning',
		7372 => 'Omsætning i år',
		7373 => 'Ingen',
		7374 => 'Ingen kunder',
		7375 => 'Ingen kunder matcher filtrene.',
		7376 => 'Kunder kommer med i CRM, når de får en status, en note eller en handel. Brug »Ny opfølgning« for at finde en kunde.',
		7377 => 'Opfølgning %s · gjort',
		7378 => 'Opfølgning %s',
		7379 => 'Ukendt',
		7380 => 'Ret',
		7381 => 'Slet',
		7382 => 'I går',
		7383 => 'Ingen aktivitet',
		7384 => 'Ingen henvendelser matcher filtrene.',
		7385 => 'Om 1 uge',
		7386 => 'Om 2 uger',
		7387 => 'Om 1 måned',
		7388 => 'med',
		7389 => '(ingen)',
		7390 => 'Hvad blev der talt om?',
		7391 => 'Vælg dato',
		7392 => 'til',
		7393 => 'mig',
		7394 => 'Markér opfølgningen fra %s som gjort',
		7395 => 'Gem',
		7396 => 'Kunde %s',
		7397 => 'ansvarlig: %s',
		7398 => 'Luk (Esc)',
		7399 => 'Opret handel',
		7400 => 'Opret tilbud',
		7401 => 'Opret ordre',
		7402 => 'Debitorkort',
		7403 => 'Jobkort',
		7404 => 'Kunde siden',
		7405 => 'Åbne poster',
		7406 => '%s forfalden',
		7407 => 'Historik',
		7408 => 'Handler og ordrer',
		7409 => 'Kontaktpersoner',
		7410 => 'forsinket',
		7411 => 'Ingen historik endnu',
		7412 => 'Skriv den første note ovenfor.',
		7413 => 'Ny note',
		7414 => 'Ingen kontaktpersoner',
		7415 => '+ Tilføj kontaktperson på debitorkortet',
		7416 => 'forventet lukket %s',
		7417 => 'Vundet %s',
		7418 => 'Tabt %s',
		7419 => 'Ingen handler',
		7420 => '+ Ny handel',
		7421 => 'Åbne tilbud og ordrer',
		7422 => 'delvist leveret',
		7423 => 'handel: %s',
		7424 => 'Ingen åbne tilbud eller ordrer.',
		7425 => '+ Opret tilbud',
		7426 => '+ Opret ordre',
		7427 => 'Tilbud kan ikke oprettes, fordi »Anvend hurtigfakturering« er slået til (Indstillinger → Ordrer).',
		7428 => 'Gemt',
		7429 => 'opfølgning %s',
		7430 => 'forrige opfølgning markeret som gjort',
		7431 => 'Søg kunde, kundenummer eller kontaktperson',
		7432 => 'Opret kunden under Debitor → Konti.',
		7433 => 'Ny opfølgning',
		7434 => 'Vælg kunden – du kan også søge på kontaktperson eller kundenummer.',
		7435 => 'Skift kunde',
		7436 => 'Faktura %s',
		7437 => 'Tilbud %s',
		7438 => 'Ordre %s',
		7439 => 'faktureret',
		7440 => 'ordre',
		7441 => 'tilbud',
		7442 => 'Brug beløbet fra tilbuddet: %s',
		7443 => 'Brug beløbet fra ordren: %s',
		7444 => 'Ordren er faktureret – markér handlen som vundet',
		7445 => 'Intet tilbud eller ordre til handlen endnu',
		7446 => 'Ret handel',
		7447 => 'Ny handel',
		7448 => 'Titel',
		7449 => 'Fx Saldi Plus, 3 brugere',
		7450 => 'Beløb (kr)',
		7451 => 'Forventet lukket',
		7452 => 'Stadie',
		7453 => 'Mig',
		7454 => 'Slet handel',
		7455 => 'Annullér',
		7456 => 'Handlen er slettet',
		7457 => 'vundet',
		7458 => 'tabt',
		7459 => 'Handlen er gemt',
		7460 => 'Handel oprettet i %s',
		7461 => 'Hvilken kunde gælder handlen?',
		7462 => 'Pris',
		7463 => 'Valgte en konkurrent',
		7464 => 'Intet behov',
		7465 => 'Forkert tidspunkt',
		7466 => 'Ingen svar',
		7467 => 'Hvorfor blev »%s« tabt?',
		7468 => 'Valgfrit. Over tid viser det, hvorfor I taber handler.',
		7469 => 'Bemærkning',
		7470 => 'Spring over',
		7471 => 'Markér som tabt',
		7472 => 'Ret %s fra %s',
		7473 => 'Type',
		7474 => 'Med',
		7475 => 'Noten er rettet',
		7476 => 'Slet %s fra %s?',
		7477 => 'Slettet',
		7478 => '%s markeret som gjort',
		7479 => '%s er åben igen',
		7480 => 'Om 2 dage',
		7481 => 'Næste mandag',
		7482 => 'Udsat til %s',
		7483 => 'ingen status',
		7484 => 'Opfølgning om 1 uge',
		7485 => 'Følg op – %s',
		7486 => 'Opfølgning sat til %s',
		7487 => 'Sæt %s som %s',
		7488 => 'vundet handel',
		7489 => 'Der er ingen kundestatusser endnu. De oprettes på debitorkortet (feltet Status).',
		7490 => 'Gruppenavn',
		7491 => 'Fjern',
		7492 => 'Fortrudt',
		7493 => 'Din bruger er ikke knyttet til en medarbejder, så »Mine« og »Dig« virker ikke. Knyt brugeren til en medarbejder under Indstillinger → Brugere.',
		7494 => 'Der er ingen medarbejdere under Indstillinger → Stamdata, så opfølgninger kan ikke fordeles.',
		7495 => 'Ny gruppe',
		7496 => 'Statusgrupperne er gemt',
		7497 => '%s flyttet til %s',
		7498 => 'Handel oprettet: »%s«',
		7499 => 'genåbnet',
		7500 => 'Handel »%s«: %s',
		7501 => 'Salg til %s',
		7502 => 'Handel oprettet ud fra kundens status: »%s«',
		7503 => 'Søg',
		7504 => 'Kun mine',
		7505 => 'Ny opfølgning (N)',
		7506 => 'Tilpas CRM',
		7507 => 'Statusgrupper styrer filteret, statuslinjen under Kunder og tavlerne under Pipeline (én tavle pr. gruppe). Gælder hele firmaet.',
		7508 => '+ Tilføj statusgruppe',
		7509 => 'Fortryd',
		7510 => 'Viser de %s senest aktive kunder. Andre kunder findes med søgningen under »Ny opfølgning« og »Ny handel«.',
		7511 => 'Kunden er måske ikke blandt de %s viste. Søg via »Ny opfølgning«.',
		// 7512 'CRM' is the bell type label (notif_types(), through findtekst)
		7513 => 'Opfølgninger i dag',
		7514 => '%s opfølgninger i dag · %s forsinkede',
		7515 => 'Ny opfølgning til dig',
		7516 => 'Handel tildelt dig',
		7517 => 'Handel vundet',
		7518 => 'Handel tabt',
		7519 => 'fra %s',
		7520 => 'Hvis opfølgninger og kunder',
		7521 => 'Alle opfølgninger og kunder i regnskabet',
		7522 => 'Kun dem, du er ansvarlig for. Valget huskes til næste gang.',
		7523 => 'Mine',
		7524 => 'Ny',
		7525 => 'Opret ny opfølgning, handel, tilbud eller ordre',
		7526 => 'Tilbud',
		7527 => 'Ordre',
		7528 => 'Nyt tilbud',
		7529 => 'Ny ordre',
		7530 => 'Hvilken kunde gælder det?',
		7531 => 'Statusser',
		7532 => 'Statusgrupper',
		7533 => '+ Tilføj status',
		7534 => 'Ny status',
		7535 => 'Statusnavn',
		7536 => '%s i brug',
		7537 => 'Bruges af %s kunder og handler – kan ikke slettes',
		7538 => 'Slet status',
		7539 => 'Slet gruppe',
		7540 => 'Der er ingen kundestatusser endnu.',
		7541 => 'Giv alle statusser et navn.',
		7542 => 'Statusser og statusgrupper er gemt',
		7543 => 'Ny kunde',
		// 20261024 ASR after the customer-perspective review
		7544 => 'Du er logget ud – genindlæs siden og prøv igen.',
		7545 => 'Genindlæs',
		7546 => 'Du har ikke rettighed til at ændre i CRM.',
		7547 => 'Statussen er i brug og kan ikke slettes.',
		7548 => 'Beløbet kunne ikke læses – skriv fx 12.500,50.',
		7549 => 'Ingen forbindelse – ændringen blev ikke gemt.',
		7550 => 'Kunne ikke gemme. Prøv igen.',
		7551 => '%s opfølgninger fra det gamle CRM er mere end 90 dage forsinkede.',
		7552 => 'Markér dem som gjort',
		7553 => 'Ikke nu',
		7554 => '%s opfølgninger fra det gamle CRM (mere end 90 dage forsinkede) markeret som gjort',
		7555 => 'slettet',
		7556 => 'Slet handlen »%s«?',
		7557 => 'Handlen, dens beløb og koblingen til tilbud eller ordre forsvinder fra CRM. Du kan fortryde fra beskeden nederst.',
		7558 => 'Kunden står ikke på tavlen – sæt status til %s',
		7559 => 'Opsæt statusser først (tandhjulet øverst til højre).',
		7560 => 'Markér %s åbne handler som %s',
		7561 => 'Flyt også handlen til %s',
		7562 => '– vælg –',
		7563 => 'Vælg, hvem opfølgningen er til.',
		7564 => 'Din bruger er ikke knyttet til en medarbejder: »Mine« virker ikke, dine noter får ingen afsender, og du skal selv vælge, hvem en opfølgning er til. Bed en administrator knytte brugeren under Indstillinger → Brugere.',
		7565 => 'Beløb (kr, ekskl. moms)',
		7566 => 'ekskl. moms',
		7567 => 'Søg blandt alle kunder',
		7568 => 'Fundet uden for listen',
		7569 => 'Markér handlen som %s',
		7570 => 'Mandag',
		7571 => 'Handel »%s« slettet',
		7572 => 'Genåbn',
		7573 => '%s kunder har en status, der ikke er med i en statusgruppe, og vises derfor ikke i CRM.',
		7574 => 'Ansvarlig: %s → %s',
		7575 => 'Der er ingen noter eller opkald endnu.',
		7576 => 'Du kan se CRM, men ændringer kræver skriveadgang til Debitor → Konti.',
		7577 => 'Faktureret – markér vundet?',
		7578 => 'Flyt %s åbne opfølgninger til %s',
		7579 => 'Flyt ned',
		7580 => 'Flyt op',
		7581 => 'Forhandling',
		7582 => 'Forslag: en salgstavle med Henvendelse › Tilbud sendt › Forhandling, hvor Vundet giver status Kunde og Tabt giver status Tabt.',
		7583 => 'Henvendelse',
		7584 => 'Hver gruppe bliver en tavle under Pipeline. Vundet → og Tabt → er den status, kunden får, når kortet trækkes til Vundet eller Tabt.',
		7585 => 'Hvilken handel gælder ordren?',
		7586 => 'Hvilken handel gælder tilbuddet?',
		7587 => 'Ingen handel',
		7588 => 'Ingen kontakt endnu',
		7589 => 'Ingen opfølgninger matcher »%s«.',
		7590 => 'Ingen statusgrupper endnu.',
		7591 => 'Knyt til handel',
		7592 => 'Knyttet til handlen »%s«',
		7593 => 'Luk',
		7594 => 'Opfølgningerne er flyttet',
		7595 => 'Opsæt statusgrupper',
		7596 => 'Opsæt statusser og statusgrupper under tandhjulet.',
		7597 => 'Salg',
		7598 => 'Salgstavlen er oprettet',
		7599 => 'Seneste kontakt %s',
		7600 => 'Skjul %s kunder uden opfølgning',
		7601 => 'Start med en salgstavle',
		7602 => 'Statusserne er de samme som feltet Status på debitorkortet. Rækkefølgen her er rækkefølgen på tavlen.',
		7603 => 'Til',
		7604 => 'Tilbud sendt',
		7605 => 'Tøm feltet for at fjerne påmindelsen – noten bliver stående',
		7606 => 'Vis %s kunder uden opfølgning',
		7607 => 'Vis alle',
		7608 => 'Viser de 10 nyeste.',
		7609 => 'ingen',
		7610 => 'Åbn tandhjulet',
		7611 => 'Vis 1 kunde uden opfølgning',
		7612 => 'Skjul 1 kunde uden opfølgning',
		7613 => 'Flyt opfølgningen til %s',
		7614 => '1 kunde har en status, der ikke er med i en statusgruppe, og vises derfor ikke i CRM.'
	);
}

/** Write access to the CRM = write on debitor.konti (the Debitor -> Konti right); the page and crmApi.php share it. */
function crm_can_write(): bool
{
	return function_exists('perm_can') ? perm_can('debitor.konti', 'write') : true;
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
	$cacheKey = 'crm_txt_' . $lang . '_' . count($ids) . '_' . max(array_keys($ids));
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

// ------------------------------------------------------------------ notifications (the bell, includes/notifications.php)
// Type 'crm' (can be switched off per user under Personlige indstillinger -> Notifikationer). The daily summary is
// generated when the bell polls (crm_notif_refresh() from notif_refresh()); the event items are written by the
// actions below, only for a user other than the acting one and only when the employee is linked to a user.

/** UTF-8 to the company's charset, unescaped (notif_upsert() escapes). index/notifications.php converts back. */
function crm_to_db(string $s): string
{
	global $db_encode;
	return ($db_encode === 'UTF8' || $s === '') ? $s : mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
}

function crm_user_for_ansat(int $ansatId): int
{
	static $map = array();
	if ($ansatId <= 0) {
		return 0;
	}
	if (!isset($map[$ansatId])) {
		$r = db_fetch_array(db_select("select id from brugere where ansat_id = $ansatId order by id limit 1", __FILE__ . " linje " . __LINE__));
		$map[$ansatId] = $r ? (int) $r['id'] : 0;
	}
	return $map[$ansatId];
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
 * A notification in the bell for the user linked to employee $ansatId - not when that is the acting user, and not
 * when the employee has no user. $key identifies the event: notif_upsert() refreshes instead of duplicating.
 */
function crm_notify(int $ansatId, string $key, string $title, string $body, string $link, ?string $expires): void
{
	global $bruger_id;
	include_once(__DIR__ . '/notifications.php');
	if (!function_exists('notif_ready') || !notif_ready()) {
		return;
	}
	$user = crm_user_for_ansat($ansatId);
	if ($user <= 0 || $user === (int) $bruger_id) {
		return;
	}
	// only to a user who may read the CRM (the Debitor right, as crm_notif_refresh checks it; review R69)
	$u = db_fetch_array(db_select("select rettigheder from brugere where id = $user", __FILE__ . " linje " . __LINE__));
	if (!$u || substr((string) $u['rettigheder'], 6, 1) < '1') {
		return;
	}
	notif_upsert($key, $user, 'crm', crm_to_db($title), crm_to_db($body), $link, $expires);
}

/** Remove the notifications of an event that is over (follow-up done or deleted, deal deleted or reopened). */
function crm_notif_drop(string $keyLike): void
{
	include_once(__DIR__ . '/notifications.php');
	if (!function_exists('notif_ready') || !notif_ready()) {
		return;
	}
	$k = db_escape_string($keyLike);
	db_modify("delete from notification_read where notification_id in (select id from notifications where source_key like '$k')", __FILE__ . " linje " . __LINE__);
	db_modify("delete from notifications where source_key like '$k'", __FILE__ . " linje " . __LINE__);
}

/** "Ny opfølgning til dig": a follow-up whose owner is another employee than the acting user. */
function crm_notify_followup(int $hid, int $cid, int $owner, string $due, string $note): void
{
	$c = db_fetch_array(db_select("select firmanavn from adresser where id = $cid", __FILE__ . " linje " . __LINE__));
	$body = crm_out($c ? (string) $c['firmanavn'] : '') . ': ' . mb_substr(trim($note), 0, 120)
		. ' · ' . crm_t('opfølgning %s', date('d-m-Y', strtotime($due))) . ' · ' . crm_t('fra %s', crm_actor_name());
	crm_notify($owner, 'crm:fu:' . $hid, crm_t('Ny opfølgning til dig'), $body, '/debitor/crm.php?tab=follow&drawer=' . $cid, date('Y-m-d', strtotime($due . ' +30 days')));
}

/** "Handel tildelt dig" / "Handel vundet" / "Handel tabt" for the deal's owner. */
function crm_notify_deal(array $d, string $what): void
{
	$id = (int) $d['id'];
	$cid = (int) $d['konto_id'];
	$c = db_fetch_array(db_select("select firmanavn from adresser where id = $cid", __FILE__ . " linje " . __LINE__));
	$amount = round((float) $d['amount']);
	$body = crm_out((string) $d['title']) . ' · ' . crm_out($c ? (string) $c['firmanavn'] : '')
		. ($amount ? ' · ' . crm_t('%s kr', number_format($amount, 0, ',', '.')) : '') . ' · ' . crm_t('fra %s', crm_actor_name());
	$title = $what === 'won' ? crm_t('Handel vundet') : ($what === 'lost' ? crm_t('Handel tabt') : crm_t('Handel tildelt dig'));
	crm_notify((int) $d['ansat_id'], "crm:deal:$id:$what", $title, $body, '/debitor/crm.php?tab=pipe&drawer=' . $cid . '&dtab=deals', date('Y-m-d', strtotime('+30 days')));
}

/**
 * The daily "Opfølgninger i dag" item for a user, from notif_refresh() (bell poll, daily digest). One item per day
 * (key crm:due:<date>), expired the day after; none when nothing is due. Needs the Debitor -> Konti right.
 */
function crm_notif_refresh(int $brugerId, string $rettigheder, int $sprogId): void
{
	if (substr($rettigheder, 6, 1) < '1' || !function_exists('notif_upsert') || !tbl_exists('crm_deals')) {
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
		. "where h.ansat_id = $ansat and h.kontaktes <= '$today' and " . crm_open_sql('h'), __FILE__ . " linje " . __LINE__));
	$n = $r ? (int) $r['n'] : 0;
	if ($n <= 0) {
		db_modify("delete from notification_read where notification_id in (select id from notifications where source_key = '$key' and user_id = $brugerId)", __FILE__ . " linje " . __LINE__);
		db_modify("delete from notifications where source_key = '$key' and user_id = $brugerId", __FILE__ . " linje " . __LINE__);
		return;
	}
	$late = (int) $r['late'];
	notif_upsert($key, $brugerId, 'crm', crm_to_db(crm_tl($sprogId, 'Opfølgninger i dag')),
		crm_to_db(crm_tl($sprogId, '%s opfølgninger i dag · %s forsinkede', $n - $late, $late)), '/debitor/crm.php?tab=follow', $today);
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
	$q = db_select("select status, count(*) as n from adresser where art = 'D' and status is not null and status != '' group by status", __FILE__ . " linje " . __LINE__);
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
	$max = 0;
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
		$out[] = array('id' => (int) $r['id'], 'n' => crm_out($r['navn']), 'i' => crm_out($r['initialer']));
	}
	return $out;
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
	$qtxt = "select a.id, a.kontonr, a.firmanavn, a.bynavn, a.tlf, a.email, a.status, a.kontoansvarlig, a.oprettet "
		. "from adresser a$join where a.art = 'D' and $where order by $order" . ($limit > 0 ? " limit $limit" : "");
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['id']] = array(
			'id'    => (int) $r['id'],
			'nr'    => crm_out($r['kontonr']),
			'n'     => crm_out($r['firmanavn']),
			'by'    => crm_out($r['bynavn']),
			'tlf'   => crm_out($r['tlf']),
			'mail'  => crm_out($r['email']),
			'st'    => trim((string) $r['status']),
			'own'   => (int) $r['kontoansvarlig'],
			'since' => (string) $r['oprettet'],
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
	$q = db_select("select konto_id, count(*) as n, sum(case when forfaldsdate < '$today' then 1 else 0 end) as late from openpost where konto_id in ($ids) and (udlignet is null or udlignet != '1') and amount > 0 group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['konto_id']]['inv'] = (int) $r['n'];
		$out[(int) $r['konto_id']]['late'] = (int) $r['late'];
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
	foreach (array('inframe', 'popup', 'tab', 'bgrp', 'drawer', 'dtab') as $k) {
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
		);
	}
	return $out;
}

/**
 * Everything the page needs. $extraId: a customer the page must show even when it is outside the loaded set (the
 * drawer named in the query, e.g. the customer just created from Kunder -> Ny kunde; review R54).
 */
function crm_dataset(int $extraId = 0): array
{
	global $ansat_id;
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
		'histFrom'  => crm_hist_from(),
		'dealFrom'  => crm_deal_from(),
		'today'     => date('Y-m-d'),
		'me'        => (int) $ansat_id,
		'canWrite'  => crm_can_write(),
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
	$r = db_fetch_array(db_select("select max(id) as id from $table where konto_id = $cid", __FILE__ . " linje " . __LINE__));
	return $r ? (int) $r['id'] : 0;
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
	$me = (int) $ansat_id;
	switch ($action) {
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
			if (!$due) {
				crm_notif_drop('crm:fu:' . $hid);
			} elseif ($newOwner > 0 && $newOwner !== (int) $old['ansat_id'] && $newOwner !== $me) {
				crm_notify_followup($hid, (int) $old['konto_id'], $newOwner, $due, (string) ($in['note'] ?? ''));
			}
			return array('id' => $hid);

		case 'set_owner':
			// review R15: the customer's responsible employee (adresser.kontoansvarlig), from the drawer
			$cid = (int) $in['cid'];
			$to = (int) ($in['owner'] ?? 0);
			$r = db_fetch_array(db_select("select kontoansvarlig from adresser where id = $cid and art = 'D'", __FILE__ . " linje " . __LINE__));
			if (!$r) return array('error' => 'customer');
			db_modify("update adresser set kontoansvarlig = " . ($to > 0 ? $to : 'NULL') . " where id = $cid", __FILE__ . " linje " . __LINE__);
			$from = (int) $r['kontoansvarlig'];
			$hid = crm_insert_entry($cid, 'status', $me, 0, crm_t('Ansvarlig: %s → %s', $from ? crm_employee_name($from) : crm_t('ingen'), $to ? crm_employee_name($to) : crm_t('ingen')), null);
			return array('from' => $from, 'hid' => $hid);

		case 'unset_owner':
			// undo of set_owner: the old responsible back and the logged line removed
			$cid = (int) $in['cid'];
			$to = (int) ($in['owner'] ?? 0);
			db_modify("update adresser set kontoansvarlig = " . ($to > 0 ? $to : 'NULL') . " where id = $cid and art = 'D'", __FILE__ . " linje " . __LINE__);
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
			// review R31: an existing quote/order of the customer attached to one of its deals that has none (undo: unlink_doc)
			$cid = (int) $in['cid'];
			$id = (int) $in['deal'];
			$oid = (int) $in['doc'];
			$o = db_fetch_array(db_select("select ordrenr, status from ordrer where id = $oid and konto_id = $cid and art = 'DO'", __FILE__ . " linje " . __LINE__));
			$d = db_fetch_array(db_select("select title, ordre_id from crm_deals where id = $id and konto_id = $cid", __FILE__ . " linje " . __LINE__));
			if (!$o || !$d) return array('error' => 'fields');
			if ((int) $d['ordre_id'] > 0) return array('error' => 'linked');
			db_modify("update crm_deals set ordre_id = $oid where id = $id", __FILE__ . " linje " . __LINE__);
			$doc = crm_t((int) $o['status'] === 0 ? 'Tilbud %s' : 'Ordre %s', (int) $o['ordrenr']);
			$hid = crm_insert_entry($cid, 'deal', $me, 0, crm_t('Handel »%s«: %s', crm_out($d['title']), $doc), null);
			return array('id' => $id, 'hid' => $hid);

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
			if ($done) crm_notif_drop('crm:fu:' . $hid);
			return array('id' => $hid);

		case 'set_due':
			$hid = (int) $in['id'];
			$due = crm_date($in['due'] ?? '');
			if (!$due) return array('error' => 'date');
			db_modify("update historik set kontaktes = '$due', completed = FALSE where id = $hid", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select konto_id, ansat_id, notat from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			if ($r) {
				crm_sync_customer((int) $r['konto_id']);
				if ((int) $r['ansat_id'] !== $me) crm_notify_followup($hid, (int) $r['konto_id'], (int) $r['ansat_id'], $due, crm_out($r['notat']));
			}
			return array('id' => $hid);

		case 'delete_entry':
			$hid = (int) $in['id'];
			$r = db_fetch_array(db_select("select * from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			if (!$r) return array('error' => 'gone');
			db_modify("delete from historik where id = $hid", __FILE__ . " linje " . __LINE__);
			crm_sync_customer((int) $r['konto_id']);
			crm_notif_drop('crm:fu:' . $hid);
			return array('row' => array('cid' => (int) $r['konto_id'], 'type' => (string) $r['type'], 'by' => (int) $r['ansat_id'], 'au' => (int) $r['author_id'], 'to' => (int) $r['kontakt_id'],
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
			}
			crm_sync_customer($cid);
			return array('id' => $id);

		case 'set_status':
			$cid = (int) $in['cid'];
			$to = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $in['status']);
			$r = db_fetch_array(db_select("select status from adresser where id = $cid and art = 'D'", __FILE__ . " linje " . __LINE__));
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
				crm_insert_entry($cid, 'deal', $me, 0, crm_t('Handel oprettet: »%s«', trim((string) $in['t'])), null);
				$prevOwn = 0;
			}
			if ($own !== $me && $own !== $prevOwn) {
				crm_notify_deal(array('id' => $id, 'konto_id' => $cid, 'title' => crm_in_raw($in['t'] ?? ''), 'amount' => $amount, 'ansat_id' => $own), 'owner');
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
			$hid = crm_insert_entry((int) $d['konto_id'], 'deal', $me, 0, crm_t('Handel »%s«: %s', crm_out($d['title']), $label), null);
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
			$hid = crm_insert_entry((int) $d['konto_id'], 'deal', $me, 0, crm_t('Handel »%s«: %s', crm_out($d['title']), crm_t('slettet')), null);
			return array('id' => $id, 'hid' => $hid, 'before' => $before);

		case 'customer':
			// one customer with its history, for a drawer on a customer outside the loaded set (picker hit, bell link; review R53/R77)
			$cid = (int) $in['id'];
			$c = crm_customers("a.id = $cid");
			if (!isset($c[$cid])) return array('error' => 'customer');
			return array('customer' => $c[$cid], 'hist' => crm_history(array($cid), true), 'deals' => crm_deals($cid));

		case 'save_groups':
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

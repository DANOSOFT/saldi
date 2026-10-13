<?php
@session_start();
$s_id=session_id();
// --- admin/bogholder_vejledning.php --- ver 5.1.0 --- 2026.10.13 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261013 CL/ASR New. The accountant panel's own guide, opened from "Vejledning" in the sidebar (replaces the link to
//                  Saldi's general getting-started page for partner users). One section per area of the panel with steps,
//                  a link straight to the place, and a note when the user lacks the right. Short questions and answers at
//                  the end. The texts are kept in one array so they can later feed tooltips or an assistant.

$css="../css/standard.css";
$title="Bogholderpanel";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("../includes/entitlements.php");
include("../includes/partnerPanel.php");
include("vr_ui.php");
include("bogholder_ui.php");
if ($db != $sqdb) { print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/logud.php\">"; exit; }
$me = bp_require_partner();
$cw = bp_cust_word(); $cw1 = bp_cust_word(false);

// [key, group, title, intro, steps[], link [text, href] | null, right | null]
$G = array(
	array('start', bp_t('Kom i gang','Getting started'), bp_t('Overblikket','The overview'),
		bp_t('Overblikket er panelets forside. Det viser, hvilke '.$cw.' der kræver noget af jer i dag, og giver genveje til det, I oftest gør.','The overview is the panel\'s front page. It shows which '.$cw.' need something from you today and gives shortcuts to what you do most.'),
		array(bp_t('Tallene øverst viser aktive regnskaber, prøveperioder og forhold, der kræver handling. Klik på et tal for at se de '.$cw.', det dækker.','The figures at the top show active accounts, trials and matters needing action. Click a figure to see the '.$cw.' it covers.'),
			bp_t('"Regnskaber der kræver arbejde" viser åbne kassekladder og bilag i dokumentpuljen. Klik på Åbn for at gå direkte til kladden eller puljen.','"Accounts with work waiting" shows open journals and documents in the pool. Click Open to go straight to the journal or the pool.'),
			bp_t('"Afventende handlinger" samler invitationer, adgangsanmodninger, prøveperioder tæt på udløb og opgraderinger, der venter på kunden.','"Pending actions" gathers invitations, access requests, trials close to ending and upgrades awaiting the customer.'),
			bp_t('Tallene for kladder og bilag læses fra '.$cw.'nes regnskaber hvert 10. minut. Brug "Opdatér nu" for at hente dem med det samme.','Journal and document figures are read from the '.$cw.'\' accounts every 10 minutes. Use "Refresh now" to fetch them at once.')),
		array(bp_t('Gå til overblikket','Go to the overview'), 'bogholder_overblik.php'), null),
	array('find', bp_t('Kom i gang','Getting started'), bp_t('Find og åbn en '.$cw1,'Find and open a '.$cw1),
		bp_t('Listen over '.$cw.' viser alle de regnskaber, du har adgang til, med pakke, betaler, status og arbejde der venter.','The '.$cw.' list shows every account you have access to, with plan, payer, status and work waiting.'),
		array(bp_t('Søg på navn, CVR eller regnskabsnummer i søgefeltet, og tryk Enter.','Search by name, CVR or account number and press Enter.'),
			bp_t('Brug fanerne og filtrene til at se fx kun prøveperioder, kun '.$cw.' med arbejde eller kun dem, firmaet betaler for.','Use the tabs and filters to see, for example, only trials, only '.$cw.' with work, or only those the firm pays for.'),
			bp_t('Klik på stjernen for at gøre en '.$cw1.' til favorit. Favoritter vises også på overblikket.','Click the star to make a '.$cw1.' a favourite. Favourites also appear on the overview.'),
			bp_t('Klik på navnet for regnskabskortet, eller på Åbn for at gå ind i regnskabet. Regnskabet åbner i en ny fane, så panelet bliver stående.','Click the name for the account card, or Open to enter the account. It opens in a new tab, so the panel stays open.')),
		array(bp_t('Gå til '.$cw,'Go to '.$cw), 'bogholder_panel.php'), 'work'),
	array('opret', bp_t(ucfirst($cw),ucfirst($cw)), bp_t('Opret et kunderegnskab','Create a customer account'),
		bp_t('Et nyt regnskab oprettes i kundens navn med standardkontoplan. Kunden ejer regnskabet, og firmaet får adgang med det samme.','A new account is created in the customer\'s name with the standard chart of accounts. The customer owns it, and the firm gets access at once.'),
		array(bp_t('Vælg Opret og tilknyt i menuen, og udfyld kundens navn og stamdata.','Choose Create and link in the menu and fill in the customer\'s name and details.'),
			bp_t('Vælg betalingsmodel: "Firmaet betaler" lægger Finans ind i jeres bogholderordning. "Kunden betaler selv" fakturerer kunden direkte, og Professionel og Business starter med 30 dages gratis prøve.','Choose the payment model: "The firm pays" puts Finans in your bookkeeper scheme. "The customer pays" invoices the customer directly, and Professionel and Business start with a 30-day free trial.'),
			bp_t('Vælg, hvordan kundens første bruger oprettes: invitation pr. e-mail, oprettet med det samme, eller senere.','Choose how the customer\'s first user is created: invitation by e-mail, created now, or later.'),
			bp_t('Vælg en ansvarlig medarbejder, og klik Opret kunderegnskab. Du lander på det nye regnskabs kort.','Choose a responsible employee and click Create customer account. You land on the new account\'s card.')),
		array(bp_t('Opret kunderegnskab','Create customer account'), 'bogholder_opret.php'), 'create'),
	array('tilknyt', bp_t(ucfirst($cw),ucfirst($cw)), bp_t('Tilknyt et eksisterende regnskab','Link an existing account'),
		bp_t('Har kunden allerede Saldi, beder I om adgang. Kunden godkender med ét klik, og intet andet ændres: ikke pakke, ikke betaler, ikke andre bogholderes adgang.','If the customer already has Saldi, you ask for access. The customer approves with one click, and nothing else changes: not the plan, not the payer, not other bookkeepers\' access.'),
		array(bp_t('Vælg Opret og tilknyt › Tilknyt eksisterende.','Choose Create and link › Link existing.'),
			bp_t('Søg på regnskabsnummer, det præcise regnskabsnavn eller CVR. Regnskabsnummeret står i kundens Saldi under Indstillinger.','Search by account number, the exact account name or CVR. The account number is in the customer\'s Saldi under Settings.'),
			bp_t('Skriv evt. en besked, og send anmodningen. Den går til regnskabets e-mail, eller til Saldi, hvis regnskabet ingen e-mail har.','Add a message if you like and send the request. It goes to the account\'s e-mail, or to Saldi if the account has none.'),
			bp_t('Følg status under Mit firma › Invitationer og anmodninger, hvor du også kan gensende eller trække anmodningen tilbage.','Follow the status under My firm › Invitations and requests, where you can also resend or withdraw it.')),
		array(bp_t('Tilknyt regnskab','Link account'), 'bogholder_opret.php?tab=tilknyt'), 'create'),
	array('brugere', bp_t('Regnskabskortet','The account card'), bp_t('Kundens brugere','The customer\'s users'),
		bp_t('På kortets fane Brugere styrer I kundens egne brugere. Firmaets medarbejdere tæller ikke i kundens brugergrænse, og i bogholderbetalt Finans er én kundebruger med uden merpris.','On the card\'s Users tab you manage the customer\'s own users. The firm\'s employees do not count against the customer\'s user limit, and firm-paid Finans includes one customer user at no extra cost.'),
		array(bp_t('Invitér en bruger pr. e-mail. Kunden vælger selv brugernavn og adgangskode.','Invite a user by e-mail. The customer chooses username and password.'),
			bp_t('Eller opret brugeren med det samme, hvis I selv giver kunden login.','Or create the user at once if you hand the login to the customer yourselves.'),
			bp_t('Luk en bruger, der ikke længere skal have adgang. Brugeren bevares og kan genåbnes.','Lock a user who should no longer have access. The user is kept and can be reopened.'),
			bp_t('Skal en anden overtage pladsen, så brug Udskift. Den nye bruger overtager den eksisterende plads, så grænsen ikke overskrides.','If someone else takes over, use Replace. The new user takes the existing seat, so the limit is not exceeded.')),
		array(bp_t('Vælg en '.$cw1,'Pick a '.$cw1), 'bogholder_panel.php'), 'users'),
	array('funktioner', bp_t('Regnskabskortet','The account card'), bp_t('Funktioner i kundens pakke','Features in the customer\'s plan'),
		bp_t('Fanen Funktioner viser, hvad kundens pakke indeholder. I kan gøre menuen enklere for kunden ved at slå inkluderede funktioner fra.','The Features tab shows what the customer\'s plan includes. You can simplify the menu for the customer by switching included features off.'),
		array(bp_t('Brug kontakten ud for en inkluderet funktion for at slå den fra eller til igen.','Use the switch next to an included feature to turn it off or on again.'),
			bp_t('Funktioner uden for pakken viser, hvilken pakke der kræves. Klik Foreslå opgradering for at sende et forslag til kunden.','Features outside the plan show which plan they need. Click Propose upgrade to send a proposal to the customer.')),
		array(bp_t('Vælg en '.$cw1,'Pick a '.$cw1), 'bogholder_panel.php'), null),
	array('opgrader', bp_t('Regnskabskortet','The account card'), bp_t('Abonnement og opgradering','Subscription and upgrade'),
		bp_t('Fanen Abonnement viser pakke, betaler, prøveperiode, betalingsstart og planlagte ændringer. Herfra foreslår I en større pakke.','The Subscription tab shows plan, payer, trial, billing start and scheduled changes. From here you propose a larger plan.'),
		array(bp_t('Vælg den nye pakke, og skriv en kort begrundelse til kunden.','Choose the new plan and write a short reason for the customer.'),
			bp_t('Før du sender, viser panelet kundens nye pris og, hvis firmaet betaler i dag, hvad det betyder for jeres mængderabat.','Before you send, the panel shows the customer\'s new price and, if the firm pays today, what it means for your volume discount.'),
			bp_t('Kunden godkender pr. e-mail. Intet koster noget, før kunden har godkendt.','The customer approves by e-mail. Nothing is charged before the customer approves.'),
			bp_t('Når kunden godkender en opgradering fra Finans, overtager kunden selv betalingen fremover.','When the customer approves an upgrade from Finans, the customer takes over payment from then on.')),
		null, 'subscription'),
	array('betaling', bp_t('Regnskabskortet','The account card'), bp_t('Betaling og bogholderordningen','Payment and the bookkeeper scheme'),
		bp_t('Et regnskab betales enten af firmaet i bogholderordningen (Finans med mængderabat) eller af kunden selv. Det kan skiftes begge veje under fanen Handlinger.','An account is paid either by the firm in the bookkeeper scheme (Finans with volume discount) or by the customer. It can be switched either way on the Actions tab.'),
		array(bp_t('"Overtag betalingen" flytter regnskabet ind i jeres ordning fra næste periode.','"Take over payment" moves the account into your scheme from next period.'),
			bp_t('"Kunden overtager betalingen" sender et forslag, som kunden skal acceptere pr. e-mail.','"The customer takes over payment" sends a proposal the customer must accept by e-mail.'),
			bp_t('Begge handlinger viser konsekvensen for kundens pris og jeres samlede pris, før du bekræfter.','Both actions show the consequence for the customer\'s price and your total before you confirm.'),
			bp_t('Under Mit firma › Priser ser du antal regnskaber i ordningen, samlet pris, gennemsnitspris og en beregner, der ikke ændrer noget.','Under My firm › Prices you see the number of accounts in the scheme, total, average and a calculator that changes nothing.')),
		array(bp_t('Se priser','See prices'), 'bogholder_firma.php?tab=priser'), 'prices'),
	array('ophor', bp_t('Regnskabskortet','The account card'), bp_t('Ophør og arkivering','Termination and archiving'),
		bp_t('Fanen Handlinger har separate handlinger, så det altid er tydeligt, hvad der sker. Hver handling viser konsekvenserne, før du bekræfter.','The Actions tab has separate actions, so it is always clear what happens. Each one shows the consequences before you confirm.'),
		array(bp_t('Afslut firmaets adgang: medarbejderne mister adgangen med det samme, men regnskab, kundens brugere og abonnement rører sig ikke.','End the firm\'s access: employees lose access at once, but the account, the customer\'s users and the subscription are untouched.'),
			bp_t('Opsig abonnementet: abonnementet ophører ved periodens udløb, og regnskabet lukkes derefter. Saldi bekræfter datoen.','Cancel the subscription: it ends at period end and the account is then closed. Saldi confirms the date.'),
			bp_t('Arkivér regnskabet: regnskabet lukkes for login og bevares som arkiv.','Archive the account: the account is closed for login and kept as an archive.'),
			bp_t('Betaler firmaet for regnskabet, skal betalingen skiftes eller abonnementet opsiges, før firmaets adgang kan afsluttes.','If the firm pays for the account, change payer or cancel before the firm\'s access can end.')),
		null, 'subscription'),
	array('medarbejdere', bp_t('Mit firma','My firm'), bp_t('Medarbejdere og rettigheder','Employees and rights'),
		bp_t('Under Mit firma › Medarbejdere styrer I, hvem der må hvad. Ejere har alle rettigheder.','Under My firm › Employees you control who may do what. Owners hold all rights.'),
		array(bp_t('Invitér en medarbejder pr. e-mail, eller opret medarbejderen direkte med brugernavn og adgangskode.','Invite an employee by e-mail, or create the employee directly with username and password.'),
			bp_t('Vælg rettigheder: Arbejde i regnskaber, Oprette kunder, Administrere brugere, Se priser og Ændre abonnementer.','Choose rights: Work in accounts, Create customers, Manage users, See prices and Change subscriptions.'),
			bp_t('Vælg, om medarbejderen ser alle firmaets '.$cw.' eller kun udvalgte.','Choose whether the employee sees all the firm\'s '.$cw.' or selected ones only.'),
			bp_t('Sæt en ansvarlig medarbejder på hver '.$cw1.' fra regnskabskortet. Ejeren kan filtrere overblik og liste på ansvarlig.','Set a responsible employee on each '.$cw1.' from the account card. The owner can filter the overview and list by responsible.'),
			bp_t('Deaktivér en medarbejder midlertidigt, eller fjern vedkommende fra firmaet.','Deactivate an employee temporarily, or remove them from the firm.')),
		array(bp_t('Gå til medarbejdere','Go to employees'), 'bogholder_firma.php?tab=medarbejdere'), 'users'),
	array('invitationer', bp_t('Mit firma','My firm'), bp_t('Invitationer og anmodninger','Invitations and requests'),
		bp_t('Alle udsendte invitationer og adgangsanmodninger samles ét sted med status.','All invitations and access requests sent are gathered in one place with their status.'),
		array(bp_t('"Afventer svar" betyder, at modtageren ikke har brugt linket endnu. Links gælder i 14 dage.','"Awaiting reply" means the recipient has not used the link yet. Links are valid for 14 days.'),
			bp_t('Gensend forlænger gyldigheden og sender mailen igen.','Resend extends validity and sends the mail again.'),
			bp_t('Tilbagekald gør linket ugyldigt med det samme.','Revoke makes the link invalid at once.')),
		array(bp_t('Gå til invitationer','Go to invitations'), 'bogholder_firma.php?tab=invitationer'), null),
	array('firma', bp_t('Mit firma','My firm'), bp_t('Firmaoplysninger, fakturaer og historik','Firm details, invoices and history'),
		bp_t('Under Mit firma ligger firmaets egne oplysninger, Saldis fakturaer til firmaet og en samlet historik.','My firm holds the firm\'s own details, Saldi\'s invoices to the firm and a combined history.'),
		array(bp_t('Firmaoplysninger: kontaktperson, adresse, faktura-e-mail og reference. Kun ejeren kan rette dem.','Firm details: contact person, address, invoice e-mail and reference. Only the owner can edit them.'),
			bp_t('Fakturaer: firmaets fakturaer fra Saldi med betalingsstatus. Kræver retten Se priser.','Invoices: the firm\'s invoices from Saldi with payment status. Requires the right See prices.'),
			bp_t('Log: hvem der har ændret adgang, brugere og abonnementer, hvornår, og hvornår ændringen træder i kraft. Hvert regnskabskort har sin egen log.','Log: who changed access, users and subscriptions, when, and when it takes effect. Each account card has its own log.')),
		array(bp_t('Gå til Mit firma','Go to My firm'), 'bogholder_firma.php'), null),
);
$FAQ = array(
	array(bp_t('Hvorfor kan jeg ikke se en bestemt '.$cw1.'?','Why can\'t I see a particular '.$cw1.'?'), bp_t('Enten har firmaet ikke adgang til regnskabet endnu, eller din bruger er sat til kun at se udvalgte '.$cw.'. Spørg firmaets ejer, som kan ændre det under Mit firma › Medarbejdere.','Either the firm has no access to the account yet, or your user is set to see selected '.$cw.' only. Ask the firm\'s owner, who can change it under My firm › Employees.')),
	array(bp_t('Hvorfor er en knap eller et menupunkt gråt?','Why is a button or menu item grey?'), bp_t('Din bruger mangler den rettighed, handlingen kræver, eller Saldi har ikke slået funktionen til for firmaet. Firmaets ejer kan tildele rettigheder; kontakt Saldi om firmaets indstillinger.','Your user lacks the right the action needs, or Saldi has not enabled the feature for the firm. The firm\'s owner can grant rights; contact Saldi about the firm\'s settings.')),
	array(bp_t('Tæller vores medarbejdere med i kundens brugergrænse?','Do our employees count against the customer\'s user limit?'), bp_t('Nej. Firmaets medarbejdere tæller aldrig med, uanset hvor mange '.$cw.' de arbejder i.','No. The firm\'s employees never count, however many '.$cw.' they work in.')),
	array(bp_t('Kan jeg arbejde i to regnskaber på samme tid?','Can I work in two accounts at the same time?'), bp_t('Regnskaber åbner i en ny fane, men et login kan kun stå i ét regnskab ad gangen. Åbner du et nyt, skifter de andre faner også. Brug panelet til at skifte hurtigt mellem dem.','Accounts open in a new tab, but one login can only be in one account at a time. Opening another switches the other tabs too. Use the panel to switch quickly between them.')),
	array(bp_t('Er prisen i prisoverblikket endelig?','Is the price in the price overview final?'), bp_t('Nej. Satserne for mængderabat er foreløbige, indtil Saldi har fastsat prisen for bogholderordningen. Det står også ved priserne.','No. The volume discount rates are provisional until Saldi sets the scheme price. It is noted next to the prices too.')),
	array(bp_t('Hvem kontakter jeg, hvis noget ikke virker?','Whom do I contact if something does not work?'), bp_t('Skriv til Saldi på <a href="mailto:info@saldi.dk">info@saldi.dk</a> og nævn regnskabsnummeret. Betalerskift, opsigelser og arkiveringer fra panelet sendes automatisk til Saldi.','Write to Saldi at <a href="mailto:info@saldi.dk">info@saldi.dk</a> and mention the account number. Payer changes, cancellations and archiving from the panel are sent to Saldi automatically.')),
);
$rights = partner_rights_all();

bp_open(array(bp_crumb_root(), bp_t('Vejledning','Guide')), bp_t('Vejledning','Guide'), bp_t('Sådan bruger I bogholderpanelet. Hvert afsnit har et link direkte til stedet.','How to use the accountant panel. Each section links straight to the place.'));
print "<div class=\"bp-guide\"><nav class=\"bp-toc\" aria-label=\"".bp_t('Indhold','Contents')."\">";
$last = ''; foreach ($G as $g) { if ($g[1] != $last) { print "<b>".bp_h($g[1])."</b>"; $last = $g[1]; } print "<a href=\"#".$g[0]."\">".bp_h($g[2])."</a>"; }
print "<b>".bp_t('Spørgsmål','Questions')."</b><a href=\"#faq\">".bp_t('Ofte stillede spørgsmål','Frequently asked questions')."</a></nav>";
print "<div class=\"bp-guide-body\"><label class=\"vr-search bp-gsearch\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\" aria-hidden=\"true\"><circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"M20 20l-3.5-3.5\"/></svg><input type=\"search\" id=\"bpGq\" placeholder=\"".bp_t('Søg i vejledningen, fx invitation eller opsig','Search the guide, e.g. invitation or cancel')."\" autocomplete=\"off\"></label>";
foreach ($G as $g) {
	list($key, , $title, $intro, $steps, $link, $right) = $g;
	$can = $right === null ? true : bp_can($right);
	print "<section class=\"vr-sect bp-gs\" id=\"$key\"><h2>".bp_h($title)."</h2><div class=\"vr-card\"><p>".bp_h($intro)."</p><ol>";
	foreach ($steps as $st) print "<li>".bp_h($st)."</li>";
	print "</ol>";
	if ($link || !$can) print "<div class=\"bp-g-foot\">".($link && $can ? "<a class=\"vr-btn\" href=\"".bp_h($link[1])."\">".bp_h($link[0])." &rsaquo;</a>" : "").(!$can ? "<span class=\"bp-g-lock\">".bp_t('Kræver rettigheden','Requires the right')." ".bp_h($rights[$right][0] ?? $right).". ".bp_t('Spørg firmaets ejer.','Ask the firm\'s owner.')."</span>" : "")."</div>";
	print "</div></section>";
}
print "<section class=\"vr-sect bp-gs\" id=\"faq\"><h2>".bp_t('Ofte stillede spørgsmål','Frequently asked questions')."</h2><div class=\"vr-card bp-faq\">";
foreach ($FAQ as $f) print "<details><summary>".bp_h($f[0])."</summary><p>".$f[1]."</p></details>";
print "</div></section>";
print "<div class=\"vr-empty\" id=\"bpGnone\" hidden><b>".bp_t('Intet i vejledningen matcher','Nothing in the guide matches')."</b><span>".bp_t('Prøv et andet ord, eller se de ofte stillede spørgsmål.','Try another word, or see the frequently asked questions.')."</span></div>";
print "</div></div>";
print "<script>(function(){var q=document.getElementById('bpGq'),secs=[].slice.call(document.querySelectorAll('.bp-gs')),none=document.getElementById('bpGnone');q.addEventListener('input',function(){var s=q.value.trim().toLowerCase(),n=0;secs.forEach(function(el){var hit=!s||el.textContent.toLowerCase().indexOf(s)!==-1;el.hidden=!hit;if(hit){n++;if(s)[].forEach.call(el.querySelectorAll('details'),function(d){if(d.textContent.toLowerCase().indexOf(s)!==-1)d.open=true;});}});none.hidden=n>0;});})();</script>";
bp_close();
?>

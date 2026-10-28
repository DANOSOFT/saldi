// SALDI Assist: shell-navigation.
//
// Widgeten kalder window.SaldiAssist.navigate(screenId) for at foere brugeren
// hen til den skaerm, svaret handler om. screenId skal staa ordret i SCREENS
// nedenfor; alt andet afvises (returnerer false), saa en manipuleret eller ukendt
// vaerdi aldrig kan aabne en side, Saldis egen navigation ikke selv aabner.
//
// 20261002 CL/LH Tightened to SPEC 2.7: bare .php paths (no '..' segments, no leading
//                slash) or exactly one allowlisted page + id parameter; the allowlist mirrors
//                includes/runtime/SaldiScreens.php. Kept the manifest's view selectors
//                (exactly one valg= or sektion= with a plain word): 36 of the 83 manifest
//                screens use them. Every other query string is refused.
// 20261002 CDX/LH Refuse destructive paths and allow bounded view selector words.
// 20261002 CDX/LH Mirror companion screens and the verified reminder id parameter.
// 20261012 CL/LH Ported from ai/mcp-assistant to master for the Guide tier (master's bridge
//                accepted any query string, admin/ and '..' paths). Allows the bare
//                admin/backup.php that the shell's own System menu opens, so "Gå dertil" works.
// 20261027 CL/LH Exact allowlist instead of path rules: the rules still let any bare .php
//                outside admin/ through, and some of those write on a plain GET
//                (lager/lagerstatusmail.php, api/v2/setup_api_key.php, bank_integration/login.php).
//                The typed record links and companion screens of the full Assist are not on
//                master; that branch adds them back with its pages.
(function (root) {
  // Every screen the assistant may open: the destinations of SALDI's own navigation, as
  // listed in the assistant's screen manifest (chaty data/screens/screen_manifest.json).
  // Keep in step with it. Left out on purpose: index/logud.php (logs out),
  // admin/admin_settings.php (not in the shell's menu), debitor/pos_ordre.php (the shell
  // opens the till in the whole window, never in the content frame, and opening it can
  // resume or reset a sale in progress), produktion/ordreliste.php (not in this repository).
  var SCREENS = [
    'admin/backup.php',
    'debitor/betalinger_settings.php',
    'debitor/crmkalender.php',
    'debitor/debitor.php?valg=debitor',
    'debitor/debitor.php?valg=historik',
    'debitor/debitor.php?valg=kommission',
    'debitor/debitor.php?valg=rental',
    'debitor/ordreliste.php?valg=faktura',
    'debitor/ordreliste.php?valg=ordrer',
    'debitor/ordre.php',
    'debitor/rapport.php',
    'finans/budget.php',
    'finans/kassekladde.php',
    'finans/kladdeliste.php',
    'finans/moms_periode.php',
    'finans/provisionsrapport.php',
    'finans/rapport.php',
    'finans/regnskab.php',
    'index/dashboard.php',
    'kreditor/kreditor.php',
    'kreditor/ordreliste.php?valg=faktura',
    'kreditor/ordreliste.php?valg=ordrer',
    'kreditor/ordre.php',
    'kreditor/rapport.php',
    'lager/beholdningsliste.php',
    'lager/modtageliste.php',
    'lager/rapport.php',
    'lager/varer.php',
    'rental/daysoff.php',
    'rental/index.php',
    'rental/items.php',
    'rental/lookupcust.php',
    'rental/remote.php',
    'rental/settings.php',
    'sager/sager.php',
    'systemdata/ansatte.php',
    'systemdata/barcodescan.php',
    'systemdata/brugerdata.php',
    'systemdata/brugere.php',
    'systemdata/diverse.php',
    'systemdata/diverse.php?sektion=api_valg',
    'systemdata/diverse.php?sektion=bank_integration',
    'systemdata/diverse.php?sektion=bilag',
    'systemdata/diverse.php?sektion=div_io',
    'systemdata/diverse.php?sektion=div_valg',
    'systemdata/diverse.php?sektion=docubizz',
    'systemdata/diverse.php?sektion=kontoindstillinger',
    'systemdata/diverse.php?sektion=labels',
    'systemdata/diverse.php?sektion=massefakt',
    'systemdata/diverse.php?sektion=ordre_valg',
    'systemdata/diverse.php?sektion=orediff',
    'systemdata/diverse.php?sektion=posoptions',
    'systemdata/diverse.php?sektion=pricelists',
    'systemdata/diverse.php?sektion=productoptions',
    'systemdata/diverse.php?sektion=provision',
    'systemdata/diverse.php?sektion=rykker_valg',
    'systemdata/diverse.php?sektion=sprog',
    'systemdata/diverse.php?sektion=stripe_valg',
    'systemdata/diverse.php?sektion=tjekliste',
    'systemdata/diverse.php?sektion=usersettings',
    'systemdata/diverse.php?sektion=variant_valg',
    'systemdata/email_settings.php',
    'systemdata/enheder.php',
    'systemdata/feedbackmail.php',
    'systemdata/formularkort.php?valg=formularer',
    'systemdata/kontoplan.php',
    'systemdata/posmenuer.php',
    'systemdata/rabatgrupper.php',
    'systemdata/regnskabsaar.php',
    'systemdata/regnskabskort.php',
    'systemdata/stamkort.php',
    'systemdata/syssetup.php',
    'systemdata/syssetup.php?valg=afdelinger',
    'systemdata/syssetup.php?valg=debitor',
    'systemdata/syssetup.php?valg=lagre',
    'systemdata/syssetup.php?valg=moms',
    'systemdata/syssetup.php?valg=projekter',
    'systemdata/syssetup.php?valg=varer',
    'systemdata/valuta.php'
  ]

  var ALLOWED = {}
  for (var i = 0; i < SCREENS.length; i++) ALLOWED[SCREENS[i]] = true

  function isAllowedScreen (screenId) {
    return typeof screenId === 'string' && Object.prototype.hasOwnProperty.call(ALLOWED, screenId)
  }

  root.SaldiAssistNavigate = function (screenId) {
    if (!isAllowedScreen(screenId)) return false
    // index/main.php exposes its update_iframe (unsaved-changes guard, iframe history).
    if (typeof root.update_iframe === 'function') {
      root.update_iframe('/' + screenId)
      return true
    }
    root.location.hash = '/' + screenId
    return true
  }
  root.SaldiAssistNavigate.isAllowed = isAllowedScreen
  root.SaldiAssistNavigate.screens = SCREENS.slice()
})(window)

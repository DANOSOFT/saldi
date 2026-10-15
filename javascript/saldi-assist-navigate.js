// SALDI Assist: shell-navigation.
//
// Widgeten kalder window.SaldiAssist.navigate(screenId) for at foere brugeren
// hen til den skaerm, svaret handler om. screenId er enten et id fra
// data/screens/screen_manifest.json (en bar sti, fx 'debitor/ordreliste.php')
// eller et af de typede SaldiScreens-links (SPEC 2.7), fx
// 'finans/kassekladde.php?kladde_id=12'.
//
// Funktionen validerer selv id'et og afviser alt andet (returnerer false), saa
// en manipuleret eller ukendt vaerdi aldrig kan blive til en absolut URL,
// javascript:, en sti uden for Saldi eller et link med en handlingsparameter.
//
// 20261002 CL/LH Tightened to SPEC 2.7: bare .php paths (no '..' segments, no leading
//                slash) or exactly one allowlisted page + id parameter; the allowlist mirrors
//                includes/runtime/SaldiScreens.php. Kept the manifest's view selectors
//                (exactly one valg= or sektion= with a plain word): 36 of the 83 manifest
//                screens use them. Every other query string is refused.
// 20261002 CDX/LH Refuse destructive paths and allow bounded view selector words.
// 20261002 CDX/LH Mirror companion screens and the verified reminder id parameter.
(function (root) {
  // Keep in step with SaldiScreens::SCREENS (types without a parameter are bare paths).
  var SCREEN_PARAMS = {
    'finans/kassekladde.php': 'kladde_id',
    'debitor/ordre.php': 'id',
    'kreditor/ordre.php': 'id',
    'debitor/debitorkort.php': 'id',
    'kreditor/kreditorkort.php': 'id',
    'lager/varekort.php': 'id',
    'inbox/index.php': 'item',
    'debitor/rykker.php': 'rykker_id',
    'debitor/rapport.php?rapportart=openpost': null,
    'finans/pulje_review.php': null,
    'systemdata/assistent.php': null,
    'systemdata/assistent_rapport.php': null,
    'finans/rapport.php': null,
    'finans/moms_periode.php': null
  }

  function isAllowedScreen (screenId) {
    if (typeof screenId !== 'string' || screenId.length > 200) return false
    // Verified overview destination of a bare debitor/rykker.php request.
    if (screenId === 'debitor/rapport.php?rapportart=openpost') return true
    var q = screenId.indexOf('?')
    var path = q === -1 ? screenId : screenId.slice(0, q)
    if (!/^[a-z0-9_][a-z0-9_\/.-]*\.php$/.test(path)) return false
    if (path.split('/').some(function (segment) { return segment === '' || segment === '.' || segment === '..' })) return false
    if (/logud|slet|delete|luk\.php/.test(path) || path.indexOf('admin/') === 0) return false
    if (q === -1) return true
    var query = screenId.slice(q + 1)
    // Manifest view selectors (debitor/ordreliste.php?valg=ordrer, systemdata/diverse.php?sektion=sprog).
    if (/^(valg|sektion)=[a-z0-9_-]{1,40}$/.test(query)) return true
    var param = Object.prototype.hasOwnProperty.call(SCREEN_PARAMS, path) ? SCREEN_PARAMS[path] : null
    if (param === null) return false
    var match = /^([a-z_]+)=([0-9]{1,10})$/.exec(query)
    return match !== null && match[1] === param
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
})(window)

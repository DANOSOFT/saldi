// SALDI Assist: shell-navigation.
//
// Widgeten kalder window.SaldiAssist.navigate(screenId) for at foere brugeren
// hen til den skaerm, svaret handler om. screenId er et id fra
// data/screens/screen_manifest.json, fx 'debitor/ordreliste.php?valg=ordrer'.
//
// Funktionen validerer selv id'et: kun relative .php-stier med simple
// query-parametre. Alt andet afvises (returnerer false), saa en manipuleret
// eller ukendt vaerdi aldrig kan blive til en absolut URL eller javascript:.
window.SaldiAssistNavigate = function (screenId) {
  if (!/^[a-z0-9_\/.-]+\.php(\?[a-z_]+=[a-z0-9_-]+(&[a-z_]+=[a-z0-9_-]+)*)?$/.test(screenId)) return false
  /* TODO(SALDI): confirm the shell function name. ../Saldi/index/main.php
     defines `const update_iframe = (uri) => {...}` inside the page script
     (checks iframe.contentWindow.docChange and confirms before leaving); it is
     not a global today. SALDI must expose it, e.g.
     window.update_iframe = update_iframe. */
  if (typeof window.update_iframe === 'function') {
    window.update_iframe('/' + screenId)
    return true
  }
  window.location.hash = '/' + screenId
  return true
}

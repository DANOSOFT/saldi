<?php
// SALDI Assist: token-endpoint. Placeres som includes/saldi_assist_token.php.
//
// Kaldes af widget-loaderen (window.SaldiAssist.getContextToken) med
// ?embed_session=<32 hex> og svarer {"token": "..."} for en logget-ind bruger.
// Starter som SALDIs egne sider: PHP-session, forbindelse, licensfunktioner og
// online.php (som afviser anonyme kald). online.php skriver SALDIs HTML-head
// ud, saa de tre includes koeres i en output-buffer, der kasseres, saa svaret
// kun er JSON.
//
// Miljoe (webserverens env, aldrig i git):
//   SALDI_ASSIST_CONTEXT_SECRET  hemmeligheden (samme som chatbottens CONTEXT_TOKEN_KEYS)
//   SALDI_ASSIST_KID             noeglens id, standard k2026a
ob_start();
@session_start();
$s_id = session_id();
include("../includes/connect.php");
include("../includes/license_func.php");

// online.php skriver "session udloebet" og afslutter, naar der ikke er en
// session; tjek derfor sessionen foerst (samme opslag som online.php:129), saa
// anonyme kald faar et JSON-svar med 401 i stedet for HTML.
$assistSession = db_fetch_array(db_select(
    "select brugernavn from online where session_id = '" . db_escape_string($s_id) . "' order by logtime desc limit 1",
    __FILE__ . ' linje ' . __LINE__
));
if (!$assistSession || empty($assistSession['brugernavn'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    http_response_code(401);
    echo json_encode(['error' => 'no_session']);
    exit;
}

include("../includes/online.php");
ob_end_clean();

require_once __DIR__ . '/saldi_assist_context_token.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($brugernavn) || empty($db)) {
    // online.php fandt alligevel ingen bruger: udsted aldrig et token uden en identificeret bruger.
    http_response_code(401);
    echo json_encode(['error' => 'no_session']);
    exit;
}

saldi_assist_token_endpoint();

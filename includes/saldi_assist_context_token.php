<?php
/**
 * SALDI Assist: kontekst-token (referenceimplementation).
 *
 * SALDI signerer et kortlivet token pr. embed-session; widgeten sender det med
 * hver chat-forespoergsel, og chatbot-backenden verificerer det i
 * backend/app/context_token.py. Formatet er
 *
 *     v1.<kid>.<payload_b64url>.<sig_b64url>
 *
 * hvor payload er et UTF-8 JSON-objekt med ksort'ede noegler, og signaturen er
 * HMAC-SHA256 over de literale bytes "v1.<kid>.<payload_b64url>".
 *
 * Denne fil hoerer til chatbot-repoet (integration/saldi-host/) og aendrer
 * ingenting i SALDI. Den er tiltaenkt at blive kopieret ind som
 * includes/saldi_assist_token.php (eller tilsvarende) af SALDI-teamet.
 *
 * jti (`bin2hex(random_bytes(12))`) er ikke et engangs-nonce: tokenet maa
 * genbruges af den samme embed-session indtil `exp`; et jti praesenteret af en
 * anden session afvises (`replay`).
 *
 * Ingen eksterne afhaengigheder: hash, hash_hmac, json_encode, random_bytes.
 */

/** Noegler pr. kid. Rotation: tilfoej en ny kid, udrul, fjern den gamle.
 *  Kid'en kommer fra SALDI_ASSIST_KID (standard k2026a) og skal matche
 *  chatbottens CONTEXT_TOKEN_KEYS. */
$SALDI_ASSIST_KID = getenv('SALDI_ASSIST_KID') ?: 'k2026a';
$SALDI_ASSIST_KEYS = [$SALDI_ASSIST_KID => getenv('SALDI_ASSIST_CONTEXT_SECRET')];

/** Tokenets levetid i sekunder. Backenden afviser laengere levetid end 900. */
const SALDI_ASSIST_TOKEN_TTL = 900;

/** base64url uden padding: '+/' -> '-_', '=' klippet af. */
function saldi_assist_b64url(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * Licens-map: feature_key => udloebsdato (ISO) eller null.
 *
 * Spejler ../Saldi/includes/license_func.php: kun raekker der er enabled kommer
 * med ('f' og '0' taeller som slaaet fra i Postgres/MySQL). Udloebsdatoen
 * normaliseres til Y-m-d og sendes med som den er - chatbotten haandhaever den
 * selv paa forespoergselstidspunktet (access_context_from_claims i
 * backend/app/context_token.py dropper flag med expiry < i dag), saa udstederen
 * behoever ikke gaette hvor laenge tokenet lever.
 *
 * @param array<int,array<string,mixed>> $licence_rows raekker fra license_features
 * @return array<string,?string>
 */
function saldi_assist_licence_map(array $licence_rows, ?int $now = null): array
{
    unset($now); // beholdt i signaturen; udloeb haandhaeves af chatbotten.
    $licences = [];
    foreach ($licence_rows as $row) {
        $feature_key = (string)($row['feature_key'] ?? '');
        if ($feature_key === '') {
            continue;
        }
        $enabled = $row['enabled'] ?? false;
        if (!$enabled || $enabled === 'f' || $enabled === '0') {
            continue;
        }
        $expires_at = $row['expires_at'] ?? null;
        if ($expires_at === null || $expires_at === '') {
            $licences[$feature_key] = null;
            continue;
        }
        $expires = strtotime((string)$expires_at);
        if ($expires === false) {
            continue;
        }
        $licences[$feature_key] = date('Y-m-d', $expires);
    }
    ksort($licences);

    return $licences;
}

/**
 * Byg claims-arrayet. Adskilt fra signeringen, saa fixture-generatoren kan
 * erstatte den tilfaeldige jti med en deterministisk vaerdi.
 *
 * @param array<string,mixed> $online_row raekke fra online (db, brugernavn, rettigheder, language_id)
 * @param array<int,array<string,mixed>> $licence_rows raekker fra license_features
 * @return array<string,mixed>
 */
function saldi_assist_context_claims(
    array $online_row,
    array $licence_rows,
    string $embed_session_hash,
    ?int $now = null,
    string $app_version = '',
    string $menu_style = ''
): array {
    $now = $now ?? time();
    $db = (string)($online_row['db'] ?? '');
    $brugernavn = (string)($online_row['brugernavn'] ?? '');

    // TODO(SALDI): bekraeft sprogkortlaegningen. online.php:140 laeser
    // online.language_id (0 = ikke sat). Vi antager 2 = engelsk, alt andet
    // dansk; backenden falder selv tilbage til 'da' ved ukendte vaerdier.
    $language_id = (int)($online_row['language_id'] ?? 0);

    $licences = saldi_assist_licence_map($licence_rows, $now);

    return [
        'iss' => 'saldi',
        'aud' => 'saldi-assist',
        // Ingen raa db-navne eller brugernavne forlader SALDI - kun hashes.
        'tenant_hash' => substr(hash('sha256', 'saldi-tenant:' . $db), 0, 32),
        'user_hash' => substr(hash('sha256', 'saldi-user:' . $db . ':' . $brugernavn), 0, 32),
        // Raa positionsstreng, som online.php:331 laeser den (brugere.rettigheder).
        'rights' => (string)($online_row['rettigheder'] ?? ''),
        // Tomt map skal serialiseres som {} og ikke [].
        'licences' => $licences === [] ? new stdClass() : $licences,
        'lang' => $language_id === 2 ? 'en' : 'da',
        // grupper.box3 for brugeren (online.php:282).
        'menu_style' => $menu_style,
        // $version fra includes/version.php ('5.0.0').
        'app_version' => $app_version,
        'iat' => $now,
        'exp' => $now + SALDI_ASSIST_TOKEN_TTL,
        'jti' => bin2hex(random_bytes(12)),
        'embed_session' => $embed_session_hash,
    ];
}

/**
 * Serialiser og signer claims. Noeglerne ksort'es foer json_encode, saa PHP og
 * Python producerer byte-identiske payloads.
 *
 * @param array<string,mixed> $claims
 */
function saldi_assist_sign_claims(array $claims, string $kid, string $secret): string
{
    ksort($claims);
    $payload = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('saldi_assist: kunne ikke json_encode claims');
    }
    $payload_b64 = saldi_assist_b64url($payload);
    $sig = saldi_assist_b64url(hash_hmac('sha256', "v1.$kid.$payload_b64", $secret, true));

    return "v1.$kid.$payload_b64.$sig";
}

/**
 * Udsted et kontekst-token for den aktive SALDI-session.
 *
 * @param array<string,mixed> $online_row
 * @param array<int,array<string,mixed>> $licence_rows
 */
function saldi_assist_context_token(
    array $online_row,
    array $licence_rows,
    string $embed_session_hash,
    string $kid,
    string $secret,
    ?int $now = null,
    string $app_version = '',
    string $menu_style = ''
): string {
    $claims = saldi_assist_context_claims(
        $online_row,
        $licence_rows,
        $embed_session_hash,
        $now,
        $app_version,
        $menu_style
    );

    return saldi_assist_sign_claims($claims, $kid, $secret);
}

/**
 * Hent licensraekker for det aktive regnskab.
 *
 * TODO(SALDI): bekraeft filtreringen. license_func.php:45 laeser hele tabellen
 * (SELECT regnskab_id, feature_key, enabled, expires_at FROM license_features)
 * og cacher pr. regnskab_id; her filtreres direkte paa det aktive regnskab.
 *
 * @return array<int,array<string,mixed>>
 */
/**
 * Feature-noegler SALDI selv gater paa (is_feature_licensed i index/main.php,
 * includes/top_menu.php, index/menu.php). license_func.php tillader en feature
 * naar tabellen license_features ikke findes, eller naar der ikke er en raekke
 * for regnskabet ("backwards compatibility"), saa udstederen skal sende de
 * samme features som licenseret - ellers hedger chatbotten skaerme som SALDI
 * selv viser.
 */
const SALDI_ASSIST_DEFAULT_FEATURES = ['booking', 'kreditor', 'lager'];

function saldi_assist_licence_rows($regnskab_id): array
{
    // db_select() har ingen parameterbinding (includes/db_query.php sender
    // strengen direkte til pg_query/mysqli_query), saa det eneste der maa
    // naa SQL'en er et valideret heltal - aldrig en raa streng.
    $defaults = [];
    foreach (SALDI_ASSIST_DEFAULT_FEATURES as $feature_key) {
        $defaults[$feature_key] = ['feature_key' => $feature_key, 'enabled' => true, 'expires_at' => null];
    }
    if (!is_numeric($regnskab_id) || (int)$regnskab_id < 0) {
        return array_values($defaults);
    }
    // Samme eksistenstjek som license_func.php:37: uden tabellen er alt tilladt.
    $exists = @db_select(
        "SELECT 1 FROM information_schema.tables WHERE table_name = 'license_features'",
        __FILE__ . ' linje ' . __LINE__
    );
    if (!$exists || !db_fetch_array($exists)) {
        return array_values($defaults);
    }
    $id = (int)$regnskab_id;
    $qtxt = 'SELECT feature_key, enabled, expires_at FROM license_features'
        . ' WHERE regnskab_id = ' . $id;
    $q = @db_select($qtxt, __FILE__ . ' linje ' . __LINE__);
    if ($q) {
        while ($r = db_fetch_array($q)) {
            // En eksplicit raekke (ogsaa en slaaet-fra) erstatter standarden.
            $defaults[(string)($r['feature_key'] ?? '')] = $r;
        }
    }
    unset($defaults['']);
    return array_values($defaults);
}

/**
 * Endpoint-skitse: includes/saldi_assist_token.php.
 *
 * Kraever en aktiv session - filen skal starte med det saedvanlige
 * `include("../includes/online.php");` moenster, som selv afviser
 * uautentificerede kald, foer denne funktion kaldes. Svar: {"token": "..."}.
 */
function saldi_assist_token_endpoint(): void
{
    // $db_id er det aktive regnskabs id i SALDI (includes/online.php, license_func.php).
    global $SALDI_ASSIST_KEYS, $SALDI_ASSIST_KID, $version, $menu, $languageID, $rettigheder, $brugernavn, $db, $db_id;

    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    $embed_session = isset($_GET['embed_session']) ? (string)$_GET['embed_session'] : '';
    if (!preg_match('/^[0-9a-f]{32}$/', $embed_session)) {
        http_response_code(400);
        echo json_encode(['error' => 'bad_embed_session']);
        return;
    }

    // Selvom online.php allerede har afvist anonyme kald: udsted aldrig et
    // token uden en identificeret bruger.
    if (empty($brugernavn) || empty($db)) {
        http_response_code(401);
        echo json_encode(['error' => 'no_session']);
        return;
    }

    $kid = (string)($SALDI_ASSIST_KID ?: 'k2026a');
    $secret = (string)($SALDI_ASSIST_KEYS[$kid] ?? '');
    if ($secret === '') {
        http_response_code(503);
        echo json_encode(['error' => 'not_configured']);
        return;
    }

    $online_row = [
        'db' => $db,
        'brugernavn' => $brugernavn,
        'rettigheder' => $rettigheder,
        'language_id' => $languageID,
    ];

    $token = saldi_assist_context_token(
        $online_row,
        saldi_assist_licence_rows($db_id ?? null),
        $embed_session,
        $kid,
        $secret,
        null,
        (string)$version,
        (string)$menu
    );

    echo json_encode(['token' => $token]);
}

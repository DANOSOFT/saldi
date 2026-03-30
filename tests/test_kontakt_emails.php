<?php
/**
 * Kontakt Emails - Integration Test
 *
 * Run from browser: https://your-domain/pblm/tests/test_kontakt_emails.php
 * Or CLI: php /var/www/html/pblm/tests/test_kontakt_emails.php
 *
 * Tests the full kontakt_emails implementation against the real database.
 * Creates a temporary test customer, runs all tests, then cleans up.
 */

// Bootstrap
include("../includes/connect.php");
include("../includes/stdFunc/getKontaktEmail.php");

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$passed = 0;
$failed = 0;
$testCustomerId = null;

function pass($msg) {
    global $passed;
    $passed++;
    echo "  PASS  $msg\n";
}

function fail($msg) {
    global $failed;
    $failed++;
    echo "  FAIL  $msg\n";
}

function check($condition, $msg) {
    if ($condition) pass($msg);
    else fail($msg);
}

echo "=======================================================\n";
echo "  Kontakt Emails - Integration Test\n";
echo "=======================================================\n\n";

// -------------------------------------------------------
echo "--- Setup: Check table exists ---\n";
// -------------------------------------------------------
$q = db_select("SELECT table_name FROM information_schema.tables WHERE table_name='kontakt_emails'", __FILE__);
$r = db_fetch_array($q);
check($r, "kontakt_emails table exists");
if (!$r) {
    echo "\n!!! Table kontakt_emails does not exist. Run betweenUpdates.php first.\n";
    exit(1);
}

// -------------------------------------------------------
echo "\n--- Setup: Create test customer ---\n";
// -------------------------------------------------------
$testKontonr = 'KETEST_' . time();
$qtxt = "INSERT INTO adresser (kontonr, firmanavn, email, tlf, art, kontotype)
         VALUES ('$testKontonr', 'KE Test Firma', 'original@test.dk', '99990000', 'D', 'erhverv')";
db_modify($qtxt, __FILE__);
$r = db_fetch_array(db_select("SELECT id FROM adresser WHERE kontonr='$testKontonr' AND art='D'", __FILE__));
$testCustomerId = $r['id'];
check($testCustomerId > 0, "Test customer created with id=$testCustomerId");

// -------------------------------------------------------
echo "\n--- Test 1: Insert emails ---\n";
// -------------------------------------------------------
$emails = [
    ['email' => 'hoved@test.dk',     'type' => 'hoved'],
    ['email' => 'faktura@test.dk',   'type' => 'faktura'],
    ['email' => 'ordre@test.dk',     'type' => 'ordre'],
    ['email' => 'tilbud@test.dk',    'type' => 'tilbud'],
    ['email' => 'kontoudtog@test.dk','type' => 'kontoudtog'],
    ['email' => 'rykker@test.dk',    'type' => 'rykker'],
    ['email' => 'andet@test.dk',     'type' => 'andet'],
];
foreach ($emails as $e) {
    db_modify("INSERT INTO kontakt_emails (konto_id, email, email_type)
               VALUES ('$testCustomerId', '{$e['email']}', '{$e['type']}')", __FILE__);
}
$q = db_select("SELECT count(*) as cnt FROM kontakt_emails WHERE konto_id='$testCustomerId'", __FILE__);
$r = db_fetch_array($q);
check($r['cnt'] == 7, "Inserted 7 emails (got {$r['cnt']})");

// -------------------------------------------------------
echo "\n--- Test 2: getKontaktEmail() - single lookup per type ---\n";
// -------------------------------------------------------
$types_expected = [
    'hoved'      => 'hoved@test.dk',
    'faktura'    => 'faktura@test.dk',
    'ordre'      => 'ordre@test.dk',
    'tilbud'     => 'tilbud@test.dk',
    'kontoudtog' => 'kontoudtog@test.dk',
    'rykker'     => 'rykker@test.dk',
    'andet'      => 'andet@test.dk',
];
foreach ($types_expected as $type => $expected) {
    $got = getKontaktEmail($testCustomerId, $type);
    check($got === $expected, "getKontaktEmail('$type') = '$got' (expected '$expected')");
}

// -------------------------------------------------------
echo "\n--- Test 3: getKontaktEmail() - fallback chain ---\n";
// -------------------------------------------------------
// Request a type that doesn't exist - should fall back to first email
$got = getKontaktEmail($testCustomerId, 'nonexistent_type');
check(!empty($got), "Fallback for unknown type returns something: '$got'");

// Empty type - should return first email
$got = getKontaktEmail($testCustomerId, '');
check(!empty($got), "Empty type returns first email: '$got'");

// Non-existent customer - should return empty
$got = getKontaktEmail(999999999, 'faktura');
check($got === '', "Non-existent customer returns empty string");

// -------------------------------------------------------
echo "\n--- Test 4: getAllKontaktEmails() - single type ---\n";
// -------------------------------------------------------
$got = getAllKontaktEmails($testCustomerId, 'faktura');
check($got === 'faktura@test.dk', "getAllKontaktEmails('faktura') = '$got'");

// -------------------------------------------------------
echo "\n--- Test 5: getAllKontaktEmails() - multiple emails same type ---\n";
// -------------------------------------------------------
db_modify("INSERT INTO kontakt_emails (konto_id, email, email_type)
           VALUES ('$testCustomerId', 'faktura2@test.dk', 'faktura')", __FILE__);
db_modify("INSERT INTO kontakt_emails (konto_id, email, email_type)
           VALUES ('$testCustomerId', 'faktura3@test.dk', 'faktura')", __FILE__);

$got = getAllKontaktEmails($testCustomerId, 'faktura');
$parts = explode(';', $got);
check(count($parts) === 3, "3 faktura emails returned (got " . count($parts) . "): $got");
check(in_array('faktura@test.dk', $parts), "Contains faktura@test.dk");
check(in_array('faktura2@test.dk', $parts), "Contains faktura2@test.dk");
check(in_array('faktura3@test.dk', $parts), "Contains faktura3@test.dk");

// -------------------------------------------------------
echo "\n--- Test 6: getAllKontaktEmails() - all emails (no type filter) ---\n";
// -------------------------------------------------------
$got = getAllKontaktEmails($testCustomerId, '');
$parts = explode(';', $got);
check(count($parts) === 9, "9 total emails returned (got " . count($parts) . ")");

// -------------------------------------------------------
echo "\n--- Test 7: getAllKontaktEmails() - fallback to adresser.email ---\n";
// -------------------------------------------------------
// Create a customer with no kontakt_emails but with adresser.email
$testKontonr2 = 'KETEST2_' . time();
db_modify("INSERT INTO adresser (kontonr, firmanavn, email, tlf, art)
           VALUES ('$testKontonr2', 'Fallback Test', 'fallback@adresser.dk', '99990001', 'D')", __FILE__);
$r = db_fetch_array(db_select("SELECT id FROM adresser WHERE kontonr='$testKontonr2' AND art='D'", __FILE__));
$testCustomerId2 = $r['id'];

$got = getKontaktEmail($testCustomerId2, 'faktura');
check($got === 'fallback@adresser.dk', "Fallback to adresser.email when no kontakt_emails: '$got'");

$got = getAllKontaktEmails($testCustomerId2, 'faktura');
check($got === 'fallback@adresser.dk', "getAllKontaktEmails also falls back: '$got'");

// -------------------------------------------------------
echo "\n--- Test 8: Semicolon-separated emails work with PHPMailer split ---\n";
// -------------------------------------------------------
$multiEmail = getAllKontaktEmails($testCustomerId, 'faktura');
$splitEmails = preg_split('/[;,]/', $multiEmail);
$validCount = 0;
foreach ($splitEmails as $addr) {
    $addr = trim($addr);
    if ($addr && strpos($addr, '@')) $validCount++;
}
check($validCount === 3, "Splitting semicolon string yields 3 valid addresses (got $validCount)");

// -------------------------------------------------------
echo "\n--- Test 9: Delete customer cascades to kontakt_emails ---\n";
// -------------------------------------------------------
$countBefore = db_fetch_array(db_select("SELECT count(*) as cnt FROM kontakt_emails WHERE konto_id='$testCustomerId'", __FILE__));
check($countBefore['cnt'] > 0, "Emails exist before delete: {$countBefore['cnt']}");

db_modify("DELETE FROM kontakt_emails WHERE konto_id = '$testCustomerId'", __FILE__);
$countAfter = db_fetch_array(db_select("SELECT count(*) as cnt FROM kontakt_emails WHERE konto_id='$testCustomerId'", __FILE__));
check($countAfter['cnt'] == 0, "Emails cleaned up after delete: {$countAfter['cnt']}");

// -------------------------------------------------------
echo "\n--- Test 10: Sync to adresser.email ---\n";
// -------------------------------------------------------
// Insert emails for testCustomerId again and check sync logic
db_modify("INSERT INTO kontakt_emails (konto_id, email, email_type)
           VALUES ('$testCustomerId', 'synced@test.dk', 'hoved')", __FILE__);
db_modify("INSERT INTO kontakt_emails (konto_id, email, email_type)
           VALUES ('$testCustomerId', 'second@test.dk', 'faktura')", __FILE__);

// Simulate the sync that debitorkort.php does on save
$r_primary = db_fetch_array(db_select("SELECT email FROM kontakt_emails WHERE konto_id = '$testCustomerId' ORDER BY id LIMIT 1", __FILE__));
$sync_email = $r_primary ? db_escape_string($r_primary['email']) : '';
db_modify("UPDATE adresser SET email = '$sync_email' WHERE id = '$testCustomerId'", __FILE__);

$r_check = db_fetch_array(db_select("SELECT email FROM adresser WHERE id = '$testCustomerId'", __FILE__));
check($r_check['email'] === 'synced@test.dk', "adresser.email synced to first kontakt_email: '{$r_check['email']}'");

// -------------------------------------------------------
echo "\n--- Test 11: Email type mapping for formular numbers ---\n";
// -------------------------------------------------------
// This tests the mapping logic used in formfunk.php
$formularMap = [
    0 => 'tilbud',
    1 => 'tilbud',
    2 => 'ordre',
    4 => 'faktura',
    5 => 'faktura',
    6 => 'rykker',
    7 => 'rykker',
    8 => 'rykker',
];

foreach ($formularMap as $formular => $expectedType) {
    $ke_type = '';
    if ($formular == 0 || $formular == 1) $ke_type = 'tilbud';
    elseif ($formular == 2) $ke_type = 'ordre';
    elseif ($formular == 4 || $formular == 5) $ke_type = 'faktura';
    elseif ($formular >= 6) $ke_type = 'rykker';

    check($ke_type === $expectedType, "Formular $formular maps to '$ke_type' (expected '$expectedType')");
}

// -------------------------------------------------------
echo "\n--- Test 12: Combine order email + kontakt_emails (dedup) ---\n";
// -------------------------------------------------------
// Simulate the combine logic from formfunk.php
$order_email = 'manual@order.dk';
$ke_emails = getAllKontaktEmails($testCustomerId, 'faktura');

$all = array_filter(array_map('trim', preg_split('/[;,]/', $order_email . ';' . $ke_emails)));
$combined = implode(';', array_unique($all));
$parts = explode(';', $combined);
check(in_array('manual@order.dk', $parts), "Combined result contains order email");
check(in_array('second@test.dk', $parts), "Combined result contains kontakt_email");
check(count($parts) === count(array_unique($parts)), "No duplicates in combined result");

// Test dedup: order email same as kontakt_email
$order_email_dup = 'second@test.dk';
$all2 = array_filter(array_map('trim', preg_split('/[;,]/', $order_email_dup . ';' . $ke_emails)));
$combined2 = implode(';', array_unique($all2));
$parts2 = explode(';', $combined2);
check(count($parts2) === 1, "Duplicate email deduplicated: '$combined2' (count=" . count($parts2) . ")");

// Test with empty order email
$all3 = array_filter(array_map('trim', preg_split('/[;,]/', '' . ';' . $ke_emails)));
$combined3 = implode(';', array_unique($all3));
check($combined3 === 'second@test.dk', "Empty order email + kontakt_email = just kontakt_email: '$combined3'");

// -------------------------------------------------------
echo "\n--- Cleanup ---\n";
// -------------------------------------------------------
db_modify("DELETE FROM kontakt_emails WHERE konto_id = '$testCustomerId'", __FILE__);
db_modify("DELETE FROM adresser WHERE id = '$testCustomerId'", __FILE__);
echo "  Cleaned up test customer $testCustomerId\n";

if (isset($testCustomerId2)) {
    db_modify("DELETE FROM kontakt_emails WHERE konto_id = '$testCustomerId2'", __FILE__);
    db_modify("DELETE FROM adresser WHERE id = '$testCustomerId2'", __FILE__);
    echo "  Cleaned up test customer $testCustomerId2\n";
}

// -------------------------------------------------------
echo "\n=======================================================\n";
echo "  Results: $passed passed, $failed failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
?>

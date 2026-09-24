<?php
// 20260921 CDX/MJ SST-745: Saving a formularkort must not create a duplicate VSPR sprogrække when
// the language is spelled with a different capitalisation.
//
// The test does not assert on the SQL string. It lifts the real sprog_id block out of
// systemdata/formularkort.php, runs it against an in-memory SQLite database standing in for
// grupper, and then inspects the rows the block actually left behind. Whatever query the block
// issues is really executed, so a regression that reintroduces an exact-match comparison fails
// here even if it is written differently.
//
// Run: php tests/test_sst745_form_language_duplicate.php

error_reporting(E_ALL);

$source = __DIR__ . '/../systemdata/formularkort.php';
$lines = file($source);
if ($lines === false) {
    fwrite(STDERR, "Cannot read $source\n");
    exit(1);
}

// Locate the block by its opening condition rather than by a fixed line number, so the test
// survives edits elsewhere in the file and fails loudly if the block is renamed or removed.
$start = null;
foreach ($lines as $index => $line) {
    if (strpos($line, "if (\$formularsprog && \$formularsprog!='Dansk') {") !== false) {
        if ($start !== null) {
            fwrite(STDERR, "FAIL: the sprog_id block appears more than once; update this test\n");
            exit(1);
        }
        $start = $index;
    }
}
if ($start === null) {
    fwrite(STDERR, "FAIL: could not find the sprog_id block in systemdata/formularkort.php\n");
    exit(1);
}

// Walk braces from the opening line to find where the if/else closes. Lines in this block contain
// braces only as PHP syntax, so counting them is enough here.
$depth = 0;
$end = null;
for ($index = $start; $index < count($lines); $index++) {
    $depth += substr_count($lines[$index], '{') - substr_count($lines[$index], '}');
    if ($depth === 0) {
        $end = $index;
        break;
    }
}
if ($end === null) {
    fwrite(STDERR, "FAIL: the sprog_id block is unbalanced\n");
    exit(1);
}

$block = implode('', array_slice($lines, $start, $end - $start + 1));
$lineNumbers = ($start + 1) . '-' . ($end + 1);

// Guard against the span silently shrinking to something that no longer contains the behaviour
// under test. Without this, a refactor could leave the test passing vacuously.
foreach (['db_select', 'db_modify', 'insert into grupper', 'max(kodenr)'] as $required) {
    if (strpos($block, $required) === false) {
        fwrite(STDERR, "FAIL: extracted block ($lineNumbers) does not contain '$required'\n");
        exit(1);
    }
}

$pdo = null;

function db_select($query, $origin)
{
    global $pdo;
    $statement = $pdo->query($query);
    if ($statement === false) {
        throw new RuntimeException("Query failed: $query");
    }
    return $statement;
}

function db_fetch_array($statement)
{
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row === false ? false : $row;
}

function db_modify($query, $origin)
{
    global $pdo;
    if ($pdo->exec($query) === false) {
        throw new RuntimeException("Modify failed: $query");
    }
    return true;
}

/**
 * Run the extracted block for each saved spelling in turn against one shared grupper table,
 * mimicking a user saving several formularkort. Returns the resulting VSPR rows and the
 * sprog_id the block resolved for each save.
 */
function saveFormularkort(array $spellings, array $existing, $block)
{
    global $pdo;
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('create table grupper (beskrivelse text, kodenr integer, art text, box1 text)');
    foreach ($existing as $kodenr => $box1) {
        $pdo->exec("insert into grupper (beskrivelse,kodenr,art,box1) values ('sprog','$kodenr','VSPR','$box1')");
    }

    $resolved = [];
    foreach ($spellings as $formularsprog) {
        $form_sprog_id = null;
        eval($block);
        $resolved[$formularsprog] = $form_sprog_id;
    }

    $rows = $pdo->query("select kodenr, box1 from grupper where art = 'VSPR' order by kodenr")
        ->fetchAll(PDO::FETCH_ASSOC);
    return [$rows, $resolved];
}

$failures = 0;

function check($description, $condition, $detail = '')
{
    global $failures;
    if ($condition) {
        echo "PASS: $description\n";
        return;
    }
    $failures++;
    echo "FAIL: $description" . ($detail === '' ? '' : " -- $detail") . "\n";
}

echo "Exercising systemdata/formularkort.php lines $lineNumbers\n\n";

// 1. The reported symptom: three capitalisations of one language must stay one row.
list($rows, $resolved) = saveFormularkort(['Engelsk', 'engelsk', 'ENGELSK'], [1 => 'Engelsk'], $block);
check(
    'three capitalisations of Engelsk leave a single VSPR row',
    count($rows) === 1,
    count($rows) . ' rows: ' . json_encode($rows)
);
check(
    'every capitalisation resolves to the existing kodenr 1',
    array_values($resolved) === [1, 1, 1],
    json_encode($resolved)
);

// 2. A genuinely new language must still be created -- the fix must not block creation.
list($rows, $resolved) = saveFormularkort(['Tysk'], [1 => 'Engelsk'], $block);
check(
    'a new language is still inserted',
    count($rows) === 2,
    json_encode($rows)
);
check(
    'the new language gets the next kodenr',
    $resolved['Tysk'] == 2,
    json_encode($resolved)
);

// 3. Distinct languages are not merged. SST-745 rules out deduplicating by label; this confirms
//    the fix does nothing of the sort.
list($rows, $resolved) = saveFormularkort(['Engelsk', 'Tysk', 'Fransk'], [], $block);
check(
    'three distinct languages produce three rows',
    count($rows) === 3,
    json_encode($rows)
);

// 4. Existing duplicates are left alone -- cleaning them up is out of scope -- but a further save
//    must not add a fourth, and must resolve deterministically to the lowest kodenr.
list($rows, $resolved) = saveFormularkort(['engelsk'], [1 => 'Engelsk', 2 => 'engelsk', 3 => 'ENGELSK'], $block);
check(
    'pre-existing duplicate rows are preserved, not deleted',
    count($rows) === 3,
    json_encode($rows)
);
check(
    'a save against existing duplicates resolves to the lowest kodenr',
    $resolved['engelsk'] == 1,
    json_encode($resolved)
);

// 5. Dansk is the built-in default and is handled by the else branch.
list($rows, $resolved) = saveFormularkort(['Dansk'], [1 => 'Engelsk'], $block);
check(
    'Dansk resolves to sprog_id 0 without creating a row',
    count($rows) === 1 && $resolved['Dansk'] === 0,
    json_encode([$rows, $resolved])
);

echo "\n" . ($failures === 0 ? "All checks passed.\n" : "$failures check(s) failed.\n");
exit($failures === 0 ? 0 : 1);

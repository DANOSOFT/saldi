<?php
// -------------------------------------------lager/vareimport.pgp------------patch 1.1.2------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20260920 CDX/LUI Validate session-owned CSV previews and apply complete imports transactionally on PHP 8.

ob_start();
@session_start();
$s_id = session_id();
$title = "Vareimport";
$modulnr = 9;
include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
require_once(__DIR__ . "/../includes/std_func.php");
require_once(__DIR__ . "/../includes/legacyItemImport.php");

$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if (empty($_SESSION['item_import_csrf'])) {
    $_SESSION['item_import_csrf'] = bin2hex(random_bytes(32));
}
$state = $_SESSION['item_import_file'] ?? null;
if ($state && $state['db'] !== $db) { $state = null; }
$format = (string)ifset($_POST, 'splitter', 'Semikolon');
$labels = (array)ifset($_POST, 'feltnavn', []);
$defaultGroup = (int)ifset($_POST, 'varegrp', 0);
$supplierAccount = (string)ifset($_POST, 'leverandor', '');
$error = '';
$rows = $prepared = $groups = $suppliers = [];
$discount = 0.0;
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!hash_equals($_SESSION['item_import_csrf'], (string)ifset($_POST, 'csrf_token', ''))) {
            http_response_code(403);
            throw new InvalidArgumentException('Ugyldig formular. Åbn siden igen og prøv på ny.');
        }
        if (isset($_FILES['uploadedfile']) && $_FILES['uploadedfile']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['uploadedfile']['error'] !== UPLOAD_ERR_OK || $_FILES['uploadedfile']['size'] > 9999999) {
                throw new InvalidArgumentException('Filen kunne ikke hentes, eller den er større end 10 MB.');
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $db)) { throw new RuntimeException('Ugyldigt regnskab.'); }
            $directory = __DIR__ . '/../temp/' . $db;
            if (!is_dir($directory) && !mkdir($directory, 0770, true)) {
                throw new RuntimeException('Importmappen kunne ikke oprettes.');
            }
            $token = bin2hex(random_bytes(16));
            $path = $directory . '/item-import-' . $token . '.csv';
            if (!move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $path)) {
                throw new RuntimeException('Importfilen kunne ikke gemmes.');
            }
            if ($state && is_file($state['path']) && !is_link($state['path'])) { unlink($state['path']); }
            $state = ['db'=>$db, 'path'=>$path, 'token'=>$token];
            $_SESSION['item_import_file'] = $state;
            $labels = [];
        } elseif (!$state || !hash_equals($state['token'], (string)ifset($_POST, 'filnavn', ''))) {
            throw new InvalidArgumentException('Hent importfilen igen før visning eller import.');
        }
    }
    if ($state) {
        if (!is_file($state['path']) || is_link($state['path'])) {
            throw new InvalidArgumentException('Importfilen er ikke længere tilgængelig.');
        }
        $rows = legacyItemImportRead($state['path'], $format);
        $columnCount = max(array_map('count', $rows));
        $labels = array_slice(array_pad($labels, $columnCount, ''), 0, $columnCount);
        foreach ($labels as $label) {
            if (!is_string($label)) { throw new InvalidArgumentException('Ugyldig kolonnebetegnelse.'); }
        }
        $discountText = (string)ifset($_POST, 'rabat', '0');
        $discount = legacyItemImportNumber($discountText === '' ? '0' : $discountText);
        $year = (int)($regnaar ?? 0);
        $query = db_select("SELECT kodenr,beskrivelse FROM grupper WHERE art='VG' AND fiscal_year=$year ORDER BY kodenr", __FILE__ . ' linje ' . __LINE__);
        while ($row = db_fetch_array($query)) { $groups[(int)$row['kodenr']] = $row['beskrivelse']; }
        $query = db_select("SELECT id,kontonr,firmanavn FROM adresser WHERE art='K' ORDER BY kontonr", __FILE__ . ' linje ' . __LINE__);
        while ($row = db_fetch_array($query)) { $suppliers[(string)$row['kontonr']] = $row; }
        if (array_filter($labels)) {
            $prepared = legacyItemImportPrepare($rows, $labels, $defaultGroup, $discount, array_keys($groups));
        }
        if (ifset($_POST, 'submit') === 'Flyt') {
            if (!$prepared) { throw new InvalidArgumentException('Vælg kolonner før import.'); }
            if ($supplierAccount !== '' && !isset($suppliers[$supplierAccount])) {
                throw new InvalidArgumentException('Den valgte leverandør findes ikke.');
            }
            $count = legacyItemImportApply($prepared, (int)($suppliers[$supplierAccount]['id'] ?? 0));
            unlink($state['path']);
            unset($_SESSION['item_import_file']);
            $_SESSION['item_import_message'] = $count . ' varelinjer er importeret.';
            $_SESSION['item_import_csrf'] = bin2hex(random_bytes(32));
            header('Location: vareimport.php', true, 303);
            exit;
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

print "<h1>Vareimport</h1><p><a href='varer.php'>Tilbage til varer</a></p>";
if ($error !== '') { print "<p role='alert'>" . $escape($error) . "</p>"; }
if (!empty($_SESSION['item_import_message'])) {
    print '<p>' . $escape($_SESSION['item_import_message']) . '</p>';
    unset($_SESSION['item_import_message']);
}
$csrf = $escape($_SESSION['item_import_csrf']);
print '<form enctype="multipart/form-data" method="post">';
print "<input type='hidden' name='csrf_token' value='$csrf'>";
print '<input type="hidden" name="MAX_FILE_SIZE" value="9999999">';
print '<label>Vælg datafil: <input name="uploadedfile" type="file" required></label><button type="submit">Hent</button></form>';
if ($state && $rows) {
    print "<form method='post'><input type='hidden' name='csrf_token' value='$csrf'>";
    print "<input type='hidden' name='filnavn' value='" . $escape($state['token']) . "'>";
    print '<p>Salgspris i filen angives i øre. Kostpris angives i kroner. Hele filen kontrolleres før import.</p>';
    print '<label>Separatortegn <select name="splitter">';
    foreach (['Semikolon','Komma','Tabulator','Brdr. Dahl'] as $option) {
        print '<option' . ($format === $option ? ' selected' : '') . '>' . $escape($option) . '</option>';
    }
    print '</select></label> <label>Leverandør <select name="leverandor"><option value="">Ingen</option>';
    foreach ($suppliers as $account => $supplier) {
        print '<option value="' . $escape($account) . '"' . ((string)$account === $supplierAccount ? ' selected' : '') . '>' . $escape($account . ' : ' . $supplier['firmanavn']) . '</option>';
    }
    print '</select></label> <label>Varegruppe <select name="varegrp"><option value="0">Vælg</option>';
    foreach ($groups as $group => $description) {
        print '<option value="' . $group . '"' . ($group === $defaultGroup ? ' selected' : '') . '>' . $escape($group . ' : ' . $description) . '</option>';
    }
    print '</select></label> <label>Rabat % <input name="rabat" value="' . $escape($discount) . '"></label>';
    print '<button name="submit" value="Vis">Vis</button>';
    if ($prepared && $error === '') { print '<button name="submit" value="Flyt">Flyt</button>'; }
    print '<table><thead><tr>';
    foreach ($labels as $index => $label) {
        print '<th><select name="feltnavn[' . $index . ']">';
        foreach (['','Eget varenr.','Lev. varenr.','Begge varenr.','Beskrivelse','Salgspris','Kostpris','Enhed','Varegrp.'] as $option) {
            print '<option' . ($label === $option ? ' selected' : '') . '>' . $escape($option) . '</option>';
        }
        print '</select></th>';
    }
    print '</tr></thead><tbody>';
    foreach ($rows as $row) {
        print '<tr>';
        foreach ($row as $value) { print '<td>' . $escape($value) . '</td>'; }
        print '</tr>';
    }
    print '</tbody></table></form>';
}
print '</body></html>';

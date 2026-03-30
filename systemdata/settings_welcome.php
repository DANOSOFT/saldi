<?php
// --- systemdata/settings_welcome.php --- 2026-03-29 ---
// Landing page for the settings section when no ?sektion= is selected.
// Shows a grid of category cards for quick navigation.

if (!isset($docubizz)) $docubizz = null;
$hasPOS = file_exists("../debitor/pos_ordre.php");

// SVG icons (Material Symbols, viewBox="0 -960 960 960")
$svg = function(string $path) {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor"><path d="' . $path . '"/></svg>';
};

$welcome_categories = [
    [
        'icon'  => $svg('M400-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM80-160v-112q0-33 17-62t47-44q51-26 115-44t141-18h14q6 0 12 2q-8 18-13.5 37.5T407-360h-7q-68 0-121.5 16T180-306q-10 5-15 14.5t-5 19.5v32h247q8 22 19 42t26 38H80Zm560 40-12-60q-12-5-22.5-10.5T584-204l-58 18-40-68 46-40q-2-14-2-26t2-26l-46-40 40-68 58 18q11-8 21.5-13.5T628-460l12-60h80l12 60q12 5 22.5 10.5T776-436l58-18 40 68-46 40q2 14 2 26t-2 26l46 40-40 68-58-18q-11 8-21.5 13.5T732-180l-12 60h-80Zm40-120q33 0 56.5-23.5T760-320q0-33-23.5-56.5T680-400q-33 0-56.5 23.5T600-320q0 33 23.5 56.5T680-240ZM400-560q33 0 56.5-23.5T480-640q0-33-23.5-56.5T400-720q-33 0-56.5 23.5T320-640q0 33 23.5 56.5T400-560Zm0-80Zm12 400Z'),
        'title' => findtekst('783|Konto & Generelt', $sprog_id),
        'desc'  => 'Kontoindstillinger, personlige valg, sprog, brugere, ansatte, kontoplan og regnskabsår.',
        'link'  => 'diverse.php?sektion=kontoindstillinger',
        'count' => 7,
    ],
    [
        'icon'  => $svg('M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h167q11-35 43-57.5t70-22.5q40 0 71.5 22.5T594-840h166q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm80-80h280v-80H280v80Zm0-160h400v-80H280v80Zm0-160h400v-80H280v80ZM480-760q17 0 28.5-11.5T520-800q0-17-11.5-28.5T480-840q-17 0-28.5 11.5T440-800q0 17 11.5 28.5T480-760Z'),
        'title' => findtekst('786|Ordre & Salg', $sprog_id),
        'desc'  => 'Ordrebehandling, provisionsberegning, rykkere, massefakturering og øredifferencer.',
        'link'  => 'diverse.php?sektion=ordre_valg',
        'count' => 5,
    ],
    [
        'icon'  => $svg('M200-80q-33 0-56.5-23.5T120-160v-451q-18-11-29-28.5T80-680v-120q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v120q0 23-11 40.5T840-611v451q0 33-23.5 56.5T760-80H200Zm0-520v440h560v-440H200Zm-40-80h640v-120H160v120Zm200 280h240v-80H360v80Zm120 20Z'),
        'title' => findtekst('787|Varer & Lager', $sprog_id),
        'desc'  => 'Vareindstillinger, varianter, mærkater, prislister, rabatgrupper, enheder og prislisteimport.',
        'link'  => 'diverse.php?sektion=productOptions',
        'count' => 7,
    ],
    [
        'icon'  => $svg('M80-160v-560q0-33 23.5-56.5T160-800h440v160h160l160 213v187h-80q0 50-35 85t-85 35q-50 0-85-35t-35-85H360q0 50-35 85t-85 35q-50 0-85-35t-35-85h-80Zm280 0q0-17 11.5-28.5T400-200q17 0 28.5 11.5T440-160q0 17-11.5 28.5T400-120q-17 0-28.5-11.5T360-160Zm400 0q0-17 11.5-28.5T800-200q17 0 28.5 11.5T840-160q0 17-11.5 28.5T800-120q-17 0-28.5-11.5T760-160ZM160-320h48q23-28 57-44t75-16q41 0 75 16t57 44h208v-400H160v400Zm440 0h200l-80-120h-120v120Zm-440 0v-400 400Z'),
        'title' => 'Integration & Forsendelse',
        'desc'  => 'Fragtmænd, GLS, MobilePay, API, webshop, valuta' . ($docubizz ? ', DocuBizz' : '') . '.',
        'link'  => 'diverse.php?sektion=div_valg',
        'count' => $docubizz ? 5 : 4,
    ],
    [
        'icon'  => $svg('M120-160v-160h720v160H120Zm80-40h80v-80h-80v80Zm-80-440v-160h720v160H120Zm80-40h80v-80h-80v80Zm-80 240v-160h720v160H120Zm80-40h80v-80h-80v80Z'),
        'title' => 'Data & Værktøjer',
        'desc'  => 'Import & eksport, bilagshåndtering, tjeklister, projekter og tekster.',
        'link'  => 'diverse.php?sektion=div_io',
        'count' => 5,
    ],
];

if ($hasPOS) {
    // Insert POS before Data
    array_splice($welcome_categories, 4, 0, [[
        'icon'  => $svg('M280-600v-80h400v80H280Zm-80 440q-33 0-56.5-23.5T120-240v-480q0-33 23.5-56.5T200-800h560q33 0 56.5 23.5T840-720v480q0 33-23.5 56.5T760-160H200Zm0-80h560v-480H200v480Zm80-80h400v-240H280v240Zm0 0v-240 240Z'),
        'title' => findtekst('271|Kasseapparat', $sprog_id),
        'desc'  => 'Kasseopsætning, betalingskort, barcode-scanning og POS menuer.',
        'link'  => 'diverse.php?sektion=posOptions',
        'count' => 3,
    ]]);
}

print "<div class='settings-welcome'>\n";
print "<h1>Indstillinger</h1>\n";
print "<p class='welcome-subtitle'>Vælg en kategori for at komme i gang</p>\n";
print "<div class='settings-welcome-grid'>\n";

foreach ($welcome_categories as $cat) {
    print "<a class='settings-welcome-card' href='{$cat['link']}'>\n";
    print "<div class='card-icon'>{$cat['icon']}</div>\n";
    print "<h3>{$cat['title']}</h3>\n";
    print "<p>{$cat['desc']}</p>\n";
    print "<span class='card-count'>{$cat['count']} indstillinger</span>\n";
    print "</a>\n";
}

print "</div>\n";
print "</div>\n";
?>

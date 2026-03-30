<?php
// --- systemdata/settings_nav.php --- 2026-03-29 ---
// Unified sidebar navigation for the settings pages.
// Groups sections into logical categories with collapsible headings.
// Used by both T (top menu) and S (sidebar menu) modes.
//
// Each section entry is an array with:
//   'label' => display text
//   'href'  => (optional) link URL; defaults to diverse.php?sektion=KEY

if (!isset($sektion)) $sektion = null;
if (!isset($docubizz)) $docubizz = null;
$hasPOS = file_exists("../debitor/pos_ordre.php");

// SVG icons (Material Symbols, viewBox="0 -960 960 960")
$svg = function(string $path) {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor">' . $path . '</svg>';
};
$icon_account      = $svg('<path d="M400-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM80-160v-112q0-33 17-62t47-44q51-26 115-44t141-18h14q6 0 12 2q-8 18-13.5 37.5T407-360h-7q-68 0-121.5 16T180-306q-10 5-15 14.5t-5 19.5v32h247q8 22 19 42t26 38H80Zm560 40-12-60q-12-5-22.5-10.5T584-204l-58 18-40-68 46-40q-2-14-2-26t2-26l-46-40 40-68 58 18q11-8 21.5-13.5T628-460l12-60h80l12 60q12 5 22.5 10.5T776-436l58-18 40 68-46 40q2 14 2 26t-2 26l46 40-40 68-58-18q-11 8-21.5 13.5T732-180l-12 60h-80Zm40-120q33 0 56.5-23.5T760-320q0-33-23.5-56.5T680-400q-33 0-56.5 23.5T600-320q0 33 23.5 56.5T680-240ZM400-560q33 0 56.5-23.5T480-640q0-33-23.5-56.5T400-720q-33 0-56.5 23.5T320-640q0 33 23.5 56.5T400-560Zm0-80Zm12 400Z"/>');
$icon_orders       = $svg('<path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h167q11-35 43-57.5t70-22.5q40 0 71.5 22.5T594-840h166q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm80-80h280v-80H280v80Zm0-160h400v-80H280v80Zm0-160h400v-80H280v80ZM480-760q17 0 28.5-11.5T520-800q0-17-11.5-28.5T480-840q-17 0-28.5 11.5T440-800q0 17 11.5 28.5T480-760Z"/>');
$icon_products     = $svg('<path d="M200-80q-33 0-56.5-23.5T120-160v-451q-18-11-29-28.5T80-680v-120q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v120q0 23-11 40.5T840-611v451q0 33-23.5 56.5T760-80H200Zm0-520v440h560v-440H200Zm-40-80h640v-120H160v120Zm200 280h240v-80H360v80Zm120 20Z"/>');
$icon_integrations = $svg('<path d="M80-160v-560q0-33 23.5-56.5T160-800h440v160h160l160 213v187h-80q0 50-35 85t-85 35q-50 0-85-35t-35-85H360q0 50-35 85t-85 35q-50 0-85-35t-35-85h-80Zm280 0q0-17 11.5-28.5T400-200q17 0 28.5 11.5T440-160q0 17-11.5 28.5T400-120q-17 0-28.5-11.5T360-160Zm400 0q0-17 11.5-28.5T800-200q17 0 28.5 11.5T840-160q0 17-11.5 28.5T800-120q-17 0-28.5-11.5T760-160ZM160-320h48q23-28 57-44t75-16q41 0 75 16t57 44h208v-400H160v400Zm440 0h200l-80-120h-120v120Zm-440 0v-400 400Z"/>');
$icon_data         = $svg('<path d="M120-160v-160h720v160H120Zm80-40h80v-80h-80v80Zm-80-440v-160h720v160H120Zm80-40h80v-80h-80v80Zm-80 240v-160h720v160H120Zm80-40h80v-80h-80v80Z"/>');
$icon_pos          = $svg('<path d="M280-600v-80h400v80H280Zm-80 440q-33 0-56.5-23.5T120-240v-480q0-33 23.5-56.5T200-800h560q33 0 56.5 23.5T840-720v480q0 33-23.5 56.5T760-160H200Zm0-80h560v-480H200v480Zm80-80h400v-240H280v240Zm0 0v-240 240Z"/>');

// Category -> section mapping
$settings_categories = [
    'account' => [
        'label' => findtekst('783|Konto & Generelt', $sprog_id),
        'icon'  => $icon_account,
        'sections' => [
            'kontoindstillinger' => ['label' => findtekst('783|Kontoindstillinger', $sprog_id)],
            'userSettings'       => ['label' => findtekst('785|Personlige valg', $sprog_id)],
            'sprog'              => ['label' => findtekst('801|Sprog', $sprog_id)],
            'brugere'            => ['label' => findtekst('777|Brugere', $sprog_id), 'href' => 'brugere.php'],
            'Stamdata'           => ['label' => findtekst('779|Stamdata', $sprog_id), 'href' => 'stamkort.php'],
            'ansatte'            => ['label' => findtekst('1262|Personalekort', $sprog_id), 'href' => 'ansatte.php'],
            'kontoplan'          => ['label' => findtekst('113|Kontoplan', $sprog_id), 'href' => 'kontoplan.php'],
            'regnskabsaar'       => ['label' => findtekst('778|Regnskabsår', $sprog_id), 'href' => 'regnskabsaar.php'],
        ]
    ],
    'orders' => [
        'label' => findtekst('786|Ordre & Salg', $sprog_id),
        'icon'  => $icon_orders,
        'sections' => [
            'ordre_valg'  => ['label' => findtekst('786|Ordrerelaterede valg', $sprog_id)],
            'provision'   => ['label' => findtekst('784|Provisionsberegning', $sprog_id)],
            'rykker_valg' => ['label' => findtekst('793|Rykkerrelaterede valg', $sprog_id)],
            'massefakt'   => ['label' => findtekst('200|Massefakturering', $sprog_id)],
            'orediff'     => ['label' => findtekst('170|Øredifferencer', $sprog_id)],
        ]
    ],
    'products' => [
        'label' => findtekst('787|Varer & Lager', $sprog_id),
        'icon'  => $icon_products,
        'sections' => [
            'productOptions' => ['label' => findtekst('787|Varerelaterede valg', $sprog_id)],
            'variant_valg'   => ['label' => findtekst('788|Variantrelaterede valg', $sprog_id)],
            'labels'         => ['label' => findtekst('791|Mærkater', $sprog_id)],
            'pricelists'     => ['label' => findtekst('792|Prislister', $sprog_id)],
            'rabatgrupper'   => ['label' => 'Rabatgrupper', 'href' => 'rabatgrupper.php'],
            'enheder'        => ['label' => 'Enheder', 'href' => 'enheder.php'],
            'prisliste'      => ['label' => 'Prislisteimport', 'href' => 'prisliste.php'],
        ]
    ],
    'integrations' => [
        'label' => 'Integration & Forsendelse',
        'icon'  => $icon_integrations,
        'sections' => [
            'div_valg'  => ['label' => findtekst('794|Diverse valg', $sprog_id)],
            'api_valg'  => ['label' => 'API'],
            'shop_valg' => ['label' => findtekst('789|Shoprelaterede valg', $sprog_id)],
            'valuta'    => ['label' => 'Valuta', 'href' => 'valuta.php'],
        ]
    ],
    'data' => [
        'label' => 'Data & Værktøjer',
        'icon'  => $icon_data,
        'sections' => [
            'div_io'     => ['label' => findtekst('802|Import & eksport', $sprog_id)],
            'bilag'      => ['label' => findtekst('797|Bilagshåndtering', $sprog_id)],
            'tjekliste'  => ['label' => findtekst('796|Tjeklister', $sprog_id)],
            'projekter'  => ['label' => 'Projekter', 'href' => 'projekter.php'],
            'tekster'    => ['label' => 'Tekster', 'href' => 'tekster.php'],
        ]
    ],
];

// Conditionally add DocuBizz to integrations
if ($docubizz) {
    $settings_categories['integrations']['sections']['docubizz'] = ['label' => 'DocuBizz'];
}

// Conditionally add POS category
if ($hasPOS) {
    // Insert before 'data' category
    $pos_cat = [
        'pos' => [
            'label' => findtekst('271|Kasseapparat', $sprog_id),
            'icon'  => $icon_pos,
            'sections' => [
                'posOptions'  => ['label' => findtekst('271|PoS-valg', $sprog_id)],
                'barcodescan' => ['label' => 'App Barcode'],
                'posmenuer'   => ['label' => 'POS menuer', 'href' => 'posmenuer.php'],
            ]
        ]
    ];
    $data_cat = $settings_categories['data'];
    unset($settings_categories['data']);
    $settings_categories = array_merge($settings_categories, $pos_cat, ['data' => $data_cat]);
}

// Determine which category the active section belongs to
$active_category = null;
foreach ($settings_categories as $cat_key => $cat) {
    if (array_key_exists($sektion, $cat['sections'])) {
        $active_category = $cat_key;
        break;
    }
}

// Render sidebar
print "<div class='settings-sidebar-inner'>\n";
print "<p class='settings-sidebar-title'>Indstillinger</p>\n";

foreach ($settings_categories as $cat_key => $cat) {
    $is_open = ($cat_key === $active_category) ? ' open' : '';
    print "<div class='settings-nav-category{$is_open}' data-category='{$cat_key}'>\n";
    print "<button type='button' class='settings-nav-category-btn' onclick='toggleSettingsCategory(this)'>";
    print "<span class='nav-icon'>{$cat['icon']}</span>";
    print "<span>{$cat['label']}</span>";
    print "<span class='nav-chevron'>&#9654;</span>";
    print "</button>\n";
    print "<ul class='settings-nav-items'>\n";

    foreach ($cat['sections'] as $sek_key => $sek_data) {
        $sek_label = $sek_data['label'];
        $href = isset($sek_data['href']) ? $sek_data['href'] : "diverse.php?sektion={$sek_key}";
        $is_active = ($sektion === $sek_key) ? ' active' : '';
        print "<li class='settings-nav-item{$is_active}'>";
        print "<a href='{$href}'>{$sek_label}</a>";
        print "</li>\n";
    }

    print "</ul>\n";
    print "</div>\n";
}

print "</div>\n";
?>
<script>
function toggleSettingsCategory(btn) {
    var category = btn.closest('.settings-nav-category');
    category.classList.toggle('open');
}
</script>

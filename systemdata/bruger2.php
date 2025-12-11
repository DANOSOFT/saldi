<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------systemdata/brugere.php-----patch 5.0.0 ----2024-12-11-----
//                           LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// Complete redesign with improved UX/UI and grouped permissions

@session_start();
$s_id=session_id();

$modulnr=1;
$title="Brugere";
$css="../css/standard.css";

$employeeId=$rights=$roRights=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

// Module definitions with categories
$moduleConfig = [
    // Index => [name_key, category, icon, supports_readonly]
    0 => ['113|Kontoplan', 'finance', '📊', false],
    1 => ['122|Indstillinger', 'system', '⚙️', false],
    2 => ['601|Kassekladde', 'finance', '📝', false],
    3 => ['322|Regnskab', 'finance', '📈', false],
    4 => ['895|Finansrapport', 'finance', '📋', false],
    5 => ['1255|Debitorordre', 'sales', '🛒', false],
    6 => ['1256|Debitorkonti', 'sales', '👥', false],
    7 => ['1257|Kreditorordre', 'purchasing', '📦', false],
    8 => ['1258|Kreditorkonti', 'purchasing', '🏢', false],
    9 => ['609|Varer', 'inventory', '📦', true],  // Only this supports read-only
    10 => ['1259|Enheder', 'inventory', '📏', false],
    11 => ['521|Sikkerhedskopi', 'system', '💾', false],
    12 => ['449|Debitorrapporter', 'sales', '📊', false],
    13 => ['1140|Kreditorrapport', 'purchasing', '📊', false],
    14 => ['1260|Produktionsordre', 'inventory', '🏭', false],
    15 => ['965|Varerapport', 'inventory', '📈', false],
];

$categories = [
    'finance' => ['name' => findtekst('322|Finans', $sprog_id), 'icon' => '💰', 'color' => '#10b981'],
    'sales' => ['name' => findtekst('1255|Salg', $sprog_id), 'icon' => '📈', 'color' => '#3b82f6'],
    'purchasing' => ['name' => findtekst('1257|Indkøb', $sprog_id), 'icon' => '🛍️', 'color' => '#8b5cf6'],
    'inventory' => ['name' => findtekst('609|Lager', $sprog_id), 'icon' => '📦', 'color' => '#f59e0b'],
    'system' => ['name' => findtekst('122|System', $sprog_id), 'icon' => '⚙️', 'color' => '#6b7280'],
];

// Build module names array
$moduleNames = [];
foreach ($moduleConfig as $idx => $config) {
    $moduleNames[$idx] = findtekst($config[0], $sprog_id);
}

// Process form submissions
$addUser = if_isset($_POST['addUser']);
$deleteUser = if_isset($_POST['deleteUser']);
$id = if_isset($_POST['id']);
$updateUser = if_isset($_POST['updateUser']);
$ret_id = if_isset($_GET['ret_id']);
$add = if_isset($_GET['add']);

if ($addUser || $updateUser) {
    $tmp = if_isset($_POST['random']);
    $brugernavn = trim(if_isset($_POST[$tmp]));
    $kode = trim(if_isset($_POST['kode']));
    $kode2 = trim(if_isset($_POST['kode2']));
    $tlf = trim(if_isset($_POST['tlf']));
    $email = trim(if_isset($_POST['email']));
    $medarbejder = trim(if_isset($_POST['medarbejder']));
    $employeeId = if_isset($_POST['employeeId']);
    $twofactor = if_isset($_POST['twofactor']) ? 't' : 'f';
    $insert_ip = if_isset($_POST['insert_ip']);
    $afd = if_isset($_POST['afdeling']);
    $rights = $_POST['rights'] ?? [];
    $roRights = $_POST['roRights'] ?? [];
    
    $rettigheder = '';
    for ($x = 0; $x < 16; $x++) {
        if (!isset($rights[$x])) $rights[$x] = null;
        if (!isset($roRights[$x])) $roRights[$x] = null;
        if ($roRights[$x] == 'on') $rettigheder .= '2';
        elseif ($rights[$x] == 'on') $rettigheder .= '1';
        else $rettigheder .= '0';
    }
    
    $brugernavn = trim($brugernavn);
    $alerttext = null;
    
    if ($kode && $kode != $kode2) {
        $alerttext = findtekst('2476|Adgangskoder er ikke ens', $sprog_id);
        $kode = null;
        $ret_id = $id;
    }
    
    $employeeId[0] = (int)($employeeId[0] ?? 0);
    
    if ($addUser && $brugernavn && !$alerttext) {
        $query = db_select("select id from brugere where brugernavn = '$brugernavn'", __FILE__ . " linje " . __LINE__);
        if ($row = db_fetch_array($query)) {
            $alerttext = findtekst('2477|Der findes allerede en bruger med dette brugernavn', $sprog_id);
        } else {
            if (!$regnaar) $regnaar = 1;
            $qtxt = "insert into brugere (brugernavn,kode,rettigheder,regnskabsaar,ansat_id,ip_address,tlf,twofactor,email) ";
            $qtxt .= "values ('$brugernavn','$kode','$rettigheder','$regnaar',$employeeId[0],'$insert_ip','$tlf','$twofactor','$email')";
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            header("Location: brugere.php");
            exit;
        }
    }
    
    if ($id && $kode && $brugernavn && !$alerttext) {
        if (strstr($kode, '**********')) {
            db_modify("update brugere set brugernavn='$brugernavn', rettigheder='$rettigheder', ansat_id=$employeeId[0], ip_address = '$insert_ip', tlf = '$tlf', twofactor = '$twofactor', email = '$email' where id=$id", __FILE__ . " linje " . __LINE__);
        } else {
            $kode = saldikrypt($id, $kode);
            db_modify("update brugere set brugernavn='$brugernavn', kode='$kode', rettigheder='$rettigheder', ansat_id=$employeeId[0], ip_address = '$insert_ip', tlf = '$tlf', twofactor = '$twofactor', email = '$email' where id=$id", __FILE__ . " linje " . __LINE__);
        }
        update_settings_value('afd', 'brugerAfd', $afd, '', $id);
        header("Location: brugere.php");
        exit;
    }
} elseif ($deleteUser) {
    $qtxt = "select ansat_id from brugere where id ='$id'";
    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    if ($r['ansat_id']) {
        $qtxt = "update ansatte set lukket='on', slutdate='" . date("Y-m-d") . "' where id = '$r[ansat_id]'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    }
    db_modify("delete from brugere where id = $id", __FILE__ . " linje " . __LINE__);
    header("Location: brugere.php");
    exit;
}

// Include header
if ($menu == 'T') {
    include_once '../includes/top_header.php';
    include_once '../includes/top_menu.php';
    print "<div id=\"header\">";
    print "<div class=\"headerbtnLft headLink\">&nbsp;</div>";
    print "<div class=\"headerTxt\">$title</div>";
    print "<div class=\"headerbtnRght headLink\">&nbsp;</div>";
    print "</div>";
    print "<div class='content-noside'>";
    print "<div id=\"leftmenuholder\">";
    include_once 'left_menu.php';
    print "</div>";
    print "<div class=\"maincontentLargeHolder\">";
} else {
    include("top.php");
}
?>

<style>
:root {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-card: #1e293b;
    --bg-hover: #334155;
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --text-muted: #64748b;
    --border-color: #334155;
    --accent-primary: #06b6d4;
    --accent-success: #10b981;
    --accent-warning: #f59e0b;
    --accent-danger: #ef4444;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.3);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.4);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.4);
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 16px;
}

.brugere-app {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--bg-primary);
    min-height: 100vh;
    padding: 24px;
    color: var(--text-primary);
}

.brugere-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding: 24px 28px;
    background: linear-gradient(135deg, var(--bg-secondary) 0%, #2d3a4f 100%);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-lg);
}

.brugere-header h1 {
    margin: 0;
    font-size: 26px;
    font-weight: 700;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent-primary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.brugere-header .subtitle {
    color: var(--text-secondary);
    font-size: 14px;
    margin-top: 4px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent-primary) 0%, #0891b2 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(6, 182, 212, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, var(--accent-success) 0%, #059669 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, var(--accent-danger) 0%, #dc2626 100%);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
}

.btn-ghost {
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
}

.btn-ghost:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.btn-sm {
    padding: 8px 14px;
    font-size: 13px;
}

/* Users Grid */
.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.user-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
}

.user-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--accent-primary);
}

.user-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.1) 0%, transparent 100%);
    border-bottom: 1px solid var(--border-color);
}

.user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent-primary) 0%, #0891b2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    color: white;
    text-transform: uppercase;
}

.user-info {
    flex: 1;
    margin-left: 16px;
}

.user-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.user-meta {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 2px;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-gold {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: #78350f;
}

.badge-2fa {
    background: rgba(16, 185, 129, 0.2);
    color: var(--accent-success);
    border: 1px solid var(--accent-success);
}

.user-card-body {
    padding: 16px 20px;
}

.permissions-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.perm-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
    background: var(--bg-hover);
    color: var(--text-secondary);
    transition: all 0.2s;
}

.perm-chip.active {
    background: rgba(16, 185, 129, 0.15);
    color: var(--accent-success);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.perm-chip.readonly {
    background: rgba(245, 158, 11, 0.15);
    color: var(--accent-warning);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.perm-chip.inactive {
    opacity: 0.4;
}

.user-card-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid var(--border-color);
}

/* Form Container */
.form-container {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.form-header {
    padding: 24px 28px;
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.1) 0%, transparent 100%);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
}

.form-body {
    padding: 28px;
}

.form-section {
    margin-bottom: 32px;
}

.form-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.form-input, .form-select {
    padding: 12px 14px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    font-size: 14px;
    color: var(--text-primary);
    transition: all 0.2s;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: var(--accent-primary);
    box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15);
}

.form-input::placeholder {
    color: var(--text-muted);
}

.checkbox-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
}

.checkbox-row:hover {
    border-color: var(--accent-primary);
}

.checkbox-row input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--accent-primary);
}

/* Permissions Section */
.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.permission-category {
    background: var(--bg-primary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.category-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.2s;
}

.category-header:hover {
    background: var(--bg-secondary);
}

.category-icon {
    font-size: 18px;
}

.category-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    flex: 1;
}

.category-toggle {
    font-size: 12px;
    color: var(--text-muted);
    transition: transform 0.2s;
}

.category-body {
    padding: 12px;
}

.permission-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    margin-bottom: 6px;
    transition: background 0.2s;
}

.permission-item:last-child {
    margin-bottom: 0;
}

.permission-item:hover {
    background: var(--bg-hover);
}

.permission-name {
    font-size: 13px;
    color: var(--text-secondary);
}

.permission-controls {
    display: flex;
    gap: 16px;
}

.permission-controls label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-muted);
    cursor: pointer;
}

.permission-controls input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--accent-primary);
}

.permission-controls input[type="checkbox"]:disabled {
    opacity: 0.3;
}

/* Form Footer */
.form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 28px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid var(--border-color);
}

.form-footer-left {
    display: flex;
    gap: 12px;
}

.form-footer-right {
    display: flex;
    gap: 12px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.empty-text {
    font-size: 14px;
    margin-bottom: 24px;
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

/* Responsive */
@media (max-width: 768px) {
    .brugere-app {
        padding: 16px;
    }
    
    .brugere-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .users-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
    
    .form-footer {
        flex-direction: column;
        gap: 16px;
    }
    
    .form-footer-left, .form-footer-right {
        width: 100%;
        justify-content: center;
    }
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.user-card {
    animation: fadeIn 0.3s ease forwards;
}

.user-card:nth-child(2) { animation-delay: 0.05s; }
.user-card:nth-child(3) { animation-delay: 0.1s; }
.user-card:nth-child(4) { animation-delay: 0.15s; }
.user-card:nth-child(5) { animation-delay: 0.2s; }
</style>

<div class="brugere-app">
<?php
// Get revisor user
$revisorQuery = db_select("SELECT * FROM settings WHERE var_name = 'revisor' AND var_grp = 'system'", __FILE__ . " linje " . __LINE__);
$revisorUserId = 0;
if (db_num_rows($revisorQuery) > 0) {
    $revisorRow = db_fetch_array($revisorQuery);
    $revisorUserId = $revisorRow['user_id'] ?? 0;
}

// Show alert if set
if (isset($alerttext) && $alerttext): ?>
    <div class="alert alert-error">
        <span>⚠️</span>
        <span><?php echo htmlspecialchars($alerttext); ?></span>
    </div>
<?php endif;

if ($ret_id || $add):
    // EDIT/ADD FORM
    $isEdit = (bool)$ret_id;
    $userData = null;
    $userRights = str_repeat('0', 16);
    $afd = 0;
    
    if ($isEdit) {
        $query = db_select("select * from brugere where id = $ret_id", __FILE__ . " linje " . __LINE__);
        $userData = db_fetch_array($query);
        $userRights = $userData['rettigheder'] ?? str_repeat('0', 16);
        $afd = get_settings_value('afd', 'brugerAfd', 0, $userData['id']);
    }
    
    // Get employees
    $employees = [];
    if ($r2 = db_fetch_array(db_select("select id from adresser where art = 'S'", __FILE__ . " linje " . __LINE__))) {
        $q2 = db_select("select * from ansatte where konto_id = $r2[id] and lukket!='on' order by initialer", __FILE__ . " linje " . __LINE__);
        while ($emp = db_fetch_array($q2)) {
            $employees[] = $emp;
        }
    }
    
    // Get departments
    $departments = [];
    $deptQuery = db_select("select * from grupper where art = 'AFD'", __FILE__ . " linje " . __LINE__);
    while ($dept = db_fetch_array($deptQuery)) {
        $departments[] = $dept;
    }
    
    $tmp = "navn" . rand(100, 999);
?>
    <div class="brugere-header">
        <div>
            <h1><?php echo $isEdit ? '✏️ ' . findtekst('1091|Rediger bruger', $sprog_id) : '➕ ' . findtekst('333|Ny bruger', $sprog_id); ?></h1>
            <div class="subtitle"><?php echo $isEdit ? htmlspecialchars($userData['brugernavn']) : findtekst('2478|Opret en ny bruger i systemet', $sprog_id); ?></div>
        </div>
        <a href="brugere.php" class="btn btn-ghost">← <?php echo findtekst('1090|Tilbage', $sprog_id); ?></a>
    </div>
    
    <form method="post" action="brugere.php" class="form-container">
        <input type="hidden" name="random" value="<?php echo $tmp; ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $userData['id']; ?>">
        <?php endif; ?>
        
        <div class="form-body">
            <!-- Basic Info Section -->
            <div class="form-section">
                <div class="section-title">
                    <span>👤</span>
                    <?php echo findtekst('2479|Grundlæggende oplysninger', $sprog_id); ?>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label"><?php echo findtekst('225|Brugernavn', $sprog_id); ?> *</label>
                        <input type="text" name="<?php echo $tmp; ?>" class="form-input" 
                               value="<?php echo $isEdit ? htmlspecialchars($userData['brugernavn']) : ''; ?>" 
                               placeholder="<?php echo findtekst('2480|Indtast brugernavn', $sprog_id); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo findtekst('589|Ansat', $sprog_id); ?></label>
                        <select name="employeeId[0]" class="form-select">
                            <option value="0">-- <?php echo findtekst('2481|Vælg ansat', $sprog_id); ?> --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                    <?php echo ($isEdit && $userData['ansat_id'] == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['initialer']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo findtekst('2482|Afdeling', $sprog_id); ?></label>
                        <select name="afdeling" class="form-select">
                            <option value="0">-- <?php echo findtekst('2483|Vælg afdeling', $sprog_id); ?> --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['kodenr']; ?>"
                                    <?php echo ($dept['kodenr'] == $afd) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['beskrivelse']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Security Section -->
            <div class="form-section">
                <div class="section-title">
                    <span>🔐</span>
                    <?php echo findtekst('2484|Sikkerhed', $sprog_id); ?>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label"><?php echo findtekst('324|Adgangskode', $sprog_id); ?><?php echo $isEdit ? '' : ' *'; ?></label>
                        <input type="password" name="kode" class="form-input" 
                               value="<?php echo $isEdit ? '********************' : ''; ?>"
                               placeholder="<?php echo $isEdit ? findtekst('2485|Lad stå for at beholde', $sprog_id) : ''; ?>"
                               <?php echo $isEdit ? '' : 'required'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo findtekst('328|Gentag adgangskode', $sprog_id); ?></label>
                        <input type="password" name="kode2" class="form-input" 
                               value="<?php echo $isEdit ? '********************' : ''; ?>"
                               <?php echo $isEdit ? '' : 'required'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo findtekst('1904|IP adresser', $sprog_id); ?></label>
                        <input type="text" name="insert_ip" class="form-input" maxlength="49"
                               value="<?php echo $isEdit ? htmlspecialchars($userData['ip_address']) : ''; ?>"
                               placeholder="<?php echo findtekst('2486|Kommasepareret liste', $sprog_id); ?>">
                    </div>
                </div>
            </div>
            
            <!-- 2FA Section -->
            <div class="form-section">
                <div class="section-title">
                    <span>📱</span>
                    <?php echo findtekst('2487|To-faktor autentificering', $sprog_id); ?>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label"><?php echo findtekst('2488|Telefon (til 2FA)', $sprog_id); ?></label>
                        <input type="tel" name="tlf" class="form-input"
                               value="<?php echo $isEdit ? htmlspecialchars($userData['tlf']) : ''; ?>"
                               placeholder="+45 12 34 56 78">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo findtekst('2489|Email (til 2FA)', $sprog_id); ?></label>
                        <input type="email" name="email" class="form-input"
                               value="<?php echo $isEdit ? htmlspecialchars($userData['email']) : ''; ?>"
                               placeholder="bruger@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-row">
                            <input type="checkbox" name="twofactor" 
                                <?php echo ($isEdit && $userData['twofactor'] == 't') ? 'checked' : ''; ?>>
                            <span><?php echo findtekst('2490|Aktiver to-faktor autentificering', $sprog_id); ?></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Permissions Section -->
            <div class="form-section">
                <div class="section-title">
                    <span>🔑</span>
                    <?php echo findtekst('329|Adgang til moduler', $sprog_id); ?>
                </div>
                
                <div class="permissions-grid">
                    <?php
                    // Group modules by category
                    $modulesByCategory = [];
                    foreach ($moduleConfig as $idx => $config) {
                        $cat = $config[1];
                        if (!isset($modulesByCategory[$cat])) {
                            $modulesByCategory[$cat] = [];
                        }
                        $modulesByCategory[$cat][$idx] = $config;
                    }
                    
                    foreach ($categories as $catKey => $catInfo):
                        if (!isset($modulesByCategory[$catKey])) continue;
                    ?>
                    <div class="permission-category">
                        <div class="category-header" style="border-left: 3px solid <?php echo $catInfo['color']; ?>;">
                            <span class="category-icon"><?php echo $catInfo['icon']; ?></span>
                            <span class="category-name"><?php echo htmlspecialchars($catInfo['name']); ?></span>
                        </div>
                        <div class="category-body">
                            <?php foreach ($modulesByCategory[$catKey] as $modIdx => $modConfig):
                                $hasAccess = (substr($userRights, $modIdx, 1) >= 1);
                                $isReadOnly = (substr($userRights, $modIdx, 1) == 2);
                                $supportsReadOnly = $modConfig[3];
                            ?>
                            <div class="permission-item">
                                <span class="permission-name"><?php echo htmlspecialchars($moduleNames[$modIdx]); ?></span>
                                <div class="permission-controls">
                                    <label>
                                        <input type="checkbox" name="rights[<?php echo $modIdx; ?>]" 
                                            <?php echo $hasAccess ? 'checked' : ''; ?>>
                                        <?php echo findtekst('2491|Adgang', $sprog_id); ?>
                                    </label>
                                    <?php if ($supportsReadOnly): ?>
                                    <label>
                                        <input type="checkbox" name="roRights[<?php echo $modIdx; ?>]"
                                            <?php echo $isReadOnly ? 'checked' : ''; ?>>
                                        <?php echo findtekst('2475|Kun visning', $sprog_id); ?>
                                    </label>
                                    <?php else: ?>
                                    <label>
                                        <input type="checkbox" name="roRights[<?php echo $modIdx; ?>]" disabled>
                                        <span style="opacity: 0.4;"><?php echo findtekst('2475|Kun visning', $sprog_id); ?></span>
                                    </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="form-footer">
            <div class="form-footer-left">
                <?php if ($isEdit): ?>
                <button type="submit" name="deleteUser" class="btn btn-danger" 
                        onclick="return confirm('<?php echo findtekst('2492|Er du sikker på at du vil slette denne bruger?', $sprog_id); ?>');">
                    🗑️ <?php echo findtekst('1099|Slet', $sprog_id); ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="form-footer-right">
                <a href="brugere.php" class="btn btn-ghost"><?php echo findtekst('1090|Annuller', $sprog_id); ?></a>
                <button type="submit" name="<?php echo $isEdit ? 'updateUser' : 'addUser'; ?>" class="btn btn-success">
                    ✓ <?php echo $isEdit ? findtekst('1091|Opdater', $sprog_id) : findtekst('1175|Tilføj', $sprog_id); ?>
                </button>
            </div>
        </div>
    </form>

<?php else:
    // USER LIST VIEW
?>
    <div class="brugere-header">
        <div>
            <h1>👥 <?php echo findtekst('225|Brugere', $sprog_id); ?></h1>
            <div class="subtitle"><?php echo findtekst('2493|Administrer brugere og deres rettigheder', $sprog_id); ?></div>
        </div>
        <a href="brugere.php?add=1" class="btn btn-success">➕ <?php echo findtekst('333|Ny bruger', $sprog_id); ?></a>
    </div>
    
    <?php
    $usersQuery = db_select("select * from brugere order by brugernavn", __FILE__ . " linje " . __LINE__);
    $userCount = 0;
    ?>
    
    <div class="users-grid">
        <?php while ($user = db_fetch_array($usersQuery)): 
            $userCount++;
            $initials = strtoupper(substr($user['brugernavn'], 0, 2));
            $isRevisor = ($revisorUserId == $user['id']);
            $has2FA = ($user['twofactor'] == 't');
            $rights = $user['rettigheder'] ?? '';
            
            // Count active permissions
            $activeCount = 0;
            $readOnlyCount = 0;
            for ($i = 0; $i < strlen($rights); $i++) {
                if ($rights[$i] == '2') $readOnlyCount++;
                elseif ($rights[$i] == '1') $activeCount++;
            }
            
            // Get employee initials
            $empInitials = '';
            if ($user['ansat_id']) {
                $empQuery = db_select("select initialer from ansatte where id = " . (int)$user['ansat_id'], __FILE__ . " linje " . __LINE__);
                if ($empRow = db_fetch_array($empQuery)) {
                    $empInitials = $empRow['initialer'];
                }
            }
        ?>
        <div class="user-card">
            <div class="user-card-header">
                <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div class="user-info">
                    <h3 class="user-name"><?php echo htmlspecialchars($user['brugernavn']); ?></h3>
                    <div class="user-meta">
                        <?php if ($empInitials): ?>
                            <?php echo htmlspecialchars($empInitials); ?>
                        <?php else: ?>
                            <?php echo findtekst('2494|Ingen ansat tilknyttet', $sprog_id); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-end;">
                    <?php if ($isRevisor): ?>
                        <span class="badge badge-gold">⭐ <?php echo findtekst('2562|Revisor', $sprog_id); ?></span>
                    <?php endif; ?>
                    <?php if ($has2FA): ?>
                        <span class="badge badge-2fa">🔐 2FA</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="user-card-body">
                <div class="permissions-summary">
                    <?php
                    // Show category summary
                    $catSummary = [];
                    foreach ($moduleConfig as $idx => $config) {
                        $cat = $config[1];
                        if (!isset($catSummary[$cat])) {
                            $catSummary[$cat] = ['total' => 0, 'active' => 0, 'readonly' => 0];
                        }
                        $catSummary[$cat]['total']++;
                        if (isset($rights[$idx])) {
                            if ($rights[$idx] == '2') $catSummary[$cat]['readonly']++;
                            elseif ($rights[$idx] == '1') $catSummary[$cat]['active']++;
                        }
                    }
                    
                    foreach ($categories as $catKey => $catInfo):
                        if (!isset($catSummary[$catKey])) continue;
                        $summary = $catSummary[$catKey];
                        $hasAny = ($summary['active'] > 0 || $summary['readonly'] > 0);
                        $hasReadOnly = $summary['readonly'] > 0;
                        $chipClass = $hasReadOnly ? 'readonly' : ($hasAny ? 'active' : 'inactive');
                    ?>
                        <span class="perm-chip <?php echo $chipClass; ?>" title="<?php echo htmlspecialchars($catInfo['name']); ?>: <?php echo $summary['active']; ?>/<?php echo $summary['total']; ?> moduler">
                            <?php echo $catInfo['icon']; ?>
                            <?php echo htmlspecialchars($catInfo['name']); ?>
                            <span style="opacity: 0.7;">(<?php echo $summary['active'] + $summary['readonly']; ?>/<?php echo $summary['total']; ?>)</span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="user-card-footer">
                <?php if (!$isRevisor && $revisorUserId == 0): ?>
                <button type="button" class="btn btn-ghost btn-sm revisor-btn" data-id="<?php echo $user['id']; ?>">
                    ⭐ <?php echo findtekst('2495|Gør til revisor', $sprog_id); ?>
                </button>
                <?php endif; ?>
                <a href="brugere.php?ret_id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">
                    ✏️ <?php echo findtekst('1091|Rediger', $sprog_id); ?>
                </a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    
    <?php if ($userCount == 0): ?>
    <div class="empty-state">
        <div class="empty-icon">👥</div>
        <h3 class="empty-title"><?php echo findtekst('2496|Ingen brugere fundet', $sprog_id); ?></h3>
        <p class="empty-text"><?php echo findtekst('2497|Kom i gang ved at oprette den første bruger', $sprog_id); ?></p>
        <a href="brugere.php?add=1" class="btn btn-success">➕ <?php echo findtekst('333|Ny bruger', $sprog_id); ?></a>
    </div>
    <?php endif; ?>
    
    <script>
    const db = "<?php echo $db; ?>";
    const confirmMessage = <?php echo json_encode(findtekst('2563|Vil du gøre denne bruger til revisor? Kun én bruger kan have revisoradgang, og du kan ikke ændre hvilken bruger der er revisor uden at kontakte Saldi support.', $sprog_id)); ?>;
    
    document.querySelectorAll('.revisor-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm(confirmMessage)) {
                fetch("brugereRevisor.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "id=" + this.dataset.id + "&db=" + db
                }).then(() => window.location.reload());
            }
        });
    });
    </script>
<?php endif; ?>
</div>

<?php
if ($menu == 'T') {
    print "</div></div>";
    include_once '../includes/topmenu/footer.php';
} else {
    include_once '../includes/oldDesign/footer.php';
}
?>

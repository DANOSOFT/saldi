<?php
// -- -------------systemdata/valuta.php------------- ver 4.1.1 -- 2026-01-22 --
// LICENSE
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
//
// Copyright (c) 2003-2026 Saldi.DK ApS
// ----------------------------------------------------------------------------
// 20150313 CA  Topmenudesign tilføjet                             søg 20150313
// 20190221 MSC - Rettet topmenu design
// 20190225 MSC - Rettet topmenu design
// 20210706 LOE std_func.php missing file included and also translated some of the texts
// 20260122 PHR - Restyled page with modern design, added POS checkbox

@session_start();
$s_id=session_id();

$modulnr=2;
$title="Valutaer";
$bgcolor=NULL; $bgcolor1=NULL; $kurs=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

// Handle AJAX request to update POS setting
if (isset($_POST['ajax_update_pos'])) {
    $kodenr = intval($_POST['kodenr']);
    $pos_value = $_POST['pos_value'] == '1' ? '1' : '0';
    db_modify("UPDATE grupper SET box4 = '$pos_value' WHERE art = 'VK' AND kodenr = '$kodenr'", __FILE__ . " linje " . __LINE__);
    echo json_encode(['success' => true]);
    exit;
}

if ($menu=='T') {
    include_once '../includes/top_header.php';
    include_once '../includes/top_menu.php';
    print "<div id=\"header\">\n";
    print "<div class=\"headerbtnLft\"></div>\n";
    print "</div><!-- end of header -->";
    print "<div id=\"leftmenuholder\">";
    include_once 'left_menu.php';
    print "</div><!-- end of leftmenuholder -->\n";
    print "<div class=\"maincontentLargeHolder\">\n";
} else {
    include("top.php");
}

include("../includes/db_query.php");
?>

<style>
/* Modern DocPool-inspired styling */
.valuta-container {
    max-width: 720px;
    margin: 30px auto;
    padding: 0 20px;
}
.valuta-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}
.valuta-header {
    background: <?php echo $buttonColor ?? '#4a90d9'; ?>;
    color: <?php echo $buttonTxtColor ?? '#fff'; ?>;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.valuta-header h2 {
    margin: 0;
    font-size: 1.15em;
    font-weight: 600;
}
.valuta-header i {
    font-size: 1.2em;
    opacity: 0.9;
}
.valuta-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.valuta-table th {
    background: #f8f9fa;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.valuta-table th:last-child,
.valuta-table td:last-child {
    text-align: center;
    width: 90px;
}
.valuta-table th:nth-child(3),
.valuta-table td:nth-child(3) {
    text-align: right;
    width: 110px;
}
.valuta-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
    color: #333;
}
.valuta-table tbody tr {
    transition: background-color 0.15s ease;
}
.valuta-table tbody tr:hover {
    background-color: #f5f8fc;
}
.valuta-table tbody tr:last-child td {
    border-bottom: none;
}
.valuta-code {
    display: inline-block;
    padding: 5px 12px;
    background: <?php echo $buttonColor ?? '#4a90d9'; ?>;
    color: <?php echo $buttonTxtColor ?? '#fff'; ?>;
    border-radius: 4px;
    font-weight: 600;
    font-size: 12px;
    text-decoration: none;
    transition: all 0.15s ease;
}
.valuta-code:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    color: <?php echo $buttonTxtColor ?? '#fff'; ?>;
    text-decoration: none;
}
.valuta-kurs {
    font-weight: 600;
    color: #2d3748;
    font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
}
.valuta-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: #28a745;
    color: white;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.15s ease;
    box-shadow: 0 2px 4px rgba(40,167,69,0.2);
}
.valuta-add-btn:hover {
    background: #218838;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40,167,69,0.25);
    color: white;
    text-decoration: none;
}
.valuta-footer {
    text-align: center;
    background: #f8f9fa;
    padding: 16px;
    border-top: 1px solid #eee;
}
/* Modern POS Toggle Switch */
.pos-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
}
.pos-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.pos-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e0;
    transition: .2s;
    border-radius: 22px;
}
.pos-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .2s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.pos-switch input:checked + .pos-slider {
    background-color: #28a745;
}
.pos-switch input:checked + .pos-slider:before {
    transform: translateX(18px);
}
.pos-switch input:focus + .pos-slider {
    box-shadow: 0 0 0 3px rgba(40,167,69,0.2);
}
</style>

<script>
function togglePOS(checkbox, kodenr) {
    var posValue = checkbox.checked ? '1' : '0';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'valuta.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status !== 200) {
                alert('Fejl ved opdatering af POS indstilling');
                checkbox.checked = !checkbox.checked;
            }
        }
    };
    xhr.send('ajax_update_pos=1&kodenr=' + kodenr + '&pos_value=' + posValue);
}
</script>

<div class="valuta-container">
    <div class="valuta-card">
        <div class="valuta-header">
            <h2><i class="fa fa-money"></i> <?php echo findtekst(552,$sprog_id); ?></h2>
        </div>
        
        <table class="valuta-table">
            <thead>
                <tr>
                    <th><?php echo findtekst(30,$sprog_id); ?></th>
                    <th><?php echo findtekst(914,$sprog_id); ?></th>
                    <th><?php echo findtekst(915,$sprog_id); ?></th>
                    <th title="Vis i POS kasseapparat">POS</th>
                </tr>
            </thead>
            <tbody>
<?php
$x=0;
$dd=date("Y-m-d");
$q=db_select("select * from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $x++;
    $qtxt="select kurs from valuta where gruppe='$r[kodenr]' and valdate <= '$dd' order by valdate desc";
    if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
        $kurs=dkdecimal($r2['kurs']);
    } else {
        $kurs="-";
    }
    $pos_checked = ($r['box4'] == '1') ? 'checked' : '';
    
    print "<tr>";
    print "<td><a class='valuta-code' href='valutakort.php?kodenr=$r[kodenr]&valuta=$r[box1]'>$r[box1]</a></td>";
    print "<td>$r[beskrivelse]</td>";
    print "<td class='valuta-kurs'>$kurs</td>";
    print "<td>";
    print "<label class='pos-switch' title='Aktiver for brug i POS'>";
    print "<input type='checkbox' $pos_checked onchange='togglePOS(this, $r[kodenr])'>";
    print "<span class='pos-slider'></span>";
    print "</label>";
    print "</td>";
    print "</tr>\n";
}
?>
            </tbody>
        </table>
        
        <div class="valuta-footer">
            <a class="valuta-add-btn" href="valutakort.php?kodenr=-1">
                <i class="fa fa-plus"></i> <?php echo findtekst(1170,$sprog_id); ?>
            </a>
        </div>
    </div>
</div>

<?php
if ($menu=='T') {
    include_once '../includes/topmenu/footer.php';
} else {
    include_once '../includes/oldDesign/footer.php';
}
?>

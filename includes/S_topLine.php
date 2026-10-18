<?php
// ---- includes/S_topLine.php --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh The shared title bar of the report front pages (finans/rapport_includes/forside.php, lager/rapport.php)
//                  on the new page head (topbar addendum §5, prototype_dashboard_tema v5): the title in the content, the
//                  duplicate big title row below it dropped; the table structure the pages continue in is unchanged.

include_once(__DIR__ . "/stdFunc/pageBar.php");
page_bar(array('title' => $title));
print "<table cellpadding='1' cellspacing='3' border='0' width='100%' height='100%' valign='top'><tbody>";
print "<tr class='noHover'><td height=99%><br></td></td>";
print "<td valign='top' align='center'><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>\n";
?>
<style>
	a:link { text-decoration: none; }
</style>

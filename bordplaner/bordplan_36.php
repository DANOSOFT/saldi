<?php
// Det gamle mejeri
print "<table border=\"0\" width=\"100%\"><tbody>";
$b=0;
$w=80;
$h=33;
$th=14;
for ($r=0;$r<20;$r++) {
	print "<tr>";
		for ($k=0;$k<15;$k++) {
		vis_bord($b,1,1);
		$b++;
	}
	print "</tr>";
}
print "</tbody></table>";
?>

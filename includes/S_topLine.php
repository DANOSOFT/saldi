<?php





   #####################
	$leftemptyBtn  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
	
	#####################
	print "<table cellpadding='1' cellspacing='3' border='0' width='100%' height='100%' valign='top'><tbody>";
	
	print "<tr id='topTr'><td width=5% style='$topStyle'>";
	print "<span class='headerbtn' style='$buttonStyle'>"
	. $leftemptyBtn . "</span>";
	print "</td>";

	print "<td width='75%' align='center' style='$topStyle'>$title</td>";

	
	print "<td width='5%' align='center' style='$topStyle''><br></td>";

		print "</tr><tr class='noHover'><td height=99%><br></td></td>";
		print "<td valign='top' align='center'><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>\n";
		print "<tr><td align=center colspan=\"5\"><big><b>$title</b></big><br><br></td></tr>";
	?>
	<style>
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
	a:link{
		text-decoration: none;
	}
	</style>
	<script>
			document.addEventListener("DOMContentLoaded", function() {
			var trElement = document.getElementById("topTr");
			if (trElement) {
				
				trElement.classList.remove("hover-highlight");
			}
		});
	</script>
	<?php
     
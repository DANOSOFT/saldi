<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	
	$bg="nix";
	$header='nix';

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	
	ini_set("display_errors", "1");
	$id=if_isset($_GET['akkordlistevalg']);
#$akkordlistevalg=45;
#$id=45;	
#if ($id) {
#	if ($akkordlistevalg) {
if ($id = $_POST['id']) {
	$x=0;
		$tmp=array();
#echo "SELECT * FROM varer WHERE id > '0' AND kategori LIKE '%$id%' ORDER BY varenr ASC<br>";
#		$fp=fopen("test.txt","w");
		$query = db_select("SELECT * FROM varer WHERE id > '0' AND kategori LIKE '%$id%' ORDER BY varenr ASC",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$tmp=explode(chr(9),$row['kategori']);
			if (in_array($id,$tmp)) {
	#			fwrite($fp,"$row[beskrivelse]\n");
				$x++;
				$vare_id[$x]=$row['id'];
				$vare_nr[$x]=$row['varenr'];
				$vare_beskrivelse[$x]=$row['beskrivelse'];
				$kategori[$x]=$row['kategori'];
				$montagepris[$x]=$row['montage'];
				$demontagepris[$x]=$row['demontage']; 
			}
		}
		$antal_varer=$x;
#		fclose($fp);
		#echo $id;
		for ($x=1;$x<=$antal_varer;$x++) {
                      
		print "<tr id=\"$vare_id[$x]\">
				<td><input type=\"text\" style=\"width:36px; text-align:right;\" name=\"op[$x]\" value=\"$op[$x]\"/></td>
				<td><input type=\"text\" style=\"width:36px; text-align:right;\" name=\"ned[$x]\" value=\"$ned[$x]\"/></td>
				<td>$id -- $vare_beskrivelse[$x]</td>
				<td class=\"alignRight\" width=\"80\">".dkdecimal($montagepris[$x])."</td>
				<td class=\"alignRight\" width=\"80\">".dkdecimal($demontagepris[$x])."</td>
				<td class=\"alignRight\">".dkdecimal($op[$x]*$montagepris[$x]+$ned[$x]*$montagepris[$x])."</td>
				<input type=\"hidden\" name=\"vare_id[$x]\" value=\"$vare_id[$x]\"/>
		</tr>";
	}
}
?>

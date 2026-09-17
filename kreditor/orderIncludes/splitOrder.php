<?php
print "<!-- BEGIN orderIncludes/splitOrder.php -->";
print "splitOrder.php<br>";
print "<table cellpadding='1' cellspacing='0' class='datatable' bordercolor='#ffffff' border='1' valign = 'top'><tbody>";

$x=0;
$newOrderId = array();
$qtxt = "select * from ordrer where konto_id = '$konto_id' and status < '3' and art = 'KO' and id != '$id'";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$newOrderId[$x]=if_isset($r['id']);
	$newOrderNo[$x]=$r['ordrenr'];
	$newOrderDate[$x]=$r['ordredate'];
	$x++;
}
print "<tr><td colspan = '11' style='width:200px'>".findtekst(2010,$sprog_id);
print "&nbsp;<select name = 'MoveItemsTo' style='width:250px'>";
for ($x=0;$x<count($newOrderId);$x++) {
	print "<option value = '$newOrderId[$x]'>".dkdato($newOrderDate[$x])." Ordre nr: $newOrderNo[$x] </option>"; 
}
print "<option value = '0'>".findtekst(2011,$languageId)."</option>"; 
print "</select>&nbsp;";
print "<input type='submit' style = 'width:120px;' accesskey='g' ";
print "value='Flyt' name='moveOrderLines' onclick='javascript:docChange = false;'>";
print "&nbsp;<input type='submit' style = 'width:120px;' accesskey='g' ";
print "value='Fortryd' name='cancel' onclick='javascript:docChange = false;'>";
print "<br>&nbsp;</td></tr>";
$x=0;
$qtxt = "select * from ordrelinjer where ordre_id = '$id' order by posnr";
$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query))	{
	if ($row['posnr']>0) {
		$x++;
		$linje_id[$x]=$row['id'];
		$kred_linje_id[$x]=$row['kred_linje_id'];
		$posnr[$x]=$row['posnr'];
		$varenr[$x]=trim($row['varenr']);
		$lev_varenr[$x]=trim($row['lev_varenr']);
		$beskrivelse[$x]=trim($row['beskrivelse']);
		$pris[$x]=$row['pris'];
		$rabat[$x]=$row['rabat'];
		$antal[$x]=$row['antal'];
		$leveres[$x]=$row['leveres'];
		$leveret[$x]=$row['leveret'];
		$enhed[$x]=$row['enhed'];
		$vare_id[$x]=$row['vare_id'];
		$momsfri[$x]=$row['momsfri'];
		$projekt[$x]=$row['projekt'];
		$serienr[$x]=$row['serienr'];
		$samlevare[$x]=$row['samlevare'];
		($row['omvbet'])?$omvbet[$x]='checked':$omvbet[$x]='';
	}
}
$linjeantal=$x;
print "<input type='hidden' name='linjeantal' value='$linjeantal'>";
$sum=0;
#if (isset($_POST['mQt']))  $mQt  = $_POST['mQt'];
#if (isset($_POST['mSQt'])) $mSQt = $_POST['mSQt'];

for ($x=1; $x<=$linjeantal; $x++)	{
	showLine($x,$varenr[$x],$antal[$x],$beskrivelse[$x],$enhed[$x],$ialt[$x],$kred_linje_id[$x],$lev_varenr[$x],$leveres[$x],$leveret[$x],
						$linje_id[$x],$momsfri[$x],$omvbet[$x],$posnr[$x],$pris[$x],$projekt[$x],$rabat[$x],$samlevare[$x],$serienr[$x],
						$sum[$x],$vare_id[$x]);  
}

print "</tbody></table></td></tr>\n";
print "<input type='hidden' name='fokus'>";
/*
print "<tr><td align=center colspan=8>";
print "<table width='100%' border='0' cellspacing='0' cellpadding='1'><tbody><tr>";
print "<td align='center'><input type='submit' style = 'width:120px;' accesskey='g' ";
print "value='Gem' name='save' onclick='javascript:docChange = false;'></td>";
print "<td align='center'><input type=submit style = 'width:120px;' accesskey='o' ";
print "value='Opslag' name='lookup' onclick='javascript:docChange = false;'></td>";
*/

function showLine($x,$varenr,$antal,$beskrivelse,$enhed,$ialt,$kred_linje_id,$lev_varenr,$leveres,$leveret,
									$linje_id,$momsfri,$omvbet,$posnr,$pris,$projekt,$rabat,$samlevare,$serienr,$sum,$vare_id) {
	global $mQt,$mSQt;								
	if ($varenr) {
		$ialt=($pris-($pris/100*$rabat))*$antal;
		$ialt=afrund($ialt,2);
		$sum=$sum+$ialt;
		if ($momsfri!='on' && !$omvbet) $momssum=$momssum+$ialt;
		#$ialt=dkdecimal($ialt,2);
		$dkpris=dkdecimal($pris,2);
		$dkrabat=dkdecimal($rabat,2);
		if ($antal) {
			$dkantal=dkdecimal($antal,2);
			if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-1);
			if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-2);
		}
	}
	else {$dkantal=''; $dkpris=''; $dkrabat=''; $ialt='';}
	print "<input type='hidden' name='linje_id[$x]' value='$linje_id'>";
	print "<input type='hidden' name='vare_id[$x]' value='$vare_id'>";
	print "<input type='hidden' name='kred_linje_id[$x]' value='$kred_linje_id'>";
	print "<input type='hidden' name='serienr[$x]' value='$serienr'>";
	print "<input type='hidden' name='omvbet[$x]' value='$omvbet'>";
	print "<tr>";
	print "<td style = 'width:50px;text-align:right;'><input type='hidden' name=posn$x value='$x'>$x</td>";
	print "<td style = 'width:125px'><input type='hidden' name=vare$x value='".htmlentities($varenr)."'>$varenr</td>"; #20180305
	print "<td style = 'width:125px'><input type='hidden' name=lev_varenr$x value='".htmlentities($lev_varenr)."'>$lev_varenr</td>";
	print "<td style = 'width:50px;text-align:right;'><input type='hidden' name=anta$x value='$dkantal'>$dkantal</td>";
	print "<td style = 'width:50px;text-align:center;'>$enhed</td>";
	print "<td width = '400px'><input type='hidden' name=beskrivelse$x value= '".htmlentities($beskrivelse)."'>$beskrivelse</td>";
	print "<td style = 'width:75px;text-align:right;'><input type='hidden' name=pris$x value='$dkpris'>$dkpris</td>";
	print "<td style = 'width:5px;text-align:right;'><input type='hidden' name=raba$x value='$dkrabat'>$dkrabat</td>";
	if ($art=='KK') $ialt=$ialt*-1;
	if ($varenr) $tmp=dkdecimal($ialt,2);
	else $tmp=NULL;
	print "<td style = 'width:75px;text-align:right;'>$tmp</td>";
	$mQt[$x] = $maxSQt = 0;
	$maxQt=$antal-$leveret;
	if (!$mQt[$x]) $mQt[$x] = 0;
	$mQt[$x] = str_replace(',','.',$mQt[$x]*=1);
	if (!$maxQt) $maxQt = 0;
	$title = str_replace('$maxQ',$maxQt,findtekst(2012,$languageId));
	print "<td title = '$title' style='width:10px; text-align:center;'>";
 	print "<input type = 'hidden' name = 'maxQt[$x]' value = '$maxQt'>"; 
 	($maxQt > 0)?$disabled = NULL:$disabled="disabled = 'disabled'";
 	print "<input style = 'width:30px;text-align:right' type = 'text' name = 'mQt[$x]' value = '$mQt[$x]' $disabled></td>"; 
	$maxSQt=$leveret*1;
	if (!$mSQt[$x]) $mSQt[$x] = 0;
	$mSQt[$x] = str_replace(',','.',$mSQt[$x]*=1);
	if (!$maxSQt) $maxSQt = 0;
	$title = str_replace('$maxQ',$maxSQt,findtekst(2013,$languageId));
 ($maxSQt >0 && $antal)?$disabled = NULL:$disabled="disabled = 'disabled'";
 	print "<td style='width:10px; text-align:center;' title = '$title' >";
 	print "<input type = 'hidden' name = 'maxSQt[$x]' value = '$maxSQt'>"; 
 	print "<input style = 'width:30px;text-align:right' type = 'text' name = 'mSQt[$x]' value = '$mSQt[$x]' $disabled></td>"; 
	print "</tr>";
}

?>

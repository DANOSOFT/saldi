<?php

$menu_id=$_GET['menu_id'];
$ret_row=$_GET['ret_row'];
$ret_col=$_GET['ret_col'];

$a=0;
$b=0;
$c=0;
$color='ffffff';
print "<table><tbody><tr>";
while ($a<'257') {
	$a=dechex($a);
	$b=dechex($b);
	$c=dechex($c);
	if (strlen($a)<2)$a='0'.$a;
	if (strlen($b)<2)$b='0'.$b;
	if (strlen($c)<2)$c='0'.$c;
	if ($b=='00' && $c=='00') $color='ffffff';
	elseif ($b=='99' && $c=='00') $color='000000';
	print "<td style=\"width:100px;height 50px;text-align:center;color:#$color;background-color:#$a$b$c\"><a style=\"text-decoration:none;color:$color;\" href=\"../systemdata/posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=$a$b$c\">$a$b$c</a></td>";
	$a=hexdec($a);
	$b=hexdec($b);
	$c=hexdec($c);
	$c+=51;
	if ($c>256) {
		$c=0;
		$b+=51;
		print ("</tr><tr>");
	}
	if ($b>256) {
		$b=0;
		$a+=51;
	}
}
/*
print "<tr>
		<td style=\"text-align:center;color:#ffffff;background-color:#000000\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=000000>000000</a></td>
		<td style=\"text-align:center;color:#ffffff;background-color:#000033\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=000033>000033</a></td>
		<td style=\"text-align:center;color:#ffffff;background-color:#000066\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=000066>000066</a></td>
		<td style=\"text-align:center;color:#ffffff;background-color:#000099\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=000099>000099></a></td>
		<td style=\"text-align:center;color:#ffffff;background-color:#0000cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=0000CC>0000CC</a></td>
		<td style=\"text-align:center;color:#ffffff;background-color:#0000ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=0000FF>0000FF</a></td>
	</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#003300\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=003300>003300</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#003333\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=003333>003333</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#003366\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=003366>003366</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#003399\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=003399>003399</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#0033cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=0033CC>0033CC</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#0033ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=0033FF>0033FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#006600\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=006600>006600</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#006633\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=006633>006633</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#006666\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=006666>006666</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#006699\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=006699>006699</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#0066cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=0066CC>0066CC</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#0066ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=0066FF>0066FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#009900\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=009900>009900</a></td>
  <td style=\"text-align:center;background-color:#009933\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=009933>009933</a></td>
  <td style=\"text-align:center;background-color:#009966\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=009966>009966</a></td>
  <td style=\"text-align:center;background-color:#009999\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=009999>009999</a></td>
  <td style=\"text-align:center;background-color:#0099cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=0099CC>0099CC</a></td>
  <td style=\"text-align:center;background-color:#0099ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=0099FF>0099FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#00cc00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00CC00>00CC00</a></td>
  <td style=\"text-align:center;background-color:#00cc33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00CC33>00CC33</a></td>
  <td style=\"text-align:center;background-color:#00cc66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00CC66>00CC66</a></td>
  <td style=\"text-align:center;background-color:#00cc99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00CC99>00CC99</a></td>
  <td style=\"text-align:center;background-color:#00cccc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00CCCC>00CCCC</a></td>
  <td style=\"text-align:center;background-color:#00ccff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00CCFF>00CCFF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#00ff00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00FF00>00FF00</a></td>
  <td style=\"text-align:center;background-color:#00ff33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00FF33>00FF33</a></td>
  <td style=\"text-align:center;background-color:#00ff66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00FF66>00FF66></a></td>
  <td style=\"text-align:center;background-color:#00ff99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00FF99>00FF99</a></td>
  <td style=\"text-align:center;background-color:#00ffcc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00FFCC>00FFCC</a></td>
  <td style=\"text-align:center;background-color:#00ffff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=00FFFF>00FFFF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#330000\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=330000>330000</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#330033\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=330033>330033</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#330066\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=330066>330066</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#330099\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=330099>330099</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#3300cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=3300CC>3300CC</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#3300ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=3300FF>3300FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#333300\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=333300>333300</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#333333\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=333333>333333</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#333366\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=333366>333366</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#333399\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=333399>333399</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#3333cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=3333CC>3333CC</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#3333ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=3333FF>3333FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#336600\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=336600>336600</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#336633\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=336633>336633</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#336666\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=336666>336666</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#336699\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=336699>336699</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#3366cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=3366CC>3366CC</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#3366ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=3366FF>3366FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#339900\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=339900>339900</a></td>
  <td style=\"text-align:center;background-color:#339933\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=339933>339933</a></td>
  <td style=\"text-align:center;background-color:#339966\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=339966>339966</a></td>
  <td style=\"text-align:center;background-color:#339999\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=339999>339999</a></td>
  <td style=\"text-align:center;background-color:#3399cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=3399CC>3399CC</a></td>
  <td style=\"text-align:center;background-color:#3399ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=3399FF>3399FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#33cc00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33CC00>33CC00</a></td>
  <td style=\"text-align:center;background-color:#33cc33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33CC33>33CC33</a></td>
  <td style=\"text-align:center;background-color:#33cc66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33CC66>33CC66</a></td>
  <td style=\"text-align:center;background-color:#33cc99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33CC99>33CC99</a></td>
  <td style=\"text-align:center;background-color:#33cccc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33CCCC>33CCCC</a></td>
  <td style=\"text-align:center;background-color:#33ccff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33CCFF>33CCFF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#33ff00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33FF00>33FF00</a></td>
  <td style=\"text-align:center;background-color:#33ff33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33FF33>33FF33</a></td>
  <td style=\"text-align:center;background-color:#33ff66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33FF66>33FF66</a></td>
  <td style=\"text-align:center;background-color:#33ff99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33FF99>33FF99</a></td>
  <td style=\"text-align:center;background-color:#33ffcc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33FFCC>33FFCC</a></td>
  <td style=\"text-align:center;background-color:#33ffff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=33FFFF>33FFFF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#660000\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=660000>=660000</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#660033\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=660033>660033</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#660066\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=660066>660066</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#660099\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=660099>660099</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#6600cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=6600CC>6600CC</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#6600ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=6600FF>6600FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#663300\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=663300>663300</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#663333\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=663333>663333</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#663366\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=663366>663366</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#663399\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=663399>663399</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#6633cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=6633CC>6633CC</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#6633ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=6633FF>6633FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#666600\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=666600>666600</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#666633\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=666633>666633</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#666666\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=666666>666666</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#666699\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=666699>666699</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#6666cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=6666CC>6666CC</a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#6666ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=6666FF>6666FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#669900\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=669900>669900</a></td>
  <td style=\"text-align:center;background-color:#669933\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=669933>669933</a></td>
  <td style=\"text-align:center;background-color:#669966\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=669966>669966</a></td>
  <td style=\"text-align:center;background-color:#669999\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=669999>669999</a></td>
  <td style=\"text-align:center;background-color:#6699cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=6699CC>6699CC</a></td>
  <td style=\"text-align:center;background-color:#6699ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=6699FF>6699FF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#66cc00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66CC00>66CC00</a></td>
  <td style=\"text-align:center;background-color:#66cc33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66CC33>66CC33</a></td>
  <td style=\"text-align:center;background-color:#66cc66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66CC66>66CC66</a></td>
  <td style=\"text-align:center;background-color:#66cc99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66CC99>66CC99</a></td>
  <td style=\"text-align:center;background-color:#66cccc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66CCCC>66CCCC</a></td>
  <td style=\"text-align:center;background-color:#66ccff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66CCFF>66CCFF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#66ff00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66FF00>66FF00</a></td>
  <td style=\"text-align:center;background-color:#66ff33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66FF33>66FF33</a></td>
  <td style=\"text-align:center;background-color:#66ff66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66FF66>66FF66</a></td>
  <td style=\"text-align:center;background-color:#66ff99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66FF99>66FF99</a></td>
  <td style=\"text-align:center;background-color:#66ffcc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66FFCC>66FFCC</a></td>
  <td style=\"text-align:center;background-color:#66ffff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=66FFFF>66FFFF</a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#990000\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=990000></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#990033\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=990033></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#990066\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=990066></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#990099\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=990099></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#9900cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=9900CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#9900ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=9900FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#993300\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=993300></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#993333\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=993333></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#993366\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=993366></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#993399\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=993399></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#9933cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=9933CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#9933ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=9933FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#996600\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=996600></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#996633\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=996633></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#996666\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=996666></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#996699\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=996699></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#9966cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=9966CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#9966ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=9966FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#999900\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=999900></a></td>
  <td style=\"text-align:center;background-color:#999933\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=999933></a></td>
  <td style=\"text-align:center;background-color:#999966\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=999966></a></td>
  <td style=\"text-align:center;background-color:#999999\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=999999></a></td>
  <td style=\"text-align:center;background-color:#9999cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=9999CC></a></td>
  <td style=\"text-align:center;background-color:#9999ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=9999FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#99cc00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99CC00></a></td>
  <td style=\"text-align:center;background-color:#99cc33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99CC33></a></td>
  <td style=\"text-align:center;background-color:#99cc66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99CC66></a></td>
  <td style=\"text-align:center;background-color:#99cc99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99CC99></a></td>
  <td style=\"text-align:center;background-color:#99cccc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99CCCC></a></td>
  <td style=\"text-align:center;background-color:#99ccff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99CCFF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#99ff00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99FF00></a></td>
  <td style=\"text-align:center;background-color:#99ff33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99FF33></a></td>
  <td style=\"text-align:center;background-color:#99ff66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99FF66></a></td>
  <td style=\"text-align:center;background-color:#99ff99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99FF99></a></td>
  <td style=\"text-align:center;background-color:#99ffcc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99FFCC></a></td>
  <td style=\"text-align:center;background-color:#99ffff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=99FFFF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc0000\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC0000></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc0033\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC0033></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc0066\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC0066></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc0099\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC0099></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc00cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC00CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc00ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC00FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc3300\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC3300></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc3333\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC3333></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc3366\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC3366></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc3399\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC3399></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc33cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC33CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc33ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC33FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc6600\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC6600></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc6633\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC6633></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc6666\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC6666></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc6699\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC6699></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc66cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC66CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#cc66ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC66FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#cc9900\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC9900></a></td>
  <td style=\"text-align:center;background-color:#cc9933\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC9933></a></td>
  <td style=\"text-align:center;background-color:#cc9966\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC9966></a></td>
  <td style=\"text-align:center;background-color:#cc9999\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC9999></a></td>
  <td style=\"text-align:center;background-color:#cc99cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC99CC></a></td>
  <td style=\"text-align:center;background-color:#cc99ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CC99FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#cccc00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCCC00></a></td>
  <td style=\"text-align:center;background-color:#cccc33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCCC33></a></td>
  <td style=\"text-align:center;background-color:#cccc66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCCC66></a></td>
  <td style=\"text-align:center;background-color:#cccc99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCCC99></a></td>
  <td style=\"text-align:center;background-color:#cccccc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCCCCC></a></td>
  <td style=\"text-align:center;background-color:#ccccff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCCCFF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#ccff00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCFF00></a></td>
  <td style=\"text-align:center;background-color:#ccff33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCFF33></a></td>
  <td style=\"text-align:center;background-color:#ccff66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCFF66></a></td>
  <td style=\"text-align:center;background-color:#ccff99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCFF99></a></td>
  <td style=\"text-align:center;background-color:#ccffcc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCFFCC></a></td>
  <td style=\"text-align:center;background-color:#ccffff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=CCFFFF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff0000\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF0000></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff0033\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF0033></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff0066\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF0066></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff0099\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF0099></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff00cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF00CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff00ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF00FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff3300\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF3300></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff3333\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF3333></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff3366\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF3366></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff3399\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF3399></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff33cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF33CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff33ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF33FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff6600\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF6600></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff6633\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF6633></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff6666\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF6666></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff6699\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF6699></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff66cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF66CC></a></td>
  <td style=\"text-align:center;color:#ffffff;background-color:#ff66ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF66FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#ff9900\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF9900></a></td>
  <td style=\"text-align:center;background-color:#ff9933\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF9933></a></td>
  <td style=\"text-align:center;background-color:#ff9966\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF9966></a></td>
  <td style=\"text-align:center;background-color:#ff9999\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF9999></a></td>
  <td style=\"text-align:center;background-color:#ff99cc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF99CC></a></td>
  <td style=\"text-align:center;background-color:#ff99ff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FF99FF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#ffcc00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFCC00></a></td>
  <td style=\"text-align:center;background-color:#ffcc33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFCC33></a></td>
  <td style=\"text-align:center;background-color:#ffcc66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFCC66></a></td>
  <td style=\"text-align:center;background-color:#ffcc99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFCC99></a></td>
  <td style=\"text-align:center;background-color:#ffcccc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFCCCC></a></td>
  <td style=\"text-align:center;background-color:#ffccff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFCCFF></a></td>
</tr>
<tr>
  <td style=\"text-align:center;background-color:#ffff00\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFFF00></a></td>
  <td style=\"text-align:center;background-color:#ffff33\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFFF33></a></td>
  <td style=\"text-align:center;background-color:#ffff66\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFFF66></a></td>
  <td style=\"text-align:center;background-color:#ffff99\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFFF99></a></td>
  <td style=\"text-align:center;background-color:#ffffcc\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFFFCC></a></td>
  <td style=\"text-align:center;background-color:#ffffff\"><a href=posmenuer.php?menu_id=$menu_id&ret_row=$ret_row&ret_col=$ret_col&farvekode=FFFFFF></a></td>
</tr>";
*/
print "</tbody></table>";
?>
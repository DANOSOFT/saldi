<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- mysale/editProfile.php --- lap 4.0.8 --- 2023-04-05	 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2021-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20210829 PHR	varoius minor changes
// 20210908 PHR Password can now be empty.
// 20220212 PHR Cleanup
// 20230406 PHR	Minor changes in memberShip (only used by pos_38)
// 04/09/2024 PBLM added sidemenu

if ($accountId && isset($_POST['addr1'])) {	
	$qtxt = "update adresser set ";
#	$qtxt.= "password = '".db_escape_string(trim($_POST['pw1']))   ."' ";
	$qtxt.= "addr1    = '".db_escape_string(trim($_POST['addr1']))   ."', ";
	$qtxt.= "addr2    = '".db_escape_string(trim($_POST['addr2']))   ."', ";
	$qtxt.= "postnr   = '".db_escape_string(trim($_POST['zip']))  ."', ";
	$qtxt.= "bynavn   = '".db_escape_string(trim($_POST['city']))  ."', ";
	$qtxt.= "tlf    = '".db_escape_string(trim($_POST['phone']))   ."', ";
	$qtxt.= "email    = '".db_escape_string(trim($_POST['email']))   ."', ";
	$qtxt.= "bank_reg = '".db_escape_string(trim($_POST['bankReg']))."', ";
	$qtxt.= "bank_konto = '".db_escape_string(trim($_POST['bankKto']))."' ";
	$qtxt.= "where id = '$accountId'";
	db_modify($qtxt, __FILE__ . " line " . __LINE__);
}
if (isset($_POST['pw1'])) {	
	$pw1     = $_POST['pw1'];
	$pw2     = $_POST['pw2'];	
	if ($pw1 != '******' && $pw1 == $pw2) {
		($pw1)?$encPw = saldikrypt($accountId,$pw1):$encPw=NULL;

		$qtxt = "update adresser set password = '$encPw' where id = '$accountId'";
		db_modify($qtxt, __FILE__ . " line " . __LINE__);
		if ($pw1) $txt="Adgangskode opdateret";
		else $txt = "";
		print "<center><big><span style='color:red;'>$txt</span></big></center>";
	} elseif  ($pw1 == '******') {
		print "<center><big><span style='color:red;'>Adgangskode ikke ændret</span></big></center>";
	} else {
		print "<center><big><span style='color:red;'>Adgangskoder er ikke ens</span></big></center>";
	}
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='adresser' and column_name='password'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table adresser ADD column password varchar(50)",__FILE__ . " linje " . __LINE__);
}
$memberShip = NULL;
$qtxt = "select var_value from settings where  var_name='medlemSetting' or var_name='memberShip'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20220729
	$memberShip = $r['var_value'];
#	if ($db != 'pos_38') $memberShip= NULL;
}
$qtxt="select * from adresser where id = '$accountId'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$accountNo = $r['kontonr'];
	$name      = $r['firmanavn'];
	$addr1     = $r['addr1'];
	$addr2     = $r['addr2'];
	$zip       = $r['postnr'];
	$city      = $r['bynavn'];
	$email     = $r['email'];
	$bankReg   = $r['bank_reg'];
	$bankKto   = $r['bank_konto'];
	($memberShip)?$member = $r['medlem']:$member = NULL;
	($memberShip)?$mCats = $r['kategori']:$mCats = NULL;
	$phone     = $r['tlf'];
	$password  = $r['password'];
	($password)?$showPw='******':$showPw=''; 
}
$memberOutput = 'Inaktiv';
if ($mCats || $mCats == '0') {
	$mCat = explode(chr(9),$mCats);
	$r=db_fetch_array(db_select("select box1,box2 from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__));
	$catId=explode(chr(9),$r['box1']);
	$catName=explode(chr(9),$r['box2']);
	if (in_array('Aktiv',$catName)) { #
		for ($x=0;$x<count($catId);$x++) {
			if (strtolower($catName[$x]) == 'aktiv' && in_array($catId[$x],$mCat)) $memberOutput = 'Aktiv';
		}
	}
}
/*
if (strpos($mCats, '1') !== false){
    $memberOutput = 'Aktiv';
}
else{
    $memberOutput = 'Inaktiv';
}
*/
$urlPrefix = "https://ssl8.saldi.dk/laja/mysale/mysale.php?id=";
if (strpos($id, $urlPrefix) === 0) {
    // Remove the URL prefix
    $newId = str_replace($urlPrefix, '', $id);
}else{
	$newId = $id;
}
?>
<button data-drawer-target="default-sidebar" data-drawer-toggle="default-sidebar" aria-controls="default-sidebar" type="button" class="inline-flex items-center p-2 mt-2 ms-3 text-sm text-gray-500 rounded-lg lg:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
   <span class="sr-only">Åben sidebar</span>
   <svg class="w-20 h-20" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
   <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
   </svg>
</button>

<aside id="default-sidebar" class="fixed top-0 left-0 z-40 w-1/2 lg:w-64 h-screen transition-transform -translate-x-full lg:translate-x-0" aria-label="Sidebar">
   <div class="h-full px-3 py-4 overflow-y-auto bg-gray-50 dark:bg-gray-800">
      <ul class="space-y-2 font-medium">
         <li>
            <a href="mysale.php?id=<?php echo $newId ?>" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path d="M13.5 2c-.178 0-.356.013-.492.022l-.074.005a1 1 0 0 0-.934.998V11a1 1 0 0 0 1 1h7.975a1 1 0 0 0 .998-.934l.005-.074A7.04 7.04 0 0 0 22 10.5 8.5 8.5 0 0 0 13.5 2Z"/>
					<path d="M11 6.025a1 1 0 0 0-1.065-.998 8.5 8.5 0 1 0 9.038 9.039A1 1 0 0 0 17.975 13H11V6.025Z"/>
				</svg>
               <span class="ms-3 font-sans text-5xl lg:text-base">Oversigt</span>
            </a>
         </li>
         <li>
            <a href="mysale.php?id=<?php echo $newId ?>&condition=<?php echo $condition ?>&editProfile=1" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path fill-rule="evenodd" d="M17 10v1.126c.367.095.714.24 1.032.428l.796-.797 1.415 1.415-.797.796c.188.318.333.665.428 1.032H21v2h-1.126c-.095.367-.24.714-.428 1.032l.797.796-1.415 1.415-.796-.797a3.979 3.979 0 0 1-1.032.428V20h-2v-1.126a3.977 3.977 0 0 1-1.032-.428l-.796.797-1.415-1.415.797-.796A3.975 3.975 0 0 1 12.126 16H11v-2h1.126c.095-.367.24-.714.428-1.032l-.797-.796 1.415-1.415.796.797A3.977 3.977 0 0 1 15 11.126V10h2Zm.406 3.578.016.016c.354.358.574.85.578 1.392v.028a2 2 0 0 1-3.409 1.406l-.01-.012a2 2 0 0 1 2.826-2.83ZM5 8a4 4 0 1 1 7.938.703 7.029 7.029 0 0 0-3.235 3.235A4 4 0 0 1 5 8Zm4.29 5H7a4 4 0 0 0-4 4v1a2 2 0 0 0 2 2h6.101A6.979 6.979 0 0 1 9 15c0-.695.101-1.366.29-2Z" clip-rule="evenodd"/>
				</svg>
               <span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base">Profil</span>
            </a>
         </li>
         <li>
            <a href="mylabel.php?id=<?php echo $newId?>&condition=<?php echo $condition?>" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path d="M4 6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h11.613a2 2 0 0 0 1.346-.52l4.4-4a2 2 0 0 0 0-2.96l-4.4-4A2 2 0 0 0 15.613 6H4Z"/>
				</svg>
               <span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base"><?php echo findtekst(3063,$sprog_id) ?></span>
            </a>
         </li>
		 <li>
            <a href="remoteBooking.php?id=<?php echo $newId?>&condition=<?php echo $condition?>" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path fill-rule="evenodd" d="M6 5V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v2H3V7a2 2 0 0 1 2-2h1ZM3 19v-8h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm5-6a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2H8Z" clip-rule="evenodd"/>
				</svg>
               <span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base">Book stand</span>
            </a>
         </li>
		 <li>
			<a href="mybooking.php?id=<?php echo $newId?>" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path fill-rule="evenodd" d="M6 5V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v2H3V7a2 2 0 0 1 2-2h1ZM3 19v-8h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm5-6a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2H8Z" clip-rule="evenodd"/>
				</svg>
				<span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base">Mine bookinger</span>
			</a>
		</li>
         <li>
            <a href="mysale.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
					<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12H4m12 0-4 4m4-4-4-4m3-4h2a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-2"/>
				</svg>
               <span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base">Logud</span>
            </a>
         </li>
      </ul>
   </div>
</aside>
<?php
($mobile)?$width='400':$width='200';
print "<center><table border = '0' style = 'width:". $width * 2 ."px'>";
print "<br>Her har du mulighed for at se dine stamoplysninger<br>og lægge adgangskode på din profil<br><br>";
print "<form action='mysale.php?id=$id&sort=$sort' method='post'>";
/* print "<input style='width:". $width * 2 ."px' value='Tilbage' type = 'submit'>"; */
print "</form>";
print "<form action='mysale.php?id=$id&sort=$sort&editProfile=1' method='post'>";
if ($memberShip) { # most be corrected by DAPE
	if ($memberOutput == 'Aktiv') {
		print "<tr><td  style = 'width:". $width ."px'>Status</td><td bgcolor='#90EE90'>$memberOutput</td></tr>";
	} else {
		print "<tr><td  style = 'width:". $width ."px'>Status</td><td bgcolor='#C85B5B'>$memberOutput</td></tr>";
	}
}
print "<tr bgcolor='$lineColor'><td  style = 'width:". $width ."px'>Kontonr</td><td>$accountNo</td></tr>";
print "<tr><td>Navn</td><td>$name</td></tr>";
print "<tr bgcolor='$lineColor'><td>Addresse</td>";
print "<td><input style=\"width:200px\" type=\"text\" name=\"addr1\" value=\"$addr1\"</td></tr>\n";
print "<tr><td></td><td><input style=\"width:200px\" type=\"text\" name=\"addr2\" value=\"$addr2\"</td></tr>\n";
print "<tr bgcolor='$lineColor'><td>Postnr / By</td>";
print "<td align=\"right\"><input style=\"width:50px\" type=\"text\" name=\"zip\" value=\"$zip\">";
print "<input style=\"width:150px\" type=\"text\" name=\"city\" value=\"$city\"</td></tr>\n";
print "<tr><td>tlf</td>";
print "<td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"phone\" value=\"$phone\"</td></tr>\n";
print "<tr bgcolor='$lineColor'><td>e-mail</td>";
print "<td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"email\" value=\"$email\"</td></tr>\n";
print "<tr><td>Bank Reg. nr: (Til udbetaling)</td>";
print "<td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bankReg\" value=\"$bankReg\"</td></tr>\n";
print "<tr bgcolor='$lineColor'><td>Bank Konto nr: (Til udbetaling)</td>";
print "<td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bankKto\" value=\"$bankKto\"</td></tr>\n";
print "<tr><td>Password</td>";	
print "<td><input style='width:". $width ."px' name = 'pw1' type = 'text' value = '$showPw'</td></tr>";
print "<tr bgcolor='$lineColor'><td>Gentag password</td><td>";
print "<input style='width:". $width ."px' name = 'pw2' type = 'text' value = '$showPw'</td></tr>";
print "<tr><td colspan = '2' align='center'>";
print "<input style='width:". $width *2 ."px' name = 'save' value='Gem' type = 'submit'></td></tr>";
print "</form>";
print "</table></center>";
?>

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
<body class="dark:bg-gray-700">

<?php
include_once("sidemenu.php");
/* print "<center><table border = '0' style = 'width:". $width * 2 ."px'>";
print "<br>Her har du mulighed for at se dine stamoplysninger<br>og lægge adgangskode på din profil<br><br>";
print "<form action='mysale.php?id=$id&sort=$sort' method='post'>";
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
print "</table></center>"; */
?>
<div class="p-4 lg:ml-64 h-screen">
	<!-- <h1 class="text-2xl font-semibold dark:text-white">Profil</h1>
	<p class="text-sm text-gray-500 dark:text-gray-400">Her har du mulighed for at se dine stamoplysninger og lægge adgangskode på din profil</p> -->
	<form class="mx-auto mt-4 w-3/4 lg:w-2/4" action='mysale.php?id=<?php echo $id ?>&sort=<?php echo $sort ?>&editProfile=1' method='post'>
	<?php if ($memberShip) { # most be corrected by DAPE
		if ($memberOutput == 'Aktiv') { ?>
			<p>Status <?php echo $memberOutput ?></p>
		<?php } else { ?>
			<p>Status <?php echo $memberOutput ?></p>
		<?php }
	} ?>
<!-- 	<div class="relative z-0 w-full mb-5 group">
		<p class="dark:text-white">Kontonr: <?php echo $accountNo ?></p>
	</div>
	<div class="relative z-0 w-full mb-5 group">
		<p class="dark:text-white">Navn: <?php echo $name ?></p>
	</div> -->
	<div class="relative z-0 w-full mb-6 group">
        <input type="text" name="addr1" id="addr1" value="<?php echo $addr1 ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
        <label for="addr1" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Addresse</label>
    </div>
	<div class="relative z-0 w-full mb-6 group">
        <input type="text" name="addr2" id="addr2" value="<?php echo $addr2 ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " />
        <label for="addr2" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Addresse 2.</label>
    </div>
    <div class="relative z-0 w-full mb-6 group">
        <input type="text" name="email" id="email" value="<?php echo $email ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
        <label for="email" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Email</label>
    </div>
	<div class="relative z-0 w-full mb-6 group">
		<input type="tel" pattern="[0-9]{8}" name="phone" id="phone" value="<?php echo $phone ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
		<label for="phone" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Telefon</label>
	</div>
	<div class="grid md:grid-cols-2 md:gap-6">
		<div class="relative z-0 w-full mb-6 group">
			<input type="text" name="zip" id="zip" value="<?php echo $zip ?>" class="block pt-8 px-6 w-full text-5xl  lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
			<label for="zip" class="peer-focus:font-medium absolute text-5xl  lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Postnr</label>
		</div>
		<div class="relative z-0 w-full mb-6 group">
			<input type="text" name="city" id="city" value="<?php echo $city ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
			<label for="city" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">By</label>
		</div>
	</div>
	<div class="grid md:grid-cols-2 md:gap-6">
		<div class="relative z-0 w-full mb-6 group">
			<input type="text" name="bankReg" id="bankReg" value="<?php echo $bankReg ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
			<label for="bankReg" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Bank Reg. nr<span class="invisible lg:visible">: (Til udbetaling)</span></label>
		</div>
		<div class="relative z-0 w-full mb-6 group">
			<input type="text" name="bankKto" id="bankKto" value="<?php echo $bankKto ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
			<label for="bankKto" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Bank Konto nr<span class="invisible lg:visible">: (Til udbetaling)</span></label>
		</div>
	</div>
	<div class="relative z-0 w-full mb-6 group">
		<input type="password" name="pw1" id="pw1" value="<?php echo $showPw ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " />
		<label for="pw1" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Password</label>
	</div>
	<div class="relative z-0 w-full mb-6 group">
		<input type="password" name="pw2" id="pw2" value="<?php echo $showPw ?>" class="block pt-8 px-6 w-full text-5xl lg:text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " />
		<label for="pw2" class="peer-focus:font-medium absolute text-5xl lg:text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-6 scale-75 top-3 -z-10 origin-[0] peer-focus:start-0 rtl:peer-focus:translate-x-1/4 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-6">Gentag password</label>
	</div>
	<button type="submit" name="save" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-3xl lg:text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Submit</button>
	</form>
</div>

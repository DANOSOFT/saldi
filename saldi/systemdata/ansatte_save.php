<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// -------systemdata/ansatte_save.php--------lap 3.0.0-------2015-02-13--06:51-----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2016 saldi.dk aps
// ----------------------------------------------------------------------
// 20140923 PK - Validering af $nummer. Tjekker om $nummer er et tal, og om talet findes i forvejen 
// 20140924 PK - Validering af $navn, så der skrives en meddelelse hvis der ikke er udfyldt navn
// 20150213	PHR - header("location:ansatte.php?id=$id&funktion=ret_ansat"); smadrer alting, så jeg har sat "if ($menu=='T')" foran.


if ($konto_id=$_POST['konto_id']) {
	$id=$_POST['id']*1;
	$navn=db_escape_string(trim($_POST['navn']));
	$nummer=db_escape_string(trim($_POST['nummer']));
	$initialer=db_escape_string(trim($_POST['initialer']));
	$addr1=db_escape_string(trim($_POST['addr1']));
	$addr2=db_escape_string(trim($_POST['addr2']));
	$postnr=db_escape_string(trim($_POST['postnr']));
	$bynavn=db_escape_string(trim($_POST['bynavn']));
	$tlf=db_escape_string(trim($_POST['tlf']));
	$fax=db_escape_string(trim($_POST['fax']));
	$mobil=db_escape_string(trim($_POST['mobil']));
	$privattlf=db_escape_string(trim($_POST['privattlf']));
	$email=db_escape_string(trim($_POST['email']));
	$cprnr=db_escape_string(trim($_POST['cprnr']));
	$notes=db_escape_string(trim($_POST['notes']));
	$bank=db_escape_string(trim($_POST['bank']));
	$loen=usdecimal($_POST['loen']);
	$extraloen=usdecimal($_POST['extraloen']);
	$lukket=trim($_POST['lukket']);
	$startdato=db_escape_string(trim($_POST['startdato']));
	$slutdato=db_escape_string(trim($_POST['slutdato']));
	($startdato)?$startdate=usdate($startdato):$startdate=NULL;
	($slutdato)?$slutdate=usdate($slutdato):$sluttdate=NULL;
	$trainee=trim($_POST['trainee']);
	list($afd,$x)=explode(":",$_POST['afd']);
	$afd=$afd*1;
	$returside=$_POST['returside'];
	$fokus=$_POST['fokus'];
	$provision=$_POST['provision'];
	$provision_id=$_POST['provision_id'];
	$gruppe_id=$_POST['gruppe_id'];
	$pro_antal=$_POST['pro_antal'];

	echo "$nummer $initialer $afd $id<br>";
	
		if (!is_numeric($nummer) && $id) { #20140923
			$messages = "Skal være et tal";
	} elseif ($id && $r=db_fetch_array(db_select("SELECT id,navn FROM ansatte WHERE nummer='$nummer' AND id != '$id'",__FILE__ . " linje " . __LINE__))){
			$message = "Medarbejdernummer eksisterer i forvejen (". $r['navn'] .")";
			alert($message); 
		} else {
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
		if (!$navn && !$id) $messages1 = "Medarbejder skal have et navn"; #20140924
		if (($id==0)&&($navn)) {
			if (!$nummer) {
				$r=db_fetch_array(db_select("SELECT max (nummer) as nummer FROM ansatte WHERE konto_id='$konto_id'",__FILE__ . " linje " . __LINE__));
				$nummer=$r['nummer']+1;
			}	
			if (!$startdate)$startdate=date("Y-m-d");
			if (!$slutdate) $slutdate="9999-12-31";
			db_modify("insert into ansatte (navn,nummer,initialer,konto_id,addr1,addr2,postnr,bynavn,tlf,fax,privattlf,mobil,email,cprnr,notes,afd,lukket,bank,startdate,slutdate,loen,extraloen,trainee) values 
				('$navn','$nummer','$initialer','$konto_id','$addr1','$addr2','$postnr','$bynavn','$tlf','$fax','$privattlf','$mobil','$email','$cprnr','$notes','$afd','$lukket','$bank','$startdate','$slutdate','$loen','$extraloen','$trainee')",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$konto_id' and navn='$navn'",__FILE__ . " linje " . __LINE__));
			$id = $r['id']; 
			if ($menu=='T') header("location:ansatte.php?id=$id&funktion=ret_ansat");
		} elseif ($id > 0) {
			if (!$startdate) $startdate="1900-01-01";
			if (!$slutdate) $slutdate="9999-12-31";
			if ($slutdate<=date("Y-m-d")) $lukket='on';
	#echo "update ansatte set navn='$navn',nummer='$nummer',initialer='$initialer',konto_id='$konto_id',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',email='$email',tlf='$tlf',fax='$fax',privattlf='$privattlf',mobil='$mobil',cprnr='$cprnr',notes='$notes',afd='$afd',lukket='$lukket',bank='$bank',startdate='$startdate',slutdate='$slutdate',loen='$loen',extraloen='$extraloen',trainee='$trainee' where id='$id'<br>";		
			$qtxt="update ansatte set navn='$navn',nummer='$nummer',initialer='$initialer',konto_id='$konto_id',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',email='$email',tlf='$tlf',fax='$fax',privattlf='$privattlf',mobil='$mobil',cprnr='$cprnr',notes='$notes',afd='$afd',lukket='$lukket',bank='$bank',startdate='$startdate',slutdate='$slutdate',loen='$loen',extraloen='$extraloen',trainee='$trainee' where id='$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if ($menu=='T') header("location:ansatte.php?id=$id&funktion=ret_ansat");
		}
		for ($x=1; $x<=$pro_antal; $x++) { 
			if ($provision_id[$x]) {
				$provision[$x]=usdecimal($provision[$x]);
				db_modify("update provision set provision='$provision[$x]',gruppe_id='$gruppe_id[$x]' where id = '$provision_id[$x]'",__FILE__ . " linje " . __LINE__);
			} elseif ($provision[$x]) {
				$provision[$x]=usdecimal($provision[$x]);
				if (!$r= db_fetch_array(db_select("select id from provision where gruppe_id = '$gruppe_id[$x]' and ansat_id='$id'",__FILE__ . " linje " . __LINE__))) {
					db_modify("insert into provision (provision,gruppe_id,ansat_id) values ('$provision[$x]','$gruppe_id[$x]','$id')",__FILE__ . " linje " . __LINE__);
				}
			}
		}
		$box=if_isset($_POST['box']);
		$extra_id_0=if_isset($_POST['extra_id_0']);
		$extra_id_1=if_isset($_POST['extra_id_1']);
		if (count($box) && $id > 0) {
			if (!$extra_id_0) {
				$r = db_fetch_array($q=db_select("select id from grupper where art='ANSAT' and kodenr='$id' and (kode='0' or kode = NULL)",__FILE__ . " linje " . __LINE__));
				$extra_id_0=$r['id'];
			}
			if (!$extra_id_0) {
				db_modify("insert into grupper (beskrivelse,kodenr,kode,art) values ('Ekstra felter på ansatte stamkort','$id','0','ANSAT')",__FILE__ . " linje " . __LINE__);
				$r = db_fetch_array($q=db_select("select id from grupper where art='ANSAT' and kodenr='$id' and kode='0'",__FILE__ . " linje " . __LINE__));
				$extra_id_0=$r['id'];
			}
			if (!$extra_id_1) {
				$r = db_fetch_array($q=db_select("select id from grupper where art='ANSAT' and kodenr='$id' and kode='1'",__FILE__ . " linje " . __LINE__));
				$extra_id_1=$r['id'];
			}
			if (!$extra_id_1) {
				db_modify("insert into grupper (beskrivelse,kodenr,kode,art) values ('Ekstra felter på ansatte stamkort','$id','1','ANSAT')",__FILE__ . " linje " . __LINE__);
				$r = db_fetch_array($q=db_select("select id from grupper where art='ANSAT' and kodenr='$id' and kode='1'",__FILE__ . " linje " . __LINE__));
				$extra_id_1=$r['id'];
			}

	/*
	echo "update grupper set 
				box1='".db_escape_string($box[1])."',
				box2='".db_escape_string($box[2])."',
				box3='".db_escape_string($box[3])."',
				box4='".db_escape_string($box[4])."',
				box5='".db_escape_string($box[5])."',
				box6='".db_escape_string($box[6])."',
				box7='".db_escape_string($box[7])."',
				box8='".db_escape_string($box[8])."',
				box9='".db_escape_string($box[9])."',
				box10='".db_escape_string($box[10])."',
				box11='".db_escape_string($box[11])."',
				box12='".db_escape_string($box[12])."',
				box13='".db_escape_string($box[13])."',
				box14='".db_escape_string($box[14])."' 
			where id='$extra_id_0'<br>";

	echo "update grupper set 
				box1='".db_escape_string($box[15])."',
				box2='".db_escape_string($box[16])."',
				box3='".db_escape_string($box[17])."',
				box4='".db_escape_string($box[18])."',
				box5='".db_escape_string($box[19])."',
				box6='".db_escape_string($box[20])."',
				box7='".db_escape_string($box[21])."',
				box8='".db_escape_string($box[22])."',
				box9='".db_escape_string($box[23])."',
				box10='".db_escape_string($box[24])."',
				box11='".db_escape_string($box[25])."',
				box12='".db_escape_string($box[26])."',
				box13='".db_escape_string($box[27])."',
				box14='".db_escape_string($box[28])."' 
			where id='$extra_id_1'<br>";
	*/		
			db_modify("update grupper set 
				box1='".db_escape_string($box[1])."',
				box2='".db_escape_string($box[2])."',
				box3='".db_escape_string($box[3])."',
				box4='".db_escape_string($box[4])."',
				box5='".db_escape_string($box[5])."',
				box6='".db_escape_string($box[6])."',
				box7='".db_escape_string($box[7])."',
				box8='".db_escape_string($box[8])."',
				box9='".db_escape_string($box[9])."',
				box10='".db_escape_string($box[10])."',
				box11='".db_escape_string($box[11])."',
				box12='".db_escape_string($box[12])."',
				box13='".db_escape_string($box[13])."',
				box14='".db_escape_string($box[14])."' 
			where id='$extra_id_0'",__FILE__ . " linje " . __LINE__);

			db_modify("update grupper set 
				box1='".db_escape_string($box[15])."',
				box2='".db_escape_string($box[16])."',
				box3='".db_escape_string($box[17])."',
				box4='".db_escape_string($box[18])."',
				box5='".db_escape_string($box[19])."',
				box6='".db_escape_string($box[20])."',
				box7='".db_escape_string($box[21])."',
				box8='".db_escape_string($box[22])."',
				box9='".db_escape_string($box[23])."',
				box10='".db_escape_string($box[24])."',
				box11='".db_escape_string($box[25])."',
				box12='".db_escape_string($box[26])."',
				box13='".db_escape_string($box[27])."',
				box14='".db_escape_string($box[28])."' 
			where id='$extra_id_1'",__FILE__ . " linje " . __LINE__);
		}
	}
}

?>

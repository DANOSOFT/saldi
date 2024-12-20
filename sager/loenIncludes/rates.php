<?php

function satser() {
	global $charset;
	global $db;
	$visLønninger='checked';
	global $sprog_id;
	
	#'plads','skole','sygdom','barn_syg');
	
	if (isset($_POST['gruppe_id']) && $gruppe_id=$_POST['gruppe_id']) {
        
 		$hideSalary      = $_POST['hideSalary'];
		$skur1           = usdecimal($_POST['skur1']);
		$skur2           = usdecimal($_POST['skur2']);
		$sygdom          = usdecimal($_POST['sygdom']);
		$skole           = usdecimal($_POST['skole']);
		$plads           = usdecimal($_POST['plads']);
		$hourId          = $_POST['hourId'];
		$hourValue       = $_POST['hourValue'];
		$hourDescription = $_POST['hourDescription'];
		$periode         = usdate($_POST['periode']);
		$traineemdr      = usdecimal($_POST['traineemdr']);
		$traineepct      = usdecimal($_POST['traineepct']);
		$mentorRate      = usdecimal($_POST['mentorRate']);
		$km_sats         = usdecimal($_POST['km_sats']);
		$km_fra          = usdecimal($_POST['km_fra']);
		$overtid_50pct   = usdecimal($_POST['overtid_50pct']);
		$overtid_100pct   = usdecimal($_POST['overtid_100pct']);
		//barsel
		
		$qtxt = "select id from settings where var_name = 'hideSalary'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "update settings set var_value = '$hideSalary' where id = '$r[id]'";
		} elseif ($hideSalary) {
			$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.="('wageRates','hideSalary','$hideSalary',";
			$qtxt.="'Hides staff wage on timetables','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select id from settings where var_name = 'mentorRate'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "update settings set var_value = '$mentorRate' where id = '$r[id]'";
		} else {
			$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
			$qtxt.="('wageRates','mentorRate','$mentorRate',";
			$qtxt.="'salary supplement for mentoring','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);

		$qtxt = "update grupper set box1='".$skur1.chr(9).$skur2."',box2='$sygdom',box3='$skole',box4='$periode',";
		$qtxt.= "box5='".$traineemdr.chr(9).$traineepct."',box6='".$km_sats.chr(9).$km_fra."',box7='$plads',";
		$qtxt.= "box8='".$overtid_50pct.chr(9).$overtid_100pct."' where id='$gruppe_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		for ($x=0;$x<count($hourValue);$x++) {
			$qtxt=NULL;
			$hourValue[$x]=usdecimal($hourValue[$x]);
			$hourDescription[$x]=db_escape_string($hourDescription[$x]);
			if ($hourId[$x]) {
				if ($hourDescription[$x]) $qtxt = "update settings set var_value='$hourValue[$x]',var_description='$hourDescription[$x]' ";
				else $qtxt="delete from settings "; 
				$qtxt.= "where id = '$hourId[$x]'";
			} elseif ($hourDescription[$x] && $hourValue[$x]) {
				$qtxt = "insert into settings (var_name,var_grp,var_value,var_description,user_id) values ";
				$qtxt.= "('hourTypes$x','casePayment','$hourValue[$x]','$hourDescription[$x]','0')";
			}
			if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	} else $hourDescription = $hourValue = array();
	
	$gruppe_id=$hourValue=array();
	$r=db_fetch_array(db_select("select * from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
	$gruppe_id=$r['id'];
	list($skur1,$skur2)=explode(chr(9),$r['box1']);
	$sygdom=$r['box2'];
	$skole=$r['box3'];
	$periode=$r['box4'];
	list($traineemdr,$traineepct)=explode(chr(9),$r['box5']);
	list($km_sats,$km_fra)=explode(chr(9),$r['box6']);
	$plads=$r['box7'];
	list($overtid_50pct,$overtid_100pct)=explode(chr(9),$r['box8']);
	$qtxt="select * from settings where var_grp='casePayment'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$x=0;	
	while ($r=db_fetch_array($q)) {
		$hourId[$x]=$r['id'];
		$hourValue[$x]=$r['var_value'];
		$hourDescription[$x]=$r['var_description'];
		$x++;
	}
	
	$qtxt="select id,var_value from settings where var_name = 'hideSalary' and (var_grp = 'wageRates' or var_grp = 'items')";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$hideSalary_Id=$r['id'];
		($r['var_value'])?$hideSalary='checked':$hideSalary='';
	}
	$qtxt="select var_value from settings where var_name = 'mentorRate' and var_grp = 'wageRates'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$mentorRate = $r['var_value'];
	}

	//barsel
	if (!$gruppe_id) {
		db_modify("insert into grupper (beskrivelse,kodenr,art) values ('Lønsatser','0','loen')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
		$gruppe_id=$r['id'];$plads=0;$skur1=0;$skur2=0;$sygdom=0;$skole=0;//barsel
	}
	print "<div class=\"content\">\n";
		print "<form name=\"loensatser\" action=\"loen.php?funktion=satser\" method=\"post\">\n";
			print "<div style=\"float:left; width:778px;\">\n";
				print "<h3>Satser</h3>\n";
				print "<div style=\"float:left; width:389px;\">\n";
					print "<div class=\"contentA\">\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Skjul lønsatser</div>
								<div class=\"rightMediumLarge\">
								<input type=\"checkbox\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" 
								title=\"Skjuler lønsatser på lønseddel\" name=\"hideSalary\" $hideSalary></div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Skur - lav sats</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"skur1\" value=\"".dkdecimal($skur1,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Skur - høj sats</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"skur2\" value=\"".dkdecimal($skur2,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Sygdom, timesats</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"sygdom\" value=\"".dkdecimal($sygdom,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Skole, timesats</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"skole\" value=\"".dkdecimal($skole,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Plads, timesats</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"plads\" value=\"".dkdecimal($plads,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\" title=\"Vælg dato for 1. dag i 1. lønperiode\">Periodestart</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"periode\" value=\"".dkdato($periode)."\"></div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\" title=\"Oplæringperiode for nye medarbejdere uden branchekendskab\">
								Oplæringsperiode (mdr)</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"traineemdr\" value=\"".dkdecimal($traineemdr,2)."\"> mdr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\" title=\"Løn% i oplæringperiode\">Oplæringssats (%)</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"traineepct\" value=\"".dkdecimal($traineepct,2)."\"> %</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\" title=\"\">Kilometersats</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"km_sats\" value=\"".dkdecimal($km_sats,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\" title=\"\">Km beregnes efter</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"km_fra\" value=\"".dkdecimal($km_fra,2)."\"> km.</div>
								<div class=\"clear\">
							</div></div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Overtidstillæg 50%</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"overtid_50pct\" value=\"".dkdecimal($overtid_50pct,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Overtidstillæg 100%</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"overtid_100pct\" value=\"".dkdecimal($overtid_100pct,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<div class=\"row\">
								<div class=\"leftLarge\">Mentor, timesats</div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"mentorRate\" value=\"".dkdecimal($mentorRate,2)."\"> kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						
						for ($x=0;$x<count($hourValue);$x++) {
							print "<div class=\"row\">
									<div class=\"leftLarge\"><input type=\"hidden\" name=\"hourId[$x]\" value=\"$hourId[$x]\">
									<input type=\"text\" class=\"textMediumLarge\" 
									style=\"text-align:left;width:120px\" name=\"hourDescription[$x]\" value=\"$hourDescription[$x]\"></div>
									<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
									style=\"text-align:right;width:80px\" name=\"hourValue[$x]\" value=\"".dkdecimal($hourValue[$x],2)."\"> kr.</div>
									<div class=\"clear\"></div>
								</div><!-- end of row -->\n";
						}
						print "<div class=\"row\">
								<div class=\"leftLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:left;width:120px\" name=\"hourDescription[$x]\" ></div>
								<div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" 
								style=\"text-align:right;width:80px\" name=\"hourValue[$x]\" > kr.</div>
								<div class=\"clear\"></div>
							</div><!-- end of row -->\n";
						print "<input type=\"hidden\" name=\"gruppe_id\" value='$gruppe_id'>\n";
						print "<input type='submit' accesskey='g' value='Gem / opdat&eacute;r' class='button gray medium' name='submit' onclick='javascript:docChange = false;'>\n";
					print "</div><!-- end of contentA -->\n";
				print "</div>\n";
			print "</div><!-- end of full container -->\n";
			print "<div class=\"clear\"></div>\n";
	
		print "</form>\n";
	print "</div>\n";
}
?> 

<?php
// ---------------includes/var2str.php---lap 3.5.0------2015-01-31----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2012-11-21 Indsat mulighed for at undlade dato foran måned 
// 2013-01-20 Rettet fejl i ovenstående - søg 20130120
// 2014.05.05 Indsat $posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr,$varemomssats så der kan anvendes variabler på ordrelinjer. Søg 20140505
// 2014.05.14 Ændret ovenstående så der erstattes både med og uden semikolon i enden af variablen. Søg 20140514
// 2015.01.31 Ændret samtlige elsif(substr.. til  if(substr.. da datovariabler ikke fungerede
// 2015.08.30 Serienumre findes nu i serienummertabellen.
// 2015.09.02 Nu kan 2. del også være en dag og ikke kun $ultimo. 20150902

if (!function_exists('var2str')) {
	function var2str($beskrivelse,$id,$posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr,$varemomssats,$rabat) {
		$id*=1;
		if ($serienr) {
			$ny_snr='';
			$qtxt="select serienr.serienr from serienr,ordrelinjer where ordrelinjer.ordre_id='$id' and ordrelinjer.posnr='$posnr' and (serienr.kobslinje_id=ordrelinjer.id or serienr.salgslinje_id=ordrelinjer.id) order by serienr.serienr";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)){
			if($ny_snr) $ny_snr.=", ";
			$ny_snr.=$r['serienr'];
			}
		}
		$r=db_fetch_array(db_select("select fakturadate,ordredate from ordrer where id = $id",__FILE__ . " linje " . __LINE__));
		if ($r['fakturadate']) $date=$r['fakturadate'];
		else $date=$r['ordredate'];
		list ($aar,$maaned,$dag)=explode("-",$date);
		$y=strlen($beskrivelse);
		$d_a=0;
		$d_nr=array();
		$d_pos=array();
		$m_a=0;
		$m_pos=array();
		$y_a=0;
		$y_pos=array();
		for ($x=0; $x<$y; $x++){ # strengen loebes igennem
			if (substr($beskrivelse,$x,7)=="\$ultimo"){ #erstatter $ultimo med måmedens sidste dag
				$d_a++;
				$d_nr[$d_a]=31;
				$d_pos[$d_a]=$x;	
				$z=$x+7;
				$beskrivelse = substr($beskrivelse,0,$x).$d_nr[$d_a].substr($beskrivelse,$z,$y);
				$x=$x+strlen($d_nr[$d_a])+1;
				$y=$y-(7+strlen($d_nr[$d_a])+1);
			} elseif(is_numeric(substr($beskrivelse,$x,2))) { #20150902
				$d_a++;
				$d_nr[$d_a]=substr($beskrivelse,$x,2);
				$d_pos[$d_a]=$x;
				$y=$y+3;
			} elseif(is_numeric(substr($beskrivelse,$x,1))) {
				$d_a++;
				$d_nr[$d_a]=substr($beskrivelse,$x,1);
				$d_pos[$d_a]=$x;
				$y=$y+2;
			}
			if (substr($beskrivelse,$x,8)=="\$kvartal"){ #start på variabel
				if ($maaned<4)$k_nr=1;
				elseif ($maaned<7)$k_nr=2;
				elseif ($maaned<10)$k_nr=3;
				else $k_nr=4;
				$z=$x+8;
				$beskrivelse = substr($beskrivelse,0,$x).$k_nr.substr($beskrivelse,$z,$y);
				$x=$x+strlen($m_nr[$m_a])+1;
				$y=$y-(7+strlen($m_nr[$m_a])+1);
			}
			if (substr($beskrivelse,$x,7)=="\$maaned"){ #start på variabel
				$m_a++; #månedsantal
				$m_nr[$m_a]=$maaned;
				$m_pos[$m_a]=$x;	
				$z=$x+7;
				if (substr($beskrivelse,$z,1)=="+") {
					$tal="";
					$z++;
					while (is_numeric(substr($beskrivelse,$z,1))) {
						$tal=$tal.substr($beskrivelse,$z,1);
						$z++;
					}	
					if ($tal) $m_nr[$m_a]=$m_nr[$m_a]+$tal;
				} 
				if (strlen($m_nr[$m_a])<2) $m_nr[$m_a]='0'.$m_nr[$m_a];
				$beskrivelse = substr($beskrivelse,0,$x).$m_nr[$m_a].substr($beskrivelse,$z,$y);
				$x=$x+strlen($m_nr[$m_a])+1;
				$y=$y-(7+strlen($m_nr[$m_a])+1);
			} 
			if (substr($beskrivelse,$x,4)=="\$aar"){ #start på variabel
				$y_a++;
				$y_nr[$y_a]=$aar;
				$y_pos[$y_a]=$x;	
				$z=$x+4;
				if (substr($beskrivelse,$z,1)=="+") {
					$tal="";
					$z++;
					while (is_numeric(substr($beskrivelse,$z,1))) {
						$tal=$tal.substr($beskrivelse,$z,1);
						$z++;
					}	
					if ($tal) $y_nr[$y_a]=$y_nr[$y_a]+$tal;
				} 
				$beskrivelse = substr($beskrivelse,0,$x).$y_nr[$y_a].substr($beskrivelse,$z,$y);
				$x=$x+strlen($y_nr[$y_a])+1;
				$y=$y-(4+strlen($y_nr[$y_a])+1);
			}
		}
		for ($x=1;$x<=$m_a;$x++) {
			while ($m_nr[$x]>12) {
				$m_nr[$x]=$m_nr[$x]-12;
				$y_nr[$x]=$y_nr[$x]+1;
				if ($m_nr[$x]<10)$m_nr[$x]='0'.$m_nr[$x];
				$z=$m_pos[$x]+2;
				$beskrivelse = substr($beskrivelse,0,$m_pos[$x]).$m_nr[$x].substr($beskrivelse,$z);
				$z=$y_pos[$x]+4;
				$beskrivelse = substr($beskrivelse,0,$y_pos[$x]).$y_nr[$x].substr($beskrivelse,$z);
			}
			if (trim(substr($beskrivelse,$m_pos[$x]-1,1))) { # if indsat 20121121 
				$d_nr[$x]=substr($beskrivelse,$m_pos[$x]-3,2);
				if (!checkdate($m_nr[$x],$d_nr[$x],$y_nr[$x])) {
				while (!checkdate($m_nr[$x],$d_nr[$x],$y_nr[$x])) {
						$d_nr[$x]=$d_nr[$x]-1;
						if ($d_nr[$x]<27) break 1;
					}
				} 
			} else $d_nr[$x]='';
			$z=$m_pos[$x]-1;
			if ($d_nr[$x]) $beskrivelse = substr($beskrivelse,0,$m_pos[$x]-3).$d_nr[$x].substr($beskrivelse,$z);
		}
		# 20140505 ->
		$beskrivelse=str_replace("\$posnr;","$posnr",$beskrivelse);
		$beskrivelse=str_replace("\$varenr;","$varenr",$beskrivelse);
		$beskrivelse=str_replace("\$antal;","$dkantal",$beskrivelse);
		$beskrivelse=str_replace("\$enhed;","$enhed",$beskrivelse);
		$beskrivelse=str_replace("\$pris;","$dkpris",$beskrivelse);
		$beskrivelse=str_replace("\$procent;","$dkprocent",$beskrivelse);
		$beskrivelse=str_replace("\$serienr;","$serienr",$beskrivelse);
		$beskrivelse=str_replace("\$varemomssats;","$varemomssats",$beskrivelse);
		$beskrivelse=str_replace("\$rabat;","$rabat",$beskrivelse);
		$beskrivelse=str_replace("\$posnr","$posnr",$beskrivelse);
		$beskrivelse=str_replace("\$varenr","$varenr",$beskrivelse);
		$beskrivelse=str_replace("\$antal","$dkantal",$beskrivelse);
		$beskrivelse=str_replace("\$enhed","$enhed",$beskrivelse);
		$beskrivelse=str_replace("\$pris","$dkpris",$beskrivelse);
		if (!strstr($beskrivelse,"\$procenttillæg")) $beskrivelse=str_replace("\$procent","$dkprocent",$beskrivelse);
		$beskrivelse=str_replace("\$serienr","$ny_snr",$beskrivelse);
		$beskrivelse=str_replace("\$varemomssats","$varemomssats",$beskrivelse);
		$beskrivelse=str_replace("\$rabat","$rabat",$beskrivelse);
		# <- 20140505
		return($beskrivelse);
	}
}
?>
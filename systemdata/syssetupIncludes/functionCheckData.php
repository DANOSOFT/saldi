<?php
function tjek ($id,$beskrivelse,$kodenr,$kode,$art,$box1,$box2,$box3,$box4,$box5,$box6,$box7,$box8,$box9) {
	$fejl=NULL;

	if ($beskrivelse)	{
		if ($art=='VG')	{
			if ($box2){ # 20150130 Test Lager Tilgang og Træk (start)
				if (!$box1) print tekstboks('"Lager Tilgang" skal udfyldes, n&aring;r "Lager Tr&aelig;k" er angivet.');
				elseif(!$fejl) $fejl=kontotjek($box1);
			}

			if ($box1){
				if (!$box2) print tekstboks('"Lager Tr&aelig;k" skal udfyldes n&aring;r "Lager Tilgang" er angivet.');
				elseif(!$fejl) $fejl=kontotjek($box2);
			} #20150130 Test Lager Tilgang og Træk (slut)
			if (!$box3) print tekstboks('Varek&oslash;b skal udfyldes'); # 20141212A
			elseif(!$fejl) $fejl=kontotjek($box3);
			if (!$box4) print tekstboks('Varesalg skal udfyldes'); # 20141212A
			elseif(!$fejl) $fejl=kontotjek($box4);
			if (!$fejl && $box5) $fejl=kontotjek($box5);
			if (!$fejl && $box6) $fejl=kontotjek($box6);
		}
		if ($art=='KM' || $art=='SM' || $art=='EM' || $art=='YM') { # 20132127
			if (!is_numeric($kodenr) && $kodenr!='-') { #20140621
				print tekstboks('Nr skal være numerisk! ('.$kodenr.')'); # 20141212A
				return ('1');
			}
		}
		if (!$fejl && $art=='KG' && $box9 && !$box6) {
			$fejl="S. moms grp skal udfyldes når OB (Omvendt betalingspligt) er afmærket";
			print tekstboks($fejl); # 20141212A
		}
		if (!$fejl && ($art=='DS'||$art=='KS'||$art=='KM'||$art=='SM')) $fejl=kontotjek($box1);
#cho __line__."<br>";
		if (!$fejl && ($art=='DG'||$art=='KG')) $fejl=momsktotjek($art,$box1);
#cho __line__."<br>";
		if (!$fejl && $art=='KG') $fejl=momsktotjek('DG',$box6);
#cho __line__."<br>";
		if (!$fejl && ($art=='DG'||$art=='KG')) $fejl=kontotjek($box2);
#cho __line__."<br>";
		if (!$fejl && ($art=='DG'||$art=='KG')) $fejl=kontotjek($box5);
#cho __line__."<br>";
		if (!$fejl && ($art=='DG'||$art=='KG')) $fejl=sprogtjek($box4);
#cho __line__."<br>";
		if (!$fejl && ($art=='LG')) $fejl=afdelingstjek($box1);
#cho __line__."<br>";
		return $fejl;
	}
}
?>

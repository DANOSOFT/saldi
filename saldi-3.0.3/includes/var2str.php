<?php
if (!function_exists('var2str')) {
function var2str($beskrivelse,$id)
{
	$r=db_fetch_array(db_select("select fakturadate,ordredate from ordrer where id = $id",__FILE__ . " linje " . __LINE__));
	if ($r['fakturadate']) $date=$r['fakturadate'];
	else $date=$r['ordredate'];
	list ($aar,$maaned,$dag)=split("-",$date);
	$y=strlen($beskrivelse);
	$d_a=0;
	$d_nr=array();
	$d_pos=array();
	$m_a=0;
	$m_pos=array();
	$y_a=0;
	$y_pos=array();
	for ($x=0; $x<$y; $x++){ # strengen loebes igennem
		if (substr($beskrivelse,$x,7)=="\$ultimo"){ #start på variabel
			$d_a++;
			$d_nr[$d_a]=31;
			$d_pos[$d_a]=$x;	
			$z=$x+7;
/*			
			if (substr($beskrivelse,$z,1)=="+") {
				$tal="";
				$z++;
				while (is_numeric(substr($beskrivelse,$z,1))) {
					$tal=$tal.substr($beskrivelse,$z,1);
					$z++;
				}	
				if ($tal) $d_nr[$d_a]=$d_nr[$d_a]+$tal;
			} 
*/			
			$beskrivelse = substr($beskrivelse,0,$x).$d_nr[$d_a].substr($beskrivelse,$z,$y);
			$x=$x+strlen($d_nr[$d_a])+1;
			$y=$y-(7+strlen($d_nr[$d_a])+1);
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
			$m_a++;
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
		$d_nr[$x]=substr($beskrivelse,$m_pos[$x]-3,2);
		if (!checkdate($m_nr[$x],$d_nr[$x],$y_nr[$x])) {
			while (!checkdate($m_nr[$x],$d_nr[$x],$y_nr[$x])) {
				$d_nr[$x]=$d_nr[$x]-1;
				if ($d_nr[$x]<27) break;
			}	
		} 
		$z=$m_pos[$x]-1;
	  $beskrivelse = substr($beskrivelse,0,$m_pos[$x]-3).$d_nr[$x].substr($beskrivelse,$z);
	}
	return($beskrivelse);
}}
?>
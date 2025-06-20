<?
// -----------------------includes/genberegn.php-------patch 0.971---------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg

// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2005 ITz ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
#include("../includes/db_query.php");

if ($regnskabsaar=$_GET[regnskabsaar]) 
{    
    print "Genberegner regnskabsaar $regnskabsaar<br>";
    genberegn($regnskabsaar);
#    exit;
}

#if (!function_exists('genberegn'))
#{
    function genberegn($regnskabsaar)
    {
    $query = db_select("select * from grupper where kodenr='$regnskabsaar' and art='RA'");
    $row = db_fetch_array($query);
    $startmaaned=$row[box1]*1;
    $startaar=$row[box2]*1;
    $slutmaaned=$row[box3]*1;
    $slutaar=$row[box4]*1;
    $slutdato=31;
    
    while (!checkdate($slutmaaned, $slutdato, $slutaar))
    {
#echo "$slutdato, $slutmaaned, $slutaar  ";        
	$slutdato=$slutdato-1;
        if ($slutdato<28){break;}
    }
#echo "slutdato $slutdato<br>";    
    $regnstart = $startaar. "-" . $startmaaned . "-" . '01';
    $regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

    $kontoantal=0;
    $query = db_select("select * from kontoplan where regnskabsaar='$regnskabsaar' and (kontotype='D' or kontotype='S') order by kontonr");
    while ($row = db_fetch_array($query)) 
    {
        $kontoantal=$kontoantal+100;
        $konto_id[$kontoantal]=$row[id];
        $kontonr[$kontoantal]=trim($row[kontonr]);
    }        

    $x=0;
    $query = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' order by transdate");
    while ($row = db_fetch_array($query))
    {
        for ($x=1; $x<=12; $x++)
        {
            list ($null, $md, $null) = split("-", $row[transdate]);
            if ($x==$md)
            {
                for ($y=100; $y<=$kontoantal; $y=$y+100)
                {
                     if ($kontonr[$y]==trim($row[kontonr]))
                     {
                         $z=$x+$y;
                         $belob[$z]=$belob[$z]+$row[debet]-$row[kredit];
                     }
                }
            }     
        }
    }
    for ($y=100; $y<=$kontoantal; $y=$y+100)
    {
        $a=$y+1; $b=$y+2; $c=$y+3; $d=$y+4; $e=$y+5; $f=$y+6; $g=$y+7; $h=$y+8; $i=$y+9; $j=$y+10; $k=$y+11; $l=$y+12; 
        if (!$belob[$a]) {$belob[$a]='0';}
        if (!$belob[$b]) {$belob[$b]='0';}
        if (!$belob[$c]) {$belob[$c]='0';}
        if (!$belob[$d]) {$belob[$d]='0';}
        if (!$belob[$e]) {$belob[$e]='0';}
        if (!$belob[$f]) {$belob[$f]='0';}
        if (!$belob[$g]) {$belob[$g]='0';}
        if (!$belob[$h]) {$belob[$h]='0';}
        if (!$belob[$i]) {$belob[$i]='0';}
        if (!$belob[$j]) {$belob[$j]='0';}
        if (!$belob[$k]) {$belob[$k]='0';}
        if (!$belob[$l]) {$belob[$l]='0';}
# echo "update kontoplan set md01 =$belob[$a], md02 =$belob[$b], md03 =$belob[$c], md04 =$belob[$d], md05 =$belob[$e], md06 =$belob[$f], md07 =$belob[$g], md08 =$belob[$h], md09 =$belob[$i], md10 =$belob[$j], md11 =$belob[$k], md12 =$belob[$l] where id=$konto_id[$y]<br>";        
	db_modify("update kontoplan set md01 =$belob[$a], md02 =$belob[$b], md03 =$belob[$c], md04 =$belob[$d], md05 =$belob[$e], md06 =$belob[$f], md07 =$belob[$g], md08 =$belob[$h], md09 =$belob[$i], md10 =$belob[$j], md11 =$belob[$k], md12 =$belob[$l] where id=$konto_id[$y]");
    }
  }
#}
?>

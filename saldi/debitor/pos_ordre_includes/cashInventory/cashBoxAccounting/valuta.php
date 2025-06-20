<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/cashInventory/cashBoxAccounting/valuta.php ---------- lap 3.7.9----2019.05.09-------
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190509 LN Handle the valuta for the ../cashBoxAccounting.php file


    if ($valuta[$z]=='DKK' || !$valuta[$z]) {
        if ($kassekonto && $mellemkonto && $udtages) {		# --> 20140709
            $r=db_fetch_array(db_select("select beskrivelse from kontoplan where kontonr = '$mellemkonto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
            $mellemnavn=db_escape_string($r['beskrivelse']);		# <-- 20140709 + *-1 ved udtages hvis < 0
            if ($udtages>0) {$debet=0;$kredit=$udtages;}
            else {$debet=$udtages*-1;$kredit=0;}
            $qtxt="insert into transaktioner";
            $qtxt.=" (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr)";
            $qtxt.=" values";
            $qtxt.=" ('0','$dd','Overført til $mellemnavn fra kasse $kasse','$kassekonto','0','$debet','$kredit',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
            $qtxt="insert into transaktioner";
            $qtxt.=" (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr)";
            $qtxt.=" values";
            $qtxt.=" ('0','$dd','Overført til $mellemnavn fra kasse $kasse','$mellemkonto','0','$kredit','$debet',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        } 	# --> 20140709
        if ($kassekonto && $diffkonto && $kassediff) {
            if ($kassediff>0) {$debet=$kassediff;$kredit=0;}
            else {$debet=0;$kredit=$kassediff*-1;}
            $qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$dd','Kassedifference, kasse $kasse','$kassekonto','0','$debet','$kredit',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
            $qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$dd','Kassedifference, kasse $kasse','$diffkonto','0','$kredit','$debet',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        } #else #cho __line__." Ikke her<br>";
    } else {
        if ($ValutaKonti[$z] && $ValutaTilgang[$z]) {
            if ($ValutaTilgang[$z]>0) {$debet=$ValutaTilgang[$z];$kredit=0;}
            else {$debet=0;$kredit=$ValutaTilgang[$z]*-1;}
            $qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$dd','Kassesalg $valuta[$z], kasse $kasse','$ValutaKonti[$z]','0','$debet','$kredit',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
            $qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$dd','Kassesalg $valuta[$z], kasse $kasse','$kassekonto','0','$kredit','$debet',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        }
        if ($ValutaKonti[$z] && $ValutaMlKonti[$z] && $ValutaUdtages[$z]) {
            $r=db_fetch_array(db_select("select beskrivelse from kontoplan where kontonr = '$ValutaMlKonti[$z]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
            $mellemnavn=db_escape_string($r['beskrivelse']);		#<-- 20140709 + *-1 ved udtages hvis < 0
            if ($ValutaUdtages[$z]>0) {$debet=0;$kredit=$ValutaUdtages[$z];}
            else {$debet=$ValutaUdtages[$z]*-1;$kredit=0;}
            $qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$dd','Overført til $mellemnavn fra kasse $kasse','$ValutaKonti[$z]','0','$debet','$kredit',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
            $qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$dd','Overført til $mellemnavn fra kasse $kasse','$ValutaMlKonti[$z]','0','$kredit','$debet',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        }	# --> 20140709
        if ($ValutaKonti[$z] && $ValutaDifKonti[$z] && $ValutaKasseDiff[$z]) {
            if ($ValutaKasseDiff[$z]>0) {
                $debet=$ValutaKasseDiff[$z];
                $kredit=0;
            }	else {
                $debet=0;
                $kredit=$ValutaKasseDiff[$z]*-1;
            }
            $qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$dd','Kassedifference, kasse $kasse','$ValutaKonti[$z]','0','$debet','$kredit',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
            $qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$dd','Kassedifference, kasse $kasse','$ValutaDifKonti[$z]','0','$kredit','$debet',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        }
    }

?>


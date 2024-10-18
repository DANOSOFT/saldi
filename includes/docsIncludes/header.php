<?php
// --- includes/documents.php -----patch 4.0.8 ----2023-07-25-----------
//                           LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
//20230725 LOE Minor modification for kassekladde

include("../includes/topline_settings.php");
global $menu;

if ($source=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kladde_id&id=$sourceId&fokus=$fokus"; #20230725
elseif ($source=="debitorOrdrer") $tmp="../debitor/ordre.php?id=$sourceId&fokus=$fokus"; #20140122
elseif ($source=="creditorOrder") $tmp="../kreditor/ordre.php?id=$sourceId&fokus=$fokus"; #20140122
else $tmp="../debitor/historikkort.php?id=$sourceId&fokus=$fokus";

if ($menu=='S') {
    print "<td width='10%'><a href=$tmp accesskey=L>
           <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(30, $sprog_id)."</button></a></td>";
    print "<td width='80%' align='center' style='$topStyle'>".findtekst(1408, $sprog_id)."</td>";
    print "<td width='10%' align='center' style='$topStyle'><br></td>";
} else {
    print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">
           <a href=$tmp accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
    print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst(1408, $sprog_id)."</td>";
    print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
}

?>

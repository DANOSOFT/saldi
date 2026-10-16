<?php
// ------orderFuncIncludes/topLine.php---patch 5.0.0 ----2026-10-08------------
// ------orderFuncIncludes/topLine.php---patch 5.0.0 ----2026-10-06------------
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20251115 LOE Created file to standardize top line  in ordrefunc includes 
// 20261008 Sawaneh The customer order on the new page head (topbar addendum §5, prototype_dashboard_tema v5): the order
//                  heading as the title, Ny as the primary button (Alt+N), Hjælp kept as the hidden tour trigger for the
//                  Assist menu, Tilbage only outside the shell. Same links as the old bar (confirmClose keeps unsaved changes).

// 20261006 CL/LH SST-848: Updated to use new SVG icons
// 20261015 Sawaneh Master merge: SST-851's double-click guard kept on the page-bar back link (plain URL, confirm in onclick).

include_once(get_relative()."includes/stdFunc/pageBar.php");

$backHref = (!strstr($returside, "ordre.php")) ? $returside : "$returside?id=$id";

// SST-851: the print page's Tilbage sits at the same spot, so the second click of a double-click would otherwise
// close the order that has just opened; clicks in the first 600 ms are ignored.
page_bar(array(
	'title' => $tekst,
	'back' => $backHref,
	'back_onclick' => 'if (performance.now() < 600) { return false; } confirmClose(' . json_encode($backHref) . ', ' . json_encode($alerttekst) . '); return false;',
	'help' => true,
	'primary' => array(findtekst('39|Ny', $sprog_id), '#', 'accesskey' => 'N',
		'onclick' => "confirmClose('$kort?returside=" . urlencode($returside) . "&ordre_id=$ny_id&fokus=$fokus','$alerttekst');"),
));
?>
<style>
tfoot tr td #footer-box {
    margin-bottom: 17px; 
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: flex-end;
}
a:link{
		text-decoration: none;
	}
</style>

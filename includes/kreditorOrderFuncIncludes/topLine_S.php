<?php
//----includes/kreditorOrderFuncIncludes/topLine_S.php---patch 4.1.1 ----2025-12-03---
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20251203 LOE Created file to standardize top used for managing S menu in kreditor/ordre.php
// 20260908 SZ SST-755: Luk links now release the lock properly (tidspkt added; the $valg
//           branch had no tabel/id at all, and a '?' where it needed '&').
// 20260924 SZ SST-755 (CodeRabbit): this file is include()d from kreditor/ordre.php's
//           sidehoved() partway through, after that function already computed
//           $sidehovedTidspktQs (a &lockToken=... query string, stamped via
//           refresh_lock_token()) - reuse it instead of independently re-querying tidspkt and
//           building a separate, token-less query string, which let this menu's own Luk links
//           bypass the whole per-render-token protection sidehoved() otherwise provides.
// 20261008 Sawaneh WP-3.1: valg is added to the returside only when missing (? or &), no empty konto_id, and the returside
//                  is urlencoded in both luk.php links. WP-3.4: Visning opens with popup=1.
// 20261008 Sawaneh The supplier order on the new page head (topbar addendum §5, prototype_dashboard_tema v5): the order
//                  heading as the title, Visning as a page action, Ny as the primary button (Alt+N), Hjælp kept as the hidden
//                  tour trigger, the lock-releasing Luk only outside the shell. Same links as the old bar.



print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>".findtekst(547,$sprog_id)."</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

    ######################
	$tilbage_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

 
	$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
	$add_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';


	#####################


	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";
	include_once(__DIR__ . "/../stdFunc/pageBar.php");

	// 20260908 SZ SST-755: append the row's current tidspkt to every Luk link so
	// includes/luk.php can confirm this tab still holds the lock before releasing it.
	// 20260924 SZ SST-755 (CodeRabbit): reuse sidehoved()'s own $sidehovedTidspktQs (despite
	// its name, a &lockToken=... string - see the comment there).
	$topLineSTidspktQs = $sidehovedTidspktQs ?? '';

	if ($kort) {
		$backHref = "../kreditor/ordre.php?id=$id&fokus=$fokus";
	} elseif ($valg) {
		// WP-3.1: valg is added only when the returside lacks it, with ? or & as needed; no empty konto_id.
		$valgReturside = (strpos((string) $returside, 'valg=') === false) ? $returside . (strpos((string) $returside, '?') === false ? '?' : '&') . 'valg=' . urlencode($valg) : $returside;
		$backHref = "javascript:confirmClose('../includes/luk.php?returside=" . urlencode($valgReturside) . "&tabel=ordrer&id=$id$topLineSTidspktQs','$alerttekst')";
	} else {
		$backHref = "javascript:confirmClose('../includes/luk.php?returside=" . urlencode((string) $returside) . "&tabel=ordrer&id=$id$topLineSTidspktQs','$alerttekst')";
	}

	$actions = array();
	$primary = null;
	$nyLabel = findtekst('39|Ny', $sprog_id);
	if (($kort != "../lager/varekort.php" && $returside != "ordre.php") && ($id)) {
		$primary = array($nyLabel, '#', 'accesskey' => 'N', 'onclick' => "confirmClose('ordre.php?returside=ordreliste.php','$alerttekst');");
	} elseif (($kort == "../lager/varekort.php" && $returside == "ordre.php") && ($id)) {
		$primary = array($nyLabel, "$kort?returside=$returside&ordre_id=$id", 'accesskey' => 'N');
	} elseif ($kort == "../kreditor/kreditorkort.php") {
		$actions[] = array(findtekst(813, $sprog_id), '#', 'bx-show', 'onclick' => "kreditor_vis=window.open('kreditorvisning.php?popup=1','kreditor_vis','scrollbars=1,resizable=1');kreditor_vis.focus();"); #20210716
		$primary = array($nyLabel, '#', 'accesskey' => 'N', 'onclick' => "confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst');");
	} elseif (($id) || ($kort != "../lager/varekort.php")) {
		$primary = array($nyLabel, '#', 'accesskey' => 'N', 'onclick' => "confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst');");
	}

	page_bar(array(
		'title' => $tekst,
		'back' => $backHref,
		'help' => true,
		'actions' => $actions,
		'primary' => $primary,
	));
	print "<div class=\"ordreform\">\n";
?>
<style>
	a:link{
		text-decoration: none;
	}
</style>

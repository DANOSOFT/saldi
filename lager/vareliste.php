<?php
// --------------------------------------------------lager/vareliste.php     patch 0.971----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// SQL finans maa kun efter skriftelig aftale med ITz ApS anvendes som
// vaert for andre virksomheders regnskaber.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 ITz ApS
// ----------------------------------------------------------------------
// 20260906 CDX/LH Route legacy item-list bookmarks to the maintained inventory page.

/**
 * Preserve the supported legacy list options without forwarding arbitrary input.
 *
 * @return string Relative URL of the maintained item list.
 */
function legacyItemListTarget(array $query): string
{
    $options = [];
    $sort = $query['sort'] ?? '';
    if (is_string($sort) && in_array($sort, ['varenr', 'enhed', 'beskrivelse', 'beholdning', 'salgspris'], true)) {
        $options['sort'] = $sort;
    }
    if (($query['vis_lev'] ?? '') === 'on') {
        $options['vis_lev'] = 'on';
    }
    return 'varer.php' . ($options ? '?' . http_build_query($options) : '');
}

// varer.php owns session validation, inventory permissions and rendering.
header('Location: ' . legacyItemListTarget($_GET), true, 302);
exit;

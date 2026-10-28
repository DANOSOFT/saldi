<?php
// SALDI Assist Guide: hvilken widget skallen (index/main.php) indlaeser.
// doc/ai-plan/mcp-assistant/SPEC-guide.md (lean v1).
//
// Miljoe (webserverens env):
//   SALDI_ASSIST_GUIDE=on     standard (ogsaa naar den ikke er sat): Guide for alle regnskaber
//   SALDI_ASSIST_GUIDE=pilot  Guide for regnskaber med settings assistant/guide = on
//   SALDI_ASSIST_GUIDE=off    kill switch: v1-supportbotten
// Uden getMcpToken i skallen (master) er v2 Guide: hjaelp og navigation, ingen adgang
// til regnskabets tal.
//
// 20261012 CL/LH Created.
// 20261028 CL/LH The Guide is the default; v1 only with SALDI_ASSIST_GUIDE=off.

const SALDI_ASSIST_GUIDE_WIDGET_URL = 'https://wuweiworkai.com/chaty-guide/widget.js';
const SALDI_ASSIST_V1_WIDGET_URL = 'https://wuweiworkai.com/chaty-v2/widget.js';

/**
 * @param string      $mode           SALDI_ASSIST_GUIDE, '' naar den ikke er sat
 * @param string|null $tenant_setting settings assistant/guide for regnskabet, null naar den ikke findes
 * @return bool true naar skallen skal indlaese v2-widgeten
 */
function saldi_assist_guide_enabled(string $mode, ?string $tenant_setting): bool
{
    if ($mode === 'on' || $mode === '') {
        return true;
    }
    return $mode === 'pilot' && $tenant_setting === 'on';
}

/**
 * Regnskabets egen raekke (ikke en brugers eller en kasses): get_settings_value() uden
 * bruger matcher ogsaa brugerraekker, derfor eget opslag.
 *
 * @return string|null var_value, eller null naar raekken ikke findes
 */
function saldi_assist_guide_tenant_setting(): ?string
{
    $row = db_fetch_array(db_select(
        "SELECT var_value FROM settings WHERE var_grp = 'assistant' AND var_name = 'guide'"
        . " AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1",
        __FILE__ . ' linje ' . __LINE__
    ));
    return $row ? (string)$row['var_value'] : null;
}

/**
 * @param string $override SALDI_ASSIST_WIDGET_URL (en test-instans), '' naar den ikke er sat
 * @param bool   $guide    saldi_assist_guide_enabled()
 */
function saldi_assist_widget_url(string $override, bool $guide): string
{
    if ($override !== '') {
        return $override;
    }
    return $guide ? SALDI_ASSIST_GUIDE_WIDGET_URL : SALDI_ASSIST_V1_WIDGET_URL;
}

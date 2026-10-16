<?php
// SALDI Assist Guide: hvilken widget skallen (index/main.php) indlaeser.
// doc/ai-plan/mcp-assistant/SPEC-guide.md (lean v1).
//
// Miljoe (webserverens env):
//   SALDI_ASSIST_GUIDE=off    standard: v1-supportbotten som i dag
//   SALDI_ASSIST_GUIDE=pilot  v2-widgeten for regnskaber med settings assistant/guide = on
//   SALDI_ASSIST_GUIDE=on     v2-widgeten for alle regnskaber
// Uden getMcpToken i skallen (master) er v2 Guide: hjaelp og navigation, ingen adgang
// til regnskabets tal. Kill switch: SALDI_ASSIST_GUIDE=off.
//
// 20261012 CL/LH Created.

/**
 * @param string      $mode           SALDI_ASSIST_GUIDE
 * @param string|null $tenant_setting settings assistant/guide for regnskabet, null naar den ikke findes
 * @return bool true naar skallen skal indlaese v2-widgeten
 */
function saldi_assist_guide_enabled(string $mode, ?string $tenant_setting): bool
{
    if ($mode === 'on') {
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

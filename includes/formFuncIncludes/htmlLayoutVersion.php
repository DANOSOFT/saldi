<?php
// 20260929 CDX/PHR Preserve existing HTML layouts and allow an explicit switch to point-based rendering.

/** @return int Layout version, cached per tenant for the current request. */
function formHtmlLayoutVersion() {
    global $db;
    static $versions = array();
    $tenant = (string)$db;
    if (!isset($versions[$tenant])) {
        // Existing sessions may print before the login updater has initialized the setting.
        $value = get_settings_value('htmlLayoutVersion', 'forms', '1');
        $versions[$tenant] = (string)$value === '2' ? 2 : 1;
    }
    return $versions[$tenant];
}

/** @return void Save only an explicit, valid selection; older forms must not reset the version. */
function saveFormHtmlLayoutVersion($value) {
    if ($value !== '1' && $value !== '2') {
        return;
    }
    update_settings_value('htmlLayoutVersion', 'forms', $value, 'HTML form layout version: 1 legacy, 2 form typography and line widths');
}

/** @return void Initialize once during the tenant update, never while printing. */
function initializeFormHtmlLayoutVersion($dbType) {
    $lookup = "SELECT var_value FROM settings WHERE var_grp='forms' AND var_name='htmlLayoutVersion'";
    if (db_fetch_array(db_select($lookup, __FILE__ . ' line ' . __LINE__))) {
        return;
    }
    $mysql = in_array(strtolower($dbType), array('mysql', 'mysqli'), true);
    $lock = "CONCAT('saldi:html_layout:', MD5(DATABASE()))";
    if ($mysql) {
        $result = db_fetch_array(db_select("SELECT GET_LOCK($lock, 30) AS acquired", __FILE__ . ' line ' . __LINE__));
        if ((int)($result['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the HTML layout migration lock.');
        }
    } else {
        db_select('SELECT pg_advisory_lock(hashtext(current_database()), 20260929)', __FILE__ . ' line ' . __LINE__);
    }
    try {
        if (!db_fetch_array(db_select($lookup, __FILE__ . ' line ' . __LINE__))) {
            $html = db_fetch_array(db_select("SELECT id FROM grupper WHERE art='PV' AND kodenr='1' AND box3 IS NOT NULL AND box3<>'' AND box3<>'0'", __FILE__ . ' line ' . __LINE__));
            $version = $html ? '1' : '2';
            db_modify("INSERT INTO settings (var_name,var_grp,var_value,var_description) VALUES ('htmlLayoutVersion','forms','$version','HTML form layout version: 1 legacy, 2 form typography and line widths')", __FILE__ . ' line ' . __LINE__);
        }
    } finally {
        if ($mysql) {
            db_select("SELECT RELEASE_LOCK($lock)", __FILE__ . ' line ' . __LINE__);
        } else {
            db_select('SELECT pg_advisory_unlock(hashtext(current_database()), 20260929)', __FILE__ . ' line ' . __LINE__);
        }
    }
}

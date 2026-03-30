<?php
// --- systemdata/settings_layout.php --- 2026-03-29 ---
// Shared helper for the modern settings layout wrapper.
// Provides settings_layout_start() and settings_layout_end() so that
// standalone pages (kontoplan, valuta, brugere, etc.) can reuse the
// same sidebar-nav + content layout as diverse.php without duplicating
// the boilerplate.

/**
 * Opens the modern settings layout: CSS link, sidebar nav, content area.
 *
 * @param string $menu          Current menu mode ('T', 'S', or other)
 * @param string $active_page   Section key to highlight in the sidebar nav
 * @param bool   $legacy_table  If true, wraps content in a legacy table wrapper
 */
function settings_layout_start($menu, $active_page, $legacy_table = true) {
    // Make $active_page available to settings_nav.php as $sektion
    global $sektion, $sprog_id, $docubizz;
    $sektion = $active_page;

    // Determine if DocuBizz is enabled (needed by settings_nav.php)
    if (!isset($docubizz)) {
        $docubizz = NULL;
        if (db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr = '2' and box6='on'", __FILE__ . " linje " . __LINE__)))
            $docubizz = 'on';
    }

    // Inject settings CSS
    print "<link rel='stylesheet' type='text/css' href='../css/settings-modern.css'>\n";

    // Hide the system sidebar (from top.php) when in settings view
    if ($menu == 'S') {
        print "<script>document.getElementById('sidebar-base').style.display = 'none';</script>\n";
    }

    // --- Modern Settings Layout ---
    print "<div class='settings-page'>\n";
    print "<div class='settings-layout'>\n";

    // Sidebar navigation
    print "<div class='settings-sidebar'>\n";
    include(__DIR__ . '/settings_nav.php');
    print "</div>\n";

    // Content area
    print "<div class='settings-content'>\n";

    if ($legacy_table) {
        print "<div class='settings-legacy-wrapper'>\n";
    }
}

/**
 * Closes the modern settings layout containers.
 *
 * @param string $menu          Current menu mode ('T', 'S', or other)
 * @param bool   $legacy_table  Must match the value passed to settings_layout_start()
 */
function settings_layout_end($menu, $legacy_table = true) {
    if ($legacy_table) {
        print "</div><!-- end settings-legacy-wrapper -->\n";
    }

    print "</div><!-- end settings-content -->\n";
    print "</div><!-- end settings-layout -->\n";
    print "</div><!-- end settings-page -->\n";

    if ($menu == 'T') {
        print "</div><!-- end maincontentLargeHolder -->\n";
    }
?>
<?php if ($menu != 'T'): ?>
</td></tr></tbody></table>
<?php endif; ?>
</body>
</html>
<?php
}

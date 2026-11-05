<?php
// ---- systemdata/importer_varianter.php --- ver 5.1.0 --- 2026.11.02 ---
// LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261006 SS Settings 4c G5.3: CSV import of variant types and values, moved from the old Varianter page.
//             The files are read by the existing import in diverse.php (variant_valg_import_types/_values), which returns to Varer & lager » Varianter with the result.
// 20261101 CL/ASR Tilbage is the shared settings button above the title (settings_back_button(), styled by css/unified-components.css), as on every settings page, not the "← Varianter" link.
//                 It goes to the settings page the user came from (Varianter), or the hub; Alt+L kept.
// 20261101 CL/ASR Review: css/unified-components.css linked as ?v=20261101e, the version the section pages and the old pages' frame link.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops; settings_back_button() adds Alt+L itself.
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Køb & lager with Varianter marked (Varer), Tilbage above the title back to Varianter, the breadcrumb Indstillinger / Køb & lager / Varianter / this page.
//                 The two import forms sit in the content column beside the menu, unchanged; the layout links css/unified-components.css, the page's head links the theme tokens (css/saldi-theme.css) as the section pages.

@session_start();
$s_id = session_id();

$title = "Importér varianter";
$css = "../css/saldi-theme.css?v=7"; // the theme tokens, as the section pages; the layout links css/unified-components.css
$permission_key = 'settings.import_export';
$permission_level = 'write';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include_once("../includes/settings/layout.php"); // the standard settings layout: Tilbage, the title and the left menu with the page's row marked

if (function_exists('perm_can') && !perm_can('settings.import_export', 'write')) {
	print "<p style='padding:24px'>" . htmlspecialchars(findtekst('5665|Du har ikke adgang til nogen indstillinger. Kontakt en administrator.', $sprog_id), ENT_QUOTES) . "</p>";
	exit;
}
if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$h = function ($s) {
	return htmlspecialchars((string) $s, ENT_QUOTES);
};
$t = function ($s) use ($sprog_id, $h) {
	return $h(findtekst($s, $sprog_id));
};
$token = "<input type='hidden' name='csrf_token' value='" . $h($_SESSION['csrf_token']) . "'>";
// Tilbage, the title 'Importér varianter fra CSV' and the left menu of Køb & lager with Varianter marked; the forms in the content column.
settings_layout_open(array('legacy' => false));
?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
    <form class="st-card" style="padding:18px" enctype="multipart/form-data" action="diverse.php?sektion=variant_valg_import_types" method="post">
      <?= $token ?>
      <h2 style="font-size:16px;margin:0 0 6px"><?= $t('6845|Varianttyper') ?></h2>
      <p style="margin:0 0 10px"><?= $t('6846|Én variant pr. linje.') ?></p>
      <pre style="background:#f4f6fa;padding:8px 10px;border-radius:8px;margin:0 0 12px">Farve
Størrelse
Materiale</pre>
      <input type="hidden" name="MAX_FILE_SIZE" value="500000">
      <input type="file" name="variant_types_file" accept=".csv,.txt" required>
      <p style="margin:12px 0 0"><button type="submit" class="st-btn st-btn-primary"><?= $t('1356|Importér') ?></button></p>
    </form>
    <form class="st-card" style="padding:18px" enctype="multipart/form-data" action="diverse.php?sektion=variant_valg_import_values" method="post">
      <?= $token ?>
      <h2 style="font-size:16px;margin:0 0 6px"><?= $t('6829|Værdier') ?></h2>
      <p style="margin:0 0 10px"><?= $t('6847|Én værdi pr. linje: variant;værdi. Varianten skal findes i forvejen.') ?></p>
      <pre style="background:#f4f6fa;padding:8px 10px;border-radius:8px;margin:0 0 12px">Farve;Rød
Farve;Blå
Størrelse;Small</pre>
      <input type="hidden" name="MAX_FILE_SIZE" value="500000">
      <input type="file" name="variant_values_file" accept=".csv,.txt" required>
      <p style="margin:12px 0 0"><button type="submit" class="st-btn st-btn-primary"><?= $t('1356|Importér') ?></button></p>
    </form>
  </div>
<?php settings_layout_close(); ?>
</body></html>

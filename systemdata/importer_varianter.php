<?php
// ---- systemdata/importer_varianter.php --- lap 5.0.0 --- 2026.10.06 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261006 Sawaneh Settings 4c G5.3: CSV import of variant types and values, moved from the old Varianter page. The
//                  files are read by the existing import in diverse.php (variant_valg_import_types/_values), which
//                  returns to Varer & lager » Varianter with the result.

@session_start();
$s_id = session_id();

$title = "Importér varianter";
$css = "../css/unified-components.css?v=20261016";
$permission_key = 'settings.import_export';
$permission_level = 'write';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

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
$back = 'settingsSection.php?s=items.variants';
?>
<div class="st-page" style="max-width:860px;margin:0 auto;padding:24px 16px">
  <p><a href="<?= $h($back) ?>" accesskey="L">&larr; <?= $t('472|Varianter') ?></a></p>
  <h1 style="font-size:22px;margin:8px 0 18px"><?= $t('6839|Importér varianter fra CSV') ?></h1>
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
</div>
</body></html>

<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/crm.php --- ver 5.1.1 --- 2026.11.11 ---
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
// 20261020 ASR CRM redesign after prototype_crm.html v1.1: Opfølgning, Pipeline (now Sager), Kalender, Kunder and Aktivitet in one page plus the customer drawer.
//              Replaces crmkalender.php, crmopret.php, crmvisning.php and historikkort.php as the CRM entry (those pages are left in place).
//              The page renders the data as JSON; javascript/crm.js draws it and posts changes to crmApi.php.
// 20261109 ASR The page in the new design (prototype D) with the shared UI kit: css/saldi-theme.css, css/saldi-kit.css and then css/crm.css (only what the kit lacks).
//              One root, <div class="sk sk-page crm">, holds everything, the drawer, scrims, menu, dialog and toast too; the kit's classes stand next to the hook classes and ids crm.js uses.
//              The primary button is the kit's fixed navy, so the --btn/--btn-text style tag is gone.
//              Same layout, texts and behaviour as before.
// 20261109 ASR Review round 1: the toast is a polite live region (role status), and two class names without a rule are gone.
// 20261110 CDX/ASR Refresh CRM assets for lead creation and conversion before the first quote or order.
// 20261110 CDX/ASR Refresh the customer picker with lead creation during a new activity.
// 20261110 CDX/ASR Keep CRM header history continuations at sentence boundaries.
// 20261110 CDX/ASR Show a login notice until login migrations have prepared the CRM schema.
// 20261111 CDX/ASR Load the CRM design locally and enforce master permissions before showing data; add personal CRM notifications.

/**
 * Injected by ../includes/connect.php and ../includes/online.php:
 * @var int    $ansat_id
 * @var string $charset
 */

@session_start();
$s_id = session_id();

$title = "CRM";
$css = "../css/saldi-theme.css?v=8"; // the light/dark tokens; css/saldi-kit.css and css/crm.css are linked below
$modulnr = 0; // CRM checks read access explicitly below, including empty rights and an HTTP 403 response.

include(__DIR__ . "/../includes/connect.php");
ob_start();
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/crmFunctions.php");

$crmBootstrap = ob_get_clean();
if (!crm_can_read()) {
	http_response_code(403);
	echo $crmBootstrap;
	print '<p role="alert">' . crm_h('Du har ikke rettighed til at se CRM.') . '</p></body></html>';
	exit;
}
if (!crm_schema_ready()) {
	http_response_code(503);
	echo $crmBootstrap;
	print '<p role="alert">' . crm_h('CRM-opdateringen er ikke klar. Log ud og ind igen.') . '</p></body></html>';
	exit;
}
echo $crmBootstrap;
unset($crmBootstrap);
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// the drawer named in the query is loaded even when it is outside the loaded set (a customer just created; review R54)
$data = crm_dataset(isset($_GET['drawer']) && ctype_digit((string) $_GET['drawer']) ? (int) $_GET['drawer'] : 0);
$data['csrf'] = (string) $_SESSION['csrf_token'];
$data['api'] = 'crmApi.php';
?>
<link rel="stylesheet" href="../css/saldi-fonts.css?v=20261111">
<link rel="stylesheet" href="../css/saldi-kit.css?v=20261109">
<link rel="stylesheet" href="../css/crm.css?v=22">
<div class="sk sk-page crm" id="crm">
  <div class="crm-head">
    <div class="sk-tabs" role="tablist" id="tabs"></div>
    <div class="crm-tools">
      <label class="sk-search crm-q"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input class="sk-input" id="q" placeholder="<?= crm_h('Søg') ?>" aria-label="<?= crm_h('Søg') ?>"></label>
      <select class="sk-input" id="grpSel" aria-label="<?= crm_h('Statusgruppe') ?>"></select>
      <div class="sk-seg" id="scope" role="radiogroup" aria-label="<?= crm_h('Hvis opfølgninger og kunder') ?>">
        <button type="button" role="radio" data-scope="all" aria-checked="true" title="<?= crm_h('Alle opfølgninger og kunder i regnskabet') ?>"><?= crm_h('Alle') ?></button>
        <button type="button" role="radio" data-scope="mine" aria-checked="false" title="<?= crm_h('Kun dem, du er ansvarlig for. Valget huskes til næste gang.') ?>"><?= crm_h('Mine') ?></button>
      </div>
      <span class="sk-bar-sep"></span>
      <button type="button" class="sk-btn sk-btn-primary" id="newBtn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg><span id="newLbl"><?= crm_h('Ny opfølgning') ?></span></button>
      <button type="button" class="sk-btn sk-btn-icon crm-notifications-btn" id="notificationsBtn" aria-label="<?= crm_h('CRM-notifikationer') ?>" title="<?= crm_h('CRM-notifikationer') ?>" aria-haspopup="dialog" aria-expanded="false"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span class="crm-notifications-badge" id="notificationCount" aria-hidden="true" hidden></span></button>
      <div class="gear">
        <button type="button" class="sk-btn sk-btn-icon" id="gearBtn" aria-label="<?= crm_h('Tilpas CRM') ?>" title="<?= crm_h('Tilpas CRM') ?>" aria-expanded="false"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg></button>
        <div class="crm-gear" id="panel" role="dialog" aria-label="<?= crm_h('Tilpas CRM') ?>">
          <div class="crm-gear-t"><?= crm_h('Tilpas CRM') ?></div>
          <p class="crm-gear-n"><?= crm_h('Statusgrupper styrer filteret, statuslinjen under Kunder og tavlerne under Sager (én tavle pr. gruppe). Gælder hele firmaet.') ?></p>
          <h4 class="sk-form-sub"><?= crm_h('Statusser') ?></h4>
          <p class="crm-gear-n"><?= crm_h('Statusserne er de samme som feltet Status på debitorkortet. Rækkefølgen her er rækkefølgen på tavlen.') ?></p>
          <div id="stList"></div>
          <button type="button" class="sk-link crm-gear-add" id="stAdd"><?= crm_h('+ Tilføj status') ?></button>
          <h4 class="sk-form-sub"><?= crm_h('Statusgrupper') ?></h4>
          <p class="crm-gear-n"><?= crm_h('Hver gruppe bliver en tavle under Sager. Vundet → og Tabt → er den status, kunden får, når kortet trækkes til Vundet eller Tabt.') ?></p>
          <div id="sgList"></div>
          <button type="button" class="sk-link crm-gear-add" id="sgAdd"><?= crm_h('+ Tilføj statusgruppe') ?></button>
          <div class="crm-gear-f"><button type="button" id="sgCancel" class="sk-btn"><?= crm_h('Annullér') ?></button><button type="button" id="sgSave" class="sk-btn sk-btn-primary"><?= crm_h('Gem') ?></button></div>
        </div>
      </div>
    </div>
  </div>
  <div class="sk-note sk-info crm-note" id="noteBar" hidden></div>
  <div class="crm-view" id="v-follow"></div>
  <div class="crm-view" id="v-pipe" hidden></div>
  <div class="crm-view" id="v-cal" hidden></div>
  <div class="crm-view" id="v-cust" hidden></div>
  <div class="crm-view" id="v-act" hidden></div>
  <div class="sk-scrim" id="dscrim"></div>
  <aside class="sk-drawer drawer crm-drawer" id="drawer" aria-label="<?= crm_h('Kunde') ?>" role="dialog"></aside>
  <div class="sk-pop crm-menu" id="menu"></div>
  <div class="sk-modal" id="scrim"><div class="sk-dialog crm-dlg" id="dlg" role="dialog" aria-modal="true"></div></div>
  <div class="sk-toast crm-toast" id="toast" role="status" aria-live="polite"><span id="toastT"></span><button type="button" class="sk-link" id="toastX"></button><button type="button" class="sk-link" id="toastU"><?= crm_h('Fortryd') ?></button></div>
</div>
<script type="application/json" id="crm-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="../javascript/crm.js?v=29" charset="utf-8"></script>
</body></html>

<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/crm.php --- lap 5.0.0 --- 2026.10.20 ---
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
// 20261020 ASR CRM redesign after prototype_crm.html v1.1: Opfølgning, Pipeline (customers or deals), Kalender,
//              Kunder and Aktivitet in one page plus the customer drawer. Replaces crmkalender.php, crmopret.php,
//              crmvisning.php and historikkort.php as the CRM entry (those pages are left in place).
//              The page renders the data as JSON; javascript/crm.js draws it and posts changes to crmApi.php.

/**
 * Injected by ../includes/connect.php and ../includes/online.php:
 * @var int    $ansat_id
 * @var string $charset
 */

@session_start();
$s_id = session_id();

$title = "CRM";
$css = "../css/saldi-theme.css?v=7";
$modulnr = 6;
$permission_key = 'debitor.konti';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/crmFunctions.php");

if (!tbl_exists('crm_deals') || !crm_col_exists('historik', 'type') || !crm_col_exists('crm_deals', 'ordre_id') || !crm_col_exists('historik', 'completed_at')) {
	crm_ensure_schema(); // first use before the next login has run includes/betweenUpdates.php
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// the drawer named in the query is loaded even when it is outside the loaded set (a customer just created; review R54)
$data = crm_dataset(isset($_GET['drawer']) && ctype_digit((string) $_GET['drawer']) ? (int) $_GET['drawer'] : 0);
$data['csrf'] = (string) $_SESSION['csrf_token'];
$data['api'] = 'crmApi.php';
?>
<link rel="stylesheet" href="../css/crm.css?v=16">
<style>:root{--btn:<?= htmlspecialchars((string) $buttonColor, ENT_QUOTES) ?>;--btn-text:<?= htmlspecialchars((string) $buttonTxtColor, ENT_QUOTES) ?>}</style>
<div class="crm" id="crm">
  <div class="head">
    <div class="tabs" role="tablist" id="tabs"></div>
    <div class="tools">
      <label class="search"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input id="q" placeholder="<?= crm_h('Søg') ?>" aria-label="<?= crm_h('Søg') ?>"></label>
      <select class="ghost" id="grpSel" aria-label="<?= crm_h('Statusgruppe') ?>"></select>
      <div class="scope" id="scope" role="radiogroup" aria-label="<?= crm_h('Hvis opfølgninger og kunder') ?>">
        <button type="button" role="radio" data-scope="all" aria-checked="true" title="<?= crm_h('Alle opfølgninger og kunder i regnskabet') ?>"><?= crm_h('Alle') ?></button>
        <button type="button" role="radio" data-scope="mine" aria-checked="false" title="<?= crm_h('Kun dem, du er ansvarlig for. Valget huskes til næste gang.') ?>"><?= crm_h('Mine') ?></button>
      </div>
      <span class="vsep"></span>
      <button class="newbtn" id="newBtn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg><span id="newLbl"><?= crm_h('Ny opfølgning') ?></span></button>
      <div class="gear">
        <button class="gear-btn" id="gearBtn" aria-label="<?= crm_h('Tilpas CRM') ?>" title="<?= crm_h('Tilpas CRM') ?>" aria-expanded="false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg></button>
        <div class="panel" id="panel" role="dialog" aria-label="<?= crm_h('Tilpas CRM') ?>">
          <div class="ph"><?= crm_h('Tilpas CRM') ?></div>
          <div class="pi"><?= crm_h('Statusgrupper styrer filteret, statuslinjen under Kunder og tavlerne under Pipeline (én tavle pr. gruppe). Gælder hele firmaet.') ?></div>
          <h4><?= crm_h('Statusser') ?></h4>
          <div class="pi"><?= crm_h('Statusserne er de samme som feltet Status på debitorkortet. Rækkefølgen her er rækkefølgen på tavlen.') ?></div>
          <div id="stList"></div>
          <button type="button" class="paddl" id="stAdd"><?= crm_h('+ Tilføj status') ?></button>
          <h4><?= crm_h('Statusgrupper') ?></h4>
          <div class="pi"><?= crm_h('Hver gruppe bliver en tavle under Pipeline. Vundet → og Tabt → er den status, kunden får, når kortet trækkes til Vundet eller Tabt.') ?></div>
          <div id="sgList"></div>
          <button type="button" class="paddl" id="sgAdd"><?= crm_h('+ Tilføj statusgruppe') ?></button>
          <div class="pf"><button type="button" id="sgCancel" class="pbtn pbtn-ghost"><?= crm_h('Annullér') ?></button><button id="sgSave" class="pbtn"><?= crm_h('Gem') ?></button></div>
        </div>
      </div>
    </div>
  </div>
  <div class="note-bar" id="noteBar" hidden></div>
  <div class="view" id="v-follow"></div>
  <div class="view" id="v-pipe" hidden></div>
  <div class="view" id="v-cal" hidden></div>
  <div class="view" id="v-cust" hidden></div>
  <div class="view" id="v-act" hidden></div>
</div>
<div class="dscrim" id="dscrim"></div>
<aside class="drawer" id="drawer" aria-label="<?= crm_h('Kunde') ?>" role="dialog"></aside>
<div class="menu" id="menu"></div>
<div class="scrim" id="scrim"><div class="dlg" id="dlg" role="dialog" aria-modal="true"></div></div>
<div class="toast" id="toast"><span id="toastT"></span><button id="toastX"></button><button id="toastU"><?= crm_h('Fortryd') ?></button></div>
<script type="application/json" id="crm-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="../javascript/crm.js?v=20" charset="utf-8"></script>
</body></html>

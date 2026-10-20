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

if (!tbl_exists('crm_deals') || !crm_col_exists('historik', 'type')) {
	crm_ensure_schema(); // first use before the next login has run includes/betweenUpdates.php
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$data = crm_dataset();
$data['csrf'] = (string) $_SESSION['csrf_token'];
$data['api'] = 'crmApi.php';
?>
<link rel="stylesheet" href="../css/crm.css?v=5">
<style>:root{--btn:<?= htmlspecialchars((string) $buttonColor, ENT_QUOTES) ?>;--btn-text:<?= htmlspecialchars((string) $buttonTxtColor, ENT_QUOTES) ?>}</style>
<div class="crm" id="crm">
  <div class="head">
    <div class="tabs" role="tablist" id="tabs"></div>
    <div class="tools">
      <label class="search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input id="q" placeholder="Søg" aria-label="Søg"></label>
      <select class="ghost" id="grpSel" aria-label="Statusgruppe"></select>
      <button class="ghost" id="mine" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>Kun mine</button>
      <span class="vsep"></span>
      <button class="newbtn" id="newBtn" title="Ny opfølgning (N)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Ny opfølgning</button>
      <div class="gear">
        <button class="gear-btn" id="gearBtn" aria-label="Tilpas CRM" title="Tilpas CRM" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg></button>
        <div class="panel" id="panel" role="dialog" aria-label="Tilpas CRM">
          <div class="ph">Tilpas CRM</div>
          <div class="pi">Statusgrupper styrer filteret, statuslinjen under Kunder og tavlerne under Pipeline (én tavle pr. gruppe). Gælder hele firmaet.</div>
          <div id="sgList"></div>
          <div class="pf"><button id="sgAdd">+ Tilføj statusgruppe</button><button id="sgSave" class="pbtn">Gem</button></div>
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
<aside class="drawer" id="drawer" aria-label="Kunde" role="dialog"></aside>
<div class="menu" id="menu"></div>
<div class="scrim" id="scrim"><div class="dlg" id="dlg" role="dialog" aria-modal="true"></div></div>
<div class="toast" id="toast"><span id="toastT"></span><button id="toastX"></button><button id="toastU">Fortryd</button></div>
<script type="application/json" id="crm-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="../javascript/crm.js?v=4" charset="utf-8"></script>
</body></html>

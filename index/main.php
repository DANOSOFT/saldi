<!doctype html>
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/main.php --- ver 5.0.0 --- 2026.10.08 ---
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
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 17042024 MMK - Added suport for reloading page, and keeping current URI, DELETED old system that didnt work
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()
// 20260630 CDX/NTR Fixed Lager/varer from refreshing once every time we try to access it.
//                  This possibly has changes across everything, but I have tested it, and it has also fixed rendering prematurely.
// 20260716 MJ      Tilfoejede Momsperioder-link i Finans-sidebar.
// 20260730 NTR - Added translation to momsperioder.
// 20260730 MJ Fjernede Momsperioder-link fra Finans-sidebaren; linket er nu en knap i regnskabsaar.php
// 20260902 CL/LH Indlejrede chaty_V2 support-chatbot (wuweiworkai.com/chaty-v2) i skallen
// 20260904 Sawaneh WP-1.6: update_iframe() tags iframe navigations with inframe=1 (context flag for hosted pages)
// 20260907 CDX/LH Fjernede gammel widget-loader, saa SALDI Assist kun indlaeses en gang
// 20260907 CDX/LH Preserve iframe navigation while merging the current shell integration.
// 20260907 CDX/LH Enable the saved-record bridge when the installation opts in.
// 20260910 Sawaneh JOB-128: hash sync raced its setTimeout(0) guard, so a page rendered on a POST
//                 response (kreditor split view, bare ordre.php URL) got reloaded from the hash as
//                 ordre.php?inframe=1 = empty new order. Track the shell-written hash explicitly and
//                 ignore the inframe flag when deciding whether the iframe already shows the target.
// 20260914 CDX/LH Removed the Guides sidebar entry and its popup.
// 20260930 LOE SST-836 Added the expiry report to the Lager menu where batch/expiry handling is in use.
// 20261002 CL/LH Added the "Opgaver" inbox entry with its open-count badge, the Saldi Assist settings link,
//                the approval modal wiring (saldi-inbox.js + CSRF/tenant config) and data-assistant="v2"
//                behind SALDI_ASSIST_V2. All of it only for tenant users (bruger_id > 0).
// 20261002 CL/LH Load the quiet "Vigtigt" banner for unseen interrupt items (javascript/saldi-assist-banner.js).
// 20261007 CL/LH Approval modal v2: texts for Afvis, the note and the key hints; cache-busting for the modal assets.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.
// 20261008 CL/LH Add the same-origin Assist upload hook.
@session_start();
$s_id = session_id();

$css = "../css/sidebar_style.css?v=20";

include("../includes/connect.php");
include("../includes/license_func.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/stdFunc/dkDecimal.php");

function check_permissions($permarr)
{
  global $rettigheder;
  $filtered = array_filter($permarr, function ($item) use ($rettigheder) {
    return (substr($rettigheder, $item, 1) == "1");
  });
  return !empty($filtered);
}

if (substr($brugernavn, 0, 11) == "debitoripad") {
  header('Location: ../debitoripad/await.php');
}

function brightenColor($color, $amount = 0.2) {
    // Remove # if present
    $color = ltrim($color, '#');
    
    // Convert hex to RGB
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    // Brighten each component
    $r = min(255, $r + ($amount * (255 - $r)));
    $g = min(255, $g + ($amount * (255 - $g)));
    $b = min(255, $b + ($amount * (255 - $b)));
    
    // Convert back to hex
    return '#' . sprintf('%02x%02x%02x', round($r), round($g), round($b));
}

/**
 * Inbox wiring for the shell, only for tenant users (revisor/admin sessions keep the shell unchanged).
 * Best effort: the badge is computed only when the inbox tables exist and any failure leaves it out.
 * Injected by ../includes/online.php, included above:
 * @var int|string $bruger_id
 */
$saldiInboxUser = (int)($bruger_id ?? 0) > 0;
$saldiInbox = null;
if ($saldiInboxUser) {
  try {
    require_once __DIR__ . '/../includes/automation/SaldiInboxService.php';
    require_once __DIR__ . '/../includes/automation/inboxCsrf.php';
    require_once __DIR__ . '/../includes/automation/approvalCard.php';
    $saldiInboxOpen = saldi_runtime_context_for_session();
    $saldiInboxDb = $saldiInboxOpen['db'];
    $saldiInboxCount = null;
    if ($saldiInboxDb->hasTable('inbox_items') && $saldiInboxDb->hasTable('decision_log')) {
      $saldiInboxCount = (new SaldiInboxService($saldiInboxDb, SaldiKindRegistry::default()))->countOpen($saldiInboxOpen['ctx']);
    }
    $saldiInboxLang = $saldiInboxOpen['ctx']->languageId;
    $saldiInboxText = static fn(int $id, string $fallback): string => saldi_ui_text($saldiInboxDb, $saldiInboxLang, $id, $fallback);
    $saldiInbox = [
      'count' => $saldiInboxCount,
      'config' => [
        'csrf' => saldi_inbox_csrf_token(),
        'tenantHash' => $saldiInboxOpen['ctx']->tenantHash(),
        'accent' => preg_match('/^#[0-9a-fA-F]{3,8}$/D', (string)$buttonColor) ? $buttonColor : '#114691',
        'accentText' => preg_match('/^#[0-9a-fA-F]{3,8}$/D', (string)$buttonTxtColor) ? $buttonTxtColor : '#ffffff',
        'texts' => [
          'approveTitle' => $saldiInboxText(5829, 'Godkend forslag'),
          'approve' => $saldiInboxText(5726, 'Godkend'),
          'approved' => $saldiInboxText(5823, 'Godkendt'),
          'cancel' => $saldiInboxText(5874, 'Annuller'),
          'close' => $saldiInboxText(2172, 'Luk'),
          'loading' => $saldiInboxText(5875, 'Henter forslaget …'),
          'busy' => $saldiInboxText(5876, 'Der er allerede en godkendelse åben.'),
          'cancelled' => $saldiInboxText(5877, 'Godkendelsen blev annulleret.'),
          'nothing' => $saldiInboxText(5822, 'Intet er ændret.'),
          'unavailable' => $saldiInboxText(5878, 'Opgaver er midlertidigt utilgængelige. Prøv igen om lidt.'),
          'companyChanged' => $saldiInboxText(5883, 'Du har skiftet regnskab i et andet vindue. Genindlæs siden.'),
          'alreadyHandled' => $saldiInboxText(5884, 'Forslaget er allerede behandlet eller ændret, siden du åbnede det.'),
          'openInInbox' => $saldiInboxText(5873, 'Vis opgaven'),
          'openCount' => $saldiInboxText(5872, '{n} åbne'),
          'invalid' => $saldiInboxText(5732, 'Kontrollér de indtastede oplysninger.'),
          'reject' => $saldiInboxText(5727, 'Afvis'),
          'rejected' => $saldiInboxText(5803, 'Afvist'),
          'note' => $saldiInboxText(5820, 'Tilføj en note'),
          'noteLabel' => $saldiInboxText(5895, 'Note til beslutningen (valgfri)'),
          'keyApprove' => $saldiInboxText(6315, 'godkend'),
          'keyReject' => $saldiInboxText(6316, 'afvis'),
          'keyCancel' => $saldiInboxText(6326, 'annuller'),
          'decidedTitle' => $saldiInboxText(6329, 'Afgjort forslag'),
          'approving' => $saldiInboxText(6327, 'Godkender …'),
          'rejecting' => $saldiInboxText(6328, 'Afviser …'),
        ],
      ],
    ];
  } catch (Throwable $e) {
    error_log('index/main.php inbox wiring: ' . get_class($e) . ' ' . $e->getMessage());
    $saldiInbox = null;
  }
}
$saldiInboxBadge = (int)($saldiInbox['count'] ?? 0);
$saldiInboxBadgeLabel = $saldiInboxBadge > 0 ? str_replace('{n}', (string)$saldiInboxBadge, findtekst('5872|{n} åbne', $sprog_id)) : '';

?>

<script>
  // Simple cookie-based refresh listener
  function checkRefreshCookie() {
      const cookies = document.cookie.split(';');
      for (let cookie of cookies) {
          const [name, value] = cookie.trim().split('=');
          if (name === 'refresh_opener' && value === 'true') {
              // Clear the cookie and reload
              document.cookie = 'refresh_opener=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
              location.reload();
              return;
          }
      }
  }

  // Check every 1000ms for the cookie
  setInterval(checkRefreshCookie, 1000);
</script>
<style>
  .showMenu{
    background: <?php echo $buttonColor; ?> !important;
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .nav-links{
    background-color: <?php echo $buttonColor; ?> !important;
    color: <?php echo $buttonTxtColor; ?> !important;
  }
  .sidebar .nav-links li:hover, .sidebar :not(.closed) .nav-links li.showMenu, .sidebar ul.nav-links li.active {
    background: <?php echo brightenColor($buttonColor, 0.2); ?> !important;
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .sidebar{
    background-color: <?php echo $buttonColor; ?> !important;
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .link_name{
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .sidebar a, .sidebar p{
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .sidebar .bx{
    color: <?php echo $buttonTxtColor; ?> !important;
  }

  .logo-img {
    width: 120px;
    height: 40px; /* ← REQUIRED */

    background-color: <?php echo $buttonTxtColor; ?> !important;

    -webkit-mask: url("../img/sidebar_logo.png") no-repeat center;
    mask: url("../img/sidebar_logo.png") no-repeat center;
    -webkit-mask-size: contain;
    mask-size: contain;
  }

  .sidebar:not(.closed) .nav-links li .sub-menu li a::before{
    background: <?php echo $buttonTxtColor; ?> !important;
  }
</style>

<meta charset="utf-8">
<title>Sidebar</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="icon" href="../img/saldiLogo.png">
<link href='../css/sidebar_style.css?v=22' rel='stylesheet'>
<?php if ($saldiInboxUser) { ?>
<link href='../css/approval_card.css?v=20261007' rel='stylesheet'>
<style>
  .sidebar .nav-links li a .saldi-inbox-badge {
    margin: 0 10px 0 auto;
    min-width: 20px;
    padding: 0 6px;
    border-radius: 10px;
    background: <?php echo $buttonTxtColor; ?>;
    color: <?php echo $buttonColor; ?>;
    font-size: 12px;
    font-weight: bold;
    line-height: 20px;
    text-align: center;
  }
  .sidebar .nav-links li a .saldi-inbox-badge[hidden] {
    display: none;
  }
  .sidebar.closed .nav-links li a .saldi-inbox-badge {
    position: absolute;
    top: 2px;
    left: 28px;
    min-width: 16px;
    margin: 0;
    padding: 0 4px;
    font-size: 10px;
    line-height: 16px;
  }
  .saldi-inbox-sr {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
  }
</style>
<?php } ?>
<meta name="viewport" content="width=device-width, initial-scale=0.8">

<div class="modalbg" onclick="
    document.getElementsByClassName('sidebar')[0].style.width=''; 
    document.getElementsByClassName('modalbg')[0].style.display='none'; 
  "></div>
<div class="sidebar">

  <div class="logo wide">
    <div class="logo-img"></div>
    <i id="icon-open" class="bx bxs-arrow-from-right"></i>
  </div>

  <div class="logo small" onclick="
      document.getElementsByClassName('sidebar')[0].style.width=''; 
      document.getElementsByClassName('modalbg')[0].style.display='none'; 
    ">
    <img class="logo-img" src="../img/sidebar_logo.png">
    <i id="icon-open" class='bx bxs-arrow-from-right'></i>
  </div>

  <ul class="nav-links top-links" style='margin-top: 1em; background: <?php echo $buttonColor; ?> !important; color: <?php echo $buttonTxtColor; ?> !important;'>
    <!-- Finans -->
    <li class="active">
      <a href="#" id="dashboard" style="background: <?php echo $buttonColor; ?> !important; color: <?php echo $buttonTxtColor; ?> !important;" onclick='clear_sidebar(); this.parentElement.classList.add("active"); update_iframe("/index/dashboard.php")'>
        <i class='bx bxs-dashboard'></i>
        <span class="link_name"><?php print findtekst('2224|Oversigt', $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick='clear_sidebar(); update_iframe("/index/dashboard.php")'><?php print findtekst('2224|Oversigt', $sprog_id); ?></a></li>
      </ul>
    </li>

    <?php if ($saldiInboxUser) { ?>
    <!-- Opgaver (automation inbox) -->
    <li id="saldi-inbox-entry">
      <a href="#" style="background: <?php echo $buttonColor; ?> !important; color: <?php echo $buttonTxtColor; ?> !important;" onclick='clear_sidebar(); this.parentElement.classList.add("active"); update_iframe("/inbox/index.php")'>
        <i class='bx bx-task'></i>
        <span class="link_name"><?php print findtekst('5870|Opgaver', $sprog_id); ?><span class="saldi-inbox-sr" data-saldi-inbox-badge-label><?php echo $saldiInboxBadgeLabel !== '' ? htmlspecialchars(' (' . $saldiInboxBadgeLabel . ')', ENT_QUOTES, 'UTF-8') : ''; ?></span></span>
        <span class="saldi-inbox-badge" data-saldi-inbox-badge aria-hidden="true" title="<?php echo htmlspecialchars($saldiInboxBadgeLabel, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $saldiInboxBadge > 0 ? '' : ' hidden'; ?>><?php echo $saldiInboxBadge > 99 ? '99+' : $saldiInboxBadge; ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick='clear_sidebar(); document.getElementById("saldi-inbox-entry").classList.add("active"); update_iframe("/inbox/index.php")'><?php print findtekst('5870|Opgaver', $sprog_id); ?></a></li>
      </ul>
    </li>
    <?php } ?>

    <li style="display: <?php if (check_permissions(array(2, 3, 4))) {
                          echo 'block';
                        } else {
                          echo 'none';
                        } ?>">
      <div class="icon_link" id="finans">
        <a href="#">
          <i class='bx bx-coin-stack'></i>
          <span class="link_name"><?php print findtekst('600|Finans', $sprog_id); ?></span>
        </a>
        <i class='bx bxs-chevron-down arrow'> </i>
      </div>
      <ul class="sub-menu">
        <li><span class="link_name"><?php print findtekst('600|Finans', $sprog_id); ?></span></li>
        <?php
        if (check_permissions(array(2))) {
          echo '<li><a href="#" id="kladdeliste" onclick=\'update_iframe("/finans/kladdeliste.php")\'>' . findtekst('601|Kassekladde', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(3))) {
          echo '<li><a href="#" id="regnskab" onclick=\'update_iframe("/finans/regnskab.php")\'>' . findtekst('602|Regnskab', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(4))) {
          echo '<li><a href="#" id="rapport" onclick=\'update_iframe("/finans/rapport.php")\'>' . findtekst('603|Rapporter', $sprog_id) . '</a></li>';
        }
        ?>
      </ul>
    </li>

    <!-- Debitor -->
    <li style="display: <?php if (check_permissions(array(5, 6, 12))) {
                          echo 'block';
                        } else {
                          echo 'none';
                        } ?>">
      <div class="icon_link" id="debitor">
        <a href="#">
          <i class='bx bx-group'></i>
          <span class="link_name"><?php print findtekst('604|Debitor', $sprog_id); ?></span>
        </a>
        <i class='bx bxs-chevron-down arrow'> </i>
      </div>
      <ul class="sub-menu">
        <li><span class="link_name"><?php print findtekst('604|Debitor', $sprog_id); ?></span></li>
        <?php
        if (check_permissions(array(5))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/ordreliste.php?menu_entry=1&reset_context=1&valg=ordrer")\'>' . findtekst('605|Ordre', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(6))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/debitor.php")\'>' . findtekst('606|Konti', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(12))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/rapport.php")\'>' . findtekst('603|Rapporter', $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(6))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/crmkalender.php")\'>CRM</a></li>';
        }
        ?>
      </ul>
    </li>
    <!-- Booking -->
    <?php
    if (is_feature_licensed('booking')) {
    ?>
      <li style="display: <?php if (check_permissions(array(6))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link">
          <a href="#">
            <i class='bx bx-calendar'></i>
            <span class="link_name"><?php print findtekst('1116|Booking', $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst('1116|Booking', $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(6))) {
            echo '<li><a href="#" onclick=\'update_iframe("/rental/index.php?vare")\'>' . findtekst('2137|Udlejningsoversigt', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/index.php")\'>' . findtekst('2138|Daglig oversigt', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/settings.php")\'>' . findtekst('122|Indstillinger', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/daysoff.php")\'>' . findtekst('2140|Lukkedage', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/items.php")\'>' . findtekst('2141|Udlejningsvare', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/remote.php")\'>' . findtekst('2143|Ekstern booking', $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/lookupcust.php")\'>' . findtekst('2142|Søg kundehistorik', $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      <?php } ?>
      <!-- Kreditor -->
      <?php if (is_feature_licensed('kreditor')) { ?>
      <li style="display: <?php if (check_permissions(array(7, 8, 13))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link" id="kreditor">
          <a href="#">
            <i class='bx bx-archive-out'></i>
            <span class="link_name"><?php print findtekst('607|Kreditorer', $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst('607|Kreditorer', $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(7))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/ordreliste.php")\'>' . findtekst('605|Ordre', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(8))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/kreditor.php")\'>' . findtekst('606|Konti', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(13))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/rapport.php")\'>' . findtekst('603|Rapporter', $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      </li>
      <?php } ?>

      <!-- Lager -->
      <?php if (is_feature_licensed('lager')) { ?>
      <li style="display: <?php if (check_permissions(array(9, 10, 15))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link" id="lager">
          <a href="#">
            <i class='bx bx-package'></i>
            <span class="link_name"><?php print findtekst('608|Lager', $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst('608|Lager', $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(9))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/varer.php")\'>' . findtekst('609|Varer', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(10))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/modtageliste.php")\'>' . findtekst('610|Varemodtagelse', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(15))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/rapport.php")\'>' . findtekst('603|Rapporter', $sprog_id) . '</a></li>';
          }
          # The expiry report had no menu entry at all; only accounts that use batch/expiry
          # management see it, and only with the right the page itself requires.
          if (check_permissions(array(12)) && batch_expiry_in_use()) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/udlobsrapport.php")\'>' . findtekst('5014|Udl&oslash;bsrapport', $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      </li>
      <?php } ?>

      <!-- System -->
      <li style="display: <?php if (check_permissions(array(0, 1, 11))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link" id="system">
          <a href="#">
            <i class='bx bx-cog'></i>
            <span class="link_name"><?php print findtekst('2377|System', $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name">System</span></li>
          <?php
          if (check_permissions(array(0))) {
            echo '<li><a href="#" onclick=\'update_iframe("/systemdata/kontoplan.php")\'>' . findtekst('612|Kontoplan', $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(1))) {
            echo '<li><a href="#" onclick=\'update_iframe("/systemdata/syssetup.php")\'>' . findtekst('122|Indstillinger', $sprog_id) . '</a></li>';

            # Kassesystem eller ej
            $qtxt = "SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'";
            $state = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
            if ($state) {
              print "<li><a href=\"#\" onclick='update_iframe(\"/systemdata/posmenuer.php\")'>" . findtekst('1940|POS-menuer', $sprog_id) . "</a></li>";
            }
          }
          if (check_permissions(array(11))) {
            $restoreRaw = findtekst('1903|Gendan brugers IP', $sprog_id);
            $restore1 = explode(" ", $restoreRaw);
            $restore = $restore1[0];
            echo '<li><a href="#" onclick=\'update_iframe("/admin/backup.php")\'>' . findtekst('614|Sikkerhedskopi', $sprog_id) . '/'.$restore.'</a></li>';
          }
          ?>
        </ul>
      </li>
  </ul>

  <ul class="nav-links">
    <?php if ($saldiInboxUser) { ?>
    <li>
      <a href="#" onclick='clear_sidebar(); update_iframe("/systemdata/assistent.php")'>
        <i class='bx bx-slider-alt'></i>
        <span class="link_name"><?php print findtekst('5871|Assist-indstillinger', $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick='clear_sidebar(); update_iframe("/systemdata/assistent.php")'><?php print findtekst('5871|Assist-indstillinger', $sprog_id); ?></a></li>
      </ul>
    </li>
    <?php } ?>
    <li>
      <a href="#" onclick="alert('Kontakt os på tlf: 46 90 22 08 mail: support@saldi.dk')">
        <i class='bx bx-envelope'></i>
        <span class="link_name"><?php print findtekst('398|Kontakt', $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick="alert('Kontakt os på tlf: 46 90 22 08 mail: support@saldi.dk')">Kontakt</a>
        </li>
      </ul>
    </li>

    <li>
      <a href="#" onclick='redirect_uri("/index/logud.php")'>
        <i class='bx bx-log-out'></i>
        <span class="link_name"><?php print findtekst('93|Log ud', $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick='redirect_uri("/index/logud.php")'><?php print findtekst('93|Log ud', $sprog_id); ?></a>
        </li>
      </ul>
    </li>

  </ul>

  <div id="desc-line">
    <a href="#" onclick="window.frames['iframe_a'].focus();
                           window.frames['iframe_a'].print();">Print</a>
    <p title="DB nummer <?php print $db; ?>">Saldi version <?php print $version; ?></p>
  </div>
</div>

<section class="home-section">
  <div class="topbar">
    <a href="javascript:void(0)" onclick="document.getElementsByClassName('sidebar')[0].setAttribute(`style`, `width: 210px !important; height: ${window.screen.availHeight+1}px`); document.getElementsByClassName('modalbg')[0].style.display='block'; "><i class='bx bx-menu' style="color: white; font-size: 50px"></i></a>
  </div>

  <div class="home-content">
    <iframe
      onLoad="
      document.title = 'Saldi - ' + this.contentWindow.document.title; 
      console.log('Locaiton', this.contentWindow.document.location.href);
      trigger_iframe_load();
      stopLoading();
      content_finished_loading(this);"
      id="iframe_a" src="-"
      name="iframe_a"
      title="Site"
      class="content-iframe"></iframe>
  </div>
  <div id="loadingBar">
    <div></div>
  </div>
</section>

<script>
  function setCookie(cname, cvalue, exdays) {
    console.log(cname, cvalue);
    const d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue;
  }

  function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
  }

  let arrow = document.querySelectorAll(".icon_link");

  for (var i = 0; i < arrow.length; i++) {
    arrow[i].addEventListener("click", (e) => {
      let arrowParent = e.target.parentElement;
      console.log(e);
      arrowParent.classList.toggle("showMenu");
    });
  }

  let sidebar = document.querySelector(".sidebar");
  let sidebarBtn = document.querySelector(".logo.wide");
  sidebarBtn.addEventListener("click", () => {
    sidebar.classList.toggle("closed");
    document.cookie = `isSidebarOpen=${sidebar.classList.contains("closed")}`
  });

  console.log(getCookie("isSidebarOpen"));
  if (getCookie("isSidebarOpen") === "true") {
    sidebar.classList.toggle("closed");
  }

  const get_iframe_path = () => {
    const iframe = document.querySelector(".content-iframe")
    if (!iframe || !iframe.contentWindow) return "";

    try {
      const url = new URL(iframe.contentWindow.location.href);
      return url.pathname + url.search;
    } catch (e) {
      return "";
    }
  }

  // Compare shell paths without the inframe flag: a page reached by an in-frame
  // redirect (ordre.php?id=X) has no inframe=1 yet still is the requested page.
  const strip_inframe = (path) => {
    try {
      const url = new URL(path, location.origin);
      url.searchParams.delete('inframe');
      return url.pathname + url.search;
    } catch (e) {
      return path;
    }
  }

  const update_iframe = (uri) => {
    const iframe = document.querySelector(".content-iframe")
    const baseUrl = (location + "").split("/").splice(0, 4).join("/");
    const targetUrl = baseUrl + (uri.startsWith("/") ? uri : "/" + uri);
    const parsedTargetUrl = new URL(targetUrl);
    // Context flag for the loaded page: it runs inside the shell's iframe, so
    // window.close()-based flows (luk.php) can't work and back targets must stay
    // in-frame. Set centrally here instead of on every menu link.
    if (!parsedTargetUrl.searchParams.has('inframe')) {
      parsedTargetUrl.searchParams.set('inframe', '1');
    }
    const targetPath = parsedTargetUrl.pathname + parsedTargetUrl.search;

    if (strip_inframe(get_iframe_path()) === strip_inframe(targetPath)) {
      return;
    }

    if (iframe.contentWindow?.docChange) {
      if (!window.confirm("Er du sikker på du gerne vil ændre side? Dine ændringer vil ikke blive gemt")) {
        return;
      }
    }

    iframe.src = parsedTargetUrl.href
  }

  const redirect_uri = (uri) => {
    window.location = (location + "").split("/").splice(0, 4).join("/") + uri
  }

  // Check for page reloads and manage inital load of iframe
  update_iframe(window.location.hash == "" ? "/index/dashboard.php" : window.location.hash.replace("#", ""));

  // Hash the shell wrote itself from an iframe load. hashchange is dispatched
  // asynchronously, so a timer-based flag could reset before the event arrived
  // and the shell would then reload the iframe from the hash - fatal for pages
  // rendered straight on a POST response (e.g. kreditor split view), whose URL
  // carries no id and reloads as an empty form.
  let shellWrittenHash = null;
  addEventListener("hashchange", (event) => {
    const newHash = event.newURL.split("#")[1];
    if (shellWrittenHash !== null && newHash === shellWrittenHash) {
      shellWrittenHash = null;
      return;
    }
    if (newHash && newHash !== "/") {
      update_iframe(newHash);
    }
  });

  function trigger_iframe_load() {
    const iframe = document.querySelector(".content-iframe");
    const path = "/" + iframe.contentWindow.document.location.href.split("/").slice(4).join("/");

    if (window.location.hash !== "#" + path) {
      shellWrittenHash = path;
      window.location.hash = path;
    }

    setCookie('last-sidebar-location', path, 1);
  }

  document.addEventListener('DOMContentLoaded', function() {
    const refs = document.querySelectorAll(".sidebar ul.nav-links li ul.sub-menu li a");
    for (let i = 0; i < refs.length; i++) {
      refs[i].addEventListener('click', function() {
        clear_sidebar();
        this.classList.toggle('active');
      });
    }
  });

  function clear_sidebar() {
    const refs = document.querySelectorAll(".sidebar ul.nav-links li ul.sub-menu li a, ul.nav-links li");
    for (let i = 0; i < refs.length; i++) {
      refs[i].classList.remove('active');
    }
  }

  function startLoading() {
    var loadingBar = document.getElementById('loadingBar');
    loadingBar.style.display = 'block'; // Show the loading bar
  }

  function stopLoading() {
    var loadingBar = document.getElementById('loadingBar');
    loadingBar.style.display = 'none'; // Hide the loading bar
  }

  var content_finished_loading = function(iframe) {
    // inject the start loading handler when content finished loading
    iframe.contentWindow.onbeforeunload = startLoading;
  };

</script>

<style>
  /* The loading bar container */
  #loadingBar {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background-color: #f3f3f3;
    z-index: 9999;
    display: none;
    /* Initially hidden */
    overflow: hidden;
  }

  /* The loading bar itself, with cool back-and-forth animation */
  #loadingBar div {
    position: fixed;
    height: 100%;
    width: 0;
    background-color: #4caf50;
    animation: loadingAnimation 2s infinite ease-in-out;
  }

  /* Keyframes for back-and-forth animation */
  @keyframes loadingAnimation {
    0% {
      width: 0;
      left: 0;
    }

    50% {
      width: 100%;
      left: 0;
    }

    100% {
      width: 0;
      left: 100%;
    }
  }
</style>

<?php
/* SALDI Assist (support-chatbot). Loaderen hentes fra chatbottens server; token-
   endpointet ligger i includes/saldi_assist_token.php. SALDI_ASSIST_WIDGET_URL
   kan saettes i webserverens miljoe til en test-instans; standard er produktion. */
$assistWidgetUrl = getenv('SALDI_ASSIST_WIDGET_URL') ?: 'https://wuweiworkai.com/chaty-v2/widget.js';
$assistVersion = isset($version) ? (string)$version : '';
require_once __DIR__ . '/../includes/automation/inboxCsrf.php';
$assistConsentCsrf = saldi_inbox_csrf_token();
?>
<script src="../javascript/saldi-assist-navigate.js"></script>
<script>
  // update_iframe er en const i sidens script; goer den tilgaengelig for
  // navigate-hook'en, saa "Gaa dertil" gaar gennem SALDIs egen navigation
  // (inkl. advarslen om ugemte aendringer).
  if (typeof update_iframe === 'function') { window.update_iframe = update_iframe; }
</script>
<script src="<?= htmlspecialchars($assistWidgetUrl, ENT_QUOTES, 'UTF-8') ?>" data-widget-id="saldi" data-brand="SALDI" data-lang="da" data-app-version="<?= htmlspecialchars($assistVersion, ENT_QUOTES, 'UTF-8') ?>"<?= getenv('SALDI_ASSIST_V2') === '1' ? ' data-assistant="v2"' : '' ?> defer></script>
<script>window.SaldiAssist = { appVersion: <?= json_encode($assistVersion) ?>, correlationId: <?= json_encode($assist_correlation_id ?? null) ?>, errorCategory: <?= json_encode($assist_error_category ?? null) ?>, getContextToken: function (sessionHash) { return fetch('../includes/saldi_assist_token.php?embed_session=' + encodeURIComponent(sessionHash), {credentials:'same-origin'}).then(function (r) { return r.ok ? r.json() : null }).then(function (j) { return j && j.token ? j.token : null }) }, navigate: window.SaldiAssistNavigate };</script>
<script>
  (function () {
    const csrf = <?= json_encode($assistConsentCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function consentRequest(method, value) {
      const options = {method: method, credentials: 'same-origin', headers: {'X-Saldi-Csrf': csrf}};
      if (method === 'POST') {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify({consent: value});
      }
      return fetch('../includes/saldi_assist_consent.php', options).then(function (response) {
        if (!response.ok) { throw new Error('consent_unavailable'); }
        return response.json();
      });
    }
    window.SaldiAssist.upload = function (files) {
      const body = new FormData();
      files.forEach(function (file) { body.append('file[]', file); });
      body.append('purpose', 'chat');
      return fetch('../includes/saldi_assist_upload.php', {method: 'POST', credentials: 'same-origin', headers: {'X-Saldi-Csrf': csrf}, body: body}).then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) {
            const error = new Error(data.error || 'upload_unavailable');
            error.code = data.error;
            error.details = data.details;
            throw error;
          }
          return data.attachments;
        });
      });
    };
    window.SaldiAssist.getConsent = function () {
      return consentRequest('GET').then(function (data) { return data.consent; });
    };
    window.SaldiAssist.setConsent = function (value) {
      if (value !== 'yes' && value !== 'no') { return Promise.reject(new Error('invalid_consent')); }
      return consentRequest('POST', value).then(function () {});
    };
  })();
</script>
<?php if ($saldiInbox !== null) { ?>
<script type="application/json" id="saldi-inbox-config"><?= json_encode($saldiInbox['config'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="../javascript/saldi-inbox.js?v=20261007"></script>
<script src="../javascript/saldi-assist-banner.js?v=20261007" defer></script>
<?php } ?>
<?php if (getenv('SALDI_ASSIST_RECORDS_ENABLED') === '1') { ?>
<script src="../javascript/saldi-assist-records.js"></script>
<script>
  window.SaldiAssist.getRecordContext = window.SaldiAssistRecords.getRecordContext;
  window.SaldiAssist.highlightRows = window.SaldiAssistRecords.highlightRows;
</script>
<?php } ?>
</html>

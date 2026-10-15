<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/bilag_paamindelse.php --- ver 5.0.0 --- 2026.10.01
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
/**
 * Injected by includes/online.php, included below:
 * @var string $db
 * @var int $db_id
 * @var int $bruger_id
 * @var string $rettigheder
 * @var string $revisor
 * @var string $menu
 * @var int $sprog_id
 * @var mixed $connection
 */
session_start();
$s_id = session_id();
$title = 'Bilagspåmindelser';
$modulnr = 2;
$css = '../css/standard.css';
ob_start();
require_once __DIR__ . '/../includes/var_def.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/online.php';
require_once __DIR__ . '/../includes/topline_settings.php';
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/../includes/mobileUpdates.php';
require_once __DIR__ . '/../restapi/core/Rights.php';
require_once __DIR__ . '/../restapi/services/ReminderService.php';

$registryLink = MobileAuth::connectRegistry();
mobileRegistryUpdates($registryLink);
$registry = new MobileSchema($registryLink);
$session = $registry->literal($s_id);
$online = $registry->rows("SELECT brugernavn,db,revisor FROM online WHERE session_id=$session ORDER BY logtime DESC LIMIT 1");
if (!$online || $online[0]['db'] !== $db) {
    ob_end_clean();
    http_response_code(403);
    exit;
}
$isRevisor = MobileAuth::enabled($online[0]['revisor']);
$tenantLink = $connection;
if (!$isRevisor) {
    $tenantStore = new MobileSchema($tenantLink);
    $username = $tenantStore->literal($online[0]['brugernavn']);
    $currentUser = $tenantStore->rows("SELECT id,rettigheder FROM brugere WHERE brugernavn=$username");
    if (count($currentUser) !== 1) {
        ob_end_clean();
        http_response_code(403);
        exit;
    }
    $bruger_id = (int)$currentUser[0]['id'];
    $rettigheder = $currentUser[0]['rettigheder'];
}
if (!Rights::allows((string)$rettigheder, 'bilag', 'read', null, $isRevisor)) {
    ob_end_clean();
    http_response_code(403);
    exit;
}
$canSend = Rights::allows((string)$rettigheder, 'bilag', 'write', null, $isRevisor);
mobileTenantUpdates($tenantLink);
$service = new ReminderService($tenantLink, $registryLink, (int)$db_id);
if ($isRevisor) {
    $username = $registry->literal($online[0]['brugernavn']);
    $user = $registry->rows("SELECT id FROM brugere WHERE brugernavn=$username");
    if (count($user) !== 1) {
        ob_end_clean();
        http_response_code(403);
        exit;
    }
    $sender = Subject::build('registry', (int)$user[0]['id']);
} else {
    $sender = Subject::build('account', (int)$bruger_id);
}
$csrf = ifset($_SESSION, 'mobile_reminder_csrf');
if (!is_string($csrf)) {
    $csrf = bin2hex(random_bytes(32));
    $_SESSION['mobile_reminder_csrf'] = $csrf;
}
$post = $_POST;
if (ifset($_SERVER, 'REQUEST_METHOD') === 'POST') {
    $submitted = ifset($post, 'csrf');
    if (!$canSend || !is_string($submitted) || !hash_equals($csrf, $submitted) || ifset($post, 'tenant_db') !== $db) {
        ob_end_clean();
        http_response_code(403);
        exit;
    }
    try {
        $input = ['target'=>ifset($post,'target'), 'message'=>ifset($post,'message'),
            'amount'=>ifset($post,'amount','') === '' ? null : ifset($post,'amount'),
            'date'=>ifset($post,'date','') === '' ? null : ifset($post,'date')];
        $service->create($sender, $input);
        $_SESSION['mobile_reminder_flash'] = 'sent';
    } catch (MobileReminderError $e) {
        $_SESSION['mobile_reminder_flash'] = 'invalid';
    } catch (Throwable $e) {
        error_log(json_encode(['event'=>'reminder_web_failed','exception'=>get_class($e)]));
        $_SESSION['mobile_reminder_flash'] = 'failed';
    }
    ob_end_clean();
    header('Location: bilag_paamindelse.php', true, 303);
    exit;
}
$flash = ifset($_SESSION, 'mobile_reminder_flash');
unset($_SESSION['mobile_reminder_flash']);
$users = $service->appUsers();
$names = array_column($users, 'username', 'subject');
$beforeInput = ifset($_GET, 'before');
$before = $beforeInput === null ? null : filter_var($beforeInput, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if ($before === false) {
    ob_end_clean();
    http_response_code(400);
    exit;
}
$history = $service->history(50, $before);

/**
 * HTML-escape content and translated labels.
 * @return string
 */
function reminderHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return string Translated web label, escaped for HTML. */
function reminderLabel(string $key, int $language): string
{
    return reminderHtml(findtekst($key, $language));
}

$heading = reminderLabel('5400|Bilagspåmindelser', (int)$sprog_id);
$close = reminderLabel('5415|Luk', (int)$sprog_id);
if ($menu === 'T') {
    require_once __DIR__ . '/../includes/top_header.php';
    require_once __DIR__ . '/../includes/top_menu.php';
    echo "<div id='header'><div class='headerbtnLft headLink'><a href='kladdeliste.php'>$close</a></div><div class='headerTxt'>$heading</div></div>";
} else {
    echo "<body><div><a href='kladdeliste.php'>$close</a><h1>$heading</h1></div>";
}
echo "<main class='content-noside' style='padding:16px'>";
if ($flash !== null) {
    $labels = ['sent'=>'5408|Påmindelsen er sendt.', 'invalid'=>'5409|Kontrollér modtager, besked, beløb og dato.', 'failed'=>'5410|Påmindelsen kunne ikke sendes. Prøv igen.'];
    echo '<p role="status">' . reminderLabel(ifset($labels, $flash, $labels['failed']), (int)$sprog_id) . '</p>';
}
if (!$users) {
    echo '<p>' . reminderLabel('5411|Ingen aktive appbrugere i dette regnskab.', (int)$sprog_id) . '</p>';
}
if ($canSend && $users) {
    echo "<form method='post' action='bilag_paamindelse.php'>";
    echo "<input type='hidden' name='csrf' value='" . reminderHtml($csrf) . "'><input type='hidden' name='tenant_db' value='" . reminderHtml($db) . "'>";
    echo '<p><label>' . reminderLabel('5401|Modtager', (int)$sprog_id) . " <select name='target'><option value='all'>" . reminderLabel('5402|Alle appbrugere', (int)$sprog_id) . '</option>';
    foreach ($users as $user) {
        echo "<option value='" . reminderHtml($user['subject']) . "'>" . reminderHtml($user['username']) . '</option>';
    }
    echo '</select></label></p><p><label>' . reminderLabel('5403|Besked', (int)$sprog_id) . "<br><textarea name='message' rows='4' cols='60' maxlength='4000' required></textarea></label></p>";
    echo '<p><label>' . reminderLabel('5404|Beløb (valgfrit)', (int)$sprog_id) . " <input name='amount' type='number' min='0' step='0.001'></label> <label>" . reminderLabel('5405|Dato (valgfrit)', (int)$sprog_id) . " <input name='date' type='date'></label></p>";
    echo "<button type='submit'>" . reminderLabel('5406|Send påmindelse', (int)$sprog_id) . '</button></form>';
}
echo '<h2>' . reminderLabel('5407|Historik', (int)$sprog_id) . '</h2><table><thead><tr><th>' . reminderLabel('5416|Oprettet', (int)$sprog_id) . '</th><th>' . reminderLabel('5403|Besked', (int)$sprog_id) . '</th><th>' . reminderLabel('5401|Modtager', (int)$sprog_id) . '</th><th>' . reminderLabel('5417|Status', (int)$sprog_id) . '</th></tr></thead><tbody>';
foreach ($history['items'] as $item) {
    foreach ($item['recipients'] as $recipient) {
        $state = $recipient['resolved_at'] !== null ? '5412|Besvaret' : '5413|Afventer bilag';
        echo '<tr><td>' . reminderHtml($item['created_at']) . '</td><td>' . nl2br(reminderHtml($item['message'])) . '</td><td>' . reminderHtml(ifset($names, $recipient['subject'], $recipient['subject'])) . '</td><td>' . reminderLabel($state, (int)$sprog_id);
        if ($recipient['resolved_at'] !== null) {
            echo ' ' . reminderHtml($recipient['resolved_at']);
        }
        echo '</td></tr>';
    }
}
echo '</tbody></table>';
if ($history['next_before'] !== null) {
    echo "<p><a href='bilag_paamindelse.php?before=" . (int)$history['next_before'] . "'>" . reminderLabel('5414|Ældre påmindelser', (int)$sprog_id) . '</a></p>';
}
echo '</main></body></html>';
ob_end_flush();

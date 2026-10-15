<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/payments/link/betal.php --- ver 5.0.0 --- 2026-10-07 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Invoice payment links.
// 20261001 CDX/LH Permit trusted Vipps and MobilePay form redirect destinations.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

/**
 * @var string $sqhost Server configuration, includes/connect.php below.
 * @var string $squser Server configuration, includes/connect.php below.
 * @var string $sqpass Server configuration, includes/connect.php below.
 * @var string $sqdb Registry database, includes/connect.php below.
 */
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../../../restapi/core/MobileTenantMetadata.php';
require_once __DIR__ . '/../../../restapi/services/payments/PaymentLinkService.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self' ".
    implode(' ',VippsEpaymentProvider::REDIRECT_ORIGINS)."; frame-ancestors 'none'; base-uri 'none'");
$method = ifset($_SERVER,'REQUEST_METHOD','GET');
$selector = ifset($_GET,'l','');
$csrfSubmitted = ifset($_POST,'csrf');
$service = null;
$link = null;
$message = '5485|Linket findes ikke længere.';
$processing = false;
try {
    $registry = MobileAuth::connectRegistry();
    PaymentLinkService::rateLimit($registry,'paylink_public',(string)ifset($_SERVER,'REMOTE_ADDR',''));
    if (!in_array($method,['GET','POST'],true)) {
        header('Allow: GET, POST');
        throw new MobileT2Error('validation_failed',405);
    }
    if (!is_string($selector) || !preg_match('/^([1-9][0-9]{0,9})-([a-f0-9]{32})$/D',$selector,$parts)) {
        throw new MobileT2Error('not_found',404);
    }
    $tenantId = (int)$parts[1];
    $store = new MobileSchema($registry);
    $tenants = $store->rows("SELECT db,regnskab FROM regnskab WHERE id=$tenantId AND COALESCE(lukket,'')<>'on'
        AND (lukkes IS NULL OR lukkes>CURRENT_DATE)");
    $db = (string)ifset($tenants,[0,'db'],'');
    if (!$tenants || !preg_match('/^[A-Za-z0-9_]+$/D',$db) || $db === $sqdb) {
        throw new MobileT2Error('not_found',404);
    }
    $connection = db_connect($sqhost,$squser,$sqpass,$db,__FILE__.' linje '.__LINE__);
    mobileTenantUpdates($connection);
    $matches = (new MobileSchema($connection))->rows('SELECT * FROM payment_links WHERE token_sha256='.
        (new MobileSchema($connection))->literal(hash('sha256',$parts[2])));
    if (!$matches) {
        throw new MobileT2Error('not_found',404);
    }
    $link = $matches[0];
    $creator = Subject::parse($link['created_by']);
    // Public access is proved by the opaque token; context identity is the stored link creator.
    $context = EngineContext::forConfig($connection,$db,$tenantId,$link['created_by'],$creator['user_id']);
    $service = new PaymentLinkService($context,$link['created_by']);
    $status = $service->status((int)$link['id'],isset($_GET['return']));
    $state = $status['state'];
    $linkId = (int)$link['id'];
    $processing = (bool)(new MobileSchema($connection))->rows("SELECT id FROM payment_attempts WHERE link_id=$linkId
        AND (state IN ('authorized','capturing') OR (state='captured' AND booking='none')) LIMIT 1");
    if ($state !== 'active') {
        $message = match ($state) {
            'paid'=>'5482|Betalt. Fakturaen er lukket.',
            'paid_unsettled','review'=>'5488|Betalingen er modtaget. Din bogholder får besked.',
            'expired'=>'5483|Linket er udløbet. Bed afsenderen om et nyt link.',
            default=>'5484|Fakturaen er ændret. Bed afsenderen om et nyt link.',
        };
    } else {
        session_set_cookie_params(['httponly'=>true,'secure'=>ifset($_SERVER,'HTTPS') === 'on','samesite'=>'Lax']);
        session_start();
        $sessionKey = 'paylink_csrf_'.hash('sha256',$selector);
        if (!is_string(ifset($_SESSION,$sessionKey))) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
        }
        $csrf = $_SESSION[$sessionKey];
        session_write_close();
        if ($method === 'POST') {
            if (!is_string($csrfSubmitted) || !hash_equals($csrf,$csrfSubmitted)) {
                throw new MobileT2Error('permission_denied',403);
            }
            $baseRows = (new MobileSchema($connection))->rows("SELECT var_value FROM settings WHERE var_grp='mobile_betaling'
                AND var_name='public_base_url' AND user_id=0 ORDER BY id DESC LIMIT 1");
            $publicUrl = rtrim((string)ifset($baseRows,[0,'var_value'],''),'/').'/debitor/payments/link/betal.php?l='.$selector;
            $postingDate = (new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
            $postingContext = new EngineContext($connection,$db,$tenantId,$link['created_by'],$creator['user_id'],$postingDate);
            $attempt = (new PaymentLinkService($postingContext,$link['created_by']))->startAttempt((int)$link['id'],$publicUrl);
            if ($attempt['redirect_url'] !== null) {
                header('Location: '.$attempt['redirect_url'],true,303);
                exit;
            }
            $processing = $attempt['state'] === 'processing';
            $message = $processing ? '5486|Betalingen er modtaget og behandles.' : '5484|Fakturaen er ændret. Bed afsenderen om et nyt link.';
            $state = $attempt['state'];
            $status['state'] = $state;
        } else {
            $message = $processing ? '5486|Betalingen er modtaget og behandles.' : '5481|Faktura';
        }
    }
    $merchant = MobileTenantMetadata::companyName($connection,(string)$tenants[0]['regnskab']);
} catch (MobileT2Error $error) {
    http_response_code($error->status);
    if ($error->status === 429) {
        header('Retry-After: '.(int)$error->details['retry_after']);
        $message = '5489|For mange forsøg. Vent lidt, og prøv igen.';
    } elseif ($error->status !== 404) {
        $message = '5487|Betalingen kunne ikke startes. Prøv igen om lidt.';
    }
} catch (Throwable $error) {
    http_response_code(500);
    error_log(json_encode(['exception'=>get_class($error),'location'=>$error->getFile().':'.$error->getLine()]));
    $message = '5487|Betalingen kunne ikke startes. Prøv igen om lidt.';
}
// Keep db_query's request-relative paths aligned with this public entry point.
chdir(__DIR__);
$escape = static fn(string $value): string => htmlspecialchars($value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
$text = static fn(string $value): string => $escape(findtekst($value,1));
?><!doctype html>
<html lang="da"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $text('5480|Betal med MobilePay') ?></title>
<style>body{font:18px system-ui;background:#f5f5f5;color:#222;margin:0;padding:24px}main{max-width:420px;margin:40px auto;background:white;padding:24px;border-radius:16px}button{background:#5433ff;color:white;padding:14px;border:0;border-radius:8px;font:inherit;width:100%}</style>
<main>
<?php if (isset($merchant)) { ?>
<h1><?= $escape($merchant) ?></h1>
<p><?= $text('5481|Faktura') ?> <?= $escape((string)$link['faktnr']) ?></p>
<p><?= $escape(number_format((float)$link['amount'],2,',','.')) ?> <?= $escape((string)$link['currency']) ?></p>
<?php } ?>
<p role="status"><?= $text($message) ?></p>
<?php if (http_response_code()<400 && ifset($status,'state') === 'active' && !$processing && isset($csrf)) { ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $escape($csrf) ?>"><button><?= $text('5480|Betal med MobilePay') ?></button></form>
<?php } ?>
</main></html>

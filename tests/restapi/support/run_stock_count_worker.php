<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_stock_count_worker.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Supply current and late posted form snapshots separately from the queue timestamp.
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../restapi/services/StockCountService.php';

error_reporting(E_ALL);
$_SERVER['REQUEST_URI'] = '/saldi/lager/optalling.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$stockConnection = RestApiEnv::connect($input['db']);
$context = new EngineContext($stockConnection, $input['db'], (int)$input['tenant_id'], $input['username'], (int)$input['user_id'], '2026-10-06');
echo json_encode(['pid'=>(int)RestApiEnv::rows($stockConnection, 'SELECT pg_backend_pid() AS pid')[0]['pid']]) . "\n";
flush();
try {
    $queueSeconds = null;
    $result = match ($input['mode']) {
        'submit' => (new StockCountService($context, $input['subject']))->submit($input['body'], new EmployeeContext($stockConnection, 'account', (int)$input['user_id'])),
        'queue' => LegacyEngine::run($context, static function () use ($input, &$queueSeconds): string {
            if (ifset($input, 'wait_for_sleep') !== null) {
                $paused = false;
                for ($i = 0; $i < 300; $i++) {
                    $state = RestApiEnv::rows($GLOBALS['connection'], 'SELECT wait_event FROM pg_stat_activity WHERE pid=$1', [$input['wait_for_sleep']]);
                    if (ifset($state, [0,'wait_event']) === 'PgSleep') {
                        $paused = true;
                        break;
                    }
                    usleep(10000);
                }
                if (!$paused) {
                    throw new LogicException('Count did not reach the delayed line insert');
                }
                usleep(1200000);
            }
            $page = file_get_contents(__DIR__ . '/../../../lager/optalling.php');
            $start = strpos($page, "if ($" . "vare_id && ($" . "optalt ||");
            $end = strpos($page, 'print "<tr><td align=', $start);
            extract($GLOBALS, EXTR_SKIP);
            extract($input['queue'], EXTR_OVERWRITE);
            $_POST = ['tidspkt'=>ifset($input, ['queue','snapshot_time'], ifset($input, ['queue','tidspkt']))];
            if (ifset($input, 'wait_for_sleep') !== null) {
                $_POST['tidspkt'] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('YmdHis');
            }
            $queueStarted = microtime(true);
            ob_start();
            try {
                eval(str_replace('__DIR__', var_export(realpath(__DIR__ . '/../../../lager'), true), substr($page, $start, $end - $start)));
                return ob_get_contents();
            } finally {
                $queueSeconds = microtime(true) - $queueStarted;
                ob_end_clean();
            }
        }),
        'web', 'web-held' => LegacyEngine::run($context, static function () use ($input): array {
            $page = file_get_contents(__DIR__ . '/../../../lager/varekort.php');
            $start = strpos($page, 'require_once', strpos($page, 'if ($acceptStockChange) {'));
            $end = strpos($page, '    print "<meta', $start);
            extract($GLOBALS, EXTR_SKIP);
            $id = (int)$input['id'];
            $lager = (int)($input['lager'] ?? 2);
            $warehouseNames = [1=>'One',2=>'Two'];
            $_POST = ['beholdning'=>$input['before'],'ny_beholdning'=>$input['quantity'],'initials'=>'CA','reason'=>'Web fixture',
                'lagerbeh'=>[$lager=>$input['before']],'ny_lagerbeh'=>[$lager=>$input['quantity']]];
            $code = str_replace('__DIR__', var_export(realpath(__DIR__ . '/../../../lager'), true), substr($page, $start, $end - $start));
            if ($input['mode'] === 'web-held') {
                $code = str_replace("transaktion('commit');", "fwrite(STDOUT, \"held\\n\"); fflush(STDOUT); fgets(STDIN); transaktion('commit');", $code);
            }
            eval($code);
            return stock_snapshot($id, $lager);
        }),
        'post-queue' => LegacyEngine::run($context, static function () use ($input): array {
            // Load only the page's function definitions, then invoke its real stock-posting action.
            $page = file_get_contents(__DIR__ . '/../../../lager/optalling.php');
            $start = strpos($page, 'function vis_optalling(');
            $end = strrpos($page, '?>');
            $functions = substr($page, $start, $end === false ? null : $end - $start);
            eval(str_replace('__DIR__', var_export(realpath(__DIR__ . '/../../../lager'), true), $functions));
            bogfor(2, false, '06-10-2026', 2, false);
            return stock_snapshot((int)$input['id'], 2);
        }),
        default => throw new LogicException('Unknown worker mode'),
    };
    echo json_encode(['result'=>$result,'queue_seconds'=>$queueSeconds], JSON_THROW_ON_ERROR) . "\n";
} catch (MobileT2Error $error) {
    echo json_encode(['error'=>$error->error,'status'=>$error->status,'details'=>$error->details], JSON_THROW_ON_ERROR) . "\n";
}
pg_close($stockConnection);

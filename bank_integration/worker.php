<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/worker.php --- ver 5.1.1 --- 2026-11-17 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
//
// 20261006 CDX/LH Created Enable Banking worker backend service.
// 20261007 CDX/LH Reported lock file failures separately from genuine contention.
// 20261028 CDX/LH Skip disabled tenants before schema access and drain eligible local queues.
// 20261028 CDX/LH Record a best-effort heartbeat after acquiring the parent lock.
// 20261028 CL/LH Heartbeat after every tenant; one failing queued delivery no longer stops the tenant's other accounts.
// 20261116 CL/LH Pull model: the worker only fetches; no queued delivery and no imported count.
// 20261117 CL/LH Without --force an account is fetched only if it is still due when its lease is taken.
//                A company without the bank tables is reported (schema_missing) and skipped; the tables come from the update chain.

/**
 * Configuration injected by includes/connect.php below; tenant selection replaces online.php.
 * @var string $sqdb
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $db_type
 * @var mixed $connection
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}
chdir(__DIR__);
$_SERVER['REQUEST_URI'] = '/saldi/bank_integration/worker.php';
$_SERVER['REMOTE_ADDR'] = 'cli';
$webservice = true;
$brugernavn = 'worker';
$s_id = '';
$db_skriv_id = 0;
$db_transaktion_depth = 0;
$db_modify_fejl = false;
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/includes/ebConnections.php';
$webservice = true;
$options = getopt('', ['tenant:', 'account:', 'force', 'dry-run']);
$exitCode = 0;
$lock = null;
try {
    $db = $sqdb;
    $connection = db_connect($sqhost, $squser, $sqpass, $sqdb);
    if (!db_select_database($connection, $sqdb)) {
        throw new EbDbException();
    }
    $global_connection = $connection;
    if (!isset($options['tenant'])) {
        $lock = fopen(__DIR__ . '/../temp/.ht_bank_worker.lock', 'c');
        if ($lock === false) {
            fwrite(STDERR, "Enable Banking worker cannot open or create the lock file.\n");
            exit(2);
        }
        $wouldBlock = 0;
        if (!flock($lock, LOCK_EX | LOCK_NB, $wouldBlock)) {
            if (!$wouldBlock) {
                fwrite(STDERR, "Enable Banking worker cannot lock the lock file.\n");
                exit(2);
            }
            fwrite(STDOUT, "Enable Banking worker already running.\n");
            exit(0);
        }
        // Heartbeat failure must not stop fetching; scope the handler to this optional write.
        // Written again after every tenant, so a long run is not reported as a stopped worker.
        $heartbeat = static function (): void {
            set_error_handler(static function (int $severity, string $message): never {
                throw new ErrorException($message, 0, $severity);
            }, E_WARNING);
            try {
                file_put_contents(__DIR__ . '/../temp/.ht_bank_worker.heartbeat', (string)time());
            } catch (Throwable $e) {
                // The status indicator treats a missing/stale heartbeat as a worker problem.
            } finally {
                restore_error_handler();
            }
        };
        $heartbeat();
        $tenants = ebRows("SELECT db FROM regnskab WHERE db IS NOT NULL AND db<>'' AND (lukket IS NULL OR lukket<>'on') ORDER BY db");
        foreach ($tenants as $tenant) {
            if ($tenant['db'] === $sqdb) {
                continue;
            }
            $args = [PHP_BINARY, __FILE__, '--tenant=' . $tenant['db']];
            foreach (['force', 'dry-run'] as $flag) {
                if (isset($options[$flag])) {
                    $args[] = '--' . $flag;
                }
            }
            if (isset($options['account'])) {
                $args[] = '--account=' . (int)$options['account'];
            }
            $child = proc_open($args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($child)) {
                $exitCode = 2;
                continue;
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $started = time();
            do {
                fwrite(STDOUT, stream_get_contents($pipes[1]));
                fwrite(STDERR, stream_get_contents($pipes[2]));
                $state = proc_get_status($child);
                if (!$state['running']) {
                    break;
                }
                if (time() - $started >= 600) {
                    proc_terminate($child);
                    usleep(100000);
                    if (proc_get_status($child)['running']) {
                        proc_terminate($child, 9);
                    }
                    $exitCode = 2;
                    fwrite(STDERR, 'Enable Banking tenant timed out: ' . $tenant['db'] . "\n");
                    break;
                }
                usleep(100000);
            } while (true);
            fwrite(STDOUT, stream_get_contents($pipes[1]));
            fwrite(STDERR, stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closed = proc_close($child);
            $heartbeat();
            if (($state['exitcode'] >= 0 ? $state['exitcode'] : $closed) !== 0) {
                $exitCode = 2;
            }
        }
    } else {
        $tenantName = (string)$options['tenant'];
        $tenant = ebRow("SELECT id, db FROM regnskab WHERE db=" . ebSql($tenantName) . " AND (lukket IS NULL OR lukket<>'on')");
        if (!$tenant || $tenantName === $sqdb) {
            throw new EbUserError('Ukendt eller lukket regnskab.');
        }
        $db = $tenantName;
        $db_id = (int)$tenant['id'];
        $db_skriv_id = $db_id;
        $connection = db_connect($sqhost, $squser, $sqpass, $db);
        if (!db_select_database($connection, $db)) {
            throw new EbDbException();
        }
        if (!ebCompanyEnabled() || !ebFeatureAvailable()) {
            exit(0);
        }
        // The tables are created at the company's next login (includes/betweenUpdates.php), not here.
        if (!ebSchemaReady()) {
            fwrite(STDOUT, $db . " schema_missing\n");
            exit(0);
        }
        $ids = ebDueAccounts();
        if (isset($options['account'])) {
            $requested = (int)$options['account'];
            $ids = isset($options['force']) || in_array($requested, $ids, true) ? [$requested] : [];
        } elseif (isset($options['force'])) {
            $ids = array_map(static fn(array $a): int => (int)$a['id'], ebRows("SELECT id FROM bank_accounts WHERE state='active'"));
        }
        foreach ($ids as $id) {
            if (isset($options['dry-run'])) {
                fwrite(STDOUT, "$db account=$id dry_run\n");
                continue;
            }
            // --force fetches now; otherwise only an account that is still due when its lease is taken.
            $result = ebSyncAccount($id, 'schedule', [], null, !isset($options['force']));
            $run = $result['run_id'] ? ebRow('SELECT fetched, new_count, review_count FROM bank_sync_runs WHERE id=' . (int)$result['run_id']) : null;
            fwrite(STDOUT, sprintf("%s account=%d status=%s fetched=%d new=%d review=%d\n", $db, $id, $result['status'],
                (int)($run['fetched'] ?? 0), (int)($run['new_count'] ?? 0), (int)($run['review_count'] ?? 0)));
            if (!in_array($result['status'], ['ok', 'skipped', 'rate_limited'], true)) {
                $exitCode = 2;
            }
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Enable Banking worker failed; check configuration and tenant database availability.\n");
    $exitCode = 2;
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
exit($exitCode);

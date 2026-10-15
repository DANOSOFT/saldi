<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/marken/summary.php --- ver 5.0.0 --- 2026.10.06
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

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/EmployeeContext.php';
require_once __DIR__ . '/../../../services/ClaimsService.php';

/** Rights-filtered field hub; hidden sections never query or disclose their figures. */
final class MarkenSummaryEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array { return ['module'=>'bilag', 'action'=>'own']; }

    public function handleRequestMethod()
    {
        header('Cache-Control: private, no-store');
        if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'GET') {
            header('Allow: GET');
            $this->sendError('validation_failed', 'Method not allowed', 405);
        }
        parent::handleRequestMethod();
    }

    /** @return bool Installed fragment, with the same boolean/v2 convention as me. */
    private function installed(string $name): bool
    {
        $path = __DIR__ . '/../../../core/capabilities.d/' . $name . '.php';
        if (!is_file($path)) { return false; }
        $flags = (static fn(string $file): array => require $file)($path);
        return in_array($flags[$name] ?? false, [true, 'v2'], true);
    }

    /**
     * @return array{bookings_today:array{planned:int,done:int}|null,hours_week:?string,km_week:?string,expenses_week:?int,
     *   open_receipt_orders:array{count:int,first:array{ordrenr:int,kreditor:array{kontonr:string,name:string}}|null}|null,sections:array{stock_count:bool,goods_receipt:bool,time:bool,bookings:bool,claims:bool,catalogue:bool}}
     */
    private function summary(): array
    {
        $result = ['bookings_today'=>null, 'hours_week'=>null, 'km_week'=>null, 'expenses_week'=>null,
            'open_receipt_orders'=>null, 'sections'=>array_fill_keys(['stock_count','goods_receipt','time','bookings','claims','catalogue'], false)];
        // Registry identities have no employee/own field persona, even after tenant exchange.
        if ($this->principal !== 'account') { return $result; }
        $store = new MobileSchema($this->conn);
        $store->execute('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            $employee = new EmployeeContext($this->conn, $this->principal, (int)$this->userId);
            $user = $store->rows('SELECT rettigheder FROM brugere WHERE id=' . (int)$this->userId)[0];
            $rights = (string)$user['rettigheder'];
            $claims = new ClaimsService($this->conn, $this->db, $this->subject, $this->principal, (int)$this->userId);
            $sections = [
                'stock_count'=>$this->installed('stock_count') && Rights::allows($rights, 'varer', 'write'),
                'goods_receipt'=>$this->installed('goods_receipt') && Rights::allows($rights, 'kreditorordre', 'read'),
                'time'=>$this->installed('time_entries') && $employee->linked && Rights::allows($rights, 'sager_loen', 'read', $employee->caseDigits),
                'bookings'=>$this->installed('bookings') && $employee->linked && Rights::allows($rights, 'employee', 'own'),
                'claims'=>$this->installed('claims') && Rights::allows($rights, 'bilag', 'own') && $claims->capability(),
                'catalogue'=>$this->installed('catalogue') && (Rights::allows($rights, 'varer', 'read') || Rights::allows($rights, 'debitorordre', 'read')),
            ];
            $result['sections'] = $sections;
            $today = new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen'));
            $monday = $today->modify('-' . ((int)$today->format('N') - 1) . ' days');
            $from = $monday->format('Y-m-d');
            $to = $monday->modify('+6 days')->format('Y-m-d');
            $fromSql = $store->literal($from);
            $toSql = $store->literal($to);
            if ($sections['bookings']) {
                $counts = $store->rows("SELECT count(*) FILTER (WHERE status='planlagt') AS planned,
                    count(*) FILTER (WHERE status='udfoert') AS done FROM mobile_bookings WHERE booking_date=" . $store->literal($today->format('Y-m-d')))[0];
                $result['bookings_today'] = ['planned'=>(int)$counts['planned'], 'done'=>(int)$counts['done']];
            }
            if ($sections['time']) {
                // Same packed-member position as TimeEntryService::list; never sum colleagues' hours.
                $id = $store->literal((string)$employee->ansatId);
                $index = "array_position(string_to_array(ansatte,chr(9)),$id)";
                $hours = $store->rows("SELECT COALESCE(sum(NULLIF((string_to_array(timer,chr(9)))[$index],'')::numeric),0) AS hours
                    FROM loen WHERE art='timer' AND $id = ANY(string_to_array(ansatte,chr(9)))
                    AND loendate >= $fromSql AND loendate <= $toSql")[0]['hours'];
                $result['hours_week'] = number_format((float)$hours, 2, '.', '');
            }
            if ($sections['claims']) {
                $subject = $store->literal($this->subject);
                $km = $store->rows("SELECT COALESCE(sum(km),0) AS km FROM mobile_mileage_trips WHERE subject=$subject
                    AND deleted_at IS NULL AND trip_date >= $fromSql AND trip_date <= $toSql")[0]['km'];
                $result['km_week'] = number_format((float)$km, 1, '.', '');
                // Reuse the claim list's legacy date normalization and subject filter.
                $expenses = $claims->expenses(['period_from'=>$from, 'period_to'=>$to, 'limit'=>1]);
                $result['expenses_week'] = $expenses['total'];
            }
            if ($sections['goods_receipt']) {
                // Match GoodsReceiptService::openOrders, including actual remaining quantities.
                $where = "o.art='KO' AND o.status IN (1,2) AND EXISTS (SELECT 1 FROM ordrelinjer l
                    WHERE l.ordre_id=o.id AND l.posnr>0 AND l.vare_id>0 AND COALESCE(trim(l.varenr),'')<>''
                    AND l.antal > COALESCE((SELECT SUM(b.antal) FROM batch_kob b WHERE b.linje_id=l.id),0))";
                $count = (int)$store->rows("SELECT count(*) AS n FROM ordrer o WHERE $where")[0]['n'];
                $first = $store->rows("SELECT o.ordrenr,o.kontonr,o.firmanavn FROM ordrer o WHERE $where ORDER BY o.ordredate DESC,o.id DESC LIMIT 1")[0] ?? null;
                $result['open_receipt_orders'] = ['count'=>$count, 'first'=>$first === null ? null :
                    ['ordrenr'=>(int)$first['ordrenr'], 'kreditor'=>['kontonr'=>(string)$first['kontonr'], 'name'=>(string)$first['firmanavn']]]];
            }
            $store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $store->execute('ROLLBACK');
            throw $error;
        }
    }

    protected function handleGet($id = null)
    {
        $this->sendResponse(true, $this->summary());
    }
}

if (realpath((string)ifset($_SERVER, 'SCRIPT_FILENAME', '')) === __FILE__) {
    (new MarkenSummaryEndpoint())->handleRequestMethod();
}

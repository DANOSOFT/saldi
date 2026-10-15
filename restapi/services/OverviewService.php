<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/OverviewService.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Preserve the queue when fiscal-year data cannot support money cards.

require_once __DIR__ . '/OverviewOpenPosts.php';

/** Distinguish unavailable accounting data from invalid client parameters. */
final class OverviewFiscalYearUnavailable extends InvalidArgumentException
{
    public string $reason;

    public function __construct(string $reason, string $message)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }
}

/** Read-only accounting queries on an explicit tenant connection; never reads a web session. */
final class OverviewService
{
    private $connection;

    /** @param resource|\PgSql\Connection $connection Selected PostgreSQL tenant. */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql, array $parameters = []): array
    {
        $result = pg_query_params($this->connection, $sql, $parameters);
        if ($result === false) {
            throw new RuntimeException('Overview query failed');
        }
        return pg_fetch_all($result) ?: [];
    }

    /** @return string Today's accounting date in the installation's Danish time zone. */
    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
    }

    /** @return string Valid ISO date, no later than today. */
    public static function date($value): string
    {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
            throw new InvalidArgumentException('as_of must be an ISO date');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Copenhagen'));
        if (!$date || $date->format('Y-m-d') !== $value || $value > self::today()) {
            throw new InvalidArgumentException('as_of must be a valid date no later than today');
        }
        return $value;
    }

    /** @return array{id:int, start:string, end:string, locked:bool} */
    private function yearShape(array $row): array
    {
        $start = sprintf('%04d-%02d-01', (int)$row['box2'], (int)$row['box1']);
        $end = sprintf('%04d-%02d-01', (int)$row['box4'], (int)$row['box3']);
        foreach ([$start, $end] as $date) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new OverviewFiscalYearUnavailable('invalid_fiscal_year', 'Invalid fiscal-year boundaries');
            }
        }
        $end = (new DateTimeImmutable($end))->format('Y-m-t');
        if ($start > $end) {
            throw new OverviewFiscalYearUnavailable('invalid_fiscal_year', 'Invalid fiscal-year boundaries');
        }
        return ['id' => (int)$row['kodenr'], 'start' => $start, 'end' => $end, 'locked' => $row['box5'] !== 'on'];
    }

    /** @return array{id:int, start:string, end:string, locked:bool} */
    public function fiscalYear(string $asOf, ?int $requested = null): array
    {
        $matches = [];
        $requestedExists = false;
        foreach ($this->rows("SELECT kodenr,box1,box2,box3,box4,box5 FROM grupper WHERE art='RA' ORDER BY kodenr") as $row) {
            if ($requested !== null && (int)$row['kodenr'] !== $requested) {
                continue;
            }
            $requestedExists = true;
            $year = $this->yearShape($row);
            if ($year['start'] <= $asOf && $year['end'] >= $asOf) {
                $matches[] = $year;
            }
        }
        if ($requested !== null && !$requestedExists) {
            throw new InvalidArgumentException('Requested fiscal year does not exist');
        }
        if (count($matches) !== 1) {
            throw new OverviewFiscalYearUnavailable($matches ? 'ambiguous_fiscal_year' : 'no_fiscal_year',
                'No unique fiscal year contains as_of');
        }
        return $matches[0];
    }

    /** @return string ISO date one calendar year earlier, clamping leap day. */
    private function previousDate(string $date): string
    {
        $year = (int)substr($date, 0, 4) - 1;
        $month = (int)substr($date, 5, 2);
        $day = min((int)substr($date, 8, 2), (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t'));
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /** @return float Posted net turnover, VAT excluded, in one fiscal year through the cutoff. */
    public function revenue(array $year, string $asOf): float
    {
        $range = ['kontomin' => 0, 'kontomaks' => 2000];
        foreach ($this->rows("SELECT var_name,var_value FROM settings WHERE var_grp='dashboard_values'
            AND var_name IN ('kontomin','kontomaks') ORDER BY id") as $setting) {
            if (!is_numeric($setting['var_value'])) {
                throw new InvalidArgumentException('Invalid turnover account range');
            }
            $range[$setting['var_name']] = (int)$setting['var_value'];
        }
        if ($range['kontomin'] >= $range['kontomaks']) {
            throw new InvalidArgumentException('Invalid turnover account range');
        }
        $row = $this->rows("SELECT COALESCE(SUM(ROUND(COALESCE(t.kredit,0)::numeric,2)
            - ROUND(COALESCE(t.debet,0)::numeric,2)),0) AS amount FROM transaktioner t
            WHERE t.transdate >= $1 AND t.transdate <= $2 AND t.kontonr > $3 AND t.kontonr < $4
            AND EXISTS (SELECT 1 FROM kontoplan k WHERE k.kontonr=t.kontonr
                AND k.regnskabsaar=$5 AND k.kontotype='D')",
            [$year['start'], $asOf, $range['kontomin'], $range['kontomaks'], $year['id']]);
        return (float)$row[0]['amount'];
    }

    /** @return string The tenant base currency, default DKK as in includes/online.php. */
    public function currency(): string
    {
        $row = $this->rows("SELECT var_value FROM settings WHERE var_name='baseCurrency' ORDER BY id LIMIT 1");
        return (string)(ifset($row, [0, 'var_value'], 'DKK') ?: 'DKK');
    }

    /** @return array{count:int, amount:float} */
    public function overdue(string $asOf): array
    {
        $posts = new OverviewOpenPosts(fn(string $sql, array $parameters): array => $this->rows($sql, $parameters), $this->currency());
        return $posts->overdue($asOf, self::today());
    }

    /** @return array{unhandled_count:int, scope:string} */
    public function queue(string $subject, bool $canReadMoney): array
    {
        $where = $canReadMoney ? '' : ' AND uploaded_by=$1';
        $rows = $this->rows("SELECT COUNT(*) AS count FROM pool_files
            WHERE status='ubehandlet' AND deleted_at IS NULL$where", $canReadMoney ? [] : [$subject]);
        return ['unhandled_count' => (int)$rows[0]['count'], 'scope' => $canReadMoney ? 'tenant' : 'own'];
    }

    /** @return bool Whether posting is locked in the calendar month containing the cutoff. */
    private function periodLocked(array $year, string $asOf): bool
    {
        if ($year['locked']) {
            return true;
        }
        $exists = $this->rows("SELECT to_regclass('moms_periode_luk') IS NOT NULL AS present");
        if ($exists[0]['present'] !== 't') {
            return false;
        }
        return (bool)$this->rows("SELECT 1 FROM moms_periode_luk WHERE kalender_aar=$1
            AND kalender_maaned=$2 AND status='closed' LIMIT 1", [(int)substr($asOf, 0, 4), (int)substr($asOf, 5, 2)]);
    }

    /**
     * Distinct configured status accounts, valued as primo plus posted debit minus credit.
     * @return array{bank:float|null, cash:float|null, total:float|null,
     *   bank_accounts:int, cash_accounts:int, configured:bool}
     */
    public function liquidity(array $year, string $asOf): array
    {
        $bank = [];
        $cash = [];
        foreach ($this->rows("SELECT box1 FROM grupper WHERE art='KASKL' AND kode='3'") as $row) {
            if (ctype_digit(trim((string)$row['box1']))) {
                $bank[(int)$row['box1']] = true;
            }
        }
        foreach ($this->rows("SELECT box2 FROM grupper WHERE art='POS' AND kodenr=1 AND fiscal_year=$1", [$year['id']]) as $row) {
            foreach (explode("\t", (string)$row['box2']) as $account) {
                if (ctype_digit(trim($account))) {
                    $cash[(int)$account] = true;
                    unset($bank[(int)$account]);
                }
            }
        }
        $totals = ['bank' => null, 'cash' => null];
        $counts = ['bank' => 0, 'cash' => 0];
        foreach (['bank' => $bank, 'cash' => $cash] as $kind => $accounts) {
            foreach (array_keys($accounts) as $account) {
                $chart = $this->rows("SELECT primo FROM kontoplan WHERE kontotype='S'
                    AND regnskabsaar=$1 AND kontonr=$2", [$year['id'], $account]);
                if (!$chart) {
                    continue;
                }
                if (count($chart) !== 1) {
                    throw new InvalidArgumentException('Ambiguous liquidity account');
                }
                $primo = afrund($chart[0]['primo'], 2);
                $movement = $this->rows("SELECT COALESCE(SUM(ROUND(COALESCE(debet,0)::numeric,2)
                    - ROUND(COALESCE(kredit,0)::numeric,2)),0) AS amount FROM transaktioner
                    WHERE kontonr=$1 AND transdate >= $2 AND transdate <= $3", [$account, $year['start'], $asOf]);
                $totals[$kind] = (float)$totals[$kind] + $primo + (float)$movement[0]['amount'];
                $counts[$kind]++;
            }
            if ($counts[$kind]) {
                $totals[$kind] = afrund($totals[$kind], 2);
            }
        }
        $configured = $counts['bank'] > 0 && $counts['cash'] > 0;
        return ['bank' => $totals['bank'], 'cash' => $totals['cash'],
            'total' => $configured ? afrund($totals['bank'] + $totals['cash'], 2) : null,
            'bank_accounts' => $counts['bank'], 'cash_accounts' => $counts['cash'], 'configured' => $configured];
    }

    /**
     * Money cards are calculated only after the caller has established finans/read rights.
     * @return array{currency:string, fiscal_year:array{id:int,start:string,end:string,locked:bool,period_locked:bool},
     *   revenue:array{amount:float,previous_amount:float|null,previous_period:array{start:string,end:string,fiscal_year:int}|null},
     *   liquidity:array{bank:float|null,cash:float|null,total:float|null,bank_accounts:int,cash_accounts:int,configured:bool},
     *   overdue:array{count:int,amount:float}}
     */
    public function money(string $asOf, ?int $requested = null): array
    {
        $year = $this->fiscalYear($asOf, $requested);
        $previous = null;
        $priorStart = $this->previousDate($year['start']);
        $priorEnd = (new DateTimeImmutable($this->previousDate($year['end'])))->format('Y-m-t');
        foreach ($this->rows("SELECT kodenr,box1,box2,box3,box4,box5 FROM grupper WHERE art='RA'") as $row) {
            $candidate = $this->yearShape($row);
            if ($candidate['start'] === $priorStart && $candidate['end'] === $priorEnd) {
                if ($previous !== null) {
                    throw new OverviewFiscalYearUnavailable('ambiguous_fiscal_year', 'Ambiguous previous fiscal year');
                }
                $previous = $candidate;
            }
        }
        $priorCutoff = $this->previousDate($asOf);
        $year['period_locked'] = $this->periodLocked($year, $asOf);
        return ['currency' => $this->currency(), 'fiscal_year' => $year,
            'revenue' => ['amount' => $this->revenue($year, $asOf),
                'previous_amount' => $previous ? $this->revenue($previous, $priorCutoff) : null,
                'previous_period' => $previous ? ['start' => $priorStart, 'end' => $priorCutoff, 'fiscal_year' => $previous['id']] : null],
            'liquidity' => $this->liquidity($year, $asOf), 'overdue' => $this->overdue($asOf)];
    }

    /**
     * Queue survives unavailable fiscal-year data; financial fields require finans/read.
     * @return array{
     *   as_of:string, money_cards_available:bool,
     *   money_cards_unavailable_reason?:string,
     *   queue:array{unhandled_count:int,scope:string},
     *   currency?:string,
     *   fiscal_year?:array{id:int,start:string,end:string,locked:bool,period_locked:bool},
     *   revenue?:array{amount:float,previous_amount:float|null,
     *     previous_period:array{start:string,end:string,fiscal_year:int}|null},
     *   liquidity?:array{bank:float|null,cash:float|null,total:float|null,
     *     bank_accounts:int,cash_accounts:int,configured:bool},
     *   overdue?:array{count:int,amount:float}
     * }
     */
    public function overview(string $subject, bool $canReadMoney, string $asOf, ?int $year = null): array
    {
        $asOf = self::date($asOf);
        $data = ['as_of' => $asOf, 'money_cards_available' => $canReadMoney, 'queue' => $this->queue($subject, $canReadMoney)];
        if ($canReadMoney) {
            try {
                $data += $this->money($asOf, $year);
            } catch (OverviewFiscalYearUnavailable $e) {
                $data['money_cards_available'] = false;
                $data['money_cards_unavailable_reason'] = $e->reason;
            }
        }
        return $data;
    }
}

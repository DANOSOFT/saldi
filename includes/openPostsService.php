<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/openPostsService.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Normalize lookup zero rates before calling the web helper without ambient tenant state.
// 20261001 CDX/LH Reconcile afrund cents without changing zero balances and guard document references against vouchers.

require_once __DIR__ . '/std_func.php';
require_once __DIR__ . '/forfaldsdag.php';
require_once __DIR__ . '/reportFunc/showOpenPosts.php';

/** Session-free open-post reader shared with the overview and legacy callers. */
class OpenPostsReader
{
    private $rows;
    private string $currency;

    /** @param callable(string,array):array $rows Explicit tenant-bound parameterized queries. */
    public function __construct(callable $rows, string $currency)
    {
        $this->rows = $rows;
        $this->currency = $currency;
    }

    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
    }

    /** @return list<array<string,mixed>> Retained debtor rows, including historical settlements. */
    public function posts(string $asOf): array
    {
        return ($this->rows)("SELECT o.*,a.kontonr,a.firmanavn AS customer_name,a.tlf,a.email,
            r.art AS order_art,r.id AS ordre_id,r.fakturadate,r.mail_fakt
            FROM openpost o JOIN adresser a ON a.id=o.konto_id AND a.art='D'
            LEFT JOIN ordrer r ON r.id=o.refnr AND r.konto_id=o.konto_id
                AND o.kladde_id=0 AND trim(r.fakturanr)=trim(o.faktnr)
            WHERE o.transdate <= $1 ORDER BY o.konto_id,o.id", [$asOf]);
    }

    /**
     * Real foreign parity rates use sign-cancelled inputs to bypass the helper's tenantless
     * placeholder cache while preserving its three-decimal foreign precision and cent control sum.
     * @return array<string,mixed> The web helper result, with globals restored even on failure.
     */
    private function aging(array $posts, string $asOf, string $today): array
    {
        foreach ($posts as &$post) {
            $currency = $post['valuta'] ?: $this->currency;
            if ($currency !== $this->currency && $currency !== '-' && (float)$post['valutakurs'] == 100) {
                $post['amount'] = -(float)$post['amount'];
                $post['valutakurs'] = -100;
            }
        }
        unset($post);
        $existed = array_key_exists('baseCurrency', $GLOBALS);
        $previous = ifset($GLOBALS, 'baseCurrency');
        $GLOBALS['baseCurrency'] = $this->currency;
        $cache = [];
        try {
            return openpost_account_aging($posts, $asOf, $today, 'D', $cache);
        } finally {
            if ($existed) {
                $GLOBALS['baseCurrency'] = $previous;
            } else {
                unset($GLOBALS['baseCurrency']);
            }
        }
    }

    /** @return list<array<string,mixed>> Rates resolved before the legacy helper's tenantless cache. */
    private function resolved(array $posts): array
    {
        $rates = [];
        foreach ($posts as &$post) {
            $post['original_currency'] = $post['valuta'] ?: $this->currency;
            $currency = $post['original_currency'];
            // Overview treats numeric zero as a missing rate, including PostgreSQL decimal strings.
            $rate = (float)$post['valutakurs'] ?: 100.0;
            if ($currency !== $this->currency && $currency !== '-' && $rate == 100) {
                $key = $currency . '|' . $post['transdate'];
                if (!isset($rates[$key])) {
                    $found = ($this->rows)("SELECT v.kurs FROM valuta v JOIN grupper g
                        ON g.kodenr=v.gruppe AND g.art='VK' WHERE g.box1=$1 AND v.valdate <= $2
                        ORDER BY v.valdate DESC,v.id DESC LIMIT 1", [$currency, $post['transdate']]);
                    $rates[$key] = (float)ifset($found, [0, 'kurs'], 100) ?: 100.0;
                }
                $rate = $rates[$key];
            }
            if ($currency === '-') {
                $post['valuta'] = $this->currency;
                $rate = 100;
            }
            $post['valutakurs'] = $rate;
        }
        unset($post);
        return $posts;
    }

    private static function isOpen(array $post, string $asOf, string $today): bool
    {
        return !$post['udlignet'] || ($asOf !== $today && $post['udlignet'] === '1'
            && (!$post['udlign_date'] || $post['udlign_date'] > $asOf));
    }

    /** @return string Canonical two-decimal base-currency money. */
    public static function money(float $amount): string
    {
        return number_format(self::cents($amount) / 100, 2, '.', '');
    }

    /** @return int Web-rounded integer cents; round only removes float noise after afrund. */
    private static function cents(float $amount): int
    {
        return (int)round(afrund($amount, 2) * 100);
    }

    /**
     * Reconcile independently rounded groups to the web's once-rounded account saldo.
     * Adjust at most one cent per fractional component, largest rounding error first,
     * with ledger order breaking ties. Exact-cent and zero balances stay unchanged;
     * each result stays between its raw floor and ceiling, preserving its sign.
     *
     * @param list<float> $amounts Raw group balances followed by the raw unallocated balance.
     * @param int $saldo Web-rounded account balance in cents.
     * @return list<int> Reconciled signed cents in the same order as the inputs.
     */
    private static function allocateCents(array $amounts, int $saldo): array
    {
        $cents = array_map(static fn(float $amount): int => self::cents($amount), $amounts);
        $remainder = $saldo - array_sum($cents);
        if ($remainder === 0) {
            return $cents;
        }
        $direction = $remainder > 0 ? 1 : -1;
        $indices = array_keys($amounts);
        usort($indices, static function (int $left, int $right) use ($amounts, $cents, $direction): int {
            $leftError = $amounts[$left] * 100 - $cents[$left];
            $rightError = $amounts[$right] * 100 - $cents[$right];
            // Foreign conversion can leave float noise in mathematically equal errors.
            return abs($leftError - $rightError) < 0.000000001 ? $left <=> $right
                : ($direction * $rightError <=> $direction * $leftError);
        });
        foreach ($indices as $index) {
            $raw = $amounts[$index] * 100;
            $adjusted = $cents[$index] + $direction;
            if ($adjusted < floor($raw + 0.000000001) || $adjusted > ceil($raw - 0.000000001)) {
                continue;
            }
            $cents[$index] = $adjusted;
            $remainder -= $direction;
            if ($remainder === 0) {
                return $cents;
            }
        }
        throw new RuntimeException('Open post rounding could not reconcile');
    }

    private static function bucket(int $days): string
    {
        if ($days <= 0) {
            return 'not_due';
        }
        return $days <= 30 ? 'd0_30' : ($days <= 60 ? 'd31_60' : ($days <= 90 ? 'd61_90' : 'd90_plus'));
    }

    /** @return array<string,mixed> Canonical invoice-group identity, amounts and due date. */
    private function groupItem(array $group, string $asOf, string $today, ?float $rawRest = null): array
    {
        $anchor = $group[0];
        foreach ($group as $post) {
            if ((float)$post['amount'] > 0 && ($post['ordre_id'] || !$anchor['ordre_id'])) {
                $anchor = $post;
                break;
            }
        }
        $due = $anchor['forfaldsdate'] ?: $anchor['transdate'];
        $days = max(0, (int)(new DateTimeImmutable($due))->diff(new DateTimeImmutable($asOf))->format('%r%a'));
        $original = $anchor;
        $original['udlignet'] = '0';
        return ['id' => (int)$anchor['id'], 'type' => openposts_item_type($anchor),
            'konto_id' => (int)$anchor['konto_id'], 'kontonr' => (string)$anchor['kontonr'],
            'customer_name' => (string)$anchor['customer_name'], 'faktnr' => trim((string)$anchor['faktnr']),
            'ordre_id' => $anchor['ordre_id'] ? (int)$anchor['ordre_id'] : null,
            'invoice_date' => $anchor['fakturadate'] ?: $anchor['transdate'], 'due_date' => $due,
            'currency' => $this->currency, 'original_currency' => $anchor['original_currency'],
            'amount' => self::money($this->aging([$original], $asOf, $today)['openY']),
            'rest' => self::money($rawRest ?? $this->aging($group, $asOf, $today)['openY']),
            'days_overdue' => $days, 'bucket' => self::bucket($days),
            'contact' => ['phone' => (string)$anchor['tlf'], 'email' => (string)$anchor['email']], '_posts' => $group];
    }

    /** @return array{items:array,accounts:array,summary:array,posts:array} Groups and history from one ledger read. */
    private function snapshot(string $asOf, string $today, ?int $kontoId = null): array
    {
        $accounts = [];
        $retained = $this->resolved($this->posts($asOf));
        foreach ($retained as $post) {
            if ($kontoId === null || (int)$post['konto_id'] === $kontoId) {
                $accounts[(int)$post['konto_id']][] = $post;
            }
        }
        $items = [];
        $balances = [];
        $buckets = array_fill_keys(['not_due', 'd0_30', 'd31_60', 'd61_90', 'd90_plus'], 0);
        $overdueCount = 0;
        $overdueTotal = 0.0;
        $creditTotal = 0;
        $oldest = 0;
        foreach ($accounts as $accountId => $posts) {
            $aging = $this->aging($posts, $asOf, $today);
            $saldo = self::cents($aging['openY']);
            $balances[$accountId] = ['saldo' => self::money($saldo / 100), 'unallocated_credit' => '0.00'];
            $overduePosts = array_values(array_filter($posts, fn(array $post): bool =>
                (float)$post['amount'] <= 0 || ($post['forfaldsdate'] ?: $post['transdate']) < $asOf));
            $overdue = afrund($this->aging($overduePosts, $asOf, $today)['openKontrol'], 2);
            if ($overdue >= 0.01) {
                $overdueCount++;
                $overdueTotal += $overdue;
            }
            $groups = [];
            $unallocated = [];
            foreach ($posts as $post) {
                if (!self::isOpen($post, $asOf, $today)) {
                    continue;
                }
                $invoice = trim((string)$post['faktnr']);
                if ($invoice === '' || str_contains($invoice, ';')) {
                    $unallocated[] = $post;
                } else {
                    $groups[$invoice][] = $post;
                }
            }
            $accountItems = [];
            $rawAmounts = [];
            foreach ($groups as $invoice => $group) {
                $rawRest = $this->aging($group, $asOf, $today)['openY'];
                $rawAmounts[] = $rawRest;
                $accountItems[] = $this->groupItem($group, $asOf, $today, $rawRest);
            }
            $rawAmounts[] = $this->aging($unallocated, $asOf, $today)['openY'];
            $allocated = self::allocateCents($rawAmounts, $saldo);
            $credit = $allocated[count($accountItems)];
            $balances[$accountId]['unallocated_credit'] = self::money($credit / 100);
            $creditTotal += $credit;
            foreach ($accountItems as $index => $item) {
                $rest = $allocated[$index];
                $item['rest'] = self::money($rest / 100);
                if ($rest > 0) {
                    $oldest = max($oldest, $item['days_overdue']);
                }
                $buckets[$item['bucket']] += $rest;
                $items[] = $item;
            }
            $buckets['not_due'] += $credit;
        }
        $summary = ['as_of' => $asOf, 'currency' => $this->currency,
            'open_total' => self::money(array_sum($buckets) / 100), 'overdue_total' => self::money($overdueTotal),
            'overdue_count' => $overdueCount, 'oldest_days_overdue' => $oldest,
            'buckets' => array_map(fn(int $cents): string => self::money($cents / 100), $buckets),
            'unallocated_credit_total' => self::money($creditTotal / 100)];
        return ['items' => $items, 'accounts' => $balances, 'summary' => $summary, 'posts' => $retained];
    }

    /** @return array{as_of:string,currency:string,open_total:string,overdue_total:string,overdue_count:int,oldest_days_overdue:int,buckets:array<string,string>,unallocated_credit_total:string} */
    public function summary(?int $kontoId = null, ?string $asOf = null, ?string $today = null): array
    {
        $today = $today ?: self::today();
        return $this->snapshot($asOf ?: $today, $today, $kontoId)['summary'];
    }

    /** @return array{count:int,amount:float} Backwards-compatible overview shape using the shared summary. */
    public function overdue(string $asOf, string $today): array
    {
        $summary = $this->summary(null, $asOf, $today);
        return ['count' => $summary['overdue_count'], 'amount' => (float)$summary['overdue_total']];
    }

    /** @return array<int,string> Base-currency open balances keyed by requested debtor id. */
    public function saldo(array $kontoIds): array
    {
        $today = self::today();
        $snapshot = $this->snapshot($today, $today);
        $balances = [];
        foreach ($kontoIds as $id) {
            $balances[(int)$id] = ifset($snapshot, ['accounts', (int)$id, 'saldo'], '0.00');
        }
        return $balances;
    }

    /** @return list<array{id:string,art:string,ordredate:string|null,sent:string}> All dunning levels linked to a group. */
    private function dunningRows(array $item): array
    {
        $ids = array_column($item['_posts'], 'id');
        return ($this->rows)("SELECT DISTINCT r.id,r.art,r.ordredate,
            EXISTS(SELECT 1 FROM document_deliveries d WHERE d.ordre_id=r.id AND d.document='rykker'
                AND d.state='sent' AND d.channel IN ('email','ean')) AS sent
            FROM ordrer r LEFT JOIN ordrelinjer l ON l.ordre_id=r.id
            WHERE r.konto_id=$1 AND r.art IN ('R1','R2','R3')
                AND (l.enhed=ANY($2::text[]) OR r.id=$3)
            ORDER BY r.art DESC,r.ordredate DESC,r.id DESC", [$item['konto_id'], '{' . implode(',', array_map('intval', $ids)) . '}', $item['ordre_id']]);
    }

    /** @return array<string,mixed> Dunning, payment configuration and active link metadata. */
    private function decorate(array $item, ?array $dunning = null): array
    {
        $ids = array_column($item['_posts'], 'id');
        $dunning = $dunning ?? $this->dunningRows($item);
        $latest = ifset($dunning, 0);
        $item['dunning'] = ['level' => $latest ? (int)substr($latest['art'], 1) : 0,
            'rykker_id' => $latest ? (int)$latest['id'] : null, 'created_on' => $latest ? $latest['ordredate'] : null,
            'sent' => $latest ? $latest['sent'] === 't' : false];
        $settings = ($this->rows)("SELECT s.var_name FROM settings s
            WHERE s.var_grp='mobile_betaling' AND s.var_name IN ('account_bank','account_kasse','account_mobilepay')
            AND EXISTS(SELECT 1 FROM kontoplan k WHERE k.kontonr::text=s.var_value AND k.kontotype='S'
                AND COALESCE(k.lukket,'')<>'on')", []);
        $register = count($settings) > 0;
        $link = false;
        $reason = $item['original_currency'] !== $this->currency && $item['original_currency'] !== '-' ? 'foreign_currency'
            : ($item['type'] !== 'invoice' || (float)$item['rest'] <= 0 ? 'not_invoice' : null);
        $item['payable'] = ['link' => $reason === null && (bool)$link, 'register' => $reason === null && $register,
            'reason' => $reason ?: (!$register ? 'no_account' : (!$link ? 'provider_not_configured' : null))];
        $links = ($this->rows)("SELECT id,state FROM payment_links WHERE openpost_id=ANY($1::integer[])
            AND state='active' AND expires_at>now() ORDER BY id DESC LIMIT 1", ['{' . implode(',', array_map('intval', $ids)) . '}']);
        $item['payment_link'] = $links ? ['id' => (int)$links[0]['id'], 'state' => $links[0]['state']] : null;
        unset($item['_posts']);
        return $item;
    }

    /** @return array{items:array,total:int,accounts:array,summary:array} Summary ignores list status/bucket/search/paging. */
    public function items(array $filter, int $limit, int $offset): array
    {
        $today = self::today();
        $snapshot = $this->snapshot($today, $today, ifset($filter, 'konto_id'));
        $items = array_values(array_filter($snapshot['items'], static function (array $item) use ($filter): bool {
            $status = ifset($filter, 'status', 'all');
            if (($status === 'overdue' && $item['days_overdue'] === 0)
                || ($status === 'not_due' && $item['days_overdue'] !== 0)
                || (ifset($filter, 'bucket') && $item['bucket'] !== $filter['bucket'])) {
                return false;
            }
            $query = mb_strtolower((string)ifset($filter, 'q', ''));
            return $query === '' || str_contains(mb_strtolower(implode(' ', [$item['customer_name'], $item['kontonr'], $item['faktnr']])), $query);
        }));
        $sort = ifset($filter, 'sort', 'days_overdue');
        usort($items, static function (array $left, array $right) use ($sort): int {
            $compared = $sort === 'id' ? $left['id'] <=> $right['id'] : ($sort === 'rest' ? (float)$right[$sort] <=> (float)$left[$sort]
                : ($sort === 'days_overdue' ? $right[$sort] <=> $left[$sort] : strcmp($left[$sort], $right[$sort])));
            return $compared ?: $left['id'] <=> $right['id'];
        });
        return ['items' => array_map(fn(array $item): array => $this->decorate($item), array_slice($items, $offset, $limit)),
            'total' => count($items), 'accounts' => $snapshot['accounts'], 'summary' => $snapshot['summary']];
    }

    /** @return array<string,mixed>|null Retained group detail, including its final settlement. */
    public function detail(int $id): ?array
    {
        $today = self::today();
        $snapshot = $this->snapshot($today, $today);
        $retained = $snapshot['posts'];
        $requested = null;
        foreach ($retained as $post) {
            if ((int)$post['id'] === $id) {
                $requested = $post;
                break;
            }
        }
        if (!$requested || trim((string)$requested['faktnr']) === '' || str_contains($requested['faktnr'], ';')) {
            return null;
        }
        $group = array_values(array_filter($retained, static fn(array $post): bool =>
            $post['konto_id'] === $requested['konto_id'] && trim((string)$post['faktnr']) === trim((string)$requested['faktnr'])));
        $found = false;
        foreach ($snapshot['items'] as &$openItem) {
            if ($openItem['konto_id'] === (int)$requested['konto_id'] && $openItem['faktnr'] === trim((string)$requested['faktnr'])) {
                $openItem['_posts'] = $group;
                $found = true;
                break;
            }
        }
        unset($openItem);
        if (!$found) {
            $snapshot['items'][] = $this->groupItem($group, $today, $today);
        }
        foreach ($snapshot['items'] as $item) {
            if (!in_array($id, array_map('intval', array_column($item['_posts'], 'id')), true)) {
                continue;
            }
            $dunning = $this->dunningRows($item);
            $detail = $this->decorate($item, $dunning);
            $detail['customer'] = ['id' => $item['konto_id'], 'kontonr' => $item['kontonr'], 'name' => $item['customer_name'],
                'saldo' => $snapshot['accounts'][$item['konto_id']]['saldo'],
                'phone' => $item['contact']['phone'], 'email' => $item['contact']['email']];
            $history = [];
            foreach ($item['_posts'] as $post) {
                if ((float)$post['amount'] < 0 && ($post['id'] != $item['id'] || $item['type'] === 'invoice')) {
                    $post['udlignet'] = '0';
                    $history[] = ['kind' => 'payment', 'date' => $post['transdate'],
                        'amount' => self::money($this->aging([$post], $today, $today)['openY'])];
                }
            }
            if ($item['ordre_id']) {
                foreach (($this->rows)("SELECT document,(finished_at AT TIME ZONE 'Europe/Copenhagen')::date AS date,recipient FROM document_deliveries
                    WHERE ordre_id=$1 AND state='sent' AND channel IN ('email','ean')
                        AND document IN ('faktura','rykker') AND finished_at IS NOT NULL ORDER BY finished_at,id", [$item['ordre_id']]) as $delivery) {
                    $history[] = ['kind' => $delivery['document'] === 'rykker' ? 'dunning' : 'invoice_sent',
                        'date' => $delivery['date'], 'recipient' => $delivery['recipient']];
                }
            }
            foreach ($dunning as $reminder) {
                if ($reminder['ordredate']) {
                    $history[] = ['kind' => 'dunning', 'date' => $reminder['ordredate'], 'level' => (int)substr($reminder['art'], 1)];
                }
            }
            $settled = array_values(array_filter($group, static fn(array $post): bool => $post['udlignet'] === '1'));
            $detail['settled'] = count($settled) === count($group);
            if ($detail['settled']) {
                $dates = array_filter(array_column($settled, 'udlign_date'));
                if ($dates) {
                    $history[] = ['kind' => 'settled', 'date' => max($dates)];
                }
            }
            usort($history, static fn(array $left, array $right): int => strcmp($left['date'], $right['date']));
            $detail['history'] = $history;
            $others = array_values(array_filter($snapshot['items'], static fn(array $other): bool =>
                $other['konto_id'] === $item['konto_id'] && $other['id'] !== $item['id']));
            $detail['other_open_posts'] = array_map(fn(array $other): array => $this->decorate($other), array_slice($others, 0, 5));
            $detail['unallocated_credit'] = $snapshot['accounts'][$item['konto_id']]['unallocated_credit'];
            return $detail;
        }
        return null;
    }
}

/**
 * @param resource|\PgSql\Connection|null $connection Explicit tenant, or the legacy selected connection.
 * @return OpenPostsReader Reader bound to that tenant with no session or transaction ownership.
 */
function openposts_reader($connection = null): OpenPostsReader
{
    $connection = $connection ?: ifset($GLOBALS, 'connection');
    if (!$connection) {
        throw new RuntimeException('A tenant connection is required');
    }
    $rows = static function (string $sql, array $parameters) use ($connection): array {
        $result = pg_query_params($connection, $sql, $parameters);
        if ($result === false) {
            throw new RuntimeException('Open posts query failed');
        }
        return pg_fetch_all($result) ?: [];
    };
    $settings = $rows("SELECT var_value FROM settings WHERE var_name='baseCurrency' ORDER BY id LIMIT 1", []);
    return new OpenPostsReader($rows, (string)(ifset($settings, [0, 'var_value'], 'DKK') ?: 'DKK'));
}

/**
 * @param array{konto_id?:int,status?:string,bucket?:string,sort?:string,q?:string} $filter Validated list filters.
 * @param int $limit Page size, validated by the caller.
 * @param int $offset Zero-based page offset, validated by the caller.
 * @return array{
 *   items: list<array<string,mixed>>, Decorated invoice, credit-note, fee and other groups.
 *   total: int, Filtered group count before paging.
 *   accounts: array<int,array{saldo:string,unallocated_credit:string}>, Signed debtor balances in base currency.
 *   summary: array<string,mixed>, Account-scoped totals, not narrowed by list filters or paging.
 * }
 * @see OpenPostsReader::summary()
 */
function openposts_items(array $filter, int $limit, int $offset): array
{
    return openposts_reader()->items($filter, $limit, $offset);
}

/**
 * @param int|null $kontoId Restrict to one debtor, or summarize the tenant.
 * @return array{
 *   as_of: string, Today's date in Europe/Copenhagen.
 *   currency: string, Tenant base currency for every monetary field.
 *   open_total: string, Open invoice-group rests plus signed unallocated balances.
 *   overdue_total: string, Positive net overdue debtor balances using the web control sum.
 *   overdue_count: int, Debtors with a positive overdue control balance, not invoice count.
 *   oldest_days_overdue: int, Maximum calendar age among positive open groups.
 *   buckets: array{not_due:string,d0_30:string,d31_60:string,d61_90:string,d90_plus:string}, Sum equals open_total.
 *   unallocated_credit_total: string, Signed balance not attached to a single invoice.
 * }
 */
function openposts_summary(?int $kontoId = null): array
{
    return openposts_reader()->summary($kontoId);
}

/**
 * @param list<int> $kontoIds Requested debtor ids.
 * @return array<int,string> Open base-currency balances keyed by debtor id, including zero balances.
 */
function openposts_customer_saldo(array $kontoIds): array
{
    return openposts_reader()->saldo($kontoIds);
}

/**
 * @param array{order_art?:string|null,art?:string|null} $row Ledger row with its joined order art.
 * @return 'invoice'|'credit_note'|'dunning_fee'|'other' Ledger-backed document classification.
 */
function openposts_item_type(array $row): string
{
    $art = (string)ifset($row, 'order_art', ifset($row, 'art', ''));
    if (in_array($art, ['R1', 'R2', 'R3'], true)) {
        return 'dunning_fee';
    }
    return $art === 'DO' ? 'invoice' : ($art === 'DK' ? 'credit_note' : 'other');
}

<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/lager/productCatalog.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Serialize legacy wall-clock modtime in the configured PostgreSQL timezone as UTC ISO instants.

require_once __DIR__ . '/productVat.php';
require_once __DIR__ . '/../../restapi/core/Paging.php';
require_once __DIR__ . '/../../restapi/core/JWT.php';
require_once __DIR__ . '/../../restapi/core/JwtSecretProvisioning.php';
require_once __DIR__ . '/../mobileUpdates.php';
require_once __DIR__ . '/../../restapi/models/finans/VatModel.php';

/** @return array Catalogue response for a validated HTTP query on the authenticated tenant. */
function product_catalog_query(string $mode, array $query, bool $seller = false): array
{
    if (!in_array($mode, $seller ? ['search', 'barcode', 'changes'] : ['search', 'barcode', 'changes', 'detail'], true)) {
        throw new MobileT2Error('validation_failed', 422, ['reason' => 'missing_fields']);
    }
    $year = array_key_exists('year', $query) ? product_catalog_integer($query['year'], 1) : null;
    $year = VatModel::resolveReferenceYear($year);
    if ($year === null) {
        throw new MobileT2Error('validation_failed', 422, ['reason' => 'outside_fiscal_year']);
    }
    $department = product_catalog_integer(ifset($query, 'afd', 0), 0);
    mobileTenantUpdates();
    $catalogue = new ProductCatalog($year, $department, $seller);
    return match ($mode) {
        'search' => $catalogue->search($query),
        'barcode' => $catalogue->barcode(ProductCatalog::text(ifset($query, 'code', ''), 200)),
        'detail' => $catalogue->detail(product_catalog_integer(ifset($query, 'id'), 1)),
        'changes' => $catalogue->changes(array_key_exists('cursor', $query) ? ProductCatalog::text($query['cursor'], 2048) : null, $query),
    };
}

/** @return int Canonical bounded SQL-safe positive/nonnegative query integer. */
function product_catalog_integer($value, int $minimum): int
{
    if ((!is_int($value) && (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)$/D', $value)))
        || strlen((string)$value) > 10 || (int)$value < $minimum || (int)$value > 2147483647) {
        throw new MobileT2Error('validation_failed', 422, ['reason' => 'missing_fields']);
    }
    return (int)$value;
}

/** Session-free catalogue on the currently selected tenant connection. */
final class ProductCatalog
{
    private const PRODUCT_COLUMNS = '*, to_char((modtime AT TIME ZONE current_setting(\'TimeZone\')) AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS mobile_modtime';

    private MobileSchema $store;
    private $connection;
    private string $database;
    private string $currency;

    public function __construct(private int $year, private int $department = 0, private bool $seller = false)
    {
        $this->connection = ifset($GLOBALS, 'connection');
        $this->store = new MobileSchema($this->connection);
        $this->database = $this->store->rows('SELECT current_database() AS db')[0]['db'];
        $rows = $this->store->rows("SELECT var_value FROM settings WHERE var_name='baseCurrency' AND var_grp='globals' ORDER BY id LIMIT 1");
        $this->currency = trim((string)ifset($rows, [0, 'var_value'], '')) ?: 'DKK';
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int} */
    public function search(array $query = []): array
    {
        $paging = new Paging($query);
        $search = self::text(ifset($query, 'q', ''), 200);
        $where = '';
        if ($search !== '') {
            $pattern = '%' . str_replace('*', '%', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search)) . '%';
            $literal = $this->store->literal($pattern);
            $where = "WHERE varenr ILIKE $literal OR beskrivelse ILIKE $literal OR varenr_alias ILIKE $literal OR stregkode ILIKE $literal";
        }
        $sort = self::text(ifset($query, 'sort', 'varenr'), 30);
        $order = Paging::order($sort, ['varenr' => 'varenr', 'name' => 'beskrivelse', 'id' => 'id']);
        $total = (int)$this->store->rows("SELECT count(*) AS total FROM varer $where")[0]['total'];
        $rows = $this->store->rows('SELECT ' . self::PRODUCT_COLUMNS . " FROM varer $where ORDER BY $order " . $paging->sql());
        return $paging->response(array_map([$this, 'item'], $rows), $total);
    }

    /** @return array{items:list<array>} */
    public function barcode(string $code): array
    {
        $code = self::text($code, 200);
        if ($code === '') {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'missing_fields']);
        }
        $literal = $this->store->literal($code);
        $rows = $this->store->rows('SELECT ' . self::PRODUCT_COLUMNS . " FROM varer WHERE varenr=$literal OR varenr_alias=$literal OR stregkode=$literal ORDER BY id LIMIT 1001");
        if (count($rows) > 1000) {
            throw new MobileT2Error('payload_too_large', 413);
        }
        return ['items' => array_map([$this, 'item'], $rows)];
    }

    /** @return array Product plus stock and the web engine's reservation quantities. */
    public function detail(int $id): array
    {
        $rows = $this->store->rows('SELECT ' . self::PRODUCT_COLUMNS . " FROM varer WHERE id=$id");
        if (!$rows) {
            throw new MobileT2Error('not_found', 404);
        }
        $item = $this->item($rows[0]);
        $stock = $this->store->rows("SELECT lager, sum(beholdning) AS quantity FROM lagerstatus WHERE vare_id=$id GROUP BY lager ORDER BY lager");
        $item['stock'] = [];
        foreach ($stock as $row) {
            $warehouse = (int)$row['lager'];
            $names = $this->store->rows("SELECT beskrivelse FROM grupper WHERE art='LG' AND kodenr=$warehouse
                ORDER BY CASE WHEN fiscal_year={$this->year} THEN 0 ELSE 1 END, id DESC LIMIT 1");
            $item['stock'][] = ['warehouse_no' => $warehouse, 'name' => (string)ifset($names, [0, 'beskrivelse'], ''),
                'quantity' => self::decimal($row['quantity'], 3)];
        }
        $reserved = find_beholdning($id, false);
        $item['reserved'] = ['in_quotes' => self::decimal($reserved[1], 3),
            'in_orders' => self::decimal($reserved[2], 3),
            'on_purchase_orders' => self::decimal($reserved[4], 3)];
        return $item;
    }

    /**
     * An authenticated snapshot cursor carries its original xmin through every id page.
     * Delta pages advance over raw events, not deduplicated products, below a fresh safe xmin.
     * @return array{items:list<array>,deleted:list<int>,next_cursor:string,has_more:bool,server_time:string}
     */
    public function changes(?string $cursor = null, array $query = []): array
    {
        $paging = new Paging($query, true);
        if ($paging->offset !== 0) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_paging']);
        }
        $state = $cursor === null ? null : $this->decodeCursor($cursor);
        $ownsTransaction = pg_transaction_status($this->connection) === PGSQL_TRANSACTION_IDLE;
        if ($ownsTransaction) {
            $this->store->execute('BEGIN');
        }
        try {
            $clock = $this->store->rows("SELECT txid_snapshot_xmin(txid_current_snapshot())::text AS watermark,
                extract(epoch FROM now())::bigint::text AS epoch,
                to_char(now() AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS time")[0];
            $this->store->rows('SELECT pg_advisory_xact_lock(730020904)');
            $floor = $this->prune($clock['watermark']);
            if ($state !== null && ((int)$state['issued_at'] < (int)$clock['epoch'] - 90 * 86400
                || self::before([$state['txid'], $state['event_id']], $floor))) {
                if ($ownsTransaction) {
                    $this->store->execute('COMMIT');
                    $ownsTransaction = false;
                }
                throw new MobileT2Error('gone', 410);
            }
            $state = $state ?? ['phase' => 'snapshot', 'txid' => $clock['watermark'], 'event_id' => '0',
                'after' => 0, 'issued_at' => $clock['epoch']];
            $fetch = $paging->limit + 1;
            $deleted = [];
            if ($state['phase'] === 'snapshot') {
                $after = (int)$state['after'];
                $rows = $this->store->rows('SELECT ' . self::PRODUCT_COLUMNS . " FROM varer WHERE id>$after ORDER BY id LIMIT $fetch");
                $more = count($rows) > $paging->limit;
                $rows = array_slice($rows, 0, $paging->limit);
                $items = array_map([$this, 'item'], $rows);
                if ($more) {
                    $state['after'] = (int)end($rows)['id'];
                } else {
                    $state['phase'] = 'changes';
                    $state['after'] = 0;
                }
            } else {
                $txid = $this->store->literal($state['txid']);
                $eventId = $this->store->literal($state['event_id']);
                $watermark = $this->store->literal($clock['watermark']);
                $events = $this->store->rows("SELECT id,vare_id,deleted,txid::text FROM mobile_catalogue_changes
                    WHERE (txid,id)>($txid::bigint,$eventId::bigint) AND txid<$watermark::bigint
                    ORDER BY txid,id LIMIT $fetch");
                $more = count($events) > $paging->limit;
                $events = array_slice($events, 0, $paging->limit);
                $latest = [];
                foreach ($events as $event) {
                    $latest[(int)$event['vare_id']] = $event;
                }
                $items = [];
                foreach ($latest as $id => $event) {
                    $product = $this->store->rows('SELECT ' . self::PRODUCT_COLUMNS . ' FROM varer WHERE id=' . (int)$id);
                    if ($event['deleted'] === 't' || !$product) {
                        $deleted[] = (int)$id;
                    } else {
                        $items[] = $this->item($product[0]);
                    }
                }
                if ($events) {
                    $last = end($events);
                    $state['txid'] = $last['txid'];
                    $state['event_id'] = $last['id'];
                }
                $state['issued_at'] = $clock['epoch'];
            }
            $result = ['items' => $items, 'deleted' => $deleted, 'next_cursor' => $this->encodeCursor($state),
                'has_more' => $more, 'server_time' => $clock['time']];
            if ($ownsTransaction) {
                $this->store->execute('COMMIT');
            }
            return $result;
        } catch (Throwable $error) {
            if ($ownsTransaction) {
                $this->store->execute('ROLLBACK');
            }
            throw $error;
        }
    }

    /** @return array Catalogue's safe, explicitly selected product projection. */
    private function item(array $row): array
    {
        $id = (int)$row['id'];
        $vat = product_vat_rate((int)$row['gruppe'], $this->year, $this->department, 25.0);
        $account = $vat['revenue_account'];
        $codes = $this->store->rows("SELECT moms FROM kontoplan WHERE kontonr=$account AND regnskabsaar={$this->year} LIMIT 1");
        $stock = $this->store->rows("SELECT COALESCE(sum(beholdning),0) AS total FROM lagerstatus WHERE vare_id=$id")[0];
        $item = ['id' => $id, 'varenr' => (string)$row['varenr'], 'name' => (string)$row['beskrivelse'],
            'barcode' => (string)ifset($row, 'stregkode', ''), 'barcode_alias' => (string)ifset($row, 'varenr_alias', ''),
            'unit' => (string)$row['enhed'], 'price' => self::decimal($row['salgspris']), 'currency' => $this->currency,
            'vat_rate' => self::decimal($vat['rate']), 'vat_code' => (string)ifset($codes, [0, 'moms'], ''),
            'momsfri' => $vat['momsfri'], 'reverse_charge_group' => $vat['omvare'], 'is_service' => !$vat['lagerfort'],
            'stock_total' => self::decimal($stock['total'], 3), 'min_stock' => self::decimal(ifset($row, 'min_lager', 0), 3),
            'closed' => (bool)ifset($row, 'lukket', ''), 'modtime' => ifset($row, 'mobile_modtime'),
            'missing_vat_account' => $vat['missing_vat_account'], 'configuration_error' => $vat['error']];
        if (!$this->seller) {
            $item['cost_price'] = self::decimal($row['kostpris']);
        }
        return $item;
    }

    /** @return array{0:string,1:string} Durable conservative floor, including late commits with old transaction timestamps. */
    private function prune(string $watermark): array
    {
        $rows = $this->store->rows("SELECT id,var_value FROM settings WHERE var_grp='mobile' AND var_name='catalogue_retention_floor' AND user_id=0 ORDER BY id LIMIT 1");
        $floor = explode(':', (string)ifset($rows, [0, 'var_value'], '0:0'));
        $safe = $this->store->literal($watermark);
        $removed = $this->store->rows("WITH removed AS (
            DELETE FROM mobile_catalogue_changes WHERE id IN (SELECT id FROM mobile_catalogue_changes
                WHERE changed_at<now()-interval '90 days' AND txid<$safe::bigint ORDER BY changed_at,id LIMIT 1000)
            RETURNING txid,id) SELECT txid::text,id::text FROM removed ORDER BY txid DESC,id DESC LIMIT 1");
        if ($removed && self::before($floor, [$removed[0]['txid'], $removed[0]['id']])) {
            $floor = [$removed[0]['txid'], $removed[0]['id']];
            $value = $this->store->literal(implode(':', $floor));
            if ($rows) {
                $settingId = (int)$rows[0]['id'];
                $this->store->execute("UPDATE settings SET var_value=$value WHERE id=$settingId");
            } else {
                $this->store->execute("INSERT INTO settings (var_grp,var_name,var_value,user_id)
                    VALUES ('mobile','catalogue_retention_floor',$value,0)");
            }
        }
        return $floor;
    }

    /** @return bool Comparison of PostgreSQL bigint cursor pairs without float conversion. */
    private static function before(array $left, array $right): bool
    {
        return (int)$left[0] < (int)$right[0] || ((int)$left[0] === (int)$right[0] && (int)$left[1] < (int)$right[1]);
    }

    private function encodeCursor(array $state): string
    {
        $state['version'] = 1;
        $state['database'] = $this->database;
        $state['year'] = $this->year;
        $state['department'] = $this->department;
        $state['seller'] = $this->seller;
        $payload = rtrim(strtr(base64_encode(json_encode($state, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        return $payload . '.' . hash_hmac('sha256', 'catalogue:' . $payload, _jwtLoadSecret());
    }

    /** @return array{phase:string,txid:string,event_id:string,after:int,issued_at:string} */
    private function decodeCursor(string $cursor): array
    {
        $parts = explode('.', $cursor);
        if (strlen($cursor) > 2048 || count($parts) !== 2 || !preg_match('/^[A-Za-z0-9_-]+$/D', $parts[0])
            || !hash_equals(hash_hmac('sha256', 'catalogue:' . $parts[0], _jwtLoadSecret()), $parts[1])) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_paging']);
        }
        $state = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        if (!is_array($state) || ifset($state, 'version') !== 1 || ifset($state, 'database') !== $this->database
            || ifset($state, 'year') !== $this->year || ifset($state, 'department') !== $this->department
            || ifset($state, 'seller') !== $this->seller || !in_array(ifset($state, 'phase'), ['snapshot', 'changes'], true)
            || !is_int(ifset($state, 'after')) || $state['after'] < 0) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_paging']);
        }
        foreach (['txid', 'event_id', 'issued_at'] as $field) {
            $value = ifset($state, $field);
            if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,18})$/D', $value)
                || (string)(int)$value !== $value) {
                throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_paging']);
            }
        }
        return $state;
    }

    public static function text($value, int $maximum): string
    {
        if (!is_string($value) || strlen($value) > $maximum || str_contains($value, "\0")) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'missing_fields']);
        }
        return trim($value);
    }

    private static function decimal($value, int $places = 2): string
    {
        return number_format((float)$value, $places, '.', '');
    }
}

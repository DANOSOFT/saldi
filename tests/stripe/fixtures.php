<?php
// 20261022 CDX/LH Real tenant fixture rows, rolled back by StripeDatabaseTestCase.

/** Minimal Saldi invoice fixtures; these do not run posting or send mail. */
final class StripeFixtures
{
    public const TODAY = '2026-01-10';
    public const INVOICE_DATE = '2026-01-08';
    public const DUE_DATE = '2026-01-16';

    /**
     * Insert a fixture with a free id even when a restored tenant's sequences lag.
     * Explicit fixture ids avoid changing the restored tables' sequence values.
     *
     * @param string $table Trusted table name.
     * @param array<string, scalar|null> $values Trusted columns and bound values.
     * @return int Inserted id.
     */
    public static function insert(string $table, array $values): int
    {
        foreach (array_merge([$table], array_keys($values)) as $identifier) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
                throw new InvalidArgumentException('Invalid fixture identifier.');
            }
        }
        $row = pg_fetch_assoc(StripeTestEnvironment::query("SELECT coalesce(max(id), 0)+1 AS id FROM $table"));
        $id = (int)$row['id'];
        $values = ['id' => $id] + $values;
        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_map(static fn($n) => '$' . $n, range(1, count($values))));
        $params = array_map(static fn($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, array_values($values));
        StripeTestEnvironment::query("INSERT INTO $table ($columns) VALUES ($placeholders)", $params);
        return $id;
    }

    /** Create a debtor with ordinary email delivery and Netto 8 terms. */
    public static function debtor(array $overrides = []): int
    {
        $r = pg_fetch_assoc(StripeTestEnvironment::query('SELECT coalesce(max(id), 0)+1 AS id FROM adresser'));
        return self::insert('adresser', array_replace([
            'art' => 'D', 'kontonr' => (string)(90000000 + (int)$r['id']),
            'firmanavn' => 'Stripe test debtor', 'kontakt' => 'Anne Test',
            'email' => 'debtor@stripe-tests.invalid', 'betalingsbet' => 'Netto',
            'betalingsdage' => 8, 'udskriv_til' => 'email',
            'stripe_fravalg' => '', 'stripe' => '', 'stripe_customer_id' => null,
            'stripe_pm_id' => null, 'stripe_date' => null, 'pbs_nr' => '',
        ], $overrides));
    }

    /** Create an uninvoiced DO order and two lines: 272.00 net + 68.00 VAT. */
    public static function order(int $debtorId, array $overrides = []): int
    {
        $debtor = self::row('adresser', $debtorId);
        $r = pg_fetch_assoc(StripeTestEnvironment::query('SELECT coalesce(max(ordrenr), 0)+1 AS n FROM ordrer'));
        $orderId = self::insert('ordrer', array_replace([
            'art' => 'DO', 'konto_id' => $debtorId, 'kontonr' => $debtor['kontonr'],
            'firmanavn' => $debtor['firmanavn'], 'kontakt' => $debtor['kontakt'],
            'email' => $debtor['email'], 'addr1' => 'Testvej 1', 'addr2' => '',
            'postnr' => '1000', 'bynavn' => 'Testby', 'cvrnr' => '',
            'status' => 1, 'ordrenr' => (int)$r['n'], 'fakturanr' => '',
            'betalingsbet' => 'Netto', 'betalingsdage' => 8,
            'udskriv_til' => 'Link', 'betalt' => '', 'nextfakt' => null,
            'ordredate' => self::INVOICE_DATE, 'fakturadate' => null,
            'sum' => '272.000', 'moms' => '68.000', 'momssats' => '25.000',
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '', 'pbs' => '',
        ], $overrides));
        foreach ([['TEST-A', 2, '100.000'], ['TEST-B', 1, '72.000']] as $index => $line) {
            self::insert('ordrelinjer', [
                'ordre_id' => $orderId, 'varenr' => $line[0], 'beskrivelse' => 'Test line ' . ($index + 1),
                'antal' => $line[1], 'pris' => $line[2], 'rabat' => '0.000',
                'enhed' => 'stk', 'posnr' => $index + 1,
            ]);
        }
        return $orderId;
    }

    /**
     * Model Saldi's issued invoice: status 4, invoice identity/date and debtor open item.
     * ordrer has no forfaldsdate column; the booked date belongs to openpost.
     *
     * @return int Order id of the issued invoice.
     */
    public static function invoice(?int $debtorId = null, array $overrides = []): int
    {
        $debtorId = $debtorId ?? self::debtor();
        $orderId = self::order($debtorId, $overrides);
        $number = (string)(90000000 + $orderId);
        StripeTestEnvironment::query('UPDATE ordrer SET status=4, fakturanr=$1, fakturadate=$2 WHERE id=$3',
            [$number, self::INVOICE_DATE, $orderId]);
        $order = self::row('ordrer', $orderId);
        $due = (new DateTimeImmutable(self::INVOICE_DATE))->modify('+' . (int)$order['betalingsdage'] . ' days')->format('Y-m-d');
        self::insert('openpost', [
            'konto_id' => $debtorId, 'konto_nr' => $order['kontonr'], 'faktnr' => $number,
            'amount' => number_format((float)$order['sum'] + (float)$order['moms'], 3, '.', ''),
            'udlignet' => '0', 'transdate' => self::INVOICE_DATE, 'forfaldsdate' => $due,
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '',
        ]);
        return $orderId;
    }

    /** Read a fixture row by its numeric id. */
    public static function row(string $table, int $id): array
    {
        if (!in_array($table, ['adresser', 'ordrer', 'ordrelinjer', 'openpost', 'stripe_payments'], true)) {
            throw new InvalidArgumentException('Unexpected fixture table.');
        }
        $row = pg_fetch_assoc(StripeTestEnvironment::query("SELECT * FROM $table WHERE id=$1", [$id]));
        if (!$row) {
            throw new RuntimeException("Missing $table fixture row $id");
        }
        return $row;
    }
}

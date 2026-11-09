<?php
// 20261109 CL/ASR Link reminder (Linkrykker) fixtures: reminder orders R1/R2/R3 shaped as debitor/ny_rykker.php makes them, the suite's own fee and interest items on reminder forms 6/7/8, and stripe_reminders rows.

/** Reminder fixtures inside the caller's test transaction (or temporary tables); nothing here commits. */
final class StripeReminderFixtures
{
    public const REMINDER_DATE = '2026-01-20';

    /** @var array{fee: int, interest: int} Items of the GEBYR rows written by forms(). */
    public static array $items = ['fee' => 0, 'interest' => 0];

    /**
     * Put this suite's fee and interest items on reminder forms 6/7/8. A restored tenant's own GEBYR rows are removed
     * first: ny_rykker.php reads them without a language filter, so several rows would make the item choice undefined.
     *
     * @param float $fee Sales price of the fee item (the reminder fee, kr.).
     * @param int $group Item group of both items.
     * @return array{fee: int, interest: int} varer.id of the fee (xb) and interest (yb) items.
     */
    public static function forms(float $fee = 100.0, int $group = 1): array
    {
        self::$items = ['fee' => self::item('Rykkergebyr', $fee, $group), 'interest' => self::item('Renter', 0, $group)];
        StripeTestEnvironment::query("DELETE FROM formularer WHERE beskrivelse = 'GEBYR'");
        foreach ([6, 7, 8] as $form) {
            StripeFixtures::insert('formularer', ['formular' => $form, 'art' => 2, 'beskrivelse' => 'GEBYR',
                'xb' => self::$items['fee'], 'yb' => self::$items['interest'], 'str' => 0, 'sprog' => 'Dansk']);
        }
        return self::$items;
    }

    /** @return int varer.id of a new item. */
    public static function item(string $name, float $price, int $group = 1): int
    {
        return StripeFixtures::insert('varer', ['varenr' => 'REM' . bin2hex(random_bytes(4)), 'beskrivelse' => $name,
            'salgspris' => $price, 'gruppe' => $group]);
    }

    /**
     * An issued invoice dated $date (due 8 days later), like StripeFixtures::invoice() with other dates.
     *
     * @param array<string, scalar|null> $overrides ordrer columns; udskriv_til defaults to Link (E-mail med link).
     * @return int ordrer.id of the invoice.
     */
    public static function invoiceOn(int $debtor, string $date, array $overrides = []): int
    {
        $id = StripeFixtures::invoice($debtor, $overrides + ['udskriv_til' => 'Link']);
        $due = (new DateTimeImmutable($date))->modify('+8 days')->format('Y-m-d');
        StripeTestEnvironment::query('UPDATE ordrer SET fakturadate = $1, ordredate = $1 WHERE id = $2', [$date, $id]);
        StripeTestEnvironment::query('UPDATE openpost SET transdate = $1, forfaldsdate = $2 WHERE konto_id = $3 AND faktnr = $4',
            [$date, $due, $debtor, StripeFixtures::row('ordrer', $id)['fakturanr']]);
        return $id;
    }

    /** @return array<string, mixed> The invoice's openpost row. */
    public static function openpost(int $invoice): array
    {
        $o = StripeFixtures::row('ordrer', $invoice);
        $row = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM openpost WHERE konto_id = $1 AND faktnr = $2 ORDER BY id',
            [(int)$o['konto_id'], $o['fakturanr']]));
        if (!$row) {
            throw new RuntimeException("Missing openpost for invoice $invoice");
        }
        return $row;
    }

    /**
     * A reminder order as ny_rykker.php creates it: one comment line per open item (ordrelinjer.enhed = openpost id),
     * an interest line (interest item) when interest > 0 and a fee line (fee item) when fee > 0; sum = fee + interest.
     *
     * @param array<int, int> $invoices Issued invoices of $debtor, listed in this order.
     * @param array{
     *   level?: int,          1..3 (R1/R2/R3), default 1.
     *   ordrenr?: int,        Shared by the levels of one chain; default a new number.
     *   fee?: float,          Fee line price, default 100.
     *   interest?: float,     Interest line price, default 0 (no line).
     *   date?: string,        Reminder date (ordredate/fakturadate), default REMINDER_DATE.
     *   status?: int,         2 = open (default), 4 = booked.
     *   email?: string,       Default the debtor's e-mail.
     *   mail_fakt?: string,   Default ''.
     *   openposts?: array<int, int>,  Extra openpost ids listed after the invoices (credits, unmapped items).
     *   valuta?: string, valutakurs?: float, sprog?: string, betalingsdage?: int,
     * } $o
     * @return int ordrer.id of the reminder.
     */
    public static function reminder(int $debtor, array $invoices, array $o = []): int
    {
        $level = $o['level'] ?? 1;
        $ordrenr = $o['ordrenr'] ?? (int)pg_fetch_result(StripeTestEnvironment::query(
            "SELECT coalesce(max(ordrenr), 0) + 1 FROM ordrer WHERE art LIKE 'R%'"), 0, 0);
        $fee = (float)($o['fee'] ?? 100);
        $interest = (float)($o['interest'] ?? 0);
        $date = $o['date'] ?? self::REMINDER_DATE;
        $d = StripeFixtures::row('adresser', $debtor);
        // projekt stays NULL as in ny_rykker.php (bogfor_nu multiplies it).
        $id = StripeFixtures::insert('ordrer', [
            'art' => 'R' . $level, 'konto_id' => $debtor, 'kontonr' => $d['kontonr'], 'firmanavn' => $d['firmanavn'],
            'kontakt' => $d['kontakt'], 'email' => $o['email'] ?? $d['email'], 'mail_fakt' => $o['mail_fakt'] ?? '',
            'addr1' => 'Testvej 1', 'postnr' => '1000', 'bynavn' => 'Testby',
            'ordrenr' => $ordrenr, 'fakturanr' => $ordrenr . '-' . $level, 'status' => $o['status'] ?? 2, 'betalt' => '-',
            'ordredate' => $date, 'levdate' => $date, 'fakturadate' => $date,
            'betalingsbet' => 'Netto', 'betalingsdage' => $o['betalingsdage'] ?? 8,
            'valuta' => $o['valuta'] ?? 'DKK', 'valutakurs' => $o['valutakurs'] ?? 100, 'sprog' => $o['sprog'] ?? '',
            'momssats' => 0, 'sum' => $fee + $interest, 'moms' => 0, 'felt_5' => '',
        ]);
        $pos = 0;
        $openposts = array_map(static fn($invoice) => (int)self::openpost($invoice)['id'], $invoices);
        foreach (array_merge($openposts, $o['openposts'] ?? []) as $openpostId) {
            $op = pg_fetch_assoc(StripeTestEnvironment::query('SELECT transdate, faktnr FROM openpost WHERE id = $1', [$openpostId]));
            StripeFixtures::insert('ordrelinjer', ['ordre_id' => $id, 'posnr' => ++$pos, 'enhed' => (string)$openpostId,
                'serienr' => $op ? $op['transdate'] : $date, 'beskrivelse' => 'Faktura ' . ($op ? $op['faktnr'] : '')]);
        }
        foreach ([['interest', $interest], ['fee', $fee]] as [$kind, $price]) {
            if ($price > 0) {
                $item = pg_fetch_assoc(StripeTestEnvironment::query('SELECT id, varenr, beskrivelse FROM varer WHERE id = $1', [self::$items[$kind]]));
                StripeFixtures::insert('ordrelinjer', ['ordre_id' => $id, 'posnr' => ++$pos, 'vare_id' => (int)$item['id'],
                    'varenr' => $item['varenr'], 'beskrivelse' => $item['beskrivelse'], 'antal' => 1, 'pris' => $price,
                    'rabat' => 0, 'serienr' => $date]);
            }
        }
        return $id;
    }

    /**
     * A debtor with one issued invoice and a 1st reminder for it.
     *
     * @return array{debtor: int, invoice: int, reminder: int}
     */
    public static function linkReminder(array $debtorFields = [], array $invoiceFields = [], array $reminder = []): array
    {
        $debtor = StripeFixtures::debtor($debtorFields);
        $invoice = StripeFixtures::invoice($debtor, $invoiceFields + ['udskriv_til' => 'Link']);
        return ['debtor' => $debtor, 'invoice' => $invoice, 'reminder' => self::reminder($debtor, [$invoice], $reminder)];
    }

    /** @return int ordrer.id of the next level of $r's chain (same ordrenr and debtor), listing the same invoice. */
    public static function nextLevel(array $r, int $level, string $date, array $o = []): int
    {
        $ordrenr = (int)StripeFixtures::row('ordrer', $r['reminder'])['ordrenr'];
        return self::reminder($r['debtor'], [$r['invoice']], ['level' => $level, 'ordrenr' => $ordrenr, 'date' => $date] + $o);
    }

    /**
     * Insert the reminder's stripe_reminders row (default: a link reminder mailed on the reminder date, fee on the next invoice).
     *
     * @param array<string, scalar|null> $values Columns to override.
     */
    public static function linkRow(int $rykkerId, array $values = []): void
    {
        $o = StripeFixtures::row('ordrer', $rykkerId);
        $draft = ($values['status'] ?? 'sent') === 'draft';   // registered, not mailed yet
        $values = array_replace([
            'rykker_id' => $rykkerId, 'konto_id' => (int)$o['konto_id'], 'ordrenr' => (int)$o['ordrenr'],
            'level' => (int)substr((string)$o['art'], 1), 'status' => 'sent', 'fee_mode' => 'next',
            'channel' => $draft ? null : 'link', 'mailed_at' => $draft ? null : $o['fakturadate'] . ' 15:30:00',
            'mailed_to' => $draft ? null : $o['email'], 'fee_ore' => 10000, 'interest_ore' => 0,
        ], $values);
        $placeholders = implode(', ', array_map(static fn($n) => '$' . $n, range(1, count($values))));
        StripeTestEnvironment::query('INSERT INTO stripe_reminders (' . implode(', ', array_keys($values)) . ") VALUES ($placeholders)",
            array_values($values));
    }

    /**
     * Test doubles for includes/ordrefunc.php's opret_ordrelinje() and levering(), so a posting hook that adds a fee line runs
     * without the stock and batch engine (which ends the process on fixture data). Call it only in a test that runs in its own
     * process (#[RunInSeparateProcess]): the doubles stay defined for the rest of the process.
     */
    public static function orderFunctionDoubles(): void
    {
        if (!function_exists('opret_ordrelinje')) {
            function opret_ordrelinje($id, $vare_id, $varenr, $antal, $beskrivelse, $pris, $rabat_ny, $procent, $art, $momsfri, $posnr, $linje_id, $incl_moms, $kdo, $rabatart, $kopi, $saet, $fast_db, $lev_varenr, $lager, $linje)
            {
                return StripeFixtures::insert('ordrelinjer', ['ordre_id' => (int)$id, 'vare_id' => (int)$vare_id, 'varenr' => (string)$varenr,
                    'antal' => $antal, 'beskrivelse' => (string)$beskrivelse, 'pris' => $pris, 'rabat' => $rabat_ny ?: 0,
                    'procent' => $procent ?: 100, 'momsfri' => (string)$momsfri, 'momssats' => $momsfri === 'on' ? 0 : 25,
                    'posnr' => (int)$posnr, 'kdo' => (string)$kdo, 'rabatart' => (string)$rabatart, 'enhed' => 'stk']);
            }
        }
        if (!function_exists('levering')) {
            function levering($id, $hurtigfakt, $genfakt, $webservice = false)
            {
                return 'OK';
            }
        }
        foreach (['opret_ordrelinje', 'levering'] as $name) {
            if ((new ReflectionFunction($name))->getFileName() !== __FILE__) {
                throw new LogicException("$name is the real includes/ordrefunc.php function here, not the test double");
            }
        }
    }

    /** @return array<string, mixed>|null The reminder's stripe_reminders row. */
    public static function linkRowOf(int $rykkerId): ?array
    {
        return pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_reminders WHERE rykker_id = $1', [$rykkerId])) ?: null;
    }
}

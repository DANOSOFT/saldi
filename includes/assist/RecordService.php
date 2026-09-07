<?php
// 20260907 CDX/LH Bounded snapshots of saved journal and sales-invoice records.
require_once __DIR__ . '/RecordAuth.php';
require_once __DIR__ . '/RecordRules.php';

final class SaldiAssistRecordService
{
    public const MAX_ROWS = 2000;

    public function __construct(private PDO $pdo, private int $year)
    {
    }

    /** @param array<int,mixed> $parameters
     *  @return array<int,array<string,mixed>> */
    private function rows(string $sql, array $parameters = [], int $limit = self::MAX_ROWS): array
    {
        $query = $this->pdo->prepare($sql . ' LIMIT ' . ($limit + 1));
        $query->execute($parameters);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > $limit) {
            throw new SaldiAssistFailure('record_too_large', 413);
        }
        return $rows;
    }

    /** @return array<string,array<string,mixed>> */
    private function groups(): array
    {
        $groups = [];
        foreach ($this->rows("SELECT art, kodenr, kode, box1, box2, box3, box4, box5 FROM grupper WHERE fiscal_year = ? OR art = 'VK' OR (art = 'RA' AND kodenr = ?) OR (art = 'DIV' AND kodenr = 3) ORDER BY id", [$this->year, $this->year], 10000) as $group) {
            $groups[$group['art'] . ':' . $group['kodenr']] = $group;
        }
        return $groups;
    }

    /** @param array<string,mixed> $data */
    private function revision(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{header:array,rows:array,reference:array} */
    private function journal(int $id): array
    {
        $header = saldi_assist_one($this->pdo, 'SELECT id, kladdenote, bogfort, kladdedate FROM kladdeliste WHERE id = ?', [$id]);
        if (!$header) {
            throw new SaldiAssistFailure('record_not_found', 404);
        }
        // SELECT * internally tolerates releases predating per-line VAT overrides. Nothing is returned without projection below.
        $rows = $this->rows('SELECT * FROM kassekladde WHERE kladde_id = ? ORDER BY id', [$id]);
        $groups = $this->groups();
        $accounts = [];
        foreach ($this->rows('SELECT kontonr, kontotype, lukket, moms FROM kontoplan WHERE regnskabsaar = ? ORDER BY kontonr, id', [$this->year], 10000) as $account) {
            $accounts['F:' . $account['kontonr']] = $account;
        }
        $numbers = [];
        foreach ($rows as $row) {
            foreach (['debet' => 'd_type', 'kredit' => 'k_type'] as $side => $field) {
                if (in_array(trim((string)$row[$field]), ['D', 'K'], true) && !empty($row[$side])) {
                    $numbers[(string)$row[$side]] = (string)$row[$side];
                }
            }
        }
        if ($numbers) {
            $placeholders = implode(',', array_fill(0, count($numbers), '?'));
            foreach ($this->rows("SELECT kontonr, art, gruppe FROM adresser WHERE kontonr IN ($placeholders) AND art IN ('D','K') ORDER BY id", array_values($numbers), 4000) as $account) {
                $accounts[$account['art'] . ':' . $account['kontonr']] = $account;
            }
        }
        $fiscal = $groups['RA:' . $this->year] ?? [];
        $period = null;
        if (checkdate((int)($fiscal['box1'] ?? 0), 1, (int)($fiscal['box2'] ?? 0))
            && checkdate((int)($fiscal['box3'] ?? 0), 1, (int)($fiscal['box4'] ?? 0))) {
            $start = sprintf('%04d-%02d-01', $fiscal['box2'], $fiscal['box1']);
            $end = new DateTimeImmutable(sprintf('%04d-%02d-01', $fiscal['box4'], $fiscal['box3']));
            $period = ['start' => $start, 'end' => $end->format('Y-m-t')];
        }
        $closed = [];
        if (saldi_assist_one($this->pdo, "SELECT to_regclass('moms_periode_luk') AS name")['name']) {
            foreach ($this->rows("SELECT kalender_aar, kalender_maaned FROM moms_periode_luk WHERE status = 'closed' ORDER BY kalender_aar, kalender_maaned") as $month) {
                $closed[] = sprintf('%04d-%02d', $month['kalender_aar'], $month['kalender_maaned']);
            }
        }
        $rates = [];
        foreach ($this->rows('SELECT dates.valuta, dates.transdate, rate.kurs FROM
            (SELECT DISTINCT valuta, transdate FROM kassekladde WHERE kladde_id = ? AND valuta <> 0 AND transdate IS NOT NULL) dates
            LEFT JOIN LATERAL (SELECT kurs FROM valuta WHERE gruppe = dates.valuta AND valdate <= dates.transdate ORDER BY valdate DESC, id DESC LIMIT 1) rate ON true
            ORDER BY dates.valuta, dates.transdate', [$id]) as $rate) {
            $rates[$rate['valuta'] . ':' . $rate['transdate']] = $rate['kurs'];
        }
        $draft = saldi_assist_one($this->pdo, 'SELECT 1 FROM tmpkassekl WHERE kladde_id = ? LIMIT 1', [$id]) !== null;
        return ['header' => $header, 'rows' => $rows, 'reference' => [
            'period' => $period, 'closed_months' => $closed, 'accounts' => $accounts,
            'groups' => $groups, 'rates' => $rates, 'has_draft' => $draft,
        ]];
    }

    /** @param array<int,int> $rowIds
     *  @return array<string,mixed> */
    public function execute(string $operation, string $kind, int $id, array $rowIds = []): array
    {
        $allowed = $kind === 'journal' ? ['get_journal_context', 'validate_journal'] : ['get_invoice_status'];
        if (!in_array($operation, $allowed, true)) {
            throw new SaldiAssistFailure('operation_forbidden');
        }
        if ($kind === 'invoice') {
            return $this->invoice($id);
        }
        $data = $this->journal($id);
        $result = [
            'kind' => $kind, 'record_id' => $id, 'basis' => 'saved', 'revision' => $this->revision($data),
            'checked_at' => gmdate('c'), 'operation' => $operation,
            'status' => match ($data['header']['bogfort']) { 'V' => 'posted', 'S' => 'simulated', default => 'draft' },
            'row_count' => count($data['rows']), 'has_saved_draft' => $data['reference']['has_draft'],
        ];
        if ($operation === 'validate_journal') {
            return array_merge($result, saldi_assist_validate_journal($data['header'], $data['rows'], $data['reference']));
        }
        $selected = $rowIds ? array_filter($data['rows'], static fn(array $row): bool => in_array((int)$row['id'], $rowIds, true)) : $data['rows'];
        $result['rows'] = [];
        foreach (array_slice(array_values($selected), 0, 60) as $row) {
            $result['rows'][] = [
                'row_id' => (int)$row['id'], 'voucher' => $row['bilag'], 'date' => $row['transdate'],
                'description' => mb_substr((string)$row['beskrivelse'], 0, 300),
                'debit_type' => $row['d_type'], 'debit' => $row['debet'], 'credit_type' => $row['k_type'], 'credit' => $row['kredit'],
                'amount' => $row['amount'], 'currency_id' => (int)($row['valuta'] ?? 0),
                'vat_exempt' => !empty($row['momsfri']), 'debit_vat' => $row['debetvat'] ?? null, 'credit_vat' => $row['kreditvat'] ?? null,
            ];
        }
        $result['rows_truncated'] = count($selected) > 60;
        $result['note'] = mb_substr((string)$data['header']['kladdenote'], 0, 300);
        return $result;
    }

    /** @return array<string,mixed> */
    private function invoice(int $id): array
    {
        // No customer addresses, emails, bank details or unrestricted order columns leave this reader.
        $order = saldi_assist_one($this->pdo, "SELECT id, art, status, kontonr, ordrenr, fakturanr, fakturadate, sum, moms, valuta, betalt, felt_1, ref, afd FROM ordrer WHERE id = ? AND art IN ('DO', 'DK')", [$id]);
        if (!$order) {
            throw new SaldiAssistFailure('record_not_found', 404);
        }
        $rows = $this->rows('SELECT id, antal, leveret, leveres, vare_id, samlevare, folgevare FROM ordrelinjer WHERE ordre_id = ? ORDER BY id', [$id]);
        $groups = $this->groups();
        $settings = $this->rows("SELECT var_name, var_grp, var_value, pos_id FROM settings WHERE (var_name = 'lockedInvoiceButton' AND var_grp = 'debitor') OR (var_name = 'showPaymentLink' AND var_grp = 'deb_ordre') ORDER BY id");
        $closedPeriod = null;
        if ($order['fakturadate'] && saldi_assist_one($this->pdo, "SELECT to_regclass('moms_periode_luk') AS name")['name']) {
            $closedPeriod = saldi_assist_one($this->pdo, "SELECT kalender_aar, kalender_maaned FROM moms_periode_luk WHERE status = 'closed' AND kalender_aar = ? AND kalender_maaned = ? LIMIT 1",
                [(int)substr($order['fakturadate'], 0, 4), (int)substr($order['fakturadate'], 5, 2)]);
        }
        $data = ['order' => $order, 'rows' => $rows, 'groups' => $groups, 'settings' => $settings, 'closed_period' => $closedPeriod];
        $reasons = [];
        $add = static function (string $code, string $message) use (&$reasons): void {
            $reasons[] = ['code' => $code, 'message' => $message, 'row_ids' => [], 'severity' => 'error'];
        };
        $status = (int)$order['status'];
        if ($status >= 3) {
            $add('already_invoiced', 'Dokumentet er allerede faktureret.');
        } elseif (!$rows) {
            $add('invoice_empty', 'Der er ingen gemte ordrelinjer at fakturere.');
        } elseif (empty($order['kontonr'])) {
            $add('customer_missing', 'Salgsdokumentet mangler en kundekonto.');
        }
        if ($status < 3 && $closedPeriod) {
            $add('period_closed', 'Perioden for den gemte fakturadato er lukket for bogføring.');
        }
        $lock = '';
        $paymentLinkConfigured = false;
        foreach ($settings as $setting) {
            if ($setting['var_name'] === 'lockedInvoiceButton') {
                $lock = (string)$setting['var_value'];
            } elseif ((int)$setting['pos_id'] === (int)$order['afd'] && $setting['var_value'] === 'on') {
                $paymentLinkConfigured = true;
            }
        }
        $possiblePaymentLock = saldi_assist_invoice_payment_locked($order['betalt'], $paymentLinkConfigured, $lock, (string)$order['ref'], (string)$order['felt_1']);
        // The page also depends on the chosen terminal, card setup, delivery batches and unsaved form choices.
        // A potential gate is not evidence that its button is disabled in this user's form.
        return [
            'kind' => 'invoice', 'record_id' => $id, 'basis' => 'saved', 'revision' => $this->revision($data),
            'checked_at' => gmdate('c'), 'operation' => 'get_invoice_status',
            'status' => $reasons ? 'blocked' : 'incomplete',
            'document_state' => $status >= 3 ? 'invoiced' : ($status === 0 ? 'offer' : 'order'),
            'state_note' => $status === 0 ? 'Gemt tilbudsstatus. Knapperne afhænger også af leveringer og hurtigfakturering.' : null,
            'document_type' => $order['art'] === 'DK' ? 'credit_note' : 'sales_order',
            'saved_status' => $status, 'invoice_number' => $order['fakturanr'], 'invoice_date' => $order['fakturadate'],
            'net' => $order['sum'], 'vat' => $order['moms'], 'currency' => $order['valuta'] ?: 'DKK',
            'payment_recorded' => !empty($order['betalt']),
            'payment_gate_requires_form_check' => $possiblePaymentLock,
            'fast_invoicing' => ($groups['DIV:3']['box4'] ?? '') === 'on',
            'row_count' => count($rows), 'issues' => $reasons,
            'actions' => [['action' => 'invoice', 'enabled' => $reasons ? false : null, 'reasons' => $reasons]],
            'coverage' => ['checked' => ['saved_document_state', 'saved_lines', 'customer_selected', 'saved_invoice_period', 'payment_lock_policy'],
                'not_checked' => ['delivery_batches', 'terminal_and_card_selection', 'unsaved_form', 'posting_transaction'], 'can_post' => null],
        ];
    }
}

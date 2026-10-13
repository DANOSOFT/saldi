<?php
// 20261012 CL/LH Request-scoped REST payment reads, configured only after tenant authentication.

require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentReadModel.php';

/** Keeps model hydration small while sharing one tenant connection and batched projections. */
final class InvoicePaymentReadContext
{
    private static $connect = null;
    private static ?\Saldi\Stripe\InvoicePaymentReadModel $model = null;
    private static bool $attempted = false;
    private static array $debtors = [];
    private static array $invoices = [];

    /**
     * Called after authentication, even when selecting the same tenant again, to discard cached facts.
     * @param callable():PDO|null $connect Lazy PDO factory using the authenticated database, or null when unsupported.
     */
    public static function configure(?callable $connect): void
    {
        self::$connect = $connect;
        self::$model = null;
        self::$attempted = false;
        self::$debtors = [];
        self::$invoices = [];
    }

    /** @param list<int> $ids */
    public static function prepareDebtors(array $ids): void
    {
        $ids = array_values(array_diff(array_unique(array_map('intval', $ids)), array_keys(self::$debtors)));
        if (!$ids) return;
        $model = self::model();
        self::$debtors += $model ? $model->debtorStatuses($ids) : array_fill_keys($ids, null);
    }

    /** @param list<int> $ids */
    public static function prepareInvoices(array $ids): void
    {
        $ids = array_values(array_diff(array_unique(array_map('intval', $ids)), array_keys(self::$invoices)));
        if (!$ids) return;
        $model = self::model();
        self::$invoices += $model ? $model->invoicePayments($ids) : array_fill_keys($ids, null);
    }

    /**
     * @return array<string,mixed>|null
     * @see \Saldi\Stripe\InvoicePaymentReadModel::debtorStatuses()
     */
    public static function debtor(int $id): ?array
    {
        self::prepareDebtors([$id]);
        return self::$debtors[$id] ?? null;
    }

    /**
     * @return array<string,mixed>|null
     * @see \Saldi\Stripe\InvoicePaymentReadModel::invoicePayments()
     */
    public static function invoice(int $id): ?array
    {
        self::prepareInvoices([$id]);
        return self::$invoices[$id] ?? null;
    }

    private static function model(): ?\Saldi\Stripe\InvoicePaymentReadModel
    {
        if (!self::$attempted) {
            self::$attempted = true;
            if (self::$connect !== null) {
                try {
                    self::$model = new \Saldi\Stripe\InvoicePaymentReadModel((self::$connect)());
                } catch (\PDOException $e) {
                    // Connection details may contain credentials; expose no raw exception to REST.
                    error_log('REST: betalingsoplysninger er utilgængelige (PDO-forbindelse).');
                }
            }
        }
        return self::$model;
    }
}

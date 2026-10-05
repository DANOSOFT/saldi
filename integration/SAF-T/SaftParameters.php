<?php
/**
 * Everything the SAF-T 2.1 export needs to know about one run.
 *
 * Built by the caller (finans/saft21Creator.php) from the same inputs the 1.0 export posts, and handed to
 * AuditFile, which passes it on to the parts that need it.
 */
final class SaftParameters {
    public function __construct(
        /** Fiscal year, grupper.kodenr where art = 'RA' (the 1.0 form field "regnaar"). */
        public readonly int $fiscalYearId,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        /** First account number to include, or null for no lower limit ("konto_fra"). */
        public readonly ?int $accountFrom = null,
        /** Last account number to include, or null for no upper limit ("konto_til"). */
        public readonly ?int $accountTo = null,
        /** Whether to also write the SourceDocuments section. */
        public readonly bool $includeSourceDocuments = false,
    ) {
    }
}

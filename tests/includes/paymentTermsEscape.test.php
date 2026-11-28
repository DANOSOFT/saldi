<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * MB-198: the payment terms (betalingsbet) posted by the account lookup's "Opret ny kunde" form
 * must be one of Kontant, Netto, Lb. md. or Forud (otherwise Netto), create_debtor() must escape
 * the value in its insert, and the till's opdater_konto() must escape it in its update ordrer.
 *
 * These files need a database connection and a logged-in session to run, so this checks the
 * relevant source blocks in plain PHP; it does not execute the SQL.
 */
final class paymentTermsEscape extends TestCase
{
    private function source(string $relative): string
    {
        $src = file_get_contents(__DIR__ . '/../../' . $relative);
        $this->assertIsString($src, "$relative could not be read");
        return $src;
    }

    private function between(string $src, string $from, string $to): string
    {
        $start = strpos($src, $from);
        $this->assertNotFalse($start, "marker missing: $from");
        $end = strpos($src, $to, $start + strlen($from));
        $this->assertNotFalse($end, "marker missing: $to");
        return substr($src, $start, $end - $start);
    }

    public function testCreateCustomerAcceptsOnlyTheFourPaymentTerms(): void
    {
        $block = $this->between(
            $this->source('debitor/ordre.php'),
            "if (isset(\$_POST['create_debtor'])) {",
            '$konto_id = create_debtor('
        );
        $this->assertMatchesRegularExpression(
            "/if\\s*\\(\\s*!in_array\\(\\s*\\\$betalingsbet\\s*,\\s*array\\(\\s*'Kontant'\\s*,\\s*'Netto'\\s*,\\s*'Lb\\. md\\.'\\s*,\\s*'Forud'\\s*\\)\\s*,\\s*true\\s*\\)\\s*\\)\\s*\\\$betalingsbet\\s*=\\s*'Netto'\\s*;/",
            $block,
            'Opret ny kunde must fall back to Netto for any betalingsbet outside the four listed terms'
        );
    }

    public function testCreateDebtorEscapesPaymentTerms(): void
    {
        $src = $this->source('includes/stdFunc/createDebitor.php');
        $insert = $this->between($src, 'insert into adresser', 'db_modify(');
        $this->assertStringContainsString('db_escape_string($betalingsbet)', $insert);
        $this->assertStringNotContainsString("'\$betalingsbet'", $insert);
    }

    public function testTillAccountUpdateEscapesPaymentTerms(): void
    {
        $body = $this->between(
            $this->source('debitor/pos_ordre.php'),
            'function opdater_konto(',
            '# endfunc opdater_konto()'
        );
        $this->assertStringContainsString("betalingsbet='\" . db_escape_string(\$betalingsbet) . \"'", $body);
        $this->assertStringNotContainsString("betalingsbet='\$betalingsbet'", $body);
    }
}

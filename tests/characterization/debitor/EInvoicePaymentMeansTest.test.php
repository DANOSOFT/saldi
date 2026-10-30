<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261021 CL/MJ SST-873 Pins which bank details an e-invoice carries.
//
//                  MEDSHOP's invoice 205406 to Region Skåne reached the recipient with one
//                  PaymentMeans holding PaymentMeansCode 42 - a Danish account-to-account
//                  transfer - built from reg.nr. 0828 and account 0002880369. An international
//                  payment needs code 30 (credit transfer) or 58 (SEPA) with the IBAN in
//                  PayeeFinancialAccount/ID and the BIC in FinancialInstitutionBranch/ID.
//                  SST-739 put IBAN and SWIFT into Stamdata and sendInvoice() has been sending
//                  them ever since, so the fields were filled and still did not come through.
//
//                  The reason is in EasyUBL's API rather than in Saldi: JsonPaymentMeans has
//                  only bankName, bankRegNo, bankAccount, bic, iban, creditorIdentifier and
//                  paymentID, it is declared additionalProperties:false, and JsonInvoice takes
//                  one paymentMeans object rather than a list. There is no field for the code
//                  and no way to send a second block, so EasyUBL picks the code from whichever
//                  fields are filled and picks 42 whenever reg.nr. and account are among them.
//                  The one lever Saldi has is to leave those two out, which is what is pinned
//                  here: only for a foreign invoice, and only when an IBAN is actually on file,
//                  so an invoice that would lose its bank details instead keeps today's 42.
//
//                  sendInvoice() is a 400-line function that opens a database connection and
//                  posts to EasyUBL, so the derivation is lifted out of the source and
//                  evaluated here instead of being reimplemented, and no customer data is used.
final class EInvoicePaymentMeansTest extends TestCase
{
    private const API = __DIR__ . '/../../../debitor/api.php';

    /** The bank details MEDSHOP has on file, as the ticket's payload shows them. */
    private const MEDSHOP = array(
        'swift' => 'SWESDK22',
        'iban'  => 'DK7308280002880369',
    );

    private static ?string $block = null;

    /**
     * The payment-means derivation, lifted from sendInvoice().
     *
     * Everything from the currency being read to the payload being built. Lifting it rather than
     * copying it means this test cannot keep passing against a rule that has moved on.
     */
    private static function derivation(): string
    {
        if (self::$block === null) {
            $src = file_get_contents(self::API);
            self::assertNotFalse($src, 'could not read debitor/api.php');
            $at = strpos($src, '$invoiceCurrency = strtoupper(');
            self::assertNotFalse($at, 'the payment-means derivation is gone from sendInvoice()');
            $end = strpos($src, '$data = [', $at);
            self::assertNotFalse($end, 'the derivation no longer ends where the payload begins');
            self::$block = substr($src, $at, $end - $at);
        }
        return self::$block;
    }

    /**
     * Runs the real derivation for one invoice and returns what the paymentMeans would carry.
     *
     * @param string $countryCode The customer country code as SST-870's derivation settled it
     * @param string $valuta ordrer.valuta
     * @param array<string, string> $stamdata The bank fields of the adresser art='S' row
     * @return array{regNo: ?string, account: ?string, bic: string, iban: string}
     */
    private function derive(string $countryCode, string $valuta, array $stamdata = array()): array
    {
        $r_faktura = array('valuta' => $valuta);
        $adresse = $stamdata + array(
            'bank_navn'  => 'Sparekassen Fyn',
            'bank_reg'   => '0828',
            'bank_konto' => '0002880369',
            'swift'      => '',
            'iban'       => '',
        );
        eval(self::derivation());
        return array(
            'regNo'   => $paymentRegNo,
            'account' => $paymentAccount,
            'bic'     => $paymentBic,
            'iban'    => $paymentIban,
        );
    }

    // ------------------------------------------------------------ the reported case

    /** Invoice 205406: Region Skåne, country SE, invoiced in SEK, IBAN and BIC on file. */
    public function testTheReportedInvoiceSendsTheIbanAndNotTheDanishAccount(): void
    {
        $got = $this->derive('SE', 'SEK', self::MEDSHOP);

        self::assertSame('', $got['regNo'],
            'the Danish reg.nr. is still sent, so EasyUBL keeps writing PaymentMeansCode 42');
        self::assertSame('', $got['account'],
            'the Danish account is still sent, so EasyUBL keeps writing PaymentMeansCode 42');
        self::assertSame('DK7308280002880369', $got['iban'],
            'the IBAN is not sent, so there is nothing for EasyUBL to build a code 30 from');
        self::assertSame('SWESDK22', $got['bic'],
            'the BIC is not sent, so FinancialInstitutionBranch/ID would be empty');
    }

    // ------------------------------------------------------------ what must not change

    /** A Danish invoice in DKK keeps the account-to-account details and its code 42. */
    public function testADanishInvoiceKeepsTheRegNoAndAccount(): void
    {
        $got = $this->derive('DK', 'DKK', self::MEDSHOP);

        self::assertSame('0828', $got['regNo'],
            'a Danish invoice lost its reg.nr., so a domestic payment can no longer be made');
        self::assertSame('0002880369', $got['account'],
            'a Danish invoice lost its account, so a domestic payment can no longer be made');
    }

    /**
     * Greenland and the Faroe Islands reach this point as DK and pay the Danish way.
     *
     * SST-870 folds both to DK before the payment block reads $countryCode, which is why this
     * test passes DK: it pins that the payment side inherits that decision rather than making
     * its own, so Greenland is not suddenly treated as abroad.
     */
    public function testAnInvoiceInsideTheDanishVatAreaKeepsTheRegNoAndAccount(): void
    {
        $got = $this->derive('DK', 'DKK', self::MEDSHOP);

        self::assertSame('0828', $got['regNo'],
            'an invoice inside the Danish VAT area lost its reg.nr.');
    }

    /** Without an IBAN there is nothing to put in its place, so today's behaviour stands. */
    public function testAForeignInvoiceWithoutAnIbanIsUnchanged(): void
    {
        $got = $this->derive('SE', 'SEK', array('swift' => 'SWESDK22', 'iban' => ''));

        self::assertSame('0828', $got['regNo'],
            'a foreign invoice with no IBAN on file was stripped of its only bank details');
        self::assertSame('0002880369', $got['account'],
            'a foreign invoice with no IBAN on file was stripped of its only bank details');
    }

    /** An IBAN field holding only spaces is not an IBAN. */
    public function testAnIbanOfOnlySpacesCountsAsAbsent(): void
    {
        $got = $this->derive('SE', 'SEK', array('iban' => '    ', 'swift' => 'SWESDK22'));

        self::assertSame('', $got['iban'],
            'whitespace was sent as an IBAN');
        self::assertSame('0828', $got['regNo'],
            'the reg.nr. was dropped for a blank IBAN, leaving the invoice with no bank details');
    }

    /** Nothing in the derivation writes the two fields Saldi leaves empty. */
    public function testTheDerivationDoesNotAssignCreditorIdentifierOrPaymentId(): void
    {
        $src = self::derivation();

        self::assertStringNotContainsString('creditorIdentifier', $src,
            'the derivation now sets creditorIdentifier, which EasyUBL also reads for a code');
        self::assertStringNotContainsString('paymentID', $src,
            'the derivation now sets paymentID, which EasyUBL fills from the invoice id');
    }

    // ------------------------------------------------------------ what counts as foreign

    /**
     * The ticket's rule: a currency other than DKK, or a country other than DK.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function invoices(): array
    {
        return array(
            // country, currency, is the Danish account dropped
            'Sweden in SEK'             => array('SE', 'SEK', true),
            'Sweden in DKK'             => array('SE', 'DKK', true),
            'Denmark in SEK'            => array('DK', 'SEK', true),
            'Denmark in EUR'            => array('DK', 'EUR', true),
            'Denmark in DKK'            => array('DK', 'DKK', false),
            'Norway in NOK'             => array('NO', 'NOK', true),
            // An empty currency is this install's own, so it must not make an invoice foreign.
            'Denmark, currency not set' => array('DK', '', false),
            'Sweden, currency not set'  => array('SE', '', true),
            // Case and padding must not decide it.
            'Denmark in lower-case dkk' => array('DK', 'dkk', false),
            'Denmark in padded DKK'     => array('DK', ' DKK ', false),
            'Denmark in lower-case sek' => array('DK', 'sek', true),
        );
    }

    #[DataProvider('invoices')]
    public function testWhichInvoicesDropTheDanishAccount(string $country, string $valuta, bool $dropped): void
    {
        $got = $this->derive($country, $valuta, self::MEDSHOP);

        if ($dropped) {
            self::assertSame('', $got['regNo'],
                "country $country in '$valuta' should be sent with the IBAN, not the Danish reg.nr.");
            self::assertSame('', $got['account'],
                "country $country in '$valuta' should be sent with the IBAN, not the Danish account");
        } else {
            self::assertSame('0828', $got['regNo'],
                "country $country in '$valuta' is domestic and should keep its reg.nr.");
            self::assertSame('0002880369', $got['account'],
                "country $country in '$valuta' is domestic and should keep its account");
        }
    }

    // ------------------------------------------------------------ the identifiers themselves

    /**
     * Stamdata stores both fields exactly as typed, and an IBAN is written in groups of four.
     *
     * @return array<string, array{string, string}>
     */
    public static function ibanSpellings(): array
    {
        return array(
            'as the ticket shows it' => array('DK7308280002880369', 'DK7308280002880369'),
            'in groups of four'      => array('DK73 0828 0002 8803 69', 'DK7308280002880369'),
            'with a leading space'   => array(' DK7308280002880369', 'DK7308280002880369'),
            'with a tab'             => array("DK73\t0828 0002880369", 'DK7308280002880369'),
        );
    }

    #[DataProvider('ibanSpellings')]
    public function testTheIbanIsSentWithoutSpaces(string $stored, string $sent): void
    {
        $got = $this->derive('SE', 'SEK', array('iban' => $stored, 'swift' => 'SWESDK22'));

        self::assertSame($sent, $got['iban'],
            'the IBAN reached EasyUBL with the spaces it was typed with');
    }

    /** A BIC is typed with a space often enough to be worth the same treatment. */
    public function testTheBicIsSentWithoutSpaces(): void
    {
        $got = $this->derive('SE', 'SEK', array('iban' => 'DK7308280002880369', 'swift' => 'SWES DK22'));

        self::assertSame('SWESDK22', $got['bic'],
            'the BIC reached EasyUBL with the space it was typed with');
    }

    /** A spaced IBAN is still recognised as present, so the Danish account still goes. */
    public function testASpacedIbanStillDropsTheDanishAccount(): void
    {
        $got = $this->derive('SE', 'SEK', array('iban' => 'DK73 0828 0002 8803 69', 'swift' => ''));

        self::assertSame('', $got['regNo'],
            'an IBAN typed in groups was not recognised as an IBAN');
    }

    /** An IBAN with no BIC is still worth sending; EasyUBL can route on the IBAN alone. */
    public function testAnIbanWithoutABicStillReplacesTheDanishAccount(): void
    {
        $got = $this->derive('SE', 'SEK', array('iban' => 'DK7308280002880369', 'swift' => ''));

        self::assertSame('', $got['regNo'],
            'a missing BIC kept the Danish reg.nr. and with it PaymentMeansCode 42');
        self::assertSame('', $got['bic'],
            'a BIC was invented for a Stamdata that has none');
    }

    // ------------------------------------------------------------ the payload wiring

    /**
     * The paymentMeans block has to read the variables the derivation settles.
     *
     * The derivation above is lifted and evaluated, so it cannot see which variables the payload
     * is built from. Without this test, putting $adresse straight back into the payload would
     * leave every other test in this file green.
     */
    public function testThePayloadReadsTheDerivedPaymentFields(): void
    {
        $src = file_get_contents(self::API);
        self::assertNotFalse($src, 'could not read debitor/api.php');

        $at = strpos($src, '"paymentMeans" => [');
        self::assertNotFalse($at, 'the paymentMeans block is gone from the payload');
        // The block ends where the next payload key begins; "]," would match the bank name's own
        // array subscript and cut the slice short after one field.
        $end = strpos($src, '"additionalDocuments"', $at);
        self::assertNotFalse($end, 'the paymentMeans block no longer sits above additionalDocuments');
        $block = substr($src, $at, $end - $at);

        self::assertStringContainsString('"bankRegNo" => $paymentRegNo', $block,
            'the payload sends the reg.nr. straight from Stamdata again, ignoring the derivation');
        self::assertStringContainsString('"bankAccount" => $paymentAccount', $block,
            'the payload sends the account straight from Stamdata again, ignoring the derivation');
        self::assertStringContainsString('"iban" => $paymentIban', $block,
            'the payload sends the IBAN straight from Stamdata again, with its spaces');
        self::assertStringContainsString('"bic" => $paymentBic', $block,
            'the payload sends the BIC straight from Stamdata again, with its spaces');
        // The bank name was never part of the problem and is valid under either code.
        self::assertStringContainsString('"bankName" => $adresse["bank_navn"]', $block,
            'the bank name is no longer sent');
    }

    /** Only one paymentMeans is sent, because EasyUBL's JsonInvoice holds one object. */
    public function testOnlyOnePaymentMeansIsSent(): void
    {
        $src = file_get_contents(self::API);
        self::assertNotFalse($src, 'could not read debitor/api.php');

        self::assertSame(1, substr_count($src, '"paymentMeans" => ['),
            'a second paymentMeans was added, which EasyUBL\'s JsonInvoice cannot carry');
    }
}

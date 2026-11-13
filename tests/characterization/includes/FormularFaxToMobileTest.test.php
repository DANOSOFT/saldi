<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/opdat_func/formularFaxToMobile.php';

// 20261111 CL/MMR SST-898 Pins how the update rewrites form texts that still use the fax variables (SD-532 made the field adresser.mobile).
//                 The standard label "Fax:" becomes the mobile label of the form's language; a label of the customer's own is kept.
//                 The texts are the ones found in accounts and in importfiler/formular*.txt. The last tests check the templates and the update step.
// 20261111 CL/MMR SST-898 (review) Own labels that end in Fax/FAX/Telefax/Faks ("Support Fax:") keep their text: only a whole standard label is replaced.
// 20261113 CL/MMR SST-898 (review 2) The Danish and Norwegian label is "Mobilnr." on the forms and in texts 378, 50 and 5230 (debitor card); "Fax.:" gives no double dot.
final class FormularFaxToMobileTest extends TestCase
{
    /** @return array<string, array{string, string, string}> text, sprog, expected */
    public static function texts(): array
    {
        return [
            'standard line'                => ['Fax: $egen_fax', 'Dansk', 'Mobilnr.: $egen_mobile'],
            'reminder footer'              => ['Cvr.: $eget_cvrnr; * Tlf: $egen_tlf; * Fax: $egen_fax; * $egen_bank_navn; $egen_bank_reg; $egen_bank_konto; ', 'Dansk',
                                               'Cvr.: $eget_cvrnr; * Tlf: $egen_tlf; * Mobilnr.: $egen_mobile; * $egen_bank_navn; $egen_bank_reg; $egen_bank_konto; '],
            'template 9 footer'            => ['Telefon:. $egen_tlf * Fax: $egen_fax * $egen_web * E-mail: $egen_email', 'Dansk',
                                               'Telefon:. $egen_tlf * Mobilnr.: $egen_mobile * $egen_web * E-mail: $egen_email'],
            'own label kept'               => ['Mobile Pay.:  $egen_fax;', 'Dansk', 'Mobile Pay.:  $egen_mobile;'],
            'dialling code kept'           => ['Fax: (+45)$egen_fax', 'Dansk', 'Mobilnr.: (+45)$egen_mobile'],
            'Norwegian'                    => ['Fax: $egen_fax', 'Norsk', 'Mobilnr.: $egen_mobile'],
            'Norwegian Faks'               => ['Faks: $eget_fax', 'Norsk', 'Mobilnr.: $egen_mobile'],
            'English'                      => ['Fax: $egen_fax', 'English', 'Mobile: $egen_mobile'],
            'Spanish'                      => ['Fax: $egen_fax', 'Spansk', 'Móvil: $egen_mobile'],
            'form variant without totals'  => ['Fax: $egen_fax', 'Uden totaler', 'Mobilnr.: $egen_mobile'],
            'Telefax label'                => ['Telefax: $egen_fax', 'Dansk', 'Mobilnr.: $egen_mobile'],
            'customer fax'                 => ['Fax: $adresser_fax', 'Dansk', 'Mobilnr.: $adresser_mobile'],
            'fax text without variable'    => ['Fax: 12345678', 'Dansk', 'Fax: 12345678'],
            'own label ending in Fax'      => ['Support Fax: $egen_fax', 'Dansk', 'Support Fax: $egen_mobile'],
            'own label ending in FAX'      => ['Kontor FAX: $egen_fax', 'Dansk', 'Kontor FAX: $egen_mobile'],
            'own label ending in Telefax'  => ['Butik Telefax: $egen_fax', 'Dansk', 'Butik Telefax: $egen_mobile'],
            'own label ending in Faks'     => ['Kontor-Faks: $egen_fax', 'Norsk', 'Kontor-Faks: $egen_mobile'],
            'own label after punctuation'  => ['Tlf./Fax: $egen_fax', 'Dansk', 'Tlf./Fax: $egen_mobile'],
            'own label in a footer'        => ['Tlf: $egen_tlf * Support Fax: $egen_fax;', 'Dansk', 'Tlf: $egen_tlf * Support Fax: $egen_mobile;'],
            'standard label after spaces'  => ['  Fax: $egen_fax', 'Dansk', '  Mobilnr.: $egen_mobile'],
            'longer variable name'         => ['$egen_faxnr', 'Dansk', '$egen_faxnr'],
            'label inside a word'          => ['Telefaxnr: $egen_tlf', 'Dansk', 'Telefaxnr: $egen_tlf'],
            'already mobile'               => ['Mobilnr.: $egen_mobile', 'Dansk', 'Mobilnr.: $egen_mobile'],
            'dot after Fax not doubled'    => ['Fax.: $egen_fax', 'Dansk', 'Mobilnr.: $egen_mobile'],
            'dot after Telefax not doubled'=> ['Tlf: $egen_tlf * Telefax.: $egen_fax', 'Dansk', 'Tlf: $egen_tlf * Mobilnr.: $egen_mobile'],
            'dot kept in English'          => ['Fax.: $egen_fax', 'English', 'Mobile.: $egen_mobile'],
            'empty'                        => ['', 'Dansk', ''],
        ];
    }

    #[DataProvider('texts')]
    public function testFormText(string $text, string $sprog, string $expected): void
    {
        self::assertSame($expected, formular_fax_to_mobile_text($text, $sprog));
        // a second run changes nothing
        self::assertSame($expected, formular_fax_to_mobile_text($expected, $sprog));
    }

    public function testTemplatesForNewAccountsHaveNoFaxVariable(): void
    {
        foreach (['formular.txt', 'formular_ny.txt'] as $file) {
            $src = (string)file_get_contents(__DIR__ . '/../../../importfiler/' . $file);
            self::assertStringNotContainsString('_fax', $src, $file);
            self::assertStringContainsString('Mobilnr.: $egen_mobile', $src, $file);
            self::assertStringNotContainsString('Mobil: $egen_mobile', $src, $file);
        }
    }

    /** @return array<string, array{string, string, string, string}> tekst id, Danish, English, Norwegian */
    public static function defaults(): array
    {
        return [
            'Stamdata, kreditor card, jobkort' => ['378', 'Mobilnr.', 'Mobile', 'Mobilnr.'],
            'form field list'                  => ['50', 'Mobilnr.', 'Mobile', 'Mobilnr.'],
            'debitor card (SD-525)'            => ['5230', 'Mobilnr.', 'Mobile number', 'Mobilnr.'],
        ];
    }

    #[DataProvider('defaults')]
    public function testTheDefaultTextIsMobilnr(string $id, string $da, string $en, string $no): void
    {
        $lines = file(__DIR__ . '/../../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES);
        $found = array_values(array_filter((array)$lines, fn ($l) => strncmp((string)$l, $id . "\t", strlen($id) + 1) === 0));
        self::assertSame([$id . "\t" . $da . "\t" . $en . "\t" . $no], $found, "tekster.csv line $id");
    }

    public function testTheUpdateStepRunsAtLogin(): void
    {
        $src = (string)file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
        self::assertStringContainsString("require_once __DIR__ . '/opdat_func/formularFaxToMobile.php';", $src);
        self::assertStringContainsString('formularFaxToMobile();', $src);
        // old Danish and Norwegian defaults, including the previous "Mobil" and the debitor card's "Mobilnummer"
        self::assertStringContainsString("deleteStaleTekst(378, array('Telefax', 'Mobile', 'Mobil'), 1);", $src);
        self::assertStringContainsString("deleteStaleTekst(378, array('Faks', 'Mobile', 'Mobil'), 3);", $src);
        self::assertStringContainsString("deleteStaleTekst(50, array('Fax', 'Mobile', 'Mobil'), 1);", $src);
        self::assertStringContainsString("deleteStaleTekst(50, array('Faks', 'Mobile', 'Mobil'), 3);", $src);
        self::assertStringContainsString("deleteStaleTekst(5230, 'Mobilnummer', 1);", $src);
        self::assertStringContainsString("deleteStaleTekst(5230, 'Mobilnummer', 3);", $src);
        // the current English defaults "Mobile" / "Mobile number" and the new "Mobilnr." must not be deleted
        self::assertDoesNotMatchRegularExpression("/deleteStaleTekst\\((378|50|5230), [^;]*'Mobile( number)?'[^;]*, 2\\)/", $src);
        self::assertDoesNotMatchRegularExpression("/deleteStaleTekst\\([^;]*'Mobilnr\\.'/", $src);
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/opdat_func/formularFaxToMobile.php';

// 20261111 CL/MMR SST-898 Pins how the update rewrites form texts that still use the fax variables (SD-532 made the field adresser.mobile).
//                 The standard label "Fax:" becomes the mobile label of the form's language; a label of the customer's own is kept.
//                 The texts are the ones found in accounts and in importfiler/formular*.txt. The last tests check the templates and the update step.
final class FormularFaxToMobileTest extends TestCase
{
    /** @return array<string, array{string, string, string}> text, sprog, expected */
    public static function texts(): array
    {
        return [
            'standard line'                => ['Fax: $egen_fax', 'Dansk', 'Mobil: $egen_mobile'],
            'reminder footer'              => ['Cvr.: $eget_cvrnr; * Tlf: $egen_tlf; * Fax: $egen_fax; * $egen_bank_navn; $egen_bank_reg; $egen_bank_konto; ', 'Dansk',
                                               'Cvr.: $eget_cvrnr; * Tlf: $egen_tlf; * Mobil: $egen_mobile; * $egen_bank_navn; $egen_bank_reg; $egen_bank_konto; '],
            'template 9 footer'            => ['Telefon:. $egen_tlf * Fax: $egen_fax * $egen_web * E-mail: $egen_email', 'Dansk',
                                               'Telefon:. $egen_tlf * Mobil: $egen_mobile * $egen_web * E-mail: $egen_email'],
            'own label kept'               => ['Mobile Pay.:  $egen_fax;', 'Dansk', 'Mobile Pay.:  $egen_mobile;'],
            'dialling code kept'           => ['Fax: (+45)$egen_fax', 'Dansk', 'Mobil: (+45)$egen_mobile'],
            'Norwegian'                    => ['Fax: $egen_fax', 'Norsk', 'Mobil: $egen_mobile'],
            'Norwegian Faks'               => ['Faks: $eget_fax', 'Norsk', 'Mobil: $egen_mobile'],
            'English'                      => ['Fax: $egen_fax', 'English', 'Mobile: $egen_mobile'],
            'Spanish'                      => ['Fax: $egen_fax', 'Spansk', 'Móvil: $egen_mobile'],
            'form variant without totals'  => ['Fax: $egen_fax', 'Uden totaler', 'Mobil: $egen_mobile'],
            'Telefax label'                => ['Telefax: $egen_fax', 'Dansk', 'Mobil: $egen_mobile'],
            'customer fax'                 => ['Fax: $adresser_fax', 'Dansk', 'Mobil: $adresser_mobile'],
            'fax text without variable'    => ['Fax: 12345678', 'Dansk', 'Fax: 12345678'],
            'longer variable name'         => ['$egen_faxnr', 'Dansk', '$egen_faxnr'],
            'label inside a word'          => ['Telefaxnr: $egen_tlf', 'Dansk', 'Telefaxnr: $egen_tlf'],
            'already mobile'               => ['Mobil: $egen_mobile', 'Dansk', 'Mobil: $egen_mobile'],
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
            self::assertStringContainsString('Mobil: $egen_mobile', $src, $file);
        }
    }

    public function testTheUpdateStepRunsAtLogin(): void
    {
        $src = (string)file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
        self::assertStringContainsString("require_once __DIR__ . '/opdat_func/formularFaxToMobile.php';", $src);
        self::assertStringContainsString('formularFaxToMobile();', $src);
        self::assertStringContainsString("deleteStaleTekst(378, array('Telefax', 'Mobile'), 1);", $src);
        self::assertStringContainsString("deleteStaleTekst(50, array('Fax', 'Mobile'), 1);", $src);
        // the current English default is "Mobile": it must not be deleted
        self::assertStringNotContainsString("deleteStaleTekst(378, array('Fax', 'Mobile'), 2)", $src);
        self::assertStringNotContainsString("deleteStaleTekst(50, array('Fax', 'Mobile'), 2)", $src);
    }
}

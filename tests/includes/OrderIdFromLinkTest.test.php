<?php
// 20261124 CL/LH MB-177 The order id check and the contact list sort the order pages use for values from the link.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/orderIdFromLink.php';

final class OrderIdFromLinkTest extends TestCase
{
    /** @return array<string, array{0: array<string, mixed>, 1: bool}> */
    public static function links(): array
    {
        return array(
            'no id' => array(array(), true),
            'whole number' => array(array('id' => '1234'), true),
            'whole number with spaces' => array(array('id' => ' 12 '), true),
            'integer' => array(array('id' => 12), true),
            'empty (new order)' => array(array('id' => ''), true),
            'null (new order)' => array(array('id' => 'null'), true),
            'zero' => array(array('id' => '0'), true),
            'expression' => array(array('id' => '1 or 1=1'), false),
            'quote' => array(array('id' => "1'"), false),
            'negative' => array(array('id' => '-1'), false),
            'decimal' => array(array('id' => '1.5'), false),
            'array' => array(array('id' => array('1')), false),
            'NUL byte at the end' => array(array('id' => "12\0"), false),
            'newline at the end' => array(array('id' => "12\n"), false),
            'second key bad' => array(array('id' => '1', 'tjek' => '1;'), false),
            'other parameters are not checked' => array(array('id' => '1', 'sort' => 'x y'), true),
        );
    }

    /** @param array<string, mixed> $get */
    #[DataProvider('links')]
    public function testOrderIdsFromTheLink(array $get, bool $ok): void
    {
        self::assertSame($ok, order_ids_from_link_are_whole_numbers($get, array('id', 'tjek')));
    }

    public function testTheContactListSortsOnlyByItsFourHeadings(): void
    {
        foreach (array('navn', 'tlf', 'mobil', 'email') as $column) {
            self::assertSame($column, contact_lookup_sort_column($column));
        }
        foreach (array(null, '', 'id', 'navn desc', 'navn,(select 1)', array('navn')) as $other) {
            self::assertSame('navn', contact_lookup_sort_column($other));
        }
    }
}

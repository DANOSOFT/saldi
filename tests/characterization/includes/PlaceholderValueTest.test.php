<?php
// 20260911 Sawaneh Cover strip_placeholder_value() / strip_placeholder_sql_literals(): the literal
//                     "dummyvalue" Shoptech sends for empty address fields must be stored blank (JOB-115).

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/std_func.php';

final class PlaceholderValueTest extends TestCase
{
    public function testPlaceholderIsBlankedCaseInsensitiveAndTrimmed(): void
    {
        self::assertSame('', strip_placeholder_value('dummyvalue'));
        self::assertSame('', strip_placeholder_value('Dummyvalue'));
        self::assertSame('', strip_placeholder_value(' DUMMYVALUE '));
    }

    public function testRealValuesPassThroughUntouched(): void
    {
        self::assertSame('Teatergade 23', strip_placeholder_value('Teatergade 23'));
        self::assertSame(' 4700 ', strip_placeholder_value(' 4700 '));
        self::assertSame('', strip_placeholder_value(''));
        self::assertSame('dummyvalue 2', strip_placeholder_value('dummyvalue 2'));
        self::assertNull(strip_placeholder_value(null));
        self::assertSame(42, strip_placeholder_value(42));
    }

    public function testQuotedPlaceholderLiteralsAreBlankedInSql(): void
    {
        $sql = "adresser(addr1,bynavn,postnr) VALUES ('Dummyvalue','dummyvalue ','4700')";
        self::assertSame(
            "adresser(addr1,bynavn,postnr) VALUES ('','','4700')",
            strip_placeholder_sql_literals($sql)
        );
        self::assertSame(
            "adresser set addr1='Teatergade 23' where id=1",
            strip_placeholder_sql_literals("adresser set addr1='Teatergade 23' where id=1")
        );
    }
}

<?php
// tests/characterization/includes/OrderCreationIdSourceTest.test.php
//
// The source half of the order-creation regression (SST-967), kept in a class of its own so a database
// skip in OrderCreationIdTest cannot silence it.
//
// debitor/ordre.php must take the order it just created from the insert on the same connection, and must
// not take the id it marks as created from "newest order for this customer and date".
//
// History:
// 20261112 LOE SST-967 created.

final class OrderCreationIdSourceTest extends PHPUnit\Framework\TestCase
{
    public function testThePageReadsTheInsertedIdFromTheConnection(): void
    {
        $code = $this->ordreSource();

        $this->assertStringContainsString('function ordre_last_insert_id()', $code,
            'the page has one place that reads the inserted id');
        $this->assertStringContainsString("pg_get_serial_sequence('ordrer','id')", $code,
            'which finds the sequence of the insert on PostgreSQL');
        $this->assertStringContainsString("currval('", $code, 'and reads the value from that sequence');
        $this->assertStringContainsString('mysqli_insert_id(db_query_connection(false))', $code,
            'and the connection of the insert on MySQL');
        $this->assertStringContainsString('$sst967_inserted_id = ordre_last_insert_id();', $code,
            'it is called right after the insert');
    }

    public function testTheCreatedMarkerComesFromTheInsert(): void
    {
        $code = $this->ordreSource();

        $read = strpos($code, '$sst967_inserted_id = ordre_last_insert_id();');
        $marker = strpos($code, '$sst967_created_order = $sst967_inserted_id;');
        $this->assertNotFalse($read, 'the inserted id is read');
        $this->assertNotFalse($marker, 'the created marker is set from it');
        $this->assertLessThan($marker, $read, 'the id is read before it is used as the created marker');
        $this->assertStringNotContainsString(
            "# SST-967 this page created it, so the case checks may not refuse it.\n\t\t\$sst967_created_order = (int) \$id;",
            $code,
            'the created marker no longer comes from the customer-and-date lookup'
        );
    }

    private function ordreSource(): string
    {
        $code = @file_get_contents(__DIR__ . '/../../../debitor/ordre.php');
        $this->assertNotFalse($code, 'cannot read debitor/ordre.php');

        return (string) $code;
    }
}

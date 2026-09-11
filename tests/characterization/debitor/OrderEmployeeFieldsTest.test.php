<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../debitor/orderIncludes/renderEmployeeFields.php';

final class OrderEmployeeFieldsTest extends TestCase
{
    private function fields(array $employees, ?string $reference, ?string $performedBy, bool $disabled = false): DOMXPath
    {
        $html = renderOrderEmployeeFields($employees, $reference, $performedBy, 'Vor ref.', $disabled);
        $document = new DOMDocument();
        $document->loadHTML('<html><head><meta charset="UTF-8"></head><body><form><table>' . $html . '</table></form></body></html>');
        return new DOMXPath($document);
    }

    private function options(DOMXPath $fields, string $name): array
    {
        $values = [];
        foreach ($fields->query("//select[@name='$name']/option") as $option) {
            $values[] = $option->getAttribute('value');
        }
        return $values;
    }

    public function testBothFieldsShareEmployeesWithIndependentSelectionsInOneRow(): void
    {
        $fields = $this->fields(['Anna', 'Søren'], 'Anna', 'Søren');
        self::assertSame(['', 'Anna', 'Søren'], $this->options($fields, 'ref'));
        self::assertSame($this->options($fields, 'ref'), $this->options($fields, 'hvem'));
        self::assertSame('Anna', $fields->evaluate('string(//select[@name="ref"]/option[@selected]/@value)'));
        self::assertSame('Søren', $fields->evaluate('string(//select[@name="hvem"]/option[@selected]/@value)'));
        self::assertSame(1, $fields->query('//tr[td/select[@name="ref"] and td/select[@name="hvem"]]')->length);
        self::assertSame('Udført af', $fields->evaluate('string(//label[@for="order-hvem"])'));
        self::assertSame('ref', $fields->query('//select')->item(0)->getAttribute('name'));
        self::assertSame('hvem', $fields->query('//select')->item(1)->getAttribute('name'));
    }

    public function testBlankPerformedByStaysBlankAndCanBeRenderedWithoutEmployees(): void
    {
        $fields = $this->fields(['Anna'], 'Anna', null);
        self::assertSame('', $fields->evaluate('string(//select[@name="hvem"]/option[@selected]/@value)'));
        self::assertSame('Anna', $fields->evaluate('string(//select[@name="ref"]/option[@selected]/@value)'));
        $empty = $this->fields([], null, null);
        self::assertSame([''], $this->options($empty, 'ref'));
        self::assertSame([''], $this->options($empty, 'hvem'));
        self::assertSame(2, $empty->query('//option[@selected and @value=""]')->length);
    }

    public function testHistoricalSelectionIsPreservedWithoutAddingItToTheOtherField(): void
    {
        $fields = $this->fields(['Anna'], 'Anna', 'Former employee');
        self::assertSame(['', 'Anna'], $this->options($fields, 'ref'));
        self::assertSame(['', 'Anna', 'Former employee'], $this->options($fields, 'hvem'));
        self::assertSame('Former employee', $fields->evaluate('string(//select[@name="hvem"]/option[@selected]/@value)'));
        self::assertSame('Former employee', $fields->evaluate('string(//input[@name="oldhvem"]/@value)'));
    }

    public function testNamesWithQuotesAndMarkupRoundTripAsText(): void
    {
        $employee = 'Søren O\'Neil "A&B" <technician>';
        $fields = $this->fields([$employee], $employee, $employee);
        foreach (['ref', 'hvem'] as $name) {
            self::assertSame(['', $employee], $this->options($fields, $name));
            self::assertSame($employee, $fields->evaluate("string(//select[@name='$name']/option[@selected])"));
        }
        self::assertSame(0, $fields->query('//technician')->length);
    }

    public function testEmployeeListsAreRefreshedAndDoNotLeakBetweenRegnskaber(): void
    {
        foreach ([['Anna'], ['Søren'], ['Anna', 'Søren', 'New employee']] as $employees) {
            $fields = $this->fields($employees, null, null);
            foreach (['ref', 'hvem'] as $name) {
                self::assertSame(array_merge([''], $employees), $this->options($fields, $name));
            }
        }
    }

    public function testEditingRestrictionAppliesToBothDropdowns(): void
    {
        self::assertSame(2, $this->fields(['Anna'], 'Anna', '', true)->query('//select[@disabled]')->length);
        self::assertSame(0, $this->fields(['Anna'], 'Anna', '', false)->query('//select[@disabled]')->length);
    }
}

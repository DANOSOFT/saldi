<?php
// 20261126 CDX/NTR Exercise the shipped delivery script for cleared, incomplete and past dates.

namespace Saldi\Tests\PosDeliveryScript;

use PHPUnit\Framework\TestCase;

final class PosDeliveryScriptTest extends TestCase
{
    /** Run the generated browser handler and return the requests it sends. */
    private function requests(string $value, bool $badInput = false): array
    {
        require_once dirname(__DIR__, 3) . '/debitor/pos_ordre_includes/saleDeliveryFields.php';
        $_SESSION = [];
        $script = preg_replace('~</?script>~', '', \pos_sale_delivery_script(42));
        $program = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const requests = [];
const form = {elements: {}};
const date = {form, value: input.value, validity: {badInput: input.badInput}};
const choice = {value: '3', selectedIndex: 0, options: [{getAttribute: () => 'levering'}]};
form.elements = {leveringsvalg_id: choice, oensket_levdato: date};
const context = {
  window: {}, document: {getElementById: () => ({style: {}})},
  FormData, Promise, Date, alert: message => {throw new Error(message)},
  fetch: async (url, options) => {
    requests.push(Object.fromEntries(options.body.entries()));
    return {json: async () => ({ok: true})};
  }
};
vm.createContext(context);
vm.runInContext(input.script, context);
context.posLeveringGem(date);
Promise.resolve(context.window.posLeveringKoe).then(() => process.stdout.write(JSON.stringify(requests)));
JS;
        $input = tmpfile();
        $output = tmpfile();
        $errors = tmpfile();
        fwrite($input, json_encode(['script' => $script, 'value' => $value, 'badInput' => $badInput], JSON_THROW_ON_ERROR));
        rewind($input);
        $process = proc_open(['node', '-e', $program], [0 => $input, 1 => $output, 2 => $errors], $pipes);
        self::assertIsResource($process);
        $exit = proc_close($process);
        rewind($output);
        rewind($errors);
        $stdout = stream_get_contents($output);
        $stderr = stream_get_contents($errors);
        fclose($input);
        fclose($output);
        fclose($errors);
        self::assertSame(0, $exit, $stderr);
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testClearingTheDateSendsAnEmptyValueToTheServer(): void
    {
        self::assertSame([['id' => '42', 'leveringsvalg_id' => '3', 'oensket_levdato' => '']], $this->requests(''));
    }

    public function testAnIncompleteDateDoesNotClearTheStoredDate(): void
    {
        self::assertSame([], $this->requests('', true));
    }

    public function testACompletePastDateIsSentForServerValidation(): void
    {
        self::assertSame([['id' => '42', 'leveringsvalg_id' => '3', 'oensket_levdato' => '2000-01-01']], $this->requests('2000-01-01'));
    }
}

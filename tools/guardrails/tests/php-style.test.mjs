import test from "node:test";
import assert from "node:assert/strict";
import style from "../checks/php-style.mjs";

function findings(source, added) {
  const lines = source.split("\n");
  return style.run({
    files: [{ path: "example.php", status: "M", added: new Map((added ?? lines.map((_, index) => index + 1)).map(line => [line, lines[line - 1]])) }],
    isVendored: () => false,
    readHead: () => source,
  });
}

test("numeric conversions use explicit casts", () => {
  for (const expression of ["$value * 1", "1 * $value", "$value *= 1"]) {
    assert(findings(`<?php\n$result = ${expression};`).some(finding => finding.message.includes("explicit")));
  }
  assert.deepEqual(findings("<?php\n$result = (float) ($value ?? 0);\n$count = (int) ($count ?? 0);\n$total = $price * 100;"), []);
});

test("PHP strings, interpolation, heredocs, comments and inline HTML are ignored", () => {
  const source = `<?php
// $value * 1 }}
$text = '$value * 1 }}';
$text = "{$value} * 1 }}";
$script = <<<JS
if (value) {{ total = value * 1; }}
JS;
?>
<script>if (value) {{ total = value * 1; }}</script>`;
  assert.deepEqual(findings(source), []);
});

test("each multiline block closes on a separate line even when only its body changed", () => {
  const source = "<?php\nif ($enabled) {\n\tif ($value) {\n\t\techo $value;\n\t}}";
  assert(findings(source, [4]).some(finding => finding.line === 5 && finding.message.includes("own line")));
  assert.deepEqual(findings(source, [1]), []);
  assert.deepEqual(findings("<?php\nif ($enabled) { if ($value) { echo $value; } }"), []);
});

test("changed statements are indented inside their block", () => {
  const source = "<?php\nif ($enabled) {\necho $value;\n}";
  assert(findings(source).some(finding => finding.line === 3 && finding.message.includes("indent")));
  assert.deepEqual(findings(source, [1]), []);
  for (const indent of ["\t", "  ", "    "]) {
    assert.deepEqual(findings(`<?php\nif ($enabled) {\n${indent}echo $value;\n}`), []);
  }
});

test("closing braces align with their block and continuation lines retain their own layout", () => {
  assert(findings("<?php\nif ($enabled) {\n\techo $value;\n\t}").some(finding => finding.message.includes("align")));
  assert.deepEqual(findings("<?php\nif ($enabled) {\n\t$result = example(\n\t\t$value,\n\t\t[\n\t\t\t'one' => 1,\n\t\t]\n\t);\n}"), []);
});

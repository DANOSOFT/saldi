// tools/guardrails/checks/php-syntax.mjs - every PHP file a change touches still parses.
//
// Past breaks: 064afcdc (a line break inside a commented-out print left half of
// it live in kreditor/ordre.php) and the files copied back from the live server
// that arrived unparseable (9be415bf, aeb38402, 00344e66, d8f80f10).
//
// Linted with PHP 8.2 because production runs 8.2: a newer PHP accepts syntax
// 8.2 rejects. Uses `php` from PATH when it is 8.2, otherwise Docker
// (GUARDRAILS_PHP_IMAGE, default php:8.2-cli).
//
// History:
// 20261027 CL/LH Created.

import { execFileSync, spawnSync } from "node:child_process";
import { mkdtempSync, writeFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const LINT_SCRIPT = `<?php
for ($i = 0; $i < (int) $argv[1]; $i++) {
	$out = [];
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__DIR__ . "/$i.php") . ' 2>&1', $out, $code);
	if ($code !== 0) {
		echo $i, "\\t", implode(' ', $out), "\\n";
	}
}
`;

function phpCommand(dir) {
  const local = spawnSync("php", ["-r", "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;"], { encoding: "utf8" });
  if (local.status === 0 && local.stdout.trim() === "8.2") return ["php"];
  const image = process.env.GUARDRAILS_PHP_IMAGE ?? "php:8.2-cli";
  const docker = spawnSync("docker", ["version", "--format", "{{.Server.Version}}"], { encoding: "utf8" });
  if (docker.status === 0) return ["docker", "run", "--rm", "-v", `${dir}:${dir}:ro`, image, "php"];
  if (local.status === 0) {
    console.warn(`php-syntax: using PHP ${local.stdout.trim()} from PATH; production runs 8.2, so 8.3+ syntax slips through`);
    return ["php"];
  }
  throw new Error("php-syntax needs PHP 8.2 on PATH or Docker (image php:8.2-cli)");
}

export default {
  id: "php-syntax",
  summary: "changed PHP files parse under PHP 8.2 (php -l)",
  run(ctx) {
    const files = ctx.files.filter(
      (file) => file.status !== "D" && file.path.endsWith(".php") && !ctx.isVendored(file.path) && file.path !== "includes/betweenUpdates2.php",
    );
    if (!files.length) return [];
    const dir = mkdtempSync(join(tmpdir(), "guardrails-php-"));
    try {
      files.forEach((file, index) => writeFileSync(join(dir, `${index}.php`), ctx.readHead(file.path) ?? ""));
      writeFileSync(join(dir, "lint.php"), LINT_SCRIPT);
      const [command, ...args] = phpCommand(dir);
      const output = execFileSync(command, [...args, join(dir, "lint.php"), String(files.length)], { encoding: "utf8", maxBuffer: 1 << 26 });
      return output
        .split("\n")
        .filter(Boolean)
        .map((row) => {
          const [index, message] = row.split("\t");
          const file = files[Number(index)];
          const line = Number([...message.matchAll(/on line (\d+)/g)].pop()?.[1] ?? 0) || undefined;
          const text = message.replaceAll(join(dir, `${index}.php`), file.path).replace(/\s*Errors parsing .*$/, "").replace(/^PHP\s+/, "").trim();
          return { path: file.path, line, message: `does not parse: ${text}` };
        });
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }
  },
};

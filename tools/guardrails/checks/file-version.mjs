// tools/guardrails/checks/file-version.mjs - behavior changes mirror the current app version.
// Tokenize without executing PHP so comments and whitespace do not require a version bump.
// 20261029 CDX/MJ Enforce the repeated CodeRabbit header-version finding (PR #882, 39a812a75).
import { execFileSync } from "node:child_process";

const TOKEN_HASH = String.raw`
$hashes = [];
foreach (json_decode(stream_get_contents(STDIN), true) as $source) {
    $hash = hash_init('sha256');
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) continue;
            hash_update($hash, $token[0] . ':' . strlen($token[1]) . ':' . $token[1]);
        } else {
            hash_update($hash, 'literal:' . $token);
        }
    }
    $hashes[] = hash_final($hash);
}
echo json_encode($hashes);
`;

export default {
  id: "file-version",
  summary: "PHP behavior changes use includes/version.php's version; comments and formatting do not require a bump",
  run(ctx) {
    const version = /\$version\s*=\s*["']([^"']+)["']/.exec(ctx.readHead("includes/version.php") ?? "")?.[1];
    if (!version) return [];
    const findings = [];
    for (const file of ctx.files) {
      if (file.status === "D" || !file.path.endsWith(".php") || ctx.isVendored(file.path)) continue;
      const source = ctx.readHead(file.path) ?? "";
      const lines = source.split(/\r?\n/).slice(0, 100);
      const line = lines.findIndex((text) => /^\s*\/\/.*\.php\b.*\b(?:ver|lap|patch)\s+\d/i.test(text));
      if (line < 0) continue;
      const declared = /\b(ver|lap|patch)\s+([^\s-]+)/i.exec(lines[line]);
      if (declared?.[1].toLowerCase() === "ver" && declared[2] === version) continue;
      const previous = ctx.readBase(file.path);
      if (previous !== null) {
        const [before, after] = JSON.parse(execFileSync("php", ["-r", TOKEN_HASH], {
          input: JSON.stringify([previous, source]), encoding: "utf8", maxBuffer: 1024 * 1024,
        }));
        if (before === after) continue;
      }
      findings.push({ path: file.path, line: line + 1,
        message: `PHP behavior changed; set the existing header marker to "ver ${version}" (includes/version.php).` });
    }
    return findings;
  },
};

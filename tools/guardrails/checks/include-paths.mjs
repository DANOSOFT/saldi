// tools/guardrails/checks/include-paths.mjs - a new include is anchored on __DIR__.
//
// doc/ai/convention_include_paths.md: a bare relative path such as
// include("../includes/connect.php") resolves against the working directory of
// whatever script ran first, not against the including file, so the same line
// works from one caller and breaks from another. New include/require statements
// say __DIR__ . '/../includes/connect.php' instead. Past mistakes: ba02be399
// (tests/characterization/support/run_varesync.php, three bare includes, corrected
// in review), c290172a7 (#999: includes/signupIncludes/signupBootstrap.php, two
// bare includes above two __DIR__ ones) and df7c0eb20 (#788: lager/batch_udfyld.php);
// 44 new bare includes reached master between 0db69465 (the convention) and 702a1c58.
//
// Only an include the change writes is judged: legacy includes stay as they are
// until someone edits them, and a line the change only moves or re-indents (the
// same text is removed elsewhere in the change) is not new. A path that starts
// with a variable, a constant or a function call is not a bare relative path.
// PHP's own tokenizer finds the statements, so an include in a comment, a string
// or an inline <script> is not one.
//
// History:
// 20261105 CL/LH Created.

import { execFileSync } from "node:child_process";
import { splitLines } from "../lib/changeset.mjs";
import { phpCommand } from "./php-syntax.mjs";

// For each source: [line, keyword, leading literal of the path] for every include whose path starts with a string literal.
const FIND_INCLUDES = String.raw`
$skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
$result = [];
foreach (json_decode(stream_get_contents(STDIN), true) as $source) {
    $tokens = token_get_all($source);
    $found = [];
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || !in_array($token[0], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) continue;
        $j = $i + 1;
        while (isset($tokens[$j]) && (is_array($tokens[$j]) ? in_array($tokens[$j][0], $skip, true) : $tokens[$j] === '(')) $j++;
        $next = $tokens[$j] ?? null;
        $path = null;
        if (is_array($next) && $next[0] === T_CONSTANT_ENCAPSED_STRING) {
            $path = substr($next[1], 1, -1);
        } elseif ($next === '"' && is_array($tokens[$j + 1] ?? null) && $tokens[$j + 1][0] === T_ENCAPSED_AND_WHITESPACE) {
            $path = $tokens[$j + 1][1];
        }
        if ($path !== null) $found[] = [$token[2], strtolower($token[1]), $path];
    }
    $result[] = $found;
}
echo json_encode($result);
`;

/** An absolute path or a stream wrapper (phar://, php://) does not depend on the working directory. */
const ANCHORED = /^(\/|[a-z][a-z0-9+.-]*:\/\/)/i;

export default {
  id: "include-paths",
  summary: "new include/require statements are anchored on __DIR__, not a bare relative path",
  run(ctx) {
    const files = ctx.files.filter((file) => file.status !== "D" && file.path.endsWith(".php") && !ctx.isVendored(file.path) && file.added.size);
    if (!files.length) return [];
    const php = phpCommand();
    const sources = files.map((file) => ctx.readHead(file.path) ?? "");
    const includes = JSON.parse(execFileSync(php[0], [...php.slice(1), "-r", FIND_INCLUDES], {
      input: JSON.stringify(sources), encoding: "utf8", maxBuffer: 1 << 26,
    }));

    const removed = new Set();
    for (const file of ctx.files) {
      const lines = file.status === "D" ? splitLines(ctx.readBase(file.path) ?? "") : file.hunks.flatMap((hunk) => hunk.removed);
      for (const text of lines) removed.add(text.trim());
    }

    const findings = [];
    files.forEach((file, index) => {
      const lines = splitLines(sources[index]);
      for (const [line, keyword, path] of includes[index]) {
        if (!file.added.has(line) || ANCHORED.test(path) || removed.has(lines[line - 1].trim())) continue;
        const dir = file.path.includes("/") ? file.path.slice(0, file.path.lastIndexOf("/") + 1) : "the repository root";
        findings.push({
          path: file.path,
          line,
          message:
            `${keyword} '${path}' resolves against the caller's working directory, not this file. ` +
            `Write ${keyword} __DIR__ . '/...' with the path from ${dir} to the file (doc/ai/convention_include_paths.md).`,
        });
      }
    });
    return findings;
  },
};

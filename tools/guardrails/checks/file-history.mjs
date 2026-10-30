// tools/guardrails/checks/file-history.mjs - the header history block (doc/ai/convention_file_history.md).
//
// The most corrected thing in review (about 35 PRs since July): no history line,
// a tag outside the vocabulary (CX, CoPilot, full names), a line inserted above
// older ones, the header date or copyright year not moved along. Only the parts
// that need no judgment are checked here; whether `ver` needed bumping (a
// behaviour change or not) is still the reviewer's call.
//
// For a PHP file that already has a history block, a change must:
//   1. add a history line: `// YYYYMMDD <TAG> <description>`, TAG = initials or CL/<initials>, CDX/<initials>
//   2. add it below every existing history line (or, after a rebase, in date order among them)
//   3. move the date on the `--- path --- ver --- date ---` line to that day or later
//   4. say `Copyright (c) ...-<that year> Danosoft ApS`
//
// History:
// 20261027 CL/LH Created.
// 20261028 CL/LH Accept history blocks that are indented like the rest of their file's block.

import { splitLines } from "../lib/changeset.mjs";

const HISTORY = /^\s*(\/\/|#)\s*(\d{8})\b/;
// Leading whitespace is allowed: about 20 files (includes/stdFunc/findTxt.php, includes/grid.php,
// debitor/api.php, ...) indent their whole history block, and a new line matches its block.
const WELL_FORMED = /^\s*\/\/ (\d{8})[ \t]+(?:(?:CL|CDX)\/)?[A-Z]{2,4}[ \t]+\S/;
const PATH_LINE = /^\/\/.*\S+\.php\b.*?(\d{4})[-./](\d{2})[-./](\d{2})\D*$/;
const COPYRIGHT = /copyright\s*\(c\)/i;

/** Number of leading lines that are the file's comment header. */
function headerLength(lines) {
  let n = 0;
  while (n < lines.length && n < 400 && /^\s*(<\?(php)?|\/\/.*|#.*)?\s*$/.test(lines[n])) n++;
  return n;
}

function validDate(yyyymmdd, today) {
  const iso = `${yyyymmdd.slice(0, 4)}-${yyyymmdd.slice(4, 6)}-${yyyymmdd.slice(6, 8)}`;
  const date = new Date(`${iso}T00:00:00Z`);
  if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== iso) return `${yyyymmdd} is not a date`;
  const tomorrow = new Date(`${today}T00:00:00Z`);
  tomorrow.setUTCDate(tomorrow.getUTCDate() + 1);
  if (date > tomorrow) return `${yyyymmdd} is in the future`;
  return null;
}

export default {
  id: "file-history",
  summary: "edited PHP files get a well-formed history line at the bottom of the block, header date and copyright moved along",
  run(ctx) {
    const findings = [];
    for (const file of ctx.files) {
      if (file.status === "D" || !file.path.endsWith(".php") || ctx.isVendored(file.path)) continue;
      const lines = splitLines(ctx.readHead(file.path));
      const header = headerLength(lines);
      const historyLines = [];
      for (let line = 1; line <= header; line++) if (HISTORY.test(lines[line - 1])) historyLines.push(line);
      const added = historyLines.filter((line) => file.added.has(line));
      const sinceFork = ctx.addedSinceFork(file.path);
      const existing = historyLines.filter((line) => !file.added.has(line) && !sinceFork.has(line));

      if (file.status === "M" && !added.length) {
        const touched = file.hunks.some((hunk) => hunk.removed.length || hunk.added.some((entry) => entry.line > header));
        if (existing.length && touched) {
          findings.push({
            path: file.path,
            line: existing[existing.length - 1],
            message: `no history line for this change; add "// ${ctx.today.replaceAll("-", "")} CL/<initials> <what changed>" below line ${existing[existing.length - 1]} (doc/ai/convention_file_history.md).`,
          });
        }
        continue;
      }
      if (!added.length) continue;

      for (const line of added) {
        const text = lines[line - 1];
        const date = HISTORY.exec(text)[2];
        const problem = !WELL_FORMED.test(text)
          ? 'it must read "// YYYYMMDD <TAG> <description>" where TAG is initials (2-4 capitals) or CL/<initials> or CDX/<initials>'
          : validDate(date, ctx.today);
        if (problem) findings.push({ path: file.path, line, message: `malformed history line: ${problem}.` });
      }

      // A line dated before entries that merged in later may sit among them in date order.
      const dateOf = (line) => HISTORY.exec(lines[line - 1])[2];
      const misplaced = added.filter((line) => existing.some((old) => old > line && dateOf(old) < dateOf(line)));
      if (misplaced.length) {
        findings.push({
          path: file.path,
          line: misplaced[0],
          message: `history line added above older entries; new lines go at the bottom of the block, after line ${existing[existing.length - 1]}.`,
        });
      }

      const newest = added.map((line) => HISTORY.exec(lines[line - 1])[2]).sort().pop();
      for (let line = 1; line <= header; line++) {
        const text = lines[line - 1];
        const path = PATH_LINE.exec(text);
        if (path && HISTORY.test(text) === false && `${path[1]}${path[2]}${path[3]}` < newest) {
          findings.push({ path: file.path, line, message: `header date ${path[1]}-${path[2]}-${path[3]} is older than this change; set it to ${newest.slice(0, 4)}-${newest.slice(4, 6)}-${newest.slice(6)} in the same format.` });
        }
        if (COPYRIGHT.test(text)) {
          const years = text.match(/\b(19|20)\d{2}\b/g) ?? [];
          if (!/Danosoft ApS/.test(text) || (years[years.length - 1] ?? "") < newest.slice(0, 4)) {
            findings.push({ path: file.path, line, message: `copyright line must read "Copyright (c) <first year>-${newest.slice(0, 4)} Danosoft ApS".` });
          }
        }
      }
    }
    return findings;
  },
};

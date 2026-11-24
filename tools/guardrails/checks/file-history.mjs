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
// Every edited PHP header must also use comment-only separators throughout.
// Proven on 9fc6a78bb against its parent: catches the gap before the new
// OpenPostReportLayoutTest history entry and 33 other header gaps.
//
// History:
// 20261027 CL/LH Created.
// 20261028 CL/LH Accept history blocks that are indented like the rest of their file's block.
// 20261029 CL/MJ Enforce aligned sentence-only continuations for new history entries (PR #874).
// 20261029 CDX/MJ Reject newly added history after a leading PHPDoc instead of inside the header.
// 20261030 CL/MJ Only a /** block counts as that PHPDoc, so history after a plain /* note in the header (includes/grid.php) is not flagged.
// 20261111 CDX/NTR Require comment-only separators throughout edited headers, including license and history sections.

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
  let inBlock = false;
  while (n < lines.length) {
    const text = lines[n].trim();
    if (inBlock || (text.startsWith("/*") && !/^\/\*\*(\s|$)/.test(text))) {
      const end = text.indexOf("*/");
      if (end !== -1 && text.slice(end + 2).trim()) break;
      inBlock = end === -1;
      n++;
    } else if (/^\s*(<\?(php)?|\/\/.*|#(?!\[).*)?\s*$/.test(lines[n])) {
      n++;
    } else {
      break;
    }
  }
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
  summary: "edited PHP headers have no uncommented blank lines, a well-formed history entry, and current header date/copyright",
  run(ctx) {
    const findings = [];
    // The diff runs with --no-renames, so a moved file is a new file plus a deleted one. Its lines that
    // already stood in a deleted file are not new for the continuation rule below.
    const deletedLines = new Set(ctx.files.filter((file) => file.status === "D").flatMap((file) => splitLines(ctx.readBase(file.path) ?? "")));
    for (const file of ctx.files) {
      if (file.status === "D" || !file.path.endsWith(".php") || ctx.isVendored(file.path)) continue;
      const lines = splitLines(ctx.readHead(file.path));
      const header = headerLength(lines);
      // Trailing whitespace separates the finished header from code/PHPDoc; only
      // gaps inside the header are forbidden. Empty lines inside /* ... */ are
      // already comments, whereas separators between // lines must themselves be //.
      let lastHeaderLine = header;
      while (lastHeaderLine > 0 && !lines[lastHeaderLine - 1].trim()) lastHeaderLine--;
      let inHeaderBlock = false;
      for (let i = 0; i < lastHeaderLine; i++) {
        const text = lines[i].trim();
        if (inHeaderBlock) {
          if (text.includes("*/")) inHeaderBlock = false;
        } else if (text.startsWith("/*")) {
          inHeaderBlock = !text.includes("*/");
        } else if (!text) {
          findings.push({ path: file.path, line: i + 1,
            message: "uncommented blank line inside the file header; use a comment-only separator (//) to keep the whole header contiguous." });
        }
      }
      const historyLines = [];
      for (let line = 1; line <= header; line++) if (HISTORY.test(lines[line - 1])) historyLines.push(line);
      // A leading PHPDoc ends headerLength(), but is not executable code. Catch entries
      // mistakenly placed between that PHPDoc and the first declaration as well. Only a
      // /** block is a PHPDoc: some headers (includes/grid.php) keep a plain /* ... */ note
      // inside the header and their history lines after it, and those are not misplaced.
      let inComment = false;
      let afterPhpDoc = false;
      for (let i = header; i < lines.length; i++) {
        const text = lines[i].trim();
        if (inComment) {
          if (text.includes("*/")) inComment = false;
          continue;
        }
        if (text.startsWith("/*")) {
          // A /*****\ licence banner (includes/barcode.php) also starts with /**; a docblock opener is /** alone or followed by space.
          if (/^\/\*\*(\s|$)/.test(text)) afterPhpDoc = true;
          inComment = !text.includes("*/");
          continue;
        }
        if (afterPhpDoc && HISTORY.test(lines[i]) && file.added.has(i + 1)) {
          findings.push({ path: file.path, line: i + 1,
            message: "history entry is outside the header block; put it immediately after the existing // history lines, before PHPDoc." });
        }
        if (text && !text.startsWith("//") && (!text.startsWith("#") || text.startsWith("#["))) break;
      }
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

      const base = splitLines(ctx.readBase(file.path) ?? "");
      const isNew = (line) => file.added.has(line) && !(file.status === "A" && deletedLines.has(lines[line - 1]));
      const endsSentence = (line) => /[.!?]["'`)]*$/.test(lines[line - 1].trimEnd());
      // A comment line under a history entry is a continuation when it is indented, or when it has
      // one space but the line above left its sentence open. A one-space line after a finished
      // sentence (// Run: ...) describes the file, and a separator or an older dated line ends the entry.
      // Only an unchanged one-space line can be an older dated entry (// 2018.12.20 ...): a new
      // continuation, or an indented old one (//                5027-5030 to ...), may start with a date.
      const continuationAt = (line) => {
        const match = /^(\s*\/\/([ \t]+))(\S.*)$/.exec(lines[line - 1]);
        if (!match || HISTORY.test(lines[line - 1])) return null;
        if (/^[-=*#~_ ]+$/.test(match[3])) return null;
        if (!isNew(line) && match[2] === " " && /^\d{2,4}[./-]\d/.test(match[3])) return null;
        if (match[2] === " " && endsSentence(line - 1)) return null;
        return match;
      };
      for (const line of historyLines) {
        const prefix = /^(\s*\/\/ \d{8}[ \t]+(?:(?:CL|CDX)\/)?[A-Z]{2,4}[ \t]+)/.exec(lines[line - 1]);
        if (!prefix) continue;
        for (let next = line + 1; next <= header; next++) {
          const continuation = continuationAt(next);
          if (!continuation) break;
          if (!isNew(line) && !isNew(next)) continue;
          if (isNew(line) && !isNew(next)) {
            // An old continuation right below a new entry belongs to the entry above it. An old
            // one-space line there is a file description (// Run: ...), not a continuation, even when
            // the new entry has no full stop: the convention does not ask for one on a one-line entry.
            if (continuation[2] === " ") break;
            // A corrected dated line keeps its own continuation: at the base, the line above that
            // continuation was a dated line, and that line is gone now.
            const at = base.indexOf(lines[next - 1]);
            if (at > 0 && HISTORY.test(base[at - 1]) && !lines.includes(base[at - 1])) break;
            let last = next;
            while (last + 1 <= header && !isNew(last + 1) && continuationAt(last + 1)) last++;
            findings.push({ path: file.path, line,
              message: `this entry splits the entry above from its continuation on line ${next}; move it below line ${last}.` });
            break;
          }
          if (continuation[1].length !== prefix[1].length) {
            findings.push({ path: file.path, line: next,
              message: `align the history continuation with the description at column ${prefix[1].length + 1}.` });
          }
          if (!endsSentence(next - 1)) {
            findings.push({ path: file.path, line: next,
              message: "history continuations start a new sentence; keep the preceding sentence on one line." });
          }
        }
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

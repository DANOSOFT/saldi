// tools/guardrails/checks/migrations.mjs - schema changes go through the update chain, in order.
//
// Rules (doc/ai/convention_database_changes.md, doc/ai/feedback_protected_update_files.md):
//   1. DDL and ensureTableAndColumns() calls live only in the update/install
//      files. Past mistakes: 5bc1a329, 76ff76b5 and 702c7aa3 created tables and
//      columns from includes that run on every request; #540 review.
//   2. includes/opdat_*.php (released versions) and includes/betweenUpdates2.php
//      (a backup) are not edited. Past mistakes: c97cb4c7, 5bc1a329, 064afcdc, d57c564e.
//   3. A new betweenUpdates.php entry is appended at the end, because updates
//      run top to bottom. Past mistakes: d57c564e, 96b64bc6, 323e8bf3, ef323006.
//
// A version cut legitimately moves betweenUpdates.php into an opdat file; mark
// it with an exception (lib/exceptions.mjs) on the first added line.
//
// History:
// 20261027 CL/LH Created.

import { splitLines } from "../lib/changeset.mjs";

const UPDATES = "includes/betweenUpdates.php";
const PROTECTED = /^includes\/(opdat_[^/]+|betweenUpdates2)\.php$/;
const SCHEMA_FILES = /^(includes\/(betweenUpdates|opdater|opdat_[^/]+)\.php|includes\/opdat_func\/|index\/install\.php|admin\/opret\.php|tests\/|tools\/)/;
const DDL = /\b(ALTER\s+TABLE|CREATE\s+(UNIQUE\s+)?INDEX|CREATE\s+TABLE|DROP\s+(TABLE|INDEX|COLUMN)|ADD\s+COLUMN|RENAME\s+COLUMN)\b|\bensureTableAndColumns\s*\(/i;
const TEMPORARY = /CREATE\s+(TEMP|TEMPORARY)\s+TABLE/i;
const COMMENT = /^\s*(\/\/|#|\*|\/\*)/;

/** Index (1-based) of the first line after the header comment block, and of the first footer line. */
function bodyBounds(lines) {
  let start = 1;
  while (start <= lines.length && /^\s*(<\?php|\/\/.*|#.*)?\s*$/.test(lines[start - 1])) start++;
  let end = lines.length;
  while (end >= start && /^\s*(\?>|#+|)\s*$/.test(lines[end - 1])) end--;
  return { start, end };
}

export default {
  id: "migrations",
  summary: "DDL only in the update chain, released opdat files untouched, betweenUpdates.php appended at the end",
  run(ctx) {
    const findings = [];
    for (const file of ctx.files) {
      if (!file.path.endsWith(".php") || ctx.isVendored(file.path)) continue;

      if (PROTECTED.test(file.path)) {
        const first = [...file.added.keys()][0];
        findings.push({
          path: file.path,
          line: first,
          message:
            `${file.path} is a released update (or a backup) and is not edited. Put the change at the end of ${UPDATES}. ` +
            `A version cut is the one exception; mark it on this line (lib/exceptions.mjs).`,
        });
        continue;
      }

      if (!SCHEMA_FILES.test(file.path)) {
        for (const [line, text] of file.added) {
          if (COMMENT.test(text) || !DDL.test(text) || TEMPORARY.test(text)) continue;
          findings.push({
            path: file.path,
            line,
            message:
              `schema change outside the update chain. Pages and includes must not create or alter tables at request time; ` +
              `add the change idempotently at the end of ${UPDATES} (doc/ai/convention_database_changes.md).`,
          });
        }
      }

      if (file.path === UPDATES && file.status === "M") {
        const lines = splitLines(ctx.readHead(UPDATES));
        const { start, end } = bodyBounds(lines);
        const sinceFork = ctx.addedSinceFork(UPDATES);
        let lastExisting = 0;
        for (let line = start; line <= end; line++) if (!file.added.has(line) && !sinceFork.has(line)) lastExisting = line;
        const early = [...file.added.keys()].filter((line) => line >= start && line < lastExisting && lines[line - 1].trim() !== "");
        if (early.length) {
          findings.push({
            path: UPDATES,
            line: early[0],
            message:
              `lines ${early[0]}-${early[early.length - 1]} are added above the existing entry ending on line ${lastExisting}. ` +
              `Updates run top to bottom, so append new entries at the end. If this must run before an existing entry, ` +
              `say which one in an exception on this line.`,
          });
        }
      }
    }
    return findings;
  },
};

// tools/guardrails/checks/tekster.mjs - numeric and descriptive translation seed files stay loadable.
//
// The file is read as raw tab-separated text: `id<TAB>Dansk<TAB>English<TAB>Norsk`,
// no quoting. findtekst() caches each row in the tenant's `tekster` table and
// always prefers that cached row, so a row's text can never quietly change.
// Descriptive keys use the same language columns in tekster_keyed.tsv, separate
// from the integer-only tekster.csv file. egne_tekster_keyed.tsv uses the same
// validation and overrides matching standard keys.
//
// Past mistakes this catches:
//   - malformed rows: spaces instead of tabs (407c858c), extra tab columns
//     (61c09faf, 157c830d, 33743486), CSV-style doubled quotes (ea615808)
//   - a reworded or reused id with no stale-row purge, so tenants keep the old
//     text (33743486 id 734, ea615808 id 2643, d57c564e, the blocks behind
//     deleteStaleTekst())
//   - two branches taking the same id (66f9b780, 88c23099, e76b88b4, #549)
//   - findtekst('NNNN|fallback') pointing at the wrong id (25e8a19e, 01451348)
//
// History:
// 20261027 CL/LH Created.
// 20261029 CDX/NTR Validate standard/custom descriptive-key TSVs and keyed fallbacks.
// 20261029 CDX/NTR Require new custom overrides to purge standard translations already cached by tenants.
// 20261029 CDX/NTR Check keyed TSV ordering when either adjacent row was added.

import { splitLines } from "../lib/changeset.mjs";

const CSV = "importfiler/tekster.csv";
const KEYED_TSV = "importfiler/tekster_keyed.tsv";
const CUSTOM_KEYED_TSV = "importfiler/egne_tekster_keyed.tsv";
const UPDATES = "includes/betweenUpdates.php";
const ENTITIES = {
  aelig: "æ", oslash: "ø", aring: "å", AElig: "Æ", Oslash: "Ø", Aring: "Å", amp: "&", nbsp: " ", quot: '"',
  apos: "'", lt: "<", gt: ">", eacute: "é", uuml: "ü", ouml: "ö", auml: "ä", Uuml: "Ü", Ouml: "Ö", Auml: "Ä",
  hellip: "…", ndash: "–", mdash: "—", euro: "€",
};

/** identifier -> {line, fields} for every row; duplicates are reported separately. */
function rows(content) {
  const byId = new Map();
  const duplicates = [];
  splitLines(content).forEach((text, index) => {
    if (text === "") return;
    const fields = text.split("\t");
    const row = { line: index + 1, text, fields };
    if (byId.has(fields[0])) duplicates.push({ ...row, first: byId.get(fields[0]).line });
    else byId.set(fields[0], row);
  });
  return { byId, duplicates };
}

/** Keys passed to deleteStaleTekst() by added migration lines. */
function purgedKeys(ctx) {
  const file = ctx.files.find((f) => f.path === UPDATES);
  const keys = new Set();
  if (!file) return keys;
  for (const text of file.added.values()) {
    for (const match of text.matchAll(/deleteStaleTekst\(\s*(['"])([a-z0-9][a-z0-9_.-]{0,189})\1/g)) keys.add(match[2]);
  }
  return keys;
}

/** How a text reads to a user: entities decoded, tags and case and spacing ignored. */
function normalise(text) {
  return text
    .replace(/&(#\d+|#x[0-9a-f]+|[a-z]+);/gi, (entity, name) => {
      if (name[0] === "#") return String.fromCodePoint(name[1] === "x" || name[1] === "X" ? parseInt(name.slice(2), 16) : Number(name.slice(1)));
      return ENTITIES[name] ?? entity;
    })
    .replace(/\\(['"])/g, "$1")
    .replace(/<[^>]+>/g, "")
    .toLowerCase()
    .replace(/\s+/g, " ")
    .replace(/[\s:.!?]+$/, "")
    .trim();
}

/** Ids that the change's added lines in betweenUpdates.php mention, as numbers or [from, to] ranges. */
function purgedIds(ctx) {
  const file = ctx.files.find((f) => f.path === UPDATES);
  const ids = new Set();
  const ranges = [];
  if (!file) return { has: () => false };
  for (const text of file.added.values()) {
    for (const range of text.matchAll(/\[\s*(\d+)\s*,\s*(\d+)\s*\]/g)) ranges.push([Number(range[1]), Number(range[2])]);
    for (const number of text.matchAll(/\b\d+\b/g)) ids.add(Number(number[0]));
  }
  return { has: (id) => ids.has(id) || ranges.some(([from, to]) => id >= from && id <= to) };
}

function checkCsv(ctx, file, findings) {
  const head = rows(ctx.readHead(CSV) ?? "");
  const base = rows(ctx.readBase(CSV) ?? "");
  const purged = purgedIds(ctx);

  for (const dup of head.duplicates) {
    findings.push({
      path: CSV,
      line: dup.line,
      message: `id ${dup.fields[0]} is already used on line ${dup.first}. Another branch took it first; give this text a new id above the current maximum.`,
    });
  }

  for (const [line, text] of file.added) {
    if (text === "") continue;
    const fields = text.replace(/\r$/, "").split("\t");
    const problems = [];
    if (!/^\d+$/.test(fields[0])) problems.push(`the id "${fields[0]}" is not a number (a space instead of a tab?)`);
    if (fields.length !== 4) problems.push(`it has ${fields.length} tab-separated fields; it needs exactly 4: id, Dansk, English, Norsk`);
    else if (fields.slice(1).some((field) => field.trim() === "")) problems.push("a language column is empty");
    if (fields.some((field) => field !== field.trim())) problems.push("a field starts or ends with whitespace");
    if (fields.some((field) => /""|^".*"$/.test(field))) problems.push('it uses CSV quoting; the file is raw tab-separated text, so the quotes show up on screen');
    if (problems.length) findings.push({ path: CSV, line, message: `malformed row: ${problems.join("; ")}.` });
  }

  for (const [id, before] of base.byId) {
    const after = head.byId.get(id);
    if (!after) {
      const users = ctx.grepHead(["-nE", `findtekst\\(\\s*['"]?${id}([|'"]|\\s*,)`, "--", "*.php"]);
      if (users.length) {
        findings.push({ path: CSV, message: `id ${id} was removed but is still used, e.g. ${users[0].replace(/^[^:]+:/, "")}` });
      }
      continue;
    }
    if (after.text === before.text) continue;
    if (after.fields.map((f) => f.trim()).join("\t") === before.fields.map((f) => f.trim()).join("\t")) {
      findings.push({ path: CSV, line: after.line, message: `id ${id} changed only in whitespace; put the row back as it was.` });
    } else if (!purged.has(Number(id))) {
      findings.push({
        path: CSV,
        line: after.line,
        message:
          `id ${id}'s text changed, but tenants keep the old text: findtekst() prefers the row cached in their tekster table. ` +
          `Either give the new text a new id, or add deleteStaleTekst(${id}, '<old text>') at the end of ${UPDATES} ` +
          `(includes/opdat_func/deleteStaleTekst.php).`,
      });
    }
  }
}

function checkKeyedTsv(ctx, file, path, findings) {
  const head = rows(ctx.readHead(path) ?? "");
  const base = rows(ctx.readBase(path) ?? "");
  const purged = purgedKeys(ctx);

  for (const dup of head.duplicates) {
    findings.push({
      path,
      line: dup.line,
      message: `key ${dup.fields[0]} is already used on line ${dup.first}; every descriptive key must be unique.`,
    });
  }

  let previous = null;
  for (const row of head.byId.values()) {
    const key = row.fields[0];
    if (previous !== null && key < previous.fields[0] && (file.added.has(previous.line) || file.added.has(row.line))) {
      findings.push({ path, line: row.line, message: `key ${key} is out of order; keep descriptive keys sorted alphabetically.` });
    }
    previous = row;
  }

  for (const [line, text] of file.added) {
    if (text === "") continue;
    const fields = text.replace(/\r$/, "").split("\t");
    const key = fields[0] ?? "";
    const problems = [];
    if (/^\d+$/.test(key)) problems.push(`the key "${key}" is numeric; numeric ids belong in ${CSV}`);
    else if (!/^[a-z0-9][a-z0-9_.-]{0,189}$/.test(key)) problems.push(`the key "${key}" must be 1-190 lowercase letters, digits, dots, underscores or hyphens`);
    if (fields.length !== 4) problems.push(`it has ${fields.length} tab-separated fields; it needs exactly 4: tekst_key, Dansk, English, Norsk`);
    else if (fields.slice(1).some((field) => field.trim() === "")) problems.push("a language column is empty");
    if (fields.some((field) => field !== field.trim())) problems.push("a field starts or ends with whitespace");
    if (fields.some((field) => /""|^".*"$/.test(field))) problems.push("it uses TSV quoting; the file is raw tab-separated text, so the quotes show up on screen");
    if (problems.length) findings.push({ path, line, message: `malformed row: ${problems.join("; ")}.` });
  }

  for (const [key, before] of base.byId) {
    const after = head.byId.get(key);
    if (!after || after.text === before.text) continue;
    if (after.fields.map((field) => field.trim()).join("\t") === before.fields.map((field) => field.trim()).join("\t")) {
      findings.push({ path, line: after.line, message: `key ${key} changed only in whitespace; put the row back as it was.` });
    } else if (!purged.has(key)) {
      findings.push({
        path,
        line: after.line,
        message:
          `key ${key}'s text changed, but tenants keep the old text: findtekst() prefers the row cached in their tekster table. ` +
          `Add deleteStaleTekst('${key}', <old text or texts>) at the end of ${UPDATES}.`,
      });
    }
  }

  if (path === CUSTOM_KEYED_TSV) {
    const standard = rows(ctx.readHead(KEYED_TSV) ?? "").byId;
    for (const [key, custom] of head.byId) {
      const standardRow = standard.get(key);
      if (!standardRow || base.byId.has(key) || purged.has(key)) continue;
      findings.push({
        path,
        line: custom.line,
        message:
          `key ${key} overrides ${KEYED_TSV}, but tenants may already have its standard translation cached. ` +
          `Add deleteStaleTekst('${key}', <the old standard translations>) at the end of ${UPDATES}.`,
      });
    }
  }
}

function checkFallbacks(ctx, findings) {
  const csv = rows(ctx.readHead(CSV) ?? "").byId;
  const keyed = rows(ctx.readHead(KEYED_TSV) ?? "").byId;
  const customKeyed = rows(ctx.readHead(CUSTOM_KEYED_TSV) ?? "").byId;
  for (const [key, row] of customKeyed) keyed.set(key, row);
  for (const file of ctx.files) {
    if (file.status === "D" || !file.path.endsWith(".php") || file.path.startsWith("tests/") || ctx.isVendored(file.path)) continue;
    for (const [line, text] of file.added) {
      for (const match of text.matchAll(/findtekst\(\s*(['"])(\d+)\|((?:\\.|(?!\1).)*)\1/g)) {
        const [, , id, fallback] = match;
        if (/[$}]/.test(fallback) || fallback.trim() === "") continue;
        const row = csv.get(id);
        if (!row) {
          findings.push({ path: file.path, line, message: `findtekst('${id}|...'): id ${id} is not in ${CSV}; add the row in the same change.` });
          continue;
        }
        const wanted = normalise(fallback);
        if (row.fields.slice(1).some((field) => normalise(field) === wanted)) continue;
        findings.push({
          path: file.path,
          line,
          message:
            `findtekst('${id}|${fallback}') does not match ${CSV} id ${id} ("${row.fields[1] ?? ""}"). ` +
            `Either the id is wrong (users see id ${id}'s text, not the fallback) or the fallback is out of date.`,
        });
      }
      for (const match of text.matchAll(/findtekst\(\s*(['"])(?!\d+\|)([a-z0-9][a-z0-9_.-]{0,189})\|((?:\\.|(?!\1).)*)\1/g)) {
        const [, , key, fallback] = match;
        if (/[$}]/.test(fallback) || fallback.trim() === "") continue;
        const row = keyed.get(key);
        if (!row) {
          findings.push({ path: file.path, line, message: `findtekst('${key}|...'): key ${key} is not in ${CUSTOM_KEYED_TSV} or ${KEYED_TSV}; add the row in the same change.` });
          continue;
        }
        const wanted = normalise(fallback);
        if (row.fields.slice(1).some((field) => normalise(field) === wanted)) continue;
        findings.push({
          path: file.path,
          line,
          message:
            `findtekst('${key}|${fallback}') does not match the effective keyed TSV row for ${key} ("${row.fields[1] ?? ""}"). ` +
            `Either the key is wrong or the fallback is out of date.`,
        });
      }
    }
  }
}

export default {
  id: "tekster",
  summary: "numeric and descriptive translation seeds are well-formed, unique, cache-safe, and match findtekst fallbacks",
  run(ctx) {
    const findings = [];
    const csvFile = ctx.files.find((file) => file.path === CSV && file.status !== "D");
    const keyedFile = ctx.files.find((file) => file.path === KEYED_TSV && file.status !== "D");
    const customKeyedFile = ctx.files.find((file) => file.path === CUSTOM_KEYED_TSV && file.status !== "D");
    if (csvFile) checkCsv(ctx, csvFile, findings);
    if (keyedFile) checkKeyedTsv(ctx, keyedFile, KEYED_TSV, findings);
    if (customKeyedFile) checkKeyedTsv(ctx, customKeyedFile, CUSTOM_KEYED_TSV, findings);
    checkFallbacks(ctx, findings);
    return findings;
  },
};

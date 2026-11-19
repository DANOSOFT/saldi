// tools/guardrails/checks/pr-body.mjs - a PR says what it changes, how it was verified, and its door.
//
// About 25 PRs since July were sent back because the description was the
// unfilled template or had no verification (#466, #467, #476, #477, #516,
// #745-#756, #840, #884, #885, #898). Whether the verification is good enough
// stays the reviewer's call; this only checks that it is there.
//
// The door check. The "## Merge Danger" section says
//   Door: one-way | two-way        After merge: none, or each step and who takes it
// (bold, bullets and case are fine). With a change to judge (--base, and --head
// for a past commit), a two-way door is held against the one-way rows of
// lib/doors.mjs: one finding per file that a one-way row claims by path, or by a
// marker on an added or removed line that is not a comment. Care rows never
// fail, and a one-way door passes whatever the change touches: the map is a
// floor, and the author may know something it does not. A removed line is read
// in the base version of its file, as an added line is in the head version.
// Blast Radius and Undo are not checked yet.
//
// The door is the value alone, or the value and an explanation after a space or
// one of ( : ; , . ("two-way (layout only)"); "one-wayish" or both values is no door.
//
// A finding that is wrong is let through by a visible line in the Merge Danger
// section that names the commit the reviewer approved and, in its reason, the
// file (all of its rows) or a row (that row only):
//   guardrails-allow pr-body until 2026-12-31 by NTR at 1a2b3c4d5e6f: debitor/betalinger.php removes a broken menu option
// A body edit dismisses no approval, so the text alone proves nothing: the line
// counts only when the description's edit history (--pr-edits, read by CI) shows
// that a reviewer in REVIEWERS (lib/doors.mjs) put it into the Merge Danger
// section, signed with their own initials, on a PR they did not author, and only
// while the file is as it was at that commit (git diff <sha> <head> -- <file>
// shows no change). Without the history no exception counts.
//
// Proven on: #884 (cce4a13a, posting in finans/bogfor.php, reverted by #931) and
// #768 (16c53aea, four payments files), each with "Door: two-way"; #884's own
// description had no Merge Danger at all.
//
// History:
// 20261027 CL/LH Created.
// 20261103 CL/LH The Merge Danger section's Door and After merge fields; a two-way door is held against lib/doors.mjs.
// 20261103 CL/LH A door exception counts only when a reviewer's edit added it; the door value must be exact.
// 20261106 CL/LH The cross-check reads each file's lines as --station does, PHP files by PHP's own tokens.
// 20261109 CL/LH An exception is bound to the commit it was approved at and counts for the rows it names; its owner is
//                who put it into the active Merge Danger section. Removed lines are read in the base file.

import { markerLinesOf, removedLinesOf } from "../lib/changeset.mjs";
import { REVIEWERS, prefetchPhp } from "../lib/doors.mjs";
import { parseException } from "../lib/exceptions.mjs";
import "../lib/php.mjs";
import { oneWayRowsOf } from "../lib/station.mjs";

const PLACEHOLDERS = [/Provide a brief explanation of the changes you have made/gi];
const FIELD = /^\s*(?:[-*+]\s+)?(?:\*\*|__)?\s*(door|blast radius|undo|after merge)\s*(?:\*\*|__)?\s*:\s*(?:\*\*|__)?(.*)$/i;
const DOORS = ["one-way", "two-way"];
/** A door value: the value alone, or followed by a space or ( : ; , . and an explanation. */
const DOOR_VALUE = /^(one-way|two-way)(?=$|[\s(:;,.])/i;
const MAP = "tools/guardrails/lib/doors.mjs";
const FORMAT = "guardrails-allow pr-body until YYYY-MM-DD by <INITIALS> at <sha>: <file or row> <why>";

/** The sections the checks read: all but CodeRabbit's. */
const ownSections = (body) => sections(body).filter((section) => !/coderabbit/i.test(section.title));

/** The active Merge Danger section: the first of the own sections, comments taken out, or undefined. */
const dangerOf = (body) => ownSections(body).find((section) => /merge danger/i.test(section.title));

function sections(body) {
  const result = [];
  let current = null;
  for (const line of body.replace(/<!--[\s\S]*?-->/g, "").split(/\r?\n/)) {
    const heading = /^#{1,6}\s+(.*?)\s*#*\s*$/.exec(line);
    if (heading) {
      current = { title: heading[1], text: "" };
      result.push(current);
    } else if (current) {
      current.text += `${line}\n`;
    }
  }
  return result;
}

function content(text) {
  let rest = text;
  for (const placeholder of PLACEHOLDERS) rest = rest.replace(placeholder, "");
  return rest.replace(/^\s*-\s*\[[ xX]\].*$/gm, "").replace(/\s+/g, " ").trim();
}

function clean(value) {
  return value.replace(/\*\*|__|`/g, "").replace(/\s+/g, " ").trim();
}

/**
 * The Merge Danger fields: {door, blastRadius, undo, afterMerge} as written, or undefined when absent.
 * A field with nothing after its label on the line takes the lines below it, up to a blank line.
 */
function fields(text) {
  const found = {};
  const lines = text.split("\n");
  for (let i = 0; i < lines.length; i++) {
    const match = FIELD.exec(lines[i]);
    if (!match) continue;
    const key = match[1].toLowerCase().replace(/ (\w)/, (_, letter) => letter.toUpperCase());
    let value = match[2];
    if (clean(value) === "") {
      for (let next = i + 1; next < lines.length && lines[next].trim() !== "" && !FIELD.test(lines[next]); next++) {
        if (!lines[next].includes("guardrails-allow")) value += ` ${lines[next]}`;
      }
    }
    if (!(key in found)) found[key] = clean(value);
  }
  return found;
}

/** "one-way", "two-way", or null when `door` is neither, exactly. */
export function doorValue(door) {
  const match = DOOR_VALUE.exec(door ?? "");
  if (!match) return null;
  const value = match[1].toLowerCase();
  const other = DOORS.find((candidate) => candidate !== value);
  return door.toLowerCase().includes(other) ? null : value;
}

function doorFindings(danger) {
  const findings = [];
  const { door, afterMerge } = fields(danger.text);
  const allowed = DOORS.map((value) => `"${value}"`).join(" or ");
  if (door === undefined || door === "") {
    findings.push({ message: `the Merge Danger section has no "Door:"; write "**Door:** one-way" or "**Door:** two-way" (map: ${MAP}).` });
  } else if (!doorValue(door)) {
    findings.push({
      message:
        `"Door: ${door}" is not a door; write ${allowed}, alone or followed by a space and why. ` +
        "Two-way with care is written two-way, with its real blast radius.",
    });
  }
  if (afterMerge === undefined || afterMerge === "") {
    findings.push({
      message: 'the Merge Danger section has no "After merge:"; write "**After merge:** none", or each step someone must take after the merge or the deploy, and who.',
    });
  } else if (!/^none\b/i.test(afterMerge) && afterMerge.length < 10) {
    findings.push({ message: `"After merge: ${afterMerge}" says too little; write "none", or the step and who takes it.` });
  }
  return { findings, door: doorValue(door) };
}

/**
 * The description's edit history, from the GraphQL answer CI saves (guardrails.yml):
 * pullRequest {author {login}, userContentEdits {totalCount, nodes {editor {login}, deletedAt, diff}}},
 * newest edit first. Once the description is edited, the oldest node is the text it was opened with.
 *
 * @returns {{author: string|null, revisions: {editor: string|null, body: string|null}[], complete: boolean}
 *   | {error: string}}  revisions oldest first; body null for a revision GitHub no longer shows
 */
export function readHistory(response) {
  if (typeof response?.error === "string") return { error: response.error };
  const pr = response?.data?.repository?.pullRequest;
  const edits = pr?.userContentEdits;
  if (!pr || !Array.isArray(edits?.nodes) || !Number.isInteger(edits.totalCount)) return { error: "the --pr-edits file is not a pull request's edit history" };
  return {
    author: pr.author?.login ?? null,
    revisions: [...edits.nodes].reverse().map((node) => ({
      editor: node?.editor?.login ?? null,
      body: typeof node?.diff === "string" && !node.deletedAt ? node.diff : null,
    })),
    complete: edits.totalCount === edits.nodes.length,
  };
}

function linesOf(text) {
  return new Set(text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean));
}

/** The lines of a description's active Merge Danger section, trimmed: where an exception line counts. */
const dangerLines = (body) => linesOf(dangerOf(body)?.text ?? "");

/**
 * Who put each line into the description's active Merge Danger section, revision by revision through the history: the
 * editor of the revision where the line last entered the section (a line moved into it from elsewhere in the
 * description is the mover's), or null when the history cannot tell (a hidden revision, or more edits than were read).
 * A description never edited is all its author's.
 *
 * @returns {Map<string, string|null>}  trimmed line -> GitHub login
 */
export function lineOwners(history, body) {
  if (!history.revisions.length) return new Map([...dangerLines(body)].map((line) => [line, history.complete ? history.author : null]));
  let previous = history.complete ? new Set() : null;
  let owners = new Map();
  for (const revision of history.revisions) {
    if (revision.body === null) {
      previous = null;
      owners = new Map();
      continue;
    }
    const lines = dangerLines(revision.body);
    owners = new Map([...lines].map((line) => [line, previous === null ? null : previous.has(line) ? (owners.get(line) ?? null) : revision.editor]));
    previous = lines;
  }
  return owners;
}

/** Why an exception line does not count, or null when a reviewer put it into the Merge Danger section. */
function unapproved(exception, line, ctx) {
  const reviewers = REVIEWERS.map((reviewer) => `${reviewer.name} (${reviewer.initials})`).join(" or ");
  if (!ctx.history) return "who added it cannot be checked without the description's edit history (CI passes --pr-edits)";
  if (ctx.history.error) return `who added it cannot be checked: ${ctx.history.error}`;
  const owner = ctx.owners.get(line.trim()) ?? null;
  if (owner === null) return "the description's edit history does not show who added it";
  const reviewer = REVIEWERS.find((entry) => entry.login.toLowerCase() === owner.toLowerCase());
  if (!reviewer) return `${owner} added it, and only ${reviewers} can`;
  if (ctx.history.author && owner.toLowerCase() === ctx.history.author.toLowerCase()) return `${owner} added it to their own PR; the other approver adds it`;
  if (exception.by !== reviewer.initials) return `it is signed ${exception.by}, but ${reviewer.name} added it and signs ${reviewer.initials}`;
  return null;
}

/**
 * Why an exception no longer covers `path`, or null while the file is as it was at the commit the exception names
 * (git diff <sha> <head> -- <path> shows no change): a change after the approval needs a new one.
 */
function outdated(exception, path, ctx) {
  const renew = "the reviewer approves the change again with a line at the PR's current head";
  if (!exception.at) return `it names no commit; ${renew} ("by <INITIALS> at <sha>:")`;
  const changed = ctx.changedSince ? ctx.changedSince(exception.at, path) : null;
  if (changed === null) return `the commit ${exception.at} it names cannot be read in this change's history; ${renew}`;
  if (changed) return `${path} changed after ${exception.at}, the commit it was approved at; ${renew}`;
  return null;
}

const escapeRegExp = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

/**
 * Whether an exception's reason names `name`, a file's path or a row's id, whole: not the end or the start of a longer
 * path or word (bogfor.php in finans/bogfor.php, payments in debitor/payments/).
 */
const names = (reason, name) => new RegExp(`(?<![\\w./-])${escapeRegExp(name)}(?![\\w/-]|\\.\\w)`).test(reason);

/** A two-way door against the change: one finding per file a one-way row claims. */
function crossCheck(danger, ctx) {
  const findings = [];
  const exceptions = [];
  for (const line of danger.text.split("\n")) {
    const exception = parseException(line, "pr-body", ctx.today);
    if (!exception || exception.kind === "other") continue;
    if (exception.kind === "allowed") exceptions.push({ ...exception, problem: unapproved(exception, line, ctx) });
    else if (exception.kind === "expired") findings.push({ message: `the Merge Danger exception for pr-body expired on ${exception.until}.` });
    else findings.push({ message: `a Merge Danger exception line is malformed; write "${FORMAT}".` });
  }
  // Each file's lines as --station reads them (markerLinesOf: every unchanged line above and below each run of added
  // ones, and a last line's eof mark, from the file as the change leaves it, or from the diff's hunks where it is not
  // known), and its removed lines the same way in the file as it was (removedLinesOf).
  const entries = new Map(
    ctx.files.map((file) => [
      file.path,
      { added: markerLinesOf(file, ctx.readHead?.(file.path) ?? null), removed: removedLinesOf(file, ctx.readBase?.(file.path) ?? null) },
    ]),
  );
  prefetchPhp([...entries].filter(([path]) => !ctx.isVendored?.(path)).flatMap(([path, lines]) => [[path, lines.added], [path, lines.removed]]));
  const head = ctx.headSha ? ctx.headSha.slice(0, 12) : "<sha>";
  for (const file of ctx.files) {
    const { added, removed } = entries.get(file.path);
    const rows = oneWayRowsOf(file.path, added, ctx.isVendored, removed).filter((match) => match.row.crossCheck !== false);
    if (!rows.length) continue;
    // An exception that names the file lets all of its rows through; one that names a row lets that row through.
    const naming = exceptions
      .filter((exception) => names(exception.reason, file.path) || rows.some((match) => names(exception.reason, match.row.id)))
      .map((exception) => ({ ...exception, problem: exception.problem ?? outdated(exception, file.path, ctx) }));
    const approved = naming.filter((exception) => exception.problem === null);
    if (approved.some((exception) => names(exception.reason, file.path))) continue;
    const left = rows.filter((match) => !approved.some((exception) => names(exception.reason, match.row.id)));
    if (!left.length) continue;
    const why = left
      .map(({ row, pattern, marker }) => `${row.id} ${pattern ? "by its path " + pattern : 'by the marker "' + marker + '"'} (${row.why})`)
      .join("; ");
    const refusedLine = naming.find(
      (exception) => exception.problem !== null && (names(exception.reason, file.path) || left.some((match) => names(exception.reason, match.row.id))),
    );
    const refused = refusedLine ? ` The exception line naming it does not count: ${refusedLine.problem}.` : "";
    findings.push({
      path: file.path,
      message:
        `the PR says "Door: two-way", but the door map makes this file one-way: ${why}. Say "Door: one-way" and how to undo, ` +
        `or, if this change cannot leave anything behind, ask your reviewer to add ` +
        `"guardrails-allow pr-body until YYYY-MM-DD by <INITIALS> at ${head}: ${file.path} <why>" to the Merge Danger section. Map: ${MAP}.${refused}`,
    });
  }
  return findings;
}

export default {
  id: "pr-body",
  summary: "the PR description is filled in: what changed, how it was verified, and a door that matches the change",
  /**
   * @param {string} body  the PR description
   * @param {{today?: string, files?: object[], isVendored?: Function, readHead?: Function, readBase?: Function,
   *   changedSince?: Function, headSha?: string|null, edits?: object}} [ctx]  the change (lib/changeset.mjs), when
   *   check.mjs has a base; edits: the description's edit history as CI reads it (readHistory), or {error}
   */
  run(body, ctx = {}) {
    const findings = [];
    const own = ownSections(body ?? "");
    const what = own.find((section) => /what|summary|why|change|description|baggrund|ændring/i.test(section.title));
    const verified = own.find((section) => /verif|test|how i|afprøv|tjek/i.test(section.title));
    if (!what || content(what.text).length < 20) {
      findings.push({ message: 'fill in "## What are the changes about?": what the change does and why, in a sentence or two.' });
    }
    if (!verified || content(verified.text).length < 40) {
      findings.push({
        message:
          'fill in "## How I verified this": the page and steps you tried in a local tenant and what you saw, ' +
          "and the tests you ran. Fixtures alone are not enough for a change users see.",
      });
    }
    const danger = dangerOf(body ?? "");
    if (!danger) {
      findings.push({
        message:
          'add a "## Merge Danger" section with "**Door:** one-way" or "two-way" (one-way: a revert does not take back what it leaves: ' +
          `rows, a number, a schema change, money, a mail, deleted data) and "**After merge:** none" or each step and who takes it. Map: ${MAP}`,
      });
      return findings;
    }
    const door = doorFindings(danger);
    findings.push(...door.findings);
    if (door.door === "two-way" && ctx.files) {
      const history = ctx.edits === undefined ? null : readHistory(ctx.edits);
      const owners = history && !history.error ? lineOwners(history, body ?? "") : new Map();
      findings.push(...crossCheck(danger, { ...ctx, history, owners, today: ctx.today ?? new Date().toISOString().slice(0, 10) }));
    }
    return findings;
  },
};

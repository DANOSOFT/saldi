// tools/guardrails/lib/changeset.mjs - what a change adds, read straight from git.
//
// Every guardrail judges a change, not the whole tree: the legacy code breaks
// most of the rules somewhere, and a check that fails on code nobody touched
// teaches people to ignore it. So each check gets the files the change touches,
// the line numbers it added, and both versions of each file.
//
// "The change" is merge-base(base, head)..head. Without --head it is the
// working tree, including staged, unstaged and untracked files, so the same
// command works before a commit, on a branch and in CI.
//
// History:
// 20261027 CL/LH Created.
// 20261104 CL/LH VENDORED lists the minified jQuery copies, so no door marker reads them.
// 20261104 CL/LH addedWithContext() and markerLines() hand the door markers the unchanged lines around each added run.
// 20261106 CL/LH Lines are split where doors.mjs defines a line terminator; a bare CR stays in the line's text.
// 20261107 CL/LH addedWithContext() marks its last entry cut where the file goes on below it.
// 20261109 CL/LH Removed lines keep their base line numbers; markerLinesOf() and removedLinesOf() keep a hunk's eof
//                mark where the file's text is not known; changedSince() compares a file with a commit's.
// 20261109 CL/LH A diff header's path is read exactly (git's C-style quoting undone), and the changed paths come from
//                git's NUL-separated list: a file whose header cannot be read counts every line as changed.

import { execFileSync } from "node:child_process";
import { readFileSync, existsSync } from "node:fs";
import { LINE_END, fileLines, lineText } from "./doors.mjs";

/**
 * Paths nobody here maintains: third-party code checked into the repo. The minified jQuery copies
 * (jquery-1.8.0.min.js, jquery-ui.min.js, the .min.js plugins) are vendored; Saldi's own jquery.*.js
 * plugins (jquery.ansatte.js, jquery.sager.js, ...) are not.
 */
export const VENDORED = /^(vendor|includes\/vendor|node_modules|tiny_mce)\/|^(javascript|sager\/js)\/jquery[^/]*\.min\.js$/;

export function git(args, options = {}) {
  return execFileSync("git", ["-c", "core.quotePath=false", ...args], {
    encoding: "utf8",
    maxBuffer: 1 << 30,
    stdio: ["ignore", "pipe", "pipe"],
    ...options,
  });
}

export function revExists(rev) {
  try {
    git(["rev-parse", "--verify", "--quiet", `${rev}^{commit}`]);
    return true;
  } catch {
    return false;
  }
}

/** The byte each of git's C-style escapes stands for (quote_c_style). */
const C_ESCAPES = { a: 7, b: 8, t: 9, n: 10, v: 11, f: 12, r: 13, '"': 34, "\\": 92 };

/**
 * The path git quoted C-style ("a/x\"y", "a/x\tb", "a/\303\246"), starting at the quote at `text[i]`: [path, the
 * index after its closing quote], or null where the quoting is malformed. Escapes give bytes, read as UTF-8.
 */
function unquoteC(text, i) {
  if (text[i] !== '"') return null;
  const bytes = [];
  for (let j = i + 1; j < text.length; ) {
    const c = text[j];
    if (c === '"') return [Buffer.from(bytes).toString("utf8"), j + 1];
    if (c !== "\\") {
      const point = String.fromCodePoint(text.codePointAt(j));
      bytes.push(...Buffer.from(point, "utf8"));
      j += point.length;
      continue;
    }
    const next = text[j + 1];
    if (next in C_ESCAPES) {
      bytes.push(C_ESCAPES[next]);
      j += 2;
    } else if (/^[0-3][0-7]{2}$/.test(text.slice(j + 1, j + 4))) {
      bytes.push(parseInt(text.slice(j + 1, j + 4), 8));
      j += 4;
    } else {
      return null;
    }
  }
  return null;
}

/**
 * The path a "diff --git a/P b/P" header names, read exactly: both halves are the same path because renames are off,
 * and git quotes one C-style where it holds a quote, a backslash or a control character (or, with core.quotePath on, a
 * byte above ASCII). Null where the header cannot be read so: the caller never guesses a path.
 */
export function headerPath(raw) {
  if (!raw.startsWith("diff --git ")) return null;
  const rest = raw.slice("diff --git ".length);
  let a;
  let b;
  if (rest.startsWith('"')) {
    const first = unquoteC(rest, 0);
    if (!first || rest[first[1]] !== " ") return null;
    const second = unquoteC(rest, first[1] + 1);
    if (!second || second[1] !== rest.length) return null;
    [a, b] = [first[0], second[0]];
  } else {
    // "a/P b/P", P unquoted in both halves: the space between them is the middle character.
    const half = (rest.length - 1) / 2;
    if (!Number.isInteger(half) || rest[half] !== " ") return null;
    [a, b] = [rest.slice(0, half), rest.slice(half + 1)];
  }
  if (!a.startsWith("a/") || !b.startsWith("b/") || a.length < 3 || a.slice(2) !== b.slice(2)) return null;
  return a.slice(2);
}

/**
 * Parse `git diff -U0` output into one record per file.
 *
 * An added line that git ends with "\ No newline at end of file" is the file's last and no LF follows it: its hunk's
 * entry says so (eof), since a CR at its end is then a bare CR, no CR LF's. A removed line is numbered as the base
 * version numbers it (removedLines, removed), and marked eof the same way. A file is keyed by the path its header
 * names, read exactly (headerPath); a header that cannot be read gives no record (filesOfDiff then reads the file whole).
 *
 * @returns {Map<string, {path: string, status: "A"|"M"|"D", oldMode: string|null, newMode: string|null,
 *   binary: boolean, hunks: {removed: string[], removedLines: {line: number, text: string, eof?: true}[],
 *   added: {line: number, text: string, eof?: true}[]}[], added: Map<number, string>, removed: Map<number, string>}>}
 */
export function parseDiff(text) {
  const files = new Map();
  let file = null;
  let hunk = null;
  let nextLine = 0;
  let oldLine = 0;
  // The entry git's previous line printed, which a "\ No newline at end of file" after it marks, or null.
  let previous = null;
  // Git's output, a line per LINE_END; an added line's text keeps the CR of its CR LF, as the file holds it.
  for (const raw of text.split(LINE_END)) {
    const last = previous;
    previous = null;
    if (last && raw.startsWith("\\ ")) {
      last.eof = true;
      continue;
    }
    if (raw.startsWith("diff --git ")) {
      const path = headerPath(raw);
      file = { path, status: "M", oldMode: null, newMode: null, binary: false, hunks: [], added: new Map(), removed: new Map() };
      // A header that cannot be read keys nothing: its lines go to a record no one reads.
      if (path !== null) files.set(path, file);
      hunk = null;
      continue;
    }
    if (!file) continue;
    if (!hunk) {
      if (raw.startsWith("new file mode ")) file.status = "A";
      else if (raw.startsWith("deleted file mode ")) file.status = "D";
      else if (raw.startsWith("old mode ")) file.oldMode = raw.slice(9);
      else if (raw.startsWith("new mode ")) file.newMode = raw.slice(9);
      else if (raw.startsWith("Binary files ")) file.binary = true;
    }
    const header = /^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/.exec(raw);
    if (header) {
      hunk = { removed: [], removedLines: [], added: [] };
      file.hunks.push(hunk);
      oldLine = Number(header[1]);
      nextLine = Number(header[2]);
      continue;
    }
    if (!hunk) continue;
    if (raw.startsWith("+")) {
      const entry = { line: nextLine++, text: raw.slice(1) };
      hunk.added.push(entry);
      file.added.set(entry.line, entry.text);
      previous = entry;
    } else if (raw.startsWith("-")) {
      const entry = { line: oldLine++, text: raw.slice(1) };
      hunk.removed.push(entry.text);
      hunk.removedLines.push(entry);
      file.removed.set(entry.line, entry.text);
      previous = entry;
    }
  }
  return files;
}

/** A file's text as git's diff numbers its lines ({line, text}, a CR LF's CR kept), the last one marked eof where no LF follows it. */
function entriesOf(text) {
  if (text === null || text.includes("\0")) return [];
  const lines = text.split(LINE_END);
  if (lines[lines.length - 1] === "") lines.pop();
  const entries = lines.map((line, index) => ({ line: index + 1, text: line }));
  if (entries.length && !text.endsWith(LINE_END)) entries[entries.length - 1].eof = true;
  return entries;
}

/** Why a file of filesOfDiff is read whole. */
export const UNREADABLE = "git's diff header for it could not be read, so every line of it counts as changed";

/**
 * The change's files: `names`, the paths git lists for the change (NUL-separated, exact), each with its record from
 * the diff `text` (parseDiff), in git's order. A name the diff gives no record for (its header could not be read) is
 * read whole: every line of it as the change leaves it is added, and every line it had is removed, so a marker anywhere
 * in it counts (fail closed); its record says so (unreadable). A record under a path git does not list is dropped.
 *
 * @param {{head: (path: string) => string|null, base: (path: string) => string|null}} read  the file as the change
 *   leaves it, and as it was
 */
export function filesOfDiff(text, names, read) {
  const parsed = parseDiff(text);
  const files = new Map();
  for (const name of names) {
    if (parsed.has(name)) {
      files.set(name, parsed.get(name));
      continue;
    }
    const [headText, baseText] = [read.head(name), read.base(name)];
    const added = entriesOf(headText);
    const removed = entriesOf(baseText);
    files.set(name, {
      path: name,
      status: baseText === null ? "A" : headText === null ? "D" : "M",
      oldMode: null,
      newMode: null,
      binary: [headText, baseText].some((content) => content?.includes("\0")),
      hunks: [{ removed: removed.map((entry) => entry.text), removedLines: removed, added }],
      added: new Map(added.map((entry) => [entry.line, entry.text])),
      removed: new Map(removed.map((entry) => [entry.line, entry.text])),
      unreadable: UNREADABLE,
    });
  }
  return files;
}

/**
 * Build the change set the checks run against.
 *
 * @param {{base: string, head?: string}} options  base: the branch the change goes into;
 *   head: a commit to judge instead of the working tree.
 */
export function buildChangeset({ base, head }) {
  const headRev = head ?? "HEAD";
  const mergeBase = git(["merge-base", base, headRev]).trim();
  const range = head ? [mergeBase, head] : [mergeBase];
  const show = (rev, path) => {
    try {
      return git(["show", `${rev}:${path}`]);
    } catch {
      return null;
    }
  };
  const headCache = new Map();
  const baseCache = new Map();
  const readHead = (path) => {
    if (!headCache.has(path)) {
      headCache.set(path, head ? show(head, path) : existsSync(path) ? readFileSync(path, "utf8") : null);
    }
    return headCache.get(path);
  };
  const readBase = (path) => {
    if (!baseCache.has(path)) baseCache.set(path, show(mergeBase, path));
    return baseCache.get(path);
  };
  // The paths from git's NUL-separated list, exactly; the prefixes fixed, whatever diff.noprefix or diff.mnemonicPrefix say.
  const diff = git(["diff", "-U0", "--no-renames", "--no-color", "--no-ext-diff", "--src-prefix=a/", "--dst-prefix=b/", ...range]);
  const names = git(["diff", "--name-only", "-z", "--no-renames", ...range]).split("\0").filter(Boolean);
  const files = filesOfDiff(diff, names, { head: readHead, base: readBase });

  if (!head) {
    const untracked = git(["ls-files", "--others", "--exclude-standard", "-z"]).split("\0").filter(Boolean);
    for (const path of untracked) {
      const content = readFileSync(path, "utf8");
      const binary = content.includes("\0");
      const added = new Map();
      const lines = binary ? [] : content.split(LINE_END);
      if (lines.length && lines[lines.length - 1] === "") lines.pop();
      lines.forEach((text, index) => added.set(index + 1, text));
      const entries = [...added].map(([line, text]) => ({ line, text }));
      files.set(path, { path, status: "A", oldMode: null, newMode: null, binary, hunks: [{ removed: [], removedLines: [], added: entries }], added, removed: new Map() });
    }
  }

  // Where the branch started. Differs from mergeBase once base has been merged
  // into the branch: lines that merge brought in are not the author's, and the
  // order checks must not count them as "existing" entries placed after theirs.
  const own = git(["rev-list", "--first-parent", "--reverse", `${mergeBase}..${headRev}`]).split(LINE_END).filter(Boolean);
  const forkPoint = own.length ? git(["rev-parse", `${own[0]}^`]).trim() : mergeBase;
  const forkCache = new Map();

  return {
    base,
    head: head ?? null,
    /** The commit --head names, or null for the working tree. */
    headSha: head ? git(["rev-parse", `${head}^{commit}`]).trim() : null,
    mergeBase,
    files: [...files.values()],
    isVendored: (path) => VENDORED.test(path),
    /** The file as the change leaves it, or null when the change deletes it. */
    readHead,
    /**
     * The file's added lines as markerHits reads them: each run between the unchanged lines above and below it
     * (markerLinesOf), so a marker that reads a statement over lines sees where an added line goes on
     * with an unchanged one, and where an unchanged one goes on with what an added line opens. A path the change
     * does not touch has none.
     */
    markerLines(path) {
      return markerLinesOf(files.get(path), readHead(path));
    },
    /**
     * Whether the file as the change leaves it differs from the file at commit `rev` (as `git diff rev head -- path`
     * shows a content change; a file absent from both is the same), or null when `rev` names no commit here.
     */
    changedSince(rev, path) {
      if (typeof rev !== "string" || !/^[0-9a-f]{7,40}$/.test(rev) || !revExists(rev)) return null;
      const blob = (spec) => {
        try {
          return git(["rev-parse", "--verify", "--quiet", spec]).trim();
        } catch {
          return null;
        }
      };
      const then = blob(`${rev}:${path}`);
      if (head) return then !== blob(`${head}:${path}`);
      // The working tree: the blob git would store for the file, through the repository's filters.
      return then !== (existsSync(path) ? git(["hash-object", "--", path]).trim() : null);
    },
    /** The file as it was before the change, or null when the change adds it. */
    readBase,
    /** Line numbers (in the head version) that did not exist where the branch started. */
    addedSinceFork(path) {
      if (!forkCache.has(path)) {
        const args = ["diff", "-U0", "--no-renames", "--no-color", "--no-ext-diff", "--src-prefix=a/", "--dst-prefix=b/", forkPoint, ...(head ? [head] : [])];
        const diff = parseDiff(git([...args, "--", `:(literal)${path}`]));
        const file = diff.get(path);
        forkCache.set(path, file ? new Set(file.added.keys()) : new Set());
      }
      return forkCache.get(path);
    },
    /** `git grep` over the version the change leaves behind. */
    grepHead(args) {
      try {
        return git(["grep", ...args, ...(head ? [head] : []), "--"]).split(LINE_END).filter(Boolean);
      } catch {
        return [];
      }
    },
  };
}

/** The line of `entries` a "\ No newline at end of file" marks (no LF follows it), or 0. */
const eofLine = (entries) => entries.find((entry) => entry.eof)?.line ?? 0;

/**
 * A diff file record's added lines as markerHits reads them (addedWithContext over `headText`, the file as the change
 * leaves it, or null where it is not known; then the hunks' eof mark says which line no LF follows).
 */
export function markerLinesOf(file, headText) {
  if (!file?.added.size) return [];
  return addedWithContext(file.added, headText, Infinity, Infinity, eofLine(file.hunks.flatMap((hunk) => hunk.added)));
}

/**
 * A diff file record's removed lines as markerHits reads them: each run between the unchanged lines of the base
 * version around it (addedWithContext over `baseText`, or null where it is not known), numbered as the base numbers
 * them, so a removed line is read in the file it was removed from. A record without base line numbers (a caller's
 * own) gives each removed line alone, unnumbered, which no lexer establishes as a comment.
 */
export function removedLinesOf(file, baseText) {
  if (!file) return [];
  if (file.removed?.size) {
    return addedWithContext(file.removed, baseText, Infinity, Infinity, eofLine(file.hunks.flatMap((hunk) => hunk.removedLines ?? [])));
  }
  return file.hunks.flatMap((hunk) => (hunk.removedLines?.length ? [] : hunk.removed.map((text) => ({ line: null, text }))));
}

/**
 * A file's added lines as markerHits reads them ({line, text}, in line order), each run of them after up to
 * `before` unchanged lines of the head version above it ({line, text, context: true}), by default all of them
 * back to the run above or the file's first line, and before up to `after` unchanged lines below it, by default all
 * of them on to the run below or the file's last line. Every text is its line's without the CR of its CR LF
 * (doors.mjs lineText, which keeps it after a CR of the line's own, so the lines joined at LF are the file's text and
 * a reader's lineText leaves them as they are): a line of the diff ends at git's LF, and a CR, U+0085, U+2028 or U+2029
 * inside it stays there and puts the line outside the readers' plain subset (doors.mjs holdsUnusualTerminator). The
 * file's last line, where the file ends without an LF, has no CR LF: a CR at its end is a bare CR, and stays, the entry
 * marked eof so that no reader strips it either (doors.mjs ownLines). A marker never hits a context
 * line; a reader may read back over them to the start of a statement an added line goes on with, or to the
 * declaration or the call a field's line stands in (doors.mjs readsFrom, outerAt), and read on over them through the
 * end of the declaration or the statement an added line opens, a hit the added line's (a method's header renamed,
 * its body unchanged below). A reader handed fewer lines counts an added literal whose statement
 * starts above the first one it was handed (doors.mjs startsAbove). The last entry is marked cut where the file goes on
 * below it (fewer than all the lines below handed, `after`, or the file unknown: headText null), and a reader reads a
 * value still open there, or at a gap in the line numbers, as one that goes on unseen (doors.mjs cutAt); elsewhere it
 * reads the end of the lines it was handed as the end of what it reads, as it reads a file's end. Where the file is not
 * known (headText null), the line no LF follows is the one the diff's hunks marked (`eofLine`): it keeps a bare CR at
 * its end, and the file ends there, so it is not cut.
 *
 * @param {Map<number, string>} added  the file's added lines (changeset file.added)
 * @param {string|null} headText  the file as the change leaves it (changeset readHead)
 * @param {number} [before]  how many unchanged lines above each run, at most
 * @param {number} [after]  how many unchanged lines below each run, at most
 * @param {number} [eofLine]  where headText is null: the line the diff marked "\ No newline at end of file", or 0
 */
export function addedWithContext(added, headText, before = Infinity, after = Infinity, eofLine = 0) {
  const head = splitLines(headText);
  // The file's lines as git numbers them: an LF at the end of the text ends its last line and starts none.
  const count = headText !== null && headText.endsWith(LINE_END) ? head.length - 1 : head.length;
  // The line no LF follows: the file's last, where it ends without one, or 0; the diff's mark where the file is not known.
  const eof = headText === null ? eofLine : !headText.endsWith(LINE_END) ? head.length : 0;
  // That line as the file holds it: a CR at its end is a bare CR, which fileLines would read as a CR LF's.
  const raw = eof && headText !== null ? headText.slice(headText.lastIndexOf(LINE_END) + 1) : "";
  const unchanged = (n) => (n === eof && raw.endsWith("\r") ? { line: n, text: raw, context: true, eof: true } : { line: n, text: head[n - 1], context: true });
  const out = [];
  let last = 0;
  const lines = [...added].sort((a, b) => a[0] - b[0]);
  lines.forEach(([line, text], k) => {
    // Below the run above (it handed `after` of them), then above this one.
    const next = k + 1 < lines.length ? lines[k + 1][0] : Infinity;
    for (let n = Math.max(last + 1, line - before); n < line; n++) {
      if (n >= 1 && n <= count) out.push(unchanged(n));
    }
    out.push(line === eof && text.endsWith("\r") ? { line, text, eof: true } : { line, text: lineText(text) });
    last = line;
    // The lines below this one, up to `after` of them, short of the next added line.
    if (next === line + 1) return;
    const below = Math.min(next - 1, count, line + after);
    for (let n = line + 1; n <= below; n++) out.push(unchanged(n));
    last = Math.max(line, below);
  });
  // The file goes on below the last line handed: what an added line opens may go on there, unseen.
  const end = out[out.length - 1];
  if (end && (headText === null ? end.line !== eof : end.line < count)) out[out.length - 1] = { ...end, cut: true };
  return out;
}

/** Split file content into lines as git numbers them, 1-based friendly: lines[n - 1] is line n (doors.mjs fileLines). */
export function splitLines(content) {
  return content === null ? [] : fileLines(content);
}

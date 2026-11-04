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

import { execFileSync } from "node:child_process";
import { readFileSync, existsSync } from "node:fs";

/** Paths nobody here maintains: third-party code checked into the repo. */
const VENDORED = /^(vendor|includes\/vendor|node_modules|tiny_mce)\//;

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

/**
 * Parse `git diff -U0` output into one record per file.
 *
 * @returns {Map<string, {path: string, status: "A"|"M"|"D", oldMode: string|null, newMode: string|null,
 *   binary: boolean, hunks: {removed: string[], added: {line: number, text: string}[]}[],
 *   added: Map<number, string>}>}
 */
export function parseDiff(text) {
  const files = new Map();
  let file = null;
  let hunk = null;
  let nextLine = 0;
  for (const raw of text.split("\n")) {
    if (raw.startsWith("diff --git ")) {
      // "diff --git a/P b/P": both halves are the same path because renames are off.
      const both = raw.slice("diff --git a/".length);
      const path = both.slice(0, (both.length - " b/".length) / 2);
      file = { path, status: "M", oldMode: null, newMode: null, binary: false, hunks: [], added: new Map() };
      files.set(path, file);
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
    const header = /^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/.exec(raw);
    if (header) {
      hunk = { removed: [], added: [] };
      file.hunks.push(hunk);
      nextLine = Number(header[1]);
      continue;
    }
    if (!hunk) continue;
    if (raw.startsWith("+")) {
      const entry = { line: nextLine++, text: raw.slice(1) };
      hunk.added.push(entry);
      file.added.set(entry.line, entry.text);
    } else if (raw.startsWith("-")) {
      hunk.removed.push(raw.slice(1));
    }
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
  const files = parseDiff(git(["diff", "-U0", "--no-renames", "--no-color", "--no-ext-diff", ...range]));

  if (!head) {
    const untracked = git(["ls-files", "--others", "--exclude-standard", "-z"]).split("\0").filter(Boolean);
    for (const path of untracked) {
      const content = readFileSync(path, "utf8");
      const binary = content.includes("\0");
      const added = new Map();
      const lines = binary ? [] : content.split("\n");
      if (lines.length && lines[lines.length - 1] === "") lines.pop();
      lines.forEach((text, index) => added.set(index + 1, text));
      const entries = [...added].map(([line, text]) => ({ line, text }));
      files.set(path, { path, status: "A", oldMode: null, newMode: null, binary, hunks: [{ removed: [], added: entries }], added });
    }
  }

  // Where the branch started. Differs from mergeBase once base has been merged
  // into the branch: lines that merge brought in are not the author's, and the
  // order checks must not count them as "existing" entries placed after theirs.
  const own = git(["rev-list", "--first-parent", "--reverse", `${mergeBase}..${headRev}`]).split("\n").filter(Boolean);
  const forkPoint = own.length ? git(["rev-parse", `${own[0]}^`]).trim() : mergeBase;
  const forkCache = new Map();

  const headCache = new Map();
  const baseCache = new Map();
  const show = (rev, path) => {
    try {
      return git(["show", `${rev}:${path}`]);
    } catch {
      return null;
    }
  };

  return {
    base,
    head: head ?? null,
    mergeBase,
    files: [...files.values()],
    isVendored: (path) => VENDORED.test(path),
    /** The file as the change leaves it, or null when the change deletes it. */
    readHead(path) {
      if (!headCache.has(path)) {
        headCache.set(path, head ? show(head, path) : existsSync(path) ? readFileSync(path, "utf8") : null);
      }
      return headCache.get(path);
    },
    /** The file as it was before the change, or null when the change adds it. */
    readBase(path) {
      if (!baseCache.has(path)) baseCache.set(path, show(mergeBase, path));
      return baseCache.get(path);
    },
    /** Line numbers (in the head version) that did not exist where the branch started. */
    addedSinceFork(path) {
      if (!forkCache.has(path)) {
        const diff = parseDiff(git(["diff", "-U0", "--no-renames", "--no-color", "--no-ext-diff", forkPoint, ...(head ? [head] : []), "--", path]));
        const file = diff.get(path);
        forkCache.set(path, file ? new Set(file.added.keys()) : new Set());
      }
      return forkCache.get(path);
    },
    /** `git grep` over the version the change leaves behind. */
    grepHead(args) {
      try {
        return git(["grep", ...args, ...(head ? [head] : []), "--"]).split("\n").filter(Boolean);
      } catch {
        return [];
      }
    },
  };
}

/** Split file content into lines, 1-based friendly: lines[n - 1] is line n. */
export function splitLines(content) {
  return content === null ? [] : content.split("\n").map((line) => line.replace(/\r$/, ""));
}

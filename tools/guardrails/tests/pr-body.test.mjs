// tools/guardrails/tests/pr-body.test.mjs - the pr-body door check: the Merge Danger fields, and a two-way door held
// against lib/doors.mjs.
//
// The check fails on #884 cce4a13a and #768 16c53aea with "Door: two-way", on #884's own description with no Merge
// Danger, and on #980 527579cc until a reviewer's exception names the file; an exception counts only when the edit
// history shows a reviewer added it. The description job of guardrails.yml is run as CI runs it. The door cross-check
// reads a change's lines as `check.mjs --station` does (each file's markerLines: the unchanged lines above each run,
// and the eof mark of a last line no LF follows), so a bare CR at a file's end and a comment line below unchanged code
// read the same both ways; a removed line is read in the base version of its file.
//
// The description job runs the base revision's checker, door map and reviewers against the PR's head, so a PR that
// loosens them is judged by the rules it goes into; before the base has a door map, the head's checker runs and the
// log says so. An exception counts only when a reviewer put it into the active Merge Danger section, for the rows it
// names, and while the file is as it was at the commit it names.
//
// History:
// 20261103 CL/LH Created.
// 20261109 CL/LH The description job runs the base revision's policy, or the head's while the base has no door map.
// 20261109 CL/LH Exceptions bound to their reviewer, a commit and the rows they name; removed lines read in the base
//                file; the hunk's eof mark where the head file is not known.
// 20261109 CL/LH Paths git quotes in a diff header are read exactly; a header that cannot be read reads the file whole.
// 20261109 CL/LH A change's .gitattributes (binary, -diff, a text conversion driver) hides none of its lines.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { cpSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import prBody, { lineOwners, readHistory } from "../checks/pr-body.mjs";
import { NO_LINES, UNREADABLE, filesOfDiff, headerPath, markerLinesOf, parseDiff } from "../lib/changeset.mjs";
import { stationReport } from "../lib/station.mjs";
import { REVIEWERS } from "../lib/doors.mjs";

const ROOT = fileURLToPath(new URL("../../../", import.meta.url));
const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
const [NICOLAI, SAUL] = REVIEWERS;
const AUTHOR = "a-contributor";

const SUMMARY = "## Summary\nThe settlement date is set when a payment settles the invoices it names.\n";
const VERIFIED = "## How I verified this\nLocal tenant, kassekladde: posted a payment and saw the date on both open posts; the suite ran green.\n";

function body({ door = "two-way", after = "none", extra = "" } = {}) {
  return `## Merge Danger\n**Door:** ${door}\n**Blast Radius:** one feature\n**Undo:** revert\n**After merge:** ${after}\n${extra}\n${SUMMARY}\n${VERIFIED}`;
}

/**
 * The answer guardrails.yml saves from GitHub for a description's edit history.
 * @param {[string|null, string|null][]} revisions  [editor login, body] oldest first; a null body is a hidden revision
 */
function history(revisions, { author = AUTHOR, totalCount } = {}) {
  const nodes = revisions
    .map(([editor, diff]) => ({ editor: editor === null ? null : { login: editor }, deletedAt: diff === null ? "2026-11-02T09:00:00Z" : null, diff }))
    .reverse();
  return { data: { repository: { pullRequest: { author: { login: author }, userContentEdits: { totalCount: totalCount ?? nodes.length, nodes } } } } };
}

function files(stdout) {
  return stdout
    .split("\n")
    .filter((line) => line.includes("[pr-body]") && !line.startsWith("::"))
    .map((line) => line.slice(0, line.indexOf(": [pr-body]")));
}

/** The door check on a real commit, judged against its parent; `edits` is the description's edit history. */
function judge(sha, text, { edits } = {}) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-pr-"));
  try {
    const args = [CHECK, "--pr-body", "-", "--base", `${sha}^`, "--head", sha];
    if (edits !== undefined) {
      writeFileSync(join(dir, "pr-edits.json"), JSON.stringify(edits));
      args.push("--pr-edits", join(dir, "pr-edits.json"));
    }
    const result = spawnSync(process.execPath, args, { cwd: ROOT, input: text, encoding: "utf8", env: { ...process.env, GUARDRAILS_TODAY: "2026-11-03", GITHUB_ACTIONS: "" } });
    return { status: result.status, stdout: result.stdout, files: files(result.stdout) };
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

/**
 * The pr-body check on one synthetic file, for rules that need no real commit. `changedSince` answers whether the file
 * changed after the commit an exception names (lib/changeset.mjs): by default it did not.
 */
function judgeFile(text, path, added, { edits, changedSince = () => false } = {}) {
  const file = { path, status: "M", added: new Map(added.map((line, i) => [i + 1, line])), hunks: [{ removed: [], added: [] }] };
  return prBody.run(text, { files: [file], isVendored: () => false, today: "2026-11-03", edits, changedSince });
}

test("#884 cce4a13a, its own description: no Merge Danger section fails", () => {
  const old = "## What are the changes about?\nExchange-rate differences are settled when the payment posts.\n\n" + VERIFIED;
  const { status, stdout } = judge("cce4a13a", old);
  assert.equal(status, 1);
  assert.match(stdout, /add a "## Merge Danger" section/);
});

test('#884 cce4a13a with "Door: two-way" fails on finans/bogfor.php (posting; reverted by #931)', () => {
  const { status, files, stdout } = judge("cce4a13a", body());
  assert.equal(status, 1);
  assert.deepEqual(files, ["finans/bogfor.php"]);
  assert.match(stdout, /posting by its path finans\/bogfor\.php/);
});

test('#768 16c53aea with "Door: two-way" fails on its four payments files', () => {
  const { status, files } = judge("16c53aea", body());
  assert.equal(status, 1);
  assert.deepEqual(files, [
    "debitor/payments/vibrant.php",
    "debitor/payments/vibrant_proxy.php",
    "systemdata/diverseIncludes/create_vibrant_login.php",
    "systemdata/diverseIncludes/show_vibrant_login.php",
  ]);
});

test("#832 8e09393b passes as one-way and fails as two-way on all four one-way files, the test file not among them", () => {
  assert.equal(judge("8e09393b", body({ door: "one-way", after: "none (the backfill runs at each tenant's next login)" })).status, 0);
  const { status, files } = judge("8e09393b", body());
  assert.equal(status, 1);
  assert.deepEqual(files, ["finans/bogfor.php", "includes/betweenUpdates.php", "includes/genberegn.php", "includes/orderFuncIncludes/genberegn.php"]);
});

test("#886 bad3e197 (CSS) passes as two-way", () => {
  assert.equal(judge("bad3e197", body()).status, 0);
});

test("care rows never fail: #917 ff80f1b5 (order page) and #966 68427370 (order pages and texts) pass as two-way", () => {
  assert.equal(judge("ff80f1b5", body()).status, 0);
  assert.equal(judge("68427370", body()).status, 0);
});

test("a one-way door passes whatever the change touches: #886 bad3e197", () => {
  assert.equal(judge("bad3e197", body({ door: "one-way" })).status, 0);
});

/** An exception line; `at` is the commit it was approved at (#980's own, for the tests on 527579cc). */
const allowLine = (reason, { until = "2026-12-31", by = NICOLAI.initials, at = "527579cc" } = {}) => `guardrails-allow pr-body until ${until} by ${by} at ${at}: ${reason}`;
/** A description opened by its author, then edited by `editor` to add `line`. */
const added = (line, editor = NICOLAI.login, options = {}) => ({ text: body({ extra: line }), edits: history([[AUTHOR, body()], [editor, body({ extra: line })]], options) });

test("#980 527579cc fails as two-way until a reviewer's edit adds an exception line naming the file or the row", () => {
  assert.deepEqual(judge("527579cc", body()).files, ["debitor/betalinger.php"]);
  for (const reason of ["debitor/betalinger.php removes a broken menu option; nothing new is written", "payments: only a broken option is removed"]) {
    const { text, edits } = added(allowLine(reason));
    assert.equal(judge("527579cc", text, { edits }).status, 0, reason);
  }
  const unrelated = added(allowLine("debitor/ordre.php is unrelated"));
  assert.deepEqual(judge("527579cc", unrelated.text, { edits: unrelated.edits }).files, ["debitor/betalinger.php"]);
  const late = added(allowLine("debitor/betalinger.php removes a broken option", { until: "2026-11-01" }));
  const expired = judge("527579cc", late.text, { edits: late.edits });
  assert.match(expired.stdout, /expired on 2026-11-01/);
  assert.deepEqual(expired.files, [".github/pull_request_template.md", "debitor/betalinger.php"]);
  const comment = body({ extra: `<!-- ${allowLine("debitor/betalinger.php")} -->` });
  const hidden = judge("527579cc", comment, { edits: history([[AUTHOR, body()], [NICOLAI.login, comment]]) });
  assert.deepEqual(hidden.files, ["debitor/betalinger.php"], "an exception inside a comment does not count");
  assert.match(judge("527579cc", body({ extra: "guardrails-allow pr-body until soon: debitor/betalinger.php" })).stdout, /malformed/);
});

test("an exception line the author adds does not count, even signed with a reviewer's initials after approval", () => {
  const line = allowLine("debitor/betalinger.php removes a broken menu option");
  const own = added(line, AUTHOR);
  const result = judge("527579cc", own.text, { edits: own.edits });
  assert.equal(result.status, 1);
  assert.deepEqual(result.files, ["debitor/betalinger.php"]);
  assert.match(result.stdout, new RegExp(`does not count: ${AUTHOR} added it, and only Nicolai \\(NTR\\) or Saul \\(SZ\\) can`));
  assert.match(judge("527579cc", body({ extra: line })).stdout, /does not count: who added it cannot be checked without the description's edit history/);
});

test("who added an exception line is read from the edit history, line by line", () => {
  const line = allowLine("debitor/betalinger.php removes a broken menu option");
  const withLine = body({ extra: line });
  const refused = (text, edits) => {
    const findings = judgeFile(text, "debitor/betalinger.php", ["x"], { edits });
    assert.equal(findings.length, 1);
    return findings[0].message.split("does not count: ")[1];
  };
  const passes = (text, edits) => assert.deepEqual(judgeFile(text, "debitor/betalinger.php", ["x"], { edits }), []);

  passes(withLine, history([[AUTHOR, body()], [NICOLAI.login, withLine]]));
  const saulLine = body({ extra: allowLine("payments: a menu option only", { by: SAUL.initials }) });
  passes(saulLine, history([[AUTHOR, body()], [SAUL.login, saulLine]]));
  // A bot or the author editing other lines later leaves the reviewer's line the reviewer's.
  const appended = `${withLine}\n## Summary by CodeRabbit\n- Bug fix: the payment list hides a broken format.\n`;
  passes(appended, history([[AUTHOR, body()], [NICOLAI.login, withLine], ["coderabbitai", appended]]));

  assert.match(refused(withLine, history([], { totalCount: 0 })), new RegExp(`^${AUTHOR} added it`), "opened with the line");
  assert.match(refused(withLine, history([[AUTHOR, body()], [SAUL.login, withLine]])), /^it is signed NTR, but Saul added it and signs SZ/);
  assert.match(refused(withLine, history([[NICOLAI.login, body()], [NICOLAI.login, withLine]], { author: NICOLAI.login })), /added it to their own PR/);
  const changed = body({ extra: allowLine("debitor/betalinger.php removes a broken menu option", { until: "2027-06-30" }) });
  assert.match(refused(changed, history([[AUTHOR, body()], [NICOLAI.login, withLine], [AUTHOR, changed]])), new RegExp(`^${AUTHOR} added it`), "the author changed the reviewer's line");
  assert.match(refused(withLine, history([[AUTHOR, body()], [NICOLAI.login, null], [AUTHOR, withLine]])), /edit history does not show who added it/, "a hidden revision");
  assert.match(refused(withLine, history([[NICOLAI.login, withLine]], { totalCount: 140 })), /edit history does not show who added it/, "more edits than were read");
  assert.match(refused(withLine, history([[null, body()], [null, withLine]])), /edit history does not show who added it/, "a deleted account");
  assert.match(refused(withLine, { errors: [{ message: "Resource not accessible by integration" }] }), /not a pull request's edit history/);
  assert.match(refused(withLine, { error: "--pr-edits x cannot be read (ENOENT)" }), /cannot be read \(ENOENT\)/);
});

test("readHistory and lineOwners follow GitHub's order: newest edit first, the oldest node the opened text", () => {
  const danger = (text) => `## Merge Danger\n${text}`;
  const edits = history([[AUTHOR, danger("a\nb")], [NICOLAI.login, danger("a\nb\r\nc")], [AUTHOR, danger("  a\nc  \nd")]]);
  const read = readHistory(edits);
  assert.deepEqual(read.revisions.map((revision) => revision.editor), [AUTHOR, NICOLAI.login, AUTHOR]);
  assert.equal(read.complete, true);
  assert.deepEqual([...lineOwners(read, "")], [["a", AUTHOR], ["c", NICOLAI.login], ["d", AUTHOR]]);
});

test("without a base the text rules still run and the cross-check says it was skipped", () => {
  const result = spawnSync(process.execPath, [CHECK, "--pr-body", "-", "--base", "origin/no-such-branch"], {
    cwd: ROOT,
    input: body(),
    encoding: "utf8",
    env: { ...process.env, GITHUB_ACTIONS: "" },
  });
  assert.equal(result.status, 0);
  assert.match(result.stdout, /door cross-check skipped: no base origin\/no-such-branch/);
});

test("the Door and After merge fields: present, known, and said in enough words", () => {
  const messages = (text) => prBody.run(text).map((finding) => finding.message);
  assert.deepEqual(messages(body()), []);
  assert.deepEqual(messages(body({ door: "One-Way" })), [], "case is tolerated");
  assert.deepEqual(messages(`## Merge danger\n- Door: \`two-way\`\n- After merge: none\n${SUMMARY}\n${VERIFIED}`), [], "bullets and code spans are tolerated");
  assert.deepEqual(messages(`## Merge Danger\n**Door**: two-way\n**After merge:**\n- Nicolai re-runs the backfill script on live\n\n${SUMMARY}\n${VERIFIED}`), [], "steps may follow on the next lines");
  assert.match(messages(body({ door: "maybe" }))[0], /"Door: maybe" is not a door; write "one-way" or "two-way"/);
  assert.match(messages(body({ door: "" }))[0], /has no "Door:"/);
  assert.match(messages(body({ after: "" }))[0], /has no "After merge:"/);
  assert.match(messages(body({ after: "tbd" }))[0], /"After merge: tbd" says too little/);
  assert.deepEqual(messages(body({ after: "Saul checks the print once deployed" })), []);
  assert.match(messages(`## Merge Danger\n<!-- **Door:** one-way -->\n**After merge:** none\n${SUMMARY}\n${VERIFIED}`)[0], /has no "Door:"/);
  for (const door of ["two-way (layout only)", "one-way: posting", "two-way, with care", "one-way. Rows stay", "two-way - a label"]) {
    assert.deepEqual(messages(body({ door })), [], door);
  }
  for (const door of ["one-wayish", "one-way-ish", "two-wayward", "oneway", "two way", "one-way / two-way", "two-way|one-way"]) {
    assert.match(messages(body({ door }))[0] ?? "", /is not a door/, door);
  }
});

test('"Door: one-wayish" on #884 cce4a13a is no door, so it neither passes as one-way nor skips the cross-check', () => {
  const { status, stdout } = judge("cce4a13a", body({ door: "one-wayish" }));
  assert.equal(status, 1);
  assert.match(stdout, /"Door: one-wayish" is not a door/);
});

test("TRUNCATE in any case, quoted or joined from pieces, is held against a two-way door; a JS truncate() or a class name is not", () => {
  const quoted = ["truncate", "Truncate", "tRuNcAtE"].flatMap((keyword) => [
    `db_modify('${keyword}' . ' ' . $table);`,
    `db_modify("${keyword}" . " " . $table);`,
    `$sql = '${keyword}';`,
    `$sql = "${keyword} table" . $table;`,
    `$sql = $prefix . '${keyword}';`,
  ]);
  for (const text of ['db_modify("truncate ordrer");', "$q = 'Truncate Table varer';", '$q = "TRUNCATE kassekladde";', "truncate only openpost", ...quoted]) {
    const findings = judgeFile(body(), "finans/cleanup.php", [text]);
    assert.equal(findings.length, 1, text);
    assert.equal(findings[0].path, "finans/cleanup.php");
    assert.match(findings[0].message, /destructive by the marker "TRUNCATE"/, text);
    assert.deepEqual(judgeFile(body({ door: "one-way" }), "finans/cleanup.php", [text]), [], text);
  }
  for (const text of ["const short = truncate(text, 20);", "el.classList.add('truncate');", '<div class="truncate">', "if ($mode == 'truncate') {"]) {
    assert.deepEqual(judgeFile(body(), "javascript/cleanup.js", [text]), [], text);
  }
});

/** The description job in guardrails.yml: its text, and the script of the step that runs --pr-body. */
function descriptionJob() {
  const lines = readFileSync(join(ROOT, ".github/workflows/guardrails.yml"), "utf8").split("\n");
  const start = lines.findIndex((line) => /^ {2}description:\s*$/.test(line));
  assert.ok(start >= 0, "guardrails.yml has a description job");
  const end = lines.findIndex((line, i) => i > start && /^ {2}\S/.test(line));
  const job = lines.slice(start, end < 0 ? lines.length : end);
  const scripts = [];
  for (let i = 0; i < job.length; i++) {
    const match = /^(\s*)(?:- )?run:\s*(.*)$/.exec(job[i]);
    if (!match) continue;
    if (!/^[|>][-+]?$/.test(match[2])) {
      scripts.push(match[2]);
      continue;
    }
    const block = [];
    for (let j = i + 1; j < job.length && (job[j].trim() === "" || job[j].search(/\S/) > match[1].length); j++) block.push(job[j]);
    const indent = Math.min(...block.filter((line) => line.trim()).map((line) => line.search(/\S/)));
    scripts.push(block.map((line) => line.slice(indent)).join("\n"));
  }
  return { text: job.join("\n"), check: scripts.find((script) => script.includes("--pr-body")) };
}

/**
 * The --pr-body step of the description job, run with bash as the runner runs it, on a real commit: by default this
 * repository's `sha` against its parent; `cwd` and `base` for a scratch repository's.
 */
function runDescriptionStep(sha, text, { headRef = "lh/fix/example", edits, cwd = ROOT, base = `${sha}^` } = {}) {
  const runnerTemp = mkdtempSync(join(tmpdir(), "guardrails-runner-"));
  try {
    if (edits !== undefined) writeFileSync(join(runnerTemp, "pr-edits.json"), JSON.stringify(edits));
    const env = { PATH: process.env.PATH, HOME: process.env.HOME, GITHUB_ACTIONS: "true", GUARDRAILS_TODAY: "2026-11-03", RUNNER_TEMP: runnerTemp };
    Object.assign(env, { PR_BODY: text, HEAD_REF: headRef, BASE_SHA: base, HEAD_SHA: sha });
    const result = spawnSync("bash", ["-e", "-c", descriptionJob().check], { cwd, encoding: "utf8", env, timeout: 600000 });
    return { status: result.status, stdout: result.stdout, files: files(result.stdout) };
  } finally {
    rmSync(runnerTemp, { recursive: true, force: true });
  }
}

test("the description job checks out the PR's history and passes its base, head and edit history; no checkout keeps credentials", () => {
  const { text, check } = descriptionJob();
  const workflow = readFileSync(join(ROOT, ".github/workflows/guardrails.yml"), "utf8");
  const checkouts = workflow.match(/uses: actions\/checkout@/g).length;
  assert.equal(workflow.match(/^ +persist-credentials: false$/gm)?.length, checkouts, "every checkout sets persist-credentials: false");
  assert.match(text, /fetch-depth: 0/);
  assert.match(text, /ref: \$\{\{ github\.event\.pull_request\.head\.sha \}\}/);
  assert.match(text, /pull-requests: read/);
  assert.match(text, /BASE_SHA: \$\{\{ github\.event\.pull_request\.base\.sha \}\}/);
  assert.match(text, /HEAD_SHA: \$\{\{ github\.event\.pull_request\.head\.sha \}\}/);
  assert.match(text, /userContentEdits\(first: 100\) \{ totalCount nodes \{ editor \{ login \} deletedAt diff \} \}/);
  assert.match(text, /> "\$RUNNER_TEMP\/pr-edits\.json"/);
  assert.match(check, /--pr-edits "\$RUNNER_TEMP\/pr-edits\.json"/);
});

test('the description job, run as CI runs it, fails #884 cce4a13a and #768 16c53aea with "Door: two-way"', () => {
  const posting = runDescriptionStep("cce4a13a", body());
  assert.equal(posting.status, 1);
  assert.deepEqual(posting.files, ["finans/bogfor.php"]);
  const payments = runDescriptionStep("16c53aea", body());
  assert.equal(payments.status, 1);
  assert.deepEqual(payments.files, [
    "debitor/payments/vibrant.php",
    "debitor/payments/vibrant_proxy.php",
    "systemdata/diverseIncludes/create_vibrant_login.php",
    "systemdata/diverseIncludes/show_vibrant_login.php",
  ]);
  assert.equal(runDescriptionStep("cce4a13a", body({ door: "one-way" })).status, 0);
  const sync = runDescriptionStep("cce4a13a", body(), { headRef: "sync/master-into-hml" });
  assert.equal(sync.status, 0);
  assert.match(sync.stdout, /door cross-check skipped: --text-only/);
  const reviewed = added(allowLine("debitor/betalinger.php removes a broken menu option"));
  assert.equal(runDescriptionStep("527579cc", reviewed.text, { edits: reviewed.edits }).status, 0);
  const own = added(allowLine("debitor/betalinger.php removes a broken menu option"), AUTHOR);
  assert.deepEqual(runDescriptionStep("527579cc", own.text, { edits: own.edits }).files, ["debitor/betalinger.php"]);
});

test("in CI a cross-check that cannot run is a finding, not a pass", () => {
  const result = spawnSync(process.execPath, [CHECK, "--pr-body", "-", "--base", "origin/no-such-branch"], {
    cwd: ROOT,
    input: body(),
    encoding: "utf8",
    env: { ...process.env, GITHUB_ACTIONS: "true" },
  });
  assert.equal(result.status, 1);
  assert.match(result.stdout, /^\.github\/workflows\/guardrails\.yml: \[pr-body\] the door cross-check did not run \(no base origin\/no-such-branch\)/m);
});

test("the existing rules are unchanged: an empty template still fails on what and how", () => {
  const findings = prBody.run("## What are the changes about?\nProvide a brief explanation of the changes you have made\n## How I verified this\n");
  assert.equal(findings.length, 3);
  assert.match(findings[0].message, /What are the changes about/);
  assert.match(findings[1].message, /How I verified this/);
});

// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const DROP = 'db.query("DROP DATABASE scratch")';
const dangerBody = (door) => `## Merge Danger\n**Door:** ${door}\n**Blast Radius:** one feature\n**Undo:** revert\n**After merge:** none\n\n${SUMMARY}\n${VERIFIED}`;

/**
 * A scratch git repository: `files` ([path, base content or null, head content]) committed as base, then the head
 * contents committed as head (commit: true) or left in the working tree. Returns run(args, input) for check.mjs there,
 * and the base and head revisions.
 */
function scratch(files, { commit }) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-pr-body-lines-"));
  const git = (...args) => spawnSync("git", ["-c", "user.name=guardrails-test", "-c", "user.email=guardrails-test@example.invalid", "-c", "commit.gpgsign=false", "-c", "core.autocrlf=false", ...args], { cwd: dir, encoding: "utf8" });
  const write = (path, content) => {
    mkdirSync(dirname(join(dir, path)), { recursive: true });
    writeFileSync(join(dir, path), content);
  };
  assert.equal(git("init", "-q").status, 0);
  for (const [path, base] of files) if (base !== null) write(path, base);
  assert.equal(git("add", "-A").status, 0);
  assert.equal(git("commit", "-q", "--allow-empty", "--no-verify", "-m", "base").status, 0);
  const base = git("rev-parse", "HEAD").stdout.trim();
  for (const [path, , head] of files) write(path, head);
  let head = null;
  if (commit) {
    assert.equal(git("add", "-A").status, 0);
    assert.equal(git("commit", "-q", "--no-verify", "-m", "head").status, 0);
    head = git("rev-parse", "HEAD").stdout.trim();
  }
  const run = (args, input) =>
    spawnSync(process.execPath, [CHECK, ...args], { cwd: dir, input, encoding: "utf8", timeout: 600000, env: { ...process.env, GUARDRAILS_TODAY: "2026-11-06", GITHUB_ACTIONS: "" } });
  return { dir, base, head, run, done: () => rmSync(dir, { recursive: true, force: true }) };
}

/** The files the cross-check says a one-way row claims ("path: [pr-body] ..." lines). */
const claimed = (stdout) =>
  stdout
    .split("\n")
    .filter((line) => line.includes("[pr-body]") && line.includes("the door map makes this file one-way"))
    .map((line) => line.slice(0, line.indexOf(": [pr-body]")));

/** --station's and the cross-check's reading of one change: the station's secrets lines and other rows, and the files the cross-check claims. */
function bothWays(files, { commit }) {
  const repo = scratch(files, { commit });
  try {
    const range = commit ? ["--base", repo.base, "--head", repo.head] : ["--base", repo.base];
    const station = repo.run(["--station", "--json", ...range]);
    assert.equal(station.status, 0, station.stderr);
    const report = JSON.parse(station.stdout);
    const pr = repo.run(["--pr-body", "-", ...range], dangerBody("two-way"));
    const oneWay = repo.run(["--pr-body", "-", ...range], dangerBody("one-way"));
    assert.equal(oneWay.status, 0, oneWay.stdout);
    return {
      station: report.markersHit.map((hit) => `${hit.path}:${hit.row}`).sort(),
      claimed: claimed(pr.stdout).sort(),
      status: pr.status,
    };
  } finally {
    repo.done();
  }
}

/**
 * A last line, no LF after it, that ends in a bare CR. A password reference there counts on --station, but the secrets
 * row stays out of the cross-check (doors.mjs crossCheck: false: a secret is human-only on --station whatever the door
 * says), so the cross-check's reading shows through a row it holds a two-way door against: a comment line that holds
 * DROP DATABASE, which a bare CR keeps out of the plain subset, so no reader establishes it as a comment.
 */
const BARE_CR_FILES = [
  ["finans/report.js", null, `// ${DROP}\r`],
  ["finans/report.php", null, `<?php\n// ${DROP}\r`],
];

test("a last line, no LF after it, that ends in a bare CR reads in the PR's door cross-check as on --station: its comment is not established, so DROP DATABASE claims the file under a two-way door, in the working tree and between commits; --station counts a password reference there too", () => {
  // Lines rebuilt from file.added would lose the eof mark: the bare CR would read as a CR LF's and the comment would be
  // passed over, while --station counts it.
  for (const commit of [false, true]) {
    for (const file of BARE_CR_FILES) {
      const got = bothWays([file], { commit });
      const where = `${file[0]}, ${commit ? "committed" : "working tree"}`;
      assert.deepEqual(got.station, [`${file[0]}:destructive`], `${where}: --station`);
      assert.deepEqual(got.claimed, [file[0]], `${where}: the cross-check`);
      assert.equal(got.status, 1, where);
    }
  }
  for (const [path, base, head] of [
    ["api/client.js", "run();\n", `run();\nconst ${NAME} = process.env.X;\r`],
    ["includes/db.php", "<?php\n", `<?php\n$${NAME} = getenv('X');\r`],
  ]) {
    assert.deepEqual(bothWays([[path, base, head]], { commit: true }), { station: [`${path}:secrets`], claimed: [], status: 0 }, `${path}: a password reference`);
  }
});

test("controls: the same last line ended by CR LF, by LF or by nothing is an established comment, so neither --station nor the cross-check claims it", () => {
  for (const end of ["\r\n", "\n", ""]) {
    for (const [path, base, head] of BARE_CR_FILES) {
      const got = bothWays([[path, base, head.replace(/\r$/, end)]], { commit: true });
      assert.deepEqual(got, { station: [], claimed: [], status: 0 }, `${path}: ${JSON.stringify(end)}`);
    }
  }
});

test("an added comment line below unchanged code is a comment for the cross-check as for --station, so its DROP DATABASE claims no file; one inside a string over lines that an unchanged line opens is the string's text and claims it in both", () => {
  // Read alone, the added line is no established comment, and the cross-check would claim the file as destructive
  // while --station does not.
  for (const [path, base, head] of [
    ["finans/report.php", "<?php\n$x = 1;\n", `<?php\n$x = 1;\n// ${DROP}\n`],
    ["finans/report.js", "run();\n", `run();\n// ${DROP}\n`],
  ]) {
    assert.deepEqual(bothWays([[path, base, head]], { commit: true }), { station: [], claimed: [], status: 0 }, path);
  }
  const text = bothWays([["finans/report.php", `<?php\n$sql = "select 1\n";\n`, `<?php\n$sql = "select 1\n// ${DROP}\n";\n`]], { commit: true });
  assert.deepEqual(text, { station: ["finans/report.php:destructive"], claimed: ["finans/report.php"], status: 1 });
});

test("the cross-check without markerLines (a caller's own change): each added line keeps the eof mark its hunk's entry holds, so a bare CR at the file's end still keeps its comment's DROP DATABASE", () => {
  // Lines rebuilt from file.added alone would lose the eof mark.
  const line = `// ${DROP}\r`;
  const file = (entry) => ({ path: "finans/report.js", status: "A", added: new Map([[1, line]]), hunks: [{ removed: [], added: [entry] }] });
  const findings = prBody.run(dangerBody("two-way"), { files: [file({ line: 1, text: line, eof: true })], isVendored: () => false, today: "2026-11-06" });
  assert.equal(findings.length, 1);
  assert.equal(findings[0].path, "finans/report.js");
  assert.match(findings[0].message, /destructive by the marker "DROP DATABASE"/);
  // Without the mark the CR is a CR LF's, and the line is an established comment.
  assert.deepEqual(prBody.run(dangerBody("two-way"), { files: [file({ line: 1, text: line })], isVendored: () => false, today: "2026-11-06" }), []);
});

test("an exception counts only for the reviewer who put it into the active Merge Danger section: a reviewer's line elsewhere in the description that the author moves into the section is the author's", () => {
  const line = allowLine("debitor/betalinger.php removes a broken menu option");
  const inDanger = body({ extra: line });
  const refused = (edits) => {
    const findings = judgeFile(inDanger, "debitor/betalinger.php", ["x"], { edits });
    assert.equal(findings.length, 1);
    return findings[0].message.split("does not count: ")[1];
  };
  // The reviewer wrote the line as an example under another heading; the author moved it, unchanged, into Merge Danger.
  const example = `${body()}\n## Notes\n${line}\n`;
  assert.match(refused(history([[AUTHOR, body()], [NICOLAI.login, example], [AUTHOR, inDanger]])), new RegExp(`^${AUTHOR} added it`));
  // The reviewer left it in an HTML comment in the section; the author took the comment marks away.
  const commented = body({ extra: `<!-- ${line} -->` });
  assert.match(refused(history([[AUTHOR, body()], [NICOLAI.login, commented], [AUTHOR, inDanger]])), new RegExp(`^${AUTHOR} added it`));
  // Control: the reviewer put it there, and the author's later edits elsewhere leave it the reviewer's.
  const later = `${inDanger}\n## Notes\nRe-ran the suite.\n`;
  assert.deepEqual(judgeFile(later, "debitor/betalinger.php", ["x"], { edits: history([[AUTHOR, body()], [NICOLAI.login, inDanger], [AUTHOR, later]]) }), []);
});

test("an exception that names a row lets only that row through: a file that is posting by its path and destructive by a marker keeps its destructive finding; one that names the file lets both through; a name inside a longer path names nothing", () => {
  const code = ['db_modify("truncate ordrer");'];
  const judged = (reason, path = "finans/bogfor.php") => {
    const line = allowLine(reason);
    return judgeFile(body({ extra: line }), path, code, { edits: history([[AUTHOR, body()], [NICOLAI.login, body({ extra: line })]]) });
  };
  assert.equal(judged("posting: the totals are not touched").length, 1);
  const [onlyPosting] = judged("posting: the totals are not touched");
  assert.equal(onlyPosting.path, "finans/bogfor.php");
  assert.match(onlyPosting.message, /destructive by the marker "TRUNCATE"/);
  assert.doesNotMatch(onlyPosting.message, /posting by its path/);
  assert.deepEqual(judged("finans/bogfor.php only renames a variable"), []);
  assert.deepEqual(judged("posting and destructive: a dry run only"), []);
  // bogfor.php alone is not finans/bogfor.php, and debitor/payments/ is a path, not the payments row.
  assert.match(judged("bogfor.php only renames a variable")[0]?.message ?? "", /posting by its path finans\/bogfor\.php \(.*\); destructive by the marker/);
  assert.equal(judged("debitor/payments/vibrant.php is unrelated", "debitor/betalinger.php").length, 1);
});

/** A scratch repository: each step ({path: content}) committed in turn; its shas, git, and run(args, input, edits) for check.mjs there. */
function commits(...steps) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-pr-body-commits-"));
  const git = (...args) => spawnSync("git", ["-c", "user.name=guardrails-test", "-c", "user.email=guardrails-test@example.invalid", "-c", "commit.gpgsign=false", ...args], { cwd: dir, encoding: "utf8" });
  assert.equal(git("init", "-q").status, 0);
  const shas = steps.map((files, n) => {
    for (const [path, content] of Object.entries(files)) {
      mkdirSync(dirname(join(dir, path)), { recursive: true });
      writeFileSync(join(dir, path), content);
    }
    assert.equal(git("add", "-A").status, 0);
    assert.equal(git("commit", "-q", "--allow-empty", "--no-verify", "-m", `step ${n}`).status, 0);
    return git("rev-parse", "HEAD").stdout.trim();
  });
  const run = (args, input, edits) => {
    if (edits) writeFileSync(join(dir, ".git", "pr-edits.json"), JSON.stringify(edits));
    const extra = edits ? ["--pr-edits", join(dir, ".git", "pr-edits.json")] : [];
    const result = spawnSync(process.execPath, [CHECK, ...args, ...extra], { cwd: dir, input, encoding: "utf8", timeout: 600000, env: { ...process.env, GUARDRAILS_TODAY: "2026-11-06", GITHUB_ACTIONS: "" } });
    return { status: result.status, stdout: result.stdout, files: files(result.stdout) };
  };
  return { dir, shas, git, run, done: () => rmSync(dir, { recursive: true, force: true }) };
}

test("an exception is bound to the commit it was approved at: a later change to the file brings the finding back until the reviewer approves the new head; a file unchanged since keeps it; a line without a commit, or with one the history does not hold, does not count", () => {
  const repo = commits(
    { "debitor/betalinger.php": "<?php\n$menu = ['card', 'broken'];\n", "debitor/betalingsliste.php": "<?php\n$rows = [];\n" },
    { "debitor/betalinger.php": "<?php\n$menu = ['card'];\n", "debitor/betalingsliste.php": "<?php\n$rows = array();\n" },
    { "debitor/betalinger.php": "<?php\n$menu = ['card'];\nsendPaymentFile($menu);\n" },
  );
  const [base, reviewed, later] = repo.shas;
  try {
    const judged = (head, at) => {
      const lines = [allowLine("debitor/betalinger.php removes a broken menu option", { at }), allowLine("debitor/betalingsliste.php only renames a variable", { at })].join("\n");
      const text = body({ extra: lines });
      return repo.run(["--pr-body", "-", "--base", base, "--head", head], text, history([[AUTHOR, body()], [NICOLAI.login, text]]));
    };
    assert.equal(judged(reviewed, reviewed).status, 0, "approved at the head it judges");
    assert.equal(judged(reviewed, reviewed.slice(0, 12)).status, 0, "a short sha");
    const stale = judged(later, reviewed);
    assert.equal(stale.status, 1);
    assert.deepEqual(stale.files, ["debitor/betalinger.php"], "betalingsliste.php is as it was at the approved commit");
    assert.match(stale.stdout, new RegExp(`does not count: debitor/betalinger\\.php changed after ${reviewed}, the commit it was approved at; the reviewer approves the change again`));
    assert.match(stale.stdout, new RegExp(`by <INITIALS> at ${later.slice(0, 12)}: debitor/betalinger\\.php <why>`), "the finding names the head to approve");
    assert.equal(judged(later, later).status, 0, "renewed at the new head");
    const missing = judged(later, "0000000");
    assert.deepEqual(missing.files, ["debitor/betalinger.php", "debitor/betalingsliste.php"]);
    assert.match(missing.stdout, /the commit 0000000 it names cannot be read in this change's history/);
    const bare = body({ extra: `guardrails-allow pr-body until 2026-12-31 by ${NICOLAI.initials}: debitor/betalinger.php removes a broken menu option` });
    const unbound = repo.run(["--pr-body", "-", "--base", base, "--head", reviewed], bare, history([[AUTHOR, body()], [NICOLAI.login, bare]]));
    assert.deepEqual(unbound.files, ["debitor/betalinger.php", "debitor/betalingsliste.php"]);
    assert.match(unbound.stdout, /does not count: it names no commit; the reviewer approves the change again/);
  } finally {
    repo.done();
  }
});

test("where the file as the change leaves it cannot be read, a last line's eof mark comes from the diff's hunk (markerLinesOf, which --station's markerLines uses too): a bare CR keeps its comment's DROP DATABASE", () => {
  const line = `// ${DROP}\r`;
  const file = (entry) => ({ path: "finans/report.js", status: "A", added: new Map([[1, line]]), hunks: [{ removed: [], added: [entry] }] });
  assert.deepEqual(markerLinesOf(file({ line: 1, text: line, eof: true }), null), [{ line: 1, text: line, eof: true }]);
  const run = (entry) => prBody.run(dangerBody("two-way"), { files: [file(entry)], isVendored: () => false, today: "2026-11-06", readHead: () => null });
  const findings = run({ line: 1, text: line, eof: true });
  assert.equal(findings.length, 1);
  assert.match(findings[0].message, /destructive by the marker "DROP DATABASE"/);
  // Without the mark the CR is a CR LF's, and the line is an established comment.
  assert.deepEqual(run({ line: 1, text: line }), []);
});

test("a removed line is read in the base version of its file: a commented-out DROP DATABASE taken out claims no file; the same statement taken out as code claims it", () => {
  for (const [path, base, head] of [
    ["finans/report.js", `run();\n// ${DROP}\n`, "run();\n"],
    ["finans/report.php", `<?php\n$x = 1;\n// ${DROP}\n`, "<?php\n$x = 1;\n"],
    ["finans/report.js", `// ${DROP}\nrun();\n`, "run();\n"],
  ]) {
    assert.deepEqual(bothWays([[path, base, head]], { commit: true }), { station: [], claimed: [], status: 0 }, `${path}: ${JSON.stringify(base)}`);
  }
  for (const [path, base, head] of [
    ["finans/report.js", `run();\n${DROP};\n`, "run();\n"],
    ["finans/report.php", `<?php\n$x = 1;\n${DROP};\n`, "<?php\n$x = 1;\n"],
  ]) {
    assert.deepEqual(bothWays([[path, base, head]], { commit: true }), { station: [], claimed: [path], status: 1 }, `${path}: ${JSON.stringify(base)}`);
  }
});

/**
 * A scratch repository for the description job: `base` and `head` ({path: content}, head over base) committed in
 * turn, with this working tree's tools/guardrails (its tests left out) where `guardrails` names a commit, edited there
 * by `edit` ({path: (text) => text}). The head is checked out, as CI checks it out.
 */
function policyRepo({ base, head, guardrails, edit = {} }) {
  const repo = commits();
  const dir = repo.dir;
  const copy = () =>
    cpSync(join(ROOT, "tools/guardrails"), join(dir, "tools/guardrails"), { recursive: true, filter: (source) => !source.startsWith(join(ROOT, "tools/guardrails/tests")) });
  const step = (files, withGuardrails, edits) => {
    if (withGuardrails) copy();
    for (const [path, change] of Object.entries(edits)) {
      const before = readFileSync(join(dir, path), "utf8");
      const after = change(before);
      assert.notEqual(after, before, `${path} is edited`);
      writeFileSync(join(dir, path), after);
    }
    for (const [path, content] of Object.entries(files)) {
      mkdirSync(dirname(join(dir, path)), { recursive: true });
      writeFileSync(join(dir, path), content);
    }
    assert.equal(repo.git("add", "-A").status, 0);
    assert.equal(repo.git("commit", "-q", "--allow-empty", "--no-verify", "-m", "step").status, 0);
    return repo.git("rev-parse", "HEAD").stdout.trim();
  };
  const baseSha = step(base, guardrails.includes("base"), {});
  const headSha = step(head, guardrails.includes("head") && !guardrails.includes("base"), edit);
  return { dir, base: baseSha, head: headSha, done: repo.done };
}

test("the description job judges a PR by the base revision's checker, door map and reviewers, not by the PR's own: a PR that takes its file out of the door map, or turns the check off, is held to the base's", () => {
  const loosened = {
    "the door map": { "tools/guardrails/lib/doors.mjs": (text) => text.replace('      "{debitor,kreditor}/{betalinger,betalingsliste}.php",\n', "") },
    "the checker": { "tools/guardrails/checks/pr-body.mjs": () => 'export default { id: "pr-body", summary: "nothing", run: () => [] };\n' },
  };
  for (const [what, edit] of Object.entries(loosened)) {
    const repo = policyRepo({
      base: { "debitor/betalinger.php": "<?php\n$menu = ['card', 'broken'];\n" },
      head: { "debitor/betalinger.php": "<?php\n$menu = ['card'];\n" },
      guardrails: ["base"],
      edit,
    });
    try {
      // The head's own rules let the change through.
      const own = spawnSync(process.execPath, [join(repo.dir, "tools/guardrails/check.mjs"), "--pr-body", "-", "--base", repo.base, "--head", repo.head], {
        cwd: repo.dir,
        input: body(),
        encoding: "utf8",
        timeout: 600000,
        env: { ...process.env, GITHUB_ACTIONS: "" },
      });
      assert.equal(own.status, 0, `${what}: ${own.stdout}`);
      const ci = runDescriptionStep(repo.head, body(), { cwd: repo.dir, base: repo.base });
      assert.match(ci.stdout, new RegExp(`^door policy: tools/guardrails of the base revision ${repo.base} judges this description$`, "m"), what);
      assert.equal(ci.status, 1, `${what}: ${ci.stdout}`);
      assert.deepEqual(ci.files, ["debitor/betalinger.php"], what);
    } finally {
      repo.done();
    }
  }
});

test("bootstrap: while the base revision has no door map, the description job runs the head's checker and says so in the log", () => {
  const repo = policyRepo({
    base: { "debitor/betalinger.php": "<?php\n$menu = ['card', 'broken'];\n" },
    head: { "debitor/betalinger.php": "<?php\n$menu = ['card'];\n" },
    guardrails: ["head"],
  });
  try {
    const ci = runDescriptionStep(repo.head, body(), { cwd: repo.dir, base: repo.base });
    assert.match(
      ci.stdout,
      new RegExp(`^door policy: bootstrap, the base revision ${repo.base} has no door map \\(tools/guardrails/lib/doors\\.mjs\\), so the PR head's tools/guardrails judges this description$`, "m"),
    );
    assert.equal(ci.status, 1, ci.stdout);
    assert.deepEqual(ci.files, ["debitor/betalinger.php"]);
    assert.equal(runDescriptionStep(repo.head, body({ door: "one-way" }), { cwd: repo.dir, base: repo.base }).status, 0);
  } finally {
    repo.done();
  }
});

/** The paths the cross-check's findings name ("path: [pr-body] the PR says ..."), a quoted one read back. */
const claimedPaths = (stdout) =>
  stdout
    .split("\n")
    .filter((line) => line.includes(": [pr-body] the PR says"))
    .map((line) => line.slice(0, line.indexOf(": [pr-body]")))
    .map((path) => (path.startsWith('"') ? JSON.parse(path) : path))
    .sort();

test("a file whose name git quotes in a diff header (a quote, a backslash, a tab) or that holds letters beyond ASCII is routed and cross-checked under its real path, and a one-way marker in it is found, committed and in the working tree", () => {
  const marked = ['finans/a"b.js', "finans/a\\b.js", "finans/a\tb.js", "finans/æøå.js"];
  const byPath = 'debitor/payments/x"y.js';
  const base = Object.fromEntries([...marked, byPath].map((path) => [path, "run();\n"]));
  const head = { ...Object.fromEntries(marked.map((path) => [path, `run();\n${DROP};\n`])), [byPath]: "run(1);\n" };
  const all = [...marked, byPath].sort();
  for (const committed of [true, false]) {
    const repo = commits(base, ...(committed ? [head] : []));
    try {
      if (!committed) for (const [path, content] of Object.entries(head)) writeFileSync(join(repo.dir, path), content);
      const range = committed ? ["--base", repo.shas[0], "--head", repo.shas[1]] : ["--base", repo.shas[0]];
      const where = committed ? "committed" : "working tree";
      const station = repo.run(["--station", "--json", ...range]);
      assert.equal(station.status, 0, station.stdout);
      const report = JSON.parse(station.stdout);
      assert.deepEqual(report.changedPaths, all, where);
      assert.deepEqual(report.markersHit.map((hit) => `${hit.path}:${hit.row}`).sort(), marked.map((path) => `${path}:destructive`).sort(), where);
      assert.deepEqual(report.doorRows.find((match) => match.row === "payments")?.hits.map((hit) => hit.path), [byPath], where);
      const pr = repo.run(["--pr-body", "-", ...range], dangerBody("two-way"));
      assert.equal(pr.status, 1, where);
      assert.deepEqual(claimedPaths(pr.stdout), all, where);
      assert.doesNotMatch(pr.stdout, /could not be read/, `${where}: every header was read`);
      assert.ok(!pr.stdout.split("\n").some((line) => line.startsWith("finans/a") && line.includes("\t")), `${where}: a tab in a name is printed quoted`);
    } finally {
      repo.done();
    }
  }
});

test("a diff header that cannot be read fails closed: no record under a wrong or empty path; the file git lists is read whole under its real path, so a marker anywhere in it is found by the cross-check and by --station", () => {
  // headerPath reads git's C-style quoting exactly, and gives null for anything else.
  assert.equal(headerPath('diff --git "a/finans/a\\"b.js" "b/finans/a\\"b.js"'), 'finans/a"b.js');
  assert.equal(headerPath('diff --git "a/finans/a\\\\b.js" "b/finans/a\\\\b.js"'), "finans/a\\b.js");
  assert.equal(headerPath('diff --git "a/finans/a\\tb.js" "b/finans/a\\tb.js"'), "finans/a\tb.js");
  assert.equal(headerPath('diff --git "a/finans/\\303\\246\\303\\270\\303\\245.js" "b/finans/\\303\\246\\303\\270\\303\\245.js"'), "finans/æøå.js");
  assert.equal(headerPath("diff --git a/finans/æøå.js b/finans/æøå.js"), "finans/æøå.js");
  assert.equal(headerPath("diff --git a/a b/c b/a b/c"), "a b/c");
  for (const raw of [
    'diff --git "a/finans/x.js b/finans/x.js',
    'diff --git "a/finans/x\\q.js" "b/finans/x\\q.js"',
    'diff --git "a/finans/x.js" "b/finans/y.js"',
    "diff --git a/finans/x.js b/finans/y.js",
    "diff --git finans/x.js finans/x.js",
    "diff --git a/ b/",
  ]) {
    assert.equal(headerPath(raw), null, raw);
  }
  const text = [
    'diff --git "a/finans/x.js b/finans/x.js',
    "new file mode 100644",
    "--- /dev/null",
    '+++ "b/finans/x.js',
    "@@ -0,0 +1 @@",
    "+run();",
    "diff --git a/finans/y.js b/finans/y.js",
    "new file mode 100644",
    "--- /dev/null",
    "+++ b/finans/y.js",
    "@@ -0,0 +1 @@",
    "+run();",
    "",
  ].join("\n");
  const heads = { "finans/x.js": `run();\n${DROP};\n`, "finans/y.js": "run();\n" };
  const read = { head: (path) => heads[path] ?? null, base: () => null };
  const files = filesOfDiff(text, ["finans/x.js", "finans/y.js"], read);
  assert.deepEqual([...files.keys()], ["finans/x.js", "finans/y.js"], "only the paths git lists");
  const x = files.get("finans/x.js");
  assert.equal(x.unreadable, UNREADABLE);
  assert.deepEqual([...x.added], [[1, "run();"], [2, `${DROP};`]], "every line of it as changed");
  assert.equal(files.get("finans/y.js").unreadable, undefined);
  const findings = prBody.run(dangerBody("two-way"), { files: [...files.values()], isVendored: () => false, today: "2026-11-06", readHead: read.head, readBase: read.base });
  assert.deepEqual(findings.map((finding) => finding.path), ["finans/x.js"]);
  assert.match(findings[0].message, /destructive by the marker "DROP DATABASE"/);
  const lines = new Map([...files].map(([path, file]) => [path, markerLinesOf(file, read.head(path))]));
  const report = stationReport({ input: { mode: "diff" }, paths: [...files.keys()], lines });
  assert.deepEqual(report.markersHit.map((hit) => `${hit.path}:${hit.line}:${hit.row}`), ["finans/x.js:2:destructive"]);
});

test("a change's own .gitattributes hides none of its lines: with its .php and .js files marked binary, -diff, or given a text conversion driver (one a developer's git config defines), --station and the cross-check still find a payments write and DROP DATABASE in them", () => {
  const php = "finans/report.php";
  const js = "finans/report.js";
  const base = { [php]: "<?php\n$x = 1;\n", [js]: "run();\n" };
  const changed = { [php]: `<?php\n$x = 1;\n$sql = "insert into pos_betalinger (id) values (1)";\n`, [js]: `run();\n${DROP};\n` };
  // A driver that converts every text to nothing, so a diff through it shows no line.
  const driver = { GIT_CONFIG_COUNT: "1", GIT_CONFIG_KEY_0: "diff.hidden.textconv", GIT_CONFIG_VALUE_0: "true" };
  const cases = [
    ["*.php binary\n*.js binary\n", {}],
    ["*.php -diff\n*.js -diff\n", {}],
    ["*.php diff=hidden\n*.js diff=hidden\n", driver],
  ];
  for (const [attributes, config] of cases) {
    const repo = commits(base, { ".gitattributes": attributes, ...changed });
    const run = (args, input) =>
      spawnSync(process.execPath, [CHECK, ...args], { cwd: repo.dir, input, encoding: "utf8", timeout: 600000, env: { ...process.env, GUARDRAILS_TODAY: "2026-11-06", GITHUB_ACTIONS: "", ...config } });
    try {
      const range = ["--base", repo.shas[0], "--head", repo.shas[1]];
      const station = run(["--station", "--json", ...range]);
      assert.equal(station.status, 0, station.stderr);
      const report = JSON.parse(station.stdout);
      assert.deepEqual(report.markersHit.map((hit) => `${hit.path}:${hit.line}:${hit.row}`).sort(), [`${js}:2:destructive`, `${php}:3:payments`], attributes);
      const pr = run(["--pr-body", "-", ...range], dangerBody("two-way"));
      assert.equal(pr.status, 1, attributes);
      assert.deepEqual(claimedPaths(pr.stdout), [js, php], attributes);
      // Read from the diff itself (--text, --no-textconv), not only through the fallback that reads a file whole.
      assert.doesNotMatch(`${pr.stdout}${station.stderr}`, /shows no line of it/, attributes);
    } finally {
      repo.done();
    }
  }
});

test("a file whose diff shows no line though its content changed is read whole (fail closed); a mode change and an empty new file keep their records", () => {
  const text = [
    "diff --git a/finans/x.js b/finans/x.js",
    "index 1111111..2222222 100644",
    "Binary files a/finans/x.js and b/finans/x.js differ",
    "diff --git a/run.sh b/run.sh",
    "old mode 100644",
    "new mode 100755",
    "diff --git a/empty.txt b/empty.txt",
    "new file mode 100644",
    "index 0000000..e69de29",
    "",
  ].join("\n");
  const heads = { "finans/x.js": `run();\n${DROP};\n`, "run.sh": "echo\n", "empty.txt": "" };
  const bases = { "finans/x.js": "run();\n", "run.sh": "echo\n" };
  const read = { head: (path) => heads[path] ?? null, base: (path) => bases[path] ?? null };
  const files = filesOfDiff(text, ["empty.txt", "finans/x.js", "run.sh"], read);
  const x = files.get("finans/x.js");
  assert.equal(x.unreadable, NO_LINES);
  assert.deepEqual([...x.added], [[1, "run();"], [2, `${DROP};`]]);
  assert.deepEqual([...x.removed], [[1, "run();"]]);
  assert.deepEqual([files.get("run.sh").unreadable, files.get("run.sh").oldMode, files.get("run.sh").newMode], [undefined, "100644", "100755"]);
  assert.deepEqual([files.get("empty.txt").unreadable, files.get("empty.txt").status], [undefined, "A"]);
  const findings = prBody.run(dangerBody("two-way"), { files: [...files.values()], isVendored: () => false, today: "2026-11-06", readHead: read.head, readBase: read.base });
  assert.deepEqual(findings.map((finding) => finding.path), ["finans/x.js"]);
  assert.match(findings[0].message, /destructive by the marker "DROP DATABASE"/);
});

test("context lines that a git setting adds despite -U0 still move both line counts", () => {
  const patch = [
    "diff --git a/finans/x.js b/finans/x.js",
    "--- a/finans/x.js",
    "+++ b/finans/x.js",
    "@@ -2,5 +2,5 @@",
    "-old two",
    "+new two",
    " three",
    " four",
    "-old five",
    "+new five",
    " six",
    "",
  ].join("\n");
  const file = parseDiff(patch).get("finans/x.js");
  assert.deepEqual([...file.added.entries()], [[2, "new two"], [5, "new five"]]);
  assert.deepEqual([...file.removed.entries()], [[2, "old two"], [5, "old five"]]);
});

test("diff.interHunkContext and GIT_DIFF_OPTS cannot shift where a marker is found", () => {
  const base = ["a();", "b();", "c();", "d();", "e();", "f();", ""].join("\n");
  const head = ["a();", "B();", "c();", "d();", "e();", DROP, ""].join("\n");
  const repo = scratch([["finans/x.js", base, head]], { commit: true });
  const saved = process.env.GIT_DIFF_OPTS;
  try {
    assert.equal(spawnSync("git", ["config", "diff.interHunkContext", "20"], { cwd: repo.dir }).status, 0);
    process.env.GIT_DIFF_OPTS = "--unified=5";
    const station = repo.run(["--station", "--json", "--base", repo.base, "--head", repo.head]);
    assert.equal(station.status, 0, station.stderr);
    const hits = JSON.parse(station.stdout).markersHit.map((hit) => `${hit.path}:${hit.line}:${hit.row}`);
    assert.deepEqual(hits, ["finans/x.js:6:destructive"]);
  } finally {
    if (saved === undefined) delete process.env.GIT_DIFF_OPTS;
    else process.env.GIT_DIFF_OPTS = saved;
    repo.done();
  }
});

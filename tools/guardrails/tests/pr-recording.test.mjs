// 20261118 CL/LH Created: a UI change needs a screen recording in the PR description.
// 20261126 CDX/NTR Cover an explicit, expiring reviewer waiver of the recording.
import test from "node:test";
import assert from "node:assert/strict";
import check, { recordingState, uiChanges } from "../checks/pr-recording.mjs";

const php = (path, ...lines) => ({ path, status: "M", added: new Map(lines.map((text, i) => [i + 1, text])) });
const file = (path, status = "M") => ({ path, status, added: new Map() });
const VIDEO = "https://github.com/user-attachments/assets/5f0c2a7e-1b3d-4c5e-9f00-0123456789ab";

test("stylesheets, scripts, templates and texts are UI; tests, tools and deletions are not", () => {
  const ui = uiChanges([file("css/main.css"), file("rental/index.js"), file("importfiler/tekster.csv"), file("e2e/a.spec.mjs"), file("tools/guardrails/check.mjs"), file("old.js", "D")]);
  assert.deepEqual(ui.map((c) => c.path), ["css/main.css", "rental/index.js", "importfiler/tekster.csv"]);
});

test("PHP is UI only where an added line writes markup", () => {
  const ui = uiChanges([
    php("debitor/ordre.php", "$q = db_select(\"select * from ordrer\");", "print \"<td class='x'>$navn</td>\";"),
    php("includes/std_func.php", "$class = \"Foo\";", "if ($a <$b) return;", "return $sum;"),
    php("lager/varekort.php", "echo ' onclick=\"go()\"';"),
  ]);
  assert.deepEqual(ui.map((c) => [c.path, c.line]), [["debitor/ordre.php", 2], ["lager/varekort.php", 1]]);
});

test("a dropped video, a video link or <video> counts; an image does not", () => {
  assert.equal(recordingState(`## Screen recording\n${VIDEO}\n`), "recorded");
  assert.equal(recordingState("See https://github.com/danosoft-kitchen/saldi/releases/download/p2/plate.webm for the run."), "recorded");
  assert.equal(recordingState('<video src="https://example.test/a.mp4"></video>'), "recorded");
  assert.equal(recordingState(`![shot](${VIDEO})`), "missing");
  assert.equal(recordingState(`<img width="300" src="${VIDEO}">`), "missing");
  assert.equal(recordingState(`<!-- ${VIDEO} -->`), "missing");
});

test("Ingen UI-ændring with a reason opts out; without one it does not", () => {
  assert.equal(recordingState("Ingen UI-ændring: only the SQL behind the list changes"), "opted-out");
  assert.equal(recordingState("Ingen UI-ændring:"), "missing");
});

test("an explicit reviewer waiver uses the existing dated exception format", () => {
  const body = "guardrails-allow pr-recording until 2026-12-31 by NTR: reviewer explicitly waived the recording for this PR";
  const files = [file("rental/index.js")];
  assert.equal(recordingState(body, "2026-11-26"), "waived");
  assert.deepEqual(check.run(body, files, { today: "2026-12-31" }), []);
  assert.equal(check.run(body, files, { today: "2027-01-01" }).length, 1);
  for (const invalid of [
    body.replace("by NTR: reviewer explicitly waived the recording for this PR", "by NTR:"),
    body.replace("by NTR:", "by:"),
    body.replace("pr-recording", "migrations"),
    `<!-- ${body} -->`,
    `<!-- ${body}`,
    `<!--\n${body}`,
    "The reviewer waived it.",
  ]) {
    assert.equal(recordingState(invalid, "2026-11-26"), "missing", invalid);
  }
});

test("the check fails a UI change without a recording, and names the files", () => {
  const findings = check.run("## What are the changes about?\nFixes it.", [file("rental/index.js")]);
  assert.equal(findings.length, 1);
  assert.match(findings[0].message, /rental\/index\.js \(stylesheet, script or markup\)/);
  assert.deepEqual(check.run(`## Screen recording\n${VIDEO}`, [file("rental/index.js")]), []);
  assert.deepEqual(check.run("no recording", [php("api/sync.php", "return $rows;")]), []);
});

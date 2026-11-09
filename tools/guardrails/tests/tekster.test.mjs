// tools/guardrails/tests/tekster.test.mjs - tekster keys only: importfiler/tekster.csv takes no new numeric ids.
//
// The tekster check fails on #942 600411b0 (ids 7650-7651) and #831 56f0e16f (ids 5440-5441); a new text takes a
// descriptive key in importfiler/tekster_keyed.tsv instead, and every remediation message points there or at
// deleteStaleTekst(), never at a new id.
//
// History:
// 20261103 CL/LH Created.
// 20261109 CL/LH A moved row is an added line, as git's diff shows it; the remediation messages name no new id.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import tekster from "../checks/tekster.mjs";

const ROOT = fileURLToPath(new URL("../../../", import.meta.url));
const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
const CSV = "importfiler/tekster.csv";
const KEYED = "importfiler/tekster_keyed.tsv";

function judge(sha) {
  const result = spawnSync(process.execPath, [CHECK, "--base", `${sha}^`, "--head", sha, "--only", "tekster"], {
    cwd: ROOT,
    encoding: "utf8",
    env: { ...process.env, GITHUB_ACTIONS: "" },
  });
  return { status: result.status, ids: [...result.stdout.matchAll(/new numeric id (\d+)/g)].map((match) => match[1]) };
}

/**
 * A change to the seed files: `before` and `after` per path, every line of `after` not in `before` counted as added,
 * and the line numbers in `moved` too (a row moved in the file is an added line of git's diff, though its text is not new).
 */
function context(files) {
  const entries = Object.entries(files).map(([path, { before, after, moved = [] }]) => {
    const old = new Set(before.split("\n"));
    const added = new Map(after.split("\n").flatMap((text, i) => (text !== "" && (!old.has(text) || moved.includes(i + 1)) ? [[i + 1, text]] : [])));
    return { path, status: "M", added, hunks: [{ removed: [], added: [...added].map(([line, text]) => ({ line, text })) }] };
  });
  return {
    files: entries,
    isVendored: () => false,
    readHead: (path) => files[path]?.after ?? null,
    readBase: (path) => files[path]?.before ?? null,
    grepHead: () => [],
  };
}

const ROWS = "100\tGem\tSave\tLagre\n101\tLuk\tClose\tLukk\n";

test("#942 600411b0 fails: it adds ids 7650 and 7651", () => {
  assert.deepEqual(judge("600411b0"), { status: 1, ids: ["7650", "7651"] });
});

test("#831 56f0e16f fails: it adds ids 5440 and 5441", () => {
  assert.deepEqual(judge("56f0e16f"), { status: 1, ids: ["5440", "5441"] });
});

test("a new text with a descriptive key passes", () => {
  const ctx = context({
    [CSV]: { before: ROWS, after: ROWS },
    [KEYED]: { before: "", after: "settlement.all_customers\tAlle debitorer\tAll customers\tAlle debitorer\n" },
  });
  assert.deepEqual(tekster.run(ctx), []);
});

test("an existing id is not new: a reworded row with its purge, or a row moved in the file", () => {
  const reworded = context({ [CSV]: { before: ROWS, after: "100\tGem nu\tSave now\tLagre nå\n101\tLuk\tClose\tLukk\n" } });
  reworded.files.push({ path: "includes/betweenUpdates.php", status: "M", added: new Map([[9, "deleteStaleTekst(100, 'Gem');"]]), hunks: [] });
  assert.deepEqual(tekster.run(reworded), []);
  // git's diff of the move: 101 stays, 100 is removed above it and added below it, on line 2.
  const moved = context({ [CSV]: { before: ROWS, after: "101\tLuk\tClose\tLukk\n100\tGem\tSave\tLagre\n", moved: [2] } });
  assert.deepEqual([...moved.files[0].added.keys()], [2], "the moved row is an added line");
  assert.ok(!tekster.run(moved).some((finding) => finding.message.includes("new numeric id")));
});

test("a new id is refused once, with the way out in the message", () => {
  const findings = tekster.run(context({ [CSV]: { before: ROWS, after: `${ROWS}102\tÅbn\tOpen\tÅpne\n` } }));
  assert.equal(findings.length, 1);
  assert.equal(findings[0].line, 3);
  assert.match(findings[0].message, /new numeric id 102: importfiler\/tekster\.csv takes no new ids/);
  assert.match(findings[0].message, /importfiler\/tekster_keyed\.tsv/);
});

test("the remediation messages point at a descriptive key or deleteStaleTekst(), never at a new id or at tekster.csv for a key", () => {
  const reworded = tekster.run(context({ [CSV]: { before: ROWS, after: "100\tGem nu\tSave now\tLagre nå\n101\tLuk\tClose\tLukk\n" } }));
  assert.equal(reworded.length, 1);
  assert.match(reworded[0].message, /id 100's text changed/);
  assert.match(reworded[0].message, /a descriptive key in importfiler\/tekster_keyed\.tsv \(importfiler\/tekster\.csv takes no new ids\)/);
  assert.match(reworded[0].message, /deleteStaleTekst\(100, '<old text>'\)/);
  assert.doesNotMatch(reworded[0].message, /new id\b(?!s)/);
  const numericKey = tekster.run(context({ [KEYED]: { before: "", after: "4711\tGem\tSave\tLagre\n" } }));
  assert.equal(numericKey.length, 1);
  assert.match(numericKey[0].message, /the key "4711" is numeric; use a descriptive key such as area\.what \(importfiler\/tekster\.csv takes no new ids\)/);
  assert.doesNotMatch(numericKey[0].message, /belong in/);
  const keyReworded = tekster.run(context({ [KEYED]: { before: "a.save\tGem\tSave\tLagre\n", after: "a.save\tGem nu\tSave now\tLagre nå\n" } }));
  assert.equal(keyReworded.length, 1);
  assert.match(keyReworded[0].message, /a new descriptive key, or add deleteStaleTekst\('a\.save', <old text or texts>\)/);
  for (const finding of [...reworded, ...numericKey, ...keyReworded]) assert.doesNotMatch(finding.message, /new id\b(?!s)|numeric ids belong/);
});

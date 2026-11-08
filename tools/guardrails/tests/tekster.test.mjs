// tools/guardrails/tests/tekster.test.mjs - tekster keys only: importfiler/tekster.csv takes no new numeric ids.
//
// The tekster check fails on #942 600411b0 (ids 7650-7651) and #831 56f0e16f (ids 5440-5441); a new text takes a
// descriptive key in importfiler/tekster_keyed.tsv instead.
//
// History:
// 20261103 CL/LH Created.
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

/** A change to the seed files: `before` and `after` per path, every line of `after` not in `before` counted as added. */
function context(files) {
  const entries = Object.entries(files).map(([path, { before, after }]) => {
    const old = new Set(before.split("\n"));
    const added = new Map(after.split("\n").flatMap((text, i) => (text !== "" && !old.has(text) ? [[i + 1, text]] : [])));
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
  const moved = context({ [CSV]: { before: ROWS, after: "101\tLuk\tClose\tLukk\n100\tGem\tSave\tLagre\n" } });
  assert.ok(!tekster.run(moved).some((finding) => finding.message.includes("new numeric id")));
});

test("a new id is refused once, with the way out in the message", () => {
  const findings = tekster.run(context({ [CSV]: { before: ROWS, after: `${ROWS}102\tÅbn\tOpen\tÅpne\n` } }));
  assert.equal(findings.length, 1);
  assert.equal(findings[0].line, 3);
  assert.match(findings[0].message, /new numeric id 102: importfiler\/tekster\.csv takes no new ids/);
  assert.match(findings[0].message, /importfiler\/tekster_keyed\.tsv/);
});

#!/usr/bin/env node
// tools/guardrails/check.mjs - the repo's mechanical review, run locally and in CI.
//
// Each check turns a mistake reviewers kept correcting into an error that names
// the file, the line and what to do instead. The table of rules and what
// enforces each one is in tools/guardrails/README.md.
//
//   node tools/guardrails/check.mjs                    # working tree vs origin/master
//   node tools/guardrails/check.mjs --base origin/hml/shopify
//   node tools/guardrails/check.mjs --base abc123^ --head abc123   # judge one past commit
//   node tools/guardrails/check.mjs --only php-syntax,js-syntax
//   gh pr view 123 --json body -q .body | node tools/guardrails/check.mjs --pr-body -
//   node tools/guardrails/check.mjs --pr-body body.md --pr-edits edits.json --base <sha> --head <sha>   # as CI does
//   node tools/guardrails/check.mjs --station          # station, reviewer and proof for this change
//   printf '%s\n' a.php b.php | node tools/guardrails/check.mjs --station --json --paths -
//
// Exit code 1 when any check finds something. An exception for a single line is
// described in lib/exceptions.mjs.
//
// The checks judge the git repository of the working directory, not the one this
// file lives in: CI runs the base revision's copy of tools/guardrails against the
// PR's checkout, so a PR is not judged by its own rules (guardrails.yml).
//
// --pr-body judges the description. When the base exists it also builds the
// change (--base, --head as above), so the description's door is held against
// the paths the change touches; without a base, or with --text-only, only the
// text is judged. --pr-edits names the description's edit history as CI reads it
// from GitHub (guardrails.yml); a door exception counts only when that history
// shows a reviewer added it, so without it no exception counts.
//
// --station routes the change by the door map (lib/doors.mjs, lib/station.mjs)
// and never fails: exit 0 with the report, 2 when it cannot route. A PHP file is
// read by PHP's own tokens (lib/php.mjs: PHP 8.2 from PATH or Docker, as
// php-syntax runs it); where PHP cannot run, every line that names a password in
// a PHP file counts, and check.mjs says why (on stderr with --json). --json prints
// one saldi.station.v1 object and nothing else; --paths reads candidate paths,
// one per line, instead of a diff (no markers then). Each is matched as the
// repository path it names (./a/b.php is a/b.php); one outside the repository
// is refused.
//
// History:
// 20261027 CL/LH Created.
// 20261103 CL/LH --station routes a change by the door map; --pr-body holds the door against the change.
// 20261103 CL/LH --paths matches canonical repository paths; --pr-edits and --text-only for the description job.
// 20261106 CL/LH --station and the door cross-check read PHP files with PHP's own tokenizer, one request per change.
// 20261107 CL/LH --station hands the markers the unchanged lines around each added run (changeset markerLines).
// 20261109 CL/LH The checks judge the working directory's repository: CI runs the base revision's copy against the PR.

import { readFileSync, readdirSync } from "node:fs";
import { buildChangeset, git, revExists, splitLines } from "./lib/changeset.mjs";
import { findException } from "./lib/exceptions.mjs";

/**
 * Why PHP's tokenizer could not read the change's PHP files (doors.mjs phpFailure), as a line for the developer, or
 * null. The door map is loaded where it is used, so a map that does not load is reported as such.
 */
async function phpNote() {
  const failure = (await import("./lib/doors.mjs")).phpFailure();
  return failure ? `PHP's tokenizer could not read the PHP files (${failure}), so every line that names a password in a PHP file counts.` : null;
}

/** Every file in checks/ is a check: adding one is adding a file. */
async function loadChecks() {
  return Promise.all(
    readdirSync(new URL("./checks/", import.meta.url))
      .filter((name) => name.endsWith(".mjs"))
      .sort()
      .map(async (name) => (await import(`./checks/${name}`)).default),
  );
}

class UsageError extends Error {}

function parseArgs(argv) {
  const args = {
    base: "origin/master",
    head: undefined,
    only: null,
    prBody: null,
    prEdits: null,
    textOnly: false,
    list: false,
    station: false,
    json: false,
    paths: null,
  };
  for (let i = 0; i < argv.length; i++) {
    const flag = argv[i];
    const value = () => {
      if (i + 1 >= argv.length) throw new UsageError(`${flag} needs a value; see the top of tools/guardrails/check.mjs`);
      return argv[++i];
    };
    switch (flag) {
      case "--base": args.base = value(); break;
      case "--head": args.head = value(); break;
      case "--only": args.only = value().split(","); break;
      case "--pr-body": args.prBody = value(); break;
      case "--pr-edits": args.prEdits = value(); break;
      case "--text-only": args.textOnly = true; break;
      case "--paths": args.paths = value(); break;
      case "--list": args.list = true; break;
      case "--station": args.station = true; break;
      case "--json": args.json = true; break;
      default:
        throw new UsageError(`unknown argument ${flag}; see the top of tools/guardrails/check.mjs`);
    }
  }
  if (!args.station && (args.json || args.paths !== null)) throw new UsageError("--json and --paths go with --station");
  if (args.station && (args.only || args.prBody !== null || args.list)) throw new UsageError("--station takes only --base, --head, --paths and --json");
  if (args.paths !== null && args.head !== undefined) throw new UsageError("--paths judges candidate paths against --base, so it takes no --head");
  if (args.prBody === null && (args.prEdits !== null || args.textOnly)) throw new UsageError("--pr-edits and --text-only go with --pr-body");
  return args;
}

function report(findings) {
  const annotate = process.env.GITHUB_ACTIONS === "true";
  for (const finding of findings) {
    const where = finding.line ? `${finding.path}:${finding.line}` : finding.path;
    console.log(`${where}: [${finding.check}] ${finding.message}`);
    if (annotate) {
      const message = `[${finding.check}] ${finding.message}`.replace(/%/g, "%25").replace(/\r/g, "").replace(/\n/g, "%0A");
      // Built before the template, so no template in this file holds another in an interpolation (doors.mjs plain subset).
      const line = finding.line ? `,line=${finding.line}` : "";
      console.log(`::error file=${finding.path}${line}::${message}`);
    }
  }
}

/** --station could not route: one saldi.station.v1 error (with --json) and exit 2. */
function stationFailed(json, code, message) {
  if (json) process.stdout.write(`${JSON.stringify({ schema: "saldi.station.v1", error: { code, message: message.slice(0, 500) } }, null, 2)}\n`);
  else console.error(`check.mjs --station: ${message}`);
  process.exitCode = 2;
}

async function station(args) {
  const fail = (code, message) => stationFailed(args.json, code, message);
  let top;
  try {
    top = git(["rev-parse", "--show-toplevel"]).trim();
  } catch (error) {
    return fail("not-a-git-repo", error.code === "ENOENT" ? "git is not on PATH" : "the working directory is not inside a git repository");
  }
  process.chdir(top);

  let routing;
  let doors;
  try {
    routing = await import("./lib/station.mjs");
    doors = await import("./lib/doors.mjs");
    // PHP's tokenizer, for the markers' reading of PHP files.
    await import("./lib/php.mjs");
  } catch (error) {
    return fail("door-map-invalid", `tools/guardrails/lib/doors.mjs does not load: ${error.message}`);
  }
  const print = (result) => process.stdout.write(args.json ? `${JSON.stringify(result, null, 2)}\n` : routing.formatStation(result));
  const problems = routing.validateDoorMap();
  if (problems.length) return fail("door-map-invalid", `tools/guardrails/lib/doors.mjs: ${problems.join("; ")}`);

  if (args.base === "" || args.base.length > 200 || !revExists(args.base)) return fail("unknown-ref", `unknown --base ${args.base}`);
  const baseSha = git(["rev-parse", `${args.base}^{commit}`]).trim();

  if (args.paths !== null) {
    let text;
    try {
      text = readFileSync(args.paths === "-" ? 0 : args.paths, "utf8");
    } catch (error) {
      return fail("bad-argument", `--paths ${args.paths} cannot be read: ${error.code ?? error.message}`);
    }
    const raw = splitLines(text).map((line) => line.trim()).filter(Boolean);
    const paths = raw.map(routing.canonicalPath);
    const bad = raw.find((_, index) => paths[index] === null);
    if (bad !== undefined) return fail("bad-argument", `not a path inside the repository: ${JSON.stringify(bad.slice(0, 200))}`);
    return print(routing.stationReport({ input: { mode: "paths", base: args.base, baseSha }, paths }));
  }

  let headSha = null;
  if (args.head !== undefined) {
    if (args.head === "" || args.head.length > 200 || !revExists(args.head)) return fail("unknown-ref", `unknown --head ${args.head}`);
    headSha = git(["rev-parse", `${args.head}^{commit}`]).trim();
  }
  const changeset = buildChangeset({ base: args.base, head: args.head });
  const lines = new Map(changeset.files.map((file) => [file.path, changeset.markerLines(file.path)]));
  // One request to PHP for every PHP file of the change.
  doors.prefetchPhp([...lines].filter(([path]) => !changeset.isVendored(path)));
  const input = { mode: "diff", base: args.base, baseSha, head: args.head ?? null, headSha };
  print(routing.stationReport({ input, paths: changeset.files.map((file) => file.path), lines, isVendored: changeset.isVendored }));
  const note = await phpNote();
  if (note) (args.json ? process.stderr : process.stdout).write(`${note}\n`);
}

async function main() {
  const argv = process.argv.slice(2);
  let args;
  try {
    args = parseArgs(argv);
  } catch (error) {
    if (!(error instanceof UsageError)) throw error;
    if (argv.includes("--station")) return stationFailed(argv.includes("--json"), "bad-argument", error.message);
    console.error(error.message);
    process.exit(2);
  }
  if (args.station) {
    try {
      return await station(args);
    } catch (error) {
      // Not a refusal a caller can name: no JSON, and an exit code that means "cannot route".
      console.error(error.stack ?? String(error));
      process.exit(1);
    }
  }

  const loaded = await loadChecks();
  if (args.list) {
    for (const check of loaded) console.log(`${check.id.padEnd(15)} ${check.summary}`);
    process.exit(0);
  }
  // pr-body judges the PR description instead of the change, so it only runs with --pr-body.
  const CHECKS = loaded.filter((check) => check.id !== "pr-body");
  const prBody = loaded.find((check) => check.id === "pr-body");

  process.chdir(git(["rev-parse", "--show-toplevel"]).trim());
  const today = process.env.GUARDRAILS_TODAY ?? new Date().toISOString().slice(0, 10);

  if (args.prBody) {
    if (!prBody) throw new Error("no pr-body check in tools/guardrails/checks/");
    const body = readFileSync(args.prBody === "-" ? 0 : args.prBody, "utf8");
    let ctx = { today };
    if (args.prEdits !== null) {
      try {
        ctx.edits = JSON.parse(readFileSync(args.prEdits, "utf8"));
      } catch (error) {
        ctx.edits = { error: `--pr-edits ${args.prEdits} cannot be read (${error.code ?? error.message.split("\n")[0]})` };
      }
    }
    let skipped = null;
    if (args.textOnly) console.log("door cross-check skipped: --text-only");
    else if (!revExists(args.base)) skipped = `no base ${args.base}`;
    else if (args.head !== undefined && !revExists(args.head)) skipped = `no head ${args.head}`;
    else {
      try {
        ctx = { ...buildChangeset({ base: args.base, head: args.head }), ...ctx };
      } catch (error) {
        skipped = `no change between ${args.base} and ${args.head ?? "the working tree"} (${error.message.split("\n")[0]})`;
      }
    }
    if (skipped) console.log(`door cross-check skipped: ${skipped}`);
    const findings = prBody.run(body, ctx).map((finding) => ({ check: prBody.id, path: ".github/pull_request_template.md", ...finding }));
    const note = skipped || args.textOnly ? null : await phpNote();
    if (note) console.log(`door cross-check: ${note}`);
    // In CI a skipped cross-check is a broken job, not a pass: the job must give it the change.
    if (skipped && process.env.GITHUB_ACTIONS === "true") {
      findings.push({
        check: prBody.id,
        path: ".github/workflows/guardrails.yml",
        message: `the door cross-check did not run (${skipped}); the description job checks out the PR's full history and passes --base and --head, or --text-only for a sync PR.`,
      });
    }
    report(findings);
    console.log(findings.length ? `\n${findings.length} problem(s) in the PR description.` : "PR description: ok");
    process.exit(findings.length ? 1 : 0);
  }

  const base = args.base;
  if (!revExists(base)) {
    console.error(`base ${base} does not exist here; fetch it (git fetch origin) or pass --base <rev>`);
    process.exit(2);
  }
  const changeset = buildChangeset({ base, head: args.head });
  const ctx = { ...changeset, today };
  const checks = args.only ? CHECKS.filter((check) => args.only.includes(check.id)) : CHECKS;

  const findings = [];
  const allowed = [];
  for (const check of checks) {
    for (const finding of await check.run(ctx)) {
      const lines = finding.line ? splitLines(changeset.readHead(finding.path)) : [];
      const exception = finding.line ? findException(lines, finding.line, check.id, today) : null;
      if (exception?.allowed) {
        allowed.push({ check: check.id, ...finding, reason: exception.reason });
        continue;
      }
      findings.push({ check: check.id, ...finding, message: exception ? `${finding.message} (${exception.problem})` : finding.message });
    }
  }

  const range = `${changeset.mergeBase.slice(0, 8)}..${args.head ?? "working tree"}`;
  report(findings);
  for (const entry of allowed) console.log(`${entry.path}:${entry.line}: [${entry.check}] allowed by exception: ${entry.reason}`);
  console.log(
    findings.length
      ? `\n${findings.length} problem(s) in ${range} (${changeset.files.length} changed files). Exceptions: tools/guardrails/lib/exceptions.mjs`
      : `guardrails: ok (${checks.map((check) => check.id).join(", ")}) on ${range}, ${changeset.files.length} changed files`,
  );
  process.exit(findings.length ? 1 : 0);
}

main().catch((error) => {
  console.error(error.stack ?? String(error));
  process.exit(2);
});

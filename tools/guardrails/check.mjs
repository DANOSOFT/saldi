#!/usr/bin/env node
// tools/guardrails/check.mjs - the repo's mechanical review, run locally and in CI.
//
// Each check turns a mistake reviewers kept correcting into an error that names
// the file, the line and what to do instead. The table of rules and what
// enforces each one is in AGENTS.md.
//
//   node tools/guardrails/check.mjs                    # working tree vs origin/master
//   node tools/guardrails/check.mjs --base origin/hml/shopify
//   node tools/guardrails/check.mjs --base abc123^ --head abc123   # judge one past commit
//   node tools/guardrails/check.mjs --only php-syntax,js-syntax
//
// Exit code 1 when any check finds something. An exception for a single line is
// described in lib/exceptions.mjs.
//
// History:
// 20261027 CL/LH Created.

import { readFileSync, readdirSync } from "node:fs";
import { buildChangeset, git, revExists, splitLines } from "./lib/changeset.mjs";
import { findException } from "./lib/exceptions.mjs";

// Every file in checks/ is a check: adding one is adding a file. pr-body judges
// the PR description instead of the change, so it only runs with --pr-body.
const loaded = await Promise.all(
  readdirSync(new URL("./checks/", import.meta.url))
    .filter((name) => name.endsWith(".mjs"))
    .sort()
    .map(async (name) => (await import(`./checks/${name}`)).default),
);
const CHECKS = loaded.filter((check) => check.id !== "pr-body");
const prBody = loaded.find((check) => check.id === "pr-body");

function parseArgs(argv) {
  const args = { base: "origin/master", head: undefined, only: null, prBody: null };
  for (let i = 0; i < argv.length; i++) {
    const value = argv[i + 1];
    switch (argv[i]) {
      case "--base": args.base = value; i++; break;
      case "--head": args.head = value; i++; break;
      case "--only": args.only = value.split(","); i++; break;
      case "--pr-body": args.prBody = value; i++; break;
      case "--list":
        for (const check of loaded) console.log(`${check.id.padEnd(15)} ${check.summary}`);
        process.exit(0);
      default:
        console.error(`unknown argument ${argv[i]}; see the top of tools/guardrails/check.mjs`);
        process.exit(2);
    }
  }
  return args;
}

function report(findings) {
  const annotate = process.env.GITHUB_ACTIONS === "true";
  for (const finding of findings) {
    const where = finding.line ? `${finding.path}:${finding.line}` : finding.path;
    console.log(`${where}: [${finding.check}] ${finding.message}`);
    if (annotate) {
      const message = `[${finding.check}] ${finding.message}`.replace(/%/g, "%25").replace(/\r/g, "").replace(/\n/g, "%0A");
      console.log(`::error file=${finding.path}${finding.line ? `,line=${finding.line}` : ""}::${message}`);
    }
  }
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  process.chdir(git(["rev-parse", "--show-toplevel"]).trim());
  const today = process.env.GUARDRAILS_TODAY ?? new Date().toISOString().slice(0, 10);

  if (args.prBody) {
    if (!prBody) throw new Error("no pr-body check in tools/guardrails/checks/");
    const body = readFileSync(args.prBody === "-" ? 0 : args.prBody, "utf8");
    const findings = prBody.run(body).map((finding) => ({ check: prBody.id, path: ".github/pull_request_template.md", ...finding }));
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

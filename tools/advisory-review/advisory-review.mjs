#!/usr/bin/env node
// Advisory-only PR review gate for legacy PHP client repositories.
// The contract is intentionally non-blocking: useful findings are reported, but
// merge decisions stay with the human operator and the client repository rules.

import { spawnSync } from "node:child_process";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import os from "node:os";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

const usage = `Usage: node integrations/saldi/advisory-review/advisory-review.mjs [--base <ref>] [--head <ref>] [--repo-dir <path>] [--post <owner/repo> --pr <number>] [--out <file>]

Runs an advisory-only PHP PR review over changed .php files.

Options:
  --base <ref>       Base ref for git diff (default: origin/master if present, else master)
  --head <ref>       Head ref for git diff (default: HEAD)
  --repo-dir <path>  Repository to inspect (default: current directory)
  --out <file>       Markdown report path (default: .factory-run/advisory-review.md)
  --post <owner/repo> --pr <number>
                     Post the report as one GitHub PR comment when gh is available
  --help             Show this help
`;

const phpHeuristics = [
  // ereg and eregi were removed after PHP 5 and should migrate to preg_match.
  {
    id: "ereg-family",
    severity: "high",
    regex: /\bereg(i|_replace|i_replace)?\s*\(/i,
    message: "uses removed ereg-family API; migrate to preg_* before PHP 8 runtime work",
  },
  // split() was removed after PHP 5 and should usually become preg_split or explode.
  {
    id: "split",
    severity: "high",
    regex: /(^|[^\w])split\s*\(/i,
    message: "uses removed split() API; replace with preg_split() or explode()",
  },
  // each() was removed in PHP 8; foreach is the usual replacement.
  {
    id: "each",
    severity: "high",
    regex: /\beach\s*\(/i,
    message: "uses removed each() API; replace with foreach-compatible iteration",
  },
  // create_function() was removed in PHP 8 and hides syntax/runtime errors.
  {
    id: "create-function",
    severity: "high",
    regex: /\bcreate_function\s*\(/i,
    message: "uses removed create_function(); replace with a closure",
  },
  // mysql_* APIs were removed after PHP 5 and need mysqli/PDO migration.
  {
    id: "mysql-api",
    severity: "high",
    regex: /\bmysql_[a-z]+\s*\(/i,
    message: "uses removed mysql_* API; migrate to mysqli or PDO",
  },
  // get_magic_quotes_* disappeared after PHP 5-era runtimes.
  {
    id: "magic-quotes",
    severity: "medium",
    regex: /\bget_magic_quotes(_gpc|_runtime)?\s*\(/i,
    message: "uses removed magic quotes API; remove the branch or replace the escaping strategy",
  },
  // $HTTP_*_VARS superglobals are obsolete PHP 4/5 compatibility names.
  {
    id: "http-vars",
    severity: "medium",
    regex: /\$HTTP_[A-Z_]+/,
    message: "uses obsolete $HTTP_* superglobal alias; use the modern $_* superglobal",
  },
  // Curly-brace string and array offsets were removed in PHP 8.
  {
    id: "curly-offset",
    severity: "high",
    regex: /\$[A-Za-z_][A-Za-z0-9_]*\s*\{\s*[^}]+\s*\}/,
    message: "uses curly-brace offset syntax removed in PHP 8; use square brackets",
  },
  // extract() creates implicit variables and can mask undefined-variable bugs.
  {
    id: "extract",
    severity: "medium",
    regex: /\bextract\s*\(/i,
    message: "uses extract(), which creates implicit variables and can hide undefined-variable bugs",
  },
  // compact() reports undefined names on newer PHP versions when variables are absent.
  {
    id: "compact",
    severity: "medium",
    regex: /\bcompact\s*\(/i,
    message: "uses compact(); confirm every named variable is defined on all paths",
  },
  // Variable variables make undefined-variable and naming bugs hard to review.
  {
    id: "variable-variable",
    severity: "medium",
    regex: /\$\$[A-Za-z_][A-Za-z0-9_]*/,
    message: "uses a variable variable; confirm the dynamic name is always defined",
  },
];

function parseArgs(argv) {
  const args = {
    base: null,
    head: "HEAD",
    repoDir: process.cwd(),
    post: null,
    pr: null,
    out: path.join(".factory-run", "advisory-review.md"),
    help: false,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    if (arg === "--help" || arg === "-h") {
      args.help = true;
    } else if (arg === "--base") {
      args.base = argv[++index];
    } else if (arg === "--head") {
      args.head = argv[++index];
    } else if (arg === "--repo-dir") {
      args.repoDir = argv[++index];
    } else if (arg === "--post") {
      args.post = argv[++index];
    } else if (arg === "--pr") {
      args.pr = argv[++index];
    } else if (arg === "--out") {
      args.out = argv[++index];
    } else {
      throw new Error(`unknown argument: ${arg}`);
    }
  }

  return args;
}

// When spawnSync runs with shell:true it concatenates command+args into one
// line WITHOUT quoting, so any argument containing whitespace is word-split by
// the shell (e.g. a multi-word instruction loses everything after the
// first word). Quote such args so they survive as a single token. Only applied
// on the shell path; the direct-exec path passes argv through untouched.
function quoteForShell(arg) {
  if (!/[\s"]/.test(arg)) {
    return arg;
  }
  if (process.platform === "win32") {
    return `"${arg.replace(/"/g, '""')}"`;
  }
  return `'${arg.replace(/'/g, `'\\''`)}'`;
}

function run(command, args = [], options = {}) {
  const useShell = options.shell ?? false;
  const spawnCommand = useShell ? quoteForShell(command) : command;
  const spawnArgs = useShell ? args.map(quoteForShell) : args;
  const result = spawnSync(spawnCommand, spawnArgs, {
    cwd: options.cwd ?? process.cwd(),
    encoding: "utf8",
    shell: useShell,
    windowsHide: true,
  });

  return {
    status: result.status ?? 1,
    stdout: result.stdout ?? "",
    stderr: result.stderr ?? "",
    output: `${result.stdout ?? ""}${result.stderr ?? ""}`,
    error: result.error,
  };
}

function commandShell(command) {
  return process.platform === "win32" && /\.(bat|cmd)$/i.test(command);
}

function findCommand(command) {
  const probe = process.platform === "win32"
    ? run("where.exe", [command])
    : run("sh", ["-c", `command -v ${command}`]);

  if (probe.status !== 0) {
    return null;
  }

  const found = probe.stdout.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);

  // On Windows, where.exe can return both an extensionless POSIX shim (a bash
  // script, first in PATH) and a real .cmd/.exe/.bat launcher. spawnSync cannot
  // run the extensionless shim directly, so prefer a Windows-executable
  // extension when one is present. Elsewhere the first match is correct.
  if (process.platform === "win32") {
    const executable = found.find((p) => /\.(cmd|bat|exe|com)$/i.test(p));
    if (executable) {
      return executable;
    }
  }

  return found[0] ?? null;
}

function ensureParentDir(filePath) {
  mkdirSync(path.dirname(filePath), { recursive: true });
}

function resolveOutputPath(filePath) {
  return path.resolve(process.cwd(), filePath);
}

function defaultBaseRef(repoDir) {
  const origin = run("git", ["rev-parse", "--verify", "origin/master^{commit}"], { cwd: repoDir });
  return origin.status === 0 ? "origin/master" : "master";
}

function gitDiff(repoDir, base, head) {
  return run("git", [
    "diff",
    "--unified=3",
    "--no-ext-diff",
    "--diff-filter=ACMRT",
    `${base}...${head}`,
    "--",
    "*.php",
  ], { cwd: repoDir });
}

function changedPhpFiles(repoDir, base, head) {
  const result = run("git", [
    "diff",
    "--name-only",
    "-z",
    "--no-ext-diff",
    "--diff-filter=ACMRT",
    `${base}...${head}`,
    "--",
    "*.php",
  ], { cwd: repoDir });

  if (result.status !== 0) {
    return [];
  }

  return result.stdout
    .split("\0")
    .filter(Boolean)
    .filter((file) => existsSync(path.join(repoDir, file)));
}

function parseNewFile(line) {
  if (!line.startsWith("+++ ")) {
    return null;
  }
  const name = line.slice(4).trim();
  if (name === "/dev/null") {
    return null;
  }
  return name.startsWith("b/") ? name.slice(2) : name;
}

function parseHunkStart(line) {
  const match = /^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/.exec(line);
  return match ? Number.parseInt(match[1], 10) : null;
}

function reviewDiff(diffText) {
  const findings = [];
  let currentFile = null;
  let newLine = 0;

  for (const line of diffText.split(/\r?\n/)) {
    const parsedFile = parseNewFile(line);
    if (parsedFile) {
      currentFile = parsedFile;
      continue;
    }

    const hunkStart = parseHunkStart(line);
    if (hunkStart !== null) {
      newLine = hunkStart;
      continue;
    }

    if (!currentFile || line.startsWith("--- ")) {
      continue;
    }

    if (line.startsWith("+") && !line.startsWith("+++ ")) {
      const added = line.slice(1);
      for (const heuristic of phpHeuristics) {
        if (heuristic.regex.test(added)) {
          findings.push(`${currentFile}:${newLine} ${heuristic.severity}(advisory) ${heuristic.message}`);
        }
      }
      newLine += 1;
      continue;
    }

    if (line.startsWith("-")) {
      continue;
    }

    if (line.startsWith(" ") || line === "") {
      newLine += 1;
    }
  }

  return findings;
}

function phpLintFindings(repoDir, files) {
  const php = findCommand("php");
  if (!php) {
    return [];
  }

  const findings = [];
  for (const file of files) {
    const result = run(php, ["-l", file], {
      cwd: repoDir,
      shell: commandShell(php),
    });
    if (result.status === 0) {
      continue;
    }

    const lineMatch = /on line (\d+)/i.exec(result.output);
    const line = lineMatch ? lineMatch[1] : "1";
    const message = result.output.replace(/\s+/g, " ").trim() || "php -l failed";
    findings.push(`${file}:${line} high(advisory) php -l failed: ${message}`);
  }
  return findings;
}

function buildReviewPrompt(diffText) {
  return `You are reviewing a legacy PHP 5-era codebase now running on PHP 8.3.

Review ONLY the PHP diff below for:
- undefined variables
- PHP5-to-PHP8 migration hazards
- obvious duplication
- misleading naming

Output one finding per line in exactly this shape:
FILE:LINE severity(advisory) message

Rules:
- Max 15 findings.
- No style nits.
- Do not review unchanged code except when needed to understand a changed line.
- If there are no findings, output exactly: No AI findings.

Diff:
${diffText}
`;
}

function runAiReview(diffText) {
  // SALDI-INTEGRATION-AUDIT 4.2 + e2e rehearsal gap G1 (2026-07-16): the AI
  // pass must NEVER auto-fire on PATH detection. Sending client PHP diffs to a
  // model provider is DPA-gated, and direct Codex CLI dispatch is prohibited
  // outright (operator ban), so this layer is fail-closed OFF until the
  // operator sets ADVISORY_AI_CMD to a sanctioned reviewer command -- the
  // claude-gpt proxy lane is the intended runner. Mirrors the TEST_AUTHOR_CMD
  // contract in bin/test-author.mjs: the command receives a short instruction
  // on stdin, reads the full prompt from the file the instruction names, and
  // prints its findings to stdout. The prompt is passed via file + stdin,
  // never interpolated into a shell string, so untrusted diff text cannot
  // inject shell syntax.
  const command = process.env.ADVISORY_AI_CMD;
  if (!command || !command.trim()) {
    return [
      "AI review disabled: ADVISORY_AI_CMD is not set. Mechanical findings only.",
      "The operator may enable the AI layer only after the client DPA is signed (SALDI-INTEGRATION-AUDIT 4.2), by setting ADVISORY_AI_CMD to a sanctioned reviewer command.",
    ];
  }

  const runDir = path.resolve(process.cwd(), ".factory-run");
  mkdirSync(runDir, { recursive: true });
  const suffix = `${process.pid}-${Date.now()}`;
  const promptFile = path.join(runDir, `advisory-review-prompt-${suffix}.txt`);
  writeFileSync(promptFile, buildReviewPrompt(diffText), "utf8");

  const instruction = `Read the review prompt from ${promptFile} and follow it exactly. Print only the findings to stdout.`;
  const result = spawnSync(command, [], {
    input: instruction,
    cwd: process.cwd(),
    encoding: "utf8",
    shell: true,
    windowsHide: true,
  });

  if ((result.status ?? 1) !== 0) {
    const note = `${result.stdout ?? ""}${result.stderr ?? ""}`.replace(/\s+/g, " ").trim();
    return [`AI review command failed; continuing with mechanical findings only.${note ? ` ${note}` : ""}`];
  }

  const lines = (result.stdout ?? "").split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  return lines.length ? lines.slice(0, 15) : ["No AI findings."];
}

function markdownList(items, emptyText) {
  if (!items.length) {
    return emptyText;
  }
  return items.map((item) => `- ${item}`).join(os.EOL);
}

function renderReport(options) {
  return [
    "## Advisory review",
    "",
    `Repository: ${options.repoDir}`,
    `Range: ${options.base}...${options.head}`,
    "",
    "### Mechanical findings",
    "",
    markdownList(options.mechanicalFindings, "No mechanical findings."),
    "",
    "### AI findings",
    "",
    markdownList(options.aiFindings, "No AI findings."),
    "",
    "This review is ADVISORY ONLY. It never blocks a merge.",
    "",
  ].join(os.EOL);
}

function postReport(ownerRepo, pr, reportPath) {
  const gh = findCommand("gh");
  if (!gh) {
    console.error("advisory-review: gh not found; skipping PR comment post.");
    return;
  }

  const result = run(gh, [
    "api",
    `repos/${ownerRepo}/issues/${pr}/comments`,
    // -F (typed field) reads @file contents; -f would post the literal
    // string "@<path>" as the comment body.
    "-F",
    `body=@${reportPath}`,
  ], {
    shell: commandShell(gh),
  });

  if (result.status !== 0) {
    const note = result.output.replace(/\s+/g, " ").trim();
    console.error(`advisory-review: gh post failed; report remains advisory and local.${note ? ` ${note}` : ""}`);
  }
}

function main() {
  let args;
  try {
    args = parseArgs(process.argv.slice(2));
  } catch (error) {
    console.error(`advisory-review: ${error.message}`);
    console.log(usage);
    process.exit(0);
  }

  if (args.help) {
    console.log(usage);
    process.exit(0);
  }

  const repoDir = path.resolve(args.repoDir);
  const base = args.base ?? defaultBaseRef(repoDir);
  const head = args.head;
  const outPath = resolveOutputPath(args.out);
  const diff = gitDiff(repoDir, base, head);
  const mechanicalFindings = [];
  let aiFindings = [];

  if (diff.status !== 0) {
    const note = diff.output.replace(/\s+/g, " ").trim() || "git diff failed";
    mechanicalFindings.push(`git:1 high(advisory) could not compute PHP diff for ${base}...${head}: ${note}`);
    aiFindings = ["AI review skipped because the PHP diff could not be computed."];
  } else if (diff.stdout.trim()) {
    const files = changedPhpFiles(repoDir, base, head);
    mechanicalFindings.push(...phpLintFindings(repoDir, files));
    mechanicalFindings.push(...reviewDiff(diff.stdout));
    aiFindings = runAiReview(diff.stdout);
  } else {
    aiFindings = ["No PHP diff found; AI review skipped."];
  }

  const report = renderReport({
    repoDir,
    base,
    head,
    mechanicalFindings,
    aiFindings,
  });

  ensureParentDir(outPath);
  writeFileSync(outPath, report, "utf8");
  console.log(report);

  if (args.post && args.pr) {
    postReport(args.post, args.pr, outPath);
  }

  process.exit(0);
}

export {
  phpHeuristics as heuristics,
  phpHeuristics,
  reviewDiff,
};

if (process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1])) {
  main();
}

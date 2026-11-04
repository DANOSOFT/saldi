// tools/guardrails/checks/js-syntax.mjs - a change does not break the JavaScript a page runs.
//
// Past break: #800 (6fad671b) dropped one ")" inside the `print <<<JS` block of
// includes/docsIncludes/docPool.php. The SyntaxError stopped the whole 157 KB
// pool script, so every tenant's document pool hung on "Indlæser filer" until
// 776ea234. Nothing in CI ran that JavaScript.
//
// Checked: changed .js/.mjs/.cjs files, plus the JavaScript inside changed PHP
// files - heredocs labelled JS/SCRIPT or holding <script>, and <script> blocks
// in the HTML around the PHP tags. PHP values inside it ({$x}, $x, <?= ?>) are
// replaced by 0 before parsing. That replacement cannot be exact for every
// legacy page, so for a PHP file the check only fails when the change adds a
// block that does not parse.
//
// History:
// 20261027 CL/LH Created.

import vm from "node:vm";
import { spawnSync } from "node:child_process";
import { mkdtempSync, writeFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const HEREDOC = /<<<[ \t]*(["']?)([A-Za-z_][A-Za-z0-9_]*)\1\r?\n([\s\S]*?)\r?\n[ \t]*\2\b/g;
const SCRIPT = /<script\b([^>]*)>([\s\S]*?)<\/script>/gi;
const PHP_ESCAPES = { n: "\n", t: "\t", r: "\r", v: "\v", f: "\f", e: "\x1b", "\\": "\\", $: "$" };

/** What PHP prints for a heredoc body: escapes applied, interpolated values replaced by 0. */
function heredocOutput(body) {
  let out = "";
  for (let i = 0; i < body.length; i++) {
    const ch = body[i];
    if (ch === "\\" && i + 1 < body.length && body[i + 1] in PHP_ESCAPES) {
      out += PHP_ESCAPES[body[++i]];
    } else if (ch === "{" && body[i + 1] === "$") {
      let depth = 0;
      let j = i;
      for (; j < body.length; j++) {
        if (body[j] === "{") depth++;
        else if (body[j] === "}" && --depth === 0) break;
      }
      out += "0" + "\n".repeat((body.slice(i, j).match(/\n/g) ?? []).length);
      i = j;
    } else if (ch === "$" && /[A-Za-z_]/.test(body[i + 1] ?? "")) {
      const variable = /^\$[A-Za-z_][A-Za-z0-9_]*(?:\[[^\]\n]*\]|->[A-Za-z_][A-Za-z0-9_]*)?/.exec(body.slice(i))[0];
      out += "0";
      i += variable.length - 1;
    } else {
      out += ch;
    }
  }
  return out;
}

/** The page around the PHP tags, with each PHP block replaced by 0 (if it prints) or nothing. */
function htmlView(source) {
  let out = "";
  let i = 0;
  while (i < source.length) {
    const open = source.indexOf("<?", i);
    if (open === -1) {
      out += source.slice(i);
      break;
    }
    out += source.slice(i, open);
    const close = source.indexOf("?>", open + 2);
    const block = source.slice(open, close === -1 ? source.length : close + 2);
    const prints = /^<\?(=|php\s+(echo|print)\b|\s*(echo|print)\b)/i.test(block);
    out += (prints ? "0" : "") + "\n".repeat((block.match(/\n/g) ?? []).length);
    i = open + block.length;
  }
  return out;
}

function lineAt(text, index) {
  let line = 1;
  for (let i = 0; i < index; i++) if (text.charCodeAt(i) === 10) line++;
  return line;
}

function scriptBlocks(text, offsetLine) {
  const blocks = [];
  for (const match of text.matchAll(SCRIPT)) {
    const attributes = match[1];
    if (/\bsrc\s*=/i.test(attributes)) continue;
    const type = /\btype\s*=\s*["']?([^"'\s>]+)/i.exec(attributes)?.[1]?.toLowerCase() ?? "";
    if (type && !["text/javascript", "application/javascript", "module"].includes(type)) continue;
    const start = match.index + match[0].indexOf(">") + 1;
    blocks.push({ line: offsetLine + lineAt(text, start) - 1, code: match[2], module: type === "module" });
  }
  return blocks;
}

/** Every piece of JavaScript in a PHP file, with the line it starts on. */
export function phpJavaScript(source) {
  const blocks = [];
  for (const match of source.matchAll(HEREDOC)) {
    const [, quote, label, body] = match;
    const output = quote === "'" ? body : heredocOutput(body);
    const line = lineAt(source, match.index) + 1;
    if (/^(JS|JAVASCRIPT|SCRIPT|[A-Z]*JS|[A-Z]*SCRIPT)$/.test(label.toUpperCase()) && !/<script\b/i.test(output)) {
      blocks.push({ line, code: output, module: false });
    } else {
      blocks.push(...scriptBlocks(output, line));
    }
  }
  blocks.push(...scriptBlocks(htmlView(source.replace(HEREDOC, (m) => "\n".repeat((m.match(/\n/g) ?? []).length))), 1));
  return blocks;
}

/** null when the code parses, otherwise {line, message} with line relative to the code. */
export function syntaxError(code, module = false) {
  if (!module) {
    try {
      new vm.Script(code, { filename: "block.js" });
      return null;
    } catch (error) {
      if (error?.name !== "SyntaxError") return null;
      if (!/\b(import|export)\b/.test(error.message)) {
        return { line: Number(/block\.js:(\d+)/.exec(error.stack ?? "")?.[1] ?? 1), message: error.message };
      }
    }
  }
  const dir = mkdtempSync(join(tmpdir(), "guardrails-js-"));
  try {
    writeFileSync(join(dir, "block.mjs"), code);
    const result = spawnSync(process.execPath, ["--check", join(dir, "block.mjs")], { encoding: "utf8" });
    if (result.status === 0) return null;
    const line = Number(/block\.mjs:(\d+)/.exec(result.stderr)?.[1] ?? 1);
    const message = /SyntaxError: .*/.exec(result.stderr)?.[0] ?? result.stderr.trim().split("\n")[0];
    return { line, message: message.replace(/^SyntaxError: /, "") };
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

function failingBlocks(source) {
  return phpJavaScript(source)
    .map((block) => ({ ...block, error: syntaxError(block.code, block.module) }))
    .filter((block) => block.error);
}

export default {
  id: "js-syntax",
  summary: "changed JS files, and JS inside changed PHP files, still parse",
  run(ctx) {
    const findings = [];
    for (const file of ctx.files) {
      if (file.status === "D" || file.binary || ctx.isVendored(file.path)) continue;
      const head = ctx.readHead(file.path);
      if (head === null) continue;

      if (/\.(m?js|cjs)$/.test(file.path) && !file.path.endsWith(".min.js")) {
        const error = syntaxError(head, file.path.endsWith(".mjs") || /^\s*(import|export)\s/m.test(head));
        if (!error) continue;
        const base = file.status === "A" ? null : ctx.readBase(file.path);
        if (base !== null && syntaxError(base, file.path.endsWith(".mjs") || /^\s*(import|export)\s/m.test(base))) continue;
        findings.push({ path: file.path, line: error.line, message: `JavaScript does not parse: ${error.message}` });
      }

      if (file.path.endsWith(".php")) {
        const headFailing = failingBlocks(head);
        if (!headFailing.length) continue;
        const baseFailing = file.status === "A" ? [] : failingBlocks(ctx.readBase(file.path) ?? "");
        if (headFailing.length <= baseFailing.length) continue;
        const known = new Set(baseFailing.map((block) => block.code.replace(/\s+/g, " ")));
        const added = headFailing.filter((block) => !known.has(block.code.replace(/\s+/g, " ")));
        for (const block of added.length ? added : headFailing) {
          findings.push({
            path: file.path,
            line: block.line + block.error.line - 1,
            message: `the JavaScript block starting on line ${block.line} does not parse: ${block.error.message}. A syntax error stops the whole block in the browser.`,
          });
        }
      }
    }
    return findings;
  },
};

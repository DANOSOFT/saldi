// tools/guardrails/checks/pr-recording.mjs - a PR that changes what users see shows it working.
//
// Rule (Lui, 18 Nov 2026), for people's PRs and the kitchen's alike: a PR with a
// UI change has a screen recording of the change working in its description.
// Drag the video into the description on GitHub; it becomes a line of its own,
// https://github.com/user-attachments/assets/<id>. A link to an .mp4, .mov or
// .webm file counts too (the kitchen's bot cannot attach, so it links). When the
// check takes a change for UI that is not, write one line instead:
//   Ingen UI-ændring: <why, in a few words>
// Whether the recording shows enough stays the reviewer's call; this only checks
// that it is there.
//
// A UI change, as read from the diff:
//   - a stylesheet, script, template or markup file (UI_EXTENSIONS), outside tests,
//     tools, docs and vendored code;
//   - a translation seed file (importfiler/*tekster*): its texts are on screen;
//   - a PHP page or include whose added lines write markup: a tag, or a class=,
//     style=, href= or on...= attribute. A PHP change that only moves data is not one.
//
// Like pr-body it judges the description, so it runs only with --pr-body, and
// only when --base names the change to read.
//
// History:
// 20261118 CL/LH Created.
// 20261126 CDX/NTR Accept explicit reviewer waivers using the shared dated exception format.

import { findException } from "../lib/exceptions.mjs";

const UI_EXTENSIONS = /\.(css|scss|less|js|mjs|jsx|ts|tsx|vue|html?|tpl|twig|svg)$/i;
const NOT_UI = /^(tests?|e2e|tools|doc|docs|\.github|vendor|includes\/vendor|node_modules|tiny_mce)\//i;
const TEKSTER = /^importfiler\/[^/]*tekster[^/]*\.(csv|tsv)$/i;
const MARKUP = /<\/?[a-z][a-z0-9-]*(\s|>|\/>)|\s(class|style|href|on[a-z]+)=\\?["']/i;

/** The changed files that change what a user sees, with the reason for each. */
export function uiChanges(files) {
  const result = [];
  for (const file of files) {
    if (file.status === "D" || NOT_UI.test(file.path)) continue;
    if (TEKSTER.test(file.path)) result.push({ path: file.path, why: "texts on screen" });
    else if (UI_EXTENSIONS.test(file.path)) result.push({ path: file.path, why: "stylesheet, script or markup" });
    else if (/\.php$/i.test(file.path)) {
      const line = [...(file.added ?? new Map()).entries()].find(([, text]) => MARKUP.test(text));
      if (line) result.push({ path: file.path, line: line[0], why: "writes markup" });
    }
  }
  return result;
}

/** Whether the description holds a screen recording, or says why none is needed. */
export function recordingState(body, today = new Date().toISOString().slice(0, 10)) {
  const text = (body ?? "").replace(/<!--[\s\S]*?(?:-->|$)/g, "");
  if (/^\s*Ingen UI-ændring\s*:\s*\S.{9,}$/im.test(text)) return "opted-out";
  const lines = text.split(/\r?\n/);
  if (lines.some((_, index) => findException(lines, index + 1, "pr-recording", today)?.allowed)) return "waived";
  // A video dropped into the description is a bare attachment URL on its own line;
  // an image is the same URL inside ![...](...) or <img src=...>.
  if (/^\s*<?https:\/\/github\.com\/user-attachments\/assets\/[0-9a-f-]{20,}>?\s*$/im.test(text)) return "recorded";
  if (/<video\b[^>]*\bsrc=/i.test(text)) return "recorded";
  if (/https?:\/\/\S+\.(mp4|mov|webm)(\?\S*)?(?=[\s)>\]"']|$)/im.test(text)) return "recorded";
  return "missing";
}

export default {
  id: "pr-recording",
  summary: "a PR with a UI change has a screen recording of it working in its description",
  description: true,
  run(body, files, { today } = {}) {
    const ui = uiChanges(files ?? []);
    if (ui.length === 0 || recordingState(body, today) !== "missing") return [];
    const shown = ui.slice(0, 5).map((change) => `${change.path}${change.line ? `:${change.line}` : ""} (${change.why})`);
    const more = ui.length > shown.length ? ` and ${ui.length - shown.length} more` : "";
    return [{
      message:
        `this PR changes what users see (${shown.join(", ")}${more}), so its description needs a screen recording ` +
        'of the change working: drag the video into "## Screen recording". If it is not a UI change, write one line ' +
        '"Ingen UI-ændring: <why>" instead.',
    }];
  },
};

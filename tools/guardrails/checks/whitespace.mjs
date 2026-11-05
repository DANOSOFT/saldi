// tools/guardrails/checks/whitespace.mjs - no incidental whitespace or file-mode churn.
//
// doc/ai/feedback_whitespace_and_indentation.md: re-indenting the block you edit
// is fine (that needs judgment, so it is not checked), but a change must not
// touch lines only to change their line ending or trailing whitespace, must not
// add new trailing whitespace, and must not flip file modes. Past churn:
// 17 of 140 commits in October changed lines only at the end; reviews on #510,
// #512, #529, #549; c6c23885 changed the mode of hundreds of files.
//
// History:
// 20261027 CL/LH Created.

const CODE = /\.(php|m?js|cjs|css|html?|sh|ya?ml|json)$/;

export default {
  id: "whitespace",
  summary: "no line-ending-only edits, no new trailing whitespace, no file mode changes",
  run(ctx) {
    const findings = [];
    for (const file of ctx.files) {
      if (ctx.isVendored(file.path)) continue;
      if (file.oldMode && file.newMode && file.oldMode !== file.newMode) {
        findings.push({ path: file.path, message: `file mode changed ${file.oldMode} -> ${file.newMode}; restore it (git update-index --chmod=-x or chmod).` });
      }
      if (file.binary || !CODE.test(file.path)) continue;
      for (const hunk of file.hunks) {
        const flagged = new Set();
        hunk.removed.forEach((before, index) => {
          const after = hunk.added[index];
          if (after && after.text !== before && after.text.trimEnd() === before.trimEnd()) {
            flagged.add(after.line);
            findings.push({ path: file.path, line: after.line, message: "only the line ending or trailing whitespace changed; put the line back as it was." });
          }
        });
        for (const { line, text } of hunk.added) {
          if (!flagged.has(line) && /[ \t]+\r?$/.test(text)) {
            findings.push({ path: file.path, line, message: "trailing whitespace on an added line." });
          }
        }
      }
    }
    return findings;
  },
};

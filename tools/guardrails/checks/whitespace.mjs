// tools/guardrails/checks/whitespace.mjs - no incidental whitespace or file-mode churn.
//
// doc/ai/feedback_whitespace_and_indentation.md: re-indenting the block you edit
// is fine (that needs judgment, so it is not checked), but a change must not
// touch nonblank lines only to change their line ending or trailing whitespace,
// add whitespace after content, or flip file modes. Blank separator lines may
// contain indentation; standalone rewrites of existing blank lines are churn. Past churn:
// 17 of 140 commits in October changed lines only at the end; reviews on #510,
// #512, #529, #549; c6c23885 changed the mode of hundreds of files.
//
// History:
// 20261027 CL/LH Created.
// 20261109 CDX/NTR Allow indented blank separators, including rapportfunc.php lines 646 and 662 from a6bb95b17.
// 20261109 CDX/NTR Keep catching standalone blank-line indentation rewrites after PR #1060 review.

const CODE = /\.(php|m?js|cjs|css|html?|sh|ya?ml|json)$/;
const BLANK_LINE = /^[ \t]*\r?$/;

export default {
  id: "whitespace",
  summary: "blank separators allowed; no incidental whitespace-only rewrites, whitespace after content or file mode changes",
  run(ctx) {
    const findings = [];
    for (const file of ctx.files) {
      if (ctx.isVendored(file.path)) continue;
      if (file.oldMode && file.newMode && file.oldMode !== file.newMode) {
        findings.push({ path: file.path, message: `file mode changed ${file.oldMode} -> ${file.newMode}; restore it (git update-index --chmod=-x or chmod).` });
      }
      if (file.binary || !CODE.test(file.path)) continue;
      for (const hunk of file.hunks) {
        const whitespaceOnly = hunk.removed.length === hunk.added.length
          && hunk.removed.every((before, index) => hunk.added[index].text.trimEnd() === before.trimEnd());
        const flagged = new Set();
        hunk.removed.forEach((before, index) => {
          const after = hunk.added[index];
          if (after && (whitespaceOnly || !BLANK_LINE.test(after.text)) && after.text !== before && after.text.trimEnd() === before.trimEnd()) {
            flagged.add(after.line);
            const message = BLANK_LINE.test(after.text)
              ? "only whitespace on an existing blank line changed; leave unrelated blank-line indentation as it was."
              : "only the line ending or trailing whitespace changed; put the line back as it was.";
            findings.push({ path: file.path, line: after.line, message });
          }
        });
        for (const { line, text } of hunk.added) {
          if (!flagged.has(line) && !BLANK_LINE.test(text) && /[ \t]+\r?$/.test(text)) {
            findings.push({ path: file.path, line, message: "trailing whitespace on an added line." });
          }
        }
      }
    }
    return findings;
  },
};

// tools/guardrails/checks/pr-body.mjs - a PR says what it changes and how it was verified.
//
// About 25 PRs since July were sent back because the description was the
// unfilled template or had no verification (#466, #467, #476, #477, #516,
// #745-#756, #840, #884, #885, #898). Whether the verification is good enough
// stays the reviewer's call; this only checks that it is there.
//
// History:
// 20261027 CL/LH Created.

const PLACEHOLDERS = [/Provide a brief explanation of the changes you have made/gi];

function sections(body) {
  const result = [];
  let current = null;
  for (const line of body.replace(/<!--[\s\S]*?-->/g, "").split(/\r?\n/)) {
    const heading = /^#{1,6}\s+(.*?)\s*#*\s*$/.exec(line);
    if (heading) {
      current = { title: heading[1], text: "" };
      result.push(current);
    } else if (current) {
      current.text += `${line}\n`;
    }
  }
  return result;
}

function content(text) {
  let rest = text;
  for (const placeholder of PLACEHOLDERS) rest = rest.replace(placeholder, "");
  return rest.replace(/^\s*-\s*\[[ xX]\].*$/gm, "").replace(/\s+/g, " ").trim();
}

export default {
  id: "pr-body",
  summary: "the PR description is filled in: what changed, and how it was verified",
  run(body) {
    const findings = [];
    const all = sections(body ?? "");
    const own = all.filter((section) => !/coderabbit/i.test(section.title));
    const what = own.find((section) => /what|summary|why|change|description|baggrund|ændring/i.test(section.title));
    const verified = own.find((section) => /verif|test|how i|afprøv|tjek/i.test(section.title));
    if (!what || content(what.text).length < 20) {
      findings.push({ message: 'fill in "## What are the changes about?": what the change does and why, in a sentence or two.' });
    }
    if (!verified || content(verified.text).length < 40) {
      findings.push({
        message:
          'fill in "## How I verified this": the page and steps you tried in a local tenant and what you saw, ' +
          "and the tests you ran. Fixtures alone are not enough for a change users see.",
      });
    }
    return findings;
  },
};

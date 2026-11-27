// tools/guardrails/checks/test-discovery.mjs - a new test is one CI actually runs.
//
// CI runs PHPUnit with phpunit.xml discovery and Playwright with
// playwright.config.mjs. 57 test files added since June sit where neither looks
// (tests/test_*.php, tests/integration/, tests/includes/grid_sort.php), so they
// pass by never running; tests/test_cash_count_snapshot.php fails its own
// assertion and nobody noticed. Reviews: #400, #551, #597.
//
// Both configs are read from the change itself, so they stay the one source of
// truth. Helpers go in a support/ directory inside a suite.
//
// History:
// 20261027 CL/LH Created.

function phpunitSuites(xml) {
  const suites = [];
  for (const match of (xml ?? "").matchAll(/<directory\b([^>]*)>([^<]+)<\/directory>/g)) {
    const suffix = /suffix="([^"]+)"/.exec(match[1])?.[1] ?? "Test.php";
    suites.push({ dir: match[2].trim().replace(/\/$/, "") + "/", suffix });
  }
  const files = [...(xml ?? "").matchAll(/<file>([^<]+)<\/file>/g)].map((match) => match[1].trim());
  return { suites, files };
}

export default {
  id: "test-discovery",
  summary: "new test files are discovered by phpunit.xml or the Playwright config",
  run(ctx) {
    const added = ctx.files.filter((file) => file.status === "A");
    if (!added.length) return [];
    const { suites, files } = phpunitSuites(ctx.readHead("phpunit.xml"));
    const testDir = /testDir:\s*["']([^"']+)["']/.exec(ctx.readHead("playwright.config.mjs") ?? "")?.[1] ?? "e2e";
    const suiteList = suites.map((suite) => `${suite.dir}**/*${suite.suffix}`).join(", ");
    const findings = [];

    for (const { path } of added) {
      if (path.endsWith(".php") && path.startsWith("tests/")) {
        const suite = suites.find((s) => path.startsWith(s.dir));
        if (files.includes(path) || (suite && path.endsWith(suite.suffix))) continue;
        if (suite && /\/support\//.test(path.slice(suite.dir.length - 1))) continue;
        findings.push({
          path,
          line: 1,
          message: suite
            ? `PHPUnit never runs this file: tests in ${suite.dir} must end in ${suite.suffix}; helpers go in a support/ directory.`
            : `PHPUnit never runs files here. Put the test in one of the suites phpunit.xml discovers (${suiteList}).`,
        });
      }
      if (/\.spec\.(m?js|ts)$/.test(path) && !path.startsWith(`${testDir.replace(/\/$/, "")}/`)) {
        findings.push({ path, line: 1, message: `CI's Playwright run only looks in ${testDir}/ (playwright.config.mjs); this spec never runs.` });
      }
    }
    return findings;
  },
};

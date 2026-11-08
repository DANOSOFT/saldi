// tools/guardrails/lib/exceptions.mjs - the one way to let a finding through.
//
// An exception sits on the offending line (or the line directly above it, for
// lines that cannot carry a comment, such as the middle of a SQL string) and
// names the check, an expiry date, who approved it and why:
//
//   // guardrails-allow migrations until 2026-12-31 by NTR: runs before the shop sync block
//
// After the expiry date the finding comes back. In code the approver's initials
// are checked by the reviewer, not by this code. The PR description uses the
// same line in its Merge Danger section, where checks/pr-body.mjs also checks
// the description's edit history: the line counts only when a reviewer added it.
//
// History:
// 20261027 CL/LH Created.
// 20261103 CL/LH parseException() reads one line, so the PR description shares the parser.

const EXCEPTION = /guardrails-allow\s+([a-z0-9-]+)\s+until\s+(\d{4}-\d{2}-\d{2})\s+by\s+([A-Z]{2,4})\s*:\s*(\S.*)/;

/**
 * Read one line as an exception for `check`.
 *
 * @returns {null | {kind: "malformed"} | {kind: "other"} | {kind: "expired", until: string}
 *   | {kind: "allowed", until: string, by: string, reason: string}}  null when the line has no exception
 */
export function parseException(text, check, today) {
  if (!text || !text.includes("guardrails-allow")) return null;
  const match = EXCEPTION.exec(text);
  if (!match) return { kind: "malformed" };
  const [, id, until, by, reason] = match;
  if (id !== check) return { kind: "other" };
  if (until < today) return { kind: "expired", until };
  return { kind: "allowed", until, by, reason };
}

/**
 * Look for an exception for `check` on `line` or the line above it.
 *
 * @param {string[]} lines  the file as the change leaves it; lines[n - 1] is line n
 * @returns {null | {allowed: true, reason: string} | {allowed: false, problem: string}}
 */
export function findException(lines, line, check, today) {
  for (const candidate of [line, line - 1]) {
    const exception = parseException(lines[candidate - 1], check, today);
    if (!exception || exception.kind === "other") continue;
    if (exception.kind === "malformed") {
      return {
        allowed: false,
        problem: `line ${candidate} has a malformed exception; write "guardrails-allow <check> until YYYY-MM-DD by <INITIALS>: <reason>"`,
      };
    }
    if (exception.kind === "expired") return { allowed: false, problem: `the exception on line ${candidate} expired on ${exception.until}` };
    return { allowed: true, reason: exception.reason };
  }
  return null;
}

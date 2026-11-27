// tools/guardrails/lib/exceptions.mjs - the one way to let a finding through.
//
// An exception sits on the offending line (or the line directly above it, for
// lines that cannot carry a comment, such as the middle of a SQL string) and
// names the check, an expiry date, who approved it and why:
//
//   // guardrails-allow migrations until 2026-12-31 by NTR: runs before the shop sync block
//
// After the expiry date the finding comes back. The approver's initials are
// checked by the reviewer, not by this code.
//
// History:
// 20261027 CL/LH Created.

const EXCEPTION = /guardrails-allow\s+([a-z0-9-]+)\s+until\s+(\d{4}-\d{2}-\d{2})\s+by\s+([A-Z]{2,4})\s*:\s*(\S.*)/;

/**
 * Look for an exception for `check` on `line` or the line above it.
 *
 * @param {string[]} lines  the file as the change leaves it; lines[n - 1] is line n
 * @returns {null | {allowed: true, reason: string} | {allowed: false, problem: string}}
 */
export function findException(lines, line, check, today) {
  for (const candidate of [line, line - 1]) {
    const text = lines[candidate - 1];
    if (!text || !text.includes("guardrails-allow")) continue;
    const match = EXCEPTION.exec(text);
    if (!match) {
      return {
        allowed: false,
        problem: `line ${candidate} has a malformed exception; write "guardrails-allow <check> until YYYY-MM-DD by <INITIALS>: <reason>"`,
      };
    }
    const [, id, until, , reason] = match;
    if (id !== check) continue;
    if (until < today) return { allowed: false, problem: `the exception on line ${candidate} expired on ${until}` };
    return { allowed: true, reason };
  }
  return null;
}

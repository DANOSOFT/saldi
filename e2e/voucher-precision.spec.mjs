// e2e/voucher-precision.spec.mjs - can a sub-øre amount be typed into a
// voucher through the UI?
//
// tests/characterization/finans/DoubleEntryIntegrityCharacterizationTest
// shows that a voucher whose lines cancel out at three decimals posts an
// unbalanced entry to the general ledger (doc/TESTING_FINDINGS.md P1-1). That
// test drives the posting engine directly, which leaves one question open and
// it is the one that decides the severity: can a person sitting in front of
// the cash journal actually get such an amount into a voucher, or does the
// form round it away first?
//
// This spec answers that from the browser. It does not assert which answer is
// correct - it pins whichever one the app gives, so the finding stays honest
// about its own reach.
//
// PREREQUISITES: see e2e/workflow.spec.mjs.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.
// 20261105 CDX/NTR Check saved and all blank journal rows keep the approval and voucher columns aligned.

import { test, expect } from "@playwright/test";
import { login, logout, captureDialogs, DEBIT_ACCOUNT, CREDIT_ACCOUNT } from "./support/app.mjs";

/**
 * Check the rendered positions of all voucher inputs, including supplementary unsaved rows.
 *
 * @param {import('@playwright/test').Page} page Open editable cash journal.
 * @returns {Promise<void>}
 */
async function expectAlignedJournalRows(page) {
  const approvalIndex = await page.locator('#kkGodkendtAlle').evaluate(input => input.closest('td').cellIndex);
  const rows = await page.locator('table.formnavi input[type="text"][name^="bila"]').evaluateAll(inputs =>
    inputs.map(input => {
      const cell = input.closest('td');
      const row = cell.parentElement;
      const approval = row.querySelectorAll('td.kk-col-godkendt');
      return { name: input.name, voucherIndex: cell.cellIndex, left: cell.getBoundingClientRect().left,
        approvalCells: approval.length, approvalIndex: approval[0]?.cellIndex };
    })
  );
  expect(rows.length).toBeGreaterThan(1);
  for (const row of rows) {
    expect(row.approvalCells, row.name).toBe(1);
    expect(row.approvalIndex, row.name).toBe(approvalIndex);
    expect(row.voucherIndex, row.name).toBe(approvalIndex + 1);
    expect(Math.abs(row.left - rows[0].left), `${row.name} must align with the first voucher input`).toBeLessThan(1);
  }
}

test("the cash journal rounds a sub-øre amount to two decimals on save", async ({ page }) => {
  captureDialogs(page);
  await login(page);

  await page.goto("/saldi/finans/kassekladde.php?returside=kladdeliste.php&tjek=-1");
  await expectAlignedJournalRows(page);

  const description = `E2E precision ${Date.now()}`;
  await page.locator('input[name="besk1"]').fill(description);
  await page.locator('input[name="debe1"]').fill(DEBIT_ACCOUNT);
  await page.locator('input[name="kred1"]').fill(CREDIT_ACCOUNT);
  // Danish decimal comma: one hundred kroner and half an øre.
  await page.locator('input[name="belo1"]').fill("100,005");
  await page.locator('[name="save"]').click();

  await expect(page.locator('input[name="besk1"]')).toHaveValue(description, { timeout: 15_000 });
  await expectAlignedJournalRows(page);

  const saved = await page.locator('input[name="belo1"]').inputValue();

  // Observed: the form hands back a two-decimal amount, so the third decimal
  // cannot be entered by hand here. That bounds P1-1 to the paths that write
  // `kassekladde.amount` directly - currency conversion and the bank/CSV
  // importers - rather than to anything a bookkeeper can type.
  //
  // If this ever comes back with three decimals, P1-1 is reachable straight
  // from the keyboard and its severity goes up accordingly.
  expect(
    saved,
    `the cash journal accepted a sub-ore amount (${saved}) - see doc/TESTING_FINDINGS.md P1-1`
  ).toMatch(/^\d{1,3}(\.\d{3})*,\d{2}$/);

  await logout(page);
});

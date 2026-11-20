// e2e/kassekladde-open-voucher.spec.mjs - SST-895: a voucher typed over several
// lines keeps one number until it balances.
//
// While the highest voucher in the cash journal does not balance, the blank line
// at the end continues it with the same number and date. Once it balances, the
// blank line gets the next number. The saved lines keep the number they were
// typed with.
//
// PREREQUISITES: see e2e/workflow.spec.mjs.
//
// History:
// 20261119 CL/SS SST-895: created.

import { test, expect } from "@playwright/test";
import { login, logout, captureDialogs, DEBIT_ACCOUNT, CREDIT_ACCOUNT } from "./support/app.mjs";

async function saveLine(page, row, { debit = "", credit = "", amount, text }) {
  await page.locator(`input[name="besk${row}"]`).fill(text);
  if (debit) await page.locator(`input[name="debe${row}"]`).fill(debit);
  if (credit) await page.locator(`input[name="kred${row}"]`).fill(credit);
  await page.locator(`input[name="belo${row}"]`).fill(amount);
  await page.locator('[name="save"]').click();
  await expect(page.locator(`input[name="besk${row}"]`)).toHaveValue(text, { timeout: 15_000 });
}

test("the blank line keeps the voucher number until the voucher balances", async ({ page }) => {
  captureDialogs(page);
  await login(page);

  await page.goto("/saldi/finans/kassekladde.php?returside=kladdeliste.php&tjek=-1");
  const voucher = Number(await page.locator('input[name="bila1"]').inputValue());
  expect(voucher, "a new journal proposes a voucher number").toBeGreaterThan(0);
  const stamp = Date.now();

  await saveLine(page, 1, { debit: DEBIT_ACCOUNT, amount: "1000,00", text: `E2E SST-895 debit ${stamp}` });
  await expect(page.locator('input[name="bila2"]'), "debit 1000 without its credit keeps the voucher open")
    .toHaveValue(String(voucher));
  await expect(page.locator('input[name="dato2"]')).toHaveValue(await page.locator('input[name="dato1"]').inputValue());

  await saveLine(page, 2, { credit: CREDIT_ACCOUNT, amount: "400,00", text: `E2E SST-895 credit 400 ${stamp}` });
  await expect(page.locator('input[name="bila3"]'), "600 is still missing").toHaveValue(String(voucher));

  await saveLine(page, 3, { credit: CREDIT_ACCOUNT, amount: "600,00", text: `E2E SST-895 credit 600 ${stamp}` });
  await expect(page.locator('input[name="bila4"]'), "the voucher balances, so the next line gets a new number")
    .toHaveValue(String(voucher + 1));

  for (const row of [1, 2, 3]) {
    await expect(page.locator(`input[name="bila${row}"]`), `saved line ${row} keeps the voucher number`)
      .toHaveValue(String(voucher));
  }
  await expect(page.locator('input[name="besk4"]'), "the voucher text is not copied to the blank line").toHaveValue("");

  await logout(page);
});

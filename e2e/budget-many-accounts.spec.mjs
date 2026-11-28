// e2e/budget-many-accounts.spec.mjs - Budget's Gem saves on a chart of accounts with more fields than max_input_vars.
//
// finans/budget.php draws kontonr[x] plus amount[x][z] and id[x][z] for every month of every open account, about 25
// fields per account, with kontoantal, maanedantal and the visible Gem at the end of the form. On a book of more than
// about 40 accounts PHP's max_input_vars (1000) cut the post before the Gem, so the save was skipped and the page came
// back from the database without a word (testlab round 2, budget-cycle: 309 accounts, every Gem lost, and PHP logged
// "Input variables exceeded 1000"). The page's script now sends the grid as one field, budget_grid.
//
// The spec types the lab's amounts as a user does: -1.234,56* in the first month of the first empty account (the *
// fills every month) and 876,54 in the last month of the last empty account. It presses the visible Gem, reads both
// back, and empties them again the same way.
//
// PREREQUISITES: see e2e/workflow.spec.mjs. The stack's book has about 480 open accounts, well past the limit.
//
// History:
// 20261105 CL/LH Created (testlab round 2).

import { test, expect } from "@playwright/test";
import { login, logout, captureDialogs, expectNoPhpFailure } from "./support/app.mjs";

const PAGE = "/saldi/finans/budget.php";

/** The rows a user can type in (row number, account, month values), the month count, and how many fields the form has. */
async function grid(page) {
  return page.evaluate(() => {
    const form = document.forms.budget;
    const rows = [];
    for (const kontonr of form.querySelectorAll('input[name^="kontonr["]')) {
      const x = /^kontonr\[(\d+)\]$/.exec(kontonr.name)[1];
      const cells = [...form.querySelectorAll(`input[name^="amount[${x}]["]`)].map((cell) => cell.value);
      if (cells.length) rows.push({ x, kontonr: kontonr.value, cells });
    }
    // The form's own fields plus the one Gem a click sends (new FormData(form) would already run the page's script).
    const fields = [...form.elements].filter((el) => el.name && el.type !== "submit").length + 1;
    return { rows, months: Number(form.elements.maanedantal.value), fields };
  });
}

/** Press the visible Gem and wait for the page the save draws. */
async function save(page) {
  const [request, response] = await Promise.all([
    page.waitForRequest((r) => r.method() === "POST" && r.url().includes("budget.php")),
    page.waitForResponse((r) => r.request().method() === "POST" && r.url().includes("budget.php")),
    page.locator('input[type="submit"][name="gem"]').click(),
  ]);
  await page.waitForLoadState("load");
  expect(response.status(), "the save is answered with the page").toBe(200);
  expectNoPhpFailure(await page.locator("body").innerText(), "budget.php after Gem");
  return new URLSearchParams(request.postData() ?? "");
}

test("Budget's Gem saves on a book whose form has more fields than max_input_vars", async ({ page }) => {
  captureDialogs(page);
  await login(page);
  let first = null;
  let last = null;
  let months = 0;
  const cell = (row, month) => page.locator(`input[name="amount[${row.x}][${month}]"]`);

  try {
    await page.goto(PAGE);
    const before = await grid(page);
    months = before.months;
    expect(before.fields, "the form has more fields than PHP's max_input_vars, as on the lab's book").toBeGreaterThan(1000);
    const empty = before.rows.filter((row) => row.cells.every((value) => value === ""));
    expect(empty.length, "two accounts without a budget to type in").toBeGreaterThan(1);
    first = empty[0];
    last = empty[empty.length - 1];

    await cell(first, 1).fill("-1.234,56*");
    await cell(last, months).fill("876,54");
    const posted = await save(page);

    await expect(page.locator(`input[name="kontonr[${first.x}]"]`)).toHaveValue(first.kontonr);
    for (let month = 1; month <= months; month++) {
      await expect(cell(first, month), `${first.kontonr} month ${month} is stored`).toHaveValue("-1235");
    }
    await expect(page.locator(`input[name="kontonr[${last.x}]"]`)).toHaveValue(last.kontonr);
    await expect(cell(last, months), `${last.kontonr} month ${months} is stored`).toHaveValue("877");
    for (let month = 1; month < months; month++) {
      await expect(cell(last, month)).toHaveValue("");
    }

    // The cause: the grid goes as one field, so the post is a handful of fields however long the chart is.
    expect(posted.has("budget_grid"), "the grid is posted as one field").toBe(true);
    expect([...posted.keys()].filter((key) => /^(kontonr|amount|id)\[/.test(key)), "no field per cell").toEqual([]);
    expect([...posted.keys()].length).toBeLessThan(20);
  } finally {
    if (first && last) {
      // 0* sets every month to 0 and the save deletes rows of 0, so both accounts are left as they were found.
      await page.goto(PAGE);
      await cell(first, 1).fill("0*");
      await cell(last, months).fill("");
      await save(page);
      await expect(cell(first, 1)).toHaveValue("");
      await expect(cell(last, months)).toHaveValue("");
    }
    await logout(page);
  }
});

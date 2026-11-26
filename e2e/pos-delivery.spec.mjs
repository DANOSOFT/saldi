// 20261126 CDX/NTR Exercise the generated POS delivery script in the browser, including date clearing.

import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { test, expect } from "@playwright/test";

const root = fileURLToPath(new URL("../", import.meta.url));
const code = "$_SESSION = []; require getcwd() . '/debitor/pos_ordre_includes/saleDeliveryFields.php'; echo pos_sale_delivery_script(42);";

// CI's PHP container generates the actual script; Playwright executes it on the host.
function deliveryScript() {
  const [command, args] = process.env.CI
    ? ["docker", ["compose", "exec", "-T", "-w", "/var/www/html/saldi", "web", "php", "-r", code]]
    : ["php", ["-r", code]];
  return execFileSync(command, args, { cwd: root, encoding: "utf8" }).replace(/<\/?script>/g, "");
}

test.beforeEach(async ({ page }) => {
  await page.setContent(`<form>
    <select name="leveringsvalg_id"><option value="3" data-type="levering" selected>Delivery</option></select>
    <input type="date" name="oensket_levdato" onchange="posLeveringGem(this)">
  </form><span id="pos-levdato"></span>`);
  await page.evaluate(() => {
    window.deliveryRequests = [];
    window.fetch = async (url, options) => {
      window.deliveryRequests.push(Object.fromEntries(options.body.entries()));
      return { json: async () => ({ ok: true }) };
    };
  });
  await page.addScriptTag({ content: deliveryScript() });
});

async function changeDate(page, value) {
  const date = page.locator('[name="oensket_levdato"]');
  await date.evaluate((input, next) => { input.value = next; }, value);
  await date.dispatchEvent("change");
  await page.evaluate(() => window.posLeveringKoe);
  return page.evaluate(() => window.deliveryRequests);
}

test("clearing a wished delivery date sends an empty value", async ({ page }) => {
  await page.locator('[name="oensket_levdato"]').evaluate((input) => { input.value = "2099-01-01"; });
  expect(await changeDate(page, "")).toEqual([
    { id: "42", leveringsvalg_id: "3", oensket_levdato: "" },
  ]);
});

test("an incomplete native date does not clear the stored date", async ({ page }) => {
  // Native date controls expose an empty value with badInput while a date is incomplete.
  await page.locator('[name="oensket_levdato"]').evaluate((input) => {
    Object.defineProperty(input, "validity", { value: { badInput: true } });
  });
  expect(await changeDate(page, "")).toEqual([]);
});

test("a complete past date reaches server validation", async ({ page }) => {
  expect(await changeDate(page, "2000-01-01")).toEqual([
    { id: "42", leveringsvalg_id: "3", oensket_levdato: "2000-01-01" },
  ]);
});

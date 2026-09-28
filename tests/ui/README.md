Run the autocomplete browser tests from the repository root after installing the development dependencies and Playwright's Chromium:

```sh
npm install
npx playwright install chromium
npx playwright test --config tests/ui/playwright.config.mjs
```

These tests run the application autocomplete script in a browser with an isolated form and account-search responses. They require no tenant, credentials or external services. Set `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` to use an existing Chromium installation, and `SALDI_UI_TEST_OUTPUT` to choose an artifact directory.

The journal history query regressions run with PHPUnit and `pdo_sqlite`:

```sh
vendor/bin/phpunit --no-configuration tests/characterization/finans/JournalHistoryRegressionTest.test.php
```

For integration verification on a disposable tenant, cover these paths:

- `sidste_5_forslag()` and `selectAccount()`: create a finance account with the same number as a supplier. Select that supplier from a bank account's recent postings, save, and post. Verify type K, the supplier open item, and the supplier control account in the ledger. Repeat in the debit direction with a customer.
- `find_dublet()`: use matching date, invoice, bank and amount for two different suppliers. Only the actual supplier should warn, including when the source journal row has been removed and `openpost` identifies the posted supplier.
- Currency conversion: enter EUR 100 matching ledger-only DKK 750 at rate 750. Confirm the posted-duplicate warning; use a DKK control and a different-amount negative control.
- VAT and lookup controls: select D/K/F suggestions on both sides, check VAT clearing/defaults, and try all four combinations of history and account lookup. Confirm ordinary lookup retains its selected account type.

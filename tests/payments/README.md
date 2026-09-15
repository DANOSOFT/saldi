# SST-788 reconciliation verification

Automated checks use mock terminal responses and isolated temporary receipt files. They never contact Nets or a physical printer.

```sh
node --test tests/payments/reconciliation.test.mjs
php tests/payments/receipt_confirmation.php
```

Run the PHP check as an unprivileged user: its raw-write failure scenario requires a read-only directory. With the local Docker setup:

```sh
docker exec --user www-data saldi-web-1 php /var/www/html/saldi/tests/payments/receipt_confirmation.php
```

Verified on 2026-09-14: 22 JavaScript tests and 20 PHP checks pass, with PHP `E_ALL` enabled. Authentication/database responses are stubbed in the PHP fixture; the separate Docker application checks below exercise actual sessions and permissions. The filesystem failure cases intentionally emit visible warnings. PHP syntax checks and `git diff --check` also pass.

Additional Docker application verification on 2026-09-14 used Apache/PHP 8.3, PostgreSQL 16, Chromium, synthetic invoices and two test registers. Nets responses and the printer handoff were mocked. All eight scenarios passed: success on registers 1 and 2, blocked popup, terminal failure, raw receipt write failure, prepared receipt write failure, denied POS access and an expired session. The success check exposed an HTML body tag before the JSON acknowledgement with menu S; `save_receipt.php` now disables both header and body markup, and the PHP fixture covers that regression. This does not verify physical printing or real payment-provider behavior.

| Affected file / behavior | Manual scenario and expected result |
| --- | --- |
| `debitor/payments/lane3000_afstemning.php`: `start()`, `afstem()`, `print_str()`, `complete()` | Reconcile a real Move3500/Lane3000, including a zero-total report. After receipt preparation, the page shows green completion, says the receipt was sent to print, and enables Back immediately. Confirm the receipt physically prints; green only confirms the browser handoff. |
| Same page: blocked popup | Block popups and reconcile. The page shows an amber message distinguishing completed reconciliation from the blocked print window; Back works. |
| Same page: `get_api_key()`, `afstem()`, `fail()` | In a test setup, use invalid credentials, an unavailable terminal, a terminal failure response, and a response without receipt text. Each stays red, enables Back, and never opens a print window. |
| Same page: register settings and Back | Exercise two configured registers and an existing POS order. Each uses its configured terminal/printserver and receipt filename; Back returns to the original order. Review the fiscal-year cast and POS module permission selection before PR readiness. |
| Same page and `importfiler/tekster.csv` | Switch between Danish, English, and Norwegian. Check initial, printing, completion, blocked-popup, error, and Back labels. |
| `debitor/payments/save_receipt.php`: acknowledgement | In a disposable test tenant, make the receipt directory unwritable, then separately block creation of the print-ready receipt. Both must prevent green success and print handoff. Restore permissions afterward. |
| Same endpoint and page: authorization | With an expired session and with a user lacking POS access, attempt the page and the acknowledgement request. Neither may reconcile/save a receipt or report success. |
| `save_receipt.php`, `print_receipt.php`: existing payment callers | Perform a normal Lane3000 payment and a Flatpay payment in the test environment. Confirm receipt contents, register routing, and print behavior remain correct when `confirm_saved` is omitted. |

Real hardware/customer verification is pending and belongs to the ticket assignee. No SQL migration or release change is included.

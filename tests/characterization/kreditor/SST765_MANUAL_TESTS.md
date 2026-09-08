# SST-765 regression checks

Use a disposable tenant with mail and integration egress contained. Create synthetic creditor orders and use two independently authenticated sessions; two tabs sharing a PHP session can serialize before reaching the database and hide the race. Inspect committed order status, line values, financial entries and open items, not HTTP status alone.

| Affected path | Scenario | Expected result |
| --- | --- | --- |
| `kreditor/bogfor.php`, `ordre.php`, `ordreM.php` | Open a received order in session B; pause posting in A while its transaction is active; save B's old form; release A. | B waits before changing any line, then rejects the stale form. Posted status and financial totals remain unchanged. |
| `kreditor/bogfor.php` | Post one received order concurrently in two independent sessions. | Exactly one posting and one open item; the second request rejects after reading the locked posted status. |
| Save followed by posting | Hold an ordinary save open while changing an invoice field or line price; start posting; then commit the save. | Posting waits and uses the newly saved header and lines. |
| `kreditor/modtag.php` | Run receipt and posting concurrently on one order; repeat receipt after posting. | The operations serialize; posting sees committed receipt state and a posted order cannot receive more stock. |
| Receipt/posting validation and failures | Try missing delivery date, missing invoice number, missing order and a controlled database write failure; retry after correcting the fixture. | No partial stock or financial writes; transaction/locks are released and a valid retry succeeds. |
| Save guards | Send an old form, a missing order id, a posted save with an empty copy parameter, and line ids that moved to another order after the form opened. | All invalid existing-order edits reject before line mutations. |
| GET product lookup return | Add a product to an editable order; repeat against a posted order and while posting holds the header lock. | Editable add succeeds; posted/waiting stale add cannot change the posted order. |
| Delete | Delete an unreceived editable order; attempt deletion after receipt and with a stale pre-posting form. | Successful deletion removes header/lines and exits; received or stale deletion rejects. Attachments are removed only after successful database deletion. |
| `orderIncludes/moveOrderLines.php` caller | Move lines to an editable existing order and to a new order; try a posted/missing/same-order target. | Valid moves preserve quantities; invalid targets reject. Source and existing target headers are locked in id order. An old source form cannot subsequently edit a moved line. |
| Copy/credit, `insertAccount()`, `indset_konto()`, `get_next_order_number()` | Copy and credit a posted order; force a failure after new-header creation; run a copy alongside posting. | Source stays posted; valid new order is created; failure rolls back pending header/lines. Nested allocation cannot commit the enclosing transaction. Allocation's table lock precedes source-row locks. |
| Posting preparation in both save pages | Save-and-post with a valid invoice, missing invoice number and non-DKK currency. | Currency and position preparation happens before save commit; existing validation messages remain. |
| Positive purchase-batch update | Post a line with 0, 1, several and 1,463 receipt rows; also place a negative line after a positive line and after an empty positive line. | At most one all-line batch UPDATE per positive line; prices/dates match prior behavior, quantities/rest and unrelated rows stay unchanged, and existing negative-line batch state is preserved. |

This correction prevents future duplicate posting through these creditor workflows. It does not reverse historical accounting entries, determine which customer voucher should be reversed, fix unrelated voucher-number allocation, or establish the exact historical interleaving from incomplete logs. PostgreSQL is the validated database target; this change does not repair existing MySQL-incompatible allocator SQL.

## Local validation, 8 September 2026

The authenticated baseline reproduced both late-save reopening and concurrent duplicate posting. Against this patch, independent sessions block on the initial header lock: the waiting stale save and second posting reject, leaving status 4, one balanced posting and one open item. Posted lines remain unchanged.

Additional real-handler checks passed: ordinary save; save-before-posting reads the saved invoice field; receipt-before-posting records the expected quantity; controlled posting failure leaves no financial/open-item rows and retry succeeds; stale deletion; empty-copy parameter cannot enable posted edits; invalid/posted move destination; foreign/moved line rejection; valid line move followed by old-form rejection; editable versus posted product addition; posted copy and credit; mobile stale save.

The two PostgreSQL characterization suites passed **45 tests and 286 assertions** under PHP 8.3 E_ALL. They use an explicitly configured disposable database (`SALDI_CHAR_PG_DSN`), randomized schemas and separate processes. Run them with `php phpunit.phar --configuration phpunit.xml tests/characterization/kreditor`. The tests skip if that fixture is not configured. They also verify the actual posting batch block, allocator transaction ownership and copy/post lock ordering. All affected PHP source files passed syntax checks.

The table above remains the manual acceptance checklist for a tester's environment; not every date/currency/serial-number variation is covered by the local synthetic fixture.

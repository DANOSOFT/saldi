# Assistant record service tests

`records_test.php` runs pure journal/authentication checks and characterizes the two predicates extracted from the legacy posting preview and invoice button.

`run_records_integration.py` exercises the actual PHP endpoint with disposable PostgreSQL databases, a temporary SELECT-only login and a temporary PHP container. It removes its resources afterward and never resets an existing company. Defaults use Docker image `saldi-web`, container `saldi-postgres-1` and network `saldi_testnet`; flags can override these names.

```bash
docker run --rm --entrypoint php -v "$PWD:/work:ro" saldi-web /work/tests/assist/records_test.php
python3 tests/assist/run_records_integration.py
```

Use the chatbot repository's virtual environment and pass `--chaty-source /path/to/chaty_V2` to also verify PHP/Python signatures and the real tool adapter. No model-provider call is made.

See [installation and scope](../../includes/assist/README.md) for the read-only database grants and environment settings. The service is disabled by default. The combined chatbot report is in its repository under `integration/saldi-host/RECORD_TOOLS_TEST_REPORT.md`.

## Verification report — 2026-09-08

- Pure PHP checks: 152 assertions passed, with warnings raised as exceptions.
- Disposable PostgreSQL/PHP integration including the real Python adapter: passed; fixture rows unchanged and write attempts denied.
- Local Apache and normal SALDI login: all three operations passed with matching revisions.
- Local embedded widget: selected saved context received, live model called journal validation, and the result card rendered.
- PHP syntax checks passed for the new service and three changed legacy pages.

No posting, invoice sending or accounting form submission was performed by these checks.

| Affected code | Manual regression scenario before enabling a company |
| --- | --- |
| `RecordRules.php`, `finans/bogfor.php` | Compare balanced one-sided entries, one voucher difference and opposite differences across two vouchers; simulation should retain the existing difference condition. |
| `RecordRules.php`, `debitor/ordre.php` | Compare payment-lock button conditions for an unpaid card order, paid order and Magento/Konto/Kontant exceptions; verify saved facts separately from delivery/terminal choices. |
| `RecordAuth.php`, `saldi_assist_record.php` | Obtain a grant through the real proxy/Apache configuration, then revoke rights or log out; subsequent reads must fail. Anonymous and wrong-origin grants must fail. |
| `RecordService.php` | Change a saved row or relevant period/account setting; old revisions must return 409. Compare saved-draft disclosure and the 2,000-row limit. |
| `index/main.php`, `saldi-assist-records.js` | Open a saved journal, ask for a check and highlight an affected row; edit without saving and confirm highlighting is refused. Change company/record and ensure previous findings cannot target it. |
| Feature flags | Disable both host and bot flags and verify the existing documentation chat still works. |

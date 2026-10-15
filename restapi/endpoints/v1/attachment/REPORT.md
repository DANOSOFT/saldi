# B4 delivery report

20261001 CDX/LH. No commits created. No production hosts contacted.
Only B4-owned files were edited. Capabilities and schema steps were not changed.

## 1. Files

Changed:
- `restapi/endpoints/v1/attachment/index.php`: declared own-document authorization, JSON/multipart handling, scoped reads/list/download, canonical errors and tombstone delete.
- `restapi/models/attachment/AttachmentModel.php`: default-off debug setting, reusable legacy vendor/image conversion, correct PDF xref offset and temporary-file cleanup.

Created:
- `restapi/models/attachment/AttachmentException.php`: canonical attachment error codes.
- `restapi/models/attachment/AttachmentUpload.php`: transport decoding, size/digest/MIME/metadata validation and B5 renderer integration check.
- `restapi/models/attachment/DurableAttachmentModel.php`: committed reservation, locked finalization/recovery, scoped DB identities, safe paths, list snapshots, tombstones and staged expiration.
- `restapi/models/attachment/expire-staged.php`: CLI-only nightly entry point; scheduling deferred to the cron owner.
- `tests/restapi/MobileAttachmentTest.php`: 23 integration/unit cases against process-owned disposable tenants.
- `restapi/endpoints/v1/attachment/CONTRACT.md`: exact implemented HTTP contract, compatibility notes and B5/B7 integration interfaces.
- `restapi/endpoints/v1/attachment/REPORT.md`: this report and gate evidence.

## 2. Endpoints and shapes

The complete implemented shapes, nullable fields and limits are in [CONTRACT.md](CONTRACT.md).

- POST `/attachment/`: JSON `{id,sha256,size?,filename?,image_base64,extracted_data?}` or equivalent multipart fields with one binary `file` and JSON-encoded `extracted_data` text. UUID v4; raw byte digest/optional size verified. 201 on a new reservation; 200 same identity/digest or completed staged recovery; 409 changed digest; 410 tombstone. Legacy uploads omitting both identity and digest still get a generated identity/digest.
- GET `/attachment/?id=<integer|uuid>` or `?client_id=<uuid>` or legacy `?file=<DB filename>`: own rows or all readable rows for current finans/bilag/registry rights. Optional `download=true` streams a contained PDF; every selector uses the same ownership/realpath checks. Deleted metadata returns its 200 tombstone; deleted download returns 410; unfinished staged reads return 404 and require POST retry.
- GET `/attachment/`: `{items:Row[],limit,offset,total}`, default limit 50, range 1..100, offset nonnegative, optional `status=ubehandlet|attached|deleted` and `mine=1`. Lists omit staged reservations and untracked disk files; ordinary users always see own rows.
- DELETE `/attachment/?id=<integer|uuid>`: own unprocessed rows only. Moves the PDF to .deleted and returns the retained Row; repeat is 200. Deleting staged/attached rows is 409. Filename deletes are rejected with 422.

Row contains `id,client_id,status,uploaded_at,sha256,filename,size,mimeType,uploadDate,extracted_data,metadata,attached_at,bilag_nr,deleted_at`. The exact nested metadata shape is documented in CONTRACT.md. Request accounting metadata remains under extracted_data. Storage paths and owner subjects are private.

Canonical `data.error`: 400/422 validation_failed; 404 validation_failed for absent/forbidden rows or files; 405 validation_failed for PUT/PATCH; 409 conflict; 410 gone; 413 payload_too_large; 415 unsupported_media_type; 500 internal. Token/session/account/right errors come from the shared foundation. Limits are exactly 15 MiB, detected PDF/JPEG/PNG MIME, 20 PDF pages/60 MP when B5's inspector is available, and 60 MP for image conversion before GD allocation.

## 3. Gates and final output tails

Each changed/new PHP file was linted individually with the required container command:

```text
docker exec -w /var/www/html/saldi saldimobile-web php -l restapi/endpoints/v1/attachment/index.php
No syntax errors detected in restapi/endpoints/v1/attachment/index.php
docker exec -w /var/www/html/saldi saldimobile-web php -l restapi/models/attachment/AttachmentModel.php
No syntax errors detected in restapi/models/attachment/AttachmentModel.php
docker exec -w /var/www/html/saldi saldimobile-web php -l restapi/models/attachment/AttachmentException.php
No syntax errors detected in restapi/models/attachment/AttachmentException.php
docker exec -w /var/www/html/saldi saldimobile-web php -l restapi/models/attachment/AttachmentUpload.php
No syntax errors detected in restapi/models/attachment/AttachmentUpload.php
docker exec -w /var/www/html/saldi saldimobile-web php -l restapi/models/attachment/DurableAttachmentModel.php
No syntax errors detected in restapi/models/attachment/DurableAttachmentModel.php
docker exec -w /var/www/html/saldi saldimobile-web php -l restapi/models/attachment/expire-staged.php
No syntax errors detected in restapi/models/attachment/expire-staged.php
docker exec -w /var/www/html/saldi saldimobile-web php -l tests/restapi/MobileAttachmentTest.php
No syntax errors detected in tests/restapi/MobileAttachmentTest.php
```

Focused verification:

```text
docker exec -w /var/www/html/saldi saldimobile-web php vendor/phpunit.phar --testsuite restapi --filter MobileAttachmentTest --display-warnings --display-deprecations
Time: 00:08.496, Memory: 29.46 MB

OK, but some tests were skipped!
Tests: 23, Assertions: 259, Skipped: 1.
```

The one skip is explicit: GD is absent in the dev container, so the legacy JPEG/PNG conversion test skips. The PDF path, multipart/base64 equivalence, correct/mismatched hashes and sizes, MIME/body limits, staged-before-file/partial-file recovery, concurrent duplicate requests, foreign users/tenants, principal namespace separation, traversal/symlink escapes, moved attached PDFs, tombstones and staged expiry are covered. The crash test terminates the disposable tenant's database backend during a trigger pause after rename and before finalization commit; retry returns the same single identity.

Required full gate (red outside B4):

```text
docker exec -w /var/www/html/saldi saldimobile-web php vendor/phpunit.phar --testsuite restapi

FAILURES!
Tests: 192, Assertions: 1398, Failures: 16, Warnings: 2, Skipped: 1.
```

Evidence for the remaining failures:

| Owner/integration | Failing evidence |
|---|---|
| B2 login | `AuthLoginEndpointTest.php:71` expects unknown account 404; new login returns 401 invalid_credentials. |
| B2 refresh | `AuthRefreshEndpointTest.php:74,120,158,174`: old nonrotating/stateless/header-fallback assertions conflict with rotated refresh tokens and sid/session enforcement. |
| B2 authorization | `BearerAuthEnforcementTest.php:121,131`: sid-less manufactured tokens now receive session_revoked instead of old 400/header fallback. |
| B1/B2/B7 foundation fixtures | `MobileFoundationTest.php:149,205,521,560` still expect m06/r06; runners now end at m07_push_delivery_identity/r07_refresh_replay_ciphertext. `:436,464,728` manufacture sid-less account/registry tokens rejected by current session checks. `:544` reads an empty fixture after earlier assertions abort before seeding. |
| B6 VAT | `ReferenceDataEndpointsTest.php:139` expects integer code; the rebuilt VatModel endpoint returns codes such as E1. |

The eight foundation failures and both warnings reproduce independently with:

```text
docker exec -w /var/www/html/saldi saldimobile-web php vendor/phpunit.phar --testsuite restapi --filter MobileFoundationTest --display-warnings
FAILURES!
Tests: 23, Assertions: 193, Failures: 8, Warnings: 2.
```

Both warnings originate at `tests/restapi/MobileFoundationTest.php:544`: undefined row 0 / array offset on null. No attachment test failed or emitted a warning. Changes to these other slices' files were not made.

Conventions: `git diff --check -- restapi/endpoints/v1/attachment restapi/models/attachment` returned no output; `cut -f1 importfiler/tekster.csv | sort | uniq -d` returned no output. Every edited legacy PHP file has the required 20261001 CDX/LH history line, every new PHP file has the standard 5.0.0 header, and new SQL values use parameters or escaped literals. No translation IDs or capabilities were changed; no secret literals were introduced. The pre-existing credential-literal issue in RestApiEnv::pgPass() remains outside this slice and is already recorded by B1's CONTRACTS.md.

## 4. Needs from other slices

- B1/orchestrator: merge CONTRACT.md's Compatibility — B4 section into doc/mobile/CONTRACTS.md. That document is B1-owned and was left untouched.
- B1: append a correction step, if legacy installations/templates can lack norm_amount/content_sha256/vendor columns. Current foundation CREATE TABLE supplies them only for absent tables. B4 tolerates absent optional columns and tests both variants; matching/hash backfill needs the schema owner's decision. Never modify an applied step.
- B5: use DurableAttachmentModel::find()/path() for thumbnail/page reads; retain owner/digest identities through moves and update relative storage_path. Supply the renderer interface described in CONTRACT.md and run its actual page/pixel/malformed-PDF and thumbnail/page security tests.
- B7: validate and resolve extracted_data.reminder_id in the upload completion path. B4 accepts scalar reminder/device/moms fields but does not persist or act on them. Schedule the CLI staged-expiry entry point nightly; it currently uses a seven-day grace and batch size 100.
- B9: copy the attachment contract into Swagger, including scoped lists, idempotency, aliases and error codes.
- B1/B2/B6/B7: reconcile the remaining full-suite assertions/fixtures with their current implementations, then rerun the shared gate.

## 5. Red/deferred

- Full REST suite remains red: 16 failures and 2 warnings in the other slices' tests listed above. B4's 23 tests pass with the one required GD skip.
- Actual PDF page count, decoded-pixel measurement and full PDF parser validation await B5's renderer; the available-renderer checks and boundary validation are implemented. The renderer is absent here; only finfo/header/EOF validation currently runs for PDFs.
- Legacy GD conversion cannot be executed in this container. Its test explicitly skips; the app's PDF transport is fully tested.
- The nightly entry point is implemented and its service is tested on disposable tenants, but is not scheduled or run across all tenants here because cron belongs to another slice.
- Shared compatibility documentation, Swagger, reminder resolution and legacy owner/backfill/lifecycle integration remain with their file owners.

## 6. Attachment review fixes — 20261001 CDX/LH

This section records the final attachment review fixes and supersedes earlier B4-only status notes above. No commits were created and no production hosts were contacted. Changes stayed in the attachment endpoints/models, legacy document includes, and MobileAttachment/MobileWiring tests. Protected updater files were not opened or edited.

| Confirmed finding | Result and regression coverage |
|---|---|
| 1. Basename fallback claims unrelated voucher files | Fixed. NULL-storage filename lookup applies only to the exact `pulje/<basename>` path. A legacy NETS collision test verifies that attaching/deleting a finance document leaves the pending pool row, metadata and file intact. |
| 2. Attached/deleted names collide with legacy uploads and metadata writes | Fixed. Attachment, deletion, missing-file cleanup, REST deletion and staged expiry release the filename in the status UPDATE using a bounded historical alias. Scanner lookup also recognizes an attached identity whose physical file remains in pulje; tombstones still allow a replacement to be adopted. Immediate alias release and both scanner cases have regressions. |
| 3. Strict Ghostscript flags reject repairable PDFs | Fixed. Removed PDFSTOPONERROR/PDFSTOPONWARNING while retaining SAFER, prlimit, output limits, wall deadlines and nonzero-exit rejection. A generated wrong-startxref fixture is uploaded, downloaded and rendered through both preview routes. Shared CONTRACTS.md needs its owner to merge the scoped contract correction. |
| 4. Reminder crashes lose the durable reservation | Fixed. Validate the reminder before committing every staged reservation, then revalidate and finalize/resolve in a second transaction. Lock ordering is consistent. The reminder crash test terminates the DB backend after rename, runs actual pool sync before retry, verifies ownership and identity survive and resolution remains absent until retry commits. Invalid reminders leave no reservation. |
| 5. Legacy attachment releases its lock before completion | Fixed. insertDoc calls one transition that holds the row lock across the move, checked target/sibling INSERTs and attached status update. A worker paused at sibling insertion proves REST DELETE waits and then receives 409. A rejected sibling INSERT proves reference rollback and physical file recovery. |
| 6. Renaming onto historical aliases fails UNIQUE(filename) | Fixed. move/converted lock and release retained historical destination aliases in their transaction. Regression cases cover attached, deleted and missing historical aliases, identity/path preservation and converted destinations. |

| Minor finding | Result |
|---|---|
| 1. Reminder failure leaves an orphan adopted by sync | Fixed by confirmed finding 4's durable reservation; no compensating unlink is needed. |
| 2. Old staged reservation has a stale updated timestamp | Fixed. Finalization, REST deletion and staged expiry update `updated`. A stale scanner cleanup predicate cannot tombstone a just-finalized reservation. |
| 3. Legacy XML is labelled PDF | Fixed. Metadata/list/download use the stored extension for PDF/XML MIME; download names use the corresponding extension. XML listing, detail, download MIME/name/body and PDF-only preview errors have a regression. |
| 4. PHP/web-server limits missing from deployment prerequisites | Documented in scoped CONTRACT.md: upload_max_filesize >=16M, post_max_size >=21M, matching web-server body limit >=21 MiB. The requested shared doc/mobile/CONTRACTS.md paragraph remains outside the allowed file scope. Its owner must add these limits and remove the two strict PDF flags. |

Twelve regression tests were added across MobileAttachmentIdentityTest and MobileAttachmentTest. Existing tombstone, non-reminder crash and MobileWiring finalization assertions were strengthened to cover the corrected contracts. Legacy history lines use the required 20261001 CDX/LH tag; revision headers use the edit date and current version 5.0.0. Diff whitespace checks pass and no credential literals were introduced.

Final gate output tails:

```text
PHP lint through saldimobile-web: all nine edited PHP files have no syntax errors.
Legacy docPool emits its existing ${var} interpolation deprecations during lint.

--testsuite restapi
Time: 03:19.070, Memory: 37.46 MB
OK, but there were issues!
Tests: 418, Assertions: 5468, Deprecations: 8, Skipped: 1.

--testsuite restapi --filter 'MobileAttachment|MobileWiring'
OK, but there were issues!
Tests: 63, Assertions: 610, Deprecations: 8, Skipped: 1.

Characterization: each file ran in its own Docker PHP process with a unique lowercase SALDI_CHAR_TEST_DB.
PoolPaths: OK (8 tests, 10 assertions)
PoolAmountNormalizer: OK (14 tests, 20 assertions)
PoolDateNormalizer: OK (18 tests, 24 assertions)
PoolDuplicateMarker: OK (7 tests, 18 assertions)
PoolVendorSuggestion: OK (5 tests, 26 assertions)
PoolVendorMatcher: FAILURES!
Tests: 59, Assertions: 131, Failures: 1.
200 token-scan matches against 2000 kreditorer took 0.6264660358429s
Failed asserting that 0.6264660358428955 is less than 0.5.
```

The full REST gate is green. The complete characterization gate remains red on the existing vendor matcher timing assertion. The same file initially passed (59 tests, 131 assertions; total runtime 0.445 seconds), but its final isolated rerun fails the 0.5-second budget. Vendor matcher implementation and characterization tests were left unchanged; functional assertions pass. This performance budget needs follow-up rather than a relaxed threshold. The eight deprecations are existing docPool string interpolations; the skip is the legacy GD conversion test because GD is unavailable.

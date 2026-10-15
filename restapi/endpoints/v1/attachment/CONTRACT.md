# B4 attachment contract

20261001 CDX/LH. `/restapi/endpoints/v1/attachment/` retains the foundation envelope
`{success: boolean, message: string, data: object|null}` and `X-Request-Id`.
The endpoint declares `bilag/own`. Current database finans/bilag read rights
permit colleagues' reads and lists; deletion and upload identity reuse remain
owner-only, including for registry principals. Tenant identity comes from the token.

## POST

JSON request:

```json
{
  "id": "<uuid-v4>",
  "sha256": "<64 hex characters>",
  "size": 1234,
  "filename": "receipt.pdf",
  "image_base64": "<base64 file bytes, optionally prefixed with a data URI>",
  "extracted_data": {
    "total_amount": "612.50",
    "invoice_date": "2026-10-01",
    "invoice_number": "123",
    "invoice_description": "Fuel",
    "currency": "DKK",
    "vendor": "Seller",
    "suggested_kontonr": "4000"
  }
}
```

Multipart uses the same text fields plus exactly one binary `file` part;
`extracted_data` is a JSON object encoded in a text field. Do not set a JSON
Content-Type on multipart. `client_id` is an accepted alias for body `id`;
if both are present they must agree. UUIDs are v4 and normalized to lowercase.
`size`, when supplied, is the number of original decoded/uploaded bytes.
`sha256`, when supplied, hashes those original bytes and is normalized to lowercase.
For the app's PDF uploads this is also the stored PDF digest.
For legacy JPEG/PNG uploads `sha256` identifies the original image;
optional legacy `content_sha256` identifies the converted PDF.

The file limit is **15 MiB (15 × 1024 × 1024 bytes)**, including converted PDFs.
MIME comes from finfo: PDF, JPEG, PNG. JPEG/PNG conversion uses GD. Missing GD
returns 422 with `Image conversion unavailable; upload a PDF`. Images above 60 MP
are rejected before GD allocation. PDF validation checks the header and EOF;
full parser validation and measured PDF page/pixel limits require B5's renderer.

`filename` is accepted but storage names are generated as `<client_id>.pdf`.
`extracted_data` is optional and its supplied fields must be scalar or null.
The listed accounting fields persist in the legacy pool columns. Seller identity
fields `vendor_vat_number`, `vendor_iban`, `vendor_bank_reg`, `vendor_bank_account`,
`customer_vat_number` feed the existing vendor matcher when vendor columns exist.
Legacy top-level `accountnr` and `subject` remain fallbacks.
`moms_code`, `device_id` and `reminder_id` are accepted scalar metadata but are
not persisted or acted on by B4; reminder validation/resolution is B7's integration.

A fresh reservation returns **201**. Existing same-id/same-digest requests return
**200**, including successful staged recovery. Existing same-id/different-digest
returns **409 conflict**. Tombstoned identity returns **410 gone**. An identity
owned by another subject returns **404 validation_failed**, even for a finans caller.

Reservation commits `status=staged` and ownership first. A second transaction locks
the row, writes `pulje/.staging/<uuid>.pdf`, checks bytes and digest, renames to
`pulje/<uuid>.pdf`, then commits `ubehandlet` and the relative storage path.
Concurrent reservations conflict on the unique keys; finalization serializes on
the row lock. A retry overwrites partial staging bytes and completes a reservation
whose file was renamed before a crash. Ownership and identity digests never change
on a retry; persisted accounting metadata comes from the reserved row.

## Row response

Every successful POST, detail GET and DELETE returns this `data` object:

```typescript
{
  id: number;                       // pool_files.id
  client_id: string | null;          // UUID v4; legacy rows may have null
  status: 'ubehandlet' | 'attached' | 'deleted';
  uploaded_at: string | null;        // ISO 8601 with offset
  sha256: string | null;
  filename: string;
  size: number | null;               // stored PDF size; null after deletion/missing file
  mimeType: 'application/pdf' | 'application/xml';
  uploadDate: string | null;         // same instant as uploaded_at
  extracted_data: {
    total_amount: string | null;
    invoice_date: string | null;
    invoice_number: string | null;
    invoice_description: string | null;
    currency: string | null;
    vendor: string | null;
    suggested_kontonr: string | null;
  };
  metadata: {
    date: string | null;
    amount: string | null;
    accountnr: string | null;
    invoiceNumber: string | null;
    invoiceDescription: string | null;
    currency: string | null;
    subject: string | null;
  };
  attached_at: string | null;
  bilag_nr: number | null;
  deleted_at: string | null;
}
```

Owner subjects and internal storage paths are never returned. New upload fields
normally contain strings; null accounting fields remain possible on historical
rows and reservations made by an earlier implementation. No T1 approval/posting
status is invented.

## GET

Supply exactly one selector:

- `?id=<positive integer>` selects the pool identity; `?id=<uuid-v4>` also reconciles a client id.
- `?client_id=<uuid-v4>` reconciles an upload.
- Legacy `?file=<exact pool_files.filename>` is a database alias, never a caller path.

Add `&download=true` to stream the contained PDF with `Content-Type: application/pdf`,
`Content-Disposition: attachment; filename="attachment-<id>.pdf"`, `Content-Length`,
`Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`, and `X-Request-Id`.
All selectors use the same ownership and realpath containment checks, including
moved attached files. Historical null storage paths resolve the DB filename inside
pulje. Missing rows, files, forbidden owners, and staged detail reads return 404
`validation_failed`. Metadata for a deleted row returns 200 with status `deleted`;
its download returns 410 `gone`. Staged reads do not falsely confirm an upload;
retry POST to complete them.

Without a selector, return a database-backed list:

```typescript
{ items: Row[]; limit: number; offset: number; total: number }
```

`limit` defaults to 50, allowed 1..100; `offset` defaults to 0, allowed nonnegative
integers up to nine decimal digits. `status` accepts `ubehandlet`, `attached`,
`deleted`; omitted includes those three statuses. Staged reservations are excluded.
`mine=1` forces own rows, including for finans/registry callers; `mine=0` is default.
A caller without finans/bilag rights always gets own rows. Results order by
`uploaded_at DESC, id DESC`; count and rows share a PostgreSQL read snapshot.
Untracked disk files are never listed.

## DELETE

`DELETE ?id=<pool integer or UUID v4>` accepts only an own `ubehandlet` identity.
The row is locked, its file is moved to `pulje/.deleted/<pool-id>.pdf`, and its status,
relative path and `deleted_at` commit together. An interrupted move can be completed
by retrying DELETE. A repeated DELETE returns the same tombstone with 200.
`staged`/`attached` rows return 409 `conflict`; absent/other-owned rows return 404
`validation_failed`. Bare filename deletes and body filename deletes return 422.

## Errors

All endpoint-generated errors use `data.error`:

| HTTP | Code | Conditions |
|---:|---|---|
| 400 | validation_failed | Malformed JSON (foundation decoder) |
| 401 | invalid_token / session_revoked | Foundation token/session checks |
| 403 | permission_denied or account_closed | Foundation rights/account checks |
| 404 | validation_failed | Absent/other-owned row, absent/escaped file, unfinished upload |
| 405 | validation_failed | PUT/PATCH |
| 409 | conflict | Changed digest for an existing id; deletion of staged/attached row |
| 410 | gone | POST of tombstone, download of deleted row |
| 413 | payload_too_large | Original/converted file >15 MiB, upload/body server limit |
| 415 | unsupported_media_type | finfo MIME outside PDF/JPEG/PNG |
| 422 | validation_failed | UUID/selectors/pagination/metadata/base64/size/digest errors; incomplete PDF; invalid image; unavailable GD; renderer limits |
| 500 | internal | Filesystem/database/server failure; generic Internal Server Error |

## Maintenance and B5 integration

`php restapi/models/attachment/expire-staged.php` is a CLI-only nightly entry point.
It iterates registry-resolved tenant databases, applies the existing schema runner,
and tombstones reservations older than seven days in batches of 100. Locked active
uploads are skipped. Any surviving complete PDF is preserved in .deleted; abandoned
partial staging bytes are removed after preserving the available file. No processed,
attached or already deleted row expires, and tombstones are retained. Output is
`{"expired":<count>,"failed":<boolean>}`; errors log only exception class and location.
**Scheduling this command belongs to the cron owner/orchestrator. It has not been
run against all tenants or scheduled here.** `DurableAttachmentModel::expireStaged`
is exercised against process-owned disposable tenants.

Shared B5 read interface:
`new DurableAttachmentModel($tenantConnection, $db, $canonicalSubject, $canReadAll)`;
`find(['id' => $id])`, then `path($row)`. These apply the same row scope and file
containment used by detail/download. Do not use the legacy filename-loading model
for thumbnail/page reads. `storage_path` is relative to the tenant document store,
resolved with the installation's `poolPaths.php` layout (owncloud/bilag/documents).

B5 renderer file: `includes/docsIncludes/attachmentPdfRenderer.php`. Define
`mobileAttachmentRendererAvailable(): bool` and
`mobileAttachmentInspectPdf(string $path): array{page_count:int, decoded_pixels:int}`.
When available, the upload validates at most 20 pages and 60,000,000 decoded pixels.
The inspector must bound CPU/memory and return a canonical 422 AttachmentException
for malformed/encrypted/unsupported PDFs. Counts are tested at their exact limits;
the real renderer is not present in this slice.

Legacy debug logging is enabled only by a tenant settings row with
`var_grp=mobile`, `var_name=attachment_debug`, `user_id=0`, `var_value=1`.
Missing/other values disable it. Capabilities remain unchanged.

## Compatibility — B4 attachments (for CONTRACTS.md's owner to merge)

- JSON base64/data-URI and optional accounting metadata remain accepted, with multipart added.
- Legacy no-id/no-digest requests receive server-generated UUID/digest values. Supplying an id requires UUID v4; non-UUID filename ids now return 422. Omitting a digest for compatibility computes it on the server; the app supplies its own digest and frozen UUID.
- Filenames are now stable server-generated UUID PDFs; caller filename suffixing and content-only cross-id deduplication are replaced by per-client-id idempotency. Retrying a legacy request without an id creates a fresh identity; callers must retain the returned client_id for subsequent retries.
- Existing filename/size/mimeType/uploadDate/metadata response fields remain, with durable identity/lifecycle fields and extracted_data added. New uploads consistently return PDFs; failed image conversion returns 422 instead of leaving an image in the pool.
- GET lists now return `{items,limit,offset,total}`, use pool rows and scope ownership. Historical rows without an owner are visible only to finans/bilag/registry readers; there is no inferred owner backfill.
- `file` remains a safe DB read alias. Caller paths, untracked files, and other users'/tenants' rows cannot be read. Deleted metadata is visible to authorized readers; deleted downloads return 410. Staged reads return 404 until POST completes.
- DELETE requires an own unprocessed identity id, retains a tombstone, moves the PDF, and returns that row. Filename/body deletes are rejected. Repeated deletes return 200; POST of the retained id returns 410.
- Upload limits and canonical 413/415/422 errors replace generic upload failures. Debug logging defaults off. The daily staged-expiry command requires scheduling; a seven-day expired reservation becomes a tombstone.

## Attachment review corrections (20261001 CDX/LH)

Legacy UBL XML rows are listed and downloaded as `application/xml` with an
`attachment-<id>.xml` download name. Mobile uploads remain PDFs. Thumbnail/page
previews support PDFs only; requesting a preview of XML returns 422
`validation_failed`. Historical attached/deleted rows have an internal
`.history-<id>-<name>` alias; their durable id and storage path remain available.
The alias is released immediately when a row leaves the pool, including expiry.

Reminder uploads validate the recipient before committing their staged reservation.
Final attachment state and recipient resolution commit together in the second
transaction. A crash after rename retains the staged identity through web pool
sync, and a same-id retry completes it. Invalid reminder recipients create no row.

Production upload prerequisites: `upload_max_filesize >= 16M`,
`post_max_size >= 21M`, and a matching web-server body limit (for example nginx
`client_max_body_size` or Apache `LimitRequestBody`) of at least 21 MiB.
The file limit is 15 MiB; JSON base64 adds one third plus metadata.
PDF inspection/rendering requires PHP `proc_open`, `/usr/bin/gs` with JPEG and
nullpage devices, `/usr/bin/prlimit`, and PHP fileinfo. Ghostscript uses `-dSAFER`
and bounded CPU, memory, output, open files and a wall deadline. Repairable PDFs
(such as an incorrect startxref offset) are accepted; strict `PDFSTOPONERROR`/
`PDFSTOPONWARNING` flags are omitted. Nonzero renderer exits still return 422.

For the owner of `doc/mobile/CONTRACTS.md`: update its production prerequisites
paragraph to include the PHP/web-server limits above and remove the two strict
PDF flags. This shared spec is outside the attachment fix file scope.

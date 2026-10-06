<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolListData.php --- ver 5.0.0 --- 2026-10-07 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261007 CL/SZ SD-719 Created: the pool list's data, moved out of includes/_docPoolData.php unchanged, so docPool.php can
//                send the first page with the page itself (one request less before the list shows) and _docPoolData.php
//                answers every later request with the same code. $q holds the request parameters ($_GET there).

require_once __DIR__ . '/poolVendorMatcher.php';
require_once __DIR__ . '/poolDuplicateMarker.php';
require_once __DIR__ . '/poolMetadata.php';
require_once __DIR__ . '/poolArchive.php';
require_once __DIR__ . '/poolListQuery.php';

if (!function_exists('poolListData')) {
/**
 * The pool list as _docPoolData.php answers it: with limit one page {rows, total, offset, limit, matches, extra, currentIndex},
 * with filesOnly every file name, otherwise every row.
 *
 * @param array $q The request parameters (dir, params, poolParams, limit, offset, sort, order, q, sum, dato, current, toCurrent, include, archived, filesOnly).
 * @return array
 */
function poolListData(array $q) {
    $params = isset($q['params']) ? urldecode($q['params']) : '';
    $poolParams = isset($q['poolParams']) ? urldecode($q['poolParams']) : '';

    // Database-only mode: Get all files from pool_files table
    $data = [];
    $fil_nr = 0;

    // Kreditor index for the vendor contract (one query, reused for every file). Built lazily
    // so a tenant whose betweenUpdates.php has not yet added the vendor_* columns costs nothing.
    $vendorIndex = null;
    $vendorColumnsExist = poolVendorColumnsExist();

    // Query all files from the pool_files table (database is the source of truth)
    $vendorSelect = $vendorColumnsExist ? ", vendor_name, vendor_cvr, vendor_iban, vendor_konto_id, vendor_match, vendor_score" : "";
    // SD-717: the normal list or the archive, never both
    $showArchived = ($q['archived'] ?? '') === '1' && poolArchiveReady();
    if ($showArchived) {
        $archiveSelect = ", archived";
        $archiveWhere = "WHERE archived IS NOT NULL";
        $archiveOrder = "archived DESC, ";
    } else {
        $archiveSelect = poolArchiveReady() ? ", archived" : "";
        $archiveWhere = "WHERE " . poolArchiveActiveSql();
        $archiveOrder = "";
    }
    $qtxt = "SELECT id, filename, subject, account, amount, file_date, invoice_number, description, currency, updated, manually_edited$vendorSelect$archiveSelect
             FROM pool_files $archiveWhere ORDER BY {$archiveOrder}file_date DESC, updated DESC";
    $result = db_select($qtxt, __FILE__ . " line " . __LINE__);

    while ($row = db_fetch_array($result)) {
        $file = $row['filename'];
        $base = pathinfo($file, PATHINFO_FILENAME);

        $subject = $row['subject'] ?: $base;
        $account = $row['account'] ?: '';
        $amount = $row['amount'] ?? '';
        $modDate = $row['file_date'] ?: '';
        $invoiceNumber = $row['invoice_number'] ?: '';
        $description = $row['description'] ?: '';
        $currency = $row['currency'] ?: '';

        // Vendor contract (kravspec afsnit 5), null for files scanned before vendor support.
        $vendor = null;
        if ($vendorColumnsExist && trim((string) ($row['vendor_match'] ?? '')) !== '') {
            try {
                if ($vendorIndex === null) $vendorIndex = poolVendorLoadIndex();
                if (poolVendorNeedsRematch($row, $vendorIndex)) {
                    // AI-6: the kreditor may have been created (or deleted) after the scan. Pure
                    // lookups against the index already in memory - no extra queries per file
                    // beyond the UPDATE when the outcome changed.
                    $bank = poolVendorRowFromIban($row['vendor_iban'] ?? null);
                    $fresh = poolVendorMatch(array(
                        'name' => $row['vendor_name'] ?? null,
                        'cvr' => $row['vendor_cvr'] ?? null,
                        'iban' => $bank['iban'],
                        'bank_reg' => $bank['bank_reg'],
                        'bank_konto' => $bank['bank_konto'],
                    ), $vendorIndex, array('nameScan' => 'tokens'));
                    $storedKontoId = ($row['vendor_konto_id'] === null || $row['vendor_konto_id'] === '') ? null : (int) $row['vendor_konto_id'];
                    if ($fresh['kontoId'] !== $storedKontoId || $fresh['match'] !== $row['vendor_match']) {
                        db_modify(
                            "UPDATE pool_files SET " . poolVendorUpdateSql($fresh) . " WHERE id = " . (int) $row['id'],
                            __FILE__ . " line " . __LINE__
                        );
                    }
                    $vendor = $fresh;
                } else {
                    $vendor = poolVendorFromRow($row, $vendorIndex);
                }
            } catch (Throwable $e) {
                // The vendor contract is a bonus on the file list; never let it break the list.
                error_log("_docPoolData vendor for {$row['filename']} failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                $vendor = null;
            }
        }

        $fil_nr++;

        // Build href - remove any existing poolFile from poolParams
        $cleanPoolParams = preg_replace('/&?poolFile=[^&]*/', '', $poolParams);
        $cleanPoolParams = ltrim($cleanPoolParams, '&');
        $hreftxt = "../includes/documents.php?$params&$cleanPoolParams&docFocus=$fil_nr&poolFile=" . urlencode($file);

        $data[] = [
            'filename' => $file,
            'subject' => $subject,
            'account' => $account,
            'amount' => $amount,
            'date' => $modDate,
            'href' => $hreftxt,
            'invoiceNumber' => $invoiceNumber,
            'description' => $description,
            'currency' => $currency,
            'vendor' => $vendor,
            'fil_nr' => $fil_nr,
            'version' => poolMetadataVersion($row),
            'manuallyEdited' => poolMetadataIsManual($row),
            'archived' => $row['archived'] ?? null,
        ];
    }

    // MB-42: the customer's pool held the same bilag under two or three filenames, with identical
    // fakturanr, amount and date, and nothing in the list said so. poolMarkDuplicates() groups those and
    // sets duplicateOf on every member (see poolDuplicateMarker.php for why a hash cannot do this part).
    // Not in the archive: an archived document is not a duplicate to act on (SD-717).
    if (!$showArchived) {
        $data = poolMarkDuplicates($data);
    }

    // SD-719: one page of the list; everything that needs the full set is done before slicing
    if (isset($q['filesOnly'])) {
        $data = array('files' => array_map(function ($row) { return $row['filename']; }, $data));
    } elseif (isset($q['limit'])) {
        $limit = max(1, min(200, (int)$q['limit']));
        $offset = max(0, (int)($q['offset'] ?? 0));
        $sortField = (string)($q['sort'] ?? '');
        $rows = $sortField !== '' ? poolListSort($data, $sortField, ($q['order'] ?? 'asc') !== 'desc') : $data;
        // No match colours in the archive (SD-717)
        $matches = $showArchived
            ? poolListMatches(array(), null, null)
            : poolListMatches($rows, (string)($q['sum'] ?? ''), (string)($q['dato'] ?? ''));
        $rows = poolListSearch(poolListOrder($rows, $matches['byFile']), (string)($q['q'] ?? ''));
        // Where the open document is in the list; toCurrent=1 makes the page reach it, so the browser can show it in one request
        $currentIndex = -1;
        $currentFile = (string)($q['current'] ?? '');
        if ($currentFile !== '') {
            foreach ($rows as $i => $row) {
                if ($row['filename'] === $currentFile) {
                    $currentIndex = $i;
                    break;
                }
            }
        }
        if (!empty($q['toCurrent']) && $currentIndex >= $offset) {
            $limit = max($limit, min(1000, $currentIndex - $offset + 1));
        }
        $page = array_slice($rows, $offset, $limit);
        foreach ($page as $i => $row) {
            $page[$i]['match'] = $matches['byFile'][$row['filename']] ?? null;
        }
        // The open document and the ticked ones, for the code that reads their data while they are not on a loaded page
        $wanted = array_merge(array((string)($q['current'] ?? '')), array_map('strval', (array)($q['include'] ?? array())));
        $onPage = array_flip(array_map(function ($row) { return $row['filename']; }, $page));
        $extra = array();
        foreach ($data as $row) {
            if (in_array($row['filename'], $wanted, true) && !isset($onPage[$row['filename']])) {
                $row['match'] = $matches['byFile'][$row['filename']] ?? null;
                $extra[] = $row;
            }
        }
        unset($matches['byFile']);
        $data = array(
            'rows' => $page,
            'total' => count($rows),
            'offset' => $offset,
            'limit' => $limit,
            'matches' => $matches,
            'extra' => $extra,
            'currentIndex' => $currentIndex,
        );
    }

    return $data;
}
}

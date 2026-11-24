<?php
// 20261110 CDX/ASR Bind document actions to the tenant root, owning module and authoritative document row.

require_once __DIR__ . '/docFileFunc.php';

/**
 * @param string $source Document source stored on the authoritative row.
 * @return string|null The owning permission key, or null for an unsupported source.
 */
function docAccessSourcePermission(string $source): ?string
{
    return [
        'kassekladde' => 'finans.kassekladde',
        'debitorOrdrer' => 'debitor.ordre',
        'creditorOrder' => 'kreditor.ordre',
        'debitor' => 'debitor.konti',
        'creditor' => 'kreditor.konti',
    ][$source] ?? null;
}

/**
 * Resolves a legacy includes-relative or absolute document path inside one tenant.
 * PHP has already decoded URL parameters; no second decoding is performed.
 *
 * @param string $tenantRoot Trusted document-root/tenant path.
 * @param string $requested Caller-supplied path, relative to includes/ or absolute.
 * @return string|null Existing canonical file path strictly inside the tenant root.
 */
function docAccessCanonicalPath(string $tenantRoot, string $requested): ?string
{
    $root = realpath($tenantRoot);
    if ($root === false || $requested === '' || str_contains($requested, "\0")) {
        return null;
    }
    $candidate = str_starts_with($requested, '/') ? $requested : dirname(__DIR__) . '/' . $requested;
    $file = realpath($candidate);
    if ($file === false || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $file;
}

/**
 * Matches the actual file, source and source ID, including linked finance documents.
 * A path that merely shares the filename, or a row pointing into another module,
 * never authorizes changing that file.
 *
 * @param string $tenantRoot Trusted document-root/tenant path.
 * @param string $canonicalPath Existing canonical requested file.
 * @param string $source Claimed document source.
 * @param int $sourceId Claimed source record ID.
 * @param array<int, array{id: int|string, filepath: string, filename: string, source: string, source_id: int|string}> $rows Candidate rows from documents.
 * @return array{id: int, filepath: string, filename: string, source: string, source_id: int, canonicalPath: string}|null Validated document and file.
 */
function docAccessMatchingRow(string $tenantRoot, string $canonicalPath, string $source, int $sourceId, array $rows): ?array
{
    $root = realpath($tenantRoot);
    $requested = docAccessCanonicalPath($tenantRoot, $canonicalPath);
    $permission = docAccessSourcePermission($source);
    if ($root === false || $requested === null || $sourceId <= 0 || $permission === null) {
        return null;
    }
    $relative = substr($requested, strlen($root) + 1);
    if (docFilePermissionKey('doc', $relative) !== $permission) {
        return null;
    }
    foreach ($rows as $row) {
        if ($row['source'] !== $source || (int)$row['source_id'] !== $sourceId) {
            continue;
        }
        $rowPath = $root . '/' . ltrim($row['filepath'], '/') . '/' . $row['filename'];
        if (docAccessCanonicalPath($root, $rowPath) !== $requested) {
            continue;
        }
        return [
            'id' => (int)$row['id'], 'filepath' => $row['filepath'], 'filename' => $row['filename'],
            'source' => $source, 'source_id' => $sourceId, 'canonicalPath' => $requested,
        ];
    }
    return null;
}

/**
 * Loads authoritative candidate rows and matches the requested file before mutation.
 *
 * @param string $tenantRoot Trusted document-root/tenant path.
 * @param string $requested Caller-supplied file path.
 * @param string $source Claimed document source.
 * @param int $sourceId Claimed source record ID.
 * @return array{id: int, filepath: string, filename: string, source: string, source_id: int, canonicalPath: string}|null Validated document and file.
 */
function docAccessFindDocument(string $tenantRoot, string $requested, string $source, int $sourceId): ?array
{
    $canonical = docAccessCanonicalPath($tenantRoot, $requested);
    if ($canonical === null || docAccessSourcePermission($source) === null || $sourceId <= 0) {
        return null;
    }
    $sourceSql = db_escape_string($source);
    $query = db_select("SELECT id, filepath, filename, source, source_id FROM documents WHERE source = '$sourceSql' AND source_id = '$sourceId'", __FILE__ . ' line ' . __LINE__);
    $rows = [];
    while ($row = db_fetch_array($query)) {
        $rows[] = $row;
    }
    return docAccessMatchingRow($tenantRoot, $canonical, $source, $sourceId, $rows);
}

/** @return never */
function docAccessDeny(): never
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Document access denied';
    exit;
}

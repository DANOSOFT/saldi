<?php
// --- includes/docsIncludes/linkDocuments.php --- 2026-10-26 ---
// Links the documents of one line to another line: each document gets a second documents row
// pointing at the same file. Used by linkDocumentsApi.php (clip-to-clip drag in kassekladde.php)
// and by kassekladde.php when a clip is dropped on the new line, which is saved first.
//
// 20261026 CL/LH Moved here from linkDocumentsApi.php so the save in kassekladde.php can link too.

/**
 * Links every document of $fromSourceId to $toSourceId. A document the target already has is
 * counted as linked and not inserted again.
 *
 * @param int    $fromSourceId Line the documents come from
 * @param int    $toSourceId   Line that gets them
 * @param string $source       documents.source, e.g. 'kassekladde'
 * @param int    $userId       Stored as documents.user_id on the new rows
 * @return array{count: int, errors: list<string>} Documents linked (new or already there) and failed inserts
 */
function linkLineDocuments($fromSourceId, $toSourceId, $source, $userId) {
	$fromSourceId = (int)$fromSourceId;
	$toSourceId   = (int)$toSourceId;
	$userId       = (int)$userId;
	$sourceEsc    = db_escape_string($source);

	$linkedCount = 0;
	$errors = array();
	$qtxt = "SELECT * FROM documents WHERE source = '$sourceEsc' AND source_id = '$fromSourceId'";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($doc = db_fetch_array($q)) {
		$filenameEsc = db_escape_string($doc['filename']);
		$filepathEsc = db_escape_string($doc['filepath']);

		$qtxt = "SELECT id FROM documents WHERE source = '$sourceEsc' AND source_id = '$toSourceId' AND filename = '$filenameEsc' AND filepath = '$filepathEsc'";
		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$linkedCount++;
			continue;
		}
		$globalIdEsc = db_escape_string($doc['global_id']);
		$qtxt = "INSERT INTO documents (global_id, filename, filepath, source, source_id, timestamp, user_id) VALUES ('$globalIdEsc', '$filenameEsc', '$filepathEsc', '$sourceEsc', '$toSourceId', '" . time() . "', '$userId')";
		if (db_modify($qtxt, __FILE__ . " linje " . __LINE__)) {
			$linkedCount++;
		} else {
			$errors[] = "Failed to link: " . $doc['filename'];
		}
	}
	return array('count' => $linkedCount, 'errors' => $errors);
}

<?php
// tests/characterization/support/varesync_stub_router.php
//
// Router for `php -S`, standing in for a shop's API folder in VaresyncTest.
// Files under /files/ are served from the folder the server was started in, which the test
// writes the CSVs to. Every other request, such as the export trigger and the stock
// push from lagerreguler(), gets a plain OK.
//
// History:
// 20261125 CL/SZ MB-145 Created.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($path, '/files/') === 0) {
	$file = getcwd() . '/' . basename($path);
	if (is_file($file)) {
		header('Content-Type: text/csv');
		readfile($file);
		return true;
	}
	http_response_code(404);
	return true;
}
echo 'OK';
return true;

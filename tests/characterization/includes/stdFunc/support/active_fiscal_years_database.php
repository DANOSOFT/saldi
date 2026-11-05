<?php
// 20261023 CL/LH MB-94 Route db_select()/db_fetch_array() to the fixture grupper table set up by
//                  ActiveFiscalYearsTest ($GLOBALS['activeFiscalYearsTestSelect'] and ...Fetch).

if (!function_exists('db_select')) {
	function db_select($query, $source = '') {
		return ($GLOBALS['activeFiscalYearsTestSelect'])($query);
	}
}

if (!function_exists('db_fetch_array')) {
	function db_fetch_array($result) {
		return ($GLOBALS['activeFiscalYearsTestFetch'])($result);
	}
}

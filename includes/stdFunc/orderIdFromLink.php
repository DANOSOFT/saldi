<?php
// 20261124 CL/LH MB-177 One check for the order id an order page reads from its link.

if (!function_exists('order_ids_from_link_are_whole_numbers')) {
	/**
	 * Whether every order id the link carries is a whole number, an empty value or the word null.
	 * The order pages put these ids into SQL as they arrive, so a link with anything else is refused.
	 *
	 * @param array<string, mixed> $get The request's $_GET.
	 * @param array<int, string> $keys The parameters that hold an order id on this page.
	 * @return bool False when one of them holds anything else, an array included.
	 */
	function order_ids_from_link_are_whole_numbers(array $get, array $keys): bool
	{
		foreach ($keys as $key) {
			if (!array_key_exists($key, $get) || $get[$key] === null) {
				continue;
			}
			if (!is_string($get[$key]) && !is_int($get[$key])) {
				return false;
			}
			$value = trim((string) $get[$key]);
			if ($value !== '' && $value !== 'null' && !ctype_digit($value)) {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists('contact_lookup_sort_column')) {
	/**
	 * The ansatte column the contact list sorts by: one of the four headings, otherwise navn.
	 *
	 * @param mixed $sort The sort value from the link.
	 * @return string A fixed column name.
	 */
	function contact_lookup_sort_column($sort): string
	{
		return in_array($sort, array('navn', 'tlf', 'mobil', 'email'), true) ? $sort : 'navn';
	}
}

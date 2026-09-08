<?php
// 20260908 CDX/LH Render optional, display-only performed-by choices (SD-558).

/**
 * Keep blank selectable, including when no active employees remain.
 * Retain historical names without adding duplicate options or HTML markup.
 *
 * @param string|null $selected Stored performer; null means no selection.
 * @param string[] $employees Active employee names.
 * @param string|null $username Current user, offered as an explicit choice only.
 * @return string Escaped option elements with exactly one selected value.
 */
function performedByOptions($selected, array $employees, $username)
{
	$selected = (string)$selected;
	$names = array_unique(array_merge(array('', $selected), $employees, array((string)$username)));
	$html = '';
	foreach ($names as $name) {
		$value = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
		$selectedAttribute = ($name === $selected) ? ' selected' : '';
		$html .= "<option value=\"$value\"$selectedAttribute>$value</option>\n";
	}
	return $html;
}

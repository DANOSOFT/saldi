<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/pageDoctype.php --- ver 5.1.1 --- 2026-11-24 ---
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
// 20261124 CL/SZ MB-143: Created. A page can ask includes/online.php for standards mode instead of quirks mode.

if (!function_exists('saldi_page_doctype')) {
	/**
	 * The doctype line includes/online.php prints at the top of a page.
	 *
	 * The default is the HTML 4.01 Transitional doctype without a system identifier, which puts the
	 * browser in quirks mode. Most pages rely on that: their height="100%" tables, table font sizes and
	 * unitless CSS lengths only work in quirks mode. A page that is built or checked for standards mode
	 * sets $standardsMode = true before it includes online.php and gets <!DOCTYPE html> instead.
	 * See doc/ai/convention_standards_mode.md.
	 *
	 * @param bool $standardsMode True when the page opted in to standards mode.
	 * @return string The doctype, without a line break.
	 */
	function saldi_page_doctype(bool $standardsMode): string {
		if ($standardsMode) {
			return '<!DOCTYPE html>';
		}
		return '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">';
	}
}

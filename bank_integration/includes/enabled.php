<?php
// Include this file wherever bank integration UI should only appear when the
// Enable Banking feed is configured on the server and switched on for the company.
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/enabled.php --- ver 5.1.1 --- 2026-10-07 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20260915 CL/NTR Initial version. bankIntegrationEnabled() tells whether the
//                 bank integration API credentials are configured.
// 20261006 CL/LH Aiia removed: bankIntegrationAvailable() = Enable Banking configured on the server, bankIntegrationEnabled() = available, tenant schema ready and switched on for the company.

require_once __DIR__ . '/ebAccess.php';

if (!function_exists('bankIntegrationEnabled')) {
	/**
	 * Whether the Enable Banking application is configured on this server (app id, redirect URL and a
	 * readable key outside the installation). Decides whether the settings section is offered at all.
	 * Requires includes/connect.php to be loaded by the caller.
	 *
	 * @return bool
	 */
	function bankIntegrationAvailable(): bool {
		static $available = null;
		if ($available === null) {
			try {
				$available = ebFeatureAvailable();
			} catch (Throwable $e) {
				$available = false;
			}
		}
		return $available;
	}

	/**
	 * Whether the bank feed is usable in the current company: configured on the server, the tenant
	 * tables exist and the company has switched it on. Callers use this to show the journal button,
	 * the status icon and the feed pages.
	 *
	 * @return bool
	 */
	function bankIntegrationEnabled(): bool {
		static $enabled = null;
		if ($enabled === null) {
			try {
				$enabled = bankIntegrationAvailable() && ebSchemaReady() && ebCompanyEnabled();
			} catch (Throwable $e) {
				$enabled = false;
			}
		}
		return $enabled;
	}
}

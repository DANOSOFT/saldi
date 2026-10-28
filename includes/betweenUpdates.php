<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.0.0 --- 2026.10.28
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261027 NTR New Release - Moved everything into opdat_5.0.php
// 20261021 Sawaneh SD-733: Shopify schema manifest v1 (includes/shopify/schema.php) ensured at the end of the file.
// 20261026 CL/SZ SD-758: delete the cached 5439-5441 rows that still hold the POS brand texts, which moved to 7200-7202.
// 20261028 CL/SZ Sync of master into hml/shopify: the two hml/shopify steps above stay here after the 5.1.0 release emptied the file.

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// ===== END PROTECTED =====

// 20261021 Sawaneh SD-733: Shopify schema manifest v1. The catalog lives in includes/shopify/schema.php and is
// shared with admin/opret.php; ensure_shopify_schema() returns at once when the marker already shows the
// current version, and does nothing on MySQL tenants (the Shopify module is PostgreSQL only).
include_once(__DIR__ . '/shopify/schema.php');
ensure_shopify_schema();

// 20261026 CL/SZ SD-758: the POS brand texts were added as 5439-5441, which master uses for the kassekladde
// voucher alert (5439) and the bogfor.php posting refusal (5440/5441), and have moved to 7200-7202. A tenant
// that opened POS-valg on hml/shopify still has the brand texts cached under the old ids, so the finance
// pages would show them. Only rows still holding a brand text are deleted; findtekst() re-seeds them.
deleteStaleTekst(5439, 'Brand');
deleteStaleTekst(5440, array(
	'Brand salget oprettes med på denne kasse. Gemmes som ordrens sprog/formularvariant',
	"Brand a sale is created with on this terminal. Stored as the order's language/form variant",
	'Brand salget opprettes med på denne kassen. Lagres som ordrens språk/formularvariant'
));
deleteStaleTekst(5441, array('Ikke gemt, mangler formularvariant', 'Not saved, no matching form variant', 'Ikke lagret, mangler formularvariant'));

?>

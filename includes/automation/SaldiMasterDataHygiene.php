<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiMasterDataHygiene.php --- ai/mcp-assistant --- 2026.10.05
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
//
// Shared read-only SQL findings for the master-data tool and weekly routine.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261005 CL/LH Duplicate parties counted with window functions (no repeated normalisation).
// 20261005 CL/LH Findings and their total in one query.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../tools/SaldiToolRuntime.php';

final class SaldiMasterDataHygiene
{
	/** @return array{rows:list<array{kind:string,label:string,screen:string}>,total:int} */
	public static function scan(SaldiTenantContext $ctx, SaldiDb $db, int $limit = 100): array
	{
		SaldiPermissionService::require($ctx, $db, 'company.read');
		$cvr = static fn(string $alias): string => "REPLACE(REPLACE(REPLACE(LOWER(TRIM(COALESCE($alias.cvrnr,''))),'dk',''),' ',''),'-','')";
		$name = static function (string $alias): string {
			$sql = "LOWER(TRIM(COALESCE($alias.firmanavn,'')))";
			foreach (["'.'", "','", "'-'", "CHR(9)", "CHR(10)"] as $char) {
				$sql = "REPLACE($sql,$char,' ')";
			}
			// Match the party draft's company-suffix normalization as well as case and spacing.
			$sql = "REGEXP_REPLACE($sql,'(^|[[:space:]])(aps|a/s|i/s|ivs)($|[[:space:]])',' ')";
			for ($i = 0; $i < 6; $i++) {
				$sql = "REPLACE($sql,'  ',' ')";
			}
			return "TRIM($sql)";
		};
		$na = $name('a');
		if (!$db->isPostgres()) {
			$na = str_replace('CHR(', 'CHAR(', $na);
		}
		$ca = $cvr('a');
		$group = "g.art=CASE WHEN a.art='D' THEN 'DG' ELSE 'KG' END AND g.kodenr=a.gruppe AND g.fiscal_year=?";
		$checks = [
			// Normalise each party once and count duplicates with window functions; the
			// earlier IN-subquery form re-normalised every row and timed out at a few thousand.
			'duplicate_party' => ["a.id IN (SELECT d.id FROM (SELECT x.id, x.cv, x.nm, COUNT(*) OVER (PARTITION BY x.cv) AS cv_count, COUNT(*) OVER (PARTITION BY x.nm) AS nm_count FROM (SELECT a.id, $ca AS cv, $na AS nm FROM adresser a WHERE a.art IN ('D','K')) x) d WHERE (d.cv<>'' AND d.cv_count>1) OR (d.nm<>'' AND d.nm_count>1))", [], 6260, 'Mulig dublet'],
			'missing_cvr' => ["a.art='D' AND $ca='' AND EXISTS (SELECT 1 FROM grupper g WHERE g.art='DG' AND g.kodenr=a.gruppe AND g.fiscal_year=? AND g.box8='on')", [$ctx->fiscalYear], 6261, 'CVR mangler'],
			'missing_email' => ["TRIM(COALESCE(a.email,''))=''", [], 6262, 'E-mail mangler'],
			'invalid_group' => ["NOT EXISTS (SELECT 1 FROM grupper g WHERE $group)", [$ctx->fiscalYear], 6263, 'Ugyldig gruppe'],
			'closed_party_referenced' => ["a.lukket='on' AND (EXISTS (SELECT 1 FROM ordrer o WHERE o.konto_id=a.id AND o.status<3) OR EXISTS (SELECT 1 FROM kassekladde l JOIN kladdeliste j ON j.id=l.kladde_id WHERE COALESCE(j.bogfort,'-') IN ('-','!') AND ((l.d_type=a.art AND " . SaldiToolRuntime::cast($db, 'CAST(l.debet AS DECIMAL(30,0))') . '=a.kontonr) OR (l.k_type=a.art AND ' . SaldiToolRuntime::cast($db, 'CAST(l.kredit AS DECIMAL(30,0))') . '=a.kontonr' . '))))', [], 6264, 'Lukket kunde eller leverandør på åben kladde eller ordre'],
		];
		$queries = $params = [];
		$labels = [];
		foreach ($checks as $kind => [$condition, $bound, $id, $fallback]) {
			$queries[] = "SELECT '$kind' AS kind, a.id, a.art, " . SaldiToolRuntime::cast($db, 'a.kontonr') . " AS number, a.firmanavn AS name FROM adresser a WHERE a.art IN ('D','K') AND " . $condition;
			array_push($params, ...$bound);
			$labels[$kind] = SaldiText::get($db, $id, $ctx->languageId, $fallback);
		}
		foreach ([
			'missing_cost_price' => ['COALESCE(v.kostpris,0)<=0', 6265, 'Kostpris mangler'],
			'below_cost_price' => ['v.salgspris<v.kostpris', 6266, 'Salgspris er lavere end kostpris'],
			'closed_product_on_order' => ["v.lukket='on' AND EXISTS (SELECT 1 FROM ordrelinjer l JOIN ordrer o ON o.id=l.ordre_id WHERE l.vare_id=v.id AND o.status<3)", 6267, 'Lukket vare på åben ordre'],
		] as $kind => [$condition, $id, $fallback]) {
			$queries[] = "SELECT '$kind' AS kind, v.id, 'V' AS art, v.varenr AS number, v.beskrivelse AS name FROM varer v WHERE " . $condition;
			$labels[$kind] = SaldiText::get($db, $id, $ctx->languageId, $fallback);
		}
		$sql = implode(' UNION ALL ', $queries);
		// One pass: the total rides along as a window count instead of a second full union.
		$rows = $db->all('SELECT findings.*, COUNT(*) OVER () AS findings_total FROM (' . $sql . ') findings ORDER BY kind, id LIMIT ?', array_merge($params, [max(1, min(100, $limit))]));
		$total = $rows ? (int)$rows[0]['findings_total'] : 0;
		return ['total' => $total, 'rows' => array_map(static fn(array $r): array => ['kind' => $r['kind'], 'label' => SaldiToolRuntime::text($labels[$r['kind']] . ': ' . $r['number'] . ' ' . $r['name']), 'screen' => SaldiScreens::link(['D' => 'debtor', 'K' => 'creditor', 'V' => 'product'][$r['art']], (int)$r['id'])], $rows)];
	}
}

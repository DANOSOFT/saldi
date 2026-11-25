<?php
// 20261106 CDX/NTR Require planning rights and validate Gantt date updates through a shared handler.
// 20261110 CDX/NTR Keep an error status when a database failure terminates the save.

/**
 * Injected by ../includes/online.php, included below:
 * @var string|null $sag_rettigheder
 */

session_start();
$s_id = session_id();
$bg = 'nix';
$header = 'nix';
$modulnr = 0;

include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/online.php');
include_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/sagerIncludes/saveGanttPlanDates.php');

// db_modify() can exit on failure before the handler returns a status.
http_response_code(500);
http_response_code(saveGanttPlanDates($_POST, $sag_rettigheder ?? null));

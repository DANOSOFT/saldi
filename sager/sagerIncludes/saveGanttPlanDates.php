<?php
// 20261106 CDX/NTR Centralize authorization and input validation for all Gantt date save paths.
// 20261110 CDX/NTR Normalize and order planning dates and roll back combined saves on failure.

/**
 * Save Gantt dates only for users with the Sager/Planlægning permission.
 * The legacy montage endpoint also accepts ISO input and uses op to select its columns.
 *
 * @param array<string, mixed> $post Submitted Gantt fields.
 * @param string|null $sagRettigheder Rights loaded by online.php for the logged-in user's employee group.
 * @param bool $separateMontageDates Whether to update montage/demontage instead of case/task dates.
 * @return int HTTP status: 200 on success, 403 without permission, 400 for invalid input, 500 on write failure.
 */
function saveGanttPlanDates(array $post, ?string $sagRettigheder, bool $separateMontageDates = false): int
{
    if (substr($sagRettigheder ?? '', 2, 1) !== '1') {
        return 403;
    }

    $options = ['options' => ['min_range' => 0]];
    $id = filter_var($post['id'] ?? 0, FILTER_VALIDATE_INT, $options);
    $opgId = filter_var($post['opg_id'] ?? 0, FILTER_VALIDATE_INT, $options);
    $op = $post['op'] ?? null;
    if ($id === false || $opgId === false || (!$id && !$opgId)) {
        return 400;
    }
    if ($separateMontageDates && (!$id || !in_array($op, ['0', '1'], true))) {
        return 400;
    }

    $planfra = ganttPlanDate($post['planfra'] ?? null, $separateMontageDates);
    $plantil = ganttPlanDate($post['plantil'] ?? null, $separateMontageDates);
    if ($planfra === null || $plantil === null || $plantil < $planfra) {
        return 400;
    }

    $id = intval($id);
    $opgId = intval($opgId);
    $planfra = db_escape_string($planfra);
    $plantil = db_escape_string($plantil);
    $queries = [];
    if ($separateMontageDates) {
        if ($op === '1') {
            $queries[] = "update sager set planfraop='$planfra', plantilop='$plantil' where id = '$id'";
        } else {
            $queries[] = "update sager set planfraned='$planfra', plantilned='$plantil' where id = '$id'";
        }
    } else {
        if ($id > 0) {
            $queries[] = "update sager set planfraop='$planfra', plantilop='$plantil' where id = '$id'";
        }
        if ($opgId > 0) {
            $queries[] = "update opgaver set opg_planfra='$planfra', opg_plantil='$plantil' where id = '$opgId'";
        }
    }

    $combined = count($queries) > 1;
    if ($combined) {
        transaktion('begin');
    }
    try {
        foreach ($queries as $query) {
            $result = db_modify($query, __FILE__ . ' linje ' . __LINE__);
            if (substr($result, 0, 2) !== "0\t") {
                if ($combined) {
                    transaktion('rollback');
                }
                return 500;
            }
        }
        if ($combined) {
            transaktion('commit');
        }
    } catch (Throwable $error) {
        if ($combined) {
            transaktion('rollback');
        }
        error_log('Gantt date save failed: ' . $error->getMessage());
        return 500;
    }

    return 200;
}

/**
 * Validate the Gantt client's dd-MM-yyyy dates without usdate() repairing malformed input.
 *
 * @param mixed $value Submitted date.
 * @param bool $allowIsoInput Also accept legacy montage input in ISO format.
 * @return string|null Validated ISO date, or null for invalid input.
 */
function ganttPlanDate($value, bool $allowIsoInput): ?string
{
    if (!is_string($value)) {
        return null;
    }
    if (preg_match('/^([0-9]{2})-([0-9]{2})-([0-9]{4})$/D', $value, $parts)) {
        [, $day, $month, $year] = $parts;
    } elseif ($allowIsoInput && preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)) {
        [, $year, $month, $day] = $parts;
    } else {
        return null;
    }
    if (!checkdate((int) $month, (int) $day, (int) $year)) {
        return null;
    }

    return "$year-$month-$day";
}

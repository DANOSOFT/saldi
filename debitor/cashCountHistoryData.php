<?php
// --- debitor/cashCountHistoryData.php --- 2026-09-18 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20260917 CDX/PHR Reconstruct saved cash counts and reconcile historical decimal errors.
// 20260917 CDX/PHR Persist unambiguous decimal repairs atomically before presentation.
// 20260917 CL/LH Keep manual-control warnings when no decimal repair was persisted.
// 20260918 CDX/PHR Ignore zero payment methods when bounding decimal repair combinations.

/**
 * Work on a copy only. report.total holds piece counts for denominations.
 * Decimal repair is restricted to the old Danish, single-currency report layout.
 * Manually adjusted cards and unknown layouts must never be inferred.
 * @return array{rows: array, warning: string}
 */
function cashCountHistoryPrepare(array $rows, $date)
{
    $result = ['rows' => $rows, 'warning' => ''];
    $start = null;
    foreach ($rows as $i => $row) {
        if (trim($row['description']) === 'Dagens omsætning:') {
            if ($start !== null) {
                $result['warning'] = 'Rapporten indeholder flere optællinger. Decimaler er ikke korrigeret.';
                return $result;
            }
            $start = $i;
        }
    }
    if ($start === null) {
        $result['warning'] = 'Dette rapportformat kan ikke kontrolleres automatisk for decimalfejl.';
        return $result;
    }
    $patterns = ['/^Dagens omsætning:$/u', '/^Morgenbeholdning$/', '/^Dagens tilgang:$/',
        '/^Forventet beholdning DKK:$/', '/^Optalt beholdning DKK:$/', '/^Difference DKK:$/',
        '/^Udtaget fra kasse\s*\d*\s*DKK:?$/'];
    foreach ($patterns as $offset => $pattern) {
        if (!isset($rows[$start + $offset]) || !preg_match($pattern, trim($rows[$start + $offset]['description']))) {
            $result['warning'] = 'Rapportens valuta eller opbygning kræver manuel kontrol af decimaler.';
            return $result;
        }
    }
    $payments = array_slice($rows, $start + 7, null, true);
    if (!$payments) {
        return $result;
    }
    foreach ($payments as $row) {
        if (preg_match('/[()]|^(Morgenbeholdning|Dagens tilgang|Forventet|Optalt|Difference|Udtaget)/u', $row['description'])) {
            $result['warning'] = 'Valuta eller manuelt ændrede kortbeløb: decimaler er ikke korrigeret.';
            return $result;
        }
    }
    $target = (int)round(((float)$rows[$start]['total'] - (float)$rows[$start + 2]['total']) * 100);
    $sum = 0;
    foreach ($payments as $row) {
        $sum += (int)round((float)$row['total'] * 100);
    }
    if ($sum === $target) {
        return $result;
    }
    $result['warning'] = 'Betalingsbeløbene stemmer ikke med omsætning minus kontanttilgang. Kontrollér originalbilaget.';
    if ($date >= '2026-09-02') {
        return $result;
    }
    // Old usdecimal(raw PHP float) removed the decimal point. Only integer
    // stored amounts can originate from that bug. Count all matching solutions.
    $variablePayments = 0;
    foreach ($payments as $row) {
        $cents = (int)round((float)$row['total'] * 100);
        if ($cents !== 0 && $cents % 100 === 0) {
            $variablePayments++;
        }
    }
    if ($variablePayments > 8) {
        return $result;
    }
    $solutions = [[]];
    foreach ($payments as $index => $row) {
        $cents = (int)round((float)$row['total'] * 100);
        $options = [$cents];
        if ($cents !== 0 && $cents % 100 === 0) {
            $options[] = intdiv($cents, 10);
            $options[] = intdiv($cents, 100);
        }
        $next = [];
        foreach ($solutions as $solution) {
            foreach (array_unique($options) as $value) {
                $next[] = $solution + [$index => $value];
            }
        }
        $solutions = $next;
    }
    $matches = [];
    foreach ($solutions as $solution) {
        if (array_sum($solution) === $target) {
            $matches[] = $solution;
        }
    }
    if (count($matches) !== 1) {
        return $result;
    }
    foreach ($matches[0] as $index => $cents) {
        if ($cents !== (int)round((float)$rows[$index]['total'] * 100)) {
            $result['rows'][$index]['original'] = $rows[$index]['total'];
            $result['rows'][$index]['total'] = $cents / 100;
        }
    }
    $result['warning'] = 'Markerede decimalrettelser er beregnet ud fra omsætning og kontanttilgang. Databasen er uændret. Kontrollér mod originalbilaget.';
    return $result;
}

function cashCountHistoryEscape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Execute a report repair statement, checking SALDI's status return. */
function cashCountHistoryModify($sql)
{
    $status = db_modify($sql, __FILE__ . ' cash count repair');
    if (substr((string)$status, 0, 2) !== "0\t") {
        throw new RuntimeException('Kunne ikke gemme kasseoptællingen. Prøv igen.');
    }
}

/**
 * Lock and repair only the selected report in the authenticated account.
 * Repeated views are idempotent: already reconciled totals produce no updates.
 * No ledger entries, cash balances or POS events are changed.
 * @return array{rows: array, warning: string}
 */
function cashCountHistoryLoadAndRepair($reportNumber, $date)
{
    $reportNumber = (int)$reportNumber;
    $savedDate = db_escape_string($date);
    $scope = "report_number=$reportNumber and date='$savedDate' and type='cashCount'";
    cashCountHistoryModify('BEGIN');
    try {
        $rows = [];
        $query = db_select("select id,description,total from report where $scope order by id for update", __FILE__ . ' lock count');
        if (!$query) {
            throw new RuntimeException('Kunne ikke hente kasseoptællingen.');
        }
        while ($row = db_fetch_array($query)) {
            $rows[] = $row;
        }
        $data = cashCountHistoryPrepare($rows, $date);
        $repaired = false;
        foreach ($data['rows'] as &$row) {
            if (!isset($row['original'])) {
                continue;
            }
            $id = (int)$row['id'];
            $total = number_format((float)$row['total'], 2, '.', '');
            $original = number_format((float)$row['original'], 2, '.', '');
            cashCountHistoryModify("update report set total=$total where id=$id and $scope and total=$original");
            $check = db_fetch_array(db_select("select total from report where id=$id and $scope", __FILE__ . ' verify repair'));
            if (!$check || (int)round((float)$check['total'] * 100) !== (int)round((float)$row['total'] * 100)) {
                throw new RuntimeException('Kunne ikke gemme kasseoptællingen. Prøv igen.');
            }
            unset($row['original']);
            $repaired = true;
        }
        unset($row);
        cashCountHistoryModify('COMMIT');
        // Only the "calculated, database unchanged" notice from cashCountHistoryPrepare()
        // becomes obsolete once a repair is stored. Warnings asking for manual control of
        // the report must survive and be shown to the user.
        if ($repaired) {
            $data['warning'] = '';
        }
        return $data;
    } catch (Throwable $error) {
        db_modify('ROLLBACK', __FILE__ . ' rollback count repair');
        throw $error;
    }
}

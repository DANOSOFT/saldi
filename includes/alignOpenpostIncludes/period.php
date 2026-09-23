<?php
// --- includes/alignOpenpostIncludes/period.php --- 2026-09-23 ---
// Copyright (c) 2026 Danosoft ApS
// Distributed under the GNU General Public License, version 2 or later.
// 20260923 CDX/PHR Add bounded month selection for open-post settlement.

/**
 * Resolve whole-month bounds and constrain the requested period to open posts.
 *
 * @return array{from: string, to: string, start: string, end: string, months: array<string, string>}
 */
function openpostSettlementPeriod($firstDate, $lastDate, $requestedFrom, $requestedTo, $fallbackDate)
{
    $first = new DateTimeImmutable(substr($firstDate ?: $fallbackDate, 0, 7) . '-01');
    $last = new DateTimeImmutable(substr($lastDate ?: $fallbackDate, 0, 7) . '-01');
    $monthNames = ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni',
        'Juli', 'August', 'September', 'Oktober', 'November', 'December'];
    $months = [];
    for ($month = $first; $month <= $last; $month = $month->modify('+1 month')) {
        $months[$month->format('Y-m')] = $monthNames[(int)$month->format('n') - 1] . ' ' . $month->format('Y');
    }
    $from = is_string($requestedFrom) && isset($months[$requestedFrom]) ? $requestedFrom : $first->format('Y-m');
    $to = is_string($requestedTo) && isset($months[$requestedTo]) ? $requestedTo : $last->format('Y-m');
    if ($from > $to) {
        $to = $from;
    }
    return [
        'from' => $from,
        'to' => $to,
        'start' => $from . '-01',
        'end' => (new DateTimeImmutable($to . '-01'))->modify('+1 month')->format('Y-m-d'),
        'months' => $months,
    ];
}

/**
 * Build the shared candidate query for the list and automatic matching.
 *
 * @param array{start: string, end: string} $period
 * @return string
 */
function openpostSettlementCandidateQuery($accountId, $postId, array $period)
{
    return "SELECT * FROM openpost WHERE konto_id=" . (int)$accountId . " AND id<>" . (int)$postId
        . " AND COALESCE(udlignet,'0')<>'1' AND transdate>='" . db_escape_string($period['start'])
        . "' AND transdate<'" . db_escape_string($period['end']) . "' ORDER BY transdate,id";
}

/** Render period changes as read-only requests, separate from settlement actions. */
function renderOpenpostSettlementPeriod(array $period, array $context)
{
    echo "<tr><td colspan='6'><form method='get' action='../includes/udlign_openpost.php'>";
    foreach ($context as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" value="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '">';
    }
    foreach (['from' => 'Fra måned', 'to' => 'Til måned'] as $side => $label) {
        echo '<label for="period_' . $side . '">' . $label . '</label> ';
        echo '<select id="period_' . $side . '" name="period_' . $side . '" onchange="'
            . "var f=this.form.elements.period_from,t=this.form.elements.period_to;"
            . ($side === 'from' ? 'if(f.value>t.value)t.value=f.value;' : 'if(t.value<f.value)f.value=t.value;')
            . 'this.form.submit();">';
        foreach ($period['months'] as $value => $text) {
            echo '<option value="' . $value . '"' . ($value === $period[$side] ? ' selected' : '') . '>' . $text . '</option>';
        }
        echo '</select> &nbsp; ';
    }
    echo "<noscript><button type='submit'>Vis periode</button></noscript></form></td></tr>";
}

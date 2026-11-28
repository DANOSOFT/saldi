<?php
// 20261110 CDX/ASR Restrict report dispatch and allow only known read-only report form submissions.

/**
 * Maps the account report's public names to its fixed set of renderers.
 *
 * @param mixed $name Request or saved report name.
 * @return string|null Canonical renderer name, or null for an unknown/non-scalar name.
 */
function account_report_name($name): ?string
{
    if (!is_string($name)) {
        return null;
    }
    $names = [
        '' => '', 'forside' => 'forside', 'openpost' => 'openpost',
        'kontokort' => 'kontokort', 'kontosaldo' => 'kontosaldo',
        'accountchart' => 'accountChart', 'åben post' => 'openpost',
    ];
    return $names[strtolower(trim($name))] ?? null;
}

/**
 * Only report selection/date/account filters are read POSTs. Reminder, settlement,
 * mail and unknown future actions keep the default write requirement.
 *
 * @param array<string, mixed> $post Submitted form fields.
 * @return bool Whether this submission only selects or filters a report.
 */
function account_report_post_is_read(array $post): bool
{
    $fields = [
        'dato', 'konto', 'husk', 'openpost', 'kontosaldo', 'kontokort', 'accountChart',
        'salgsstat', 'saft', 'startDate', 'endDate', 'rapportart', 'submit',
        'dato_fra', 'dato_til', 'konto_fra', 'konto_til', 'kontonr',
        'openpost_content', 'kontosaldo_content', 'kontokort_content', 'accountChart_content',
    ];
    foreach ($post as $field => $value) {
        if (!in_array($field, $fields, true) || !is_scalar($value)) {
            return false;
        }
    }
    if (isset($post['submit']) && !in_array(strtolower(trim((string)$post['submit'])), ['', 'ok'], true)) {
        return false;
    }
    return !isset($post['rapportart']) || account_report_name($post['rapportart']) !== null;
}

/**
 * Dispatches exclusively to a report renderer, never to a function named by input.
 *
 * @param mixed $name Report name.
 * @param mixed ...$arguments Existing renderer arguments, including account type D/K.
 * @return mixed The renderer's result.
 */
function account_report_dispatch($name, ...$arguments)
{
    $renderer = account_report_name($name);
    if ($renderer === null || $renderer === '') {
        http_response_code(400);
        return null;
    }
    if ($renderer === 'accountChart') {
        require_once __DIR__ . '/reportFunc/accountChart.php';
    }
    return $renderer(...$arguments);
}

<?php
// 20260907 CDX/LH Pure checks and characterization of the extracted legacy predicates.
// 20260908 CDX/LH Cover Apache/CGI header handling and conflicting credentials.
// 20260908 CDX/LH Characterize sub-cent voucher differences independently of totals.
require_once __DIR__ . '/../../includes/assist/RecordRules.php';
require_once __DIR__ . '/../../includes/assist/RecordAuth.php';

set_error_handler(static function (int $severity, string $message): void { throw new RuntimeException($message); });
$checks = 0;
/** Count an assertion and stop immediately with its non-sensitive test label. */
function expect_assist(bool $ok, string $label): void
{
    global $checks;
    $checks++;
    if (!$ok) {
        throw new RuntimeException($label);
    }
}
foreach ([0, 0.009, 0.01, -0.01, 10] as $difference) {
    foreach ([[], [4 => 1.0]] as $vouchers) {
        expect_assist(saldi_assist_has_differences($difference, $vouchers) === (abs($difference) >= 0.01 || count($vouchers)), 'legacy balance predicate');
    }
}
foreach ([null, '', '0', '1', '25.00'] as $paid) {
    foreach ([false, true] as $link) {
        foreach (['', 'on'] as $lock) {
            foreach (['', 'Magento'] as $reference) {
                foreach (['Konto', 'Kontant', 'Kort'] as $method) {
                    $disabled = '';
                    if (!$paid && $link && $lock === 'on') { $disabled = 'disabled'; }
                    if ($reference === 'Magento' || $method === 'Konto' || $method === 'Kontant') { $disabled = ''; }
                    expect_assist(saldi_assist_invoice_payment_locked($paid, $link, $lock, $reference, $method) === (bool)$disabled, 'legacy payment predicate');
                }
            }
        }
    }
}
$reference = ['period' => ['start' => '2026-01-01', 'end' => '2026-12-31'], 'closed_months' => [],
    'accounts' => ['F:1000' => ['kontotype' => 'D', 'lukket' => '', 'moms' => ''], 'F:2000' => ['kontotype' => 'S', 'lukket' => '', 'moms' => '']],
    'groups' => [], 'rates' => []];
$row = ['id' => 1, 'bilag' => 1, 'transdate' => '2026-09-01', 'amount' => '100.005', 'debet' => 1000, 'kredit' => 0, 'd_type' => 'F', 'k_type' => 'F'];
$rows = [$row, array_merge($row, ['id' => 2, 'debet' => 0, 'kredit' => 2000])];
$valid = saldi_assist_validate_journal(['bogfort' => '-'], $rows, $reference);
expect_assist($valid['status'] === 'checks_passed', 'one-sided rows balance per voucher');
expect_assist($valid['coverage']['can_post'] === null, 'preliminary check is not posting approval');
$bad = $rows;
$bad[1]['bilag'] = 2;
$validation = saldi_assist_validate_journal(['bogfort' => '-'], $bad, $reference);
expect_assist(count($validation['differences']) === 2, 'opposite voucher differences must not cancel');
expect_assist($validation['differences'][0]['row_ids'] === [1], 'stable affected row IDs');
expect_assist($validation['differences'][0]['difference'] === '100.005', 'stored three-decimal amount preserved');
// bogfor.php rounds individual amounts with afrund(..., 2), then passes diffbilag
// to the final predicate. These simple two-row vouchers establish its boundaries.
foreach (['004' => false, '005' => true, '009' => true, '010' => true] as $fraction => $blocked) {
    foreach ([1, -1] as $sign) {
        $boundaryRows = $rows;
        $boundaryRows[0]['amount'] = $sign > 0 ? '100.' . $fraction : '100.000';
        $boundaryRows[1]['amount'] = $sign < 0 ? '100.' . $fraction : '100.000';
        $result = saldi_assist_validate_journal(['bogfort' => '-'], $boundaryRows, $reference);
        $difference = $sign * (int)$fraction / 1000;
        $legacyVoucherDifferences = $blocked ? [1 => $sign * 0.01] : [];
        expect_assist(saldi_assist_has_differences($difference, $legacyVoucherDifferences) === $blocked, 'legacy sub-cent voucher decision');
        expect_assist(($result['status'] === 'blocked') === $blocked, 'assistant preserves sub-cent voucher decision');
        expect_assist($result['difference_count'] === ($blocked ? 1 : 0), 'voucher finding at rounding boundary');
    }
}
$reference['closed_months'] = ['2026-09'];
expect_assist(in_array('period_closed', array_column(saldi_assist_validate_journal([], $rows, $reference)['issues'], 'code'), true), 'period lock');
$reference['closed_months'] = [];
$rows[0]['valuta'] = 1;
expect_assist(in_array('exchange_rate_missing', array_column(saldi_assist_validate_journal([], $rows, $reference)['issues'], 'code'), true), 'missing FX rate');
$rows[0]['valuta'] = 0;
$rows[0]['debetvat'] = 'S1';
expect_assist(in_array('vat_setup_missing', array_column(saldi_assist_validate_journal([], $rows, $reference)['issues'], 'code'), true), 'missing VAT configuration');
expect_assist(saldi_assist_milli('-12.003') === -12003, 'negative decimals');

$secret = bin2hex(random_bytes(32));
$online = ['session_id' => bin2hex(random_bytes(16)), 'db' => 'test_company', 'brugernavn' => 'test_user'];
$claims = SaldiAssistRecordAuth::claims($online, 'journal', 17, str_repeat('a', 32), $secret, time());
$token = SaldiAssistRecordAuth::sign($claims, 'test', $secret);
expect_assist(saldi_assist_authorization(['HTTP_AUTHORIZATION' => $token], []) === $token, 'CGI authorization');
expect_assist(saldi_assist_authorization([], ['Authorization' => $token]) === $token, 'Apache authorization');
expect_assist(saldi_assist_authorization([], ['authorization' => $token]) === $token, 'lowercase authorization');
expect_assist(saldi_assist_authorization(['HTTP_AUTHORIZATION' => $token], ['Authorization' => $token]) === $token, 'same header through both APIs');
expect_assist(saldi_assist_authorization(['HTTP_AUTHORIZATION' => $token], ['Authorization' => $token . 'x']) === '', 'conflicting headers fail closed');
expect_assist(saldi_assist_authorization([], ['X-Forwarded-Authorization' => $token]) === '', 'forwarded header does not authenticate');
expect_assist(SaldiAssistRecordAuth::verify($token, 'test', $secret, time()) === $claims ||
    SaldiAssistRecordAuth::verify($token, 'test', $secret, time()) == $claims, 'grant round trip');
expect_assist(strpos($token, $online['session_id']) === false, 'no raw session cookie in grant');
foreach (['signature', 'expired', 'company', 'user', 'session'] as $case) {
    try {
        if ($case === 'signature') { SaldiAssistRecordAuth::verify($token . 'x', 'test', $secret, time()); }
        elseif ($case === 'expired') { SaldiAssistRecordAuth::verify($token, 'test', $secret, time() + 300); }
        else {
            $changed = $online;
            $field = ['company' => 'db', 'user' => 'brugernavn', 'session' => 'session_id'][$case];
            $changed[$field] .= '_changed';
            SaldiAssistRecordAuth::assertSession($claims, $changed, $secret);
        }
        expect_assist(false, 'grant must reject ' . $case);
    } catch (SaldiAssistFailure $error) { expect_assist(true, 'grant rejected ' . $case); }
}
echo "PASS $checks assertions\n";

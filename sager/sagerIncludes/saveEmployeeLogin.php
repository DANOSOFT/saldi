<?php
// 20261110 CDX/NTR Keep existing employee passwords on blank saves and validate new logins before writing.
// 20261113 CDX/NTR Preserve the roles permission check when changing or linking an employee login.

/**
 * Save an employee's login, leaving its stored password alone when both password fields are blank.
 *
 * @param int $employeeId Saved employee ID.
 * @param int $fiscalYear Current fiscal year, or zero to use the newest active year for a new login.
 * @param array{brugere_navn?: string, kode1?: string, kode2?: string} $input Submitted login fields.
 * @param int $languageId Language for validation messages.
 * @return string|null A validation message, or null on success or when no login was requested.
 */
function saveEmployeeLogin(int $employeeId, int $fiscalYear, array $input, int $languageId): ?string
{
    $username = (string) ($input['brugere_navn'] ?? '');
    $password = (string) ($input['kode1'] ?? '');
    $confirmation = (string) ($input['kode2'] ?? '');
    if (is_input_too_long($username)) {
        return findtekst('5149|Brugernavnet må højst være 80 tegn', $languageId);
    }

    // Read the saved employee's login rather than trusting the hidden user ID in the form.
    $login = db_fetch_array(db_select("select * from brugere where ansat_id='$employeeId'", __FILE__ . ' linje ' . __LINE__));
    $userId = (int) ($login['id'] ?? 0);
    if ($username === '' && !$userId) {
        return null;
    }
    if ($username !== '') {
        $nameSql = db_escape_string(strtolower($username));
        $namedLogin = db_fetch_array(db_select("select * from brugere where lower(brugernavn)='$nameSql'", __FILE__ . ' linje ' . __LINE__));
        if ($namedLogin && (int) $namedLogin['id'] !== $userId) {
            if ($userId || !empty($namedLogin['ansat_id'])) {
                return findtekst('1928|Der findes allerede en bruger med dette brugernavn', $languageId) . ': ' . $username;
            }
            $login = $namedLogin;
            $userId = (int) $namedLogin['id'];
        }
    }

    $changePassword = $password !== '' || $confirmation !== '';
    if (($changePassword || empty($login['kode'])) && ($password === '' || $confirmation === '')) {
        return findtekst('3077|Kodeord skal angives', $languageId) . '!';
    }
    if ($changePassword && $password !== $confirmation) {
        return findtekst('3078|Kodeord ikke ens', $languageId) . '!';
    }

    if ($userId) {
        $changesLogin = $changePassword || (int) $login['ansat_id'] !== $employeeId
            || ($username !== '' && $username !== $login['brugernavn']);
        if (!$changesLogin) {
            return null;
        }
        // The employee page loads userFunctions.php before calling this helper.
        if (function_exists('user_may_manage') && !user_may_manage($login)) {
            return findtekst('5548|Du har ikke adgang til denne funktion. Kontakt din administrator.', $languageId);
        }
        $updates = ["ansat_id='$employeeId'"];
        if ($username !== '') {
            $updates[] = "brugernavn='" . db_escape_string($username) . "'";
        }
        if ($changePassword) {
            $updates[] = "kode='" . db_escape_string(md5($password)) . "'";
        }
        db_modify('update brugere set ' . implode(', ', $updates) . " where id='$userId'", __FILE__ . ' linje ' . __LINE__);
    } else {
        if (!$fiscalYear) {
            $fiscalYear = newest_active_fiscal_year();
        }
        $yearSql = $fiscalYear ? "'$fiscalYear'" : 'NULL';
        $query = 'insert into brugere (brugernavn, kode, ansat_id, regnskabsaar) values ';
        $query .= "('" . db_escape_string($username) . "', '" . db_escape_string(md5($password)) . "', '$employeeId', $yearSql)";
        db_modify($query, __FILE__ . ' linje ' . __LINE__);
    }
    return null;
}

<?php
//
// strict_types is off on purpose: eval'd code inherits the calling file's setting, and the lifted function runs as on the page.

use PHPUnit\Framework\TestCase;

/**
 * A successful restore (admin/restore.php restore()) ends on the login page with the restore notice (testlab round 1,
 * backup-restore-roundtrip, 2026-11-05).
 *
 * The lab saw no completion message on either leg. restore() read $popup, which is not global there, so every restore
 * logged "Undefined variable $popup"; the old alert sat inside the "<!--" comment that hides the import output, so it never
 * showed; and the redirect to index/index.php?restored= ran inside main.php's iframe, whose frame check reloaded the top
 * window without the query (index/index.php, covered by e2e/login-frame-query.spec.mjs).
 *
 * Pins: restore() reads no variable it neither takes, declares global nor assigns; restore_done_redirect() sends the page
 * to the login page with the ledger name and the backup's time in a query the login page reads, and a popup window sends
 * the window that opened it there. restore.php is a page script (session, includes, output), so the two functions are
 * lifted out of its source rather than loaded with it.
 *
 * History:
 * 20261105 CL/LH Created.
 */
final class RestoreDoneRedirectTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../admin/restore.php';

    /** Variables PHP defines in every scope. */
    private const SUPERGLOBALS = ['$GLOBALS', '$_SERVER', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_REQUEST', '$_ENV', '$this'];

    /** restore_done_redirect() from admin/restore.php, lifted on first use. */
    private static function redirect(string $regnskab, string $backupdate, bool $popup): string
    {
        require_once __DIR__ . '/../../../includes/stdFunc/jsString.php';
        if (!function_exists('restore_done_redirect')) {
            eval(self::functionSource('restore_done_redirect'));
        }
        return restore_done_redirect($regnskab, $backupdate, $popup);
    }

    /**
     * The tokens of one function in admin/restore.php, from `function` to its closing brace.
     *
     * @return array<int, array{0: int, 1: string, 2: int}|string>
     */
    private static function functionTokens(string $name): array
    {
        $source = file_get_contents(self::PAGE);
        self::assertNotFalse($source, 'could not read admin/restore.php');
        $tokens = token_get_all($source);
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) {
                continue;
            }
            $depth = 0;
            for ($k = $j; $k < count($tokens); $k++) {
                $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                if ($text === '{' || (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($text === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return array_slice($tokens, $i, $k - $i + 1);
                    }
                }
            }
        }
        self::fail("function $name() is gone from admin/restore.php");
    }

    private static function functionSource(string $name): string
    {
        return implode('', array_map(static fn($t) => is_array($t) ? $t[1] : $t, self::functionTokens($name)));
    }

    /**
     * The login page's address in the refresh tag the redirect prints.
     *
     * @return array{0: string, 1: array<string, string>} the path and the query
     */
    private static function refreshTarget(string $html): array
    {
        self::assertMatchesRegularExpression('/^<meta http-equiv="refresh" content="0;URL=[^"<>]+">$/', $html);
        preg_match('/URL=([^"]+)"/', $html, $m);
        $url = html_entity_decode($m[1], ENT_QUOTES);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        return [(string) parse_url($url, PHP_URL_PATH), $query];
    }

    public function test_restore_reads_no_variable_it_does_not_define(): void
    {
        $tokens = self::functionTokens('restore');
        $defined = self::SUPERGLOBALS;
        $read = [];
        $inGlobal = false;
        $inParams = null;
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($inParams === null && $text === '(') {
                $inParams = true;
            } elseif ($inParams === true && $text === ')') {
                $inParams = false;
            }
            if (is_array($token) && $token[0] === T_GLOBAL) {
                $inGlobal = true;
                continue;
            }
            if ($inGlobal && $text === ';') {
                $inGlobal = false;
            }
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }
            $next = $i + 1;
            while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            $after = $next < $count ? (is_array($tokens[$next]) ? $tokens[$next][1] : $tokens[$next]) : '';
            if ($inParams || $inGlobal || $after === '=') {
                $defined[] = $text;
            } else {
                $read[$text] = $read[$text] ?? $token[2];
            }
        }
        $undefined = array_diff_key($read, array_flip($defined));
        self::assertSame([], $undefined, 'restore() reads these variables (name => line) without defining them');
    }

    public function test_a_restore_goes_to_the_login_page_with_the_ledger_and_the_backups_time(): void
    {
        [$path, $query] = self::refreshTarget(self::redirect('Hansen & Søn ApS', '20261105-2258', false));

        self::assertSame('../index/index.php', $path, 'the login page, from admin/');
        self::assertSame(['regnskab' => 'Hansen & Søn ApS', 'restored' => '20261105-2258'], $query, 'a name with & and a space survives the address');
        self::assertMatchesRegularExpression('/^(\d{4})(\d{2})(\d{2})-?(\d{2})(\d{2})$/', $query['restored'], 'the form index/index.php shows with a date');
    }

    public function test_a_backup_without_a_usable_time_still_gets_the_notice(): void
    {
        self::assertSame('1', self::refreshTarget(self::redirect('demo', '', false))[1]['restored']);
        self::assertSame('20261105-2258', self::refreshTarget(self::redirect('demo', '20261105-2258"><script>', false))[1]['restored']);
    }

    public function test_a_popup_window_sends_the_window_that_opened_it_to_the_login_page(): void
    {
        $html = self::redirect('Hansen & Søn ApS', '20261105-2258', true);

        self::assertStringStartsWith('<script>', $html);
        self::assertStringEndsWith('</script>', $html);
        self::assertStringNotContainsString('luk.php', $html, 'luk.php runs online.php, which the restore has logged out');
        self::assertStringNotContainsString('<BODY', $html);
        self::assertSame(1, preg_match('/window\.opener\.location\.href = ("[^"]*"); window\.close\(\);\} else \{window\.location\.href = \1;\}/', $html, $m), $html);
        $url = json_decode($m[1]);
        self::assertSame('../index/index.php?regnskab=Hansen%20%26%20S%C3%B8n%20ApS&restored=20261105-2258', $url);
        self::assertStringNotContainsString('&', $m[1], 'the address is escaped for the script block');
    }
}

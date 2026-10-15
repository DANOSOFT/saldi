<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/T4StaticChecksTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Created.
// 20261015 CL/LH The app's inventory/status check moved to the app repo (Saldi-Mobile __tests__/config/inventoryMutation.test.ts).

use PHPUnit\Framework\TestCase;

/** Static boundaries for new T4 code; protected update backups are never opened. */
final class T4StaticChecksTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return array<string,array{module:string,action:string,methods:list<string>}> Exact registered endpoint inventory. */
    public static function endpoints(): array
    {
        $entries = [];
        foreach (glob(__DIR__.'/authz-matrix.d/t4-*.php') as $file) {
            foreach (require $file as $path=>$policy) {
                if (isset($entries[$path])) { throw new LogicException('Duplicate T4 endpoint '.$path); }
                $entries[$path] = $policy;
            }
        }
        return $entries;
    }

    /** @return list<string> All new T4 production PHP and adapted stock REST writers. */
    private function productionFiles(): array
    {
        $files = array_map(static fn(string $path): string=>'restapi/endpoints/v1/'.$path,array_keys(self::endpoints()));
        foreach (['restapi/services/StockCountService.php','restapi/services/GoodsReceiptService.php','restapi/services/TimeEntryService.php',
            'restapi/services/BookingService.php','restapi/services/ClaimsService.php','restapi/core/EmployeeContext.php',
            'restapi/endpoints/v1/inventory/status/index.php','restapi/models/lager/LagerStatusModel.php',
            'includes/lager/stockMovement.php','includes/lager/stockLocks.php','includes/kreditor/creditorReceive.php',
            'includes/sager/loenTimer.php','includes/docsIncludes/poolAttachSource.php','includes/employeeClaims/mileageRates.php',
            'includes/employeeClaims/mileageStatement.php','systemdata/diverseIncludes/mobilMarkenValg.php'] as $file) { $files[] = $file; }
        foreach (['stock_count','goods_receipt','time_entries','bookings','claims'] as $name) {
            $files[] = 'restapi/core/capabilities.d/'.$name.'.php';
        }
        return $files;
    }

    /** @return list<string> Executable tokens, excluding comments so documentation does not hide actual statements. */
    private function literals(string $file): array
    {
        $values = [];
        foreach (token_get_all(file_get_contents(self::ROOT.'/'.$file)) as $token) {
            if (is_array($token) && in_array($token[0],[T_CONSTANT_ENCAPSED_STRING,T_ENCAPSED_AND_WHITESPACE],true)) {
                $values[] = trim($token[1],"'\"");
            }
        }
        return $values;
    }

    public function testOnlySharedStockEnginesWriteStockTablesAndT4NeverPosts(): void
    {
        $writers = ['includes/lager/stockMovement.php','includes/lager/stockLocks.php','includes/kreditor/creditorReceive.php'];
        foreach ($this->productionFiles() as $file) {
            $this->assertFileExists(self::ROOT.'/'.$file);
            $source = file_get_contents(self::ROOT.'/'.$file);
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0],[T_STRING,T_NAME_FULLY_QUALIFIED,T_CONSTANT_ENCAPSED_STRING],true)) {
                    $this->assertNotContains(strtolower(trim($token[1],"'\"\\")),['bogfor','kladde_bogfor','bogfor_nu'],$file.':'.$token[2]);
                }
            }
            if (!in_array($file,$writers,true)) {
                foreach ($this->literals($file) as $sql) {
                    // T4 retains the legacy location-only update; quantities use stock_set_quantity.
                    if ($file === 'restapi/models/lager/LagerStatusModel.php' && $sql === 'UPDATE lagerstatus SET ') {
                        $this->assertSame(1,substr_count($source,"'UPDATE lagerstatus SET '"));
                        $this->assertStringContainsString("implode(',', \$locations)",$source);
                        $this->assertStringContainsString("'lok' . (\$index + 1)",$source);
                        continue;
                    }
                    $this->assertDoesNotMatchRegularExpression('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?)\s+"?(?:lagerstatus|batch_kob|batch_salg|stocklog)\b/i',$sql,$file);
                }
            }
        }
    }

    public function testRestNeverIncludesInteractiveStockReceiptOrPayrollPages(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT.'/restapi',FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') { continue; }
            $inside = false;
            foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && in_array($token[0],[T_INCLUDE,T_INCLUDE_ONCE,T_REQUIRE,T_REQUIRE_ONCE],true)) { $inside = true; }
                elseif ($token === ';') { $inside = false; }
                elseif ($inside && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $this->assertDoesNotMatchRegularExpression('~(?:lager/optalling|sager/loen|kreditor/modtag)\.php$~',trim($token[1],"'\""),$file->getPathname());
                }
            }
        }
    }

    public function testEveryEndpointHasExactDeclarationAndSwaggerMethods(): void
    {
        $entries = self::endpoints();
        $this->assertCount(20,$entries);
        $swagger = file_get_contents(self::ROOT.'/restapi/swagger.yaml');
        $methods = 0;
        foreach ($entries as $path=>$policy) {
            $source = file_get_contents(self::ROOT.'/restapi/endpoints/v1/'.$path);
            $this->assertMatchesRegularExpression('/function\s+requiredRight\s*\(\s*\)\s*:\s*\?array/',$source,$path);
            $this->assertMatchesRegularExpression("/['\"]module['\"]\\s*=>\\s*['\"]".preg_quote($policy['module'],'/')."['\"]/",$source,$path);
            if ($policy['action'] === 'method') {
                $this->assertMatchesRegularExpression("/REQUEST_METHOD.*?===\\s*['\"]GET['\"]\\s*\\?\\s*['\"]read['\"]\\s*:\\s*['\"]write['\"]/s",$source,$path);
            } else {
                $this->assertMatchesRegularExpression("/['\"]action['\"]\\s*=>\\s*['\"]".preg_quote($policy['action'],'/')."['\"]/",$source,$path);
            }
            $this->assertSame(1,preg_match('~^  /'.preg_quote($path,'~').':\n(.*?)(?=^  /|^components:)~ms',$swagger,$match),$path);
            foreach ($policy['methods'] as $method) {
                $methods++;
                $this->assertStringContainsString('    '.strtolower($method).':',$match[1],$path);
            }
        }
        $this->assertSame(32,$methods);
    }

    public function testTranslationsAreUniqueAndNewT4FilesContainNoSecretLiterals(): void
    {
        $ids = [];
        foreach (file(self::ROOT.'/importfiler/tekster.csv',FILE_IGNORE_NEW_LINES) as $line) {
            $id = explode("\t",$line,2)[0];
            if (!ctype_digit($id)) { continue; }
            $this->assertArrayNotHasKey($id,$ids,'Duplicate translation '.$id);
            $ids[$id] = true;
        }
        foreach (range(5490,5499) as $id) { $this->assertArrayHasKey($id,$ids); }
        foreach ($this->productionFiles() as $file) {
            $tokens = token_get_all(file_get_contents(self::ROOT.'/'.$file));
            $significant = array_values(array_filter($tokens,static fn($token): bool=>!is_array($token) || !in_array($token[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)));
            foreach ($significant as $index=>$token) {
                if (!is_array($token) || $token[0] !== T_VARIABLE || !preg_match('/(?:password|passwd|pgpass|clientSecret|client_secret|apikey|api_key|jwtSecret|jwt_secret)$/i',$token[1])) { continue; }
                if (($significant[$index+1] ?? null) === '=' && is_array($significant[$index+2] ?? null) && $significant[$index+2][0] === T_CONSTANT_ENCAPSED_STRING) {
                    $this->assertSame('',trim($significant[$index+2][1],"'\""),$file.':'.$token[2].' secret literal');
                }
            }
        }
    }
}

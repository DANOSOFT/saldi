<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/T3StaticChecksTest.php --- ver 5.0.0 --- 2026.10.06
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

use PHPUnit\Framework\TestCase;

/** Structural guard over the T3 production files, without loading endpoint handlers. */
final class T3StaticChecksTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return array<string,array{module:string,action:string,methods:list<string>}> Slice-owned authorization inventory. */
    public static function endpoints(): array
    {
        $entries = [];
        foreach (glob(__DIR__ . '/authz-matrix.d/t3-*.php') as $file) {
            foreach (require $file as $path=>$policy) {
                if (isset($entries[$path])) {
                    throw new LogicException('Duplicate T3 authorization policy: ' . $path);
                }
                $entries[$path] = $policy;
            }
        }
        return $entries;
    }

    /** @return list<string> New T3 production files; shared legacy files retain their pre-existing posting paths. */
    private function productionFiles(): array
    {
        $files = array_map(fn($path)=>'restapi/endpoints/v1/' . $path,array_keys(self::endpoints()));
        foreach ([
            'restapi/services/AttachmentApproveService.php','restapi/services/AttachmentMetadataService.php',
            'restapi/services/AttachmentReviewService.php','restapi/services/BankLineService.php',
            'restapi/services/BookkeeperSummaryService.php','restapi/services/ClassificationService.php',
            'restapi/services/MobileEventOutbox.php','restapi/services/MobileEventFanout.php','restapi/services/MobileEventScanners.php',
            'includes/docsIncludes/poolApprove.php','includes/docsIncludes/poolAttach.php','includes/docsIncludes/poolAudit.php',
            'includes/docsIncludes/poolClassification.php','includes/docsIncludes/poolReview.php','includes/docsIncludes/poolReviewVersion.php',
            'includes/finans/bankLines.php','includes/finans/kladdeJournal.php','includes/lager/stockWarning.php',
            'finans/kassekladde_includes/journalLine.php','finans/kassekladde_includes/vatCodes.php',
            'cron/mobile_events.php','cron/mobile_scanners.php','cron/bookkeeper_summary.php',
        ] as $file) {
            $files[] = $file;
        }
        foreach (glob(self::ROOT . '/restapi/core/capabilities.d/*.php') as $file) {
            if (in_array(basename($file),['attachment_edit.php','review.php','bank_lines.php','suggestions.php','events.php','bookkeeper.php'],true)) {
                $files[] = substr($file,strlen(self::ROOT)+1);
            }
        }
        return $files;
    }

    public function testT3CannotCallPostingFunctions(): void
    {
        foreach ($this->productionFiles() as $file) {
            $this->assertFileExists(self::ROOT . '/' . $file);
            foreach (token_get_all(file_get_contents(self::ROOT . '/' . $file)) as $token) {
                if (!is_array($token) || !in_array($token[0],[T_STRING,T_NAME_FULLY_QUALIFIED,T_CONSTANT_ENCAPSED_STRING],true)) {
                    continue;
                }
                $name = strtolower(trim($token[1],"'\"\\"));
                $this->assertNotContains($name,['bogfor','kladde_bogfor','bogfor_nu'],$file . ':' . $token[2]);
            }
        }
    }

    public function testRestApiNeverIncludesInteractiveFinancePages(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/restapi',FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $tokens = token_get_all(file_get_contents($file->getPathname()));
            $insideInclude = false;
            foreach ($tokens as $token) {
                if (is_array($token) && in_array($token[0],[T_INCLUDE,T_INCLUDE_ONCE,T_REQUIRE,T_REQUIRE_ONCE],true)) {
                    $insideInclude = true;
                } elseif ($token === ';') {
                    $insideInclude = false;
                } elseif ($insideInclude && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $literal = trim($token[1],"'\"");
                    $this->assertDoesNotMatchRegularExpression('~(?:finans/(?:kassekladde|bogfor)\.php|(?:^|/)moveButton\.php)$~',$literal,$file->getPathname() . ':' . $token[2]);
                }
            }
        }
    }

    public function testAllT3EndpointsDeclareTheirRegisteredRightAndSwaggerMethod(): void
    {
        $entries = self::endpoints();
        $this->assertCount(15,$entries);
        $swagger = file_get_contents(self::ROOT . '/restapi/swagger.yaml');
        $methodCount = 0;
        foreach ($entries as $path=>$policy) {
            $source = file_get_contents(self::ROOT . '/restapi/endpoints/v1/' . $path);
            $compact = preg_replace('/\s+/','',$source);
            $this->assertStringContainsString('functionrequiredRight():?array',$compact,$path);
            $this->assertMatchesRegularExpression("/return\\[['\"]module['\"]=>['\"]" . preg_quote($policy['module'],'/') . "['\"],['\"]action['\"]=>['\"]" . preg_quote($policy['action'],'/') . "['\"]\\];/",$compact,$path);
            $this->assertSame(1,preg_match('~^  /' . preg_quote($path,'~') . ':\n(.*?)(?=^  /|^components:)~ms',$swagger,$match),$path);
            foreach ($policy['methods'] as $method) {
                $methodCount++;
                $this->assertStringContainsString('    ' . strtolower($method) . ':',$match[1],$path);
            }
        }
        $this->assertSame(16,$methodCount);
    }
}

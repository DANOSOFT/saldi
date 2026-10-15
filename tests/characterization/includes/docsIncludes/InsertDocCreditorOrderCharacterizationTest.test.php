<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/docsIncludes/InsertDocCreditorOrderCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.05
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
require_once __DIR__ . '/../../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../../../includes/docsIncludes/poolPaths.php';

final class InsertDocCreditorOrderCharacterizationTest extends TestCase
{
    private static $conn;
    private static string $root;
    public static function setUpBeforeClass(): void
    {
        if ($reason=CharacterizationEnv::unavailableReason()) { self::markTestSkipped($reason); }
        CharacterizationEnv::bootstrapTenant();
        self::$conn=CharacterizationEnv::connect(CharacterizationEnv::testDb());
        mobileTenantUpdates(self::$conn);
        self::$root=dirname(poolPuljePath(CharacterizationEnv::testDb()));
        if (!is_dir(self::$root.'/pulje')) { mkdir(self::$root.'/pulje',0777,true); }
        CharacterizationEnv::rows(self::$conn,"INSERT INTO settings(var_name,var_value) SELECT 'globalId','37' WHERE NOT EXISTS (SELECT 1 FROM settings WHERE var_name='globalId')");
        CharacterizationEnv::rows(self::$conn,"UPDATE settings SET var_value='37' WHERE var_name='globalId'");
    }
    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            pg_close(self::$conn);
            if (is_dir(self::$root)) {
                $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
                rmdir(self::$root);
            }
            CharacterizationEnv::teardownTenant();
        }
    }
    public function testCreditorOrderUsesLegacyFolderAndIdentity(): void
    {
        $order=(int)CharacterizationEnv::one(self::$conn,"INSERT INTO ordrer(art,status,ordrenr) VALUES ('KO',1,94123) RETURNING id")['id'];
        $name=bin2hex(random_bytes(8)).'.pdf';
        $bytes='delivery-note-'.bin2hex(random_bytes(8));
        file_put_contents(self::$root.'/pulje/'.$name,$bytes);
        $pool=CharacterizationEnv::one(self::$conn,"INSERT INTO pool_files(filename,storage_path) VALUES ($1,$2) RETURNING id",[$name,'pulje/'.$name]);
        $input=['source'=>'creditorOrder','sourceId'=>$order,'poolFile'=>$name];
        $child=CharacterizationEnv::runChild(__DIR__.'/../../support/run_insert_doc.php',[CharacterizationEnv::testDb(),json_encode($input,JSON_THROW_ON_ERROR)]);
        $this->assertSame(0,$child['exit'],$child['stderr'].$child['stdout']);
        $this->assertSame('',$child['stderr']);
        $docs=CharacterizationEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1',[$pool['id']]);
        $this->assertCount(1,$docs);
        $this->assertSame('37',$docs[0]['global_id']);
        $this->assertSame($name,$docs[0]['filename']);
        $this->assertSame('/creditor/orders/'.$order,$docs[0]['filepath']);
        $this->assertSame('creditorOrder',$docs[0]['source']);
        $this->assertSame((string)$order,$docs[0]['source_id']);
        $this->assertSame('0',$docs[0]['user_id']);
        $row=CharacterizationEnv::one(self::$conn,'SELECT * FROM pool_files WHERE id=$1',[$pool['id']]);
        $this->assertSame('attached',$row['status']);
        $this->assertSame('creditor/orders/'.$order.'/'.$name,$row['storage_path']);
        $this->assertSame('.history-'.$pool['id'].'-'.$name,$row['filename']);
        $this->assertSame([(int)$docs[0]['id']],json_decode($row['document_ids'],true));
        $this->assertSame($bytes,file_get_contents(self::$root.'/'.$row['storage_path']));
        $this->assertFileDoesNotExist(self::$root.'/pulje/'.$name);
    }
}

<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/docsIncludes/InsertDocAttachCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../../../includes/docsIncludes/poolPaths.php';

require_once __DIR__ . '/../../support/ModificationTrail.php';

final class InsertDocAttachCharacterizationTest extends TestCase
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
    public static function cases(): array { return ['new'=>[false,0,false],'existing'=>[true,0,false],'siblings'=>[true,2,false],'selected sibling'=>[true,2,true]]; }
    #[DataProvider('cases')]
    public function testRealInsertDocAttach(bool $existing,int $siblings,bool $selected): void
    {
        $journal=(int)CharacterizationEnv::one(self::$conn,"INSERT INTO kladdeliste(bogfort,kladdenote) VALUES ('-','attach characterization') RETURNING id")['id'];
        $ids=[];
        for ($i=0;$i<($existing ? 1+$siblings : 0);$i++) {
            $ids[]=(int)CharacterizationEnv::one(self::$conn,"INSERT INTO kassekladde(kladde_id,bilag,transdate,d_type,k_type,amount,pos) VALUES ($1,42,'2026-09-14','F','F',0,$2) RETURNING id",[$journal,$i+1])['id'];
        }
        $name=bin2hex(random_bytes(8)).'.pdf';
        $bytes='attach-characterization-'.bin2hex(random_bytes(8));
        file_put_contents(self::$root.'/pulje/'.$name,$bytes);
        $pool=CharacterizationEnv::one(self::$conn,"INSERT INTO pool_files(filename,storage_path,amount,file_date) VALUES ($1,$2,'125,00','2026-09-14') RETURNING id",[$name,'pulje/'.$name]);
        $input=['source'=>'kassekladde','sourceId'=>$existing ? $ids[0] : 0,'kladde_id'=>$journal,'poolFile'=>$name,'bilag'=>42,'dato'=>'14-09-2026','sum'=>'125,00','beskrivelse'=>"Supplier's receipt"];
        if ($selected) { $input['targetSourceIds']=(string)$ids[1]; }
        $trail = ModificationTrail::read(CharacterizationEnv::testDb());
        $child=CharacterizationEnv::runChild(__DIR__.'/../../support/run_insert_doc.php',[CharacterizationEnv::testDb(),json_encode($input,JSON_THROW_ON_ERROR)]);
        $this->assertSame(0,$child['exit'],$child['stderr'].$child['stdout']);
        $this->assertSame('',$child['stderr']);
        ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'INSERT INTO documents\\b');
        ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'UPDATE pool_files\\b');
        $lines=CharacterizationEnv::rows(self::$conn,'SELECT * FROM kassekladde WHERE kladde_id=$1 ORDER BY id',[$journal]);
        $lineId=(int)$lines[0]['id'];
        $this->assertSame('42',$lines[0]['bilag']);
        $this->assertSame('2026-09-14',$lines[0]['transdate']);
        $this->assertEquals(125,$lines[0]['amount']);
        $this->assertSame("Supplier's receipt",$lines[0]['beskrivelse']);
        $docs=CharacterizationEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1 ORDER BY id',[$pool['id']]);
        $this->assertCount($selected ? 2 : 1+$siblings,$docs);
        foreach ($docs as $doc) {
            $this->assertSame('37',$doc['global_id']);
            $this->assertSame($name,$doc['filename']);
            $this->assertSame('/finance/'.$journal.'/'.$lineId,$doc['filepath']);
            $this->assertSame('kassekladde',$doc['source']);
            $this->assertSame('0',$doc['user_id']);
        }
        $row=CharacterizationEnv::one(self::$conn,'SELECT * FROM pool_files WHERE id=$1',[$pool['id']]);
        $this->assertSame('attached',$row['status']);
        $this->assertSame('pulje/'.$name === $row['storage_path'],false);
        $this->assertSame('finance/'.$journal.'/'.$lineId.'/'.$name,$row['storage_path']);
        $this->assertSame('.history-'.$pool['id'].'-'.$name,$row['filename']);
        $this->assertSame('42',$row['bilag_nr']);
        $this->assertSame(array_map(static fn($d)=>(int)$d['id'],$docs),json_decode($row['document_ids'],true));
        $this->assertSame($bytes,file_get_contents(self::$root.'/'.$row['storage_path']));
        $this->assertFileDoesNotExist(self::$root.'/pulje/'.$name);
    }
}

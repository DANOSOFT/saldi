<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/AttachmentApproveConcurrencyTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Cover reused destination names and real reconciler kills around collision recovery.

use PHPUnit\Framework\Attributes\DataProvider;
require_once __DIR__.'/support/T3B2bTestCase.php';

final class AttachmentApproveConcurrencyTest extends T3B2bTestCase
{
    private array $children=[];
    private array $markers=[];
    protected function tearDown(): void
    {
        foreach ($this->children as $child) {
            if (is_resource($child['process'])) { proc_terminate($child['process'],9); $this->finish($child); }
        }
        foreach ($this->markers as $marker) { foreach ([$marker,$marker.'.release'] as $file) { if (is_file($file)) { unlink($file); } } }
        $this->children=[]; $this->markers=[];
    }
    private function args(array $row,array $body=[]): array
    {
        return ['db'=>RestApiEnv::testDb(),'tenant_id'=>RestApiEnv::regnskabId(RestApiEnv::accountOpen()),
            'subject'=>self::$reviewer,'username'=>'t3_reviewer','user_id'=>Subject::parse(self::$reviewer)['user_id'],
            'id'=>(int)$row['id'],'body'=>$body+['id'=>(int)$row['id'],'client_id'=>RequestId::resolve(null)],
            'etag'=>'"'.pool_review_version($row).'"'];
    }
    private function start(array $args,?string $hook=null): array
    {
        $marker='/tmp/t3b2b-'.RequestId::resolve(null);
        if ($hook !== null) { $args+=['hook'=>$hook,'marker'=>$marker]; }
        $pipes=[];
        $process=proc_open([PHP_BINARY,__DIR__.'/support/run_attachment_approve.php',json_encode($args,JSON_THROW_ON_ERROR)],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start approval'); }
        fclose($pipes[0]);
        $child=['process'=>$process,'pipes'=>$pipes,'marker'=>$marker]; $this->children[]=$child; $this->markers[]=$marker;
        return $child;
    }
    private function rendezvous(array $child): void
    {
        $deadline=microtime(true)+15;
        do {
            clearstatcache(true,$child['marker']);
            if (is_file($child['marker'])) { $this->assertTrue(true); return; }
            if (!proc_get_status($child['process'])['running']) { $output=$this->finish($child); $this->fail(json_encode($output)); }
            usleep(20000);
        } while (microtime(true)<$deadline);
        $this->fail('Child did not reach test hook');
    }
    private function release(array $child): void { file_put_contents($child['marker'].'.release','go'); }
    private function finish(array $child): array
    {
        $stdout=stream_get_contents($child['pipes'][1]); $stderr=stream_get_contents($child['pipes'][2]);
        fclose($child['pipes'][1]); fclose($child['pipes'][2]); $exit=proc_close($child['process']);
        foreach ($this->children as $i=>$active) { if ($active['process'] === $child['process']) { unset($this->children[$i]); } }
        return ['exit'=>$exit,'stdout'=>$stdout,'stderr'=>$stderr,'json'=>json_decode($stdout,true)];
    }
    private function runChildRequest(array $args): array
    {
        $result=$this->finish($this->start($args));
        $this->assertSame(0,$result['exit'],$result['stderr'].$result['stdout']);
        $this->assertIsArray($result['json'],$result['stderr'].$result['stdout']);
        return $result['json'];
    }
    public static function kills(): array
    {
        $cases=[];
        foreach (['K1','K2','K3'] as $point) { foreach (['retry','reconcile'] as $recovery) { $cases[$point.' '.$recovery]=[$point,$recovery]; } }
        return $cases;
    }
    #[DataProvider('kills')]
    public function testRealKilledProcessRecoversExactlyOnce(string $point,string $recovery): void
    {
        $row=$this->receipt();
        if ($point === 'K1') { RestApiEnv::rows(self::$conn,"UPDATE pool_files SET updated=now()-interval '61 seconds' WHERE id=$1",[$row['id']]); $row=$this->row((int)$row['id']); }
        $args=$this->args($row);
        $before=count(RestApiEnv::rows(self::$conn,'SELECT id FROM kassekladde'));
        $poolBefore=count(RestApiEnv::rows(self::$conn,'SELECT id FROM pool_files'));
        $child=$this->start($args,$point); $this->rendezvous($child);
        $intent=RestApiEnv::rows(self::$conn,'SELECT * FROM pool_file_moves WHERE pool_file_id=$1',[$row['id']])[0];
        $busy=$this->runChildRequest($args+['mode'=>'reconcile','intent'=>(int)$intent['id']]); $this->assertSame('busy',$busy['state']);
        if ($point === 'K1') {
            // Simulate a file absent for more than the web sync's 60-second protection period.
            $this->assertSame(200,$this->runChildRequest($args+['mode'=>'sync'])['status']);
            $this->assertSame('ubehandlet',$this->row((int)$row['id'])['status']);
        }
        $this->assertTrue(proc_terminate($child['process'],9)); $this->finish($child);
        if ($recovery === 'retry') { $response=$this->runChildRequest($args); $this->assertSame(200,$response['status'],json_encode($response)); }
        else { $result=$this->runChildRequest($args+['mode'=>'reconcile','intent'=>(int)$intent['id']]); $this->assertSame($point === 'K1' ? 'aborted' : 'done',$result['state']); }
        $newLines=count(RestApiEnv::rows(self::$conn,'SELECT id FROM kassekladde'))-$before;
        $expected=$point === 'K1' && $recovery === 'reconcile' ? 0 : 1;
        $this->assertSame($expected,$newLines);
        $this->assertSame($poolBefore,count(RestApiEnv::rows(self::$conn,'SELECT id FROM pool_files')));
        $actual=$this->row((int)$row['id']);
        if ($expected) {
            $this->assertAttached($row); $this->assertSame(200,$this->runChildRequest($args)['status']);
            $this->assertCount(1,RestApiEnv::rows(self::$conn,'SELECT id FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']]));
        } else {
            $this->assertSame('pending',$actual['review_state']); $this->assertSame('ubehandlet',$actual['status']);
            $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM documents WHERE pool_file_id=$1',[$row['id']]));
        }
        $locations=[$intent['source_path'],$intent['staging_path'],$actual['storage_path']];
        $files=array_filter(array_unique($locations),fn($path)=>is_file(self::$root.'/'.$path));
        $this->assertCount(1,$files); $this->assertSame($row['sha256'],hash_file('sha256',self::$root.'/'.array_values($files)[0]));
    }
    public function testTwoReviewersSerializeToOneDecision(): void
    {
        $row=$this->receipt(); $first=$this->start($this->args($row),'K1'); $this->rendezvous($first);
        $secondId=(int)RestApiEnv::rows(self::$conn,"UPDATE brugere SET rettigheder='0010000000000000' WHERE brugernavn='t3_other' RETURNING id")[0]['id'];
        $args=$this->args($row); $args['subject']=Subject::build('account',$secondId); $args['username']='t3_other'; $args['user_id']=$secondId;
        $second=$this->start($args); $this->release($first);
        $a=$this->finish($first); $b=$this->finish($second);
        $this->assertSame(200,$a['json']['status'],json_encode($a)); $this->assertSame(409,$b['json']['status'],json_encode($b));
        $this->assertSame('already_decided',$b['json']['details']['reason']);
        $this->assertAttached($row); $this->assertCount(1,RestApiEnv::rows(self::$conn,'SELECT id FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']]));
    }
    public function testOwnerEditWinsDuringReservationAndApprovalRejectsStaleVersion(): void
    {
        $row=$this->receipt(); $child=$this->start($this->args($row),'K1'); $this->rendezvous($child);
        $edit=$this->patch($row,$this->body(['description'=>'Owner correction'])); $this->assertSame(200,$edit['status'],$edit['body']);
        $this->release($child); $result=$this->finish($child); $this->assertSame(412,$result['json']['status'],json_encode($result));
        $actual=$this->row((int)$row['id']); $this->assertSame('pending',$actual['review_state']);
        $this->assertSame('Owner correction',$actual['description']); $this->assertFileExists(self::$root.'/'.$actual['storage_path']);
    }
    public function testConcurrentFirstApprovalsShareOneCommittedJournalAndDistinctVouchers(): void
    {
        RestApiEnv::rows(self::$conn,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $before=count(RestApiEnv::rows(self::$conn,'SELECT id FROM kladdeliste WHERE mobile_approval_journal=true'));
        $a=$this->start($this->args($this->receipt()),'target_resolved'); $b=$this->start($this->args($this->receipt()),'target_resolved');
        $this->rendezvous($a); $this->rendezvous($b); $this->release($a); $this->release($b);
        $ra=$this->finish($a); $rb=$this->finish($b);
        $this->assertSame(200,$ra['json']['status'],json_encode($ra)); $this->assertSame(200,$rb['json']['status'],json_encode($rb));
        $a=$ra['json']['body']['data']['draft']; $b=$rb['json']['body']['data']['draft'];
        $this->assertSame($a['kladde_id'],$b['kladde_id']); $this->assertNotSame($a['bilag'],$b['bilag']);
        $this->assertSame($before+1,count(RestApiEnv::rows(self::$conn,'SELECT id FROM kladdeliste WHERE mobile_approval_journal=true')));
    }
    public function testRollbackLeavesResolvedJournalReusable(): void
    {
        RestApiEnv::rows(self::$conn,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $row=$this->receipt(); $body=['id'=>(int)$row['id'],'client_id'=>RequestId::resolve(null)];
        try { $this->approvalService(static function($point) { if ($point === 'before_commit') { throw new RuntimeException('Injected rollback'); } })->approve((int)$row['id'],$body,'"'.pool_review_version($row).'"',$body['client_id']); $this->fail('Missing fault'); }
        catch (RuntimeException $error) { $this->assertSame('Injected rollback',$error->getMessage()); }
        $journal=(int)RestApiEnv::rows(self::$conn,"SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'")[0]['var_value'];
        $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM kassekladde WHERE kladde_id=$1',[$journal]));
        $response=$this->approve($this->row((int)$row['id']),$body); $this->assertSame(200,$response['status'],$response['body']);
        $this->assertSame($journal,$response['json']['data']['draft']['kladde_id']);
    }
    private function webAttach(array $row,int $journal,int $line): array
    {
        return $this->child(__DIR__.'/../characterization/support/run_insert_doc.php',[RestApiEnv::testDb(),json_encode(['source'=>'kassekladde','sourceId'=>$line,'kladde_id'=>$journal,'poolFile'=>$row['filename'],'bilag'=>42,'dato'=>'14-09-2026','sum'=>'125,00','beskrivelse'=>'T3 purchase'],JSON_THROW_ON_ERROR)]);
    }
    public function testWebAttachAndApprovalBothOrdersAndWhileStaged(): void
    {
        [$row,$journal,$line]=$this->bankReceipt(); $result=$this->webAttach($row,$journal,$line); $this->assertSame(0,$result['exit'],$result['stderr']);
        $response=$this->approve($row); $this->assertSame(409,$response['status'],$response['body']);
        $this->assertSame('attachment_processed',$response['json']['data']['details']['reason']);
        [$row,$journal,$line]=$this->bankReceipt(); $child=$this->start($this->args($row),'K1'); $this->rendezvous($child);
        $before=$this->accounting(); $result=$this->webAttach($row,$journal,$line); $this->assertSame(0,$result['exit'],$result['stderr']);
        $this->assertSame($before,$this->accounting()); $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM documents WHERE pool_file_id=$1',[$row['id']]));
        $this->release($child); $result=$this->finish($child); $this->assertSame(200,$result['json']['status'],json_encode($result));
        $before=$this->accounting(); $result=$this->webAttach($row,$journal,$line); $this->assertSame(0,$result['exit'],$result['stderr']);
        $this->assertSame($before,$this->accounting()); $this->assertAttached($row);
    }
    public function testActualWebReturnAllowsReapprovalOnSameBankRow(): void
    {
        require_once __DIR__.'/../../includes/finans/bankLines.php';
        [$row,$journal,$line,$bank]=$this->bankReceipt();
        RestApiEnv::rows(self::$conn,"UPDATE bank_lines SET state='received',pool_file_id=$1 WHERE id=$2",[$row['id'],$bank]);
        $response=$this->approve($row); $this->assertSame(200,$response['status'],$response['body']);
        $attached=$this->row((int)$row['id']); $args=$this->args($attached)+['mode'=>'return','path'=>'../bilag/'.RestApiEnv::testDb().'/'.$attached['storage_path'],'line'=>$line,'journal'=>$journal];
        $result=$this->finish($this->start($args)); $this->assertSame(0,$result['exit'],$result['stderr'].$result['stdout']);
        $returned=$this->row((int)$row['id']); $this->assertSame('pending',$returned['review_state']);
        $bankRow=RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$bank])[0];
        $this->assertSame('received',bank_line_effective_state($bankRow));
        $response=$this->approve($returned); $this->assertSame(200,$response['status'],$response['body']);
        $this->assertContains('line_account_kept',array_column($response['json']['data']['warnings'],'code'));
        $this->assertCount(2,RestApiEnv::rows(self::$conn,'SELECT id FROM mobile_journal_lines WHERE pool_file_id=$1 AND kassekladde_id=$2',[$row['id'],$line]));
        $this->assertAttached($row);
    }

    public function testCommittedFileMovePendingReplaysAndReconcilesWithoutAnotherDecision(): void
    {
        $row=$this->receipt(); $body=['id'=>(int)$row['id'],'client_id'=>RequestId::resolve(null)]; $blocked=null;
        $hook=function($point) use ($row,&$blocked): void {
            if ($point !== 'K2') { return; }
            $intent=RestApiEnv::rows(self::$conn,'SELECT * FROM pool_file_moves WHERE pool_file_id=$1',[$row['id']])[0];
            $blocked=self::$root.'/'.$intent['dest_path']; mkdir(dirname($blocked),0777,true); file_put_contents($blocked,'collision');
        };
        $response=$this->approvalService($hook)->approve((int)$row['id'],$body,'"'.pool_review_version($row).'"',$body['client_id']);
        $this->assertSame(200,$response['status']); $this->assertContains('file_move_pending',array_column($response['body']['data']['warnings'],'code'));
        $this->assertSame('approved',$this->row((int)$row['id'])['review_state']);
        unlink($blocked);
        $replay=$this->runChildRequest($this->args($row,$body)); $this->assertSame($response['body'],$replay['body']);
        $this->assertSame(['Idempotent-Replayed'=>'true'],$replay['headers']); $this->assertAttached($row);
        $this->assertCount(1,RestApiEnv::rows(self::$conn,'SELECT id FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']]));
    }
    public function testLostIntentNeverGuessesOrTombstonesIdentity(): void
    {
        $row=$this->receipt(); $args=$this->args($row); $child=$this->start($args,'K1'); $this->rendezvous($child);
        $intent=RestApiEnv::rows(self::$conn,'SELECT * FROM pool_file_moves WHERE pool_file_id=$1',[$row['id']])[0];
        $this->assertTrue(proc_terminate($child['process'],9)); $this->finish($child);
        unlink(self::$root.'/'.$intent['staging_path']);
        $result=$this->runChildRequest($args+['mode'=>'reconcile','intent'=>(int)$intent['id']]); $this->assertSame('lost',$result['state']);
        $this->assertSame('pending',$this->row((int)$row['id'])['review_state']);
        $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']]));
    }
    public function testReservedCollisionRestoresSameIdentityUnderSafeName(): void
    {
        $row=$this->receipt(); $args=$this->args($row); $child=$this->start($args,'K1'); $this->rendezvous($child);
        $intent=RestApiEnv::rows(self::$conn,'SELECT * FROM pool_file_moves WHERE pool_file_id=$1',[$row['id']])[0];
        $this->assertTrue(proc_terminate($child['process'],9)); $this->finish($child);
        file_put_contents(self::$root.'/'.$intent['source_path'],'Different file');
        $result=$this->runChildRequest($args+['mode'=>'reconcile','intent'=>(int)$intent['id']]); $this->assertSame('aborted',$result['state']);
        $actual=$this->row((int)$row['id']); $this->assertSame('pulje/'.$row['id'].'-'.$row['filename'],$actual['storage_path']);
        $this->assertSame($row['sha256'],hash_file('sha256',self::$root.'/'.$actual['storage_path']));
        $this->assertSame('Different file',file_get_contents(self::$root.'/'.$intent['source_path']));
    }

    public static function destinationReservations(): array
    {
        return ['open bank row'=>['-',false],'posted bank row'=>['V',false],
            'committed intent without references'=>['-',true]];
    }

    #[DataProvider('destinationReservations')]
    public function testSameFilenameCannotTakeCommittedApprovalDestination(string $posted,bool $removeReferences): void
    {
        [$first,$journal,$line,$bank]=$this->bankReceipt($posted,true);
        $child=$this->start($this->args($first),'K2');
        $this->rendezvous($child);
        $intent=RestApiEnv::rows(self::$conn,'SELECT * FROM pool_file_moves WHERE pool_file_id=$1',[$first['id']])[0];
        $this->assertSame('committed',$intent['state']);
        $this->assertFileDoesNotExist(self::$root.'/'.$intent['dest_path']);
        $documents=RestApiEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1 ORDER BY id',[$first['id']]);
        $this->assertCount(2,$documents);
        if ($removeReferences) {
            RestApiEnv::rows(self::$conn,'DELETE FROM documents WHERE pool_file_id=$1',[$first['id']]);
            PoolFileTransitions::refreshDocuments((int)$first['id']);
        }
        $second=$this->receipt(['bank_line_id'=>$bank]);
        $this->assertTrue(rename(self::$root.'/'.$second['storage_path'],self::$root.'/pulje/'.$first['filename']));
        RestApiEnv::rows(self::$conn,'UPDATE pool_files SET filename=$1,storage_path=$2 WHERE id=$3',
            [$first['filename'],'pulje/'.$first['filename'],$second['id']]);
        $second=$this->row((int)$second['id']);
        $this->assertNotSame($first['sha256'],$second['sha256']);
        $before=$this->accounting();
        $response=$this->runChildRequest($this->args($second));
        $this->assertSame(409,$response['status'],json_encode($response));
        $this->assertSame('attachment_processed',$response['details']['reason']);
        $this->assertSame($before,$this->accounting());
        $this->assertSame('pending',$this->row((int)$second['id'])['review_state']);
        $this->assertSame($second['sha256'],hash_file('sha256',self::$root.'/'.$second['storage_path']));
        $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM documents WHERE pool_file_id=$1',[$second['id']]));
        $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM mobile_journal_lines WHERE pool_file_id=$1',[$second['id']]));
        if (!$removeReferences) {
            $this->assertSame($documents,RestApiEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1 ORDER BY id',[$first['id']]));
        }
        $this->release($child);
        $result=$this->finish($child);
        $this->assertSame(200,$result['json']['status'],json_encode($result));
        $this->assertSame($first['sha256'],hash_file('sha256',self::$root.'/'.$intent['dest_path']));
        $this->assertFileDoesNotExist(self::$root.'/'.$intent['staging_path']);
    }

    public function testAttachIdentityCannotReplaceAnotherDocumentOwner(): void
    {
        [$first]=$this->bankReceipt('-',true);
        $response=$this->approve($first);
        $this->assertSame(200,$response['status'],$response['body']);
        $attached=$this->assertAttached($first,2);
        $documents=RestApiEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1 ORDER BY id',[$first['id']]);
        $second=$this->receipt();
        try {
            PoolFileTransitions::attachIdentity(RestApiEnv::testDb(),(int)$second['id'],self::$root.'/'.$attached['storage_path'],self::$userId);
            $this->fail('A different identity must not claim existing references');
        } catch (RuntimeException $error) {
            $this->assertSame('Attachment destination belongs to another pool identity',$error->getMessage());
        }
        $this->assertSame($documents,RestApiEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1 ORDER BY id',[$first['id']]));
        $this->assertSame($second,$this->row((int)$second['id']));
    }

    public function testDocumentReferencesReserveAnAbsentDestinationWithoutAnOpenIntent(): void
    {
        [$row,$journal,$line]=$this->bankReceipt();
        $target=pool_attach_target(self::$root,$journal,$line,$row['filename']);
        RestApiEnv::rows(self::$conn,"INSERT INTO documents(filename,filepath,source,source_id) VALUES ($1,$2,'kassekladde',$3)",
            [$row['filename'],ltrim($target['filepath'],'/'),$line]);
        $before=$this->accounting();
        $response=$this->approve($row);
        $this->assertSame(409,$response['status'],$response['body']);
        $this->assertSame('attachment_processed',$response['json']['data']['details']['reason']);
        $this->assertSame($before,$this->accounting());
        $this->assertSame('pending',$this->row((int)$row['id'])['review_state']);
        $this->assertSame($row['sha256'],hash_file('sha256',self::$root.'/'.$row['storage_path']));
        $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM documents WHERE pool_file_id=$1',[$row['id']]));
    }

    public static function recoveryKills(): array
    {
        return ['before rename'=>['recovery_reserved'],'after rename'=>['recovery_renamed']];
    }

    #[DataProvider('recoveryKills')]
    public function testKilledCollisionReconcilerFinishesFromDurableFallbackPath(string $point): void
    {
        $row=$this->receipt();
        $args=$this->args($row);
        $child=$this->start($args,'K1');
        $this->rendezvous($child);
        $intent=RestApiEnv::rows(self::$conn,'SELECT * FROM pool_file_moves WHERE pool_file_id=$1',[$row['id']])[0];
        $this->assertTrue(proc_terminate($child['process'],9));
        $this->finish($child);
        file_put_contents(self::$root.'/'.$intent['source_path'],'Different file');
        $reconciler=$this->start($args+['mode'=>'reconcile','intent'=>(int)$intent['id']],$point);
        $this->rendezvous($reconciler);
        $durable=RestApiEnv::rows(self::$conn,'SELECT * FROM pool_file_moves WHERE id=$1',[$intent['id']])[0];
        $fallback='pulje/'.$row['id'].'-'.$row['filename'];
        $this->assertSame($fallback,$durable['dest_path']);
        $this->assertSame('reserved',$durable['state']);
        if ($point === 'recovery_renamed') {
            $this->assertSame($row['sha256'],hash_file('sha256',self::$root.'/'.$fallback));
            $this->assertFileDoesNotExist(self::$root.'/'.$intent['staging_path']);
        } else {
            $this->assertFileDoesNotExist(self::$root.'/'.$fallback);
            $this->assertSame($row['sha256'],hash_file('sha256',self::$root.'/'.$intent['staging_path']));
        }
        $this->assertTrue(proc_terminate($reconciler['process'],9));
        $this->finish($reconciler);
        $this->assertSame($row['storage_path'],$this->row((int)$row['id'])['storage_path']);
        $this->assertSame(200,$this->runChildRequest($args+['mode'=>'sync'])['status']);
        $this->assertSame([['id'=>(string)$row['id']]],RestApiEnv::rows(self::$conn,'SELECT id FROM pool_files WHERE filename=$1',[$row['id'].'-'.$row['filename']]));
        $result=$this->runChildRequest($args+['mode'=>'reconcile','intent'=>(int)$intent['id']]);
        $this->assertSame('aborted',$result['state']);
        $actual=$this->row((int)$row['id']);
        $this->assertSame($fallback,$actual['storage_path']);
        $this->assertSame('pending',$actual['review_state']);
        $this->assertSame('ubehandlet',$actual['status']);
        $this->assertSame($row['sha256'],hash_file('sha256',self::$root.'/'.$actual['storage_path']));
        $this->assertSame('Different file',file_get_contents(self::$root.'/'.$intent['source_path']));
        $poolCount=count(RestApiEnv::rows(self::$conn,'SELECT id FROM pool_files'));
        $this->assertSame('aborted',$this->runChildRequest($args+['mode'=>'reconcile','intent'=>(int)$intent['id']])['state']);
        $this->assertSame(200,$this->runChildRequest($args+['mode'=>'sync'])['status']);
        $this->assertSame($poolCount,count(RestApiEnv::rows(self::$conn,'SELECT id FROM pool_files')));
        $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']]));
        $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT id FROM documents WHERE pool_file_id=$1',[$row['id']]));
        $retry=$this->runChildRequest($this->args($actual,$args['body']));
        $this->assertSame(200,$retry['status'],json_encode($retry));
        $this->assertAttached($row);
        $this->assertCount(1,RestApiEnv::rows(self::$conn,'SELECT id FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']]));
    }
}

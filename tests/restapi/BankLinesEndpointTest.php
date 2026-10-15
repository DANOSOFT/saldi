<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/BankLinesEndpointTest.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/support/T3BankLineTestCase.php';

final class BankLinesEndpointTest extends T3BankLineTestCase
{
    public function test_missing_paging_summary_and_web_document_matching(): void
    {
        $a = $this->bank('-612.50');
        $b = $this->bank('-100.25');
        $incoming = $this->bank('12.00');
        $matched = $this->bank('-300.00');
        $orphan = $this->bank('-50.00');
        RestApiEnv::rows(self::$conn,'DELETE FROM kassekladde WHERE id=$1',[$orphan['kassekladde_id']]);
        RestApiEnv::rows(self::$conn,"INSERT INTO documents(source,source_id,filename) VALUES ('kassekladde',$1,'web-match.pdf')",[$matched['kassekladde_id']]);
        $route = 'bank-lines/index.php?account=' . $this->account;
        $first = $this->http('GET',$route . '&state=missing&limit=1');
        $this->assertSame(200,$first['status'],$first['body']);
        $data = $first['json']['data'];
        $this->assertSame(2,$data['total']);
        $this->assertSame(1,$data['limit']);
        $this->assertSame(0,$data['offset']);
        $this->assertSame(['missing_count'=>2,'missing_total'=>'712.75'],$data['summary']);
        $this->assertSame((int)$b['id'],$data['items'][0]['id']);
        $this->assertSame('open',$data['items'][0]['state']);
        $this->assertSame('-100.25',$data['items'][0]['amount']);
        $this->assertSame('Bank, drift',$data['items'][0]['account']['name']);
        $this->assertSame((int)$b['kassekladde_id'],$data['items'][0]['journal']['kassekladde_id']);
        $this->assertFalse($data['items'][0]['journal']['posted']);
        $this->assertNull($data['items'][0]['reminder']);
        $this->assertNull($data['items'][0]['attachment']);
        $second = $this->http('GET',$route . '&limit=1&offset=1')['json']['data'];
        $this->assertSame((int)$a['id'],$second['items'][0]['id']);
        $empty = $this->http('GET',$route . '&offset=100')['json']['data'];
        $this->assertSame(2,$empty['total']);
        $this->assertSame([],$empty['items']);
        $this->assertSame($data['summary'],$empty['summary']);
        $found = $this->http('GET',$route . '&state=matched')['json']['data'];
        $this->assertSame(1,$found['total']);
        $this->assertSame((int)$matched['id'],$found['items'][0]['id']);
        RestApiEnv::rows(self::$conn,"DELETE FROM documents WHERE source='kassekladde' AND source_id::text=$1",[(string)$matched['kassekladde_id']]);
        $this->assertSame(3,$this->http('GET',$route)['json']['data']['total']);
        $all = $this->http('GET',$route . '&state=all')['json']['data'];
        $this->assertSame(4,$all['total']);
        $this->assertNotContains((int)$incoming['id'],array_column($all['items'],'id'));
        $orphanItem = array_column($all['items'],null,'id')[$orphan['id']];
        $this->assertNull($orphanItem['journal']);
        for ($i=0;$i<20;$i++) {
            $this->bank('-500000000000.00');
        }
        $this->assertSame('10000000001012.75',$this->http('GET',$route)['json']['data']['summary']['missing_total']);
    }

    public function test_targeted_and_broadcast_reminders_replay_and_closed_lines(): void
    {
        $line = $this->bank();
        $body = $this->body(['bank_line_id'=>(int)$line['id'],'target'=>self::$capture]);
        $first = $this->http('POST','bank-lines/remind.php',$body);
        $this->assertSame(201,$first['status'],$first['body']);
        $data = $first['json']['data'];
        $this->assertSame(1,$data['recipient_count']);
        $this->assertSame('requested',$data['bank_line']['state']);
        $this->assertSame('bank_capture',$data['bank_line']['reminder']['target_name']);
        $this->assertFalse($data['bank_line']['reminder']['all']);
        $this->assertMatchesRegularExpression('/Z$|\+00:00$/',$data['bank_line']['reminder']['sent_at']);
        $inbox = $this->http('GET','notifications/',null,self::$captureHeaders)['json']['data']['items'];
        $message = array_column($inbox,null,'reminder_id')[$data['reminder_id']];
        $this->assertSame('Mangler bilag',$message['title']);
        $this->assertSame('612.500',$message['amount']);
        $this->assertSame('2026-09-14',$message['date']);
        $this->assertSame('Påmindelse fra apitest: CIRCLE K 4055 AARHUS 14/9, 612,50.',$message['message']);
        $again = $this->http('POST','bank-lines/remind.php',$body);
        $this->assertSame($first['json'],$again['json']);
        $this->assertSame('true',$again['headers']['idempotent-replayed']);
        $changed = $this->http('POST','bank-lines/remind.php',$body + ['message'=>'Different']);
        $this->assertSame(409,$changed['status']);
        $this->assertSame('client_id_reused',$changed['json']['data']['details']['reason']);
        $broadcast = $this->http('POST','bank-lines/remind.php',$this->body(['bank_line_id'=>(int)$line['id'],'target'=>'all','message'=>'Send bank receipt']));
        $this->assertSame(201,$broadcast['status'],$broadcast['body']);
        $this->assertSame(2,$broadcast['json']['data']['recipient_count']);
        $this->assertTrue($broadcast['json']['data']['bank_line']['reminder']['all']);
        $this->assertNull($broadcast['json']['data']['bank_line']['reminder']['target_name']);
        foreach (['ignored','matched'] as $state) {
            if ($state === 'ignored') {
                RestApiEnv::rows(self::$conn,"UPDATE bank_lines SET state='ignored' WHERE id=$1",[$line['id']]);
            } else {
                RestApiEnv::rows(self::$conn,"INSERT INTO documents(source,source_id,filename) VALUES ('kassekladde',$1,'matched.pdf')",[$line['kassekladde_id']]);
            }
            $denied = $this->http('POST','bank-lines/remind.php',$this->body(['bank_line_id'=>(int)$line['id'],'target'=>'all']));
            $this->assertSame(409,$denied['status']);
            $this->assertSame('bank_line_closed',$denied['json']['data']['details']['reason']);
        }
    }

    public function test_ignore_reopen_and_replay(): void
    {
        $line = $this->bank();
        $body = $this->body(['bank_line_id'=>(int)$line['id'],'action'=>'ignore','note'=>'Private transaction']);
        $ignored = $this->http('POST','bank-lines/state.php',$body);
        $this->assertSame(200,$ignored['status'],$ignored['body']);
        $this->assertSame('ignored',$ignored['json']['data']['state']);
        $replay = $this->http('POST','bank-lines/state.php',$body);
        $this->assertSame($ignored['json'],$replay['json']);
        $this->assertSame('true',$replay['headers']['idempotent-replayed']);
        $reopened = $this->http('POST','bank-lines/state.php',$this->body(['bank_line_id'=>(int)$line['id'],'action'=>'reopen']));
        $this->assertSame('open',$reopened['json']['data']['state']);
        $this->http('POST','bank-lines/remind.php',$this->body(['bank_line_id'=>(int)$line['id'],'target'=>'all']));
        $this->http('POST','bank-lines/state.php',$this->body(['bank_line_id'=>(int)$line['id'],'action'=>'ignore']));
        $reopened = $this->http('POST','bank-lines/state.php',$this->body(['bank_line_id'=>(int)$line['id'],'action'=>'reopen']));
        $this->assertSame('requested',$reopened['json']['data']['state']);
    }

    public function test_rights_validation_and_method_contracts(): void
    {
        $line = $this->bank();
        foreach (['bank-lines/index.php'=>'GET','bank-lines/remind.php'=>'POST','bank-lines/state.php'=>'POST'] as $route=>$method) {
            $body = $method === 'POST' ? $this->body(['bank_line_id'=>(int)$line['id'],'target'=>'all','action'=>'ignore']) : null;
            $this->assertSame(401,$this->http($method,$route,$body,[])['status']);
            $this->assertSame(403,$this->http($method,$route,$body,self::$captureHeaders)['status']);
            $this->assertSame(405,$this->http($method === 'GET' ? 'POST' : 'GET',$route,$method === 'GET' ? [] : null)['status']);
        }
        RestApiEnv::rows(self::$conn,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',['0020000000000000',self::$userId]);
        $this->assertSame(200,$this->http('GET','bank-lines/index.php')['status']);
        $this->assertSame(403,$this->http('POST','bank-lines/state.php',$this->body(['bank_line_id'=>(int)$line['id'],'action'=>'ignore']))['status']);
        $this->assertSame(403,$this->http('POST','bank-lines/remind.php',$this->body(['bank_line_id'=>(int)$line['id'],'target'=>'all']))['status']);
        RestApiEnv::rows(self::$conn,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',['0010000000000000',self::$userId]);
        foreach (['state=bad','state[]=missing','account=1%20OR%201=1','limit=0','offset=-1'] as $query) {
            $bad = $this->http('GET','bank-lines/index.php?' . $query);
            $this->assertSame(422,$bad['status'],$bad['body']);
            $this->assertSame('validation_failed',$bad['json']['data']['error']);
        }
        foreach ([[],['client_id'=>RequestId::resolve(null),'bank_line_id'=>'1','target'=>'all'],
            $this->body(['bank_line_id'=>(int)$line['id'],'target'=>'inactive']),
            $this->body(['bank_line_id'=>(int)$line['id'],'target'=>'all','message'=>[]])] as $body) {
            $bad = $this->http('POST','bank-lines/remind.php',$body);
            $this->assertSame(422,$bad['status'],$bad['body']);
        }
        $missing = $this->http('POST','bank-lines/state.php',$this->body(['bank_line_id'=>2147483647,'action'=>'ignore']));
        $this->assertSame(404,$missing['status'],$missing['body']);
        $incoming = $this->bank('12.00');
        $this->assertSame(422,$this->http('POST','bank-lines/remind.php',$this->body(['bank_line_id'=>(int)$incoming['id'],'target'=>'all']))['status']);
    }
}

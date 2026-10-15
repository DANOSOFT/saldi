<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileT4RightsMatrixTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Capture receipt orders with nullable delivery and order dates for contract validation.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/T4Seed.php';

/** Every supported field endpoint method is exercised with live sessions and valid bodies. */
final class MobileT4RightsMatrixTest extends TestCase
{
    private static ?T4Seed $seed = null;

    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) { self::markTestSkipped($reason); }
        self::$seed = new T4Seed();
    }

    public static function tearDownAfterClass(): void { self::$seed?->close(); self::$seed = null; }

    /** @return iterable<string,array{string,string,string,string}> Independent inventory must match the slice registrations. */
    public static function operations(): iterable
    {
        foreach ([
            'stock/warehouses.php'=>['varer','read',['GET']],
            'stock/counts/snapshot.php'=>['varer','read',['GET']],
            'stock/counts/lookup.php'=>['varer','read',['GET']],
            'stock/counts/index.php'=>['varer','method',['GET','POST']],
            'creditor/receipts/orders.php'=>['kreditorordre','read',['GET']],
            'creditor/receipts/index.php'=>['kreditorordre','method',['GET','POST']],
            'creditor/receipts/attach.php'=>['kreditorordre','write',['POST']],
            'cases/index.php'=>['sager_loen','read',['GET']],
            'cases/tasks.php'=>['sager_loen','read',['GET']],
            'time-entries/hour-types.php'=>['sager_loen','read',['GET']],
            'time-entries/index.php'=>['sager_loen','method',['GET','POST','PATCH','DELETE']],
            'time-entries/submit.php'=>['sager_loen','write',['POST']],
            'bookings/index.php'=>['employee','own',['GET','POST','PATCH']],
            'bookings/status.php'=>['employee','own',['POST']],
            'bookings/employees.php'=>['employee','own',['GET']],
            'employee/expenses.php'=>['bilag','own',['GET','POST']],
            'employee/mileage/rates.php'=>['bilag','own',['GET']],
            'employee/mileage/trips.php'=>['bilag','own',['GET','POST','PATCH','DELETE']],
            'employee/mileage/statements.php'=>['bilag','own',['GET','POST']],
            'marken/summary.php'=>['bilag','own',['GET']],
        ] as $path=>[$module,$action,$methods]) {
            foreach ($methods as $method) {
                yield $path.' '.$method => [$path,$method,$module,$action === 'method' ? ($method === 'GET' ? 'read' : 'write') : $action];
            }
        }
    }

    /** @return array{string,?array,list<string>,string} Valid scenario and response contract name. */
    private function scenario(string $path,string $method,string $role): array
    {
        $s = self::$seed;
        $body = null;
        $headers = [];
        $schema = match ($path) {
            'stock/warehouses.php'=>'Warehouses','stock/counts/snapshot.php'=>'CountSnapshot','stock/counts/lookup.php'=>'CountLookup',
            'stock/counts/index.php'=>$method === 'GET' ? 'CountHistory' : 'CountSubmit',
            'creditor/receipts/orders.php'=>'ReceiptOrders','creditor/receipts/index.php'=>$method === 'GET' ? 'Receipts' : 'ReceiptSubmit',
            'creditor/receipts/attach.php'=>'ReceiptAttach','cases/index.php'=>'Cases','cases/tasks.php'=>'CaseTasks',
            'time-entries/hour-types.php'=>'HourTypes','time-entries/index.php'=>match ($method) {'GET'=>'TimeEntries','DELETE'=>'Deleted',default=>'TimeEntry'},
            'time-entries/submit.php'=>'TimeSubmit','bookings/index.php'=>$method === 'GET' ? 'BookingsDay' : 'Booking',
            'bookings/status.php'=>'Booking','bookings/employees.php'=>'BookingEmployees',
            'employee/expenses.php'=>$method === 'GET' ? 'Expenses' : 'Expense','employee/mileage/rates.php'=>'MileageRates',
            'employee/mileage/trips.php'=>match ($method) {'GET'=>'MileageTrips','DELETE'=>'Deleted',default=>'MileageTrip'},
            'employee/mileage/statements.php'=>$method === 'GET' ? 'MileageStatements' : 'MileageStatement','marken/summary.php'=>'MarkenSummary',
        };
        if (str_starts_with($path,'stock/') || str_starts_with($path,'creditor/')) {
            $stock = $s->stock();
            if ($path === 'stock/counts/snapshot.php') { $path .= '?lager=2'; }
            if ($path === 'stock/counts/lookup.php') { $path .= '?lager=2&code='.rawurlencode($stock['code']); }
            if ($path === 'stock/counts/index.php' && $method === 'POST') { $body = $s->countBody($stock); }
            if ($path === 'creditor/receipts/index.php') {
                if ($method === 'GET') { $path .= '?order_id='.$stock['order']; }
                else {
                    $body = ['client_id'=>T4Seed::uuid(),'order_id'=>$stock['order'],'receipt_date'=>'2026-10-06','lines'=>[['line_id'=>$stock['line'],'quantity'=>'6']]];
                    $detail = $s->request('GET','creditor/receipts/orders.php?id='.$stock['order']);
                    $this->success($detail,'ReceiptOrder');
                    $headers[] = 'If-Match: '.$detail['json']['data']['etag'];
                }
            }
            if ($path === 'creditor/receipts/attach.php') {
                $row = $s->base->receipt([],$role);
                $body = ['client_id'=>T4Seed::uuid(),'order_id'=>$stock['order'],'attachment_id'=>(int)$row['id']];
            }
        } elseif ($path === 'cases/tasks.php') { $path .= '?case_id='.$s->caseId; }
        elseif ($path === 'time-entries/index.php' && $method === 'POST') { $body = $s->timeBody(); }
        elseif (($path === 'time-entries/index.php' && in_array($method,['PATCH','DELETE'],true)) || $path === 'time-entries/submit.php') {
            $entry = $s->request('POST','time-entries/index.php',$s->timeBody(),$role);
            $this->success($entry,'TimeEntry');
            $id = $entry['json']['data']['id'];
            $headers[] = 'If-Match: '.$entry['json']['data']['etag'];
            $body = ['client_id'=>T4Seed::uuid()];
            if ($method === 'PATCH') { $body['hours'] = '6.50'; }
            if ($path === 'time-entries/submit.php') { $body['ids'] = [$id]; }
            else { $path .= '?id='.$id; }
        } elseif ($path === 'bookings/index.php' && $method === 'GET') { $path .= '?date=2026-10-06'; }
        elseif ($path === 'bookings/index.php' && $method === 'POST') { $body = $s->bookingBody(); }
        elseif (($path === 'bookings/index.php' && $method === 'PATCH') || $path === 'bookings/status.php') {
            $entry = $s->request('POST','bookings/index.php',$s->bookingBody(),$role);
            $this->success($entry,'Booking');
            $headers[] = 'If-Match: '.$entry['json']['data']['etag'];
            $body = ['client_id'=>T4Seed::uuid()];
            if ($method === 'PATCH') { $path .= '?id='.$entry['json']['data']['id']; $body['start'] = '09:00'; }
            else { $body += ['id'=>$entry['json']['data']['id'],'status'=>'udfoert']; }
        } elseif ($path === 'employee/expenses.php' && $method === 'POST') {
            $row = $s->base->receipt([],$role);
            $body = ['client_id'=>T4Seed::uuid(),'attachment_id'=>(int)$row['id']];
        } elseif ($path === 'employee/mileage/rates.php') { $path .= '?year=2026'; }
        elseif ($path === 'employee/mileage/trips.php' && $method === 'POST') { $body = $s->tripBody(); }
        elseif ($path === 'employee/mileage/trips.php' && in_array($method,['PATCH','DELETE'],true)) {
            $entry = $s->request('POST','employee/mileage/trips.php',$s->tripBody(),$role);
            $this->success($entry,'MileageTrip');
            $path .= '?id='.$entry['json']['data']['id'];
            $headers[] = 'If-Match: '.$entry['json']['data']['etag'];
            $body = ['client_id'=>T4Seed::uuid()];
            if ($method === 'PATCH') { $body['km'] = '41.0'; }
        } elseif ($path === 'employee/mileage/statements.php' && $method === 'POST') {
            $this->success($s->request('POST','employee/mileage/trips.php',$s->tripBody(),$role),'MileageTrip');
            $body = ['client_id'=>T4Seed::uuid(),'period_from'=>'2026-10-01','period_to'=>'2026-10-31'];
        }
        return [$path,$body,$headers,$schema];
    }

    private function permits(string $role,string $module,string $action,string $path,string $method): bool
    {
        if ($module === 'employee' || $module === 'sager_loen') {
            return $role !== 'registry' && ($module === 'employee' || in_array($role,['full','time'],true));
        }
        if ($path === 'employee/mileage/statements.php' && $method === 'POST' && $role === 'registry') { return false; }
        return $action === 'own' || in_array($role,['full','registry'],true) || ($action === 'read' &&
            (($module === 'varer' && $role === 'stockRead') || ($module === 'kreditorordre' && $role === 'receiptRead')));
    }

    #[DataProvider('operations')]
    public function testEveryT4MethodAndPrincipal(string $file,string $method,string $module,string $action): void
    {
        [$path,$body,$headers] = $this->scenario($file,$method,'full');
        $this->denied(self::$seed->request($method,$path,$body,'anonymous',$headers),401);
        foreach (['receipt','stockRead','receiptRead','time','noCase','full','registry'] as $role) {
            if ($file === 'creditor/receipts/attach.php' && $role === 'registry') {
                [$registryPath,$registryBody,$registryHeaders] = $this->scenario($file,$method,$role);
                $refused = self::$seed->request($method,$registryPath,$registryBody,$role,$registryHeaders);
                $this->denied($refused,404);
                continue;
            }
            if (!$this->permits($role,$module,$action,$file,$method)) {
                $response = self::$seed->request($method,$path,$body,$role,$headers);
                $this->denied($response,403);
                if ($role === 'registry' && ($module === 'employee' || $module === 'sager_loen' || $file === 'employee/mileage/statements.php')) {
                    $this->assertSame('no_employee_link',$response['json']['data']['details']['reason']);
                }
                continue;
            }
            [$ownPath,$ownBody,$ownHeaders,$schema] = $this->scenario($file,$method,$role);
            $first = self::$seed->request($method,$ownPath,$ownBody,$role,$ownHeaders);
            $this->success($first,$schema);
            if ($method !== 'GET') {
                $again = self::$seed->request($method,$ownPath,$ownBody,$role,$ownHeaders);
                $this->assertSame($first['status'],$again['status'],$again['body']);
                $this->assertSame($first['json'],$again['json']);
                $this->assertSame('true',$again['headers']['idempotent-replayed'] ?? null);
                $changed = $ownBody;
                // All handlers consult replay before business validation; a supported field changes its hash.
                if ($method === 'DELETE') { $changed['note'] = 'Changed intent'; }
                elseif ($file === 'time-entries/submit.php') { $changed['ids'] = array_merge($changed['ids'],[2147483647]); }
                elseif ($file === 'creditor/receipts/attach.php' || $file === 'employee/expenses.php') { $changed['attachment_id'] = 2147483647; }
                elseif ($file === 'bookings/status.php') { $changed['status'] = 'aflyst'; }
                elseif ($file === 'employee/mileage/statements.php') { $changed['period_from'] = '2026-10-02'; }
                elseif ($file === 'employee/mileage/trips.php') { $changed['km'] = '40.0'; }
                elseif ($file === 'creditor/receipts/index.php') { $changed['lines'][0]['quantity'] = '1'; }
                else { $changed[$module === 'sager_loen' ? 'text' : 'note'] = 'Changed intent'; }
                $conflict = self::$seed->request($method,$ownPath,$changed,$role,$ownHeaders);
                $this->denied($conflict,409);
                $this->assertSame('client_id_reused',$conflict['json']['data']['details']['reason'],$conflict['body']);
            }
        }
    }

    private function success(array $response,string $schema): void
    {
        $this->assertGreaterThanOrEqual(200,$response['status'],$response['body']);
        $this->assertLessThan(300,$response['status'],$response['body']);
        $this->assertTrue($response['json']['success']);
        $this->assertArrayHasKey('x-request-id',$response['headers']);
        if (in_array($schema,['TimeEntry','TimeEntries','TimeSubmit','HourTypes'],true)) {
            $this->privateKeys($response['json']['data']);
        }
        self::$seed->contracts[] = ['schema'=>$schema,'response'=>$response['json']];
    }

    private function denied(array $response,int $status): void
    {
        $this->assertSame($status,$response['status'],$response['body']);
        $this->assertFalse($response['json']['success']);
        $this->assertArrayHasKey('x-request-id',$response['headers']);
        if ($status !== 401) {
            $this->assertSame(match ($status) {
                403=>'permission_denied',404=>'not_found',409=>'conflict',412=>'precondition_failed',422=>'validation_failed',
            },$response['json']['data']['error']);
        }
        self::$seed->contractErrors[] = ['status'=>$status,'response'=>$response['json']];
    }

    public function testPopulatedListsDetailsAndValidatorErrorsMatchTheContract(): void
    {
        $s = self::$seed;
        $count = $s->request('POST','stock/counts/index.php',$s->countBody($s->stock()));
        $this->success($count,'CountSubmit');
        $this->success($s->request('GET','stock/counts/index.php'),'CountHistory');
        $this->success($s->request('GET','stock/counts/index.php?id='.$count['json']['data']['count_id']),'CountDetail');
        [$path,$body,$headers] = $this->scenario('creditor/receipts/index.php','POST','full');
        $this->success($s->request('POST',$path,$body,'full',$headers),'ReceiptSubmit');
        $receipts = $s->request('GET','creditor/receipts/index.php?order_id='.$body['order_id']);
        $this->success($receipts,'Receipts');
        $this->assertCount(1,$receipts['json']['data']['items']);

        $file = $s->base->receipt([],'receipt');
        $this->success($s->request('POST','employee/expenses.php',['client_id'=>T4Seed::uuid(),'attachment_id'=>(int)$file['id']],'receipt'),'Expense');
        $this->success($s->request('GET','employee/expenses.php',null,'receipt'),'Expenses');
        $trip = $s->request('POST','employee/mileage/trips.php',$s->tripBody(),'receipt');
        $this->success($trip,'MileageTrip');
        $this->success($s->request('GET','employee/mileage/trips.php',null,'receipt'),'MileageTrips');
        $this->success($s->request('GET','employee/mileage/trips.php?id='.$trip['json']['data']['id'],null,'receipt'),'MileageTrip');
        $statement = $s->request('POST','employee/mileage/statements.php',[
            'client_id'=>T4Seed::uuid(),'period_from'=>'2026-10-01','period_to'=>'2026-10-31'],'receipt');
        $this->success($statement,'MileageStatement');
        $this->success($s->request('GET','employee/mileage/statements.php',null,'receipt'),'MileageStatements');
        $this->success($s->request('GET','employee/mileage/statements.php?id='.$statement['json']['data']['statement_id'],null,'receipt'),'MileageStatement');

        $invalid = $s->timeBody();
        $invalid['hours'] = 7.5;
        $error = $s->request('POST','time-entries/index.php',$invalid,'time');
        $this->denied($error,422);
        $entry = $s->request('POST','time-entries/index.php',$s->timeBody(),'time')['json']['data'];
        $error = $s->request('PATCH','time-entries/index.php?id='.$entry['id'],[
            'client_id'=>T4Seed::uuid(),'hours'=>'6.00'],'time',['If-Match: "'.str_repeat('0',64).'"']);
        $this->denied($error,412);
    }

    public function testReceiptOrdersWithMissingDatesMatchTheContract(): void
    {
        $s = self::$seed;
        foreach ([['2026-10-01', null], [null, '2026-10-06'], [null, null]] as [$orderDate, $deliveryDate]) {
            $stock = $s->stock();
            $s->sql('UPDATE ordrer SET ordredate=$1,levdate=$2 WHERE id=$3', [$orderDate, $deliveryDate, $stock['order']]);

            $list = $s->request('GET', 'creditor/receipts/orders.php?limit=100');
            $this->success($list, 'ReceiptOrders');
            $items = array_column($list['json']['data']['items'], null, 'order_id');
            $this->assertArrayHasKey($stock['order'], $items);
            $this->assertSame($orderDate, $items[$stock['order']]['order_date']);
            $this->assertSame($deliveryDate, $items[$stock['order']]['levdate']);

            $detail = $s->request('GET', 'creditor/receipts/orders.php?id='.$stock['order']);
            $this->success($detail, 'ReceiptOrder');
            $this->assertSame($stock['order'], $detail['json']['data']['order_id']);
            $this->assertSame($orderDate, $detail['json']['data']['order_date']);
            $this->assertSame($deliveryDate, $detail['json']['data']['levdate']);
        }
    }

    public function testBookingsAssigneeAdministratorAndCaseLeaderAndUnlinkedUser(): void
    {
        $s = self::$seed;
        foreach (['receipt','admin','leader'] as $role) {
            $created = $s->request('POST','bookings/index.php',$s->bookingBody(),'receipt');
            $this->success($created,'Booking');
            $data = $created['json']['data'];
            $this->success($s->request('PATCH','bookings/index.php?id='.$data['id'],['client_id'=>T4Seed::uuid(),'start'=>'10:00'],
                $role,['If-Match: '.$data['etag']]),'Booking');
        }
        $created = $s->request('POST','bookings/index.php',$s->bookingBody(),'receipt')['json']['data'];
        $this->denied($s->request('POST','bookings/status.php',['client_id'=>T4Seed::uuid(),'id'=>$created['id'],'status'=>'udfoert'],
            'other',['If-Match: '.$created['etag']]),403);
        $this->denied($s->request('POST','bookings/index.php',$s->bookingBody()+['employee_ansat_id'=>$s->employees['other']],'receipt'),403);
        $id = (int)$s->base->users['receipt']['id'];
        $s->sql('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[$id]);
        try {
            $response = $s->request('GET','bookings/index.php?date=2026-10-06',null,'receipt');
            $this->denied($response,403);
            $this->assertSame('no_employee_link',$response['json']['data']['details']['reason']);
        } finally { $s->sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2',[$s->employees['receipt'],$id]); }
    }

    public function testCountSessionCannotPostWithAnotherClientAndClaimsOfOthersAreHidden(): void
    {
        $s = self::$seed;
        $body = $s->countBody($s->stock());
        $this->success($s->request('POST','stock/counts/index.php',$body),'CountSubmit');
        $body['client_id'] = T4Seed::uuid();
        $response = $s->request('POST','stock/counts/index.php',$body);
        $this->denied($response,409);
        $this->assertSame('count_already_posted',$response['json']['data']['details']['reason']);
        $trip = $s->request('POST','employee/mileage/trips.php',$s->tripBody(),'receipt')['json']['data'];
        foreach (['other','full','registry'] as $role) {
            $response = $s->request('GET','employee/mileage/trips.php?id='.$trip['id'],null,$role);
            $this->assertSame(404,$response['status'],$response['body']);
            $this->assertSame('not_found',$response['json']['data']['error']);
            $row = $s->base->receipt([],'receipt');
            $response = $s->request('POST','employee/expenses.php',['client_id'=>T4Seed::uuid(),'attachment_id'=>(int)$row['id']],$role);
            $this->assertSame(404,$response['status'],$response['body']);
        }
    }

    public function testMixedWebSeddelShowsOnlyOwnPositionAndNeverPayroll(): void
    {
        $s = self::$seed;
        $first = $s->request('POST','time-entries/index.php',$s->timeBody(),'time')['json']['data'];
        $s->sql('UPDATE loen SET ansatte=$1,timer=$2,t50pct=$3,t100pct=$3 WHERE id=$4',
            [$s->employees['other']."\t".$s->employees['time'],"23.75\t7.50","0\t0",$first['id']]);
        $response = $s->request('GET','time-entries/index.php?id='.$first['id'],null,'time');
        $this->success($response,'TimeEntry');
        $entry = $response['json']['data'];
        $this->assertSame('7.50',$entry['hours']);
        $this->assertFalse($entry['editable']);
        $this->assertSame('web_seddel',$entry['read_only_reason']);
        foreach ([['PATCH','time-entries/index.php?id='.$entry['id'],['hours'=>'1.00']],
            ['DELETE','time-entries/index.php?id='.$entry['id'],[]],['POST','time-entries/submit.php',['ids'=>[$entry['id']]]]] as [$method,$path,$fields]) {
            $error = $s->request($method,$path,['client_id'=>T4Seed::uuid()]+$fields,'time',['If-Match: '.$entry['etag']]);
            $this->denied($error,409);
            $this->assertSame('entry_read_only',$error['json']['data']['details']['reason']);
        }
        $this->assertSame("23.75\t7.50",$s->sql('SELECT timer FROM loen WHERE id=$1',[$entry['id']])[0]['timer']);
        $own = $s->request('POST','time-entries/index.php',$s->timeBody(),'full')['json']['data'];
        $this->assertSame(404,$s->request('GET','time-entries/index.php?id='.$own['id'],null,'time')['status']);
        $this->privateKeys($entry);
        $this->privateKeys($s->request('GET','time-entries/index.php',null,'time')['json']['data']);
    }

    private function privateKeys($value): void
    {
        if (!is_array($value)) { return; }
        foreach ($value as $key=>$child) {
            if (is_string($key)) { $this->assertDoesNotMatchRegularExpression('/loen|sum|rate|wage/i',$key); }
            $this->privateKeys($child);
        }
    }
}

<?php
// 20261117 CDX/ASR Exercise opt-in scheduling, approval, payment holds and durable delivery without sending mail.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reminderFixtures.php';
require_once __DIR__ . '/../../includes/reminderAutomation.php';
require_once __DIR__ . '/../../includes/opdat_func/reminderAutomation.php';

final class ReminderAutomationTest extends StripeDatabaseTestCase
{
    private ReminderAutomation $automation;
    private int $invoice;
    private int $debtor;
    private int $sent = 0;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ordrer','ordrelinjer','openpost'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            StripeTestEnvironment::query("CREATE TEMP SEQUENCE auto_{$table}_seq START 900000000");
            StripeTestEnvironment::query("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.auto_{$table}_seq')");
        }
        reminder_automation_schema_ensure();
        StripeReminderFixtures::forms();
        StripeTestEnvironment::setting('stripe','reminder_format','classic');
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='10',box7='10' WHERE art='DIV' AND kodenr='4'");
        StripeTestEnvironment::query("UPDATE grupper SET box3='DKK' WHERE art='DG' AND kodenr='1'");
        $this->debtor = StripeFixtures::debtor(['gruppe'=>1]);
        $this->invoice = StripeReminderFixtures::invoiceOn($this->debtor, '2026-01-01', ['udskriv_til'=>'email']);
        $GLOBALS['stripe_charge_today'] = '2026-01-20';
        $this->automation = $this->service('2026-01-20');
    }

    private function service(string $date): ReminderAutomation
    {
        return new ReminderAutomation($GLOBALS['connection'], $GLOBALS['db'], dirname(__DIR__, 2), $date);
    }

    /** @return array{sent: bool,result: bool,message: string,channel: string} */
    private function transport(): array
    {
        $this->sent++;
        return ['sent'=>true,'result'=>true,'message'=>'','channel'=>'classic'];
    }

    private function runAutomation(): array
    {
        return $this->automation->run(function (int $id) {
            $this->automation->send($id, fn()=> $this->transport(), 'test');
        }, 'test');
    }

    public function testWebActionsRejectStaleCompanyCsrfAndMissingWriteRights(): void
    {
        require_once __DIR__ . '/../../includes/reminderAutomationActions.php';
        $csrf = bin2hex(random_bytes(16));
        $request = ['action'=>'configure','mode'=>'automatic','csrf_token'=>$csrf,'ledger'=>$GLOBALS['db']];
        foreach ([[$request, false, true], [$request, true, false],
            [array_replace($request,['csrf_token'=>'']), true, true],
            [array_replace($request,['ledger'=>'another_ledger']), true, true]] as [$input,$write,$configure]) {
            try {
                reminder_automation_action($this->automation,$input,$csrf,$GLOBALS['db'],'test',$write,$configure,static function(){});
                $this->fail('Unauthorized mutation accepted.');
            } catch (RuntimeException $e) {
                $this->assertSame('off',$this->automation->config()['mode']);
            }
        }
        $this->assertSame([], $this->automation->jobs());
    }

    /** @return void Isolated posting accounts and transaction rows. */
    private function postingFixtures(): void
    {
        StripeTestEnvironment::query('CREATE TEMP TABLE transaktioner (LIKE public.transaktioner INCLUDING ALL)');
        StripeTestEnvironment::query('CREATE TEMP SEQUENCE auto_trans_seq START 900000000');
        StripeTestEnvironment::query("ALTER TABLE pg_temp.transaktioner ALTER COLUMN id SET DEFAULT nextval('pg_temp.auto_trans_seq')");
        StripeTestEnvironment::query("DELETE FROM grupper WHERE art='RA'");
        StripeFixtures::insert('grupper',['art'=>'RA','kodenr'=>5,'box1'=>'01','box2'=>'2026','box3'=>'12','box4'=>'2026','box5'=>'on','fiscal_year'=>5]);
        StripeTestEnvironment::query("UPDATE grupper SET box8='',box4='1000' WHERE art='VG' AND kodenr=1");
        StripeTestEnvironment::query("UPDATE grupper SET box2='56000' WHERE art='DG' AND kodenr=1");
        $GLOBALS['regnaar']='5';
        StripeTestEnvironment::query('CREATE TEMP TABLE kontoplan (LIKE public.kontoplan INCLUDING ALL)');
        foreach ([1000,56000] as $account) {
            StripeTestEnvironment::query("INSERT INTO kontoplan(id,kontonr,regnskabsaar,kontotype,lukket) VALUES(cast($1 AS int),cast($1 AS int),5,'D','')", [$account]);
        }
    }

    public function testClassicFlowBooksFeesAtomicallyAndStopsAfterThirdReminder(): void
    {
        $this->postingFixtures();
        $this->automation->configure('automatic','test');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $first=(int)$this->automation->jobs()[0]['order_id'];
        $this->automation=$this->service('2026-01-29');
        $this->assertSame(0,$this->runAutomation()['created'],'No escalation before the actual sending deadline.');
        foreach (['2026-02-01','2026-02-13'] as $today) {
            $GLOBALS['stripe_charge_today']=$today;
            $this->automation=$this->service($today);
            $run=$this->runAutomation();
            $this->assertSame([], $run['errors']);
            $this->assertSame(1,$run['created']);
            $this->assertSame(1,$run['sent']);
        }
        $this->assertSame('4',StripeFixtures::row('ordrer',$first)['status']);
        $this->assertSame('300.000',pg_fetch_result(StripeTestEnvironment::query('SELECT sum(debet) FROM transaktioner'),0,0));
        $this->assertSame('0.000',pg_fetch_result(StripeTestEnvironment::query('SELECT sum(debet-kredit) FROM transaktioner'),0,0));
        $this->automation=$this->service('2026-03-01');
        $this->assertSame(0,$this->runAutomation()['created']);
        $this->assertCount(3,$this->automation->jobs());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testRealPdfRendererUsesTheTrackedDeliveryWithoutSendingRealMail(): void
    {
        if (!is_executable('/usr/bin/ps2pdf')) $this->markTestSkipped('ps2pdf required');
        $this->assertFalse(function_exists('send_mails'));
        function send_mails(...$args) {
            $GLOBALS['reminder_test_delivery'][] = ['id'=>(int)$args[0], 'pdf'=>file_get_contents($args[1])];
            return 'OK';
        }
        $cwd = getcwd();
        $work = sys_get_temp_dir() . '/saldi-auto-print-' . bin2hex(random_bytes(8));
        mkdir($work . '/debitor', 0700, true);
        mkdir($work . '/logolib/1', 0700, true);
        mkdir($work . '/temp/' . $GLOBALS['db'], 0700, true);
        symlink(dirname(__DIR__,2) . '/includes', $work . '/includes');
        symlink(dirname(__DIR__,2) . '/importfiler', $work . '/importfiler');
        $_SERVER['REQUEST_URI']='/test/debitor/reminderAutomation.php';
        $GLOBALS['db_id']=1;
        $GLOBALS['ps2pdf']='/usr/bin/ps2pdf';
        $GLOBALS['pdftk']='/usr/bin/pdftk';
        $GLOBALS['formularsprog']='dansk';
        $GLOBALS['reminder_test_delivery']=[];
        $this->automation->configure('automatic','test');
        try {
            chdir($work . '/debitor');
            $run = $this->automation->run('reminder_automation_render_send','test');
            $this->assertSame([], $run['errors']);
            $this->assertSame(1, $run['sent']);
            $this->assertCount(1, $GLOBALS['reminder_test_delivery']);
            $pdf = $GLOBALS['reminder_test_delivery'][0]['pdf'];
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertGreaterThan(1000,strlen($pdf));
            $this->assertSame(0,$this->automation->run('reminder_automation_render_send','test')['sent']);
            $this->assertCount(1, $GLOBALS['reminder_test_delivery']);
        } finally {
            chdir($cwd);
            stripe_invoice_pdf_cleanup($work);
            unset($GLOBALS['reminder_test_delivery']);
        }
    }

    public function testPendingCardPaymentAndPaidInvoiceInWaitingDraftPreventSending(): void
    {
        $payment = StripeFixtures::insert('stripe_payments', ['ordre_id'=>$this->invoice,
            'payment_intent_id'=>'pi_auto_' . bin2hex(random_bytes(6)), 'amount_ore'=>34000,
            'status'=>'processing', 'source'=>'page', 'save_card'=>false]);
        $this->automation->configure('approval','test');
        $this->assertSame(0,$this->runAutomation()['created']);
        StripeTestEnvironment::query("UPDATE stripe_payments SET status='canceled' WHERE id=$1",[$payment]);
        $second = StripeReminderFixtures::invoiceOn($this->debtor,'2026-01-02',['udskriv_til'=>'email']);
        $this->assertSame(1,$this->runAutomation()['created']);
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->automation->approve($id,'test');
        StripeTestEnvironment::query("UPDATE openpost SET udlignet='1' WHERE id=$1",[StripeReminderFixtures::openpost($second)['id']]);
        $this->runAutomation();
        $this->assertSame(0,$this->sent);
        $this->assertSame('held',$this->automation->job($id)['state']);
    }

    public function testPostingRollsBackWhenTheNextDraftCannotBeQueued(): void
    {
        $this->postingFixtures();
        $this->automation->configure('automatic','test');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $id=(int)$this->automation->jobs()[0]['order_id'];
        // Fail after posting and draft creation, at the queue insert: all three must roll back together.
        StripeTestEnvironment::query('ALTER TABLE reminder_automation_jobs ADD CONSTRAINT fixture_no_next CHECK(previous_id IS NULL)');
        $this->automation=$this->service('2026-02-01');
        $GLOBALS['stripe_charge_today']='2026-02-01';
        $run=$this->runAutomation();
        $this->assertCount(1,$run['errors']);
        $this->assertSame(0,$run['created']);
        $this->assertCount(1,$this->automation->jobs());
        $this->assertSame('2',StripeFixtures::row('ordrer',$id)['status']);
        $this->assertSame('0',pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM transaktioner'),0,0));
        $this->assertSame('1',pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM openpost'),0,0));
        $this->assertSame('1',pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM ordrer WHERE art LIKE 'R%'"),0,0));
    }

    public function testClosedPostingAccountStopsEscalation(): void
    {
        $this->postingFixtures();
        $this->automation->configure('automatic','test');
        $this->runAutomation();
        StripeTestEnvironment::query("UPDATE kontoplan SET lukket='on' WHERE kontonr=1000");
        $this->automation=$this->service('2026-02-01');
        $run=$this->runAutomation();
        $this->assertCount(1,$run['errors']);
        $this->assertStringContainsString('lukket',$run['errors'][0]);
        $this->assertStringContainsString('lukket',$this->automation->config()['last_error']);
        $this->assertSame(0,$run['created']);
        $this->assertSame('0',pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM transaktioner'),0,0));
    }

    public function testAnotherConnectionOwnsTheRunLockAndNestedSendCannotClaimTwice(): void
    {
        $parameters=[];
        foreach (['host'=>'SALDI_TEST_PGHOST','user'=>'SALDI_TEST_PGUSER','password'=>'SALDI_TEST_PGPASSWORD','dbname'=>'SALDI_TEST_DB'] as $key=>$env) {
            $parameters[]=$key . "='" . str_replace(["\\","'"],["\\\\","\\'"],(string)getenv($env)) . "'";
        }
        $second=pg_connect(implode(' ',$parameters), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($second);
        $this->automation->configure('automatic','test');
        try {
            pg_query($second,"SELECT pg_advisory_lock(hashtext(current_database()),hashtext('reminder_automation'))");
            try {
                $this->runAutomation();
                $this->fail('A second process must not create or send.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('allerede i gang',$e->getMessage());
            }
            $this->assertSame([],$this->automation->jobs());
        } finally {
            pg_query($second,"SELECT pg_advisory_unlock(hashtext(current_database()),hashtext('reminder_automation'))");
            pg_close($second);
        }
        $run=$this->automation->run(function(int $id) {
            $this->automation->send($id,function() use ($id) {
                $duplicate=$this->automation->send($id,fn()=> $this->transport(),'second');
                $this->assertFalse($duplicate['sent']);
                return $this->transport();
            },'first');
        },'test');
        $this->assertSame(1,$run['sent']);
        $this->assertSame(1,$this->sent);
    }

    public function testTrackedLinkMailFailureHasNoClassicFallbackAndInvalidPdfNeverSends(): void
    {
        require_once __DIR__ . '/../../includes/formFuncIncludes/reminderMailDelivery.php';
        StripeTestEnvironment::setting('stripe','reminder_format','auto');
        StripeTestEnvironment::query("UPDATE ordrer SET udskriv_til='Link' WHERE id=$1",[$this->invoice]);
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->assertNotNull(stripe_reminder_row($id));
        $pdf=tempnam(sys_get_temp_dir(),'auto-reminder-');
        try {
            file_put_contents($pdf,'%PDF-1.4 fixture');
            StripeTestTransport::$mailSucceeds=false;
            $result=formSendReminderEntry($id,$pdf,'customer@stripe-tests.invalid','Dansk',6, function(){ $this->sent++; return 'OK'; });
            $this->assertFalse($result['sent']);
            $this->assertSame(0,$this->sent);
            $this->assertSame('failed',$this->automation->job($id)['state']);
            $this->assertCount(1,StripeTestTransport::$mails);
            $this->automation->approve($id,'test',true);
            file_put_contents($pdf,'broken PDF');
            $result=formSendReminderEntry($id,$pdf,'customer@stripe-tests.invalid','Dansk',6, fn()=> 'OK');
            $this->assertFalse($result['sent']);
            $this->assertCount(1,StripeTestTransport::$mails);
            $this->assertSame('failed',$this->automation->job($id)['state']);
        } finally {
            unlink($pdf);
        }
    }

    public function testStartsOffAndCopiedConfigurationIsNotArmed(): void
    {
        $this->assertSame(['mode'=>'off','armed'=>false,'last_run'=>null,'last_error'=>''], $this->automation->config());
        $this->assertCount(1, $this->automation->preview());
        $this->automation->configure('automatic','test');
        StripeTestEnvironment::query("UPDATE reminder_automation_config SET binding='copied'");
        $this->assertFalse($this->automation->config()['armed']);
        $this->expectException(RuntimeException::class);
        $this->runAutomation();
    }

    public function testApprovalCreatesExactlyOneDraftUntilExplicitApproval(): void
    {
        $this->automation->configure('approval','test');
        $run = $this->runAutomation();
        $this->assertSame([], $run['errors']);
        $this->assertSame(1, $run['created']);
        $this->assertSame(0, $this->sent);
        $id = (int)$this->automation->jobs()[0]['order_id'];
        $this->assertSame('approval',$this->automation->job($id)['state']);
        $this->assertSame(0,$this->runAutomation()['created']);
        $this->automation->approve($id,'reviewer');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $this->assertSame(1,$this->sent);
        $this->assertSame(0,$this->runAutomation()['sent']);
        $this->assertSame('2026-01-20',$this->automation->job($id)['sent_date']);
    }

    public function testAutomaticModeDoesNotPromoteOldApprovalDrafts(): void
    {
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $this->automation->configure('automatic','test');
        $this->assertSame(0,$this->runAutomation()['sent']);
        $this->assertSame('approval',$this->automation->jobs()[0]['state']);
    }

    public function testAutomaticCreatesAndSendsOnceAndTracksTheRealSendDate(): void
    {
        $this->automation->configure('automatic','test');
        $run=$this->runAutomation();
        $this->assertSame([], $run['errors']);
        $this->assertSame(1,$run['created']);
        $this->assertSame(1,$run['sent']);
        $this->assertSame(1,$this->sent);
        $this->assertSame(0,$this->runAutomation()['created']);
        $this->assertSame(1,$this->sent);
        $this->assertSame('2026-01-20',reminder_sent_date((int)$this->automation->jobs()[0]['order_id']));
    }

    public function testPaymentAfterApprovalStopsSending(): void
    {
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->automation->approve($id,'test');
        StripeTestEnvironment::query("UPDATE openpost SET udlignet='1'");
        $this->runAutomation();
        $this->assertSame(0,$this->sent);
        $this->assertSame('held',$this->automation->job($id)['state']);
    }

    public function testAmbiguousMailFailureIsNeverRetriedAutomatically(): void
    {
        $this->automation->configure('automatic','test');
        $run=$this->automation->run(function(int $id) {
            $this->automation->send($id,function(){ $this->sent++; throw new RuntimeException('SMTP disconnected'); },'test');
        },'test');
        $this->assertCount(1,$run['errors']);
        $this->assertSame(1,$this->sent);
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->assertSame('failed',$this->automation->job($id)['state']);
        $this->runAutomation();
        $this->assertSame(1,$this->sent);
        try {
            $this->automation->approve($id,'test');
            $this->fail('An uncertain attempt requires explicit verification.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('uafklaret',$e->getMessage());
        }
        $this->automation->approve($id,'test',true);
        $this->assertSame(1,$this->runAutomation()['sent']);
    }

    public function testDisablingAutomationHoldsQueuedDrafts(): void
    {
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->automation->approve($id,'test');
        $this->automation->configure('off','test');
        $this->assertSame('approval',$this->automation->job($id)['state']);
        $this->assertFalse($this->automation->config()['armed']);
    }

    public function testOpenCreditStopsCreationAndExistingManualChainsAreNotAdopted(): void
    {
        $this->automation->configure('automatic','test');
        StripeFixtures::insert('openpost',['konto_id'=>$this->debtor,'amount'=>-10,'udlignet'=>'0']);
        $this->assertStringContainsString('Udlign',$this->automation->preview()[0]['reason']);
        $this->assertSame(0,$this->runAutomation()['created']);
        StripeTestEnvironment::query('DELETE FROM openpost WHERE amount<0');
        StripeReminderFixtures::reminder($this->debtor,[$this->invoice]);
        $this->assertSame([], $this->automation->preview());
    }
}

<?php
// 20261117 CDX/ASR Exercise opt-in scheduling, approval, payment holds and durable delivery without sending mail.
// 20261126 CL/ASR Stripe port: the three tests that book the previous reminder run in their own process. ReminderAutomation::bookPrevious() loads includes/openpost.php, whose bogfor_nu() and vis_aabne_poster() are also declared by includes/ordrefunc.php and includes/reportFunc/showOpenPosts.php.
//                 A run of all suites with the test database, where test files had loaded either one first, stopped with "Cannot redeclare".
// 20261126 ASR Review I12: a run takes no table lock on ordrer, and preview() can be limited to one account.
// 20261126 ASR Review I7 (owner default Q10): a tracked reminder sent by hand while the flow is off or the ledger is a copy gets a message, not an exception.
// 20261126 ASR Review R2: that message follows the job's state; a sent, held, failed or uncertain reminder is not offered to be deleted.
// 20261126 ASR Review GPT-R-1: the posting fixtures put the item and debtor groups in the fiscal year they post in (grupper has one row per year).
//              Booking the previous reminder checks and uses the groups of its fiscal year, not another year's rows.
// 20261126 ASR Review GPT-R-5: a failed attempt that is paused and approved again still needs the check of the mail server's log.
// 20261126 ASR Review GPT-R-6: a delivery confirmed in the mail server's log is recorded as sent on that day, without a new mail, and is escalated from there.
// 20261126 ASR Review GPT-R-7: a draft approved later than it was made goes out dated on the day it is sent, with its deadline from that day in the mail and the PDF.
// 20261126 ASR Review GPT-R-9: the automatic reminders go to the customer's reminder contacts too, as the reminder editor sends them, and a contact counts as the address.
// 20261126 ASR Review GPT-F-1: the run books a previous reminder left at status 3 without anything posted and escalates it; one at status 3 with its postings is not posted again.
// 20261126 ASR Review GPT-F-2: in a page request a posting the database rejects for one candidate is rolled back and reported, and the run goes on with the next candidate.
// 20261126 ASR Review GPT-G-4: a group without a row in the reminder's fiscal year stops the run and the manual booking with the same reason, and nothing is posted; set up for the year, it is booked with that year's accounts.
// 20261126 ASR Review CL2-1: a sent 1st reminder without a fee, whose sum the reminder page wrote as '0.000', is booked and escalated by the run.
// 20261126 ASR Review AR2-2: a reminder whose sending failed, also once paused, is refused by "Ryk alle"'s booking and by Ny rykker with a pointer to Automatiske rykkere and no offer to delete it; queued again, it keeps the draft wording.
// 20261127 ASR Review GPT-H-1: send() holds the customer's dispatch lock while the reminder goes out and until its job is saved as sent, and lets it go afterwards.
// 20261127 ASR Review AR3-1: a paused draft that was never attempted, or whose attempt was checked and approved again, is refused with "approve it under Automatiske rykkere, or delete it" (8234); paused with an unresolved attempt it keeps 8231.
// 20261127 CL/ASR Wording of a comment; no code change.
require_once __DIR__ . '/support/bootstrap.php';
require_once __DIR__ . '/support/reminderFixtures.php';
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
        StripeTestEnvironment::query("UPDATE grupper SET box8='',box4='1000',fiscal_year=5 WHERE art='VG' AND kodenr=1");
        StripeTestEnvironment::query("UPDATE grupper SET box2='56000',fiscal_year=5 WHERE art='DG' AND kodenr=1");
        $GLOBALS['regnaar']='5';
        StripeTestEnvironment::query('CREATE TEMP TABLE kontoplan (LIKE public.kontoplan INCLUDING ALL)');
        foreach ([1000,56000] as $account) {
            StripeTestEnvironment::query("INSERT INTO kontoplan(id,kontonr,regnskabsaar,kontotype,lukket) VALUES(cast($1 AS int),cast($1 AS int),5,'D','')", [$account]);
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
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

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
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

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
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

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testBookingThePreviousReminderUsesTheGroupsOfItsFiscalYear(): void
    {
        $this->postingFixtures();
        // Last year's rows of the same groups: a stock-tracked fee group and accounts that are not in this year's chart.
        StripeFixtures::insert('grupper',['art'=>'VG','kodenr'=>1,'box4'=>'1999','box8'=>'on','fiscal_year'=>4]);
        StripeFixtures::insert('grupper',['art'=>'DG','kodenr'=>1,'box2'=>'55999','box3'=>'DKK','fiscal_year'=>4]);
        $this->automation->configure('automatic','test');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $first=(int)$this->automation->jobs()[0]['order_id'];
        $GLOBALS['stripe_charge_today']='2026-02-01';
        $this->automation=$this->service('2026-02-01');
        $run=$this->runAutomation();
        $this->assertSame([], $run['errors']);
        $this->assertSame(1,$run['created']);
        $this->assertSame('4',StripeFixtures::row('ordrer',$first)['status']);
        $journal=pg_fetch_all(StripeTestEnvironment::query('SELECT kontonr,debet,kredit FROM transaktioner WHERE ordre_id=$1 ORDER BY debet DESC',[$first]));
        $this->assertSame([['56000','100.000','0.000'],['1000','0.000','100.000']],
            array_map(static fn(array $r): array => [(string)$r['kontonr'],(string)$r['debet'],(string)$r['kredit']], $journal));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAGroupWithoutARowInTheRemindersYearStopsTheRunAndBogfoerAlike(): void
    {
        $this->postingFixtures();
        $this->automation->configure('automatic','test');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $first=(int)$this->automation->jobs()[0]['order_id'];
        // The fee item's group has last year's row only (accounts that exist), as on a ledger where the group was not set up for the year.
        StripeTestEnvironment::query("UPDATE grupper SET fiscal_year=4 WHERE art='VG' AND kodenr=1");
        $ra=pg_fetch_assoc(StripeTestEnvironment::query("SELECT beskrivelse FROM grupper WHERE art='RA' AND kodenr=5"));
        $year=trim((string)($ra['beskrivelse'] ?? '')) ?: '5';
        $refusal="rykkergebyret kan ikke bogføres, da regnskabsår $year mangler varegruppe 1. Opret varegruppe 1 i regnskabsår $year under Indstillinger, og bogfør rykkeren igen.";

        $GLOBALS['stripe_charge_today']='2026-02-01';
        $this->automation=$this->service('2026-02-01');
        $run=$this->runAutomation();
        $this->assertCount(1,$run['errors']);
        $this->assertStringEndsWith(': '.$refusal,$run['errors'][0],'the automatic run');
        $this->assertSame(0,$run['created']);
        $this->assertSame('2',StripeFixtures::row('ordrer',$first)['status']);
        $this->assertSame([], $this->journalOf($first));
        $this->assertSame(0,$this->feeItemsOf($first));

        // "Ryk alle" (includes/openpost.php in the caller's transaction, the writer the run uses) refuses it with the same reason and
        // writes nothing. The Bogfør button's copies give it as "Rykker <nr>: <reason>" (StripeBookingTest, which owns its transactions).
        require_once __DIR__ . '/../../includes/openpost.php';
        try {
            bogfor_rykker($first,false);
            $this->fail('"Ryk alle" books the fee with another year\'s group.');
        } catch (RuntimeException $e) {
            $this->assertSame($refusal,$e->getMessage(),'"Ryk alle"');
        }
        $this->assertSame('2',StripeFixtures::row('ordrer',$first)['status']);
        $this->assertSame([], $this->journalOf($first));
        $this->assertSame(0,$this->feeItemsOf($first));

        // Set up for the year: the next run books it with that year's accounts and escalates it.
        StripeTestEnvironment::query("UPDATE grupper SET fiscal_year=5 WHERE art='VG' AND kodenr=1");
        $run=$this->runAutomation();
        $this->assertSame([], $run['errors']);
        $this->assertSame(1,$run['created']);
        $this->assertSame('4',StripeFixtures::row('ordrer',$first)['status']);
        $this->assertSame([['56000','100.000','0.000'],['1000','0.000','100.000']], $this->journalOf($first));
    }

    /** @return list<array{0: string, 1: string, 2: string}> The reminder's postings: account, debit, credit. */
    private function journalOf(int $id): array
    {
        return array_map(static fn(array $r): array => [(string)$r['kontonr'], (string)$r['debet'], (string)$r['kredit']],
            pg_fetch_all(StripeTestEnvironment::query('SELECT kontonr,debet,kredit FROM transaktioner WHERE ordre_id=$1 ORDER BY debet DESC',[$id])) ?: []);
    }

    /** @return int The reminder's fee open items. */
    private function feeItemsOf(int $id): int
    {
        return (int)pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM openpost WHERE refnr=$1',[$id]),0,0);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testThePreviousReminderLeftAtStatus3WithoutPostingIsBookedAndEscalated(): void
    {
        $this->postingFixtures();
        $this->automation->configure('automatic','test');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $first=(int)$this->automation->jobs()[0]['order_id'];
        // Bogfør refused for another line left it at status 3 with the fee line's account and nothing posted (earlier code).
        StripeTestEnvironment::query('UPDATE ordrer SET status=3 WHERE id=$1',[$first]);
        StripeTestEnvironment::query('UPDATE ordrelinjer SET bogf_konto=1000 WHERE ordre_id=$1 AND vare_id>0',[$first]);
        $GLOBALS['stripe_charge_today']='2026-02-01';
        $this->automation=$this->service('2026-02-01');

        $run=$this->runAutomation();

        $this->assertSame([], $run['errors'], 'bookPrevious() books it instead of stopping at "kunne ikke bogføres"');
        $this->assertSame(1,$run['created'],'its 2nd reminder');
        $this->assertSame('4',StripeFixtures::row('ordrer',$first)['status']);
        $this->assertSame(1,$this->feeItemsOf($first));
        $this->assertSame([['56000','100.000','0.000'],['1000','0.000','100.000']],$this->journalOf($first));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testThePreviousReminderAtStatus3WithItsPostingsIsNotPostedAgain(): void
    {
        $this->postingFixtures();
        $this->automation->configure('automatic','test');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $first=(int)$this->automation->jobs()[0]['order_id'];
        // Booked, with its fee open item and postings, but left at status 3.
        $row=StripeFixtures::row('ordrer',$first);
        StripeTestEnvironment::query('UPDATE ordrer SET status=3 WHERE id=$1',[$first]);
        StripeTestEnvironment::query("INSERT INTO openpost(konto_id,konto_nr,faktnr,refnr,amount,beskrivelse,udlignet,transdate,kladde_id,projekt)
            VALUES($1,$2,$3,$4,100,'Gebyr mm. fra tidligere rykker','0',$5,0,'')",[$row['konto_id'],$row['kontonr'],$row['fakturanr'],$first,$row['fakturadate']]);
        foreach ([[56000,100,0],[1000,0,100]] as [$account,$debit,$credit]) {
            StripeTestEnvironment::query("INSERT INTO transaktioner(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,projekt,ordre_id)
                VALUES(0,$1,'Gebyr mm. fra tidligere rykker',$2,$3,$4,$5,0,0,'0',$6)",[$row['fakturadate'],$account,$row['fakturanr'],$debit,$credit,$first]);
        }
        $GLOBALS['stripe_charge_today']='2026-02-01';
        $this->automation=$this->service('2026-02-01');

        $run=$this->runAutomation();

        $this->assertSame(0,$run['created']);
        $this->assertCount(1,$run['errors']);
        $this->assertStringContainsString('kunne ikke bogføres',$run['errors'][0]);
        $this->assertSame('3',StripeFixtures::row('ordrer',$first)['status']);
        $this->assertSame(1,$this->feeItemsOf($first),'no second fee open item');
        $this->assertSame([['56000','100.000','0.000'],['1000','0.000','100.000']],$this->journalOf($first),'no second posting');
    }

    /**
     * Review CL2-1: an R1 without a fee (no fee item for the 1st reminder) that was shown in the reminder page, which writes its
     * sum as '0.000'. The run books it (nothing to post) and makes its 2nd reminder, where the fee check stopped the chain.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAPreviousReminderWithoutAFeeWhoseSumWasWrittenAsZeroIsBookedAndEscalated(): void
    {
        $this->postingFixtures();
        StripeTestEnvironment::query("UPDATE formularer SET xb=0 WHERE beskrivelse='GEBYR' AND formular=6");
        $this->automation->configure('automatic','test');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $first=(int)$this->automation->jobs()[0]['order_id'];
        $this->assertSame(0,$this->feeItemsOfLines($first),'precondition: the 1st reminder has no fee line');
        StripeTestEnvironment::query('UPDATE ordrer SET sum=0 WHERE id=$1',[$first]);   // as debitor/rykker.php writes it when the reminder is shown
        $GLOBALS['stripe_charge_today']='2026-02-01';
        $this->automation=$this->service('2026-02-01');

        $run=$this->runAutomation();

        $this->assertSame([], $run['errors'], 'not "Det forrige rykkergebyr skal bogføres før næste rykker."');
        $this->assertSame(1,$run['created'],'its 2nd reminder');
        $this->assertSame('4',StripeFixtures::row('ordrer',$first)['status'],'booked');
        $this->assertSame(0,$this->feeItemsOf($first),'no fee open item');
        $this->assertSame([],$this->journalOf($first),'nothing posted');
        $second=(int)pg_fetch_result(StripeTestEnvironment::query("SELECT id FROM ordrer WHERE art='R2' AND konto_id=$1",[$this->debtor]),0,0);
        $this->assertSame(1,$this->feeItemsOfLines($second),'the 2nd reminder has its fee');
    }

    /** Text 8231: the refusal for an automatic reminder that may have reached the customer (Review AR2-2). */
    private const UNSENT_CHECKED = "den automatiske rykker er ikke registreret som sendt. Den er sat på pause, eller dens afsendelse fejlede eller er uafklaret. "
        . "Se den under Automatiske rykkere: vælg Registrér som sendt, hvis mailserverens log viser, at den blev sendt, ellers Godkend den.";
    /** Text 8242: the refusal for a draft that waits for approval or for the run. */
    private const UNSENT_DRAFT = 'den automatiske rykker er ikke sendt endnu. Godkend den i rykkerflowet, eller slet den.';
    /** Text 8234: the refusal for a paused draft without an unresolved sending attempt (Review AR3-1). */
    private const UNSENT_PAUSED = 'den automatiske rykker er sat på pause og ikke sendt. Godkend den under Automatiske rykkere, eller slet den.';

    /**
     * The booking of "Ryk alle" (bogfor_rykker() in the caller's transaction, includes/openpost.php) and Ny rykker
     * (reminder_create_batch()) for an automatic reminder that was not sent. The caller loads includes/openpost.php.
     *
     * @return array{0: string, 1: list<string>} The booking's refusal ('booked' when it booked) and Ny rykker's notes.
     */
    private function unsentRefusals(int $id, string $runDate): array
    {
        try {
            bogfor_rykker($id, false);
            $booked='booked';
        } catch (RuntimeException $e) {
            $booked=$e->getMessage();
        }
        return [$booked, reminder_create_batch([], [$id], $runDate, $runDate, 'test')['notes']];
    }

    /**
     * Review AR2-2: an automatic reminder whose sending failed may have reached the customer. "Ryk alle" (the booking in the
     * caller's transaction, includes/openpost.php) and Ny rykker refuse it, as before, but point to Automatiske rykkere and do
     * not offer to delete it, also once it is paused. Approved again after the check, it is a draft waiting for the run (8242).
     * Review AR3-1: paused again after that, its attempt is resolved, so it is a paused draft (8234).
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAnAutomaticReminderWhoseSendingFailedIsRefusedWithoutBeingOfferedToBeDeleted(): void
    {
        require_once __DIR__ . '/../../includes/openpost.php';   // bogfor_rykker() as "Ryk alle" and the run use it
        // send() logs the failed delivery with error_log(); on stderr it would fail a test in its own process.
        $log=tempnam(sys_get_temp_dir(),'auto-reminder-log-');
        ini_set('error_log',$log);
        $this->automation->configure('automatic','test');
        $this->automation->run(function(int $id) {
            $this->automation->send($id,function(){ $this->sent++; throw new RuntimeException('SMTP disconnected'); },'test');
        },'test');
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $nr=StripeFixtures::row('ordrer',$id)['fakturanr'];
        $runDate='2026-02-20';
        $checked=self::UNSENT_CHECKED;
        $draft=self::UNSENT_DRAFT;
        $paused=self::UNSENT_PAUSED;

        foreach (['failed'=>fn()=>null, 'held'=>fn()=>$this->automation->hold($id,'test')] as $state=>$step) {
            $step();
            $this->assertSame($state,$this->automation->job($id)['state']);
            $this->assertSame(["Rykker $nr: $checked", ["Ingen ny rykker for $nr: $checked"]], $this->unsentRefusals($id, $runDate), $state);
            $this->assertSame('2',StripeFixtures::row('ordrer',$id)['status'],"$state: not booked");
        }
        $this->assertSame('t',$this->automation->jobs()[0]['uncertain'],'paused with an unresolved attempt');
        $this->automation->approve($id,'test',true);
        $this->assertSame('queued',$this->automation->job($id)['state']);
        $this->assertSame(["Rykker $nr: $draft", ["Ingen ny rykker for $nr: $draft"]], $this->unsentRefusals($id, $runDate), 'a draft waiting for the run');
        // Review AR3-1: paused again after the check, the attempt is resolved and the job's row offers Godkend, not Registrér som sendt.
        $this->automation->hold($id,'test');
        $this->assertSame('held',$this->automation->job($id)['state']);
        $this->assertSame('f',$this->automation->jobs()[0]['uncertain']);
        $this->assertSame(["Rykker $nr: $paused", ["Ingen ny rykker for $nr: $paused"]], $this->unsentRefusals($id, $runDate), 'paused after the check');
        $this->assertSame('2',StripeFixtures::row('ordrer',$id)['status'],'paused after the check: not booked');
        $this->assertSame(1,$this->sent);
        $this->assertStringContainsString('SMTP disconnected',(string)file_get_contents($log));
        unlink($log);
    }

    /**
     * Review AR3-1: a draft paused before any attempt to send it cannot have reached the customer. "Ryk alle"'s booking and
     * Ny rykker refuse it with a pointer to Automatiske rykkere, where its row offers Godkend, and say it may be deleted (8234),
     * not "Registrér som sendt" (8231). Approved, it is a draft waiting for the run (8242).
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testADraftPausedBeforeAnyAttemptIsRefusedWithTheOfferToApproveOrDeleteIt(): void
    {
        require_once __DIR__ . '/../../includes/openpost.php';   // bogfor_rykker() as "Ryk alle" and the run use it
        $this->automation->configure('approval','test');
        $this->assertSame(1,$this->runAutomation()['created']);
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $nr=StripeFixtures::row('ordrer',$id)['fakturanr'];
        $runDate='2026-02-20';
        $paused=self::UNSENT_PAUSED;
        $draft=self::UNSENT_DRAFT;

        $this->automation->hold($id,'test');
        $this->assertSame('held',$this->automation->job($id)['state']);
        $this->assertSame('f',$this->automation->jobs()[0]['uncertain'],'no sending attempt');
        $this->assertFalse(reminder_sending_tracked($id),'not tracked as sent, so it may be deleted');
        $this->assertSame(["Rykker $nr: $paused", ["Ingen ny rykker for $nr: $paused"]], $this->unsentRefusals($id, $runDate), 'paused before any attempt');
        $this->assertSame('2',StripeFixtures::row('ordrer',$id)['status'],'not booked');
        $this->automation->approve($id,'test');
        $this->assertSame('queued',$this->automation->job($id)['state']);
        $this->assertSame(["Rykker $nr: $draft", ["Ingen ny rykker for $nr: $draft"]], $this->unsentRefusals($id, $runDate), 'approved: a draft waiting for the run');
        $this->assertSame(0,$this->sent);
    }

    /** @return int The reminder's lines with an item (fee or interest). */
    private function feeItemsOfLines(int $id): int
    {
        return (int)pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM ordrelinjer WHERE ordre_id=$1 AND vare_id>0',[$id]),0,0);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAPostingTheDatabaseRejectsInAPageRequestSkipsOnlyThatCandidate(): void
    {
        $this->postingFixtures();
        $second=StripeFixtures::debtor(['gruppe'=>1]);
        StripeReminderFixtures::invoiceOn($second,'2026-01-01',['udskriv_til'=>'email']);
        $this->automation->configure('automatic','test');
        $this->assertSame(2,$this->runAutomation()['sent']);
        $first=(int)pg_fetch_result(StripeTestEnvironment::query("SELECT id FROM ordrer WHERE art='R1' AND konto_id=$1",[$this->debtor]),0,0);
        $other=(int)pg_fetch_result(StripeTestEnvironment::query("SELECT id FROM ordrer WHERE art='R1' AND konto_id=$1",[$second]),0,0);
        // The database rejects the first reminder's postings (a trigger stands in for any statement it refuses).
        StripeTestEnvironment::query("CREATE FUNCTION pg_temp.reject_reminder_posting() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'posting rejected'; END $$");
        StripeTestEnvironment::query("CREATE TRIGGER reject_reminder_posting BEFORE INSERT ON pg_temp.transaktioner FOR EACH ROW
            WHEN (NEW.ordre_id = $first) EXECUTE FUNCTION pg_temp.reject_reminder_posting()");
        $GLOBALS['stripe_charge_today']='2026-02-01';
        $this->automation=$this->service('2026-02-01');
        // The rejected statement is still mailed to the error address; in a test container mail() (msmtp) reports its refused connection on
        // file descriptor 2, which fails a test in its own process. Descriptor 2 goes to a temporary file for the rest of this process.
        fclose(STDERR);
        $mailErrors=tmpfile();
        $GLOBALS['webservice']=false;   // a page request (the run button): db_modify() shows the error and ends the request outside db_checked()
        try {
            $run=$this->runAutomation();
        } finally {
            $GLOBALS['webservice']=true;
        }

        $this->assertCount(1,$run['errors']);
        $this->assertMatchesRegularExpression('/Databasefejl \(fejlreference F-\d{8}-[A-Z0-9]{6}\)/',$run['errors'][0]);
        $this->assertSame(1,$run['created'],'the next candidate is escalated');
        $this->assertSame('2',StripeFixtures::row('ordrer',$first)['status'],'the rejected booking is rolled back');
        $this->assertSame(0,$this->feeItemsOf($first));
        $this->assertSame([],$this->journalOf($first));
        $this->assertSame('0',pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM ordrer WHERE art='R2' AND konto_id=$1",[$this->debtor]),0,0),'no 2nd reminder for it');
        $this->assertSame('4',StripeFixtures::row('ordrer',$other)['status']);
        $this->assertSame([['56000','100.000','0.000'],['1000','0.000','100.000']],$this->journalOf($other));
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

    public function testSendHoldsTheCustomersDispatchLockUntilTheJobIsSent(): void
    {
        $parameters=[];
        foreach (['host'=>'SALDI_TEST_PGHOST','user'=>'SALDI_TEST_PGUSER','password'=>'SALDI_TEST_PGPASSWORD','dbname'=>'SALDI_TEST_DB'] as $key=>$env) {
            $parameters[]=$key . "='" . str_replace(["\\","'"],["\\\\","\\'"],(string)getenv($env)) . "'";
        }
        $second=pg_connect(implode(' ',$parameters), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($second);
        $try="SELECT pg_try_advisory_lock(hashtext('saldi:reminder_dispatch'),{$this->debtor})";
        // Whether this session holds the customer's lock when the job is saved as sent.
        StripeTestEnvironment::query('CREATE TEMP TABLE h1_job_seen (order_id integer, held bigint)');
        StripeTestEnvironment::query("CREATE FUNCTION public.h1_job_seen() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                INSERT INTO h1_job_seen SELECT NEW.order_id, count(*) FROM pg_locks l WHERE l.locktype = 'advisory' AND l.pid = pg_backend_pid()
                    AND l.granted AND l.objsubid = 2 AND l.objid::text::bigint = {$this->debtor}
                    AND l.classid::text::bigint = (hashtext('saldi:reminder_dispatch')::bigint & 4294967295);
                RETURN NEW;
            END \$\$");
        StripeTestEnvironment::query("CREATE TRIGGER h1_job_seen AFTER UPDATE ON reminder_automation_jobs FOR EACH ROW WHEN (NEW.state = 'sent')
            EXECUTE FUNCTION public.h1_job_seen()");
        $this->automation->configure('automatic','test');
        $during=[];
        try {
            $run=$this->automation->run(function(int $id) use ($second, $try, &$during) {
                $this->automation->send($id,function() use ($second, $try, &$during) {
                    $during[]=pg_fetch_result(pg_query($second,$try),0,0);
                    return $this->transport();
                },'test');
            },'test');
            $this->assertSame([], $run['errors']);
            $this->assertSame(1,$run['sent']);
            $this->assertSame(['f'],$during,'another session cannot take the lock while the reminder goes out');
            $id=(int)$this->automation->jobs()[0]['order_id'];
            $this->assertSame([['order_id'=>(string)$id,'held'=>'1']],pg_fetch_all(StripeTestEnvironment::query('SELECT order_id, held FROM h1_job_seen')),
                'it is held when the job is saved as sent');
            $this->assertSame('t',pg_fetch_result(pg_query($second,$try),0,0),'and let go afterwards');
            pg_query($second,"SELECT pg_advisory_unlock(hashtext('saldi:reminder_dispatch'),{$this->debtor})");
        } finally {
            pg_close($second);
        }
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

    public function testManualSendWhileTheFlowIsOffOrCopiedIsRefusedWithAMessage(): void
    {
        require_once __DIR__ . '/../../includes/formFuncIncludes/reminderMailDelivery.php';
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $pdf=tempnam(sys_get_temp_dir(),'auto-reminder-');
        try {
            file_put_contents($pdf,'%PDF-1.4 fixture');
            $cases = [
                'switched off' => fn() => $this->automation->configure('off','test'),
                'copied ledger' => fn() => StripeTestEnvironment::query("UPDATE reminder_automation_config SET mode='approval',binding='copied'"),
            ];
            foreach ($cases as $case => $switch) {
                $switch();
                $result=formSendReminderEntry($id,$pdf,'customer@stripe-tests.invalid','Dansk',6, function(){ $this->sent++; return 'OK'; });
                $this->assertFalse($result['sent'], $case);
                $this->assertStringContainsString('eller slet kladden', $result['message'], $case);
                $this->assertSame(0,$this->sent, $case);
                $this->assertSame('approval',$this->automation->job($id)['state'], $case);
            }
        } finally {
            unlink($pdf);
        }
    }

    /** Review R2: while the flow is off, the refusal follows the job's state, and only a draft is offered to be deleted. */
    public function testManualSendWhileTheFlowIsOffSaysWhatHappenedToASentOrHeldReminder(): void
    {
        require_once __DIR__ . '/../../includes/formFuncIncludes/reminderMailDelivery.php';
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->automation->configure('off','test');
        $pdf=tempnam(sys_get_temp_dir(),'auto-reminder-');
        try {
            file_put_contents($pdf,'%PDF-1.4 fixture');
            StripeTestEnvironment::query("UPDATE reminder_automation_jobs SET state='sent',sent_at=current_timestamp,sent_date='2026-01-20' WHERE order_id=$1", [$id]);
            $result=formSendReminderEntry($id,$pdf,'customer@stripe-tests.invalid','Dansk',6, function(){ $this->sent++; return 'OK'; });
            $this->assertFalse($result['sent']);
            $this->assertStringContainsString('er sendt af de automatiske rykkere den 20-01-2026 og kan ikke sendes igen herfra', $result['message']);
            $this->assertStringNotContainsString('slet', $result['message'], 'a sent reminder is not offered to be deleted');
            $this->assertSame('sent',$this->automation->job($id)['state']);
            foreach (['held','failed','sending'] as $state) {
                StripeTestEnvironment::query('UPDATE reminder_automation_jobs SET state=$2,sent_at=NULL,sent_date=NULL WHERE order_id=$1', [$id, $state]);
                $result=formSendReminderEntry($id,$pdf,'customer@stripe-tests.invalid','Dansk',6, function(){ $this->sent++; return 'OK'; });
                $this->assertFalse($result['sent'], $state);
                $this->assertStringContainsString('se den under Automatiske rykkere', $result['message'], $state);
                $this->assertStringNotContainsString('slet', $result['message'], $state);
                $this->assertSame($state,$this->automation->job($id)['state'], $state);
            }
            $this->assertSame(0,$this->sent);
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

    /** Review GPT-R-5: pausing a failed attempt does not drop the check of the mail server's log before it is approved again. */
    public function testPausingAnUncertainAttemptStillNeedsTheMailServerCheck(): void
    {
        $this->automation->configure('automatic','test');
        $this->automation->run(function(int $id) {
            $this->automation->send($id,function(){ $this->sent++; throw new RuntimeException('SMTP disconnected'); },'test');
        },'test');
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->assertSame('failed',$this->automation->job($id)['state']);
        $this->automation->hold($id,'test');
        $this->assertSame('held',$this->automation->job($id)['state']);
        try {
            $this->automation->approve($id,'test');
            $this->fail('A paused uncertain attempt was approved without the check.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('uafklaret',$e->getMessage());
        }
        $this->assertSame('held',$this->automation->job($id)['state']);
        $this->assertSame('t',$this->automation->jobs()[0]['uncertain'],'the page asks for the check on the paused job');
        $this->assertSame(0,$this->runAutomation()['sent'],'nothing is sent while it is paused');
        $this->assertSame(1,$this->sent);

        $this->automation->approve($id,'test',true);
        $this->assertSame('queued',$this->automation->job($id)['state']);
        $this->assertSame('f',$this->automation->jobs()[0]['uncertain'],'checked');
        // Paused again after the check, before the next run: no new attempt, no new check.
        $this->automation->hold($id,'test');
        $this->automation->approve($id,'test');
        $this->assertSame(1,$this->runAutomation()['sent']);
        $this->assertSame(2,$this->sent);
        $this->assertSame('f',$this->automation->jobs()[0]['uncertain']);
    }

    /**
     * Review GPT-R-6: the claim committed, the mail server took the reminder, and the process stopped before 'sent' was saved.
     * The operator confirms the delivery in the mail server's log: the job is sent on that day, nothing is mailed again, and the
     * next level follows it.
     */
    public function testADeliveryConfirmedInTheMailServerLogIsRecordedAsSentWithoutSendingAgain(): void
    {
        require_once __DIR__ . '/../../includes/reminderAutomationActions.php';
        require_once __DIR__ . '/../../includes/opdat_func/reminderDispatch.php';
        reminder_dispatch_schema_ensure('postgresql');
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->automation->approve($id,'test');
        try {
            $this->automation->confirmSent($id,'test','2026-01-20',true);
            $this->fail('A draft that was never attempted was recorded as sent.');
        } catch (RuntimeException $e) {
            $this->assertSame('Kun en rykker, hvis afsendelse er uafklaret, kan registreres som sendt.',$e->getMessage());
        }
        // The claim as send() commits it, and no outcome.
        StripeTestEnvironment::query("UPDATE reminder_automation_jobs SET state='sending',attempted_at='2026-01-20 10:00:00+01' WHERE order_id=$1",[$id]);
        StripeTestEnvironment::query("INSERT INTO reminder_automation_events(order_id,action,actor) VALUES($1,'sending','test')",[$id]);
        $this->assertSame('t',$this->automation->jobs()[0]['uncertain']);
        $this->assertSame('2026-01-20',$this->automation->jobs()[0]['attempted_day']);
        $this->assertSame([],array_filter($this->automation->preview(),static fn(array $c): bool => $c['previous']===$id),'not escalated while uncertain');

        $csrf=bin2hex(random_bytes(16));
        $post=['action'=>'confirm_sent','order_id'=>(string)$id,'sent_date'=>'2026-01-20','checked_delivered'=>'yes','csrf_token'=>$csrf,'ledger'=>$GLOBALS['db']];
        foreach ([[['checked_delivered'=>''],'Bekræft, at mailserverens log viser, at rykkeren blev sendt, og angiv dagen.'],
            [['sent_date'=>''],'Bekræft, at mailserverens log viser, at rykkeren blev sendt, og angiv dagen.'],
            [['sent_date'=>'2026-01-19'],'Dagen, rykkeren blev sendt, skal ligge fra 20.01.2026 til 20.01.2026.'],
            [['sent_date'=>'2026-01-21'],'Dagen, rykkeren blev sendt, skal ligge fra 20.01.2026 til 20.01.2026.'],
            [['sent_date'=>'20-01-2026'],'Dagen, rykkeren blev sendt, skal ligge fra 20.01.2026 til 20.01.2026.']] as [$change,$message]) {
            try {
                reminder_automation_action($this->automation,array_replace($post,$change),$csrf,$GLOBALS['db'],'test',true,false,static function(){});
                $this->fail('Recorded as sent: ' . json_encode($change));
            } catch (RuntimeException $e) {
                $this->assertSame($message,$e->getMessage());
            }
            $this->assertSame('sending',$this->automation->job($id)['state']);
        }
        $this->assertSame('Rykkeren er registreret som sendt den 20.01.2026.',
            reminder_automation_action($this->automation,$post,$csrf,$GLOBALS['db'],'test',true,false,static function(){}));
        $job=$this->automation->job($id);
        $this->assertSame(['sent','2026-01-20','classic'],[$job['state'],$job['sent_date'],$job['channel']]);
        $this->assertSame(0,$this->sent,'nothing is mailed again');
        $this->assertSame('2026-01-20',reminder_sent_date($id));
        $this->assertSame('2026-01-20',reminder_dispatch_date($id));
        $this->assertSame('2026-01-20 classic',pg_fetch_result(StripeTestEnvironment::query(
            "SELECT detail FROM reminder_automation_events WHERE order_id=$1 AND action='confirm_sent'",[$id]),0,0));
        $this->assertSame(0,$this->runAutomation()['sent'],'and not by the next run either');
        try {
            $this->automation->confirmSent($id,'test','2026-01-20',true);
            $this->fail('A sent reminder was recorded again.');
        } catch (RuntimeException $e) {
            $this->assertSame('Kun en rykker, hvis afsendelse er uafklaret, kan registreres som sendt.',$e->getMessage());
        }
        // The next level follows the day it went out (10 days).
        $this->automation=$this->service('2026-01-31');
        $next=array_values(array_filter($this->automation->preview(),static fn(array $c): bool => $c['previous']===$id));
        $this->assertCount(1,$next);
        $this->assertSame([2,''],[$next[0]['level'],$next[0]['reason']]);
    }

    /** Review GPT-R-6: a claim younger than 15 minutes may still be sending; a link reminder is marked sent on the confirmed day. */
    public function testAConfirmedLinkReminderIsMarkedSentOnThatDay(): void
    {
        require_once __DIR__ . '/../../includes/formFuncIncludes/reminderMailDelivery.php';
        StripeTestEnvironment::setting('stripe','reminder_format','auto');
        StripeTestEnvironment::query("UPDATE ordrer SET udskriv_til='Link' WHERE id=$1",[$this->invoice]);
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->assertSame('draft',stripe_reminder_row($id)['status']);
        StripeTestEnvironment::query("UPDATE reminder_automation_jobs SET state='sending',attempted_at=current_timestamp WHERE order_id=$1",[$id]);
        StripeTestEnvironment::query("INSERT INTO reminder_automation_events(order_id,action,actor) VALUES($1,'sending','test')",[$id]);
        $this->automation=$this->service(substr((string)pg_fetch_result(StripeTestEnvironment::query("SELECT cast(current_timestamp AT TIME ZONE 'Europe/Copenhagen' AS date)"),0,0),0,10));
        try {
            $this->automation->confirmSent($id,'test',$this->automation->jobs()[0]['attempted_day'],true);
            $this->fail('A claim of a minute ago may still be sending.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Vent mindst 15 minutter',$e->getMessage());
        }
        StripeTestEnvironment::query("UPDATE reminder_automation_jobs SET state='failed',attempted_at='2026-01-20 10:00:00+01' WHERE order_id=$1",[$id]);
        $this->automation=$this->service('2026-01-22');
        $this->assertSame('link',$this->automation->confirmSent($id,'test','2026-01-21',true));
        $row=stripe_reminder_row($id);
        $this->assertSame(['sent','link','2026-01-21 00:00:00'],[$row['status'],$row['channel'],$row['mailed_at']]);
        $this->assertSame(['sent','2026-01-21','link'],[$this->automation->job($id)['state'],$this->automation->job($id)['sent_date'],$this->automation->job($id)['channel']]);
        $this->assertSame([],StripeTestTransport::$mails,'nothing is mailed');
    }

    /** Review GPT-R-7: a draft approved later than it was made goes out with a deadline from the day it is sent, in the mail and in the PDF. */
    public function testADraftApprovedLateGoesOutWithADeadlineFromTheDayItIsSent(): void
    {
        require_once __DIR__ . '/../../includes/formFuncIncludes/reminderMailDelivery.php';
        StripeTestEnvironment::setting('stripe','reminder_format','auto');
        StripeTestEnvironment::query("UPDATE ordrer SET udskriv_til='Link' WHERE id=$1",[$this->invoice]);
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->assertNotNull(stripe_reminder_row($id));
        $this->assertSame('2026-01-30',stripe_reminder_deadline(StripeFixtures::row('ordrer',$id)),'drafted on 20.01.2026 with 10 payment days');

        // Approved and sent on 10 February.
        $GLOBALS['stripe_charge_today']='2026-02-10';
        $this->automation=$this->service('2026-02-10');
        $this->automation->approve($id,'test');
        $this->automation->dateForDispatch($id,'test');
        $order=StripeFixtures::row('ordrer',$id);
        $this->assertSame(['2026-02-10','2026-02-10','2026-02-10'],[$order['ordredate'],$order['levdate'],$order['fakturadate']]);
        $this->assertSame('Betal senest 20.02.2026.',formReminderDeadlineText($id,'Betal straks.'),'the PDF');
        $pdf=tempnam(sys_get_temp_dir(),'auto-reminder-');
        try {
            file_put_contents($pdf,'%PDF-1.4 fixture');
            $this->assertTrue(formSendReminderEntry($id,$pdf,'customer@stripe-tests.invalid','Dansk',6)['sent']);
        } finally {
            unlink($pdf);
        }
        $this->assertCount(1,StripeTestTransport::$mails);
        $this->assertStringContainsString('20.02.2026',StripeTestTransport::$mails[0]['text'],'the mail');
        $this->assertStringNotContainsString('30.01.2026',StripeTestTransport::$mails[0]['text']);
        $this->assertSame('2026-02-10 2026-02-10',pg_fetch_result(StripeTestEnvironment::query(
            "SELECT o.fakturadate || ' ' || j.sent_date FROM ordrer o JOIN reminder_automation_jobs j ON j.order_id=o.id WHERE o.id=$1",[$id]),0,0));
        $this->assertSame('2026-01-20 2026-02-10',pg_fetch_result(StripeTestEnvironment::query(
            "SELECT detail FROM reminder_automation_events WHERE order_id=$1 AND action='dated'",[$id]),0,0));

        // Sent: printed or resent later, its date stays.
        $this->automation=$this->service('2026-02-15');
        $this->automation->dateForDispatch($id,'test');
        $this->assertSame('2026-02-10',StripeFixtures::row('ordrer',$id)['fakturadate']);
    }

    public function testOnlyATrackedDraftOfAFlowThatIsOnIsDatedForSending(): void
    {
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $manual=StripeReminderFixtures::reminder($this->debtor,[$this->invoice],['date'=>'2026-01-05']);
        $booked=StripeReminderFixtures::reminder($this->debtor,[$this->invoice],['date'=>'2026-01-05','status'=>4]);
        StripeTestEnvironment::query("INSERT INTO reminder_automation_jobs(order_id,mode,state,actor) VALUES($1,'approval','approval','test')",[$booked]);
        $this->automation=$this->service('2026-02-10');
        $this->automation->dateForDispatch($manual,'test');
        $this->automation->dateForDispatch($booked,'test');
        $this->assertSame('2026-01-05',StripeFixtures::row('ordrer',$manual)['fakturadate'],'not tracked');
        $this->assertSame('2026-01-05',StripeFixtures::row('ordrer',$booked)['fakturadate'],'booked');
        $this->automation->hold($id,'test');
        $this->automation->dateForDispatch($id,'test');
        $this->assertSame('2026-01-20',StripeFixtures::row('ordrer',$id)['fakturadate'],'paused: send() does not claim it');
        $this->automation->approve($id,'test');
        $this->automation->configure('off','test');
        $this->automation->dateForDispatch($id,'test');
        $this->assertSame('2026-01-20',StripeFixtures::row('ordrer',$id)['fakturadate'],'the flow is off');
    }

    /** Review GPT-R-9: the automatic reminders go to the customer's reminder contacts too, as a reminder sent from the editor does. */
    public function testAutomaticRemindersGoToTheCustomersReminderContactsToo(): void
    {
        StripeTestEnvironment::query("UPDATE adresser SET email='general@stripe-tests.invalid' WHERE id=$1",[$this->debtor]);
        StripeFixtures::insert('kontakt_emails',['konto_id'=>$this->debtor,'email'=>'accounts@stripe-tests.invalid','email_type'=>'rykker']);
        StripeFixtures::insert('kontakt_emails',['konto_id'=>$this->debtor,'email'=>'GENERAL@stripe-tests.invalid ','email_type'=>'faktura']);
        // Without its own address, but with a reminder contact: reminded there, not held for a missing address.
        $other=StripeFixtures::debtor(['gruppe'=>1,'email'=>'']);
        StripeReminderFixtures::invoiceOn($other,'2026-01-01',['udskriv_til'=>'email']);
        StripeFixtures::insert('kontakt_emails',['konto_id'=>$other,'email'=>'ap@stripe-tests.invalid','email_type'=>'rykker']);
        $reasons=array_column($this->automation->preview(),'reason','account');
        $this->assertSame(['',''],[$reasons[$this->debtor],$reasons[$other]]);

        $this->automation->configure('automatic','test');
        $to=[];
        $run=$this->automation->run(function (int $id) use (&$to) {
            $to[(int)StripeFixtures::row('ordrer',$id)['konto_id']]=StripeFixtures::row('ordrer',$id)['email'];
            $this->automation->send($id,fn()=> $this->transport(),'test');
        },'test');
        $this->assertSame([],$run['errors']);
        $this->assertSame(2,$run['sent']);
        $this->assertSame('general@stripe-tests.invalid;accounts@stripe-tests.invalid',$to[$this->debtor],'the address and the reminder contact, once each');
        $this->assertSame('ap@stripe-tests.invalid',$to[$other]);
        // The editor's merge is the same helper: nothing is added twice.
        $this->assertSame('general@stripe-tests.invalid;accounts@stripe-tests.invalid',getReminderEmails($this->debtor,$to[$this->debtor]));
        $this->assertSame('x@stripe-tests.invalid',getReminderEmails($this->debtor + 100000000,'x@stripe-tests.invalid'),'no contacts: as it is');
    }

    public function testDraftPausedBeforeAnyAttemptIsApprovedWithoutTheCheck(): void
    {
        $this->automation->configure('approval','test');
        $this->runAutomation();
        $id=(int)$this->automation->jobs()[0]['order_id'];
        $this->automation->hold($id,'test');
        $this->assertSame('f',$this->automation->jobs()[0]['uncertain']);
        $this->automation->approve($id,'test');
        $this->assertSame('queued',$this->automation->job($id)['state']);
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

    public function testRunTakesNoOrderTableLockAndReplansOnlyTheCandidatesAccount(): void
    {
        $this->assertCount(1, $this->automation->preview($this->debtor));
        $this->assertSame([], $this->automation->preview($this->debtor + 100000000), 'another account has no candidate');
        $this->automation->configure('approval','test');
        $this->assertSame(1, $this->runAutomation()['created']);
        $tableLocks = StripeTestEnvironment::query("SELECT count(*) FROM pg_locks WHERE pid=pg_backend_pid() AND locktype='relation'
            AND relation='ordrer'::regclass AND mode='ShareRowExclusiveLock'");
        $this->assertSame('0', pg_fetch_result($tableLocks, 0, 0), 'order saves, invoicing and POS sales are not held back');
    }
}

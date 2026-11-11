<?php
// 20261111 CDX/ASR Cover standalone CRM rights, assignment alerts and isolation of personal read state.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AccessAndNotifications extends TestCase
{
    private ?CrmLeadFixture $fixture = null;

    protected function setUp(): void
    {
        $dsn = (string) getenv('SALDI_CHAR_PG_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN to the disposable PostgreSQL fixture database.');
        }
        self::assertTrue(extension_loaded('pgsql'));
        require_once __DIR__ . '/../support/CrmLeadFixture.php';
        $this->fixture = new CrmLeadFixture($dsn);
    }

    protected function tearDown(): void
    {
        if ($this->fixture !== null) {
            $this->fixture->close();
        }
    }

    /** @return array<string, array{string, int, bool, bool, bool, bool}> */
    public static function rights(): array
    {
        return [
            'write both modules' => ['0000011000000000', 101, false, true, true, true],
            'read CRM and orders' => ['0000022000000000', 101, false, true, false, false],
            'write CRM read orders' => ['0000021000000000', 101, false, true, true, false],
            'read CRM write orders' => ['0000012000000000', 101, false, true, false, true],
            'no CRM right' => ['0000010000000000', 101, false, false, false, true],
            'empty rights' => ['', 101, false, false, false, false],
            'truncated rights' => ['11111', 101, false, false, false, false],
            'unknown rights' => ['9999999999999999', 101, false, false, false, false],
            'auditor' => ['1111111111111111', -1, true, true, false, false],
            'auditor flag on real user' => ['1111111111111111', 101, true, true, false, false],
            'no authenticated user' => ['1111111111111111', 0, false, false, false, false],
        ];
    }

    #[DataProvider('rights')]
    public function testMasterRightsAreEnforcedWithoutTheRoleModule(string $rights, int $user, bool $auditor, bool $read, bool $write, bool $order): void
    {
        self::assertFalse(function_exists('perm_can'), 'This suite must exercise master without the separate permission module.');
        $GLOBALS['rettigheder'] = $rights;
        $GLOBALS['bruger_id'] = $user;
        $GLOBALS['revisor'] = $auditor;
        self::assertSame($read, crm_can_read());
        self::assertSame($write, crm_can_write());
        self::assertSame($order, crm_can_order());
    }

    public function testReadOnlyUserCanReadTheCustomerButCannotMutateActivities(): void
    {
        $GLOBALS['rettigheder'][6] = '2';
        self::assertArrayNotHasKey('error', crm_action('customer', ['id' => 2]));
        self::assertSame(['error' => 'noaccess'], $this->fixture->action(static fn(): array => crm_action('add_entry', [
            'cid' => 2, 'type' => 'note', 'note' => 'Must not be saved',
        ])));
        self::assertSame([], $this->fixture->rows('SELECT * FROM historik'));
        $GLOBALS['rettigheder'] = '';
        self::assertSame(['error' => 'noaccess'], crm_action('customer', ['id' => 2]));
        self::assertSame([], crm_dataset());
    }

    public function testOnlyReadAndPersonalStateActionsUseReadPermission(): void
    {
        foreach (['search', 'place', 'customer', 'data', 'busy', 'ical', 'notifications_seen'] as $action) {
            self::assertTrue(crm_action_reads($action), $action);
        }
        foreach (['save_lead', 'prepare_document', 'add_entry', 'save_meeting', 'send_invites', 'link_doc', 'delete_deal', 'unknown'] as $action) {
            self::assertFalse(crm_action_reads($action), $action);
        }
    }

    public function testAssignmentOnlyNotifiesAnotherUserWhoCanReadCrm(): void
    {
        crm_notify(11, 'crm:own', 'Own assignment', '', '/debitor/crm.php?tab=follow', null);
        crm_notify(12, 'crm:other', "Øster's meeting", 'København', '/debitor/crm.php?tab=cal&drawer=2', null);
        self::assertSame([['bruger_id' => '102', 'title' => "Øster's meeting", 'body' => 'København', 'url' => 'crm.php?tab=cal&drawer=2']],
            $this->fixture->rows('SELECT bruger_id, title, body, url FROM crm_notifications'));

        $this->fixture->query("UPDATE brugere SET rettigheder = '' WHERE id = 102");
        crm_notify(12, 'crm:denied', 'No access', '', '/debitor/crm.php', null);
        crm_notify(999, 'crm:missing', 'No user', '', '/debitor/crm.php', null);
        self::assertCount(1, $this->fixture->rows('SELECT * FROM crm_notifications'));
    }

    public function testNotificationReadStateIsIsolatedEvenWhenAnotherUsersIdIsSubmitted(): void
    {
        crm_notification_upsert('crm:a', 101, 'First', '', 'crm.php', null);
        crm_notification_upsert('crm:a', 102, 'Second', '', 'crm.php', null);
        $ids = array_column($this->fixture->rows('SELECT id, bruger_id FROM crm_notifications'), 'id', 'bruger_id');
        $GLOBALS['rettigheder'][6] = '2';
        crm_notifications_seen([(int) $ids[102]]);
        self::assertSame([['bruger_id' => '101', 'seen' => 'f'], ['bruger_id' => '102', 'seen' => 'f']],
            $this->fixture->rows('SELECT bruger_id, seen FROM crm_notifications ORDER BY bruger_id'));
        crm_notifications_seen();
        self::assertSame([['bruger_id' => '101', 'seen' => 't'], ['bruger_id' => '102', 'seen' => 'f']],
            $this->fixture->rows('SELECT bruger_id, seen FROM crm_notifications ORDER BY bruger_id'));
        $own = crm_notifications_dataset();
        self::assertSame(['First'], array_column($own['notifications'], 'title'));
        self::assertSame(0, $own['notificationUnread']);
        self::assertTrue($own['notifications'][0]['seen']);

        $GLOBALS['bruger_id'] = 102;
        self::assertSame(['Second'], array_column(crm_notifications_dataset()['notifications'], 'title'));
        self::assertSame(1, crm_notifications_dataset()['notificationUnread']);
        $GLOBALS['rettigheder'] = '';
        crm_notifications_seen();
        self::assertSame([['seen' => 'f']], $this->fixture->rows('SELECT seen FROM crm_notifications WHERE bruger_id = 102'));
        self::assertSame(['notifications' => [], 'notificationUnread' => 0], crm_notifications_dataset());
    }

    public function testRefreshRetainsReadStateButMovedFollowupReopensIt(): void
    {
        crm_notify_followup(7, 2, 12, date('Y-m-d'), 'Initial call');
        $GLOBALS['bruger_id'] = 102;
        crm_notifications_seen();
        self::assertSame(0, crm_notifications_dataset()['notificationUnread']);
        $GLOBALS['bruger_id'] = 101;
        crm_notify_followup(7, 2, 12, date('Y-m-d'), 'Initial call');
        self::assertSame([['seen' => 't']], $this->fixture->rows('SELECT seen FROM crm_notifications'));
        crm_notify_followup(7, 2, 12, date('Y-m-d', strtotime('+1 day')), 'Moved call', true);
        self::assertSame([['seen' => 'f']], $this->fixture->rows('SELECT seen FROM crm_notifications'));
        self::assertCount(1, $this->fixture->rows('SELECT * FROM crm_notifications'));
        crm_notif_drop('crm:fu:7');
        self::assertSame([], $this->fixture->rows('SELECT * FROM crm_notifications'));
    }

    public function testDatasetRefreshesPersonalDailySummaryWithoutReopeningReadNotice(): void
    {
        $this->fixture->query("INSERT INTO historik (konto_id, ansat_id, kontaktes, completed, type) VALUES (2, 12, CURRENT_DATE, FALSE, 'opkald')");
        $GLOBALS['bruger_id'] = 102;
        $GLOBALS['ansat_id'] = 12;
        $GLOBALS['rettigheder'][6] = '2';
        $data = crm_dataset();
        self::assertSame(1, $data['notificationUnread']);
        self::assertSame(['Opfølgninger i dag'], array_column($data['notifications'], 'title'));
        crm_notifications_seen();
        self::assertSame(0, crm_dataset()['notificationUnread']);
        self::assertCount(1, $this->fixture->rows('SELECT * FROM crm_notifications'));
        $this->fixture->query('UPDATE historik SET completed = TRUE');
        self::assertSame([], crm_dataset()['notifications']);
    }

    public function testReassignmentRemovesTheOldOwnersNoticeIncludingWhenTakingItBackYourself(): void
    {
        $row = $this->fixture->rows("INSERT INTO historik (konto_id, ansat_id, kontaktes, completed, type) VALUES (2, 12, CURRENT_DATE, FALSE, 'opkald') RETURNING id")[0];
        $id = (int) $row['id'];
        crm_notify_followup($id, 2, 12, date('Y-m-d'), 'Other owner');
        self::assertCount(1, $this->fixture->rows('SELECT * FROM crm_notifications'));
        self::assertArrayNotHasKey('error', $this->fixture->action(static fn(): array => crm_action('update_entry', [
            'id' => $id, 'owner' => 11, 'note' => 'Taken back', 'type' => 'opkald',
        ])));
        self::assertSame([], $this->fixture->rows('SELECT * FROM crm_notifications'));
        self::assertArrayNotHasKey('error', $this->fixture->action(static fn(): array => crm_action('move_followups', [
            'cid' => 2, 'owner' => 12, 'ids' => [$id],
        ])));
        self::assertSame([['bruger_id' => '102']], $this->fixture->rows('SELECT bruger_id FROM crm_notifications'));
        self::assertArrayNotHasKey('error', $this->fixture->action(static fn(): array => crm_action('move_followups', [
            'cid' => 2, 'owner' => 11, 'ids' => [$id],
        ])));
        self::assertSame([], $this->fixture->rows('SELECT * FROM crm_notifications'));
    }

    public function testExpiredNoticesAreHiddenAndRecentListIsBoundedWithoutLosingUnreadCount(): void
    {
        crm_notification_upsert('crm:expired', 101, 'Expired', '', 'crm.php', date('Y-m-d', strtotime('-1 day')));
        $this->fixture->query("INSERT INTO crm_notifications (event_key, bruger_id, title, created) SELECT 'crm:' || n, 101, 'Active ' || n, CURRENT_DATE FROM generate_series(1, 55) n");
        $data = crm_notifications_dataset();
        self::assertCount(50, $data['notifications']);
        self::assertSame(55, $data['notificationUnread']);
        self::assertFalse($data['notifications'][0]['seen']);
        self::assertNotContains('Expired', array_column($data['notifications'], 'title'));
    }

    public function testCrmJsonAndCalendarEndpointsCannotBecomeBackTargets(): void
    {
        self::assertFalse(_nav_is_recordable('/asr/debitor/crmApi.php'));
        self::assertFalse(_nav_is_recordable('/asr/api/crm/ical.php?r=1&k=private'));
        self::assertTrue(_nav_is_recordable('/asr/debitor/crm.php?tab=cal'));
    }
}

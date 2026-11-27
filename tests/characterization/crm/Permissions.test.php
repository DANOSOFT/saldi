<?php
// 20261110 CDX/ASR Verify independent CRM rights, action denial, cross-module data isolation and calendar revocation.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Permissions extends TestCase
{
    private ?CrmLeadFixture $fixture = null;

    protected function setUp(): void
    {
        $dsn = (string) getenv('SALDI_CHAR_PG_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN to the disposable fixture database.');
        }
        require_once __DIR__ . '/../support/CrmLeadFixture.php';
        $this->fixture = new CrmLeadFixture($dsn);
        $this->fixture->query('CREATE TEMPORARY TABLE roles (id integer PRIMARY KEY)');
        $this->fixture->query('CREATE TEMPORARY TABLE role_permissions (role_id integer, permission_key text, level text)');
        $this->fixture->query('ALTER TABLE brugere ADD COLUMN role_id integer, ADD COLUMN rettigheder text, ADD COLUMN status boolean');
        $this->fixture->query('CREATE TEMPORARY TABLE settings (id serial PRIMARY KEY, var_grp text, var_name text, var_value text, user_id integer)');
        $this->fixture->query('CREATE TEMPORARY TABLE crm_ical (bruger_id integer PRIMARY KEY, token text, created date)');
        $this->fixture->query("INSERT INTO roles VALUES (1)");
        $this->fixture->query("INSERT INTO brugere (id, ansat_id, role_id, rettigheder, status) VALUES (1,11,1,'1111111111111111',TRUE)");
        $GLOBALS['bruger_id'] = 1;
        $GLOBALS['revisor'] = false;
    }

    protected function tearDown(): void
    {
        $this->fixture?->close();
    }

    /** @param array<string, string> $levels */
    private function levels(array $levels): void
    {
        $this->fixture->query('DELETE FROM role_permissions');
        foreach ($levels as $key => $level) {
            $this->fixture->query('INSERT INTO role_permissions VALUES (1,$1,$2)', [$key, $level]);
        }
        perm_current_levels(true);
    }

    /** @return array<string, array{string, string}> */
    public static function deniedLevels(): array
    {
        return ['none/log' => ['none', 'log'], 'read/log' => ['read', 'log'],
            'none/deny' => ['none', 'deny'], 'read/deny' => ['read', 'deny']];
    }

    #[DataProvider('deniedLevels')]
    public function testEveryMutationIsDeniedBeforeBusinessQueriesEvenWithFullLegacyRights(string $level, string $mode): void
    {
        $this->fixture->query("INSERT INTO settings(var_grp,var_name,var_value) VALUES ('permissions','enforce',$1)", [$mode]);
        $this->levels(['crm.access' => $level, 'debitor.konti' => 'write', 'debitor.ordre' => 'write']);
        self::assertSame($mode, perm_enforcement_mode());
        // No input fields: reaching a business branch would warn or query; denial must happen first.
        foreach (['save_lead', 'prepare_document', 'add_entry', 'reopen_many', 'close_old', 'update_entry',
            'set_owner', 'unset_owner', 'move_followups', 'unmove_followups', 'link_doc', 'unlink_doc',
            'starter', 'toggle_done', 'set_due', 'delete_entry', 'restore_entry', 'set_status',
            'save_deal', 'move_deal', 'restore_deal', 'delete_deal', 'save_meeting', 'send_invites',
            'save_groups', 'unknown_future_action'] as $action) {
            self::assertSame(['error' => 'noaccess'], crm_action($action, []), $action);
        }
        self::assertSame('', crm_ical_set('new'));
        self::assertSame('', crm_ical_set('off'));
        self::assertSame([], $this->fixture->rows('SELECT * FROM crm_ical'));
        self::assertSame([], $this->fixture->rows('SELECT * FROM historik'));
        self::assertSame([], $this->fixture->rows('SELECT * FROM crm_deals'));
        if ($level === 'none') {
            self::assertSame([], crm_dataset());
            self::assertSame([], crm_search('Existing'));
            self::assertSame(['error' => 'noaccess'], crm_action('customer', ['id' => 2]));
        } else {
            self::assertSame(2, crm_action('customer', ['id' => 2])['customer']['id']);
        }
    }

    public function testCrmOnlyReaderReceivesContactsAndCasesWithoutDebtorFinancialsOrSalesDocuments(): void
    {
        $this->fixture->query("INSERT INTO historik(konto_id,notat,type,kontaktet) VALUES (2,'CRM note','note',current_date)");
        $this->fixture->query("INSERT INTO ordrer(id,konto_id,art,ordrenr,status,sum,ordredate,fakturadate) VALUES
            (51,2,'DO',501,1,2500,current_date,current_date), (52,2,'DO',502,3,5000,current_date,current_date)");
        $this->fixture->query("INSERT INTO openpost(konto_id,amount,forfaldsdate) VALUES (2,1000,current_date-1)");
        $this->fixture->query("INSERT INTO crm_deals(konto_id,title,amount,state,ordre_id) VALUES (2,'CRM case',75,'open',51)");
        $this->levels(['crm.access' => 'read']);
        $data = crm_dataset(2);
        $customer = array_column($data['customers'], null, 'id')[2];
        self::assertSame('Existing debtor', $customer['n']);
        self::assertSame(0, $customer['rev']);
        self::assertSame(0, $customer['late']);
        self::assertArrayNotHasKey('bal', $customer);
        self::assertSame([], $customer['docs']);
        self::assertSame(75.0, $data['deals'][0]['v']);
        self::assertNull($data['deals'][0]['doc']);
        self::assertFalse($data['canOrderRead']);
        self::assertFalse($data['canBalanceRead']);
        self::assertFalse($data['canRevenueRead']);
        self::assertSame('CRM note', $data['hist'][0]['note']);
        self::assertSame('Existing debtor', crm_search('Existing debtor')[0]['n']);

        $this->levels(['crm.access' => 'read', 'debitor.ordre' => 'read']);
        $customer = crm_action('customer', ['id' => 2])['customer'];
        self::assertSame(5000.0, $customer['rev']);
        self::assertSame(51, $customer['docs'][0]['id']);
        self::assertArrayNotHasKey('bal', $customer);
        $this->levels(['crm.access' => 'read', 'debitor.konti' => 'read']);
        $customer = crm_action('customer', ['id' => 2])['customer'];
        self::assertSame(1000.0, $customer['bal']);
        self::assertSame([], $customer['docs']);
    }

    public function testCrmOnlyWriterCanMaintainLeadAndHistoryButCannotConvertToDebtor(): void
    {
        $this->levels(['crm.access' => 'write']);
        $result = $this->fixture->action(fn() => crm_action('save_lead', ['n' => 'CRM-only lead', 'own' => 11]));
        self::assertArrayNotHasKey('error', $result);
        $cid = $result['id'];
        $note = $this->fixture->action(fn() => crm_action('add_entry', ['cid' => $cid, 'note' => 'Customer called']));
        self::assertGreaterThan(0, $note['id']);
        $removed = $this->fixture->action(fn() => crm_action('delete_entry', ['id' => $note['id']]));
        self::assertSame('Customer called', $removed['row']['note']);
        $restored = $this->fixture->action(fn() => crm_action('restore_entry', $removed));
        self::assertGreaterThan(0, $restored['id']);
        self::assertSame(['error' => 'noaccess'], crm_action('prepare_document', ['cid' => $cid, 'group' => 21, 'kind' => 'quote']));
        $this->levels(['crm.access' => 'write', 'debitor.ordre' => 'write']);
        self::assertSame(['error' => 'noaccess'], $this->fixture->action(fn() => crm_action('prepare_document', ['cid' => $cid, 'group' => 21, 'kind' => 'quote'])));
        self::assertSame('L', $this->fixture->rows('SELECT art FROM adresser WHERE id=$1', [$cid])[0]['art']);
        $this->levels(['crm.access' => 'write']);
        self::assertSame(['error' => 'noaccess'], crm_action('link_doc', []));
    }

    public function testCalendarFeedRecipientLosesAccessImmediatelyWhenRoleIsRemovedOrAccountClosed(): void
    {
        $this->levels(['crm.access' => 'read']);
        $user = ['status' => true, 'rettigheder' => str_repeat('1', 16)];
        self::assertTrue(crm_user_can_read(1, $user));
        $this->levels(['debitor.konti' => 'write']);
        self::assertFalse(crm_user_can_read(1, $user));
        $this->levels(['crm.access' => 'write']);
        foreach ([false, 'f', 0, '0'] as $closed) {
            self::assertFalse(crm_user_can_read(1, array_replace($user, ['status' => $closed])));
        }
        self::assertTrue(crm_user_can_read(1, array_replace($user, ['status' => null])));
        self::assertFalse(crm_user_can_read(0, $user));
    }

    public function testNotificationsStopBeingWrittenAndDisappearFromListsAndDigestsAfterCrmRevocation(): void
    {
        $this->fixture->query('CREATE TEMPORARY TABLE notifications (id serial PRIMARY KEY, user_id integer, type text, title text, body text, link text, expires date, source_key text, created timestamp DEFAULT current_timestamp)');
        $this->fixture->query('CREATE TEMPORARY TABLE notification_read (user_id integer, notification_id integer, read_at timestamp)');
        $this->fixture->query("INSERT INTO roles VALUES (2)");
        $this->fixture->query("INSERT INTO brugere(id,ansat_id,role_id,rettigheder,status) VALUES (2,12,2,'1111111111111111',TRUE)");
        $this->levels(['crm.access' => 'write']);
        $this->fixture->query("INSERT INTO role_permissions VALUES (2,'crm.access','read')");
        crm_notify(12, 'crm:test:allowed', 'CRM activity', 'Private customer note', '/debitor/crm.php', null);
        self::assertCount(1, $this->fixture->rows('SELECT * FROM notifications'));
        $this->fixture->query("DELETE FROM role_permissions WHERE role_id=2");
        crm_notify(12, 'crm:test:refused', 'CRM activity', 'Must not arrive', '/debitor/crm.php', null);
        crm_notif_refresh(2, str_repeat('1', 16), 1);
        self::assertCount(1, $this->fixture->rows('SELECT * FROM notifications'));
        self::assertSame([], notif_list(2));
        self::assertSame([], notif_digest_items(2, ''));
    }

    public function testSalesOrderCaseLinkRequiresCurrentSessionToken(): void
    {
        $_SESSION = [];
        self::assertFalse(crm_order_link_token_valid([]));
        $token = bin2hex(random_bytes(32));
        self::assertFalse(crm_order_link_token_valid(['crm_csrf' => $token]));
        $_SESSION['csrf_token'] = $token;
        self::assertFalse(crm_order_link_token_valid([]));
        self::assertFalse(crm_order_link_token_valid(['crm_csrf' => []]));
        self::assertFalse(crm_order_link_token_valid(['crm_csrf' => bin2hex(random_bytes(32))]));
        self::assertTrue(crm_order_link_token_valid(['crm_csrf' => $token]));
    }

    public function testCalendarTokenRotationAndRevocationRequireWriteAndApiClassificationMatches(): void
    {
        self::assertSame('read', crm_action_permission('ical'));
        self::assertSame('write', crm_action_permission('ical', ['mode' => 'new']));
        self::assertSame('write', crm_action_permission('ical', ['mode' => 'off']));
        foreach (['search', 'place', 'customer', 'data', 'busy'] as $action) {
            self::assertSame('read', crm_action_permission($action));
        }
        self::assertSame('write', crm_action_permission('future_action'));
        $this->fixture->query('CREATE TEMPORARY TABLE regnskab (id integer, db text)');
        $this->fixture->query("INSERT INTO regnskab VALUES (8,'crm_fixture')");
        $this->levels(['crm.access' => 'write']);
        $first = crm_ical_set('new');
        self::assertStringContainsString('r=8&k=1-', $first);
        $token = $this->fixture->rows('SELECT token FROM crm_ical')[0]['token'];
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
        self::assertNotSame($first, crm_ical_set('new'));
        $rotated = $this->fixture->rows('SELECT token FROM crm_ical')[0]['token'];
        $this->levels(['crm.access' => 'read']);
        self::assertSame('', crm_ical_set('off'));
        self::assertSame($rotated, $this->fixture->rows('SELECT token FROM crm_ical')[0]['token']);
        $this->levels(['crm.access' => 'write']);
        self::assertSame('', crm_ical_set('off'));
        self::assertSame([], $this->fixture->rows('SELECT token FROM crm_ical'));
    }
}

<?php
// tests/restapi/ModuleRightsTest.php
//
// The REST API refuses a user without the endpoint's module right, as that
// module's pages do (BaseEndpoint::checkModuleRight()). The testlab's users-and-rights
// session logged in e2etest3 (rights 0000010000000000, Debitorordre only),
// whose menu has no Kontoplan and no Kreditor and whom kreditor/ordre.php
// refuses, and read the chart of accounts with its balances through
// accounts/ and the purchase orders through creditor/orders/ with HTTP 200.
//
// Each case seeds its own user with a given rights string into the suite's
// throwaway tenant and logs in with it. It also covers what review found
// still open: a read-only user's write once enforcement is 'deny', a role
// whose rights string opens a module the role does not give, and an
// endpoint reaching another module's orders through the body's order type,
// an order or line id of the other side, or SQL in an id or a body field.
//
// History:
// 20261105 CL/LH Created.
//                Review: an assigned role decides the level, and SQL in an order's or a line's body fields stays a value.
// 20261114 CL/ASR The roles table made here names the built-in flag is_built_in, as includes/betweenUpdates.php does since the rename of roles.system (master d7cdeb81).

require_once __DIR__ . '/support/RestApiTestCase.php';

final class ModuleRightsTest extends RestApiTestCase
{
    private const ACCOUNTS = '/restapi/endpoints/v1/accounts/';
    private const CREDITOR_ORDERS = '/restapi/endpoints/v1/creditor/orders/';
    private const CREDITOR_ORDER_LINES = '/restapi/endpoints/v1/creditor/orderlines/';
    private const DEBTOR_ORDERS = '/restapi/endpoints/v1/debitor/orders/';
    private const DEBTOR_ORDER_LINES = '/restapi/endpoints/v1/debitor/orderlines/';
    private const INVOICES = '/restapi/endpoints/v1/debitor/invoices/';
    private const EXTERNAL_PAID_INVOICES = '/restapi/endpoints/v1/debitor/external-paid-invoices/';

    /** Debitorordre (position 5) and nothing else, as e2etest3 has it in the testlab. */
    private const DEBTOR_ORDERS_ONLY = '0000010000000000';

    /** Kreditorordre (position 7) and nothing else. */
    private const CREDITOR_ORDERS_ONLY = '0000000100000000';

    /** Kontoplan read-only ('2' at position 0), as a role with Kun visning on Kontoplan renders it. */
    private const KONTOPLAN_READ = '2000000000000000';

    /** An order the endpoints would create (all the fields OrderService::createOrder() requires without accountId). */
    private const PURCHASE_ORDER = ['companyName' => 'Apirights Leverandør ApS', 'phone' => '99887755', 'email' => 'apirights-supplier@example.invalid', 'vatRate' => 25];

    /**
     * The rights string perm_sync_user() writes for a role with Kassesystem and no Debitorordre: pos.kasse derives from
     * position 5, and perm_legacy_string() opens that position read so the till's pages let the user in.
     */
    private const TILL_ROLE_STRING = '0000020000000000';

    /** @var array<string, string> Access token per rights string and role, for this class's tenant. */
    private static $tokens = [];

    /** @var array<string, array{0:int, 1:int, 2:string}> Order id, line id and line description per order type. */
    private static $ordersWithLine = [];

    public static function tearDownAfterClass(): void
    {
        self::$tokens = [];
        self::$ordersWithLine = [];
        parent::tearDownAfterClass();
    }

    /**
     * Seed a user with this rights string (null = no rights string, as sager/ansatte.php creates an employee's login)
     * and, when $roleId is set, that role (brugere.role_id), and return its access token.
     */
    private static function tokenFor(?string $rettigheder, int $roleId = 0): string
    {
        $slot = ($rettigheder ?? "\0null") . "\0role$roleId";
        if (!isset(self::$tokens[$slot])) {
            $name = 'apirights_' . bin2hex(random_bytes(4));
            $password = TestCredentials::password('restapi_rights');
            self::rows(
                'INSERT INTO brugere (brugernavn, kode, email, rettigheder, status) VALUES ($1, $2, $3, $4, true) RETURNING id',
                [$name, md5($password), 'apirights@example.invalid', $rettigheder]
            );
            if ($roleId > 0) {
                self::rows('UPDATE brugere SET role_id = $1 WHERE brugernavn = $2', [$roleId, $name]);
            }
            $login = RestApiEnv::login($name, $password, RestApiEnv::accountOpen());
            $token = $login['json']['data']['access_token'] ?? '';
            if ($token === '') {
                throw new RuntimeException('login for the seeded rights user failed: ' . $login['body']);
            }
            self::$tokens[$slot] = $token;
        }
        return self::$tokens[$slot];
    }

    /**
     * A query on this class's tenant over a connection of its own (RestApiTestCase::tenant() keeps one per process,
     * which an earlier class's teardown has closed).
     *
     * @param array<int, mixed> $params
     * @return list<array<string, ?string>>
     */
    private static function rows(string $sql, array $params = []): array
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            return RestApiEnv::rows($tenant, $sql, $params);
        } finally {
            pg_close($tenant);
        }
    }

    /** @return array{status:int, json:?array, body:string} */
    private static function request(?string $rettigheder, string $method, string $path, ?array $body = null, int $roleId = 0): array
    {
        return RestApiEnv::http($method, $path, $body, ['Authorization: Bearer ' . self::tokenFor($rettigheder, $roleId)]);
    }

    /**
     * A role in this class's tenant with these levels, every other key none. The template tenant has had no web login,
     * so includes/betweenUpdates.php has not made the role tables yet; they are made here with its Postgres schema.
     *
     * @param array<string, string> $levels Permission key => level.
     */
    private static function role(array $levels): int
    {
        self::rows('CREATE TABLE IF NOT EXISTS roles (id SERIAL PRIMARY KEY NOT NULL, role_key varchar(30), navn varchar(80) NOT NULL, beskrivelse text, is_built_in boolean DEFAULT false, oprettet timestamp DEFAULT now())');
        self::rows('CREATE TABLE IF NOT EXISTS role_permissions (role_id integer NOT NULL, permission_key varchar(60) NOT NULL, level varchar(5) NOT NULL, PRIMARY KEY (role_id, permission_key))');
        self::rows('ALTER TABLE brugere ADD COLUMN IF NOT EXISTS role_id integer');
        $roleId = (int) self::rows('INSERT INTO roles (role_key, navn) VALUES ($1, $2) RETURNING id', ['apirights_' . bin2hex(random_bytes(4)), 'Apirights role'])[0]['id'];
        foreach ($levels as $key => $level) {
            self::rows('INSERT INTO role_permissions (role_id, permission_key, level) VALUES ($1, $2, $3)', [$roleId, $key, $level]);
        }
        return $roleId;
    }

    /**
     * Set the company's permission enforcement mode (settings permissions/enforce, perm_enforcement_mode()); null removes
     * the row, which reads as 'log'.
     */
    private static function setEnforcement(?string $mode): void
    {
        self::rows("DELETE FROM settings WHERE var_grp = 'permissions' AND var_name = 'enforce'");
        if ($mode !== null) {
            self::rows("INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('permissions', 'enforce', $1, 'Permission enforcement: log or deny')", [$mode]);
        }
    }

    /**
     * An order of this type (DO or KO) with one line, made by the suite's API user (every right) through its own
     * module's endpoints.
     *
     * @return array{0:int, 1:int, 2:string} Order id, line id and the line's description.
     */
    private static function orderWithLine(string $art): array
    {
        if (!isset(self::$ordersWithLine[$art])) {
            $side = ($art === 'KO') ? 'creditor' : 'debitor';
            $order = self::api('POST', "/restapi/endpoints/v1/$side/orders/", self::PURCHASE_ORDER);
            $orderId = (int) ($order['json']['data']['id'] ?? 0);
            $description = "Apirights $art line";
            $line = self::api('POST', "/restapi/endpoints/v1/$side/orderlines/", ['orderId' => $orderId, 'description' => $description, 'quantity' => 1, 'price' => 10]);
            $lineId = (int) ($line['json']['data']['id'] ?? 0);
            if ($orderId <= 0 || $lineId <= 0) {
                throw new RuntimeException("could not make a $art order with a line: " . $order['body'] . ' ' . $line['body']);
            }
            self::$ordersWithLine[$art] = [$orderId, $lineId, $description];
        }
        return self::$ordersWithLine[$art];
    }

    private function assertRefused(array $res, string $context): void
    {
        $this->assertSame(403, $res['status'], "$context: " . substr($res['body'], 0, 300));
        $this->assertIsArray($res['json'], "$context answers JSON: " . substr($res['body'], 0, 300));
        $this->assertFalse($res['json']['success'], $context);
        $this->assertNull($res['json']['data'], "$context carries no data");
    }

    /**
     * The endpoints of modules the user lacks, with a GET and a write each. Kontoplan is position 0, Kreditorordre 7.
     *
     * @return array<string, array{0:string, 1:string, 2:?array}>
     */
    public static function otherModules(): array
    {
        return [
            'accounts list' => ['GET', self::ACCOUNTS, null],
            'one account' => ['GET', self::ACCOUNTS . '?id=1', null],
            'account create' => ['POST', self::ACCOUNTS, ['accountNumber' => 9123456, 'description' => 'Apirights konto', 'accountType' => 'D']],
            'creditor orders list' => ['GET', self::CREDITOR_ORDERS, null],
            'creditor order create' => ['POST', self::CREDITOR_ORDERS, self::PURCHASE_ORDER],
            'creditor order lines' => ['GET', self::CREDITOR_ORDER_LINES . '?order_id=1', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('otherModules')]
    public function test_a_debtor_order_user_is_refused_the_chart_of_accounts_and_purchase_orders(string $method, string $path, ?array $body): void
    {
        $res = self::request(self::DEBTOR_ORDERS_ONLY, $method, $path, $body);

        $this->assertRefused($res, "$method $path for " . self::DEBTOR_ORDERS_ONLY);
        $this->assertStringNotContainsString('kontonr', $res['body'], 'no account row in the refusal');
    }

    public function test_a_refused_write_stores_nothing(): void
    {
        $before = self::rows("SELECT count(*) AS n FROM ordrer WHERE art = 'KO'");

        $this->assertRefused(self::request(self::DEBTOR_ORDERS_ONLY, 'POST', self::CREDITOR_ORDERS, self::PURCHASE_ORDER), 'creditor order create');
        $this->assertRefused(self::request(self::DEBTOR_ORDERS_ONLY, 'POST', self::ACCOUNTS, ['accountNumber' => 9123457, 'description' => 'Apirights konto', 'accountType' => 'D']), 'account create');

        $this->assertSame($before, self::rows("SELECT count(*) AS n FROM ordrer WHERE art = 'KO'"), 'no purchase order was created');
        $this->assertSame([], self::rows('SELECT id FROM kontoplan WHERE kontonr = $1', [9123457]), 'no account was created');
    }

    /** The same user keeps its own module: the gate is per module, not a blanket refusal. */
    public function test_a_debtor_order_user_reads_debtor_orders_and_invoices(): void
    {
        foreach ([self::DEBTOR_ORDERS, self::INVOICES] as $path) {
            $res = self::request(self::DEBTOR_ORDERS_ONLY, 'GET', $path . '?limit=1');
            $this->assertSame(200, $res['status'], "GET $path: " . substr($res['body'], 0, 300));
            $this->assertTrue($res['json']['success'], $path);
        }
    }

    /**
     * Rights strings read the way the module gate in includes/online.php reads them: any character from '1' up at the
     * position is access, '2' (read, from a role) included.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function openingStrings(): array
    {
        return [
            'Kontoplan write' => ['1000000000000000', self::ACCOUNTS],
            'Kontoplan read' => ['2000000000000000', self::ACCOUNTS],
            'Kontoplan 9' => ['9000000000000000', self::ACCOUNTS],
            'Kreditorordre read' => ['0000000200000000', self::CREDITOR_ORDERS],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('openingStrings')]
    public function test_the_module_position_opens_its_endpoint(string $rettigheder, string $path): void
    {
        $res = self::request($rettigheder, 'GET', $path . '?limit=1');

        $this->assertSame(200, $res['status'], "GET $path for $rettigheder: " . substr($res['body'], 0, 300));
        $this->assertTrue($res['json']['success']);
    }

    /**
     * A user without a rights string holds no right, as on the chart-of-accounts pages: refused even on Debitorordre.
     *
     * @return array<string, array{0:?string}>
     */
    public static function noRightsString(): array
    {
        return [
            'NULL' => [null],
            'empty' => [''],
            'zero' => ['0'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('noRightsString')]
    public function test_a_user_without_a_rights_string_is_refused_every_module(?string $rettigheder): void
    {
        foreach ([self::ACCOUNTS, self::DEBTOR_ORDERS, self::DEBTOR_ORDER_LINES . '?order_id=1', self::EXTERNAL_PAID_INVOICES] as $path) {
            $this->assertRefused(self::request($rettigheder, 'GET', $path), 'GET ' . $path . ' for ' . var_export($rettigheder, true));
        }
    }

    /**
     * Once enforcement is 'deny', read-only opens a module's reads but not its writes, as perm_enforce_request() refuses
     * them on the web: POST and PUT on accounts/ answer 403 and store nothing.
     */
    public function test_a_read_only_user_is_refused_writes_once_enforcement_is_deny(): void
    {
        $account = self::rows('SELECT id, kontonr, beskrivelse FROM kontoplan ORDER BY id LIMIT 1')[0];
        self::setEnforcement('deny');
        try {
            $read = self::request(self::KONTOPLAN_READ, 'GET', self::ACCOUNTS . '?limit=1');
            $this->assertSame(200, $read['status'], 'GET accounts/ read-only under deny: ' . substr($read['body'], 0, 300));

            $this->assertRefused(
                self::request(self::KONTOPLAN_READ, 'POST', self::ACCOUNTS, ['accountNumber' => 9123456, 'description' => 'Unauthorized account', 'accountType' => 'D']),
                'POST accounts/ read-only under deny'
            );
            $this->assertRefused(
                self::request(self::KONTOPLAN_READ, 'PUT', self::ACCOUNTS, ['id' => (int) $account['id'], 'description' => 'Unauthorized rename']),
                'PUT accounts/ read-only under deny'
            );
        } finally {
            self::setEnforcement(null);
        }

        $this->assertSame([], self::rows('SELECT id FROM kontoplan WHERE kontonr = $1', [9123456]), 'no account was created');
        $this->assertSame([$account], self::rows('SELECT id, kontonr, beskrivelse FROM kontoplan WHERE id = $1', [$account['id']]), 'the account is unchanged');
    }

    /** Until enforcement is 'deny' the web lets a read-only user save and only logs it; the API does the same. */
    public function test_a_read_only_user_may_write_while_enforcement_only_logs(): void
    {
        $res = self::request(self::KONTOPLAN_READ, 'POST', self::ACCOUNTS, ['accountNumber' => 9123458, 'description' => 'Apirights log mode', 'accountType' => 'D']);

        $this->assertSame(201, $res['status'], 'POST accounts/ read-only under log: ' . substr($res['body'], 0, 300));
    }

    /**
     * The body cannot change the order type an endpoint creates: {"art":"DO","type":"KO"} on debitor/orders/ made a
     * purchase order for a user without Kreditorordre.
     *
     * @return array<string, array{0:string, 1:string, 2:string, 3:string}>
     */
    public static function orderTypeInBody(): array
    {
        return [
            'debitor/orders/ asked for KO' => [self::DEBTOR_ORDERS_ONLY, self::DEBTOR_ORDERS, 'DO', 'KO'],
            'debitor/invoices/ asked for KO' => [self::DEBTOR_ORDERS_ONLY, self::INVOICES, 'DO', 'KO'],
            'creditor/orders/ asked for DO' => [self::CREDITOR_ORDERS_ONLY, self::CREDITOR_ORDERS, 'KO', 'DO'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('orderTypeInBody')]
    public function test_the_body_cannot_change_the_order_type_an_endpoint_creates(string $rettigheder, string $path, string $own, string $other): void
    {
        $before = self::rows('SELECT count(*) AS n FROM ordrer WHERE art = $1', [$other]);

        $res = self::request($rettigheder, 'POST', $path, ['art' => $own, 'type' => $other, 'companyName' => 'Rights test', 'phone' => '99887755', 'email' => 'rights@example.invalid', 'vatRate' => 25]);

        $this->assertSame(201, $res['status'], "POST $path: " . substr($res['body'], 0, 300));
        $id = (int) ($res['json']['data']['id'] ?? 0);
        $this->assertSame([['art' => $own]], self::rows('SELECT art FROM ordrer WHERE id = $1', [$id]), "$path made a $own order");
        $this->assertSame($before, self::rows('SELECT count(*) AS n FROM ordrer WHERE art = $1', [$other]), "no $other order was made");
    }

    /**
     * Each order lines endpoint serves its own module's orders only: the other side's order, its line, and a line added to
     * it are "not found" for a user with only this side's right, and the order gets no line.
     *
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function otherSidesOrder(): array
    {
        return [
            'debitor/orderlines/ on a purchase order' => [self::DEBTOR_ORDERS_ONLY, self::DEBTOR_ORDER_LINES, 'KO'],
            'creditor/orderlines/ on a sales order' => [self::CREDITOR_ORDERS_ONLY, self::CREDITOR_ORDER_LINES, 'DO'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('otherSidesOrder')]
    public function test_an_order_lines_endpoint_serves_only_its_own_modules_orders(string $rettigheder, string $path, string $otherArt): void
    {
        [$orderId, $lineId, $description] = self::orderWithLine($otherArt);
        $lines = self::rows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$orderId]);

        foreach (['?order_id=' . $orderId, '?id=' . $lineId] as $query) {
            $res = self::request($rettigheder, 'GET', $path . $query);
            $this->assertSame(404, $res['status'], "GET $path$query: " . substr($res['body'], 0, 300));
            $this->assertNull($res['json']['data'] ?? null, "GET $path$query carries no data");
            $this->assertStringNotContainsString($description, $res['body'], "GET $path$query shows no line");
        }

        $add = self::request($rettigheder, 'POST', $path, ['orderId' => $orderId, 'description' => 'Unauthorized line', 'quantity' => 1, 'price' => 10]);
        $this->assertSame(400, $add['status'], "POST $path: " . substr($add['body'], 0, 300));
        $this->assertSame('Order not found', $add['json']['message'] ?? null, 'the same answer as an order that does not exist');
        $this->assertSame($lines, self::rows('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$orderId]), 'no line was added');
    }

    /** The same users keep their own side: they read and add lines on their own module's orders. */
    public function test_an_order_lines_endpoint_still_serves_its_own_modules_orders(): void
    {
        foreach ([[self::DEBTOR_ORDERS_ONLY, self::DEBTOR_ORDER_LINES, 'DO'], [self::CREDITOR_ORDERS_ONLY, self::CREDITOR_ORDER_LINES, 'KO']] as [$rettigheder, $path, $art]) {
            [$orderId, $lineId, $description] = self::orderWithLine($art);

            $list = self::request($rettigheder, 'GET', $path . '?order_id=' . $orderId);
            $this->assertSame(200, $list['status'], "GET $path?order_id: " . substr($list['body'], 0, 300));
            $this->assertContains($description, array_column($list['json']['data'], 'description'));

            $one = self::request($rettigheder, 'GET', $path . '?id=' . $lineId);
            $this->assertSame(200, $one['status'], "GET $path?id: " . substr($one['body'], 0, 300));

            $add = self::request($rettigheder, 'POST', $path, ['orderId' => $orderId, 'description' => "Apirights own $art line", 'quantity' => 1, 'price' => 10]);
            $this->assertSame(201, $add['status'], "POST $path: " . substr($add['body'], 0, 300));
        }
    }

    /** SQL in an id cannot widen the order type a query is limited to (the models cast ids to int). */
    public function test_sql_in_an_id_does_not_reach_another_modules_orders(): void
    {
        [, , $purchaseLine] = self::orderWithLine('KO');
        [$salesOrderId] = self::orderWithLine('DO');

        $lines = self::request(self::DEBTOR_ORDERS_ONLY, 'GET', self::DEBTOR_ORDER_LINES . '?order_id=' . rawurlencode("$salesOrderId OR 1=1"));
        $this->assertSame(200, $lines['status'], substr($lines['body'], 0, 300));
        $this->assertSame([$salesOrderId], array_values(array_unique(array_map('intval', array_column($lines['json']['data'], 'orderId')))), 'only the sales order\'s lines');
        $this->assertStringNotContainsString($purchaseLine, $lines['body']);

        $order = self::request(self::DEBTOR_ORDERS_ONLY, 'GET', self::DEBTOR_ORDERS . '?id=' . rawurlencode("0 OR art = 'KO' OR id = 0"));
        $this->assertSame(404, $order['status'], 'no purchase order through debitor/orders/: ' . substr($order['body'], 0, 300));
    }

    /**
     * An assigned role decides the level, not the rights string it is rendered into. A role with Kassesystem and no
     * Debitorordre gets 0000020000000000, position 5 open for the till; once enforcement is 'deny' the web refuses that
     * user Debitorordre, and so does the API. The same string without a role still reads.
     */
    public function test_an_assigned_role_decides_the_level_not_the_rights_string_it_renders(): void
    {
        $tillRole = self::role(['pos.kasse' => 'write']);
        [$salesOrderId] = self::orderWithLine('DO');
        self::setEnforcement('deny');
        try {
            foreach ([self::DEBTOR_ORDERS . '?limit=1', self::INVOICES . '?limit=1', self::DEBTOR_ORDER_LINES . '?order_id=' . $salesOrderId] as $path) {
                $this->assertRefused(self::request(self::TILL_ROLE_STRING, 'GET', $path, null, $tillRole), "GET $path for the till role under deny");
            }
            $before = self::rows("SELECT count(*) AS n FROM ordrer WHERE art = 'DO'");
            $this->assertRefused(self::request(self::TILL_ROLE_STRING, 'POST', self::DEBTOR_ORDERS, self::PURCHASE_ORDER, $tillRole), 'POST debitor/orders/ for the till role under deny');
            $this->assertSame($before, self::rows("SELECT count(*) AS n FROM ordrer WHERE art = 'DO'"), 'no sales order was created');

            $read = self::request(self::TILL_ROLE_STRING, 'GET', self::DEBTOR_ORDERS . '?limit=1');
            $this->assertSame(200, $read['status'], 'the same string without a role reads Debitorordre: ' . substr($read['body'], 0, 300));
        } finally {
            self::setEnforcement(null);
        }
    }

    /**
     * A number field that is not a number is a 400 and stores nothing. OrderModel::save() put the body's status into
     * its INSERT unescaped, so this status replaced the values after it, the order type included, and debitor/orders/
     * stored a purchase order for a user without Kreditorordre.
     *
     * @return array<string, array{0:string}>
     */
    public static function orderCreatingEndpoints(): array
    {
        return [
            'debitor/orders/' => [self::DEBTOR_ORDERS],
            'debitor/invoices/' => [self::INVOICES],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('orderCreatingEndpoints')]
    public function test_sql_in_an_order_status_is_refused_and_stores_no_order(string $path): void
    {
        [$salesOrderId] = self::orderWithLine('DO');
        $accountId = (int) self::rows('SELECT konto_id FROM ordrer WHERE id = $1', [$salesOrderId])[0]['konto_id'];
        $orders = self::rows('SELECT art, count(*) AS n FROM ordrer GROUP BY art ORDER BY art');

        $res = self::request(self::DEBTOR_ORDERS_ONLY, 'POST', $path, ['accountId' => $accountId, 'vatRate' => 25, 'status' => "0', '987654', '100', 'KO', '2026-11-08' -- "]);

        $this->assertSame(400, $res['status'], "POST $path: " . substr($res['body'], 0, 300));
        $this->assertStringContainsString('status', $res['json']['message'] ?? '', 'the answer names the field');
        $this->assertSame([], self::rows('SELECT id, art FROM ordrer WHERE ordrenr = 987654'), 'no order took the injected number');
        $this->assertSame($orders, self::rows('SELECT art, count(*) AS n FROM ordrer GROUP BY art ORDER BY art'), 'no order was created');
    }

    /** Text in an order body is stored as text: a quote in it no longer ends the value (the new debtor's name, the notes). */
    public function test_quotes_in_an_orders_text_are_stored_as_written(): void
    {
        $name = "O'Brien's Apirights ApS";
        $notes = "x', '', '0', '0', '0', 'DKK', 'netto', '8', '1', '', '0', '987655', '100', 'KO', '2026-11-08' -- ";
        $phone = (string) random_int(10000000, 99999999);

        $res = self::request(self::DEBTOR_ORDERS_ONLY, 'POST', self::DEBTOR_ORDERS, ['companyName' => $name, 'phone' => $phone, 'email' => "apirights-$phone@example.invalid", 'vatRate' => 25, 'notes' => $notes]);

        $this->assertSame(201, $res['status'], substr($res['body'], 0, 300));
        $id = (int) ($res['json']['data']['id'] ?? 0);
        $this->assertSame([['art' => 'DO', 'notes' => $notes, 'firmanavn' => $name]], self::rows('SELECT art, notes, firmanavn FROM ordrer WHERE id = $1', [$id]));
        $this->assertSame([['firmanavn' => $name, 'art' => 'D']], self::rows('SELECT firmanavn, art FROM adresser WHERE tlf = $1', [$phone]), 'the new debtor has the name as written');
        $this->assertSame([], self::rows('SELECT id FROM ordrer WHERE ordrenr = 987655'));
    }

    /**
     * SQL in a line's number field does not add a line to another module's order. OrderLineService put the body's
     * positionNumber into its INSERT unescaped, so this one added a second row: a line on the purchase order.
     */
    public function test_sql_in_an_order_line_number_adds_no_line_to_another_modules_order(): void
    {
        [$purchaseOrderId] = self::orderWithLine('KO');
        [$salesOrderId] = self::orderWithLine('DO');
        $lines = self::rows('SELECT ordre_id, count(*) AS n FROM ordrelinjer WHERE ordre_id IN ($1, $2) GROUP BY ordre_id ORDER BY ordre_id', [$purchaseOrderId, $salesOrderId]);

        $res = self::request(self::DEBTOR_ORDERS_ONLY, 'POST', self::DEBTOR_ORDER_LINES, [
            'orderId' => $salesOrderId, 'description' => 'Apirights line', 'quantity' => 1, 'price' => 10,
            'positionNumber' => "1', '0', NULL), ($purchaseOrderId, NULL, '', '', 'Apirights injected', '1', '0', '100', '10', '10', '0', '0', '0', '1",
        ]);

        $this->assertSame(400, $res['status'], substr($res['body'], 0, 300));
        $this->assertStringContainsString('positionNumber', $res['json']['message'] ?? '');
        $this->assertSame($lines, self::rows('SELECT ordre_id, count(*) AS n FROM ordrelinjer WHERE ordre_id IN ($1, $2) GROUP BY ordre_id ORDER BY ordre_id', [$purchaseOrderId, $salesOrderId]), 'no line was added to either order');
        $this->assertSame([], self::rows("SELECT id FROM ordrelinjer WHERE beskrivelse = 'Apirights injected'"));
    }
}

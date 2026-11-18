<?php
// 20261116 CDX/ASR Regress login against shared role schemas without changing existing grants.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/permissions.php';

final class RoleSchemaCompatibilityTest extends StripeDatabaseTestCase
{
    public function testBothBuiltInColumnNamesPreserveRolesAndSeedIdempotently(): void
    {
        foreach (['system', 'is_built_in'] as $column) {
            $schema = 'role_compat_' . bin2hex(random_bytes(5));
            StripeTestEnvironment::query('CREATE SCHEMA ' . $schema);
            StripeTestEnvironment::query('SET LOCAL search_path TO ' . $schema . ', public');
            StripeTestEnvironment::query("CREATE TABLE roles (id serial PRIMARY KEY, role_key varchar(30), navn varchar(80), beskrivelse text, $column boolean DEFAULT false)");
            StripeTestEnvironment::query('CREATE TABLE role_permissions (role_id integer, permission_key varchar(60), level varchar(5), PRIMARY KEY(role_id, permission_key))');
            StripeTestEnvironment::query('CREATE TABLE brugere (id serial PRIMARY KEY, role_id integer, rettigheder varchar(16))');
            StripeTestEnvironment::query('CREATE TABLE settings (id serial PRIMARY KEY, var_grp text, var_name text, var_value text, var_description text)');
            StripeTestEnvironment::query("INSERT INTO roles(role_key,navn,beskrivelse,$column) VALUES ('administrator','Existing admin','Keep description',true),(null,'Investor','Custom role',false)");
            StripeTestEnvironment::query("INSERT INTO role_permissions VALUES (1,'settings.users','write'),(2,'finance.journal','read')");
            StripeTestEnvironment::query("INSERT INTO brugere(role_id,rettigheder) VALUES (2,'0000000000000000')");
            $this->assertSame($column, perm_role_system_column());
            $roles = perm_roles();
            $this->assertTrue($roles[0]['system']);
            $this->assertFalse($roles[1]['system']);
            perm_ensure_default_roles();
            $first = perm_roles();
            perm_ensure_default_roles();
            $this->assertSame($first, perm_roles(), 'Repeated login must not duplicate roles');
            $admin = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM roles WHERE id=1'));
            $this->assertSame('Existing admin', $admin['navn']);
            $this->assertSame('Keep description', $admin['beskrivelse']);
            $this->assertSame('read', pg_fetch_result(StripeTestEnvironment::query("SELECT level FROM role_permissions WHERE role_id=2 AND permission_key='finance.journal'"), 0, 0));
            $this->assertSame('2', pg_fetch_result(StripeTestEnvironment::query('SELECT role_id FROM brugere WHERE id=1'), 0, 0));
        }
    }
}

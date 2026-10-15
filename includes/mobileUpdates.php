<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/mobileUpdates.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Address B1 identity, migration and security review findings.
// 20261001 CDX/LH Append B7 legacy inbox columns, push key correction and dispatched token fingerprint.
// 20261001 CDX/LH Expose the ordered migration steps for append-safe marker assertions.
// 20261001 CDX/LH Add normalized amounts to existing pool tables for legacy web inserts.

// 20261001 CDX/LH Append Tier 2 schema and atomic settlement IDs.
// 20261005 CDX/LH Append authoritative captured money per payment reference.
// 20261005 CDX/LH Provision sales employee and accounting period guards at REST login.
// 20261001 CDX/LH Append Tier 3 review, audit, bank, event, provenance, VAT and move schema.
// 20261001 CDX/LH Append mobile approval journal identity to prevent recycled web ID reuse.
// 20261001 CDX/LH Append Tier 4 counts, receipts, time, bookings, claims and FEFO schema.

require_once __DIR__ . '/../restapi/core/MobileSchema.php';

/** Tier 1 migrations on the selected tenant. Pass a connection explicitly in tests. */
function mobileTenantUpdates($connection = null): void
{
    $schema = new MobileSchema($connection);
    $schema->run('tenant', mobileTenantUpdateSteps());
}

/**
 * Ordered migrations shared by the runner and marker consumers.
 *
 * @return array<string, Closure(MobileSchema): void> Named steps in application order.
 */
function mobileTenantUpdateSteps(): array
{
    return [
        'm01_pool_identity' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS pool_files (
                id serial PRIMARY KEY, filename varchar(255) NOT NULL UNIQUE, subject text,
                account varchar(50), amount varchar(50), norm_amount numeric(15,3), file_date varchar(50),
                invoice_number varchar(100), description text, currency varchar(10),
                updated timestamptz DEFAULT now(), vendor_name text, vendor_cvr varchar(20),
                vendor_iban varchar(40), vendor_konto_id integer, vendor_match varchar(10),
                vendor_score numeric(4,3), content_sha256 char(64))");
            foreach ([
                'uploaded_by' => 'varchar(255)', 'client_id' => 'varchar(36)', 'sha256' => 'char(64)',
                'status' => "varchar(20) NOT NULL DEFAULT 'ubehandlet' CHECK (status IN ('staged','ubehandlet','attached','deleted'))",
                'storage_path' => 'text', 'attached_at' => 'timestamptz', 'attached_by' => 'text',
                'bilag_nr' => 'integer', 'document_ids' => 'text', 'thumbnail_path' => 'text',
                'deleted_at' => 'timestamptz', 'uploaded_at' => 'timestamptz DEFAULT now()',
            ] as $name => $definition) {
                $s->column('pool_files', $name, $definition);
            }
            $s->index('pool_files', 'mobile_pool_client_id', 'client_id', true);
            $s->index('pool_files', 'mobile_pool_owner_status', 'uploaded_by, status');
        },
        'm02_document_pool_link' => function (MobileSchema $s): void {
            $s->column('documents', 'pool_file_id', 'integer REFERENCES pool_files(id)');
            $s->index('documents', 'mobile_documents_pool', 'pool_file_id');
        },
        'm03_push_identity' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS push_tokens (
                id serial PRIMARY KEY, user_id integer, token text NOT NULL, platform varchar(20),
                db_name varchar(100), created_at timestamptz DEFAULT now(), UNIQUE(token, user_id, db_name))");
            foreach ([
                'subject' => 'varchar(255)', 'principal' => "varchar(8) DEFAULT 'account' CHECK (principal IN ('account','registry'))",
                'device_id' => 'varchar(128)', 'last_seen_at' => 'timestamptz', 'native_token' => 'text',
            ] as $name => $definition) {
                $s->column('push_tokens', $name, $definition);
            }
            $s->execute("UPDATE push_tokens SET last_seen_at=created_at WHERE last_seen_at IS NULL");
            $s->index('push_tokens', 'mobile_push_subject_device', 'subject, device_id', true);
        },
        'm04_reminders_inbox_dispatch' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS reminders (
                id serial PRIMARY KEY, target_subject text NOT NULL, message text NOT NULL,
                amount numeric(15,3), date date, created_by text NOT NULL, created_at timestamptz DEFAULT now())");
            $s->execute("CREATE TABLE IF NOT EXISTS notifications (
                id serial PRIMARY KEY, type varchar(30) NOT NULL, title text NOT NULL, message text NOT NULL,
                reminder_id integer REFERENCES reminders(id), created_by text NOT NULL, created_at timestamptz DEFAULT now())");
            $s->execute("CREATE TABLE IF NOT EXISTS notification_recipients (
                id serial PRIMARY KEY, notification_id integer NOT NULL REFERENCES notifications(id),
                subject varchar(255) NOT NULL, read_at timestamptz, resolved_at timestamptz,
                UNIQUE(notification_id, subject))");
            $s->execute("CREATE TABLE IF NOT EXISTS push_dispatch (
                id serial PRIMARY KEY, recipient_id integer NOT NULL REFERENCES notification_recipients(id),
                device_id varchar(128) NOT NULL, push_token_id integer REFERENCES push_tokens(id) ON DELETE SET NULL,
                state varchar(20) NOT NULL DEFAULT 'stored' CHECK (state IN ('stored','sent','ticketed','delivered','rejected')),
                attempts integer NOT NULL DEFAULT 0, ticket_id text, next_attempt_at timestamptz DEFAULT now(),
                last_error text, created_at timestamptz DEFAULT now(), UNIQUE(recipient_id, device_id))");
            $s->index('notification_recipients', 'mobile_recipient_subject', 'subject');
            $s->index('push_dispatch', 'mobile_dispatch_due', 'state, next_attempt_at');
        },
        'm05_mobile_ip_exception' => function (MobileSchema $s): void {
            $s->column('brugere', 'mobil_undtaget', 'boolean NOT NULL DEFAULT false');
        },
        'm06_timezone_correction' => function (MobileSchema $s): void {
            foreach ([
                'pool_files' => ['updated', 'attached_at', 'deleted_at', 'uploaded_at'],
                'push_tokens' => ['created_at', 'last_seen_at'],
                'reminders' => ['created_at'], 'notifications' => ['created_at'],
                'notification_recipients' => ['read_at', 'resolved_at'],
                'push_dispatch' => ['next_attempt_at', 'created_at'],
            ] as $table => $columns) {
                foreach ($columns as $column) {
                    $s->timezoneColumn($table, $column);
                }
            }
        },
        'm07_push_delivery_identity' => function (MobileSchema $s): void {
            global $db_type;
            foreach ([
                'type'=>'varchar(30)', 'title'=>'text', 'message'=>'text',
                'reminder_id'=>'integer REFERENCES reminders(id)', 'created_by'=>'text',
                'created_at'=>'timestamptz DEFAULT now()',
            ] as $column=>$definition) {
                $s->column('notifications', $column, $definition);
            }
            $s->column('push_dispatch', 'sent_token_hash', 'char(64)');
            $s->column('push_dispatch', 'ticketed_at', 'timestamptz');
            if ($db_type === 'mysqli' || $db_type === 'mysql') {
                $indexes = $s->rows("SELECT index_name FROM information_schema.statistics
                    WHERE table_schema=DATABASE() AND table_name='push_tokens' AND non_unique=0
                    GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index)='token,user_id,db_name'");
                foreach ($indexes as $index) {
                    $name = str_replace('`', '``', $index['index_name']);
                    $s->execute("ALTER TABLE push_tokens DROP INDEX `$name`");
                }
            } else {
                $s->execute('ALTER TABLE push_tokens DROP CONSTRAINT IF EXISTS push_tokens_token_user_id_db_name_key');
            }
        },
        'm08_pool_normalized_amount' => function (MobileSchema $s): void {
            $s->column('pool_files', 'norm_amount', 'numeric(15,3)');
        },
        'm2_01_idempotency' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_idempotency (
                id serial PRIMARY KEY, subject varchar(255) NOT NULL, client_id varchar(64) NOT NULL,
                operation varchar(40) NOT NULL, request_sha256 char(64) NOT NULL,
                http_status smallint NOT NULL, response_json text NOT NULL,
                resource_type varchar(30), resource_id integer, created_at timestamptz NOT NULL DEFAULT now(),
                UNIQUE(subject, client_id))");
            $s->index('mobile_idempotency', 'mobile_idempotency_created', 'created_at');
        },
        'm2_02_order_origin' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_order_origins (
                ordre_id integer PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE,
                created_by varchar(255) NOT NULL, kind varchar(8) NOT NULL CHECK (kind IN ('tilbud','ordre')),
                created_at timestamptz NOT NULL DEFAULT now())");
            $s->index('mobile_order_origins', 'mobile_order_origin_creator', 'created_by, created_at');
        },
        'm2_03_document_deliveries' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS document_deliveries (
                id serial PRIMARY KEY, ordre_id integer NOT NULL,
                document varchar(10) NOT NULL CHECK (document IN ('tilbud','ordre','faktura','rykker')),
                channel varchar(6) NOT NULL CHECK (channel IN ('email','ean','none')), recipient text,
                state varchar(10) NOT NULL DEFAULT 'sending' CHECK (state IN ('sending','sent','failed','unknown')),
                error_reason varchar(40), provider_ref text, created_by varchar(255) NOT NULL,
                client_id varchar(36) NOT NULL UNIQUE, created_at timestamptz NOT NULL DEFAULT now(), finished_at timestamptz)");
            $s->index('document_deliveries', 'mobile_delivery_order', 'ordre_id, created_at');
        },
        'm2_04_payment_links' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS payment_links (
                id serial PRIMARY KEY, token_sha256 char(64) NOT NULL UNIQUE, provider varchar(20) NOT NULL,
                openpost_id integer NOT NULL, konto_id integer NOT NULL, ordre_id integer, faktnr text NOT NULL,
                amount numeric(15,2) NOT NULL CHECK (amount > 0), currency char(3) NOT NULL,
                state varchar(16) NOT NULL DEFAULT 'active' CHECK (state IN ('active','paid','paid_unsettled','review','expired','cancelled','superseded')),
                expires_at timestamptz NOT NULL, created_by varchar(255) NOT NULL, client_id varchar(36) NOT NULL UNIQUE,
                created_at timestamptz NOT NULL DEFAULT now(), paid_at timestamptz, settled_at timestamptz, registration_id integer)");
            $s->index('payment_links', 'mobile_link_post_state', 'openpost_id, state');
            if (!ifset($GLOBALS, 'db_type') || !in_array($GLOBALS['db_type'], ['mysql','mysqli'], true)) {
                $s->execute("CREATE UNIQUE INDEX IF NOT EXISTS mobile_link_active ON payment_links(openpost_id) WHERE state='active'");
            }
        },
        'm2_05_payment_attempts' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS payment_attempts (
                id serial PRIMARY KEY, link_id integer NOT NULL REFERENCES payment_links(id),
                provider_reference varchar(50) NOT NULL UNIQUE,
                state varchar(12) NOT NULL DEFAULT 'created' CHECK (state IN ('created','authorized','capturing','captured','aborted','expired','cancelled','terminated','failed')),
                booking varchar(10) NOT NULL DEFAULT 'none' CHECK (booking IN ('none','booked','review','unsettled')),
                registration_id integer, amount_minor bigint NOT NULL, currency char(3) NOT NULL,
                redirect_url text, psp_reference varchar(64), last_checked_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz, captured_at timestamptz)");
            $s->index('payment_attempts', 'mobile_attempt_link', 'link_id');
            $s->index('payment_attempts', 'mobile_attempt_state', 'state, updated_at');
            if (!in_array(ifset($GLOBALS, 'db_type'), ['mysql','mysqli'], true)) {
                $s->execute("CREATE UNIQUE INDEX IF NOT EXISTS mobile_attempt_live ON payment_attempts(link_id) WHERE state IN ('created','authorized','capturing')");
            }
        },
        'm2_06_payment_provider_events' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS payment_provider_events (
                id serial PRIMARY KEY, provider varchar(20) NOT NULL, event_key char(64) NOT NULL UNIQUE,
                provider_reference varchar(50), name varchar(60) NOT NULL, payload text NOT NULL,
                state varchar(10) NOT NULL DEFAULT 'received' CHECK (state IN ('received','processed','ignored','failed')),
                error text, received_at timestamptz NOT NULL DEFAULT now(), processed_at timestamptz)");
            $s->index('payment_provider_events', 'mobile_provider_event_state', 'state, received_at');
            $s->index('payment_provider_events', 'mobile_provider_event_reference', 'provider_reference');
        },
        'm2_07_payment_registrations' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS payment_registrations (
                id serial PRIMARY KEY, client_id varchar(64) NOT NULL UNIQUE,
                source varchar(12) NOT NULL CHECK (source IN ('app','payment_link')), openpost_id integer NOT NULL,
                konto_id integer NOT NULL, faktnr text NOT NULL, amount numeric(15,2) NOT NULL, currency char(3) NOT NULL,
                method varchar(10) NOT NULL CHECK (method IN ('bank','kasse','mobilepay')), account_kontonr integer NOT NULL,
                transdate date NOT NULL, kladde_id integer NOT NULL, bilag integer NOT NULL,
                aligned boolean NOT NULL DEFAULT false, review_reason varchar(40), created_by varchar(255) NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now())");
            $s->index('payment_registrations', 'mobile_registration_post', 'openpost_id');
            $s->index('payment_registrations', 'mobile_registration_customer', 'konto_id, created_at');
        },
        'm2_08_notification_refs' => function (MobileSchema $s): void {
            $s->column('notifications', 'ref_type', 'varchar(20)');
            $s->column('notifications', 'ref_id', 'integer');
        },
        'm2_09_catalogue_sync' => function (MobileSchema $s): void {
            if (in_array(ifset($GLOBALS, 'db_type'), ['mysql','mysqli'], true)) {
                // PostgreSQL transaction snapshots are required for the safe delta feed.
                return;
            }
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_catalogue_changes (
                id bigserial PRIMARY KEY, vare_id integer NOT NULL, deleted boolean NOT NULL DEFAULT false,
                txid bigint NOT NULL DEFAULT txid_current(), changed_at timestamptz NOT NULL DEFAULT now())");
            $s->index('mobile_catalogue_changes', 'mobile_catalogue_txid', 'txid, id');
            $s->index('mobile_catalogue_changes', 'mobile_catalogue_age', 'changed_at');
            $s->index('varer', 'mobile_varer_stregkode', 'stregkode');
            $s->execute("CREATE OR REPLACE FUNCTION mobile_varer_change() RETURNS trigger LANGUAGE plpgsql AS \$\$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        INSERT INTO mobile_catalogue_changes(vare_id,deleted) VALUES (OLD.id,true);
                        RETURN OLD;
                    END IF;
                    INSERT INTO mobile_catalogue_changes(vare_id,deleted) VALUES (NEW.id,false);
                    RETURN NEW;
                END \$\$");
            if (!$s->rows("SELECT 1 FROM pg_trigger WHERE tgrelid='varer'::regclass AND tgname='mobile_varer_change' AND NOT tgisinternal")) {
                $s->execute('CREATE TRIGGER mobile_varer_change AFTER INSERT OR UPDATE OR DELETE ON varer FOR EACH ROW EXECUTE FUNCTION mobile_varer_change()');
            }
        },
        'm2_10_udlign_sequence' => function (MobileSchema $s): void {
            if (in_array(ifset($GLOBALS, 'db_type'), ['mysql','mysqli'], true)) {
                return;
            }
            require_once __DIR__ . '/openpost/udlignId.php';
            udlign_initialize_sequence($s);
        },
        'm2_11_payment_capture_money' => function (MobileSchema $s): void {
            $s->column('payment_attempts', 'captured_minor', 'bigint CHECK (captured_minor > 0)');
            $s->column('payment_attempts', 'captured_currency', 'char(3)');
            // Existing amounts and processing timestamps cannot be treated as provider facts.
            // Recovery resolves historical captures from the provider before filling these columns.
        },
        'm2_12_app_login_legacy_schema' => function (MobileSchema $s): void {
            // Older installed tenants receive this header column only at web login.
            $s->column('ordrer', 'performed_by', 'text');
            if (in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true)) {
                return;
            }
            // check_periode_luk() and the posting trigger share the web's SD-646 table.
            $s->execute("CREATE TABLE IF NOT EXISTS moms_periode_luk (
                id serial PRIMARY KEY, kalender_aar integer NOT NULL,
                kalender_maaned integer NOT NULL CHECK (kalender_maaned BETWEEN 1 AND 12),
                status varchar(6) NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed')),
                lukket_af varchar(100), lukket_dato timestamp,
                aabnet_af varchar(100), aabnet_dato timestamp,
                UNIQUE (kalender_aar, kalender_maaned))");
            if (!$s->rows("SELECT 1 FROM pg_proc WHERE oid=to_regprocedure('check_moms_periode_luk()')")) {
                $s->execute("CREATE FUNCTION check_moms_periode_luk() RETURNS trigger AS \$\$
                    BEGIN
                        IF EXISTS (SELECT 1 FROM moms_periode_luk
                            WHERE kalender_aar=EXTRACT(YEAR FROM NEW.transdate)
                            AND kalender_maaned=EXTRACT(MONTH FROM NEW.transdate) AND status='closed') THEN
                            RAISE EXCEPTION 'Perioden % er lukket for bogfoering - kontakt bogholder for at genaabne.',
                                TO_CHAR(NEW.transdate, 'MM-YYYY');
                        END IF;
                        RETURN NEW;
                    END; \$\$ LANGUAGE plpgsql");
            }
            if (!$s->rows("SELECT 1 FROM pg_trigger WHERE tgrelid='transaktioner'::regclass
                AND tgname='tr_check_moms_periode_luk' AND NOT tgisinternal")) {
                $s->execute('CREATE TRIGGER tr_check_moms_periode_luk BEFORE INSERT OR UPDATE ON transaktioner
                    FOR EACH ROW EXECUTE FUNCTION check_moms_periode_luk()');
            }
        },
        'm3_01_pool_review' => function (MobileSchema $s): void {
            foreach ([
                'manually_edited' => 'boolean NOT NULL DEFAULT false',
                'review_state' => "varchar(10) NOT NULL DEFAULT 'pending' CHECK (review_state IN ('pending','approved','rejected'))",
                'reviewed_by' => 'varchar(255)', 'reviewed_at' => 'timestamptz',
                'rejection_code' => 'varchar(20)', 'rejection_note' => 'text',
                'resubmitted_at' => 'timestamptz', 'vat_code' => 'varchar(10)',
                'reminder_id' => 'integer REFERENCES reminders(id)',
            ] as $name => $definition) {
                $s->column('pool_files', $name, $definition);
            }
            $s->index('pool_files', 'mobile_pool_review', 'status, review_state, uploaded_at');
        },
        'm3_02_pool_audit' => function (MobileSchema $s): void {
            $mysql = in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true);
            $identity = $mysql ? 'bigint AUTO_INCREMENT' : 'bigserial';
            $s->execute("CREATE TABLE IF NOT EXISTS pool_file_audit (
                id $identity PRIMARY KEY, pool_file_id integer NOT NULL REFERENCES pool_files(id),
                event varchar(20) NOT NULL CHECK (event IN ('uploaded','metadata_changed','attached','deleted','returned','approved','rejected','resubmitted','draft_created')),
                actor varchar(255) NOT NULL, changes text NOT NULL DEFAULT '{}', reason_code varchar(20),
                note text, request_id varchar(36), prev_sha256 char(64), row_sha256 char(64) NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now())");
            $s->index('pool_file_audit', 'mobile_pool_audit_chain', 'pool_file_id, id');
            if ($mysql) {
                return;
            }
            $s->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION mobile_append_only() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Mobile provenance is append-only' USING ERRCODE = '55000';
END $$
SQL
            );
            $s->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION mobile_audit_chain() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    PERFORM id FROM pool_files WHERE id=NEW.pool_file_id FOR UPDATE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Audit attachment does not exist' USING ERRCODE = '23503';
    END IF;
    -- Allocate after the pool lock, so even direct concurrent audit inserts order by id.
    NEW.id := nextval(pg_get_serial_sequence('pool_file_audit','id'));
    SELECT row_sha256 INTO NEW.prev_sha256 FROM pool_file_audit
        WHERE pool_file_id=NEW.pool_file_id ORDER BY id DESC LIMIT 1;
    NEW.changes := (NEW.changes::jsonb)::text;
    NEW.row_sha256 := encode(sha256(convert_to(COALESCE(NEW.prev_sha256,'') ||
        jsonb_build_array(NEW.id,NEW.pool_file_id,NEW.event,NEW.actor,NEW.changes::jsonb,
            NEW.reason_code,NEW.note,NEW.request_id,
            to_char(NEW.created_at AT TIME ZONE 'UTC','YYYY-MM-DD"T"HH24:MI:SS.US"Z"'))::text,
        'UTF8')), 'hex');
    RETURN NEW;
END $$
SQL
            );
            $s->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION mobile_pool_files_audit() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    actor_name text := NULLIF(current_setting('saldi.actor',true),'');
    request_uuid text := NULLIF(current_setting('saldi.request_id',true),'');
    diff jsonb := '{}'::jsonb;
    field text;
    old_row jsonb;
    new_row jsonb := to_jsonb(NEW);
    lifecycle text;
BEGIN
    IF TG_OP='INSERT' THEN
        IF NEW.status <> 'staged' THEN
            INSERT INTO pool_file_audit(pool_file_id,event,actor,request_id)
                VALUES (NEW.id,'uploaded',COALESCE(actor_name,NULLIF(NEW.uploaded_by,''),'web'),request_uuid);
        END IF;
        RETURN NEW;
    END IF;
    old_row := to_jsonb(OLD);
    FOREACH field IN ARRAY ARRAY['subject','account','amount','file_date','invoice_number',
        'description','currency','vat_code','vendor_name','vendor_cvr','vendor_iban',
        'vendor_konto_id','vendor_match','vendor_score'] LOOP
        IF old_row->field IS DISTINCT FROM new_row->field THEN
            diff := diff || jsonb_build_object(field,jsonb_build_object('from',old_row->field,'to',new_row->field));
        END IF;
    END LOOP;
    IF diff <> '{}'::jsonb THEN
        INSERT INTO pool_file_audit(pool_file_id,event,actor,changes,request_id)
            VALUES (NEW.id,'metadata_changed',COALESCE(actor_name,'web'),diff::text,request_uuid);
    END IF;
    IF NEW.status IS DISTINCT FROM OLD.status THEN
        lifecycle := CASE
            WHEN OLD.status='staged' AND NEW.status='ubehandlet' THEN 'uploaded'
            WHEN NEW.status='attached' THEN 'attached'
            WHEN NEW.status='deleted' THEN 'deleted'
            WHEN OLD.status IN ('attached','deleted') AND NEW.status='ubehandlet' THEN 'returned'
            ELSE NULL END;
        IF lifecycle IS NOT NULL THEN
            INSERT INTO pool_file_audit(pool_file_id,event,actor,changes,request_id)
                VALUES (NEW.id,lifecycle,COALESCE(actor_name,
                    CASE WHEN lifecycle='uploaded' THEN NULLIF(NEW.uploaded_by,'') END,'web'),
                    jsonb_build_object('status',jsonb_build_object('from',OLD.status,'to',NEW.status))::text,request_uuid);
        END IF;
    END IF;
    RETURN NEW;
END $$
SQL
            );
            foreach ([
                ['pool_file_audit','mobile_audit_chain','BEFORE INSERT','ROW','mobile_audit_chain'],
                ['pool_file_audit','mobile_audit_immutable','BEFORE UPDATE OR DELETE','ROW','mobile_append_only'],
                ['pool_file_audit','mobile_audit_no_truncate','BEFORE TRUNCATE','STATEMENT','mobile_append_only'],
                ['pool_files','mobile_pool_files_audit','AFTER INSERT OR UPDATE','ROW','mobile_pool_files_audit'],
            ] as [$table, $name, $event, $scope, $function]) {
                if (!$s->rows("SELECT 1 FROM pg_trigger WHERE tgrelid='$table'::regclass AND tgname='$name' AND NOT tgisinternal")) {
                    $s->execute("CREATE TRIGGER $name $event ON $table FOR EACH $scope EXECUTE FUNCTION $function()");
                }
            }
        },
        'm3_03_bank_lines' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS bank_line_imports (
                id serial PRIMARY KEY, source varchar(10) NOT NULL CHECK (source IN ('csv','aiia')),
                account_kontonr integer NOT NULL, kladde_id integer NOT NULL, file_sha256 char(64),
                line_count integer NOT NULL DEFAULT 0, imported_by varchar(255) NOT NULL,
                imported_at timestamptz NOT NULL DEFAULT now())");
            $s->execute("CREATE TABLE IF NOT EXISTS bank_lines (
                id serial PRIMARY KEY, account_kontonr integer NOT NULL, line_key char(64) NOT NULL,
                source varchar(10) NOT NULL, provider_ref text, transdate date NOT NULL,
                amount numeric(15,2) NOT NULL, currency char(3) NOT NULL, text text NOT NULL,
                balance numeric(15,2), first_import_id integer NOT NULL REFERENCES bank_line_imports(id),
                state varchar(10) NOT NULL DEFAULT 'open' CHECK (state IN ('open','requested','received','ignored')),
                pool_file_id integer REFERENCES pool_files(id), state_changed_at timestamptz,
                state_changed_by varchar(255), ignore_note text, created_at timestamptz NOT NULL DEFAULT now(),
                UNIQUE(account_kontonr,line_key))");
            $s->execute("CREATE TABLE IF NOT EXISTS bank_line_journal_links (
                bank_line_id integer NOT NULL REFERENCES bank_lines(id), kassekladde_id integer NOT NULL,
                import_id integer NOT NULL REFERENCES bank_line_imports(id),
                created_at timestamptz NOT NULL DEFAULT now(), PRIMARY KEY(bank_line_id,kassekladde_id))");
            $s->column('reminders', 'bank_line_id', 'integer REFERENCES bank_lines(id)');
            $s->column('pool_files', 'bank_line_id', 'integer REFERENCES bank_lines(id)');
            $s->index('bank_lines', 'mobile_bank_state_date', 'state, transdate');
            $s->index('bank_lines', 'mobile_bank_pool', 'pool_file_id');
            $s->index('bank_line_journal_links', 'mobile_bank_journal', 'kassekladde_id');
            $s->index('reminders', 'mobile_reminder_bank', 'bank_line_id');
        },
        'm3_04_classification' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS classification_suggestions (
                id serial PRIMARY KEY, pool_file_id integer REFERENCES pool_files(id), vendor_key varchar(120),
                inputs_sha256 char(64) NOT NULL, kontonr integer, vat_code varchar(10), kreditor_kontonr varchar(20),
                basis varchar(20) NOT NULL CHECK (basis IN ('last_correction','supplier_history','ocr','common_accounts','none')),
                evidence_count integer NOT NULL DEFAULT 0, evidence_total integer NOT NULL DEFAULT 0,
                engine_version varchar(20) NOT NULL, requested_by varchar(255) NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(pool_file_id,engine_version,inputs_sha256))");
            $s->execute("CREATE TABLE IF NOT EXISTS classification_feedback (
                id serial PRIMARY KEY, pool_file_id integer REFERENCES pool_files(id),
                suggestion_id integer REFERENCES classification_suggestions(id), vendor_key varchar(120),
                role varchar(10) NOT NULL CHECK (role IN ('uploader','reviewer')),
                outcome varchar(10) NOT NULL CHECK (outcome IN ('accepted','corrected','none')),
                kontonr integer NOT NULL, vat_code varchar(10), kreditor_kontonr varchar(20),
                actor varchar(255) NOT NULL, client_id varchar(36) UNIQUE, created_at timestamptz NOT NULL DEFAULT now())");
            $s->index('classification_feedback', 'mobile_feedback_vendor', 'vendor_key, role, created_at');
            if (!in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true)) {
                foreach (['classification_suggestions', 'classification_feedback'] as $table) {
                    foreach (['immutable' => 'UPDATE OR DELETE', 'no_truncate' => 'TRUNCATE'] as $suffix => $event) {
                        $name = 'mobile_' . $table . '_' . $suffix;
                        $scope = $event === 'TRUNCATE' ? 'STATEMENT' : 'ROW';
                        if (!$s->rows("SELECT 1 FROM pg_trigger WHERE tgrelid='$table'::regclass AND tgname='$name' AND NOT tgisinternal")) {
                            $s->execute("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH $scope EXECUTE FUNCTION mobile_append_only()");
                        }
                    }
                }
            }
        },
        'm3_05_events' => function (MobileSchema $s): void {
            $identity = in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true) ? 'bigint AUTO_INCREMENT' : 'bigserial';
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_events (
                id $identity PRIMARY KEY, type varchar(30) NOT NULL, dedupe_key varchar(160) NOT NULL UNIQUE,
                ref_type varchar(20), ref_id integer, target_subject varchar(255), payload text NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(), fanned_out_at timestamptz,
                notification_id integer REFERENCES notifications(id) ON DELETE SET NULL,
                attempts integer NOT NULL DEFAULT 0, last_error varchar(60))");
            $s->execute("CREATE TABLE IF NOT EXISTS notification_preferences (
                subject varchar(255) NOT NULL, type varchar(30) NOT NULL, enabled boolean NOT NULL,
                updated_at timestamptz NOT NULL DEFAULT now(), PRIMARY KEY(subject,type))");
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_scanner_state (
                scanner varchar(30) PRIMARY KEY, seeded_at timestamptz, last_run_at timestamptz, cursor text)");
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_low_stock_state (
                vare_id integer PRIMARY KEY, below boolean NOT NULL, crossings integer NOT NULL DEFAULT 0,
                changed_at timestamptz NOT NULL DEFAULT now())");
            $s->index('mobile_events', 'mobile_event_age', 'created_at');
            if (!in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true)) {
                $s->execute('CREATE INDEX IF NOT EXISTS mobile_events_pending ON mobile_events(id) WHERE fanned_out_at IS NULL');
            }
        },
        'm3_06_journal_provenance' => function (MobileSchema $s): void {
            $identity = in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true) ? 'bigint AUTO_INCREMENT' : 'bigserial';
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_journal_lines (
                id $identity PRIMARY KEY, kassekladde_id integer NOT NULL, kladde_id integer NOT NULL,
                pool_file_id integer NOT NULL REFERENCES pool_files(id), bank_line_id integer REFERENCES bank_lines(id),
                mode varchar(12) NOT NULL CHECK (mode IN ('new_line','bank_row','attach_only')),
                currency char(3) NOT NULL, rate numeric(15,6), rate_date date, rate_source varchar(20),
                created_by varchar(255) NOT NULL, client_id varchar(36) NOT NULL UNIQUE,
                created_at timestamptz NOT NULL DEFAULT now())");
            $s->index('mobile_journal_lines', 'mobile_journal_line', 'kassekladde_id');
            $s->index('mobile_journal_lines', 'mobile_journal_pool', 'pool_file_id');
            $s->index('mobile_journal_lines', 'mobile_journal_header', 'kladde_id');
        },
        'm3_07_kassekladde_vat' => function (MobileSchema $s): void {
            $definition = in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true) ? 'varchar(10)' : 'varchar';
            foreach (['kassekladde', 'tmpkassekl'] as $table) {
                foreach (['debetvat', 'kreditvat'] as $column) {
                    $s->column($table, $column, $definition);
                }
            }
        },
        'm3_08_pool_file_moves' => function (MobileSchema $s): void {
            $identity = in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true) ? 'bigint AUTO_INCREMENT' : 'bigserial';
            $s->execute("CREATE TABLE IF NOT EXISTS pool_file_moves (
                id $identity PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE,
                pool_file_id integer NOT NULL REFERENCES pool_files(id), source_path text NOT NULL,
                staging_path text NOT NULL, dest_path text, sha256 char(64) NOT NULL,
                state varchar(10) NOT NULL CHECK (state IN ('reserved','committed','done','aborted','lost')),
                last_error varchar(60), created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(), completed_at timestamptz)");
            if (!in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true)) {
                $s->execute("CREATE UNIQUE INDEX IF NOT EXISTS mobile_pool_move_open ON pool_file_moves(pool_file_id) WHERE state IN ('reserved','committed')");
                $s->execute("CREATE INDEX IF NOT EXISTS mobile_pool_move_due ON pool_file_moves(created_at) WHERE state IN ('reserved','committed')");
            }
        },
        'm3_09_mobile_journal_identity' => function (MobileSchema $s): void {
            $s->column('kladdeliste', 'mobile_approval_journal', 'boolean NOT NULL DEFAULT false');
        },
        'm4_01_stock_counts' => function (MobileSchema $s): void {
            $mysql = in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true);
            $identity = $mysql ? 'bigint AUTO_INCREMENT' : 'bigserial';
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_stock_counts (
                id serial PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE,
                session_id varchar(36) NOT NULL UNIQUE, subject varchar(255) NOT NULL, ansat_id integer,
                lager integer NOT NULL, reason_code varchar(20) NOT NULL
                    CHECK (reason_code IN ('optaelling','svind','brud','fejlregistrering')),
                note text, started_at timestamptz, submitted_at timestamptz NOT NULL DEFAULT now(),
                line_count integer NOT NULL, adjusted_count integer NOT NULL, value_estimate numeric(15,2) NOT NULL,
                shop_sync varchar(16) NOT NULL DEFAULT 'pending'
                    CHECK (shop_sync IN ('pending','done','partial','not_configured')))");
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_stock_count_lines (
                id $identity PRIMARY KEY, count_id integer NOT NULL REFERENCES mobile_stock_counts(id),
                line_ref varchar(36) NOT NULL, vare_id integer NOT NULL, lager integer NOT NULL,
                counted_qty numeric(15,3) NOT NULL, expected_qty numeric(15,3), expected_token char(64),
                counted_at timestamptz, resolution varchar(4) NOT NULL DEFAULT 'none' CHECK (resolution IN ('none','set')),
                before_qty numeric(15,3) NOT NULL, after_qty numeric(15,3) NOT NULL, delta numeric(15,3) NOT NULL,
                kostpris numeric(15,3) NOT NULL, stocklog_id integer,
                outcome varchar(10) NOT NULL CHECK (outcome IN ('adjusted','unchanged')),
                UNIQUE(count_id,vare_id,lager))");
            $s->index('mobile_stock_counts', 'mobile_stock_count_subject', 'subject, submitted_at');
            $s->index('mobile_stock_counts', 'mobile_stock_count_lager', 'lager, submitted_at');
            $s->index('mobile_stock_count_lines', 'mobile_stock_count_item', 'vare_id');
            if (!$mysql) {
                $s->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION mobile_stock_count_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF (to_jsonb(NEW) - 'shop_sync') IS DISTINCT FROM (to_jsonb(OLD) - 'shop_sync') THEN
        RAISE EXCEPTION 'Stock counts are immutable except shop_sync' USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END $$
SQL
                );
                mobileT4AppendOnlyTriggers($s, 'mobile_stock_count_lines');
                foreach ([
                    ['mobile_stock_count_guard','UPDATE','ROW','mobile_stock_count_guard'],
                    ['mobile_stock_count_no_delete','DELETE','ROW','mobile_append_only'],
                    ['mobile_stock_count_no_truncate','TRUNCATE','STATEMENT','mobile_append_only'],
                ] as [$name, $event, $scope, $function]) {
                    if (!$s->rows("SELECT 1 FROM pg_trigger WHERE tgrelid='mobile_stock_counts'::regclass AND tgname='$name' AND NOT tgisinternal")) {
                        $s->execute("CREATE TRIGGER $name BEFORE $event ON mobile_stock_counts FOR EACH $scope EXECUTE FUNCTION $function()");
                    }
                }
            }
        },
        'm4_02_goods_receipts' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_goods_receipts (
                id serial PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE, ordre_id integer NOT NULL,
                subject varchar(255) NOT NULL, ansat_id integer, receipt_date date NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(), attachment_pool_file_id integer REFERENCES pool_files(id),
                attachment_state varchar(8) NOT NULL DEFAULT 'none' CHECK (attachment_state IN ('none','pending','attached','failed')),
                attachment_error varchar(40))");
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_goods_receipt_lines (
                receipt_id integer NOT NULL REFERENCES mobile_goods_receipts(id), ordrelinje_id integer NOT NULL,
                vare_id integer, variant_id integer NOT NULL DEFAULT 0, lager integer NOT NULL,
                quantity numeric(15,3) NOT NULL CHECK (quantity > 0), batch_no varchar(100), due_date date,
                PRIMARY KEY(receipt_id,ordrelinje_id))");
            $s->index('mobile_goods_receipts', 'mobile_receipt_order', 'ordre_id, created_at');
            mobileT4AppendOnlyTriggers($s, 'mobile_goods_receipt_lines');
        },
        'm4_03_time_entries' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_time_entries (
                loen_id integer PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE,
                subject varchar(255) NOT NULL, ansat_id integer NOT NULL,
                timer_started_at timestamptz, timer_stopped_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(), submitted_at timestamptz, deleted_at timestamptz)");
            $s->index('mobile_time_entries', 'mobile_time_employee', 'ansat_id, created_at');
        },
        'm4_04_bookings' => function (MobileSchema $s): void {
            $identity = in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true) ? 'bigint AUTO_INCREMENT' : 'bigserial';
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_bookings (
                id serial PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE, booking_date date NOT NULL,
                start_time time NOT NULL, duration_minutes integer NOT NULL CHECK (duration_minutes BETWEEN 5 AND 720),
                ansat_id integer NOT NULL, konto_id integer, customer_name text NOT NULL, customer_address text,
                customer_phone varchar(40), service text NOT NULL, sag_id integer, note text,
                status varchar(10) NOT NULL DEFAULT 'planlagt' CHECK (status IN ('planlagt','udfoert','aflyst')),
                version integer NOT NULL DEFAULT 1, created_by varchar(255) NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(), updated_by varchar(255), updated_at timestamptz, done_at timestamptz)");
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_booking_history (
                id $identity PRIMARY KEY, booking_id integer NOT NULL REFERENCES mobile_bookings(id),
                event varchar(10) NOT NULL CHECK (event IN ('created','moved','edited','status')),
                actor varchar(255) NOT NULL, before_json text, after_json text NOT NULL,
                at timestamptz NOT NULL DEFAULT now())");
            $s->index('mobile_bookings', 'mobile_booking_day_employee', 'booking_date, ansat_id');
            $s->index('mobile_bookings', 'mobile_booking_employee_day', 'ansat_id, booking_date');
            mobileT4AppendOnlyTriggers($s, 'mobile_booking_history');
        },
        'm4_05_employee_claims' => function (MobileSchema $s): void {
            $mysql = in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true);
            $pdfType = $mysql ? 'longblob' : 'bytea';
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_mileage_statements (
                id serial PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE, subject varchar(255) NOT NULL,
                ansat_id integer, period_from date NOT NULL, period_to date NOT NULL,
                km_total numeric(10,1) NOT NULL, amount numeric(15,2) NOT NULL,
                rates_json text NOT NULL, lines_json text NOT NULL, pdf $pdfType, pdf_sha256 char(64),
                pool_file_id integer REFERENCES pool_files(id), state varchar(10) NOT NULL CHECK (state IN ('generating','sent','withdrawn')),
                created_at timestamptz NOT NULL DEFAULT now(), sent_at timestamptz, withdrawn_at timestamptz)");
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_mileage_allocations (
                statement_id integer NOT NULL REFERENCES mobile_mileage_statements(id), scope varchar(255) NOT NULL,
                year integer NOT NULL, km_high numeric(10,1) NOT NULL, km_low numeric(10,1) NOT NULL,
                PRIMARY KEY(statement_id,year))");
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_mileage_trips (
                id serial PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE, subject varchar(255) NOT NULL,
                ansat_id integer, trip_date date NOT NULL, from_text varchar(200) NOT NULL, to_text varchar(200) NOT NULL,
                km numeric(8,1) NOT NULL CHECK (km > 0 AND km <= 2000), purpose varchar(200) NOT NULL,
                statement_id integer REFERENCES mobile_mileage_statements(id), version integer NOT NULL DEFAULT 1,
                created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz, deleted_at timestamptz)");
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_expense_claims (
                id serial PRIMARY KEY, client_id varchar(36) NOT NULL UNIQUE,
                pool_file_id integer NOT NULL UNIQUE REFERENCES pool_files(id),
                kind varchar(8) NOT NULL CHECK (kind IN ('udlaeg','koersel')), subject varchar(255) NOT NULL,
                ansat_id integer, statement_id integer REFERENCES mobile_mileage_statements(id),
                created_at timestamptz NOT NULL DEFAULT now())");
            $s->index('mobile_mileage_statements', 'mobile_statement_subject_period', 'subject, period_from');
            $s->index('mobile_mileage_allocations', 'mobile_mileage_allowance_scope', 'scope, year');
            $s->index('mobile_mileage_trips', 'mobile_trip_statement', 'statement_id');
            $s->index('mobile_expense_claims', 'mobile_claim_subject_created', 'subject, created_at');
            if ($mysql) {
                $s->index('mobile_mileage_trips', 'mobile_trip_subject_date', 'subject, trip_date');
            } else {
                $s->execute('CREATE INDEX IF NOT EXISTS mobile_trip_subject_date ON mobile_mileage_trips(subject,trip_date) WHERE deleted_at IS NULL');
                mobileT4AppendOnlyTriggers($s, 'mobile_mileage_allocations');
                $s->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION mobile_generated_document_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF (NEW.amount IS DISTINCT FROM OLD.amount OR NEW.file_date IS DISTINCT FROM OLD.file_date
        OR NEW.currency IS DISTINCT FROM OLD.currency OR NEW.description IS DISTINCT FROM OLD.description
        OR NEW.invoice_number IS DISTINCT FROM OLD.invoice_number)
        AND EXISTS (SELECT 1 FROM mobile_expense_claims WHERE pool_file_id=OLD.id AND kind='koersel') THEN
        RAISE EXCEPTION 'generated_document' USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END $$
SQL
                );
                if (!$s->rows("SELECT 1 FROM pg_trigger WHERE tgrelid='pool_files'::regclass AND tgname='mobile_generated_document_guard' AND NOT tgisinternal")) {
                    $s->execute('CREATE TRIGGER mobile_generated_document_guard BEFORE UPDATE ON pool_files FOR EACH ROW EXECUTE FUNCTION mobile_generated_document_guard()');
                }
            }
        },
        'm4_06_fefo_columns' => function (MobileSchema $s): void {
            foreach ([
                'batch_kob' => ['due_date' => 'DATE NULL', 'batch_no' => 'VARCHAR(100) NULL'],
                'varer' => ['has_due_date' => 'BOOLEAN DEFAULT FALSE', 'default_shelf_life_days' => 'INTEGER NULL'],
                'ordrelinjer' => ['batch_due_date' => 'DATE NULL', 'batch_batch_no' => 'VARCHAR(100) NULL'],
            ] as $table => $columns) {
                foreach ($columns as $name => $definition) {
                    $s->column($table, $name, $definition);
                }
            }
        },
    ];
}

/** PostgreSQL immutability on new T4 tables; identifiers are migration constants only. */
function mobileT4AppendOnlyTriggers(MobileSchema $schema, string $table): void
{
    if (in_array(ifset($GLOBALS, 'db_type'), ['mysql', 'mysqli'], true)) {
        return;
    }
    foreach (['immutable' => 'UPDATE OR DELETE', 'no_truncate' => 'TRUNCATE'] as $suffix => $event) {
        $name = $table . '_' . $suffix;
        $scope = $event === 'TRUNCATE' ? 'STATEMENT' : 'ROW';
        if (!$schema->rows("SELECT 1 FROM pg_trigger WHERE tgrelid='$table'::regclass AND tgname='$name' AND NOT tgisinternal")) {
            $schema->execute("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH $scope EXECUTE FUNCTION mobile_append_only()");
        }
    }
}

/** Web login retries pending steps without interrupting the legacy login path. */
function mobileTenantUpdatesAtLogin($connection = null): void
{
    try {
        mobileTenantUpdates($connection);
    } catch (Throwable $exception) {
        error_log(MobileSchema::failedStep() ?? 'mobile_tenant_step');
    }
}

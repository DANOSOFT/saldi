#!/usr/bin/env python3
"""Exercise the real PHP endpoint in disposable PostgreSQL databases and a PHP container.

Uses the existing local SALDI Docker image/network. Never bootstraps or resets an
existing company. Every name and credential is generated and removed in finally.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys
import tempfile
import time
from types import SimpleNamespace
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


def command(args, data=None):
    result = subprocess.run(args, input=data, text=True, capture_output=True)
    if result.returncode:
        # SQL/env failures can echo credentials. Do not print subprocess streams.
        raise RuntimeError(f"{args[0]} failed (exit {result.returncode})")
    return result.stdout.strip()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--postgres", default="saldi-postgres-1")
    parser.add_argument("--image", default="saldi-web")
    parser.add_argument("--network", default="saldi_testnet")
    parser.add_argument("--chaty-source", type=Path)
    args = parser.parse_args()
    source = Path(__file__).resolve().parents[2]
    suffix = secrets.token_hex(5)
    master, tenant, reader = [f"assist_test_{suffix}_{part}" for part in ("master", "tenant", "reader")]
    password, secret, sid = secrets.token_hex(32), secrets.token_hex(32), secrets.token_hex(16)
    databases, role_created, container = [], False, None

    def sql(database, text):
        return command(["docker", "exec", "-i", args.postgres, "sh", "-c",
            'PGPASSWORD="$POSTGRES_PASSWORD" exec psql -X -v ON_ERROR_STOP=1 -At -U "$POSTGRES_USER" -d "$1"', "sh", database], text)

    def snapshot(database, tables):
        return [sql(database, f"SELECT md5(COALESCE(string_agg(row_to_json(t)::text, '' ORDER BY row_to_json(t)::text), '')) FROM {table} t;") for table in tables]

    def expect(value, label):
        if not value:
            raise AssertionError(label)

    try:
        sql("postgres", f"CREATE ROLE {reader} LOGIN PASSWORD '{password}';")
        role_created = True
        for database in [master, tenant]:
            sql("postgres", f"CREATE DATABASE {database};")
            databases.append(database)
        sql(master, f"""
            CREATE TABLE online (session_id text, db text, brugernavn text, regnskabsaar integer, logtime text);
            CREATE TABLE regnskab (db text, lukket text, lukkes date);
            CREATE TABLE settings (var_name text, var_value text);
            INSERT INTO online VALUES ('{sid}', '{tenant}', 'assist_user', 1, '1');
            INSERT INTO regnskab VALUES ('{tenant}', '', '2099-12-31');
            INSERT INTO settings VALUES ('timezone', 'Europe/Copenhagen');
        """)
        sql(tenant, """
            CREATE TABLE brugere (brugernavn text, rettigheder text);
            INSERT INTO brugere VALUES ('assist_user', '111111');
            CREATE TABLE kladdeliste (id integer PRIMARY KEY, kladdenote text, bogfort text, kladdedate date);
            INSERT INTO kladdeliste VALUES (7, 'Kontrolkladden', '-', '2026-09-01');
            CREATE TABLE kassekladde (id integer PRIMARY KEY, kladde_id integer, bilag integer, transdate date, beskrivelse text,
                d_type text, debet numeric(15,0), k_type text, kredit numeric(15,0), amount numeric(15,3), momsfri text,
                debetvat text, kreditvat text, valuta integer, afd integer, projekt text, ansat text);
            INSERT INTO kassekladde VALUES
                (10, 7, 1, '2026-09-01', 'Debet', 'F', 1000, 'F', 0, 100.005, '', '', '', 0, 0, '', ''),
                (11, 7, 2, '2026-09-01', 'Kredit', 'F', 0, 'F', 2000, 100.005, '', '', '', 0, 0, '', '');
            CREATE TABLE tmpkassekl (kladde_id integer);
            CREATE TABLE kontoplan (id integer, kontonr numeric(15,0), regnskabsaar integer, kontotype text, lukket text, moms text);
            INSERT INTO kontoplan VALUES (1,1000,1,'D','',''), (2,2000,1,'S','','');
            CREATE TABLE grupper (id integer, art text, kodenr integer, kode text, box1 text, box2 text, box3 text, box4 text, box5 text, fiscal_year integer);
            INSERT INTO grupper VALUES (1,'RA',1,'','1','2026','12','2026','',NULL), (2,'DIV',3,'','','','','on','',NULL);
            CREATE TABLE adresser (id integer, kontonr text, art text, gruppe integer);
            CREATE TABLE valuta (id integer, gruppe integer, valdate date, kurs numeric(15,3));
            CREATE TABLE moms_periode_luk (kalender_aar integer, kalender_maaned integer, status text);
            CREATE TABLE ordrer (id integer PRIMARY KEY, art text, status integer, kontonr text, ordrenr integer, fakturanr text, fakturadate date,
                sum numeric(15,3), moms numeric(15,3), valuta text, betalt text, felt_1 text, ref text, afd integer);
            INSERT INTO ordrer VALUES
                (12,'DO',3,'100',12,'10012','2026-09-01',100,25,'DKK','0','Konto','',0),
                (13,'DO',0,'',13,'',NULL,100,25,'DKK','0','Konto','',0),
                (14,'DO',2,'100',14,'',NULL,100,25,'DKK','0','Kort','',0);
            CREATE TABLE ordrelinjer (id integer, ordre_id integer, antal numeric, leveret numeric, leveres numeric, vare_id integer, samlevare text, folgevare integer);
            INSERT INTO ordrelinjer VALUES (21,12,1,1,0,1,'',0), (22,13,1,0,1,1,'',0), (23,14,1,1,0,1,'',0);
            CREATE TABLE settings (id integer, var_name text, var_grp text, var_value text, pos_id integer);
            INSERT INTO settings VALUES (1,'lockedInvoiceButton','debitor','on',0), (2,'showPaymentLink','deb_ordre','on',0);
        """)
        for database in databases:
            sql(database, f"GRANT CONNECT ON DATABASE {database} TO {reader}; GRANT USAGE ON SCHEMA public TO {reader}; GRANT SELECT ON ALL TABLES IN SCHEMA public TO {reader};")
        with tempfile.TemporaryDirectory(prefix="saldi-assist-test-") as temporary:
            env_path = Path(temporary) / "reader.env"
            env_path.write_text("\n".join(f"{key}={value}" for key, value in {
                "SALDI_ASSIST_RECORDS_ENABLED": "1", "SALDI_ASSIST_KID": "integration",
                "SALDI_ASSIST_CONTEXT_SECRET": secret, "SALDI_ASSIST_DB_HOST": args.postgres,
                "SALDI_ASSIST_DB_USER": reader, "SALDI_ASSIST_DB_PASSWORD": password,
                "SALDI_ASSIST_DB_MASTER": master,
            }.items()) + "\n")
            os.chmod(env_path, 0o600)
            container = command(["docker", "run", "-d", "--rm", "--network", args.network, "--env-file", str(env_path),
                "-v", f"{source}:/work:ro", "-p", "127.0.0.1::8080", "--entrypoint", "php", args.image,
                "-d", "display_errors=0", "-S", "0.0.0.0:8080", "-t", "/work"])
        # saldi_testnet is internal; an ordinary bridge is required to publish the loopback port.
        command(["docker", "network", "connect", "bridge", container])
        port = command(["docker", "port", container, "8080/tcp"]).split(":")[-1]
        origin = f"http://127.0.0.1:{port}"
        url = origin + "/includes/saldi_assist_record.php"
        command(["docker", "exec", container, "php", "-r", 'session_id($argv[1]); session_start(); $_SESSION["assist_test"] = true; session_write_close();', sid])

        def call(body, token=None, cookie=True, request_origin=origin):
            headers = {"Content-Type": "application/json", "Origin": request_origin}
            if cookie:
                headers["Cookie"] = f"PHPSESSID={sid}"
            if token:
                headers["Authorization"] = f"Bearer {token}"
            request = Request(url, data=json.dumps(body).encode(), headers=headers)
            for attempt in range(30):
                try:
                    with urlopen(request, timeout=8) as response:
                        return response.status, json.load(response)
                except HTTPError as error:
                    return error.code, json.load(error)
                except URLError:
                    if attempt == 29:
                        raise
                    time.sleep(0.1)

        def grant(kind="journal", record_id=7):
            status, data = call({"operation": "grant", "kind": kind, "record_id": record_id, "embed_session": "a" * 32})
            expect(status == 200, f"grant failed: {status} {data}")
            return data

        tenant_tables = ["brugere", "kladdeliste", "kassekladde", "tmpkassekl", "kontoplan", "grupper", "adresser", "valuta", "moms_periode_luk", "ordrer", "ordrelinjer", "settings"]
        before = snapshot(tenant, tenant_tables), snapshot(master, ["online", "regnskab", "settings"])
        expect(call({"operation": "grant", "kind": "journal", "record_id": 7, "embed_session": "a" * 32}, cookie=False)[0] == 401, "anonymous grant denied")
        expect(call({"operation": "grant"}, request_origin="https://elsewhere.example")[0] == 403, "cross-origin grant denied")
        journal = grant()
        status, validation = call({"operation": "validate_journal", "revision": journal["revision"]}, journal["grant"], cookie=False)
        expect(status == 200 and len(validation["differences"]) == 2, "opposite voucher differences")
        expect(validation["differences"][0]["row_ids"] == [10], "stable row IDs")
        status, context = call({"operation": "get_journal_context", "revision": journal["revision"], "row_ids": [11, 999]}, journal["grant"], cookie=False)
        expect(status == 200 and [row["row_id"] for row in context["rows"]] == [11], "row scope enforced")
        expect(call({"operation": "get_invoice_status"}, journal["grant"])[0] == 403, "operation scope enforced")
        for record_id, reason in [(12, "already_invoiced"), (13, "customer_missing")]:
            invoice = grant("invoice", record_id)
            status, data = call({"operation": "get_invoice_status", "revision": invoice["revision"]}, invoice["grant"], cookie=False)
            expect(status == 200 and data["actions"][0]["enabled"] is False and data["issues"][0]["code"] == reason, "invoice action reason")
        pending = grant("invoice", 14)
        _, data = call({"operation": "get_invoice_status"}, pending["grant"])
        expect(data["payment_gate_requires_form_check"] and data["actions"][0]["enabled"] is None, "form-dependent payment gate stays unknown")
        expect(before == (snapshot(tenant, tenant_tables), snapshot(master, ["online", "regnskab", "settings"])), "all reads leave all fixture tables identical")

        if args.chaty_source:
            sys.path.insert(0, str(args.chaty_source.resolve()))
            from backend.app.config import settings
            from backend.app.record_tools import RecordContext, RecordToolSession
            settings.saldi_records_enabled = True
            settings.saldi_record_api_url = url
            settings.saldi_record_allow_http = True
            settings.context_token_keys_raw = json.dumps({"integration": secret})
            resolved = SimpleNamespace(access=True, screen_id="finans/kassekladde.php", claims=SimpleNamespace(
                tenant_hash=hashlib.sha256(f"saldi-tenant:{tenant}".encode()).hexdigest()[:32],
                user_hash=hashlib.sha256(f"saldi-user:{tenant}:assist_user".encode()).hexdigest()[:32], embed_session="a" * 32))
            tools = RecordToolSession.create(RecordContext(kind="journal", recordId=7, revision=journal["revision"]), journal["grant"], resolved)
            expect(tools is not None and tools.execute("validate_journal", {})["status"] == "blocked", "real Python to PHP transport and signature interoperability")

        sql(tenant, "INSERT INTO moms_periode_luk VALUES (2026, 9, 'closed'); UPDATE ordrer SET fakturadate = '2026-09-01' WHERE id = 14;")
        period_grant = grant("invoice", 14)
        _, period_result = call({"operation": "get_invoice_status"}, period_grant["grant"])
        expect(period_result["issues"][0]["code"] == "period_closed", "closed invoice period")
        expect(call({"operation": "validate_journal", "revision": journal["revision"]}, journal["grant"])[0] == 409, "reference-setting change invalidates revision")
        sql(tenant, "DELETE FROM moms_periode_luk; INSERT INTO tmpkassekl VALUES (7);")
        draft_grant = grant()
        expect(draft_grant["hasSavedDraft"], "temporary draft is disclosed")
        sql(tenant, "DELETE FROM tmpkassekl;")
        sql(tenant, "INSERT INTO kladdeliste VALUES (9, '', '-', '2026-09-01'); INSERT INTO kassekladde (id, kladde_id, amount) SELECT id, 9, 0 FROM generate_series(1000, 3000) id;")
        expect(call({"operation": "grant", "kind": "journal", "record_id": 9, "embed_session": "a" * 32})[0] == 413, "oversized journal is refused in full")

        sql(tenant, "UPDATE kassekladde SET amount = amount + 0.001 WHERE id = 10;")
        expect(call({"operation": "validate_journal", "revision": journal["revision"]}, journal["grant"])[0] == 409, "stale revision refused")
        fresh = grant()
        expect(fresh["revision"] != journal["revision"], "revision changes with saved content")
        sql(tenant, "UPDATE brugere SET rettigheder = '000000';")
        expect(call({"operation": "validate_journal"}, fresh["grant"])[0] == 403, "live rights revocation")
        sql(tenant, "UPDATE brugere SET rettigheder = '111111';")
        sql(master, "UPDATE online SET db = 'company_changed';")
        expect(call({"operation": "validate_journal"}, fresh["grant"])[0] == 403, "company switch invalidates grant")
        sql(master, f"UPDATE online SET db = '{tenant}'; DELETE FROM online;")
        expect(call({"operation": "validate_journal"}, fresh["grant"])[0] == 401, "logout invalidates grant")
        denied = command(["docker", "exec", container, "php", "-r",
            'require "/work/includes/assist/RecordAuth.php"; $p=saldi_assist_read_connection($argv[1]); try { $p->exec("DELETE FROM kassekladde"); exit(1); } catch (PDOException $e) { echo $e->getCode(); }', tenant])
        expect(denied in ("25006", "42501"), "database enforces read-only access")
        adapter = ", Python adapter" if args.chaty_source else ""
        print(f"PASS: PHP endpoints{adapter}, scoped grants, revisions, invoice reasons, unchanged snapshots and database read-only enforcement")
    finally:
        if container:
            command(["docker", "rm", "-f", container])
        for database in reversed(databases):
            sql("postgres", f"DROP DATABASE {database};")
        if role_created:
            sql("postgres", f"DROP ROLE {reader};")


if __name__ == "__main__":
    main()

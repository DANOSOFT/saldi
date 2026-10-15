<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiRuntimeTestEnv.php --- ai/mcp-assistant --- 2026.10.01
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
//
// Disposable plan_tpl clones and isolated registry/session cleanup for DB tests.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../../../includes/automation/SaldiInboxService.php';
require_once __DIR__ . '/../../TestCredentials.php';

final class SaldiRuntimeTestEnv
{
    public SaldiDb $master;
    public SaldiDb $db;
    public array $regnskab;
    public string $name;
    private array $sessions = [];
    private array $previousEnv = [];

    public function __construct(bool $schema = true)
    {
        if (getenv('SALDI_CHAR_PGPASS') === false) {
            throw new RuntimeException('SALDI_CHAR_PGPASS is unset');
        }
        foreach (['TYPE' => 'postgresql', 'HOST' => getenv('SALDI_CHAR_PGHOST'), 'USER' => getenv('SALDI_CHAR_PGUSER'), 'PASSWORD' => getenv('SALDI_CHAR_PGPASS'), 'MASTER' => getenv('SALDI_CHAR_MASTER_DB'), 'PORT' => getenv('SALDI_CHAR_PGPORT') ?: '5432'] as $suffix => $value) {
            $key = 'SALDI_RUNTIME_DB_' . $suffix;
            $this->previousEnv[$key] = getenv($key);
            putenv($key . '=' . $value);
        }
        saldi_runtime_reset();
        $cfg = saldi_runtime_config();
        // CREATE/DROP DATABASE can exceed the normal query budget on a busy dev volume.
        $this->master = SaldiDb::connect($cfg, $cfg['master_db'], 30000);
        $this->name = 'plan_mcp_t_' . getmypid() . '_' . bin2hex(random_bytes(4));
        $template = getenv('SALDI_CHAR_TEMPLATE_DB');
        if (!is_string($template) || $template !== 'plan_tpl') {
            throw new RuntimeException('Tests require SALDI_CHAR_TEMPLATE_DB=plan_tpl');
        }
        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                $this->master->exec('CREATE DATABASE "' . $this->name . '" TEMPLATE "plan_tpl"');
                break;
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'being accessed by other users') || $attempt === 9) {
                    throw $e;
                }
                usleep(50000 * ($attempt + 1));
            }
        }
        try {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                try {
                    $id = 9000 + random_int(0, 899999);
                    $this->master->insert('regnskab', ['id' => $id, 'db' => $this->name, 'regnskab' => 'Runtime tests', 'lukket' => '']);
                    $this->regnskab = ['id' => $id, 'db' => $this->name];
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23505' || $attempt === 9) {
                        throw $e;
                    }
                }
            }
            $this->db = SaldiDb::connect($cfg, $this->name);
            if ($schema) {
                saldi_runtime_ensure_schema($this->db);
            }
            $this->db->exec("DELETE FROM brugere WHERE id IN (910001, 910002)");
            foreach ([910001 => "O'Brien", 910002 => 'Runtime other'] as $userId => $username) {
                $this->db->insert('brugere', ['id' => $userId, 'brugernavn' => $username, 'kode' => password_hash(TestCredentials::password('runtime'), PASSWORD_DEFAULT), 'rettigheder' => str_repeat('1', 16), 'regnskabsaar' => 4, 'language_id' => 1]);
            }
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    public function ctx(int $userId = 910001, ?string $rights = null, ?string $principal = null, ?int $grantId = null, array $scopes = []): SaldiTenantContext
    {
        $ctx = saldi_runtime_context_for_user($this->db, $this->regnskab, $userId, $principal ?? 'user:' . $userId, $grantId, $scopes);
        return new SaldiTenantContext($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, $userId, $ctx->username, $rights ?? $ctx->rights, $ctx->fiscalYear, $ctx->languageId, $ctx->principal, $grantId, $scopes, $ctx->baseCurrency);
    }

    public function session(SaldiTenantContext $ctx, bool $revisor = false): string
    {
        $session = bin2hex(random_bytes(16));
        $this->master->exec('INSERT INTO online (session_id, brugernavn, db, logtime, revisor) VALUES (?, ?, ?, ?, CAST(? AS boolean))', [$session, $ctx->username, $this->name, (string)time(), $revisor ? 'true' : 'false']);
        $this->sessions[] = $session;
        return $session;
    }

    public function enable(): void
    {
        SaldiSettings::set($this->db, $this->ctx(), 'assistant', 'tools_enabled', 'on');
        SaldiSettings::set($this->db, $this->ctx(), 'assistant', 'external_agents', 'on');
    }

    public function close(): void
    {
        saldi_runtime_reset();
        if (isset($this->db)) {
            $this->db->close();
        }
        if (isset($this->master)) {
            foreach ($this->sessions as $session) {
                $this->master->exec('DELETE FROM online WHERE session_id = ?', [$session]);
            }
            if (isset($this->regnskab)) {
                $this->master->exec('DELETE FROM regnskab WHERE id = ?', [$this->regnskab['id']]);
            }
            $this->master->exec('DROP DATABASE IF EXISTS "' . $this->name . '"');
            $this->master->close();
        }
        foreach ($this->previousEnv as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
        saldi_runtime_reset();
    }
}

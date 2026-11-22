<?php
// 20261122 CDX/ASR Run the real approval transaction on a second PostgreSQL connection for lock tests.

namespace JournalApprovalFixture;

$db = new \PDO(getenv('SALDI_CHAR_DSN'), getenv('SALDI_CHAR_PGUSER') ?: null, getenv('SALDI_CHAR_PGPASS') ?: null);
$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$schema = $argv[1];
if (!preg_match('/^approval_[a-f0-9]+$/', $schema)) throw new \RuntimeException('Invalid test schema');
$db->exec('SET search_path TO ' . $schema);
$db->exec('SET application_name TO ' . $db->quote($schema));
$db->exec("SET lock_timeout TO '8s'");
$GLOBALS['journalApprovalDb'] = $db;
require __DIR__ . '/journal_approval_database.php';
$source = file_get_contents(dirname(__DIR__, 4) . '/finans/kassekladde_includes/journalApproval.php');
eval('namespace ' . __NAMESPACE__ . '; use RuntimeException; use Throwable; ' . substr($source, 5));
echo json_encode(kk_set_approval(1, [10], true, [10 => $argv[2]], 'concurrent reviewer'));

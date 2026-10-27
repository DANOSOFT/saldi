<?php
// tests/characterization/includes/docsIncludes/PoolPathsCharacterizationTest.test.php
//
// MB-42: pins poolDocFolderName() / poolPuljePath() in includes/docsIncludes/poolPaths.php.
//
// An installation stores its documents in owncloud, a local bilag folder or a custom documents folder,
// and every place that needs to find them detects the layout with the same owncloud -> bilag ->
// documents chain (includes/documents.php, includes/docsIncludes/insertDoc.php, includes/vis_bilag.php).
// The login-time content_sha256 backfill in betweenUpdates.php has no $docFolder to inherit, and used to
// hardcode bilag - so on the other two layouts it found no files, hashed nothing, and because it wrote
// its settings flag regardless, never tried again. These tests pin the detection itself, which is the
// part that can be exercised without an installation of each kind.
//
// History:
// 20260925 LOE MB-42: created.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/includes/docsIncludes/poolPaths.php';

final class PoolPathsCharacterizationTest extends TestCase
{
	private $root;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/pool_paths_' . bin2hex(random_bytes(6));
		mkdir($this->root, 0777, true);
	}

	protected function tearDown(): void
	{
		foreach (array('owncloud', 'bilag', 'documents') as $folder) {
			$tenant = $this->root . '/' . $folder . '/tenant_db/pulje';
			if (is_dir($tenant)) {
				rmdir($tenant);
				rmdir($this->root . '/' . $folder . '/tenant_db');
				rmdir($this->root . '/' . $folder);
			}
		}
		if (is_dir($this->root)) {
			rmdir($this->root);
		}
	}

	private function makeLayout($folders)
	{
		foreach ($folders as $folder) {
			mkdir($this->root . '/' . $folder . '/tenant_db/pulje', 0777, true);
		}
	}

	public function testOwnCloudWinsWhenItIsTheOnlyLayout(): void
	{
		$this->makeLayout(array('owncloud'));
		$this->assertSame('owncloud', poolDocFolderName($this->root));
	}

	public function testOwnCloudWinsOverTheOtherTwoLayouts(): void
	{
		// Same precedence as insertDoc.php: a site that has more than one folder keeps using owncloud.
		$this->makeLayout(array('owncloud', 'bilag', 'documents'));
		$this->assertSame('owncloud', poolDocFolderName($this->root));
	}

	public function testBilagIsUsedWhenThereIsNoOwnCloud(): void
	{
		$this->makeLayout(array('bilag', 'documents'));
		$this->assertSame('bilag', poolDocFolderName($this->root));
	}

	public function testCustomDocumentsFolderIsUsedWhenItIsTheOnlyLayout(): void
	{
		// This is the layout the old hardcoded ../bilag got wrong.
		$this->makeLayout(array('documents'));
		$this->assertSame('documents', poolDocFolderName($this->root));
	}

	public function testBilagIsTheFallbackWhenNoLayoutFolderExists(): void
	{
		// Every other place in the codebase falls back to bilag rather than failing.
		$this->assertSame('bilag', poolDocFolderName($this->root));
	}

	public function testThePuljePathFollowsTheDetectedLayout(): void
	{
		$this->makeLayout(array('owncloud'));
		$this->assertSame($this->root . '/owncloud/tenant_db/pulje', poolPuljePath('tenant_db', $this->root));

		rmdir($this->root . '/owncloud/tenant_db/pulje');
		rmdir($this->root . '/owncloud/tenant_db');
		rmdir($this->root . '/owncloud');
		$this->makeLayout(array('documents'));
		$this->assertSame($this->root . '/documents/tenant_db/pulje', poolPuljePath('tenant_db', $this->root));
	}

	public function testATrailingSlashOnTheRootDoesNotDoubleUp(): void
	{
		$this->makeLayout(array('bilag'));
		$this->assertSame($this->root . '/bilag/tenant_db/pulje', poolPuljePath('tenant_db', $this->root . '/'));
	}

	public function testTheDefaultRootIsThisInstallation(): void
	{
		// Without a root the helpers resolve against the installation the file lives in, which is what
		// the backfill relies on; whatever the layout here is, the path has to end the same way.
		$this->assertStringEndsWith('/tenant_db/pulje', poolPuljePath('tenant_db'));
		$this->assertContains(poolDocFolderName(), array('owncloud', 'bilag', 'documents'));
	}
}

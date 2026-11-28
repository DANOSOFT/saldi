<?php
// 20261110 CDX/ASR Registered capabilities appear once in the editor and CRM levels respect actor access.
// 20261114 CL/ASR Integration: the role fixture names the built-in flag is_built_in, as perm_roles() does since d7cdeb81.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class roleEditorView extends TestCase
{
	protected function setUp(): void
	{
		require_once __DIR__ . '/support/RoleEditorView.php';
		require_once __DIR__ . '/../../includes/permissions.php';
		require_once __DIR__ . '/../../systemdata/usersRolesIncludes/view.php';
	}

	/** @return DOMXPath Render the production role editor using real registry definitions. */
	private function render(string $actorCrm = 'write', bool $ownRole = false): DOMXPath
	{
		$levels = array_fill_keys(array_keys(permission_registry()), 'none');
		$levels['crm.access'] = 'read';
		$ownLevels = array_fill_keys(array_keys(permission_registry()), 'write');
		$ownLevels['crm.access'] = $actorCrm;
		$role = array('id' => 7, 'key' => '', 'navn' => 'CRM team', 'beskrivelse' => '', 'is_built_in' => false, 'levels' => $levels, 'role_revision' => 'revision');
		$vm = array('editRole' => $role, 'ownLevels' => $ownLevels, 'canRolesWrite' => true, 'canUsers' => false, 'canAudit' => false,
			'ownRoleId' => $ownRole ? 7 : 1, 'roleStatusCounts' => array(), 'roleDefaults' => array(), 'roleChanged' => false,
			'sprogId' => 1, 'roles' => array($role), 'roleLevels' => array(7 => $levels), 'roleCounts' => array(), 'roleHolders' => array(),
			'charset' => 'UTF-8', 'selfUrl' => 'usersRoles.php', 'enforceMode' => 'deny');
		$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		$t = static fn($value): string => $h(findtekst($value));
		ob_start();
		ur_view_roles($vm, $h, $t, static fn($value): string => '?' . $h($value), static fn($value): string => $h($value['navn']));
		$html = ob_get_clean();
		$doc = new DOMDocument();
		// HTML5 elements are valid in the application but unknown to libxml's HTML4 parser.
		libxml_use_internal_errors(true);
		$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
		libxml_clear_errors();
		libxml_use_internal_errors(false);
		return new DOMXPath($doc);
	}

	public function testEveryRegistryKeyAppearsOnceInTheEditableMatrix(): void
	{
		$view = $this->render();
		$rows = $view->query('//*[@data-key and @role="radiogroup"]');
		$rendered = array();
		foreach ($rows as $row) {
			$rendered[] = $row->getAttribute('data-key');
		}
		$registered = array_keys(permission_registry());
		sort($registered);
		sort($rendered);
		self::assertSame($registered, $rendered, 'A new permission group must not disappear from role saves');
		self::assertSame(1, $view->query('//*[@data-role-group="crm"]//*[@data-key="crm.access"]')->length);
	}

	public function testCrmReadIsSelectableButWriteIsLockedAboveActorsAccess(): void
	{
		$view = $this->render('read');
		self::assertSame(1, $view->query('//input[@type="radio" and @name="level[crm.access]" and @value="read" and @checked and not(@disabled)]')->length);
		self::assertSame(1, $view->query('//input[@type="radio" and @name="level[crm.access]" and @value="write" and @disabled]')->length);
	}

	public function testOwnRoleCannotReduceCrmAccessAndTheHiddenFallbackKeepsIt(): void
	{
		$view = $this->render('read', true);
		self::assertSame(1, $view->query('//input[@type="radio" and @name="level[crm.access]" and @value="none" and @disabled]')->length);
		self::assertSame(1, $view->query('//input[@type="hidden" and @name="level[crm.access]" and @value="read"]')->length);
	}
}

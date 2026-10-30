<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../finans/kassekladde_includes/shortcutProfile.php';

/**
 * The user's shortcut profile (SD-726): which gear box options are stored choices, and the profile read back from them.
 */
final class shortcutProfile extends TestCase
{
    public function testDefaultWithNothingStored(): void
    {
        $this->assertSame(['ctrl_arrow' => 'lines'], kkShortcutProfile(''));
        $this->assertSame(['ctrl_arrow' => 'lines'], kkShortcutProfile(null));
    }

    public function testStoredChoiceAmongTheOtherOptions(): void
    {
        $this->assertSame(['ctrl_arrow' => 'save'], kkShortcutProfile('vat_d,ac_forslag, sc.ctrl_arrow=save ,modk_auto'));
    }

    public function testOnlyKnownNonDefaultChoicesAreStored(): void
    {
        $this->assertTrue(kkShortcutToken('sc.ctrl_arrow=save'));
        $this->assertFalse(kkShortcutToken('sc.ctrl_arrow=lines'), 'the default is not stored');
        $this->assertFalse(kkShortcutToken('sc.ctrl_arrow=jump'));
        $this->assertFalse(kkShortcutToken('sc.ctrl_f=save'));
        $this->assertFalse(kkShortcutToken('modk_auto'));
        $this->assertFalse(kkShortcutToken("sc.ctrl_arrow=save'"));
    }

    public function testUnknownStoredValueGivesTheDefault(): void
    {
        $this->assertSame(['ctrl_arrow' => 'lines'], kkShortcutProfile('sc.ctrl_arrow=jump,sc.other=x'));
    }
}

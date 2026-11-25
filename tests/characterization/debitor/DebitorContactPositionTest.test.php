<?php
// tests/characterization/debitor/DebitorContactPositionTest.test.php
//
// Saving a contact person on the customer card (debitor/ansatte.php).
//
// The contact at position 1 is the card's primary contact (adresser.kontakt).
// Saving an existing contact keeps its position; only a new contact goes
// first (MB-138). Imported contacts have no position (NULL), and PostgreSQL
// lists NULL positions last, so the primary contact must get position 1 when
// another contact is given a position. Renaming the primary contact renames
// adresser.kontakt too, even when nobody holds position 1.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261122 CL/SZ MB-138 Created; the no-position and rename cases were raised by Lui on PR #1168.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class DebitorContactPositionTest extends CharacterizationTestCase
{
    /**
     * A customer card with the given contacts, as [name, position or null].
     *
     * @param list<array{string, int|null}> $contacts
     * @return array{id:int, contacts:array<string,int>} the card id and each contact's id by name
     */
    private function card(string $primary, array $contacts): array
    {
        $debtor = self::$fx->debtor();
        self::rows('UPDATE adresser SET kontakt = $1 WHERE id = $2', [$primary, $debtor['id']]);
        $ids = [];
        foreach ($contacts as [$name, $position]) {
            $row = self::one(
                "INSERT INTO ansatte (konto_id, navn, posnr, lukket) VALUES ($1, $2, $3, '') RETURNING id",
                [$debtor['id'], $name, $position]
            );
            $ids[$name] = (int)$row['id'];
        }
        return ['id' => (int)$debtor['id'], 'contacts' => $ids];
    }

    /** Posts the contact form the way its Gem button does. */
    private function save(int $kontoId, int $id, string $navn): void
    {
        $fields = http_build_query([
            'id' => $id, 'konto_id' => $kontoId, 'navn' => $navn,
            'addr1' => '', 'addr2' => '', 'postnr' => '', 'bynavn' => '', 'tlf' => '', 'mobile' => '',
            'mobil' => '', 'email' => '', 'notes' => '', 'returside' => '', 'fokus' => '', 'submit' => 'Gem',
        ]);
        $out = $this->runChildJson('run_ansatte_page.php', [$fields, CharacterizationEnv::sessionId()]);
        $this->assertSame([], $out['warnings']);
    }

    /** @return array<string,int|null> each contact's position by name, in the order the card lists them */
    private function positions(int $kontoId): array
    {
        $positions = [];
        foreach (self::rows('SELECT navn, posnr FROM ansatte WHERE konto_id = $1 ORDER BY posnr, id', [$kontoId]) as $row) {
            $positions[$row['navn']] = $row['posnr'] === null ? null : (int)$row['posnr'];
        }
        return $positions;
    }

    private function primary(int $kontoId): ?string
    {
        return self::one('SELECT kontakt FROM adresser WHERE id = $1', [$kontoId])['kontakt'];
    }

    public function test_saving_a_contact_keeps_its_position_and_the_primary_contact(): void
    {
        $card = $this->card('Anna', [['Anna', 1], ['Bo', 2]]);

        $this->save($card['id'], $card['contacts']['Bo'], 'Bo');

        $this->assertSame(['Anna' => 1, 'Bo' => 2], $this->positions($card['id']));
        $this->assertSame('Anna', $this->primary($card['id']));
    }

    public function test_a_new_contact_goes_first_and_becomes_the_primary_contact(): void
    {
        $card = $this->card('Anna', [['Anna', 1], ['Bo', 2]]);

        $this->save($card['id'], 0, 'Cleo');

        $this->assertSame(['Cleo' => 1, 'Anna' => 2, 'Bo' => 3], $this->positions($card['id']));
        $this->assertSame('Cleo', $this->primary($card['id']));
    }

    /** Imported contacts have no position. PostgreSQL lists NULL last, so the primary contact must not stay without one. */
    public function test_saving_another_contact_gives_the_primary_contact_position_1(): void
    {
        $card = $this->card('Anna', [['Anna', null], ['Bo', null]]);

        $this->save($card['id'], $card['contacts']['Bo'], 'Bo');

        $this->assertSame(['Anna' => 1, 'Bo' => 2], $this->positions($card['id']), 'the primary contact is listed first');
        $this->assertSame('Anna', $this->primary($card['id']));
    }

    public function test_saving_the_primary_contact_without_a_position_gives_it_position_1(): void
    {
        $card = $this->card('Anna', [['Anna', null], ['Bo', null]]);

        $this->save($card['id'], $card['contacts']['Anna'], 'Anna');

        $this->assertSame(['Anna' => 1, 'Bo' => null], $this->positions($card['id']));
        $this->assertSame('Anna', $this->primary($card['id']));
    }

    public function test_renaming_the_primary_contact_renames_it_on_the_card_when_nobody_holds_position_1(): void
    {
        $card = $this->card('Anna', [['Anna', 2], ['Bo', 3]]);

        $this->save($card['id'], $card['contacts']['Anna'], 'Anne');

        $this->assertSame('Anne', $this->primary($card['id']), 'adresser.kontakt follows the new name');
        $this->assertSame(['Anne' => 2, 'Bo' => 3], $this->positions($card['id']), 'positions are kept');
    }

    public function test_renaming_another_contact_leaves_the_primary_contact_when_nobody_holds_position_1(): void
    {
        $card = $this->card('Anna', [['Anna', 2], ['Bo', 3]]);

        $this->save($card['id'], $card['contacts']['Bo'], 'Bodil');

        $this->assertSame('Anna', $this->primary($card['id']));
        $this->assertSame(['Anna' => 2, 'Bodil' => 3], $this->positions($card['id']));
    }
}

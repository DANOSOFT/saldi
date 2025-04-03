<?php
/**
 * AdresseReadDTO - A lightweight Data Transfer Object for address information
 * Contains essential address fields without related entities
 */
class AdresseReadDTO
{
    // Essential properties
    private $addr1;
    private $addr2;
    private $postnr;
    private $bynavn;
    private $land;
    private $kontakt;
    private $email;

    /**
     * Constructor - Create a DTO from database row or AdresseModel
     *
     * @param array|AdresseModel $source Data source (DB row or AdresseModel)
     */
    public function __construct($source = null)
    {
        if ($source !== null) {
            $this->loadFromArray($source);
        }
    }

    /**
     * Load data from database row
     *
     * @param array $row Database row
     */
    private function loadFromArray(array $row)
    {
        $this->addr1 = isset($row['addr1']) ? $row['addr1'] : null;
        $this->addr2 = isset($row['addr2']) ? $row['addr2'] : null;
        $this->postnr = isset($row['postnr']) ? $row['postnr'] : null;
        $this->bynavn = isset($row['bynavn']) ? $row['bynavn'] : null;
        $this->land = isset($row['land']) ? $row['land'] : null;
        $this->kontakt = isset($row['kontakt']) ? $row['kontakt'] : null;
        $this->email = isset($row['email']) ? $row['email'] : null;
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'addr1' => $this->addr1,
            'addr2' => $this->addr2,
            'postnr' => $this->postnr,
            'bynavn' => $this->bynavn,
            'land' => $this->land,
            'kontakt' => $this->kontakt,
            'email' => $this->email,
        ];
    }

    // Getters
    public function getAddr1() { return $this->addr1; }
    public function getAddr2() { return $this->addr2; }
    public function getPostnr() { return $this->postnr; }
    public function getBynavn() { return $this->bynavn; }
    public function getLand() { return $this->land; }
    public function getKontakt() { return $this->kontakt; }
    public function getEmail() { return $this->email; }
}
<?php

/**
 * VareReadDTO - A lightweight Data Transfer Object for VareModel
 * Includes only essential product information without related entities
 */
class VareReadDTO
{
    // Essential properties
    private $id;
    private $varenr;
    private $stregkode;
    private $beskrivelse;
    private $salgspris;
    private $kostpris;
    private $modtime;

    /**
     * Constructor - Create a DTO from database row or VareModel
     * 
     * @param array|VareModel $source Data source (DB row or VareModel)
     */
    public function __construct($source = null)
    {
        if ($source !== null) {
            if ($source instanceof VareModel) {
                $this->loadFromVareModel($source);
            } elseif (is_array($source)) {
                $this->loadFromArray($source);
            }
        }
    }

    /**
     * Load data from VareModel
     * 
     * @param VareModel $vare
     */
    private function loadFromVareModel(VareModel $vare)
    {
        $this->id = $vare->getId();
        $this->varenr = $vare->getVarenr();
        $this->stregkode = $vare->getStregkode();
        $this->beskrivelse = $vare->getBeskrivelse();
        $this->salgspris = $vare->getSalgsPris();
        $this->kostpris = $vare->getKostPris();
        // Assuming we need to add getModTime() to VareModel
    }

    /**
     * Load data from database row
     * 
     * @param array $row Database row
     */
    private function loadFromArray(array $row)
    {
        $this->id = isset($row['id']) ? (int)$row['id'] : null;
        $this->varenr = isset($row['varenr']) ? $row['varenr'] : null;
        $this->stregkode = isset($row['stregkode']) ? $row['stregkode'] : null;
        $this->beskrivelse = isset($row['beskrivelse']) ? $row['beskrivelse'] : null;
        $this->salgspris = isset($row['salgspris']) ? (float)$row['salgspris'] : null;
        $this->kostpris = isset($row['kostpris']) ? (float)$row['kostpris'] : null;
        $this->modtime = isset($row['modtime']) ? $row['modtime'] : null;
    }

    /**
     * Convert to array representation
     * 
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'varenr' => $this->varenr,
            'stregkode' => $this->stregkode,
            'beskrivelse' => $this->beskrivelse,
            'salgspris' => $this->salgspris,
            'kostpris' => $this->kostpris,
            'modtime' => $this->modtime
        ];
    }

    // Getters
    public function getId() { return $this->id; }
    public function getVarenr() { return $this->varenr; }
    public function getStregkode() { return $this->stregkode; }
    public function getBeskrivelse() { return $this->beskrivelse; }
    public function getSalgsPris() { return $this->salgspris; }
    public function getKostPris() { return $this->kostpris; }
    public function getModTime() { return $this->modtime; }
}
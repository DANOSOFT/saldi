<?php

/**
 * VareReadDTO - A lightweight Data Transfer Object for VareModel
 * Includes only essential product information without related entities
 */
class VareReadDTO
{
    // Essential properties using English names to match VareModel
    private $id;
    private $sku;
    private $barcode;
    private $description;
    private $salesPrice;
    private $costPrice;
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
        $this->sku = $vare->getSku();
        $this->barcode = $vare->getBarcode();
        $this->description = $vare->getDescription();
        $this->salesPrice = $vare->getSalesPrice();
        $this->costPrice = $vare->getCostPrice();
        // Note: VareModel doesn't have getModTime() method, so we'll set it to null
        $this->modtime = null;
    }

    /**
     * Load data from database row (still uses Danish column names from DB)
     * 
     * @param array $row Database row
     */
    private function loadFromArray(array $row)
    {
        $this->id = isset($row['id']) ? (int)$row['id'] : null;
        $this->sku = isset($row['varenr']) ? $row['varenr'] : null;
        $this->barcode = isset($row['stregkode']) ? $row['stregkode'] : null;
        $this->description = isset($row['beskrivelse']) ? $row['beskrivelse'] : null;
        $this->salesPrice = isset($row['salgspris']) ? (float)$row['salgspris'] : null;
        $this->costPrice = isset($row['kostpris']) ? (float)$row['kostpris'] : null;
        $this->modtime = isset($row['modtime']) ? $row['modtime'] : null;
    }

    /**
     * Convert to array representation using English field names
     * 
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'salesPrice' => $this->salesPrice,
            'costPrice' => $this->costPrice,
            'modtime' => $this->modtime
        ];
    }

    /**
     * Convert to array with Danish field names for backward compatibility
     * 
     * @return array
     */
    public function toDanishArray()
    {
        return [
            'id' => $this->id,
            'varenr' => $this->sku,
            'stregkode' => $this->barcode,
            'beskrivelse' => $this->description,
            'salgspris' => $this->salesPrice,
            'kostpris' => $this->costPrice,
            'modtime' => $this->modtime
        ];
    }

    // Getters using English names
    public function getId() { return $this->id; }
    public function getSku() { return $this->sku; }
    public function getBarcode() { return $this->barcode; }
    public function getDescription() { return $this->description; }
    public function getSalesPrice() { return $this->salesPrice; }
    public function getCostPrice() { return $this->costPrice; }
    public function getModTime() { return $this->modtime; }
    
    // Legacy getters for backward compatibility (Danish names)
    public function getVarenr() { return $this->sku; }
    public function getStregkode() { return $this->barcode; }
    public function getBeskrivelse() { return $this->description; }
    public function getSalgsPris() { return $this->salesPrice; }
    public function getKostPris() { return $this->costPrice; }

    // Setters using English names
    public function setId($id) { $this->id = $id; }
    public function setSku($sku) { $this->sku = $sku; }
    public function setBarcode($barcode) { $this->barcode = $barcode; }
    public function setDescription($description) { $this->description = $description; }
    public function setSalesPrice($salesPrice) { $this->salesPrice = (float)$salesPrice; }
    public function setCostPrice($costPrice) { $this->costPrice = (float)$costPrice; }
    public function setModTime($modtime) { $this->modtime = $modtime; }
}
<?php

/**
 * SizeModel - Value object for item dimensions and weight properties
 */
class SizeModel 
{
    // Dimension properties
    private $width;
    private $height;
    private $length;
    
    // Weight properties
    private $netWeight;
    private $grossWeight;
    
    // Units
    private $netWeightUnit;
    private $grossWeightUnit;
    
    /**
     * Constructor
     * 
     * @param array $data Size data array
     */
    public function __construct(array $data = [])
    {
        // Set default values
        $this->width = 0;
        $this->height = 0;
        $this->length = 0;
        $this->netWeight = 0;
        $this->grossWeight = 0;
        $this->netWeightUnit = 'kg';
        $this->grossWeightUnit = 'kg';
        
        // Override with provided data
        if (!empty($data)) {
            $this->fromArray($data);
        }
    }
    
    /**
     * Populate object from array
     * 
     * @param array $data Size data array
     * @return SizeModel
     */
    public function fromArray(array $data)
    {
        if (isset($data['width'])) $this->width = (float)$data['width'];
        if (isset($data['height'])) $this->height = (float)$data['height'];
        if (isset($data['length'])) $this->length = (float)$data['length'];
        if (isset($data['netWeight'])) $this->netWeight = (float)$data['netWeight'];
        if (isset($data['grossWeight'])) $this->grossWeight = (float)$data['grossWeight'];
        if (isset($data['netWeightUnit'])) $this->netWeightUnit = $data['netWeightUnit'];
        if (isset($data['grossWeightUnit'])) $this->grossWeightUnit = $data['grossWeightUnit'];
        
        return $this;
    }
    
    /**
     * Convert object to array
     * 
     * @return array
     */
    public function toArray()
    {
        return [
            'dimensions' => [
                'width' => $this->width,
                'height' => $this->height,
                'length' => $this->length
            ],
            'weights' => [
                'net' => [
                    'value' => $this->netWeight,
                    'unit' => $this->netWeightUnit
                ],
                'gross' => [
                    'value' => $this->grossWeight,
                    'unit' => $this->grossWeightUnit
                ]
            ],
        ];
    }
    
    /**
     * Calculate volume based on dimensions
     * 
     * @return float
     */
    public function getVolume()
    {
        return $this->width * $this->height * $this->length;
    }
    
    // Getters
    public function getWidth() { return $this->width; }
    public function getHeight() { return $this->height; }
    public function getLength() { return $this->length; }
    public function getNetWeight() { return $this->netWeight; }
    public function getGrossWeight() { return $this->grossWeight; }
    public function getNetWeightUnit() { return $this->netWeightUnit; }
    public function getGrossWeightUnit() { return $this->grossWeightUnit; }
    
    // Setters
    public function setWidth($width) { $this->width = (float)$width; return $this; }
    public function setHeight($height) { $this->height = (float)$height; return $this; }
    public function setLength($length) { $this->length = (float)$length; return $this; }
    public function setNetWeight($weight) { $this->netWeight = (float)$weight; return $this; }
    public function setGrossWeight($weight) { $this->grossWeight = (float)$weight; return $this; }
    public function setNetWeightUnit($unit) { $this->netWeightUnit = $unit; return $this; }
    public function setGrossWeightUnit($unit) { $this->grossWeightUnit = $unit; return $this; }
}
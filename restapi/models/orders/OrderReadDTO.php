<?php

/**
 * OrderReadDTO - A lightweight Data Transfer Object for OrderModel
 * Includes only essential order information without complex nested data
 */
class OrderReadDTO
{
    // Essential properties
    private $id;
    private $ordrenr;
    private $firmanavn;
    private $telefon;
    private $email;
    private $ordredate;
    private $sum;
    private $status;
    private $valuta;

    /**
     * Constructor - Create a DTO from database row or OrderModel
     * 
     * @param array|OrderModel $source Data source (DB row or OrderModel)
     */
    public function __construct($source = null)
    {
        if ($source !== null) {
            if ($source instanceof OrderModel) {
                $this->loadFromOrderModel($source);
            } elseif (is_array($source)) {
                $this->loadFromArray($source);
            }
        }
    }

    /**
     * Load data from OrderModel
     * 
     * @param OrderModel $order
     */
    private function loadFromOrderModel(OrderModel $order)
    {
        $this->id = $order->getId();
        $this->ordrenr = $order->getOrdrenr();
        $this->firmanavn = $order->getFirmanavn();
        $this->telefon = $order->getTelefon();
        $this->email = $order->getEmail();
        $this->ordredate = $order->getOrdredate();
        $this->sum = $order->getSum();
        $this->status = $order->getStatus();
        $this->valuta = $order->getValuta();
    }

    /**
     * Load data from database row
     * 
     * @param array $row Database row
     */
    private function loadFromArray(array $row)
    {
        $this->id = isset($row['id']) ? (int)$row['id'] : null;
        $this->ordrenr = isset($row['ordrenr']) ? (int)$row['ordrenr'] : null;
        $this->firmanavn = isset($row['firmanavn']) ? $row['firmanavn'] : null;
        $this->telefon = isset($row['phone']) ? $row['phone'] : null;
        $this->email = isset($row['email']) ? $row['email'] : null;
        $this->ordredate = isset($row['ordredate']) ? $row['ordredate'] : null;
        $this->sum = isset($row['sum']) ? (float)$row['sum'] : null;
        $this->status = isset($row['status']) ? (int)$row['status'] : null;
        $this->valuta = isset($row['valuta']) ? $row['valuta'] : null;
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
            'ordrenr' => $this->ordrenr,
            'firmanavn' => $this->firmanavn,
            'telefon' => $this->telefon,
            'email' => $this->email,
            'ordredate' => $this->ordredate,
            'sum' => $this->sum,
            'status' => $this->status,
            'valuta' => $this->valuta
        ];
    }

    // Getters
    public function getId() { return $this->id; }
    public function getOrdrenr() { return $this->ordrenr; }
    public function getFirmanavn() { return $this->firmanavn; }
    public function getTelefon() { return $this->telefon; }
    public function getEmail() { return $this->email; }
    public function getOrdredate() { return $this->ordredate; }
    public function getSum() { return $this->sum; }
    public function getStatus() { return $this->status; }
    public function getValuta() { return $this->valuta; }
}
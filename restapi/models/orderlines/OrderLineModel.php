<?php

class OrderLineModel
{
    private $id;
    private $ordre_id;
    private $vare_id;
    private $varenr;
    private $enhed;
    private $beskrivelse;
    private $antal;
    private $rabat;
    private $rabatart;
    private $procent;
    private $pris;
    private $vat_price;
    private $kostpris;
    private $momsfri;
    private $momssats;
    private $posnr;
    private $variant_id;
    private $bogf_konto;
    private $vat_account;
    private $lager;
    private $serienr;

    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->loadFromId($id);
        }
    }

    private function loadFromId($id)
    {
        $qtxt = "SELECT * FROM ordrelinjer WHERE id = $id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->ordre_id = (int)$r['ordre_id'];
            $this->vare_id = $r['vare_id'] ? (int)$r['vare_id'] : null;
            $this->varenr = $r['varenr'];
            $this->enhed = $r['enhed'];
            $this->beskrivelse = $r['beskrivelse'];
            $this->antal = (float)$r['antal'];
            $this->rabat = (float)$r['rabat'];
            $this->rabatart = $r['rabatart'];
            $this->procent = (float)$r['procent'];
            $this->pris = (float)$r['pris'];
            $this->vat_price = (float)$r['vat_price'];
            $this->kostpris = (float)$r['kostpris'];
            $this->momsfri = (int)$r['momsfri'];
            $this->momssats = (float)$r['momssats'];
            $this->posnr = (int)$r['posnr'];
            $this->variant_id = $r['variant_id'] ? (int)$r['variant_id'] : null;
            $this->bogf_konto = $r['bogf_konto'];
            $this->vat_account = $r['vat_account'] ? (int)$r['vat_account'] : null;
            $this->lager = $r['lager'];
            $this->serienr = $r['serienr'];
            return true;
        }
        return false;
    }

    public function save()
    {
        if ($this->id) {
            // Update existing line
            $qtxt = "UPDATE ordrelinjer SET 
                beskrivelse = '" . db_escape_string($this->beskrivelse) . "',
                antal = '$this->antal',
                rabat = '$this->rabat',
                pris = '$this->pris',
                vat_price = '$this->vat_price',
                kostpris = '$this->kostpris',
                procent = '$this->procent'
                WHERE id = $this->id";

            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            return explode("\t", $q)[0] == "0";
        }
        return false;
    }

    /* public function delete()
    {
        if (!$this->id) {
            return false;
        }

        $qtxt = "DELETE FROM ordrelinjer WHERE id = $this->id";
        $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        return explode("\t", $q)[0] == "0";
    } */

    public static function getByOrderId($orderId)
    {
        $qtxt = "SELECT id FROM ordrelinjer WHERE ordre_id = $orderId ORDER BY posnr";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new OrderLineModel($r['id']);
        }
        return $items;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'ordre_id' => $this->ordre_id,
            'vare_id' => $this->vare_id,
            'varenr' => $this->varenr,
            'enhed' => $this->enhed,
            'beskrivelse' => $this->beskrivelse,
            'antal' => $this->antal,
            'rabat' => $this->rabat,
            'rabatart' => $this->rabatart,
            'procent' => $this->procent,
            'pris' => $this->pris,
            'vat_price' => $this->vat_price,
            'kostpris' => $this->kostpris,
            'momsfri' => $this->momsfri,
            'momssats' => $this->momssats,
            'posnr' => $this->posnr,
            'variant_id' => $this->variant_id,
            'bogf_konto' => $this->bogf_konto,
            'vat_account' => $this->vat_account,
            'lager' => $this->lager,
            'serienr' => $this->serienr
        ];
    }

    // Getters
    public function getId() { return $this->id; }
    public function getOrdreId() { return $this->ordre_id; }
    public function getVareId() { return $this->vare_id; }
    public function getVarenr() { return $this->varenr; }
    public function getBeskrivelse() { return $this->beskrivelse; }
    public function getAntal() { return $this->antal; }
    public function getPris() { return $this->pris; }
    public function getRabat() { return $this->rabat; }

    // Setters
    public function setAntal($antal) { $this->antal = $antal; }
    public function setPris($pris) { $this->pris = $pris; }
    public function setRabat($rabat) { $this->rabat = $rabat; }
    public function setBeskrivelse($beskrivelse) { $this->beskrivelse = $beskrivelse; }
}
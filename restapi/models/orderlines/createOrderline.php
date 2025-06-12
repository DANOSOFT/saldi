<?php

class OrderLineService 
{
    private $db;
    private $regnaar;
    private $afd;
    private $momssats;
    private $status;
    private $log;

    public function __construct() 
    {
        global $db, $afd, $momssats, $status;
        $this->db = $db;
        $this->regnaar = $this->getCurrentFiscalYear();
        $this->afd = $afd;
        $this->momssats = $momssats;
        $this->status = $status;
    }

    /**
     * Get current fiscal year based on date ranges
     */
    private function getCurrentFiscalYear()
    {
        $query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
        $currentYear = date('Y');
        $currentMonth = date('m');
        $regnaar = null;
        
        while($row = db_fetch_array($query)){
            $box1 = $row['box1']; // Starting month
            $box2 = $row['box2']; // Starting year
            $box3 = $row['box3']; // Ending month
            $box4 = $row['box4']; // Ending year
            $kodenr = $row['kodenr'];

            // Check if the current year and month fall within the range
            if (($currentYear > $box2 || ($currentYear == $box2 && $currentMonth >= $box1)) &&
                ($currentYear < $box4 || ($currentYear == $box4 && $currentMonth <= $box3))) {
                // The current year and month fall within the range
                $regnaar = $kodenr;
                break; // Found the matching fiscal year, exit loop
            }
        }
        
        return $regnaar;
    }

    /**
     * Main function to create order line
     */
    public function createOrderLine($params) 
    {
        // Extract parameters
        extract($params);
        
        if (!$id) {
            return "missing ordre ID";
        }

        // Validate order status
        if ($this->status >= 3) {
            return "Der kan ikke tilføjes linjer i en bogført ordre";
        }

        // Clean up temporary files
        $this->cleanupTempFiles($id);

        // Handle timestamp validation
        if (!$this->validateTimestamp($varenr)) {
            return;
        }

        // Set defaults
        $this->setDefaults($procent, $fast_db, $rabat_ny, $saet, $afd);

        // Validate price
        if ($pris && $pris > 99999999) {
            return "Ulovlig værdi i prisfelt";
        }

        // Get order and customer info
        $orderInfo = $this->getOrderInfo($id);
        if (!$orderInfo) {
            return "Order not found";
        }

        // Handle variant lookup
        $variantInfo = $this->handleVariants($varenr);

        // Get product information
        $productInfo = $this->getProductInfo($vare_id, $varenr, $variantInfo);
        if (!$productInfo && !$kopi) {
            return $this->handleMissingProduct($varenr, $art, $id);
        }

        // Calculate pricing and discounts
        $pricing = $this->calculatePricing($productInfo, $orderInfo, $pris, $rabat_ny, $incl_moms, $momsfri);

        // Create or update order line
        $result = $this->createOrUpdateOrderLine([
            'id' => $id,
            'productInfo' => $productInfo,
            'variantInfo' => $variantInfo,
            'pricing' => $pricing,
            'orderInfo' => $orderInfo,
            'params' => $params
        ]);

        return $result;
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles($id) 
    {
        if (file_exists("../temp/{$this->db}/pos$id.txt")) {
            unlink("../temp/{$this->db}/pos$id.txt");
        }
    }

    /**
     * Validate timestamp to prevent duplicate submissions
     */
    private function validateTimestamp($varenr) 
    {
        if (isset($_POST['timestamp']) && $_POST['timestamp']) {
            global $bruger_id, $barcodeNew;
            
            $timestamp = $_POST['timestamp'] . "|" . $varenr;
            $fn = "../temp/{$this->db}/timestamp" . $bruger_id . ".txt";
            $preTimestamp = file_get_contents($fn);
            
            if ($timestamp == $preTimestamp) {
                return false;
            } else {
                file_put_contents($fn, $timestamp);
            }
        }
        return true;
    }

    /**
     * Set default values for parameters
     */
    private function setDefaults(&$procent, &$fast_db, &$rabat_ny, &$saet, &$afd) 
    {
        if ($procent == '') $procent = 100;
        if (!is_numeric($fast_db)) $fast_db = 0;
        if (!is_numeric($rabat_ny)) $rabat_ny = 0;
        if (!is_numeric($saet)) $saet = 0;
        if (!$afd) $afd = 0;
    }

    /**
     * Get order and customer information
     */
    private function getOrderInfo($orderId) 
    {
        $qtxt = "SELECT ordrer.art, ordrer.status, ordrer.valutakurs, ordrer.afd, 
                 adresser.gruppe as debitorgruppe, adresser.rabatgruppe as debitorrabatgruppe 
                 FROM adresser, ordrer 
                 WHERE ordrer.id='$orderId' AND adresser.id=ordrer.konto_id";
                 
        $result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($r = db_fetch_array($result)) {
            return [
                'art' => $r['art'],
                'status' => $r['status'],
                'valutakurs' => $r['valutakurs'],
                'afd' => $r['afd'],
                'debitorgruppe' => $r['debitorgruppe'],
                'debitorrabatgruppe' => $r['debitorrabatgruppe']
            ];
        }
        return null;
    }

    /**
     * Handle variant products
     */
    private function handleVariants($varenr) 
    {
        if (!$varenr) return null;

        $varenr_up = strtoupper($varenr);
        
        $qtxt = "SELECT id, vare_id, variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'";
        if (strlen($varenr) == 12 && is_numeric($varenr)) {
            $qtxt .= " OR variant_stregkode='0$varenr'";
        }

        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $variant_descriptions = $this->getVariantDescriptions($r['variant_type']);
            
            return [
                'id' => $r['id'],
                'vare_id' => $r['vare_id'],
                'variant_type' => $r['variant_type'],
                'description' => implode(' ', $variant_descriptions)
            ];
        }
        
        return null;
    }

    /**
     * Get variant descriptions
     */
    private function getVariantDescriptions($variant_type) 
    {
        $variant_type_array = explode(chr(9), $variant_type);
        $descriptions = [];

        foreach ($variant_type_array as $type_id) {
            if (empty($type_id)) continue;

            $qtxt = "SELECT beskrivelse FROM variant_typer WHERE id = '$type_id'";
            if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                $descriptions[] = $r['beskrivelse'];
            }
        }

        return $descriptions;
    }

    /**
     * Get product information
     */
    private function getProductInfo($vare_id, $varenr, $variantInfo) 
    {
        if ($vare_id && $variantInfo) {
            $vare_id = $variantInfo['vare_id'];
        }

        if ($vare_id) {
            $qtxt = "SELECT * FROM varer WHERE id='$vare_id'";
        } elseif ($varenr) {
            $varenr_low = strtolower($varenr);
            $varenr_up = strtoupper($varenr);
            
            $qtxt = "SELECT * FROM varer WHERE 
                    lower(varenr) = '$varenr_low' OR upper(varenr) = '$varenr_up' 
                    OR varenr LIKE '$varenr' OR lower(stregkode) = '$varenr_low' 
                    OR upper(stregkode) = '$varenr_up' OR stregkode LIKE '$varenr'";
            
            if (strlen($varenr) == 12 && is_numeric($varenr)) {
                $qtxt .= " OR stregkode='0$varenr'";
            }
        } else {
            return null;
        }

        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return [
                'id' => $r['id'],
                'varenr' => $r['varenr'],
                'enhed' => $r['enhed'],
                'beskrivelse' => $r['beskrivelse'],
                'salgspris' => $r['salgspris'],
                'kostpris' => $r['kostpris'],
                'campaign_cost' => $r['campaign_cost'],
                'rabatgruppe' => $r['rabatgruppe'],
                'gruppe' => $r['gruppe'],
                'samlevare' => $r['samlevare'],
                'dvrg' => $r['dvrg'],
                'tier_price' => $r['tier_price'],
                'folgevare' => $r['folgevare'],
                'beholdning' => $r['beholdning'],
                'm_antal' => $r['m_antal'],
                'serienr' => $r['serienr'],
                'specialtype' => $r['specialtype'],
                'special_price' => $r['special_price'],
                'special_from_date' => $r['special_from_date'],
                'special_to_date' => $r['special_to_date'],
                'special_from_time' => $r['special_from_time'],
                'special_to_time' => $r['special_to_time']
            ];
        }

        return null;
    }

    /**
     * Handle missing product scenario
     */
    private function handleMissingProduct($varenr, $art, $id) 
    {
        global $webservice;
        
        if ($webservice) {
            if ($varenr) {
                return "Varenr: $varenr eksisterer ikke";
            } else {
                return '0';
            }
        } else {
            vareopslag($art, 'varenr', 'beskrivelse', $id, '', '', '%' . $varenr . '%');
            exit;
        }
    }

    /**
     * Calculate pricing including VAT, discounts, and special prices
     */
    private function calculatePricing($productInfo, $orderInfo, $inputPrice, $discount, $inclMoms, $momsfri) 
    {
        $pricing = [
            'price' => $inputPrice ?: ($productInfo ? $productInfo['salgspris'] : 0),
            'cost' => $productInfo ? $productInfo['kostpris'] : 0,
            'discount' => $discount ?: 0,
            'vat_rate' => 0,
            'vat_account' => 0
        ];

        if ($productInfo) {
            // Handle special pricing periods
            $this->applySpecialPricing($pricing, $productInfo);

            // Calculate VAT
            if (!$momsfri) {
                $vatInfo = $this->calculateVAT($productInfo['gruppe']);
                $pricing['vat_rate'] = $vatInfo['rate'];
                $pricing['vat_account'] = $vatInfo['account'];
            }
        }

        // Apply currency conversion
        if ($orderInfo['valutakurs'] && $orderInfo['valutakurs'] != 100) {
            $pricing['price'] = $pricing['price'] * 100 / $orderInfo['valutakurs'];
            $pricing['cost'] = $pricing['cost'] * 100 / $orderInfo['valutakurs'];
        }

        // Calculate VAT price
        if ($momsfri) {
            $pricing['vat_price'] = $pricing['price'];
        } else {
            $pricing['vat_price'] = $pricing['price'] + ($pricing['price'] * $pricing['vat_rate'] / 100);
        }

        return $pricing;
    }

    /**
     * Apply special pricing if within valid period
     */
    private function applySpecialPricing(&$pricing, $productInfo) 
    {
        $now_date = date("Y-m-d");
        $now_time = date("H:i:s");
        
        $inSpecialPeriod = (
            ($productInfo['special_from_date'] < $now_date || 
             ($productInfo['special_from_date'] == $now_date && $productInfo['special_from_time'] <= $now_time)) &&
            ($productInfo['special_to_date'] > $now_date || 
             ($productInfo['special_to_date'] == $now_date && $productInfo['special_to_time'] >= $now_time))
        );

        if ($inSpecialPeriod && $productInfo['special_price']) {
            if ($productInfo['specialtype'] == 'percent') {
                if ($pricing['discount'] == 0) {
                    $pricing['discount'] = $productInfo['special_price'];
                }
            } else {
                $pricing['price'] = $productInfo['special_price'];
                $pricing['cost'] = $productInfo['campaign_cost'] ?: $pricing['cost'];
            }
        }
    }

    /**
     * Calculate VAT information
     */
    private function calculateVAT($varegruppe) 
    {
        // Get product group VAT settings
        $qtxt = "SELECT box4, box6, box7, box8 FROM grupper 
                WHERE art = 'VG' AND kodenr = '$varegruppe' AND fiscal_year = '{$this->regnaar}'";
                
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $bogfkto = $r['box4'];
            
            if ($bogfkto) {
                // Get VAT rate and account
                $qtxt = "SELECT moms FROM kontoplan WHERE kontonr = '$bogfkto' AND regnskabsaar = '{$this->regnaar}'";
                if ($r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                    $momsCode = (int) substr($r2['moms'], 1);
                    if ($momsCode) {
                        $qtxt = "SELECT box1, box2 FROM grupper WHERE art = 'SM' AND kodenr = '$momsCode' AND fiscal_year = '{$this->regnaar}'";
                        if ($r3 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                            return [
                                'account' => (int) $r3['box1'],
                                'rate' => (float) $r3['box2']
                            ];
                        }
                    }
                }
            }
        }

        return [
            'account' => 0,
            'rate' => $this->momssats ?: 25.0
        ];
    }

    /**
     * Create or update order line
     */
    private function createOrUpdateOrderLine($data) 
    {
        $id = $data['id'];
        $productInfo = $data['productInfo'];
        $variantInfo = $data['variantInfo'];
        $pricing = $data['pricing'];
        $orderInfo = $data['orderInfo'];
        $params = $data['params'];

        // Check if line already exists (for POS or existing line updates)
        $existingLine = $this->findExistingOrderLine($data);
        
        if ($existingLine) {
            return $this->updateExistingOrderLine($existingLine, $params);
        } else {
            return $this->insertNewOrderLine($data);
        }
    }

    /**
     * Find existing order line
     */
    private function findExistingOrderLine($data) 
    {
        // Implementation for finding existing lines
        // This would contain the logic for checking if a line should be updated vs created new
        return null;
    }

    /**
     * Update existing order line
     */
    private function updateExistingOrderLine($existingLine, $params) 
    {
        // Implementation for updating existing lines
        return 0;
    }

    /**
     * Insert new order line
     */
    private function insertNewOrderLine($data) 
    {
        $id = $data['id'];
        $productInfo = $data['productInfo'];
        $variantInfo = $data['variantInfo'];
        $pricing = $data['pricing'];
        $params = $data['params'];

        // Get next position number
        $posnr = $this->getNextPositionNumber($id, $params['posnr'] ?? null);

        // Build description
        $beskrivelse = $params['beskrivelse'] ?? ($productInfo ? $productInfo['beskrivelse'] : '');
        if ($variantInfo && $variantInfo['description']) {
            $beskrivelse .= " " . $variantInfo['description'];
        }

        // Ensure required numeric values have defaults
        $antal = $params['antal'] ?? 1;
        $procent = $params['procent'] ?? 100;
        $momsfri = $params['momsfri'] ?? 0;

        // Insert the order line
        $qtxt = "INSERT INTO ordrelinjer (
            ordre_id, vare_id, varenr, enhed, beskrivelse, antal, rabat, rabatart, procent,
            m_rabat, pris, vat_price, kostpris, momsfri, momssats, posnr, projekt, folgevare,
            rabatgruppe, bogf_konto, vat_account, kred_linje_id, kdo, serienr, variant_id,
            leveres, samlevare, omvbet, saet, fast_db, lev_varenr, tilfravalg, lager, barcode
        ) VALUES (
            '$id', '" . ($productInfo['id'] ?? '') . "', '" . ($productInfo['varenr'] ?? '') . "', 
            '" . ($productInfo['enhed'] ?? '') . "', '$beskrivelse', '$antal', 
            '{$pricing['discount']}', '" . ($params['rabatart'] ?? '') . "', '$procent', '0', 
            '{$pricing['price']}', '{$pricing['vat_price']}', '{$pricing['cost']}', '$momsfri', 
            '{$pricing['vat_rate']}', '$posnr', '', '" . ($productInfo['folgevare'] ?? '') . "', 
            '" . ($productInfo['rabatgruppe'] ?? '') . "', '" . ($params['bogfkto'] ?? '') . "',
            '{$pricing['vat_account']}', '" . ($params['kred_linje_id'] ?? '') . "', 
            '" . ($params['kdo'] ?? '') . "', '" . ($productInfo['serienr'] ?? '') . "', 
            '" . ($variantInfo['id'] ?? '') . "', '" . ($params['leveres'] ?? '') . "',
            '" . ($productInfo['samlevare'] ?? '') . "', '" . ($params['omvbet'] ?? '') . "', 
            '" . ($params['saet'] ?? 0) . "', '" . ($params['fast_db'] ?? 0) . "', 
            '" . ($params['lev_varenr'] ?? '') . "', '" . ($params['tilfravalgNy'] ?? '') . "',
            '" . ($params['lager'] ?? '') . "', '" . ($params['barcodeNew'] ?? '') . "'
        )";

        db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        return $pricing['price'] * $antal;
    }

    /**
     * Get next position number for order line
     */
    private function getNextPositionNumber($orderId, $inputPosnr) 
    {
        if ($inputPosnr) {
            return $inputPosnr;
        }

        $qtxt = "SELECT MAX(posnr) as posnr FROM ordrelinjer WHERE ordre_id = '$orderId'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return ($r['posnr'] ?? 0) + 1;
        }

        return 1;
    }
}

// Wrapper function to maintain compatibility
function opret_ordrelinje($id, $vare_id, $varenr, $antal, $beskrivelse, $pris, $rabat_ny, $procent, $art, $momsfri, $posnr, $linje_id, $incl_moms, $kdo, $rabatart, $kopi, $saet, $fast_db, $lev_varenr, $lager, $linje) 
{
    $service = new OrderLineService();
    
    $params = compact(
        'id', 'vare_id', 'varenr', 'antal', 'beskrivelse', 'pris', 'rabat_ny', 'procent',
        'art', 'momsfri', 'posnr', 'linje_id', 'incl_moms', 'kdo', 'rabatart', 'kopi',
        'saet', 'fast_db', 'lev_varenr', 'lager', 'linje'
    );
    
    return $service->createOrderLine($params);
}
<?php
// 20260920 CDX/LUI Parse legacy item files once and validate all rows before transactional writes.

/** @return list<list<string>> */
function legacyItemImportRead(string $path, string $format): array
{
    $delimiters = ['Semikolon'=>';', 'Komma'=>',', 'Tabulator'=>"\t"];
    if ($format !== 'Brdr. Dahl' && !isset($delimiters[$format])) {
        throw new InvalidArgumentException('Ukendt filformat.');
    }
    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new RuntimeException('Importfilen kunne ikke åbnes.');
    }
    $rows = [];
    try {
        while ($format === 'Brdr. Dahl' ? ($line = fgets($handle)) !== false : ($line = fgetcsv($handle, 0, $delimiters[$format], '"', '')) !== false) {
            if ($format === 'Brdr. Dahl') {
                if (trim($line) === '') { continue; }
                $fields = [];
                foreach ([[1,10],[12,35],[47,10],[58,3],[61,3],[64,1],[65,3]] as [$start,$length]) {
                    $fields[] = trim(substr($line, $start, $length));
                }
                $fields[1] = strtr($fields[1], [chr(145)=>'æ',chr(155)=>'ø',chr(134)=>'å',chr(146)=>'Æ',chr(157)=>'Ø',chr(143)=>'Å']);
            } else {
                $fields = array_map(static fn($value) => trim((string)$value), $line);
                if (count($fields) === 1 && $fields[0] === '') { continue; }
            }
            if (!$rows) { $fields[0] = preg_replace('/^\xEF\xBB\xBF/', '', $fields[0]); }
            $rows[] = $fields;
        }
    } finally {
        fclose($handle);
    }
    if (!$rows) { throw new InvalidArgumentException('Importfilen er tom.'); }
    return $rows;
}

/** @return float Validated decimal value; Danish comma and plain decimal point are supported. */
function legacyItemImportNumber(string $value): float
{
    $value = trim($value);
    if (str_contains($value, ',')) {
        if (!preg_match('/^-?(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)(?:,[0-9]+)?$/D', $value)) {
            throw new InvalidArgumentException('Ugyldigt beløb: ' . $value);
        }
        $value = str_replace(',', '.', str_replace('.', '', $value));
    }
    if (!preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $value)) {
        throw new InvalidArgumentException('Ugyldigt beløb: ' . $value);
    }
    $number = (float)$value;
    if (!is_finite($number)) { throw new InvalidArgumentException('Beløbet er for stort.'); }
    return $number;
}

/** @return list<array{number:string,supplier_number:string,description:string,unit:string,group:int,sales_price:float,cost_price:float}> */
function legacyItemImportPrepare(array $rows, array $labels, int $defaultGroup, float $discount, array $validGroups): array
{
    $allowed = ['Eget varenr.','Lev. varenr.','Begge varenr.','Beskrivelse','Salgspris','Kostpris','Enhed','Varegrp.'];
    $selected = array_values(array_filter($labels, static fn($label) => $label !== ''));
    if (array_diff($selected, $allowed) || count($selected) !== count(array_unique($selected))) {
        throw new InvalidArgumentException('Vælg hver kolonnebetegnelse højst én gang.');
    }
    if ((!in_array('Eget varenr.', $labels, true) && !in_array('Begge varenr.', $labels, true)) ||
        array_diff(['Beskrivelse','Salgspris','Enhed'], $labels) ||
        (!in_array('Kostpris', $labels, true) && $discount <= 0) ||
        (!in_array('Varegrp.', $labels, true) && !in_array($defaultGroup, $validGroups, true))) {
        throw new InvalidArgumentException('Vælg varenummer, beskrivelse, salgspris, enhed, kostpris/rabat og en gyldig varegruppe.');
    }
    if ($discount < 0 || $discount > 100) { throw new InvalidArgumentException('Rabat skal være mellem 0 og 100.'); }
    $prepared = [];
    foreach ($rows as $index => $row) {
        $values = [];
        foreach ($labels as $column => $label) {
            if ($label === '') { continue; }
            if (!array_key_exists($column, $row)) {
                throw new InvalidArgumentException('Linje ' . ($index + 1) . ': den valgte kolonne mangler.');
            }
            $values[$label] = trim($row[$column]);
        }
        $number = $values['Begge varenr.'] ?? $values['Eget varenr.'] ?? '';
        $groupValue = $values['Varegrp.'] ?? (string)$defaultGroup;
        if ($number === '' || $values['Beskrivelse'] === '' || !ctype_digit($groupValue) || !in_array((int)$groupValue, $validGroups, true)) {
            throw new InvalidArgumentException('Linje ' . ($index + 1) . ': manglende varenummer/beskrivelse eller ukendt varegruppe.');
        }
        // Preserve this legacy import format: sales prices are supplied in øre;
        // a mapped cost-price column is already in kroner.
        $sales = legacyItemImportNumber($values['Salgspris']) / 100;
        $cost = isset($values['Kostpris']) ? legacyItemImportNumber($values['Kostpris']) : $sales * (1 - $discount / 100);
        $prepared[] = ['number'=>$number, 'supplier_number'=>$values['Begge varenr.'] ?? $values['Lev. varenr.'] ?? '',
            'description'=>$values['Beskrivelse'], 'unit'=>$values['Enhed'], 'group'=>(int)$groupValue,
            'sales_price'=>$sales, 'cost_price'=>$cost];
    }
    return $prepared;
}

/** @return void Throw on a rejected write so the enclosing import rolls back. */
function legacyItemImportWrite(string $sql): void
{
    $result = db_modify($sql, __FILE__ . ' linje ' . __LINE__);
    if (!is_string($result) || !str_starts_with($result, "0\t")) {
        throw new RuntimeException('Importen kunne ikke gemmes. Ingen varer er importeret.');
    }
}

/** @return int Number of input rows applied in one transaction. */
function legacyItemImportApply(array $items, int $supplierId): int
{
    transaktion('begin');
    try {
        foreach ($items as $item) {
            $number = db_escape_string($item['number']);
            $description = db_escape_string($item['description']);
            $unit = db_escape_string($item['unit']);
            $supplierNumber = db_escape_string($item['supplier_number']);
            $group = (int)$item['group'];
            $sales = (float)$item['sales_price'];
            $cost = (float)$item['cost_price'];
            $record = db_fetch_array(db_select("SELECT id FROM varer WHERE varenr='$number'", __FILE__ . ' linje ' . __LINE__));
            if ($record) {
                $id = (int)$record['id'];
                legacyItemImportWrite("UPDATE varer SET beskrivelse='$description',salgspris=$sales,kostpris=$cost,enhed='$unit',gruppe=$group WHERE id=$id");
            } else {
                $minimum = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name='min_beholdning' AND var_grp='productOptions' ORDER BY id DESC LIMIT 1", __FILE__ . ' linje ' . __LINE__));
                $minStock = (int)($minimum['var_value'] ?? 0);
                legacyItemImportWrite("INSERT INTO varer (varenr,beskrivelse,salgspris,kostpris,enhed,gruppe,min_lager) VALUES ('$number','$description',$sales,$cost,'$unit',$group,$minStock)");
                $record = db_fetch_array(db_select("SELECT id FROM varer WHERE varenr='$number'", __FILE__ . ' linje ' . __LINE__));
                if (!$record) { throw new RuntimeException('Den importerede vare kunne ikke genfindes.'); }
                $id = (int)$record['id'];
            }
            if ($supplierId > 0) {
                $supplier = db_fetch_array(db_select("SELECT id FROM vare_lev WHERE vare_id=$id AND lev_id=$supplierId", __FILE__ . ' linje ' . __LINE__));
                if ($supplier) {
                    $linkId = (int)$supplier['id'];
                    legacyItemImportWrite("UPDATE vare_lev SET kostpris=$cost,lev_varenr='$supplierNumber' WHERE id=$linkId");
                } else {
                    legacyItemImportWrite("INSERT INTO vare_lev (vare_id,lev_id,lev_varenr,kostpris) VALUES ($id,$supplierId,'$supplierNumber',$cost)");
                }
            }
        }
        transaktion('commit');
    } catch (Throwable $error) {
        transaktion('rollback');
        throw $error;
    }
    return count($items);
}

<?php
// 20260920 CDX/LH Share atomic, VAT-exempt reminder posting between manual and automatic paths.
// 20260921 CDX/LH Scope duplicate receivables to the reminder identity, excluding unrelated journal vouchers.
require_once __DIR__ . '/salesPostingVat.php';

/** Post one reminder, including optional automatic advancement, as one unit. */
function saldiPostReminder($id, $markPaid = false)
{
    global $regnaar, $db_modify_fejl, $webservice;
    if (!ctype_digit((string)$id) || (int)$id < 1 || (int)$regnaar < 1) {
        return array('ok' => false, 'error' => 'Ugyldig rykker eller regnskabsår');
    }
    $id = (int)$id;
    $year = (int)$regnaar;
    $oldWebservice = $webservice;
    $webservice = true; // db_modify must report failure so this transaction can roll back.
    $open = false;
    try {
        if (!transaktion('begin')) {
            throw new RuntimeException('Rykker kunne ikke bogføres');
        }
        $open = true;
        if ($db_modify_fejl) {
            throw new RuntimeException('Tidligere databasefejl i transaktionen');
        }
        $one = static function ($sql) {
            $result = db_select($sql, __FILE__ . ' linje ' . __LINE__);
            if (!$result) {
                throw new RuntimeException('Rykkerdata kunne ikke læses');
            }
            return db_fetch_array($result);
        };
        $write = static function ($sql) {
            global $db_modify_fejl;
            $result = db_modify($sql, __FILE__ . ' linje ' . __LINE__);
            if ($result === false || $db_modify_fejl || (is_string($result) && substr($result, 0, 2) === "1\t")) {
                throw new RuntimeException('Rykker kunne ikke bogføres; ingen ændringer er gemt');
            }
        };
        $order = $one("SELECT * FROM ordrer WHERE id=$id FOR UPDATE");
        if (!$order || !in_array($order['art'], array('R1', 'R2', 'R3'), true)) {
            throw new RuntimeException('Rykker findes ikke');
        }
        if ((int)$order['status'] === 4) {
            transaktion('commit');
            $open = false;
            return array('ok' => !$db_modify_fejl, 'id' => $id, 'error' => 'Rykker kunne ikke bogføres');
        }
        // refnr also stores journal voucher numbers. Only this reminder's
        // customer, invoice and non-journal origin identify its receivable.
        $customerId = (int)$order['konto_id'];
        $invoice = db_escape_string((string)$order['fakturanr']);
        if ($one("SELECT id FROM transaktioner WHERE ordre_id=$id")
            || $one("SELECT id FROM openpost WHERE refnr=$id AND konto_id=$customerId AND faktnr='$invoice' AND kladde_id=0")) {
            throw new RuntimeException('Rykker har allerede posteringer; kontrollér bogføringen');
        }
        $fiscal = $one("SELECT box1,box2,box3,box4 FROM grupper WHERE art='RA' AND kodenr=$year");
        $date = (string)$order['fakturadate'];
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$fiscal || !$parsed || $parsed->format('Y-m-d') !== $date
            || !checkdate((int)$fiscal['box1'], 1, (int)$fiscal['box2'])
            || !checkdate((int)$fiscal['box3'], 1, (int)$fiscal['box4'])) {
            throw new RuntimeException('Ugyldig rykkerdato eller regnskabsår');
        }
        $start = sprintf('%04d-%02d-01', $fiscal['box2'], $fiscal['box1']);
        $end = (new DateTimeImmutable(sprintf('%04d-%02d-01', $fiscal['box4'], $fiscal['box3'])))->format('Y-m-t');
        if ($date < $start || $date > $end) {
            throw new RuntimeException('Rykkerdato udenfor regnskabsår');
        }
        $customer = $one("SELECT gruppe FROM adresser WHERE id=$customerId AND art='D'");
        $customerGroup = (int)($customer['gruppe'] ?? 0);
        $debtor = $one("SELECT box2 FROM grupper WHERE art='DG' AND kodenr=$customerGroup AND fiscal_year=$year");
        $account = static function ($value) use ($one, $year) {
            $value = trim((string)$value);
            if (!ctype_digit($value) || (int)$value < 1 || !$one("SELECT id FROM kontoplan WHERE kontonr=" . (int)$value . " AND regnskabsaar=$year")) {
                throw new RuntimeException('Bogføringskonto mangler i regnskabsåret');
            }
            return (int)$value;
        };
        $debtorAccount = $account($debtor['box2'] ?? '');
        $currency = trim((string)($order['valuta'] ?? ''));
        $rate = (float)($order['valutakurs'] ?? 0);
        if ($currency === '' || $currency === 'DKK') {
            $rate = 100;
        }
        elseif ($rate <= 0) {
            $currencySql = db_escape_string($currency);
            $exchange = $one("SELECT v.kurs FROM valuta v JOIN grupper g ON v.gruppe=" . nr_cast('g.kodenr') . " WHERE g.art='VK' AND g.box1='$currencySql' AND v.valdate<='$date' ORDER BY v.valdate DESC LIMIT 1");
            $rate = (float)($exchange['kurs'] ?? 0);
        }
        if (!is_finite($rate) || $rate <= 0) {
            throw new RuntimeException('Valutakurs mangler for rykkerdato');
        }
        $lines = db_select("SELECT id,vare_id,antal,pris,rabat FROM ordrelinjer WHERE ordre_id=$id AND vare_id>0 AND posnr>=0 ORDER BY id", __FILE__ . ' linje ' . __LINE__);
        if (!$lines) {
            throw new RuntimeException('Rykkerlinjer kunne ikke læses');
        }
        $groups = $lineAccounts = array();
        while ($line = db_fetch_array($lines)) {
            $productId = (int)$line['vare_id'];
            $group = $one("SELECT g.box4,g.box8 FROM varer v JOIN grupper g ON g.kodenr=v.gruppe AND g.art='VG' AND g.fiscal_year=$year WHERE v.id=$productId");
            if (!$group) {
                throw new RuntimeException('Varegruppe mangler i regnskabsåret');
            }
            if ($group['box8'] === 'on') {
                throw new RuntimeException('Lagerført vare kan ikke bruges som rykkergebyr');
            }
            $revenue = $account($group['box4']);
            foreach (array('antal', 'pris') as $field) {
                if (!is_numeric($line[$field]) || !is_finite((float)$line[$field])) {
                    throw new RuntimeException('Ugyldigt gebyrbeløb');
                }
            }
            $discount = $line['rabat'] === null || $line['rabat'] === '' ? 0 : $line['rabat'];
            if (!is_numeric($discount) || !is_finite((float)$discount)) {
                throw new RuntimeException('Ugyldigt gebyrbeløb');
            }
            $gross = (float)$line['antal'] * (float)$line['pris'];
            $net = afrund($gross - afrund($gross * (float)$discount / 100, 2), 2);
            if (!is_finite($net)) {
                throw new RuntimeException('Ugyldigt gebyrbeløb');
            }
            $groups[$revenue] = ($groups[$revenue] ?? 0) + $net;
            $lineAccounts[(int)$line['id']] = $revenue;
        }
        ksort($groups, SORT_NUMERIC);
        $sourceTotal = afrund(array_sum($groups), 2);
        $baseTotal = afrund($sourceTotal * $rate / 100, 2);
        $converted = array_map(static function ($amount) use ($rate) { return $amount * $rate / 100; }, $groups);
        $allocated = salesPostingAllocateCents($converted, $baseTotal);
        foreach ($lineAccounts as $lineId => $revenue) {
            $write("UPDATE ordrelinjer SET bogf_konto=$revenue WHERE id=$lineId");
        }
        $customerNumber = db_escape_string(str_replace(' ', '', (string)$order['kontonr']));
        $description = 'Gebyr mm. fra tidligere rykker';
        $employee = db_escape_string((string)($order['ref'] ?? ''));
        $employeeRow = $one("SELECT afd FROM ansatte WHERE navn='$employee'");
        $department = (int)($employeeRow['afd'] ?? 0);
        $project = (int)($order['projekt'] ?? 0);
        $logDate = date('Y-m-d'); $logTime = date('H:i');
        if ($baseTotal != 0) {
            $write("INSERT INTO openpost(konto_id,konto_nr,faktnr,refnr,amount,beskrivelse,udlignet,transdate,kladde_id,valuta,valutakurs) VALUES($customerId,'$customerNumber','$invoice',$id,$baseTotal,'$description','0','$date',0,'DKK',100)");
        }
        $post = static function ($konto, $signedDebit) use ($write,$invoice,$id,$date,$description,$department,$project,$logDate,$logTime) {
            if ($signedDebit == 0) {
                return;
            }
            $debit = max(0, $signedDebit); $credit = max(0, -$signedDebit);
            $write("INSERT INTO transaktioner(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ordre_id) VALUES(0,'$date','$description',$konto,'$invoice',$debit,$credit,0,$department,'$logDate','$logTime',$project,$id)");
        };
        $post($debtorAccount, $baseTotal);
        foreach ($allocated as $revenue => $amount) {
            $post((int)$revenue, -$amount);
        }
        $expectedDebit = max(0, $baseTotal); $expectedCredit = max(0, -$baseTotal);
        $expectedRows = $baseTotal != 0 ? 1 : 0;
        foreach ($allocated as $amount) {
            $expectedDebit += max(0, -$amount); $expectedCredit += max(0, $amount);
            if ($amount != 0) {
                $expectedRows++;
            }
        }
        $balance = $one("SELECT COUNT(*) AS row_count,COALESCE(SUM(debet),0) AS debit,COALESCE(SUM(kredit),0) AS credit FROM transaktioner WHERE ordre_id=$id");
        if (!$balance || (int)$balance['row_count'] !== $expectedRows
            || abs((float)$balance['debit'] - $expectedDebit) > 0.00001
            || abs((float)$balance['credit'] - $expectedCredit) > 0.00001
            || abs((float)$balance['debit'] - (float)$balance['credit']) > 0.00001) {
            throw new RuntimeException('Rykkerens posteringer balancerer ikke');
        }
        $paid = $markPaid ? ",betalt='on'" : '';
        $write("UPDATE ordrer SET sum=$sourceTotal,moms=0,momssats=0,status=4$paid WHERE id=$id");
        $write("DELETE FROM ordrelinjer WHERE ordre_id=$id AND posnr<0");
        transaktion('commit');
        $open = false;
        if ($db_modify_fejl) {
            throw new RuntimeException('Rykker kunne ikke bogføres');
        }
        return array('ok' => true, 'id' => $id);
    } catch (Throwable $error) {
        if ($open) {
            transaktion('rollback');
        }
        return array('ok' => false, 'error' => $error->getMessage());
    } finally {
        $webservice = $oldWebservice;
    }
}

/** Preserve the legacy UI feedback while returning a usable success/failure value. */
function saldiPostReminderForUi($id, $markPaid = false)
{
    $result = saldiPostReminder($id, $markPaid);
    if ($result['ok']) {
        return (int)$result['id'];
    }
    $message = json_encode($result['error'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    print '<script>alert(' . $message . ');</script>';
    return false;
}

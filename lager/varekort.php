<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/varekort.php --- lap 4.1.1 --- 2024-10-25 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20130210 Break ændret til break 1
// 20131007 Kontrol for cirkulær reference indsat. Søg 20131007
// 20140603 Indsat visning af stregkode. Søg 20140603
// 20140617 ekstra felt "indhold" til angivelse af vægt / rumfang mm. samt automatisk beregning af kg/liter/ pris mm. Søg indhold
// 21040909 Fejltekst fra "barcode" hvis stregkode ikke kan genereres bliver nu undertrykt. #20140909
// 20150210 Beholdninger og lokationer vises nu fra hvert lager.
// 20150521 Shopurl tilrettet til "nyt api". Søg shopurl
// 20151028 understøtter ny også tbarcode
// 20160119 PHR Tilføjet varefoto og regulering af varebeholdning ved gruppeændring fra ikke lagerført til lagerført og vice versa
// 20160130 PHR Tilføjet "tilbudsdage" så man kan vælge hvilke ugedage et tilbud skal være aktivt.
// 20160308 PHR Tilføjet "link til slet shop id dom fjerner binding til shop vare. Søg slet_shopbinding.php og fjernet mulighed for publicering.
// 20160606 PHR Opdat shop_vare erstattet af opdat behold...
// 20161006 PHR hvis prisen er 0.001 vises prisen med 3 decimaler. Hvis brugt i pos indsættes varen til 0 kr oden at der hoppes til pris. 20161006
// 20170106 PHR Mrabat trak moms fra på procentrabat. Ændret "%" til "percent"
// 20170210 PHR - Aktivering af nyt API 20170210 
// 20171030 PHR Overføldige paramatrre i kald til og funktion kontoopslag fjernet. 
// 20171106 PHR Stregkodegenereing flyttet i funktion barcode og der kan nu udskrives labels fra varianter. 
// 20180123 PHR En del rettelser i i forhold til varianter og flere lagre. Beholdninger rettes nu kun i varianter, hvis varianter.
// 20180204 PHR Styklister deaktiveres, hvis der anvendes varianter.
// 20180214 PHR Varianter kan ikke mere slettes hvis der er varianter på lager.
// 20180314 PHR Lager var fast til 1 i kald til 'vareproduktion'. 
// 20190117 MSC - Rettet topmenu design til og rettet isset fejl
// 20190118 PHR - Adjusted for different VAT rates. VAT is defined by 'gruppe' -> 'kontoplan'
// 20190118 PHR - Items can no longer be changed from item with variants or vice versa if there are items in stock.
// 20190123 PHR - Barcode line can now hold more than one barcode. Seperate by ';'
// 20190129 PHR - PHR Incl_moms changed according to'$vatOnItemCard' to fullfill demands for different Vat Rates
// 20190220 MSC - Rettet topmenu design og isset fejl
// 20190321 PHR - Added 'read-only'. Look for $noEdit
// 20190404  LN - Added call to varios functions in productCardIncludes/percentageField and created percentage field.
// 20190409 PHR - Added call to function vatPercent in productCardIncludes/itemVat.php and moved vat calculation there.
// 20190421 PHR - Added confirmDescriptionChange, can be set in 'Indstillinger' > 'VareValg'. 
// 20190321 PHR - Edited 'read-only'. Changed from [10] to [9]
// 20191022 PHR - Added copy option, search 'Kopier'.
// 20191106 PHR - Added $netWeight & $grossWeight
// 20191209 PHR - Added quantity incomming and outgoing. Search incomming / outgoing
// 20200211 PHR - Removed htmlentities varius 
// 20200310 PHR - Changed (!count($lagernavn)) to (count($lagernavn) <= 1)  20200310-1
// 20200310 PHR - Changed = to += 20200310-2
// 20200312 PHR - Added 'order by posnr' in 'stykliste' queries
// 20200326 PHR - Moved function barcode to ../includes/std_func.php
// 20200327 PHR - Changed weight to 3 decimal places
// 20200513 PHR - Various cleanup related to variant handling as variants were disabled when less than 2 variants.  
// 20200611 PHR - handling of ' and " in description.
// 20200612 PHR - Added pattern match on item no (varenr).
// 20200714 PHR - Corrected copy handling
// 20200922 PHR - Added confirmStockChange, can be set in 'Indstillinger' > 'VareValg'. 
// 20201020 PHR - Total qty was inserted as variant qty when adding variant.
// 20201115 PHR - Added weights and measures and moved unit section to productCardIncludes/units.php
// 20210125 PHR - Changed $x to $y af parent loop was reset.
// 20210207 PHR - 'Lagerreguler' must not be run if variants.
// 20210213 PHR - Some cleanup
// 20210223 PHR - Commission is calculated if sales price > 0 and cost = 0 and default commission is set.  
// 20210308 PHR - Added hidden as shock was reset when using FIFO 20210308 
// 20210401 PHR - Changed 'kostpris til real kostpris when salgsspris > 0 20210401 
// 20220117 PHR - Volume is now calulated.
// 20220203 PHR - Moved shop syncronization to function sync_shop_vare
// 20220215 PHR - Vat is withdrawn from m_rabat_array items if $incl_moms is set.
// 20220419 DAPE - dg is now properly calculated without VAT
// 20221004 MLH - Added on_price_list, tier_price_multiplier, tier_price_method, tier_price_rounding, salgspris_multiplier, salgspris_method, salgspris_rounding, retail_price_multiplier, retail_price_method, retail_price_rounding
// 20221004 MLH - Added show_advanced_price_calc
// 20230425 PHR - php8 +20230603
// 20230704 PBLM - Added js to handle changes check
// 20230824 PHR - Moved updateProductPrice routine to seperate file in productsIncludes.
// 20230910 PHR - Moved a several routines to seperate files in productCardIncludes.
// 20231006 PHR - some changes in Variants - Must be totally rewritten
// 20231018 PHR - More changes in Variants - Must be totally rewritten
// 20240105 PHR - Error in $ant_be_af
// 20250815	PHR	- Languages
// 20251025 PHR - Enabled call to updateProductPrice.php and added oldRetailPrice	

ob_start(); //Starts output buffering

@session_start();
$s_id = session_id();

$begin = 0;
$folgevare = 0;
$modulnr = 9;
$styklister = 1;
$title = "Varekort";

$ant_indg_i = 0;
$batchItem = $beholdning = $beskrivelse[0] = $betalingsdage = $betegnelse = $box8 = NULL;
$commissionItem = $confirmDescriptionChange = $confirmStockChange = $copyId = $ean13 = $enhed = NULL;
$fejl = $find = $fokus = $folgevarenr = NULL;
$grossWeight = $gruppe = NULL;
$kategori = NULL;
$lager = $numberOfStocks = $lev = $lev_ant = NULL;
$m_antal = $m_rabat = $montage = $demontage = NULL;
$netWeight = $ny_beholdning = 0;
$oldDescription = $ordre_id = NULL;
$shop_id = $shopurl = $show_advanced_price_calc = $sort = $stockItem = $submit = NULL;
$rabatgruppe = $ref = $returside = NULL;
$tilbudgruppe = NULL;
$varenr = $variant = $variantVarerVariantId = $vis_kost = NULL;

$campaign_cost = $special_price = $special_from_date = $special_to_date = 0;
$oldCost = $oldSale = $p_grp_kostpris = $p_grp_salgspris = $p_grp_retail_price = $p_grp_tier_price = 0;
$beskrivelse = $kat_id = $lagerbeh = $ny_lagerbeh = $varianter_id = $variantVarerId = $variantVarerQty = array();

// 20221004
$on_price_list = 1;
$tier_price_multiplier = $salgspris_multiplier = $retail_price_multiplier = 0;
$tier_price_method = $tier_price_rounding = $salgspris_method = $salgspris_rounding = $retail_price_method = $retail_price_rounding = "";

if (file_exists("../documents/"))
    $docfolder = "../documents/";
elseif (file_exists("../bilag/"))
    $docfolder = "../bilag/";
elseif (file_exists($docfolder))
    $docfolder = $docfolder;

$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/vareopslag.php"); # 20090514
include("../includes/stykliste.php");
include("../includes/fuld_stykliste.php");
include("productCardIncludes/itemVat.php");
include("productCardIncludes/percentageField.php");

$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='specialtype'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $qtxt = "ALTER TABLE varer ADD specialtype varchar(10)";
    db_modify($qtxt, __FILE__ . "linje" . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='volume_lager'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    db_modify("ALTER table varer ADD column volume_lager float default 1", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='notesinternal'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    db_modify("ALTER table varer ADD column notesinternal text", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='varer' and column_name='colli_webfragt'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    db_modify("ALTER table varer ADD column colli_webfragt float DEFAULT 0", __FILE__ . " linje " . __LINE__);
}
($rettigheder[9] == '2') ? $noEdit = "disabled=true" : $noEdit = NULL;

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

$usePos = 0;
$qtxt = "SELECT id FROM grupper WHERE art = 'POS'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
    $usePos = 1;

$qtxt = "select id,var_value from settings where var_name = 'confirmDescriptionChange' and var_grp = 'items'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
    $confirmDescriptionChange = $r['var_value'];
$qtxt = "select id,var_value from settings where var_name = 'confirmStockChange' and var_grp = 'items'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
    $confirmStockChange = $r['var_value'];


$qtxt = "select box2 from grupper where art = 'DIV' and kodenr = '5'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
    $shopurl = trim($r['box2']);

$qtxt = "select count(id) as stocks from grupper where art='LG'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
    $numberOfStocks = $r['stocks'];
if (!$numberOfStocks)
    $numberOfStocks = 1;

$opener = if_isset($_GET['opener']);
$id = if_isset($_GET['id']) * 1;
if (isset($_GET['returside']) && $returside = $_GET['returside']) {
    $ordre_id = if_isset($_GET['ordre_id']) * 1;
    $fokus = if_isset($_GET['fokus']);
    $vare_lev_id = if_isset($_GET['leverandor']);
    $vis_samlevarer = if_isset($_GET['vis_samlevarer']);
    setcookie("saldi", $returside, $ordre_id, $fokus, $vare_lev_id);
}
if ($funktion = if_isset($_GET['funktion'])) {
    $funktion(if_isset($_GET['sort']), if_isset($_GET['fokus']), $id, if_isset($_GET['vis_kost']), '', if_isset($_GET['find']), 'varekort.php');
}
if ($konto_id = if_isset($_GET['konto_id'])) {
    db_modify("insert into vare_lev (lev_id, vare_id, posnr) values ('$konto_id', '$id', '1')", __FILE__ . " linje " . __LINE__);
}
if (isset($_GET['vare_id']) && cirkeltjek($_GET['vare_id']) == 0) {
    $vare_id = $_GET['vare_id'];
    if ($vare_id != $id) {
        db_modify("insert into styklister (vare_id, indgaar_i, antal) values ('$vare_id', '$id', '1')", __FILE__ . " linje " . __LINE__);
        db_modify("update varer set delvare =  'on' where id = '$vare_id'", __FILE__ . " linje " . __LINE__);
    }
}
if ($delete_category = if_isset($_GET['delete_category'])) {
    db_modify("delete from grupper where id = '$delete_category'", __FILE__ . " linje " . __LINE__);
}
if ($delete_var_type = if_isset($_GET['delete_var_type'])) {
    db_modify("delete from variant_varer where id = '$delete_var_type'", __FILE__ . " linje " . __LINE__);
    db_modify("delete from lagerstatus where vare_id='$id' and variant_id = '$delete_var_type'", __FILE__ . " linje " . __LINE__);
    db_modify("delete from shop_varer where saldi_id='$id' and saldi_variant = '$delete_var_type'", __FILE__ . " linje " . __LINE__);
}
$rename_category = if_isset($_GET['rename_category']);
$show_subcat = if_isset($_GET['show_subcat']);

$deleteItem = if_isset($_POST['deleteItem']);
$saveItem = if_isset($_POST['saveItem']);
$submit = if_isset($_POST['submit']);

if ($deleteItem == 'Slet') {
    $id = if_isset($_POST['id']);
    db_modify("delete from varer where id = $id", __FILE__ . " linje " . __LINE__);
    db_modify("delete from shop_varer where saldi_id = $id", __FILE__ . " linje " . __LINE__);
    db_modify("delete from vare_lev where vare_id = '$id'", __FILE__ . " linje " . __LINE__);
    print "<meta http-equiv=\"refresh\" content=\"0;URL=varer.php\">";
    exit;
}
$acceptStockChange = if_isset($_POST['acceptStockChange']);
$cancelStockChange = if_isset($_POST['cancelStockChange']);

#$acceptStockChange=1;
if ($acceptStockChange) {
    $initials = if_isset($_POST['initials']);
    $reason = if_isset($_POST['reason']);
    $beholdning = if_isset($_POST['beholdning']);
    $ny_beholdning = if_isset($_POST['ny_beholdning']);
    $stockchange = $ny_beholdning - $beholdning;
    $qtxt = "select kostpris from varer where id=$id";
    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    $cost = $r['kostpris'] * 1;
    $userName = substr($brugernavn, 0, 10);
    $qtxt = "insert into stocklog (item_id,username,initials,reason,correction,logtime) values ";
    $qtxt .= "('$id','" . db_escape_string($userName) . "','" . db_escape_string($initials) . "','" . db_escape_string($reason) . "',";
    $qtxt .= "'" . $stockchange . "','" . date("U") . "')";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    $lagerbeh = if_isset($_POST['lagerbeh'], array());
    $ny_lagerbeh = if_isset($_POST['ny_lagerbeh'], array());
    for ($x = 1; $x <= count($ny_lagerbeh); $x++) {
        #       if ($ny_lagerbeh[$x]!=$lagerbeh[$x]) {
        lagerreguler($id, $ny_lagerbeh[$x], $cost, $x, date("Y-m-d"), '0');
        #       }
    }
    print "<meta http-equiv='refresh' content='0;URL=varekort.php?id=$id'>";
    exit;
} elseif ($cancelStockChange) {
    print "<meta http-equiv='refresh' content='0;URL=varekort.php?id=$id'>";
    exit;
}
if (isset($_POST['copy']) && $_POST['copy'])
    $submit = 'copy';
if (isset($_POST['supplierLookUp']) && $_POST['supplierLookUp'])
    $submit = 'supplierLookUp';

if ($saveItem || $submit = trim($submit)) {
    $id = if_isset($_POST['id']);
    $beskrivelse = if_isset($_POST['beskrivelse']);
    $beskrivelse[0] = trim(if_isset($_POST['beskrivelse0'])); # fordi fokus ikke fungerer på array navne
    $grossWeight = usdecimal((isset($_POST['grossWeight']) ? $_POST['grossWeight'] : 0), 3);
    $grossWeightUnit = if_isset($_POST['grossWeightUnit'], '');
    $varenr = db_escape_string(trim(if_isset($_POST['varenr'])));
    $stregkode = db_escape_string(trim(if_isset($_POST['stregkode'])));
    $oldDescription = trim(if_isset($_POST['oldDescription']));
    $enhed = db_escape_string(trim(if_isset($_POST['enhed'])));
    $enhed2 = db_escape_string(trim(if_isset($_POST['enhed2'])));
    $forhold = usdecimal(if_isset($_POST['forhold']), 2);
    $salgspris = usdecimal(if_isset($_POST['salgspris']), 2);
    $salgspris2 = usdecimal(if_isset($_POST['salgspris2']), 2);
    $kostpris = if_isset($_POST['kostpris']);
    $gl_kostpris = if_isset($_POST['gl_kostpris']);
    $commissionItem = if_isset($_POST['commissionItem']);
    $kostpris2 = if_isset($_POST['kostpris2']);
    $montage = usdecimal(if_isset($_POST['montage']), 2); # montagepris til stillads
    $demontage = usdecimal(if_isset($_POST['demontage']), 2); # demontagepris til stillads
    $netWeight = usdecimal((isset($_POST['netWeight']) ? $_POST['netWeight'] : 0), 3);
    $netWeightUnit = if_isset($_POST['netWeightUnit'], '');
    $length = usdecimal(if_isset($_POST['length'], 0), 0);
    $width = usdecimal(if_isset($_POST['width'], 0), 0);
    $height = usdecimal(if_isset($_POST['height'], 0), 0);
    $indhold = usdecimal(if_isset($_POST['indhold'], 0), 2);
    $provisionsfri = trim(if_isset($_POST['provisionsfri']));
    $publiceret = if_isset($_POST['publiceret']);
    $publ_pre = if_isset($_POST['publ_pre']);
    list($leverandor) = explode(':', if_isset($_POST['leverandor']));
    $vare_lev_id = if_isset($_POST['vare_lev_id']);
    $lev_varenr = if_isset($_POST['lev_varenr']);
    $lev_antal = if_isset($_POST['lev_antal']);
    $lev_pos = if_isset($_POST['lev_pos']);
    $gruppe = (int) if_isset($_POST['gruppe']);
    $ny_gruppe = if_isset($_POST['ny_gruppe']);
    $dvrg_nr[0] = if_isset($_POST['dvrg']) * 1; # DebitorVareRabatGruppe
    $prisgruppe = if_isset($_POST['prisgruppe']) * 1;
    $tilbudgruppe = if_isset($_POST['tilbudgruppe']) * 1;
    $rabatgruppe = if_isset($_POST['rabatgruppe']) * 1;
    $operation = if_isset($_POST['operation']) * 1;
    $min_lager = if_isset($_POST['min_lager']);
    $max_lager = if_isset($_POST['max_lager']);
    $volume_lager = if_isset($_POST['volume_lager']);
    $beholdning = if_isset($_POST['beholdning']);
    $ny_beholdning = if_isset($_POST['ny_beholdning']);
    $lukket = if_isset($_POST['lukket']);
    $serienr = db_escape_string(trim(if_isset($_POST['serienr'])));
    #   list ($gruppe)           =  explode (':', if_isset($_POST['gruppe']));
    $notes = db_escape_string(trim(if_isset($_POST['notes'])));
    $notesInternal = db_escape_string(trim(if_isset($_POST['notesInternal'])));
    $ordre_id = if_isset($_POST['ordre_id']);
    $returside = if_isset($_POST['returside']);
    $fokus = if_isset($_POST['fokus']);
    $vare_sprogantal = if_isset($_POST['vare_sprogantal']);
    $vare_sprog_id = if_isset($_POST['vare_sprog_id']);
    $vare_tekst_id = if_isset($_POST['vare_tekst_id']);
    $trademark = db_escape_string(trim(if_isset($_POST['trademark'])));
    $retail_price = usdecimal(if_isset($_POST['retail_price']), 2);
    $oldRetailPrice .= if_isset($_POST['oldRetailPrice'], 0);
    $specialType = if_isset($_POST['specialType']);
    $special_price = usdecimal(if_isset($_POST['special_price']), 2);
    $tier_price = usdecimal(if_isset($_POST['tier_price']), 2);
    $special_from_date = usdate(if_isset($_POST['special_from_date']));
    $special_to_date = usdate(if_isset($_POST['special_to_date']));
    $special_from_time = if_isset($_POST['special_from_time']);
    $special_to_time = if_isset($_POST['special_to_time']);
    $colli = usdecimal(if_isset($_POST['colli']), 2);
    $colli_webfragt = usdecimal(if_isset($_POST['colli_webfragt']), 2);
    $outer_colli = usdecimal(if_isset($_POST['outer_colli']), 2);
    $open_colli_price = usdecimal(if_isset($_POST['open_colli_price']), 2);
    $outer_colli_price = usdecimal(if_isset($_POST['outer_colli_price']), 2);
    $campaign_cost = usdecimal(if_isset($_POST['campaign_cost']), 2);
    $folgevarenr = db_escape_string(trim(if_isset($_POST['folgevarenr'])));
    $location = db_escape_string(trim(if_isset($_POST['location'])));
    $numberOfStocks = if_isset($_POST['lagerantal']);
    $lagerid = if_isset($_POST['lagerid']);
    $lagerlok = if_isset($_POST['lagerlok']);
    $m_type = if_isset($_POST['m_type']);
    $m_rabat_array = if_isset($_POST['m_rabat_array'], array());
    $m_antal_array = if_isset($_POST['m_antal_array'], array());
    $kat_valg = if_isset($_POST['kat_valg']);
    $kat_id = if_isset($_POST['kat_id']);
    $ny_kategori = if_isset($_POST['ny_kategori']);
    $rename_category = if_isset($_POST['rename_category']);
    $vare_varianter = if_isset($_POST['vare_varianter']);
    $useVariants = $vare_varianter;
    $varianter_id = if_isset($_POST['varianter_id'], array());
    $var_type = if_isset($_POST['var_type']);
    $var_type_beh = if_isset($_POST['var_type_beh']);
    $var_type_stregk = if_isset($_POST['var_type_stregk']);
    $variant_vare_id = if_isset($_POST['variant_vare_id']);
    $variant_vare_stregkode = if_isset($_POST['variant_vare_stregkode']);
    $variantVarerQty = if_isset($_POST['variant_varer_beholdning'], array());
    $lagerbeh = if_isset($_POST['lagerbeh']);
    $ny_lagerbeh = if_isset($_POST['ny_lagerbeh'], array());
    #20221004
    $on_price_list = if_isset($_POST['on_price_list']);
    $tier_price_multiplier = usdecimal(if_isset($_POST['tier_price_multiplier']), 2);
    $tier_price_method = if_isset($_POST['tier_price_method']);
    $tier_price_rounding = if_isset($_POST['tier_price_rounding']);
    $salgspris_multiplier = usdecimal(if_isset($_POST['salgspris_multiplier']), 2);
    $salgspris_method = if_isset($_POST['salgspris_method']);
    $salgspris_rounding = if_isset($_POST['salgspris_rounding']);
    $retail_price_multiplier = usdecimal(if_isset($_POST['retail_price_multiplier']), 2);
    $retail_price_method = if_isset($_POST['retail_price_method']);
    $retail_price_rounding = if_isset($_POST['retail_price_rounding']);
    $show_advanced_price_calc = if_isset($_POST['show_advanced_price_calc']);

    if (!$kat_id)
        $kat_id = array();
    if (!$variant_vare_id)
        $variant_vare_id = array();
    ######### Kategorier #########
    for ($x = 0; $x <= count($kat_id); $x++) {
        #cho "KV $kat_valg[$x]<br>";
        if (isset($kat_valg[$x]) && $kat_valg[$x]) {
            ($kategori) ? $kategori .= chr(9) . $kat_id[$x] : $kategori = $kat_id[$x];
        }
    }
    $cat = explode("|", $ny_kategori);
    $niveau = (count($cat) - 1);
    $ny_kategori = $cat[$niveau];
    if ($niveau) {
        $master = $cat[0];
    } else
        $master = NULL;
    $tmp = findtekst(343, $sprog_id);
    $ny_kategori = trim($ny_kategori);
    $master = trim($master);

    if ($commissionItem && $kostpris[0] == '0,00' && $salgspris > 0) {
        $qtxt = "select var_value from settings where var_name='defaultProvision' and var_grp='items'";
        if ($r = db_fetch_array($q = db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $kostpris[0] = 1 - $r['var_value'] / 100;
        } else
            $kostpris[0] = 0;
    } else
        $kostpris[0] = usdecimal($kostpris[0], 3);

    if ($commissionItem && $kostpris[0] > '0' && $salgspris == 0) {
        $qtxt = "select var_value from settings where var_grp = 'items' and var_name = 'commissionInclVat'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
            $commissionInclVat = $r['var_value'];
        else
            $commissionInclVat = NULL;
        if ($commissionInclVat) {
            $qtxt = "select var_value from settings where var_grp = 'items' and var_name = 'commissionAccountUsed'";
            if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
                $commissionAccountUsed = $r['var_value'];
            if ($commissionAccountUsed) {
                $qtxt = "select moms from kontoplan where kontonr = '$commissionAccountUsed' and regnskabsaar = '$regnaar'";
                $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
                $vatType = substr($r['moms'], 0, 1) . 'M';
                $vatNo = substr($r['moms'], 1);
                $qtxt = "select box2 from grupper where art = '$vatType' and kodenr = '$vatNo' and fiscal_year = '$regnaar'";
                $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
                $commissionAccountVat = $r['box2'];
            }
        }
        if ($commissionInclVat)
            $kostpris[0] += $kostpris[0] / 100 * $commissionAccountVat;
    }

    $qtxt = "select * from grupper where art='VG' and kodenr = '$gruppe' and fiscal_year = '$regnaar'";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $stockItem = if_isset($r['box8'], 0);
        $batchItem = if_isset($r['box9'], 0);
    }
    $qtxt = "select box4 from grupper where art='API'";
    $api_fil = NULL;
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
        $api_fil = trim($r['box4']);
    transaktion('begin');
    $begin = 1;
    if ($ny_kategori && $ny_kategori != $tmp) {
        $x = 0;
        $qtxt = "select id,box2 from grupper where art='V_CAT' and box1='" . db_escape_string($ny_kategori) . "' and box2='$master'";
        $r = db_fetch_array($q = db_select($qtxt, __FILE__ . " linje " . __LINE__));
        #       $master_id=$r['id']*1;
        if (!$rename_category && $r = db_fetch_array($q = db_select("select id from grupper where art='V_CAT' and lower(box1) = '" . db_escape_string(strtolower($ny_kategori)) . "' and box2='$master'", __FILE__ . " linje " . __LINE__))) {
            $alerttekst = findtekst(344, $sprog_id);
            $alerttekst = str_replace('$ny_kategori', $ny_kategori, $alerttekst);
            print "<BODY onLoad=\"javascript:alert('$alerttekst')\">\n";
        } elseif ($rename_category) {
            db_modify("update grupper set box1='" . db_escape_string($ny_kategori) . "' where id='$rename_category'", __FILE__ . " linje " . __LINE__);
            $rename_category = 0;
        } else {
            $box2 = NULL;
            $qtxt = "select box2 from grupper where id='$master'";
            if ($master && $r = db_fetch_array($q = db_select($qtxt, __FILE__ . " linje " . __LINE__)))
                $box2 = $r['box2'];
            if ($box2)
                $master = $box2 . chr(9) . $master;
            db_modify("insert into grupper(beskrivelse,art,box1,box2) values ('Varekategorier','V_CAT','" . db_escape_string($ny_kategori) . "','$master')", __FILE__ . " linje " . __LINE__);
            $r = db_fetch_array($q = db_select("select id from grupper where art='V_CAT' and lower(box1)= '" . db_escape_string(strtolower($ny_kategori)) . "' and box2='$master'", __FILE__ . " linje " . __LINE__));
            ($kategori) ? $kategori .= chr(9) . $r['id'] : $kategori = $r['id'];
        }
        $r = db_fetch_array(db_select("select box4 from grupper where art='API'", __FILE__ . " linje " . __LINE__));
        if ($shopurl && $shopurl != "!") {
            $url = $shopurl . "/opdat_shop_kat.php";
            #           print "<body onload=\"javascript:window.open('$url','opdat:kat');\">";
        }
    }
    if ($id && is_array($lagerlok)) {
        for ($x = 1; $x <= count($lagerlok); $x++) {
            $qtxt = "select id from lagerstatus where vare_id='$id' and lager='$x' limit 1";
            if ($r = db_fetch_array($q = db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                $qtxt = "update lagerstatus set lok1='" . db_escape_string($lagerlok[$x]) . "' where vare_id='$id' and lager='$x'";
                db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            } #else $qtxt="insert into lagerstatus (vare_id,lager,lok1) values ('$id','$x','$lagerlok[$x]')";
        }
    }

    ######### varianter #########
/*
    $variant=NULL;
    for ($x=0;$x<=count($varianter_id);$x++) {
    if (isset($vare_varianter[$x]) && $vare_varianter[$x]) {
                ($variant)?$variant.=chr(9).$varianter_id[$x]:$variant=$varianter_id[$x];
        }
    }
    if (!$variant) { Do not enable, please!
        db_modify("update batch_kob set variant_id='0' where vare_id='$id' and variant_id!='0'",__FILE__ . " linje " . __LINE__);
        db_modify("update batch_salg set variant_id='0' where vare_id='$id' and variant_id!='0'",__FILE__ . " linje " . __LINE__);
        db_modify("update lagerstatus set variant_id='0' where vare_id='$id' and variant_id!='0'",__FILE__ . " linje " . __LINE__);
    }
*/
    for ($x = 0; $x < count($variant_vare_id); $x++) {
        if (!isset($variantVarerQty[$x]))
            $variantVarerQty[$x] = array();
        for ($l = 1; $l <= count($variantVarerQty[$x]); $l++) {
            $variantVarerQty[$x][$l] = (float)usdecimal($variantVarerQty[$x][$l], 2);
            if ($variant_vare_stregkode[$x]) {
                $qtxt = "select vare_id from variant_varer where variant_stregkode='$variant_vare_stregkode[$x]' and id !='$variant_vare_id[$x]'";
                if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                    $qtxt = "select varenr,beskrivelse from varer where id = '$r[vare_id]'";
                    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
                    alert("Stregkode allerede er brugt i varenr: $r[varenr] $r[beskrivelse]");
                } else {
                    $qtxt = "update variant_varer set variant_stregkode='$variant_vare_stregkode[$x]' where id='$variant_vare_id[$x]'";
                    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                }
            } else
                alert('Stregkode må ikke slettes');
            $qtxt = "select id,beholdning from lagerstatus where vare_id='$id' and variant_id='$variant_vare_id[$x]' and lager='$l'";
            $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
            transaktion('begin');
            $lager = $l;
            lagerreguler($id, $variantVarerQty[$x][$l], $kostpris[0], $lager, date('Y-m-d'), $variant_vare_id[$x]);
            transaktion('commit');
        }
    }
    // When processing the form submission:
    if (isset($_POST['var_type']) && isset($_POST['var_type_stregk'])) {
        // Initialize empty array to store selected variant types
        $selected_types = array();

        // Process each variant type selection
        for ($x = 0; $x < count($_POST['var_type']); $x++) {
            // Only include non-empty selections
            if (!empty($_POST['var_type'][$x])) {
                $selected_types[] = $_POST['var_type'][$x];
            }
        }

        // Create tab-separated string of selected variant types
        $ny_variant_type = implode(chr(9), $selected_types);

        // Get the barcode
        $var_type_stregk = $_POST['var_type_stregk'];

        // Only proceed if we have at least one variant type selected and a barcode
        if (!empty($ny_variant_type) && !empty($var_type_stregk)) {
            // Check if this variant or barcode already exists
            $qtxt = "select id from variant_varer where vare_id='$id' and (variant_type='$ny_variant_type' or variant_stregkode='$var_type_stregk')";
            if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                alert("stregkode $var_type_stregk eller variant allerde allerede i brug.");
            } else {
                // Set initial inventory for this variant
                ($variantsum) ? $var_type_beh = 0 : $var_type_beh = $beholdning * 1;

                // Insert the new variant
                $qtxt = "insert into variant_varer(vare_id,variant_type,variant_stregkode,variant_beholdning) values ('$id','$ny_variant_type','$var_type_stregk','0')";
                db_modify($qtxt, __FILE__ . " linje " . __LINE__);

                // Get the newly created variant ID
                $qtxt = "select id from variant_varer where vare_id='$id' and variant_type='$ny_variant_type' and variant_stregkode = '$var_type_stregk'";
                $r = db_fetch_array($q = db_select($qtxt, __FILE__ . " linje " . __LINE__));
                $ny_variant_id = $r['id'];

                // Initialize inventory status for this variant
                $qtxt = "insert into lagerstatus (vare_id,variant_id,lager,beholdning) values ('$id','$ny_variant_id','1','0')";
                db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            }
        } else if (!empty($var_type_stregk)) {
            alert("Vælg mindst én variant type.");
        }
    }


    if (($publ_pre || $publiceret) && $shopurl) {
        $kategorier = explode(chr(9), $kategori);
        $shop_kat_id = '';
        for ($x = 0; $x < count($kategorier); $x++) {
            if ($kategorier[$x] && $r = db_fetch_array($q = db_select("select box3 from grupper where id='$kategorier[$x]'", __FILE__ . " linje " . __LINE__))) {
                ($shop_kat_id) ? $shop_kat_id .= chr(9) . $r['box3'] : $shop_kat_id = $r['box3'];
            }
        }
    }
    ######### Følgevarer #########
    if ($folgevarenr) {
        $r = db_fetch_array(db_select("select id from varer where varenr = '$folgevarenr'", __FILE__ . " linje " . __LINE__));
        if ($r['id'])
            $folgevare = $r['id'] * 1;
        elseif (substr($folgevarenr, 0, 4) == 'MENU') {
            $folgevare = substr($folgevarenr, 4) * -1;
        } else
            print "<BODY onLoad=\"javascript:alert('Varenummer $folgevarenr eksisterer ikke!')\">";
    }
    /* 20220215 Is this used for anything???
        if ($rabatgruppe) {
            $r=db_fetch_array(db_select("select * from grupper where art='VRG' and kodenr = '$rabatgruppe'",__FILE__ . " linje " . __LINE__));
            $m_type=$r['box1'];
            $m_rabat_array=explode(";",$r['box2']);
            $m_antal_array=explode(";",$r['box3']);     
        }
    */
    if ($ny_gruppe && $ny_gruppe != $gruppe) {
        $r = db_fetch_array(db_select("select box8 from grupper where art='VG' and kodenr = '$ny_gruppe' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
        $tmp1 = ($r['box8']);
        $r = db_fetch_array(db_select("select box8 from grupper where art='VG' and kodenr = '$gruppe' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
        $tmp2 = ($r['box8']);
        if ($tmp1 == 'on' && $tmp1 != $tmp2) {
            $r = db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$id'", __FILE__ . " linje " . __LINE__));
            $ny_beholdning = $r['antal'] * 1;
            $r = db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$id'", __FILE__ . " linje " . __LINE__));
            $ny_beholdning = $ny_beholdning - $r['antal'];
            db_modify("update varer set beholdning='$ny_beholdning' where id = '$id'", __FILE__ . " linje " . __LINE__);
            $beholdning = $ny_beholdning;
        } elseif ($tmp1 != 'on') {
            $r = db_fetch_array(db_select("select box12 from grupper where art = 'POS' and kodenr = '2'", __FILE__ . " linje " . __LINE__));
            if (trim($r['box12'])) {
                db_modify("update varer set beholdning='0' where id = '$id'", __FILE__ . " linje " . __LINE__);
                $beholdning = 0;
                $ny_beholdning = 0;
            }
        } else
            db_modify("update varer set beholdning='0' where id = '$id'", __FILE__ . " linje " . __LINE__);
        db_modify("update varer set gruppe = '$ny_gruppe' where id = '$id'", __FILE__ . " linje " . __LINE__);
        $gruppe = $ny_gruppe;
    }

    ######### Moms #########
/*
    if ($provisionPercentage >= 0) {
        $kostpris[0] = calculateCost($kostpris[0], $salgspris, $provisionPercentage);
    }
*/
    $incl_moms = $vatOnItemCard = 0;
    $qtxt = "select var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
        $vatOnItemCard = $r['var_value'];
    if ($vatOnItemCard) {
        $incl_moms = vatPercent($gruppe);
        $salgspris *= 100 / (100 + $incl_moms);
        $salgspris2 *= 100 / (100 + $incl_moms);
        if ($specialType != 'percent')
            $special_price *= 100 / (100 + $incl_moms);
    }

    # Genererer tekststrenge med maengderabatter - decimaltaltal rettes til "us" og felter med antal "0" fjernes.
    for ($x = 0; $x < count($m_rabat_array); $x++) {
        if (!isset($m_rabat_array[$x]))
            $m_rabat_array[$x] = 0;
        if (!isset($m_antal_array[$x]))
            $m_antal_array[$x] = 0;
        $tmp1 = usdecimal($m_rabat_array[$x], 8) * 1;
        $tmp2 = usdecimal($m_antal_array[$x], 8) * 1;
        if ($incl_moms && $m_type != "percent")
            $tmp1 *= 100 / (100 + $incl_moms);

        if ($tmp1 && $tmp2) {
            if ($m_antal) {
                $m_rabat = $m_rabat . ";" . $tmp1;
                $m_antal = $m_antal . ";" . $tmp2;
            } else {
                $m_rabat = $tmp1;
                $m_antal = $tmp2;
            }
        }
    }
    if ($prisgruppe) {
        $r = db_fetch_array(db_select("select * from grupper where art='VPG' and kodenr = '$prisgruppe'", __FILE__ . " linje " . __LINE__));
        if ($r['box1'])
            $kostpris[0] = (float) $r['box1'];
        if ($r['box2'])
            $salgspris = (float) $r['box2'];
        if ($r['box3'])
            $retail_price = (float) $r['box3'];
        if ($r['box4'])
            $tier_price = (float) $r['box4'];
    }
    ######## Styklister ->
    $delvare = if_isset($_POST['delvare']);
    $samlevare = if_isset($_POST['samlevare']);
    $fokus = if_isset($_POST['fokus']);
    $be_af_ant = if_isset($_POST['be_af_ant'], array());
    $be_af_id = if_isset($_POST['be_af_id'], array());
    $ant_be_af = if_isset($_POST['ant_be_af'], 0);
    $indg_i_id = if_isset($_POST['indg_i_id'], array());
    $indg_i_ant = if_isset($_POST['indg_i_ant'], array());
    $ant_indg_i = if_isset($_POST['ant_indg_i'], 0);
    $indg_i_pos = if_isset($_POST['indg_i_pos'], array());
    $be_af_pos = if_isset($_POST['be_af_pos'], array());
    $be_af_vare_id = if_isset($_POST['be_af_vare_id'], array());
    $be_af_vnr = if_isset($_POST['be_af_vnr'], array());
    $be_af_beskrivelse = if_isset($_POST['be_af_beskrivelse'], array());


    #   if ($deleteItem=='Slet') {
#       db_modify("delete from varer where id = $id",__FILE__ . " linje " . __LINE__);
#       db_modify("delete from shop_varer where saldi_id = $id",__FILE__ . " linje " . __LINE__);
#       transaktion ('commit');
#       print "<meta http-equiv=\"refresh\" content=\"0;URL=varer.php\">";
#   }   else {
    if (($salgspris == 0) && ($salgspris2 > 0) && ($forhold > 0))
        $salgspris = $salgspris2 * $forhold;
    if ($salgspris >= 100000000000) {
        print tekstboks("Salgspris " . dkdecimal($salgspris, 2) . " for høj - nulstillet");
        $salgspris = 0;
    }
    for ($x = 1; $x <= $lev_antal; $x++) {
        if (($lev_pos[$x] != "-") && ($lev_pos[$x])) {
            $lev_pos[$x] = $lev_pos[$x] * 1;
            if (($kostpris[$x] == 0) && ($kostpris2[$x] > 0) && ($forhold > 0))
                $kostpris[$x] = $kostpris2[$x] * $forhold;
            $kostpris[$x] = usdecimal($kostpris[$x], 2);
            $lev_varenr[$x] = db_escape_string(trim($lev_varenr[$x]));
            db_modify("update vare_lev set posnr = $lev_pos[$x], lev_varenr = '$lev_varenr[$x]', kostpris = '$kostpris[$x]' where id = '$vare_lev_id[$x]'", __FILE__ . " linje " . __LINE__);
        } elseif (!$lev_pos[$x]) {
            print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en leverand&oslash;r!')\">";
        } else
            db_modify("delete from vare_lev where id = '$vare_lev_id[$x]'", __FILE__ . " linje " . __LINE__);
    }
    for ($x = 1; $x <= $vare_sprogantal; $x++) {
        $tmp = db_escape_string($beskrivelse[$x]);
        $qtxt = '';
        if ($vare_tekst_id[$x])
            $qtxt = "update varetekster set tekst='$tmp' where id='$vare_tekst_id[$x]'";
        elseif ($id && $vare_sprog_id[$x] && $tmp) {
            $qtxt = "insert into varetekster(vare_id,sprog_id,tekst) values ('$id','$vare_sprog_id[$x]','$tmp')";
        }
        if ($qtxt)
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    }

    if (!$min_lager) {
        $query = db_select("SELECT var_value FROM settings WHERE var_name = 'min_beholdning' AND var_grp = 'productOptions'", __FILE__ . " linje " . __LINE__);
        if (db_num_rows($query) > 0) {
            $r = db_fetch_array($query);
            $min_lager = (int) $r["var_value"];
        } else {
            $min_lager = 0;
        }
    } else {
        $min_lager = usdecimal($min_lager, 2);
    }
    if (!$max_lager)
        $max_lager = '0';
    else
        $max_lager = usdecimal($max_lager, 2);
    if (!$volume_lager)
        $volume_lager = '0';
    else
        $volume_lager = usdecimal($volume_lager, 2);

    if (!$lukket)
        $lukket = '0';
    else
        $lukket = '1';
    if (count($indg_i_ant) && strlen(trim($indg_i_ant[0])) > 1) {
        list($x) = explode(':', $indg_i_ant[0]);
        #           $fejl=cirkeltjek($x, 'vare_id');
    }
    if (isset($be_af_ant[0]) && strlen(trim($be_af_ant[0])) > 1) {
        list($x) = explode(':', $be_af_ant[0]);
    }
    if ($delvare == 'on' && ($gl_kostpris - $kostpris[0] != 0 || $oldRetailPrice - $retail_price != 0)) {
        $costDiff = $kostpris[0] - $gl_kostpris;
        $retailDiff = $retail_price - $oldRetailPrice;
        include_once('productCardIncludes/updateParentPrice.php');
        updateParentPrice($id, $costDiff, $retailDiff);
    }
    if (!$fejl) {
        if (($samlevare != 'on') && ($ant_be_af > 0)) {
            print "Du skal s&aelig;tte antal til 0 p&aring; samtlige varer som denne vare best&aring;r af, f&oslash;r du fjerner fluebenet i \"samlevare\"!<br>";
            $samlevare = 'on';
        }
        if (!$betalingsdage) {
            $betalingsdage = 0;
        }
        if ($id == 0) {
            if (strpos($varenr, "'")) {
                $alerttxt = "Ikke tilladt tegn i varenummer: Apostrof fjernet";
                alert("$alerttxt");
                $varenr = str_replace("'", "", $varenr);
            }
            $query = db_select("select id from varer where lower(varenr) = '" . strtolower($varenr) . "' or  upper(varenr) = '" . strtoupper($varenr) . "'", __FILE__ . " linje " . __LINE__);
            $row = db_fetch_array($query);
            if ($row['id']) {
                print "<BODY onLoad=\"javascript:alert('Der findes allerede en vare med varenr: $varenr!')\">";
                $varenr = '';
                $id = 0;
            } elseif ($varenr) {
                $query = db_select("SELECT var_value FROM settings WHERE var_name = 'min_beholdning' AND var_grp = 'productOptions'", __FILE__ . " linje " . __LINE__);
                if (db_num_rows($query) > 0) {
                    $r = db_fetch_array($query);
                    $minBeholdning = (int) $r["var_value"];
                    db_modify("insert into varer (varenr,lukket,salgspris,kostpris,min_lager) values ('$varenr','0','0','0',$minBeholdning)", __FILE__ . " linje " . __LINE__);
                } else {
                    db_modify("insert into varer (varenr,lukket,salgspris,kostpris) values ('$varenr','0','0','0')", __FILE__ . " linje " . __LINE__);
                }
                $query = db_select("select id from varer where varenr = '$varenr'", __FILE__ . " linje " . __LINE__);
                $row = db_fetch_array($query);
                $id = $row['id'];
                if ($vare_lev_id) {
                    db_modify("insert into vare_lev (lev_id, vare_id, posnr) values ($vare_lev_id, $id, 1)", __FILE__ . " linje " . __LINE__);
                }
                $qtxt = "select id,var_value from settings where var_name = 'defaultCommission' and var_grp = 'items'";
                if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                    $provision = $r['var_value'];
                    $kostpris[0] = 1 - $provision / 100;
                    $qtxt = "update varer set kostpris = '$kostpris[0]', provision = '$provision' where id = '$id'";
                    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                }
            } else
                print "<BODY onLoad=\"javascript:alert('Skriv et varenummer i feltet og pr&oslash;v igen!')\">";
        } elseif ($id > 0) {
            if (!$leverandor)
                $leverandor = '0';
            if ($stregkode) {
                if ($r = db_fetch_array(db_select("select varenr,beskrivelse from varer where stregkode='$stregkode' and id !='$id'", __FILE__ . " linje " . __LINE__))) {
                    print "<BODY onLoad=\"javascript:alert('Varenr: $r[varenr] | $r[beskrivelse] har samme stregkode')\">";
                    $stregkode = '';
                }
            }
            if ($indhold != round($indhold, 0)) { # 
                $tmp = $indhold;
                db_modify("update varer set indhold='$tmp' where id = '$id'", __FILE__ . " linje " . __LINE__);
                $r = db_fetch_array(db_select("select indhold from varer where id='$id'", __FILE__ . " linje " . __LINE__));
                if ($r['indhold'] != $indhold) {
                    db_modify("ALTER TABLE varer ALTER COLUMN indhold TYPE numeric(15,3)", __FILE__ . " linje " . __LINE__);
                }
            }
            if ($special_from_time) {
                list($t, $m) = explode(":", $special_from_time);
                $t *= 1;
                $t *= 1;
                if ($t > 23)
                    $t = 23;
                if ($m > 59)
                    $m = 59;
                $special_from_time = $t . ":" . $m . ":00";
                list($t, $m) = explode(":", $special_to_time);
                $t *= 1;
                $t *= 1;
                if ($t > 23)
                    $t = 23;
                if ($m > 59)
                    $m = 59;
                if ($m < 59)
                    $s = '00';
                else
                    $s = 59;
                $special_to_time = $t . ":" . $m . ":" . $s;
            } else {
                $special_from_time = "00:00:00";
                $special_to_time = "00:00:00";
            }
            $provision = 0;
            if ($commissionItem) {
                if ($kostpris[0] && $salgspris) {
                    $qtxt = "select kostpris from varer where id = '$id'";
                    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
                        $oldCost = $r['kostpris'];
                } elseif ($salgspris) {
                    $qtxt = "select salgspris from varer where id = '$id'";
                    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
                        $oldPrice = $r['salgspris'];
                }
                if (isset($_POST['provision']))
                    $newProvision = usdecimal($_POST['provision']);
                $qtxt = "select provision from varer where id = '$id'";
                if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
                    $provision = $r['provision'];
                if ($salgspris == 0 && $kostpris[0] > 0 && $kostpris[0] < 1) {
                    $tmp = 100 * (1 - $kostpris[0]);
                    $provision = 100 * (1 - $kostpris[0]);
                } elseif ($salgspris > 0 && $provision && $oldPrice == 0 && $kostpris[0] == 0) {
                    $kostpris[0] = $salgspris - ($salgspris * $provision / 100);
                } elseif ($salgspris > 0 && ($kostpris[0] != $oldCost || !$provision)) {
                    $provision = 100 - $kostpris[0] * 100 / $salgspris;
                } elseif ($salgspris > 0 && $newProvision != $provision) { #20210401
                    $kostpris[0] = $salgspris - ($salgspris * $newProvision / 100);
                    $provision = $newProvision;
                } elseif ($salgspris > 0 && $provision > 0 && $kostpris[0]) { #20210401
                    $kostpris[0] = $salgspris - ($salgspris * $provision / 100);
                    #                   $provision=0;
                } elseif ($salgspris > 0 && $newProvision != $provision) { #20210401
                    $kostpris[0] = $salgspris - ($salgspris * $newProvision / 100);
                    $provision = $newProvision;
                } else {
                    $alertxt = "For kommissionsvarer skal kostprisen udgøre den del af salgsprisen den tilfalder ejeren.\\n";
                    $alertxt .= "F.eks. ved 15% i provision skal kostprisen være 0,85, når salgspris er sat til 0";
                    #                   alert ($alertxt);
                }
                if (!is_numeric($provision))
                    $provision = 0;
                $provision = afrund($provision, 0);
            }
            /*
                        if ($salgspris_multiplier > 0) {
                            $salgspris = $kostpris[0]*100/$salgspris_multiplier;
                        }
                        if ($tier_price_multiplier > 0) {
                            $tier_price = $kostpris[0]*100/$tier_price_multiplier;
                        }
            */
            $qtxt = "update varer set stregkode='$stregkode',enhed='$enhed',enhed2='$enhed2',indhold='$indhold',";
            $qtxt .= "forhold='$forhold',salgspris = '$salgspris',kostpris = '$kostpris[0]',";
            $qtxt .= "provisionsfri = '$provisionsfri',gruppe = '$gruppe',prisgruppe = '$prisgruppe',";
            $qtxt .= "tilbudgruppe = '$tilbudgruppe',rabatgruppe = '$rabatgruppe',serienr = '$serienr',";
            $qtxt .= "lukket = '$lukket',notes = '$notes', notesinternal='$notesInternal',samlevare='$samlevare',";
            $qtxt .= "min_lager='$min_lager',max_lager='$max_lager',trademark='$trademark',";
            $qtxt .= "retail_price='$retail_price',volume_lager='$volume_lager',special_price='$special_price',";
            $qtxt .= "tier_price='$tier_price',specialtype='$specialType',special_from_date='$special_from_date',";
            $qtxt .= "special_to_date='$special_to_date',special_from_time='$special_from_time',";
            $qtxt .= "special_to_time='$special_to_time',colli='$colli',outer_colli='$outer_colli', colli_webfragt='$colli_webfragt',";
            $qtxt .= "open_colli_price='$open_colli_price',outer_colli_price='$outer_colli_price',";
            $qtxt .= "campaign_cost='$campaign_cost',location='$location',folgevare='$folgevare',montage='$montage',";
            $qtxt .= "demontage='$demontage',m_type='$m_type',m_antal='$m_antal',m_rabat='$m_rabat',";
            $qtxt .= "dvrg='$dvrg_nr[0]',kategori='$kategori',varianter='$variant',publiceret='$publiceret',";
            $qtxt .= "operation='$operation',netweight='$netWeight',grossweight='$grossWeight',";
            $qtxt .= "netweightunit='$netWeightUnit',grossweightUnit='$grossWeightUnit',length='$length',";
            $qtxt .= "width='$width',height='$height',on_price_list='" . (($on_price_list == 1) ? "1" : "0") . "',";// 20221004
            $qtxt .= "tier_price_method='$tier_price_method',tier_price_rounding='$tier_price_rounding',";// 20221004
            $qtxt .= "tier_price_multiplier='$tier_price_multiplier',salgspris_method='$salgspris_method',";// 20221004
            $qtxt .= "salgspris_rounding='$salgspris_rounding',salgspris_multiplier='$salgspris_multiplier',";// 20221004
            $qtxt .= "retail_price_method='$retail_price_method',retail_price_rounding='$retail_price_rounding',";// 20221004
            $qtxt .= "retail_price_multiplier='$retail_price_multiplier',provision='$provision' where id = '$id'";
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            if ($submit == 'copy') {
                $copyItemNo = "kopi_af_$varenr";
                $r = db_fetch_array(db_select("select id from varer where varenr='$copyItemNo'", __FILE__ . " linje " . __LINE__));
                if ($r['id'])
                    alert("ret varenr på kopi af `$varenr` først");
                else {
                   $query = db_select("SELECT string_agg(column_name, ', ') AS all_columns, 
                    string_agg(column_name, ', ') AS dest_columns,
                    string_agg(CASE WHEN column_name = 'varenr' THEN 'varenr || ''_copy''' ELSE column_name END, ', ') AS source_columns
                    FROM information_schema.columns 
                    WHERE table_name = 'varer' AND column_name != 'id'", __FILE__ . " linje " . __LINE__);
                    $row = db_fetch_array($query);
                    $dest_columns = $row['dest_columns'];
                    $source_columns = $row['source_columns'];
                    $copyNr = $varenr . "_copy";
                    // Next, build the INSERT query
                    $qtxt = "INSERT INTO varer ($dest_columns) SELECT $source_columns FROM varer WHERE id = $id";
                    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                    $r = db_fetch_array(db_select("select id from varer where varenr='$copyNr'", __FILE__ . " linje " . __LINE__));
                    $copyId = $r['id'];

                    $query = db_select("SELECT string_agg(column_name, ', ') AS all_columns, 
                    string_agg(CASE WHEN column_name = 'vare_id' THEN 'vare_id' ELSE column_name END, ', ') AS dest_columns,
                    string_agg(CASE WHEN column_name = 'vare_id' THEN '$copyId' ELSE column_name END, ', ') AS source_columns
                    FROM information_schema.columns 
                    WHERE table_name = 'vare_lev' AND column_name != 'id'", __FILE__ . " linje " . __LINE__);

                    $row = db_fetch_array($query);
                    $dest_columns = $row['dest_columns'];
                    $source_columns = $row['source_columns'];

                    // Next, build the INSERT query using those columns
                    $qtxt = "INSERT INTO vare_lev ($dest_columns) SELECT $source_columns FROM vare_lev WHERE vare_id = $id";

                    // Execute the INSERT query
                    db_modify($qtxt, __FILE__ . " linje " . __LINE__);

                    if ($samlevare == "on"){
                        $query = db_select("SELECT string_agg(column_name, ', ') AS all_columns, 
                        string_agg(CASE WHEN column_name = 'indgaar_i' THEN 'indgaar_i' ELSE column_name END, ', ') AS dest_columns,
                        string_agg(CASE WHEN column_name = 'indgaar_i' THEN '$copyId' ELSE column_name END, ', ') AS source_columns
                        FROM information_schema.columns 
                        WHERE table_name = 'styklister' AND column_name != 'id'", __FILE__ . " linje " . __LINE__);

                        $row = db_fetch_array($query);
                        $dest_columns = $row['dest_columns'];
                        $source_columns = $row['source_columns'];

                        // Next, build the INSERT query using those columns
                        $qtxt = "INSERT INTO styklister ($dest_columns) SELECT $source_columns FROM styklister WHERE indgaar_i = $id";

                        // Execute the INSERT query
                        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                    }
                }
            }
            $dd = date("Y-m-d");
            /*
            $qtxt="select id,kostpris,transdate from kostpriser where vare_id='$id' order by transdate desc limit 1";
            $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
            $qtxt=NULL;
            if ($r['transdate'] != $dd && $r['kostpris'] != $kostpris[0]) {
                $qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$id','$kostpris[0]','$dd')";
            } elseif ($r['transdate'] == $dd && $r['kostpris'] != $kostpris[0]) {
                $qtxt="update kostpriser set kostpris=$kostpris[0] where id = '$r[id]'";
            }
            */
            if ($kostpris[0]) {
                include_once('../lager/productsIncludes/updateProductPrice.php');
                updateProductPrice($id, $kostpris[0], $dd);
            }
            if (($operation) && ($r = db_fetch_array(db_select("select varenr from varer where operation = '$operation' and id !=$id", __FILE__ . " linje " . __LINE__)))) {
                print "<BODY onLoad=\"javascript:alert('Operationsnr: $operation er i brug af $r[varenr]! Operationsnr ikke &aelig;ndret')\">";
            } elseif ($operation) {
                $r = db_fetch_array(db_select("select box10 from grupper where art='VG' and kodenr = '$gruppe' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
                if ($r['box10'] != 'on')
                    $operation = 0;
                db_modify("update varer set operation = '$operation' where id = '$id'", __FILE__ . " linje " . __LINE__);
            }

            ######################################## Stykliste ############################################
            if ($samlevare == 'on' && $ant_be_af > 0) {
                for ($x = 1; $x <= $ant_be_af; $x++) {
                    $be_af_ant[$x] = usdecimal($be_af_ant[$x], 2);
                    if ($be_af_pos[$x] == "-")
                        $be_af_ant[$x] = 0;
                    if (($be_af_ant[$x] > 0) && ($be_af_pos[$x])) {
                        $be_af_pos[$x] = round($be_af_pos[$x]);
                        $qtxt = "update styklister set antal = $be_af_ant[$x], posnr = $be_af_pos[$x] where id = '$be_af_id[$x]'";
                        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                    } else {
                        db_modify("delete from styklister where id = '$be_af_id[$x]'", __FILE__ . " linje " . __LINE__);
                    }
                }
                if (($be_af_vnr[0]) || ($be_af_beskrivelse[0])) {
                    $be_af_pos[0] = round($be_af_pos[0]);
                    if (($be_af_vnr[0]) && ($be_af_beskrivelse[0]))
                        $query = db_select("select id from varer where varenr = '$be_af_vnr[0]' or beskrivelse = '$be_af_beskrivelse[0]'", __FILE__ . " linje " . __LINE__);
                    elseif ($be_af_vnr[0])
                        $query = db_select("select id from varer where varenr = '$be_af_vnr[0]'", __FILE__ . " linje " . __LINE__);
                    elseif ($be_af_beskrivelse[0])
                        $query = db_select("select id from varer where beskrivelse = '$be_af_beskrivelse[0]'", __FILE__ . " linje " . __LINE__);
                    if ($row = db_fetch_array($query)) {
                        if (($row['id'] == $id) || (in_array($row['id'], $be_af_vare_id))) {
                        } elseif (cirkeltjek($row['id']) == 0) {
                            db_modify("insert into styklister (vare_id, indgaar_i, antal, posnr) values ('$row[id]', '$id', '1', '$be_af_pos[0]')", __FILE__ . " linje " . __LINE__);
                            db_modify("update varer set delvare =  'on' where id = '$row[id]'", __FILE__ . " linje " . __LINE__);
                        }
                    } elseif (($be_af_vnr[0]) && ($be_af_beskrivelse[0])) {
                        if (!strpos($be_af_vnr[0], "*"))
                            $be_af_vnr[0] = "*" . $be_af_vnr[0] . "*";
                        if (!strpos($be_af_beskrivelse[0], "*"))
                            $be_af_beskrivelse[0] = "*" . $be_af_beskrivelse[0] . "*";
                        $fokus = "varenr";
                        $find = "'" . $be_af_vnr[0] . "' and beskrivelse like '" . $be_af_beskrivelse[0] . "'";
                    } elseif ($be_af_vnr[0]) {
                        if (!strpos($be_af_vnr[0], "*"))
                            $be_af_vnr[0] = "*" . $be_af_vnr[0] . "*";
                        $fokus = "varenr";
                        $find = "'" . $be_af_vnr[0] . "'";
                    } else {
                        if (!strpos($be_af_beskrivelse[0], "*"))
                            $be_af_beskrivelse[0] = "*" . $be_af_beskrivelse[0] . "*";
                        $fokus = "beskrivelse0";
                        $find = "'" . $be_af_beskrivelse[0] . "'";
                    }
                }
                $kostpris[0] = (float) stykliste($id, 0, '');
                db_modify("update varer set kostpris = '$kostpris[0]' where id = '$id'", __FILE__ . " linje " . __LINE__);

            }
            /*
                        if ($delvare=='on') {
                            for ($x=1; $x<=$ant_indg_i; $x++)   {
                                if ($indg_i_ant[$x]>0) {
                                #   $indg_i_ant[$x]=round($indg_i_ant[$x]);
                                    db_modify("update styklister set antal = $indg_i_ant[$x] where id = '$indg_i_id[$x]'",__FILE__ . " linje " . __LINE__);
                                }
                                else {db_modify("delete from styklister where id = '$indg_i_id[$x]'",__FILE__ . " linje " . __LINE__);}
                            }
                            if (strlen(trim($indg_i_ant[0]))>1) {
                                list ($x) = explode(':',$indg_i_ant[0]);
                                $x=trim($x);
                                $query = db_select("select id from varer where varenr = '$x'",__FILE__ . " linje " . __LINE__);
                                $row = db_fetch_array($query);
                                db_modify("insert into styklister (vare_id, indgaar_i, antal) values ($id, $row[id], 1)",__FILE__ . " linje " . __LINE__);
                                db_modify("update varer set samlevare='on' where id = $row[id]",__FILE__ . " linje " . __LINE__);
                            }
                        }
            */
            #############################################################################################
        }
        $leverandor = trim($leverandor);
        if ($leverandor) {
            $query = db_select("select id from adresser where kontonr='$leverandor' and art = 'K'", __FILE__ . " linje " . __LINE__);
            if ($row = db_fetch_array($query)) {
                db_modify("insert into vare_lev (lev_id, vare_id) values ('$row[id]', '$id')", __FILE__ . " linje " . __LINE__);
            }
        }
    }
    #   }
} elseif ($id && isset($_POST['ChangeDescription']) && $_POST['ChangeDescription'] == 'Ja') {
    $newDecsription = db_escape_string($_POST['newDecsription']);
    $qtxt = "update varer set beskrivelse='$newDecsription' where id='$id'";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
for ($x = 1; $x <= count($ny_lagerbeh); $x++) {
    $ny_beholdning += $ny_lagerbeh[$x];
}
if ($confirmStockChange && !$changeStock && $ny_beholdning != $beholdning) {
    include("productCardIncludes/confirmStockChange.php");
}
if ($stockItem) {
    for ($x = 1; $x <= $numberOfStocks; $x++) {
        if ($ny_lagerbeh[$x] != $lagerbeh[$x]) {
            if ($samlevare) {
                print "<meta http-equiv=\"refresh\" content=\"0;URL=vareproduktion.php?id=$id&antal=1&lager=$x&ny_beholdning=$ny_lagerbeh[$x]&samlevare=$samlevare\">";
                exit;
            } elseif (!count($variant_vare_id)) { #20210208
                lagerreguler($id, $ny_lagerbeh[$x], $kostpris[0], $x, date("Y-m-d"), '0');
            }
        } elseif ($api_fil && !count($variant_vare_id)) { #20170210
            sync_shop_vare($id, 0, $x); // 20220203 outcommented lines above 
        }
    }
} else {
    sync_shop_price($id);
}

if ($popup && !$returside)
    $returside = "../includes/luk.php";
elseif (!$returside)
    $returside = "varer.php";
$tekst = findtekst(154, $sprog_id);

if ($begin)
    transaktion('commit');
if ($submit == 'supplierLookUp')
    kontoopslag("navn", $fokus, $id);
elseif (strstr($submit, "Vare")) {
    if (!$sort)
        $sort = "varenr";
    if (!$fokus)
        $fokus = "varenr";
    vareopslag($sort, $fokus, $id, $vis_kost, $ref, $find, "varekort.php");
}
if ($saveItem && $beskrivelse[0] != $oldDescription) {
    if ($confirmDescriptionChange)
        include("productCardIncludes/changeDescription.php");
    else {
        $qtxt = "update varer set beskrivelse = '" . db_escape_string($beskrivelse[0]) . "' where id= '$id'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    }
}
################################################## OUTPUT ####################################################

include("../includes/topline_settings.php");

print "<center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
if ($menu == 'T') {
    include_once '../includes/top_menu.php';
    include_once '../includes/top_header.php';
    print "<div id=\"header\"> 
    <div class=\"headerbtnLft\"><a title=\"Klik her for at lukke varekortet\" class=\"button red small left\" href=$returside accesskey=\"L\">Luk</a></div>
    <span class=\"headerTxt\">$title</span>";
    print "<div class=\"headerbtnRght\"></div>";
    print "</div><!-- end of header -->
        <div class=\"maincontentLargeHolder\">\n";
    print "<center><table border='0' cellspacing='1' width='75%' align='center';>";
} elseif ($menu == 'S') {
    print "<tr><td align=\"center\" valign=\"top\">\n";
    print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";

    $tmp = ($popup) ? "onClick=\"javascript=opener.location.reload();\"" : "";
    if ($opener != 'varer.php') {
        print "<td width=\"10%\">
               <a href=\"javascript:confirmClose('$returside?id=$ordre_id&fokus=$fokus&varenr=" . addslashes($varenr) . "&vare_id=$id','$tekst')\" accesskey=L>
               <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" . findtekst(30, $sprog_id) . "</button></a></td>\n";
    } else {
        print "<td /*width=\"10%\"*/ $tmp><a href=\"javascript:confirmClose('$returside?','$tekst')\" accesskey=L>
               <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Luk</button></a></td>\n";
    }
    print "<td align='center' style='$topStyle'>" . findtekst(566, $sprog_id) . "</td>\n";
    // print "<td width='10%' align='center' style='$topStyle'><br></td>\n";

    # Open pos menus
    if ($id) {
        if ($usePos) {
            print "<td width='10%' align='right'>
        <a href=\"javascript:console.log(getCookie('pos_menu_location'));confirmClose(`../systemdata/posmenuer.php?menu_id=\${getCookie('pos_menu_location').split('-')[0]}&ret_row=\${getCookie('pos_menu_location').split('-')[1]}&ret_col=\${getCookie('pos_menu_location').split('-')[2]}`,'$tekst'); \" accesskey=B>
        <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" . "POS menuer" . "</button></a></td>\n";
        }
        # Create new item
        print "<td width='10%' align='right'>
         <a href=\"javascript:confirmClose('varekort.php?opener=$opener&returside=$returside&ordre_id=$id','$tekst')\" accesskey=N>
         <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" . findtekst(39, $sprog_id) . "</button></a><td>\n";
    }
    print "</td></tbody></table>\n";
    print "</td></tr>\n";
    print "<td align = center valign = center>\n";
    print "<table cellpadding='1' cellspacing='1' border='0' width='80%'><tbody>\n";
} else {
    print "<tr><td align=\"center\" valign=\"top\">\n";
    print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
    $tmp = ($popup) ? "onClick=\"javascript=opener.location.reload();\"" : "";
    if ($opener != 'varer.php')
        print "<td width=\"10%\" $top_bund><a href=\"javascript:confirmClose('$returside?id=$ordre_id&fokus=$fokus&varenr=" . addslashes($varenr) . "&vare_id=$id','$tekst')\" accesskey=L>" . findtekst(30, $sprog_id) . "</a></td>\n";
    else
        print "<td width=\"10%\" $tmp $top_bund> <a href=\"javascript:confirmClose('$returside?','$tekst')\" accesskey=L>Luk</a></td>\n";
    print "<td width=\"70%\" $top_bund align=\"center\">" . findtekst(566, $sprog_id) . "</td>\n";

    # Open pos menus
    if ($id)
        print "<td width=\"10%\" $top_bund align=\"right\"><a href=\"javascript:console.log(getCookie('pos_menu_location'));confirmClose(`../systemdata/posmenuer.php?menu_id=\${getCookie('pos_menu_location').split('-')[0]}&ret_row=\${getCookie('pos_menu_location').split('-')[1]}&ret_col=\${getCookie('pos_menu_location').split('-')[2]}`,'$tekst'); \" accesskey=B>" . "POS menuer" . "</a>\n";

    # Create new item
    if ($id)
        print "<td width=\"10%\" $top_bund align=\"right\"><a href=\"javascript:confirmClose('varekort.php?opener=$opener&returside=$returside&ordre_id=$id','$tekst')\" accesskey=N>" . findtekst(39, $sprog_id) . "</a>\n";
    print "</td></tbody></table>\n";
    print "</td></tr>\n";
    print "<td align = center valign = center>\n";
    print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"80%\"><tbody>\n";
}
$vare_varianter = array();
if ($id > 0) {
    $query = db_select("select * from varer where id = '$id'", __FILE__ . " linje " . __LINE__);
    $row = db_fetch_array($query);
    $varenr = $row['varenr'];
    $stregkode = $row['stregkode'];
    $beskrivelse[0] = $row['beskrivelse'];
    $enhed = $row['enhed'];
    $enhed2 = $row['enhed2'];
    $indhold = $row['indhold'];
    $forhold = $row['forhold'];
    $salgspris = $row['salgspris'];
    $netprice = $row['salgspris'];
    $kostpris[0] = $row['kostpris'];
    $montage = $row['montage']; # montagepris til stillads
    $demontage = $row['demontage']; # demontagepris til stillads
    $provision = $row['provision'];
    $provisionsfri = $row['provisionsfri'];
    $publiceret = $row['publiceret'];
    $gruppe = $row['gruppe'] * 1;
    $prisgruppe = $row['prisgruppe'] * 1;
    $rabatgruppe = $row['rabatgruppe'] * 1;
    $dvrg_nr[0] = $row['dvrg'] * 1; # DebitorVareRabatGruppe
    $serienr = $row['serienr'];
    $lukket = $row['lukket'];
    $notes = $row['notes'];
    $notesInternal = $row['notesinternal'];
    $delvare = $row['delvare'];
    $samlevare = $row['samlevare'];
    $min_lager = $row['min_lager'];
    $max_lager = $row['max_lager'];
    $volume_lager = $row['volume_lager'];
    $beholdning = $row['beholdning'] * 1;
    $operation = $row['operation'] * 1;
    $trademark = $row['trademark'];
    $location = $row['location'];
    $folgevare = $row['folgevare'] * 1;
    $specialType = $row['specialtype'];
    $special_price = $row['special_price'];
    $campaign_cost = $row['campaign_cost'];
    $special_from_date = $row['special_from_date'];
    $special_to_date = $row['special_to_date'];
    $special_from_time = substr($row['special_from_time'], 0, 5);
    $special_to_time = substr($row['special_to_time'], 0, 5);
    $retail_price = $row['retail_price'];
    $tier_price = $row['tier_price'];
    $colli = $row['colli'];
    $colli_webfragt = $row['colli_webfragt'];
    $outer_colli = $row['outer_colli'];
    $open_colli_price = $row['open_colli_price'];
    $outer_colli_price = $row['outer_colli_price'];
    $campaign_cost = $row['campaign_cost'];
    $m_type = $row['m_type'];
    $m_rabat_array = explode(";", $row['m_rabat']);
    $m_antal_array = explode(";", $row['m_antal']);
    $kategori = explode(chr(9), $row['kategori']);
    $kategori_antal = count($kategori);
    $fotonavn = $row['fotonavn'];
    $grossWeight = $row['grossweight'];
    $netWeight = $row['netweight'];
    $grossWeightUnit = $row['grossweightunit'];
    $netWeightUnit = $row['netweightunit'];
    $length = $row['length'];
    $width = $row['width'];
    $height = $row['height'];

    // 20221004
    $on_price_list = $row['on_price_list'];
    $tier_price_multiplier = $row['tier_price_multiplier'];
    $tier_price_method = $row['tier_price_method'];
    $tier_price_rounding = $row['tier_price_rounding'];
    $salgspris_multiplier = $row['salgspris_multiplier'];
    $salgspris_method = $row['salgspris_method'];
    $salgspris_rounding = $row['salgspris_rounding'];
    $retail_price_multiplier = $row['retail_price_multiplier'];
    $retail_price_method = $row['retail_price_method'];
    $retail_price_rounding = $row['retail_price_rounding'];

    if ($copyId) {
        $id = $copyId;
        $varenr = $copyItemNo;
        print "<body onload=\"alert('Gem før du retter varenummer eller lukker!');\">";
    }

    if ($ny_beholdning)
        $beholdning = $ny_beholdning;
    #    if ($row['varianter']) $vare_varianter=explode(chr(9),$row['varianter']);
#    for ($x=0;$x<count($vare_varianter);$x++) echo __line__." $x $vare_varianter[$x]<br>"; 

    $x = 0;
    $qtxt = "select * from variant_varer where vare_id = '$id'";# order by variant_text";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $variantVarerId[$x] = $r['id'];
        $variantVarerBarcode[$x] = $r['variant_stregkode'];
        $variantVarerType[$x] = explode(chr(9), $r['variant_type']);
        $variantVarerText[$x] = $r['variant_text'];
        $variantVarerVariantId[$x] = $r['variant_id'];
        $var_beh[$x] = 0;
        for ($l = 1; $l <= $numberOfStocks; $l++) {
            $variantVarerQty[$x][$l] = 0;
            $qtxt = "select beholdning from lagerstatus ";
            $qtxt .= "where vare_id='$id' and lager='$l' and variant_id='$variantVarerId[$x]'";
            if ($r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                $variantVarerQty[$x][$l] = $r2['beholdning'];
                $var_beh[$x] += $variantVarerQty[$x][$l];
            }
        }
        $x++;
    }
    if ($r = db_fetch_array(db_select("select shop_id from shop_varer where saldi_id='$id'", __FILE__ . " linje " . __LINE__))) {
        $shop_id = $r['shop_id'];
        $publiceret = 'on';
    } else {
        $shop_id = NULL;
        #       $publiceret=NULL;
    }
    $qtxt = "select var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
        $vatOnItemCard = $r['var_value'];
    else
        $vatOnItemCard = NULL;
    /*
       if (!$vatOnItemCard) { // remove this after rel 3.7.6
           $r = db_fetch_array(db_select("select id,box1 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
           if ($r['box1']) {
               db_modify("update grupper set box1='' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
               $qtxt="select id from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
               $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
               if (!$r['id']) {
                   $qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
                   $qtxt.="('items','vatOnItemCard','on','Hvis sat, vises salgspriser incl. moms på varekort ','0')"; 
                   db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                   db_modify("update grupper set box1 = '' where id='$id'",__FILE__ . " linje " . __LINE__);
               }
           }
       }
   // <-
   */
    $incl_moms = 0;
    $gruppe = (int) $gruppe;
    if ($vatOnItemCard) {
        $incl_moms = vatPercent($gruppe);
        /*
                $qtxt="select box4,box7 from grupper where art='VG' and kodenr='$gruppe' and box7!='on'";
                if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
                    $konto = $r['box4'];
                    $qtxt="select moms from kontoplan where kontonr='$r[box4]' order by id desc limit 1";
                    $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                    $momsart=substr($r['moms'],0,1).'M';  #20230425
                    $momskode=substr($r['moms'],1);
                    $qtxt="select box2 from grupper where art='$momsart' and kodenr = '$momskode'";
                    $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                    $incl_moms=$r['box2']*1;
                }
        */
    }
    $salgspris *= (100 + $incl_moms) / (100);
    if ($specialType != 'percent')
        $special_price *= (100 + $incl_moms) / (100);

    if ($folgevare > 0) {
        $r = db_fetch_array(db_select("select varenr from varer where id = '$folgevare'", __FILE__ . " linje " . __LINE__));
        $folgevarenr = $r['varenr'];
    } elseif ($folgevare < 0) {
        if ($r = db_fetch_array(db_select("select id from grupper where kodenr = '" . abs($folgevare) . "'", __FILE__ . " linje " . __LINE__))) {
            $folgevarenr = 'MENU' . abs($folgevare);
        }
    }
    $x = 0;
    $q = db_select("select * from grupper where art='DVRG' order by kodenr", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $x++;
        $dvrg_nr[$x] = $r['kodenr'] * 1;
        $dvrg_navn[$x] = $r['box1'];
    }
    $qtxt = "select * from grupper where art='VPG' and kodenr = '$prisgruppe'";
    if ($prisgruppe && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $p_grp_kostpris = (float) $r['box1'];
        $p_grp_salgspris = (float) $r['box2'];
        $p_grp_retail_price = (float) $r['box3'];
        $p_grp_tier_price = (float) $r['box4'];
    }
    $qtxt = "select * from grupper where art='VTG' and kodenr = '$tilbudgruppe'";
    if ($tilbudgruppe && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $campaign_cost = (float) $r['box1'];
        $special_price = (float) $r['box2'];
        $special_from_date = (float) $r['box3'];
        $special_to_date = (float) $r['box4'];
    }
    $qtxt = "select * from grupper where art='VRG' and kodenr = '$rabatgruppe'";
    if ($rabatgruppe && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $m_type = $r['box1'];
        $m_rabat_array = explode(";", $r['box2']);
        $m_antal_array = explode(";", $r['box3']);
        if ($incl_moms && $m_type == 'amount') { // 20220215
            for ($x = 0; $x < count($m_rabat_array); $x++) {
                $m_rabat_array[$x] = usdecimal($m_rabat_array[$x]) * 100 / (100 + $incl_moms);
            }
        }
    }
    $qtxt = "select var_value from settings where var_name = 'useCommission' and var_grp = 'items'";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $useCommission = $r['var_value'];
    } else
        $useCommission = NULL;
    #   $kpris=dkdecimal($row['kostpris']);
    $q = db_select("select * from grupper where art='VG' and kodenr = '$gruppe' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__);
    $r = db_fetch_array($q);
    $stockItem = if_isset($r['box8'], 0);
    $batchItem = if_isset($r['box9'], 0);
} else {
    $gruppe = 1;
    $leverandor = 0;
}
if (!isset($min_lager))
    $min_lager = NULL;
if (!isset($max_lager))
    $max_lager = NULL;
if (!isset($volume_lager))
    $volume_lager = NULL;

if (!$min_lager)
    $min_lager = 0;
if (!$max_lager)
    $max_lager = 0;
if (!$volume_lager)
    $volume_lager = 0;

$x = 0;
$q = db_select("select * from grupper where art = 'VSPR' order by kodenr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $x++;
    $vare_sprog_id[$x] = $r['kodenr'];
    $vare_sprog[$x] = $r['box1'];
    $vare_tekst_id[$x] = 0;
    $beskrivelse[$x] = null;
    $qtxt = "select * from varetekster where vare_id='$id' and sprog_id = '$vare_sprog_id[$x]'";
    if ($r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $vare_tekst_id[$x] = $r2['id'] * 1;
        $beskrivelse[$x] = $r2['tekst'];
    }
}
$vare_sprogantal = $x;

$x = 0;
$q = db_select("select * from varianter", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $varianter_id[$x] = $r['id'];
    $varianter_beskrivelse[$x] = $r['beskrivelse'];
    $x++;
}

if ($r = db_fetch_array(db_select("select id from labels limit 1", __FILE__ . " linje " . __LINE__)))
    $labelprint = 1;
else
    $labelprint = NULL;

################################# output #####################################

if (!isset($publiceret))
    $publiceret = NULL;
if (!isset($vare_lev_id))
    $vare_lev_id = NULL;
if (!isset($fifo))
    $fifo = NULL;
if (!isset($varianter))
    $varianter = NULL;
if (!isset($ant_be_af))
    $ant_be_af = NULL;

print "<form name='varekort' action='varekort.php?opener=$opener' method='post'>\n";

print "<input type = 'hidden' name=id value='$id'>\n";
print "<input type = 'hidden' name=ordre_id value='$ordre_id'>\n";
print "<input type = 'hidden' name=returside value='$returside'>\n";
print "<input type = 'hidden' name=fokus value='$fokus'>\n";
print "<input type = 'hidden' name=leverandor value='$lev'>\n";
print "<input type = 'hidden' name=lagerantal value='$numberOfStocks'>\n";
print "<input type = 'hidden' name=vare_sprogantal value='$vare_sprogantal'>\n";
print "<input type = 'hidden' name=publ_pre value='$publiceret'>\n";
for ($x = 1; $x <= $vare_sprogantal; $x++) {
    print "<input type = 'hidden' name=vare_sprog_id[$x] value='$vare_sprog_id[$x]'>\n";
}

($noEdit) ? $href = NULL : $href = "ret_varenr.php?id=$id";

if (substr($rettigheder,7,1) && $id) {
    print "<tr><td colspan=\"1\"></td>\n";
    print "<td colspan=\"1\" align=\"center\"><b>" . findtekst(917, $sprog_id) . ": <a href=\"$href\">$varenr</a></b></td>\n";

    $query = db_select("select * from vare_lev where vare_id='$id'", __FILE__ . " linje " . __LINE__);
    if (db_fetch_array($query)) {
        print "<td colspan=\"1\" align=\"right\">
        <input type='text' id='bestil-antal' placeholder='1,00' size=6 style='text-align: right'></input>
        <button type='button' onclick=\"bestil('$id', document.getElementById('bestil-antal').value)\">Bestil</button>
        </td>\n";

        print '<script>
        function bestil(varenr, antal) {
            if (antal === "") {antal = 1;}
            console.log(varenr, antal);
            // Create form data for the POST request
            const formData = new FormData();
            formData.append("vare_id", varenr);
            formData.append("antal", antal);
        
            // Send the fetch request with proper configuration
            fetch("orderapi.php", {
                method: "POST",
                body: formData,
                credentials: "include"  // Ensure cookies are sent with the request
            })
            .then(response => {
                if (response.status === 200) {
                    alert("Ordren blev gennemført!");
                    console.log("Order successful:", response);
                    window.location.reload()
                } else {
                    throw new Error("Server returned status: " + response.status);
                }
            })
            .catch(error => {
                alert("Fejl: " + error.message);
                console.error("Order error:", error);
            });
        }
        </script>';
        
    } else {
        print "<td colspan=\"1\" align=\"right\">
        <input type='text' disabled title='Bestilling ikke muligt, ingen leverandør fundet' placeholder='Bestilling ikke muligt, ingen leverandør fundet' size=10 style='text-align: right'></input>
        <button type='button' disabled>Bestil</button>
        </td>\n";
    }
} else {
    print "<tr><td colspan=\"3\" align=\"center\"><b>" . findtekst(917, $sprog_id) . ": <a href=\"$href\">$varenr</a></b></td>\n";
}

print "</tr>";
if (!$varenr) {
    ($db == 'saldi_735') ? $pattern = '[a-zA-Z0-9+._ -]+' : $pattern = '[a-zA-Z0-9+._-]+';
    $fokus = "varenr";
    print "<input type=\"hidden\" name=\"vare_lev_id\" value=\"$vare_lev_id\">\n";
    print "<td colspan=\"3\" align=\"center\">\n";
    print "<input class=\"inputbox\" type=\"text\" size=\"25\" name=\"varenr\" value=\"$varenr\" pattern='" . $pattern . "' ";
    print "onchange=\"javascript:docChange = true;\"></td></tr>\n";
    print "<tr><td align=center>" . findtekst(2021, $sprog_id) . "</td>\n";
    print "<tr><td align=center><a href='varescanimport.php?returside=varekort.php'><button type='button'>Masse vare scan</button></a></td></tr>\n";
} else {
    print "<input type=\"hidden\" name=\"varenr\" value=\"$varenr\">\n";
    print "<tr><td colspan='4' width='100%'><table bordercolor='#FFFFFF' border='1' cellspacing='5' width='100%'><tbody>\n";
    print "<tr><td colspan='2' valign='top'><table border='0' width='100%'><tbody>\n"; # Vareinfo enhedstabel ->
    print "\n<!-- productCardIncludes/showInfo.php begin -->\n";
    include_once("productCardIncludes/showInfo.php");
    print "\n<!-- productCardIncludes/showInfo.php end -->\n";
    ################# VARIANTER #######################
    $showVariants = '1';
    if ($showVariants && count($variantVarerId)) { // Selected Variants is shown
        print "<tr><td colspan=\"2\"><hr></td></tr>\n";
        print "<tr><td colspan=\"2\">" . findtekst(472, $sprog_id) . "</td></tr>\n";
        print "<tr><td colspan=\"2\"><table border=\"0\"><tbody>\n";
        print "\n<!-- productCardIncludes/showVariantsInfo.php begin -->\n";
        include_once('productCardIncludes/showVariantsInfo.php');
        print "\n<!-- productCardIncludes/showVariantsInfo.php end -->\n";
        print "</tbody></table></td>\n";
    } else { // Inputfield for barcode is shown
        print "<tr><td>" . findtekst(2016, $sprog_id) . "<!--Stregkode--></td><td><input class=\"inputbox\" type=\"text\" style=\"text-alig1n:left;width:400px;\" name=\"stregkode\" value=\"$stregkode\" onchange=\"javascript:docChange = true;\"></td>\n";
    }
    print "</tbody></table></td>\n";
    #print "<tr><td>Varianter</td><td>\n";
    if (!count($variantVarerId) || !$stockItem) {
        ($stregkode) ? $tmp = $stregkode : $tmp = $varenr;
        $png = barcode($tmp);
        if ($png) {
            print "<td align=\"center\"><table width=\"100%\"><tbody>\n";
            if ($labelprint && file_exists($png))
                print "<tr><td align=\"right\"><a href=\"../lager/labelprint.php?id=$id&beskrivelse=" . urlencode($beskrivelse[0]) . "&stregkode=" . urlencode($tmp) . "&src=$png&pris=$salgspris&enhed=$enhed&indhold=$indhold\" target=\"blank\"><img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a></td></tr>\n";
            #       print "<tr><td align=\"center\">$beskrivelse[0]</td></tr>\n";
            if (file_exists($png)) {
                print "<tr><td align=\"center\"><img style=\"border:0px solid;\" alt=\"\" src=\"$png\"></td></tr>\n";
            } else {
                print "<tr><td align=\"center\" 
                title=\"Stregkode kan ikke generes.&#xA;varenr. indeholder ugyldige tegn\"><big>!</big></td></tr>\n";
            }
            #       if ($labelprint && file_exists($png)) print "<tr><td align=\"right\"><a href=\"../lager/labelprint.php?stregkode=".urlencode($tmp)."&src=$png&pris=$salgspris\" target=\"blank\"><img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a></td></tr>\n";
            print "</tbody></table>\n";
        } else
            print "</tr>\n";
    } else
        print "</tr>\n";
    print "</tr>\n";
    ######### ==> tabel 4
#print "<tr><td colspan=4 width=100%><table border=1 width=100%><tbody>";
    print "<tr><td width=\"33%\" valign=top><table border=\"0\" width=\"100%\"><tbody>"; # Pris enhedstabel ->
    print "\n<!-- productCardIncludes/showPrices.php begin -->\n";
    include_once("productCardIncludes/showPrices.php");
    print "\n<!-- productCardIncludes/showPrices.php end -->\n";
    print "</tbody></table></td>"; #<- Pris enhedstabel

    print "<td width=33% valign=top><table border=0 width=100%><tbody>"; # Tilbudstabel ->
    print "\n<!-- productCardIncludes/showDiscounts.php begin -->\n";
    include_once("productCardIncludes/showDiscounts.php");
    print "\n<!-- productCardIncludes/showDiscounts.php end -->\n";
    print "</tbody></table></td>";# <- Tilbudstabel 

    print "<td valign=top width=33%><table border=0 width=100%><tbody>"; # Collitabel ->
    print "\n<!-- productCardIncludes/showColli.php begin -->\n";
    include_once("productCardIncludes/showColli.php");
    print "\n<!-- productCardIncludes/showColli.php end -->\n";

    print "</tbody></table></td></tr>";# <- Collitabel 
    print "<tr><td valign=top><table border='0' width='100%'><tbody>"; # Enhedstabel ->
    print "\n<!-- productCardIncludes/showUnits.php begin -->\n";
    include('productCardIncludes/showUnits.php');
    print "\n<!-- productCardIncludes/showUnits.php end -->\n";
    print "</tbody></table></td>";

    print "<td valign=top><table border=0 width=100%><tbody>"; # Gruppe tabel ->
    print "\n<!-- productCardIncludes/showGroups.php begin -->\n";
    include('productCardIncludes/showGroups.php');
    print "\n<!-- productCardIncludes/showGroups.php end -->\n";
    print "</tbody></table></td>";# <- Gruppe tabel

    print "<td valign=\"top\"><table border=\"0\" width=\"100%\"><tbody>"; # M-rabat tabel ->
    print "\n<!-- productCardIncludes/showQtyDiscounts.php begin -->\n";
    include('productCardIncludes/showQtyDiscounts.php');
    print "\n<!-- productCardIncludes/showQtyDiscounts.php end -->\n";
    print "</tbody></table></td></tr>";# <- M-rabat tabel 
    print "<tr><td valign=\"top\" height=\"200px\"><table border=\"0\" width=\"100%\"><tbody>"; # Diverse tabel ->
    print "\n<!-- productCardIncludes/showLocations.php begin -->\n";
    include('productCardIncludes/showLocations.php');
    print "\n<!-- productCardIncludes/showLocations.php end -->\n";
    print "</tbody></table></td>";#  <- Diverse tabel
#################### KATEGORIER ###########################
    print "<td valign=\"top\" height=\"200px\">";
    print "<div class=\"vindue\">";
    print "<table border=0 width=100%><tbody>"; # Kategori tabel ->
    print "\n<!-- productCardIncludes/showCategories.php begin -->\n";
    include('productCardIncludes/showCategories.php');
    print "\n<!-- productCardIncludes/showCategories.php end -->\n";
    print "</tbody></table></div></td>";#  <- Kategori tabel

    ####################################### VARIANTER #############################################
    print "<td valign=\"top\" height=\"200px\"><div class=\"vindue\"><table border=\"0\" width=\"100%\"><tbody>"; # 
    print "\n<!-- productCardIncludes/useVariants.php begin -->\n";
    include('productCardIncludes/useVariants.php');
    print "\n<!-- productCardIncludes/useVariants.php end -->\n";
    print "</tbody></table></div></td></tr>";#  <- Variant tabel

    ####################################### NOTER/BESKRIVELSE #############################################
    print "\n<!-- productCardIncludes/notesEtc.php begin -->\n";
    include('productCardIncludes/notesEtc.php');
    print "\n<!-- productCardIncludes/notesEtc.php end -->\n";

    print "<tr>";

    ####################################### STYKLISTER #############################################
    if (!isset($samlevare))
        $samlevare = NULL;

    if ($samlevare != 'on') {
        if ($id) {
            $x = 0;
            $vare_lev_id = array();
            $query = db_select("select * from vare_lev where vare_id='$id' order by posnr", __FILE__ . " linje " . __LINE__);
            while ($row = db_fetch_array($query)) {
                $x++;
                $vare_lev_id[$x] = $row['id'];
                $lev_id[$x] = $row['lev_id'];
                $lev_varenr[$x] = $row['lev_varenr'];
                $kostpris[$x] = $row['kostpris'];
                if ($x == 1 && !$lev_varenr[$x] && !$kostpris[$x]) {
                    $lev_varenr[$x] = $varenr;
                    $kostpris[$x] = $kostpris[0] * 1;
                    $qtxt = "update vare_lev set lev_varenr='" . db_escape_string($lev_varenr[$x]) . "',kostpris='$kostpris[$x]' where id='$row[id]'";
                    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                }
            }
            $lev_ant = $x;
            if ($lev_ant) {
                print "<input type = 'hidden' name=lev_antal value=$lev_ant>";
                print "<tr><td colspan=3><table border=0 width=100%><tbody>";
                print "<tr><td> Pos.</td><td> Leverand&oslash;r</td><td> Varenr.</td><td align='right'> Indkøbspris ($enhed)</td>";
                if (($enhed2) && ($forhold > 0)) {
                    print "<td align='right'> Indkøbspris ($enhed2)</td>";
                }
                print "</tr>";
                for ($x = 1; $x <= $lev_ant; $x++) {
                    $query = db_select("select kontonr, firmanavn from adresser where id='$lev_id[$x]'", __FILE__ . " linje " . __LINE__);
                    $row = db_fetch_array($query);
                    $y = dkdecimal($kostpris[$x], 2);
                    print "
			<td>
				<span title='Pos = minus sletter leverand&oslash;ren';>
					<input class=\"inputbox\" type = 'text' size=1 name=lev_pos[$x] value=$x onchange=\"javascript:docChange = true;\">
				</span>
			</td>
			<td> 
				$row[kontonr]:" . $row['firmanavn'] . "
			</td>
			<td>
				<input class=\"inputbox\" type = 'text' style=text-align:right size=9 name=lev_varenr[$x] value=\"$lev_varenr[$x]\" onchange=\"javascript:docChange = true;\">
			</td>
			<td style=text-align:right>
				<input class=\"inputbox\" type = 'text' style=text-align:right size=9 name=kostpris[$x] value=\"$y\" onchange=\"javascript:docChange = true;\">
			</td>";
                    if (($enhed2) && ($forhold > 0)) {
                        $y = dkdecimal($kostpris[$x] / $forhold, 2);
                        print "<td><input class=\"inputbox\" type = 'text' style=text-align:right size=9 name=kostpris2[$x] value=\"$y\" onchange=\"javascript:docChange = true;\"></td>";
                    }
                    print "</td></tr>";
                    print "<input class=\"inputbox\" type = 'hidden' name=vare_lev_id[$x] value=$vare_lev_id[$x]>";
                }
                print "</tbody></table>";
            }
        }
    }
    print "</tr></tbody></table></td></tr>";
    print "<tr><td colspan=5 width=100%><table border=0 width=100%><tbody>";

    $be_af_sum = 0;
    if ($samlevare == 'on') {
        $query = db_select("select * from styklister where indgaar_i=$id order by posnr", __FILE__ . " linje " . __LINE__);
        $x = 0;
        while ($row = db_fetch_array($query)) {
            $x++;
            $query2 = db_select("select * from varer where id = $row[vare_id]", __FILE__ . " linje " . __LINE__);
            $row2 = db_fetch_array($query2);
            $be_af_vnr[$x] = $row2['varenr'];
            $be_af_beskrivelse[$x] = $row2['beskrivelse'];
            $be_af_enhed[$x] = $row2['enhed'];
            $be_af_ant[$x] = $row['antal'];
            $be_af_id[$x] = $row2['id'];
            $be_af_kostpris[$x] = $row2['kostpris'];
            $be_af_sum += $be_af_kostpris[$x] * $be_af_ant[$x];
            print "<input type = 'hidden' name=be_af_id[$x] value='$row[id]'>";
            print "<input type = 'hidden' name=be_af_vare_id[$x] value='$row[vare_id]'>";
            print "<input type = 'hidden' name=be_af_vnr[$x] value='$be_af_vnr[$x]'>";
            print "<input type = 'hidden' name=be_af_beskrivelse[$x] value='$be_af_beskrivelse[$x]'>";
        }
        $ant_be_af = $x;
    }

    if (!isset($delvare))
        $delvare = NULL;

    if ($delvare == 'on') {
        $qtxt = "select * from styklister where vare_id=$id order by posnr";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        $x = 0;
        while ($row = db_fetch_array($q)) {
            $query2 = db_select("select * from varer where id = $row[indgaar_i]", __FILE__ . " linje " . __LINE__);
            $row2 = db_fetch_array($query2);
            if ($row2['id'] == $id) { #20131007
                db_modify("delete from styklister where id='$row[id]'", __FILE__ . " linje " . __LINE__);
                $txt = "Cirkulær reference konstateret, varenr.: $row2[varenr] fjernet fra stykliste";
                print "<BODY onLoad=\"javascript:alert('$txt')\">";
            } else {
                $x++;
                $indg_i_vnr[$x] = $row2['varenr'];
                $indg_i_beskrivelse[$x] = $row2['beskrivelse'];
                $indg_i_enhed[$x] = $row2['enhed'];
                $indg_i_ant[$x] = $row['antal'];
                $indg_i_id[$x] = $row2['id'];
                print "<input type = 'hidden' name=indg_i_id[$x] value='$row[id]'>";
            }
        }
        if ($x == 0) {
            print "<input type = 'hidden' name=delvare value=''>";
            $delvare = '';
        }
        $ant_indg_i = $x;
    }

    # Vises hvis varen er en stykliste
    if ($samlevare == 'on') {
        $be_af_pos[0] = 0;
        ($beholdning) ? $readonly = 'readonly' : $readonly = '';
        print "<tr><td valign=top><table width=20%><tbody><tr><td> <a href=stykliste.php?id=$id>Stykliste</a></td></tr>";
        print "</tbody></table></td>";
        print "<td></td><td><table border=0 width=80%><tbody>";
        print "<tr><td> Pos.</td><td width=80> V.nr.</td><td width=300> Beskrivelse</td><td> Antal</td></tr>";
        for ($x = 1; $x <= $ant_be_af; $x++) {
            $dkantal = dkdecimal($be_af_ant[$x], 2);
            if (substr($dkantal, -1) == '0')
                $dkantal = substr($dkantal, 0, -1);
            if (substr($dkantal, -1) == '0')
                $dkantal = substr($dkantal, 0, -2);
            print "<tr>";
            print "<td><input class=\"inputbox\" type=\"text\" size=2 style=\"text-align:right\" name=\"be_af_pos[$x]\" value=\"$x\" $readonly></td>";
            print "<td><a href='?opener=&id=$be_af_id[$x]&returside=varer.php&vis_samlevarer=on'>$be_af_vnr[$x]</a></td><td>$be_af_beskrivelse[$x]</td>";
            print "<td><input class=\"inputbox\" type=\"text\" size=\"2\" style=\"text-align:right\" name=\"be_af_ant[$x]\" value=\"$dkantal\" $readonly>&nbsp;$be_af_enhed[$x]</td>";
            if ($x === $ant_be_af) {
                print "<td align='right' title='Varens kostpris' style='border-bottom: 1px #151515 solid'>" . dkdecimal($be_af_kostpris[$x] * $be_af_ant[$x], 2) . "</td>";
            } else {
                print "<td align='right' title='Varens kostpris'>" . dkdecimal($be_af_kostpris[$x] * $be_af_ant[$x], 2) . "</td>";
            }
            print "</tr>";
        }
        print "<tr><td colspan='3'></td><td>Kostpris i alt:</td>";
        print "<td align='right'>" . dkdecimal($be_af_sum, 2) . "</td><tr>";
        $be_af_pos[0] = $ant_be_af + 1;
        print "</tr>";
        print "</tr></tbody></table></td></tr>";
        print "<input type = 'hidden' name=ant_be_af value='$ant_be_af'>";
    }

    # Vises hvis varen indegår i en stykliste
    if ($delvare == 'on') {
        if ($vis_samlevarer) {
            print "<tr><td valign=top width=10%><span title='Klik her for at lukke oversigten'><a href=varekort.php?opener=$opener&id=$id&returside=$returside>Indg&aring;r i</a></td><td></td><td><table width=80% border=0><tbody>";
            print "<tr><td> Pos.</td><td width=80> V.nr.</td><td width=300> Beskrivelse</td><td> Antal</td></tr>";
            for ($x = 1; $x <= $ant_indg_i; $x++) {
                print "<tr><td><input class=\"inputbox\" type = 'text' size=2 name=indg_i_ant[$x] value=$x></td><td><a href='?id=$indg_i_id[$x]'>$indg_i_vnr[$x]</a></td><td>$indg_i_beskrivelse[$x]</td><td align=\"right\">$indg_i_ant[$x]</td></tr>";
            }
            print "<input type=\"hidden\" name=\"vis_samlevarer\" value=\"on\">";
        } else {
            print "<tr><td colspan=3><table width=100% border='0' cellspacing='1'><tbody>";
            print "<tr><td width=100% align=center><a href=varekort.php?opener=$opener&id=$id&returside=$returside&vis_samlevarer=on>Denne vare indg&aring;r i andre varer - Klik for oversigt</a></td></tr>";
        }

        #   print "<tr><td><input class=\"inputbox\" type = 'text' size=2 name=indg_i_ant[$x] value=$x></td><td colspan=2><SELECT class=\"inputbox\" NAME=indg_i_ant[0]>";
#   print "<option>$row[varenr]&nbsp;".substr($row['beskrivelse'],0,60)."</option>";
#   $query = db_select("select * from varer where id != $id order by varenr",__FILE__ . " linje " . __LINE__);
#   while ($row = db_fetch_array($query)) {
#       if ((!in_array($row['id'], $be_af_id))&&(!in_array($row['id'], $indg_i_id))){print "<option>$row[varenr] : ".substr($row['beskrivelse'],0,60)."</option>";}
#   }   
#   print "</SELECT></td>";
        print "</tr></tbody></table></td></tr>";
    }

    print "</td></tr></tbody></table></td></tr>";
    # <== tabel 4
}

if (!isset($kostpris[0]))
    $kostpris[0] = 0;

if ($menu == 'T') {
    $hrlinje = "<hr width=75%>";
} else {
    $hrlinje = "<hr width=100%>";
}
print "<tr><td colspan=4><center>$hrlinje</td></tr>";
print "<tr><td colspan=4 align=center><table width=100%><tbody>";

print "<input type = 'hidden' name=ant_indg_i value='$ant_indg_i'>";
print "<input type = 'hidden' name=returside value='$returside'>";
print "<input type = 'hidden' name=fokus value='$fokus'>";
print "<input type = 'hidden' name=ordre_id value='$ordre_id'>";
print "<input type = 'hidden' name=delvare value='$delvare'>";
print "<input type = 'hidden' name=gl_kostpris value='$kostpris[0]'>";
print "<input type = 'hidden' name='oldRetailPrice' value='$retail_price'>";


print "<tr><td align = center><input class='button green medium' style='width:150px;' type=submit accesskey=\"g\" ";
print "value=\"" . findtekst(3, $sprog_id) . "\" name=\"saveItem\" onclick=\"javascript:docChange = false;\" $noEdit></td>";

($beholdning || $noEdit) ? $disabled = 'disabled' : $disabled = '';
if ($varenr && $samlevare == 'on') {
    $txt1100 = findtekst(1100, $sprog_id); //Kopier
    print "<td align = center><input class='button blue medium' style='width:150px;' type=submit accesskey='k' value='$txt1100' name='copy'></td>";
    print "<td align = center><input class='button blue medium' style='width:150px;' type=submit title='Inds&aelig;t varer i stykliste' accesskey=\"l\" value=\"Vareopslag\" name=\"submit\" onclick=\"javascript:docChange = false;\" $disabled></td>";
} elseif ($varenr) {
    $txt1100 = findtekst(1100, $sprog_id); //Kopier
    $txt2049 = findtekst(2049, $sprog_id); //Leverandøropslag
    print "<td align = center><input class='button blue medium' style='width:150px;' type=submit accesskey='k' value='$txt1100' name='copy'></td>";
    print "<td align = center><input class='button blue medium' style='width:150px;' type=submit accesskey='l' value='$txt2049' name='supplierLookUp' onclick='javascript:docChange = false;' $noEdit></td>";
}
if ($id) {
    $txt1099 = findtekst(1099, $sprog_id); //Slet
    $q = db_select("select distinct(id) from ordrelinjer where vare_id = $id", __FILE__ . " linje " . __LINE__);
    if (!db_fetch_array($q) && $lev_ant < 1 && $ant_be_af < 1 && $ant_indg_i < 1) {
        print "<td align=center><input style='width:150px;' class='button red medium' type=submit ";
        print "value=\"$txt1099\" name=\"deleteItem\" OnClick=\"return confirm('Slet varenr $varenr ?')\"></td>";
    }
}
print "</form>";
print "</tr></tbody></table></td></tr>";
print "</tr></tbody></table></td></tr>";


function prisopdatx2($id, $diff)
{
    $x = 0;
    $y = 0;
    $q1 = db_select("select * from styklister where vare_id =$id", __FILE__ . " linje " . __LINE__);
    while ($r1 = db_fetch_array($q1)) {
        $x++;
        $indgaar_i[$x] = $r1['indgaar_i'];
        $belob = $r1['antal'] * $diff;
        db_modify("update varer set kostpris=kostpris+$belob where id=$indgaar_i[$x]", __FILE__ . " linje " . __LINE__);
    }
    $y = $x;
    for ($y = 1; $y <= $x; $y++) {
        $q1 = db_select("select * from styklister where vare_id=$indgaar_i[$y]", __FILE__ . " linje " . __LINE__);
        while ($r1 = db_fetch_array($q1)) {
            if ($r1['indgaar_i'] != $id) {
                $x++;
                $vare_id[$x] = $r1['id'];
                $indgaar_i[$x] = $r1['indgaar_i'];
                $antal[$x] = $r1['antal'];
                db_modify("update varer set kostpris=kostpris+$diff*$antal[$x] where id=$vare_id[$x]", __FILE__ . " linje " . __LINE__);
            } else {
                $vare_id[$x] = $r1['id'];
                $r2 = db_fetch_array(db_select("select varenr from varer where id=$vare_id[$y]", __FILE__ . " linje " . __LINE__));
                db_modify("delete from styklister where id=$r1[id]", __FILE__ . " linje " . __LINE__);
                print "<BODY onLoad=\"javascript:alert('Cirkul&aelig;r reference registreret varenr.: $r2[varenr] fjernet fra styklisten')\">";
            }
        }
    }
}


function prisopdat_xx($id)
{

    $x = 0;
    $query = db_select("select id from varer where delvare = 'on' and samlevare != 'on'", __FILE__ . " linje " . __LINE__); #finder varer paa laveste nevieu
    while ($row = db_fetch_array($query)) {
        $x++;
        $vare_id[$x] = $row['id'];
    }
    $vareantal = $x;

    $x = 0;
    $query = db_select("select * from styklister", __FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($query)) {
        $x++;
        $s_id[$x] = $row['id'];
        $s_vare_id[$x] = $row['vare_id'];
        $s_antal[$x] = $row['antal'];
        $s_indgaar_i[$x] = $row['indgaar_i'];
    }
    $antal_s = $x;
    $kontrol = array();
    $x = 0;
    for ($a = 1; $a <= $vareantal; $a++) {
        $kostpris = 0;
        for ($b = 1; $b <= $antal_s; $b++) {
            if ($vare_id[$a] == $s_indgaar_i[$b]) {
                $query = db_select("select kostpris from vare_lev where vare_id = $s_vare_id[$b] order by posnr", __FILE__ . " linje " . __LINE__); #finder varer 1 nivaau lavere
                if ($row = db_fetch_array($query)) {
                    $kostpris = $kostpris + $row['kostpris'] * $s_antal[$b];
                } else {
                    $query = db_select("select kostpris from varer where id = $s_vare_id[$b]", __FILE__ . " linje " . __LINE__); #finder varer 1 nivaau lavere
                    $row = db_fetch_array($query);
                    $kostpris = $kostpris + $row['kostpris'] * $s_antal[$b];
                }
            }
            if ($vare_id[$a] == $s_vare_id[$b]) {
                $vareantal++;
                $vare_id[$vareantal] = $s_indgaar_i[$b];
            }
        }
        if ($kostpris > 0) {
            db_modify("update varer set kostpris='$kostpris' where id=$vare_id[$a]", __FILE__ . " linje " . __LINE__);
        }
    }
    for ($a = 1; $a <= $vareantal; $a++) {
    }
}

function kategorier($x, $id, $kat_niveau, $kategori_antal, $kat_id, $kategori, $kat_beskrivelse, $kat_master)
{
    global $sprog_id;
    $checked = "";
    for ($y = 0; $y <= $kategori_antal; $y++) {
        if ($kat_id == $kategori[$y])
            $checked = "checked";
    }
    print "<tr><td title=\"ID=$kat_id\">";
    for ($i = 0; $i < $kat_niveau; $i++) {
        print "-&nbsp;";
    }
    #   if ($kat_niveau) print " ";
    print "$kat_master -> $kat_beskrivelse";
    #           if ($show_subcat!=$kat_id[$x]) print "&nbsp;<a href=\"varekort.php?id=$id&show_subcat=$kat_id[$x]\">&nbsp;-></a>";
    print "</td>\n";
    $tekst = findtekst(395, $sprog_id);
    #               $tekst=str_replace('$firmanavn',$firmanavn,$tekst); 
    print "<td title=\"$tekst\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"kat_valg[$x]\" $checked></td>\n";
    print "<td title=\"" . findtekst(396, $sprog_id) . "\"><!--tekst 396--><a href=\"varekort.php?id=$id&    =$kat_id\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
    print "<td title=\"" . findtekst(397, $sprog_id) . "\"><!--tekst 396--><a href=\"varekort.php?id=$id&delete_category=$kat_id\" onclick=\"return confirm('Vil du slette denne katagori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
    print "</tr>\n";
    print "<input type=\"hidden\" name=\"kat_id[$x]\" value=\"$kat_id\">\n";
} # endfunc kategorier

function samletjek($id)
{
    $x = 0;
    $indgaar_i = array();
    $vare_id = array();
    $query = db_select("select vare_id, indgaar_i from styklister where vare_id != $id", __FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($query)) {
        $x++;
        $indgaar_i[$x] = $row['indgaar_i'];
        $vare_id[$x] = $row['vare_id'];
    }
    $query = db_select("select id from varer where id != $id and samlevare='on'", __FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($query)) {
        if (!in_array($row['id'], $indgaar_i)) {
            db_modify("update varer set samlevare = '' where id=$row[id]", __FILE__ . " linje " . __LINE__);
        } else {
            db_modify("delete from vare_lev where vare_id=$row[id]", __FILE__ . " linje " . __LINE__);
        }
    }
    $query = db_select("select id from varer where id != $id and delvare='on'", __FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($query)) {
        if (!in_array($row['id'], $vare_id)) {
            db_modify("update varer set delvare = '' where id=$row[id]", __FILE__ . " linje " . __LINE__);
        }
    }
}

function cirkeltjek($vare_id)
{
    global $id;
    $x = 0;
    $fejl = 0;
    $query = db_select("select styklister.vare_id as vare_id, varer.samlevare as samlevare from styklister, varer where indgaar_i=$vare_id and varer.id=$vare_id", __FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($query)) {
        if ($id == $row['vare_id']) {
            print "<BODY onLoad=\"javascript:alert('Cirkulær reference registreret')\">";
            $x = 0;
            $fejl = 1;
            break 1;
        } elseif (($row['samlevare'] == 'on') && ($fejl != 1)) {
            $x++;
            $s_vare_id[$x] = $row['vare_id'];
        }
    }
    for ($a = 1; $a <= $x; $a++) {
        $query = db_select("select styklister.vare_id as vare_id, varer.samlevare as samlevare from styklister, varer where indgaar_i=$s_vare_id[$a] and varer.id=$s_vare_id[$a]", __FILE__ . " linje " . __LINE__);

        while ($row = db_fetch_array($query)) {
            if ($id == $row['vare_id']) {
                print "<BODY onLoad=\"javascript:alert('Cirkulær reference registreret')\">";
                $a = $x;
                $fejl = 1;
                break 1;
            } elseif (($row['samlevare'] == 'on') && ($fejl != 1)) {
                $x++;
                $s_vare_id[$x] = $row['vare_id'];
            }
        }
    }
    if ($fejl > 0)
        return $fejl;
}

######################################################################################################################################
function kontoopslag($sort, $fokus, $id)
{
    global $bgcolor2;
    global $top_bund;
    global $returside;
    global $ordre_id;
    global $fokus;
    global $vare_lev_id;
    global $opener;
    global $menu;

    include("../includes/topline_settings.php");

    # Malenes topmenu
    if ($menu == 'T') {
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"headerSmallWindow\">
        <div class=\"headerLittlebtnLft\"><a class=\"button red small right\" href=$returside accesskey=L>Luk</a></div>
        <span class=\"headerLittleTxt\">Varekort</span>";
        print "<div class=\"headerLittlebtnRght\"></div>";
        print "</div><!-- end of header -->
            <div class=\"maincontentLargeHolder\">\n";
        print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0  \" width=\"100%\" valign = \"top\">";
        print "<tbody><tr><td colspan=8>";
        print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

    } elseif ($menu == 'S') {
        print "<table width='100%'><tbody>";

        print "<td width=\"10%\">
           <a href=varekort.php?opener=$opener&returside=$returside&ordre_id=$ordre_id&vare_id=$id&id=$id&fokus=$fokus accesskey=L>
           <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Luk</button></a></td>";

        print "<td width=\"80%\" align='center' style='$topStyle'>Varekort</td>";

        print "<td width=\"10%\" align=\"right\" onClick=\"JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\">
           <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">Ny</button></td>";

        print "</tbody></table></td></tr>";

    } else {
        print "<table width='100%'><tbody>";
        print "<td width=\"10%\" $top_bund><a href=varekort.php?opener=$opener&returside=$returside&ordre_id=$ordre_id&vare_id=$id&id=$id&fokus=$fokus accesskey=L>Luk</a></td>";
        print "<td width=\"80%\" $top_bund align=\"center\">Varekort</td>";
        print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\"><u>Ny</u></td>";
        print "</tbody></table></td></tr>";
    }
    print "<table width='100%'><tbody>";
    print "<td><b><a href=varekort.php?opener=$opener&sort=kontonr&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Kontonr</b></td>";
    print "<td><b><a href=varekort.php?opener=$opener&sort=firmanavn&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Navn</b></td>";
    print "<td><b><a href=varekort.php?opener=$opener&sort=addr1&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Adresse</b></td>";
    print "<td><b><a href=varekort.php?opener=$opener&sort=addr2&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Adresse2</b></td>";
    print "<td><b><a href=varekort.php?opener=$opener&sort=postnr&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Postnr</b></td>";
    print "<td><b><a href=varekort.php?opener=$opener&sort=bynavn&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>bynavn</b></td>";
    print "<td><b><a href=varekort.php?opener=$opener&sort=land&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>land</b></td>";
    #   print"<td><b><a href=varekort.php?opener=$opener&sort=kontakt&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Kontaktperson</b></td>";
    print "<td><b><a href=varekort.php?opener=$opener&sort=tlf&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Telefon</b></td>";
    print " </tr>";

    if (!isset($_GET['sort']))
        $_GET['sort'] = null;
    $sort = $_GET['sort'];
    if (!$sort) {
        $sort = 'firmanavn';
    }

    $q = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' order by $sort", __FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($q)) {
        $kontonr = str_replace(" ", "", $row['kontonr']);
        print "<tr>";
        print "<td><a href=varekort.php?id=$id&konto_id=$row[id]&returside=$returside&vare_lev_id=$row[id]>$row[kontonr]</a></td>";
        print "<td>$row[firmanavn]</td>";
        print "<td>$row[addr1]</td>";
        print "<td>$row[addr2]</td>";
        print "<td>$row[postnr]</td>";
        print "<td>$row[bynavn]</td>";
        print "<td>$row[land]</td>";
        #       print "<td>$row[kontakt]</td>";
        print "<td>$row[tlf]</td>";
        print "</tr>";
    }

    print "</tbody></table></td></tr></tbody></table>";
    exit;
}
if ($menu == 'T') {
    print "</tbody>
    </table>
    </td></tr>
    <tr><td align = \"center\" valign = \"bottom\">
            <table width=\"75%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
                <td width=\"75%\"><br></td>
            </tbody></table>
    </td></tr>
    </tbody></table></body></html>
    ";
} elseif ($menu == 'S') {
    print "</tbody>
    </table>
    </td></tr>
    </tbody></table></body></html>
    ";
} else {
    print "</tbody>
    </table>
    </td></tr>
    <tr><td align = \"center\" valign = \"bottom\">
            <table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
                <td width=\"100%\" $top_bund><br></td>
            </tbody></table>
    </td></tr>
    </tbody></table></body></html>
    ";
}
if (!$fokus)
    $fokus = "varenr";
print "<script language=\"javascript\">
document.varekort.$fokus.focus();
</script>";
?>
<script>
    const bool = "<?php echo $confirmDescriptionChange; ?>";
    if (bool != "") {
        const salgspris = document.querySelector("input[name=salgspris]");
        const oldPrice = salgspris.value;
        salgspris.addEventListener("change", (e) => {
            if (confirm("Er du sikker på du vil ændre salgsprisen?") == true) {
                salgspris.value = e.target.value;
                const form = document.querySelector("[name=saveItem]");
                form.click()
            } else {
                salgspris.value = oldPrice;
            }
        })
        const kostpris = document.querySelector("#costPrice");
        const oldCost = kostpris.value;
        kostpris.addEventListener("change", (e) => {
            if (confirm("Er du sikker på du vil ændre kostprisen?") == true) {
                kostpris.value = e.target.value;
                const form = document.querySelector("[name=saveItem]");
                form.click()
            } else {
                kostpris.value = oldCost;
                const form = document.querySelector("[name=saveItem]");
                form.click()
            }
        })
    }
    function getCookie(cname) {
        let name = cname + "=";
        let decodedCookie = decodeURIComponent(document.cookie);
        let ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }
</script>

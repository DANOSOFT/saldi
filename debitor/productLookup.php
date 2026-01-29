<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/productLookup.php --- patch 4.1.1 --- 2025-01-XX ---
// Product lookup page using grid system for order entry
// Based on vareliste.php grid implementation

@session_start();
$s_id = session_id();

$css = "../css/standard.css?v=20";

$include_start = microtime(true);
include("../includes/std_func.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/stdFunc/dkDecimal.php");
include("../includes/ordrefunc.php");

// Get parameters from order page
$id = if_isset($_GET, NULL, 'id');
$art = if_isset($_GET, NULL, 'art');
$sort = if_isset($_GET, NULL, 'sort');
$fokus = if_isset($_GET, NULL, 'fokus');
$vis_kost = if_isset($_GET, NULL, 'vis_kost');
$ref = if_isset($_GET, NULL, 'ref');
$find = if_isset($_GET, NULL, 'find');
$bordnr = if_isset($_GET, NULL, 'bordnr');
$afd_lager = if_isset($_GET, NULL, 'lager');

// Handle product selection - redirect back to order
if (isset($_GET['vare_id'])) {
    $vare_id = $_GET['vare_id'];
    $href = ($art == 'PO') ? "pos_ordre.php" : "ordre.php";
    $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
    $lager_param = ($afd_lager) ? "&lager=$afd_lager" : "";
    $url = "$href?id=$id&vare_id=$vare_id&fokus=$fokus$bordnr_param$lager_param";
    header("Location: $url");
    exit;
}

// Handle multiple item insertion
if (isset($_GET['insertItems']) && isset($_GET['vare_id']) && isset($_GET['antal'])) {
    $href = ($art == 'PO') ? "pos_ordre.php" : "ordre.php";
    $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
    $url = "$href?id=$id&fokus=$fokus$bordnr_param";
    // Add items as query parameters
    $vare_ids = is_array($_GET['vare_id']) ? $_GET['vare_id'] : array($_GET['vare_id']);
    $antals = is_array($_GET['antal']) ? $_GET['antal'] : array($_GET['antal']);
    foreach ($vare_ids as $idx => $vare_id) {
        if (isset($antals[$idx]) && $antals[$idx] > 0) {
            $url .= "&vare_id[]=$vare_id&antal[]=" . $antals[$idx];
        }
    }
    header("Location: $url");
    exit;
}

// Set default values
if (!$sort) {
    if ($bruger_id) {
        $qtxt = "select var_value from settings where var_name='itemLookup' and var_grp='deb_order' and user_id='$bruger_id'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $sort = $r['var_value'];
            if ($sort == "ordredate") {
                $sort = "varenr";
            }
        } else {
            $sort = 'beskrivelse';
        }
    } else {
        $sort = 'beskrivelse';
    }
}

if (!$fokus) {
    $fokus = 'varenr';
}

if (!$ref) {
    $ref = $brugernavn;
}

// Get department and warehouse info
$afd = 0;
if (!$afd && $ref) {
    $qtxt = "select ansatte.afd from ansatte where navn='$ref'";
    ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $afd = $r['afd'] : $afd = 0;
    if (!$afd) {
        $qtxt = "select ansat_id from brugere where brugernavn='$ref'";
        ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $ansat_id = $r['ansat_id'] : $ansat_id = 0;
        $qtxt = "select afd from ansatte where id='$ansat_id'";
        ($ansat_id && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $afd = $r['afd'] : $afd = 0;
    }
}

$lager = NULL;
if ($afd) {
    $qtxt = "select box1 from grupper where kodenr='$afd' and art = 'AFD'";
    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    $lager = (int) $r['box1'];
    if (!$lager) {
        $qtxt = "select kodenr from grupper where box1='$afd' and art = 'LG'";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        $lager = (int) $r['kodenr'];
    }
}

// Get order info if id is provided
if ($id && (!$art || !$ref)) {
    $r = db_fetch_array(db_select("select art,ref from ordrer where id='$id'", __FILE__ . " linje " . __LINE__));
    if (!$art) $art = $r['art'];
    if (!$ref) $ref = $r['ref'];
}

if ($art == 'PO' && !strpos($_SERVER['PHP_SELF'], 'pos_ordre')) {
    $art = 'DO';
}

// Determine href for return links
if ($art == 'DO' || $art == 'DK') {
    $href = "ordre.php";
    $kundeordre = findtekst(1092, $sprog_id);
    $title = "$kundeordre $id - Vareopslag";
} elseif ($art == 'PO') {
    $href = "pos_ordre.php";
    $title = "POS ordre $id - Vareopslag";
} else {
    $href = "ordre.php";
    $title = "Vareopslag";
}

// Include header
if ($art == 'DO' || $art == 'DK') {
    sidehoved($id, "../debitor/ordre.php", "../lager/varekort.php", $fokus, $title);
} elseif ($art == 'PO') {
    // POS order header would go here if needed
}

// Include grid system
include(get_relative() . "includes/grid.php");

// Get VAT-free groups
$momsfri = array();
$x = 0;
$q = db_select("select kodenr from grupper where art='VG' and box7 = 'on' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $momsfri[$x] = $r['kodenr'];
    $x++;
}

// Initialize variables
if (!isset($incl_moms)) {
    $incl_moms = ($art == 'PO') ? 'on' : '';
}
if (!isset($momssats)) {
    $momssats = 25; // Default VAT rate, should be fetched from settings
}

// Get warehouses - use GROUP BY to ensure unique warehouse numbers
$lg_nr = array();
$lg_navn = array();
$seen_kodenr = array(); // Track seen warehouse numbers to prevent duplicates
$x = 0;
$q = db_select("select beskrivelse,kodenr from grupper where art = 'LG' GROUP BY kodenr, beskrivelse order by kodenr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    // Only add if we haven't seen this kodenr before
    if (!in_array($r['kodenr'], $seen_kodenr)) {
        $lg_navn[$x] = $r['beskrivelse'];
        $lg_nr[$x] = $r['kodenr'];
        $seen_kodenr[] = $r['kodenr'];
        $x++;
    }
}

// Column configuration
$columns = array();

// Varenr column
$columns[] = array(
    "field" => "varenr",
    "headerName" => "Varenr",
    "render" => function ($value, $row, $column) use ($href, $id, $fokus, $bordnr, $afd_lager) {
        $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
        $lager_param = ($afd_lager) ? "&lager=$afd_lager" : "";
        $fokus_param = ($fokus) ? "&fokus=$fokus" : "";
        $url = "$href?id=$id&vare_id=$row[id]$fokus_param$bordnr_param$lager_param";
        $alias = htmlspecialchars($row['varenr_alias'] ? $row['varenr_alias'] : '', ENT_QUOTES, 'UTF-8');
        $stregkode = htmlspecialchars($row['stregkode'] ? $row['stregkode'] : '', ENT_QUOTES, 'UTF-8');
        return "<td align='$column[align]' onclick=\"event.preventDefault(); window.location.href='$url'\" style='cursor:pointer'><a href='$url' onclick=\"event.stopPropagation();\">$value</a><span style='display:none;'>$alias $stregkode</span></td>";
    },
    "sqlOverride" => "v.varenr",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
        $words = preg_split('/\s+/', trim($term));
        $conditions = array();
        foreach ($words as $word) {
            if (!empty($word)) {
                $word = db_escape_string($word);
                $conditions[] = "(lower(v.varenr) like '%$word%' or lower(v.varenr_alias) like '%$word%' or lower(v.stregkode) like '%$word%')";
            }
        }
        return !empty($conditions) ? "(" . implode(" AND ", $conditions) . ")" : "1=1";
    },
);

// Enhed column
$columns[] = array(
    "field" => "enhed",
    "headerName" => "Enhed",
    "width" => "0.5",
    "sqlOverride" => "v.enhed",
    "render" => function ($value, $row, $column) use ($href, $id, $fokus, $bordnr, $afd_lager) {
        $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
        $lager_param = ($afd_lager) ? "&lager=$afd_lager" : "";
        $fokus_param = ($fokus) ? "&fokus=$fokus" : "";
        $url = "$href?id=$id&vare_id=$row[id]$fokus_param$bordnr_param$lager_param";
        return "<td align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
);

// Beskrivelse column
$columns[] = array(
    "field" => "beskrivelse",
    "headerName" => "Beskrivelse",
    "width" => "3",
    "sqlOverride" => "v.beskrivelse",
    "render" => function ($value, $row, $column) use ($href, $id, $fokus, $bordnr, $afd_lager) {
        $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
        $lager_param = ($afd_lager) ? "&lager=$afd_lager" : "";
        $fokus_param = ($fokus) ? "&fokus=$fokus" : "";
        $url = "$href?id=$id&vare_id=$row[id]$fokus_param$bordnr_param$lager_param";
        return "<td align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
        $words = preg_split('/\s+/', trim($term));
        $conditions = array();
        foreach ($words as $word) {
            if (!empty($word)) {
                $word = db_escape_string($word);
                $conditions[] = "(lower(v.beskrivelse) like '%$word%' or lower(v.trademark) like '%$word%')";
            }
        }
        return !empty($conditions) ? "(" . implode(" AND ", $conditions) . ")" : "1=1";
    },
);

// Salgspris column
$columns[] = array(
    "field" => "salgspris",
    "headerName" => "Salgspris",
    "type" => "number",
    "align" => "right",
    "width" => "0.5",
    "sqlOverride" => "v.salgspris",
    "render" => function ($value, $row, $column) use ($href, $id, $fokus, $bordnr, $afd_lager, $incl_moms, $momssats, $momsfri) {
        $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
        $lager_param = ($afd_lager) ? "&lager=$afd_lager" : "";
        $fokus_param = ($fokus) ? "&fokus=$fokus" : "";
        $url = "$href?id=$id&vare_id=$row[id]$fokus_param$bordnr_param$lager_param";
        if ($incl_moms && !in_array($row['gruppe'], $momsfri)) {
            $salgspris = $value + $value * $momssats / 100;
        } else {
            $salgspris = $value;
        }
        $formatted = dkdecimal($salgspris, 2);
        return "<td align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$formatted</td>";
    },
);

// Warehouse columns (if multiple warehouses)
$SQLLagerFetch = "";
$SQLLagerJoin = "";
$used_lager_aliases = array(); // Track used aliases to prevent duplicates
if (count($lg_nr) > 1) {
    foreach ($lg_nr as $lg_idx => $lg_kodenr) {
        // Ensure unique alias - use index if duplicate kodenr
        $alias_key = "ls" . $lg_kodenr;
        if (in_array($alias_key, $used_lager_aliases)) {
            $alias_key = "ls" . $lg_kodenr . "_" . $lg_idx;
        }
        $used_lager_aliases[] = $alias_key;
        
        $columns[] = array(
            "field" => "lager" . $lg_kodenr,
            "headerName" => (string) $lg_navn[$lg_idx],
            "type" => "number",
            "align" => "right",
            "width" => "0.2",
            "searchable" => true,
            "decimalPrecision" => 2,
            "sqlOverride" => "COALESCE($alias_key.beholdning, 0)",
            "render" => function ($value, $row, $column) use ($href, $id, $fokus, $bordnr, $lg_kodenr) {
                $lagerId = $column['lagerId'];
                $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
                $lager_param = "&lager=$lg_kodenr";
                $url = "$href?id=$id&vare_id=$row[id]&fokus=$fokus$bordnr_param$lager_param";
                if ($row["samlevare"] == "on") {
                    return "<td></td>";
                }
                if (!$value) {
                    return "<td align='$column[align]'>0,00</td>";
                }
                $formatted = dkdecimal($value, 2);
                return "<td align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$formatted</a></td>";
            },
            "generateSearch" => function ($column, $term) use ($alias_key) {
                $field = "COALESCE($alias_key.beholdning, 0)";
                $term = db_escape_string($term);
                // Check for number range (e.g., "10:50" or "10,50")
                if (strstr($term, ':') || strstr($term, ',')) {
                    $term = str_replace(',', ':', $term);
                    list($num1, $num2) = explode(":", $term, 2);
                    return "round({$field}::numeric, 2) >= '".usdecimal($num1)."' 
                            AND 
                            round({$field}::numeric, 2) <= '".usdecimal($num2)."'";
                } else {
                    $term = usdecimal($term);
                    return "round({$field}::numeric, 2) >= $term 
                            AND 
                            round({$field}::numeric, 2) <= $term";
                }
            },
            "lagerId" => $lg_kodenr,
        );
        $SQLLagerFetch .= "COALESCE($alias_key.beholdning, 0) AS lager$lg_kodenr,\n";
        $SQLLagerJoin .= "LEFT JOIN lagerstatus $alias_key ON v.id = $alias_key.vare_id AND $alias_key.lager = $lg_kodenr\n";
    }
} else {
    // Single warehouse - show total inventory
    $columns[] = array(
        "field" => "beholdning",
        "headerName" => "Beholdning",
        "type" => "number",
        "align" => "right",
        "width" => "0.5",
        "searchable" => true,
        "decimalPrecision" => 2,
        "sqlOverride" => "COALESCE(v.beholdning, 0)",
        "render" => function ($value, $row, $column) use ($href, $id, $fokus, $bordnr, $afd_lager) {
            $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
            $lager_param = ($afd_lager) ? "&lager=$afd_lager" : "";
            $fokus_param = ($fokus) ? "&fokus=$fokus" : "";
            $url = "$href?id=$id&vare_id=$row[id]$fokus_param$bordnr_param$lager_param";
            // Calculate reserved items
            $reserveret = 0;
            $vare_id = $row['id'];
            $q2 = db_select("select * from batch_kob where vare_id='$vare_id' and rest > 0", __FILE__ . " linje " . __LINE__);
            while ($r2 = db_fetch_array($q2)) {
                $q3 = db_select("select * from reservation where batch_kob_id=$r2[id]", __FILE__ . " linje " . __LINE__);
                while ($r3 = db_fetch_array($q3))
                    $reserveret = $reserveret + $r3['antal'];
            }
            $formatted = dkdecimal($value, 2);
            $title = $reserveret > 0 ? " title='Reserveret: $reserveret'" : "";
            return "<td align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'$title>$formatted</td>";
        },
        "generateSearch" => function ($column, $term) {
            $field = "COALESCE(v.beholdning, 0)";
            $term = db_escape_string($term);
            // Check for number range (e.g., "10:50" or "10,50")
            if (strstr($term, ':') || strstr($term, ',')) {
                $term = str_replace(',', ':', $term);
                list($num1, $num2) = explode(":", $term, 2);
                return "round({$field}::numeric, 2) >= '".usdecimal($num1)."' 
                        AND 
                        round({$field}::numeric, 2) <= '".usdecimal($num2)."'";
            } else {
                $term = usdecimal($term);
                return "round({$field}::numeric, 2) >= $term 
                        AND 
                        round({$field}::numeric, 2) <= $term";
            }
        },
    );
}

// Kostpris column (if vis_kost is on)
if ($vis_kost == 'on') {
    $columns[] = array(
        "field" => "kostpris",
        "headerName" => "Kostpris",
        "type" => "number",
        "align" => "right",
        "width" => "0.5",
        "sqlOverride" => "(SELECT kostpris FROM vare_lev WHERE vare_id = v.id ORDER BY posnr LIMIT 1)",
        "render" => function ($value, $row, $column) use ($href, $id, $fokus, $bordnr, $afd_lager) {
            $bordnr_param = ($bordnr) ? "&bordnr=$bordnr" : "";
            $lager_param = ($afd_lager) ? "&lager=$afd_lager" : "";
            $fokus_param = ($fokus) ? "&fokus=$fokus" : "";
            $url = "$href?id=$id&vare_id=$row[id]$fokus_param$bordnr_param$lager_param";
            $formatted = dkdecimal($value, 2);
            return "<td align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$formatted</td>";
        },
    );
}

// Build base query - grid system will add WHERE and SORT
$query = "SELECT 
    v.id AS id,
    v.varenr AS varenr,
    v.varenr_alias AS varenr_alias,
    v.stregkode AS stregkode,
    v.enhed AS enhed,
    v.beskrivelse AS beskrivelse,
    v.trademark AS trademark,
    v.salgspris AS salgspris,
    v.beholdning AS beholdning,
    v.gruppe AS gruppe,
    v.samlevare AS samlevare,
    $SQLLagerFetch
    (SELECT kostpris FROM vare_lev WHERE vare_id = v.id ORDER BY posnr LIMIT 1) AS kostpris
FROM varer v
$SQLLagerJoin
WHERE v.lukket != '1' AND {{WHERE}}
ORDER BY {{SORT}}";

// Grid data configuration
$data = array(
    "table_name" => "varer",
    "query" => $query,
    "columns" => $columns,
    "filters" => array(), // Can add filters later if needed
);

// Render grid
print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid("productLookup$id", $data);
print "</div>";

// Add JavaScript for ESC key to return to order and to preserve order context in links
print "<script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-latest.min.js\"></script>\n";
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/arrowkey.js\"></script>\n";
print "<script type=\"text/javascript\">
    $(document).ready(function () {
        // Store order context
        var orderContext = {
            id: '$id',
            art: '$art',
            fokus: '$fokus',
            bordnr: '$bordnr',
            lager: '$afd_lager',
            href: '$href'
        };
        
        // Intercept all product links to ensure they include order context
        $(document).on('click', '#datatable-productLookup$id a[href*=\"vare_id\"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var href = $(this).attr('href');
            
            // Parse the href to extract the file and query string
            var parts = href.split('?');
            var file = parts[0];
            var queryString = parts[1] || '';
            
            // Parse query parameters
            var params = {};
            if (queryString) {
                queryString.split('&').forEach(function(param) {
                    var keyValue = param.split('=');
                    if (keyValue.length === 2) {
                        params[decodeURIComponent(keyValue[0])] = decodeURIComponent(keyValue[1]);
                    }
                });
            }
            
            // Ensure order context is preserved, but don't override lager if it's already in the URL
            params.id = orderContext.id;
            if (orderContext.art) params.art = orderContext.art;
            if (orderContext.fokus) params.fokus = orderContext.fokus;
            if (orderContext.bordnr) params.bordnr = orderContext.bordnr;
            // Only set lager if it's not already in the URL (preserve warehouse-specific lager from clicked link)
            if (!params.lager && orderContext.lager) {
                params.lager = orderContext.lager;
            }
            
            // Reconstruct the URL
            var paramPairs = [];
            for (var key in params) {
                if (params.hasOwnProperty(key)) {
                    paramPairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
                }
            }
            var newUrl = file + '?' + paramPairs.join('&');
            window.location.href = newUrl;
            return false;
        });
        
        // Also handle row clicks
        $(document).on('click', '#datatable-productLookup$id td[onclick*=\"vare_id\"]', function(e) {
            var onclick = $(this).attr('onclick');
            if (onclick && onclick.indexOf('vare_id') > -1) {
                e.preventDefault();
                e.stopPropagation();
                // Extract the full URL from onclick
                var urlMatch = onclick.match(/window\.location\.href=['\"]([^'\"]+)['\"]/);
                if (urlMatch) {
                    // Use the URL directly from onclick (it already has the correct lager parameter)
                    window.location.href = urlMatch[1];
                } else {
                    // Fallback: construct URL manually
                    var match = onclick.match(/vare_id=([^&'\"]+)/);
                    if (match) {
                        var vare_id = match[1];
                        var lagerMatch = onclick.match(/lager=([^&'\"]+)/);
                        var url = orderContext.href + '?id=' + orderContext.id + '&vare_id=' + vare_id;
                        if (orderContext.fokus) url += '&fokus=' + orderContext.fokus;
                        if (orderContext.bordnr) url += '&bordnr=' + orderContext.bordnr;
                        // Use lager from onclick if present, otherwise use orderContext.lager
                        if (lagerMatch) {
                            url += '&lager=' + lagerMatch[1];
                        } else if (orderContext.lager) {
                            url += '&lager=' + orderContext.lager;
                        }
                        window.location.href = url;
                    }
                }
                return false;
            }
        });
        
        // ESC key to return to order
        $('input[type=\"text\"],textarea,a[href]').keyup(function (e) {
            if (e.which === 27) {
                window.location.href = orderContext.href + '?id=' + orderContext.id;
            }
        });
        
        // Preserve order context in grid form by adding hidden fields
        var form = $('#datatable-wrapper-productLookup$id form');
        if (form.length) {
            if (!form.find('input[name=\"id\"]').length) {
                form.append('<input type=\"hidden\" name=\"id\" value=\"' + orderContext.id + '\">');
            }
            if (orderContext.art && !form.find('input[name=\"art\"]').length) {
                form.append('<input type=\"hidden\" name=\"art\" value=\"' + orderContext.art + '\">');
            }
            if (orderContext.fokus && !form.find('input[name=\"fokus\"]').length) {
                form.append('<input type=\"hidden\" name=\"fokus\" value=\"' + orderContext.fokus + '\">');
            }
            if (orderContext.bordnr && !form.find('input[name=\"bordnr\"]').length) {
                form.append('<input type=\"hidden\" name=\"bordnr\" value=\"' + orderContext.bordnr + '\">');
            }
        }
    });
</script>";
?>


<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- patch 5.0.0 --- 2026-07-16 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260716 MJ  R5 moms periodelaasning: opret tabel, triggerfunction og trigger ved login.
//              CREATE FUNCTION indeholder PL/pgSQL-semikolon og maa ikke koere via db_modify()
//              (injecttjek() ville flagge dem). Bruges pg_query() direkte i stedet.


$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '38' and tekst = 'Stillingsliste'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}

# 20260715 CL/SZ - lager/rapport.php's "Bestilt" (Ordered) column query lost its ordrer.levdate
# range filter (see lager/rapport.php ~line 653) so open orders are found by status/leveret alone.
# Neither was ever indexed, so that query now scans far more rows than the old (incorrect)
# date-bounded version did. These target the actual filter conditions it uses.
db_modify("CREATE INDEX IF NOT EXISTS ordrer_status_idx ON ordrer (status)",__FILE__ . " linje " . __LINE__);
db_modify("CREATE INDEX IF NOT EXISTS ordrelinjer_open_ordre_id_idx ON ordrelinjer (ordre_id) WHERE leveret < antal",__FILE__ . " linje " . __LINE__);

# 20260715 CL/SZ - the ordrer.levdate range filter on the Bestilt query above was restored, so
# index that too now that it's back in active use.
db_modify("CREATE INDEX IF NOT EXISTS ordrer_levdate_idx ON ordrer (levdate)",__FILE__ . " linje " . __LINE__);

# 20260715 CL/SZ - lager/rapport.php's detailed Koeb/Salg loop calls find_kostpris()/
# find_varemomssats() (includes/ordrefunc.php / includes/std_func.php) once per order line -
# also used by debitor/ordre.php, kreditor/ordre.php(M) and includes/rapport.php. These hit
# batch_salg.linje_id, grupper (art,kodenr) and kontoplan (kontonr,regnskabsaar) with no
# supporting index (grupper/kontoplan only had their primary key), forcing a full table scan
# on every single order line processed - the main cost of a large report, not the Bestilt query.
db_modify("CREATE INDEX IF NOT EXISTS batch_salg_linje_id_idx ON batch_salg (linje_id)",__FILE__ . " linje " . __LINE__);
db_modify("CREATE INDEX IF NOT EXISTS grupper_art_kodenr_idx ON grupper (art, kodenr)",__FILE__ . " linje " . __LINE__);
db_modify("CREATE INDEX IF NOT EXISTS kontoplan_kontonr_regnskabsaar_idx ON kontoplan (kontonr, regnskabsaar)",__FILE__ . " linje " . __LINE__);

# 20260715 CL/SZ - lager/rapport.php's per-item loop looks up kostpriser (vare_id, transdate)
# once per item whenever the report's end date isn't today (~line 893). kostpriser only had its
# primary key, so every item forced a full table scan of kostpriser to find its latest price -
# on a large item report this is the same "no index on the hot per-row lookup" issue as above.
db_modify("CREATE INDEX IF NOT EXISTS kostpriser_vare_id_transdate_idx ON kostpriser (vare_id, transdate)",__FILE__ . " linje " . __LINE__);

#####

// R5 moms periodelaasning — opret tabel, funktion og trigger een gang ved login.
// Kontrollen sker paa trigger-eksistens; er triggeren der, springes hele blokken over.
$qtxt = "SELECT 1 FROM pg_trigger WHERE tgname='tr_check_moms_periode_luk' LIMIT 1";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    db_modify("CREATE TABLE IF NOT EXISTS moms_periode_luk (
        id               SERIAL PRIMARY KEY,
        kalender_aar     INTEGER NOT NULL,
        kalender_maaned  INTEGER NOT NULL CHECK (kalender_maaned BETWEEN 1 AND 12),
        status           VARCHAR(6) NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed')),
        lukket_af        VARCHAR(100),
        lukket_dato      TIMESTAMP,
        aabnet_af        VARCHAR(100),
        aabnet_dato      TIMESTAMP,
        UNIQUE (kalender_aar, kalender_maaned)
    )", __FILE__ . " linje " . __LINE__);
    // pg_query() bruges her for at omgaa injecttjek(): PL/pgSQL-kroppen indeholder
    // semikolon uden for enkeltcitater, som injecttjek() ville fejlfortolke som injection.
    $fn  = "CREATE OR REPLACE FUNCTION check_moms_periode_luk() ";
    $fn .= "RETURNS TRIGGER AS \$\$ ";
    $fn .= "BEGIN ";
    $fn .= "    IF EXISTS ( ";
    $fn .= "        SELECT 1 FROM moms_periode_luk ";
    $fn .= "        WHERE kalender_aar    = EXTRACT(YEAR  FROM NEW.transdate) ";
    $fn .= "          AND kalender_maaned = EXTRACT(MONTH FROM NEW.transdate) ";
    $fn .= "          AND status = 'closed' ";
    $fn .= "    ) THEN ";
    $fn .= "        RAISE EXCEPTION 'Perioden % er lukket for bogfoering - kontakt bogholder for at genaabne.', ";
    $fn .= "            TO_CHAR(NEW.transdate, 'MM-YYYY'); ";
    $fn .= "    END IF; ";
    $fn .= "    RETURN NEW; ";
    $fn .= "END; ";
    $fn .= "\$\$ LANGUAGE plpgsql";
    pg_query($connection, $fn);
    pg_query($connection,
        "CREATE TRIGGER tr_check_moms_periode_luk "
        . "BEFORE INSERT OR UPDATE ON transaktioner "
        . "FOR EACH ROW EXECUTE FUNCTION check_moms_periode_luk()");
}

?>

<?php
function ensure_emballage_schema() {
	$q = db_select("SELECT table_name FROM information_schema.tables WHERE table_name='emballage'", __FILE__ . " linje " . __LINE__);
	if (!db_fetch_array($q)) {
		$qtxt = "CREATE TABLE emballage (
			id serial NOT NULL PRIMARY KEY,
			varer_id integer NOT NULL,
			category text,
			type text,
			waste_sorting text,
			niveau text,
			end_user text,
			weight numeric(15,4),
			text text
		)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX emballage_varer_id_idx ON emballage(varer_id)", __FILE__ . " linje " . __LINE__);
	}

	$q = db_select("SELECT table_name FROM information_schema.tables WHERE table_name='emballage_cat'", __FILE__ . " linje " . __LINE__);
	if (!db_fetch_array($q)) {
		$qtxt = "CREATE TABLE emballage_cat (
			id serial NOT NULL PRIMARY KEY,
			type text,
			value text
		)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);

		$seed = array(
			'category' => array('Pap','Papir','Jernholdige metaller','Aluminium','Glas','Plast','Mad- og drikkevarekartoner','Træ','Tekstil','Porcelæn','Kork','Keramik'),
			'type' => array('Kasse','Boks','Flaske','Label','Europalle','Engangspalle','Beskyttelse','Spand','Strækfilm','Pose','Dåse','Bakke','Dunk','Æske','Flamingo','Andet','Låg'),
			'waste_sorting' => array('Genbrugsaffald','Restaffald','Farligt affald'),
			'niveau' => array('Lav (Grøn)','Mellem (Gul)','Høj (Rød)'),
			'end_user' => array('Husholdning','Erhverv'),
		);
		foreach ($seed as $cat => $values) {
			foreach ($values as $v) {
				$ve = db_escape_string($v);
				db_modify("INSERT INTO emballage_cat (type,value) VALUES ('$cat','$ve')", __FILE__ . " linje " . __LINE__);
			}
		}
	}

	$q = db_select("SELECT column_name FROM information_schema.columns WHERE table_name='adresser' AND column_name='enduser_type'", __FILE__ . " linje " . __LINE__);
	if (!db_fetch_array($q)) {
		db_modify("ALTER TABLE adresser ADD COLUMN enduser_type varchar(20)", __FILE__ . " linje " . __LINE__);
	}
}

function emballage_cat_options($type, $selected = '') {
	$te = db_escape_string($type);
	$q = db_select("SELECT value FROM emballage_cat WHERE type='$te' ORDER BY id", __FILE__ . " linje " . __LINE__);
	$out = "<option value=''></option>";
	while ($r = db_fetch_array($q)) {
		$v = $r['value'];
		$h = htmlspecialchars($v, ENT_QUOTES);
		$sel = ($selected !== '' && $selected == $v) ? ' selected' : '';
		$out .= "<option value=\"$h\"$sel>$h</option>";
	}
	return $out;
}

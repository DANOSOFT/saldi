<?php
// 20261113 CDX/NTR SD-599 Isolate the existing stock-warning schema compatibility helper.
// 20261113 CDX/NTR Scope the legacy table lookup to the connected tenant schema.
//
// Self-healing schema check: creates order_stock_warning_log if it doesn't
// exist yet. Cached so it only runs the existence query once per request.
/** @return bool Retains the legacy schema fallback and once-per-request cache. */
function _sw_ensure_log_table()
{
	static $checked = false;
	if ($checked) return true;
	$checked = true;
	$schema = in_array($GLOBALS['db_type'] ?? '', ['mysql', 'mysqli'], true) ? 'DATABASE()' : 'current_schema()';
	$r = @db_fetch_array(@db_select("SELECT table_name FROM information_schema.tables WHERE table_schema=$schema AND table_name='order_stock_warning_log' LIMIT 1", __FILE__ . " linje " . __LINE__));
	if ($r) return true;
	@db_modify("CREATE TABLE IF NOT EXISTS order_stock_warning_log (
		id serial NOT NULL,
		ordre_id integer NOT NULL,
		linje_id integer NULL,
		vare_id integer NULL,
		varenr varchar(50) NULL,
		beskrivelse varchar(255) NULL,
		beholdning numeric(15,3) NULL,
		min_lager numeric(15,3) NULL,
		employee_id integer NULL,
		employee_name varchar(100) NULL,
		note text NOT NULL,
		logged_at timestamp DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	)", __FILE__ . " linje " . __LINE__);
	@db_modify("CREATE INDEX IF NOT EXISTS idx_oswl_ordre ON order_stock_warning_log(ordre_id)", __FILE__ . " linje " . __LINE__);
	return true;
}


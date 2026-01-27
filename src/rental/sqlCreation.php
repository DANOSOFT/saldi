<?php	
	function ensureTableAndColumns($db, $tableName, $expectedColumns, $renameColumns = []) {
		// Check if table exists
		$qtxt = "SELECT table_name FROM information_schema.tables WHERE (table_schema = '$db' or table_catalog='$db') and table_name='$tableName'";
		$tableExists = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	
		if (!$tableExists) {
			// Create table with expected columns and their types
			$columnsTxt = implode(", ", array_map(function($col, $type) { return "$col $type"; }, array_keys($expectedColumns), $expectedColumns));
			$qtxt = "CREATE TABLE $tableName ($columnsTxt)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			// Fetch all columns of the table
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='$tableName'";
			$result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			$columns = [];
			while ($row = db_fetch_array($result)) {
				$columns[] = $row['column_name'];
			}
	
			// Rename columns if specified
			foreach ($renameColumns as $oldName => $newName) {
				if (in_array($oldName, $columns) && !in_array($newName, $columns)) {
					$qtxt = "ALTER TABLE $tableName RENAME COLUMN \"$oldName\" TO \"$newName\"";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					// Update the columns array to reflect the change
					$columns[array_search($oldName, $columns)] = $newName;
				}
			}
	
			// Check if all expected columns exist, if not, add them with their types
			foreach ($expectedColumns as $column => $type) {
				if (!in_array($column, $columns)) {
					$qtxt = "ALTER TABLE $tableName ADD COLUMN $column $type";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
			}
		}
	
		return true;
	}

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'item_name' => 'varchar (255)', 'product_id' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalitems', $expectedColumns);
	
	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'order_id' => 'INTEGER', 'rt_from' => 'numeric(15,0)', 'rt_to' => 'numeric(15,0)', 'item_id' => 'INTEGER', 'cust_id' => 'INTEGER', "expiry_time" => 'TIMESTAMP'];
	ensureTableAndColumns($db, 'rentalperiod', $expectedColumns);
	
	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'item_id' => 'INTEGER', "rr_from" => 'numeric(15,0)', "rr_to" => "numeric(15,0)", 'comment' => 'varchar (255)'];
	$renameColumns = [
		"from" => "rr_from",
		"to" => "rr_to"
	];
	ensureTableAndColumns($db, 'rentalreserved', $expectedColumns, $renameColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'day' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalclosed', $expectedColumns);
	
	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'booking_format' => 'INTEGER', 'search_cust_name' => 'INTEGER', 'search_cust_number' => 'INTEGER', 'search_cust_tlf' => 'INTEGER', 'start_day' => 'INTEGER', 'deletion' => 'INTEGER', 'find_weeks' => 'INTEGER', 'end_day' => 'INTEGER', 'put_together' => 'INTEGER', 'pass' => 'varchar(255)', 'use_password' => 'INTEGER', 'invoice_date' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalsettings', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'host' => 'varchar(255)', 'username' => 'varchar(255)', 'password' => 'varchar(255)'];
	ensureTableAndColumns($db, 'rentalmail', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'product_id' => 'INTEGER', 'descript' => 'text', 'is_active' => 'smallint', 'choose_periods' => 'smallint', 'max' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalremote', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'rentalremote_id' => 'INTEGER', 'amount' => 'INTEGER'];
	ensureTableAndColumns($db, 'rentalremoteperiods', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'payment_intent_id' => 'varchar(255)', 'amount' => 'INTEGER', 'betalings_link' => 'varchar(255)', 'kontonr' => 'INTEGER', 'created_at' => 'TIMESTAMP'];
	ensureTableAndColumns($db, 'betalingslink', $expectedColumns);

	$expectedColumns = ['id' => 'SERIAL PRIMARY KEY', 'apikey' => 'varchar(255)', 'trade_conditions' => 'varchar(255)'];
	ensureTableAndColumns($db, 'rentalpayment', $expectedColumns);
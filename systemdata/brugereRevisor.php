<?php

include "../includes/connect.php";

db_connect($sqhost, $squser, $sqpass, $_POST["db"]);
$query = db_select("SELECT * FROM settings WHERE var_name = 'revisor' AND var_grp = 'system'", __FILE__ . " linje " . __LINE__);
if(db_num_rows($query) > 0){
	db_modify("UPDATE settings SET user_id = $_POST[id] WHERE var_name = 'revisor' AND var_grp = 'system'", __FILE__ . " linje " . __LINE__);
}else{
	db_modify("INSERT INTO settings (var_name, var_grp, user_id) VALUES ('revisor', 'system', $_POST[id])", __FILE__ . " linje " . __LINE__);
}
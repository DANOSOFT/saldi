<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// -------------api/api.php----------lap 3.6.6-----2016-12-07----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// Inspireret af: https://www.leaseweb.com/labs/2015/10/creating-a-simple-rest-api-in-php/

@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// get the HTTP method, path and body of the request
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'],'/'));
$input = json_decode(file_get_contents('php://input'),true);
 
echo __line__;
 // connect to the database
#$link = db_connect('localhost', 'postgres', 'AberKarl', 'udvikling_6');
#mysqli_set_charset($link,'utf8');
 
// retrieve the table and key from the path
$table = preg_replace('/[^a-z0-9_]+/i','',array_shift($request));
$key = array_shift($request)+0;
 
// escape the columns and values from the input object
$columns = preg_replace('/[^a-z0-9_]+/i','',array_keys($input));
$values = array_map(function ($value) use ($link) {
  if ($value===null) return null;
  return db_escape_string($link,(string)$value);
},array_values($input));
 
// build the SET part of the SQL command
$set = '';
for ($i=0;$i<count($columns);$i++) {
  $set.=($i>0?',':'').'`'.$columns[$i].'`=';
  $set.=($values[$i]===null?'NULL':'"'.$values[$i].'"');
}
 
// create SQL based on HTTP method
switch ($method) {
  case 'GET':
    $sql = "select * from `$table`".($key?" WHERE id=$key":''); break;
  case 'PUT':
    $sql = "update `$table` set $set where id=$key"; break;
  case 'POST':
    $sql = "insert into `$table` set $set"; break;
  case 'DELETE':
    $sql = "delete `$table` where id=$key"; break;
}
 
// excecute SQL statement
$result = db_query($link,$sql);
 
// die if SQL statement failed
if (!$result) {
  http_response_code(404);
  die(db_error());
}
 
// print results, insert id or affected row count
if ($method == 'GET') {
  if (!$key) echo '[';
		
  while($i=0;$i<db_num_rows($result);$i++) {
    echo ($i>0?',':'').json_encode(db_fetch_object($result));
  }
  if (!$key) echo ']';
} elseif ($method == 'POST') {
	$insert_id = SELECT nextval('foo_seq'); INSERT INTO table (foo...) values ($link)
echo  pg_insert_id($link);
} else {
  echo pg_affected_rows($link);
}
 
// close mysql connection
mysqli_close($link);
?>

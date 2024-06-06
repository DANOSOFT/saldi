<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- systemdata/diverseIncludes/create_vibrant_term.php ---------- lap 3.9.9----2023.03.15-------
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
// Copyright (c) 2012-2023 saldi.dk aps
// ----------------------------------------------------------------------
@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");

use ErrorException;

$post = json_decode(file_get_contents('php://input'));
$pos_id = $post->{'id'} + 1;

$qtxt = "SELECT var_value FROM settings WHERE var_name='vibrant_auth'";
$apikey = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

# Get max id in table
$qtxt = "SELECT count(var_value) FROM settings WHERE var_grp = 'vibrant_terms'";
$max_id = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
# If there are no terminals we stup new id count
if ($max_id) {
  $max_id = $max_id[0];
} else {
  $max_id = 0;
}

$id = $max_id + 1;

$url = "https://pos.api.vibrant.app/pos/v1/terminals";
$data = array(
  "name" => "Terminal $id", 
  "mode" => "terminal", 
  "descriptor" => "Terminal $id, fra Saldi", 
  "virtual" => false
);#Give me a call

// use key "http" even if you send the request to https://...
$options = array(
    "http" => array(
        "header"  => array(
          "Content-type: application/json",
          "apikey:$apikey",
        ),
        "method"  => "POST",
        "content" => json_encode($data)
    )
);

try {
  $context  = stream_context_create($options);
  $result = file_get_contents($url, false, $context);
  echo $result;

  // Access response body in case of an error
  if (isset($http_response_header)) {
    $responseHeaders = implode("\n", $http_response_header);
    $statusCode = null;
    preg_match('/\d{3}/', $responseHeaders, $matches);
    if (!empty($matches)) {
        $statusCode = $matches[0];
    }
    
    if ($statusCode === '401') {
        throw new Exception("401 Unauthorized, tjek din vibrant API nøjle under `Diverse Valg`.");
    } else if ($statusCode === '403') {
        throw new Exception("403 Forbidden, tjek din vibrant API nøjle under `Diverse Valg`.");
    } else if ($statusCode === '406') {
        throw new Exception("406 Not Accpetable, du har nået dit maks antal vibrant terminaler.");
    }

  }

  $response = json_decode($result, true);

  if ($response === null) {
      // Error parsing JSON response
      throw new Exception("Error parsing JSON response");
  } else {
      // Successful response, access the parsed response data
      // For example, if the response contains a "message" field:
      $name = $response['name'];
      $id = $response['id'];
      $qtxt = "UPDATE settings SET pos_id = -1 WHERE pos_id = $pos_id AND var_grp = 'vibrant_terms'";
      db_modify($qtxt, __FILE__ . " linje " . __LINE__);
      $qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description, pos_id) VALUES ('$name', 'vibrant_terms', '$id', 'A terminal on the vibrant system', $pos_id)";
      db_modify($qtxt, __FILE__ . " linje " . __LINE__);
      $qtxt = "UPDATE settings SET var_value='Vibrant: $name' WHERE pos_id=$pos_id and var_name='terminal_type'";
      db_modify($qtxt,__FILE__ . " linje " . __LINE__);
      echo "success";
  }
} catch (ErrorException $e) {
  echo "Error making the HTTP request: " . $e->getMessage();
  print "\nERROR";
} catch (Exception $e) {
  echo "Error making the HTTP request: " . $e->getMessage();
  print "\nERROR";
}



?>

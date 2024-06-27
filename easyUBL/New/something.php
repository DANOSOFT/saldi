<?php

// Get the data sent via curl from easyUBL and json_decode it
$data = json_decode(file_get_contents('php://input'), true);

// Get the data from the array
$documentXmlBase64Content = $data['documentXmlBase64Content'];
$documentID = $data['ublDocumentId'];

// Decode the base64 data
$decoded_data = base64_decode($documentXmlBase64Content);

// Write the decoded data to an XML file
$file_path = "path/to/$documentID.xml";
file_put_contents($file_path, $decoded_data);


<?php 

# ----------------------------------------------------------------------
date_default_timezone_set('Europe/Copenhagen');

/**
 * Send file to invoice extraction API and save extracted data to .info file
 * 
 * @param string $filePath Full path to the PDF or image file
 * @return array|null Returns array with 'amount', 'date', 'vendor' on success, null on failure
 */
function sendToInvoiceExtractionApi($filePath) {
	$apiUrl = "http://72.62.59.20:5000/extract-invoice";
	$apiKey = "mkwKtJIbiPZx6AwtHVLx6hdWSEyeDWUY";
	
	// Check if file exists
	if (!file_exists($filePath)) {
		echo "File not found: $filePath\n";
		return null;
	}
	
	// Detect file type
	$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
	$allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
	
	if (!in_array($fileExt, $allowedTypes)) {
		return null; // Skip unsupported file types silently
	}
	
	$tempImagePath = null;
	$fileToProcess = $filePath;
	
	// If PDF, convert to image first using ImageMagick
	if ($fileExt === 'pdf') {
		$tempImagePath = sys_get_temp_dir() . '/invoice_' . uniqid() . '.png';
		
		// Try using Imagick PHP extension first
		if (class_exists('Imagick')) {
			try {
				$imagick = new Imagick();
				$imagick->setResolution(150, 150);
				$imagick->readImage($filePath . '[0]');
				$imagick->setImageFormat('png');
				$imagick->setImageCompressionQuality(90);
				$imagick->writeImage($tempImagePath);
				$imagick->clear();
				$imagick->destroy();
				$fileToProcess = $tempImagePath;
			} catch (Exception $e) {
				echo "Imagick PDF conversion failed: " . $e->getMessage() . "\n";
				$tempImagePath = null;
			}
		}
		
		// Fall back to ImageMagick command line
		if ($tempImagePath === null || !file_exists($tempImagePath)) {
			$tempImagePath = sys_get_temp_dir() . '/invoice_' . uniqid() . '.png';
			$escapedInput = escapeshellarg($filePath . '[0]');
			$escapedOutput = escapeshellarg($tempImagePath);
			$command = "convert -density 150 $escapedInput -quality 90 $escapedOutput 2>&1";
			exec($command, $output, $returnCode);
			
			if ($returnCode !== 0 || !file_exists($tempImagePath)) {
				echo "ImageMagick PDF conversion failed\n";
				if ($tempImagePath && file_exists($tempImagePath)) {
					@unlink($tempImagePath);
				}
				return null;
			}
			$fileToProcess = $tempImagePath;
		}
	}
	
	// Read file content
	$fileContent = file_get_contents($fileToProcess);
	if ($fileContent === false || strlen($fileContent) === 0) {
		echo "Failed to read file: $fileToProcess\n";
		if ($tempImagePath && file_exists($tempImagePath)) {
			@unlink($tempImagePath);
		}
		return null;
	}
	
	// Base64 encode the image content
	$base64Image = base64_encode($fileContent);
	
	// Clean up temporary file
	if ($tempImagePath && file_exists($tempImagePath)) {
		@unlink($tempImagePath);
	}
	
	// Prepare API request
	$invoiceId = 'invoice-' . time() . '-' . rand(1000, 9999);
	$requestData = array(
		'id' => $invoiceId,
		'image' => $base64Image,
		'skip_classification' => true
	);
	
	// Make API call
	$ch = curl_init($apiUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Accept: application/json',
		'Authorization: Bearer ' . $apiKey
	));
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	
	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curlError = curl_error($ch);
	curl_close($ch);
	
	if ($curlError) {
		echo "cURL error: $curlError\n";
		return null;
	}
	
	if ($httpCode < 200 || $httpCode >= 300) {
		echo "API returned HTTP $httpCode\n";
		return null;
	}
	
	// Parse response
	$responseData = json_decode($response, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		echo "Failed to parse API response\n";
		return null;
	}
	
	// Extract data
	$amount = null;
	$date = null;
	$vendor = null;
	
	if (isset($responseData['extracted_data'])) {
		$extractedData = $responseData['extracted_data'];
		
		if (isset($extractedData['total_amount'])) {
			$amount = $extractedData['total_amount'];
		}
		
		if (isset($extractedData['invoice_date'])) {
			$date = $extractedData['invoice_date'];
			// Convert DD-MM-YY to YYYY-MM-DD
			if (preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $date, $matches)) {
				$date = '20' . $matches[3] . '-' . $matches[2] . '-' . $matches[1];
			}
		}
		
		if (isset($extractedData['vendor'])) {
			$vendor = $extractedData['vendor'];
		}
	}
	
	// If we got extracted data, create a .info file
	if ($amount !== null || $date !== null || $vendor !== null) {
		// Create .info file path (same as original file but with .info extension)
		$baseName = pathinfo($filePath, PATHINFO_FILENAME);
		$dirName = dirname($filePath);
		$infoFilePath = $dirName . '/' . $baseName . '.info';
		
		// .info file format (same as docPool.php):
		// Line 0: subject (use vendor name or filename)
		// Line 1: account (empty)
		// Line 2: amount
		// Line 3: date
		$subject = $vendor ?? $baseName;
		$account = ''; // Empty - to be filled by user
		$infoAmount = $amount ?? '';
		$infoDate = $date ?? '';
		
		$infoLines = [
			$subject,
			$account,
			$infoAmount,
			$infoDate
		];
		
		if (file_put_contents($infoFilePath, implode(PHP_EOL, $infoLines) . PHP_EOL) !== false) {
			chmod($infoFilePath, 0666);
			echo "Created .info file: $infoFilePath\n";
			echo "  Subject: $subject, Amount: $infoAmount, Date: $infoDate\n";
		} else {
			echo "Failed to create .info file: $infoFilePath\n";
		}
		
		return array(
			'amount' => $amount,
			'date' => $date,
			'vendor' => $vendor
		);
	}
	
	return null;
}
$time = date('Y-m-d H:i:s');
#************************************************************* 
#$hostname="{imap-mail.outlook.com:993/imap/ssl/notls}INBOX";
#//saldibilag
# /saldibilag/ssl5.saldi.dk/saldi_6/pulje
# /saldibilag/ssl4.saldi.dk/saldi_6/pulje

$emailName = "doc@docs.saldi.dk";
$password = "76Vy9NxK";


#$mbox = imap_open($hostname, $emailName, $password);

//************************************************************* */
$rootFolder = "/saldibilag";

$mailer = "mailer@saldi.dk";

set_time_limit(3000);
$nameOffile = date("Y-m-d_H-i-s");


$forwarded = "Fwd";
$hostname="{mx05.kikkenborg.it:993/imap/ssl/novalidate-cert}INBOX";
#$hostname="{mx05.kikkenborg.it:993/imap/ssl/novalidate-cert}";
$trashname = "{mx05.kikkenborg.it:993/imap/ssl/novalidate-cert}Trash";
/* try to connect */
#       	 "<hr>";
$inbox = imap_open($hostname,$emailName,$password) or die('Cannot connect to e-mail: ' . imap_last_error());
$headers = imap_headers($inbox);
#   echo count($headers)."";
 

file_put_contents("/scripts/email.log",__line__." ".date('Y-m-d H:i')."\n",FILE_APPEND);

$processedMails = array();

#if (file_exists ("/scripts/processedMails.txt")) $processedMails = explode("\n",file_get_contents("/scripts/processedMails.txt"));
for ($x=1;$x<=count($headers);$x++) {
	$IsAttachment = false; 
	#cho "\n ------------------------- \n";
  $headerInfo[$x] = imap_headerinfo($inbox,$x);	
	#var_dump($headerInfo[$x]);
	#       $to_address = $headerInfo[$x]->toaddress;
	//*******************************************************
                                if(isset($headerInfo[$x]->toaddress)){
                                        $Taddress = $headerInfo[$x]->toaddress;
                                    $to_address = getEmailAddressFromMany($Taddress);// for  cc or bcc
                                    echo "To Address Alp: $to_address\n";
                                }else{
                                    $to_address=NULL;
                                }
                                ##For specific situations when Bcc is not in the Field
                                $headerIn = imap_fetchheader($inbox, $x);
                                $toAdd = emailExistsInCCBCC($headerIn);
                                if($toAdd){
                                    $to_address = $toAdd;
                                    echo "To Address Bcc: $to_address\n";
                                }
	//*******************************************************

	
#	(isset($headerInfo[$x]->toaddress))?$to_address = $headerInfo[$x]->toaddress:$to_address=NULL;
	(isset($headerInfo[$x]->ccaddress))?$cc_address = $headerInfo[$x]->ccaddress:$cc_address=NULL;
	(isset($headerInfo[$x]->bccaddress))?$bcc_address = $headerInfo[$x]->bccaddress:$bcc_address=NULL;
#	echo __line__." To $to_address\n";
#	echo __line__." Cc $cc_address\n";
#	echo __line__." Bcc $bcc_address\n";
	$saldi_ext = "saldi.dk";
	$toaddress_ext = substr(trim($to_address,">"), -8);
	if ($saldi_ext != $toaddress_ext) {
    $ccaddress_ext = substr(trim($cc_address,">"), -8);
    $bccaddress_ext = substr(trim($bcc_address,">"), -8);
    if ($saldi_ext == $ccaddress_ext) $to_address = $cc_address;
    elseif ($saldi_ext == $bccaddress_ext) $to_address = $bcc_address;
	}

			$structure = imap_fetchstructure($inbox, $x);
	
####################### Formatting the headers for messages to be forwarded
	$etype = array('text', 'multipart', 'message', 'application', 'audio','image', 'video', 'model', 'other');
	$type = $structure->type;
	if ($type == 1 && $structure->ifparameters == 1) {
		$parameters = $structure->parameters;
		$attribute = $parameters[0]->attribute;
		$value = $parameters[0]->value;
		#cho $attribute . "//" . $value . "<br />";
	}

	$Headers ="From:" . $mailer . "\r\n";
	$Headers .="Date: " . date("r") . "\r\n";
	$Headers .="MIME-Version: 1.0\r\n";
	$Headers .="Content-Type: " . $etype[$structure->type] . '/' . strtolower($structure->subtype) . ";\r\n";
	if ($type == 1) { // multipart
		$Headers .= "\t boundary=\"" . $value . "\""."\r\n";
	}
######################				
	   $headerm = $headerInfo[$x];

        if ($headerm && is_object($headerm)) {
            if (isset($headerm->message_id)) {
                $message_id = trim($headerm->message_id);
            } else {
                $message_id = '';
            }
        } else {
            $message_id = null;
        }


	#$subject = trim($headerInfo[$x]->subject);
	$subject = isset($headerInfo[$x]->subject) ? trim($headerInfo[$x]->subject) : time();
	if (strpos($subject,"***POSSIBLE SPAM*** Score/Req:") && strpos($subject,"Undelivered Mail Returned to Sender")) {
		$subject = "Undelivered Mail Returned to Sender";
	}
	#cho __line__." Subject $subject\n";    
#	if (in_array($message_id,$processedMails) && $to_address != 'saldi_6@ssl5.saldi.dk') {
	if ((in_array($message_id,$processedMails) && $to_address != 'saldi_6@ssl5.saldi.dk')|| $message_id == "<D253BFEB-ED18-490B-B1B2-740F0080B575@ibon.dk>" ) {
		#cho "$message_id allready processed\n";
	} else {
		#cho "processing $message_id\n";
		$to_address = strtolower($to_address);
		$to_address = str_replace('rotary_95@ssl.saldi.dk','rotary_95@ssl7.saldi.dk',$to_address);
		$to_address = str_replace('saldi_715@ssl.saldi.dk','saldi_715@ssl5.saldi.dk',$to_address);
		if (strpos($to_address,"<") && substr($to_address,-1 == ">")) {
			$tmpA = explode ("<",$to_address);
			$to_address = end($tmpA);
			$to_address = trim ($to_address,">");
		}
		echo __line__." To address: $to_address\n";     

		list($reciever,$domain) = explode("@",strtolower($to_address));
		#$msgno = $headerInfo[$x]->Msgno;
        if (is_object($headerInfo[$x]) && isset($headerInfo[$x]->msgno)) {
            $msgno = $headerInfo[$x]->msgno;
        } else {
            $msgno = null; // or handle the error
        }
		$to_address1 = explode("@",$to_address);
		#cho __line__." Reciever $reciever - Domain $domain\n";
		/*      
		if (strpos($reciever,"<") && substr($reciever,-1 == ">")) {
			$tmpA = explode ("<",$reciever);
			$reciever = end($tmpA);
			$reciever = trim ($reciever,">");
		}
		*/
		if (strpos('x'.$reciever,"bilag_pos_") && ($domain == "ssl.saldi.dk")) {
			$domain = 'ssl4.saldi.dk';
		}
		if (strpos($reciever,"_saldi_") && ($domain == "ssl.saldi.dk" || $domain == "syntax_error")) {
			$domain = 'ssl5.saldi.dk';
		}
		if (strpos($reciever,"_rotary_") && ($domain == "ssl.saldi.dk" || $domain == "syntax_error")) {
			$domain = 'ssl7.saldi.dk';
		}
		if (strpos($reciever,"rotary_95") && $domain == "ssl.saldi.dk") {
			$domain = 'ssl7.saldi.dk';
		}
		if (strpos($reciever,"_ida_") && ($domain == "ssl.saldi.dk" || $domain == "syntax_error")) {
			$domain = 'ssl7.saldi.dk';
		}
		if (strpos($reciever,"_agercon_") && ($domain == "ssl.saldi.dk" || $domain == "syntax_error")) {
			$domain = 'ssl7.saldi.dk';
		}
		if (strpos($reciever,"_exitsound_") && ($domain == "ssl.saldi.dk" || $domain == "syntax_error")) {
			$domain = 'ssl7.saldi.dk';
		}
		if (strpos($reciever,"_al_regnskab_") && ($domain == "ssl.saldi.dk" || $domain == "syntax_error")) {
			$domain = 'ssl7.saldi.dk';
		}
        if (strpos($reciever,"saldi_") && ($domain == "ssl.saldi.dk" || $domain == "syntax_error")) {
			$domain = 'ssl5.saldi.dk';
        }
        if (strpos($reciever,"gratis_7149") && ($domain == "ssl.saldi.dk" || $domain == "syntax_error")) {
                    $domain = 'ssl9.saldi.dk';
        }	
		echo __line__." Reciever $reciever - Domain $domain\n";
	
		(is_numeric($reciever))?$accountId = $reciever:$accountId = NULL;
	    echo __line__." To address2: $to_address and AccountId: $accountId \n";  
		if ($accountId || substr($reciever,0,6)=='bilag_') {
			$filename = NULL;
			/* get all new emails. If set to 'ALL' instead
			* of 'NEW' retrieves all the emails, but can be 
			* resource intensive, so the following variable, 
			* $max_emails, puts the limit on the number of emails downloaded.
			* 
			*/

			echo __line__." Account ID3 $accountId\n";       
			#var_dump($headerInfo[0]);
			if ($accountId) {
				#cho __line__." accountId $accountId\n";
				$folderName=$rootFolder."/". $accountId; //this creates a directory of the prefix email if it doesn't exit.
				#cho __line__." FolderName $folderName\n";
				echo "with accoundId $accountId";
				if (!file_exists($folderName)) {
					mkdir ($folderName);
					chmod($folderName, 0777);
				}
				$folderName.="/pool";
				if (!file_exists("$folderName")) {
					mkdir ($folderName);
					chmod($folderName, 0777);
				}
			} else {
				$reciever = str_replace ('bilag_','',$reciever);
			echo __line__." Reciever $reciever\n";
				$folderName = $rootFolder."/".$domain."/".$reciever;
				#echo __line__."Bz FolderName $folderName\n";
				if (!file_exists($folderName)) {
					mkdir ($folderName);
					chmod($folderName, 0777);
				}
				$folderName.= "/pulje";
				#cho __line__." FolderName $folderName\n";
				if (!file_exists($folderName)) {
					mkdir ($folderName);
					chmod($folderName, 0777);
					#cho __line__." $folderName created\n";                 
				}
			}
			#cho __line__." FolderName $folderName\n";
			$email_number = $x;
			/* get information specific to this email */
			$overview = imap_fetch_overview($inbox,$email_number,0);

			/* get mail message, not actually used here. 
			Refer to http://php.net/manual/en/function.imap-fetchbody.php
			for details on the third parameter.
			*/
			$message = imap_fetchbody($inbox,$email_number,2);
			/* get mail structure */
			$attachments = array();
			/* if any attachments found... */
			if (isset ($structure->parts) && count($structure->parts)) {
				for($i = 0; $i < count($structure->parts); $i++) {
					$attachments[$i] = array(
						'is_attachment' => false,
						'filename' => '',
						'name' => '',
						'attachment' => ''
					);
					if ($structure->parts[$i]->ifdparameters) {
						foreach($structure->parts[$i]->dparameters as $object) {
							if(strtolower($object->attribute) == 'filename' || strtolower($object->attribute) == 'filename*') {
								$attachments[$i]['is_attachment'] = true;
								$attachments[$i]['filename'] = $object->value;
								$IsAttachment = true; //used to initialize emails without attachments
#echo "FILNAME IS ".$attachments[$i]['filename']."\n";
								#file_put_contents("/scripts/filenames.log",__line__." FN ". $attachments[$i]['filename'] ."\n",FILE_APPEND);
							} 
						}
					}
					if ($structure->parts[$i]->ifparameters) {
						foreach ($structure->parts[$i]->parameters as $object) {
							if (strtolower($object->attribute) == 'name') {
								$attachments[$i]['is_attachment'] = true;
								$attachments[$i]['name'] = $object->value;
								$IsAttachment = true;
#echo "FILNAME IS ".$attachments[$i]['filename']."\n";
#file_put_contents("/scripts/filenames.log",__line__." N ". $attachments[$i]['name'] ."\n",FILE_APPEND);
							}
						}
					}
#file_put_contents("/scripts/filenames.log",__line__." A ". $attachments[$i]['attachment'] ."\n",FILE_APPEND);
					if ($attachments[$i]['is_attachment']) {
						$attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i+1);
						/* 3 = BASE64 encoding */
						if ($structure->parts[$i]->encoding == 3) {
							$attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
						}
						/* 4 = QUOTED-PRINTABLE encoding */
						elseif ($structure->parts[$i]->encoding == 4) {
							$attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
						}
					}
				}
			}
          $isDotEml =false;
		  
			/* iterate through each attachment and save it */
			foreach($attachments as $attachment) {  
				if($attachment['is_attachment'] == 1) { // Check if the attachment is valid
					$filename = $attachment['name'];
					if(substr($filename, -4) === '.eml'){
						$isDotEml = true;
						continue;
					}  
						#file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
					if ($filename == '=?UTF-8?Q?Vedh=c3=a6ftet_meddelelsesdel?=') break(1);
					#cho __line__." $filename\n";
					$filename = trim($filename);
					if (substr($filename,0,8) == '=?UTF-8?') {
		    			########################### Decodes UTF encoded filenames
      		                    //    $decodedFilename = preg_replace_callback(
				                //           '/=\?UTF-8\?B\?([^\?]+)\?=/i',
		                        //   function($match) {
                		        //       return base64_decode($match[1]);
		                        //   },
                			    //       $filename
		                        //  );

                                #############decode both B and Q encoding#################
                                    $decodedFilename = preg_replace_callback(
                                        '/=\?UTF-8\?([QB])\?([^\?]+)\?=/i',
                                        function ($match) {
                                            $encoding = strtoupper($match[1]);
                                            $text = $match[2];
                                            if ($encoding === 'B') {
                                                return base64_decode($text);
                                            } elseif ($encoding === 'Q') {
                                                // imap_qprint handles quoted-printable decoding
                                                return imap_qprint($text);
                                            }
                                            return $text;
                                        },
                                        $filename
                                    );
                                #############20251015######################

                      			if ($decodedFilename !== false && $decodedFilename !== $filename) {
		                            # $filename = utf8_decode($decodedFilename);
                                    echo "Decoded Filename: $decodedFilename\n";
                                     $filename = $decodedFilename;
                		         } else {
                                    echo "Failed to decode filename or no decoding needed: $filename\n";
                        		$filename = substr($filename,10);
                        		$filename = substr($filename,0,strlen($filename)-2);
                        		$filename = str_replace('=c3=a6','ae',$filename);
                        		$filename = str_replace('=c3=86','AE',$filename);
                       		 	$filename = str_replace('=c3=b8','oe',$filename);
                        		$filename = str_replace('=c3=98','OE',$filename);
                        		$filename = str_replace('=c3=a5','aa',$filename);
                        		$filename = str_replace('=c3=85','AA',$filename);
                      			}
                    			##########################
							/*
										$filename = substr($filename,10);
							#file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
										$filename = substr($filename,0,strlen($filename)-2);
										$filename = str_replace('=c3=a6','ae',$filename);
										$filename = str_replace('=c3=86','AE',$filename);
										$filename = str_replace('=c3=b8','oe',$filename);
										$filename = str_replace('=c3=98','OE',$filename);
										$filename = str_replace('=c3=a5','aa',$filename);
										$filename = str_replace('=c3=85','AA',$filename);
							#file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
							*/
					}
					if (substr($filename,0,11) == '=?iso-8859-') {
						$filename = substr($filename,15);
#file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
						$filename = substr($filename,0,strlen($filename)-2);
						$filename = str_replace('=E6','ae',$filename);
						$filename = str_replace('=C6','AE',$filename);
						$filename = str_replace('=F8','oe',$filename);
						$filename = str_replace('=D8','OE',$filename);
						$filename = str_replace('=E5','aa',$filename);
						$filename = str_replace('=C5','AA',$filename);
                #file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
					}
                     echo "Filenameb4Pregconv: $filename\n";
					#$filename = preg_replace('/[^A-Za-z0-9\-.]/', '_', $filename);
                    $filename = preg_replace('/[^\p{L}0-9\-.]/u', '_', $filename); //20251015
                     echo "Filenameb4Conv: $filename\n";
                    #######
                    $last5 = substr($filename, -5);
                   if(stripos($last5, 'png') !== false) {
                       $filename = sanitize_filename($filename); //20251010
                    }
                      
                     
                     echo "Filename: $filename\n";
                     echo "Elast five;".$last5."\n";
                    #######
                #file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
					#cho __line__." $filename\n";
					if (empty($filename)) $filename = $attachment['filename'];
                #file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
					#cho __line__." $filename\n";
					if (empty($filename)) $filename = time() . ".dat";
					#cho __line__." $filename\n";
					$tmpA = explode('.',$filename);
					$extension = end($tmpA);
					$tmp = strlen($filename) - (strlen($extension)+1);
					$filenameWithoutExtention = substr($filename,0,$tmp);
					$fullName = $folderName ."/". $filename;
					echo __line__."fullName: $fullName ($filename)\n";
					$c=0;
					while (file_exists($fullName)) {
						$c++;
						$tmp = $filenameWithoutExtention."($c)";
                #file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
						$fullName=str_replace($filenameWithoutExtention,$tmp,$folderName ."/". $filename);
                #file_put_contents("/scripts/filenames.log",__line__." $filename\n",FILE_APPEND);
					
					}
					#echo __line__." tmp: ($tmp)\n";
					// create new file here
					# $filename = str_replace(' ','_',$filename);
					if(file_put_contents($fullName, $attachment['attachment'])){
						chmod($fullName, 0666);
						
						// Send file to invoice extraction API
						sendToInvoiceExtractionApi($fullName);
           
						print "To be deleted $message_id ? \n";
						#cho __line__." Messages before delete: " . $check->Nmsgs . "\n";
						$rs1 = imap_mail_copy($inbox,$x,'Trash');
						if($rs1){
							$rs2 = imap_delete($inbox, $x);
							#	f($rs2) echo "$message_id deleted\n";
						}  else echo "Copying $message_id failed\n";
					}
                ##############################################
				} //end if for attachment
			} //end loop
			//else if attachment doesn't exist, imap_body should be copied from here to html file //
			$url = "https://saldi.dk/locator/locator.php?action=getDbMail&loe=9&dbName=$reciever";
			$email = trim(file_get_contents($url),'"');
			#$take = array();
			if ($email == 'okhalelawrence@gmail.com' || $email == 'okhalelawrence@hotmail.com' ) {
				$email = 'loe@saldi.dk';
			}
	    //  Check if the email is a .eml file and later other edge cases? 
		 echo "Checking if email is .eml0 file \n";	
         
		  
		 if($isDotEml && $IsAttachment){ 
			 echo "Checking if email is .eml1 file: $filename \n";	
				if(strlen($filename)<=60){
					 echo "Checking if email is .eml2 file \n";	
					#$time = date('Y-m-d H:i');
					#$msgStructure = imap_fetchstructure($inbox, $x);
					$totalAttachments = countAttachments($structure);
					$logmsg = "[$time] : Processing embedded attachments in .eml998";
					#$logmsg = print_r($structure, true);
					file_put_contents("/scripts/check.txt", $logmsg."\n", FILE_APPEND);
					echo "Found $totalAttachments PDF attachments\n";
					if ($totalAttachments > 1) {
					 echo "Multiple attachments found: $totalAttachments<br>";
						$flat = flattenParts($structure->parts);
						$pdfs = findPdfParts($flat);
					    #print_r($pdfs);
						savePdfAttachmentsFromParts($inbox, $x, $pdfs, $folderName);
					} else {
						print "Single attachment foundxxxec: $filename<br>";
						download_attachment($inbox, $x, $structure, $folderName, $subject);
					}
				}
			}

        //++++++++++++++++++++Handles Emails without attachments++++++++++++++++++++++++++++++
       # var_dump($IsAttachment);    
        if($IsAttachment == false){
        
            # $subject1 = trim($headerInfo[$x]->subject);
            $subject1 = isset($headerInfo[$x]->subject) ? trim($headerInfo[$x]->subject) : time();
                //Loop through to decode the subject if the are encoded
                $subject = '';
                $subjectParts = imap_mime_header_decode($subject1);
                foreach ($subjectParts as $part) {
                    $subject .= $part->text;
                }
                $subject = preg_replace('/[^A-Za-z0-9\-.]/', '_', $subject);
            
                if($subject!="Undelivered Mail Returned to Sender"){
                    $emailBody= imap_fetchbody($inbox, $x, 1);
                    if (!empty($structure->parts)) {
                        for ($i = 0; $i < count($structure->parts); $i++) {
                            if (is_object($structure->parts[$i]) && isset($structure->parts[$i]->subtype)) {
                                $take[$i] = strtolower($structure->parts[$i]->subtype);
                            } else {
                                $take[$i] = null; 
                            }
                        }
                    }
        /*
                    if(in_array("related", $take )) {
                        $raw_email_text = quoted_printable_decode(imap_fetchbody($inbox,$x,2.1));

                    } else {
                        $raw_email_text = quoted_printable_decode(imap_fetchbody($inbox,$x,2));
                    }
        */ 
            if(in_array("related", $take )) {
                $raw_email_text = quoted_printable_decode(imap_fetchbody($inbox,$x,2.1));
        
            } elseif(in_array("alternative", $take)){
                //$plainText = imap_fetchbody($imapStream, $messageNumber, $index + 1.1);
                $plainText  = quoted_printable_decode(imap_fetchbody($inbox,$x,1.1));
                // Fetch the HTML part (usually with subtype 'html')
                $htmlText = quoted_printable_decode(imap_fetchbody($inbox,$x,1.2));

                //  use HTML if available, otherwise use plain text
                $raw_email_text  = !empty($htmlText) ? $htmlText : $plainText;
            
            } elseif(in_array("rfc822", $take)){
                $raw_email_text = quoted_printable_decode(imap_fetchbody($inbox, $x, 2.0));
            }elseif(in_array("plain", $take)){
                $raw_email_text = quoted_printable_decode(imap_fetchbody($inbox, $x, 1));
            }else {
                $raw_email_text = quoted_printable_decode(imap_fetchbody($inbox,$x,2));
            }          
                    $subjects = preg_replace('/[^A-Za-z0-9\-.]/', '_', $subject);
                                                
                                    $saved =  file_put_contents("$folderName/".$subjects.".html", "$raw_email_text\n", FILE_APPEND);
                            if($saved !== false){ 
                                            $rs1 = imap_mail_copy($inbox,$x,'Trash');
                                            if ($rs1) {
                                                            $rs2 = imap_delete($inbox, $x);
                                                    if($rs2) {
                                                        echo "$message_id deleted\n";
                                                    }
                                                    

                                            }else{
                                                file_put_contents("/scripts/email_1.log", 'error saving file'.date('Y-m-d H:i')."\n",FILE_APPEND);
                                            }
                            }else{
                            file_put_contents("/scripts/email_1.log", 'error saving file'.date('Y-m-d H:i')."\n",FILE_APPEND);

                        } 

                //******************************embedded emails as alternatives*********************20230619*
                        if ($structure->type === 1 && $structure->subtype === 'ALTERNATIVE') {
                                            
                                            $raw_email_text = imap_fetchbody($inbox, $x, 2.0);
                                                if($raw_email_text == Null ||$raw_email_text == " "){
                                                $raw_email_text = imap_fetchbody($inbox, $x, 1.2)  ;
                                                }
                                                if ($structure->encoding === 1) {
                                                $raw_email_text = imap_utf8($raw_email_text);
                                                } elseif ($structure->encoding === 2) {
                                                $raw_email_text = imap_binary($raw_email_text);
                                                } elseif ($structure->encoding === 3) {
                                                $raw_email_text = imap_base64($raw_email_text);
                                                } elseif ($structure->encoding === 4) {
                                                $raw_email_text = quoted_printable_decode($raw_email_text);
                                                }
                                                
                                                
                                                $raw_email_text = imap_qprint($raw_email_text);
                                                            
                                                        $saved =  file_put_contents("$folderName/".$subjects.".html", "$raw_email_text\n", FILE_APPEND);
                                                        if($saved !== false){
                                                            $rs1 = imap_mail_copy($inbox,$x,'Trash');
                                                            if ($rs1) {
                                                                            $rs2 = imap_delete($inbox, $x);
                                                                    if($rs2) {
                                                                        echo "$message_id deleted W\n";
                                                                    }
                            
                            
                                                            }else{
                                                                file_put_contents("/scripts/email_1.log", 'error saving file'.date('Y-m-d H:i')."\n",FILE_APPEND);
                                                            }
                                                        }else{
                                                                file_put_contents("/scripts/email_1.log", 'error saving file'.date('Y-m-d H:i')."\n",FILE_APPEND);
                                                        }        
                                            }
            }
        
                
        }

			
        //++++++++++++++++++++Ends Emails without attachments++++++++++++++++++++++++++++++				
                            
        #++++++++++++++++++++++Handles Forwarded Emails with files++++++++++++++++++
                #file_put_contents("/scripts/check.txt","Filename is found $filename\n"); 
                    //Loop through to decode the subject if they are encoded
                $subject1 = '';
                $subjectParts = imap_mime_header_decode($subject);
                foreach ($subjectParts as $part) {
                    $subject1 .= $part->text;
                }
                if (strpos($subject1, $forwarded) !== false) {
                    // Get the email structure
                    $structure = imap_fetchstructure($inbox, $x);   
                    inlineMails($inbox, $x, $structure,0,$subject1);//This Method is defined below
                }
        #++++++++++++++++++++++++++++++++++++++++
#		} elseif ($subject == 'Undelivered Mail Returned to Sender' || strpos($to_address,'ssl.saldi.dk') === false) {
		if ($filename) file_put_contents("/scripts/processedMails.txt", $message_id."\n", FILE_APPEND);
	} elseif ($subject == 'Undelivered Mail Returned to Sender' || strpos($to_address,'ssl.saldi.dk') !== false || (substr($to_address, -9) == '.saldi.dk' || $to_address == 'faktura@saldi.dk' )) {
	#	 elseif ($subject == 'Undelivered Mail Returned to Sender') {
            echo "processing bounced mails......";
				###########################################
				 $Headerss =  imap_fetchheader($inbox, $x);//As forwarding mail, this takes the initial message header properties.
				############################################
			$url = "https://saldi.dk/locator/locator.php?action=getDbMail&loe=9&dbName=$reciever";
#			echo "url $url\n";
			if($subject !='Undelivered Mail Returned to Sender'){
    				$subject = 'Bounced email';
			}
			$email = trim(file_get_contents($url),'"');
			file_put_contents("/scripts/fw_mails.txt","$reciever -> $email\n");
               $headerInfo[$x] = imap_headerinfo($inbox,$x);	
                $overview = imap_fetch_overview($inbox, $x, 0);
                $from_address = $headerInfo[$x]->from[0]->mailbox . '@' . $headerInfo[$x]->from[0]->host;
     		
#			if ($email == 'faktura@saldi.dk' || $email == 'loe@saldi.dk' ) { #Should just be: if ($email) {
			#if ($email == 'okhalelawrence@gmail.com') $email = 'loe@saldi.dk';
           
			if ($email) {
                #if (!$email) $email = $from_address; #20250813
				if ($r=imap_mail($email, $subject, imap_body($inbox, $x), $Headerss)) {
					#if ($r=imap_mail($email, $subject, $em, $Headers)) {
					$rs1 = imap_mail_copy($inbox,$x,'Trash');
					if($rs1) {
						$rs2 = imap_delete($inbox, $x);
						if($rs2) {
							echo "$message_id deleted M\n";
						}
					} #else echo "Copying $message_id failed\n";
				} #else echo "Sent to $email failed";
				file_put_contents("/scripts/fw_mails.txt","$email sent\n", FILE_APPEND);
			}elseif($from_address){

                 //Send a message that email wasn't treated
                $headerInfo[$x] = imap_headerinfo($inbox,$x);	
                $overview = imap_fetch_overview($inbox, $x, 0);
                $from_address = $headerInfo[$x]->from[0]->mailbox . '@' . $headerInfo[$x]->from[0]->host;

                $subject = "Undeliverable: " . $overview[0]->subject;  //20250812
                $body = "Your email to $to_address was rejected.";
             
                // Send bounce
                mail($from_address, $subject, $body, "From: noreply@saldi.dk");

                // delete or move the message
                   $rs1 = imap_mail_copy($inbox,$x,'Trash');
					if($rs1) {
						$rs2 = imap_delete($inbox, $x);
						if($rs2) {
							echo "$message_id deleted for unprocessed mail\n";
						}
					}
                
                file_put_contents("/scripts/bounced_mails.txt", "email to: $to_address rejected at $nameOffile\n", FILE_APPEND);
            }
			file_put_contents("/scripts/processedMails.txt", $message_id."\n", FILE_APPEND);
		}else{

         file_put_contents("/scripts/unknown_mails.txt", "$nameOffile - Email to : $to_address not processed and Domain is:$domain \n", FILE_APPEND);
       }
	}
      
	$check = imap_mailboxmsginfo($inbox);
}
imap_close($inbox, CL_EXPUNGE);
echo "Done";


// Recursive function to process the email and its parts
function inlineMails($inbox, $x, $part, $partNum,$subject) {
  global $folderName;
  echo "inside inline function \n";
  $subject = preg_replace('/[^A-Za-z0-9\-.]/', '_', $subject); //
  // Check if the part is an inline files
  if (isset($part->disposition) && $part->disposition === 'inline') {
      // Get the image filename

              $filename = $part->dparameters[0]->value;
                        $filename = urldecode($filename);
        //Check if the filename is more than a regular file
        if(strlen($filename)<=60){

                //Process the PDF part
                ###################################################
                if ($part->type == 3 && $part->subtype == 'PDF') {
                        $fileData = imap_fetchbody($inbox, $x, 2.2);//This worked for the other two:
                        //$fileData = imap_fetchbody($inbox, $x, $partNum+1.2);

                        if($fileData == Null || $fileData == " "){
                        $fileData = imap_fetchbody($inbox, $x, $partNum+1.2)  ;
                        }

                        if ($part->encoding === 1) {
                        $fileData = imap_utf8($fileData);
                        } elseif ($part->encoding === 2) {
                                $fileData = imap_binary($fileData);
                        } elseif ($part->encoding === 3) {
                        $fileData = imap_base64($fileData);
                        } elseif ($part->encoding === 4) {
                        $fileData = quoted_printable_decode($fileData);
                        }

        // Generate a unique filename for saving the file

                // Save the file to a directory in Linux
                $xmagePath = $folderName."/" . $filename;

                        # file_put_contents($xmagePath, $fileData); //pdf
                        #       chmod( $xmagePath, 666);
                                $y = file_put_contents($xmagePath, $fileData); //pdf

                                if ($y !== false) {
                                chmod( $xmagePath, 666);
                                file_put_contents(__line__." Setting $xmagePath to 666", $fileData);
                                $rs1 = imap_mail_copy($inbox,$x,'Trash');
                                if($rs1){
                                $rs2 = imap_delete($inbox, $x);
                                }
                                }


                }

        		// Process the first part object (JPEG image)

              if ($part->type == 5 && $part->subtype == 'JPEG') {
                $currentPartNum = $partNum === '' ? '1' : $partNum; // Default to '1' if partNum is empty
                echo "Found JPEG part. Part Number: {$currentPartNum}\n";
                $fileData = imap_fetchbody($inbox, $x, $currentPartNum);
    
                if (!empty($fileData) && $fileData !== false && trim($fileData) !== '' && strlen($fileData) > 0) {
                    // Decode the file data based on the encoding
                    switch ($part->encoding) {
                        case 0: // 7BIT
                        case 1: // 8BIT
                            // No decoding needed
                            break;
                        case 2: // BINARY
                            $fileData = imap_binary($fileData);
                            break;
                        case 3: // BASE64
                            $fileData = imap_base64($fileData);
                            break;
                        case 4: // QUOTED-PRINTABLE
                            $fileData = quoted_printable_decode($fileData);
                            break;
                        default:
                            echo "Unknown encoding: {$part->encoding}\n";
                            break;
                    }
    
                    // Ensure the file data is decoded correctly
                    if ($fileData !== false && !empty($fileData)) {
                        // Save the image to a directory in Linux
                        $imagePath = $folderName . "/" . $filename;
                        file_put_contents($imagePath, $fileData); // JPEG
                        chmod($imagePath, 0666);
                        file_put_contents(__line__." Setting $imagePath to 666", $fileData);
                        //echo "Saved JPEG image to {$imagePath}\n";
                    } else {
                       // echo "Failed to decode the file data for part number: {$currentPartNum}.\n";
                    }
                } else {
                   // echo "No valid file data found for part number: {$currentPartNum}.\n";
                }
              }
        ###################################################

                }

        
        }elseif (isset($part->disposition) && $part->disposition === 'attachment'){ //for not inline attachments
                $filename = $part->dparameters[0]->value;
                $filename = urldecode($filename);
#file_put_contents("/scripts/check.txt",__line__." ".date('Y-m-d H:i')."forwarded:filename;".$filename."\n",FILE_APPEND);
                //Check if the filename is more than a regular file

		            if($part->disposition != 'inline'){
                        if(strlen($filename)<=60){
							#$time = date('Y-m-d H:i');
                            $msgStructure = imap_fetchstructure($inbox, $x);
                            $totalAttachments = countAttachments($msgStructure);
                              $logmsg = "[$time] : Processing embedded attachments";
							  file_put_contents("/scripts/check.txt", $logmsg."\n", FILE_APPEND);
							  
                            if ($totalAttachments > 1) {
                               # echo "Multiple attachments found: $totalAttachments<br>";
                                $flat = flattenParts($structure->parts);
                                $pdfs = findPdfParts($flat);
                               # print_r($pdfs);
                                savePdfAttachmentsFromParts($inbox, $x, $pdfs, $folderName);
                            } else {
                                print "Single attachment found: $filename<br>";
                                download_attachment($inbox, $x, $part, $folderName, $subject);
                            }
                            #file_put_contents("/scripts/processedMails.txt", $message_id."\n", FILE_APPEND);

                        }
                    }
        }

// Check if the part has sub-parts
if (isset($part->parts) && is_array($part->parts) && count($part->parts) > 0) {        
// Loop through each sub-part
        foreach ($part->parts as $subPartNum => $subPart) {
            // Construct the new part number for the sub-part
            $newPartNum = ($partNum ? "$partNum." : "") . ($subPartNum + 1);
            //echo "Processing sub-part number: {$newPartNum}\n"; // Debugging statement
            // Process the sub-part recursively
            inlineMails($inbox, $x, $subPart, $newPartNum, $subject);
        }
    }
}


//get the extention from the filename
function getExtentionName($filename){
    $lastDotPosition = strrpos($filename, '.');
    if ($lastDotPosition !== false) {
    $extension = substr($filename, $lastDotPosition + 1);
    return $extension; // Output: pdf
    } else {
    // No file extension found use default .htm
    return ".htm";
    }
}

#*******************
 function getEmailAddressFromMany($string){ //20230619 

    if (strpos($string, ',') !== false) {
        // Split the string by comma and space
        $array = explode(", ", $string);

        $result = null;

        foreach ($array as $value) {
            // Extract email addresses inside <>
            preg_match_all('/<([^>]+)>/', $value, $matches);
            $emails = $matches[1]; // array of emails

            $found = false;
            foreach ($emails as $email) {
                // Get the part before '@'
                $localPart = strstr($email, '@', true);

                // Check if the local part starts with 'bilag_'
                if (strpos($localPart, 'bilag_') === 0) {
                    $result = $value;
                    $found = true;
                    break;
                }

                // Check if last 9 chars of the email contain '.saldi.dk'#20250815
                if (strlen($email) >= 9 && strpos(substr($email, -9), '.saldi.dk') !== false) {
                    $result = $value;
                    $found = true;
                    break;
                }
            }

            if ($found) {
                break;
            } else {
                // No match for this value, continue checking next
                $result = $string; 
            }
        }

        return $result;

    } else {
        // If no comma, just return the string as is
        return $string;
    }
}

#*****************  

// for forwareded emebeded attachments
function download_attachment($inbox, $x, $structure, $dir, $subject) {
        // Initialize file data
		global $time;
        $fileData = '';
       echo "This is the download_attachment function for $x <br>";
        // Check if the email is multipart
        if ($structure->type === 1) {
            // Iterate over the parts of the email
            foreach ($structure->parts as $partNum => $part) {
                // Check if the part has a filename (potential attachment)
                if (isset($part->dparameters[0]->value)) {
                    // Fetch the attachment
                    $attachmentData = imap_fetchbody($inbox, $x, $partNum + 1);
                    
                    // Decode the attachment based on encoding
                    switch ($part->encoding) {
                        case 1:
                            $attachmentData = imap_utf8($attachmentData);
                            break;
                        case 2:
                            $attachmentData = imap_binary($attachmentData);
                            break;
                        case 3:
                            $attachmentData = imap_base64($attachmentData);
                            break;
                        case 4:
                            $attachmentData = quoted_printable_decode($attachmentData);
                            break;
                    }
                    
                    // Check if attachment data is not empty and is a PDF
                    if (!empty($attachmentData) && stripos($part->dparameters[0]->value, '.pdf') !== false) {
                        $fileData = $attachmentData;
                        break; // Exit loop once PDF attachment is found
                    }
                }
            }
          } elseif ($structure->type === 3 && isset($structure->subtype) && strtolower($structure->subtype) === 'pdf') {
            // If the email is of type APPLICATION and subtype is PDF
            // Fetch the attachment
            $fileData = imap_fetchbody($inbox, $x, 2.0); 
           
            // Decode the attachment based on encoding
            switch ($structure->encoding) {
                case 1:
                    $fileData = imap_utf8($fileData);
                    break;
                case 2:
                    $fileData = imap_binary($fileData);
                    break;
                case 3:
                    $fileData = imap_base64($fileData);
                    break;
                case 4:
                    $fileData = quoted_printable_decode($fileData);
                    break;
            }
        }else{
          echo "nothing here";
        }
        if (empty($fileData)) {
            error_log("No valid PDF attachment found in email with index $x.");
            return null; // No valid PDF attachment found
        }
      # var_dump($fileData); exit; 
        // Generate unique filename
        $uniqueFilename = $subject . ".pdf"; // Assuming PDF extension
        
        // Save the file
        $xmagePath  = $dir . "/" . $uniqueFilename;
       # file_put_contents($filePath, $fileData);


   # file_put_contents($xmagePath, $fileData); //pdf
   #       chmod( $xmagePath, 666);
           $y = file_put_contents($xmagePath, $fileData); //pdf

           if ($y !== false) {
			$time = date('Y-m-d H:i:s');
					error_log("[$time] Saved PDF attachment to $xmagePath.");
					// Log the successful save
					// Set permissions for the saved file
				chmod( $xmagePath, 666);
        file_put_contents(__line__." Setting $xmagePath to 666", $fileData);
				$rs1 = imap_mail_copy($inbox,$x,'Trash');
				if($rs1){
					$rs2 = imap_delete($inbox, $x);
				}
		   } else {
			   error_log("[$time] Failed to save the PDF attachment to $xmagePath.");
           }
        
        return $uniqueFilename; // Return the saved filename
    }
  
    


    
#20250709 flattenParts function
function flattenParts($parts, $flattened = [], $prefix = '', $fullPrefix = true) {
    foreach ($parts as $idx => $part) {
        $num = $prefix . ($idx + 1);
        $flattened[$num] = $part;

        if (isset($part->parts)) {
            if ($part->type == 2) { 
                // MESSAGE/RFC822 – don't extend prefix
                $flattened = flattenParts($part->parts, $flattened, $num . '.', false);
            } else {
                $newPrefix = $fullPrefix ? $num . '.' : $prefix;
                $flattened = flattenParts($part->parts, $flattened, $newPrefix, $fullPrefix);
            }
            unset($flattened[$num]->parts);
        }
    }
    return $flattened;
}

############ findPdfParts function
function findPdfParts($flat) {
    $pdfs = [];
    foreach ($flat as $num => $part) {
        $hasName = false;
        $name = '';

        if (!empty($part->dparameters)) {
            foreach ($part->dparameters as $p) {
                if (strtolower($p->attribute) === 'filename') {
                    $hasName = true;
                    $name = $p->value;
                }
            }
        }

        if (!$hasName && !empty($part->parameters)) {
            foreach ($part->parameters as $p) {
                if (strtolower($p->attribute) === 'name') {
                    $hasName = true;
                    $name = $p->value;
                }
            }
        }

        if ($hasName && preg_match('/\.pdf$/i', $name)) {
            $pdfs[] = [
                'partNum' => $num,
                'filename' => $name,
                'encoding' => $part->encoding,
            ];
        }
    }
    return $pdfs;
}



#20250710 savePdfAttachmentsFromParts function
function savePdfAttachmentsFromParts($inbox, $msgNumber, $pdfParts, $outputDir) {
    global $time;

    $msg1 = "[$time] Processing PDF attachments for message ID: $msgNumber\n";
    file_put_contents("/scripts/check.txt", $msg1, FILE_APPEND); 

    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true); 
    }

    $allSaved = true; // ✅ Track whether all PDFs saved

    foreach ($pdfParts as $pdf) {
        $partNum = $pdf['partNum'];
        $filename = $pdf['filename'];
        $encoding = $pdf['encoding'];

        echo "Processing part $partNum: $filename\n";

        $body = imap_fetchbody($inbox, $msgNumber, $partNum);

        // Try deeper subparts if empty
        $trimmedBody = trim($body);
        if (empty($trimmedBody)) {
            echo "[Info] Body empty for $partNum. Trying deeper subparts...\n";

            for ($i = 1; $i <= 5; $i++) {
                $deeperPart = $partNum . '.' . $i;
                $body = imap_fetchbody($inbox, $msgNumber, $deeperPart);
                $trimmedBody = trim($body);
                if (!empty($trimmedBody)) {
                    echo "[Found] Non-empty body at $deeperPart\n";
                    $partNum = $deeperPart;
                    break;
                }
            }
        }

        $trimmedBody = trim($body);
        if (empty($trimmedBody)) {
            echo "[Warning] Still empty after deeper check: $filename\n";
            $allSaved = false; // ❌ at least one failed
            continue;
        }

        // Decode
        switch ($encoding) {
            case 3:
                $body = base64_decode($body);
                break;
            case 4:
                $body = quoted_printable_decode($body);
                break;
            case 2:
                $body = imap_binary($body);
                break;
            default:
                break;
        }

        if (empty($body)) {
            echo "[Error] Decoded body is empty for $filename\n";
            $allSaved = false;
            continue;
        }

        // Save PDF
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $filename);
        $savePath = $outputDir . '/' . $safeFilename;

        $msg3 = "[$time] Saving PDF to: $savePath\n";
        file_put_contents("/scripts/check.txt", $msg3, FILE_APPEND);

        $y = file_put_contents($savePath, $body);
        if ($y !== false) {
            $msg2 = "[$time] File saved successfully: $savePath\n";
            file_put_contents("/scripts/emailpaths.txt", $msg2, FILE_APPEND);
            chmod($savePath, 0666);
            file_put_contents(__line__." Setting $savePath to 666", $fileData);
        } else {
            $allSaved = false;
            $errormsg = "[$time] Failed to save file: $savePath";
            file_put_contents("/scripts/check.txt", $errormsg . "\n", FILE_APPEND);
        }
    }

    // ✅ After all attachments processed — delete if all succeeded
    if ($allSaved) {
        $msg4 = "[$time] All attachments saved — moving message $msgNumber to Trash\n";
        file_put_contents("/scripts/check.txt", $msg4, FILE_APPEND);

        if (imap_mail_copy($inbox, $msgNumber, 'Trash')) {
            imap_delete($inbox, $msgNumber);
        }
    } else {
        $msg4 = "[$time] Not all attachments saved — message $msgNumber NOT deleted\n";
        file_put_contents("/scripts/check.txt", $msg4, FILE_APPEND);
    }
}


#++++++++++countPdfAttachments function
function countAttachments($part, $isEml = false) {
    $count = 0;

    // Always check current level if parts exist
    if (isset($part->parts)) {
        foreach ($part->parts as $subpart) {
            // Recurse into all parts, including nested .eml structures
            $count += countAttachments($subpart, $part->type == 2); // Pass isEml=true if parent is message/rfc822

            // Check if this subpart is an attachment with a filename
            if (
                isset($subpart->disposition) &&
                strtolower($subpart->disposition) === 'attachment' &&
                isset($subpart->dparameters[0]->value)
            ) {
                $filename = $subpart->dparameters[0]->value;

                // Check if it's a PDF
                if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf') {
                    $count++;
                }
            }
        }
    }

    return $count;
}

#20250711
function emailExistsInCCBCC($rawHeader) {
    # $rawHeader = imap_fetchheader($inbox, $x);
   // Match "for <bilag_...>" in Received headers
    if (preg_match('/for\s+<(?<email>bilag_[\w._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>/i', $rawHeader, $matches)) {
        return $matches['email'];
    }

    // Match "Delivered-To: bilag_..."
    if (preg_match('/Delivered-To:\s*(bilag_[\w._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $rawHeader, $matches)) {
        return $matches[1];
    }

    // No bilag_ address found
    return false;
}

function sanitize_filename($filename) {
    // Replace known extended Latin/Danish characters
    $translit = [
        'æ' => 'ae', 'Æ' => 'Ae',
        'ø' => 'oe', 'Ø' => 'Oe',
        'å' => 'aa', 'Å' => 'Aa',
        'ä' => 'ae', 'Ä' => 'Ae',
        'ö' => 'oe', 'Ö' => 'Oe',
        'ü' => 'ue', 'Ü' => 'Ue',
        'ß' => 'ss',
        'ñ' => 'n',  'Ñ' => 'N',
        'á' => 'a',  'Á' => 'A',
        'é' => 'e',  'É' => 'E',
        'í' => 'i',  'Í' => 'I',
        'ó' => 'o',  'Ó' => 'O',
        'ú' => 'u',  'Ú' => 'U'
    ];
    $filename = strtr($filename, $translit);

    // Fallback transliteration for any remaining special chars
   # $filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);

    // Remove all but safe characters
   # $filename = preg_replace('/[^\w\-\. ]+/', '_', $filename);

    // Trim unwanted characters from ends
    $filename = trim($filename, " \t\n\r\0\x0B.");

    // Separate the name and extension
    $dot_position = strrpos($filename, '.');
    if ($dot_position !== false) {
        $name = substr($filename, 0, $dot_position);
        $ext = substr($filename, $dot_position); // Includes the dot
        echo "extensionzzzz: $ext";
    } else {
        $name = $filename;
        $ext = 'png';
    }

    // Truncate the name part if longer than 54 characters
    if (strlen($name) > 54) {
        $name = substr($name, 0, 54);
    }

    if (substr($name, -1) !== '.') {
        return $name . '.' . $ext;
    } else {
        return $name . $ext;
    }


}


 ?>


<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------------- systemdata/diverse.php -------patch 4.0.8 ----2023-08-31--
//                           LICENSE
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20230306 MSC - Edited screenshot message
// 20230413 MSC - Added email form php and edited form to reflect the new code
// 20230414 MSC - Deleted 21 og tidligere from screenshot guide
// 20230416 MSC - Changed cols and row to height and weight in textarea

@session_start();
$s_id=session_id();
ob_start();

$title="Feedback";
$modulnr=1;
$diffkto=NULL;

if(isset($_POST['button']))
{
	$from_email		 = 'feedback@saldi.dk'; //from mail, sender email address
	$recipient_email = 'feedback@saldi.dk'; //recipient email address
	
	//Load POST data from HTML form
	$sender_name = $_POST["sender_name"]; //sender name
	$reply_to_email = $_POST["sender_email"]; //sender email, it will be used in "reply-to" header
	$subject	 = "Feedback: Nyt design"; //subject for the email
	$link	 = $_POST["link"]; //link to website where error is
	$regnskabForm	 = $_POST["regnskabForm"]; //regnskab name
	$browser	 = $_POST["browser"]; //which browser the user is using
	$message	 = $_POST["message"]; //body of the email

	if (isset($_FILES['attachment'])) {

	/*Always remember to validate the form fields like this
	if(strlen($sender_name)<1)
	{
		die('Name is too short or empty!');
	}
	*/
	//Get uploaded file data using $_FILES array
	$tmp_name = $_FILES['attachment']['tmp_name']; // get the temporary file name of the file on the server
	$name	 = $_FILES['attachment']['name']; // get the name of the file
	$size	 = $_FILES['attachment']['size']; // get size of the file for size validation
	$type	 = $_FILES['attachment']['type']; // get type of the file
	$error	 = $_FILES['attachment']['error']; // get the error (if any)

	//validate form field for attaching the file
	if($error > 0 && $error != 4)
	{
        include('feedback_fail_besked.php');
		die();
	}

	//read from the uploaded file & base64_encode content
	$handle = fopen($tmp_name, "r"); // set the file handle only for reading the file
	$content = fread($handle, $size); // reading the file
	fclose($handle);				 // close upon completion

	}

	$encoded_content = chunk_split(base64_encode($content));
	$boundary = md5("random"); // define boundary with a md5 hashed value

	$message2 = "
	Navn: $sender_name
	Email: $reply_to_email
	Regnskab: $regnskabForm
	Browser: $browser
	Link til siden: $link
	
	$message
	";

	//header
	$headers = "MIME-Version: 1.0\r\n"; // Defining the MIME version
	$headers .= "From:".$from_email."\r\n"; // Sender Email
	$headers .= "Reply-To: ".$reply_to_email."\r\n"; // Email address to reach back
	$headers .= "Content-Type: multipart/mixed;"; // Defining Content-Type
	$headers .= "boundary = $boundary\r\n"; //Defining the Boundary
		
	//plain text
	$body = "--$boundary\r\n";
	$body .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
	$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
	$body .= chunk_split(base64_encode($message2));

	if (isset($_FILES['attachment'])) {
		//attachment
		$body .= "--$boundary\r\n";
		$body .="Content-Type: $type; name=".$name."\r\n";
		$body .="Content-Disposition: attachment; filename=".$name."\r\n";
		$body .="Content-Transfer-Encoding: base64\r\n";
		$body .="X-Attachment-Id: ".rand(1000, 99999)."\r\n\r\n";
		$body .= $encoded_content; // Attaching the encoded file with email
	}
		
	
	$sentMailResult = mail($recipient_email, $subject, $body, $headers);

    if($sentMailResult ){
        $mailSent = "Emailen er blevet sendt og vi vil se på den så hurtigest så muligt.";
        // unlink($name); // delete the file after attachment sent.
    }
    else{
        include('feedback_fail_besked.php');
    }
    
}

function getBrowser() { 
	$u_agent = $_SERVER['HTTP_USER_AGENT'];
	$bname = 'Unknown';
	$platform = 'Unknown';
	$version= "";
  
	//First get the platform?
	if (preg_match('/linux/i', $u_agent)) {
	  $platform = 'linux';
	}elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
	  $platform = 'mac';
	}elseif (preg_match('/windows|win32/i', $u_agent)) {
	  $platform = 'windows';
	}
  
	// Next get the name of the useragent yes seperately and for good reason
	if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)){
	  $bname = 'Internet Explorer';
	  $ub = "MSIE";
	}elseif(preg_match('/Firefox/i',$u_agent)){
	  $bname = 'Mozilla Firefox';
	  $ub = "Firefox";
	}elseif(preg_match('/OPR/i',$u_agent)){
	  $bname = 'Opera';
	  $ub = "Opera";
	}elseif(preg_match('/Chrome/i',$u_agent) && !preg_match('/Edge/i',$u_agent)){
	  $bname = 'Google Chrome';
	  $ub = "Chrome";
	}elseif(preg_match('/Safari/i',$u_agent) && !preg_match('/Edge/i',$u_agent)){
	  $bname = 'Apple Safari';
	  $ub = "Safari";
	}elseif(preg_match('/Netscape/i',$u_agent)){
	  $bname = 'Netscape';
	  $ub = "Netscape";
	}elseif(preg_match('/Edge/i',$u_agent)){
	  $bname = 'Edge';
	  $ub = "Edge";
	}elseif(preg_match('/Trident/i',$u_agent)){
	  $bname = 'Internet Explorer';
	  $ub = "MSIE";
	}
  
	// finally get the correct version number
	$known = array('Version', $ub, 'other');
	$pattern = '#(?<browser>' . join('|', $known) .
  ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
	if (!preg_match_all($pattern, $u_agent, $matches)) {
	  // we have no matching number just continue
	}
	// see how many we have
	$i = count($matches['browser']);
	if ($i != 1) {
	  //we will have two since we are not using 'other' argument yet
	  //see if version is before or after the name
	  if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
		  $version= $matches['version'][0];
	  }else {
		  $version= $matches['version'][1];
	  }
	}else {
	  $version= $matches['version'][0];
	}
  
	// check if we have a number
	if ($version==null || $version=="") {$version="?";}
  
	return array(
	  'userAgent' => $u_agent,
	  'name'      => $bname,
	  'version'   => $version,
	  'platform'  => $platform,
	  'pattern'    => $pattern
	);
  } 
  
  // now try it
  $ua=getBrowser();
  $yourbrowser= "" . $ua['name'] . " V" . $ua['version'];

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");


include_once '../includes/top_header.php';
include_once '../includes/top_menu.php';
print "<div id=\"header\">"; 
print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
print "<div class=\"headerTxt\">$title</div>";     
print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
print "</div>";
print "<div class='content-noside'>";

print "<center><div style='display: flex; flex-direction: column; justify-content: center;'>";

print "<center><div>

Hvis du finder en fejl på det nye design, hvor designet ikke virker eller ser forkert ud, vil vi sætte stor pris på at få feedback.<br>
Tag venligst et screenshot/skærmbillede af selve fejlen og vedhæfte den under screenshot/billede.<br>
Skriv meget gerne en beskrivelse og kopier linket til selve siden hvor du har oplevet fejlen.<br><br>

<b>$mailSent</b>

<br><br></div></center>";


print "<form enctype='multipart/form-data' method='POST' action=''>";

print "<center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'><b>Navn:</b></div>";
print "<div style='width:70%; text-align:left;'><input name='sender_name' type='text' style='width:444px;' required></div></div></center>";

print "<center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'><b>Email:</b></div>";
print "<div style='width:70%; text-align:left;'><input name='sender_email' type='email' style='width:444px;'required></div></div></center>";

print "<center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'><b>Regnskab:</b></div>";
print "<div style='width:70%; text-align:left;'><input name='regnskabForm' type='text' style='width:444px;' value='$regnskab' required></div></div></center>";

print "<center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'><b>Browser:</b></div>";
print "<div style='width:70%; text-align:left;'><input name='browser' type='text' style='width:444px;' value='$yourbrowser' required></div></div></center>";

print "<center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'><b>Link til siden:</b></div>";
print "<div style='width:70%; text-align:left;'><input name='link' type='text' style='width:444px;' required></div></div></center>";

print "<center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'><b>Vedhæft screenshot/billede:</b></div>";
print "<div style='width:70%; text-align:left;'>
<input type='hidden' name='MAX_FILE_SIZE' value='4194304'/>
<input name='attachment' type='file' style='width:444px;' cols='50'></div></div></center>";

print "<center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'><b>Beskrivelse:</b></div>";
print "<div style='width:70%; text-align:left;'><textarea name='message' style='width:444px; height: 300px;' required></textarea></div></div></center>";

print "<center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'></div>";
print "<div style='width:70%; text-align:left;'><input type='submit' name='button' value='Send' style='width:444px'></div></div></center>";

print "</form>";


$user_agent = $_SERVER['HTTP_USER_AGENT'];

function getOS() { 

    global $user_agent;

    $os_platform  = "Unknown OS Platform";

    $os_array     = array(
                          '/windows nt 10/i'      =>  'Windows 10',
                          '/windows nt 6.3/i'     =>  'Windows 8.1',
                          '/windows nt 6.2/i'     =>  'Windows 8',
                          '/windows nt 6.1/i'     =>  'Windows 7',
                          '/windows nt 6.0/i'     =>  'Windows Vista',
                          '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
                          '/windows nt 5.1/i'     =>  'Windows XP',
                          '/windows xp/i'         =>  'Windows XP',
                          '/windows nt 5.0/i'     =>  'Windows 2000',
                          '/windows me/i'         =>  'Windows ME',
                          '/win98/i'              =>  'Windows 98',
                          '/win95/i'              =>  'Windows 95',
                          '/win16/i'              =>  'Windows 3.11',
                          '/macintosh|mac os x/i' =>  'Mac OS X',
                          '/mac_powerpc/i'        =>  'Mac OS 9',
                          '/linux/i'              =>  'Linux',
                          '/ubuntu/i'             =>  'Ubuntu',
                          '/iphone/i'             =>  'iPhone',
                          '/ipod/i'               =>  'iPod',
                          '/ipad/i'               =>  'iPad',
                          '/android/i'            =>  'Android',
                          '/blackberry/i'         =>  'BlackBerry',
                          '/webos/i'              =>  'Mobile'
                    );

    foreach ($os_array as $regex => $value)
        if (preg_match($regex, $user_agent))
            $os_platform = $value;

    return $os_platform;
}

$user_os        = getOS();

print_r($device_details);

print "<br><center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'></div>";
print "<div style='width:70%; text-align:left;'><input type='button' onclick=\"location.href='feedback_guide.php'\" value='Guide til at udfylde feedback form' style='width:444px'></div></div></center>";

print "<br><center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; margin-top:10px; width:30%; text-align:right;'></div>";
print "<div style='width:70%; text-align:left;'><input type='button' onclick=\"location.href='#nav'\" value='Hvordan tager man et screenshot?' style='width:444px'></div></div></center>";
print "<div class='expandableSearch' id='nav' style='padding-top:5px;'>";
print "<br><center><div style='display: flex; flex-direction: row; justify-content: center; width:900px;'>";
print "<div style='margin-right:10px; width:30%; text-align:right;'></div>";
print "<div style='width:70%; text-align:left;'>
<div style='width:444px;'>";

if ($user_os=='Linux') {
print "<b>Linux:</b> <br>
Tryk på PrtSc knapeen på dit tastatur, hvor efter du kan finde filen i din billed mappe<br><br>";
} 

if ($user_os=='Windows 10' or $user_os=='Windows 8.1' or $user_os=='Windows 7' or $user_os=='Windows Vista' or $user_os=='Windows Server 2003/XP x64' or $user_os=='Windows XP') {
print "<b>Windows:</b> <br>
Hold Windows knapeen og tryk derefter på PrtSc knappen på dit tastatur, hvorefter du kan finde screenshottet i din billede mappe under en mappe kaldt skærmbilleder";
}

if ($user_os=='Mac OS X' or $user_os=='Mac OS 9') {
print "<b>Mac:</b> <br>
Tryk og hold shift og command og 3 nede, hvorefter du kan finde screenshottet i din billedmappe";

}

print "</div>

</div></div></center>";

print "</div></center>";


include_once '../includes/topmenu/footer.php';

?>
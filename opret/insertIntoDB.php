<?php

    include "../includes/connect.php";
    include "../includes/db_query.php";
    require "../vendor/autoload.php";

    use phpmailer\PHPMailer\PHPMailer;
    use phpmailer\PHPMailer\Exception;

    $data = json_decode(file_get_contents('php://input'), true);
    $dd=date("Y-m-d");
    #if ($ip=='95.166.170.121') echo "INSERT INTO kundedata (firmanavn,  addr1, addr2, postnr, bynavn, kontakt, tlf , email , cvrnr, regnskab, brugernavn, kodeord, kontrol_id, aktiv, oprettet,slettet,referer,affiliate)values('$firma', '$adresse', '$adresse2', '$postnr', '$bynavn', '$navn', '$telefon' , '$email' , '$cvr', '$regnskab', '$brugernavn', '$kodeord', '$kontrol_id', '0','$dd','','$referer','$affiliate')";
    $qtxt = "INSERT INTO kundedata ";
    $qtxt.= "(firmanavn,addr1,addr2,postnr,bynavn,kontakt,tlf,email,cvrnr,regnskab,brugernavn,kodeord,";
    $qtxt.= "kontrol_id,aktiv,oprettet,slettet) ";
    $qtxt.= " values ";
    $qtxt.= "('".db_escape_string($data["firma"])."','".db_escape_string($data["adresse"])."','".db_escape_string($data["adresse2"])."',";
    $qtxt.= "'".db_escape_string($data["postnr"])."','".db_escape_string($data["bynavn"])."','".db_escape_string($data["navn"])."',";
    $qtxt.= "'".db_escape_string($data["telefon"])."','".db_escape_string($data["email"])."','".db_escape_string($data["cvr"])."',";
    $qtxt.= "'".db_escape_string($data["regnskab"])."','".db_escape_string($data["brugernavn"])."','".db_escape_string($data["kodeord"])."',";
    $qtxt.= "'".db_escape_string($data["kontrol_id"])."',0,'$dd','')";
    db_modify($qtxt,__FILE__ . " linje " . __LINE__);

    $kontrol_id="";
    srand((double)microtime()*1000000);
    $chars = 'ABCDEFGHIJKLNMOPQRSTUVXYabcdefghijklmnopqrstuvwxyz0123456789';
    for ($i=0; $i<3; $i++) $kontrol_id.= $chars[rand(0, strlen($chars)-1)];
    $kontrol_id.=date("U");
    for ($i=0; $i<3; $i++) $kontrol_id.= $chars[rand(0, strlen($chars)-1)];
    $kontrol_id=$kontrol_id.":1:50;
    $linkadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?kontrol_id=".$kontrol_id;

    $message .= "\nKlik på nedenstående link for at bekræfte bestillingen og verificere\noprettelsen af dit regnskab:\n$linkadresse\n\n";
#		$message .= "Linket er gyldigt i 7 dage.\n\n";
    $message .= "Tilmeldingsoplysninger:\n";
    $message .= "Navn:       $data[navn]\n";
    $message .= "Firma:      $data[firma]\n";
    $message .= "CVR-nr.:    $data{cvr]\n";
    $message .= "Adresse:    $data[adresse]\n";
    if ($data["adresse2"]) $message .= "            $data[adresse2]";
    $message .= "\nPostnr/By:  $data[postnr] $data[bynavn]\n";
    $message .= "Telefon:    $data[telefon]\n";
    $message .= "E-mail:     $data[email]\n";
    $message .= "Regnskab:   $data[regnskabsNavn]\n\n";
    $message .= "Din email og telefonnummer anvendes som hhv. brugernavn og adgangskode ved første login.\n"; 
    $message .= "Du opfordres til at ændre brugernavn og især adgangskode under Indstillinger -> Brugere.\n";
    $message .= "Her kan du også tilføje flere brugere.\n";
    /* if ($eventuelt) $message .= "Eventuelt: $eventuelt\n"; */
    $message .= "\nBemærk at navnet på regnskabet skal skrives nøjagtigt som angivet. \nDer skelnes mellem store og små bogstaver.\n";
    $message .= "Ved første login kommer du direkte ind i oprettelse af 1. regnskabsår.\nDette SKAL oprettes inden regnskabet kan bruges.\n";
    $tmp = urlencode($data["regnskabsNavn"]);
    $message .= "Herefter kan du finde dit regnskab på:\nhttp://www.saldi.dk/finans?regnskab=$tmp \n\n";
    $message .= "På http://forum.saldi.dk kan der findes svar på de fleste spørgsmål.\n";
#		$message .= "Bemærk at forummet er beskyttet med adgangskode - Skriv Saldi (med stort S) som både brugernavn og adgangskode.\n";
    $message .= "Brugervejledningen kan findes her:  http://www.saldi.dk/dok/index.htm\n";
    #$message .= "og du kan finde en videomanual her: http://flash.saldi.dk \n";
    $message .= "Som SALDI-kunde har du adgang til hotline og ubegrænset support pr. email. Benyt mailadressen support@saldi.dk.\n";
    $message .= "Har du i øvrigt spørgsmål, eller hvis der er andet, vi kan hjælpe med, så ring blot på telefon 4690 2208.\n\n";
    $message .= "Velkommen til og god fornøjelse\n\n";
    $message .= "Med venlig hilsen\n";
    $message .= "Saldi.dk ApS\n";

    $mail = new PHPMailer();
    $mail->IsSMTP();                                   // send via SMTP
    $mail->Host  = "localhost"; // SMTP servers
    $mail->SMTPAuth = false;     // turn on SMTP authentication
    $afsendermail='saldi@saldi.dk';
    $afsendernavn='SALDI ';

    $mail->SetFrom($afsendermail,$afsendernavn);
#		$mail->From  = $afsendermail;
#		$mail->FromName = $afsendernavn;
    $mail->AddAddress($to);
    $mail->AddBCC('phr@saldi.dk');
    if (! substr($valgt_kursus,0,3)=="Nej") {
        $mail->AddBCC('kursus@saldi.dk');
    }
    $mail->Subject  =  "Bestilling af SALDI-Abonnement";
    $mail->Body     =  $message;

    if(!$mail->Send()){
        echo "Fejl i afsendelse til $to<p>";
        echo "Mailer Error: " . $mail->ErrorInfo;
        exit;
    }

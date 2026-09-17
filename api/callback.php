<?PHP
      // NOTE: You cannot display the data in a browser, since the resultpage is called in the background
      // Collect return values and store them in a file, database, send them by email etc.
      // EXAMPLE: Send an e-mail with data
      // Set keys we wish to read from $_POST array

      $fields = array('msgtype','ordernumber','amount','currency','time','state','qpstat','qpstatmsg','chstat','chstatmsg','merchant','merchantemail','transaction','cardtype','cardnumber','cardexpire','splitpayment','fraudprobability','fraudremarks','fraudreport','fee','md5check');
      $message = '';
      // Loop through $fields array, check if key exists in $_POST array, if so collect the value
      while (list(,$k) = each($fields)) {
        if (isset($_POST[$k])) {
          if ($k == 'merchantemail') $merchantEmail = $_POST[$k];
          $message .= "$k: " .$_POST[$k] . "\r\n";
        }
      }
      // Send an email with the data posted to your resultpage
      if ($merchantEmail && $message) mail($merchantEmail, 'callbackurl', $message);
?>

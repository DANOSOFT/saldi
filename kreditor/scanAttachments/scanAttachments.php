<?php
//session_start();

//include("../includes/connect.php");
//include("../includes/online.php");

#include("header.php");
$D = $_SESSION['filepath'] ;
$P = $_SESSION['action'];








    if(!unlink($D)) {
 #      echo "<script> alert( 'error occured while deleting')</script>" ;

      
       $mytxt = "Upload A Voucher";
 
       $Addr ="scanAttachments/paperflowupload.php";
       
       
       
      
       echo "<br>";
       echo "<br>";
       echo "<br>";
       
       echo " <a href=\"$Addr\"target=\"new window\">$mytxt </a> </br>";
    }else{

      #header("Location:spaperflowupload.php?delete succesful");
      header("Location:../kreditor/ordreliste.php?delete succesful");

    }

      

 























 
    
    
    
#    include("footer.php");
    


?>

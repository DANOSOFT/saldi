
<?php

// ----------------------------------------------------------------------
 //This file authorizes and authenticates users via their account_name and UserName and PassWord.
 //This also Assumes the users have account on this server ssl10.
 
session_start();
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include("../includes/db_query.php");
include("../includes/connect.php");
include("../includes/std_func.php");


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

      $X= trim($_SERVER['PHP_AUTH_USER']);
      //Login details of the requesting user
      $y = explode("$", $X);
      $username= $y[0];
      $account = $y[6];
       // $username= trim($_SERVER['PHP_AUTH_USER']);
      $password= trim($_SERVER['PHP_AUTH_PW']); 

        $row=db_fetch_array(db_select("select * from brugere where brugernavn = '$username'",__FILE__ . " linje " . __LINE__));
       

        $pw2=saldikrypt($row['id'],$password);
        $r=db_fetch_array(db_select("select * from brugere where brugernavn = '$username' AND kode = '$pw2'",__FILE__ . " linje " . __LINE__));

      /////////////////////////////
      
       $qtxt = "select * from regnskab where regnskab = '".db_escape_string($account)."'";
       $ro = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

   if(!$r || !$ro){
       $arr=array('error'=>'Wrong Credentials');
       echo json_encode($arr);
       exit;
    }else{
       
                $account_id        = trim($ro['db']);
               
                $to_email = "bilag_".$account_id."@".$_SERVER['SERVER_NAME'];


                    $arr= array('email'=> $to_email, 'code'=>http_response_code(200) );
                    echo json_encode($arr);
    }
}else{
    $arr=array('error'=>'Wrong Call');
    echo json_encode($arr);
    exit;

}

?>

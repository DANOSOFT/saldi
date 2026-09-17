<?php 
	(isset($_GET['id']))?$id=$_GET['id']:$id=NULL;
	(isset($_GET['db']))?$db=$_GET['db']:$db=NULL;
	(isset($_GET['amount']))?$amount=$_GET['amount']:$amount=NULL;
	(isset($_GET['kortnavn']))?$kortnavn=$_GET['kortnavn']:$kortnavn=NULL;
	(isset($_GET['pos_bet_id']))?$pos_bet_id=$_GET['pos_bet_id']:$pos_bet_id=NULL;
	
	if ($id && $db && $amount && $kortnavn && $pos_bet_id) {
		$cf=fopen('../temp/'. $db .'/'. $id ."-". $pos_bet_id .'.txt','w');
		fwrite($cf,$kortnavn.chr(9).$amount."\n");
		fclose($cf);
		chmod('../temp/'. $db .'/'. $id ."-". $pos_bet_id .'.txt',0666);
	}
?>
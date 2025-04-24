<?php
@session_start();
$s_id=session_id();

function updateOrderCost($id) {

  $i = 0;
  $vareId=array();
  $qtxt = "SELECT ordrelinjer.id as olid, ordrelinjer.vare_id as olvid, ordrelinjer.kostpris as olkpr, varer.kostpris as vkpr ";
  $qtxt.= "from ordrelinjer,varer where ordrelinjer.vare_id > '0' and ordrelinjer.ordre_id = '$orderId' and varer.id = ordrelinjer.vare_id order by ordrelinjer.id";

  $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
  while ($r=db_fetch_array($q)) {
    if (!in_array($r['vare_id'],$vareId)) {
      $id[$i]    = $r['olid'];
      $vareId[$i]  = $r['olvid'];
      $olCost[$i] = $r['olkpr'];
	  $vCost[$i] = $r['vkpr'];
	  $i++;
    }
  }
  for ($i=0; $i<count($id); $i++) {
    if ($olCost[$i] != $vCost[$i]) {
      $qtxt = "update ordrelinjer set kostpris = '$vCost[$i]' where id = '$id[$i]'";
	  db_modify($qtxt,__FILE__ . " linje " . __LINE__);
    }
  }
} 
?>

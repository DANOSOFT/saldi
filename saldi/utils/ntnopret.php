<?php
  @session_start();
  $s_id=session_id();


  include("../includes/connect.php");
  include("../includes/online.php");
  include("../includes/db_query.php");
  transaktion("begin");
  db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',1, '100', 0, 6, 55100)");
  db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',1, '-100', 0, 6, 2100)");
  db_modify("update batch_kob set pris='100' where id=3");
transaktion("commit");
echo "Udført";
?>
</tbody></table>
</body></html>

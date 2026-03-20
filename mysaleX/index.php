<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <title>Saldi - Mit salg</title>
</head>
<?php
(isset($_GET['id']))?$id = $_GET['id']:$id = 0;
print "<meta http-equiv='refresh' content='0;url=mysale.php?id=$id'>";
?>
</body>
</html>

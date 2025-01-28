<?php
    @session_start();
    $s_id=session_id();
    $id = $_GET['id'];
    $condition = $_GET['condition'];
    include ('../includes/connect.php');
    include ("../includes/std_func.php");
    $query = db_select("SELECT db FROM mysale WHERE link = '$id'", __FILE__ . " linje " . __LINE__);
    $row = db_fetch_array($query);
    $db = $row['db'];
    $urlPrefix = "https://ssl8.saldi.dk/laja/mysale/mysale.php?id=";
if (strpos($id, $urlPrefix) === 0) {
    // Remove the URL prefix
    $newId = str_replace($urlPrefix, '', $id);
}else{
	$newId = $id;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src='tailwind.js'></script>
    <link href="flowbite.min.css" rel="stylesheet" />
    <link href="main.css" rel="stylesheet" />
    <!-- Include Flatpickr CSS -->
    <link rel="stylesheet" href="../css/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="../css/flatpickrDark.css">

    <!-- Include Flatpickr JavaScript -->
    <script src="../javascript/flatpickr.min.js"></script>
    <script src="../javascript/flatpickrDa.js"></script>
</head>
<body class="bg-gray-700">
        <?php
        include_once("sidemenu.php");
        ?>
        <div class="p-4 lg:ml-64 h-screen">
            <div id="loading">
                <img id="loading-image" src="https://upload.wikimedia.org/wikipedia/commons/c/c7/Loading_2.gif?20170503175831" alt="Loading..." />
            </div>
            <div class="main w-4/5 mx-auto mt-2">
                <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                    <table class="w-full text-4xl lg:text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 tablePoint">
                    </table>
                </div>
            </div>
            <script src="flowbite.min.js"></script>
            <script src="mybooking.js?<?php echo time(); ?>"></script>
        </div>
    </body>
</html>
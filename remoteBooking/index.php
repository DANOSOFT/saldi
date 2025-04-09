<?php
include("../includes/connect.php");
include("../includes/std_func.php");
$db = $_GET["id"];
$connection = db_connect($sqhost, $squser, $sqpass, $db);

$query = db_select("SELECT trade_conditions FROM rentalpayment", __FILE__ . " linje " . __LINE__);
if(db_num_rows($query) > 0) {
    $row = db_fetch_array($query);
    $terms = $row["trade_conditions"];
}else{
    $terms = "#";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="tailwind.js"></script>
    <link href="flowbite.min.css" rel="stylesheet" />
    <link href="main.css" rel="stylesheet" />
    <!-- Include Flatpickr CSS -->
    <link rel="stylesheet" href="../css/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="../css/flatpickrDark.css">

    <!-- Include Flatpickr JavaScript -->
    <script src="../javascript/flatpickr.min.js"></script>
    <script src="../javascript/flatpickrDa.js"></script>
    <title>Booking</title>
</head>
<body class="bg-gray-700">
<div id="loading">
    <img id="loading-image" src="https://upload.wikimedia.org/wikipedia/commons/c/c7/Loading_2.gif?20170503175831" alt="Loading..." />
</div>
<!-- Main modal -->
<div id="crud-modal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-md max-h-full mx-auto">
        <!-- Modal content -->
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-600">
            <!-- Modal header -->
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t dark:border-gray-600">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Dine informationer
                </h3>
                <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-toggle="crud-modal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14" data-modal-toggle="crud-modal">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" data-modal-toggle="crud-modal"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>
            <hr>
            <!-- Modal body -->
             <p class="text-white mt-3 px-4">Hvis du allerede er kunde kan du logge ind her istedet 
                <button class="text-white mt-3 inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 payment" id="login" onClick="window.open('https://ssl8.saldi.dk/laja/mysale/mysale.php', '_blank')">
                    Logind
                </button>
            </p>
            <hr class="mt-5">      
                <form class="p-4 md:p-5" method="post">
                    <div class="grid gap-4 mb-4 grid-cols-2">
                        <div class="col-span-2 sm:col-span-1">
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Navn</label>
                            <input type="text" name="name" id="name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label for="addr" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Adresse</label>
                            <input type="text" name="addr" id="addr" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label for="city" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">By</label>
                            <input type="text" name="city" id="city" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label for="zip" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Post Nr.</label>
                            <input type="text" name="zip" id="zip" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label for="tlf" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Tlf</label>
                            <input type="text" name="tlf" id="tlf" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Email</label>
                            <input type="text" name="email" id="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" required>
                        </div>
                        <div class="col-span-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="terms" name="terms" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" required>
                                <label for="terms" class="ml-2 text-sm font-medium text-gray-900 dark:text-white">
                                    Jeg accepterer <a href="<?php echo $terms ?>" target="_blank" class="text-blue-600 hover:underline dark:text-blue-500">handelsbetingelserne</a>
                                </label>
                            </div>
                        </div>
                    </div>
                    <button class="text-white inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 payment" id="newUser">
                        gå til betaling
                    </button>
                </form>
        </div>
    </div>
</div>



    <div class="main w-7/8 mx-auto mt-2">
    </div>
    <script src="flowbite.min.js"></script>
    <script src="index.js?v=<?php echo time(); ?>"></script>
</body>
</html>
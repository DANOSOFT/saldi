

<div class="col-6 pdfUpload">
    <form id="formUp" action="ordreliste.php" method="post" enctype="multipart/form-data">
        <div class ="row">
            <input type="file" name="fileToUpload" id="fileToUpload" class="form-control form-control-sm">
        </div>
        <div class ="row">
            <input type="submit" value="Upload Voucher" name="submit" class="form-control form-control-sm">
    </form>
</div>

<style>
    .pdfUpload {
        margin-top: 20px;
    }
    #fileToUpload {
        width: 250px;
    }
</style>

<?php
$dirPath = dirname(__DIR__, 3);
$dirPath = "$dirPath/vouchers/$db/scan";
#print "<h1> Directory: $dirPath</h1>";

if(isset($_POST['submit'])){
	$errors= array();//
	$name = $_FILES['fileToUpload']['name'];
	$size = $_FILES['fileToUpload']['size'];
	$type = $_FILES['fileToUpload']['type'];
	$tmp_name = $_FILES['fileToUpload']['tmp_name'];
	$tmp = explode('.', $_FILES['fileToUpload']['name']);
	$file_ext = end($tmp);
	#$file_ext=strtolower(end(explode('.',$_FILES['fileToUpload']['name'])));
	$extensions= array("pdf","jpeg","jpg","png");
	$jj = file_get_contents($_FILES['fileToUpload']['tmp_name']);
	if(in_array($file_ext,$extensions)=== false){
		$errors[] = "please choose a Pdf file.";
	}
	if($size >= 4097152){
		$errors[]='File size must not be greater than 3MB';
	}
	#$dir = "../paperpdf/upvoucher/";
	$dir = "../bilag/$db/scan/";
	#$uploaded= "../paperpdf/upvoucher/$name";
	$uploaded= "../bilag/$db/scan/$name";
	#print "<h1> Directory: $dir <br> upload: $uploaded </h1>";
	#$dir = "$dirPath/";
	#$uploaded= "$dirPath/$name";
	if (!file_exists($dir)) {
		mkdir($dir, 0777, true);
	}
	$files = scandir($dir);
	$filesize = count($files);
	$_SESSION['pdfFileName'] = $name;

	if($name && file_exists($uploaded)){
		$url = "paperflowupload.php";
		echo "<script> alert('The file $name already exists');</script>";//20201210
		echo "<meta http-equiv=\"refresh\" content=\"3;URL=../kreditor/ordreliste.php\">\n"; //this replaced the script above for sending the voucher twice instead of once even when return is used
		return;
	} elseif (!$name) {
		echo "<script> alert('No file chosen');</script>";//20201210
		echo "<meta http-equiv=\"refresh\" content=\"3;URL=../kreditor/ordreliste.php\">\n"; //this replaced the script above for sending the 	
	} else {
		$_SESSION['vouc'] = $jj; // this is used in paperflowapi.php
		$_SESSION['filepath']= $uploaded;
		$target_dir = $dir.basename($_FILES["fileToUpload"]["name"]);
            #print "<h1> Target dir: $target_dir </h1>";
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_dir)) {
                echo "The file has been uploaded.";
            } else {
                echo "Sorry, there was an error uploading your file.";
                return;
            }

            $path = $uploaded;
            header("Content-Length: " . filesize ( $path ) );
            header("Content-disposition: inline; filename=".basename($path));
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            ob_clean();
            flush();
            if(http_response_code()!=200 ) {
                echo "<meta http-equiv=\"refresh\" content=\"60\" >";
            }
            echo "<embed src='$path' type='application/pdf' width='80%' height='200px' />"; //this works and displays pdf

            echo " <form action=\"scanAttachments/paperflowapi.php\" method=\"post\" id =\"formUp\enctype=\"multipart/form-data\" >
                 <input type=\"hidden\" id=\"ToUpload\" name=\"ToUpload\" value=\"$path\">
                  
                  <input type=\"submit\" value=\"Upload PDF\">
                </form> " ;

            echo "  <form action=\"../paperpdf/voucherdelete.php\" method=\"POST\" >
                <input type=\"hidden\" name=\"delete\" value=\"delete\">
                <input name=\"delete\" type=\"submit\" id=\"delete\" value=\"delete\">
                </form>";

        }
    }
?>

<?php?
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/scanAttachments/paperflowupload.php --- lap 4.0.4 --- 2021-11-25 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2021-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 
//20210804 Created by LOE
//20211125 PHR Moved to kreditor/scanAttachments and modified


$_SESSION['content'] = NULL
?>
<center>
<br><br><br>
<form action="paperflowapi.php" method="POST" enctype="multipart/form-data">
	<input type="file" name="toUpload" id="toUpload"><br><br>
	<input type="submit" value="Upload PDF" name="submit"><br>
</form>
<br><a href='../ordreliste.php?valg=skanBilag'><button>Cancel</button></a>



<!--


<script>


$(document).ready(function (e) {
 $("#formUp").on('submit',(function(e) {
 // e.preventDefault();
  $.ajax({
         url: "paperflowapi.php",
   type: "POST",
   data:  new FormData(this),
   contentType: false,
         cache: false,
   processData:false,
   beforeSend : function()
   {
    //$("#preview").fadeOut();
    $("#err").fadeOut();
   },
   success: function(data)
      {
    if(data=='invalid')
    {
     // invalid file format.
     $("#err").html("Invalid File !").fadeIn();
    }
    else
    {
     // view uploaded file.
     //$("#preview").html(data).fadeIn();
     $("#formUp")[0].reset(); 
     console.log(status);
    }
      },
     error: function(e) 
      {
    $("#err").html(e).fadeIn();
      }          
    });
 }));
});


</script>


-->







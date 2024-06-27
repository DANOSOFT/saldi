/*
 *
 *20240112 LOE created the file
 */



   // Function to handle drag enter event
   function handleDragEnter(event) {
    event.preventDefault();
    // Change background color of 'dropZone' for visual indication
    document.getElementById('dropZone').style.backgroundColor = 'blue';
  }

  // Function to handle drag leave event
  function handleDragLeave(event) {
    event.preventDefault();
    // Revert background color of 'dropZone'
    document.getElementById('dropZone').style.backgroundColor = '';
  }


  function handleDragOver(event) {
    event.preventDefault();
  }

  function handleDrop(event) {
    event.preventDefault();
         // Remove 'dragover' class from 'dropZone' after dropping, this ensures it is not stuck on successive drops
        document.getElementById('dropZone').classList.remove('dragover');
        // Reset event listeners 
        resetEventListeners();

    // Retrieve the dropped file
    var files = event.dataTransfer.files;
    if (files.length > 0) {
      var file = files[0];
		// Check if the dropped file is a PDF
		if (file.type === 'application/pdf') {
      // Extract variables from $clipImage URL
		// var clipVariables = {
		// 	sourceId: '<?php echo $id[$y]; ?>',
		// 	kladde_id: '<?php echo $kladde_id; ?>',
		// 	bilag: '<?php echo $bilag[$y]; ?>',
		// 	dokument: '<?php echo $dokument[$y]; ?>',
		// 	bilag_id: '<?php echo $id[$y]; ?>',
		// 	fokus: 'bila<?php echo $y; ?>'
		// };

		// Handle the file asynchronously (AJAX)
    jQuery(document).ready(function($) {
		   uploadFile(file, clipVariables);

      });
       /*
        console.log('PDF file dropped:', file.name, clipVariables.kladde_id,
        clipVariables.bilag, 
        clipVariables.sourceId);
        */
      } else {
        alert('Please drop a PDF file.');
      }
    
    }
	 // Revert background color of 'dropText' span
	 document.getElementById('dropText').style.backgroundColor = '';

}	
  

  function uploadFile(file, clipVariables) {
    // Create a FormData object and append the file and variables to it
    var formData = new FormData();
    formData.append('uploadedFile', file);
    
    // Append variables from $clipImage URL
    for (var key in clipVariables) {
      if (clipVariables.hasOwnProperty(key)) {
        formData.append(key, clipVariables[key]);
      }
    }

    // Send the file and variables using AJAX
    $.ajax({
      url: '../includes/documents.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        //console.log('File uploaded successfully:', response);
        //console.log('File uploaded successfully:', response);
//alert('File uploaded successfully')

        // Handle the response as needed
            // Reload the PHP file 
            // if (confirm('File uploaded successfully')) {
              
            //    window.location.reload();
            // }
                  // Confirm the upload using JavaScript
            var confirmUpload = confirm('upload successful');

            // If confirmed, set the flag and reload the page
            if (confirmUpload) {
              // Set the flag in the URL
              window.location.href = window.location.href + '?confirm_upload=true';
            }

        
      

      },
      error: function(xhr, status, error) {
        console.error('Error uploading file:', error);
        // Handle the error as needed
      }
    });
  }

// Function to reset event listeners 
function resetEventListeners() {
  var dropZone = document.getElementById('dropZone');

  // Remove existing event listeners
  dropZone.removeEventListener('dragenter', handleDragEnter);
  dropZone.removeEventListener('dragleave', handleDragLeave);
  dropZone.removeEventListener('dragover', handleDragOver);
  dropZone.removeEventListener('drop', handleDrop);

  // Add new event listeners
  dropZone.addEventListener('dragenter', handleDragEnter);
  dropZone.addEventListener('dragleave', handleDragLeave);
  dropZone.addEventListener('dragover', handleDragOver);
  dropZone.addEventListener('drop', handleDrop);
}
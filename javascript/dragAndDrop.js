/*
 *
 *20240112 LOE created the file
 */

   // Function to handle drag enter event
   function handleDragEnter(event) {
    event.preventDefault();
    // Change background color of 'dropZone' for visual indication
    var dropZone = document.getElementById('dropZone');
    if (dropZone) dropZone.style.backgroundColor = '#e0e0ff';
  }

  // Function to handle drag leave event
  function handleDragLeave(event) {
    event.preventDefault();
    // Revert background color of 'dropZone'
    var dropZone = document.getElementById('dropZone');
    if (dropZone) dropZone.style.backgroundColor = '';
  }


  function handleDragOver(event) {
    event.preventDefault();
  }

  function handleDrop(event) {
    event.preventDefault();
    
    var dropZone = document.getElementById('dropZone');
    var dropText = document.getElementById('dropText');
    
    // Remove 'dragover' class from 'dropZone' after dropping
    if (dropZone) dropZone.classList.remove('dragover');
    
    // Reset event listeners 
    resetEventListeners();

    // Retrieve the dropped file
    var files = event.dataTransfer.files;
    if (files.length > 0) {
      var file = files[0];
      // Check if the dropped file is a PDF or image (jpg, jpeg, png)
      var allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
      var fileType = file.type.toLowerCase();
      
      // Also check by file extension as fallback
      var fileName = file.name.toLowerCase();
      var hasAllowedExtension = fileName.endsWith('.pdf') || fileName.endsWith('.jpg') || 
                                 fileName.endsWith('.jpeg') || fileName.endsWith('.png');
      
      if (allowedTypes.indexOf(fileType) !== -1 || hasAllowedExtension) {
        // Handle the file upload
        if (typeof clipVariables !== 'undefined') {
          uploadFile(file, clipVariables);
        } else {
          console.error('clipVariables is not defined');
          alert('Error: Upload configuration not found. Please refresh the page.');
        }
      } else {
        alert('Please drop a PDF or image file (jpg, png).\nFile type: ' + fileType);
      }
    }
    // Revert background color of 'dropText' span
    if (dropText) dropText.style.backgroundColor = '';
}	
  

  function uploadFile(file, clipVariables) {
    // Show loading indicator
    var dropZone = document.getElementById('dropZone');
    var dropText = document.getElementById('dropText');
    var originalText = dropText ? dropText.innerHTML : '';
    
    if (dropZone) {
      dropZone.style.backgroundColor = '#fffacd'; // Light yellow
      dropZone.style.borderColor = '#ffa500'; // Orange
    }
    if (dropText) {
      dropText.innerHTML = '⏳ Uploader og analyserer...';
      dropText.style.color = '#666';
    }
    
    // Create a FormData object and append the file and variables to it
    var formData = new FormData();
    formData.append('uploadedFile', file);
    
    // Append variables from clipVariables
    for (var key in clipVariables) {
      if (clipVariables.hasOwnProperty(key)) {
        formData.append(key, clipVariables[key]);
      }
    }

    // Determine the correct URL based on current location
    var currentPath = window.location.pathname;
    var uploadUrl;
    if (currentPath.indexOf('/includes/') !== -1) {
      uploadUrl = 'documents.php';
    } else if (currentPath.indexOf('/finans/') !== -1 || currentPath.indexOf('/debitor/') !== -1 || currentPath.indexOf('/kreditor/') !== -1) {
      uploadUrl = '../includes/documents.php';
    } else {
      uploadUrl = 'includes/documents.php';
    }

    // Send the file using fetch API
    fetch(uploadUrl, {
      method: 'POST',
      body: formData
    })
    .then(function(response) {
      // Get response text first to debug
      return response.text().then(function(text) {
        // Try to parse as JSON
        try {
          return JSON.parse(text);
        } catch(e) {
          console.error('Response is not valid JSON:', text.substring(0, 500));
          // If response contains success indicators, treat as success
          if (text.indexOf('"success":true') !== -1 || text.indexOf("success': true") !== -1) {
            // Try to extract filename from response
            var filenameMatch = text.match(/"filename"\s*:\s*"([^"]+)"/);
            return {
              success: true,
              filename: filenameMatch ? filenameMatch[1] : file.name,
              message: 'File uploaded successfully'
            };
          }
          throw new Error('Invalid response from server');
        }
      });
    })
    .then(function(data) {
      // Reset loading indicator
      if (dropZone) {
        dropZone.style.backgroundColor = '';
        dropZone.style.borderColor = '';
      }
      if (dropText) {
        dropText.innerHTML = originalText;
        dropText.style.color = '';
      }
      
      if (data && data.success) {
        var message = '✓ Upload successful: ' + data.filename;
        if (data.extracted) {
          if (data.extracted.amount) {
            message += '\nAmount: ' + data.extracted.amount;
          }
          if (data.extracted.date) {
            message += '\nDate: ' + data.extracted.date;
          }
        }
        alert(message);
        
        // Redirect to focus on the uploaded file
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('poolFile', data.filename);
        currentUrl.searchParams.set('openPool', '1');
        window.location.href = currentUrl.toString();
      } else {
        var errorMsg = data && data.message ? data.message : 'Upload failed';
        alert('Error: ' + errorMsg);
      }
    })
    .catch(function(error) {
      // Reset loading indicator on error
      if (dropZone) {
        dropZone.style.backgroundColor = '';
        dropZone.style.borderColor = '';
      }
      if (dropText) {
        dropText.innerHTML = originalText;
        dropText.style.color = '';
      }
      
      console.error('Error uploading file:', error);
      alert('Error uploading file: ' + error.message);
    });
  }

// Function to reset event listeners 
function resetEventListeners() {
  var dropZone = document.getElementById('dropZone');
  if (!dropZone) return;

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
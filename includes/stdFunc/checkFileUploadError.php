<?php
#if (function_exists("checkFileUploadError")) exit;
function checkFileUploadError($file) {
  if (!isset($file['error']) || is_array($file['error'])) {
    return 'Invalid parameters.';
  }

  $errors = array(
    UPLOAD_ERR_OK => 'No errors.',
    UPLOAD_ERR_INI_SIZE => 'Larger than upload_max_filesize.',
    UPLOAD_ERR_FORM_SIZE => 'Larger than form MAX_FILE_SIZE.',
    UPLOAD_ERR_PARTIAL => 'Partial upload.',
    UPLOAD_ERR_NO_FILE => 'No file.',
    UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory.',
    UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk.',
    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.'
  );
  // Check file upload error
  if ($file['error'] !== UPLOAD_ERR_OK) {
    return $errors[$file['error']];
  }
}
?>

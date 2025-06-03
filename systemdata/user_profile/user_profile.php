<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---systemdata/user_profile/user_profile.php---patch 4.1.1---2025-06--02---
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();

$modulnr=1;
$title="Brugere Profile";
$css="../css/standard.css";

$employeeId=$rights=$roRights=array();


include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/std_func.php");



 
# db of online user

if($is_admin){

	$message = "Your detail not found here."; 
			
			echo "<script type='text/javascript'>
        alert(" . json_encode($message) . ");
        window.location.href = '../syssetup.php';
      </script>";

	exit;
}


###################
$qtxt = "SELECT * FROM ansatte WHERE brugernavn = '$brugernavn'";
$employeeDetails = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

if (empty($employeeDetails)) {
    // Not found in ansatte, check brugere
    $qtxt2 = "SELECT * FROM brugere WHERE brugernavn = '$brugernavn'";
    $brugere = db_fetch_array(db_select($qtxt2, __FILE__ . " linje " . __LINE__));
    if (!empty($brugere)) {
        $bruger_id = $brugere['id'];
		$brugernavn = $brugere['brugernavn'];
        $insert = "INSERT INTO ansatte (bruger_id, brugernavn) VALUES ('$bruger_id', '$brugernavn')";
        db_modify($insert, __FILE__ . " linje " . __LINE__);

		if($bruger_id){
			$qtxt = "SELECT id FROM ansatte WHERE bruger_id = '$bruger_id' AND brugernavn = '$brugernavn' ORDER BY id DESC LIMIT 1";
			$result = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$ansatId = $result['id'] ?? null;

			if ($ansatId) {
				$updateAnsattId = "UPDATE brugere SET ansat_id = $ansatId WHERE id = $bruger_id";
				db_modify($updateAnsattId, __FILE__ . " linje " . __LINE__);
			}
		}

		
    } else {
       
        error_log("Brugernavn not found in brugere: $brugernavn");
    }
}




$initialer= $employeeDetails["initialer"];
$employeeName = $employeeDetails["navn"];
$employeeUserName = $employeeDetails['brugernavn'];
$employeeEmail = $employeeDetails["email"];
$employeeMobile = $employeeDetails["mobil"];
$employeeKontoId = $employeeDetails['konto_id'];
$employeeImage = $employeeDetails['profile_image'];


#######get the company they work under
		$q = db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array($q);
			if($r != false){
				$countryConfig = $r['land'];
				$konto_id1=(int) $r['id'];
				$CompanyName =$r['firmanavn'];

				if(!if_isset($employeeKontoId,NULL) && if_isset($konto_id1,NULL)){
					#Update ansatte table with the konto id
					$updateKontotId = "UPDATE ansatte SET konto_id = $konto_id1 WHERE id = $bruger_id";
					db_modify($updateKontotId, __FILE__ . " linje " . __LINE__);
				}
		}
################


//Employee data
$user = [
    'full_name'    => "$employeeName",
    'email'        => "$employeeEmail",
    'phone'        => "$employeeMobile",
    'company_name' => "$CompanyName",
    'job_title'    => "$initialer",
    'dbName'       => "$db",
    'globalId'     => '',
    'AccountName'  => '',
    'userName'     =>  "$employeeUserName" 
];


// --- Handle form data (image upload) before output ---
$imageMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
		if (!mkdir($uploadDir, 0777, true)) {
			die('<div class="alert alert-danger mt-2">Failed to create upload directory: Check permissions</div>');
		}
	}

    $file = $_FILES['profile_image'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $newName = 'user_' . md5($user['globalId']) . '.' . $ext;
            $dest = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // Set relative path for use on the site
                $relativePath = 'uploads/' . $newName;
                $employeeImage  = $relativePath;


				// Look up old image path

				$tempBackupPath = '';
				if ($oldData && !empty($oldData['profile_image'])) {
					$oldImagePath = __DIR__ . '/' . $oldData['profile_image'];
					if (file_exists($oldImagePath)) {
						// Create temp backup path, e.g. add ".bak" suffix
						$tempBackupPath = $oldImagePath . '.bak';

						// Move old image to backup instead of deleting
						if (!rename($oldImagePath, $tempBackupPath)) {
							// Could not move old image to backup, handle error if needed
							$imageMsg = '<div class="alert alert-danger mt-2">Failed to backup old image.</div>';
							exit;
						}
					}
				}

				// Escape inputs for SQL
				$escapedPath = function_exists('db_escape_string') ? db_escape_string($relativePath) : addslashes($relativePath);
				$escapedBrugernavn = function_exists('db_escape_string') ? db_escape_string($brugernavn) : addslashes($brugernavn);

		
				$updateAnsatImage = "UPDATE ansatte SET profile_image = '$escapedPath' WHERE brugernavn = '$escapedBrugernavn'";
				$Updated = db_modify($updateAnsatImage, __FILE__ . " linje " . __LINE__);

				if ($Updated) {
					
                    echo "<script>alert('Image updated and saved')</script>";
					// DB update succeeded, delete the temp backup file if it exists
					if ($tempBackupPath && file_exists($tempBackupPath)) {
						unlink($tempBackupPath);
					}

				}else {
                    $imageMsg = '<div class="alert alert-warning mt-2">Image saved, but DB update failed.</div>';
					 // Remove the new file since DB update failed
					if (file_exists($dest)) {
						unlink($dest);
					}
                }
            } else {
                $imageMsg = '<div class="alert alert-danger mt-2">Upload failed. Could not move file to destination.</div>';
            }
        } else {
            $imageMsg = '<div class="alert alert-warning mt-2">Invalid file type.</div>';
        }
    } else {
        $imageMsg = '<div class="alert alert-danger mt-2">No file selected or upload error.</div>';
    }
}


// Handle profile details POST (email, phone, company, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    // Sanitize inputs
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['userName']);
    $employeeName = trim($_POST['full_name']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileMsg = '<div class="alert alert-danger mt-2">Invalid email address.</div>';
    } else {
        // Update DB 
      
        $updateAnsatProfile = "UPDATE ansatte SET mobil='$phone', email='$email', navn ='$employeeName' WHERE brugernavn = '$username'";
		$Updated = db_modify($updateAnsatProfile, __FILE__ . " linje " . __LINE__);
        // Update $user array to reflect changes immediately on page
        $user['email'] = $email;
        $user['phone'] = $phone;
        $user['userName'] = $username;
        $user['full_name'] = $employeeName;

        echo "Profile details updated";
    }
}

// --- Profile overview output ---
print '<style>
.user-profile-container { min-height: 80vh !important; }
.user-profile-card { box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.10) !important; border: none !important; border-radius: 1.5rem !important; background: #fff !important; }
.user-profile-header { display: flex !important; align-items: center !important; justify-content: space-between !important; margin-bottom: 2rem !important; flex-wrap: wrap !important; }
.user-profile-avatar { width: 120px !important; height: 120px !important; object-fit: cover !important; border-radius: 50% !important; box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important; margin-left: 2rem !important; flex-shrink: 0 !important; }
.user-profile-title { margin-top: 0 !important; margin-bottom: 0.25rem !important; font-weight: bold !important; color: #0d6efd !important; }
.user-profile-job { color: #6c757d !important; font-size: 0.95rem !important; }
.user-profile-info { flex: 1 1 0 !important; min-width: 0 !important; }
.user-profile-row .bg-light { background: #f8f9fa !important; border-radius: 0.75rem !important; padding: 1rem !important; margin-bottom: 0.5rem !important; }
.user-profile-label { font-weight: 600 !important; color: #6c757d !important; min-width: 160px !important; display: inline-block !important; letter-spacing: 0.5px !important; }
.user-profile-value { margin-left: 2.5rem !important; display: inline-block !important; }
.user-profile-img-form { margin-top: 1rem !important; text-align: right !important; }
.user-profile-img-form input[type="file"] { display: inline-block !important; }
.user-profile-img-form button { margin-left: 0.5rem !important; }
@media (max-width: 768px) {
	.user-profile-header { flex-direction: column-reverse !important; align-items: center !important; }
	.user-profile-avatar { margin: 0 0 1rem 0 !important; }
	.user-profile-info { width: 100% !important; text-align: center !important; }
	.user-profile-img-form { text-align: center !important; }
}
</style>';

print '<div class="container d-flex justify-content-center align-items-center user-profile-container">';
print '  <div class="row w-100 justify-content-center">';

print '    <div class="col-md-7 col-lg-5">';
print '      <div class="card user-profile-card p-4">';
####
print '<div style="margin-left:2rem; margin-bottom:1rem;">';
print '  <button onclick="location.href=\'../syssetup.php\'" 
               style="padding: 4px 8px; background-color: #2D68C4; color: white; border: none; border-radius: 4px; font-size: 0.9rem; cursor: pointer;">
            &larr; Back to System Setup
         </button>';
print '</div>';
###

print '        <div class="user-profile-header">';
print '          <div class="user-profile-info">';
print '            <h3 class="user-profile-title">' . htmlspecialchars($user["full_name"]) . '</h3>';
print '            <div class="user-profile-job">' .  htmlspecialchars($user['company_name']) . '</div>';
print '          </div>';
if (!empty($employeeImage )) {
	print '          <img src="' . htmlspecialchars($employeeImage ) . '?t=' . time() . '" alt="Profile Image" class="img-fluid user-profile-avatar">';
} else {
	print '          <img src="https://ui-avatars.com/api/?name=' . urlencode($user["full_name"]) . '&background=0D8ABC&color=fff&size=120" alt="No Image" class="img-fluid user-profile-avatar">';
}
print '        </div>';

// Image upload form with "Update Image" button
print <<<HTML
<form class="user-profile-img-form" method="post" enctype="multipart/form-data" id="profileImgForm">
	<button type="button" class="btn btn-sm btn-primary" id="showFileInputBtn">Update Image</button>
	<span id="fileInputWrapper" style="display:none;">
	<input type="file" name="profile_image" accept="image/*" required>
	<button type="submit" class="btn btn-sm btn-success">Upload</button>
	<button type="button" class="btn btn-sm btn-secondary" id="cancelFileInputBtn">Cancel</button>
	</span>
</form>
<script>
document.getElementById('showFileInputBtn').onclick = function() {
	document.getElementById('fileInputWrapper').style.display = 'inline-block';
	this.style.display = 'none';
};
document.getElementById('cancelFileInputBtn').onclick = function() {
	document.getElementById('fileInputWrapper').style.display = 'none';
	document.getElementById('showFileInputBtn').style.display = 'inline-block';
	document.querySelector('input[name="profile_image"]').value = '';
};
</script>
HTML;

if ($imageMsg) print $imageMsg;

print '        <div class="mb-3" style="margin-left: 2rem;">';
print '          <div class="row g-2 user-profile-row">';
$fields = [
	'Email'        => $user["email"],
	'Phone'        => $user["phone"],
	'Database'     => $user["dbName"],
	'Global ID'    => $user["globalId"],
	'Full Name'    => $user["full_name"],
	'Username'     => $user["userName"]
];
foreach ($fields as $label => $value) {
	print '            <div class="col-12">';
	print '              <div class="bg-light">';
	print '                <span class="user-profile-label">' . htmlspecialchars($label) . ':</span>';
	print '                <span class="user-profile-value">' . htmlspecialchars($value) . '</span>';
	print '              </div>';
	print '            </div>';
}
print '          </div>';
print '        </div>';



print '<style>.user-profile-header { justify-content: center !important; }';
print '.user-profile-title, .user-profile-job { text-align: center !important; display: block !important; margin-left: 0 !important; }</style>';

// Link to edit other profile details
print '<div class="text-end mt-3" style="margin-left: 2rem;">';
####

$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

if ($editMode) {
    print '<form method="post" class="mt-4" style="margin-left: 2rem;">';
    print '  <input type="hidden" name="edit_profile" value="1">';

    print '  <div class="mb-3">';
    print '    <label for="email" class="form-label user-profile-label">Email:</label>';
    print '    <input type="email" name="email" id="email" value="' . htmlspecialchars($user['email']) . '" class="form-control" required>';
    print '  </div>';

    print '  <div class="mb-3">';
    print '    <label for="phone" class="form-label user-profile-label">Phone:</label>';
    print '    <input type="text" name="phone" id="phone" value="' . htmlspecialchars($user['phone']) . '" class="form-control">';
    print '  </div>';

    print '  <div class="mb-3">';
    print '    <label for="full_name" class="form-label user-profile-label">Name:</label>';
    print '    <input type="text" name="full_name" id="full_name" value="' . htmlspecialchars($user['full_name']) . '" class="form-control">';
    print '  </div>';

    print '    <input type="hidden" name="userName" id="userName" value="' . htmlspecialchars($user['userName']) . '" class="form-control" required>';

    print '  <div class="mb-3 text-end">';
    print '    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>';
    print '    <a href="?edit=0" class="btn btn-secondary btn-sm">Cancel</a>';
    print '  </div>';

    print '</form>';
} else {
    // Show the Edit button
    print '<div class="text-end mt-3" style="margin-left: 2rem;">';
    print '  <a href="?edit=1" class="btn btn-outline-secondary btn-sm">Edit Profile Details</a>';
    print '</div>';
}


####
print '</div>';

print '      </div>';
print '    </div>';
print '  </div>';
print '</div>';

<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/tofaktor.php --- lap 4.1.0 --- 2024.02.09 ---
// Included fra login.php
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------

$css="../css/login.css";

?>

<div id="main">
	<div class="loginHolder">
		<div class="loginBox">
			<div class="loginForm">
			<a href='https://saldi.dk'><img class="logoimg" src='../img/Saldi_Main_Logo.png' width='100px'></a>
			<div>
				<h2>Tofaktorgodkendelse</h2>
				<p><?php print $status; ?></p>
			</div>
			<form method="POST" action="login.php" id="faform">
			<input class="textinput" type="text" id="regnskab" name="regnskab" value="<?php print $regnskab; ?>" tabindex="1" hidden>
				<input class="textinput" type="text" id="login" name="brugernavn" value="<?php print $brugernavn; ?>" tabindex="2" hidden>
				<input class="textinput" type="password" id="password" name="password"  value="<?php print $password; ?>" tabindex="3" hidden>
				<div id="fa-row">
					<input class="textinput" type="text" id="code_1" name="code_1" tabindex="4">
					<input class="textinput" type="text" id="code_2" name="code_2" tabindex="5">
					<input class="textinput" type="text" id="code_3" name="code_3" tabindex="6">
					<input class="textinput" type="text" id="code_4" name="code_4" tabindex="7">
					<input class="textinput" type="text" id="code_5" name="code_5" tabindex="8">
					<input class="textinput" type="text" id="code_6" name="code_6" tabindex="9" maxlength="1">
				</div>
				<div id="fa-row" style="height: 47px; padding-top: 20px;">
					<button class="button blue flright">Indsend</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	window.addEventListener("load", (event) => {
		document.getElementById("code_1").focus();
	});

	document.getElementById("code_1").addEventListener("input", (event) => {
		console.log(event);
		if (event.inputType === "deleteContentBackward") {
		} else {
			if (event.target.value.length > 1) {
				document.getElementById("code_2").value = event.target.value[1];
				document.getElementById("code_1").value = event.target.value[0];
			}
			document.getElementById("code_2").focus();
		}
	});

	document.getElementById("code_2").addEventListener("input", (event) => {
		if (event.inputType === "deleteContentBackward") {
			document.getElementById("code_1").focus();
		} else {
			if (event.target.value.length > 1) {
				document.getElementById("code_3").value = event.target.value[1];
				document.getElementById("code_2").value = event.target.value[0];
			}
			document.getElementById("code_3").focus();
		}
	});
	document.getElementById("code_2").addEventListener("keydown", (event) => {
		if (event.key === 'Backspace' && event.target.value === '') {
			document.getElementById("code_1").value = "";
			document.getElementById("code_1").focus();
		}
	});

	document.getElementById("code_3").addEventListener("input", (event) => {
		if (event.inputType === "deleteContentBackward") {
			document.getElementById("code_2").focus();
		} else {
			if (event.target.value.length > 1) {
				document.getElementById("code_4").value = event.target.value[1];
				document.getElementById("code_3").value = event.target.value[0];
			}
			document.getElementById("code_4").focus();
		}
	});
	document.getElementById("code_3").addEventListener("keydown", (event) => {
		if (event.key === 'Backspace' && event.target.value === '') {
			document.getElementById("code_2").value = "";
			document.getElementById("code_2").focus();
		}
	});

	document.getElementById("code_4").addEventListener("input", (event) => {
		if (event.inputType === "deleteContentBackward") {
			document.getElementById("code_3").focus();
		} else {
			if (event.target.value.length > 1) {
				document.getElementById("code_5").value = event.target.value[1];
				document.getElementById("code_4").value = event.target.value[0];
			}
			document.getElementById("code_5").focus();
		}
	});
	document.getElementById("code_4").addEventListener("keydown", (event) => {
		if (event.key === 'Backspace' && event.target.value === '') {
			document.getElementById("code_3").value = "";
			document.getElementById("code_3").focus();
		}
	});

	document.getElementById("code_5").addEventListener("input", (event) => {
		if (event.inputType === "deleteContentBackward") {
			document.getElementById("code_4").focus();
		} else {
			if (event.target.value.length > 1) {
				document.getElementById("code_6").value = event.target.value[1];
				document.getElementById("code_5").value = event.target.value[0];
			}
			document.getElementById("code_6").focus();
		}
	});
	document.getElementById("code_5").addEventListener("keydown", (event) => {
		if (event.key === 'Backspace' && event.target.value === '') {
			document.getElementById("code_4").value = "";
			document.getElementById("code_4").focus();
		}
	});

	document.getElementById("code_6").addEventListener("input", (event) => {
		if (event.inputType === "deleteContentBackward") {
			document.getElementById("code_5").focus();
		} else {
			document.getElementById("faform").submit();
		}
	});
	document.getElementById("code_6").addEventListener("keydown", (event) => {
		if (event.key === 'Backspace' && event.target.value === '') {
			document.getElementById("code_5").value = "";
			document.getElementById("code_5").focus();
		}
	});
</script>

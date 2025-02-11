<?php
// ------------------debitoripad/await.php--------lap 3.2.9----2024.06.07--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$css="../css/debitoripad.css";

$title="batch";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

print "<div id='background'></div>";

?>

<!-- Waiting for connect -->
<div id="statusline">
	<p><?php echo $brugernavn; ?></p>
	<p>Afventer aktivering</p>
</div>

<?php

$q = db_select("select id from ordrer where email='$brugernavn'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
# Check if there is a pending order that needs filling out by this tablet, the email field will be called the same as the user
if ($r) {
	# Check if input form has been filled out
	$email = $_GET["email"];
	if (isset($email)) {
		db_modify("UPDATE ordrer SET email='$email' WHERE email='$brugernavn'",__FILE__ . " linje " . __LINE__);
		header('Location: ./await.php');
		die();
	}

	# Print input form
	?>
		<div id="form-wapper">
			<form>
				<input name="email" placeholder="E-mail" type="email" pattern="[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{1,63}$" required>
				<button>Gem</button>
			</form>
			<span id="cancel" onclick="document.location.href = '?email='">Anuller</span>
		</div>
		<script>
			document.addEventListener("DOMContentLoaded", function() {
				var element = document.querySelector('#form-wapper');
				if(element){element.classList.add('ease')}
				var element = document.querySelector('#statusline');
				if(element){element.classList.add('dissapear')}
			});
		</script>
	<?php
	die();
}
?>

<script>
	setTimeout(() => {
		window.location.reload();
	}, 5000)
</script>

<?php

function ui_output($pretty_amount, $status_text, $status_color, $raw_amount = null, $indbetaling = null, $ordre_id = null, $ident = null) {
	if ($status_color === "red") {
		$bg = "#f00";
		$button = "
		<button style='
			width: 100%;
			height: 50px;

			margin-top: 5px;'
			onclick=\"window.location.replace('../pos_ordre.php?id=$ordre_id&godkendt=afvist')\"
		>
			Tilbage
		</button>";
	} else if ($status_color === "green") {
		$amount = (int) $raw_amount;
		$amount = $amount / 100;
		header("Location: ../pos_ordre.php?id=$ordre_id&godkendt=OK&indbetaling=$indbetaling&amount=$amount&betaling=mobilepay");
		exit;
	}else if ($status_color === "yellow") {
		$bg = "#ff0";
		$button = "
		<button style='
			width: 100%;
			height: 50px;
			margin-top: 5px;
			' ";
		if ($status_text === "Afventer QR scan") {
			$button = $button . "onclick=\"window.location.replace('../pos_ordre.php?id=$ordre_id&godkendt=afvist')\">
				Annuller
			</button>";
		} else {
			$button = $button . "onclick=\"window.location.replace('mobilepayCancel.php?ident=$ident&id=$ordre_id')\">
				Tilbage
			</button>";
		}
	}



	print "<div style='
		position: 
		absolute; 
		top: 50%; 
		left: 50%; 
		transform: translate(-50%, -50%);   
		width: 350px;
		min-height: 170px;
		padding: 30px;
		background-color: #ddd;
		border-radius: 7px;
	'>";
	print "$pretty_amount kr<br>";

	print "<div style='
		width: calc(100% - 30px);

		border: 2px #000 solid;
		padding: 15px;
		vertical-align: middle;

		margin-top: 10px;
		margin-bottom: 10px;
		background-color: $bg;
	'>";
	print "$status_text";
	print "</div>";
	print "$button";


	print "</div>";
}

?>

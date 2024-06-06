<?php

    function showPdf($paperflowArray, $id)
    {
        $pdf = $paperflowArray['data'][$id]['image'];
        print "<iframe src='$pdf' class='pdfIframe' title='pdf'> </iframe>";
    }
?>

<style>
	.pdfIframe {
        margin-top: 20px;
		height: 900px;
		width: 80%;
		border: #000000 2px solid;
		display: block;
		position: relative;
	}
</style>

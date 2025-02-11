<?php


function pos_row() {
    ?>
<style>
.posbut {
    padding: 1em;
    cursor:pointer;
    width: 100%;
}
#posbut-wrapper a {
    flex: 1;
    min-width: 15em;
}
</style>
<div style="
	flex: 2;
	min-width: 500px;
	background-color: #fff;
	border-radius: 5px;
	box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
	padding: 1.4em 2em;
">
	<h4 style="margin: 0; color: #999">POS muligheder</h4>
    <br>
        <div id="posbut-wrapper" style="
            display: flex;
            gap: 2em;
            flex-wrap: wrap;
        ">

            
            <a href="../lager/varer.php?returside=../index/dashboard.php"><button class="posbut" type="button">Åben vareliste</button></a>
            <a href="../lager/varekort.php?returside=../index/dashboard.php"><button class="posbut" type="button">Opret vare</button></a>
            <a href="../systemdata/posmenuer.php"><button class="posbut" type="button">Menu opsætning</button></a>
            <a href="../debitor/rapport.php"><button class="posbut" type="button">Åben rapporter</button></a>
        </div>
	</div>
	<?php
}


?>
<?php


function pos_row() {
    global $sprog_id;
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
	<h4 style="margin: 0; color: #999"><?php print findtekst('2771|POS-muligheder', $sprog_id)?></h4>
    <br>
        <div id="posbut-wrapper" style="
            display: flex;
            gap: 2em;
            flex-wrap: wrap;
        ">

            
            <a href="../lager/varer.php?returside=../index/dashboard.php"><button class="posbut" type="button"><?php echo findtekst('2584|Åbn vareliste', $sprog_id);?></button></a>
            <a href="../lager/varekort.php?returside=../index/dashboard.php"><button class="posbut" type="button"><?php echo findtekst('2585|Opret vare', $sprog_id);?></button></a>
            <a href="../systemdata/posmenuer.php"><button class="posbut" type="button"><?php echo findtekst('2586|Menu opsætning', $sprog_id);?></button></a>
            <a href="../debitor/rapport.php"><button class="posbut" type="button"><?php echo findtekst('2587|Åbn rapporter', $sprog_id);?></button></a>
        </div>
	</div>
	<?php
}


?>
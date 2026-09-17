<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager/varekort_includes/hiddevVars.php---------lap 3.9.3---2020-09-05	-----
// LICENS
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
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------

print "<input type='hidden' name='beskrivelse[0]' value=\"$beskrivelse[0]\">";
print "<input type='hidden' name='fokus' value='$fokus'>";
print "<input type='hidden' name='gruppe' value=\"$gruppe\">";
print "<input type='hidden' name='leverandor' value='$lev'>";
print "<input type='hidden' name='lagerantal' value='$lagerantal'>";
print "<input type='hidden' name='id' value='$id'>";
print "<input type='hidden' name='ordre_id' value='$ordre_id'>";
print "<input type='hidden' name='publ_pre' value='$publiceret'>";
print "<input type='hidden' name='returside' value='$returside'>";
print "<input type=\"hidden\" name='varenr' value=\"$varenr\">";
print "<input type='hidden' name='vare_sprogantal' value='$vare_sprogantal'>";
print "<input type=\"hidden\" name='vare_lev_id' value=\"$vare_lev_id\">";
print "<input type='hidden' name='oldDescription' value=\"$oldDescription\">";
for ($x=1;$x<=$vare_sprogantal;$x++) {
	print "<input type='hidden' name='vare_tekst_id[$x]' value=\"$vare_tekst_id[$x]\">";
	print "<input type='hidden' name='vare_sprog_id[$x]' value='$vare_sprog_id[$x]'>";
}
for($x=1;$x<=count($ny_lagerbeh);$x++) {
	print "<input type='hidden' name='lagerbeh[$x]' value=\"$lagerbeh[$x]\">";
	print "<input type='hidden' name='ny_lagerbeh[$x]' value=\"$ny_lagerbeh[$x]\">";
}
?>

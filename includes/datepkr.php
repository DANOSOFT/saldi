<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/datepkr.php -----patch 4.1.0 ----2024-05-28--------------
// license
//
// this program is free software. you can redistribute it and / or
// modify it under the terms of the gnu general public license (gpl)
// which is published by the free software foundation; either in version 2
// of this license or later version of your choice.
// however, respect the following:
//
// it is forbidden to use this program in competition with saldi.dk aps
// or other proprietor of the program without prior written agreement.
//
// the program is published with the hope that it will be beneficial,
// but without any kind of claim or warranty. 
// see gnu general public license for more details.
// http://www.saldi.dk/dok/gnu_gpl_v2.html
//
// copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------

function date_picker($date, $dateelmname, $formname, $justering = "right", $width = "") {
    /* create a datepicker
     *
     * parameters:
     * $date: a normal saldi formatted date or date range (ddmmyy:ddmmyy)
     * $dateelmname: the name of the element that the date picker will set and submit
     * $formname: the name of the form that will be automatically submitted
     * $justering: for use within tables, can set how it should be aligned
     * $width: the width of the field
     */
    

    print "<input type='text' name='$dateelmname-pkr' value='$date' style='text-align:$justering;$width'/> ";

    if ($date) {
        print "<button type=button onclick='
                document.getelementsbyname(\"$dateelmname\")[0].value=\"\"; 
                document.getelementsbyname(\"$dateelmname-pkr\")[0].value=\"\"; 
                document.getelementsbyname(\"$formname\")[0].submit.click();
            '>x</button>";
    }

    print "
    <script>
        $(function() {
            $('input[name=\"$dateelmname-pkr\"]').daterangepicker(
                {
                    locale: {
                        format: 'ddmmyy',
                        separator: ':'
                    },
                    autoupdateinput: false,
                    opens: 'left',
                    ranges: {
                        'idag': [moment(), moment()],
                        'igår': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                        'sidste 7 dage': [moment().subtract(6, 'days'), moment()],
                        'sidste 30 dage': [moment().subtract(29, 'days'), moment()],
                        'denne måned': [moment().startof('month'), moment().endof('month')],
                        'sidste måned': [moment().subtract(1, 'month').startof('month'), moment().subtract(1, 'month').endof('month')],
                        'dette år': [moment().startof('year'), moment().endof('year')],
                        'sidste år': [moment().subtract(1, 'year').startof('year'), moment().subtract(1, 'year').endof('year')]
                    }
                }, 
                function(start, end, label) {
                    console.log(start.format('ddmmyy') + ':' + end.format('ddmmyy'));
                    $('input[name=\"$dateelmname\"]').val(start.format('ddmmyy') + ':' + end.format('ddmmyy'));
                    $('input[name=\"$dateelmname-pkr\"]').val(start.format('ddmmyy') + ':' + end.format('ddmmyy'));

                    document.getelementsbyname('sogefelter')[0].submit.click();
                }
            );
        });
    </script>
    ";
}

?>
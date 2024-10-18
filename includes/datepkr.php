<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/datepkr.php -----patch 4.1.0 ----2024-05-28--------------
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------

function date_picker($date, $dateElmName, $formName, $justering = "right", $width = "") {
    /* Create a datepicker
     *
     * Parameters:
     * $date: A normal saldi formatted date or date range (ddmmyy:ddmmyy)
     * $dateElmName: The name of the element that the date picker will set and submit
     * $formName: The name of the form that will be automatically submitted
     * $justering: For use within tables, can set how it should be aligned
     * $width: The width of the field
     */
    

    print "<input type='text' name='$dateElmName-pkr' value='$date' style='text-align:$justering;$width'/> ";

    if ($date) {
        print "<button type=button onclick='
                document.getElementsByName(\"$dateElmName\")[0].value=\"\"; 
                document.getElementsByName(\"$dateElmName-pkr\")[0].value=\"\"; 
                document.getElementsByName(\"$formName\")[0].submit.click();
            '>x</button>";
    }

    print "
    <script>
        $(function() {
            $('input[name=\"$dateElmName-pkr\"]').daterangepicker(
                {
                    locale: {
                        format: 'DDMMYY',
                        separator: ':'
                    },
                    autoUpdateInput: false,
                    opens: 'left',
                    ranges: {
                        'Idag': [moment(), moment()],
                        'Igår': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                        'Sidste 7 dage': [moment().subtract(6, 'days'), moment()],
                        'Sidste 30 dage': [moment().subtract(29, 'days'), moment()],
                        'Denne måned': [moment().startOf('month'), moment().endOf('month')],
                        'Sidste måned': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                        'Dette år': [moment().startOf('year'), moment().endOf('year')],
                        'Sidste år': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
                    }
                }, 
                function(start, end, label) {
                    console.log(start.format('DDMMYY') + ':' + end.format('DDMMYY'));
                    $('input[name=\"$dateElmName\"]').val(start.format('DDMMYY') + ':' + end.format('DDMMYY'));
                    $('input[name=\"$dateElmName-pkr\"]').val(start.format('DDMMYY') + ':' + end.format('DDMMYY'));

                    document.getElementsByName('sogefelter')[0].submit.click();
                }
            );
        });
    </script>
    ";
}

?>
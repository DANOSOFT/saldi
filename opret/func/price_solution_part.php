<?php
// -- opret/price_solution_part.php --------------------------- 2021-12-01 --
// Copyright (c) 2003-2021 Saldi.dk ApS
// --------------------------------------------------------------------------
// 20211127 CA  Returns price for a part of the solution.
//              item_number	Number of the item (e.g. postings or users) 
//              item_price	Price per per_items number (e.g. 1000 postings)
//		item_incl	Number of items included for free
//		item_max	Maximum number of items to pay for
//		per_items	Number of items for each item_price

function price_solution_part ($item_number, $item_price, $item_incl=0, $item_max=-1, $per_items=1) {

if ( $item_number <= $item_incl ) return 0;
if ( $item_incl < 0 ) return 0;
if ( ($item_number >= $item_max) && ($item_max>-1) ) { 
        if ( (($item_max-$item_incl)%$per_items) === 0 ) return (($item_max-$item_incl)/$per_items*$item_price);
        return (ceil(($item_max-$$item_number)/$per_items)*$item_price);
}
if ( (($item_number-$item_incl)%$per_items) === 0 ) return (($item_number-$item_incl)/$per_items*$item_price);
return (ceil(($item_number-$item_incl)/$per_items)*$item_price);

}


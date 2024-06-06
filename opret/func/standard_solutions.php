<?php
// -- opret/standard_solutions.php ---------------------------- 2022-02-21 --
// Copyright (c) 2003-2022 Saldi.dk ApS
// --------------------------------------------------------------------------
// 20211201 CA  Returns parametres for standardsolution
//              Prices are per month except price_per_invoice
//              -1 indicates that this price is not a part of the solution
// 20211213 CA  Removed some dev echos and updated prices
// 20211220 CA  Added using Betalingsservice to avoid invoice fees
// 20220221 CA  Added the solution Komplet and changed values for Premium

function standard_solutions ($solution_name, $price_per_solution=0, $price_per_user=0, $users_incl=0, $users_max=-1, $price_per_1000_postings=0, $postings_incl=0, $postings_max=-1, $price_per_invoice=-1, $payservice_avoid_invoice_fee=TRUE) {

$solution_name=ucfirst(trim(strtolower($solution_name)));

# solution_name		Name of the solution
# solution		Price per month for the solution. Use 0 if nothing is included in the solution.
# user			Price per month per concurrent user. Use 0 if no limit.
# users_incl		Number of free concurrent users included in the solution. Use -1 if no limit.
# users_max		Maximum number of users which will be invoiced. Use -1 if no limit. 
# 1000_postings		Price per month per 1,000 yearly postings. Use 0 if no linit.
# postings_incl		Number of free postings included in the solution. Use -1 if no limit.
# postings_max		Maximum number of postings which will be invoiced. Use -1 if no limit. 
# invoice		Price per invoice (invoice fee). Use -1 if no invoice fee.
# paymentservice	Show that you can avoid invoice fee if you subscripe to the payment service Betalingsservice. TRUE or FALSE
switch($solution_name) { 
	case "Basis":
		$returnval=array(
			"solution_name" => $solution_name,
			"solution" => 79,
			"user" => 119,
			"users_incl" => 1,
			"users_max" => -1,
			"1000_postings" => 0,
			"postings_incl" => -1,
			"postings_max" => -1,
			"invoice" => 24,
			"paymentservice" => TRUE 
		);
		break;
	case "Premium":
		$returnval=array(
			"solution_name" => $solution_name,
			"solution" => 100,
			"user" => 100,
			"users_incl" => 1,
			"users_max" => -1,
			"1000_postings" => 0,
			"postings_incl" => -1,
			"postings_max" => -1,
			"invoice" => 24,
                        "paymentservice" => TRUE
		);
		break;
	case "Pro":
		$returnval=array(
			"solution_name" => $solution_name,
			"solution" => 359,
			"user" => 199,
			"users_incl" => 2,
			"users_max" => -1,
			"1000_postings" => 0,
			"postings_incl" => -1,
			"postings_max" => -1,
			"invoice" => 24,
                        "paymentservice" => TRUE
		);
		break;
	case "Komplet":
		$returnval=array(
			"solution_name" => $solution_name,
			"solution" => 147,
			"user" => 149,
			"users_incl" => 1,
			"users_max" => -1,
			"1000_postings" => 0,
			"postings_incl" => -1,
			"postings_max" => -1,
			"invoice" => 24,
                        "paymentservice" => TRUE
		);
		break;
	default:
		if ( $price_per_solution < 0 ) {
			$returnval=NULL;
		} else {
			$returnval=array(
				"solution_name" => $solution_name,
				"solution" => $price_per_solution,
				"user" => $price_per_user,
				"users_incl" => $users_incl,
				"users_max" => $users_max,
				"1000_postings" => $price_per_1000_postings,
				"postings_incl" => $postings_incl,
				"postings_max" => $postings_max,
				"invoice" => $price_per_invoice,
	                        "paymentservice" => $payservice_avoid_invoice_fee
			);
		}
}

return $returnval;
}

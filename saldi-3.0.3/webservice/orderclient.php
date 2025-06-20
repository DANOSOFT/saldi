<?php
# --------------- webservice/orderclient.php ----2009.10.15 --------

require_once('nusoap.php');

#############################################################################

#Create connection;

$regnskab="xxx";
$brugernavn="xxx";
$password="xxx";
$url="https://ssl2.saldi.dk/demo/webservice/webservice.php";

#$c = new soapclient($url);
$c = new nusoap_client($url);
#echo "C $c<br>";
#############################################################################

#Login

$temp=$regnskab.chr(9).$brugernavn.chr(9).$password;
$s_id = $c->call('Login', array('Login' => $temp));

echo "Session id: $s_id<br>";

#############################################################################

# Get account ID (Check if account exist);

$acc_type			= "D";
$account	= "21542327";

$string=$s_id.chr(9).$acc_type.chr(9).$account;
$account_id = $c->call('GetAccountId', array('GetAccountId' => $string));

echo "account id: $account_id<br>";

#############################################################################

 if (!$account_id) {
	$art = "D";					# Customer			 Allowed values: D (customer) or K (supplier)
#	$account = "21342327";	# Accountnumber		 Autocreated if empty
	$company = "Testfirma";# Company name		 REQUIRED
	$addr1 = "Testvej 1";	# Address 1
	$addr2 = "";						# Addresse 2	
	$zip = "1234";				# zipcode	
	$city = "Testby";			# 
	$country = "Danmark";	# 
	$contact = "Jens Testesen";	# Primary contact  
	$phone = "87654321";		# 
	$fax = "12345678";		# 
	$email = "test@test.dk";	# e-mail
	$mail_inv = "on";				# send invoice as mail	 Empty or "on"
	$web = "www.test.dk";	# 
	$vat_no = "43215678";	# 
	$group = "1";				# Debitorgruppe nr.		 REQUIRED
	$bank_name = "";			
	$bank_reg = "";				#
	$bank_account = "";
	$bank_fi = "";
	$erh = "";
	$swift = "";
	$notes = "En kommentar\nom denne kunde";
	$discount = "";
	$kreditmax = "";
	$pay_terms = "netto";		# Select either: Forud / Kontant / Efterkrav / Netto / Lb.md.
	$pay_days = "0";
	$ean = "";
	$institution = "";
#echo ("$art,$account,$company,$addr1,$addr2,$zip,$city,$country,$contact,$phone,$fax,$email,$mail_inv,$web,$vat_no,$group,$bank_name,$bank_reg,$bank_account,$bank_fi,$erh,$swift,$notes,$discount,$kreditmax,$pay_terms,$pay_days,$ean,$institution<br>");
	$account_id=create_account($art,$account,$company,$addr1,$addr2,$zip,$city,$country,$contact,$phone,$fax,$email,$mail_inv,$web,$vat_no,$group,$bank_name,$bank_reg,$bank_account,$bank_fi,$erh,$swift,$notes,$kreditmax,$pay_terms,$pay_days,$ean,$institution,$discount);
 
	echo "account id: $account_id<br>";
 }
 
#############################################################################

# Get item ID (Check if account exist);

$item	= "001";

$string=$s_id.chr(9).$item;
$item_id = $c->call('GetItemId', array('GetItemId' => $string));

echo "Item id: $item_id<br>";
 
################ create item #################### 


$varenr="001";
$beskrivelse="Eksempel på en vare";
$trademark="Volvo";
$enhed="stk";
$enhed2="";
$provisionsfri="";
$lukket="";
$notes="";
$samlevare="";
$location="Hylde4 reol3";
$forhold=1;
$salgspris="75.00";
$kostpris="50.00";
$gruppe=1;
$min_lager=5;
$max_lager=10;
$retail_price="75.00";
$special_price=0;
$tier_price=0;
$special_from_date="";
$special_to_date="";
$colli=0;
$outer_colli=0;
$open_colli_price=0;
$outer_colli_price=0;
$campaign_cost=0;

$item_id=create_item($varenr,$beskrivelse,$enhed,$enhed2,$forhold,$salgspris,$kostpris,$provisionsfri,$gruppe,$lukket,$notes,$samlevare,$min_lager,$max_lager,$trademark,$retail_price,$special_price,$tier_price,$special_from_date,$special_to_date,$colli,$outer_colli,$open_colli_price,$outer_colli_price,$campaign_cost,$location);

echo "Item id $item_id<br>";

################ create order #################### 

$art			= "DO";							# Customer order.::	Either DO (customer order), DK (creditnota), KO (supplier order) or KK (creditnota)
															#	In danish, customer is "Debitor" and suplier is "Kredditor". 
$contact	= "Jens Testesen";	# Contact at customer  
$email		= "phr@image.dk";		# e-mail
$mail_inv	= "on";							# send invoice as e-mail
$notes	= "";
$ean		= "";
$institution	= "";
$currency	= "DKK";						# The curency must exist in the Saldi
$language	= "Dansk";					# Language on invoice - must exist in Saldi
$projekt	= "0";							# Projekt ID  - must exist in Saldi if != 0
$ref		= "";									# Salesperson 
$orderdate 	= "2009-06-03";		# If empty current date is set. 
$deliverydate= "2009-06-03";	# If empty date is set at delivery
$invoicedate	= "2009-06-03";	# If empty date is set at invoice
$nextfakt	= "";								# 
$amount	= "379.76";						# Sum of order - vat not included

$account_id=30;
$order_id=create_order($account_id,$art,$contact,$email,$mail_inv,$notes,$ean,$institution,$currency,$language,$projekt,$ref,$orderdate,$deliverydate,$invoicedate,$nextfakt,$amount);

echo "Order id $order_id<br>";

if (is_numeric($order_id)) {

	$product_no	= "001";					#	 Must exist in product list. If empty line is treaded as a comment.
	$pos_nr	= "1";								#  Can be empty
	$number	= "500";								#	 Amount of products
	$description	= "";						#  If empty, description is read from stocklist	
	$price		= "0.75";						# Item price
	$discount	= "0"; 							#%	

$string = $s_id.chr(9).$order_id.chr(9).$product_no.chr(9).$pos_nr.chr(9).$number.chr(9).$description.chr(9).$price.chr(9).$discount;

$reply = $c->call('AddOrderLine', array('AddOrderLine' => $string));

echo "OrderLineId 1: $reply<br>"; 

	$product_no	= "";								# if no product no, then it is a comment,
	$pos_nr	= "2";									# 
	$number	= "1";										#
	$description	= "Kommentar";	#	

$string = $s_id.chr(9).$order_id.chr(9).$product_no.chr(9).$pos_nr.chr(9).$number.chr(9).$description.chr(9).$price.chr(9).$discount;

$reply = $c->call('AddOrderLine', array('AddOrderLine' => $string));

echo "OrderLineId 2: $reply<br>"; 

	
}
# Order to invoice ();

#$order_id="95";
$string=$s_id.chr(9).$order_id;

$svar = $c->call('OrderToInvoice', array('OrderToInvoice' => $string));

echo "Svar $svar<br>";
 
###############
# Send invoice as mail ();

$string=$s_id.chr(9).$order_id;
$svar = $c->call('SendInvoice', array('SendInvoice' => $string));

echo "Mail send $svar<br>";
 

	
$reply = $c->call('Logoff', array('s_id' => $s_id));

echo "$reply<br>";

#################################################################################		
		


		
function create_order($account_id,$art,$contact,$email,$mail_inv,$notes,$ean,$institution,$currency,$language,$projekt,$ref,$orderdate,$deliverydate,$invoicedate,$nextfakt,$amount){
	
	global $c;			
	global $s_id;			
	
	$string=$s_id.chr(9).$art.chr(9).$account_id.chr(9).$contact.chr(9).$email.chr(9).$mail_inv.chr(9).$notes.chr(9).$ean.chr(9).$institution.chr(9).$currency.chr(9).$language.chr(9).$projekt.chr(9).$ref.chr(9).$orderdate.chr(9).$deliverydate.chr(9).$invoicedate.chr(9).$nextfakt.chr(9).$amount;
	$reply = $c->call('CreateOrder', array('CreateOrder' => $string));

	return ($reply);
}

function create_account($art,$account,$company,$addr1,$addr2,$zip,$city,$country,$contact,$phone,$fax,$email,$mail_inv,$web,$vat_no,$group,$bank_name,$bank_reg,$bank_account,$bank_fi,$erh,$swift,$notes,$discount,$kreditmax,$pay_terms,$pay_days,$ean,$institution) {
	global $c;			
	global $s_id;			
	
	$string=$s_id.chr(9).$art.chr(9).$account.chr(9).$company.chr(9).$addr1.chr(9).$addr2.chr(9).$zip.chr(9).$city.chr(9).$country.chr(9).$contact.chr(9).$phone.chr(9).$fax.chr(9).$email.chr(9).$mail_inv.chr(9).$web.chr(9).$vat_no.chr(9).$group.chr(9).$bank_name.chr(9).$bank_reg.chr(9).$bank_account.chr(9).$bank_fi.chr(9).$erh.chr(9).$swift.chr(9).$notes.chr(9).$discount.chr(9).$kreditmax.chr(9).$pay_terms.chr(9).$pay_days.chr(9).$ean.chr(9).$institution;
	$reply = $c->call('CreateAccount', array('CreateAccount' => $string));
	return ($reply);
}		

function create_item($varenr,$beskrivelse,$enhed,$enhed2,$forhold,$salgspris,$kostpris,$provisionsfri,$gruppe,$lukket,$notes,$samlevare,$min_lager,$max_lager,$trademark,$retail_price,$special_price,$tier_price,$special_from_date,$special_to_date,$colli,$outer_colli,$open_colli_price,$outer_colli_price,$campaign_cost,$location) {
	global $c;			
	global $s_id;			
	
	$string=$s_id.chr(9).$varenr.chr(9).$beskrivelse.chr(9).$enhed.chr(9).$enhed2.chr(9).$forhold.chr(9).$salgspris.chr(9).$kostpris.chr(9).$provisionsfri.chr(9).$gruppe.chr(9).$lukket.chr(9).$notes.chr(9).$samlevare.chr(9).$min_lager.chr(9).$max_lager.chr(9).$trademark.chr(9).$retail_price.chr(9).$special_price.chr(9).$tier_price.chr(9).$special_from_date.chr(9).$special_to_date.chr(9).$colli.chr(9).$outer_colli.chr(9).$open_colli_price.chr(9).$outer_colli_price.chr(9).$campaign_cost.chr(9).$location;
	$reply = $c->call('CreateItem', array('CreateItem' => $string));
	return ($reply);
}		
###################################################################################

?>

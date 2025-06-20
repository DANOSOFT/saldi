<?php
// #----------------- saldi_update.php -----ver 3.2.6---- 2012.01.10 ----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2012 DANOSOFT ApS
// ----------------------------------------------------------------------


if (!function_exists('saldi_update')) {
	function saldi_update($insert_id) {
	global $url;

		$debitorgruppe=1; #Den debitorgruppe som webdebitorer tilhører.

		if (!$insert_id) return('missing insert_id');
		$q=mysql_query("select * from orders where orders_id = '$insert_id'");
		if ($r=mysql_fetch_array($q)) {
			$customers_id=$r['customers_id'];
			$customers_name=$r['customers_name'];
			$customers_company=$r['customers_company'];
			$customers_street_address=$r['customers_street_address'];
			$customers_city=$r['customers_city'];
			$customers_postcode=$r['customers_postcode'];
			$customers_country=$r['customers_country'];
			$customers_telephone=$r['customers_telephone'];
			$customers_email_address=$r['customers_email_address'];
			$delivery_name=$r['delivery_name'];
			$delivery_company=$r['delivery_company'];
			$delivery_street_address=$r['delivery_street_address'];
			$delivery_city=$r['delivery_city'];
			$delivery_postcode=$r['delivery_postcode'];
			$delivery_country=$r['delivery_country'];
			$billing_name=$r['billing_name'];
			$billing_company=$r['billing_company'];
			$billing_street_address=$r['billing_street_address'];
			$billing_city=$r['billing_city'];
			$billing_postcode=$r['billing_postcode'];
			$billing_country=$r['billing_country'];
			$date_purchased=$r['date_purchased'];
			$payment_method=$r['payment_method'];
			$currency=$r['currency'];
			$currency_value=$r['currency_value'];
		}	else return('order not found');
		$x=0;
		$q=mysql_query("select * from orders_products where orders_id = '$insert_id'");
		while ($r=mysql_fetch_array($q)) {
			$x++;
			$orders_products_id[$x]=$r['orders_products_id'];
			$products_id[$x]=$r['products_id'];
			$products_model[$x]=$r['products_model'];
			$products_name[$x]=$r['products_name'];
			$products_price[$x]=$r['products_price'];
			$products_tax[$x]=$r['products_tax'];
			$products_quantity[$x]=$r['products_quantity'];
			$stregkode[$x]=$products_model[$x];
		}
		$item_quantity=$x;
		if (!$item_quantity) return('no items in order');

		if (file_exists("soapklient/soapfunc.php")) $filnavn="soapklient/soapfunc.log";
		else $filnavn="soapfunc.log";
		$fp=fopen($filnavn,'a');
		fwrite($fp,"------------".date("Y-m-d H:i:s")."------------\n");

		#		medshop hack.
		if ($billing_country=='Denmark') $debitorgruppe="1";
		elseif ($billing_country=='Norway') $debitorgruppe="3";
		elseif ($billing_company) $debitorgruppe="4";
		else $debitorgruppe="2";

		for ($x=1;$x<=$item_quantity;$x++) {
			$y=0;
			$select="products_options.products_options_id as products_options_id,products_options_values.products_options_values_id as products_options_values_id";
			$from="orders_products_attributes,products_options,products_options_values";
			$where="orders_products_attributes.orders_id = '$insert_id' and orders_products_attributes.orders_products_id='$orders_products_id[$x]' ";
			$where.="and products_options.products_options_name=orders_products_attributes.products_options and ";
			$where.="products_options_values.products_options_values_name=orders_products_attributes.products_options_values";
			fwrite($fp,__LINE__." - SELECT $select FROM $from WHERE $where\n");
			$q=mysql_query("SELECT $select FROM $from WHERE $where");
			while ($r=mysql_fetch_array($q)) {
				$y++;
				$products_options_id[$x][$y]=$r['products_options_id'];
				$products_options_values_id[$x][$y]=$r['products_options_values_id'];
				fwrite($fp,__LINE__.$products_options_id[$x][$y]." -:-".$products_options_values_id[$x][$y]."\n");
			}
		}
		if (file_exists("soapklient/soapfunc.php")) include("soapklient/soapfunc.php");
		else include("soapfunc.php");
		if (file_exists("soapklient/saldi_connect.php")) include("soapklient/saldi_connect.php");
		else include("saldi_connect.php");
		fwrite($fp,__LINE__."$url logon($regnskab,$brugernavn,$adgangskode)\n");
		list($fejl,$svar)=explode(chr(9),logon($regnskab,$brugernavn,$adgangskode));
		fwrite($fp,__LINE__."fejl $fejl - svar $svar\n");
		$fejl*=1;
		if ($fejl) return ($fejl.chr(9).$svar);
		else $s_id=$svar;
		fwrite($fp,__LINE__." - saldi_id from shop_adresser where shop_id='$customers_id'\n");
		list($fejl,$svar)=explode(chr(9),singleselect($s_id,"saldi_id from shop_adresser where shop_id='$customers_id'"));
		fwrite($fp,__LINE__." - svar $svar\n");
		if ($fejl) return ($svar);
		elseif ($svar) {
			$konto_id=$svar;
			fwrite($fp,__LINE__." - kontonr from adresser where id='$konto_id'\n");
			list($fejl,$svar)=explode(chr(9),singleselect($s_id,"kontonr from adresser where id='$konto_id'"));
			fwrite($fp,__LINE__." - svar $svar\n");
			if ($fejl) return ($svar);
			else $kontonr=$svar;

		} else {
			if ($customers_company) {
				$kontotype='Erhverv';
				$fornavn=NULL;
				$efternavn=NULL;
				$kontakt=$customers_name;
			} else {
				$kontotype='Privat';
				$customers_company=$customers_name;
				$delivery_company=$delivery_name;
				$billing_company=$billing_name;

				list($fornavn,$tmp)=explode(" ",$customers_name);
				$efternavn=trim(substr($customers_name,strlen($fornavn)));
				$kontakt='';
			}
			fwrite($fp,__LINE__." - id,kontonr,firmanavn,addr1 from adresser where art='D' and tlf='$customers_telephone'\n");
			# tjekker om der er en kunde i saldi med samme telefonnummer.
			list($fejl,$svar)=explode(chr(9),singleselect($s_id,"id,kontonr,firmanavn,addr1 from adresser where art='D' and tlf='$customers_telephone'"));
			fwrite($fp,__LINE__." - svar $svar\n");
			if ($fejl) return ($svar);
			else list($konto_id,$kontonr,$firmanavn,$addr1)=explode(',',$svar);
			if ($kontonr && strtolower($customers_company)==strtolower($firmanavn) && strtolower($customers_street_address)==strtolower($addr1)) {
				return("0".chr(9).$id);
			} elseif ($customers_telephone) {
				fwrite($fp,__LINE__." - id,kontonr,firmanavn,addr1 from adresser where art='D' and kontonr='$customers_telephone'\n");
				# tjekker om der er en kunde i saldi med samme kontonummer som kundens telefonnummer.
				list($fejl,$svar)=explode(chr(9),singleselect($s_id,"id,kontonr,firmanavn,addr1 from adresser where art='D' and kontonr='$customers_telephone'"));
				fwrite($fp,__LINE__." - svar $fejl - $svar\n");
				if ($fejl) return ($svar);
				else list($konto_id,$kontonr,$firmanavn,$addr1)=explode(',',$svar);
#				if ($kontonr && strtolower($customers_company)==strtolower($firmanavn) && strtolower($customers_street_address)==strtolower($addr1)) {
#				return("0".chr(9).$id);
			} elseif (!$customers_telephone) {
				fwrite($fp,__LINE__." - max(kontonr) as kontonr from adresser where art='D'\n");
				list($fejl,$svar)=explode(chr(9),singleselect($s_id,"max(kontonr) as kontonr from adresser where art='D'"));
				fwrite($fp,__LINE__." - svar $svar\n");
				if ($fejl) return ('1'.chr(9).$svar);
				else $kontonr=$svar+1;
			} 
			if (!$kontonr) $kontonr=str_replace("+","",$customers_telephone);
			fwrite($fp,__LINE__."- $kontonr=".str_replace("z","",$customers_telephone)." - \n");
			$kontonr=str_replace(" ","",$kontonr);
			fwrite($fp,__LINE__."- $kontonr - \n");
			$kontonr*=1;
			fwrite($fp,__LINE__." -$kontonr - \n");
			if (!$konto_id) { 
				fwrite($fp,__LINE__." - adresser (kontonr,firmanavn,fornavn,efternavn,kontakt,addr1,bynavn,postnr,land,tlf,email,lev_kontakt,lev_firmanavn,lev_addr1,lev_bynavn,lev_postnr,lev_land,lev_tlf,art,kontotype,gruppe) values ('$kontonr','$customers_company','$fornavn','$efternavn','$kontakt','$customers_street_address','$customers_city','$customers_postcode','$customers_country','$customers_telephone','$customers_email_address','$delivery_name','$delivery_company','$delivery_street_address','$delivery_city','$delivery_postcode','$delivery_country','$delivery_telephone','D','$kontotype','$debitorgruppe')\n");
				list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"adresser (kontonr,firmanavn,fornavn,efternavn,kontakt,addr1,bynavn,postnr,land,tlf,email,lev_kontakt,lev_firmanavn,lev_addr1,lev_bynavn,lev_postnr,lev_land,lev_tlf,art,kontotype,gruppe) values ('$kontonr','$customers_company','$fornavn','$efternavn','$kontakt','$customers_street_address','$customers_city','$customers_postcode','$customers_country','$customers_telephone','$customers_email_address','$delivery_name','$delivery_company','$delivery_street_address','$delivery_city','$delivery_postcode','$delivery_country','$delivery_telephone','D','$kontotype','$debitorgruppe')"));
				fwrite($fp,__LINE__." - svar $svar\n");
				if ($fejl) return ($fejl.chr(9).$svar);
				else $konto_id=$svar;
				fwrite($fp,__LINE__." - shop_adresser (shop_id,saldi_id) values ('$customers_id','$konto_id')\n");
				list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"shop_adresser (shop_id,saldi_id) values ('$customers_id','$konto_id')"));
				fwrite($fp,__LINE__." - svar $svar\n");
				if ($fejl) return('1'.chr(9).$svar);
				if ($kontotype=='Erhverv') { 
					fwrite($fp,__LINE__." - ansatte (navn,konto_id) values ('$customers_name','$konto_id')\n");
					list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"ansatte (navn,konto_id) values ('$customers_name','$konto_id')"));
					fwrite($fp,__LINE__." - svar $svar\n");
					if ($fejl) return ($fejl.chr(9).$svar);
				}
			}
		}
		fwrite($fp,__LINE__." - saldi_id from shop_ordrer where shop_id='$insert_id'\n");
		list($fejl,$svar)=explode(chr(9),singleselect($s_id,"saldi_id from shop_ordrer where shop_id='$insert_id'"));
		fwrite($fp,__LINE__." - svar $svar\n");
		if ($fejl) return ($svar);
		elseif ($svar) $ordre_id=$svar;
		else {
			fwrite($fp,__LINE__." - max(ordrenr) as ordrenr from ordrer where art='DO'\n");
			list($fejl,$svar)=explode(chr(9),singleselect($s_id,"max(ordrenr) as ordrenr from ordrer where art='DO'"));
			fwrite($fp,__LINE__." - fejl $fejl, svar $svar\n");
			if ($fejl) return ($fejl.chr(9).$svar);
			else $ordrenr=$svar+1;
			$ordredate=date("Y-m-d");
			if(!$billing_company){
				$billing_company=$billing_name;
				$billing_name='';
			}
			if(!$delivery_company){
				$delivery_company=$delivery_name;
				$delivery_name='';
			}
#cho "ordrer (konto_id,kontonr,firmanavn,kontakt,addr1,bynavn,postnr,land,email,lev_kontakt,lev_navn,lev_addr1,lev_bynavn,lev_postnr,art) values ('$konto_id','$kontonr','$billing_company','$billing_name','$billing_street_address','$billing_city','$billing_postcode','$billing_country','$customers_email_address','$delivery_name','$delivery_company','$delivery_street_address','$delivery_city','$delivery_postcode','DO')";
			list($fejl,$svar)=explode(chr(9),singleselect($s_id,"box2 as momssats from grupper where art='SM' and kodenr='$debitorgruppe'"));
			fwrite($fp,__LINE__." - fejl $fejl, svar $svar\n");
			if ($fejl) return ($fejl.chr(9).$svar);
			else $momssats=$svar*1;
			fwrite($fp,__LINE__." - ordrer (ordrenr,konto_id,kontonr,firmanavn,kontakt,addr1,bynavn,postnr,land,email,lev_kontakt,lev_navn,lev_addr1,lev_bynavn,lev_postnr,art,momssats,kundeordnr) values ('$ordrenr','$ordredate','$konto_id','$kontonr','$billing_company','$billing_name','$billing_street_address','$billing_city','$billing_postcode','$billing_country','$customers_email_address','$delivery_name','$delivery_company','$delivery_street_address','$delivery_city','$delivery_postcode','DO','$momssats','$insert_id')\n");
			list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"ordrer (ordrenr,ordredate,konto_id,kontonr,firmanavn,kontakt,addr1,bynavn,postnr,land,email,lev_kontakt,lev_navn,lev_addr1,lev_bynavn,lev_postnr,art,status,momssats,kundeordnr) values ('$ordrenr','$ordredate','$konto_id','$kontonr','$billing_company','$billing_name','$billing_street_address','$billing_city','$billing_postcode','$billing_country','$customers_email_address','$delivery_name','$delivery_company','$delivery_street_address','$delivery_city','$delivery_postcode','DO','2','$momssats','$insert_id')"));
			fwrite($fp,__LINE__." - svar $svar\n");
			if ($fejl) return ($fejl.chr(9).$svar);
			else $ordre_id=$svar;
			fwrite($fp,__LINE__." - shop_ordrer (shop_id,saldi_id) values ('$insert_id','$ordre_id')\n");
			list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"shop_ordrer (shop_id,saldi_id) values ('$insert_id','$ordre_id')"));
			fwrite($fp,__LINE__." - svar $svar\n");
			if ($fejl) return('1'.chr(9).$svar);
		}
		# Tilføjer ordrelinjer
		for ($x=1;$x<=$item_quantity;$x++) {
			# Tjekker om varerelation eksisterer i Saldi
			fwrite($fp,__LINE__." - saldi_id from shop_varer where shop_id='$products_id[$x]'\n");
			list($fejl,$svar)=explode(chr(9),singleselect($s_id,"saldi_id from shop_varer where shop_id='$products_id[$x]'"));
			fwrite($fp,"svar $svar\n");
			if ($fejl) return ($svar);
			elseif ($svar) $vare_id[$x]=$svar;
			else {
				# Tjekker om varenummeret eksisterer i Saldi
				fwrite($fp,__LINE__." - id from varer where varenr='$products_model[$x]'\n");
				list($fejl,$svar)=explode(chr(9),singleselect($s_id,"id from varer where varenr='$products_model[$x]'"));
				fwrite($fp,__LINE__." - svar $svar\n");
				if ($fejl) return ($svar);
				elseif ($svar) $vare_id[$x]=$svar;
				else {
					#Hvis varen ikke eksisterer i Saldi oprettes den.
					fwrite($fp,__LINE__." - varer (varenr,beskrivelse,salgspris,gruppe) values ('$products_model[$x]','$products_name[$x]','$products_price[$x]','1')\n");
					list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"varer (varenr,beskrivelse,salgspris,gruppe) values ('$products_model[$x]','$products_name[$x]','$products_price[$x]','1')"));
					fwrite($fp,__LINE__." - svar $svar\n");
					if ($fejl) return ('1'.chr(9).$svar);
					else $vare_id=$svar;
				}
				# Og der oprettes relation mellem Saldi & osc vare ID
				fwrite($fp,__LINE__." - shop_varer (shop_id,saldi_id) values ('$products_id[$x]','$ordre_id')\n");
				list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"shop_varer (shop_id,saldi_id) values ('$products_id[$x]','$ordre_id')"));
				fwrite($fp,__LINE__." - svar $svar\n");
				if ($fejl) return('1'.chr(9).$svar);
			}
			fwrite($fp,__LINE__." - Tjekker om varen indeholder varianter\n");
			#Tjekker om varen indeholder varianter.
			if (count($products_options_values_id[$x])) {
				$tmp=count($products_options_values_id[$x]);
				for ($y=1;$y<=$tmp;$y++) {
				fwrite($fp,__LINE__." - products_options_values_id: ".$products_options_values_id[$x][$y]."\n");
					if ($products_options_values_id[$x][$y]) {
						fwrite($fp,__LINE__." - id from variant_typer where shop_id='".$products_options_values_id[$x][$y]."'\n");
						list($fejl,$svar)=explode(chr(9),singleselect($s_id,"id from variant_typer where shop_id='".$products_options_values_id[$x][$y]."'"));
						fwrite($fp,__LINE__." - svar $svar\n");
						if ($fejl) return('1'.chr(9).$svar);
						else $variant_type_id[$x][$y]=$svar;
					}
				}
				sort($variant_type_id[$x]);
				$tmp=count($variant_type_id[$x]);
				$variant[$x]=NULL;
				for ($y=0;$y<$tmp;$y++) {
					($variant[$x])?$variant[$x].=chr(9).$variant_type_id[$x][$y]:$variant[$x]=$variant_type_id[$x][$y];
				}
				fwrite($fp,__LINE__." - variant_stregkode from variant_varer where variant_type='$variant[$x]'\n");

				list($fejl,$svar)=explode(chr(9),singleselect($s_id,"variant_stregkode from variant_varer where variant_type='$variant[$x]'"),2);
				fwrite($fp,__LINE__." - stregkode $svar\n");
				if ($fejl) return('1'.chr(9).$svar);
				else $stregkode[$x]=$svar;
			}
			# Ordrelinjen operettes.
			fwrite($fp,__LINE__." - \"$ordre_id\",\"$stregkode[$x]\",\"$products_name[$x]\",\"$products_quantity[$x]\",\"$products_price[$x]\",\"$products_tax[$x]\",\"$x\"\n");
			list($fejl,$svar)=explode(chr(9),addorderline("$s_id","$ordre_id","$stregkode[$x]","$products_name[$x]","$products_quantity[$x]","$products_price[$x]","$products_tax[$x]","$x"));
			fwrite($fp,__LINE__." - svar $svar\n");
			if ($fejl) return('1'.chr(9).$svar);
		}
		return('0'.chr(9).$konto_id.chr(9).$ordre_id);
		fclose($fp);
	}
}
?>
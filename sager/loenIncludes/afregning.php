<?php
function loenafregning() {#Summeret lønafregning

	global $ansat_id;
	global $bgcolor,$brugernavn;
	global $charset;
	global $db;
	global $overtid_50pct,$overtid_100pct;
	global $sag_rettigheder,$sprog_id;

	$funcstarttime = microtime(true);

	fod_log('loenafregning:' . "\n");

#cho "ansat_id $ansat_id<br>";
	$afregnet = NULL;
	$predatoer = NULL;
	
	$bgcolor2="#ffffff";
	$linjebg = $bgcolor2;

	$vis = if_isset($_GET['vis']);

	$afregn = if_isset($_POST['afregn']);
	if ($afregn)
	{
		$afregnet = if_isset($_POST['afregnet']);
		if ($afregnet) {
			$afregnet = usdate($afregnet);
			$afregnet = strtotime($afregnet);
		} else {
			$afreg = NULL;
		}
	}

	$periode = if_isset($_POST['periode']);
	if (!$periode) $periode = if_isset($_GET['periode']);

	$ansatvalg = if_isset($_GET['ansat_id']);

	$alle_ansatte_id = if_isset($_POST['alle_ansatte_id']);
	if (!$alle_ansatte_id) $alle_ansatte_id = if_isset($_GET['alle_ansatte_id']);

	$visalle = if_isset($_POST['visalle']);
	if (!$visalle) $visalle = if_isset($_GET['visalle']);

	$refresh = if_isset($_GET['refresh']);
	//if (!$refresh) $refresh=='on';
	//cho "refresh: $refresh<br>";
	if($alle_ansatte_id) {
		$alleid = explode(',',$alle_ansatte_id);
		$alleAnsatte_id = array_values(array_unique($alleid));
	} else {
		$alleAnsatte_id = array();
	}

	$alleAnsatte_id_total = count($alleAnsatte_id);
	
	if (!substr($sag_rettigheder,6,1))
		$ansatvalg = $ansat_id;

	$x = 0;
	$r = db_fetch_array(db_select("select box4 from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
	$p_start[$x] = strtotime($r['box4']);
	while ($p_start[$x] <= date("U")+1209600) {
		$x++;
		$p_start[$x] = $p_start[$x-1]+1209600;
	}

	if (!$periode) $periode = $p_start[$x-2];

	$startdate = date("Y-m-d",$periode);
/*
	if ($db == 'stillads_5' && $startdate == '2021-01-18') {
		$slutdate = date("Y-m-d",$periode+1209600/2);
		$tmp = $periode;
		for ($d = 0;$d<7;$d++) {
			$datoliste[$d] = date("Y-m-d",$tmp);
			$tmp += 86400;
		}
	} else {
*/
	$slutdate = date("Y-m-d",$periode+1209600);
	$tmp = $periode;
	for ($d = 0;$d<14;$d++) {
		$datoliste[$d] = date("Y-m-d",$tmp);
		$tmp += 86400;
	}

	fod_log("startdate: " . $startdate . "\n");

	if ($afregn) {
		# 20130604 tilføjet: and afvist<'1'
		$qtxt = "UPDATE loen set afregnet='$afregnet',afregnet_af='$brugernavn' WHERE (art='akk_afr' or art='akktimer' or art='akkord' or art='timer' or art='plads' or art='sygdom' or art='barn_syg' or art='skole') and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL) and (afregnet='' or afregnet is NULL)";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

	$foddata = fod_indsaml_data($periode);
	$foddage = $foddata->dage;
	
	if ($visalle=='on') {#20141003
		print "<div id=\"printableArea\">\n";

		$key = 0;
		foreach ($alleAnsatte_id as $ansatvalg) {// loop af ansatte
			$key++;

			$datoliste = NULL;
			
			if ($ansatvalg) {
				$qtxt = "SELECT * FROM ansatte WHERE id = '$ansatvalg'";
			} else {
				$qtxt = "SELECT * FROM ansatte ORDER BY navn";
			}

			$fodansatinfos = array();

			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$fodansatinfo = new stdClass();
				$fodansatinfo->id = $r['id'];
				$fodansatinfo->navn = $r['navn'];
				array_push($fodansatinfos, $fodansatinfo);
			}
			
			$x = 0;
			$y = 0;
			$pre_d = array();
			$post_d = array();
			# 20130604 tilføjet: and afvist<'1'
			$qtxt = "SELECT * FROM loen WHERE ";
			$qtxt.=" art='akk_afr' and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";
			$qtxt.=" order by loendate";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$ad = array();
				$datoer[$x] = $r['datoer'];
				$ad = explode(chr(9),$datoer[$x]);
				for ($d = 0;$d<count($ad);$d++) {
					if ($ad[$d] && $ad[$d]<$startdate && (!in_array($ad[$d],$pre_d))) {
						$pre_d[$x] = $ad[$d];
						$x++;
					}
				}
				for ($d = 0;$d<count($ad);$d++) {
					if ($ad[$d] && $ad[$d]>=$slutdate && (!in_array($ad[$d],$post_d))) {
						$post_d[$y] = $ad[$d];
#cho "PO $post_d[$y]<br>";
						$y++;
					}
				}
			}
			sort($pre_d);
			sort($post_d);
			for ($d = 0;$d<count($pre_d);$d++) {
				$predatoer.=$pre_d[$d];
				$datoliste[$d] = $pre_d[$d];
#cho "D1 $datoliste[$d]<br>";
			}
			$tmp = $periode;
			for ($d = count($pre_d);$d<14+count($pre_d);$d++) {
				$datoliste[$d] = date("Y-m-d",$tmp);
#cho "D2 $datoliste[$d]<br>";
				$tmp += 86400;
			}
			
			$a = count($datoliste);
			$b = count($datoliste)+count($post_d);
			for ($d = $a;$d<$b;$d++) {
				$postdatoer.=$post_d[$d-$a];
				$datoliste[$d] = $post_d[$d-$a];
#cho "D3 $datoliste[$d]<br>";
			}
			
			$datoantal = count($datoliste);
			$x = 0;

			print "<div class=\"content\">\n";
				//print "<form name=\"loenafregning\" action=\"loen.php?funktion=loenafregning&amp;periode=$periode&amp;alle_ansatte_id=$alle_ansatte_id&amp;visalle=$visalle\" method=\"post\">\n";
					print "<div style=\"float:left; width:828px;#background-color:lightgreen;\">\n";
					//print "<div id=\"printableArea\">\n";
						$tmp = "<h2 class=\"printHeadline\">Lønopgørelse</h2>\n";
						if ($afregnet) $tmp.="<p class=\"printHeadline\">afregnet d. ".date("d-m-Y",$afregnet)." af $afregnet_af</p>\n";
						print "$tmp\n";
								print "<table width=\"100%\" border=\"0\" class=\"loenafregning\"><tbody>\n";
								print "<tr><td colspan=\"14\"><b>Periode&nbsp;&nbsp;</b>".date("d-m-Y",$periode)."&nbsp;&ndash;&nbsp;".date("d-m-Y",$periode+1209600-86400)."</td></tr>\n";
								/*print "<tr><td colspan=\"14\"><b>Periode&nbsp;&nbsp;</b><SELECT NAME=\"periode\" class=\"printSelect\" onchange=\"this.form.submit()\" >\n";
								for ($x = count($p_start)-3;$x>=0;$x--){
									if ($periode==$p_start[$x]) print "<OPTION value=\"$p_start[$x]\">".date("d-m-Y",$p_start[$x])."&nbsp;&ndash;&nbsp;".date("d-m-Y",$p_start[$x+1]-86400)."</option>\n";
								}
								for ($x = count($p_start)-3;$x>=0;$x--){
									if ($periode!=$p_start[$x]) print "<OPTION value=\"$p_start[$x]\">".date("d-m-Y",$p_start[$x])."&nbsp;&ndash;&nbsp;".date("d-m-Y",$p_start[$x+1]-86400)."</option>\n";
								}
								print "</SELECT></td></tr>\n";*/
								if ($vis=="belob") {
									print "<tr><td  colspan=\"14\"><b>Lønafregning beløb</b></td></tr>\n";
								} else {
									print "<tr><td  colspan=\"14\"><b>Lønafregning timer</b></td></tr>\n";
								}

								$vist = 0;

								$fodtotal = new stdClass();
								fod_felter_nulstil($fodtotal);

								for($d = 0;$d<$datoantal;$d++) {
									$foddagid = $datoliste[$d];
									$foddag = isset($foddage[$foddagid])?$foddage[$foddagid]:null;

									if ($foddag && $foddag->ansat_sum) {
										$foddagtotal = new stdClass();
										fod_felter_nulstil($foddagtotal);

										if (count($fodansatinfos)>1 || !$vist) {
											if (count($fodansatinfos)==1) {
												print "<tr><td colspan=\"15\"><hr class=\"printHr\"></td></tr><tr><td colspan=\"15\">\n";
											} else {
												print "<tr><td colspan=\"14\"><hr class=\"printHr\"></td></tr><tr><td colspan=\"14\">\n";
											}

											if (count($fodansatinfos)==1) {
												print "<h3 class=\"printHeadline\">" . $fodansatinfos[0]->navn . "</h3>\n";
												$vist = 1;
											} else {
												print "<b>".dkdato($datoliste[$d])."</b>\n";
											}
											print "</td></tr>
											<tr bgcolor=\"$linjebg\"><td><b>\n";
											(count($fodansatinfos)==1)?print "Dato":print "Navn";
										
											print "</b></td>";
												if (count($fodansatinfos)==1) print "<td align=\"center\"><b>Sted</b></td>";
												print "<td align=\"center\"><b>Timer</b></td>
												<td align=\"center\"><b>Dyrtid</b></td>
												<td align=\"center\"><b>50%</b></td>
												<td align=\"center\"><b>100%</b></td>
												<td align=\"center\"><b>Akkord</b></td>";
/*if ($vis=="belob")*/ print "<td align=\"center\"><b>Kørsel</b></td>"; # udkommenteret 20142503
												print "<td align=\"center\"><b>Skur&nbsp;1</b></td>
												<td align=\"center\"><b>Skur&nbsp;2</b></td>
												<td align=\"center\"><b>Mentor</b></td>
												<td align=\"center\"><b>Plads</b></td>
												<td align=\"center\"><b>Sygdom</b></td>
												<td align=\"center\"><b>Barn&nbsp;syg</b></td>
												<td align=\"center\"><b>Skole</b></td>
												<td align=\"center\"><b>I&nbsp;alt</b></td>
											</tr>";
										}
										for ($y = 0;$y<count($fodansatinfos);$y++) {
											$fodansatid = $fodansatinfos[$y]->id;
											$fodansat = isset($foddag->ansatte[$fodansatid])?$foddag->ansatte[$fodansatid]:null;

											if ($fodansat && $fodansat->ansat_sum) {
												fod_felter_laeg_til($foddagtotal, $fodansat);

												foreach($fodansat->sager as $fodsag) {
													if (0) {
														foreach($fodsag->opgaver as $fodopgave) {
															($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
															print "<tr bgcolor=\"$linjebg\">";

															fod_felter_vis_dato($foddagid);
															fod_felter_vis_sted($fodopgave, false);

															if ($vis=="belob") {
																fod_felter_vis_beloeb($fodopgave, false, false);
															} else {
																fod_felter_vis_timer($fodopgave, false, false);
															}
														}
													} else {
														($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
														print "<tr bgcolor=\"$linjebg\">";

														fod_felter_vis_dato($foddagid);
														fod_felter_vis_sted($fodsag, false);

														if ($vis=="belob") {
															fod_felter_vis_beloeb($fodsag, false, false);
														} else {
															fod_felter_vis_timer($fodsag, false, false);
														}

														print "</tr>";
													}
												}
											}
										}

										fod_felter_laeg_til($fodtotal, $foddagtotal);
									}
								}
								
								if (count($fodansatinfos)==1) {
									print "<tr><td colspan=\"15\"><hr class=\"printHr\"></td></tr>";
								} else {
									print "<tr><td colspan=\"14\"><hr class=\"printHr\"></td></tr>";
								}

								($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
								print "<tr bgcolor=\"$linjebg\">";
								fod_felter_vis_titel('I alt', true);
								if ($vis=="belob") {
									if (count($fodansatinfos)==1)
										print "<td>&nbsp;</td>";

									fod_felter_vis_beloeb($fodtotal, true, true);
								} else {
									if (count($fodansatinfos)==1) print "<td>&nbsp;</td>";
									fod_felter_vis_timer($fodtotal, true, true);
								}
								print "</tr>
								<tr><td colspan=\"14\"><br></td></tr>";
								/*
								print "<tr class=\"printDisplayNone\"><td colspan=\"7\">
									<input type=submit style=\"width:80px\" value=\"Opdat&eacute;r\" class=\"button gray medium\" name=\"submitForm\" onclick=\"javascript:docChange = false;\">";
								print "<td colspan=\"7\" align=\"right\">";
								if (substr($sag_rettigheder,6,1) && !$afregnet && !$ansatvalg)
								{
									$afregnet = date("d-m-Y");
									print "
										<input type=\"text\" style=\"width:80px;\" value=\"$afregnet\" name=\"afregnet\">
										<input type=\"submit\" style=\"width:80px;\" value=\"Afregn\" class=\"button gray medium\" name=\"afregn\" onclick=\"javascript:docChange = false;\"></td>
										</tr>\n";
								}*/
								print "</tbody></table>\n";		
#					print "</div><!-- end of contentA -->\n";
							//print "</div><!-- end of printableArea -->\n";
							
							//print "<a href=\"loen.php?funktion=loenafregning&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode&amp;visalle=on\">vis alle&nbsp;</a>";
						print "</div><!-- end of full container -->\n";
					print "<div class=\"clear\"></div>\n";
				//print "</form>\n";
			print "</div>\n";
			
			if ($key<$alleAnsatte_id_total) {
				print "<div class=\"page-break\"><br /></div>\n";
			}
		} // loop af ansatte omkring hele funktionen
		print "</div><!-- end of printableArea -->\n";
	
	} else {
		//cho "else";
		$alle_ansatte_id =  NULL;
		if ($ansatvalg) {
			$qtxt = "SELECT * FROM ansatte WHERE id = '$ansatvalg'";
		} else {
			$qtxt = "SELECT * FROM ansatte ORDER BY navn";
		}

		$fodansatinfos = array();

		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$fodansatinfo = new stdClass();
			$fodansatinfo->id = $r['id'];
			$fodansatinfo->navn = $r['navn'];
			array_push($fodansatinfos, $fodansatinfo);
		}
		
		$x = 0;
		$y = 0;
		$pre_d = array();
		$post_d = array();
		# 20130604 tilføjet: and afvist<'1'
		$qtxt = "SELECT * FROM loen";
		$qtxt.=" WHERE art='akk_afr' and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";

		if (count($fodansatinfos)==1)
			$qtxt.=" and ansatte like '%" . $fodansatinfos[0]->id . "%'";

		$qtxt.=" order by loendate";
		#cho "$qtxt<br>";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ad = array();
			$datoer[$x] = $r['datoer'];
			$ad = explode(chr(9),$datoer[$x]);
			for ($d = 0;$d<count($ad);$d++) {
				if ($ad[$d] && $ad[$d]<$startdate && (!in_array($ad[$d],$pre_d))) {
					$pre_d[$x] = $ad[$d];
					$x++;
				}
			}
			for ($d = 0;$d<count($ad);$d++) {
				if ($ad[$d] && $ad[$d]>=$slutdate && (!in_array($ad[$d],$post_d))) {
					$post_d[$y] = $ad[$d];
					$y++;
				}
			}
		}
		sort($pre_d);
		sort($post_d);
		for ($d = 0;$d<count($pre_d);$d++) {
			$datoliste[$d] = $pre_d[$d];
#cho "D1 $datoliste[$d]<br>";
		}
		$tmp = $periode;
		for ($d = count($pre_d);$d<14+count($pre_d);$d++) {
			$datoliste[$d] = date("Y-m-d",$tmp);
#cho "D2 $datoliste[$d]<br>";
			$tmp += 86400;
		}
		
		$a = count($datoliste);
		$b = count($datoliste)+count($post_d);
		for ($d = $a;$d<$b;$d++) {
			$datoliste[$d] = $post_d[$d-$a];
		}

		$datoantal = count($datoliste);

		# 20130228 tilføjet: or art='akktimer'
		# 20130604 tilføjet: and afvist<'1'

	print "<div class=\"content\">\n";
		print "<form name=\"loenafregning\" action=\"loen.php?funktion=loenafregning&amp;periode=$periode&amp;ansat_id=$ansatvalg&amp;vis=$vis&amp;refresh=on\" method=\"post\">\n";
			print "<div style=\"float:left; width:828px;#background-color:lightgreen;\">\n";
			print "<div id=\"printableArea\">\n";
				$tmp = "<h2 class=\"printHeadline\">Lønopgørelse</h2>\n";
				if (isset($afregnet) && $afregnet) $tmp.="<p class=\"printHeadline\">afregnet d. ".date("d-m-Y",$afregnet)." af $afregnet_af</p>\n";
				print "$tmp\n";
#					print "<div class=\"contentA\">\n";
						print "<table width='100%' border='0' class='loenafregning'><tbody>\n";
						print "<tr><td colspan='10'><b>Periode&nbsp;&nbsp;</b><SELECT NAME=\"periode\" class=\"printSelect\" onchange=\"this.form.submit()\">\n";# onchange=\"this.form.submit()
						for ($x = count($p_start)-3;$x>=0;$x--) {
							if ($periode==$p_start[$x]) print "<OPTION value=\"$p_start[$x]\">".date("d-m-Y",$p_start[$x])."&nbsp;&ndash;&nbsp;".date("d-m-Y",$p_start[$x+1]-86400)."</option>\n";
						}
						for ($x = count($p_start)-3;$x>=0;$x--) {
							if ($periode!=$p_start[$x]) print "<OPTION value=\"$p_start[$x]\">".date("d-m-Y",$p_start[$x])."&nbsp;&ndash;&nbsp;".date("d-m-Y",$p_start[$x+1]-86400)."</option>\n";
						}
						print "</SELECT></td>";
						//print "<td colspan='4' align='right'><input type=submit style=\"width:80px\" value=\"Opdat&eacute;r\" class=\"button gray medium\" name=\"submitForm\" onclick=\"javascript:docChange = false;\"></td></tr>";
						//print "\n";
						if ($vis=="belob") {
							print "<tr><td  colspan=\"14\"><b>Lønafregning beløb</b></td></tr>\n";
						} else {
							print "<tr><td  colspan=\"14\"><b>Lønafregning timer</b></td></tr>\n";
						}
						$vist = 0;

						$fodtotal = new stdClass();
						fod_felter_nulstil($fodtotal);

						for($d = 0;$d<$datoantal;$d++) {
							$foddagid = $datoliste[$d];
							$foddag = isset($foddage[$foddagid])?$foddage[$foddagid]:null;

							if ($foddag && $foddag->ansat_sum) {
								$foddagtotal = new stdClass();
								fod_felter_nulstil($foddagtotal);

								if (count($fodansatinfos)>1 || !$vist) {
									if (count($fodansatinfos)==1) {
										print "<tr><td colspan=\"15\"><hr class=\"printHr\"></td></tr><tr><td colspan=\"15\">\n";
									} else {
										print "<tr><td colspan=\"14\"><hr class=\"printHr\"></td></tr><tr><td colspan=\"14\">\n";
									}

									if (count($fodansatinfos)==1) {
										print "<h3 class=\"printHeadline\">" . $fodansatinfos[0]->navn . "</h3>\n";
										$vist = 1;
									} else print "<b>".dkdato($datoliste[$d])."</b>\n";
									print "</td></tr>
									<tr bgcolor=\"$linjebg\"><td><b>\n";

									(count($fodansatinfos)==1)?print "Dato":print "Navn";
								
									print "</b></td>";
										if (count($fodansatinfos)==1) print "<td align=\"center\"><b>Sted</b></td>";
										print "<td align=\"center\"><b>Timer</b></td>
										<td align=\"center\"><b>Dyrtid</b></td>
										<td align=\"center\"><b>50%</b></td>
										<td align=\"center\"><b>100%</b></td>
										<td align=\"center\"><b>Akkord</b></td>";
/*if ($vis=="belob")*/ print "<td align=\"center\"><b>Kørsel</b></td>"; # udkommenteret 20142503
										print "<td align=\"center\"><b>Skur&nbsp;1</b></td>
										<td align=\"center\"><b>Skur&nbsp;2</b></td>
										<td align=\"center\"><b>Mentor</b></td>
										<td align=\"center\"><b>Plads</b></td>
										<td align=\"center\"><b>Sygdom</b></td>
										<td align=\"center\"><b>Barn&nbsp;syg</b></td>
										<td align=\"center\"><b>Skole</b></td>
										<td align=\"center\"><b>I&nbsp;alt</b></td>
									</tr>";
								}
								for ($y = 0;$y<count($fodansatinfos);$y++) {
									$fodansatid = $fodansatinfos[$y]->id;
									$fodansat = isset($foddag->ansatte[$fodansatid])?$foddag->ansatte[$fodansatid]:null;

									if ($fodansat && $fodansat->ansat_sum) {
										fod_felter_laeg_til($foddagtotal, $fodansat);

										$alle_ansatte_id .= $fodansatinfos[$y]->id . ',';
										
										if (count($fodansatinfos) != 1) {
											($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
											print "<tr bgcolor=\"$linjebg\">";

											fod_felter_vis_ansat($fodansat, $periode, $vis);

											if ($vis=="belob") {
												fod_felter_vis_beloeb($fodansat, false, false);
											} else {
												fod_felter_vis_timer($fodansat, false, false);
											}

											print "</tr>";
										} else {
											if ($fodansat) {
												foreach($fodansat->sager as $fodsag) {
													if (0) {
														foreach($fodsag->opgaver as $fodopgave) {
															($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
															print "<tr bgcolor=\"$linjebg\">";

															fod_felter_vis_dato($foddagid);
															fod_felter_vis_sted($fodopgave, false);

															if ($vis=="belob") {
																fod_felter_vis_beloeb($fodopgave, false, false);
															} else {
																fod_felter_vis_timer($fodopgave, false, false);
															}
														}
													} else {
														($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
														print "<tr bgcolor=\"$linjebg\">";

														fod_felter_vis_dato($foddagid);
														fod_felter_vis_sted($fodsag, false);

														if ($vis=="belob") {
															fod_felter_vis_beloeb($fodsag, false, false);
														} else {
															fod_felter_vis_timer($fodsag, false, false);
														}
													}
												}
											}
										}
									}
								}
								if (count($fodansatinfos)>1) {
									($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
									print "<tr bgcolor=\"$linjebg\">";
									fod_felter_vis_titel('I alt', true);
									fod_felter_vis_timer($foddagtotal, true, true);
									print "</tr>";
								}

								fod_felter_laeg_til($fodtotal, $foddagtotal);
							}
						}
						
						if (count($fodansatinfos)==1) {
							print "<tr><td colspan=\"15\"><hr class=\"printHr\"></td></tr>";
						} else {
							print "<tr><td colspan=\"14\"><hr class=\"printHr\"></td></tr>";
						}

						($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;

						print "<tr bgcolor=\"$linjebg\">";

						fod_felter_vis_titel("I alt", true);

						if (count($fodansatinfos) == 1) {
							print "<td>&nbsp;</td>";
						}

						if ($vis=="belob") {
							fod_felter_vis_beloeb($fodtotal, true, true);
						} else {
							fod_felter_vis_timer($fodtotal, true, true);
						}

						print "</tr>";

						print "<tr><td colspan=\"14\"><br></td></tr>";
						print "<tr class=\"printDisplayNone\"><td colspan=\"7\">
							<input type=submit style=\"width:80px\" value=\"Opdat&eacute;r\" class=\"button gray medium\" name=\"submitForm\" onclick=\"javascript:docChange = false;\">";
						print "<td colspan=\"7\" align=\"right\">";
						if (substr($sag_rettigheder,6,1) && !$afregnet && !$ansatvalg) {
							$afregnet=date("d-m-Y");
							print "
								<input type=\"text\" style=\"width:80px;\" value=\"$afregnet\" name=\"afregnet\">
								<input type=\"submit\" style=\"width:80px;\" value=\"Afregn\" class=\"button gray medium\" name=\"afregn\" onclick=\"javascript:docChange = false;\"></td>
								</tr>\n";
						}
						print "</tbody></table>\n";		
#					print "</div><!-- end of contentA -->\n";
					print "</div><!-- end of printableArea -->\n";
					
					print "<input type=\"hidden\" name=\"alle_ansatte_id\" value=\"".rtrim($alle_ansatte_id, ",")."\">\n";
					#cho "alle_ansatte_id: $alle_ansatte_id<br>";
					#cho "periode: $periode";
					if ($refresh=='on') {
						$refresh = NULL;
						//print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/loen.php?funktion=loenafregning&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode\">";
						
						#20150702
						print "<input type=\"hidden\" id=\"alle_ansatte_id\" value=\"".rtrim($alle_ansatte_id, ",")."\">\n";
						print "<input type=\"hidden\" id=\"periode\" value=\"$periode\">\n";
						
						print "<script type=\"text/javascript\">
							$(document).ready(function()
							{
								var alle_ansatte_id = $(\"#alle_ansatte_id\").val();
								var periode = $(\"#periode\").val();
								var dataString = 'alle_ansatte_id='+ alle_ansatte_id + '&periode=' + periode;
							
								$.ajax({
									type: \"POST\",
									url: \"ajax_loenafregning.php\",
									data: dataString,
									dataType: \"html\",
									cache: false,
									success: function(html) {
										$(\".loenafregningVis\").html(html);
									}
								});
								
							});
						</script>";
					}
					//print "<a href=\"loen.php?funktion=loenafregning&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode&amp;visalle=on\">vis alle&nbsp;</a>";
				print "</div><!-- end of full container -->\n";
			print "<div class=\"clear\"></div>\n";
		print "</form>\n";
	print "</div>\n";
	}

	$funcendtime = microtime(true);

	fod_log('loenafregning: end: ' . ($funcendtime - $funcstarttime) . ' seconds' . "\n");
} #endfunc s_loenafregning
?>

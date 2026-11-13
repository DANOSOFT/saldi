# Permission declarations added in phase 3 (2026-09-16)

Pages that included `includes/online.php` without setting `$modulnr` were given a `$permission_key` by directory/filename rule (`any` = every logged-in user). Pages with `$modulnr` are mapped automatically through `permission_registry()`. Review during the logging period: the Log tab in Brugere & roller lists `unguarded` and `would-deny` events per page. Correct a wrong key by editing the declaration in the file.

| File | Key | Basis |
|---|---|---|
| admin/aaben_regnskab.php | `-` | skipped (master-admin layer / update files) |
| admin/admin_settings.php | `-` | skipped (master-admin layer / update files) |
| admin/bankfordeling.php | `-` | skipped (master-admin layer / update files) |
| admin/slet_regnskab.php | `-` | skipped (master-admin layer / update files) |
| admin/vis_regnskaber.php | `-` | skipped (master-admin layer / update files) |
| api/hent_ordrer.php | `any` | declared |
| api/hent_varer.php | `any` | declared |
| api/test_mismatch.php | `any` | declared |
| api/tilknyt.php | `any` | declared |
| book_missing_invoices.php | `debitor.ordre` | declared |
| booking/index.php | `any` | declared |
| bordplaner/planner/index.php | `debitor.ordre` | declared |
| bordplaner/planner/save.php | `debitor.ordre` | declared |
| bordplaner/table_plan.php | `debitor.ordre` | declared |
| createJson/index.php | `any` | declared |
| debitor/_varerInsert.php | `debitor.ordre` | declared |
| debitor/accountLookupData.php | `debitor.konti` | declared |
| debitor/afslut_rykker.php | `debitor.konti` | declared |
| debitor/api.php | `debitor.ordre` | declared |
| debitor/batch.php | `debitor.ordre` | declared |
| debitor/crmkalender.php | `debitor.konti` | declared |
| debitor/crmopret.php | `debitor.konti` | declared |
| debitor/crmvisning.php | `debitor.konti` | declared |
| debitor/csv2ordre.php | `debitor.ordre` | declared |
| debitor/debkort_save.php | `debitor.konti` | declared |
| debitor/formularprint.php | `debitor.ordre` | declared |
| debitor/genfakturer.php | `debitor.ordre` | declared |
| debitor/inkassoprint.php | `debitor.konti` | declared |
| debitor/kds/index.php | `debitor.ordre` | declared |
| debitor/kds/kitchen.php | `debitor.ordre` | declared |
| debitor/kds/recall.php | `debitor.ordre` | declared |
| debitor/kds/show_items.php | `debitor.ordre` | declared |
| debitor/koekkenprint.php | `debitor.ordre` | declared |
| debitor/kontoprint.php | `debitor.konti` | declared |
| debitor/ny_rykker.php | `debitor.konti` | declared |
| debitor/oioubl_dok.php | `debitor.ordre` | declared |
| debitor/oioxml_dok.php | `debitor.ordre` | declared |
| debitor/payments/flatpay.php | `debitor.ordre` | declared |
| debitor/payments/flatpay_old.php | `debitor.ordre` | declared |
| debitor/payments/lane3000-sim.php | `debitor.ordre` | declared |
| debitor/payments/lane3000.php | `debitor.ordre` | declared |
| debitor/payments/lane3000_afstemning.php | `debitor.ordre` | declared |
| debitor/payments/lane3001.php | `debitor.ordre` | declared |
| debitor/payments/mobilepay.php | `debitor.ordre` | declared |
| debitor/payments/mobilepayCancel.php | `debitor.ordre` | declared |
| debitor/payments/mobilepayListen.php | `debitor.ordre` | declared |
| debitor/payments/save_receipt.php | `debitor.ordre` | declared |
| debitor/payments/vibrant.php | `debitor.ordre` | declared |
| debitor/pbs_import.php | `debitor.ordre` | declared |
| debitor/pos_ordre_includes/includedFiles.php | `debitor.ordre` | declared |
| debitor/pos_print/koekkenbon.php | `debitor.ordre` | declared |
| debitor/productLookup.php | `debitor.ordre` | declared |
| debitor/ret_genfakt.php | `debitor.ordre` | declared |
| debitor/rykkerprint.php | `debitor.konti` | declared |
| debitor/saetpris.php | `debitor.ordre` | declared |
| debitor/saftCashRegister.php | `finans.regnskab` | declared |
| debitor/saftCashRegisterCreator.php | `finans.regnskab` | declared |
| debitor/save_date_settings.php | `debitor.konti` | declared |
| debitor/serienummer.php | `debitor.ordre` | declared |
| debitor/sync_stamkort.php | `debitor.konti` | declared |
| debitor/ubl2ordre.php | `debitor.ordre` | declared |
| debitor/udskriftsvalg.php | `debitor.ordre` | declared |
| debitor/updateCompany.php | `debitor.konti` | declared |
| debitor/update_payment_dates.php | `debitor.konti` | declared |
| debitoripad/await.php | `any` | declared |
| debitoripad/choose.php | `any` | declared |
| docsIncludes/emailDoc.php | `any` | auto (directory/filename rule) |
| finans/bankReconcile.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/bankimport.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/danlonimport.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/datalonimport.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/hentordrer.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/importer.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/kassekladde_includes/fetchbilagsmatch.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/kontokort_moms_standalone.php | `finans.regnskab` | auto (directory/filename rule) |
| finans/kontokort_standalone.php | `finans.regnskab` | auto (directory/filename rule) |
| finans/kontospec.php | `finans.regnskab` | auto (directory/filename rule) |
| finans/openpostdato.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/pbsimport.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/pbsm602import.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/pbswebimport.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/pulje_review.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/pulje_upload.php | `finans.kassekladde` | auto (directory/filename rule) |
| finans/rapport_includes/bankReconcile.php | `finans.regnskab` | auto (directory/filename rule) |
| finans/regnskabbasis.php | `finans.regnskab` | auto (directory/filename rule) |
| finans/saft.php | `finans.regnskab` | auto (directory/filename rule) |
| finans/saftCreator.php | `finans.regnskab` | auto (directory/filename rule) |
| finans/simuler.php | `finans.kassekladde` | auto (directory/filename rule) |
| includes/betweenUpdates.php | `-` | skipped (master-admin layer / update files) |
| includes/bilag.php | `any` | auto (directory/filename rule) |
| includes/docsIncludes/emailDoc.php | `any` | auto (directory/filename rule) |
| includes/docsIncludes/showDoc.php | `any` | auto (directory/filename rule) |
| includes/documents.php | `any` | auto (directory/filename rule) |
| includes/genberegn.php | `any` | auto (directory/filename rule) |
| includes/importer.php | `settings.import_export` | auto (directory/filename rule) |
| includes/login.php | `any` | auto (directory/filename rule) |
| includes/opdat_0.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_1.0.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_1.1.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_1.9.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_2.0.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_2.1.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.0.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.1.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.2.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.3.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.4.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.5.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.6.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.7.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.8.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_3.9.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_4.0.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_4.1.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_4.2.php | `-` | skipped (master-admin layer / update files) |
| includes/opdat_kostpriser.php | `-` | skipped (master-admin layer / update files) |
| includes/opdater.php | `-` | skipped (master-admin layer / update files) |
| includes/orderFuncIncludes/genberegn.php | `any` | auto (directory/filename rule) |
| includes/prislister.php | `lager.varer` | auto (directory/filename rule) |
| includes/sagsmenu.php | `any` | auto (directory/filename rule) |
| includes/saldi_assist_token.php | `any` | auto (directory/filename rule) |
| includes/udskriv.php | `any` | auto (directory/filename rule) |
| includes/unlock_order.php | `any` | auto (directory/filename rule) |
| includes/vis_bilag.php | `any` | auto (directory/filename rule) |
| index/admin_menu.php | `any` | auto (directory/filename rule) |
| index/backup.php | `system.backup` | auto (directory/filename rule) |
| index/createInEasyUbl.php | `any` | auto (directory/filename rule) |
| index/customer_graph_data.php | `any` | auto (directory/filename rule) |
| index/dashboard.php | `any` | auto (directory/filename rule) |
| index/dashboardIncludes/language.php | `any` | auto (directory/filename rule) |
| index/dashboardIncludes/regnaar.php | `any` | auto (directory/filename rule) |
| index/login.php | `any` | auto (directory/filename rule) |
| index/logud.php | `any` | auto (directory/filename rule) |
| index/main.php | `any` | auto (directory/filename rule) |
| index/uploadCompanyId.php | `any` | auto (directory/filename rule) |
| index/weekly_graph_data.php | `any` | auto (directory/filename rule) |
| kreditor/accountLookupData.php | `kreditor.ordre` | auto (directory/filename rule) |
| kreditor/batch.php | `kreditor.ordre` | auto (directory/filename rule) |
| kreditor/bogfor.php | `kreditor.ordre` | auto (directory/filename rule) |
| kreditor/formularprint.php | `kreditor.ordre` | auto (directory/filename rule) |
| kreditor/modtag.php | `kreditor.ordre` | auto (directory/filename rule) |
| kreditor/ordre2csv.php | `kreditor.ordre` | auto (directory/filename rule) |
| kreditor/productLookup.php | `kreditor.ordre` | auto (directory/filename rule) |
| kreditor/serienummer.php | `kreditor.ordre` | auto (directory/filename rule) |
| kreditor/ublimport.php | `kreditor.ordre` | auto (directory/filename rule) |
| lager/beholdningsliste.php | `lager.varer` | auto (directory/filename rule) |
| lager/lagerflyt.php | `lager.varer` | auto (directory/filename rule) |
| lager/lagerstatus.php | `lager.varer` | auto (directory/filename rule) |
| lager/lagerstatusmail.php | `lager.varer` | auto (directory/filename rule) |
| lager/lister/ordrestatus.php | `lager.varer` | auto (directory/filename rule) |
| lager/lister/serialnumber.php | `lager.varer` | auto (directory/filename rule) |
| lager/lister/vareliste.php | `lager.varer` | auto (directory/filename rule) |
| lager/orderapi.php | `debitor.ordre` | auto (directory/filename rule) |
| lager/pricelist.php | `lager.varer` | auto (directory/filename rule) |
| lager/productCardIncludes/stockLog.php | `lager.varer` | auto (directory/filename rule) |
| lager/stockLog.php | `lager.varer` | auto (directory/filename rule) |
| lager/updateSetKostprise.php | `lager.varer` | auto (directory/filename rule) |
| lager/vareimport.php | `lager.varer` | auto (directory/filename rule) |
| lager/vareliste.php | `lager.varer` | auto (directory/filename rule) |
| lager/vvsimport.php | `lager.varer` | auto (directory/filename rule) |
| mysale/mylabel.php | `any` | auto (directory/filename rule) |
| mysale/mylabelX.php | `any` | auto (directory/filename rule) |
| mysale/mysale.php | `any` | auto (directory/filename rule) |
| projectManager/api/issues.php | `any` | auto (directory/filename rule) |
| projectManager/api/notifications.php | `any` | auto (directory/filename rule) |
| projectManager/debug_sql.php | `any` | auto (directory/filename rule) |
| projectManager/index.php | `any` | auto (directory/filename rule) |
| projectManager/install_direct.php | `any` | auto (directory/filename rule) |
| projectManager/install_project_management.php | `any` | auto (directory/filename rule) |
| projectManager/project_view.php | `any` | auto (directory/filename rule) |
| projectManager/projects.php | `any` | auto (directory/filename rule) |
| projectManager/status.php | `any` | auto (directory/filename rule) |
| remoteBooking/mail.php | `any` | auto (directory/filename rule) |
| rental/header.php | `debitor.konti` | auto (directory/filename rule) |
| rental/rental.php | `debitor.konti` | auto (directory/filename rule) |
| rental/upload.php | `debitor.konti` | auto (directory/filename rule) |
| sager/bilag_ansatmappe.php | `any` | auto (directory/filename rule) |
| sager/bilag_mappe.php | `any` | auto (directory/filename rule) |
| sager/bilag_sager.php | `any` | auto (directory/filename rule) |
| sager/enhedssum.php | `any` | auto (directory/filename rule) |
| sager/view_bilag_sager.php | `any` | auto (directory/filename rule) |
| soapserver/addorderline.php | `any` | auto (directory/filename rule) |
| soapserver/invoice.php | `any` | auto (directory/filename rule) |
| soapserver/logon.php | `any` | auto (directory/filename rule) |
| soapserver/multiselect.php | `any` | auto (directory/filename rule) |
| soapserver/singleinsert.php | `any` | auto (directory/filename rule) |
| soapserver/singleselect.php | `any` | auto (directory/filename rule) |
| soapserver/singleupdate.php | `any` | auto (directory/filename rule) |
| sync_shop/sync_all_products.php | `any` | auto (directory/filename rule) |
| systemdata/brugerdata.php | `any` | auto (directory/filename rule) |
| systemdata/diverseIncludes/create_vibrant_login.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/diverseIncludes/create_vibrant_term.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/diverseIncludes/language.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/diverseIncludes/save_flatpay_id.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/email_settings.php | `settings.smtp` | auto (directory/filename rule) |
| systemdata/exporter_adresser.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/exporter_debitor.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/exporter_formular.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/exporter_kontoplan.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/exporter_kreditor.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/exporter_posmenu.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/exporter_saet_hms.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/exporter_varer.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/exporter_variantvarer.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/formeditor.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/formularimport.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/formularkort.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/gdpr.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/importAccountMap.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_adresser.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_debitor.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_formular.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_kontoplan.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_kreditor.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_posmenu.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_saet_hms.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_varelokationer.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_varer.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/importer_variantvarer.php | `settings.import_export` | auto (directory/filename rule) |
| systemdata/kontokort.php | `system.kontoplan` | auto (directory/filename rule) |
| systemdata/laas_lager.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/load_form_data.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/logoslet.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/logoupload.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/pos_del_btn.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/pos_get_product_id.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/pos_ryk_knap.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/save_form_data.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/settingsSearch.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/settingsSection.php | `system.indstillinger` → settings group key of the section (`?s=`) | declared; write checked on POST |
| systemdata/settingsLookup.php | `system.indstillinger` | declared (read-only JSON) |
| systemdata/settingsMoved.php | `any` | declared |
| systemdata/solarvvs.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/stdktoplan.php | `system.kontoplan` | auto (directory/filename rule) |
| systemdata/sys_div_func.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/sys_div_func_includes/delete_mobilepay_webhook.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/sys_div_func_includes/setup_mobilepay_webhook.php | `system.indstillinger` | auto (directory/filename rule) |
| systemdata/view_logoupload.php | `system.indstillinger` | auto (directory/filename rule) |

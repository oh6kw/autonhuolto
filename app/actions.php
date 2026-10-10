<?php
declare(strict_types=1);

/* ------------------------------ POST-toiminnot ------------------------------ */
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')!=='login'){
    requireCsrf(); $action=(string)($_POST['action']??''); authRequire($action); if($action==='update_service'&&!empty($_POST['delete_photo']))authRequire('delete_photo'); authHandlePost($db,$action);
    try {
        editRequire($db,$action);
        if($action==='save_app_settings'){
            $theme=in_array(post('theme'),['dark','mid','light'],true)?post('theme'):'dark';
            $priceMode=post('price_input_mode')==='gross'?'gross':'net';
            $vatRate=max(0.0,(float)(floatpost('vat_rate',25.5)??25.5));
            $pricingForSave=['price_input_mode'=>$priceMode,'vat_rate'=>(string)$vatRate];
            $hourlyInput=max(0.0,(float)(floatpost('hourly_rate',65.0)??65.0));
            $hourlyNet=(float)(priceNetFromInput($hourlyInput,$pricingForSave)??0.0);
            $pairs=[
                'feature_invoicing'=>isset($_POST['feature_invoicing'])?'1':'0',
                'feature_mechanics'=>isset($_POST['feature_mechanics'])?'1':'0',
                'feature_customers'=>isset($_POST['feature_customers'])?'1':'0',
                'feature_time_tracking'=>isset($_POST['feature_time_tracking'])?'1':'0',
                'feature_inventory'=>isset($_POST['feature_inventory'])?'1':'0',
                'parts_markup_enabled'=>isset($_POST['parts_markup_enabled'])?'1':'0','parts_markup_percent'=>(string)min(500.0,max(0.0,(float)(floatpost('parts_markup_percent',15.0)??15.0))),
                'theme'=>$theme,'hourly_rate'=>(string)$hourlyNet,'vat_rate'=>(string)$vatRate,'price_input_mode'=>$priceMode,'payment_days'=>(string)max(0,intpost('payment_days',14)),
                'shop_name'=>post('shop_name')?:DEFAULT_APP_NAME,'home_title'=>post('home_title')?:DEFAULT_HOME_TITLE,'home_subtitle'=>post('home_subtitle'),'business_id'=>post('business_id'),'shop_address'=>post('shop_address'),'shop_email'=>post('shop_email'),'shop_phone'=>post('shop_phone'),'iban'=>post('iban'),'bic'=>post('bic'),'mobilepay_enabled'=>isset($_POST['mobilepay_enabled'])?'1':'0','mobilepay_number'=>post('mobilepay_number'),'mobilepay_name'=>post('mobilepay_name'),'show_logo_invoice'=>isset($_POST['show_logo_invoice'])?'1':'0','show_logo_service_print'=>isset($_POST['show_logo_service_print'])?'1':'0','show_logo_car_history'=>isset($_POST['show_logo_car_history'])?'1':'0','show_logo_all_history'=>isset($_POST['show_logo_all_history'])?'1':'0','show_logo_header'=>isset($_POST['show_logo_header'])?'1':'0'
            ];
            /* hintojen syöttötavan vaihto muuntaa myös varaston hankintahinnat (tallennetaan syöttötavan mukaisena), vanhalla ALV-kannalla */
            $oldApp=appSettings($db);$oldMode=priceInputMode($oldApp);$convertedParts=0;
            $db->beginTransaction();
            try{
                if($oldMode!==$priceMode){
                    $factor=1+appVatRate($oldApp)/100;
                    $sel=$db->query("SELECT id,purchase_price FROM parts WHERE purchase_price IS NOT NULL");$upd=$db->prepare("UPDATE parts SET purchase_price=? WHERE id=?");
                    foreach($sel->fetchAll() as $pr){$old=(float)$pr['purchase_price'];$new=round($priceMode==='gross'?$old*$factor:$old/$factor,2);$upd->execute([$new,(int)$pr['id']]);$convertedParts++;}
                }
                foreach($pairs as $k=>$v)appSet($db,$k,$v);
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            if(isset($_POST['remove_logo']))appSet($db,'logo_path','');
            $newLogo=storeUploadedLogo($_FILES['company_logo']??null);if($newLogo!==null)appSet($db,'logo_path',$newLogo);
            flash('Asetukset tallennettiin.'.($convertedParts>0?' Varaston '.$convertedParts.' hankintahintaa muunnettiin '.($priceMode==='gross'?'verollisiksi':'verottomiksi').' (ALV '.dec(appVatRate($oldApp),1).' %).':'')); redirect('?view=settings');
        }

        if($action==='add_mechanic'){
            $name=post('mechanic_name');if($name==='')throw new RuntimeException('Anna mekaanikolle nimi.');
            $active=isset($_POST['mechanic_active'])?1:0;$makeDefault=isset($_POST['mechanic_default'])?1:0;
            $count=(int)$db->query("SELECT COUNT(*) FROM mechanics")->fetchColumn();if($count===0){$active=1;$makeDefault=1;}
            $db->beginTransaction();if($makeDefault)$db->exec("UPDATE mechanics SET is_default=0");
            $sort=(int)$db->query("SELECT COALESCE(MAX(sort_order),0)+10 FROM mechanics")->fetchColumn();
            $st=$db->prepare("INSERT INTO mechanics(name,active,is_default,sort_order) VALUES(?,?,?,?)");$st->execute([$name,$active,$makeDefault,$sort]);
            $db->commit();flash('Mekaanikko lisättiin.');redirect('?view=settings#mekaanikot');
        }
        if($action==='delete_mechanic'){
            $id=intpost('mechanic_id');if($id<=0)throw new RuntimeException('Mekaanikkoa ei löytynyt.');
            if(post('delete_confirm')!=='1')throw new RuntimeException('Vahvista mekaanikon poisto.');
            $db->beginTransaction();
            $st=$db->prepare("SELECT id,name FROM mechanics WHERE id=?");$st->execute([$id]);$mech=$st->fetch();if(!$mech)throw new RuntimeException('Mekaanikkoa ei löytynyt.');
            $st=$db->prepare("SELECT COUNT(*) FROM services WHERE mechanic_id=?");$st->execute([$id]);$svcCount=(int)$st->fetchColumn();
            if($svcCount!==intpost('usage_count',-1))throw new RuntimeException('Mekaanikon huoltojen määrä muuttui ('.$svcCount.' kpl). Avaa Asetukset uudelleen ja tarkista poisto.');
            $isMuu=mb_strtolower(trim((string)$mech['name']))==='muu';$muuId=null;$created=false;
            if($svcCount>0&&!$isMuu){
                $q=$db->prepare("SELECT id FROM mechanics WHERE LOWER(TRIM(name))='muu' AND id<>? ORDER BY active DESC,id LIMIT 1");$q->execute([$id]);$muuId=$q->fetchColumn();
                if($muuId===false){$sort=(int)$db->query("SELECT COALESCE(MAX(sort_order),0)+10 FROM mechanics")->fetchColumn();$db->prepare("INSERT INTO mechanics(name,active,is_default,sort_order) VALUES('Muu',1,0,?)")->execute([$sort]);$muuId=(int)$db->lastInsertId();$created=true;}
                $db->prepare("UPDATE services SET mechanic_id=?,mechanic_name_snapshot='Muu' WHERE mechanic_id=?")->execute([(int)$muuId,$id]);
            } elseif($svcCount>0){
                $db->prepare("UPDATE services SET mechanic_id=NULL,mechanic_name_snapshot='' WHERE mechanic_id=?")->execute([$id]);
            }
            $st=$db->prepare("UPDATE auth_users SET mechanic_id=0 WHERE mechanic_id=?");$st->execute([$id]);$userCount=$st->rowCount();
            $db->prepare("DELETE FROM mechanics WHERE id=?")->execute([$id]);
            $has=(int)$db->query("SELECT COUNT(*) FROM mechanics WHERE active=1 AND is_default=1")->fetchColumn();
            if($has===0){$next=$db->query("SELECT id FROM mechanics WHERE active=1 ORDER BY sort_order,name,id LIMIT 1")->fetchColumn();if($next!==false)$db->prepare("UPDATE mechanics SET is_default=1 WHERE id=?")->execute([(int)$next]);}
            $db->commit();
            flash('Mekaanikko '.(string)$mech['name'].' poistettiin.'.($svcCount>0?($isMuu?' '.$svcCount.' huollolta mekaanikko jäi tyhjäksi.':' '.$svcCount.' huollon mekaanikoksi tuli "Muu"'.($created?' (Muu-mekaanikko luotiin automaattisesti).':'.')):'').($userCount>0?' '.$userCount.' käyttäjän oletusmekaanikko-kytkentä poistui.':''),'warn');
            redirect('?view=settings#mekaanikot');
        }
        if($action==='update_mechanic'){
            $id=intpost('mechanic_id');$name=post('mechanic_name');if($id<=0||$name==='')throw new RuntimeException('Mekaanikon tiedot ovat puutteelliset.');
            $active=isset($_POST['mechanic_active'])?1:0;$makeDefault=isset($_POST['mechanic_default'])?1:0;
            $db->beginTransaction();if($makeDefault){$db->exec("UPDATE mechanics SET is_default=0");$active=1;}
            $st=$db->prepare("UPDATE mechanics SET name=?,active=?,is_default=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");$st->execute([$name,$active,$makeDefault,$id]);
            $has=(int)$db->query("SELECT COUNT(*) FROM mechanics WHERE active=1 AND is_default=1")->fetchColumn();
            if($has===0){$next=$db->query("SELECT id FROM mechanics WHERE active=1 ORDER BY sort_order,name,id LIMIT 1")->fetchColumn();if($next!==false)$db->prepare("UPDATE mechanics SET is_default=1 WHERE id=?")->execute([(int)$next]);}
            $db->commit();flash('Mekaanikon tiedot päivitettiin.');redirect('?view=settings#mekaanikot');
        }

        if($action==='add_catalog_item'){
            $label=post('item_label'); if($label==='')throw new RuntimeException('Anna huoltokohteelle nimi.');
            $section=post('item_section')?:'Muut'; $kind=validItemKind(post('item_kind')); $key='custom_'.bin2hex(random_bytes(5));
            $max=(int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM item_catalog")->fetchColumn();
            $defaultAction=$kind==='inspection'?'Tarkastettu':'Vaihdettu / tehty';$defaultUnit=$kind==='fluid'?'L':'kpl';$defaultQty=$kind==='part'?1:null;$defaultReset=$kind==='service'?0:1;
            $st=$db->prepare("INSERT INTO item_catalog(item_key,label,section,sort_order,is_custom,active,item_kind,default_action,default_unit,default_quantity,default_resets_interval) VALUES(?,?,?,?,1,1,?,?,?,?,?)");$st->execute([$key,$label,$section,$max+10,$kind,$defaultAction,$defaultUnit,$defaultQty,$defaultReset]);
            flash('Uusi huoltokohde lisättiin.'); redirect('?view=settings#kohteet');
        }
        if($action==='update_catalog_item'){
            $key=post('item_key');
            $st=$db->prepare("SELECT item_key FROM item_catalog WHERE item_key=?");$st->execute([$key]);
            if(!$st->fetchColumn())throw new RuntimeException('Huoltokohdetta ei löytynyt.');
            $label=post('item_label');if($label==='')throw new RuntimeException('Nimi ei voi olla tyhjä.');
            /* item_key on pysyvä sisäinen tunniste: nimeä, ryhmää ja näkyvyyttä saa muuttaa turvallisesti. */
            $kind=validItemKind(post('item_kind'));$defAction=$kind==='inspection'?'Tarkastettu':'Vaihdettu / tehty';$defUnit=$kind==='fluid'?'L':'kpl';$defQty=$kind==='part'?1:null;$defReset=$kind==='service'?0:1;
            $db->prepare("UPDATE item_catalog SET label=?,section=?,item_kind=?,default_action=?,default_unit=?,default_quantity=?,default_resets_interval=?,active=? WHERE item_key=?")->execute([$label,post('item_section')?:'Muut',$kind,$defAction,$defUnit,$defQty,$defReset,isset($_POST['active'])?1:0,$key]);
            flash('Huoltokohde päivitettiin. Historia ja sisäinen tunniste säilyivät ennallaan.');redirect('?view=settings#'.(preg_match('/^[A-Za-z0-9_.:-]{1,128}$/',$key)?'kohde-'.$key:'kohteet'));
        }

        if($action==='add_customer'){
            if(!customersEnabled($app))throw new RuntimeException('Asiakasrekisteri ei ole käytössä.');
            $name=post('customer_name');if($name==='')throw new RuntimeException('Anna asiakkaan nimi.');
            $number=post('customer_number')?:nextCustomerNumber($db);$type=post('customer_type')==='company'?'company':'private';
            $st=$db->prepare("INSERT INTO customers(customer_number,customer_type,name,contact_name,phone,email,address,postal_code,city,business_id,notes,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
            $st->execute([$number,$type,$name,post('contact_name'),post('phone'),post('email'),post('address'),post('postal_code'),post('city'),post('business_id'),post('customer_notes'),isset($_POST['customer_active'])?1:0]);
            $newId=(int)$db->lastInsertId();flash('Asiakas lisättiin.');redirect('?view=customers&customer='.$newId);
        }
        if($action==='update_customer'){
            $id=intpost('customer_id');$name=post('customer_name');if($id<=0||$name==='')throw new RuntimeException('Asiakkaan nimi puuttuu.');
            $number=post('customer_number')?:nextCustomerNumber($db);$type=post('customer_type')==='company'?'company':'private';
            $st=$db->prepare("UPDATE customers SET customer_number=?,customer_type=?,name=?,contact_name=?,phone=?,email=?,address=?,postal_code=?,city=?,business_id=?,notes=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $st->execute([$number,$type,$name,post('contact_name'),post('phone'),post('email'),post('address'),post('postal_code'),post('city'),post('business_id'),post('customer_notes'),isset($_POST['customer_active'])?1:0,$id]);
            flash('Asiakkaan tiedot päivitettiin.');redirect('?view=customers&customer='.$id);
        }
        if($action==='delete_customer'){
            if(!customersEnabled($app))throw new RuntimeException('Asiakasrekisteri ei ole käytössä.');
            if(!isset($_POST['delete_confirm']))throw new RuntimeException('Vahvista asiakkaan pysyvä poisto.');
            $id=intpost('customer_id');if($id<=0)throw new RuntimeException('Asiakasta ei valittu.');
            $customer=getCustomer($db,$id);if(!$customer)throw new RuntimeException('Asiakasta ei löytynyt.');
            $refs=customerDeleteReferences($db,$id);
            if(!customerDeleteAllowed($refs)){
                $parts=[];if($refs['cars']>0)$parts[]=$refs['cars'].' nykyinen auto';if($refs['history']>0)$parts[]=$refs['history'].' omistushistorian rivi';if($refs['services']>0)$parts[]=$refs['services'].' huolto';
                throw new RuntimeException('Asiakasta ei voi poistaa, koska siihen liittyy '.implode(', ',$parts).'. Asiakas voidaan arkistoida.');
            }
            $db->beginTransaction();
            try{$st=$db->prepare('DELETE FROM customers WHERE id=?');$st->execute([$id]);if($st->rowCount()!==1)throw new RuntimeException('Asiakasta ei voitu poistaa.');$db->commit();}
            catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash('Asiakas poistettiin pysyvästi.');redirect('?view=customers');
        }
        if($action==='set_car_customer'){
            $cid=intpost('car_id');$newCustomerId=max(0,intpost('customer_id'));$startDate=post('ownership_start_date');$carRow=getCar($db,$cid);if(!$carRow)throw new RuntimeException('Autoa ei löytynyt.');
            if(!validOptionalIsoDate($startDate))throw new RuntimeException('Omistajuuden alkupäivä on virheellinen.');
            $oldCustomerId=(int)($carRow['current_customer_id']??0);
            $newCustomer=$newCustomerId>0?getCustomer($db,$newCustomerId):null;if($newCustomerId>0&&!$newCustomer)throw new RuntimeException('Asiakasta ei löytynyt.');
            /* Sama asiakas: kyse ei ole omistajanvaihdoksesta vaan nykyisen avoimen historiarivin alkupäivän korjauksesta. */
            if($newCustomerId===$oldCustomerId){
                if($newCustomerId<=0){flash('Autolla ei ole nykyistä asiakaslinkkiä.','info');redirect('?car='.$cid.'#asiakkuus');}
                $st=$db->prepare("SELECT id,start_date FROM car_customer_history WHERE car_id=? AND customer_id=? AND end_date='' ORDER BY id DESC LIMIT 1");$st->execute([$cid,$newCustomerId]);$open=$st->fetch();
                if($open){
                    if((string)$open['start_date']===$startDate){flash('Omistajuuden alkupäivä oli jo ajan tasalla.','info');redirect('?car='.$cid.'#asiakkuus');}
                    $db->prepare("UPDATE car_customer_history SET start_date=? WHERE id=? AND car_id=?")->execute([$startDate,(int)$open['id'],$cid]);
                }else{
                    $db->prepare("INSERT INTO car_customer_history(car_id,customer_id,start_date,end_date,customer_name_snapshot,notes) VALUES(?,?,?,'',?,'Historiarivi luotu nykyisen asiakaslinkin korjauksessa')")->execute([$cid,$newCustomerId,$startDate,(string)$newCustomer['name']]);
                }
                flash('Nykyisen omistajuuden alkupäivä päivitettiin.');redirect('?car='.$cid.'#asiakkuus');
            }
            if($oldCustomerId>0&&$startDate==='')throw new RuntimeException('Anna omistajanvaihdoksen päivämäärä, kun vaihdat asiakkaalta toiselle tai poistat nykyisen asiakaslinkin.');
            $db->beginTransaction();
            try{
                if($oldCustomerId>0){
                    $endDate=date('Y-m-d',strtotime($startDate.' -1 day'));
                    $st=$db->prepare("UPDATE car_customer_history SET end_date=? WHERE car_id=? AND customer_id=? AND end_date='' ");$st->execute([$endDate,$cid,$oldCustomerId]);
                }
                $db->prepare("UPDATE cars SET current_customer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$newCustomerId?:null,$cid]);
                if($newCustomer){
                    $st=$db->prepare("INSERT INTO car_customer_history(car_id,customer_id,start_date,end_date,customer_name_snapshot,notes) VALUES(?,?,?,'',?,'')");$st->execute([$cid,$newCustomerId,$startDate,(string)$newCustomer['name']]);
                }
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash($newCustomer?'Auto liitettiin asiakkaaseen '.$newCustomer['name'].'.':'Auton nykyinen asiakaslinkki poistettiin. Omistushistoria säilyi.');redirect('?car='.$cid.'#asiakkuus');
        }
        if($action==='update_car_customer_history'){
            if(!customersEnabled($app))throw new RuntimeException('Asiakasrekisteri ei ole käytössä.');
            $cid=intpost('car_id');$hid=intpost('history_id');$startDate=post('history_start_date');$endDate=post('history_end_date');
            if(!validOptionalIsoDate($startDate)||!validOptionalIsoDate($endDate))throw new RuntimeException('Omistushistorian päivämäärä on virheellinen.');
            $st=$db->prepare("SELECT h.*,c.current_customer_id FROM car_customer_history h JOIN cars c ON c.id=h.car_id WHERE h.id=? AND h.car_id=?");$st->execute([$hid,$cid]);$row=$st->fetch();if(!$row)throw new RuntimeException('Omistushistorian riviä ei löytynyt.');
            $isCurrent=(int)$row['customer_id']===(int)($row['current_customer_id']??0)&&(string)$row['end_date']==='';
            if($isCurrent)$endDate='';
            if($startDate!==''&&$endDate!==''&&$endDate<$startDate)throw new RuntimeException('Omistajuuden päättymispäivä ei voi olla alkupäivää aikaisempi.');
            $db->prepare("UPDATE car_customer_history SET start_date=?,end_date=? WHERE id=? AND car_id=?")->execute([$startDate,$endDate,$hid,$cid]);
            flash('Omistushistorian päivämäärät päivitettiin.');redirect('?car='.$cid.'#asiakkuus');
        }
        if($action==='create_customer_from_car'){
            if(!customersEnabled($app))throw new RuntimeException('Asiakasrekisteri ei ole käytössä.');
            $cid=intpost('car_id');$carRow=getCar($db,$cid);if(!$carRow)throw new RuntimeException('Autoa ei löytynyt.');if((int)($carRow['current_customer_id']??0)>0)throw new RuntimeException('Auto on jo liitetty asiakkaaseen.');
            $name=post('customer_name')?:trim((string)$carRow['owner']);if($name==='')throw new RuntimeException('Anna asiakkaalle nimi.');$ownershipStart=post('ownership_start_date');if(!validOptionalIsoDate($ownershipStart))throw new RuntimeException('Omistajuuden alkupäivä on virheellinen.');
            $db->beginTransaction();$number=nextCustomerNumber($db);
            $st=$db->prepare("INSERT INTO customers(customer_number,customer_type,name,phone,email,address,business_id,notes,active) VALUES(?,'private',?,?,?,?,?,'Luotu auton vanhoista asiakastiedoista',1)");
            $st->execute([$number,$name,(string)$carRow['customer_phone'],(string)$carRow['customer_email'],(string)$carRow['customer_address'],(string)$carRow['customer_business_id']]);$customerId=(int)$db->lastInsertId();
            $db->prepare("UPDATE cars SET current_customer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$customerId,$cid]);
            $db->prepare("INSERT INTO car_customer_history(car_id,customer_id,start_date,end_date,customer_name_snapshot,notes) VALUES(?,?,?,'',?,'Alkuperäinen alkupäivä ei tiedossa')")->execute([$cid,$customerId,$ownershipStart,$name]);
            $db->commit();flash('Asiakas luotiin auton nykyisistä tiedoista ja auto liitettiin asiakkaaseen.');redirect('?view=customers&customer='.$customerId);
        }

        if($action==='backfill_service_customer_snapshots'){
            if(!customersEnabled($app))throw new RuntimeException('Asiakasrekisteri ei ole käytössä.');
            $cid=intpost('car_id');$carRow=getCar($db,$cid);if(!$carRow)throw new RuntimeException('Autoa ei löytynyt.');
            $customerId=(int)($carRow['current_customer_id']??0);if($customerId<=0)throw new RuntimeException('Autolla ei ole nykyistä asiakasta.');
            if(!isset($_POST['confirm_backfill']))throw new RuntimeException('Vahvista vanhojen huoltojen asiakassnapshotien täydentäminen.');
            $from=post('snapshot_from_date');if(!validOptionalIsoDate($from))throw new RuntimeException('Virheellinen alkupäivä.');
            $snap=customerSnapshotForCar($db,$carRow);$sql="UPDATE services SET customer_id=?,customer_name_snapshot=?,customer_address_snapshot=?,customer_email_snapshot=?,customer_phone_snapshot=?,customer_business_id_snapshot=?,updated_at=CURRENT_TIMESTAMP WHERE car_id=? AND TRIM(COALESCE(customer_name_snapshot,''))='' AND (customer_id IS NULL OR customer_id=0)";$params=[$snap['id'],$snap['name'],$snap['address'],$snap['email'],$snap['phone'],$snap['business_id'],$cid];
            if($from!==''){$sql.=" AND service_date>=?";$params[]=$from;}
            $st=$db->prepare($sql);$st->execute($params);$count=$st->rowCount();
            flash('Asiakassnapshot täydennettiin '.(int)$count.' vanhaan huoltoon. Olemassa olevia snapshot-tietoja ei muutettu.');redirect('?car='.$cid.'#asiakkuus');
        }

        if($action==='save_car'){
            $id=intpost('car_id');$reg=strtoupper(post('reg_plate'));$nick=post('nickname');if($reg===''&&$nick==='')throw new RuntimeException('Anna autolle rekisteritunnus tai lempinimi.');
            $existingCar=$id>0?getCar($db,$id):null;if($id>0&&!$existingCar)throw new RuntimeException('Autoa ei löytynyt.');
            /* Olemassa olevan auton kilometrilukema päivitetään vain yläosan Päivitä km -toiminnolla,
               jotta jokainen manuaalinen muutos saa oman aikaleimatun historiarivin. */
            $currentKm=$existingCar?(int)$existingCar['current_km']:max(0,intpost('current_km'));
            if(!validOptionalIsoDate(post('first_registration_date')))throw new RuntimeException('Ensirekisteröintipäivä on virheellinen.');
            $vals=[$reg,$nick,post('owner'),post('make'),post('model'),post('year'),post('engine'),post('vin'),$currentKm,post('notes'),post('first_registration_date'),max(0,intpost('warning_km',3000)),max(0,intpost('warning_months',3)),floatpost('engine_oil_capacity'),post('engine_oil_spec'),floatpost('gearbox_oil_capacity'),post('gearbox_oil_spec'),floatpost('coolant_capacity'),post('coolant_spec'),post('customer_address'),post('customer_email'),post('customer_phone'),post('customer_business_id')];
            if($id>0){$st=$db->prepare("UPDATE cars SET reg_plate=?,nickname=?,owner=?,make=?,model=?,year=?,engine=?,vin=?,current_km=?,notes=?,first_registration_date=?,warning_km=?,warning_months=?,engine_oil_capacity=?,engine_oil_spec=?,gearbox_oil_capacity=?,gearbox_oil_spec=?,coolant_capacity=?,coolant_spec=?,customer_address=?,customer_email=?,customer_phone=?,customer_business_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");$st->execute([...$vals,$id]);flash('Auton tiedot päivitettiin.');redirect('?car='.$id);}
            $selectedCustomer=customersEnabled($app)?max(0,intpost('new_car_customer_id')):0;if($selectedCustomer>0&&!getCustomer($db,$selectedCustomer))throw new RuntimeException('Valittua asiakasta ei löytynyt.');
            $newCarOwnershipStart=post('new_car_ownership_start_date');if($selectedCustomer>0&&!validOptionalIsoDate($newCarOwnershipStart))throw new RuntimeException('Omistajuuden alkupäivä on virheellinen.');
            $st=$db->prepare("INSERT INTO cars(reg_plate,nickname,owner,make,model,year,engine,vin,current_km,notes,first_registration_date,warning_km,warning_months,engine_oil_capacity,engine_oil_spec,gearbox_oil_capacity,gearbox_oil_spec,coolant_capacity,coolant_spec,customer_address,customer_email,customer_phone,customer_business_id,current_customer_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$st->execute([...$vals,$selectedCustomer?:null]);$id=(int)$db->lastInsertId();
            if($currentKm>0)$db->prepare("INSERT INTO odometer_readings(car_id,odometer,recorded_at,source,note) VALUES(?,?,?,?,?)")->execute([$id,$currentKm,date('Y-m-d H:i:s'),'initial','Auton lähtökilometrit']);
            if($selectedCustomer>0){$cu=getCustomer($db,$selectedCustomer);$db->prepare("INSERT INTO car_customer_history(car_id,customer_id,start_date,end_date,customer_name_snapshot,notes) VALUES(?,?,?,'',?,'')")->execute([$id,$selectedCustomer,$newCarOwnershipStart,(string)($cu['name']??'')]);}
            flash('Auto lisättiin.');redirect('?car='.$id);
        }
        if($action==='update_km'){
            $cid=intpost('car_id');$newKm=max(0,intpost('current_km'));$carRow=getCar($db,$cid);if(!$carRow)throw new RuntimeException('Autoa ei löytynyt.');
            $oldKm=(int)$carRow['current_km'];if($newKm<=0)throw new RuntimeException('Anna mittarilukema.');
            if($oldKm>0&&$newKm<$oldKm)throw new RuntimeException('Uusi mittarilukema '.km($newKm).' on nykyistä lukemaa '.km($oldKm).' pienempi. Korjaa syöttö; Päivitä km ei pienennä auton nykyistä lukemaa.');
            $today=date('Y-m-d');$dup=$db->prepare("SELECT id FROM odometer_readings WHERE car_id=? AND odometer=? AND substr(recorded_at,1,10)=? LIMIT 1");$dup->execute([$cid,$newKm,$today]);
            if($dup->fetchColumn()){if($newKm>$oldKm)$db->prepare("UPDATE cars SET current_km=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$newKm,$cid]);flash('Sama mittarilukema on jo kirjattu tälle päivälle. Nykyinen lukema on ajan tasalla.','info');redirect('?car='.$cid);}
            $db->beginTransaction();
            try{
                $db->prepare("INSERT INTO odometer_readings(car_id,odometer,recorded_at,source,note) VALUES(?,?,?,?,?)")->execute([$cid,$newKm,date('Y-m-d H:i:s'),'manual','']);
                $db->prepare("UPDATE cars SET current_km=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$newKm,$cid]);
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash('Kilometrilukema '.km($newKm).' kirjattiin mittarilukemahistoriaan.');redirect('?car='.$cid);
        }
        if($action==='add_old_odometer'){
            $cid=intpost('car_id');$carRow=getCar($db,$cid);if(!$carRow)throw new RuntimeException('Autoa ei löytynyt.');
            $date=trim(post('reading_date'));$newKm=(int)preg_replace('/\D+/','',post('reading_km'));$note=trim(post('reading_note'));
            if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$dm)||!checkdate((int)$dm[2],(int)$dm[3],(int)$dm[1]))throw new RuntimeException('Anna lukeman päivämäärä.');
            if($date>date('Y-m-d'))throw new RuntimeException('Päivämäärä ei voi olla tulevaisuudessa.');
            if($newKm<=0)throw new RuntimeException('Anna mittarilukema.');
            if($newKm>9999999)throw new RuntimeException('Mittarilukema on liian suuri.');
            if(mb_strlen($note)>200)throw new RuntimeException('Huomio saa olla enintään 200 merkkiä.');
            /* Lukeman on sovittava historian ketjuun: aiempana päivänä ei suurempi, myöhempänä ei pienempi kuin jo kirjatut. */
            $before=null;$after=null;
            foreach(kilometerHistoryEntries($db,$cid,false) as $e){
                $ek=(int)$e['km'];if($ek<=0)continue;$ed=(string)$e['date'];
                if($ed===$date&&$ek===$newKm&&$e['source']!=='service'){flash('Sama lukema on jo kirjattu tälle päivälle.','info');redirect('?car='.$cid);}
                if($ed<$date&&$ek>$newKm&&($before===null||$ek>$before['km']))$before=['km'=>$ek,'date'=>$ed];
                if($ed>$date&&$ek<$newKm&&($after===null||$ek<$after['km']))$after=['km'=>$ek,'date'=>$ed];
            }
            if($before)throw new RuntimeException('Lukema '.km($newKm).' päivälle '.fiDate($date).' ei sovi historiaan: jo '.fiDate($before['date']).' on kirjattu suurempi lukema '.km($before['km']).'. Tarkista päivämäärä ja lukema.');
            if($after)throw new RuntimeException('Lukema '.km($newKm).' päivälle '.fiDate($date).' ei sovi historiaan: vasta '.fiDate($after['date']).' on kirjattu pienempi lukema '.km($after['km']).'. Tarkista päivämäärä ja lukema.');
            $db->beginTransaction();
            try{
                $db->prepare("INSERT INTO odometer_readings(car_id,odometer,recorded_at,source,note) VALUES(?,?,?,?,?)")->execute([$cid,$newKm,$date.' 12:00:00','old',$note]);
                if($newKm>(int)$carRow['current_km'])$db->prepare("UPDATE cars SET current_km=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$newKm,$cid]);
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash('Vanha mittarilukema '.km($newKm).' ('.fiDate($date).') kirjattiin kilometrihistoriaan.');redirect('?car='.$cid.'#kilometrit');
        }
        if($action==='delete_odometer_reading'){
            $cid=intpost('car_id');$rid=intpost('reading_id');$carRow=getCar($db,$cid);if(!$carRow)throw new RuntimeException('Autoa ei löytynyt.');
            $st=$db->prepare("SELECT * FROM odometer_readings WHERE id=? AND car_id=?");$st->execute([$rid,$cid]);$reading=$st->fetch();if(!$reading)throw new RuntimeException('Mittarimerkintää ei löytynyt.');
            $db->beginTransaction();
            try{
                $db->prepare("DELETE FROM odometer_readings WHERE id=? AND car_id=?")->execute([$rid,$cid]);
                if((int)$reading['odometer']===(int)$carRow['current_km'])recalculateCurrentKm($db,$cid);
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash('Mittarimerkintä poistettiin.');redirect('?car='.$cid);
        }
        if($action==='save_item_settings'){
            $cid=intpost('car_id');$items=allItems($db,true);if(!getCar($db,$cid))throw new RuntimeException('Autoa ei löytynyt.');
            foreach($items as $it)if(!validOptionalIsoDate(trim((string)($_POST['first_due_date'][$it['item_key']]??''))))throw new RuntimeException('Huolto-ohjelman lähtöpäivä on virheellinen: '.(string)$it['label'].'.');
            $db->beginTransaction();
            $st=$db->prepare("INSERT INTO car_item_settings(car_id,item_key,enabled,interval_km,interval_months,schedule_type,first_due_km,first_due_date,notes) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(car_id,item_key) DO UPDATE SET enabled=excluded.enabled,interval_km=excluded.interval_km,interval_months=excluded.interval_months,schedule_type=excluded.schedule_type,first_due_km=excluded.first_due_km,first_due_date=excluded.first_due_date,notes=excluded.notes");
            foreach($items as $it){$k=$it['item_key'];$st->execute([$cid,$k,isset($_POST['enabled'][$k])?1:0,max(0,(int)($_POST['interval_km'][$k]??0)),max(0,(int)($_POST['interval_months'][$k]??0)),in_array($k,array_values(beltInspectionItems()),true)?'inspect':(isset(beltInspectionItems()[$k])?'replace':validScheduleType(trim((string)($_POST['schedule_type'][$k]??'replace')))),max(0,(int)($_POST['first_due_km'][$k]??0)),trim((string)($_POST['first_due_date'][$k]??'')),trim((string)($_POST['item_notes'][$k]??''))]);}
            $db->commit();flash('Huolto-ohjelma tallennettiin.');redirect('?car='.$cid.'#ohjelma');
        }

        if(in_array($action,['add_service','update_service'],true)){
            $cid=intpost('car_id');$sid=intpost('service_id');$date=post('service_date',date('Y-m-d'));if($date===''||!validOptionalIsoDate($date))throw new RuntimeException('Huoltopäivä on virheellinen.');$odo=max(0,intpost('odometer'));$serviceType=validServiceType(post('service_type'));$title=post('title')?:$serviceType;$cost=floatpost('total_cost');
            $items=itemMap($db);$selected=array_values(array_intersect(array_keys($items),array_map('strval',(array)($_POST['done']??[]))));
            $existingService=null;$actionSnapshots=[];
            if($action==='update_service'){$st=$db->prepare("SELECT id,odometer,mechanic_id,mechanic_name_snapshot,labor_hours,labor_rate,labor_vat_rate,actual_work_seconds,service_origin,customer_id,customer_name_snapshot,customer_address_snapshot,customer_email_snapshot,customer_phone_snapshot,customer_business_id_snapshot FROM services WHERE id=? AND car_id=?");$st->execute([$sid,$cid]);$existingService=$st->fetch();if(!$existingService)throw new RuntimeException('Muokattavaa huoltoa ei löytynyt.');$snapshotSt=$db->prepare('SELECT item_key,item_label_snapshot,item_section_snapshot FROM service_actions WHERE service_id=? ORDER BY id');$snapshotSt->execute([$sid]);foreach($snapshotSt->fetchAll() as $row)if(!isset($actionSnapshots[$row['item_key']]))$actionSnapshots[$row['item_key']]=$row;}
            $db->beginTransaction();
            /* Ominaisuuden piilottaminen ei saa koskaan nollata vanhaa dataa. */
            $labHours=array_key_exists('labor_hours',$_POST)?max(0,(float)(floatpost('labor_hours',0)??0)):(float)($existingService['labor_hours']??0);
            $labVatPosted=floatpost('labor_vat_rate');$labVat=$labVatPosted!==null?max(0.0,$labVatPosted):($existingService&&$existingService['labor_vat_rate']!==null?(float)$existingService['labor_vat_rate']:appVatRate($app));
            $labRate=invoicingEnabled($app)&&array_key_exists('labor_rate',$_POST)?priceNetFromInputAtVat(floatpost('labor_rate'),$app,$labVat):($existingService['labor_rate']??null);
            if($labRate===null)$labVat=null;
            $actualWorkSeconds=array_key_exists('actual_work_seconds',$_POST)?max(0,intpost('actual_work_seconds')):(int)($existingService['actual_work_seconds']??0);
            $mechanicId=0;$mechanicName='';
            $mechanicSelectionAllowed=mechanicsEnabled($app)||($existingService&&array_key_exists('mechanic_id',$_POST));
            if($mechanicSelectionAllowed){
                $mechanicId=max(0,intpost('mechanic_id'));
                if($mechanicId>0){
                    if($existingService && $mechanicId===(int)($existingService['mechanic_id']??0) && trim((string)($existingService['mechanic_name_snapshot']??''))!==''){
                        $mechanicName=(string)$existingService['mechanic_name_snapshot'];
                    } else {
                        $mst=$db->prepare("SELECT name FROM mechanics WHERE id=?");$mst->execute([$mechanicId]);$mechanicName=trim((string)($mst->fetchColumn()?:''));if($mechanicName==='')$mechanicId=0;
                    }
                }
            } elseif($existingService){
                $mechanicId=(int)($existingService['mechanic_id']??0);$mechanicName=(string)($existingService['mechanic_name_snapshot']??'');
            }
            $origin=post('service_origin');if(!in_array($origin,['own','external','legacy'],true))$origin=$existingService?(string)($existingService['service_origin']??'legacy'):'own';
            $carForCustomer=getCar($db,$cid)??[];$snap=customerSnapshotForCar($db,$carForCustomer);
            if($existingService){$snap=['id'=>$existingService['customer_id']!==null?(int)$existingService['customer_id']:null,'name'=>(string)($existingService['customer_name_snapshot']??''),'address'=>(string)($existingService['customer_address_snapshot']??''),'email'=>(string)($existingService['customer_email_snapshot']??''),'phone'=>(string)($existingService['customer_phone_snapshot']??''),'business_id'=>(string)($existingService['customer_business_id_snapshot']??'')];}
            if($action==='add_service'){$st=$db->prepare("INSERT INTO services(car_id,service_date,odometer,title,service_type,workshop,total_cost,notes,labor_hours,labor_rate,labor_vat_rate,actual_work_seconds,mechanic_id,mechanic_name_snapshot,service_origin,customer_id,customer_name_snapshot,customer_address_snapshot,customer_email_snapshot,customer_phone_snapshot,customer_business_id_snapshot) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$st->execute([$cid,$date,$odo,$title,$serviceType,post('workshop'),$cost,post('service_notes'),$labHours,$labRate,$labVat,$actualWorkSeconds,$mechanicId?:null,$mechanicName,$origin,$snap['id'],$snap['name'],$snap['address'],$snap['email'],$snap['phone'],$snap['business_id']]);$sid=(int)$db->lastInsertId();}
            else {$st=$db->prepare("UPDATE services SET service_date=?,odometer=?,title=?,service_type=?,workshop=?,total_cost=?,notes=?,labor_hours=?,labor_rate=?,labor_vat_rate=?,actual_work_seconds=?,mechanic_id=?,mechanic_name_snapshot=?,service_origin=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND car_id=?");$st->execute([$date,$odo,$title,$serviceType,post('workshop'),$cost,post('service_notes'),$labHours,$labRate,$labVat,$actualWorkSeconds,$mechanicId?:null,$mechanicName,$origin,$sid,$cid]);$db->prepare("DELETE FROM service_actions WHERE service_id=?")->execute([$sid]);$db->prepare("DELETE FROM service_custom_actions WHERE service_id=?")->execute([$sid]);}
            $ins=$db->prepare("INSERT INTO service_actions(service_id,item_key,action,brand,supplier_sku,oem_number,notes,quantity,unit,price_net,vat_rate,item_label_snapshot,item_section_snapshot,resets_interval) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $remember=$db->prepare("INSERT INTO car_item_settings(car_id,item_key,preferred_brand,supplier_sku,oem_number) VALUES(?,?,?,?,?) ON CONFLICT(car_id,item_key) DO UPDATE SET preferred_brand=CASE WHEN excluded.preferred_brand<>'' THEN excluded.preferred_brand ELSE preferred_brand END,supplier_sku=CASE WHEN excluded.supplier_sku<>'' THEN excluded.supplier_sku ELSE supplier_sku END,oem_number=CASE WHEN excluded.oem_number<>'' THEN excluded.oem_number ELSE oem_number END");
            
            $desiredStock=[];$stockChanges=0;
            foreach($selected as $k){
                $brand=trim((string)($_POST['service_brand'][$k]??''));$sku=trim((string)($_POST['service_supplier_sku'][$k]??''));$oem=trim((string)($_POST['service_oem_number'][$k]??''));$note=trim((string)($_POST['service_item_notes'][$k]??''));
                $qraw=str_replace(',','.',trim((string)($_POST['service_quantity'][$k]??'')));$qty=$qraw!==''&&is_numeric($qraw)?max(0,(float)$qraw):null;$unit=in_array((string)($_POST['service_unit'][$k]??''),['kpl','L'],true)?(string)$_POST['service_unit'][$k]:(string)($items[$k]['default_unit']??itemDefaultUnit($k));
                $vatRaw=str_replace(',','.',trim((string)($_POST['service_vat_rate'][$k]??'')));$rowVat=$vatRaw!==''&&is_numeric($vatRaw)?max(0.0,(float)$vatRaw):appVatRate($app);$pr=str_replace(',','.',trim((string)($_POST['service_price_net'][$k]??'')));$price=$pr!==''&&is_numeric($pr)?(float)$pr:null;if($price!==null&&invoicingEnabled($app))$price=priceNetFromInputAtVat($price,$app,$rowVat);if($price===null)$rowVat=null;
                $act=validActionType(trim((string)($_POST['service_action_type'][$k]??($items[$k]['default_action']??'Vaihdettu / tehty'))));$reset=isset($_POST['service_resets_interval'][$k])?1:0;if(isset(beltInspectionItems()[$k])&&$act!=='Vaihdettu / tehty')$reset=0;$ins->execute([$sid,$k,$act,$brand,$sku,$oem,$note,$qty,$unit,$price,$rowVat,(string)($actionSnapshots[$k]['item_label_snapshot']??$items[$k]['label']),(string)($actionSnapshots[$k]['item_section_snapshot']??$items[$k]['section']),$reset]);$remember->execute([$cid,$k,$brand,$sku,$oem]);
                if(($sku!==''||$oem!=='')&&in_array((string)($items[$k]['item_kind']??'generic'),['part','fluid'],true)){$partName=(string)$items[$k]['label'].($brand!==''?' · '.$brand:'');learnPartForCar($db,$cid,$k,$partName,$brand,$sku,$oem);}
                if(inventoryEnabled($app)&&(string)($_POST['service_use_stock'][$k]??'0')==='1'){$spid=(int)($_POST['service_stock_part'][$k]??0);$sqraw=str_replace(',','.',trim((string)($_POST['service_stock_qty'][$k]??'')));if($spid<=0||$sqraw===''||!is_numeric($sqraw)||(float)$sqraw<=0)throw new RuntimeException('Valitse varastosta käytettävä varaosa ja positiivinen määrä: '.(string)$items[$k]['label'].'.');$desiredStock[$k]=['part_id'=>$spid,'quantity'=>(float)$sqraw];}
            }
            $customDesc=(array)($_POST['custom_desc']??[]);$customNotes=(array)($_POST['custom_notes']??[]);$customPrice=(array)($_POST['custom_price_net']??[]);$customVat=(array)($_POST['custom_vat_rate']??[]);$ic=$db->prepare("INSERT INTO service_custom_actions(service_id,description,notes,price_net,vat_rate) VALUES(?,?,?,?,?)");
            foreach($customDesc as $i=>$desc){$desc=trim((string)$desc);if($desc==='')continue;$vr=str_replace(',','.',trim((string)($customVat[$i]??'')));$rowVat=$vr!==''&&is_numeric($vr)?max(0.0,(float)$vr):appVatRate($app);$p=str_replace(',','.',trim((string)($customPrice[$i]??'')));$pv=$p!==''&&is_numeric($p)?(float)$p:null;if($pv!==null&&invoicingEnabled($app))$pv=priceNetFromInputAtVat($pv,$app,$rowVat);if($pv===null)$rowVat=null;$ic->execute([$sid,$desc,trim((string)($customNotes[$i]??'')),$pv,$rowVat]);}
            if(inventoryEnabled($app))$stockChanges=reconcileServiceInventoryUsage($db,$sid,$cid,$date,$title,$desiredStock);
            $deletePhotoPaths=[]; if($action==='update_service'){
                foreach((array)($_POST['photo_caption_existing']??[]) as $pid=>$caption){$pid=(int)$pid;if($pid<=0)continue;$caption=trim((string)$caption);if(mb_strlen($caption)>240)$caption=mb_substr($caption,0,240);$db->prepare("UPDATE service_photos SET caption=? WHERE id=? AND service_id=? AND car_id=?")->execute([$caption,$pid,$sid,$cid]);}
                foreach((array)($_POST['delete_photo']??[]) as $pid){$st=$db->prepare("SELECT file_path FROM service_photos WHERE id=? AND service_id=? AND car_id=?");$st->execute([(int)$pid,$sid,$cid]);$fp=$st->fetchColumn();if($fp!==false){$deletePhotoPaths[]=(string)$fp;$db->prepare("DELETE FROM service_photos WHERE id=? AND service_id=?")->execute([(int)$pid,$sid]);}}
            }
            $photoCount=storeUploadedPhotos($db,$sid,getCar($db,$cid)??['id'=>$cid,'reg_plate'=>''],isset($_FILES['service_images'])&&is_array($_FILES['service_images'])?$_FILES['service_images']:null,(array)($_POST['service_image_captions']??[]));
            $carBeforeKm=(int)((getCar($db,$cid)['current_km']??0));
            if($odo>0)$db->prepare("UPDATE cars SET current_km=CASE WHEN CAST(current_km AS INTEGER) < CAST(? AS INTEGER) THEN CAST(? AS INTEGER) ELSE CAST(current_km AS INTEGER) END,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$odo,$odo,$cid]);
            /* Jos muokattu huolto oli nimenomaan nykyisen km:n lähde ja sen lukemaa korjattiin alaspäin,
               laske nykyinen km uudelleen. Mahdollinen erillinen uudempi mittarimerkintä jää luonnollisesti voimaan. */
            if($action==='update_service'&&$existingService&&(int)$existingService['odometer']===$carBeforeKm&&$odo<(int)$existingService['odometer'])recalculateCurrentKm($db,$cid);
            $db->commit();foreach($deletePhotoPaths??[] as $fp)deletePhotoFile($fp);$msg=$action==='add_service'?'Huolto tallennettiin.':'Huollon muutokset tallennettiin.';if(($stockChanges??0)>0)$msg.=' Varastosaldo täsmäytettiin.';if(($photoCount??0)>0)$msg.=' Kuvia lisättiin '.(int)$photoCount.'.';flash($msg);redirect('?car='.$cid.'#historia');
        }

        if($action==='add_part'){
            $cid=intpost('car_id');if(post('part_name')==='')throw new RuntimeException('Anna varaosalle nimi.');if(!getCar($db,$cid))throw new RuntimeException('Autoa ei löytynyt.');
            $stock=max(0.0,floatpost('part_stock_qty',0)??0);$unit=post('part_stock_unit')?:'kpl';$reorder=floatpost('part_reorder_level');$purchase=floatpost('part_purchase_price');$compat=(array)($_POST['part_compatible_cars']??[]);$compat[]=$cid;
            $db->beginTransaction();try{$st=$db->prepare("INSERT INTO parts(car_id,item_key,part_name,brand,supplier_sku,oem_number,url,notes,stock_qty,stock_unit,shelf_location,reorder_level,purchase_price) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");$st->execute([$cid,post('part_item_key'),post('part_name'),post('part_brand'),post('part_supplier_sku'),post('part_oem_number'),post('part_url'),post('part_notes'),$stock,$unit,post('part_shelf_location'),$reorder,$purchase]);$pid=(int)$db->lastInsertId();syncPartCompatibility($db,$pid,$cid,$compat);if($stock>0){$tx=$db->prepare("INSERT INTO inventory_transactions(part_id,quantity_change,event_type,note) VALUES(?,?,'initial','Alkusaldo')");$tx->execute([$pid,$stock]);}$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash('Varaosa lisättiin muistioon. Sopivuus tallennettiin '.count(partCompatibleCarIds($db,$pid)).' autolle.');redirect(post('return')==='inventory'?'?view=inventory':'?car='.$cid.'#varaosat');
        }
        if($action==='update_part'){
            $cid=intpost('car_id');$pid=intpost('part_id');if(post('part_name')==='')throw new RuntimeException('Anna varaosalle nimi.');
            $chk=$db->prepare("SELECT p.* FROM parts p WHERE p.id=? AND EXISTS (SELECT 1 FROM part_car_compatibility pc WHERE pc.part_id=p.id AND pc.car_id=?)");$chk->execute([$pid,$cid]);$oldPart=$chk->fetch();if(!$oldPart)throw new RuntimeException('Muokattavaa varaosaa ei löytynyt tälle autolle.');$primary=(int)$oldPart['car_id'];$compat=(array)($_POST['part_compatible_cars']??[]);
            $usage=$db->prepare('SELECT COUNT(*) FROM service_inventory_usage WHERE part_id=?');$usage->execute([$pid]);
            if((int)$usage->fetchColumn()>0&&(post('part_item_key')!==(string)$oldPart['item_key']||(array_key_exists('part_stock_unit',$_POST)&&(post('part_stock_unit')?:'kpl')!==(string)$oldPart['stock_unit'])))throw new RuntimeException('Huoltoon käytetyn varastonimikkeen kohdetta tai yksikköä ei voi vaihtaa. Lisää eri yksikölle tai kohteelle uusi nimike.');
            $db->beginTransaction();try{$st=$db->prepare("UPDATE parts SET item_key=?,part_name=?,brand=?,supplier_sku=?,oem_number=?,url=?,notes=?,stock_unit=?,shelf_location=?,reorder_level=?,purchase_price=? WHERE id=?");$st->execute([post('part_item_key'),post('part_name'),post('part_brand'),post('part_supplier_sku'),post('part_oem_number'),post('part_url'),post('part_notes'),array_key_exists('part_stock_unit',$_POST)?(post('part_stock_unit')?:'kpl'):$oldPart['stock_unit'],array_key_exists('part_shelf_location',$_POST)?post('part_shelf_location'):$oldPart['shelf_location'],array_key_exists('part_reorder_level',$_POST)?floatpost('part_reorder_level'):$oldPart['reorder_level'],array_key_exists('part_purchase_price',$_POST)?floatpost('part_purchase_price'):$oldPart['purchase_price'],$pid]);syncPartCompatibility($db,$pid,$primary,$compat);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash('Varaosan tiedot ja autosopivuudet päivitettiin.');redirect('?car='.$cid.'#varaosat');
        }
        if($action==='adjust_part_stock') {
            $appNow=appSettings($db);if(!inventoryEnabled($appNow))throw new RuntimeException('Varaosavarasto ei ole käytössä.');
            $pid=intpost('part_id');$cid=intpost('car_id');$direction=post('stock_direction');$qty=floatpost('stock_qty');
            if($pid<=0||$qty===null||$qty<=0)throw new RuntimeException('Anna varastotapahtumalle positiivinen määrä.');
            if(!in_array($direction,['in','out'],true))throw new RuntimeException('Virheellinen varastotapahtuma.');
            $st=$db->prepare("SELECT p.id,p.stock_qty,p.stock_unit FROM parts p WHERE p.id=? AND EXISTS (SELECT 1 FROM part_car_compatibility pc WHERE pc.part_id=p.id AND pc.car_id=?)");$st->execute([$pid,$cid]);$part=$st->fetch();if(!$part)throw new RuntimeException('Varaosaa ei löytynyt tälle autolle.');
            $delta=$direction==='in'?$qty:-$qty;$newQty=(float)$part['stock_qty']+$delta;if($newQty<0)throw new RuntimeException('Varastosta ei voi ottaa enemmän kuin saldoa on.');
            $db->beginTransaction();try{$db->prepare("UPDATE parts SET stock_qty=? WHERE id=?")->execute([$newQty,$pid]);$db->prepare("INSERT INTO inventory_transactions(part_id,quantity_change,event_type,note) VALUES(?,?,?,?)")->execute([$pid,$delta,$direction,post('stock_note')]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash(($direction==='in'?'Lisättiin varastoon ':'Otettiin varastosta ').dec($qty,2).' '.(string)$part['stock_unit'].'.');redirect(post('return')==='inventory'?'?view=inventory':'?car='.$cid.'#varaosat');
        }
        if($action==='clear_inventory_history'){
            if(!inventoryEnabled(appSettings($db)))throw new RuntimeException('Varaosavarasto ei ole käytössä.');
            $upTo=intpost('stock_history_up_to',-1);
            if($upTo<0||post('stock_history_confirm')!=='1')throw new RuntimeException('Vahvista varastotapahtumien poistaminen. Avaa tapahtumahistoria uudelleen.');
            $db->beginTransaction();
            // Lokin poisto ei muuta parts.stock_qty:tä eikä service_inventory_usagea.
            // Lomakkeen avaamisen jälkeen syntyneet tapahtumat jäävät talteen.
            $st=$db->prepare('DELETE FROM inventory_transactions WHERE id<=?');$st->execute([$upTo]);$removed=$st->rowCount();
            appSet($db,'inventory_history_cleared_at',date('c'));
            appSet($db,'inventory_history_cleared_by',(string)$currentUser['display_name']);
            appSet($db,'inventory_history_cleared_count',(string)$removed);
            $db->commit();flash('Varastohistoriasta poistettiin '.$removed.' tapahtumaa. Saldot ja huoltoihin liitetty varastokäyttö säilyivät.');
            redirect('?view=inventory&stock_page=1#varastotapahtumat');
        }
        if($action==='bulk_update_inventory') {
            $appNow=appSettings($db);if(!inventoryEnabled($appNow))throw new RuntimeException('Varaosavarasto ei ole käytössä.');
            $stockRows=inventoryChangedRows($db);
            $units=(array)($_POST['inventory_stock_unit']??[]);$shelves=(array)($_POST['inventory_shelf_location']??[]);$reorders=(array)($_POST['inventory_reorder_level']??[]);$prices=(array)($_POST['inventory_purchase_price']??[]);
            if(count($stockRows)>2000)throw new RuntimeException('Varastorivejä on liikaa yhdellä tallennuksella.');
            $parseNumber=static function(mixed $raw,string $label,bool $nullable=false): ?float {
                $v=trim(str_replace(',','.',(string)$raw));
                if($v===''){if($nullable)return null;throw new RuntimeException($label.' puuttuu.');}
                if(!is_numeric($v))throw new RuntimeException($label.' ei ole kelvollinen numero.');
                $n=(float)$v;if(!is_finite($n)||$n<0)throw new RuntimeException($label.' ei voi olla negatiivinen.');
                return $n;
            };
            $select=$db->prepare("SELECT id,stock_qty,stock_unit,shelf_location,reorder_level,purchase_price FROM parts WHERE id=?");
            $update=$db->prepare("UPDATE parts SET stock_qty=?,stock_unit=?,shelf_location=?,reorder_level=?,purchase_price=? WHERE id=?");
            $tx=$db->prepare("INSERT INTO inventory_transactions(part_id,quantity_change,event_type,note) VALUES(?,?,'correction','Saldon korjaus Varasto-näkymästä')");
            $changed=0;$stockChanged=0;
            $db->beginTransaction();
            try {
                foreach($stockRows as $rawId=>$rawStock){
                    $pid=(int)$rawId;if($pid<=0)continue;
                    $select->execute([$pid]);$part=$select->fetch();if(!$part)throw new RuntimeException('Varaosaa #'.$pid.' ei löytynyt.');
                    $newStock=(float)$parseNumber($rawStock,'Saldo');
                    $newUnit=trim((string)($units[$rawId]??$part['stock_unit']??'kpl'));if($newUnit==='')$newUnit='kpl';if(mb_strlen($newUnit)>20)$newUnit=mb_substr($newUnit,0,20);
                    $newShelf=trim((string)($shelves[$rawId]??$part['shelf_location']??''));
                    $newReorder=$parseNumber($reorders[$rawId]??'','Varoitusraja',true);
                    $newPrice=$parseNumber($prices[$rawId]??'','Hankintahinta',true);
                    $oldStock=(float)$part['stock_qty'];$delta=$newStock-$oldStock;
                    $different=abs($delta)>0.0000001 || $newUnit!==(string)$part['stock_unit'] || $newShelf!==(string)$part['shelf_location'] || (($newReorder===null)!==($part['reorder_level']===null)) || ($newReorder!==null&&$part['reorder_level']!==null&&abs($newReorder-(float)$part['reorder_level'])>0.0000001) || (($newPrice===null)!==($part['purchase_price']===null)) || ($newPrice!==null&&$part['purchase_price']!==null&&abs($newPrice-(float)$part['purchase_price'])>0.0000001);
                    if(!$different)continue;
                    $update->execute([$newStock,$newUnit,$newShelf,$newReorder,$newPrice,$pid]);$changed++;
                    if(abs($delta)>0.0000001){$tx->execute([$pid,$delta]);$stockChanged++;}
                }
                $db->commit();
            } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            flash($changed>0?'Varasto päivitettiin: '.$changed.' nimikettä muutettu'.($stockChanged>0?', '.$stockChanged.' saldokorjausta kirjattu historiaan.':'.'):'Varastossa ei ollut tallennettavia muutoksia.');
            redirect('?view=inventory');
        }
        if($action==='delete_part'){$cid=intpost('car_id');$pid=intpost('part_id');$st=$db->prepare("SELECT 1 FROM part_car_compatibility WHERE part_id=? AND car_id=?");$st->execute([$pid,$cid]);if(!$st->fetchColumn())throw new RuntimeException('Varaosaa ei löytynyt tälle autolle.');$used=$db->prepare("SELECT COUNT(*) FROM service_inventory_usage WHERE part_id=?");$used->execute([$pid]);if((int)$used->fetchColumn()>0)throw new RuntimeException('Varaosaa on käytetty huoltoon varastosta. Säilytä nimike, jotta huollon varastokäyttö voidaan myöhemmin palauttaa turvallisesti.');$db->prepare("DELETE FROM parts WHERE id=?")->execute([$pid]);flash('Varaosa poistettiin kaikista siihen liitetyistä autoista.','warn');redirect('?car='.$cid.'#varaosat');}
        if($action==='delete_photo'){$cid=intpost('car_id');$pid=intpost('photo_id');$st=$db->prepare("SELECT sp.file_path FROM service_photos sp JOIN services s ON s.id=sp.service_id WHERE sp.id=? AND s.car_id=?");$st->execute([$pid,$cid]);$fp=$st->fetchColumn();if($fp===false)throw new RuntimeException('Kuvaa ei löytynyt.');$db->prepare("DELETE FROM service_photos WHERE id=?")->execute([$pid]);deletePhotoFile((string)$fp);flash('Kuva poistettiin.','warn');redirect('?car='.$cid.'#historia');}
        if($action==='delete_service'){
            $cid=intpost('car_id');$sid=intpost('service_id');
            $serviceSt=$db->prepare("SELECT odometer,service_date,title FROM services WHERE id=? AND car_id=?");$serviceSt->execute([$sid,$cid]);$serviceRow=$serviceSt->fetch();if(!$serviceRow)throw new RuntimeException('Huoltoa ei löytynyt.');$serviceOdo=(int)$serviceRow['odometer'];
            $carBefore=getCar($db,$cid);if(!$carBefore)throw new RuntimeException('Autoa ei löytynyt.');$recalcAfterDelete=$serviceOdo===(int)$carBefore['current_km'];
            $inv=$db->prepare("SELECT * FROM invoices WHERE service_id=?");$inv->execute([$sid]);$linkedInvoice=$inv->fetch();
            if($linkedInvoice && !invoiceCanDelete($linkedInvoice)){
                throw new RuntimeException('Huollolla on lasku '.(string)$linkedInvoice['invoice_number'].' ('.(string)$linkedInvoice['status'].'). Lähetettyä, maksettua tai hyvitettyä laskua ei poisteta huollon mukana.');
            }
            $photoPaths=array_column(getServicePhotos($db,$sid),'file_path');
            $db->beginTransaction();
            try{
                if($linkedInvoice)$db->prepare("DELETE FROM invoices WHERE id=? AND status='Luonnos' AND sent_at='' AND paid_at='' AND credited_at=''")->execute([(int)$linkedInvoice['id']]);
                $restoredStock=restoreServiceInventoryUsage($db,$sid,$cid,(string)$serviceRow['service_date'],(string)$serviceRow['title']);
                $st=$db->prepare("DELETE FROM services WHERE id=? AND car_id=?");$st->execute([$sid,$cid]);
                if($st->rowCount()!==1)throw new RuntimeException('Huoltoa ei löytynyt.');
                if($recalcAfterDelete)recalculateCurrentKm($db,$cid);
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            foreach($photoPaths as $fp)deletePhotoFile((string)$fp);
            $msg='Huolto poistettiin.';if(($restoredStock??0)>0)$msg.=' Huollossa varastosta käytetyt nimikkeet palautettiin saldoon.';if($linkedInvoice)$msg.=' Myös laskuluonnos '.(string)$linkedInvoice['invoice_number'].' poistettiin.';
            flash($msg,'warn');redirect('?car='.$cid.'#historia');
        }
        if($action==='delete_car'){
            $cid=intpost('car_id');$cnt=$db->prepare("SELECT COUNT(*) FROM invoices i JOIN services s ON s.id=i.service_id WHERE s.car_id=?");$cnt->execute([$cid]);if((int)$cnt->fetchColumn()>0)throw new RuntimeException('Autolla on laskuja. Autoa ei poisteta ennen laskujen käsittelyä.');$st=$db->prepare("SELECT file_path FROM service_photos WHERE car_id=?");$st->execute([$cid]);$photoPaths=$st->fetchAll(PDO::FETCH_COLUMN);
            $shared=$db->prepare("SELECT p.id,(SELECT pc2.car_id FROM part_car_compatibility pc2 WHERE pc2.part_id=p.id AND pc2.car_id<>? ORDER BY pc2.car_id LIMIT 1) replacement_car_id FROM parts p WHERE p.car_id=?");$shared->execute([$cid,$cid]);$reassigned=0;
            $db->beginTransaction();try{foreach($shared->fetchAll() as $pr){$replacement=(int)($pr['replacement_car_id']??0);if($replacement>0){$db->prepare("UPDATE parts SET car_id=? WHERE id=?")->execute([$replacement,(int)$pr['id']]);$reassigned++;}}/* Huollot vapauttavat varastokäytön viittaukset ennen omien varaosien kaskadipoistoa. */$db->prepare("DELETE FROM services WHERE car_id=?")->execute([$cid]);$db->prepare("DELETE FROM cars WHERE id=?")->execute([$cid]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            foreach($photoPaths as $fp)deletePhotoFile((string)$fp);$msg='Auto ja sen tiedot poistettiin.';if($reassigned>0)$msg.=' '.($reassigned).' myös muille autoille sopivaa varaosaa säilytettiin varastossa.';flash($msg,'warn');redirect('?');
        }

        if($action==='create_invoice'){
            $app=appSettings($db); if(!invoicingEnabled($app))throw new RuntimeException('Laskutus ei ole käytössä. Ota se käyttöön Asetukset-sivulta.');
            $sid=intpost('service_id');$s=serviceWithDetails($db,$sid);if(!$s)throw new RuntimeException('Huoltoa ei löytynyt.');
            $st=$db->prepare("SELECT id FROM invoices WHERE service_id=?");$st->execute([$sid]);$existing=(int)$st->fetchColumn();if($existing)redirect('?invoice='.$existing);
            $number=nextInvoiceNumber($db);$issue=date('Y-m-d');$due=addMonths($issue,0);$due=date('Y-m-d',strtotime('+'.max(0,(int)($app['payment_days']??14)).' days',strtotime($issue)));
            $invoiceCustomerName=trim((string)($s['customer_name_snapshot']??''));$invoiceCustomerAddress=(string)($s['customer_address_snapshot']??'');$invoiceCustomerEmail=(string)($s['customer_email_snapshot']??'');$invoiceCustomerBusiness=(string)($s['customer_business_id_snapshot']??'');if($invoiceCustomerName===''){if(customersEnabled($app)&&!empty($s['current_customer_id']))throw new RuntimeException('Laskua ei muodostettu: vanhalta huollolta puuttuu tapahtumahetken asiakassnapshot. Täydennä oikea asiakas auton Asiakkuus-osiossa ennen laskun muodostamista.');$invoiceCustomerName=(string)($s['owner']??'');$invoiceCustomerAddress=(string)($s['customer_address']??'');$invoiceCustomerEmail=(string)($s['customer_email']??'');$invoiceCustomerBusiness=(string)($s['customer_business_id']??'');}
            $db->beginTransaction();$st=$db->prepare("INSERT INTO invoices(service_id,invoice_number,issue_date,due_date,status,seller_name,seller_business_id,seller_address,seller_email,seller_phone,seller_iban,seller_bic,seller_mobilepay_enabled,seller_mobilepay_number,seller_mobilepay_name,seller_logo_path,customer_name,customer_address,customer_email,customer_business_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$st->execute([$sid,$number,$issue,$due,'Luonnos',$app['shop_name']??DEFAULT_APP_NAME,$app['business_id']??'',$app['shop_address']??'',$app['shop_email']??'',$app['shop_phone']??'',$app['iban']??'',$app['bic']??'',(($app['mobilepay_enabled']??'0')==='1'?1:0),$app['mobilepay_number']??'',$app['mobilepay_name']??'',$app['logo_path']??'',$invoiceCustomerName,$invoiceCustomerAddress,$invoiceCustomerEmail,$invoiceCustomerBusiness]);$iid=(int)$db->lastInsertId();
            $vat=(float)($app['vat_rate']??25.5);$line=$db->prepare("INSERT INTO invoice_lines(invoice_id,description,qty,unit,unit_price_net,vat_rate,sort_order) VALUES(?,?,?,?,?,?,?)");$sort=10;$lineCount=0;
            $hours=(float)($s['labor_hours']??0);$rate=$s['labor_rate']!==null?(float)$s['labor_rate']:(float)($app['hourly_rate']??65);$laborVat=$s['labor_vat_rate']!==null?(float)$s['labor_vat_rate']:$vat;if($hours>0){$line->execute([$iid,'Työ: '.$s['title'],$hours,'h',$rate,$laborVat,$sort]);$lineCount++;}$sort+=10;
            foreach($s['actions'] as $a){if($a['price_net']===null)continue;$desc=$a['label'];$extra=trim(implode(' ',array_filter([$a['brand'],$a['supplier_sku']])));if($extra)$desc.=' · '.$extra;$qty=$a['quantity']!==null?(float)$a['quantity']:1.0;$unit=(string)($a['unit']?:'kpl');$rowVat=$a['vat_rate']!==null?(float)$a['vat_rate']:$vat;$line->execute([$iid,$desc,$qty,$unit,(float)$a['price_net'],$rowVat,$sort]);$lineCount++;$sort+=10;}
            foreach($s['custom_actions'] as $a){if($a['price_net']===null)continue;$rowVat=$a['vat_rate']!==null?(float)$a['vat_rate']:$vat;$line->execute([$iid,$a['description'],1,'kpl',(float)$a['price_net'],$rowVat,$sort]);$lineCount++;$sort+=10;}
            if($lineCount===0){$db->rollBack();throw new RuntimeException('Laskua ei muodostettu: huollolla ei ole yhtään hinnoiteltua laskuriviä. Lisää osalle tai muulle työlle hinta, tai lisää laskutettavaa työaikaa.');}$db->commit();flash('Laskuluonnos muodostettiin.');redirect('?invoice='.$iid);
        }
        if($action==='update_invoice_status'){
            $iid=intpost('invoice_id');$status=post('status');$st=$db->prepare('SELECT * FROM invoices WHERE id=?');$st->execute([$iid]);$invoiceRow=$st->fetch();
            if(!$invoiceRow)throw new RuntimeException('Laskua ei löytynyt.');
            if(!in_array($status,invoiceAllowedStatuses($invoiceRow),true))throw new RuntimeException('Laskun tilaa ei voi palauttaa taaksepäin. Lähetys-, maksu- ja hyvityshistoria säilytetään.');
            $now=date('Y-m-d H:i:s');
            $db->prepare("UPDATE invoices SET status=?,sent_at=CASE WHEN sent_at='' AND status='Luonnos' AND ? IN ('Lähetetty','Maksettu') THEN ? ELSE sent_at END,paid_at=CASE WHEN paid_at='' AND ?='Maksettu' THEN ? ELSE paid_at END,credited_at=CASE WHEN credited_at='' AND ?='Hyvitetty' THEN ? ELSE credited_at END WHERE id=?")->execute([$status,$status,$now,$status,$now,$status,$now,$iid]);
            flash('Laskun tila päivitettiin.');redirect('?invoice='.$iid);
        }
        if($action==='delete_invoice'){
            $iid=intpost('invoice_id');
            $st=$db->prepare("SELECT i.*,s.car_id FROM invoices i JOIN services s ON s.id=i.service_id WHERE i.id=?");$st->execute([$iid]);$inv=$st->fetch();
            if(!$inv)throw new RuntimeException('Laskua ei löytynyt.');
            if(!invoiceCanDelete($inv))throw new RuntimeException('Vain Luonnos-tilassa olevan laskun voi poistaa. Lähetetty, maksettu tai hyvitetty lasku säilytetään historiassa.');
            $db->prepare("DELETE FROM invoices WHERE id=? AND status='Luonnos' AND sent_at='' AND paid_at='' AND credited_at=''")->execute([$iid]);
            flash('Laskuluonnos '.(string)$inv['invoice_number'].' poistettiin. Huolto säilyi.','warn');redirect('?car='.(int)$inv['car_id'].'#historia');
        }

        if($action==='accept_data_issue'){
            $key=post('issue_key');if(!preg_match('/^[A-Za-z0-9._:-]{1,128}$/',$key))throw new RuntimeException('Virheellinen tarkistuksen tunniste.');
            $st=$db->prepare("INSERT INTO data_issue_ignores(issue_key,issue_title,issue_detail,note,accepted_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(issue_key) DO UPDATE SET issue_title=excluded.issue_title,issue_detail=excluded.issue_detail,note=excluded.note,accepted_at=CURRENT_TIMESTAMP");$st->execute([$key,post('issue_title'),post('issue_detail'),post('issue_note')]);
            flash('Poikkeama hyväksyttiin. Dataa ei muutettu.');redirect('?view=settings#datacheck');
        }
        if($action==='accept_data_issues'){
            $grp=post('issue_group');if(!in_array($grp,['img','warn','info'],true))throw new RuntimeException('Virheellinen poikkeamaryhmä.');
            $st=$db->prepare("INSERT INTO data_issue_ignores(issue_key,issue_title,issue_detail,note,accepted_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(issue_key) DO UPDATE SET issue_title=excluded.issue_title,issue_detail=excluded.issue_detail,note=excluded.note,accepted_at=CURRENT_TIMESTAMP");
            $n=0;$db->beginTransaction();
            foreach(dataCheckIssues($db) as $di){if(dataIssueGroup($di)!==$grp||!preg_match('/^[A-Za-z0-9._:-]{1,128}$/',(string)$di['key']))continue;$st->execute([$di['key'],$di['title'],$di['detail'],'Hyväksytty lähdeaineiston poikkeamaksi']);$n++;}
            $db->commit();
            flash($n>0?'Hyväksyttiin '.$n.' poikkeamaa. Dataa ei muutettu.':'Ryhmässä ei ollut hyväksyttäviä poikkeamia.');redirect('?view=settings#datacheck');
        }
        if($action==='restore_data_issue'){
            $key=post('issue_key');if(!preg_match('/^[A-Za-z0-9._:-]{1,128}$/',$key))throw new RuntimeException('Virheellinen tarkistuksen tunniste.');$db->prepare("DELETE FROM data_issue_ignores WHERE issue_key=?")->execute([$key]);flash('Poikkeama palautettiin tarkistuslistalle.');redirect('?view=settings#datacheck');
        }

        if($action==='restore_db'){
            if(post('restore_phrase')!=='PALAUTA'||empty($_POST['restore_check']))throw new RuntimeException('Palautusta ei vahvistettu. Rastita varmistus ja kirjoita PALAUTA.');
            if(empty($_FILES['restore_file']['tmp_name'])||($_FILES['restore_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Valitse palautettava SQLite- tai ZIP-varmuuskopio.');
            $message=backupRestore($db,(string)$_FILES['restore_file']['tmp_name']);
            authForget();flash($message.' Palautetun varmuuskopion käyttäjät ja oikeudet ovat käytössä. Kirjaudu uudelleen.','warn');redirect('?');
        }
    } catch(Throwable $e){ if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack(); flash($e->getMessage(),'error');if(post('return')==='inventory')redirect('?view=inventory');$cid=intpost('car_id');redirect($cid?'?car='.$cid:($action==='clear_inventory_history'?'?view=inventory&stock_page=1#varastotapahtumat':((($_POST['action']??'')==='restore_db'||($_POST['action']??'')==='save_app_settings')?'?view=settings':'?'))); }
}

/* ------------------------------ Excel-vienti ------------------------------ */
if(($_GET['export']??'')==='xlsx'){ exportCarXlsx($db,(int)($_GET['car']??0)); }
if(($_GET['export']??'')==='all_xlsx'){ exportAllCarsXlsx($db); }
if(($_GET['export']??'')==='inventory_xlsx'){ if(!inventoryEnabled($app)){http_response_code(404);exit('Varaosavarasto ei ole käytössä.');} exportInventoryXlsx($db); }

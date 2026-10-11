<?php
declare(strict_types=1);

/**
 * app/migrate.php – tietokannan päivitys vanhemmasta skeemasta.
 * Skeema 12 → 13 (versio 1.1.0): kannassa olevat valmiit arvot muutetaan suomenkielisistä teksteistä kielineutraaleiksi
 * koodeiksi (ks. app/vocab.php), jolloin ne näytetään käyttöliittymän kielellä. Taulujen rakenne ei muutu.
 *  - toimenpide, huoltotyyppi, yksikkö, laskun tila ja kohteen ryhmä → koodit
 *  - valmiiden kohteiden nimet → tyhjä (nimi tulee kielitiedostosta); käyttäjän muuttamat nimet säilyvät
 *  - käyttäjän oma kohde, jonka nimi on sama kuin uuden valmiin kohteen (esim. "Ilmastointihuolto"), muuttuu valmiiksi kohteeksi
 *  - ohjelman omat muistiinpanot → @koodi; mekaanikko "Muu" → @other; etusivun oletusotsikot → @default
 *  - hyväksytyt tarkistuspoikkeamat saavat kielineutraalin tunnisteen
 * Ennen muutoksia otetaan turvakopio kannasta tiedoston viereen (.pre-migration-…); jos kopiointi epäonnistuu, mitään ei muuteta.
 * Päivitys on yksi tapahtuma: virheessä kanta jää ennalleen.
 */

/** Päivittää koodin muotoon vaiheittain: muuttaa vain eroavat arvot. */
function migrateMapColumn(PDO $db,string $table,string $column,callable $map): int {
    $changed=0;$upd=$db->prepare('UPDATE "'.$table.'" SET "'.$column.'"=? WHERE "'.$column.'"=?');
    foreach($db->query('SELECT DISTINCT "'.$column.'" FROM "'.$table.'"')->fetchAll(PDO::FETCH_COLUMN) as $old){
        $new=$map((string)$old);
        if($new!==(string)$old){$upd->execute([$new,(string)$old]);$changed+=$upd->rowCount();}
    }
    return $changed;
}
/** Vaihtaa huoltokohteen tunnisteen kaikissa tauluissa (viiteavaimet ovat tapahtuman ajan pois päältä). */
function migrateRenameItemKey(PDO $db,string $old,string $new): void {
    foreach(['car_item_settings','service_actions','parts','service_inventory_usage'] as $table)
        $db->prepare('UPDATE "'.$table.'" SET item_key=? WHERE item_key=?')->execute([$new,$old]);
    $db->prepare('UPDATE item_catalog SET item_key=? WHERE item_key=?')->execute([$new,$old]);
}

function migrateSchema12To13(PDO $db): void {
    $previousLang=i18nLang();i18nLang('fi'); /* vanhan kannan tekstit ovat suomea */
    try{
        $dir=dirname(DB_FILE);
        $copy=$dir.'/.pre-migration-12-to-13-'.date('Ymd-His').'.sqlite3';
        try{ consistentDatabaseCopy($db,$copy); }
        catch(Throwable $e){ throw new RuntimeException(t('db.err_migration_backup',['message'=>$e->getMessage()]),0,$e); }

        /* Hyväksyttyjen poikkeamien vanhat tunnisteet (tekstin tiiviste) muunnetaan ennen kuin mitään muuta muutetaan. */
        $issueKeyMap=[];
        try{foreach(dataCheckIssues($db,true) as $di)if(!empty($di['legacy_key'])&&$di['legacy_key']!==$di['key'])$issueKeyMap[(string)$di['legacy_key']]=(string)$di['key'];}catch(Throwable){}

        $db->exec('PRAGMA foreign_keys=OFF');
        $db->beginTransaction();
        try{
            $lowerFi=fn(string $key)=>mb_strtolower((string)(i18nCatalog('fi')['item.'.$key]??''));

            /* 1. Valmiit kohteet: nimi tyhjäksi, jos se on oletusnimi. Oma kohde, jonka nimi on uuden valmiin kohteen nimi, muuttuu valmiiksi. */
            foreach($db->query('SELECT item_key,label,is_custom FROM item_catalog')->fetchAll() as $r){
                if((int)$r['is_custom']===0&&mb_strtolower(trim((string)$r['label']))===$lowerFi((string)$r['item_key']))
                    $db->prepare("UPDATE item_catalog SET label='' WHERE item_key=?")->execute([$r['item_key']]);
            }
            $have=array_flip($db->query('SELECT item_key FROM item_catalog')->fetchAll(PDO::FETCH_COLUMN));
            foreach(defaultMaintenanceItems() as $def){
                [$key,$section]=$def;if(isset($have[$key]))continue;
                $st=$db->prepare("SELECT item_key FROM item_catalog WHERE is_custom=1 AND LOWER(TRIM(label))=? ORDER BY sort_order LIMIT 1");$st->execute([$lowerFi($key)]);
                $custom=$st->fetchColumn();
                if($custom===false||$lowerFi($key)==='')continue;
                migrateRenameItemKey($db,(string)$custom,$key);
                /* Kohteen oma tyyppi, oletustoimenpide ja huoltovälit säilyvät ennallaan; vain tunniste ja nimen lähde muuttuvat. */
                $db->prepare("UPDATE item_catalog SET is_custom=0,label='' WHERE item_key=?")->execute([$key]);
                $have[$key]=true;
            }
            foreach($db->query('SELECT item_key FROM item_catalog WHERE is_custom=0')->fetchAll(PDO::FETCH_COLUMN) as $key){
                $name=$lowerFi((string)$key);if($name==='')continue;
                $db->prepare("UPDATE service_actions SET item_label_snapshot='' WHERE item_key=? AND LOWER(TRIM(item_label_snapshot))=?")->execute([$key,$name]);
            }
            insertStandardItems($db,false); /* uudet valmiit kohteet lisätään pois päältä olevina */

            /* 2. Sanastoarvot. Tuntematon toimenpide/huoltotyyppi/tila saa saman oletuksen kuin ohjelma ennenkin. */
            migrateMapColumn($db,'item_catalog','default_action',fn($v)=>vocabCode('action',$v,'replaced'));
            migrateMapColumn($db,'service_actions','action',fn($v)=>vocabCode('action',$v,'replaced'));
            migrateMapColumn($db,'services','service_type',fn($v)=>vocabCode('stype',$v,'scheduled'));
            migrateMapColumn($db,'invoices','status',fn($v)=>vocabCode('invstatus',$v,'draft'));
            foreach([['item_catalog','default_unit'],['service_actions','unit'],['parts','stock_unit'],['invoice_lines','unit'],['service_inventory_usage','unit']] as [$table,$col])
                migrateMapColumn($db,$table,$col,fn($v)=>$v===''?$v:vocabNormalize('unit',$v));
            foreach([['item_catalog','section'],['service_actions','item_section_snapshot']] as [$table,$col])
                migrateMapColumn($db,$table,$col,fn($v)=>$v===''?$v:vocabNormalize('section',$v));

            /* 3. Ohjelman omat muistiinpanot, mekaanikko "Muu" ja etusivun oletusotsikot. */
            $notes=noteLegacyFi();
            foreach([['inventory_transactions','note'],['odometer_readings','note'],['parts','notes'],['data_issue_ignores','note']] as [$table,$col])
                migrateMapColumn($db,$table,$col,fn($v)=>$notes[$v]??$v);
            foreach([['mechanics','name'],['services','mechanic_name_snapshot']] as [$table,$col])
                migrateMapColumn($db,$table,$col,fn($v)=>mb_strtolower(trim($v))==='muu'?MECHANIC_OTHER:$v);
            $db->prepare("UPDATE app_settings SET setting_value=? WHERE setting_key='home_title' AND TRIM(setting_value) IN (?,'')")->execute([HOME_TEXT_DEFAULT,DEFAULT_HOME_TITLE]);
            $db->prepare("UPDATE app_settings SET setting_value=? WHERE setting_key='home_subtitle' AND TRIM(setting_value)=?")->execute([HOME_TEXT_DEFAULT,DEFAULT_HOME_SUBTITLE]);

            /* 4. Hyväksytyt poikkeamat: uusi kielineutraali tunniste vanhan tekstitiivisteen tilalle. */
            $exists=$db->prepare('SELECT COUNT(*) FROM data_issue_ignores WHERE issue_key=?');$setKey=$db->prepare('UPDATE data_issue_ignores SET issue_key=? WHERE issue_key=?');
            foreach($issueKeyMap as $old=>$new){$exists->execute([$new]);if((int)$exists->fetchColumn()===0)$setKey->execute([$new,$old]);}

            $db->prepare("UPDATE app_settings SET setting_value=? WHERE setting_key='schema_version'")->execute(['13']);
            $fk=$db->query('PRAGMA foreign_key_check')->fetchAll();
            if($fk)throw new RuntimeException(t('db.err_migration_fk',['n'=>count($fk)]));
            $db->commit();
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw new RuntimeException(t('db.err_migration_failed',['message'=>$e->getMessage(),'copy'=>basename($copy)]),0,$e);
        }finally{
            $db->exec('PRAGMA foreign_keys=ON');
        }
    }finally{
        i18nLang($previousLang);
    }
}

/**
 * Skeema 13 → 14 (versio 1.2.4): laskulle tulee omat sarakkeet viitenumerolle (tallennetaan laskua luotaessa, ei lasketa myöhemmin uudelleen),
 * vapaalle huomautukselle sekä sähköpostilähetyksen ajalle ja vastaanottajalle. Vanhojen laskujen viite täytetään nykyisellä säännöllä
 * (laskunumeron numerot + tarkiste), joten jo lähetettyjen laskujen viitteet eivät muutu. Turvakopio otetaan ensin; virheessä kanta jää ennalleen.
 */
function migrateSchema13To14(PDO $db): void {
    $dir=dirname(DB_FILE);
    $copy=$dir.'/.pre-migration-13-to-14-'.date('Ymd-His').'.sqlite3';
    try{ consistentDatabaseCopy($db,$copy); }
    catch(Throwable $e){ throw new RuntimeException(t('db.err_migration_backup',['message'=>$e->getMessage()]),0,$e); }
    $db->beginTransaction();
    try{
        $have=[];foreach($db->query('PRAGMA table_info("invoices")')->fetchAll() as $c)$have[(string)$c['name']]=true;
        foreach(['reference','note','emailed_at','emailed_to'] as $col)if(!isset($have[$col]))$db->exec('ALTER TABLE invoices ADD COLUMN '.$col." TEXT NOT NULL DEFAULT ''");
        $used=[];$upd=$db->prepare('UPDATE invoices SET reference=? WHERE id=?');
        foreach($db->query("SELECT id,invoice_number FROM invoices WHERE reference='' ORDER BY id")->fetchAll() as $r){
            $ref=invoiceReference((string)$r['invoice_number']);
            if($ref===''||isset($used[$ref]))continue;
            $used[$ref]=true;$upd->execute([$ref,(int)$r['id']]);
        }
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_invoices_reference ON invoices(reference) WHERE reference<>''");
        $db->prepare("UPDATE app_settings SET setting_value=? WHERE setting_key='schema_version'")->execute(['14']);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw new RuntimeException(t('db.err_migration_failed',['message'=>$e->getMessage(),'copy'=>basename($copy)]),0,$e);
    }
    /* Huoltokuvien JPEG-esikatselut tehtiin ennen 1.2.4:ää ilman EXIF-asennon huomiointia (pystykuvat kyljellään). Poistetaan ne; ne syntyvät automaattisesti uudelleen oikein päin, kun kuva näytetään. Alkuperäisiin ei kosketa. */
    try{
        if(is_dir(IMAGE_DIR))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(IMAGE_DIR,FilesystemIterator::SKIP_DOTS)) as $f){
            if($f->isLink()||!$f->isFile())continue;$path=str_replace('\\','/',$f->getPathname());
            if(str_contains($path,'/logo/'))continue;
            if(preg_match('/\.(?:thumb|web)\.jpe?g$/i',$path))@unlink($path);
        }
    }catch(Throwable $e){}
}

/**
 * Skeema 14 → 15 (versio 1.2.7): auton vanhat omistaja- ja asiakastiedot (cars.owner, customer_address, customer_email, customer_phone,
 * customer_business_id) poistuvat; asiakastiedot hoitaa asiakasrekisteri, omistushistoria ja huoltojen asiakaskopiot.
 * Ettei mitään tietoa katoa, autosta, jolla ei ole asiakaslinkkiä mutta jolla on vanha omistajan nimi, luodaan ensin asiakas (samat tiedot
 * yhdistetään yhdeksi asiakkaaksi) ja auto linkitetään siihen. Sen jälkeen sarakkeet poistetaan (SQLite 3.35+; jos poisto ei onnistu,
 * sarakkeet jäävät käyttämättöminä paikoilleen). Samalla poistetaan vanhojen versioiden käyttämättömät asetusrivit. Turvakopio otetaan ensin.
 */
function migrateSchema14To15(PDO $db): void {
    $dir=dirname(DB_FILE);
    $copy=$dir.'/.pre-migration-14-to-15-'.date('Ymd-His').'.sqlite3';
    try{ consistentDatabaseCopy($db,$copy); }
    catch(Throwable $e){ throw new RuntimeException(t('db.err_migration_backup',['message'=>$e->getMessage()]),0,$e); }
    $db->beginTransaction();
    try{
        $have=[];foreach($db->query('PRAGMA table_info("cars")')->fetchAll() as $c)$have[(string)$c['name']]=true;
        $legacy=array_values(array_filter(['owner','customer_address','customer_email','customer_phone','customer_business_id'],fn($c)=>isset($have[$c])));
        if(isset($have['owner'])){
            $sel=array_map(fn($c)=>isset($have[$c])?$c:"'' AS $c",['owner','customer_address','customer_email','customer_phone','customer_business_id']);
            $rows=$db->query('SELECT id,'.implode(',',$sel)." FROM cars WHERE (current_customer_id IS NULL OR current_customer_id=0) AND TRIM(owner)<>'' ORDER BY id")->fetchAll();
            $made=[];
            $insCustomer=$db->prepare("INSERT INTO customers(customer_number,customer_type,name,phone,email,address,business_id,notes,active) VALUES(?,?,?,?,?,?,?,'Luotu auton vanhoista asiakastiedoista',1)");
            $insHistory=$db->prepare("INSERT INTO car_customer_history(car_id,customer_id,start_date,end_date,customer_name_snapshot,notes) VALUES(?,?,'','',?,'Alkuperäinen alkupäivä ei tiedossa')");
            $link=$db->prepare('UPDATE cars SET current_customer_id=? WHERE id=?');
            foreach($rows as $r){
                $name=trim((string)$r['owner']);$email=trim((string)$r['customer_email']);$phone=trim((string)$r['customer_phone']);$bid=trim((string)$r['customer_business_id']);$addr=trim((string)$r['customer_address']);
                $key=mb_strtolower($name).'|'.mb_strtolower($email).'|'.$phone.'|'.$bid.'|'.mb_strtolower($addr);
                if(!isset($made[$key])){
                    $insCustomer->execute([nextCustomerNumber($db),($bid!==''||preg_match('/\\b(oy|oyj|ab|ky|tmi|ltd|gmbh)\\.?$/iu',$name))?'company':'private',$name,$phone,$email,$addr,$bid]);
                    $made[$key]=(int)$db->lastInsertId();
                }
                $link->execute([$made[$key],(int)$r['id']]);
                $insHistory->execute([(int)$r['id'],$made[$key],$name]);
            }
        }
        $db->exec("DELETE FROM data_issue_ignores WHERE issue_key LIKE 'unlinked-customer-car-%'");
        $db->exec("DELETE FROM app_settings WHERE setting_key IN ('auth_legacy_setup','migration_060_service_types','migration_070_item_profiles','migration_077_feature_flags','migration_0822_part_compatibility','feature_0824_belt_inspections','use_mode')");
        foreach($legacy as $col){
            try{ $db->exec('ALTER TABLE cars DROP COLUMN "'.$col.'"'); }
            catch(Throwable $e){ /* vanha SQLite (alle 3.35): sarake jää käyttämättömänä, sillä on oletusarvo eikä se haittaa */ }
        }
        $db->prepare("UPDATE app_settings SET setting_value=? WHERE setting_key='schema_version'")->execute([(string)SCHEMA_VERSION]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw new RuntimeException(t('db.err_migration_failed',['message'=>$e->getMessage(),'copy'=>basename($copy)]),0,$e);
    }
}

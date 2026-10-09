<?php
declare(strict_types=1);

/**
 * app/edits.php – lomakkeen muokkausristiriidat: tilannekuva, allekirjoitettu versio ja ristiriitojen tunnistus.
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

/* Lomakkeen tilannekuva estää vanhentuneiden tietojen ylikirjoituksen.
   Ei uusia kantatauluja: sisältötiiviste tunnistaa myös saman sekunnin muutokset.
   Tarkistus ja kirjoitus tapahtuvat saman sovelluksen käyttölukon sisällä. */
function editRows(PDO $db,string $sql,array $params): array {
    $st=$db->prepare($sql);$st->execute($params);return $st->fetchAll();
}
function editState(PDO $db,string $kind,int $id): string {
    $state=[];
    switch($kind){
        case 'car':
            $state=editRows($db,'SELECT * FROM cars WHERE id=?',[$id]);
            // Huollon uusi kilometrilukema ei muuta auton perustietolomakkeen kirjoittamia kenttiä.
            foreach($state as &$row){unset($row['current_km'],$row['updated_at']);}unset($row);break;
        case 'service':
            $state[]=editRows($db,'SELECT * FROM services WHERE id=?',[$id]);
            foreach(['service_actions','service_custom_actions','service_photos','service_inventory_usage'] as $table)$state[]=editRows($db,"SELECT * FROM $table WHERE service_id=? ORDER BY id",[$id]);
            $state[]=editRows($db,"SELECT setting_key,setting_value FROM app_settings WHERE setting_key IN ('price_input_mode','vat_rate','feature_invoicing') ORDER BY setting_key",[]);break;
        case 'part':case 'inventory':
            $state[]=editRows($db,'SELECT * FROM parts WHERE id=?',[$id]);
            $state[]=editRows($db,'SELECT * FROM part_car_compatibility WHERE part_id=? ORDER BY car_id',[$id]);break;
        case 'program':
            $state[]=editRows($db,'SELECT * FROM car_item_settings WHERE car_id=? ORDER BY item_key',[$id]);
            $state[]=editRows($db,'SELECT * FROM item_catalog ORDER BY item_key',[]);break;
        case 'ownership':
            $state[]=editRows($db,'SELECT id,current_customer_id FROM cars WHERE id=?',[$id]);
            $state[]=editRows($db,'SELECT * FROM car_customer_history WHERE car_id=? ORDER BY id',[$id]);break;
        case 'customer':$state=editRows($db,'SELECT * FROM customers WHERE id=?',[$id]);break;
        case 'invoice':$state=editRows($db,'SELECT * FROM invoices WHERE id=?',[$id]);break;
        default:throw new LogicException('Tuntematon lomakkeen kohde.');
    }
    return hash('sha256',json_encode($state,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR));
}
function editSignature(PDO $db,string $kind,int $id,string $payload): string {
    // Allekirjoitus käyttää vain palvelimella olevaa käyttäjän salasanatiivistettä.
    // Ei erillistä lomakeavainta istuntoon: istunnonvaihto ja palautus sitovat tokenin uudelleen.
    $user=$GLOBALS['currentUser']??null;
    if(!$user||empty($user['password_hash']))throw new LogicException('Lomakkeen käyttäjä puuttuu.');
    return hash_hmac('sha256','autohuolto-edit-v1:'.$kind.':'.$id.':'.authValue($db,'auth_install_id').':'.(int)$user['id'].':'.csrf().':'.$payload,(string)$user['password_hash']);
}
function editToken(PDO $db,string $kind,int $id,?array $values=null): string {
    $payload=rtrim(strtr(base64_encode(json_encode(['state'=>editState($db,$kind,$id),'values'=>$values],JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR)),'+/','-_'),'=');
    return $payload.'.'.editSignature($db,$kind,$id,$payload);
}
function editReadToken(PDO $db,string $kind,int $id,mixed $token): array {
    if(!is_string($token)||strlen($token)>20000||!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D',$token,$m)||!hash_equals(editSignature($db,$kind,$id,$m[1]),$m[2]))editConflict('Lomakkeen tarkistustieto puuttuu tai on vanhentunut. Avaa ajantasainen lomake uudelleen.');
    $data=json_decode((string)base64_decode(strtr($m[1],'-_','+/'),true),true);
    if(!is_array($data)||!is_string($data['state']??null))editConflict('Lomakkeen tarkistustieto ei ole kelvollinen.');
    return $data;
}
function editField(PDO $db,string $kind,int $id): void {
    if($id>0)echo '<input type="hidden" name="edit_version" value="'.h(editToken($db,$kind,$id)).'">';
}
function editRequire(PDO $db,string $action): void {
    $map=['save_car'=>['car','car_id'],'update_service'=>['service','service_id'],'update_part'=>['part','part_id'],'save_item_settings'=>['program','car_id'],'update_customer'=>['customer','customer_id'],'set_car_customer'=>['ownership','car_id'],'update_car_customer_history'=>['ownership','car_id'],'update_invoice_status'=>['invoice','invoice_id']];
    if(!isset($map[$action]))return;[$kind,$field]=$map[$action];$id=intpost($field);
    if($action==='save_car'&&$id<=0)return;
    $data=editReadToken($db,$kind,$id,$_POST['edit_version']??null);
    if(!hash_equals(editState($db,$kind,$id),$data['state']))editConflict('Näitä tietoja on muutettu lomakkeen avaamisen jälkeen. Tallennus estettiin, jotta aiemmat muutokset säilyvät.');
}
function editConflict(string $message): never {
    global $db,$appName;
    if($db instanceof PDO&&$db->inTransaction())$db->rollBack();
    http_response_code(409);header('Cache-Control: no-store');
    $action=post('action');$cid=intpost('car_id');$target='?';
    if($action==='bulk_update_inventory')$target='?view=inventory';
    elseif($action==='update_customer')$target='?view=customers&customer='.intpost('customer_id');
    elseif($action==='update_invoice_status')$target='?invoice='.intpost('invoice_id');
    elseif($cid>0){$target='?car='.$cid;if($action==='update_service')$target.='&edit_service='.intpost('service_id').'#huolto';elseif($action==='save_car')$target.='#autontiedot';elseif($action==='update_part')$target.='#varaosat';elseif($action==='save_item_settings')$target.='#ohjelma';else $target.='#asiakkuus';}
    $labels=['car_id'=>'Auton numero','service_id'=>'Huollon numero','part_id'=>'Varaosan numero','service_origin'=>'Työn alkuperä','first_registration_date'=>'Ensirekisteröinti','current_km'=>'Nykyiset kilometrit','reg_plate'=>'Rekisteri','nickname'=>'Lempinimi','owner'=>'Omistaja / käyttäjä','make'=>'Merkki','model'=>'Malli','year'=>'Vuosimalli','engine'=>'Moottori','vin'=>'VIN','notes'=>'Muistiinpanot','service_date'=>'Huoltopäivä','odometer'=>'Kilometrit','title'=>'Otsikko','service_type'=>'Huollon tyyppi','service_notes'=>'Huollon muistiinpanot','workshop'=>'Korjaamo','done'=>'Valitut työt','service_brand'=>'Osan merkki','service_supplier_sku'=>'Varaosanumero','service_oem_number'=>'OEM','service_item_notes'=>'Työn muistiinpano','service_quantity'=>'Määrä','service_unit'=>'Yksikkö','service_price_net'=>'Syötetty hinta','service_vat_rate'=>'ALV %','service_action_type'=>'Toimenpide','service_resets_interval'=>'Huoltovälin nollaus','service_stock_part'=>'Varasto-osa','service_stock_qty'=>'Varastosta käytetty määrä','service_use_stock'=>'Varaston käyttö','custom_desc'=>'Muu työ','custom_notes'=>'Muun työn huomautus','custom_price_net'=>'Muun työn hinta','custom_vat_rate'=>'Muun työn ALV','labor_hours'=>'Työtunnit','labor_rate'=>'Tuntihinta','labor_vat_rate'=>'Työn ALV','actual_work_seconds'=>'Työaika sekunteina','mechanic_id'=>'Mekaanikon numero','total_cost'=>'Kokonaishinta','inventory_stock_qty'=>'Varastosaldo','inventory_stock_unit'=>'Varastoyksikkö','inventory_shelf_location'=>'Hyllypaikka','inventory_reorder_level'=>'Varoitusraja','inventory_purchase_price'=>'Hankintahinta','customer_name'=>'Asiakkaan nimi','customer_notes'=>'Asiakkaan muistiinpanot'];
    $skip=['csrf','action','edit_version','inventory_baseline','inventory_complete'];$lines=[];$items=itemMap($db);
    $walk=function(mixed $value,string $name)use(&$walk,&$lines,$items):void{if(is_array($value)){foreach($value as $key=>$entry)$walk($entry,$name.' / '.($items[(string)$key]['label']??(string)$key));}elseif(is_scalar($value))$lines[]=$name.': '.(string)$value;};
    foreach($_POST as $key=>$value)if(!in_array($key,$skip,true))$walk($value,$labels[$key]??str_replace('_',' ',$key));
    $text=implode("\n",$lines);$hasUploads=false;
    $checkUpload=function(mixed $errors)use(&$checkUpload,&$hasUploads):void{if(is_array($errors)){foreach($errors as $error)$checkUpload($error);}elseif((int)$errors!==UPLOAD_ERR_NO_FILE)$hasUploads=true;};
    foreach($_FILES as $file)$checkUpload($file['error']??UPLOAD_ERR_NO_FILE);
    ?><!doctype html><html lang="fi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tallennus estettiin – <?=h($appName)?></title><style>*{box-sizing:border-box}body{background:#0a0f15;color:#edf3f8;font:16px/1.5 system-ui,sans-serif;margin:0;padding:24px}.box{max-width:820px;margin:30px auto;padding:26px;background:#121922;border:1px solid #354352;border-radius:16px}h1{font-size:26px}a,button{display:inline-block;margin:8px 8px 8px 0;padding:12px 16px;background:#ffb33c;border:0;border-radius:8px;color:#111;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.secondary{background:#293542;color:#edf3f8}textarea{width:100%;min-height:280px;margin-top:12px;padding:12px;background:#0c1219;color:#edf3f8;border:1px solid #354352;border-radius:8px;font:14px/1.5 monospace}small{color:#aab6c3}</style></head><body><main class="box"><h1>Tallennus estettiin</h1><p role="alert"><?=h($message)?></p><p>Omat lähettämäsi tiedot ovat alla kopioitavissa. Vertaa niitä ajantasaisiin tietoihin ennen uutta tallennusta.</p><a href="<?=h($target)?>" target="_blank" rel="noopener">Avaa ajantasaiset tiedot uuteen välilehteen</a><button type="button" class="secondary" onclick="history.back()">Palaa lomakkeelle</button><?php if($hasUploads):?><p>Kuvatiedostoja ei tallennettu. Valitse ne uudelleen seuraavalla tallennuskerralla.</p><?php endif;?><details open><summary>Omat lähettämäsi tiedot</summary><textarea readonly aria-label="Omat lähettämäsi tiedot"><?=h($text)?></textarea></details><small>Mitään tästä tallennusyrityksestä ei kirjoitettu tietokantaan. Versio <?=h(APP_VERSION)?></small></main></body></html><?php exit;
}

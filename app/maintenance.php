<?php
declare(strict_types=1);

/**
 * app/maintenance.php – huolto-ohjelma, huoltovälit ja huoltotapahtumat.
 * Huoltotyypit ja toimenpiteet, hihnatarkastusten asetukset, huoltorivien tekstit ja hinnat, huoltovälien seuranta,
 * huoltotilanne (calculateMaintenanceStatus) ja ennusteet (forecastForDue, dueInfo).
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

/** Huoltotyypit ja toimenpiteet: koodi => nimi (koodit tallennetaan kantaan, ks. app/vocab.php). */
function serviceTypes(): array { return vocabOptions('stype'); }
function actionTypes(): array { return vocabOptions('action'); }
function validServiceType(string $v): string { return vocabCode('stype',$v,'scheduled'); }
function validActionType(string $v): string { return vocabCode('action',$v,'replaced'); }
function itemKinds(): array { return ['generic'=>t('maint.kind_generic'),'fluid'=>t('maint.kind_fluid'),'part'=>t('maint.kind_part'),'inspection'=>t('maint.kind_inspection'),'service'=>t('maint.kind_service')]; }
function scheduleTypes(): array { return ['replace'=>t('maint.schedule_replace'),'inspect'=>t('maint.schedule_inspect'),'condition'=>t('maint.schedule_condition')]; }
function validItemKind(string $v): string { return array_key_exists($v,itemKinds())?$v:'generic'; }
function validScheduleType(string $v): string { return array_key_exists($v,scheduleTypes())?$v:'replace'; }
function actionForSchedule(string $schedule,string $fallback='replaced'): string {
    return $schedule==='inspect'?'inspected':($schedule==='condition'?'inspected':$fallback);
}
function resetDefaultForAction(string $schedule,string $action): bool {
    if($schedule==='inspect') return $action==='done' || $action==='inspected' || $action==='replaced' || $action==='repaired';
    if($schedule==='condition') return $action==='replaced' || $action==='repaired';
    return $action==='replaced' || $action==='repaired';
}
function resetDefaultForItem(string $itemKey,string $schedule,string $action): bool {
    if(isset(beltInspectionItems()[$itemKey]))return $action==='replaced';
    // Jarrujen huolto/tarkastus aloittaa oletuksena uuden seurantavälin.
    // Käyttäjä voi edelleen ottaa rastin pois yksittäisestä kirjauksesta.
    if(in_array($itemKey,['brakes_front','brakes_rear'],true)) return true;
    return resetDefaultForAction($schedule,$action);
}
/** Tarkastukset käyttävät omia kohteitaan ja välejään, eivät hihnan vaihtoväliä. */
function beltInspectionItems(): array { return ['aux_belt'=>'aux_belt_inspect','timing_belt'=>'timing_belt_inspect']; }
function isBrakeItem(string $key): bool { return in_array($key,['brakes_front','brakes_rear'],true); }
function normalizeBeltSettings(array $settings): array {
    foreach(beltInspectionItems() as $belt=>$inspect){
        $old=$settings[$belt]??[];
        // Vanha Tarkastus-profiili kuuluu tarkastusväliin. Vaihtoväliä ei arvata.
        if(($old['schedule_type']??'')==='inspect'){
            if(!isset($settings[$inspect])||($settings[$inspect]['schedule_type']??'')!=='inspect')$settings[$inspect]=array_replace($old,['item_key'=>$inspect]);
            $settings[$belt]=array_replace($old,['schedule_type'=>'replace','interval_km'=>0,'interval_months'=>0,'first_due_km'=>0,'first_due_date'=>'']);
        }
        $settings[$inspect]=($settings[$inspect]??['enabled'=>$old['enabled']??1])+['schedule_type'=>'inspect'];
        $settings[$inspect]['schedule_type']='inspect';
    }
    return $settings;
}
function serviceActionLabel(array $a): string {
    $code=(string)($a['action']??'');
    return actionLabel(($a['item_key']??'')==='inspection'&&$code==='replaced'?'done':$code);
}
function serviceActionQuantityText(array $a): string {
    $q=$a['quantity']??null;$brake=isBrakeItem((string)($a['item_key']??''));
    if($q===null){if(!$brake||($a['price_net']??null)===null)return ''; $q=1;}
    return dec((float)$q,(float)$q===(float)(int)$q?0:2).' '.($brake?t('maint.unit_whole'):unitLabel((string)$a['unit']));
}
/** Hintatekstin osat (erotin ' · '); 'net' = veroton-osa. */
function servicePriceParts(array $a,array $app,bool $custom=false): array {
    if(($a['price_net']??null)===null)return [];
    $net=(float)$a['price_net'];$vat=$a['vat_rate']!==null?(float)$a['vat_rate']:appVatRate($app);$gross=$net*(1+$vat/100);
    $unit=$custom?'':(isBrakeItem((string)($a['item_key']??''))?t('maint.unit_whole'):unitLabel((string)($a['unit']??'')));
    $suffix=$unit!==''?' / '.$unit:'';
    $parts=[
        ['text'=>t($custom?'maint.price_custom':'maint.price_unit',['price'=>money($gross),'suffix'=>$suffix,'vat'=>dec($vat,1)]),'net'=>false],
        ['text'=>t('maint.price_net',['price'=>money($net),'suffix'=>$suffix]),'net'=>true],
    ];
    $q=$a['quantity']??null;
    if(!$custom&&$q!==null)$parts[]=['text'=>t('maint.price_total',['price'=>money((float)$q*$net*(1+$vat/100))]),'net'=>false];
    return $parts;
}
function servicePriceText(array $a,array $app,bool $custom=false): string {
    return implode(' · ',array_column(servicePriceParts($a,$app,$custom),'text'));
}
/** Tulostehinnat: verollinen yksikköhinta ja rivisumma erottuvat, kaikki teksti escapetaan. */
function servicePriceHtml(array $a,array $app,bool $custom=false): string {
    $html=[];
    foreach(servicePriceParts($a,$app,$custom) as $pp){
        $part=$pp['text'];
        if($part==='')continue;
        if(!$pp['net']&&preg_match('/^(.*?)(-?[0-9 ]+,[0-9]{2} '.preg_quote(CURRENCY,'/').')(.*)$/u',$part,$match))
            $html[]=h($match[1]).'<strong class="print-price-gross">'.h($match[2]).'</strong>'.h($match[3]);
        else $html[]=h($part);
    }
    return implode(' · ',$html);
}
function intervalTrackingKey(array $row): string {
    $key=(string)$row['item_key'];$inspect=beltInspectionItems()[$key]??null;
    return $inspect!==null&&($row['action']??'')==='inspected'?$inspect:$key;
}
function intervalStartKeys(array $row): array {
    $key=(string)$row['item_key'];$inspect=beltInspectionItems()[$key]??null;
    if($inspect!==null){
        if(($row['action']??'')==='inspected')return [$inspect];
        return ($row['action']??'')==='replaced'&&(int)($row['resets_interval']??1)===1?[$key,$inspect]:[];
    }
    return (int)($row['resets_interval']??1)===1?[$key]:[];
}
function serviceIntervalNote(array $a): ?string {
    if(isset(beltInspectionItems()[(string)($a['item_key']??'')])&&($a['action']??'')==='inspected')return t('maint.note_inspection_logged');
    if((int)($a['resets_interval']??1)===1)return null;
    return t('maint.note_interval_not_reset');
}
/**
 * Huoltokohteet. label/section ovat näytettävät (käännetyt) arvot; label_raw/section_raw ovat kantaan tallennetut
 * (valmiin kohteen tyhjä label_raw tarkoittaa kielitiedoston oletusnimeä).
 */
function allItems(PDO $db,bool $onlyActive=false): array {
    $sql="SELECT * FROM item_catalog".($onlyActive?" WHERE active=1":"")." ORDER BY sort_order,section,label";
    return array_map('itemRowForDisplay',$db->query($sql)->fetchAll());
}
function itemRowForDisplay(array $r): array {
    $r['label_raw']=(string)$r['label'];$r['section_raw']=(string)$r['section'];
    $r['label']=itemLabelOf((string)$r['item_key'],(string)$r['label']);$r['section']=sectionLabel((string)$r['section']);
    return $r;
}
function itemMap(PDO $db): array { $m=[]; foreach(allItems($db,false) as $r)$m[$r['item_key']]=$r; return $m; }
function getServiceActions(PDO $db,int $sid): array {
    $st=$db->prepare("SELECT sa.*,COALESCE(NULLIF(sa.item_label_snapshot,''),ic.label) label,COALESCE(NULLIF(sa.item_section_snapshot,''),ic.section) section FROM service_actions sa JOIN item_catalog ic ON ic.item_key=sa.item_key WHERE sa.service_id=? ORDER BY ic.sort_order,ic.section,ic.label");$st->execute([$sid]);
    return array_map(function(array $a): array{$a['label']=itemLabelOf((string)$a['item_key'],(string)$a['label']);$a['section']=sectionLabel((string)$a['section']);return $a;},$st->fetchAll());
}
function getCustomActions(PDO $db,int $sid): array { $st=$db->prepare("SELECT * FROM service_custom_actions WHERE service_id=? ORDER BY id");$st->execute([$sid]);return $st->fetchAll(); }
function itemDefaultUnit(string $key): string { return in_array($key,['engine_oil','gearbox_oil','diff_oil','transfer_oil','brake_fluid','coolant','power_steering'],true)?'l':'pcs'; }
function serviceWithDetails(PDO $db,int $sid): ?array { $st=$db->prepare("SELECT s.*,c.reg_plate,c.nickname,c.owner,c.make,c.model,c.year,c.engine,c.vin,c.first_registration_date,c.customer_address,c.customer_email,c.customer_phone,c.customer_business_id,c.current_customer_id,cu.name current_customer_name,cu.address current_customer_address,cu.postal_code current_customer_postal_code,cu.city current_customer_city,cu.email current_customer_email,cu.phone current_customer_phone,cu.business_id current_customer_business_id FROM services s JOIN cars c ON c.id=s.car_id LEFT JOIN customers cu ON cu.id=c.current_customer_id WHERE s.id=?");$st->execute([$sid]);$r=$st->fetch();if(!$r)return null;$r['actions']=getServiceActions($db,$sid);$r['custom_actions']=getCustomActions($db,$sid);$r['photos']=getServicePhotos($db,$sid);$r['inventory_usage']=serviceInventoryUsage($db,$sid);$detailRows=[$r];attachServiceIntervals($db,$detailRows);return $detailRows[0]; }
function lastItemEvents(PDO $db,int $carId): array {
    $st=$db->prepare("SELECT sa.item_key,sa.action,sa.resets_interval,s.service_date,s.odometer,s.id service_id FROM service_actions sa JOIN services s ON s.id=sa.service_id WHERE s.car_id=? ORDER BY s.service_date DESC,s.id DESC,sa.id DESC");$st->execute([$carId]);$out=[];$seen=[];
    foreach($st->fetchAll() as $r)foreach(intervalStartKeys($r) as $key){
        $sid=(int)$r['service_id'];if(isset($seen[$key][$sid]))continue;$seen[$key][$sid]=true;
        if(count($out[$key]??[])<2)$out[$key][]=$r;
    }
    return $out;
}
/**
 * Kohdekohtainen historia: vain tallennettu resets_interval-valinta aloittaa välin.
 * Tarkastus/lisäys ilman valintaa ei siirrä lähtökohtaa. Käyttäjän oma valinta säilyy.
 * Järjestys on päivämäärä + huollon id, joten myös jälkikäteen lisätyt huollot toimivat.
 * Saman huollon mahdolliset saman kohteen rivit vertaavat kaikki edelliseen huoltoon.
 */
function itemIntervalHistory(PDO $db,int $carId): array {
    $st=$db->prepare("SELECT sa.id action_id,sa.item_key,sa.action,sa.resets_interval,s.id service_id,s.service_date,s.odometer FROM service_actions sa JOIN services s ON s.id=sa.service_id WHERE s.car_id=? ORDER BY s.service_date,s.id,sa.id");
    $st->execute([$carId]);$out=[];$last=[];$pending=[];$serviceId=null;
    foreach($st->fetchAll() as $r){
        $sid=(int)$r['service_id'];
        if($serviceId!==$sid){foreach($pending as $key=>$event)$last[$key]=$event;$pending=[];$serviceId=$sid;}
        $k=intervalTrackingKey($r);$previous=$last[$k]??null;
        $starts=intervalStartKeys($r);$reset=in_array($k,$starts,true);
        $out[(int)$r['action_id']]=['previous'=>$previous,'interval'=>intervalInfo($r,$previous),'resets'=>$reset,'tracking_key'=>$k,'replacement_previous'=>$last[(string)$r['item_key']]??null,'replacement_interval'=>intervalInfo($r,$last[(string)$r['item_key']]??null)];
        foreach($starts as $key)$pending[$key]=$r;
    }
    return $out;
}
/** Liittää saman laskennan sivun, huoltosivun, historiatulosteen ja Excelin töihin. */
function attachServiceIntervals(PDO $db,array &$services): void {
    $history=[];
    foreach($services as &$s){
        $cid=(int)$s['car_id'];if(!isset($history[$cid]))$history[$cid]=itemIntervalHistory($db,$cid);
        foreach($s['actions'] as &$a)$a['maintenance_interval']=$history[$cid][(int)$a['id']]??null;
        unset($a);
    }
    unset($s);
}
function actionIntervalText(array $action): string {
    $info=$action['maintenance_interval']??null;if(!$info)return '';
    $key=(string)($info['tracking_key']??$action['item_key']??'');
    $inspection=in_array($key,array_values(beltInspectionItems()),true);$belt=isset(beltInspectionItems()[$key]);
    $previous=$info['previous'];
    if(!$previous)$text=t($inspection?'maint.no_previous_belt_inspection':($belt?'maint.no_previous_belt_replace':'maint.no_previous_start'));
    else{
        $interval=$info['interval']['text']?:t('maint.interval_unknown');
        $baseline=fiDate($previous['service_date']).' · '.recordedKm((int)$previous['odometer']);
        $textKey=$inspection?'maint.interval_inspection':($belt?($info['resets']?'maint.interval_belt_actual':'maint.interval_belt_since_replace'):($info['resets']?'maint.interval_actual':'maint.interval_since_start'));
        $text=t($textKey,['interval'=>$interval,'baseline'=>$baseline]);
    }
    if($inspection&&isset(beltInspectionItems()[(string)($action['item_key']??'')])){
        $old=$info['replacement_previous']??null;
        if($old)$text.=' · '.t('maint.interval_since_replace',['interval'=>($info['replacement_interval']['text']?:t('maint.interval_unknown')),'date'=>fiDate($old['service_date'])]);
    }
    return $text;
}
/** Yksi laskentatulos huoltotilanteelle, suosituksille, tulosteelle ja Excelille. */
function maintenanceItemStatus(array $car,array $events,array $setting,array $drive=[]): array {
    $last=$events[0]??null;$previous=$events[1]??null;
    $today=date('Y-m-d');
    $elapsed=intervalInfo(['service_date'=>$today,'odometer'=>(int)($car['current_km']??0)],$last);
    $actual=$last?intervalInfo($last,$previous):intervalInfo([],null);
    $due=dueInfo($car,$last,$setting);$forecast=forecastForDue($car,$due,$drive);$due['forecast']=$forecast;
    $warning='';
    if($last&&(int)($car['current_km']??0)>0&&(int)$last['odometer']>(int)$car['current_km'])$warning=t('maint.warn_km_lower');
    if($last&&(string)$last['service_date']>$today)$warning=trim($warning.' '.t('maint.warn_start_future'));
    return ['last'=>$last,'previous'=>$previous,'elapsed'=>$elapsed,'actual'=>$actual,'due'=>$due,'forecast'=>$forecast,'warning'=>$warning];
}
function calculateMaintenanceStatus(PDO $db,array $car,?array $items=null,?array $settings=null,?array $events=null,?array $drive=null): array {
    $cid=(int)$car['id'];$items=$items??allItems($db,true);
    if($settings===null){$settings=[];$st=$db->prepare('SELECT * FROM car_item_settings WHERE car_id=?');$st->execute([$cid]);foreach($st->fetchAll() as $r)$settings[$r['item_key']]=$r;$settings=normalizeBeltSettings($settings);}
    $settings=normalizeBeltSettings($settings);
    $events=$events??lastItemEvents($db,$cid);$drive=$drive??drivingRateEstimate($db,$car);$out=[];
    foreach($items as $it){$k=(string)$it['item_key'];$set=$settings[$k]??[];$out[$k]=maintenanceItemStatus($car,$events[$k]??[],$set,$drive)+['item'=>$it,'enabled'=>!isset($set['enabled'])||(int)$set['enabled']===1];}
    return $out;
}
/** Muodostaa huoltoajankohdan ennusteen annetusta huoltorajasta ja ajomääräarviosta. */
function forecastForDue(array $car,array $due,array $drive): array {
    $status=(string)($due['status']??'');
    if($status==='due')return ['date'=>'','km_date'=>'','first'=>'due','text'=>t('maint.forecast_due_now')];
    $cur=(int)($car['current_km']??0); $dueKm=(int)($due['due_km']??0); $dueDate=(string)($due['due_date']??'');
    $kmDate='';$basis=(!empty($drive['anchor_date'])&&(string)$drive['anchor_date']<date('Y-m-d'))?' · '.t('maint.forecast_basis',['date'=>fiDate((string)$drive['anchor_date'])]):'';
    if($dueKm>$cur&&!empty($drive['available'])&&(float)($drive['annual_km']??0)>0){
        $remaining=$dueKm-$cur;
        $days=(int)ceil($remaining/(float)$drive['annual_km']*365.0);
        if($days>=0&&$days<=36500){$d=new DateTime((string)($drive['anchor_date']??$drive['today']??date('Y-m-d')));$d->modify('+'.$days.' days');$kmDate=$d->format('Y-m-d');}
    }
    if($kmDate!==''&&$dueDate!==''){
        if(strtotime($kmDate)<=strtotime($dueDate))return ['date'=>$kmDate,'km_date'=>$kmDate,'first'=>'km','text'=>t('maint.forecast_km_first',['date'=>fiDate($kmDate),'basis'=>$basis])];
        return ['date'=>$dueDate,'km_date'=>$kmDate,'first'=>'time','text'=>t('maint.forecast_time_first',['date'=>fiDate($dueDate),'km_date'=>fiDate($kmDate),'basis'=>$basis])];
    }
    if($kmDate!=='')return ['date'=>$kmDate,'km_date'=>$kmDate,'first'=>'km','text'=>t('maint.forecast_km_only',['date'=>fiDate($kmDate),'basis'=>$basis])];
    return ['date'=>'','km_date'=>'','first'=>'','text'=>''];
}
function dueInfo(array $car, ?array $last, array $setting): array {
    $ik=(int)($setting['interval_km']??0); $im=(int)($setting['interval_months']??0); $firstKm=(int)($setting['first_due_km']??0); $firstDate=(string)($setting['first_due_date']??'');
    $dueKm=0;$dueDate='';
    if($last){ if($ik>0&&(int)$last['odometer']>0)$dueKm=(int)$last['odometer']+$ik; if($im>0&&!empty($last['service_date']))$dueDate=addMonths($last['service_date'],$im); }
    else { if($firstKm>0)$dueKm=$firstKm; if($firstDate!=='')$dueDate=$firstDate; }
    if(!$dueKm&&!$dueDate){ return ['status'=>$last?'neutral':'unknown','text'=>$last?t('maint.no_interval'):t('maint.no_baseline'),'detail'=>'','due_km'=>0,'due_date'=>'']; }
    $cur=(int)$car['current_km']; $today=date('Y-m-d'); $warnKm=max(0,(int)($car['warning_km']??3000)); $warnMonths=max(0,(int)($car['warning_months']??3));
    $overKm=$dueKm>0&&$cur>=$dueKm; $overDate=$dueDate!==''&&$today>=$dueDate;
    $nearKm=$dueKm>0&&!$overKm&&($dueKm-$cur)<=$warnKm;
    $warnDate=$warnMonths>0?addMonths($today,$warnMonths):$today;
    $nearDate=$dueDate!==''&&!$overDate&&$dueDate<=$warnDate;
    $status=($overKm||$overDate)?'due':(($nearKm||$nearDate)?'soon':'ok');
    $parts=[]; if($dueKm)$parts[]=t('maint.deadline',['value'=>km($dueKm)]); if($dueDate)$parts[]=t('maint.deadline',['value'=>fiDate($dueDate)]);
    $remain=[]; if($dueKm){$d=$dueKm-$cur;$remain[]=$d<0?t('maint.km_over',['n'=>number_format(abs($d),0,',',' ')]):t('maint.km_left',['n'=>number_format($d,0,',',' ')]);}
    if($dueDate){$days=(int)floor((strtotime($dueDate)-strtotime($today))/86400);$remain[]=$days<0?t('maint.days_over',['n'=>abs($days)]):t('maint.days_left',['n'=>$days]);}
    $mode=validScheduleType((string)($setting['schedule_type']??'replace'));
    $dueLabel=$mode==='inspect'?t('maint.status_due_inspect'):($mode==='condition'?t('maint.status_due_condition'):t('maint.status_due'));
    $soonLabel=$mode==='inspect'?t('maint.status_soon_inspect'):($mode==='condition'?t('maint.status_soon_inspect'):t('maint.status_soon'));
    return ['status'=>$status,'text'=>$status==='due'?$dueLabel:($status==='soon'?$soonLabel:t('maint.status_ok')),'detail'=>implode(' · ',$parts),'remain'=>implode(' · ',$remain),'due_km'=>$dueKm,'due_date'=>$dueDate,'schedule_type'=>$mode];
}
function mechanicsList(PDO $db,bool $activeOnly=false): array {
    $sql="SELECT * FROM mechanics".($activeOnly?" WHERE active=1":"")." ORDER BY is_default DESC,sort_order,name,id";
    return array_map(function(array $m):array{$m['name_raw']=(string)$m['name'];$m['name']=mechanicLabel((string)$m['name']);return $m;},$db->query($sql)->fetchAll());
}
function defaultMechanicId(PDO $db): int {
    $linked=(int)($GLOBALS['currentUser']['mechanic_id']??0);if($linked){$st=$db->prepare('SELECT id FROM mechanics WHERE id=? AND active=1');$st->execute([$linked]);if($st->fetchColumn())return $linked;}
    $v=$db->query("SELECT id FROM mechanics WHERE active=1 ORDER BY is_default DESC,sort_order,name,id LIMIT 1")->fetchColumn();
    return $v===false?0:(int)$v;
}

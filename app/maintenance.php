<?php
declare(strict_types=1);

/**
 * app/maintenance.php – huolto-ohjelma, huoltovälit ja huoltotapahtumat.
 * Huoltotyypit ja toimenpiteet, hihnatarkastusten asetukset, huoltorivien tekstit ja hinnat, huoltovälien seuranta,
 * huoltotilanne (calculateMaintenanceStatus) ja ennusteet (forecastForDue, dueInfo).
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

function serviceTypes(): array { return ['Määräaikaishuolto','Korjaus','Katsastus','Rengastyö','Muu']; }
function actionTypes(): array { return ['Vaihdettu / tehty','Tehty','Tarkastettu','Korjattu','Puhdistettu','Lisätty / täytetty']; }
function validServiceType(string $v): string { return in_array($v,serviceTypes(),true)?$v:'Määräaikaishuolto'; }
function validActionType(string $v): string { return in_array($v,actionTypes(),true)?$v:'Vaihdettu / tehty'; }
function itemKinds(): array { return ['generic'=>'Yleinen','fluid'=>'Neste','part'=>'Varaosa','inspection'=>'Tarkastus','service'=>'Palvelu']; }
function scheduleTypes(): array { return ['replace'=>'Vaihto','inspect'=>'Tarkastus','condition'=>'Kunnon mukaan']; }
function validItemKind(string $v): string { return array_key_exists($v,itemKinds())?$v:'generic'; }
function validScheduleType(string $v): string { return array_key_exists($v,scheduleTypes())?$v:'replace'; }
function actionForSchedule(string $schedule,string $fallback='Vaihdettu / tehty'): string {
    return $schedule==='inspect'?'Tarkastettu':($schedule==='condition'?'Tarkastettu':$fallback);
}
function resetDefaultForAction(string $schedule,string $action): bool {
    if($schedule==='inspect') return $action==='Tehty' || $action==='Tarkastettu' || $action==='Vaihdettu / tehty' || $action==='Korjattu';
    if($schedule==='condition') return $action==='Vaihdettu / tehty' || $action==='Korjattu';
    return $action==='Vaihdettu / tehty' || $action==='Korjattu';
}
function resetDefaultForItem(string $itemKey,string $schedule,string $action): bool {
    if(isset(beltInspectionItems()[$itemKey]))return $action==='Vaihdettu / tehty';
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
    $label=(string)($a['action']??'');
    return ($a['item_key']??'')==='inspection'&&$label==='Vaihdettu / tehty'?'Tehty':$label;
}
function serviceActionQuantityText(array $a): string {
    $q=$a['quantity']??null;$brake=isBrakeItem((string)($a['item_key']??''));
    if($q===null){if(!$brake||($a['price_net']??null)===null)return ''; $q=1;}
    return dec((float)$q,(float)$q===(float)(int)$q?0:2).' '.($brake?'kokonaisuus':($a['unit']?:'kpl'));
}
function servicePriceText(array $a,array $app,bool $custom=false): string {
    if(($a['price_net']??null)===null)return '';
    $net=(float)$a['price_net'];$vat=$a['vat_rate']!==null?(float)$a['vat_rate']:appVatRate($app);$gross=$net*(1+$vat/100);
    $unit=$custom?'':(isBrakeItem((string)($a['item_key']??''))?'kokonaisuus':($a['unit']?:'kpl'));
    $suffix=$unit!==''?' / '.$unit:'';
    $text=($custom?'Hinta ':'Yksikköhinta ').money($gross).$suffix.' sis. ALV '.dec($vat,1).' % · Veroton '.money($net).$suffix;
    $q=$a['quantity']??null;
    if(!$custom&&$q!==null)$text.=' · Yhteensä '.money((float)$q*$net*(1+$vat/100)).' sis. ALV';
    return $text;
}
/** Tulostehinnat: verollinen yksikköhinta ja rivisumma erottuvat, kaikki teksti escapetaan. */
function servicePriceHtml(array $a,array $app,bool $custom=false): string {
    $parts=explode(' · ',servicePriceText($a,$app,$custom));$html=[];
    foreach($parts as $part){
        if($part==='')continue;
        if(!str_starts_with($part,'Veroton ')&&preg_match('/^(.*?)(-?[0-9 ]+,[0-9]{2} '.preg_quote(CURRENCY,'/').')(.*)$/u',$part,$match))
            $html[]=h($match[1]).'<strong class="print-price-gross">'.h($match[2]).'</strong>'.h($match[3]);
        else $html[]=h($part);
    }
    return implode(' · ',$html);
}
function intervalTrackingKey(array $row): string {
    $key=(string)$row['item_key'];$inspect=beltInspectionItems()[$key]??null;
    return $inspect!==null&&($row['action']??'')==='Tarkastettu'?$inspect:$key;
}
function intervalStartKeys(array $row): array {
    $key=(string)$row['item_key'];$inspect=beltInspectionItems()[$key]??null;
    if($inspect!==null){
        if(($row['action']??'')==='Tarkastettu')return [$inspect];
        return ($row['action']??'')==='Vaihdettu / tehty'&&(int)($row['resets_interval']??1)===1?[$key,$inspect]:[];
    }
    return (int)($row['resets_interval']??1)===1?[$key]:[];
}
function serviceIntervalNote(array $a): ?string {
    if(isset(beltInspectionItems()[(string)($a['item_key']??'')])&&($a['action']??'')==='Tarkastettu')return 'Tarkastus kirjattu · Vaihtoväliä ei nollattu';
    if((int)($a['resets_interval']??1)===1)return null;
    return 'Huoltoväliä ei nollattu';
}
function allItems(PDO $db,bool $onlyActive=false): array {
    $sql="SELECT * FROM item_catalog".($onlyActive?" WHERE active=1":"")." ORDER BY sort_order,section,label";
    return $db->query($sql)->fetchAll();
}
function itemMap(PDO $db): array { $m=[]; foreach(allItems($db,false) as $r)$m[$r['item_key']]=$r; return $m; }
function getServiceActions(PDO $db,int $sid): array {
    $st=$db->prepare("SELECT sa.*,COALESCE(NULLIF(sa.item_label_snapshot,''),ic.label) label,COALESCE(NULLIF(sa.item_section_snapshot,''),ic.section) section FROM service_actions sa JOIN item_catalog ic ON ic.item_key=sa.item_key WHERE sa.service_id=? ORDER BY ic.sort_order,ic.section,ic.label");$st->execute([$sid]);return $st->fetchAll();
}
function getCustomActions(PDO $db,int $sid): array { $st=$db->prepare("SELECT * FROM service_custom_actions WHERE service_id=? ORDER BY id");$st->execute([$sid]);return $st->fetchAll(); }
function itemDefaultUnit(string $key): string { return in_array($key,['engine_oil','gearbox_oil','diff_oil','transfer_oil','brake_fluid','coolant','power_steering'],true)?'L':'kpl'; }
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
    if(!$previous)$text=$inspection?'Ei aiempaa kirjattua hihnan tarkastusta tai vaihtoa':($belt?'Ei aiempaa kirjattua hihnan vaihtoa':'Ei aiempaa kirjattua huoltovälin aloitusta');
    else{
        $interval=$info['interval']['text']?:'Väliä ei voida laskea';
        $baseline=fiDate($previous['service_date']).' · '.recordedKm((int)$previous['odometer']);
        $prefix=$inspection?'Tarkastusväli: ':($belt?($info['resets']?'Toteutunut vaihtoväli: ':'Edellisestä vaihdosta: '):($info['resets']?'Toteutunut huoltoväli: ':'Edellisestä huoltovälin aloituksesta: '));
        $text=$prefix.$interval.' (edellinen '.$baseline.')';
    }
    if($inspection&&isset(beltInspectionItems()[(string)($action['item_key']??'')])){
        $old=$info['replacement_previous']??null;
        if($old)$text.=' · Edellisestä vaihdosta: '.($info['replacement_interval']['text']?:'Väliä ei voida laskea').' ('.fiDate($old['service_date']).')';
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
    if($last&&(int)($car['current_km']??0)>0&&(int)$last['odometer']>(int)$car['current_km'])$warning='Nykyinen mittarilukema on pienempi kuin tämän kohteen viimeisessä huollossa. Tarkista kilometrit.';
    if($last&&(string)$last['service_date']>$today)$warning=trim($warning.' Huoltovälin aloitus on tulevaisuudessa. Tarkista päiväys.');
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
    if($status==='due')return ['date'=>'','km_date'=>'','first'=>'due','text'=>'Huolto on jo ajankohtainen'];
    $cur=(int)($car['current_km']??0); $dueKm=(int)($due['due_km']??0); $dueDate=(string)($due['due_date']??'');
    $kmDate='';$basis=(!empty($drive['anchor_date'])&&(string)$drive['anchor_date']<date('Y-m-d'))?' · perustuu '.fiDate((string)$drive['anchor_date']).' mittarilukemaan':'';
    if($dueKm>$cur&&!empty($drive['available'])&&(float)($drive['annual_km']??0)>0){
        $remaining=$dueKm-$cur;
        $days=(int)ceil($remaining/(float)$drive['annual_km']*365.0);
        if($days>=0&&$days<=36500){$d=new DateTime((string)($drive['anchor_date']??$drive['today']??date('Y-m-d')));$d->modify('+'.$days.' days');$kmDate=$d->format('Y-m-d');}
    }
    if($kmDate!==''&&$dueDate!==''){
        if(strtotime($kmDate)<=strtotime($dueDate))return ['date'=>$kmDate,'km_date'=>$kmDate,'first'=>'km','text'=>'Arvioitu huolto '.fiDate($kmDate).' · kilometriraja tulee arviolta ensin'.$basis];
        return ['date'=>$dueDate,'km_date'=>$kmDate,'first'=>'time','text'=>'Arvioitu huolto '.fiDate($dueDate).' · aikaraja tulee arviolta ensin (km-raja nykyajolla noin '.fiDate($kmDate).')'.$basis];
    }
    if($kmDate!=='')return ['date'=>$kmDate,'km_date'=>$kmDate,'first'=>'km','text'=>'Km-raja saavutetaan nykyajolla arviolta '.fiDate($kmDate).$basis];
    return ['date'=>'','km_date'=>'','first'=>'','text'=>''];
}
function dueInfo(array $car, ?array $last, array $setting): array {
    $ik=(int)($setting['interval_km']??0); $im=(int)($setting['interval_months']??0); $firstKm=(int)($setting['first_due_km']??0); $firstDate=(string)($setting['first_due_date']??'');
    $dueKm=0;$dueDate='';
    if($last){ if($ik>0&&(int)$last['odometer']>0)$dueKm=(int)$last['odometer']+$ik; if($im>0&&!empty($last['service_date']))$dueDate=addMonths($last['service_date'],$im); }
    else { if($firstKm>0)$dueKm=$firstKm; if($firstDate!=='')$dueDate=$firstDate; }
    if(!$dueKm&&!$dueDate){ return ['status'=>$last?'neutral':'unknown','text'=>$last?'Ei huoltoväliä':'Ei lähtötietoa','detail'=>'','due_km'=>0,'due_date'=>'']; }
    $cur=(int)$car['current_km']; $today=date('Y-m-d'); $warnKm=max(0,(int)($car['warning_km']??3000)); $warnMonths=max(0,(int)($car['warning_months']??3));
    $overKm=$dueKm>0&&$cur>=$dueKm; $overDate=$dueDate!==''&&$today>=$dueDate;
    $nearKm=$dueKm>0&&!$overKm&&($dueKm-$cur)<=$warnKm;
    $warnDate=$warnMonths>0?addMonths($today,$warnMonths):$today;
    $nearDate=$dueDate!==''&&!$overDate&&$dueDate<=$warnDate;
    $status=($overKm||$overDate)?'due':(($nearKm||$nearDate)?'soon':'ok');
    $parts=[]; if($dueKm)$parts[]='viimeistään '.km($dueKm); if($dueDate)$parts[]='viimeistään '.fiDate($dueDate);
    $remain=[]; if($dueKm){$d=$dueKm-$cur;$remain[]=$d<0?number_format(abs($d),0,',',' ').' km yli':number_format($d,0,',',' ').' km jäljellä';}
    if($dueDate){$days=(int)floor((strtotime($dueDate)-strtotime($today))/86400);$remain[]=$days<0?abs($days).' pv yli':$days.' pv jäljellä';}
    $mode=validScheduleType((string)($setting['schedule_type']??'replace'));
    $dueLabel=$mode==='inspect'?'TARKASTUS AJANKOHTA':($mode==='condition'?'TARKISTA KUNTO':'HUOLTOAJANKOHTA');
    $soonLabel=$mode==='inspect'?'TARKASTUS LÄHESTYY':($mode==='condition'?'TARKASTUS LÄHESTYY':'LÄHESTYY');
    return ['status'=>$status,'text'=>$status==='due'?$dueLabel:($status==='soon'?$soonLabel:'OK'),'detail'=>implode(' · ',$parts),'remain'=>implode(' · ',$remain),'due_km'=>$dueKm,'due_date'=>$dueDate,'schedule_type'=>$mode];
}
function mechanicsList(PDO $db,bool $activeOnly=false): array {
    $sql="SELECT * FROM mechanics".($activeOnly?" WHERE active=1":"")." ORDER BY is_default DESC,sort_order,name,id";
    return $db->query($sql)->fetchAll();
}
function defaultMechanicId(PDO $db): int {
    $linked=(int)($GLOBALS['currentUser']['mechanic_id']??0);if($linked){$st=$db->prepare('SELECT id FROM mechanics WHERE id=? AND active=1');$st->execute([$linked]);if($st->fetchColumn())return $linked;}
    $v=$db->query("SELECT id FROM mechanics WHERE active=1 ORDER BY is_default DESC,sort_order,name,id LIMIT 1")->fetchColumn();
    return $v===false?0:(int)$v;
}

<?php
declare(strict_types=1);

/**
 * app/cars.php – auton tiedot, mittarilukemat ja tietojen tarkistus.
 * Auton haku (getCar), vuosikulut, kilometrihistoria ja -aikajana, ajomääräarvio, tietojen tarkistus (dataCheckIssues)
 * ja Bilteman linkki. Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

/* Bilteman sivu toimii käsin täytettävien ajoneuvotietojen lähteenä. */
function biltemaVehicleLink(string $registration=''): void {
    $plate=preg_replace('/\s+/u','',mb_strtoupper(trim($registration)));
    if(preg_match('/^([A-ZÅÄÖ]{1,3})-?([0-9]{1,3})$/u',$plate,$m))$plate=$m[1].'-'.$m[2];else $plate='';
    $url='https://www.biltema.fi/auton-varaosahaku/'.($plate!==''?rawurlencode($plate).'/':'');
    ?><a class="btn2 biltema-open" style="margin-top:7px" target="_blank" rel="noopener noreferrer" href="<?=h($url)?>"><?=t('cars.biltema_open')?></a><div class="tiny biltema-status" role="status" aria-live="polite"><?=t('cars.biltema_status')?></div><?php
}
function getCar(PDO $db,int $id): ?array { $st=$db->prepare("SELECT * FROM cars WHERE id=?");$st->execute([$id]);$r=$st->fetch();return $r?:null; }
function getAnnualCosts(PDO $db, int $carId): array {
    $st = $db->prepare("
        SELECT substr(service_date, 1, 4) AS year, SUM(total_cost) AS total 
        FROM services 
        WHERE car_id = ? AND total_cost IS NOT NULL AND total_cost > 0 
        GROUP BY year 
        ORDER BY year DESC
    ");
    $st->execute([$carId]);
    return $st->fetchAll();
}
/**
 * Arvioi vuosittain ajetut kilometrit mittarilukemista (suuntaa antava).
 * Lukemista tehdään kasvava jono (päivän suurin lukema; pienemmät poikkeamat ohitetaan), ja vuoden rajat
 * (1.1.) arvioidaan lineaarisella interpolaatiolla lähimpien lukemien väliltä, eli oletetaan tasainen ajotahti.
 * Palauttaa ['years'=>[vuosi=>rivi], 'summary'=>[...]] tai tyhjän, jos lukemia on alle kaksi.
 * Rivi: km, partial (vuosi osittainen), from/to (päivät), days, rough (rajan ympäriltä puuttuu tiheä lukema),
 * project (vuositahdin arvio käynnissä olevalle vuodelle).
 */
function annualKmEstimates(array $points,?string $today=null): array {
    $byDay=[];
    foreach($points as $p){
        $km=(int)($p['km']??0);$d=substr((string)($p['date']??''),0,10);if($km<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d))continue;
        $byDay[$d]=max($byDay[$d]??0,$km);
    }
    ksort($byDay);$series=[];$runMax=0;
    foreach($byDay as $d=>$km){if($km<$runMax)continue;$runMax=$km;$series[]=['d'=>(int)(strtotime($d.' 00:00:00 UTC')/86400),'date'=>$d,'km'=>$km];}
    if(count($series)<2)return [];
    $first=$series[0];$last=$series[count($series)-1];if($last['d']<=$first['d'])return [];
    /* Interpolaatio päivälle; palauttaa [km, aukon pituus päivinä] tai null alueen ulkopuolella. */
    $at=function(int $day) use($series){
        $n=count($series);
        for($i=0;$i<$n;$i++){
            if($series[$i]['d']===$day)return [(float)$series[$i]['km'],0];
            if($series[$i]['d']>$day){
                if($i===0)return null;$a=$series[$i-1];$b=$series[$i];$gap=$b['d']-$a['d'];
                return [$a['km']+($b['km']-$a['km'])*($day-$a['d'])/$gap,$gap];
            }
        }
        return null;
    };
    $dayOf=fn(int $y)=>(int)(strtotime($y.'-01-01 00:00:00 UTC')/86400);
    $todayDay=(int)(strtotime(($today?:date('Y-m-d')).' 00:00:00 UTC')/86400);$thisYear=(int)($today?substr($today,0,4):date('Y'));
    $y0=(int)substr($first['date'],0,4);$y1=(int)substr($last['date'],0,4);$years=[];
    for($y=$y0;$y<=$y1;$y++){
        $s=$dayOf($y);$e=$dayOf($y+1);$partial=false;$rough=false;
        if($s<=$first['d']){$sk=(float)$first['km'];$sd=$first['d'];$partial=$partial||($first['d']>$s);}
        else{$r=$at($s);if(!$r)continue;$sk=$r[0];$sd=$s;if($r[1]>180)$rough=true;}
        if($e>$last['d']){$ek=(float)$last['km'];$ed=$last['d'];$partial=true;}
        else{$r=$at($e);if(!$r)continue;$ek=$r[0];$ed=$e;if($r[1]>180)$rough=true;}
        $days=$ed-$sd;if($days<=0)continue;
        $row=['km'=>(int)round($ek-$sk),'partial'=>$partial,'from'=>gmdate('Y-m-d',$sd*86400),'to'=>gmdate('Y-m-d',$ed*86400),'days'=>$days,'rough'=>$rough,'project'=>null];
        if($partial&&$y===$thisYear&&$days>=30)$row['project']=(int)round(($ek-$sk)/$days*365);
        $years[$y]=$row;
    }
    $spanDays=$last['d']-$first['d'];
    return ['years'=>$years,'summary'=>['km'=>$last['km']-$first['km'],'days'=>$spanDays,'per_year'=>(int)round(($last['km']-$first['km'])/($spanDays/365.25)),'from'=>$first['date'],'to'=>$last['date']]];
}
/**
 * Palauttaa auton erilliset Päivitä km / lähtölukema -merkinnät uusimmasta vanhimpaan.
 * Tätä käytetään hallinnollisiin tarpeisiin; käyttäjälle näytettävä yhteinen aikajana
 * muodostetaan kilometerHistoryEntries()-funktiolla.
 */
function getOdometerReadings(PDO $db,int $carId,int $limit=0): array {
    $sql="SELECT * FROM odometer_readings WHERE car_id=? ORDER BY recorded_at DESC,id DESC";
    if($limit>0)$sql.=" LIMIT ".max(1,$limit);
    $st=$db->prepare($sql);$st->execute([$carId]);return $st->fetchAll();
}
/** Palauttaa jäljellä olevan huolto- ja mittarihistorian suurimman aidon kilometripisteen. */
function historyMaxOdometer(PDO $db,int $carId): int {
    $st=$db->prepare("SELECT MAX(km) FROM (SELECT MAX(odometer) km FROM services WHERE car_id=? AND odometer>0 UNION ALL SELECT MAX(odometer) km FROM odometer_readings WHERE car_id=? AND odometer>0)");
    $st->execute([$carId,$carId]);return max(0,(int)$st->fetchColumn());
}
/** Päivittää cars.current_km:n historian suurimpaan pisteeseen. Käytetään vain, kun nykyisen km:n lähde korjataan tai poistetaan. */
function recalculateCurrentKm(PDO $db,int $carId): int {
    $km=historyMaxOdometer($db,$carId);
    $db->prepare("UPDATE cars SET current_km=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$km,$carId]);
    return $km;
}
/**
 * Yhteinen käyttäjälle näytettävä kilometrihistoria.
 * Mukana ovat kaikki huoltotapahtumat myös silloin, kun km puuttuu, sekä erilliset
 * mittarimerkinnät. Huollolle annetaan laskennassa kellonajaksi 12:00 vain vakaan
 * lajittelun vuoksi; käyttöliittymä ei väitä huollolle kellonaikaa.
 */
function kilometerHistoryEntries(PDO $db,int $carId,bool $newestFirst=true): array {
    $rows=[];
    $st=$db->prepare("SELECT id,service_date,odometer,title,service_type FROM services WHERE car_id=?");$st->execute([$carId]);
    foreach($st->fetchAll() as $r){
        $rows[]=[
            'date'=>(string)$r['service_date'],'datetime'=>(string)$r['service_date'].' 12:00:00','km'=>(int)$r['odometer'],
            'source'=>'service','type_code'=>(string)$r['service_type'],'source_label'=>($r['service_type']?serviceTypeLabel((string)$r['service_type']):t('vocab.service_title_default')),'id'=>(int)$r['id'],'service_id'=>(int)$r['id'],'reading_id'=>0,
            'title'=>(string)$r['title'],'note'=>(string)$r['title'],'warning'=>false,'warning_text'=>''
        ];
    }
    $st=$db->prepare("SELECT id,recorded_at,odometer,source,note FROM odometer_readings WHERE car_id=?");$st->execute([$carId]);
    foreach($st->fetchAll() as $r){
        $src=(string)($r['source']?:'manual');
        $rows[]=[
            'date'=>substr((string)$r['recorded_at'],0,10),'datetime'=>(string)$r['recorded_at'],'km'=>(int)$r['odometer'],
            'source'=>$src,'source_label'=>$src==='initial'?t('cars.source_initial'):($src==='old'?t('cars.source_old'):t('cars.source_update')),'id'=>(int)$r['id'],'service_id'=>0,'reading_id'=>(int)$r['id'],
            'title'=>'','note'=>noteLabel((string)($r['note']??'')),'warning'=>false,'warning_text'=>''
        ];
    }
    usort($rows,function($a,$b){$c=strcmp((string)$a['datetime'],(string)$b['datetime']);if($c!==0)return $c;$sa=(string)$a['source']==='service'?0:1;$sb=(string)$b['source']==='service'?0:1;return $sa<=>$sb?:((int)$a['id']<=>(int)$b['id']);});

    /* Ristiriita arvioidaan päivätasolla. Saman päivän sisäistä kellonaikajärjestystä ei arvata. */
    $byDate=[];foreach($rows as $i=>$r)$byDate[(string)$r['date']][]=$i;
    $priorMax=0;
    foreach($byDate as $date=>$indexes){
        $dayMax=0;
        foreach($indexes as $i){
            $v=(int)$rows[$i]['km'];if($v<=0)continue;
            if($priorMax>0&&$v<$priorMax){$rows[$i]['warning']=true;$rows[$i]['warning_text']=t('cars.reading_lower_warning');}
            $dayMax=max($dayMax,$v);
        }
        if($dayMax>0)$priorMax=max($priorMax,$dayMax);
    }
    if($newestFirst)$rows=array_reverse($rows);
    return $rows;
}
/** Yhdistää huoltojen ja erillisten mittarimerkintöjen positiiviset kilometripisteet laskentaa varten. */
function odometerTimelinePoints(PDO $db,int $carId): array {
    $points=[];
    foreach(kilometerHistoryEntries($db,$carId,false) as $r){
        if((int)$r['km']<=0)continue;
        $points[]=['date'=>(string)$r['date'],'datetime'=>(string)$r['datetime'],'km'=>(int)$r['km'],'source'=>(string)$r['source'],'id'=>(int)$r['id'],'note'=>(string)($r['note']??'')];
    }
    return $points;
}
/**
 * Arvioi auton vuotuisen ajomäärän huoltojen ja erillisten mittarimerkintöjen
 * muodostamasta aikajanasta. Nykyisen kilometrilukeman päivätty piste valitaan
 * ankkuriksi, jos sellainen löytyy. Vanhoille ilman ankkuria tallennetuille
 * current_km-arvoille säilyy turvallinen legacy-fallback: nykyhetki.
 */
function drivingRateEstimate(PDO $db,array $car): array {
    $cur=(int)($car['current_km']??0); $carId=(int)($car['id']??0);
    if($cur<=0||$carId<=0)return ['available'=>false];
    $today=date('Y-m-d');$points=odometerTimelinePoints($db,$carId);
    $anchor=['date'=>$today,'datetime'=>$today.' 23:59:59','km'=>$cur,'source'=>'current'];
    foreach($points as $pt){
        if((int)$pt['km']!==$cur||(string)$pt['date']>$today)continue;
        if($anchor['source']==='current'||strcmp((string)$pt['datetime'],(string)$anchor['datetime'])>0)$anchor=['date'=>(string)$pt['date'],'datetime'=>(string)$pt['datetime'],'km'=>$cur,'source'=>(string)$pt['source']];
    }
    $anchorTs=strtotime((string)$anchor['date']);if(!$anchorTs)return ['available'=>false];
    $best=null;$bestScore=PHP_INT_MAX;
    foreach($points as $pt){
        $date=(string)$pt['date'];$baseKm=(int)$pt['km'];$ts=strtotime($date);
        if(!$ts||$ts>=$anchorTs||$baseKm<=0||$baseKm>$cur)continue;
        $days=(int)floor(($anchorTs-$ts)/86400);if($days<60||$days>1095)continue;
        $delta=$cur-$baseKm;if($delta<100)continue;
        /* 60–730 pv on ensisijainen ikkuna; lähimpänä 365 päivää oleva voittaa. */
        $score=abs($days-365)+(($days>730)?10000:0);
        if($score<$bestScore){$bestScore=$score;$best=['date'=>$date,'km'=>$baseKm,'days'=>$days,'delta_km'=>$delta,'source'=>(string)$pt['source']];}
    }
    if(!$best)return ['available'=>false,'anchor_date'=>$anchor['date'],'anchor_source'=>$anchor['source']];
    $annual=(float)$best['delta_km']/(float)$best['days']*365.0;
    if(!is_finite($annual)||$annual<=0)return ['available'=>false,'anchor_date'=>$anchor['date'],'anchor_source'=>$anchor['source']];
    $sourceLabel=function(string $src): string {return $src==='service'?t('cars.src_service'):($src==='initial'?t('cars.src_initial'):($src==='old'?t('cars.src_old'):($src==='manual'?t('cars.src_manual'):t('cars.src_reading'))));};
    return [
        'available'=>true,
        'annual_km'=>(int)round($annual),
        'daily_km'=>$annual/365.0,
        'baseline_date'=>$best['date'],
        'baseline_km'=>$best['km'],
        'baseline_source'=>$best['source'],
        'anchor_date'=>$anchor['date'],
        'anchor_source'=>$anchor['source'],
        'sample_days'=>$best['days'],
        'sample_km'=>$best['delta_km'],
        'sample_text'=>t('cars.sample_text',['date'=>fiDate((string)$anchor['date']),'km'=>number_format((int)$best['delta_km'],0,',',' '),'elapsed'=>elapsedText($best['date'],$anchor['date']),'from'=>$sourceLabel((string)$best['source']),'to'=>$sourceLabel((string)$anchor['source'])]),
        'current_km'=>$cur,
        'today'=>$today,
    ];
}
function dataIssueGroup(array $di): string {
    if(($di['level']??'')==='error')return 'error';
    if(str_starts_with((string)($di['key']??''),'missing-image-'))return 'img';
    return ($di['level']??'')==='warn'?'warn':'info';
}
/**
 * Tietojen tarkistuksen löydökset. Jokaisella löydöksellä on kielineutraali tunniste (key), jolla hyväksytty poikkeama tunnistetaan
 * kielestä riippumatta. $all=true palauttaa myös hyväksytyt löydökset; legacy_key on vanhan version tekstitiiviste (migraatio).
 */
function dataCheckIssues(PDO $db,bool $all=false): array {
    $out=[];$ignored=array_flip($db->query("SELECT issue_key FROM data_issue_ignores")->fetchAll(PDO::FETCH_COLUMN));
    $add=function(string $level,string $title,string $detail,string $url,string $key) use (&$out,$ignored,$all){if(!$all&&isset($ignored[$key]))return;$out[]=['level'=>$level,'title'=>$title,'detail'=>$detail,'url'=>$url,'key'=>$key,'legacy_key'=>hash('sha256',$title."\n".$detail),'ignored'=>isset($ignored[$key])];};
    try {$integrity=(string)$db->query('PRAGMA integrity_check')->fetchColumn(); if($integrity!=='ok')$add('error',t('cars.issue_integrity'),'PRAGMA integrity_check: '.$integrity,'','db-integrity');}
    catch(Throwable $e){$add('error',t('cars.issue_integrity_failed'),$e->getMessage(),'','db-integrity-failed');}
    try { foreach($db->query('PRAGMA foreign_key_check')->fetchAll() as $r)$add('error',t('cars.issue_broken_db_ref'),t('cars.issue_broken_db_ref_detail',['table'=>($r['table']??'?'),'row'=>($r['rowid']??'?')]),'','db-fk-'.preg_replace('/[^A-Za-z0-9_]/','',(string)($r['table']??'x')).'-'.(int)($r['rowid']??0)); } catch(Throwable) {}

    $sql="SELECT cis.*,c.id car_id,c.reg_plate,c.nickname,ic.label FROM car_item_settings cis JOIN cars c ON c.id=cis.car_id JOIN item_catalog ic ON ic.item_key=cis.item_key WHERE cis.interval_km>0 OR cis.interval_months>0";
    foreach($db->query($sql)->fetchAll() as $r){
        $ik=(int)$r['interval_km']; $im=(int)$r['interval_months']; $car=trim((string)($r['reg_plate']?:$r['nickname']));
        $itemName=itemLabelOf((string)$r['item_key'],(string)$r['label']);$ikey=(int)$r['car_id'].'-'.preg_replace('/[^A-Za-z0-9_]/','',(string)$r['item_key']);
        if($ik>0&&$ik<500&&$im>1000)$add('warn',$car.' · '.$itemName,t('cars.issue_interval_swapped',['km'=>number_format($ik,0,',',' '),'months'=>number_format($im,0,',',' ')]),'?car='.(int)$r['car_id'].'#ohjelma','interval-swapped-'.$ikey);
        elseif($im>240)$add('warn',$car.' · '.$itemName,t('cars.issue_interval_large',['months'=>number_format($im,0,',',' ')]),'?car='.(int)$r['car_id'].'#ohjelma','interval-large-'.$ikey);
        elseif($ik>0&&$ik<500)$add('info',$car.' · '.$itemName,t('cars.issue_interval_short',['km'=>number_format($ik,0,',',' ')]),'?car='.(int)$r['car_id'].'#ohjelma','interval-short-'.$ikey);
    }
    foreach($db->query("SELECT * FROM cars ORDER BY id")->fetchAll() as $c){
        $cid=(int)$c['id']; $name=trim((string)($c['reg_plate']?:$c['nickname']?:(t('cars.car_number',['id'=>$cid]))));
        if((int)$c['current_km']<=0)$add('info',$name,t('cars.issue_no_current_km'),'?car='.$cid.'#autontiedot','no-current-km-'.$cid);
        $st=$db->prepare("SELECT id,service_date,odometer,title FROM services WHERE car_id=? ORDER BY service_date,id");$st->execute([$cid]);$rows=$st->fetchAll();$prev=null;$maxKm=0;
        foreach($rows as $r){
            $odo=(int)$r['odometer']; $maxKm=max($maxKm,$odo);
            if($odo<=0)$add('info',$name.' · '.fiDate($r['service_date']),t('cars.issue_service_no_km'),'?car='.$cid.'&edit_service='.(int)$r['id'].'#huolto','service-no-km-'.(int)$r['id']);
            if((string)$r['service_date']>date('Y-m-d'))$add('warn',$name.' · '.fiDate($r['service_date']),t('cars.issue_service_future'),'?car='.$cid.'&edit_service='.(int)$r['id'].'#huolto','service-future-'.(int)$r['id']);
            if($prev&&$odo>0&&(int)$prev['odometer']>0&&$odo<(int)$prev['odometer'])$add('warn',$name.' · '.fiDate($r['service_date']),t('cars.issue_reading_lower_than_prev',['km'=>km($odo),'date'=>fiDate($prev['service_date']),'prev_km'=>recordedKm((int)$prev['odometer'])]),'?car='.$cid.'&edit_service='.(int)$r['id'].'#huolto','service-km-lower-'.(int)$r['id'].'-'.$odo.'-'.(int)$prev['odometer']);
            $q=$db->prepare("SELECT (SELECT COUNT(*) FROM service_actions WHERE service_id=?)+(SELECT COUNT(*) FROM service_custom_actions WHERE service_id=?)");$q->execute([(int)$r['id'],(int)$r['id']]);
            if((int)$q->fetchColumn()===0)$add('info',$name.' · '.fiDate($r['service_date']),t('cars.issue_no_work_items'),'?car='.$cid.'&edit_service='.(int)$r['id'].'#huolto','service-no-items-'.(int)$r['id']);
            $prev=$r;
        }
        $st=$db->prepare("SELECT id,recorded_at,odometer,source FROM odometer_readings WHERE car_id=? ORDER BY recorded_at,id");$st->execute([$cid]);$readPrev=null;
        foreach($st->fetchAll() as $rr){
            $odo=(int)$rr['odometer'];$maxKm=max($maxKm,$odo);$rd=(string)$rr['recorded_at'];
            if($odo<=0)$add('info',$name.' · '.t('cars.reading_entry'),t('cars.issue_reading_no_km'),'?car='.$cid,'reading-no-km-'.(int)$rr['id']);
            if($rd!==''&&strtotime($rd)!==false&&strtotime($rd)>time()+300)$add('warn',$name.' · '.t('cars.reading_entry'),t('cars.issue_reading_future',['time'=>date('d.m.Y H:i',strtotime($rd))]),'?car='.$cid,'reading-future-'.(int)$rr['id']);
            if($readPrev&&$odo>0&&(int)$readPrev['odometer']>0&&$odo<(int)$readPrev['odometer'])$add('warn',$name.' · '.t('cars.reading_history'),t('cars.issue_reading_lower_than_prev_reading',['time'=>date('d.m.Y H:i',strtotime($rd)),'km'=>km($odo),'prev_time'=>date('d.m.Y H:i',strtotime((string)$readPrev['recorded_at'])),'prev_km'=>recordedKm((int)$readPrev['odometer'])]),'?car='.$cid,'reading-lower-'.(int)$rr['id'].'-'.$odo.'-'.(int)$readPrev['odometer']);
            $readPrev=$rr;
        }
        if($maxKm>0&&(int)$c['current_km']>0&&(int)$c['current_km']<$maxKm)$add('warn',$name,t('cars.issue_current_lower_than_max',['km'=>km((int)$c['current_km']),'max'=>km($maxKm)]),'?car='.$cid.'#autontiedot','current-lower-max-'.$cid.'-'.(int)$c['current_km'].'-'.$maxKm);
    }
    /* Mekaanikkoa vaaditaan tarkistusmielessä vain omalta työltä. Ulkopuolisen korjaamon henkilön nimeä ei tarvitse tietää. */
    $mechanicsSetting=(string)($db->query("SELECT setting_value FROM app_settings WHERE setting_key='feature_mechanics'")->fetchColumn() ?: '0');
    if($mechanicsSetting==='1'){
        $sqlMissingMechanic="SELECT s.id,s.car_id,s.service_date,s.odometer,s.title,s.service_type,c.reg_plate,c.nickname,c.make,c.model FROM services s JOIN cars c ON c.id=s.car_id WHERE s.service_origin='own' AND TRIM(COALESCE(s.mechanic_name_snapshot,''))='' ORDER BY s.service_date DESC,s.id DESC";
        foreach($db->query($sqlMissingMechanic)->fetchAll() as $r){
            $carLabel=trim((string)($r['reg_plate']?:$r['nickname']?:(trim((string)(($r['make']??'').' '.($r['model']??''))))));
            if($carLabel==='')$carLabel=t('cars.car_number',['id'=>(int)$r['car_id']]);
            $detail=t('cars.issue_missing_mechanic_detail',['date'=>fiDate((string)$r['service_date']),'km'=>recordedKm((int)$r['odometer']),'title'=>trim((string)($r['title']?:serviceTypeLabel((string)$r['service_type'])?:t('vocab.service_title_default')))]);
            $add('info',$carLabel.' · '.t('cars.mechanic_missing'),$detail,'?car='.(int)$r['car_id'].'&edit_service='.(int)$r['id'].'#huolto','missing-mechanic-service-'.(int)$r['id']);
        }
    }
    $customerSetting=(string)($db->query("SELECT setting_value FROM app_settings WHERE setting_key='feature_customers'")->fetchColumn() ?: '0');
    if($customerSetting==='1'){
        foreach($db->query("SELECT id,reg_plate,nickname,owner FROM cars WHERE current_customer_id IS NULL OR current_customer_id=0 ORDER BY id")->fetchAll() as $r){
            $name=trim((string)($r['reg_plate']?:$r['nickname']?:(t('cars.car_number',['id'=>(int)$r['id']]))));
            $legacy=trim((string)($r['owner']??''));
            $detail=$legacy!==''?t('cars.issue_unlinked_customer_legacy',['legacy'=>$legacy]):t('cars.issue_unlinked_customer');
            $add('info',$name.' · '.t('cars.customer_link_missing'),$detail,'?car='.(int)$r['id'].'#asiakkuus','unlinked-customer-car-'.(int)$r['id']);
        }
        foreach($db->query("SELECT c.id,c.reg_plate,c.nickname,COUNT(s.id) cnt FROM cars c JOIN services s ON s.car_id=c.id WHERE c.current_customer_id IS NOT NULL AND c.current_customer_id<>0 AND TRIM(COALESCE(s.customer_name_snapshot,''))='' AND (s.customer_id IS NULL OR s.customer_id=0) GROUP BY c.id,c.reg_plate,c.nickname HAVING COUNT(s.id)>0 ORDER BY c.id")->fetchAll() as $r){
            $name=trim((string)($r['reg_plate']?:$r['nickname']?:(t('cars.car_number',['id'=>(int)$r['id']]))));$cnt=(int)$r['cnt'];
            $add('info',$name.' · '.t('cars.customer_snapshots_missing'),t('cars.issue_customer_snapshots_detail',['n'=>$cnt]),'?car='.(int)$r['id'].'#asiakkuus','missing-customer-snapshots-car-'.(int)$r['id']);
        }
    }

    foreach($db->query("SELECT id,name,postal_code,city FROM customers WHERE TRIM(postal_code)<>'' AND postal_code NOT GLOB '[0-9][0-9][0-9][0-9][0-9]' ORDER BY name,id")->fetchAll() as $r){
        $add('info',(string)$r['name'].' · '.t('cars.postal_code'),t('cars.issue_postal_detail',['postal'=>(string)$r['postal_code'],'city'=>(string)$r['city']]),'?view=customers&customer='.(int)$r['id'],'customer-postal-'.(int)$r['id']);
    }
    foreach($db->query("SELECT c.id,c.reg_plate,c.nickname,c.current_customer_id FROM cars c LEFT JOIN customers cu ON cu.id=c.current_customer_id WHERE c.current_customer_id IS NOT NULL AND c.current_customer_id<>0 AND cu.id IS NULL")->fetchAll() as $r)$add('error',t('cars.issue_broken_car_customer'),t('cars.issue_broken_car_customer_detail',['name'=>trim((string)($r['reg_plate']?:$r['nickname']?:(t('cars.car_number',['id'=>(int)$r['id']])))),'id'=>(int)$r['current_customer_id']]),'?car='.(int)$r['id'].'#asiakkuus','orphan-car-customer-'.(int)$r['id']);
    foreach($db->query("SELECT s.id,s.car_id,s.customer_id,c.reg_plate,c.nickname FROM services s JOIN cars c ON c.id=s.car_id LEFT JOIN customers cu ON cu.id=s.customer_id WHERE s.customer_id IS NOT NULL AND s.customer_id<>0 AND cu.id IS NULL")->fetchAll() as $r)$add('error',t('cars.issue_broken_service_customer'),t('cars.issue_broken_service_customer_detail',['name'=>trim((string)($r['reg_plate']?:$r['nickname']?:(t('cars.car_number',['id'=>(int)$r['car_id']])))),'service'=>(int)$r['id'],'id'=>(int)$r['customer_id']]),'?car='.(int)$r['car_id'].'&edit_service='.(int)$r['id'].'#huolto','orphan-service-customer-'.(int)$r['id']);
    foreach($db->query("SELECT s.id,s.car_id,s.mechanic_id,c.reg_plate,c.nickname FROM services s JOIN cars c ON c.id=s.car_id LEFT JOIN mechanics m ON m.id=s.mechanic_id WHERE s.mechanic_id IS NOT NULL AND s.mechanic_id<>0 AND m.id IS NULL")->fetchAll() as $r)$add('error',t('cars.issue_broken_service_mechanic'),t('cars.issue_broken_service_mechanic_detail',['name'=>trim((string)($r['reg_plate']?:$r['nickname']?:(t('cars.car_number',['id'=>(int)$r['car_id']])))),'service'=>(int)$r['id'],'id'=>(int)$r['mechanic_id']]),'?car='.(int)$r['car_id'].'&edit_service='.(int)$r['id'].'#huolto','orphan-service-mechanic-'.(int)$r['id']);

    foreach($db->query("SELECT vin,COUNT(*) cnt,GROUP_CONCAT(reg_plate, ', ') regs FROM cars WHERE TRIM(vin)<>'' GROUP BY vin HAVING COUNT(*)>1")->fetchAll() as $r)$add('warn',t('cars.issue_duplicate_vin'),(string)$r['vin'].' · '.(string)$r['regs'],'','dup-vin-'.md5((string)$r['vin']));
    foreach($db->query("SELECT reg_plate,COUNT(*) cnt FROM cars WHERE TRIM(reg_plate)<>'' GROUP BY UPPER(reg_plate) HAVING COUNT(*)>1")->fetchAll() as $r)$add('warn',t('cars.issue_duplicate_plate'),(string)$r['reg_plate'],'','dup-plate-'.md5(strtoupper((string)$r['reg_plate'])));
    foreach($db->query("SELECT sp.file_path,c.id car_id,c.reg_plate FROM service_photos sp JOIN cars c ON c.id=sp.car_id")->fetchAll() as $r){$abs=photoAbsolutePath((string)$r['file_path']);if(!$abs||!is_file($abs))$add('warn',($r['reg_plate']?:t('cars.car')).' · '.t('cars.missing_image'),t('cars.issue_missing_image_detail',['path'=>(string)$r['file_path']]),'?car='.(int)$r['car_id'].'#historia','missing-image-'.md5((string)$r['file_path']));}
    $rank=['error'=>0,'warn'=>1,'info'=>2]; usort($out,fn($a,$b)=>($rank[$a['level']]<=>$rank[$b['level']])?:strcmp($a['title'],$b['title'])); return $out;
}

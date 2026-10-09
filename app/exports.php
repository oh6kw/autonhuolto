<?php
declare(strict_types=1);

/**
 * app/exports.php – Excel-viennit (.xlsx).
 * xlsx-tiedoston rakennus ilman ulkoisia kirjastoja (ZipArchive), yhden auton vienti, kaikkien autojen vienti ja varastovienti.
 * Vain funktiot. Ladataan index.php:n alussa; viennit käynnistetään app/actions.php:n GET-käsittelystä.
 */

function xlsxXml(string $v): string { $v=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',$v)??''; return htmlspecialchars($v,ENT_XML1|ENT_QUOTES,'UTF-8'); }
function xlsxCol(int $n): string { $s=''; while($n>0){$n--; $s=chr(65+($n%26)).$s; $n=intdiv($n,26);} return $s; }
function xlsxSafeSheetName(string $name,array $used=[]): string {
    $name=preg_replace('/[\\\\\\/\\?\\*\\[\\]\\:]+/u',' ',$name)??'Taulukko'; $name=trim(preg_replace('/\\s+/u',' ',$name)??'Taulukko'); if($name==='')$name='Taulukko';
    $base=mb_substr($name,0,31,'UTF-8'); $try=$base; $i=2;
    while(in_array(mb_strtolower($try,'UTF-8'),array_map(fn($x)=>mb_strtolower($x,'UTF-8'),$used),true)){ $suffix=' '.$i++; $try=mb_substr($base,0,max(1,31-mb_strlen($suffix,'UTF-8')),'UTF-8').$suffix; }
    return $try;
}
function xlsxSheetXml(array $rows,bool $freezeTop=true,bool $autoFilter=true): string {
    $maxCols=1; foreach($rows as $r)$maxCols=max($maxCols,count($r)); $lastCol=xlsxCol($maxCols); $lastRow=max(1,count($rows));
    $views=$freezeTop?'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>':'<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
    $out='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$views.'<sheetFormatPr defaultRowHeight="15"/><sheetData>';
    foreach($rows as $ri=>$row){ $rn=$ri+1; $out.='<row r="'.$rn.'">'; foreach(array_values($row) as $ci=>$value){ if($value===null||$value==='')continue; $ref=xlsxCol($ci+1).$rn; if(is_int($value)||is_float($value)){ $out.='<c r="'.$ref.'"><v>'.xlsxXml((string)$value).'</v></c>'; } else { $out.='<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.xlsxXml((string)$value).'</t></is></c>'; } } $out.='</row>'; }
    $out.='</sheetData>'; if($autoFilter&&count($rows)>1)$out.='<autoFilter ref="A1:'.$lastCol.$lastRow.'"/>'; return $out.'</worksheet>';
}
function outputXlsx(string $filename,array $sheets,array $csvFallback=[]): never {
    if(!class_exists('ZipArchive')){
        $csv=$csvFallback ?: (array_values($sheets)[0]??[]);
        $filename=preg_replace('/\\.xlsx$/i','.csv',$filename)??($filename.'.csv');
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$filename.'"'); header('Cache-Control:no-store');
        echo "\xEF\xBB\xBF"; $fh=fopen('php://output','wb'); foreach($csv as $row)fputcsv($fh,$row,';'); fclose($fh); exit;
    }
    $used=[];$normalized=[]; foreach($sheets as $name=>$rows){$safe=xlsxSafeSheetName((string)$name,$used);$used[]=$safe;$normalized[$safe]=$rows;}
    $tmp=tempnam(sys_get_temp_dir(),'huolto-xlsx-'); if($tmp===false)throw new RuntimeException('Excel-viennin väliaikaistiedostoa ei saatu luotua.'); @unlink($tmp); $tmp.='.xlsx';
    $zip=new ZipArchive(); if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Excel-tiedostoa ei saatu luotua.');
    $ct='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $wb='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $i=1; foreach($normalized as $name=>$rows){$ct.='<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';$wb.='<sheet name="'.xlsxXml($name).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';$rels.='<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';$zip->addFromString('xl/worksheets/sheet'.$i.'.xml',xlsxSheetXml($rows,true,true));$i++;}
    $ct.='</Types>';$wb.='</sheets></workbook>';$rels.='</Relationships>';
    $zip->addFromString('[Content_Types].xml',$ct);
    $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml',$wb);$zip->addFromString('xl/_rels/workbook.xml.rels',$rels);$zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="'.$filename.'"'); header('Content-Length: '.filesize($tmp)); header('Cache-Control:no-store'); readfile($tmp); @unlink($tmp); exit;
}
function carExportSheets(PDO $db,int $carId): array {
    $car=getCar($db,$carId); if(!$car)throw new RuntimeException('Autoa ei löytynyt.');
    $exportApp=appSettings($db);$exportAppName=trim((string)($exportApp['shop_name']??DEFAULT_APP_NAME))?:DEFAULT_APP_NAME;$exportCustomer=currentCustomerForCar($db,$carId);
    $st=$db->prepare("SELECT * FROM services WHERE car_id=? ORDER BY service_date DESC,id DESC");$st->execute([$carId]);$services=$st->fetchAll();
    $first=$services?end($services):null; if($services)reset($services); $latest=$services[0]??null; $exportServices=array_reverse($services);
    $summary=[
        ['Kenttä','Arvo'],
        ['Ohjelma / korjaamo',$exportAppName],
        ['Rekisteri',(string)$car['reg_plate']],['Lempinimi',(string)$car['nickname']],['Asiakas',$exportCustomer?(string)$exportCustomer['name']:(string)$car['owner']],['Asiakasnumero',$exportCustomer?(string)$exportCustomer['customer_number']:''],['Omistaja / käyttäjä (legacy)',(string)$car['owner']],
        ['Merkki',(string)$car['make']],['Malli',(string)$car['model']],['Vuosimalli',(string)$car['year']],['Ensirekisteröinti',fiDate((string)($car['first_registration_date']??''))],
        ['VIN',(string)$car['vin']],['Moottori',(string)$car['engine']],['Nykyinen km',(int)$car['current_km']],
        ['Moottoriöljy määrä L',$car['engine_oil_capacity']!==null?(float)$car['engine_oil_capacity']:''],['Moottoriöljy speksi',(string)($car['engine_oil_spec']??'')],
        ['Vaihteistoöljy määrä L',$car['gearbox_oil_capacity']!==null?(float)$car['gearbox_oil_capacity']:''],['Vaihteistoöljy speksi',(string)($car['gearbox_oil_spec']??'')],
        ['Jäähdytysneste määrä L',$car['coolant_capacity']!==null?(float)$car['coolant_capacity']:''],['Jäähdytysneste speksi',(string)($car['coolant_spec']??'')],
        ['Huoltoja',count($services)],['Ensimmäinen kirjattu huolto',$first?fiDate((string)$first['service_date']):''],['Viimeisin kirjattu huolto',$latest?fiDate((string)$latest['service_date']):''],
        ['Viimeisin huolto km',$latest?(int)$latest['odometer']:''],
        ['Ajomääräarvio km/v',($drive=drivingRateEstimate($db,$car))['available']?(int)$drive['annual_km']:''],['Ajomääräarvion aineisto',$drive['available']?(string)$drive['sample_text']:'Ei riittävästi historiaa'],
        ['Hintojen syöttötapa',priceInputMode($exportApp)==='gross'?'Verollinen':'ALV 0 %'],['ALV %',appVatRate($exportApp)],
        ['Muistiinpanot',(string)$car['notes']]
    ];
    $history=[['Päivämäärä','Kilometrit','Edellisestä huoltomerkinnästä km','Edellisestä huoltomerkinnästä aika','Tapahtuman tyyppi','Huollon nimi','Tehdyt työt','Huoltopaikka','Työn alkuperä','Asiakas tapahtumahetkellä','Mekaanikko','Toteutunut työaika','Laskutettava työaika h','Huomautukset','Omat kulut','Kuvia']];
    $lines=[['Päivämäärä','Kilometrit','Tapahtuman tyyppi','Huollon nimi','Huoltokohde','Toimenpide','Huoltoväli alkaa tästä','Määrä','Yksikkö','Merkki','Varaosanumero','OEM','Huomio','Hinta ALV 0','ALV %','Hinta sis. ALV','Kohdekohtainen huoltoväli']];
    foreach($exportServices as &$svc)$svc['actions']=getServiceActions($db,(int)$svc['id']);unset($svc);
    attachServiceIntervals($db,$exportServices);
    foreach($exportServices as $i=>$svc){
        $actions=$svc['actions'];$custom=getCustomActions($db,(int)$svc['id']);$photos=getServicePhotos($db,(int)$svc['id']);$gap=intervalInfo($svc,$exportServices[$i-1]??null);
        $work=array_map(fn($a)=>(string)$a['label'],$actions);foreach($custom as $a)$work[]=(string)$a['description'];
        $gapKm=''; if(!empty($gap['km']))$gapKm=(int)str_replace([' ','km'],['',''],$gap['km']);
        $history[]=[fiDate($svc['service_date']),(int)$svc['odometer'],$gapKm,$gap['time']??'',(string)($svc['service_type']??'Määräaikaishuolto'),(string)$svc['title'],implode(', ',$work),(string)$svc['workshop'],(string)($svc['service_origin']??''),(string)($svc['customer_name_snapshot']??''),(string)($svc['mechanic_name_snapshot']??''),(int)($svc['actual_work_seconds']??0)>0?formatDuration((int)$svc['actual_work_seconds']):'',(float)($svc['labor_hours']??0)>0?(float)$svc['labor_hours']:'',(string)$svc['notes'],$svc['total_cost']!==null?(float)$svc['total_cost']:'',count($photos)];
        foreach($actions as $a){$pn=$a['price_net']!==null?(float)$a['price_net']:null;$lines[]=[fiDate($svc['service_date']),(int)$svc['odometer'],(string)($svc['service_type']??'Määräaikaishuolto'),(string)$svc['title'],(string)$a['label'],serviceActionLabel($a),in_array(intervalTrackingKey($a),intervalStartKeys($a),true)?'Kyllä':'Ei',$a['quantity']!==null?(float)$a['quantity']:'',(string)($a['unit']??''),(string)$a['brand'],(string)$a['supplier_sku'],(string)$a['oem_number'],(string)$a['notes'],$pn??'',$pn!==null?(float)($a['vat_rate']??appVatRate($exportApp)):'',$pn!==null?$pn*(1+(float)($a['vat_rate']??appVatRate($exportApp))/100):'',actionIntervalText($a)];}
        foreach($custom as $a){$pn=$a['price_net']!==null?(float)$a['price_net']:null;$lines[]=[fiDate($svc['service_date']),(int)$svc['odometer'],(string)($svc['service_type']??'Määräaikaishuolto'),(string)$svc['title'],'Muu työ','Tehty','Ei','','','','','',(string)$a['description'].(($a['notes']??'')!==''?' · '.$a['notes']:''),$pn??'',$pn!==null?(float)($a['vat_rate']??appVatRate($exportApp)):'',$pn!==null?$pn*(1+(float)($a['vat_rate']??appVatRate($exportApp))/100):'',actionIntervalText($a)];}
    }
    $settings=[];$st=$db->prepare("SELECT * FROM car_item_settings WHERE car_id=?");$st->execute([$carId]);foreach($st->fetchAll() as $r)$settings[$r['item_key']]=$r;$settings=normalizeBeltSettings($settings);
    $events=lastItemEvents($db,$carId);$drive=$drive??drivingRateEstimate($db,$car);$maintenance=calculateMaintenanceStatus($db,$car,null,$settings,$events,$drive);$program=[['Kohde','Ryhmä','Tavoite','Viimeksi','Viimeksi km','km-väli','kk-väli','Seuraava km','Seuraava päivä','Tila','Jäljellä / yli','Huoltoennuste','Ensin tulee','Ajettu viime huollosta','Aikaa viime huollosta','Todellinen edellinen väli','Laskennan huomio']];
    foreach(allItems($db,true) as $it){$set=$settings[$it['item_key']]??[];if(isset($set['enabled'])&&(int)$set['enabled']!==1)continue;$state=$maintenance[$it['item_key']];$last=$state['last'];$due=$state['due'];$fc=$state['forecast'];$firstLabel=($fc['first']??'')==='km'?'Kilometriraja':(($fc['first']??'')==='time'?'Aikaraja':(($fc['first']??'')==='due'?'Jo ajankohtainen':''));$program[]=[(string)$it['label'],(string)$it['section'],scheduleTypes()[validScheduleType((string)($set['schedule_type']??'replace'))],$last?fiDate((string)$last['service_date']):'',$last?(int)$last['odometer']:'',(int)($set['interval_km']??0),(int)($set['interval_months']??0),(int)($due['due_km']??0),!empty($due['due_date'])?fiDate($due['due_date']):'',(string)$due['text'],(string)($due['remain']??''),!empty($fc['date'])?fiDate((string)$fc['date']):(string)($fc['text']??''),$firstLabel,$state['elapsed']['km'],$state['elapsed']['time'],$state['actual']['text'],$state['warning']];}
    $parts=[['Kohde','Nimi','Merkki','Varaosanumero','OEM','Sopii autoihin','Linkki','Muistiinpano','Varastosaldo','Yksikkö','Hyllypaikka','Varoitusraja','Hankintahinta / yks. ALV 0 %','Hankintahinta / yks. sis. ALV '.dec(appVatRate($exApp=appSettings($db)),1).' %']];$st=$db->prepare("SELECT p.*,ic.label item_label FROM parts p LEFT JOIN item_catalog ic ON ic.item_key=p.item_key WHERE EXISTS (SELECT 1 FROM part_car_compatibility pc WHERE pc.part_id=p.id AND pc.car_id=?) ORDER BY COALESCE(ic.sort_order,99999),p.part_name,p.id");$st->execute([$carId]);foreach($st->fetchAll() as $r)$parts[]=[(string)($r['item_label']??''),(string)$r['part_name'],(string)$r['brand'],(string)$r['supplier_sku'],(string)$r['oem_number'],partCompatibilityText($db,(int)$r['id']),(string)$r['url'],(string)$r['notes'],(float)($r['stock_qty']??0),(string)($r['stock_unit']??'kpl'),(string)($r['shelf_location']??''),$r['reorder_level']!==null?(float)$r['reorder_level']:'',...array_map(fn($v)=>$v??'',purchasePriceNetGross($exApp,$r['purchase_price']!==null?(float)$r['purchase_price']:null))];
    $odometer=[['Päivä / aika','Kilometrit','Lähde','Tapahtuma / huomio','Poikkeama']];foreach(kilometerHistoryEntries($db,$carId,false) as $pt){$src=(string)$pt['source'];$time=($src==='service'||$src==='old')?fiDate((string)$pt['date']):date('d.m.Y H:i',strtotime((string)$pt['datetime']));$odometer[]=[$time,(int)$pt['km']>0?(int)$pt['km']:'',(string)$pt['source_label'],(string)($pt['note']??''),!empty($pt['warning'])?(string)$pt['warning_text']:''];}
    $checks=[['Taso','Otsikko','Huomio']];foreach(dataCheckIssues($db) as $i){$u=(string)($i['url']??'');if(str_contains($u,'car='.$carId))$checks[]=[(string)$i['level'],(string)$i['title'],(string)$i['detail']];}
    return ['Yhteenveto'=>$summary,'Huollot'=>$history,'Huoltorivit'=>$lines,'Huolto-ohjelma'=>$program,'Kilometrihistoria'=>$odometer,'Varaosat'=>$parts,'Tarkistukset'=>$checks];
}
function exportCarXlsx(PDO $db,int $carId): never {
    $car=getCar($db,$carId); if(!$car){http_response_code(404);exit('Autoa ei löytynyt.');}
    $base=preg_replace('/[^A-Za-z0-9_-]+/','-',strtoupper((string)($car['reg_plate']?:$car['nickname'])))?:('auto-'.$carId);
    $sheets=carExportSheets($db,$carId); outputXlsx($base.'_'.preg_replace('/[^A-Za-z0-9_-]+/','-',trim($car['make'].'_'.$car['model'])).'_huoltohistoria_'.date('Y-m-d').'.xlsx',$sheets,$sheets['Huollot']??[]);
}
function inventoryExportPartRows(PDO $db,string $q='',int $carFilter=0,bool $lowOnly=false,string $sort='car',string $dir='asc'): array {
    $sort=in_array($sort,['car','part','shelf','stock','reorder','price','value'],true)?$sort:'car';$dir=$dir==='desc'?'desc':'asc';
    $rows=inventoryPartRows($db);
    $rows=array_values(array_filter($rows,function(array $r)use($q,$carFilter,$lowOnly):bool{
        if($carFilter>0&&!in_array($carFilter,array_map('intval',array_filter(explode(',',(string)($r['compat_car_ids']??'')))),true))return false;
        if($lowOnly&&($r['reorder_level']===null||(float)$r['stock_qty']>(float)$r['reorder_level']))return false;
        if($q!==''){$hay=implode(' ',[(string)$r['part_name'],(string)$r['brand'],(string)$r['supplier_sku'],(string)$r['oem_number'],(string)$r['shelf_location'],(string)($r['compat_search']??'')]);if(mb_stripos($hay,$q,0,'UTF-8')===false)return false;}
        return true;
    }));
    $value=function(array $r,string $key):mixed{return match($key){'car'=>mb_strtolower((string)($r['compat_labels']??''),'UTF-8'),'part'=>mb_strtolower(trim((string)$r['part_name'].' '.$r['brand'].' '.$r['supplier_sku'].' '.$r['oem_number']),'UTF-8'),'shelf'=>mb_strtolower((string)$r['shelf_location'],'UTF-8'),'stock'=>(float)$r['stock_qty'],'reorder'=>$r['reorder_level']===null?null:(float)$r['reorder_level'],'price'=>$r['purchase_price']===null?null:(float)$r['purchase_price'],'value'=>(float)$r['stock_qty']*(float)($r['purchase_price']??0),default=>''};};
    usort($rows,function(array $a,array $b)use($value,$sort,$dir):int{$av=$value($a,$sort);$bv=$value($b,$sort);if($av===null&&$bv===null)$c=0;elseif($av===null)$c=1;elseif($bv===null)$c=-1;elseif(is_float($av)||is_int($av))$c=$av<=>$bv;else$c=strnatcasecmp((string)$av,(string)$bv);if($c===0)$c=(int)$a['id']<=>(int)$b['id'];return $dir==='desc'?-$c:$c;});
    return $rows;
}
function inventoryExportSheets(PDO $db,array $partRows,string $title='Varaosavarasto',array $filters=[]): array {
    $items=itemMap($db);$invApp=appSettings($db);$vatTxt=dec(appVatRate($invApp),1);$parts=[['Huoltokohde','Varaosa','Merkki','Varaosanumero','OEM','Sopii autoihin','Hyllypaikka','Saldo','Yksikkö','Varoitusraja','Hankintahinta / yks. ALV 0 %','Hankintahinta / yks. sis. ALV '.$vatTxt.' %','Varaston arvo ALV 0 %','Varaston arvo sis. ALV '.$vatTxt.' %','Tila','Tuotelinkki','Muistiinpano']];
    $totalValue=0.0;$totalValueGross=0.0;$lowCount=0;$partIds=[];
    foreach($partRows as $r){$pid=(int)$r['id'];$partIds[]=$pid;$qty=(float)($r['stock_qty']??0);[$priceNet,$priceGross]=purchasePriceNetGross($invApp,$r['purchase_price']!==null?(float)$r['purchase_price']:null);$stockValue=$priceNet!==null?round($qty*$priceNet,2):null;$stockValueGross=$priceGross!==null?round($qty*$priceGross,2):null;$low=$r['reorder_level']!==null&&$qty<=(float)$r['reorder_level'];if($stockValue!==null){$totalValue+=$stockValue;$totalValueGross+=$stockValueGross;}if($low)$lowCount++;$parts[]=[(string)($items[(string)$r['item_key']]['label']??$r['item_key']),(string)$r['part_name'],(string)$r['brand'],(string)$r['supplier_sku'],(string)$r['oem_number'],(string)($r['compat_labels']??partCompatibilityText($db,$pid)),(string)$r['shelf_location'],$qty,(string)($r['stock_unit']?:'kpl'),$r['reorder_level']!==null?(float)$r['reorder_level']:'',$priceNet??'',$priceGross??'',$stockValue??'',$stockValueGross??'',$low?'Varoitusrajalla':'OK',(string)$r['url'],(string)$r['notes']];}
    $summary=[['Varaosavaraston Excel-vienti',''],['Otsikko',$title],['Luotu',date('d.m.Y H:i')],['Nimikkeitä',count($partRows)],['Varoitusrajalla',$lowCount],['Varaston arvo ALV 0 %',round($totalValue,2)],['Varaston arvo sis. ALV '.$vatTxt.' %',round($totalValueGross,2)]];foreach($filters as $label=>$val){if((string)$val!=='')$summary[]=[$label,(string)$val];}
    $inventoryApp=appSettings($db);if(!empty($inventoryApp['inventory_history_cleared_at'])){$summary[]=['Varastoloki viimeksi tyhjennetty',date('d.m.Y H:i',strtotime($inventoryApp['inventory_history_cleared_at']))];$summary[]=['Tyhjentäjä',$inventoryApp['inventory_history_cleared_by']??''];}
    $transactions=[['Päivä / aika','Varaosa','Merkki','Varaosanumero','OEM','Sopii autoihin','Hyllypaikka','Muutos','Yksikkö','Tapahtuma','Saldo tapahtuman jälkeen','Huomio']];
    if($partIds){$marks=implode(',',array_fill(0,count($partIds),'?'));$st=$db->prepare("SELECT t.*,p.part_name,p.brand,p.supplier_sku,p.oem_number,p.stock_unit,p.shelf_location,p.stock_qty current_stock FROM inventory_transactions t JOIN parts p ON p.id=t.part_id WHERE t.part_id IN ($marks) ORDER BY t.created_at ASC,t.id ASC");$st->execute($partIds);$txRows=$st->fetchAll();$sum=[];$current=[];foreach($txRows as $t){$pid=(int)$t['part_id'];$sum[$pid]=($sum[$pid]??0.0)+(float)$t['quantity_change'];$current[$pid]=(float)$t['current_stock'];}$running=[];foreach($sum as $pid=>$delta)$running[$pid]=($current[$pid]??0.0)-$delta;foreach($txRows as $t){$pid=(int)$t['part_id'];$running[$pid]=($running[$pid]??0.0)+(float)$t['quantity_change'];$transactions[]=[date('d.m.Y H:i',strtotime((string)$t['created_at'])),(string)$t['part_name'],(string)$t['brand'],(string)$t['supplier_sku'],(string)$t['oem_number'],partCompatibilityText($db,$pid),(string)$t['shelf_location'],(float)$t['quantity_change'],(string)($t['stock_unit']?:'kpl'),inventoryEventLabel((string)$t['event_type']),(float)$running[$pid],(string)$t['note']];}}
    return ['Yhteenveto'=>$summary,'Varaosat'=>$parts,'Varastotapahtumat'=>$transactions];
}
function exportInventoryXlsx(PDO $db): never {
    $q=trim((string)($_GET['q']??''));$carFilter=(int)($_GET['car_id']??0);$lowOnly=(string)($_GET['low']??'')==='1';$sort=(string)($_GET['sort']??'car');$dir=(string)($_GET['dir']??'asc');
    $rows=inventoryExportPartRows($db,$q,$carFilter,$lowOnly,$sort,$dir);$filters=[];if($q!=='')$filters['Haku']=$q;if($carFilter>0){$c=getCar($db,$carFilter);if($c)$filters['Auto']=trim((string)($c['reg_plate']?:$c['nickname']).' · '.trim((string)$c['make'].' '.(string)$c['model']));}if($lowOnly)$filters['Rajaus']='Vain varoitusrajalla / vähissä';
    $sheets=inventoryExportSheets($db,$rows,'Varaosavarasto',$filters);$app=appSettings($db);$name=trim((string)($app['shop_name']??DEFAULT_APP_NAME))?:DEFAULT_APP_NAME;$ascii=@iconv('UTF-8','ASCII//TRANSLIT',$name);$ascii=$ascii!==false?$ascii:$name;$base=trim((string)(preg_replace('/[^A-Za-z0-9_-]+/','-',$ascii)??''),'-_')?:'Autonhuolto';outputXlsx($base.'_varaosavarasto_'.date('Y-m-d').'.xlsx',$sheets,$sheets['Varaosat']??[]);
}
function exportAllCarsXlsx(PDO $db): never {
    $cars=$db->query("SELECT c.*,cu.name current_customer_name,cu.customer_number current_customer_number FROM cars c LEFT JOIN customers cu ON cu.id=c.current_customer_id ORDER BY COALESCE(NULLIF(cu.name,''),NULLIF(c.owner,''),'~'),COALESCE(NULLIF(c.nickname,''),c.reg_plate),c.id")->fetchAll();
    $carRows=[['Rekisteri','Lempinimi','Asiakas','Asiakasnumero','Omistaja / käyttäjä (legacy)','Merkki','Malli','Vuosimalli','Ensirekisteröinti','VIN','Moottori','Nykyinen km','Huoltoja','Viimeisin huolto']];
    $sheets=[];
    foreach($cars as $car){
        $cid=(int)$car['id'];
        $st=$db->prepare("SELECT * FROM services WHERE car_id=? ORDER BY service_date DESC,id DESC");$st->execute([$cid]);$servicesDesc=$st->fetchAll();$latest=$servicesDesc[0]??null;
        $services=array_reverse($servicesDesc);
        $carRows[]=[(string)$car['reg_plate'],(string)$car['nickname'],(string)($car['current_customer_name']?:$car['owner']),(string)($car['current_customer_number']??''),(string)$car['owner'],(string)$car['make'],(string)$car['model'],(string)$car['year'],fiDate((string)($car['first_registration_date']??'')),(string)$car['vin'],(string)$car['engine'],(int)$car['current_km'],count($servicesDesc),$latest?fiDate((string)$latest['service_date']):''];
        $carSheet=[['Päivämäärä','Kilometrit','Tyyppi','Huollon nimi','Tehdyt työt','Mekaanikko','Toteutunut työaika','Huomiot']];
        foreach($services as $svc){
            $acts=getServiceActions($db,(int)$svc['id']);$custom=getCustomActions($db,(int)$svc['id']);$work=array_map(fn($a)=>(string)$a['label'],$acts);foreach($custom as $a)$work[]=(string)$a['description'];
            $carSheet[]=[fiDate((string)$svc['service_date']),(int)$svc['odometer'],(string)$svc['service_type'],(string)$svc['title'],implode(', ',$work),(string)($svc['mechanic_name_snapshot']??''),(int)($svc['actual_work_seconds']??0)>0?formatDuration((int)$svc['actual_work_seconds']):'',(string)$svc['notes']];
        }
        $sheetName=trim((string)($car['reg_plate']?:$car['nickname']?:('Auto '.$cid)));$sheets[$sheetName]=$carSheet;
    }

    // Yhteiset huolto- ja huoltorivivälilehdet ovat aidosti kronologisia kaikkien autojen kesken.
    $allHistory=[['Rekisteri','Auto','Asiakas tapahtumahetkellä','Omistaja / käyttäjä (legacy)','Päivämäärä','Kilometrit','Tapahtuman tyyppi','Huollon nimi','Tehdyt työt','Huoltopaikka','Mekaanikko','Toteutunut työaika','Huomautukset','Kulut']];
    $allLines=[['Rekisteri','Auto','Päivämäärä','Kilometrit','Huoltokohde','Toimenpide','Määrä','Yksikkö','Merkki','Varaosanumero','OEM','Huomio','Kohdekohtainen huoltoväli']];
    $q=$db->query("SELECT s.*,c.reg_plate,c.nickname,c.owner,c.make,c.model FROM services s JOIN cars c ON c.id=s.car_id ORDER BY s.service_date ASC,s.id ASC");
    $exportAllServices=$q->fetchAll();foreach($exportAllServices as &$svc)$svc['actions']=getServiceActions($db,(int)$svc['id']);unset($svc);attachServiceIntervals($db,$exportAllServices);
    foreach($exportAllServices as $svc){
        $acts=$svc['actions'];$custom=getCustomActions($db,(int)$svc['id']);$work=array_map(fn($a)=>(string)$a['label'],$acts);foreach($custom as $a)$work[]=(string)$a['description'];
        $auto=trim((string)($svc['nickname']?:$svc['reg_plate']));$mm=trim((string)$svc['make'].' '.(string)$svc['model']);if($mm!=='')$auto=$auto!==''?$auto.' · '.$mm:$mm;
        $allHistory[]=[(string)$svc['reg_plate'],$auto,(string)($svc['customer_name_snapshot']??''),(string)$svc['owner'],fiDate((string)$svc['service_date']),(int)$svc['odometer'],(string)$svc['service_type'],(string)$svc['title'],implode(', ',$work),(string)$svc['workshop'],(string)($svc['mechanic_name_snapshot']??''),(int)($svc['actual_work_seconds']??0)>0?formatDuration((int)$svc['actual_work_seconds']):'',(string)$svc['notes'],$svc['total_cost']!==null?(float)$svc['total_cost']:''];
        foreach($acts as $a)$allLines[]=[(string)$svc['reg_plate'],$auto,fiDate((string)$svc['service_date']),(int)$svc['odometer'],(string)$a['label'],(string)$a['action'],$a['quantity']!==null?(float)$a['quantity']:'',(string)$a['unit'],(string)$a['brand'],(string)$a['supplier_sku'],(string)$a['oem_number'],(string)$a['notes'],actionIntervalText($a)];
    }
    $allOdometer=[['Rekisteri','Auto','Päivä / aika','Kilometrit','Lähde','Tapahtuma / huomio','Poikkeama']];$odoRows=[];
    foreach($cars as $oc){$cid=(int)$oc['id'];$auto=trim((string)($oc['nickname']?:$oc['reg_plate']));$mm=trim((string)$oc['make'].' '.(string)$oc['model']);if($mm!=='')$auto=$auto!==''?$auto.' · '.$mm:$mm;foreach(kilometerHistoryEntries($db,$cid,false) as $pt)$odoRows[]=['reg'=>(string)$oc['reg_plate'],'auto'=>$auto,'pt'=>$pt];}
    usort($odoRows,fn($a,$b)=>strcmp((string)$a['pt']['datetime'],(string)$b['pt']['datetime'])?:strcmp((string)$a['reg'],(string)$b['reg']));
    foreach($odoRows as $or){$pt=$or['pt'];$src=(string)$pt['source'];$time=($src==='service'||$src==='old')?fiDate((string)$pt['date']):date('d.m.Y H:i',strtotime((string)$pt['datetime']));$allOdometer[]=[$or['reg'],$or['auto'],$time,(int)$pt['km']>0?(int)$pt['km']:'',(string)$pt['source_label'],(string)($pt['note']??''),!empty($pt['warning'])?(string)$pt['warning_text']:''];}
    $inventorySheets=inventoryExportSheets($db,inventoryExportPartRows($db),'Koko varaosarekisteri');
    $sheets=['Kaikki autot'=>$carRows,'Kaikki huollot'=>$allHistory,'Kaikki huoltorivit'=>$allLines,'Kaikki kilometrihistoria'=>$allOdometer,'Varastoyhteenveto'=>$inventorySheets['Yhteenveto'],'Varaosat'=>$inventorySheets['Varaosat'],'Varastotapahtumat'=>$inventorySheets['Varastotapahtumat']]+$sheets;
    $exportApp=appSettings($db);$exportName=trim((string)($exportApp['shop_name']??DEFAULT_APP_NAME))?:DEFAULT_APP_NAME;
    $ascii=@iconv('UTF-8','ASCII//TRANSLIT',$exportName);$ascii=$ascii!==false?$ascii:$exportName;
    $fileBase=trim((string)(preg_replace('/[^A-Za-z0-9_-]+/','-',$ascii)??''),'-_')?:'Autonhuolto';
    outputXlsx($fileBase.'_kaikki_autot_'.date('Y-m-d').'.xlsx',$sheets,$allHistory);
}

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
    $name=preg_replace('/[\\\\\\/\\?\\*\\[\\]\\:]+/u',' ',$name)??t('export.sheet_default'); $name=trim(preg_replace('/\\s+/u',' ',$name)??t('export.sheet_default')); if($name==='')$name=t('export.sheet_default');
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
    $tmp=tempnam(sys_get_temp_dir(),'huolto-xlsx-'); if($tmp===false)throw new RuntimeException(t('export.err_tmp_file')); @unlink($tmp); $tmp.='.xlsx';
    $zip=new ZipArchive(); if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException(t('export.err_create_file'));
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
    $car=getCar($db,$carId); if(!$car)throw new RuntimeException(t('export.err_car_not_found'));
    $exportApp=appSettings($db);$exportAppName=trim((string)($exportApp['shop_name']??DEFAULT_APP_NAME))?:DEFAULT_APP_NAME;$exportCustomer=currentCustomerForCar($db,$carId);
    $st=$db->prepare("SELECT * FROM services WHERE car_id=? ORDER BY service_date DESC,id DESC");$st->execute([$carId]);$services=$st->fetchAll();
    $first=$services?end($services):null; if($services)reset($services); $latest=$services[0]??null; $exportServices=array_reverse($services);
    $summary=[
        [t('export.col_field'),t('export.col_value')],
        [t('export.col_program_shop'),$exportAppName],
        [t('export.col_reg'),(string)$car['reg_plate']],[t('export.col_nickname'),(string)$car['nickname']],[t('export.col_customer'),$exportCustomer?(string)$exportCustomer['name']:(string)$car['owner']],[t('export.col_customer_number'),$exportCustomer?(string)$exportCustomer['customer_number']:''],[t('export.col_owner_legacy'),(string)$car['owner']],
        [t('export.col_make'),(string)$car['make']],[t('export.col_model'),(string)$car['model']],[t('export.col_year'),(string)$car['year']],[t('export.col_first_reg'),fiDate((string)($car['first_registration_date']??''))],
        [t('export.col_vin'),(string)$car['vin']],[t('export.col_engine'),(string)$car['engine']],[t('export.col_current_km'),(int)$car['current_km']],
        [t('export.col_engine_oil_cap'),$car['engine_oil_capacity']!==null?(float)$car['engine_oil_capacity']:''],[t('export.col_engine_oil_spec'),(string)($car['engine_oil_spec']??'')],
        [t('export.col_gearbox_oil_cap'),$car['gearbox_oil_capacity']!==null?(float)$car['gearbox_oil_capacity']:''],[t('export.col_gearbox_oil_spec'),(string)($car['gearbox_oil_spec']??'')],
        [t('export.col_coolant_cap'),$car['coolant_capacity']!==null?(float)$car['coolant_capacity']:''],[t('export.col_coolant_spec'),(string)($car['coolant_spec']??'')],
        [t('export.col_services_count'),count($services)],[t('export.col_first_service'),$first?fiDate((string)$first['service_date']):''],[t('export.col_latest_service'),$latest?fiDate((string)$latest['service_date']):''],
        [t('export.col_latest_service_km'),$latest?(int)$latest['odometer']:''],
        [t('export.col_drive_estimate'),($drive=drivingRateEstimate($db,$car))['available']?(int)$drive['annual_km']:''],[t('export.col_drive_sample'),$drive['available']?(string)$drive['sample_text']:t('export.no_history')],
        [t('export.col_price_mode'),priceInputMode($exportApp)==='gross'?t('export.price_mode_gross'):t('export.price_mode_net')],[t('export.col_vat_percent'),appVatRate($exportApp)],
        [t('export.col_notes_summary'),(string)$car['notes']]
    ];
    $history=[[t('export.col_date'),t('export.col_km'),t('export.col_gap_km'),t('export.col_gap_time'),t('export.col_event_type'),t('export.col_service_name'),t('export.col_works'),t('export.col_workshop'),t('export.col_origin'),t('export.col_customer_snapshot'),t('export.col_mechanic'),t('export.col_actual_time'),t('export.col_billable_hours'),t('export.col_remarks'),t('export.col_own_costs'),t('export.col_photos')]];
    $lines=[[t('export.col_date'),t('export.col_km'),t('export.col_event_type'),t('export.col_service_name'),t('export.col_service_item'),t('export.col_action'),t('export.col_interval_starts'),t('export.col_qty'),t('export.col_unit'),t('export.col_make'),t('export.col_part_number'),t('export.col_oem'),t('export.col_remark'),t('export.col_price_net'),t('export.col_vat_percent'),t('export.col_price_gross'),t('export.col_item_interval')]];
    foreach($exportServices as &$svc)$svc['actions']=getServiceActions($db,(int)$svc['id']);unset($svc);
    attachServiceIntervals($db,$exportServices);
    foreach($exportServices as $i=>$svc){
        $actions=$svc['actions'];$custom=getCustomActions($db,(int)$svc['id']);$photos=getServicePhotos($db,(int)$svc['id']);$gap=intervalInfo($svc,$exportServices[$i-1]??null);
        $work=array_map(fn($a)=>(string)$a['label'],$actions);foreach($custom as $a)$work[]=(string)$a['description'];
        $gapKm=''; if(!empty($gap['km']))$gapKm=(int)str_replace([' ','km'],['',''],$gap['km']);
        $history[]=[fiDate($svc['service_date']),(int)$svc['odometer'],$gapKm,$gap['time']??'',serviceTypeLabel((string)($svc['service_type']??'scheduled')),(string)$svc['title'],implode(', ',$work),(string)$svc['workshop'],(string)($svc['service_origin']??''),(string)($svc['customer_name_snapshot']??''),mechanicLabel((string)($svc['mechanic_name_snapshot']??'')),(int)($svc['actual_work_seconds']??0)>0?formatDuration((int)$svc['actual_work_seconds']):'',(float)($svc['labor_hours']??0)>0?(float)$svc['labor_hours']:'',(string)$svc['notes'],$svc['total_cost']!==null?(float)$svc['total_cost']:'',count($photos)];
        foreach($actions as $a){$pn=$a['price_net']!==null?(float)$a['price_net']:null;$lines[]=[fiDate($svc['service_date']),(int)$svc['odometer'],serviceTypeLabel((string)($svc['service_type']??'scheduled')),(string)$svc['title'],(string)$a['label'],serviceActionLabel($a),in_array(intervalTrackingKey($a),intervalStartKeys($a),true)?t('export.yes'):t('export.no'),$a['quantity']!==null?(float)$a['quantity']:'',(($a['unit']??'')===''?'':unitLabel((string)$a['unit'])),(string)$a['brand'],(string)$a['supplier_sku'],(string)$a['oem_number'],(string)$a['notes'],$pn??'',$pn!==null?(float)($a['vat_rate']??appVatRate($exportApp)):'',$pn!==null?$pn*(1+(float)($a['vat_rate']??appVatRate($exportApp))/100):'',actionIntervalText($a)];}
        foreach($custom as $a){$pn=$a['price_net']!==null?(float)$a['price_net']:null;$lines[]=[fiDate($svc['service_date']),(int)$svc['odometer'],serviceTypeLabel((string)($svc['service_type']??'scheduled')),(string)$svc['title'],t('export.work_other'),actionLabel('done'),t('export.no'),'','','','','',(string)$a['description'].(($a['notes']??'')!==''?' · '.$a['notes']:''),$pn??'',$pn!==null?(float)($a['vat_rate']??appVatRate($exportApp)):'',$pn!==null?$pn*(1+(float)($a['vat_rate']??appVatRate($exportApp))/100):'',actionIntervalText($a)];}
    }
    $settings=[];$st=$db->prepare("SELECT * FROM car_item_settings WHERE car_id=?");$st->execute([$carId]);foreach($st->fetchAll() as $r)$settings[$r['item_key']]=$r;$settings=normalizeBeltSettings($settings);
    $events=lastItemEvents($db,$carId);$drive=$drive??drivingRateEstimate($db,$car);$maintenance=calculateMaintenanceStatus($db,$car,null,$settings,$events,$drive);$program=[[t('export.col_item'),t('export.col_group'),t('export.col_goal'),t('export.col_last'),t('export.col_last_km'),t('export.col_interval_km'),t('export.col_interval_months'),t('export.col_next_km'),t('export.col_next_date'),t('export.col_status'),t('export.col_remaining'),t('export.col_forecast'),t('export.col_first_due'),t('export.col_driven_since'),t('export.col_time_since'),t('export.col_actual_interval'),t('export.col_calc_note')]];
    foreach(allItems($db,true) as $it){$set=$settings[$it['item_key']]??[];if(isset($set['enabled'])&&(int)$set['enabled']!==1)continue;$state=$maintenance[$it['item_key']];$last=$state['last'];$due=$state['due'];$fc=$state['forecast'];$firstLabel=($fc['first']??'')==='km'?t('export.first_km'):(($fc['first']??'')==='time'?t('export.first_time'):(($fc['first']??'')==='due'?t('export.first_due'):''));$program[]=[(string)$it['label'],(string)$it['section'],scheduleTypes()[validScheduleType((string)($set['schedule_type']??'replace'))],$last?fiDate((string)$last['service_date']):'',$last?(int)$last['odometer']:'',(int)($set['interval_km']??0),(int)($set['interval_months']??0),(int)($due['due_km']??0),!empty($due['due_date'])?fiDate($due['due_date']):'',(string)$due['text'],(string)($due['remain']??''),!empty($fc['date'])?fiDate((string)$fc['date']):(string)($fc['text']??''),$firstLabel,$state['elapsed']['km'],$state['elapsed']['time'],$state['actual']['text'],$state['warning']];}
    $parts=[[t('export.col_item'),t('export.col_name'),t('export.col_make'),t('export.col_part_number'),t('export.col_oem'),t('export.col_compat'),t('export.col_link'),t('export.col_notes'),t('export.col_stock_qty'),t('export.col_unit'),t('export.col_shelf'),t('export.col_reorder'),t('export.col_purchase_net'),t('export.col_purchase_gross',['vat'=>dec(appVatRate($exApp=appSettings($db)),1)])]];$st=$db->prepare("SELECT p.*,ic.label item_label FROM parts p LEFT JOIN item_catalog ic ON ic.item_key=p.item_key WHERE EXISTS (SELECT 1 FROM part_car_compatibility pc WHERE pc.part_id=p.id AND pc.car_id=?) ORDER BY COALESCE(ic.sort_order,99999),p.part_name,p.id");$st->execute([$carId]);foreach($st->fetchAll() as $r)$parts[]=[($r['item_label']!==null?itemLabelOf((string)$r['item_key'],(string)$r['item_label']):''),(string)$r['part_name'],(string)$r['brand'],(string)$r['supplier_sku'],(string)$r['oem_number'],partCompatibilityText($db,(int)$r['id']),(string)$r['url'],noteLabel((string)$r['notes']),(float)($r['stock_qty']??0),unitLabel((string)($r['stock_unit']??'')),(string)($r['shelf_location']??''),$r['reorder_level']!==null?(float)$r['reorder_level']:'',...array_map(fn($v)=>$v??'',purchasePriceNetGross($exApp,$r['purchase_price']!==null?(float)$r['purchase_price']:null))];
    $odometer=[[t('export.col_datetime'),t('export.col_km'),t('export.col_source'),t('export.col_event_note'),t('export.col_deviation')]];foreach(kilometerHistoryEntries($db,$carId,false) as $pt){$src=(string)$pt['source'];$time=($src==='service'||$src==='old')?fiDate((string)$pt['date']):date('d.m.Y H:i',strtotime((string)$pt['datetime']));$odometer[]=[$time,(int)$pt['km']>0?(int)$pt['km']:'',(string)$pt['source_label'],(string)($pt['note']??''),!empty($pt['warning'])?(string)$pt['warning_text']:''];}
    $checks=[[t('export.col_level'),t('export.col_title'),t('export.col_remark')]];foreach(dataCheckIssues($db) as $i){$u=(string)($i['url']??'');if(str_contains($u,'car='.$carId))$checks[]=[(string)$i['level'],(string)$i['title'],(string)$i['detail']];}
    return [t('export.sheet_summary')=>$summary,t('export.sheet_history')=>$history,t('export.sheet_lines')=>$lines,t('export.sheet_program')=>$program,t('export.sheet_odometer')=>$odometer,t('export.sheet_parts')=>$parts,t('export.sheet_checks')=>$checks];
}
function exportCarXlsx(PDO $db,int $carId): never {
    $car=getCar($db,$carId); if(!$car){http_response_code(404);exit(t('export.err_car_not_found'));}
    $base=preg_replace('/[^A-Za-z0-9_-]+/','-',strtoupper((string)($car['reg_plate']?:$car['nickname'])))?:t('export.file_car_fallback',['id'=>$carId]);
    $sheets=carExportSheets($db,$carId); outputXlsx(t('export.file_car_history',['base'=>$base,'car'=>preg_replace('/[^A-Za-z0-9_-]+/','-',trim($car['make'].'_'.$car['model'])),'date'=>date('Y-m-d')]),$sheets,$sheets[t('export.sheet_history')]??[]);
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
function inventoryExportSheets(PDO $db,array $partRows,?string $title=null,array $filters=[]): array {
    $title??=t('export.inventory_title');$items=itemMap($db);$invApp=appSettings($db);$vatTxt=dec(appVatRate($invApp),1);$parts=[[t('export.col_service_item'),t('export.col_part'),t('export.col_make'),t('export.col_part_number'),t('export.col_oem'),t('export.col_compat'),t('export.col_shelf'),t('export.col_stock'),t('export.col_unit'),t('export.col_reorder'),t('export.col_purchase_net'),t('export.col_purchase_gross',['vat'=>$vatTxt]),t('export.col_stock_value_net'),t('export.col_stock_value_gross',['vat'=>$vatTxt]),t('export.col_status'),t('export.col_product_link'),t('export.col_notes')]];
    $totalValue=0.0;$totalValueGross=0.0;$lowCount=0;$partIds=[];
    foreach($partRows as $r){$pid=(int)$r['id'];$partIds[]=$pid;$qty=(float)($r['stock_qty']??0);[$priceNet,$priceGross]=purchasePriceNetGross($invApp,$r['purchase_price']!==null?(float)$r['purchase_price']:null);$stockValue=$priceNet!==null?round($qty*$priceNet,2):null;$stockValueGross=$priceGross!==null?round($qty*$priceGross,2):null;$low=$r['reorder_level']!==null&&$qty<=(float)$r['reorder_level'];if($stockValue!==null){$totalValue+=$stockValue;$totalValueGross+=$stockValueGross;}if($low)$lowCount++;$parts[]=[(string)($items[(string)$r['item_key']]['label']??$r['item_key']),(string)$r['part_name'],(string)$r['brand'],(string)$r['supplier_sku'],(string)$r['oem_number'],(string)($r['compat_labels']??partCompatibilityText($db,$pid)),(string)$r['shelf_location'],$qty,unitLabel((string)$r['stock_unit']),$r['reorder_level']!==null?(float)$r['reorder_level']:'',$priceNet??'',$priceGross??'',$stockValue??'',$stockValueGross??'',$low?t('export.low_stock'):t('export.status_ok'),(string)$r['url'],noteLabel((string)$r['notes'])];}
    $summary=[[t('export.summary_title'),''],[t('export.col_title'),$title],[t('export.col_created'),date('d.m.Y H:i')],[t('export.col_item_count'),count($partRows)],[t('export.low_stock'),$lowCount],[t('export.col_stock_value_net'),round($totalValue,2)],[t('export.col_stock_value_gross',['vat'=>$vatTxt]),round($totalValueGross,2)]];foreach($filters as $label=>$val){if((string)$val!=='')$summary[]=[$label,(string)$val];}
    $inventoryApp=appSettings($db);if(!empty($inventoryApp['inventory_history_cleared_at'])){$summary[]=[t('export.log_cleared'),date('d.m.Y H:i',strtotime($inventoryApp['inventory_history_cleared_at']))];$summary[]=[t('export.log_cleared_by'),$inventoryApp['inventory_history_cleared_by']??''];}
    $transactions=[[t('export.col_datetime'),t('export.col_part'),t('export.col_make'),t('export.col_part_number'),t('export.col_oem'),t('export.col_compat'),t('export.col_shelf'),t('export.col_change'),t('export.col_unit'),t('export.col_event'),t('export.col_balance_after'),t('export.col_remark')]];
    if($partIds){$marks=implode(',',array_fill(0,count($partIds),'?'));$st=$db->prepare("SELECT t.*,p.part_name,p.brand,p.supplier_sku,p.oem_number,p.stock_unit,p.shelf_location,p.stock_qty current_stock FROM inventory_transactions t JOIN parts p ON p.id=t.part_id WHERE t.part_id IN ($marks) ORDER BY t.created_at ASC,t.id ASC");$st->execute($partIds);$txRows=$st->fetchAll();$sum=[];$current=[];foreach($txRows as $t){$pid=(int)$t['part_id'];$sum[$pid]=($sum[$pid]??0.0)+(float)$t['quantity_change'];$current[$pid]=(float)$t['current_stock'];}$running=[];foreach($sum as $pid=>$delta)$running[$pid]=($current[$pid]??0.0)-$delta;foreach($txRows as $t){$pid=(int)$t['part_id'];$running[$pid]=($running[$pid]??0.0)+(float)$t['quantity_change'];$transactions[]=[date('d.m.Y H:i',strtotime((string)$t['created_at'])),(string)$t['part_name'],(string)$t['brand'],(string)$t['supplier_sku'],(string)$t['oem_number'],partCompatibilityText($db,$pid),(string)$t['shelf_location'],(float)$t['quantity_change'],unitLabel((string)$t['stock_unit']),inventoryEventLabel((string)$t['event_type']),(float)$running[$pid],noteLabel((string)$t['note'])];}}
    return [t('export.sheet_summary')=>$summary,t('export.sheet_parts')=>$parts,t('export.sheet_inventory_transactions')=>$transactions];
}
function exportInventoryXlsx(PDO $db): never {
    $q=trim((string)($_GET['q']??''));$carFilter=(int)($_GET['car_id']??0);$lowOnly=(string)($_GET['low']??'')==='1';$sort=(string)($_GET['sort']??'car');$dir=(string)($_GET['dir']??'asc');
    $rows=inventoryExportPartRows($db,$q,$carFilter,$lowOnly,$sort,$dir);$filters=[];if($q!=='')$filters[t('export.filter_search')]=$q;if($carFilter>0){$c=getCar($db,$carFilter);if($c)$filters[t('export.filter_car')]=trim((string)($c['reg_plate']?:$c['nickname']).' · '.trim((string)$c['make'].' '.(string)$c['model']));}if($lowOnly)$filters[t('export.filter_limit')]=t('export.filter_low_only');
    $sheets=inventoryExportSheets($db,$rows,t('export.inventory_title'),$filters);$app=appSettings($db);$name=trim((string)($app['shop_name']??DEFAULT_APP_NAME))?:DEFAULT_APP_NAME;$ascii=@iconv('UTF-8','ASCII//TRANSLIT',$name);$ascii=$ascii!==false?$ascii:$name;$base=trim((string)(preg_replace('/[^A-Za-z0-9_-]+/','-',$ascii)??''),'-_')?:'Autonhuolto';outputXlsx(t('export.file_inventory',['base'=>$base,'date'=>date('Y-m-d')]),$sheets,$sheets[t('export.sheet_parts')]??[]);
}
function exportAllCarsXlsx(PDO $db): never {
    $cars=$db->query("SELECT c.*,cu.name current_customer_name,cu.customer_number current_customer_number FROM cars c LEFT JOIN customers cu ON cu.id=c.current_customer_id ORDER BY COALESCE(NULLIF(cu.name,''),NULLIF(c.owner,''),'~'),COALESCE(NULLIF(c.nickname,''),c.reg_plate),c.id")->fetchAll();
    $carRows=[[t('export.col_reg'),t('export.col_nickname'),t('export.col_customer'),t('export.col_customer_number'),t('export.col_owner_legacy'),t('export.col_make'),t('export.col_model'),t('export.col_year'),t('export.col_first_reg'),t('export.col_vin'),t('export.col_engine'),t('export.col_current_km'),t('export.col_services_count'),t('export.col_latest_service_short')]];
    $sheets=[];
    foreach($cars as $car){
        $cid=(int)$car['id'];
        $st=$db->prepare("SELECT * FROM services WHERE car_id=? ORDER BY service_date DESC,id DESC");$st->execute([$cid]);$servicesDesc=$st->fetchAll();$latest=$servicesDesc[0]??null;
        $services=array_reverse($servicesDesc);
        $carRows[]=[(string)$car['reg_plate'],(string)$car['nickname'],(string)($car['current_customer_name']?:$car['owner']),(string)($car['current_customer_number']??''),(string)$car['owner'],(string)$car['make'],(string)$car['model'],(string)$car['year'],fiDate((string)($car['first_registration_date']??'')),(string)$car['vin'],(string)$car['engine'],(int)$car['current_km'],count($servicesDesc),$latest?fiDate((string)$latest['service_date']):''];
        $carSheet=[[t('export.col_date'),t('export.col_km'),t('export.col_type'),t('export.col_service_name'),t('export.col_works'),t('export.col_mechanic'),t('export.col_actual_time'),t('export.col_remarks_alt')]];
        foreach($services as $svc){
            $acts=getServiceActions($db,(int)$svc['id']);$custom=getCustomActions($db,(int)$svc['id']);$work=array_map(fn($a)=>(string)$a['label'],$acts);foreach($custom as $a)$work[]=(string)$a['description'];
            $carSheet[]=[fiDate((string)$svc['service_date']),(int)$svc['odometer'],serviceTypeLabel((string)$svc['service_type']),(string)$svc['title'],implode(', ',$work),mechanicLabel((string)($svc['mechanic_name_snapshot']??'')),(int)($svc['actual_work_seconds']??0)>0?formatDuration((int)$svc['actual_work_seconds']):'',(string)$svc['notes']];
        }
        $sheetName=trim((string)($car['reg_plate']?:$car['nickname']?:t('export.car_sheet_fallback',['id'=>$cid])));$sheets[$sheetName]=$carSheet;
    }

    // Yhteiset huolto- ja huoltorivivälilehdet ovat aidosti kronologisia kaikkien autojen kesken.
    $allHistory=[[t('export.col_reg'),t('export.col_car'),t('export.col_customer_snapshot'),t('export.col_owner_legacy'),t('export.col_date'),t('export.col_km'),t('export.col_event_type'),t('export.col_service_name'),t('export.col_works'),t('export.col_workshop'),t('export.col_mechanic'),t('export.col_actual_time'),t('export.col_remarks'),t('export.col_costs')]];
    $allLines=[[t('export.col_reg'),t('export.col_car'),t('export.col_date'),t('export.col_km'),t('export.col_service_item'),t('export.col_action'),t('export.col_qty'),t('export.col_unit'),t('export.col_make'),t('export.col_part_number'),t('export.col_oem'),t('export.col_remark'),t('export.col_item_interval')]];
    $q=$db->query("SELECT s.*,c.reg_plate,c.nickname,c.owner,c.make,c.model FROM services s JOIN cars c ON c.id=s.car_id ORDER BY s.service_date ASC,s.id ASC");
    $exportAllServices=$q->fetchAll();foreach($exportAllServices as &$svc)$svc['actions']=getServiceActions($db,(int)$svc['id']);unset($svc);attachServiceIntervals($db,$exportAllServices);
    foreach($exportAllServices as $svc){
        $acts=$svc['actions'];$custom=getCustomActions($db,(int)$svc['id']);$work=array_map(fn($a)=>(string)$a['label'],$acts);foreach($custom as $a)$work[]=(string)$a['description'];
        $auto=trim((string)($svc['nickname']?:$svc['reg_plate']));$mm=trim((string)$svc['make'].' '.(string)$svc['model']);if($mm!=='')$auto=$auto!==''?$auto.' · '.$mm:$mm;
        $allHistory[]=[(string)$svc['reg_plate'],$auto,(string)($svc['customer_name_snapshot']??''),(string)$svc['owner'],fiDate((string)$svc['service_date']),(int)$svc['odometer'],serviceTypeLabel((string)$svc['service_type']),(string)$svc['title'],implode(', ',$work),(string)$svc['workshop'],mechanicLabel((string)($svc['mechanic_name_snapshot']??'')),(int)($svc['actual_work_seconds']??0)>0?formatDuration((int)$svc['actual_work_seconds']):'',(string)$svc['notes'],$svc['total_cost']!==null?(float)$svc['total_cost']:''];
        foreach($acts as $a)$allLines[]=[(string)$svc['reg_plate'],$auto,fiDate((string)$svc['service_date']),(int)$svc['odometer'],(string)$a['label'],actionLabel((string)$a['action']),$a['quantity']!==null?(float)$a['quantity']:'',($a['unit']===''?'':unitLabel((string)$a['unit'])),(string)$a['brand'],(string)$a['supplier_sku'],(string)$a['oem_number'],(string)$a['notes'],actionIntervalText($a)];
    }
    $allOdometer=[[t('export.col_reg'),t('export.col_car'),t('export.col_datetime'),t('export.col_km'),t('export.col_source'),t('export.col_event_note'),t('export.col_deviation')]];$odoRows=[];
    foreach($cars as $oc){$cid=(int)$oc['id'];$auto=trim((string)($oc['nickname']?:$oc['reg_plate']));$mm=trim((string)$oc['make'].' '.(string)$oc['model']);if($mm!=='')$auto=$auto!==''?$auto.' · '.$mm:$mm;foreach(kilometerHistoryEntries($db,$cid,false) as $pt)$odoRows[]=['reg'=>(string)$oc['reg_plate'],'auto'=>$auto,'pt'=>$pt];}
    usort($odoRows,fn($a,$b)=>strcmp((string)$a['pt']['datetime'],(string)$b['pt']['datetime'])?:strcmp((string)$a['reg'],(string)$b['reg']));
    foreach($odoRows as $or){$pt=$or['pt'];$src=(string)$pt['source'];$time=($src==='service'||$src==='old')?fiDate((string)$pt['date']):date('d.m.Y H:i',strtotime((string)$pt['datetime']));$allOdometer[]=[$or['reg'],$or['auto'],$time,(int)$pt['km']>0?(int)$pt['km']:'',(string)$pt['source_label'],(string)($pt['note']??''),!empty($pt['warning'])?(string)$pt['warning_text']:''];}
    $inventorySheets=inventoryExportSheets($db,inventoryExportPartRows($db),t('export.inventory_title_all'));
    $sheets=[t('export.sheet_all_cars')=>$carRows,t('export.sheet_all_history')=>$allHistory,t('export.sheet_all_lines')=>$allLines,t('export.sheet_all_odometer')=>$allOdometer,t('export.sheet_inventory_summary')=>$inventorySheets[t('export.sheet_summary')],t('export.sheet_parts')=>$inventorySheets[t('export.sheet_parts')],t('export.sheet_inventory_transactions')=>$inventorySheets[t('export.sheet_inventory_transactions')]]+$sheets;
    $exportApp=appSettings($db);$exportName=trim((string)($exportApp['shop_name']??DEFAULT_APP_NAME))?:DEFAULT_APP_NAME;
    $ascii=@iconv('UTF-8','ASCII//TRANSLIT',$exportName);$ascii=$ascii!==false?$ascii:$exportName;
    $fileBase=trim((string)(preg_replace('/[^A-Za-z0-9_-]+/','-',$ascii)??''),'-_')?:'Autonhuolto';
    outputXlsx(t('export.file_all_cars',['base'=>$fileBase,'date'=>date('Y-m-d')]),$sheets,$allHistory);
}

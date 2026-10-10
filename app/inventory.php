<?php
declare(strict_types=1);

/**
 * app/inventory.php – varaosat ja varasto: yhteensopivuudet, saldot, varastotapahtumat ja huoltojen varastokäyttö.
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

function inventoryEventLabel(string $type): string {
    return match($type){
        'initial'=>t('inv.event_initial'),'in'=>t('inv.event_add'),'out'=>t('inv.event_remove'),'add'=>t('inv.event_add'),'remove'=>t('inv.event_remove'),'correction'=>t('inv.event_correction'),
        'service_use'=>t('inv.event_service_use'),'service_return'=>t('inv.event_service_return'),default=>$type!==''?$type:t('inv.event_default')
    };
}
/** Varastosaldo ei riipu lokin säilymisestä. Saldo tapahtuman jälkeen johdetaan
 * nykyisestä saldosta vähentämällä myöhemmät muutokset, kuten Excel-viennissä. */
function inventoryHistoryPage(PDO $db,int $requested=1,int $partId=0,string $event=''): array {
    $stats=$db->query('SELECT COUNT(*) total,COALESCE(MAX(id),0) last_id FROM inventory_transactions')->fetch();
    $where=[];$args=[];
    if($partId>0){$where[]='t.part_id=?';$args[]=$partId;}
    if($event!==''){$where[]='t.event_type=?';$args[]=$event;}
    $filter=$where?' WHERE '.implode(' AND ',$where):'';
    $count=$db->prepare('SELECT COUNT(*) FROM inventory_transactions t'.$filter);$count->execute($args);$total=(int)$count->fetchColumn();
    $pages=max(1,(int)ceil($total/50));$page=min($pages,max(1,$requested));$offset=($page-1)*50;
    $sql="SELECT t.*,p.part_name,p.brand,p.supplier_sku,p.stock_unit,p.car_id,
      p.stock_qty-COALESCE((SELECT SUM(n.quantity_change) FROM inventory_transactions n
       WHERE n.part_id=t.part_id AND (n.created_at>t.created_at OR (n.created_at=t.created_at AND n.id>t.id))),0) stock_after
      FROM inventory_transactions t JOIN parts p ON p.id=t.part_id".$filter.' ORDER BY t.created_at DESC,t.id DESC LIMIT ? OFFSET ?';
    $st=$db->prepare($sql);$i=1;foreach($args as $value)$st->bindValue($i++,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue($i++,50,PDO::PARAM_INT);$st->bindValue($i,$offset,PDO::PARAM_INT);$st->execute();
    return ['total'=>$total,'all_total'=>(int)$stats['total'],'last_id'=>(int)$stats['last_id'],'page'=>$page,'pages'=>$pages,'offset'=>$offset,'rows'=>$st->fetchAll()];
}
function partCompatibleCarIds(PDO $db,int $partId): array { $st=$db->prepare("SELECT car_id FROM part_car_compatibility WHERE part_id=? ORDER BY car_id");$st->execute([$partId]);return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)); }
function syncPartCompatibility(PDO $db,int $partId,int $primaryCarId,array $carIds): void {
    $ids=array_values(array_unique(array_filter(array_map('intval',$carIds),fn($id)=>$id>0)));$valid=[];$chk=$db->prepare("SELECT 1 FROM cars WHERE id=?");foreach($ids as $id){$chk->execute([$id]);if($chk->fetchColumn())$valid[]=$id;}if(!$valid)throw new RuntimeException(t('inv.err_compat_car_required'));
    $db->prepare("DELETE FROM part_car_compatibility WHERE part_id=?")->execute([$partId]);$ins=$db->prepare("INSERT INTO part_car_compatibility(part_id,car_id) VALUES(?,?)");foreach($valid as $id)$ins->execute([$partId,$id]);
    $primaryCarId=in_array($primaryCarId,$valid,true)?$primaryCarId:$valid[0];
    $db->prepare('UPDATE parts SET car_id=? WHERE id=?')->execute([$primaryCarId,$partId]);
}
function partCompatibilityCars(PDO $db,int $partId): array {
    $st=$db->prepare("SELECT c.id,c.reg_plate,c.nickname,c.make,c.model,c.owner,cu.name current_customer_name FROM part_car_compatibility pc JOIN cars c ON c.id=pc.car_id LEFT JOIN customers cu ON cu.id=c.current_customer_id WHERE pc.part_id=? ORDER BY COALESCE(NULLIF(c.reg_plate,''),NULLIF(c.nickname,''),c.make),c.model,c.id");$st->execute([$partId]);return $st->fetchAll();
}
function partCompatibilityText(PDO $db,int $partId): string { $labels=[];foreach(partCompatibilityCars($db,$partId) as $c){$id=trim((string)($c['reg_plate']?:$c['nickname']));$model=trim((string)$c['make'].' '.(string)$c['model']);$labels[]=trim($id.($id!==''&&$model!==''?' · ':'').$model);}return implode(', ',array_filter($labels)); }
function partsForCar(PDO $db,int $carId): array { $st=$db->prepare("SELECT p.* FROM parts p WHERE EXISTS (SELECT 1 FROM part_car_compatibility pc WHERE pc.part_id=p.id AND pc.car_id=?) ORDER BY p.id DESC");$st->execute([$carId]);return $st->fetchAll(); }
function learnPartForCar(PDO $db,int $carId,string $itemKey,string $partName,string $brand,string $sku,string $oem): void {
    if($sku===''&&$oem==='')return;$st=$db->prepare("SELECT id FROM parts WHERE item_key=? AND COALESCE(brand,'')=? AND COALESCE(supplier_sku,'')=? AND COALESCE(oem_number,'')=? ORDER BY id LIMIT 1");$st->execute([$itemKey,$brand,$sku,$oem]);$pid=(int)($st->fetchColumn()?:0);
    if($pid<=0){$ins=$db->prepare("INSERT INTO parts(car_id,item_key,part_name,brand,supplier_sku,oem_number,url,notes) VALUES(?,?,?,?,?,?,'','@auto_added')");$ins->execute([$carId,$itemKey,$partName,$brand,$sku,$oem]);$pid=(int)$db->lastInsertId();}
    $db->prepare("INSERT OR IGNORE INTO part_car_compatibility(part_id,car_id) VALUES(?,?)")->execute([$pid,$carId]);
}
function inventoryPartRows(PDO $db): array {
    $rows=$db->query("SELECT p.*,c.reg_plate,c.nickname,c.make,c.model,c.owner,cu.name current_customer_name FROM parts p JOIN cars c ON c.id=p.car_id LEFT JOIN customers cu ON cu.id=c.current_customer_id")->fetchAll();
    foreach($rows as &$r){$compat=partCompatibilityCars($db,(int)$r['id']);$ids=[];$labels=[];$search=[];foreach($compat as $c){$ids[]=(int)$c['id'];$base=trim((string)($c['reg_plate']?:$c['nickname']));$model=trim((string)$c['make'].' '.(string)$c['model']);$labels[]=trim($base.($base!==''&&$model!==''?' · ':'').$model);$search[]=implode(' ',[$base,$c['make'],$c['model'],$c['current_customer_name']??'',$c['owner']??'']);}$r['compat_car_ids']=implode(',',$ids);$r['compat_labels']=implode(' / ',array_filter($labels));$r['compat_search']=implode(' ',$search);$r['open_car_id']=$ids[0]??(int)$r['car_id'];}unset($r);return $rows;
}
function stockPartsForCarItem(PDO $db,int $carId,string $itemKey): array {
    $st=$db->prepare("SELECT p.* FROM parts p WHERE p.item_key=? AND EXISTS (SELECT 1 FROM part_car_compatibility pc WHERE pc.part_id=p.id AND pc.car_id=?) ORDER BY CASE WHEN p.stock_qty>0 THEN 0 ELSE 1 END,p.part_name,p.brand,p.id");
    $st->execute([$itemKey,$carId]);return $st->fetchAll();
}
function serviceInventoryUsage(PDO $db,int $serviceId): array {
    $st=$db->prepare("SELECT u.*,p.part_name,p.brand,p.supplier_sku,p.oem_number,p.stock_qty current_stock,p.stock_unit current_stock_unit,p.shelf_location current_shelf FROM service_inventory_usage u JOIN parts p ON p.id=u.part_id WHERE u.service_id=? ORDER BY u.item_key,u.id");
    $st->execute([$serviceId]);return $st->fetchAll();
}
function inventoryServiceNote(PDO $db,int $serviceId,int $carId,string $itemKey,string $date,string $title): string {
    $car=getCar($db,$carId)??[];$items=itemMap($db);$label=(string)($items[$itemKey]['label']??$itemKey);$carLabel=trim((string)($car['reg_plate']??'') ?: (string)($car['nickname']??''));
    return t('inv.service_ref',['id'=>$serviceId]).($carLabel!==''?' · '.$carLabel:'').($date!==''?' · '.fiDate($date):'').' · '.$label.($title!==''&&$title!==$label?' · '.$title:'');
}
function inventoryApplyPartDelta(PDO $db,int $partId,float $stockDelta,string $eventType,string $note): void {
    if(abs($stockDelta)<0.0000001)return;$st=$db->prepare("SELECT stock_qty FROM parts WHERE id=?");$st->execute([$partId]);$cur=$st->fetchColumn();if($cur===false)throw new RuntimeException(t('inv.err_used_part_missing'));$new=(float)$cur+$stockDelta;if($new < -0.0000001)throw new RuntimeException(t('inv.err_stock_insufficient'));if(abs($new)<0.0000001)$new=0.0;
    $db->prepare("UPDATE parts SET stock_qty=? WHERE id=?")->execute([$new,$partId]);$db->prepare("INSERT INTO inventory_transactions(part_id,quantity_change,event_type,note) VALUES(?,?,?,?)")->execute([$partId,$stockDelta,$eventType,$note]);
}
function reconcileServiceInventoryUsage(PDO $db,int $serviceId,int $carId,string $date,string $title,array $desired): int {
    $existing=[];foreach(serviceInventoryUsage($db,$serviceId) as $u)$existing[(string)$u['item_key']]=$u;$keys=array_values(array_unique(array_merge(array_keys($existing),array_keys($desired))));$changes=0;
    foreach($keys as $itemKey){$old=$existing[$itemKey]??null;$want=$desired[$itemKey]??null;$note=inventoryServiceNote($db,$serviceId,$carId,$itemKey,$date,$title);
        if($want){$partId=(int)($want['part_id']??0);$qty=(float)($want['quantity']??0);if($partId<=0||!is_finite($qty)||$qty<=0)throw new RuntimeException(t('inv.err_usage_qty_invalid'));$pst=$db->prepare("SELECT p.id,p.stock_qty,p.stock_unit,p.shelf_location FROM parts p WHERE p.id=? AND p.item_key=? AND EXISTS (SELECT 1 FROM part_car_compatibility pc WHERE pc.part_id=p.id AND pc.car_id=?)");$pst->execute([$partId,$itemKey,$carId]);$part=$pst->fetch();if(!$part)throw new RuntimeException(t('inv.err_part_not_compatible'));$unit=(string)($part['stock_unit']?:'pcs');$shelf=(string)($part['shelf_location']??'');
            if($old && (int)$old['part_id']===$partId){$diff=$qty-(float)$old['quantity'];if(abs($diff)>0.0000001){inventoryApplyPartDelta($db,$partId,-$diff,$diff>0?'service_use':'service_return',$note.($shelf!==''?' · '.$shelf:''));$changes++;}$db->prepare("UPDATE service_inventory_usage SET quantity=?,unit=?,shelf_snapshot=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$qty,$unit,$shelf,(int)$old['id']]);continue;}
            if($old){inventoryApplyPartDelta($db,(int)$old['part_id'],(float)$old['quantity'],'service_return',$note.' · aiempi varastokäyttö palautettu');$db->prepare("DELETE FROM service_inventory_usage WHERE id=?")->execute([(int)$old['id']]);$changes++;}
            inventoryApplyPartDelta($db,$partId,-$qty,'service_use',$note.($shelf!==''?' · '.$shelf:''));$db->prepare("INSERT INTO service_inventory_usage(service_id,item_key,part_id,quantity,unit,shelf_snapshot) VALUES(?,?,?,?,?,?)")->execute([$serviceId,$itemKey,$partId,$qty,$unit,$shelf]);$changes++;
        } elseif($old){inventoryApplyPartDelta($db,(int)$old['part_id'],(float)$old['quantity'],'service_return',$note.' · huollon varastokäyttö poistettu');$db->prepare("DELETE FROM service_inventory_usage WHERE id=?")->execute([(int)$old['id']]);$changes++;}
    }
    return $changes;
}
function restoreServiceInventoryUsage(PDO $db,int $serviceId,int $carId,string $date='',string $title=''): int {
    $rows=serviceInventoryUsage($db,$serviceId);$count=0;foreach($rows as $u){$note=inventoryServiceNote($db,$serviceId,$carId,(string)$u['item_key'],$date,$title).' · huolto poistettu, varastokäyttö palautettu';inventoryApplyPartDelta($db,(int)$u['part_id'],(float)$u['quantity'],'service_return',$note);$count++;}$db->prepare("DELETE FROM service_inventory_usage WHERE service_id=?")->execute([$serviceId]);return $count;
}
function inventoryEditValues(array $row): array {
    return [(float)$row['stock_qty'],mb_substr(trim((string)($row['stock_unit']?:'pcs')),0,20),trim((string)$row['shelf_location']),$row['reorder_level']===null?null:(float)$row['reorder_level'],$row['purchase_price']===null?null:(float)$row['purchase_price']];
}
function inventoryPostedNumber(mixed $raw,string $label,bool $nullable=false): ?float {
    if(!is_scalar($raw)&&$raw!==null)throw new RuntimeException(t('inv.err_number_invalid',['label'=>$label]));
    $v=trim(str_replace(',','.',(string)$raw));if($v===''){if($nullable)return null;throw new RuntimeException(t('inv.err_number_missing',['label'=>$label]));}
    if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0)throw new RuntimeException(t('inv.err_number_not_positive',['label'=>$label]));return (float)$v;
}
function inventoryChangedRows(PDO $db): array {
    if(($_POST['inventory_complete']??null)!=='1')editConflict(t('inv.err_form_incomplete'));
    $rows=(array)($_POST['inventory_stock_qty']??[]);if(count($rows)>2000)throw new RuntimeException(t('inv.err_too_many_rows'));$changed=[];
    foreach($rows as $rawId=>$rawStock){
        $id=(int)$rawId;if($id<=0)throw new RuntimeException(t('inv.err_invalid_part'));
        $base=editReadToken($db,'inventory',$id,$_POST['inventory_baseline'][$rawId]??null);
        $values=[inventoryPostedNumber($rawStock,t('inv.col_stock')),mb_substr(vocabNormalize('unit',(string)($_POST['inventory_stock_unit'][$rawId]??'')),0,20),trim((string)($_POST['inventory_shelf_location'][$rawId]??'')),inventoryPostedNumber($_POST['inventory_reorder_level'][$rawId]??'',t('inv.col_reorder'),true),inventoryPostedNumber($_POST['inventory_purchase_price'][$rawId]??'',t('inv.field_purchase_price'),true)];
        if($values[1]==='')$values[1]='pcs';
        if($values===($base['values']??null))continue; // Koskematon rivi ei kirjoita vanhaa saldoa takaisin.
        if(!hash_equals(editState($db,'inventory',$id),$base['state']))editConflict(t('inv.err_part_conflict',['id'=>$id]));
        $changed[$rawId]=$rawStock;
    }
    return $changed;
}

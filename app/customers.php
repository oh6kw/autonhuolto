<?php
declare(strict_types=1);

/**
 * app/customers.php – asiakkaat: haku, asiakasnumerot, poistotarkistukset, auton omistaja ja omistajahistoria.
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

function customerTypeLabel(string $type): string { return $type==='company'?t('cust.type_company'):t('cust.type_private'); }
function customersList(PDO $db,bool $activeOnly=false): array {
    $sql="SELECT cu.*,(SELECT COUNT(*) FROM cars c WHERE c.current_customer_id=cu.id) current_car_count FROM customers cu".($activeOnly?" WHERE cu.active=1":"")." ORDER BY cu.active DESC,LOWER(cu.name),cu.id";
    return $db->query($sql)->fetchAll();
}
function getCustomer(PDO $db,int $id): ?array { $st=$db->prepare("SELECT * FROM customers WHERE id=?");$st->execute([$id]);$r=$st->fetch();return $r?:null; }
function customerDeleteReferences(PDO $db,int $id): array { $refs=[]; foreach(['cars'=>'SELECT COUNT(*) FROM cars WHERE current_customer_id=?','history'=>'SELECT COUNT(*) FROM car_customer_history WHERE customer_id=?','services'=>'SELECT COUNT(*) FROM services WHERE customer_id=?'] as $key=>$sql){$st=$db->prepare($sql);$st->execute([$id]);$refs[$key]=(int)$st->fetchColumn();} return $refs; }
function customerDeleteAllowed(array $refs): bool { return array_sum(array_map('intval',$refs))===0; }
function nextCustomerNumber(PDO $db): string {
    $max=1000; foreach($db->query("SELECT customer_number FROM customers WHERE TRIM(customer_number)<>''")->fetchAll(PDO::FETCH_COLUMN) as $n){if(ctype_digit((string)$n))$max=max($max,(int)$n);}
    return (string)($max+1);
}
function currentCustomerForCar(PDO $db,int $carId): ?array {
    $st=$db->prepare("SELECT cu.* FROM cars c JOIN customers cu ON cu.id=c.current_customer_id WHERE c.id=?");$st->execute([$carId]);$r=$st->fetch();return $r?:null;
}
function customerAddressText(array $c): string { return trim(implode("\n",array_filter([trim((string)($c['address']??'')),trim((string)(($c['postal_code']??'').' '.($c['city']??'')))]))); }
function carOwnerLabel(PDO $db,array $car): string {
    if(!empty($car['current_customer_name']))return (string)$car['current_customer_name'];
    if(!empty($car['current_customer_id'])){ $c=currentCustomerForCar($db,(int)$car['id']); if($c)return (string)$c['name']; }
    return trim((string)($car['owner']??''));
}
function customerSnapshotForCar(PDO $db,array $car): array {
    $cu=!empty($car['current_customer_id'])?getCustomer($db,(int)$car['current_customer_id']):null;
    if($cu)return ['id'=>(int)$cu['id'],'name'=>(string)$cu['name'],'address'=>customerAddressText($cu),'email'=>(string)$cu['email'],'phone'=>(string)$cu['phone'],'business_id'=>(string)$cu['business_id']];
    return ['id'=>null,'name'=>(string)($car['owner']??''),'address'=>(string)($car['customer_address']??''),'email'=>(string)($car['customer_email']??''),'phone'=>(string)($car['customer_phone']??''),'business_id'=>(string)($car['customer_business_id']??'')];
}
function carCustomerHistory(PDO $db,int $carId): array {
    $st=$db->prepare("SELECT h.*,cu.customer_number,cu.name current_name FROM car_customer_history h LEFT JOIN customers cu ON cu.id=h.customer_id WHERE h.car_id=? ORDER BY CASE WHEN h.start_date='' THEN 1 ELSE 0 END,h.start_date DESC,h.id DESC");$st->execute([$carId]);return $st->fetchAll();
}

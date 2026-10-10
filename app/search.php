<?php
declare(strict_types=1);

/**
 * app/search.php – yhteinen haku: autot, asiakkaat, huollot, laskut ja varaosat yhdellä hakusanalla.
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

/** LIKE-kuvio, jossa %, _ ja \ käsitellään tavallisina merkkeinä. */
function searchLike(string $q): string { return '%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%'; }
/** Hakee kaikista ryhmistä (enintään $limit osumaa/ryhmä). Palauttaa ryhmät avaimin cars, customers, services, invoices, parts. */
function globalSearch(PDO $db,string $q,array $app,int $limit=20): array {
    $q=trim($q);$out=['cars'=>[],'customers'=>[],'services'=>[],'invoices'=>[],'parts'=>[]];
    if(mb_strlen($q)<2)return $out;
    $like=searchLike($q);
    $run=function(string $sql,int $n) use ($db,$like,$limit): array { $st=$db->prepare($sql.' LIMIT '.(int)$limit);$st->execute(array_fill(0,$n,$like));return $st->fetchAll(); };
    $out['cars']=$run("SELECT id,reg_plate,nickname,make,model,owner,year FROM cars WHERE reg_plate LIKE ? ESCAPE '\\' OR nickname LIKE ? ESCAPE '\\' OR make LIKE ? ESCAPE '\\' OR model LIKE ? ESCAPE '\\' OR owner LIKE ? ESCAPE '\\' OR vin LIKE ? ESCAPE '\\' OR notes LIKE ? ESCAPE '\\' ORDER BY COALESCE(NULLIF(nickname,''),reg_plate),id",7);
    if(customersEnabled($app))$out['customers']=$run("SELECT id,customer_number,name,phone,email,city FROM customers WHERE name LIKE ? ESCAPE '\\' OR customer_number LIKE ? ESCAPE '\\' OR phone LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\' OR business_id LIKE ? ESCAPE '\\' OR contact_name LIKE ? ESCAPE '\\' ORDER BY name,id",6);
    $out['services']=$run("SELECT s.id,s.car_id,s.service_date,s.title,s.odometer,c.reg_plate,c.nickname,c.make,c.model FROM services s JOIN cars c ON c.id=s.car_id WHERE s.title LIKE ? ESCAPE '\\' OR s.notes LIKE ? ESCAPE '\\' OR s.workshop LIKE ? ESCAPE '\\' OR s.customer_name_snapshot LIKE ? ESCAPE '\\' OR EXISTS(SELECT 1 FROM service_custom_actions sca WHERE sca.service_id=s.id AND sca.description LIKE ? ESCAPE '\\') OR EXISTS(SELECT 1 FROM service_actions sa WHERE sa.service_id=s.id AND (sa.brand LIKE ? ESCAPE '\\' OR sa.supplier_sku LIKE ? ESCAPE '\\' OR sa.oem_number LIKE ? ESCAPE '\\')) ORDER BY s.service_date DESC,s.id DESC",8);
    if(invoicingEnabled($app))$out['invoices']=$run("SELECT i.id,i.invoice_number,i.issue_date,i.status,i.customer_name,c.reg_plate,c.nickname FROM invoices i JOIN services s ON s.id=i.service_id JOIN cars c ON c.id=s.car_id WHERE i.invoice_number LIKE ? ESCAPE '\\' OR i.customer_name LIKE ? ESCAPE '\\' OR i.reference LIKE ? ESCAPE '\\' OR i.note LIKE ? ESCAPE '\\' ORDER BY i.issue_date DESC,i.id DESC",4);
    if(inventoryEnabled($app))$out['parts']=$run("SELECT id,part_name,brand,supplier_sku,oem_number,shelf_location,stock_qty,stock_unit FROM parts WHERE part_name LIKE ? ESCAPE '\\' OR brand LIKE ? ESCAPE '\\' OR supplier_sku LIKE ? ESCAPE '\\' OR oem_number LIKE ? ESCAPE '\\' OR shelf_location LIKE ? ESCAPE '\\' ORDER BY part_name,id",5);
    return $out;
}

<?php
declare(strict_types=1);

/**
 * AUTONHUOLTO – kevyt korjaamojärjestelmä
 * Kehitysversio 0.8.70-dev · PHP 8.2+ / PDO_SQLITE · tietokannan skeema 12
 *
 * Autonhuolto – vapaa ohjelmisto, lisenssi GNU AGPL v3 (tiedosto LICENSE).
 * Copyright (C) 2026 Jarno Jaskari
 *
 * Muutoshistoria: CHANGELOG.md · tiedostorakenne, asennus ja käyttö: SETUP.md
 * Selain-CSS ja JavaScript: assets/app.css ja assets/app.js
 * Toiminnot jaettu tiedostoihin: app/ (funktiot ja käsittelijät) ja views/ (sivun HTML).
 * Lataus: funktiotiedostot heti vakioiden jälkeen; app/backup.php, app/printing.php ja lopuksi
 * app/actions.php (actions.php ajaa POST-käsittelijät heti ja kutsuu edellisten funktioita).
 *
 * Ominaisuudet: autot, mittarilukemahistoria, kohdekohtaiset huoltovälit ja ennusteet,
 * huoltohistoria, kuvat ja kuvatekstit, varaosat ja moniautosopivuus, varasto,
 * asiakkaat ja omistushistoria, mekaanikot, työajastin, laskutus, MobilePay,
 * logot, teemat, tulosteet/PDF, Excel-viennit, tietojen tarkistus ja poikkeamien hyväksyntä.
 */

umask(0077);
/* Ympäristötarkistus: selkeä virheilmoitus puuttuvista PHP-laajennuksista ennen kuin mikään muu ehtii kaatua. */
(function(): void {
    $missing=[];
    if(version_compare(PHP_VERSION,'8.2.0','<'))$missing[]='PHP 8.2 tai uudempi (nyt '.PHP_VERSION.')';
    foreach(['pdo_sqlite'=>'PDO SQLite (pdo_sqlite)','mbstring'=>'mbstring'] as $ext=>$label)if(!extension_loaded($ext))$missing[]=$label;
    if($missing){
        http_response_code(500);header('Content-Type: text/plain; charset=utf-8');
        exit("Autonhuolto ei voi käynnistyä, koska palvelimelta puuttuu:\n- ".implode("\n- ",$missing)."\n\nSuositeltavia lisäksi: zip (ZIP-backup, Excel-viennit) ja gd (kuvien ja logon pienennys).");
    }
})();
function authHttps(): bool {
    if(getenv('AUTOHUOLTO_FORCE_HTTPS')==='1')return true;
    return (!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||(int)($_SERVER['SERVER_PORT']??0)===443;
}
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.use_trans_sid','0');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>authHttps(),'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.gc_maxlifetime','43200');
session_name('autohuolto_'.substr(hash('sha256',__DIR__),0,12));
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
header('Cache-Control: no-store, private');
date_default_timezone_set('Europe/Helsinki');

const DEFAULT_APP_NAME      = 'Autonhuolto';
const DEFAULT_HOME_TITLE    = 'Huoltokirja';
const DEFAULT_HOME_SUBTITLE = 'Pidä omat, perheen ja tuttujen autot samassa huoltohistoriassa.';
const APP_VERSION           = '0.8.70-dev';
/* AGPL v3 §13: verkossa ajettavan ohjelman käyttäjille on tarjottava lähdekoodi. Aseta tähän julkisen koodivaraston osoite (esim. 'https://github.com/KÄYTTÄJÄ/autonhuolto'); tyhjänä linkkiä ei näytetä. */
const APP_SOURCE_URL        = 'https://github.com/oh6kw/autonhuolto';
const SCHEMA_VERSION   = 12;
/* Tietokanta on aina autohuolto.sqlite3 sovelluskansiossa. Ympäristömuuttuja AUTOHUOLTO_DB_PATH voi osoittaa sen muualle (esim. web-juuren ulkopuolelle). */
if(trim((string)getenv('AUTOHUOLTO_DB_PATH'))!=='')define('DB_FILE',(string)getenv('AUTOHUOLTO_DB_PATH'));
else define('DB_FILE',__DIR__.'/autohuolto.sqlite3');
const BACKUP_DB_NAME = 'autohuolto.sqlite3';
const APP_DIR        = __DIR__; /* Käytä näkymissä (views/) __DIR__:n sijaan: APP_DIR on aina sovelluksen juurikansio. */
const CURRENCY     = '€';
const IMAGE_DIR    = __DIR__ . '/kuvat';

require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/exports.php';
require __DIR__ . '/app/cars.php';
require __DIR__ . '/app/maintenance.php';
require __DIR__ . '/app/invoices.php';
require __DIR__ . '/app/inventory.php';
require __DIR__ . '/app/customers.php';
require __DIR__ . '/app/images.php';
require __DIR__ . '/app/edits.php';

/* Kaikki funktiot ovat app/-tiedostoissa (ks. CHANGELOG.md). Tässä tiedostossa on vain authHttps(), koska istunto alustetaan ennen kuin app/-tiedostot ladataan. */

/* Tietokanta: käyttölukko (yksi pyyntö kerrallaan) → avaus ja versiotarkistus → käyttäjähallinta → asetukset. Funktiot: app/db.php */
dbAcquireLock();
try{
    $db=dbConnect();
    authBootstrap($db);
    $app=appSettings($db)+defaultAppSettings();
    $appName=trim((string)$app['shop_name'])?:DEFAULT_APP_NAME;
}catch(Throwable $e){
    http_response_code(503);header('Content-Type: text/plain; charset=utf-8');
    exit('Huoltokirjan tietokantaa ei voitu avata: '.$e->getMessage());
}

/* Myös ennen 0.8.16:ta ladattu nykyinen logo saa johdannaisversiot automaattisesti. Alkuperäiseen ei kosketa. */
$configuredLogo=(string)($app['logo_path']??'');if($configuredLogo!==''&&logoAbsolutePath($configuredLogo)&&is_file((string)logoAbsolutePath($configuredLogo)))ensureLogoDerivatives($configuredLogo);

/* Huoltokuvien näyttö kulkee sovelluksen kirjautumisen kautta. */
if(isset($_GET['image'])){
    $pid=(int)$_GET['image'];$variant=(string)($_GET['variant']??'thumb');if(!in_array($variant,['thumb','web','original'],true))$variant='thumb';$st=$db->prepare("SELECT file_path,mime_type FROM service_photos WHERE id=?");$st->execute([$pid]);$ph=$st->fetch();
    if(!$ph){http_response_code(404);exit('Kuvaa ei löytynyt.');}$relative=(string)$ph['file_path'];$display=$variant==='original'?$relative:photoDisplayRelative($relative,$variant);$abs=photoAbsolutePath($display);if(!$abs||!is_file($abs)){http_response_code(404);exit('Kuvatiedostoa ei löytynyt.');}$info=@getimagesize($abs);$mime=(string)($info['mime']??($ph['mime_type']?:'application/octet-stream'));
    header('Content-Type: '.$mime);header('Content-Length: '.filesize($abs));header('Cache-Control: private, max-age=604800');readfile($abs);exit;
}
/* Yrityksen logo näytetään kirjautumisen takaa. Vain /kuvat/logo/-hakemiston tiedostot sallitaan. */
if(isset($_GET['brand_logo'])){
    $name=basename((string)$_GET['brand_logo']);$relative='kuvat/logo/'.$name;$abs=logoAbsolutePath($relative);
    if(!$abs||!is_file($abs)){http_response_code(404);exit('Logoa ei löytynyt.');}
    $img=@getimagesize($abs);$mime=(string)($img['mime']??'');if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit('Virheellinen logotiedosto.');}
    header('Content-Type: '.$mime);header('Content-Length: '.filesize($abs));header('Cache-Control: private, max-age=604800');readfile($abs);exit;
}

/* Järjestys on tärkeä: actions.php ajaa POST-käsittelijät heti ja kutsuu backup.php:n ja printing.php:n funktioita. */
require __DIR__ . '/app/backup.php';
require __DIR__ . '/app/printing.php';
require __DIR__ . '/app/actions.php';

/* ------------------------------ Sivun data ------------------------------ */
$app=appSettings($db);$appName=trim((string)($app['shop_name']??DEFAULT_APP_NAME))?:DEFAULT_APP_NAME;$theme=$app['theme']??'dark';$mechanicsAll=mechanicsList($db,false);$activeMechanics=mechanicsList($db,true);$customerList=customersList($db,false);$activeCustomers=customersList($db,true);$items=allItems($db,true);$itemMap=itemMap($db);$carId=(int)($_GET['car']??0);$car=$carId?getCar($db,$carId):null;$view=(string)($_GET['view']??'');$showAll=$view==='all';$showSettings=$view==='settings';$showAccount=$view==='account';$showCustomers=$view==='customers'&&customersEnabled($app);$showInvoices=$view==='invoices'&&invoicingEnabled($app);$showInventory=$view==='inventory'&&inventoryEnabled($app);$customerId=$showCustomers?(int)($_GET['customer']??0):0;$customer=$customerId?getCustomer($db,$customerId):null;
$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
$cars=$db->query("SELECT c.*,cu.name current_customer_name,cu.customer_number current_customer_number,(SELECT MAX(service_date) FROM services s WHERE s.car_id=c.id) last_service_date,(SELECT odometer FROM services s WHERE s.car_id=c.id ORDER BY service_date DESC,id DESC LIMIT 1) last_service_km,(SELECT COUNT(*) FROM services s WHERE s.car_id=c.id) service_count FROM cars c LEFT JOIN customers cu ON cu.id=c.current_customer_id ORDER BY COALESCE(NULLIF(cu.name,''),NULLIF(c.owner,''),'~'),COALESCE(NULLIF(c.nickname,''),c.reg_plate),c.id DESC")->fetchAll();
$allServices=[];if($showAll){$allServices=$db->query("SELECT s.*,c.reg_plate,c.nickname,c.owner,c.make,c.model FROM services s JOIN cars c ON c.id=s.car_id ORDER BY s.service_date DESC,s.id DESC")->fetchAll();foreach($allServices as &$r){$r['actions']=getServiceActions($db,(int)$r['id']);$r['custom_actions']=getCustomActions($db,(int)$r['id']);$r['photos']=getServicePhotos($db,(int)$r['id']);$r['inventory_usage']=serviceInventoryUsage($db,(int)$r['id']);}unset($r);attachServiceIntervals($db,$allServices);$per=[];foreach($allServices as $i=>$r)$per[(int)$r['car_id']][]=$i;foreach($per as $idxs)foreach($idxs as $p=>$idx)$allServices[$idx]['gap']=intervalInfo($allServices[$idx],isset($idxs[$p+1])?$allServices[$idxs[$p+1]]:null);}
$settings=[];$events=[];$maintenance=[];$services=[];$parts=[];$partDefaults=[];$partCompatIds=[];$partCompatText=[];$kilometerHistory=[];$currentOwnershipHistory=null;$editService=null;$editActionMap=[];$editCustom=[];$editPhotos=[];$editStockUsage=[];$recommend=[];$driveEstimate=['available'=>false];$dataIssues=$showSettings?dataCheckIssues($db):[];$acceptedIssues=$showSettings?$db->query("SELECT * FROM data_issue_ignores ORDER BY accepted_at DESC")->fetchAll():[];
if($car){$car['current_customer']=currentCustomerForCar($db,$carId);$car['customer_history']=carCustomerHistory($db,$carId);foreach($car['customer_history'] as $ch){if((int)($ch['customer_id']??0)===(int)($car['current_customer_id']??0)&&(string)($ch['end_date']??'')===''){$currentOwnershipHistory=$ch;break;}}$st=$db->prepare("SELECT * FROM car_item_settings WHERE car_id=?");$st->execute([$carId]);foreach($st->fetchAll() as $r)$settings[$r['item_key']]=$r;$settings=normalizeBeltSettings($settings);$events=lastItemEvents($db,$carId);$st=$db->prepare("SELECT * FROM services WHERE car_id=? ORDER BY service_date DESC,id DESC");$st->execute([$carId]);$services=$st->fetchAll();foreach($services as &$r){$r['actions']=getServiceActions($db,(int)$r['id']);$r['custom_actions']=getCustomActions($db,(int)$r['id']);$r['photos']=getServicePhotos($db,(int)$r['id']);$r['inventory_usage']=serviceInventoryUsage($db,(int)$r['id']);}unset($r);attachServiceIntervals($db,$services);$parts=partsForCar($db,$carId);foreach($parts as $p){$pid=(int)$p['id'];$partCompatIds[$pid]=partCompatibleCarIds($db,$pid);$partCompatText[$pid]=partCompatibilityText($db,$pid);$pk=(string)($p['item_key']??'');if($pk!==''&&!isset($partDefaults[$pk]))$partDefaults[$pk]=$p;}usort($parts,fn($a,$b)=>strnatcasecmp((string)$a['part_name'],(string)$b['part_name']));$eid=(int)($_GET['edit_service']??0);if($eid){$st=$db->prepare("SELECT * FROM services WHERE id=? AND car_id=?");$st->execute([$eid,$carId]);$editService=$st->fetch()?:null;if($editService){foreach(getServiceActions($db,$eid) as $a)$editActionMap[$a['item_key']]=$a;$editCustom=getCustomActions($db,$eid);$editPhotos=getServicePhotos($db,$eid);foreach(serviceInventoryUsage($db,$eid) as $u)$editStockUsage[(string)$u['item_key']]=$u;}}
    $kilometerHistory=kilometerHistoryEntries($db,$carId,true);
    $driveEstimate=drivingRateEstimate($db,$car);
    $maintenance=calculateMaintenanceStatus($db,$car,$items,$settings,$events,$driveEstimate);
    foreach($maintenance as $state){if(!$state['enabled'])continue;$due=$state['due'];if(in_array($due['status'],['due','soon'],true))$recommend[]=['item'=>$state['item'],'due'=>$due];}
}

$customerCars=[];$customerHistoryCars=[];$customerServices=[];$customerDeleteRefs=['cars'=>0,'history'=>0,'services'=>0];if($showCustomers&&$customer){
    $st=$db->prepare("SELECT c.*,(SELECT MAX(service_date) FROM services s WHERE s.car_id=c.id) last_service_date,(SELECT COUNT(*) FROM services s WHERE s.car_id=c.id) service_count FROM cars c WHERE c.current_customer_id=? ORDER BY COALESCE(NULLIF(c.nickname,''),c.reg_plate),c.id");$st->execute([$customerId]);$customerCars=$st->fetchAll();
    $st=$db->prepare("SELECT h.*,c.reg_plate,c.nickname,c.make,c.model FROM car_customer_history h JOIN cars c ON c.id=h.car_id WHERE h.customer_id=? ORDER BY CASE WHEN h.start_date='' THEN 1 ELSE 0 END,h.start_date DESC,h.id DESC");$st->execute([$customerId]);$customerHistoryCars=$st->fetchAll();
    $st=$db->prepare("SELECT s.*,c.reg_plate,c.nickname,c.make,c.model FROM services s JOIN cars c ON c.id=s.car_id WHERE s.customer_id=? ORDER BY s.service_date DESC,s.id DESC LIMIT 100");$st->execute([$customerId]);$customerServices=$st->fetchAll();
    $customerDeleteRefs=customerDeleteReferences($db,$customerId);
}

$invoiceRows=[];$invoiceYears=[];$invoiceMonth=preg_match('/^\d{4}-\d{2}$/',(string)($_GET['month']??''))?(string)$_GET['month']:date('Y-m');$invoiceYear=preg_match('/^\d{4}$/',(string)($_GET['year']??''))?(string)$_GET['year']:date('Y');$invoiceListYear=preg_match('/^\d{4}$/',(string)($_GET['list_year']??''))?(string)$_GET['list_year']:'';$invoiceFilterStatus=in_array((string)($_GET['status']??''),['Luonnos','Lähetetty','Maksettu','Hyvitetty'],true)?(string)$_GET['status']:'';$invoiceQ=trim((string)($_GET['q']??''));$monthSummary=[];$yearSummary=[];
$inventoryParts=[];$inventoryValue=0.0;$inventoryLow=0;if($showInventory){$inventoryParts=inventoryPartRows($db);foreach($inventoryParts as $ip){$inventoryValue+=(float)$ip['stock_qty']*(float)($ip['purchase_price']??0);if($ip['reorder_level']!==null&&(float)$ip['stock_qty']<=(float)$ip['reorder_level'])$inventoryLow++;}}
if($showInvoices){
    $invoiceYears=$db->query("SELECT DISTINCT substr(issue_date,1,4) y FROM invoices WHERE length(issue_date)>=4 ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);if(!in_array(date('Y'),$invoiceYears,true))array_unshift($invoiceYears,date('Y'));
    $mStart=$invoiceMonth.'-01';$mDate=new DateTime($mStart);$mEnd=(clone $mDate)->modify('+1 month')->format('Y-m-d');$invoicePrevMonth=(clone $mDate)->modify('-1 month')->format('Y-m');$invoiceNextMonth=(clone $mDate)->modify('+1 month')->format('Y-m');$invoicePrevYear=(string)((int)$invoiceYear-1);$invoiceNextYear=(string)((int)$invoiceYear+1);$yStart=$invoiceYear.'-01-01';$yEnd=((int)$invoiceYear+1).'-01-01';$monthSummary=invoicePeriodSummary($db,$mStart,$mEnd);$yearSummary=invoicePeriodSummary($db,$yStart,$yEnd);
    $where=['1=1'];$params=[];if($invoiceFilterStatus!==''){$where[]='i.status=?';$params[]=$invoiceFilterStatus;}if($invoiceListYear!==''){$where[]='substr(i.issue_date,1,4)=?';$params[]=$invoiceListYear;}if($invoiceQ!==''){$where[]='(i.invoice_number LIKE ? OR i.customer_name LIKE ? OR c.reg_plate LIKE ? OR c.nickname LIKE ? OR c.make LIKE ? OR c.model LIKE ?)';$like='%'.$invoiceQ.'%';array_push($params,$like,$like,$like,$like,$like,$like);}
    $sql="SELECT i.*,s.title,s.car_id,c.reg_plate,c.nickname,c.make,c.model,COALESCE((SELECT SUM(il.qty*il.unit_price_net) FROM invoice_lines il WHERE il.invoice_id=i.id),0) net_total,COALESCE((SELECT SUM(il.qty*il.unit_price_net*il.vat_rate/100.0) FROM invoice_lines il WHERE il.invoice_id=i.id),0) vat_total FROM invoices i JOIN services s ON s.id=i.service_id JOIN cars c ON c.id=s.car_id WHERE ".implode(' AND ',$where)." ORDER BY i.issue_date DESC,i.id DESC";$st=$db->prepare($sql);$st->execute($params);$invoiceRows=$st->fetchAll();
}

$invoiceId=(int)($_GET['invoice']??0);$invoice=null;$invoiceLines=[];if($invoiceId){$st=$db->prepare("SELECT i.*,s.title,s.car_id,c.reg_plate,c.nickname,c.make,c.model FROM invoices i JOIN services s ON s.id=i.service_id JOIN cars c ON c.id=s.car_id WHERE i.id=?");$st->execute([$invoiceId]);$invoice=$st->fetch()?:null;if($invoice){$st=$db->prepare("SELECT * FROM invoice_lines WHERE invoice_id=? ORDER BY sort_order,id");$st->execute([$invoiceId]);$invoiceLines=$st->fetchAll();}}

if(($currentUser['role']??'')!=='admin')ob_start('authFilterHtml');
require __DIR__ . '/views/layout_top.php';
if($showAccount)require __DIR__ . '/views/account.php';
elseif($invoice)require __DIR__ . '/views/invoice.php';
elseif($showInvoices)require __DIR__ . '/views/invoices.php';
elseif($showSettings)require __DIR__ . '/views/settings.php';
elseif($showCustomers)require __DIR__ . '/views/customers.php';
elseif($showInventory)require __DIR__ . '/views/inventory.php';
elseif($showAll)require __DIR__ . '/views/all_services.php';
elseif(!$car)require __DIR__ . '/views/home.php';
else require __DIR__ . '/views/car.php';
require __DIR__ . '/views/layout_bottom.php';

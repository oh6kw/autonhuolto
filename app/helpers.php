<?php
declare(strict_types=1);

/**
 * app/helpers.php – yleiset apufunktiot.
 * Pyynnön ja lomakkeen luku, muotoilu, päivämäärät sekä hinnat ja ALV.
 * Ei tietokantaa eikä käyttäjähallintaa, joten näitä voi kutsua mistä tahansa.
 * Ladataan index.php:n alussa ennen muuta koodia.
 */

/* ------------------------------ Pyyntö, lomake ja istunto ------------------------------ */
function post(string $key, string $default=''): string { return trim((string)($_POST[$key] ?? $default)); }
function intpost(string $key, int $default=0): int { $v=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT); return $v===false?$default:(int)$v; }
function floatpost(string $key, ?float $default=null): ?float {
    $v = str_replace([' ', ','], ['', '.'], post($key));
    if ($v==='') return $default;
    return is_numeric($v) ? (float)$v : $default;
}
function redirect(string $url): never { header('Location: '.$url); exit; }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function requireCsrf(): void {
    if(empty($_SESSION['csrf'])||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])) { http_response_code(400); exit('Virheellinen istuntoavain. Päivitä sivu ja yritä uudelleen.'); }
}
function flash(string $message, string $type='ok'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function normalizeFilesArray(array $f): array { $out=[]; if(!isset($f['name']))return $out; if(!is_array($f['name']))return [$f]; foreach($f['name'] as $i=>$name)$out[]=['name'=>$name,'type'=>$f['type'][$i]??'','tmp_name'=>$f['tmp_name'][$i]??'','error'=>$f['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$f['size'][$i]??0]; return $out; }

/* ------------------------------ Muotoilu ------------------------------ */
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(?float $v): string { return $v===null?'—':number_format($v,2,',',' ').' '.CURRENCY; }
function km(?int $v): string { return !$v?'—':number_format($v,0,',',' ').' km'; }
function recordedKm(?int $v): string { return $v!==null&&$v>0?km($v):'Kilometrilukema ei kirjattu'; }
function dec(float $v, int $digits=2): string { return number_format($v,$digits,',',' '); }
function fileSizeText(int $bytes): string { if($bytes>=1073741824)return number_format($bytes/1073741824,2,',',' ').' Gt';if($bytes>=1048576)return number_format($bytes/1048576,2,',',' ').' Mt';if($bytes>=1024)return number_format($bytes/1024,0,',',' ').' kt';return $bytes.' t'; }
function formatDuration(int $seconds): string {
    $seconds=max(0,$seconds);$h=intdiv($seconds,3600);$m=intdiv($seconds%3600,60);$s=$seconds%60;
    if($h>0)return $h.' h '.str_pad((string)$m,2,'0',STR_PAD_LEFT).' min';
    if($m>0)return $m.' min '.str_pad((string)$s,2,'0',STR_PAD_LEFT).' s';
    return $s.' s';
}
function carName(array $car): string {
    $p=trim((string)($car['nickname']??'')) ?: trim((string)($car['reg_plate']??''));
    $s=trim((string)($car['make']??'').' '.(string)($car['model']??''));
    return $p&&$s ? "$p · $s" : ($p ?: ($s ?: 'Nimetön auto'));
}

/* ------------------------------ Päivämäärät ja välit ------------------------------ */
function fiDate(?string $d): string { if(!$d)return '—'; $ts=strtotime($d); return $ts?date('d.m.Y',$ts):$d; }
function fiMonthLabel(string $ym): string {
    static $months=[1=>'Tammikuu',2=>'Helmikuu',3=>'Maaliskuu',4=>'Huhtikuu',5=>'Toukokuu',6=>'Kesäkuu',7=>'Heinäkuu',8=>'Elokuu',9=>'Syyskuu',10=>'Lokakuu',11=>'Marraskuu',12=>'Joulukuu'];
    if(!preg_match('/^(\d{4})-(\d{2})$/',$ym,$m))return $ym;
    $mo=(int)$m[2];return ($months[$mo]??$m[2]).' '.$m[1];
}
function elapsedText(?string $olderDate, ?string $newerDate): string {
    if(!$olderDate||!$newerDate)return '';
    try { $a=new DateTime($olderDate); $b=new DateTime($newerDate); if($b<$a)return ''; $d=$a->diff($b); $p=[]; if($d->y)$p[]=$d->y.' v'; if($d->m)$p[]=$d->m.' kk'; if($d->d||!$p)$p[]=$d->d.' pv'; return implode(' ',$p); } catch(Throwable){ return ''; }
}
function intervalInfo(array $newer, ?array $older): array {
    if(!$older)return ['km'=>'','time'=>'','text'=>''];
    $kt=''; $nk=(int)($newer['odometer']??0); $ok=(int)($older['odometer']??0);
    if($nk>0&&$ok>0&&$nk>=$ok)$kt=number_format($nk-$ok,0,',',' ').' km';
    $tt=elapsedText($older['service_date']??null,$newer['service_date']??null);
    return ['km'=>$kt,'time'=>$tt,'text'=>implode(' · ',array_filter([$kt,$tt]))];
}
function addMonths(string $date,int $months): string {
    $d=new DateTimeImmutable($date);$day=(int)$d->format('j');
    $target=$d->modify('first day of this month')->modify(($months>=0?'+':'').$months.' months');
    return $target->setDate((int)$target->format('Y'),(int)$target->format('n'),min($day,(int)$target->format('t')))->format('Y-m-d');
}
function validOptionalIsoDate(string $date): bool {
    if($date==='')return true;
    $d=DateTime::createFromFormat('!Y-m-d',$date);
    return $d!==false && $d->format('Y-m-d')===$date;
}

/* ------------------------------ Hinnat ja ALV ------------------------------ */
function priceInputMode(array $app): string { return (string)($app['price_input_mode']??'net')==='gross'?'gross':'net'; }
function appVatRate(array $app): float { return max(0.0,(float)($app['vat_rate']??25.5)); }
function priceNetFromInput(?float $value,array $app): ?float {
    if($value===null)return null;
    if(priceInputMode($app)==='gross'){ $factor=1.0+appVatRate($app)/100.0; return $factor>0?$value/$factor:$value; }
    return $value;
}
function priceInputFromNet(?float $value,array $app): ?float {
    if($value===null)return null;
    return priceInputMode($app)==='gross'?$value*(1.0+appVatRate($app)/100.0):$value;
}
function priceNetFromInputAtVat(?float $value,array $app,?float $vatRate=null): ?float {
    if($value===null)return null;
    $vat=$vatRate??appVatRate($app);
    if(priceInputMode($app)==='gross'){ $factor=1.0+max(0.0,$vat)/100.0; return $factor>0?$value/$factor:$value; }
    return $value;
}
function priceInputFromNetAtVat(?float $value,array $app,?float $vatRate=null): ?float {
    if($value===null)return null;
    $vat=$vatRate??appVatRate($app);
    return priceInputMode($app)==='gross'?$value*(1.0+max(0.0,$vat)/100.0):$value;
}
/** Varaston hankintahinnan otsikko: sama ALV-tulkinta kuin hintojen syöttötavassa (asetukset). */
function purchasePriceLabel(array $app,string $base='Hankintahinta'): string {
    return priceInputMode($app)==='gross'?$base.' sis. ALV '.dec(appVatRate($app),1).' %':$base.' veroton';
}
/** Tallennettu hankintahinta (syöttötavan mukainen) -> [veroton, verollinen], pyöristetty 2 desimaaliin. */
function purchasePriceNetGross(array $app,?float $stored): array {
    if($stored===null)return [null,null];
    $f=1+appVatRate($app)/100;
    return priceInputMode($app)==='gross'?[round($stored/$f,2),round($stored,2)]:[round($stored,2),round($stored*$f,2)];
}
function priceInputLabel(array $app,string $base='Hinta'): string {
    return priceInputMode($app)==='gross'?$base.' sis. ALV '.dec(appVatRate($app),1).' %':$base.' veroton';
}

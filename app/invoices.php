<?php
declare(strict_types=1);

/**
 * app/invoices.php – laskut: numerointi, jaksoyhteenveto ja tilat.
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

/** Suomalaisen viitenumeron tarkistenumero (painot 7-3-1 oikealta vasemmalle). */
function invoiceReferenceCheckDigit(string $digits): int {
    $weights=[7,3,1];$sum=0;$n=strlen($digits);
    for($i=0;$i<$n;$i++)$sum+=(int)$digits[$n-1-$i]*$weights[$i%3];
    return (10-($sum%10))%10;
}
/** Viitenumero johdetaan laskunumerosta (esim. 2026-0001 → 20260001 + tarkiste). Tyhjä, jos numerosta ei saa kelvollista viitettä (4–20 numeroa). */
function invoiceReference(string $invoiceNumber): string {
    $d=preg_replace('/\D/','',$invoiceNumber);if($d===null||strlen($d)<3||strlen($d)>19)return '';
    return $d.invoiceReferenceCheckDigit($d);
}
/** Laskun viite: laskua luotaessa tallennettu arvo. Jos laskurivissä ei ole reference-saraketta (vanha kysely), lasketaan se laskunumerosta. */
function invoiceRef(array $inv): string {
    if(array_key_exists('reference',$inv))return trim((string)$inv['reference']);
    return invoiceReference((string)($inv['invoice_number']??''));
}
/** Maksulomake ja viivakoodi tulostetaan vain avoimelle laskulle (luonnos tai lähetetty): maksettua tai hyvitettyä laskua ei pidä maksaa uudelleen. */
function invoiceShowsPaymentForm(array $inv): bool { return in_array((string)($inv['status']??''),['draft','sent'],true); }
/** Viite luettavassa muodossa: viiden numeron ryhmissä oikealta lukien. */
function invoiceReferenceFormatted(string $reference): string {
    return $reference===''?'':strrev(trim(chunk_split(strrev($reference),5,' ')));
}
/** Code 128 -symbolien palkki-/välileveydet (arvot 0–106; 106 = lopetus, 7 elementtiä). */
const CODE128_PATTERNS=['212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'];
/**
 * Suomalaisen pankkiviivakoodin (versio 4) numerosarja, 54 numeroa. Tyhjä, jos tiedoista ei saa kelvollista koodia:
 * IBAN pitää olla suomalainen (FI + 16 numeroa), viite 4–20 numeroa, summa 0,01–999 999,99 €.
 * Rakenne: 4 + IBAN:n 16 numeroa + euroina 6 + sentteinä 2 + 000 + viite 20 (nollilla täytetty) + eräpäivä VVKKPP (000000, jos ei ole).
 */
function invoiceBarcodeDigits(string $iban,float $gross,string $reference,string $dueDateIso): string {
    $iban=strtoupper(preg_replace('/\s+/','',$iban)??'');
    if(!preg_match('/^FI\d{16}$/',$iban)||!preg_match('/^\d{4,20}$/',$reference))return '';
    $cents=(int)round($gross*100);if($cents<1||$cents>99999999)return '';
    $due=preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$dueDateIso,$m)?substr($m[1],2).$m[2].$m[3]:'000000';
    $digits='4'.substr($iban,2).str_pad((string)$cents,8,'0',STR_PAD_LEFT).'000'.str_pad($reference,20,'0',STR_PAD_LEFT).$due;
    return strlen($digits)===54?$digits:'';
}
/** Code 128 -viivakoodi (merkistö C, parillinen määrä numeroita) SVG:nä. Tyhjä, jos syöte ei kelpaa. */
function code128cSvg(string $digits,float $moduleMm=0.33,float $heightMm=14.0): string {
    if($digits===''||strlen($digits)%2!==0||!ctype_digit($digits))return '';
    $values=[105];for($i=0;$i<strlen($digits);$i+=2)$values[]=(int)substr($digits,$i,2);
    $sum=105;for($i=1;$i<count($values);$i++)$sum+=$values[$i]*$i;$values[]=$sum%103;$values[]=106;
    $x=10;$rects='';
    foreach($values as $v){$bar=true;foreach(str_split(CODE128_PATTERNS[$v]) as $w){$w=(int)$w;if($bar)$rects.='<rect x="'.$x.'" y="0" width="'.$w.'" height="50"/>';$x+=$w;$bar=!$bar;}}
    $total=$x+10;
    return '<svg class="barcode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$total.' 50" width="'.round($total*$moduleMm,1).'mm" height="'.$heightMm.'mm" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="'.h(t('print.barcode_aria')).'"><rect x="0" y="0" width="'.$total.'" height="50" fill="#fff"/><g fill="#000">'.$rects.'</g></svg>';
}
/** Laskun pankkiviivakoodi SVG:nä (tyhjä, jos asetus on pois päältä tai laskulta puuttuu kelvollinen IBAN/viite/summa). */
function invoiceBarcodeSvg(array $app,array $inv,float $gross): string {
    if(($app['invoice_barcode']??'1')!=='1'||!invoiceShowsPaymentForm($inv))return '';
    return code128cSvg(invoiceBarcodeDigits((string)($inv['seller_iban']??''),$gross,invoiceRef($inv),(string)($inv['due_date']??'')));
}
/** Tilisiirtolomake (tulosteen alareunaan): saaja, IBAN/BIC, maksaja, viite, eräpäivä, summa ja pankkiviivakoodi. Kaksikielinen kiinteä teksti (fi/sv). */
function invoiceGiroHtml(array $app,array $inv,float $gross): string {
    $ref=invoiceRef($inv);
    $payer=trim((string)$inv['customer_name']);if(trim((string)$inv['customer_address'])!=='')$payer.="\n".trim((string)$inv['customer_address']);
    $payee=trim((string)$inv['seller_name']);if(trim((string)$inv['seller_address'])!=='')$payee.="\n".trim((string)$inv['seller_address']);
    $barcode=invoiceBarcodeSvg($app,$inv,$gross);
    $c=fn(string $k)=>t('print.giro_'.$k);
    $lab=fn(string $k)=>'<div class="giro-label">'.$c($k).'</div>';
    ob_start();?>
<div class="giro-wrap"><div class="giro-cut"><span>✂</span></div><div class="giro">
<div class="g g-lab g-r1"><?=$lab('payee_account')?></div>
<div class="g g-r1 g-iban"><span class="g-tiny">IBAN</span><strong><?=h(trim(chunk_split(strtoupper(preg_replace('/\s+/','',(string)$inv['seller_iban'])),4,' ')))?></strong></div>
<div class="g g-r1 g-bic"><span class="g-tiny">BIC</span><strong><?=h($inv['seller_bic'])?></strong></div>
<div class="g g-lab g-r2"><?=$lab('payee')?></div>
<div class="g g-r2 g-payee"><?=nl2br(h($payee))?></div>
<div class="g g-msg"><span class="g-tiny"><?=$c('message')?></span><?php if($ref===''):?><div><?=h(t('print.giro_invoice_ref',['number'=>$inv['invoice_number']]))?></div><?php endif;?></div>
<div class="g g-lab g-r3"><div class="giro-vert"><?=$c('title')?></div><div class="giro-lab-top"><?=$lab('payer')?></div><div class="giro-lab-bottom"><?=$lab('signature')?></div></div>
<div class="g g-r3 g-payer"><div><?=nl2br(h($payer))?></div><div class="giro-sign"></div></div>
<div class="g g-lab g-ref"><?=$lab('reference')?></div>
<div class="g g-ref g-refval"><strong><?=h(invoiceReferenceFormatted($ref))?></strong></div>
<div class="g g-lab g-r4"><?=$lab('from_account')?></div>
<div class="g g-r4 g-from"></div>
<div class="g g-lab g-r4 g-duelab"><?=$lab('due')?></div>
<div class="g g-r4 g-dueval"><strong><?=h(fiDate((string)$inv['due_date']))?></strong></div>
<div class="g g-r4 g-sum"><span class="g-tiny"><?=$c('sum')?></span><strong><?=h(number_format($gross,2,',',' ').' EUR')?></strong></div>
</div><div class="giro-foot"><div class="giro-barcode"><?=$barcode?></div><div class="giro-legal"><?=$c('legal_fi')?><br><?=$c('legal_sv')?><div class="giro-bank">PANKKI BANKEN</div></div></div></div>
<?php return (string)ob_get_clean();
}
function nextInvoiceNumber(PDO $db): string {
    $year=date('Y');
    $st=$db->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC");
    $st->execute([$year.'-%']); $max=0;
    foreach($st->fetchAll(PDO::FETCH_COLUMN) as $n){ if(preg_match('/^'.preg_quote($year,'/').'-(\d+)$/',(string)$n,$m))$max=max($max,(int)$m[1]); }
    return $year.'-'.str_pad((string)($max+1),4,'0',STR_PAD_LEFT);
}
function invoicePeriodSummary(PDO $db,string $from,string $to): array {
    /* Laskutettu/avoin/luonnos/hyvitetty ryhmitellään laskun päiväyksellä. Maksettu ryhmitellään
       varsinaisella paid_at-aikaleimalla; jos maksupäivää ei ole tallennettu, käytetään issue_datea. */
    $sql="SELECT i.status,COUNT(*) invoice_count,
                 COALESCE(SUM((SELECT SUM(il.qty*il.unit_price_net) FROM invoice_lines il WHERE il.invoice_id=i.id)),0) net_total,
                 COALESCE(SUM((SELECT SUM(il.qty*il.unit_price_net*il.vat_rate/100.0) FROM invoice_lines il WHERE il.invoice_id=i.id)),0) vat_total
          FROM invoices i WHERE i.issue_date>=? AND i.issue_date<? GROUP BY i.status";
    $st=$db->prepare($sql);$st->execute([$from,$to]);
    $out=['all_count'=>0,'billed_count'=>0,'billed_net'=>0.0,'billed_vat'=>0.0,'billed_gross'=>0.0,'paid_gross'=>0.0,'paid_count'=>0,'open_gross'=>0.0,'draft_gross'=>0.0,'draft_count'=>0,'credited_gross'=>0.0,'credited_count'=>0];
    foreach($st->fetchAll() as $r){
        $status=(string)$r['status'];$count=(int)$r['invoice_count'];$net=(float)$r['net_total'];$vat=(float)$r['vat_total'];$gross=$net+$vat;$out['all_count']+=$count;
        if(in_array($status,['sent','paid'],true)){$out['billed_count']+=$count;$out['billed_net']+=$net;$out['billed_vat']+=$vat;$out['billed_gross']+=$gross;}
        if($status==='sent')$out['open_gross']+=$gross;
        elseif($status==='draft'){$out['draft_gross']+=$gross;$out['draft_count']+=$count;}
        elseif($status==='credited'){$out['credited_gross']+=$gross;$out['credited_count']+=$count;}
    }
    $paidSql="SELECT COUNT(*) cnt,
                    COALESCE(SUM((SELECT SUM(il.qty*il.unit_price_net*(1+il.vat_rate/100.0)) FROM invoice_lines il WHERE il.invoice_id=i.id)),0) gross_total
              FROM invoices i
              WHERE i.status='paid' AND COALESCE(NULLIF(substr(i.paid_at,1,10),''),i.issue_date)>=? AND COALESCE(NULLIF(substr(i.paid_at,1,10),''),i.issue_date)<?";
    $pst=$db->prepare($paidSql);$pst->execute([$from,$to]);$pr=$pst->fetch()?:[];$out['paid_count']=(int)($pr['cnt']??0);$out['paid_gross']=(float)($pr['gross_total']??0);
    return $out;
}
function invoiceCanDelete(array $invoice): bool {
    return ($invoice['status']??'')==='draft'&&empty($invoice['sent_at'])&&empty($invoice['paid_at'])&&empty($invoice['credited_at']);
}
function invoiceAllowedStatuses(array $invoice): array {
    if(!empty($invoice['credited_at'])||($invoice['status']??'')==='credited')return ['credited'];
    if(!empty($invoice['paid_at'])||($invoice['status']??'')==='paid')return ['paid','credited'];
    if(!empty($invoice['sent_at'])||($invoice['status']??'')==='sent')return ['sent','paid','credited'];
    return ['draft','sent','paid','credited'];
}
/** Code 128 C -viivakoodin palkit moduuleina: lista [x, leveys] ja kokonaisleveys (sis. 10 moduulin hiljaiset alueet). Tyhjä lista, jos syöte ei kelpaa. */
function code128cBars(string $digits): array {
    if($digits===''||strlen($digits)%2!==0||!ctype_digit($digits))return ['bars'=>[],'total'=>0];
    $values=[105];for($i=0;$i<strlen($digits);$i+=2)$values[]=(int)substr($digits,$i,2);
    $sum=105;for($i=1;$i<count($values);$i++)$sum+=$values[$i]*$i;$values[]=$sum%103;$values[]=106;
    $x=10;$bars=[];
    foreach($values as $v){$bar=true;foreach(str_split(CODE128_PATTERNS[$v]) as $w){$w=(int)$w;if($bar)$bars[]=[$x,$w];$x+=$w;$bar=!$bar;}}
    return ['bars'=>$bars,'total'=>$x+10];
}
/** Laskun viivakoodin numerosarja (tyhjä, jos asetus on pois päältä, lasku ei ole avoin tai tiedot eivät kelpaa). */
function invoiceBarcodeDigitsFor(array $app,array $inv,float $gross): string {
    if(($app['invoice_barcode']??'1')!=='1'||!invoiceShowsPaymentForm($inv))return '';
    return invoiceBarcodeDigits((string)($inv['seller_iban']??''),$gross,invoiceRef($inv),(string)($inv['due_date']??''));
}

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
/** Viite luettavassa muodossa: viiden numeron ryhmissä oikealta lukien. */
function invoiceReferenceFormatted(string $reference): string {
    return $reference===''?'':strrev(trim(chunk_split(strrev($reference),5,' ')));
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
        if(in_array($status,['Lähetetty','Maksettu'],true)){$out['billed_count']+=$count;$out['billed_net']+=$net;$out['billed_vat']+=$vat;$out['billed_gross']+=$gross;}
        if($status==='Lähetetty')$out['open_gross']+=$gross;
        elseif($status==='Luonnos'){$out['draft_gross']+=$gross;$out['draft_count']+=$count;}
        elseif($status==='Hyvitetty'){$out['credited_gross']+=$gross;$out['credited_count']+=$count;}
    }
    $paidSql="SELECT COUNT(*) cnt,
                    COALESCE(SUM((SELECT SUM(il.qty*il.unit_price_net*(1+il.vat_rate/100.0)) FROM invoice_lines il WHERE il.invoice_id=i.id)),0) gross_total
              FROM invoices i
              WHERE i.status='Maksettu' AND COALESCE(NULLIF(substr(i.paid_at,1,10),''),i.issue_date)>=? AND COALESCE(NULLIF(substr(i.paid_at,1,10),''),i.issue_date)<?";
    $pst=$db->prepare($paidSql);$pst->execute([$from,$to]);$pr=$pst->fetch()?:[];$out['paid_count']=(int)($pr['cnt']??0);$out['paid_gross']=(float)($pr['gross_total']??0);
    return $out;
}
function invoiceCanDelete(array $invoice): bool {
    return ($invoice['status']??'')==='Luonnos'&&empty($invoice['sent_at'])&&empty($invoice['paid_at'])&&empty($invoice['credited_at']);
}
function invoiceAllowedStatuses(array $invoice): array {
    if(!empty($invoice['credited_at'])||($invoice['status']??'')==='Hyvitetty')return ['Hyvitetty'];
    if(!empty($invoice['paid_at'])||($invoice['status']??'')==='Maksettu')return ['Maksettu','Hyvitetty'];
    if(!empty($invoice['sent_at'])||($invoice['status']??'')==='Lähetetty')return ['Lähetetty','Maksettu','Hyvitetty'];
    return ['Luonnos','Lähetetty','Maksettu','Hyvitetty'];
}

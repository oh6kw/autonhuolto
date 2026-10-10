<?php
declare(strict_types=1);
/* Laskun lähetys sähköpostilla (PDF-liitteenä). Ladataan views/invoice.php:stä. */
if(!canAction('send_invoice_email'))return;
$mailReady=mailConfigured($app);
$emailedAt=trim((string)($invoice['emailed_at']??''));
$defaultTo=trim((string)($invoice['customer_email']??''));
$gross=$net+$vat;
$shopName=trim((string)($invoice['seller_name']??''))?:$appName;
$defaultSubject=t('mail.invoice_subject',['number'=>(string)$invoice['invoice_number'],'shop'=>$shopName]);
$ref=invoiceRef($invoice);
$payLines=[];
if(invoiceShowsPaymentForm($invoice)&&trim((string)$invoice['seller_iban'])!==''){
    $payLines[]=t('mail.invoice_pay_iban',['iban'=>trim(chunk_split(strtoupper((string)preg_replace('/\s+/','',(string)$invoice['seller_iban'])),4,' '))]);
    $payLines[]=$ref!==''?t('mail.invoice_pay_reference',['ref'=>invoiceReferenceFormatted($ref)]):t('mail.invoice_pay_message',['number'=>(string)$invoice['invoice_number']]);
}
$defaultBody=t('mail.invoice_body',['number'=>(string)$invoice['invoice_number'],'amount'=>money($gross),'due'=>fiDate((string)$invoice['due_date']),'shop'=>$shopName,'pay'=>implode("\n",$payLines)]);
?>
<details class="clean invoice-mail" id="sahkoposti"><summary class="btn2">✉ <?=t('invoice.mail_summary')?><?php if($emailedAt!==''):?> <span class="badge okbadge"><?=t('invoice.mail_sent_badge')?></span><?php endif;?></summary><div class="inside">
<?php if($emailedAt!==''):?><p class="tiny"><?=t('invoice.mail_last',['time'=>h(date('d.m.Y H:i',strtotime($emailedAt))),'to'=>h((string)$invoice['emailed_to'])])?></p><?php endif;?>
<?php if(!$mailReady):?>
<div class="flash warn"><?=t('invoice.mail_not_configured')?><?php if(canAction('save_app_settings')):?> <a href="?view=settings#sahkoposti"><?=t('invoice.mail_open_settings')?></a><?php endif;?></div>
<?php else:?>
<form method="post" id="invoiceMailForm"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="send_invoice_email"><input type="hidden" name="invoice_id" value="<?=$invoiceId?>"><?php editField($db,'invoice',$invoiceId);?>
<div class="grid grid2"><div><label for="im_to"><?=t('invoice.mail_to')?></label><input id="im_to" name="mail_to" value="<?=h($defaultTo)?>" required placeholder="<?=h(t('invoice.mail_to_placeholder'))?>" autocomplete="off"><div class="tiny"><?=t('invoice.mail_to_help')?></div></div><div><label for="im_sub"><?=t('invoice.mail_subject')?></label><input id="im_sub" name="mail_subject" value="<?=h($defaultSubject)?>" required maxlength="200"></div></div>
<label for="im_msg"><?=t('invoice.mail_message')?></label><textarea id="im_msg" name="mail_message" rows="9" maxlength="5000"><?=h($defaultBody)?></textarea>
<div class="mail-opts"><label><input type="checkbox" name="mail_attach_pdf" value="1" checked> <?=t('invoice.mail_attach_pdf')?></label><?php if((string)$invoice['status']==='draft'):?><label><input type="checkbox" name="mail_mark_sent" value="1" checked> <?=t('invoice.mail_mark_sent')?></label><?php endif;?></div>
<div class="form-actions"><button class="btn" onclick="return confirm('<?=h(addcslashes(t('invoice.mail_confirm'),"'\\"))?>')"><?=t('invoice.mail_send')?></button></div>
<p class="tiny"><?=t('invoice.mail_note')?></p>
</form>
<?php endif;?>
</div></details>

<?php
declare(strict_types=1);
/* Luonnoslaskun muokkaus: päivämäärät, asiakas, huomautus ja rivit. Vain luonnokselle, jota ei ole lähetetty (invoiceCanDelete). Ladataan views/invoice.php:stä. */
if(!invoiceCanDelete($invoice)||!canAction('update_invoice'))return;
$unitOpts=vocabOptions('unit');
$rowHtml=function(string $i,array $l) use ($unitOpts,$app): string {
    $unit=(string)($l['unit']??'pcs');$opts=$unitOpts;if($unit!==''&&!isset($opts[$unit]))$opts[$unit]=$unit;
    ob_start();?>
<tr class="inv-edit-row"><td><input type="hidden" name="line_id[<?=h($i)?>]" value="<?=(int)($l['id']??0)?>"><input name="line_desc[<?=h($i)?>]" value="<?=h($l['description']??'')?>" maxlength="300" aria-label="<?=h(t('invoice.col_description'))?>"></td>
<td><input name="line_qty[<?=h($i)?>]" value="<?=isset($l['qty'])?h(rtrim(rtrim(number_format((float)$l['qty'],3,',',''),'0'),',')):''?>" inputmode="decimal" size="6" aria-label="<?=h(t('invoice.col_qty'))?>"></td>
<td><select name="line_unit[<?=h($i)?>]" aria-label="<?=h(t('invoice.edit_unit'))?>"><?php foreach($opts as $uc=>$ul):?><option value="<?=h($uc)?>" <?=$uc===$unit?'selected':''?>><?=h($ul)?></option><?php endforeach;?></select></td>
<td><input name="line_price[<?=h($i)?>]" value="<?=isset($l['unit_price_net'])?h(number_format((float)$l['unit_price_net'],2,',','')):''?>" inputmode="decimal" size="8" aria-label="<?=h(t('invoice.col_unit_price_vat0'))?>"></td>
<td><input name="line_vat[<?=h($i)?>]" value="<?=isset($l['vat_rate'])?h(rtrim(rtrim(number_format((float)$l['vat_rate'],2,',',''),'0'),',')):h(rtrim(rtrim(number_format(appVatRate($app),2,',',''),'0'),','))?>" inputmode="decimal" size="4" aria-label="<?=h(t('invoice.col_vat'))?>"></td>
<td class="inv-edit-del"><?php if(!empty($l['id'])):?><label><input type="checkbox" name="line_delete[<?=h($i)?>]" value="1"> <?=t('invoice.edit_delete_line')?></label><?php endif;?></td></tr>
<?php return (string)ob_get_clean();
};
?>
<details class="clean invoice-edit" id="muokkaa"><summary class="btn2"><?=t('invoice.edit_summary')?></summary><div class="inside">
<form method="post" id="invoiceEditForm"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="update_invoice"><input type="hidden" name="invoice_id" value="<?=$invoiceId?>"><?php editField($db,'invoice',$invoiceId);?>
<p class="tiny"><?=t('invoice.edit_help')?></p>
<div class="grid grid4"><div><label for="ie_issue"><?=t('invoice.issue_date')?></label><input id="ie_issue" type="date" name="issue_date" value="<?=h($invoice['issue_date'])?>" required></div><div><label for="ie_due"><?=t('invoice.due_date')?></label><input id="ie_due" type="date" name="due_date" value="<?=h($invoice['due_date'])?>" required></div></div>
<h3 class="set-h"><?=t('invoice.customer')?></h3>
<div class="grid grid4"><div><label for="ie_cn"><?=t('invoice.edit_customer_name')?></label><input id="ie_cn" name="customer_name" value="<?=h($invoice['customer_name'])?>" maxlength="200"></div><div><label for="ie_ca"><?=t('invoice.edit_customer_address')?></label><textarea id="ie_ca" name="customer_address" rows="3"><?=h($invoice['customer_address'])?></textarea></div><div><label for="ie_ce"><?=t('invoice.edit_customer_email')?></label><input id="ie_ce" type="email" name="customer_email" value="<?=h($invoice['customer_email'])?>" maxlength="254"></div><div><label for="ie_cb"><?=t('invoice.edit_customer_business_id')?></label><input id="ie_cb" name="customer_business_id" value="<?=h($invoice['customer_business_id'])?>" maxlength="40"></div></div>
<h3 class="set-h"><?=t('invoice.edit_lines')?></h3>
<div class="tablewrap"><table class="inv-edit-table"><thead><tr><th><?=t('invoice.col_description')?></th><th><?=t('invoice.col_qty')?></th><th><?=t('invoice.edit_unit')?></th><th><?=t('invoice.col_unit_price_vat0')?></th><th><?=t('invoice.col_vat')?></th><th></th></tr></thead><tbody id="invEditRows">
<?php $ri=0;foreach($invoiceLines as $l){echo $rowHtml((string)$ri,$l);$ri++;}for($k=0;$k<2;$k++){echo $rowHtml((string)$ri,[]);$ri++;}?>
</tbody></table></div>
<template id="invRowTpl"><?=$rowHtml('__I__',[])?></template>
<div class="form-actions"><button type="button" class="btn2" id="invAddRow" data-next="<?=$ri?>"><?=t('invoice.edit_add_line')?></button></div>
<h3 class="set-h"><?=t('invoice.edit_note')?></h3>
<div><label for="ie_note" class="sr-only"><?=t('invoice.edit_note')?></label><textarea id="ie_note" name="invoice_note" rows="3" maxlength="1500" placeholder="<?=h(t('invoice.edit_note_placeholder'))?>"><?=h($invoice['note']??'')?></textarea><div class="tiny"><?=t('invoice.edit_note_help')?></div></div>
<div class="form-actions"><button class="btn"><?=t('invoice.edit_save')?></button></div>
</form>
<script>(function(){var b=document.getElementById('invAddRow'),t=document.getElementById('invRowTpl'),body=document.getElementById('invEditRows');if(!b||!t||!body)return;b.addEventListener('click',function(){var n=parseInt(b.dataset.next,10)||0;b.dataset.next=String(n+1);var tmp=document.createElement('tbody');tmp.innerHTML=t.innerHTML.replace(/__I__/g,String(n));var row=tmp.firstElementChild;if(row){body.appendChild(row);var f=row.querySelector('input[name^="line_desc"]');if(f)f.focus();}});})();</script>
</div></details>

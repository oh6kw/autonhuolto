<?php
declare(strict_types=1);
/* Huoltosivun lähetys sähköpostilla (versio 1.2.7). Ladataan views/car.php:n huoltohistoriasta; muuttujat $s (huolto), $carId, $app, $appName ovat käytössä. */
if(!canAction('send_service_email'))return;
$smReady=mailConfigured($app);
$smShop=trim((string)($app['shop_name']??''))?:$appName;
$smCar=trim(trim((string)(($car['reg_plate']??'')?:($car['nickname']??''))).' '.trim((string)($car['make']??'').' '.(string)($car['model']??'')));
$smTo=trim((string)($s['customer_email_snapshot']??''));
if($smTo==='')$smTo=trim((string)($car['current_customer']['email']??''));
$smSubject=t('mail.service_subject',['title'=>(string)$s['title'],'car'=>$smCar,'shop'=>$smShop]);
$smBody=t('mail.service_body',['car'=>$smCar,'date'=>fiDate((string)$s['service_date']),'shop'=>$smShop]);
$smId='sm'.(int)$s['id'];
?>
<details class="clean service-mail" style="margin-top:10px"><summary class="btn2">✉ <?=t('car.service_mail_summary')?></summary><div class="inside">
<?php if(!$smReady):?>
<div class="flash warn"><?=t('invoice.mail_not_configured')?><?php if(canAction('save_app_settings')):?> <a href="?view=settings#sahkoposti"><?=t('invoice.mail_open_settings')?></a><?php endif;?></div>
<?php else:?>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="send_service_email"><input type="hidden" name="service_id" value="<?=(int)$s['id']?>">
<div class="grid grid2"><div><label for="<?=$smId?>_to"><?=t('invoice.mail_to')?></label><input id="<?=$smId?>_to" name="mail_to" value="<?=h($smTo)?>" required placeholder="<?=h(t('invoice.mail_to_placeholder'))?>" autocomplete="off"><div class="tiny"><?=t('invoice.mail_to_help')?></div></div><div><label for="<?=$smId?>_sub"><?=t('invoice.mail_subject')?></label><input id="<?=$smId?>_sub" name="mail_subject" value="<?=h($smSubject)?>" required maxlength="200"></div></div>
<label for="<?=$smId?>_msg"><?=t('invoice.mail_message')?></label><textarea id="<?=$smId?>_msg" name="mail_message" rows="6" maxlength="5000"><?=h($smBody)?></textarea>
<p class="tiny"><?=t('car.service_mail_note')?></p>
<div class="form-actions"><button class="btn" onclick="return confirm('<?=h(addcslashes(t('car.service_mail_confirm'),"'\\"))?>')"><?=t('car.service_mail_send')?></button></div>
</form>
<?php endif;?>
</div></details>

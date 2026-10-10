<?php
declare(strict_types=1);
?><!doctype html><html lang="<?=h(i18nLang())?>" data-theme="<?=h($theme)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($appName)?><?= $car?' – '.h(carName($car)):($showInvoices?' – '.t('layout.invoices'):($showInventory?' – '.t('layout.inventory_title'):($showCustomers?' – '.t('layout.customers'):($showSettings?' – '.t('layout.settings'):'')))) ?></title>
<?php
$assetBase=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??''))),'/');
if($assetBase==='.'||$assetBase==='')$assetBase='';
if($assetBase==='/')$assetBase='';
$assetBase.='/assets';
?>
<link rel="stylesheet" href="<?=h($assetBase)?>/app.css?v=<?=rawurlencode(APP_VERSION)?>">
<script>window.I18N=<?=json_encode((object)i18nJsPayload(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP)?>;</script>
<script src="<?=h($assetBase)?>/app.js?v=<?=rawurlencode(APP_VERSION)?>" defer></script>
</head><body>
<header class="top"><div class="wrap topin"><a class="logo" href="?"><?php $headerLogo=(string)($app['logo_path']??'');if(($app['show_logo_header']??'0')==='1'&&$headerLogo!==''&&logoAbsolutePath($headerLogo)&&is_file((string)logoAbsolutePath($headerLogo))):?><img class="header-logo-img" src="<?=h(logoUrl($headerLogo,'header'))?>" alt="<?=h($appName)?>"><?php endif;?><span class="logo-copy"><b>🔧 <?=h($appName)?></b><small><?=h(APP_VERSION)?></small></span></a><nav class="nav"><a href="?"><?=t('layout.cars')?></a><?php if(customersEnabled($app)):?><a href="?view=customers"><?=t('layout.customers')?></a><?php endif;?><?php if(invoicingEnabled($app)):?><a href="?view=invoices"><?=t('layout.invoices')?></a><?php endif;?><?php if(inventoryEnabled($app)):?><a href="?view=inventory"><?=t('layout.inventory_nav')?></a><?php endif;?><a href="?view=all"><?=t('layout.all_services')?></a><?php if(canAction('save_app_settings')):?><a href="?view=settings"><?=t('layout.settings')?></a><?php endif;?><a href="?view=account"><?=h($currentUser['display_name'])?> · <?=h(roleLabel($currentUser['role']))?></a><form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout"><button class="btn2"><?=t('layout.logout')?></button></form></nav></div></header>
<main class="wrap"><?php if($flash):?><div class="flash <?=h($flash['type'])?>"><?=h($flash['message'])?></div><?php endif;?>

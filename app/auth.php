<?php
declare(strict_types=1);

/**
 * app/auth.php – käyttäjät, kirjautuminen ja käyttöoikeudet.
 * Salasanat ja istunto, kirjautumisen rajoitus, käyttäjähallinnan POST-käsittely (authHandlePost),
 * roolit ja toimintokohtaiset oikeudet (canAction), lomakkeiden suodatus katselijalle (authFilterHtml) ja tapahtumaloki.
 * Vain funktiot ja vakiot; mitään ei suoriteta latauksessa. authBootstrap() ja authHandlePost() kutsutaan index.php:stä.
 * Ladataan index.php:n alussa ennen tietokannan avaamista. authHttps() on index.php:ssä, koska istunto alustetaan ennen tätä tiedostoa.
 */

// Tunnistautuminen on erillinen lisäys skeemaan 12. Huoltohistoriaa ei muuteta.
const AUTH_DUMMY_HASH = '$2y$12$HSP2nXRzGiZ.GDZskvA/4uBm5gnbxovaClAfxGMI8u/ioTrVgGNPq';
const AUTH_IDLE_SECONDS = 28800;
const AUTH_MAX_SECONDS = 43200;
const AUTH_AUDIT_PAGE_SIZE = 50;
function loggedIn(): bool { return !empty($GLOBALS['currentUser']); }
function authValue(PDO $db,string $key): string {
    $st=$db->prepare('SELECT setting_value FROM app_settings WHERE setting_key=?');$st->execute([$key]);return (string)($st->fetchColumn()?:'');
}
function authAudit(PDO $db,string $event,?int $target=null): void {
    $db->prepare('INSERT INTO auth_audit(actor_id,event,target_id,created_at) VALUES(?,?,?,?)')->execute([$GLOBALS['currentUser']['id']??null,$event,$target,date('c')]);
}
function authUser(PDO $db,int $id): ?array {
    $st=$db->prepare('SELECT * FROM auth_users WHERE id=?');$st->execute([$id]);return $st->fetch()?:null;
}
function roleLabel(string $role): string { return t(['viewer'=>'auth.role_viewer','editor'=>'auth.role_editor','admin'=>'auth.role_admin'][$role]??'auth.role_none'); }
function authEventLabel(string $event): string {
    if(preg_match('/^clear_auth_audit:(login|all):([0-9]+)$/D',$event,$m))return t($m[1]==='login'?'auth.event_clear_login':'auth.event_clear_all',['n'=>$m[2]]);
    if(str_starts_with($event,'delete_user:'))return t('auth.event_delete_user',['username'=>substr($event,12)]);
    $key=['initial_setup'=>'auth.event_initial_setup','login'=>'auth.event_login','change_password'=>'auth.event_change_password','add_user'=>'auth.event_add_user','update_user'=>'auth.event_update_user','reset_user_password'=>'auth.event_reset_user_password','regenerate_recovery_key'=>'auth.event_regenerate_recovery_key','logout_others'=>'auth.event_logout_others','update_own_mechanic'=>'auth.event_update_own_mechanic','update_own_language'=>'auth.event_update_own_language','emergency_password_reset'=>'auth.event_emergency_password_reset'][$event]??null;
    return $key!==null?t($key):$event;
}
function authActionRoles(): array {
    $writer=['save_car','add_service','update_service','add_part','update_part','adjust_part_stock','bulk_update_inventory','save_item_settings','add_customer','update_customer','create_customer_from_car','set_car_customer','update_car_customer_history','update_km','add_old_odometer','create_invoice','update_invoice_status','update_invoice','send_invoice_email'];
    $admin=['save_app_settings','add_catalog_item','update_catalog_item','add_mechanic','update_mechanic','delete_mechanic','accept_data_issue','accept_data_issues','restore_data_issue','backfill_service_customer_snapshots','restore_db','delete_car','delete_service','delete_part','delete_customer','delete_photo','delete_odometer_reading','delete_invoice','add_user','update_user','reset_user_password','delete_user','clear_auth_audit','clear_inventory_history','save_mail_settings','send_test_mail','snooze_backup_reminder'];
    $map=[];foreach($writer as $a)$map[$a]=['editor','admin'];foreach($admin as $a)$map[$a]=['admin'];
    $map['change_password']=['viewer','editor','admin'];$map['logout_others']=['viewer','editor','admin'];$map['update_own_mechanic']=['viewer','editor','admin'];$map['update_own_language']=['viewer','editor','admin'];$map['logout']=['viewer','editor','admin'];return $map;
}
function canAction(string $action): bool { return in_array($GLOBALS['currentUser']['role']??'',authActionRoles()[$action]??[],true); }
function authDeny(): never { http_response_code(403);header('Content-Type: text/plain; charset=utf-8');exit(t('auth.err_forbidden')); }
function authRequire(string $action): void { if(!canAction($action))authDeny(); }
function authValidatePassword(string $password,string $confirmation): void {
    if($password!==$confirmation)throw new RuntimeException(t('auth.err_password_mismatch'));
    if(mb_strlen($password)<12||strlen($password)>72||str_contains($password,"\0"))throw new RuntimeException(t('auth.err_password_length'));
}
function authPasswordHash(string $password): string { return password_hash($password,PASSWORD_BCRYPT,['cost'=>12]); }
function authUsername(string $value): string {
    $v=strtolower(trim($value));if(!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/D',$v))throw new RuntimeException(t('auth.err_username_format'));return $v;
}
function authDisplayName(string $value): string {
    $v=trim($value);if($v===''||mb_strlen($v)>100)throw new RuntimeException(t('auth.err_name_required'));return $v;
}
function authRole(string $value): string {
    if(!in_array($value,['viewer','editor','admin'],true))throw new RuntimeException(t('auth.err_invalid_role'));return $value;
}
function authThrottleKeys(string $username): array {
    return [['account',hash('sha256',strtolower(trim($username))),10],['ip',hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown')),30]];
}
function authLocked(PDO $db,string $username): bool {
    $st=$db->prepare('SELECT locked_until FROM auth_attempts WHERE scope=? AND attempt_key=?');$locked=false;
    foreach(authThrottleKeys($username) as [$scope,$key]){$st->execute([$scope,$key]);if((int)$st->fetchColumn()>time())$locked=true;}return $locked;
}
function authFailure(PDO $db,string $username): void {
    $now=time();$st=$db->prepare('SELECT * FROM auth_attempts WHERE scope=? AND attempt_key=?');
    $up=$db->prepare('INSERT INTO auth_attempts(scope,attempt_key,window_started,failures,locked_until) VALUES(?,?,?,?,?) ON CONFLICT(scope,attempt_key) DO UPDATE SET window_started=excluded.window_started,failures=excluded.failures,locked_until=excluded.locked_until');
    foreach(authThrottleKeys($username) as [$scope,$key,$limit]){
        $st->execute([$scope,$key]);$row=$st->fetch();$restart=!$row||($now-(int)$row['window_started']>=600&&(int)$row['locked_until']<=$now);
        $start=$restart?$now:(int)$row['window_started'];$fail=$restart?1:(int)$row['failures']+1;$lock=$fail>=$limit?$now+900:0;
        $up->execute([$scope,$key,$start,$fail,$lock]);
    }
    $db->prepare('DELETE FROM auth_attempts WHERE window_started<? AND locked_until<?')->execute([$now-86400,$now]);
}
function authLoginSession(PDO $db,array $user): void {
    session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(24));unset($_SESSION['autotalli_auth']);
    $_SESSION['auth']=['id'=>(int)$user['id'],'version'=>(int)$user['session_version'],'install'=>authValue($db,'auth_install_id'),'started'=>time(),'last'=>time()];
}
function authForget(): void { unset($_SESSION['auth'],$_SESSION['autotalli_auth']);$GLOBALS['currentUser']=null; }
function authCurrent(PDO $db): ?array {
    $s=$_SESSION['auth']??null;if(!is_array($s))return null;$u=authUser($db,(int)($s['id']??0));$now=time();
    if(!$u||!(int)$u['active']||(int)$u['session_version']!==(int)($s['version']??0)||!hash_equals(authValue($db,'auth_install_id'),(string)($s['install']??''))||$now-(int)($s['last']??0)>AUTH_IDLE_SECONDS||$now-(int)($s['started']??0)>AUTH_MAX_SECONDS){authForget();return null;}
    $_SESSION['auth']['last']=$now;return $u;
}
function authPage(string $title,string $error,string $fields,string $button,string $action): never {
    $name=initialAppName();header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html><html lang="<?=h(i18nLang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="data:image/svg+xml,<?=h(rawurlencode(faviconDefaultSvg()))?>"><title><?=h($name)?> – <?=h($title)?></title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0a0f15;color:#edf3f8;font-family:system-ui,sans-serif}.box{width:min(94vw,540px);padding:30px;border:1px solid #293542;border-radius:22px;background:#121922}h1{font-size:28px}p,.tiny{color:#aab6c3}label{display:block;margin:14px 0 5px;font-size:14px}input,select{width:100%;padding:12px;border-radius:9px;border:1px solid #354352;background:#0c1219;color:#fff;font:inherit}button{width:100%;margin-top:18px;padding:14px;border:0;border-radius:10px;background:#ffb33c;font:inherit;font-weight:800;cursor:pointer}.err{padding:12px;background:#ff596422;color:#ff9299;border-radius:10px}.tiny{font-size:12px}code{overflow-wrap:anywhere}</style></head><body><form class="box" method="post"><h1>🔧 <?=h($name)?></h1><h2><?=h($title)?></h2><?php if($error!==''):?><div class="err" role="alert"><?=h($error)?></div><?php endif;?><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="<?=h($action)?>"><?=$fields?><button><?=h($button)?></button><p class="tiny"><?=t('auth.version',['version'=>h(APP_VERSION)])?></p></form><?php if(count(i18nAvailableLanguages())>1):?><p class="tiny" style="text-align:center"><?php foreach(i18nLanguageOptions() as $lc=>$ln):?><a href="?lang=<?=h($lc)?>" style="color:#ffb33c;margin:0 6px;<?=$lc===i18nLang()?'font-weight:800':''?>"><?=h($ln)?></a><?php endforeach;?></p><?php endif;?></body></html><?php exit;
}
function authBootstrap(PDO $db): void {
    $hasAuth=(int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='auth_users'")->fetchColumn()>0;
    if(!$hasAuth){
        $db->beginTransaction();
        try{
            authSchema($db);
            appSet($db,'auth_install_id',bin2hex(random_bytes(24)));
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    $GLOBALS['currentUser']=authCurrent($db);
    $count=(int)$db->query('SELECT COUNT(*) FROM auth_users')->fetchColumn();
    /* Kieli ennen kirjautumista: järjestelmän oletus; kirjautuneella käyttäjällä oma kieli (jos valittu). Asennus- ja kirjautumissivulla kielen voi vaihtaa osoitteella ?lang=xx. */
    $sysLang=i18nValidLanguage(authValue($db,'language'))?:I18N_DEFAULT_LANG;
    if(!$GLOBALS['currentUser']){
        if(isset($_GET['lang'])&&i18nValidLanguage((string)$_GET['lang'])!=='')$_SESSION['pre_lang']=i18nValidLanguage((string)$_GET['lang']);
        $chosen=i18nValidLanguage((string)($_POST['lang']??''))?:i18nValidLanguage((string)($_SESSION['pre_lang']??''));
        i18nLang($chosen?:($count===0?i18nFromBrowser():$sysLang));
    }else i18nLang(authValue($db,'user_lang_'.(int)$GLOBALS['currentUser']['id'])?:$sysLang);
    $action=(string)($_POST['action']??'');
    $isPost=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    if($count===0){
        if(authValue($db,'auth_initialized')==='1'){
            http_response_code(503);
            exit(t('auth.err_no_admins'));
        }
        $error='';
        if($isPost){
            if($action!=='initial_setup')authDeny();
            requireCsrf();
            try{
                if(authLocked($db,'@setup')){http_response_code(429);throw new RuntimeException(t('auth.err_too_many_attempts'));}
                $username=authUsername(post('username'));
                $name=authDisplayName(post('display_name'));
                $pw=(string)($_POST['password']??'');
                authValidatePassword($pw,(string)($_POST['password_confirm']??''));
                $shop=authDisplayName(post('shop_name'));
                $db->beginTransaction();
                try{
                    $db->prepare('INSERT INTO auth_users(username,display_name,password_hash,role,created_at,updated_at) VALUES(?,?,?,\'admin\',?,?)')->execute([$username,$name,authPasswordHash($pw),date('c'),date('c')]);
                    $id=(int)$db->lastInsertId();
                    appSet($db,'auth_initialized','1');
                    appSet($db,'shop_name',$shop);appSet($db,'language',i18nLang());
                    authAudit($db,'initial_setup',$id);
                    $db->prepare('UPDATE auth_users SET last_login_at=? WHERE id=?')->execute([date('c'),$id]);
                    $db->commit();
                }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                $user=authUser($db,$id);
                authLoginSession($db,$user);
                flash(t('auth.flash_admin_created'));
                redirect('?view=settings#kayttajat');
            }catch(Throwable $e){$error=$e->getMessage();}
        }
        $fields='<p>'.t('auth.setup_intro').'</p><label>'.t('auth.setup_language').'</label><select name="lang" onchange="location.search=\'?lang=\'+this.value">'.implode('',array_map(fn($c,$n)=>'<option value="'.h($c).'"'.($c===i18nLang()?' selected':'').'>'.h($n).'</option>',array_keys(i18nLanguageOptions()),i18nLanguageOptions())).'</select><div class="tiny">'.t('auth.setup_language_help').'</div><label>'.t('auth.setup_shop_name').'</label><input name="shop_name" required maxlength="100"><label>'.t('auth.setup_display_name').'</label><input name="display_name" required maxlength="100"><label>'.t('auth.login_username').'</label><input name="username" required minlength="3" maxlength="64" autocomplete="username"><label>'.t('auth.setup_password').'</label><input type="password" name="password" required minlength="12" autocomplete="new-password"><label>'.t('auth.setup_password_again').'</label><input type="password" name="password_confirm" required autocomplete="new-password">';
        authPage(t('auth.setup_title'),$error,$fields,t('auth.setup_button'),'initial_setup');
    }
    if(!$GLOBALS['currentUser']){
        $error='';
        if($isPost){
            if($action!=='login')authDeny();
            requireCsrf();
            $username=strtolower(post('username'));
            if(authLocked($db,$username))$error=t('auth.err_too_many_attempts');
            else{
                $st=$db->prepare('SELECT * FROM auth_users WHERE username=?');$st->execute([$username]);$user=$st->fetch();$pw=(string)($_POST['password']??'');
                $hash=$user['password_hash']??AUTH_DUMMY_HASH;$verified=strlen($pw)<=72&&!str_contains($pw,"\0")&&password_verify($pw,$hash);
                if($user&&(int)$user['active']===1&&$verified){
                    if(password_needs_rehash($hash,PASSWORD_BCRYPT,['cost'=>12]))$db->prepare('UPDATE auth_users SET password_hash=? WHERE id=?')->execute([authPasswordHash($pw),$user['id']]);
                    $db->prepare('UPDATE auth_users SET last_login_at=? WHERE id=?')->execute([date('c'),$user['id']]);
                    $db->prepare('DELETE FROM auth_attempts WHERE scope=\'account\' AND attempt_key=?')->execute([hash('sha256',$username)]);
                    $GLOBALS['currentUser']=$user;authAudit($db,'login',(int)$user['id']);authLoginSession($db,$user);redirect((int)$user['force_password_change']?'?view=account':'?');
                }
                authFailure($db,$username);$error=t('auth.err_login_failed');
            }
        }
        authPage(t('auth.login_title'),$error,'<label>'.t('auth.login_username').'</label><input name="username" required autofocus autocomplete="username"><label>'.t('auth.login_password').'</label><input type="password" name="password" required autocomplete="current-password">',t('auth.login_button'),'login');
    }
    if($isPost&&$action==='logout'){requireCsrf();authForget();$_SESSION=[];session_regenerate_id(true);redirect('?');}
    if((int)$GLOBALS['currentUser']['force_password_change']&&!(($isPost&&$action==='change_password')||(!$isPost&&($_GET['view']??'')==='account'&&count($_GET)===1)))redirect('?view=account');
    if(isset($_GET['backup'])||($_GET['view']??'')==='settings')authRequire('save_app_settings');
}
/** Käytössä oleva kieli: käyttäjän oma valinta, muuten järjestelmän oletus asetuksista. */
function authUserLanguage(array $app): string {
    $u=$GLOBALS['currentUser']??null;
    if($u){$own=i18nValidLanguage((string)($app['user_lang_'.(int)$u['id']]??''));if($own!=='')return $own;}
    return i18nValidLanguage((string)($app['language']??''))?:I18N_DEFAULT_LANG;
}
function authHandlePost(PDO $db,string $action): void {
    if(!in_array($action,['add_user','update_user','reset_user_password','delete_user','change_password','logout_others','update_own_mechanic','update_own_language','clear_auth_audit'],true))return;
    authRequire($action);$u=$GLOBALS['currentUser'];$destination=in_array($action,['change_password','logout_others','update_own_mechanic','update_own_language'],true)?'?view=account':($action==='clear_auth_audit'?'?view=settings&audit_page=1#kayttajaloki':'?view=settings#kayttajat');
    try{
        if($action==='change_password'||$action==='logout_others'){
            if(authLocked($db,'@password-'.$u['id']))throw new RuntimeException(t('auth.err_too_many_attempts'));
            if(!password_verify((string)($_POST['current_password']??''),$u['password_hash'])){
                if(!authLocked($db,'@password-'.$u['id']))authFailure($db,'@password-'.$u['id']);throw new RuntimeException(t('auth.err_current_password_wrong'));
            }
            if(authLocked($db,'@password-'.$u['id']))throw new RuntimeException(t('auth.err_too_many_attempts'));
        }
        if($action==='change_password'){
            $pw=(string)($_POST['new_password']??'');authValidatePassword($pw,(string)($_POST['password_confirm']??''));
            if(password_verify($pw,$u['password_hash']))throw new RuntimeException(t('auth.err_password_same'));
            $db->prepare('UPDATE auth_users SET password_hash=?,session_version=session_version+1,force_password_change=0,updated_at=? WHERE id=?')->execute([authPasswordHash($pw),date('c'),$u['id']]);authAudit($db,'change_password',(int)$u['id']);authLoginSession($db,authUser($db,(int)$u['id']));flash(t('auth.flash_password_changed'));
        }elseif($action==='logout_others'){
            $db->prepare('UPDATE auth_users SET session_version=session_version+1,updated_at=? WHERE id=?')->execute([date('c'),$u['id']]);authAudit($db,'logout_others',(int)$u['id']);authLoginSession($db,authUser($db,(int)$u['id']));flash(t('auth.flash_sessions_ended'));
        }elseif($action==='update_own_mechanic'){
            $mid=authMechanicId($db,intpost('mechanic_id'));
            $db->prepare('UPDATE auth_users SET mechanic_id=?,updated_at=? WHERE id=?')->execute([$mid,date('c'),$u['id']]);authAudit($db,'update_own_mechanic',(int)$u['id']);flash(t('auth.flash_mechanic_saved'));
        }elseif($action==='update_own_language'){
            $lang=i18nValidLanguage(post('own_language'));
            if($lang==='')$db->prepare('DELETE FROM app_settings WHERE setting_key=?')->execute(['user_lang_'.(int)$u['id']]);else appSet($db,'user_lang_'.(int)$u['id'],$lang);
            authAudit($db,'update_own_language',(int)$u['id']);i18nLang($lang?:(i18nValidLanguage(authValue($db,'language'))?:I18N_DEFAULT_LANG));flash(t('auth.flash_language_saved'));
        }elseif($action==='clear_auth_audit'){
            $scope=post('audit_scope');$upTo=intpost('audit_up_to',-1);
            if(!in_array($scope,['login','all'],true)||$upTo<0)throw new RuntimeException(t('auth.err_audit_clear_invalid'));
            if(post('audit_confirm')!=='1')throw new RuntimeException(t('auth.err_audit_clear_unconfirmed'));
            $db->beginTransaction();
            // Uudemmat tapahtumat säilyvät, jos joku kirjautui lomakkeen avaamisen jälkeen.
            $sql='DELETE FROM auth_audit WHERE id<=?'.($scope==='login'?" AND event='login'":'');
            $st=$db->prepare($sql);$st->execute([$upTo]);$removed=$st->rowCount();
            authAudit($db,'clear_auth_audit:'.$scope.':'.$removed);
            $db->commit();
            flash(t('auth.flash_audit_cleared',['n'=>$removed]));
        }elseif($action==='add_user'){
            $username=authUsername(post('username'));$name=authDisplayName(post('display_name'));$role=authRole(post('role'));$pw=(string)($_POST['new_password']??'');authValidatePassword($pw,(string)($_POST['password_confirm']??''));$mechanic=authMechanicId($db,intpost('mechanic_id'));
            $db->prepare('INSERT INTO auth_users(username,display_name,password_hash,role,active,force_password_change,mechanic_id,created_at,updated_at) VALUES(?,?,?,?,1,1,?,?,?)')->execute([$username,$name,authPasswordHash($pw),$role,$mechanic,date('c'),date('c')]);authAudit($db,'add_user',(int)$db->lastInsertId());flash(t('auth.flash_user_created'));
        }elseif($action==='update_user'){
            $id=intpost('user_id');$target=authUser($db,$id);if(!$target)throw new RuntimeException(t('auth.err_user_not_found'));$name=authDisplayName(post('display_name'));$role=authRole(post('role'));$active=isset($_POST['user_active'])?1:0;$mechanic=authMechanicId($db,intpost('mechanic_id'));
            $db->beginTransaction();
            if($target['role']==='admin'&&(int)$target['active']===1&&($role!=='admin'||!$active)&&(int)$db->query("SELECT COUNT(*) FROM auth_users WHERE role='admin' AND active=1")->fetchColumn()<=1)throw new RuntimeException(t('auth.err_last_admin_change'));
            $changed=$role!==$target['role']||$active!==(int)$target['active']||$mechanic!==(int)$target['mechanic_id'];
            $db->prepare('UPDATE auth_users SET display_name=?,role=?,active=?,mechanic_id=?,session_version=session_version+?,updated_at=? WHERE id=?')->execute([$name,$role,$active,$mechanic,$changed?1:0,date('c'),$id]);authAudit($db,'update_user',$id);$db->commit();flash(t('auth.flash_user_updated'));
        }elseif($action==='delete_user'){
            $id=intpost('user_id');$db->beginTransaction();$target=authUser($db,$id);
            if(!$target)throw new RuntimeException(t('auth.err_user_not_found_deleted'));
            if(post('delete_confirm')!=='1'||post('confirm_username')!==$target['username'])throw new RuntimeException(t('auth.err_delete_unconfirmed'));
            if($target['role']==='admin'&&(int)$target['active']===1&&(int)$db->query("SELECT COUNT(*) FROM auth_users WHERE role='admin' AND active=1")->fetchColumn()<=1)throw new RuntimeException(t('auth.err_last_admin_delete'));
            authAudit($db,'delete_user:'.$target['username'],$id);
            $db->prepare('DELETE FROM auth_users WHERE id=?')->execute([$id]);$db->prepare('DELETE FROM app_settings WHERE setting_key=?')->execute(['user_lang_'.$id]);$db->commit();
            flash(t('auth.flash_user_deleted',['username'=>$target['username']]));
            if($id===(int)$u['id']){authForget();redirect('?');}
        }elseif($action==='reset_user_password'){
            $id=intpost('user_id');$target=authUser($db,$id);if(!$target)throw new RuntimeException(t('auth.err_user_not_found'));if($id===(int)$u['id'])throw new RuntimeException(t('auth.err_own_password_via_account'));
            $pw=(string)($_POST['new_password']??'');authValidatePassword($pw,(string)($_POST['password_confirm']??''));$db->prepare('UPDATE auth_users SET password_hash=?,session_version=session_version+1,force_password_change=1,updated_at=? WHERE id=?')->execute([authPasswordHash($pw),date('c'),$id]);authAudit($db,'reset_user_password',$id);flash(t('auth.flash_password_reset'));
        }
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();flash($e instanceof PDOException?($action==='clear_auth_audit'?t('auth.err_audit_clear_failed'):t('auth.err_user_save_failed')):$e->getMessage(),'error');}
    redirect($destination);
}
function authMechanicId(PDO $db,int $id): int {
    if(!$id)return 0;$st=$db->prepare('SELECT id FROM mechanics WHERE id=? AND active=1');$st->execute([$id]);if(!$st->fetchColumn())throw new RuntimeException(t('auth.err_mechanic_inactive'));return $id;
}
/** Vain näkymien viimeistely. Varsinainen suoja on authRequire() ennen jokaista POSTia. */
function authFilterHtml(string $html): string {
    return (string)preg_replace_callback('~<form\b[^>]*>.*?</form>~is',static function(array $m): string {
        $form=$m[0];if(!preg_match('~^<form\b[^>]*\bmethod=["\']?post\b~i',$form))return $form;
        if(!preg_match('~<input\b[^>]*\bname=["\']action["\'][^>]*\bvalue=["\']([^"\']+)["\']~i',$form,$a))return '';
        if(canAction($a[1]))return $form;
        if(in_array($a[1],['save_car','update_service','update_part','update_customer','save_item_settings','update_car_customer_history','update_invoice_status'],true)){
            $body=preg_replace('~</?form\b[^>]*>|<button\b[^>]*>.*?</button>~is','',$form);
            return '<div class="readonly-form"><p class="tiny">'.t('auth.readonly_notice').'</p><fieldset disabled style="border:0;padding:0;margin:0;min-width:0">'.$body.'</fieldset></div>';
        }
        return '';
    },$html);
}

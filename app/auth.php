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
function roleLabel(string $role): string { return ['viewer'=>'Katselija','editor'=>'Tallentaja','admin'=>'Ylläpitäjä'][$role]??'Ei oikeutta'; }
function authEventLabel(string $event): string {
    if(preg_match('/^clear_auth_audit:(login|all):([0-9]+)$/D',$event,$m))return 'Lokista poistettu '.$m[2].' tapahtumaa · '.($m[1]==='login'?'kirjautumiset':'koko loki');
    if(str_starts_with($event,'delete_user:'))return 'Käyttäjän poisto · '.substr($event,12);
    return ['initial_setup'=>'Ylläpitäjän käyttöönotto','login'=>'Kirjautuminen','change_password'=>'Oman salasanan vaihto','add_user'=>'Käyttäjän luonti','update_user'=>'Käyttäjätietojen / oikeuksien muutos','reset_user_password'=>'Väliaikaisen salasanan asetus','regenerate_recovery_key'=>'Palautuskoodin uusiminen','logout_others'=>'Muiden istuntojen päättäminen','update_own_mechanic'=>'Oman oletusmekaanikon muutos','emergency_password_reset'=>'Salasanan hätäpalautus'][$event]??$event;
}
function authActionRoles(): array {
    $writer=['save_car','add_service','update_service','add_part','update_part','adjust_part_stock','bulk_update_inventory','save_item_settings','add_customer','update_customer','create_customer_from_car','set_car_customer','update_car_customer_history','update_km','add_old_odometer','create_invoice','update_invoice_status'];
    $admin=['save_app_settings','add_catalog_item','update_catalog_item','add_mechanic','update_mechanic','delete_mechanic','accept_data_issue','accept_data_issues','restore_data_issue','backfill_service_customer_snapshots','restore_db','delete_car','delete_service','delete_part','delete_customer','delete_photo','delete_odometer_reading','delete_invoice','add_user','update_user','reset_user_password','delete_user','clear_auth_audit','clear_inventory_history'];
    $map=[];foreach($writer as $a)$map[$a]=['editor','admin'];foreach($admin as $a)$map[$a]=['admin'];
    $map['change_password']=['viewer','editor','admin'];$map['logout_others']=['viewer','editor','admin'];$map['update_own_mechanic']=['viewer','editor','admin'];$map['logout']=['viewer','editor','admin'];return $map;
}
function canAction(string $action): bool { return in_array($GLOBALS['currentUser']['role']??'',authActionRoles()[$action]??[],true); }
function authDeny(): never { http_response_code(403);header('Content-Type: text/plain; charset=utf-8');exit('Käyttöoikeutesi eivät riitä tähän toimintoon.'); }
function authRequire(string $action): void { if(!canAction($action))authDeny(); }
function authValidatePassword(string $password,string $confirmation): void {
    if($password!==$confirmation)throw new RuntimeException('Uudet salasanat eivät täsmää.');
    if(mb_strlen($password)<12||strlen($password)>72||str_contains($password,"\0"))throw new RuntimeException('Salasanan tulee olla vähintään 12 merkkiä ja enintään 72 tavua. Voit käyttää usean sanan lausetta.');
}
function authPasswordHash(string $password): string { return password_hash($password,PASSWORD_BCRYPT,['cost'=>12]); }
function authUsername(string $value): string {
    $v=strtolower(trim($value));if(!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/D',$v))throw new RuntimeException('Käyttäjätunnus: 3–64 merkkiä, pienet a–z, numerot, piste, alaviiva tai yhdysmerkki.');return $v;
}
function authDisplayName(string $value): string {
    $v=trim($value);if($v===''||mb_strlen($v)>100)throw new RuntimeException('Anna nimi (enintään 100 merkkiä).');return $v;
}
function authRole(string $value): string {
    if(!in_array($value,['viewer','editor','admin'],true))throw new RuntimeException('Virheellinen käyttäjärooli.');return $value;
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
    ?><!doctype html><html lang="fi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($name)?> – <?=h($title)?></title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0a0f15;color:#edf3f8;font-family:system-ui,sans-serif}.box{width:min(94vw,540px);padding:30px;border:1px solid #293542;border-radius:22px;background:#121922}h1{font-size:28px}p,.tiny{color:#aab6c3}label{display:block;margin:14px 0 5px;font-size:14px}input{width:100%;padding:12px;border-radius:9px;border:1px solid #354352;background:#0c1219;color:#fff;font:inherit}button{width:100%;margin-top:18px;padding:14px;border:0;border-radius:10px;background:#ffb33c;font:inherit;font-weight:800;cursor:pointer}.err{padding:12px;background:#ff596422;color:#ff9299;border-radius:10px}.tiny{font-size:12px}code{overflow-wrap:anywhere}</style></head><body><form class="box" method="post"><h1>🔧 <?=h($name)?></h1><h2><?=h($title)?></h2><?php if($error!==''):?><div class="err" role="alert"><?=h($error)?></div><?php endif;?><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="<?=h($action)?>"><?=$fields?><button><?=h($button)?></button><p class="tiny">Versio <?=h(APP_VERSION)?></p></form></body></html><?php exit;
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
    $action=(string)($_POST['action']??'');
    $isPost=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    if($count===0){
        if(authValue($db,'auth_initialized')==='1'){
            http_response_code(503);
            exit('Ylläpitäjätunnukset puuttuvat. Palauta tietokanta palvelimen turvakopiosta.');
        }
        $error='';
        if($isPost){
            if($action!=='initial_setup')authDeny();
            requireCsrf();
            try{
                if(authLocked($db,'@setup')){http_response_code(429);throw new RuntimeException('Liian monta epäonnistunutta yritystä. Odota 15 minuuttia.');}
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
                    appSet($db,'shop_name',$shop);
                    authAudit($db,'initial_setup',$id);
                    $db->prepare('UPDATE auth_users SET last_login_at=? WHERE id=?')->execute([date('c'),$id]);
                    $db->commit();
                }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
                $user=authUser($db,$id);
                authLoginSession($db,$user);
                flash('Ylläpitäjätunnus luotu. Luo vielä toinen ylläpitäjä (Asetukset → Käyttäjät), jos salasana unohtuu.');
                redirect('?view=settings#kayttajat');
            }catch(Throwable $e){$error=$e->getMessage();}
        }
        $fields='<p>Luo ensimmäinen ylläpitäjätunnus. Erillistä käyttöönottokoodia ei tarvita.</p><label>Yrityksen / korjaamon nimi</label><input name="shop_name" required maxlength="100"><label>Oma nimi</label><input name="display_name" required maxlength="100"><label>Käyttäjätunnus</label><input name="username" required minlength="3" maxlength="64" autocomplete="username"><label>Uusi salasana (vähintään 12 merkkiä)</label><input type="password" name="password" required minlength="12" autocomplete="new-password"><label>Uusi salasana uudelleen</label><input type="password" name="password_confirm" required autocomplete="new-password">';
        authPage('Ensimmäinen käyttöönotto',$error,$fields,'Luo ylläpitäjätunnus','initial_setup');
    }
    if(!$GLOBALS['currentUser']){
        $error='';
        if($isPost){
            if($action!=='login')authDeny();
            requireCsrf();
            $username=strtolower(post('username'));
            if(authLocked($db,$username))$error='Liian monta epäonnistunutta yritystä. Odota 15 minuuttia.';
            else{
                $st=$db->prepare('SELECT * FROM auth_users WHERE username=?');$st->execute([$username]);$user=$st->fetch();$pw=(string)($_POST['password']??'');
                $hash=$user['password_hash']??AUTH_DUMMY_HASH;$verified=strlen($pw)<=72&&!str_contains($pw,"\0")&&password_verify($pw,$hash);
                if($user&&(int)$user['active']===1&&$verified){
                    if(password_needs_rehash($hash,PASSWORD_BCRYPT,['cost'=>12]))$db->prepare('UPDATE auth_users SET password_hash=? WHERE id=?')->execute([authPasswordHash($pw),$user['id']]);
                    $db->prepare('UPDATE auth_users SET last_login_at=? WHERE id=?')->execute([date('c'),$user['id']]);
                    $db->prepare('DELETE FROM auth_attempts WHERE scope=\'account\' AND attempt_key=?')->execute([hash('sha256',$username)]);
                    $GLOBALS['currentUser']=$user;authAudit($db,'login',(int)$user['id']);authLoginSession($db,$user);redirect((int)$user['force_password_change']?'?view=account':'?');
                }
                authFailure($db,$username);$error='Virheellinen käyttäjätunnus tai salasana.';
            }
        }
        authPage('Kirjautuminen',$error,'<label>Käyttäjätunnus</label><input name="username" required autofocus autocomplete="username"><label>Salasana</label><input type="password" name="password" required autocomplete="current-password">','Kirjaudu','login');
    }
    if($isPost&&$action==='logout'){requireCsrf();authForget();$_SESSION=[];session_regenerate_id(true);redirect('?');}
    if((int)$GLOBALS['currentUser']['force_password_change']&&!(($isPost&&$action==='change_password')||(!$isPost&&($_GET['view']??'')==='account'&&count($_GET)===1)))redirect('?view=account');
    if(isset($_GET['backup'])||($_GET['view']??'')==='settings')authRequire('save_app_settings');
}
function authHandlePost(PDO $db,string $action): void {
    if(!in_array($action,['add_user','update_user','reset_user_password','delete_user','change_password','logout_others','update_own_mechanic','clear_auth_audit'],true))return;
    authRequire($action);$u=$GLOBALS['currentUser'];$destination=in_array($action,['change_password','logout_others','update_own_mechanic'],true)?'?view=account':($action==='clear_auth_audit'?'?view=settings&audit_page=1#kayttajaloki':'?view=settings#kayttajat');
    try{
        if($action==='change_password'||$action==='logout_others'){
            if(authLocked($db,'@password-'.$u['id']))throw new RuntimeException('Liian monta epäonnistunutta yritystä. Odota 15 minuuttia.');
            if(!password_verify((string)($_POST['current_password']??''),$u['password_hash'])){
                if(!authLocked($db,'@password-'.$u['id']))authFailure($db,'@password-'.$u['id']);throw new RuntimeException('Nykyinen salasana on väärä.');
            }
            if(authLocked($db,'@password-'.$u['id']))throw new RuntimeException('Liian monta epäonnistunutta yritystä. Odota 15 minuuttia.');
        }
        if($action==='change_password'){
            $pw=(string)($_POST['new_password']??'');authValidatePassword($pw,(string)($_POST['password_confirm']??''));
            if(password_verify($pw,$u['password_hash']))throw new RuntimeException('Valitse aiemmasta poikkeava salasana.');
            $db->prepare('UPDATE auth_users SET password_hash=?,session_version=session_version+1,force_password_change=0,updated_at=? WHERE id=?')->execute([authPasswordHash($pw),date('c'),$u['id']]);authAudit($db,'change_password',(int)$u['id']);authLoginSession($db,authUser($db,(int)$u['id']));flash('Salasana vaihdettiin. Muut istuntosi päättyivät.');
        }elseif($action==='logout_others'){
            $db->prepare('UPDATE auth_users SET session_version=session_version+1,updated_at=? WHERE id=?')->execute([date('c'),$u['id']]);authAudit($db,'logout_others',(int)$u['id']);authLoginSession($db,authUser($db,(int)$u['id']));flash('Kaikki muut kirjautumisistuntosi päättyivät. Tämä istunto jatkuu.');
        }elseif($action==='update_own_mechanic'){
            $mid=authMechanicId($db,intpost('mechanic_id'));
            $db->prepare('UPDATE auth_users SET mechanic_id=?,updated_at=? WHERE id=?')->execute([$mid,date('c'),$u['id']]);authAudit($db,'update_own_mechanic',(int)$u['id']);flash('Oletusmekaanikko tallennettiin.');
        }elseif($action==='clear_auth_audit'){
            $scope=post('audit_scope');$upTo=intpost('audit_up_to',-1);
            if(!in_array($scope,['login','all'],true)||$upTo<0)throw new RuntimeException('Virheellinen lokin tyhjennys. Avaa tapahtumaloki uudelleen.');
            if(post('audit_confirm')!=='1')throw new RuntimeException('Vahvista lokitietojen poistaminen rastittamalla varmistus.');
            $db->beginTransaction();
            // Uudemmat tapahtumat säilyvät, jos joku kirjautui lomakkeen avaamisen jälkeen.
            $sql='DELETE FROM auth_audit WHERE id<=?'.($scope==='login'?" AND event='login'":'');
            $st=$db->prepare($sql);$st->execute([$upTo]);$removed=$st->rowCount();
            authAudit($db,'clear_auth_audit:'.$scope.':'.$removed);
            $db->commit();
            flash('Lokista poistettiin '.$removed.' tapahtumaa. Tyhjennyksestä jäi merkintä. Käyttäjät, salasanat ja huoltotiedot säilyivät.');
        }elseif($action==='add_user'){
            $username=authUsername(post('username'));$name=authDisplayName(post('display_name'));$role=authRole(post('role'));$pw=(string)($_POST['new_password']??'');authValidatePassword($pw,(string)($_POST['password_confirm']??''));$mechanic=authMechanicId($db,intpost('mechanic_id'));
            $db->prepare('INSERT INTO auth_users(username,display_name,password_hash,role,active,force_password_change,mechanic_id,created_at,updated_at) VALUES(?,?,?,?,1,1,?,?,?)')->execute([$username,$name,authPasswordHash($pw),$role,$mechanic,date('c'),date('c')]);authAudit($db,'add_user',(int)$db->lastInsertId());flash('Käyttäjä luotiin. Hän vaihtaa salasanan ensimmäisellä kirjautumisella.');
        }elseif($action==='update_user'){
            $id=intpost('user_id');$target=authUser($db,$id);if(!$target)throw new RuntimeException('Käyttäjää ei löytynyt.');$name=authDisplayName(post('display_name'));$role=authRole(post('role'));$active=isset($_POST['user_active'])?1:0;$mechanic=authMechanicId($db,intpost('mechanic_id'));
            $db->beginTransaction();
            if($target['role']==='admin'&&(int)$target['active']===1&&($role!=='admin'||!$active)&&(int)$db->query("SELECT COUNT(*) FROM auth_users WHERE role='admin' AND active=1")->fetchColumn()<=1)throw new RuntimeException('Viimeistä aktiivista ylläpitäjää ei voi poistaa käytöstä tai muuttaa toiseksi rooliksi.');
            $changed=$role!==$target['role']||$active!==(int)$target['active']||$mechanic!==(int)$target['mechanic_id'];
            $db->prepare('UPDATE auth_users SET display_name=?,role=?,active=?,mechanic_id=?,session_version=session_version+?,updated_at=? WHERE id=?')->execute([$name,$role,$active,$mechanic,$changed?1:0,date('c'),$id]);authAudit($db,'update_user',$id);$db->commit();flash('Käyttäjän tiedot tallennettiin. Oikeuksien muutos päättää hänen aiemmat istuntonsa.');
        }elseif($action==='delete_user'){
            $id=intpost('user_id');$db->beginTransaction();$target=authUser($db,$id);
            if(!$target)throw new RuntimeException('Käyttäjää ei löytynyt. Se on ehkä jo poistettu.');
            if(post('delete_confirm')!=='1'||post('confirm_username')!==$target['username'])throw new RuntimeException('Käyttäjän poistoa ei vahvistettu. Avaa käyttäjälista uudelleen.');
            if($target['role']==='admin'&&(int)$target['active']===1&&(int)$db->query("SELECT COUNT(*) FROM auth_users WHERE role='admin' AND active=1")->fetchColumn()<=1)throw new RuntimeException('Viimeistä aktiivista ylläpitäjää ei voi poistaa.');
            authAudit($db,'delete_user:'.$target['username'],$id);
            $db->prepare('DELETE FROM auth_users WHERE id=?')->execute([$id]);$db->commit();
            flash('Käyttäjä '.$target['username'].' poistettiin pysyvästi. Huoltotiedot, mekaanikot ja tapahtumaloki säilyivät.');
            if($id===(int)$u['id']){authForget();redirect('?');}
        }elseif($action==='reset_user_password'){
            $id=intpost('user_id');$target=authUser($db,$id);if(!$target)throw new RuntimeException('Käyttäjää ei löytynyt.');if($id===(int)$u['id'])throw new RuntimeException('Vaihda oma salasanasi Omat tiedot -sivulta.');
            $pw=(string)($_POST['new_password']??'');authValidatePassword($pw,(string)($_POST['password_confirm']??''));$db->prepare('UPDATE auth_users SET password_hash=?,session_version=session_version+1,force_password_change=1,updated_at=? WHERE id=?')->execute([authPasswordHash($pw),date('c'),$id]);authAudit($db,'reset_user_password',$id);flash('Uusi väliaikainen salasana tallennettiin. Käyttäjä vaihtaa sen kirjautuessaan.');
        }
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();flash($e instanceof PDOException?($action==='clear_auth_audit'?'Lokitietojen tyhjennys epäonnistui. Tietoja ei poistettu.':'Käyttäjätietojen tallennus epäonnistui. Tarkista, ettei tunnus ole jo käytössä.'):$e->getMessage(),'error');}
    redirect($destination);
}
function authMechanicId(PDO $db,int $id): int {
    if(!$id)return 0;$st=$db->prepare('SELECT id FROM mechanics WHERE id=? AND active=1');$st->execute([$id]);if(!$st->fetchColumn())throw new RuntimeException('Valitse käytössä oleva mekaanikko.');return $id;
}
/** Vain näkymien viimeistely. Varsinainen suoja on authRequire() ennen jokaista POSTia. */
function authFilterHtml(string $html): string {
    return (string)preg_replace_callback('~<form\b[^>]*>.*?</form>~is',static function(array $m): string {
        $form=$m[0];if(!preg_match('~^<form\b[^>]*\bmethod=["\']?post\b~i',$form))return $form;
        if(!preg_match('~<input\b[^>]*\bname=["\']action["\'][^>]*\bvalue=["\']([^"\']+)["\']~i',$form,$a))return '';
        if(canAction($a[1]))return $form;
        if(in_array($a[1],['save_car','update_service','update_part','update_customer','save_item_settings','update_car_customer_history','update_invoice_status'],true)){
            $body=preg_replace('~</?form\b[^>]*>|<button\b[^>]*>.*?</button>~is','',$form);
            return '<div class="readonly-form"><p class="tiny">Katseluoikeus · tietoja ei voi muuttaa.</p><fieldset disabled style="border:0;padding:0;margin:0;min-width:0">'.$body.'</fieldset></div>';
        }
        return '';
    },$html);
}

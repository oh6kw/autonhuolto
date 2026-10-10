<?php
declare(strict_types=1);

/* ------------------------- Täysi sovellusbackup ------------------------- */
function backupRemoveTree(string $dir): void {
    if(!is_dir($dir))return;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $item){$path=$item->getPathname();if($item->isLink()){@unlink($path);continue;}$item->isDir()?@rmdir($path):@unlink($path);} @rmdir($dir);
}
function backupRelativeSafe(string $relative): bool {
    $relative=ltrim(str_replace('\\','/',$relative),'/');
    if($relative===''||str_contains($relative,'..')||str_contains($relative,"\0"))return false;
    foreach(explode('/',$relative) as $part)if($part===''||$part==='.'||$part==='..')return false;
    return true;
}
function backupRuntimeExcluded(string $relative): bool {
    $relative=ltrim(str_replace('\\','/',$relative),'/');$base=basename($relative);$dbName=basename(DB_FILE);
    if(in_array($base,[$dbName,BACKUP_DB_NAME,$dbName.'-wal',$dbName.'-shm',BACKUP_DB_NAME.'-wal',BACKUP_DB_NAME.'-shm','backup-manifest.json','.autohuolto-access-7f3c91.lock','.autohuolto-setup-7f3c91.php'],true))return true;
    foreach(explode('/',$relative) as $part)if(preg_match('/^\.(?:pre-restore|restore-stage|restore-old|restore-incoming|restore-tmp|failed-kuvat|pre-users|autohuolto-backup-tmp)-/',$part))return true;
    if(preg_match('/^autohuolto-(?:taysi-)?backup-.*\.(?:zip|sqlite3)$/i',$base)||preg_match('/^index\.php\.backup-/i',$base))return true;
    /* kaikki SQLite-tiedoston kopiot ja muunnelmat (esim. autohuolto.sqlite3.ennen-…, käsin tai skriptillä otetut varmuuskopiot) jätetään pois: ne eivät kuulu ohjelmapuuhun, ja niiden oikeudet voivat estää ZIP:n luonnin. */
    if(preg_match('/\.sqlite3(?:[.-].*)?$/i',$base))return true;
    return false;
}
function backupApplicationTreeEntries(string $root): array {
    $files=[];$directories=[];$root=rtrim($root,'/\\');
    if(!is_dir($root))throw new RuntimeException('Sovellushakemistoa ei löydy.');
    $walk=function(string $dir,string $prefix)use(&$walk,&$files,&$directories):void{
        $it=new FilesystemIterator($dir,FilesystemIterator::SKIP_DOTS);
        foreach($it as $item){
            $rel=($prefix!==''?$prefix.'/':'').$item->getFilename();
            if(!backupRelativeSafe($rel))throw new RuntimeException('Sovellushakemistossa on epäkelpo tiedostopolku: '.$rel.'.');
            if(backupRuntimeExcluded($rel))continue;
            if($item->isLink())throw new RuntimeException('Täyttä varmuuskopiota ei luotu, koska sovellushakemistossa on symbolinen linkki: '.$rel.'.');
            if($item->isDir()){$directories[]=['path'=>$rel,'mode'=>((int)$item->getPerms()&0777)];$walk($item->getPathname(),$rel);continue;}
            if(!$item->isFile())continue;
            if(!is_readable($item->getPathname()))throw new RuntimeException('Täyttä varmuuskopiota ei luotu, koska ohjelma ei voi lukea tiedostoa: '.$rel.'. Siirrä tiedosto pois ohjelman hakemistosta tai korjaa sen omistaja ja oikeudet.');
            $files[]=['path'=>$rel,'size'=>(int)$item->getSize(),'sha256'=>(string)hash_file('sha256',$item->getPathname())];
        }
    };
    $walk($root,'');usort($files,fn($a,$b)=>strcmp($a['path'],$b['path']));usort($directories,fn($a,$b)=>strcmp($a['path'],$b['path']));
    return ['files'=>$files,'directories'=>$directories];
}
/* Palautus koskee vain ohjelman omia tiedostoja. Muut tiedostot (esim. oma .htaccess, robots.txt, toinen sivusto samassa kansiossa)
   jätetään rauhaan: niitä ei kirjoiteta eikä poisteta. Kun ohjelmaan lisätään uusia kansioita, lisää ne tähän. */
const BACKUP_MANAGED_ROOT_FILES = ['index.php','CHANGELOG.md','BACKUP.md','SETUP.md','README.md','LICENSE','SECURITY.md','.gitignore'];
const BACKUP_MANAGED_DIRS       = ['app','assets','views','kuvat','docs'];
const BACKUP_KEEP_DAYS          = 30;
function backupRestoreManaged(string $relative): bool {
    $relative=ltrim(str_replace('\\','/',$relative),'/');
    if($relative==='')return false;
    $parts=explode('/',$relative);
    if(count($parts)===1)return in_array($parts[0],BACKUP_MANAGED_ROOT_FILES,true)||in_array($parts[0],BACKUP_MANAGED_DIRS,true);
    if(!in_array($parts[0],BACKUP_MANAGED_DIRS,true))return false;
    /* kuvat/ on käyttäjän lataamaa sisältöä: sinne palautetaan vain kuvia ja suojaava index.html, ei koskaan ajettavaa koodia. */
    if($parts[0]==='kuvat'){
        $base=end($parts);
        return (bool)preg_match('/\.(?:jpe?g|png|webp)$/i',$base)||$base==='index.html';
    }
    /* docs/ sisältää vain esimerkkikuvia ja ohjeita: ei koskaan ajettavaa koodia. */
    if($parts[0]==='docs')return (bool)preg_match('/\.(?:png|jpe?g|webp|md)$/i',(string)end($parts));
    return true;
}
function backupPathInKuvat(string $relative): bool { return $relative==='kuvat'||str_starts_with($relative,'kuvat/'); }
function backupFilterManaged(array $entries): array {
    return array_values(array_filter($entries,fn($e)=>backupRestoreManaged((string)$e['path'])));
}
/* Uudet tiedostot saavat web-palvelimen luettavissa olevat oikeudet (umask 0077 antaisi muuten 0600/0700 ja rikkoisi esim. assets/-tiedostot).
   Jo olemassa olevan tiedoston tai kansion oikeudet säilytetään sellaisenaan. */
function backupDefaultMode(string $relative,bool $isDir): int {
    if(backupPathInKuvat($relative))return $isDir?0770:0660;
    return $isDir?0755:0644;
}
function backupPruneOldSafetyCopies(string $dir,string $keepStamp): int {
    $removed=0;$cutoff=time()-BACKUP_KEEP_DAYS*86400;
    foreach(glob(rtrim($dir,'/').'/.pre-restore-*')?:[] as $path){
        if(str_contains($path,$keepStamp)||is_link($path))continue;
        $mt=@filemtime($path);if($mt===false||$mt>=$cutoff)continue;
        if(is_dir($path)){backupRemoveTree($path);$removed++;}elseif(@unlink($path))$removed++;
    }
    return $removed;
}
function backupTempDir(): string {
    // Käytä PHP:n omaa temp-hakemistoa.
    // Näin backup ei riipu sovelluskansion erityisistä kirjoitusoikeuksista.
    $dir=rtrim(sys_get_temp_dir(),'/\\').'/autohuolto-backup-'.bin2hex(random_bytes(8));
    if(!@mkdir($dir,0700,true))throw new RuntimeException('Varmuuskopion valmistelu epäonnistui.');
    return $dir;
}
function validateRestoreDatabase(string $file,?string $images=null): void {
    if(!sqliteLooksValid($file))throw new RuntimeException('Varmuuskopio ei ole kelvollinen SQLite-tietokanta.');
    $check=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $check->exec('PRAGMA query_only=ON');
    if($check->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException('Varmuuskopion tietokanta on vioittunut.');
    if($check->query("SELECT COUNT(*) FROM sqlite_master WHERE type IN ('trigger','view')")->fetchColumn()>0)throw new RuntimeException('Varmuuskopiossa on huoltokirjaan kuulumattomia tietokantarakenteita.');
    requireCurrentDatabaseVersion($check);
    $tables=$check->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    foreach(currentDatabaseDefinition() as $table=>$definition){
        if(!in_array($table,$tables,true))throw new RuntimeException('Varmuuskopiosta puuttuu huoltokirjan taulu: '.$table.'.');
        $columns=[];foreach($check->query('PRAGMA table_info("'.$table.'")')->fetchAll() as $col)$columns[$col['name']]=$col;
        foreach($definition['columns'] as $name=>$expected){if(!isset($columns[$name]))throw new RuntimeException('Varmuuskopiosta puuttuu sarake '.$table.'.'.$name.'.');if(strtoupper((string)$columns[$name]['type'])!==$expected['type']||(int)$columns[$name]['pk']!==$expected['pk'])throw new RuntimeException('Varmuuskopion sarakerakenne on virheellinen: '.$table.'.'.$name.'.');}
        $declared=[];foreach($check->query('PRAGMA foreign_key_list("'.$table.'")')->fetchAll() as $fk)$declared[]=[(string)$fk['table'],(string)$fk['from'],(string)$fk['to']];
        foreach($definition['foreign_keys'] as $fk){[$parent,$childCol,$parentCol]=$fk;if(!in_array($fk,$declared,true))throw new RuntimeException('Varmuuskopiosta puuttuu viittauksen suoja: '.$table.'.'.$childCol.'.');$sql='SELECT COUNT(*) FROM "'.$table.'" c LEFT JOIN "'.$parent.'" p ON p."'.$parentCol.'"=c."'.$childCol.'" WHERE c."'.$childCol.'" IS NOT NULL AND p."'.$parentCol.'" IS NULL';if((int)$check->query($sql)->fetchColumn()>0)throw new RuntimeException('Varmuuskopiossa on rikkoutunut tietokantaviittaus: '.$table.'.'.$childCol.'.');}
        $unique=[];foreach($check->query('PRAGMA index_list("'.$table.'")')->fetchAll() as $idx){if(!(int)$idx['unique']||(int)$idx['partial'])continue;$unique[]=array_column($check->query('PRAGMA index_info('.$check->quote((string)$idx['name']).')')->fetchAll(),'name');}
        foreach($definition['unique'] as $keys)if(!in_array($keys,$unique,true))throw new RuntimeException('Varmuuskopiosta puuttuu tietokannan yksikäsitteisyysraja: '.$table.'.');
    }
    if($check->query('PRAGMA foreign_key_check')->fetch())throw new RuntimeException('Varmuuskopiossa on rikkoutuneita tietokantaviittauksia.');
    foreach([['cars','current_customer_id','customers'],['services','customer_id','customers'],['services','mechanic_id','mechanics']] as [$table,$column,$parent])if($check->query('SELECT COUNT(*) FROM "'.$table.'" c LEFT JOIN "'.$parent.'" p ON p.id=c."'.$column.'" WHERE c."'.$column.'">0 AND p.id IS NULL')->fetchColumn()>0)throw new RuntimeException('Varmuuskopiossa on puuttuva asiakas- tai mekaanikkoviittaus.');
    if($images!==null){
        $paths=$check->query("SELECT file_path FROM service_photos UNION SELECT setting_value FROM app_settings WHERE setting_key='logo_path' UNION SELECT seller_logo_path FROM invoices WHERE seller_logo_path<>''")->fetchAll(PDO::FETCH_COLUMN);
        foreach($paths as $path){$path=trim((string)$path);if($path==='')continue;if(!preg_match('~^kuvat/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\\.(?:jpe?g|png|webp)$~i',$path)||str_contains($path,'..')||!is_file($images.'/'.substr($path,6)))throw new RuntimeException('ZIP-varmuuskopiosta puuttuu kuva tai logo: '.$path.'.');}
    }
}

function backupCreateFullZip(string $snapshot,string $download): void {
    if(!class_exists('ZipArchive'))throw new RuntimeException('Täysi ZIP-backup vaatii PHP ZipArchive -laajennuksen.');
    if(!is_file($snapshot))throw new RuntimeException('Tietokannan backup-snapshotia ei löytynyt.');

    // Tee ensin lista tiedostoista, jotta backupin oma ZIP tai keskeneräinen temp-tiedosto
    // ei päädy vahingossa mukaan sovelluspuuhun.
    $tree=backupApplicationTreeEntries(__DIR__.'/..');
    $zip=new ZipArchive();
    $open=$zip->open($download,ZipArchive::CREATE|ZipArchive::OVERWRITE);
    if($open!==true)throw new RuntimeException('ZIP-varmuuskopiota ei saatu luotua (ZipArchive-koodi '.(string)$open.').');

    try {
        if(!$zip->addFile($snapshot,BACKUP_DB_NAME))throw new RuntimeException('Tietokantaa ei saatu lisättyä ZIP-varmuuskopioon.');
        foreach($tree['files'] as $entry){
            $full=__DIR__.'/../'.$entry['path'];
            if(!is_file($full))throw new RuntimeException('Sovellustiedosto katosi backupin aikana: '.$entry['path'].'.');
            if(!$zip->addFile($full,$entry['path']))throw new RuntimeException('Tiedostoa ei saatu lisättyä varmuuskopioon: '.$entry['path'].'.');
        }
        if(!$zip->close())throw new RuntimeException('ZIP-varmuuskopion viimeistely epäonnistui.');
    } catch(Throwable $e) {
        try{$zip->close();}catch(Throwable){}
        @unlink($download);
        throw $e;
    }
}
function backupExtractZip(string $upload,string $stage): array {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-palautus vaatii PHP ZipArchive -laajennuksen.');
    $zip=new ZipArchive();$open=$zip->open($upload,ZipArchive::CHECKCONS);if($open!==true)throw new RuntimeException('ZIP-varmuuskopiota ei saatu avattua tai se on vioittunut.');
    $hasDb=false;$hasIndex=false;$manifest=null;$total=0;
    try{
        if($zip->numFiles>50000)throw new RuntimeException('ZIP-varmuuskopiossa on liikaa tiedostoja.');
        for($i=0;$i<$zip->numFiles;$i++){
            $st=$zip->statIndex($i);if(!$st)throw new RuntimeException('ZIP-tiedoston hakemisto on vioittunut.');
            $name=str_replace('\\','/',(string)$st['name']);$size=(int)$st['size'];$total+=$size;
            if($total>2*1024*1024*1024||$size>1024*1024*1024)throw new RuntimeException('ZIP-varmuuskopio ylittää palautuksen kokorajan.');
            if(!backupRelativeSafe(rtrim($name,'/')))throw new RuntimeException('ZIP-varmuuskopiossa on virheellinen tiedostopolku.');
            $opsys=0;$attr=0;if($zip->getExternalAttributesIndex($i,$opsys,$attr)&&(($attr>>16)&0170000)===0120000)throw new RuntimeException('ZIP-varmuuskopio ei saa sisältää symbolisia linkkejä.');
            $isDir=str_ends_with($name,'/');$target=$stage.'/'.$name;
            if($isDir){if(!is_dir($target)&&!mkdir($target,0770,true))throw new RuntimeException('Palautuksen hakemiston valmistelu epäonnistui.');continue;}
            if(!is_dir(dirname($target))&&!mkdir(dirname($target),0770,true))throw new RuntimeException('Palautuksen hakemiston valmistelu epäonnistui.');
            $in=$zip->getStream($name);$out=@fopen($target,'xb');if(!$in||!$out){if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);throw new RuntimeException('ZIP-tiedoston purkaminen epäonnistui: '.$name.'.');}
            try{$copied=stream_copy_to_stream($in,$out);$flushed=fflush($out);}finally{fclose($in);fclose($out);}
            if($copied!==$size||!$flushed||strtolower(hash_file('crc32b',$target))!==sprintf('%08x',(int)$st['crc']))throw new RuntimeException('ZIP-tiedoston sisältö on vioittunut tai jäi vajaaksi: '.$name.'.');
            if($name===BACKUP_DB_NAME)$hasDb=true;
            if($name==='index.php')$hasIndex=true;
            if($name==='backup-manifest.json'){$raw=file_get_contents($target);$manifest=json_decode((string)$raw,true,512,JSON_THROW_ON_ERROR);}
        }
        if(!$hasDb)throw new RuntimeException('Täydestä ZIP-varmuuskopiosta puuttuu autohuolto.sqlite3.');
        if(!is_dir($stage.'/kuvat'))mkdir($stage.'/kuvat',0770,true);
        return ['db'=>$stage.'/'.BACKUP_DB_NAME,'application_root'=>$stage,'full_application'=>$hasIndex,'manifest'=>$manifest];
    }finally{$zip->close();}
}
function backupValidateManifest(array $manifest,string $root,string $db): void {
    if(($manifest['format']??'')!=='autohuolto-full-backup'||(int)($manifest['format_version']??0)!==4)throw new RuntimeException('Varmuuskopion manifest on tuntematon.');
    if((int)($manifest['schema_version']??0)!==SCHEMA_VERSION)throw new RuntimeException('Varmuuskopion tietokannan skeemaversio ei täsmää tähän ohjelmaversioon.');
    $dbh=(string)($manifest['database']['sha256']??'');if($dbh===''||!hash_equals($dbh,(string)hash_file('sha256',$db)))throw new RuntimeException('Varmuuskopion tietokantatiedosto ei täsmää manifestiin.');
    foreach((array)($manifest['files']??[]) as $entry){$path=(string)($entry['path']??'');$full=$root.'/'.$path;if(!backupRelativeSafe($path)||!is_file($full)||!hash_equals((string)($entry['sha256']??''),(string)hash_file('sha256',$full)))throw new RuntimeException('Varmuuskopion tiedosto ei täsmää manifestiin: '.$path.'.');}
}
function backupCopyTreeSnapshot(string $source,string $target): void {
    if(file_exists($target))throw new RuntimeException('Sovelluksen turvakopion kohde on jo olemassa.');
    if(!mkdir($target,0700,true))throw new RuntimeException('Sovelluksen turvakopiota ei saatu luotua.');
    try{
        $tree=backupApplicationTreeEntries($source);
        foreach($tree['directories'] as $d){$dst=$target.'/'.$d['path'];if(!is_dir($dst)&&!mkdir($dst,0770,true))throw new RuntimeException('Sovelluksen turvakansion luonti epäonnistui.');@chmod($dst,(int)$d['mode']);}
        foreach($tree['files'] as $f){$src=$source.'/'.$f['path'];$dst=$target.'/'.$f['path'];if(!is_dir(dirname($dst))&&!mkdir(dirname($dst),0770,true))throw new RuntimeException('Sovelluksen turvakansion valmistelu epäonnistui.');if(!copy($src,$dst))throw new RuntimeException('Sovellustiedoston turvakopiointi epäonnistui: '.$f['path'].'.');}
    }catch(Throwable $e){backupRemoveTree($target);throw $e;}
}
function backupRestoreTree(string $source,string $target): void {
    $s=backupApplicationTreeEntries($source);$t=backupApplicationTreeEntries($target);
    $s['files']=backupFilterManaged($s['files']);$s['directories']=backupFilterManaged($s['directories']);
    $t['files']=backupFilterManaged($t['files']);$t['directories']=backupFilterManaged($t['directories']);
    $sf=array_fill_keys(array_column($s['files'],'path'),true);$sd=array_fill_keys(array_column($s['directories'],'path'),true);
    foreach(array_reverse($t['files']) as $f)if(!isset($sf[$f['path']]))@unlink($target.'/'.$f['path']);
    foreach(array_reverse($t['directories']) as $d)if(!isset($sd[$d['path']]))@rmdir($target.'/'.$d['path']);
    foreach($s['directories'] as $d){
        $dst=$target.'/'.$d['path'];
        if(!is_dir($dst)){if(!mkdir($dst,0770,true))throw new RuntimeException('Sovelluksen palautuksen hakemiston luonti epäonnistui: '.$d['path'].'.');@chmod($dst,backupDefaultMode($d['path'],true));}
    }
    foreach($s['files'] as $f){
        $src=$source.'/'.$f['path'];$dst=$target.'/'.$f['path'];
        if(!is_dir(dirname($dst))){if(!mkdir(dirname($dst),0770,true))throw new RuntimeException('Sovelluksen palautuksen hakemiston valmistelu epäonnistui.');@chmod(dirname($dst),backupDefaultMode(dirname($f['path']),true));}
        $mode=is_file($dst)?((int)@fileperms($dst)&0777):backupDefaultMode($f['path'],false);
        $tmp=$dst.'.restore-tmp-'.bin2hex(random_bytes(4));
        if(!copy($src,$tmp)){@unlink($tmp);throw new RuntimeException('Sovellustiedoston palautus epäonnistui: '.$f['path'].'.');}
        @chmod($tmp,$mode);
        if(!rename($tmp,$dst)){@unlink($tmp);throw new RuntimeException('Sovellustiedoston paikalleenvienti epäonnistui: '.$f['path'].'.');}
    }
}
/** Lukee ohjelmaversion varmuuskopion index.php:stä (const APP_VERSION = '…'). */
/** Ohjelmaversio tiedostonimeen (vain turvalliset merkit). */
function backupFileVersion(): string { return preg_replace('/[^0-9A-Za-z._-]/','',APP_VERSION)?:'tuntematon'; }
function backupAppVersion(string $indexPath): ?string {
    $src=(string)@file_get_contents($indexPath);
    return preg_match("~const\s+APP_VERSION\s*=\s*'([0-9A-Za-z.+-]{1,40})'~",$src,$m)?$m[1]:null;
}
/** Estää vahingossa tapahtuvan ohjelman palautuksen vanhempaan versioon täyden ZIP-palautuksen yhteydessä. */
function backupCheckVersionDowngrade(string $backupRoot,bool $allowOlder): ?string {
    $bv=backupAppVersion($backupRoot.'/index.php');
    if($bv===null){
        if(!$allowOlder)throw new RuntimeException('Varmuuskopion ohjelmaversiota ei voitu lukea. Palautus voi vaihtaa koko ohjelman tuntemattomaan versioon. Palauta mieluummin pelkkä SQLite-varmuuskopio (ohjelma säilyy), tai rastita "Salli palautus vanhempaan ohjelmaversioon", jos olet varma.');
        return 'Varmuuskopion ohjelmaversio oli tuntematon.';
    }
    if(version_compare($bv,APP_VERSION,'<')){
        if(!$allowOlder)throw new RuntimeException('Palautusta ei tehty: varmuuskopion ohjelmaversio ('.$bv.') on vanhempi kuin nyt käytössä oleva ('.APP_VERSION.'). Täyden ZIP-palautuksen jälkeen koko ohjelma olisi vanha versio ja päivityksesi katoaisi. Jos haluat vain tiedot takaisin, palauta pelkkä SQLite-varmuuskopio: ohjelma säilyy ennallaan. Jos haluat varmasti palata vanhaan versioon, rastita "Salli palautus vanhempaan ohjelmaversioon".');
        return 'Ohjelma palautui vanhempaan versioon '.$bv.' (aiemmin '.APP_VERSION.').';
    }
    return null;
}
function backupRestore(PDO &$db,string $upload): string {
    $stage=__DIR__.'/.restore-stage-'.bin2hex(random_bytes(8));if(!mkdir($stage,0700))throw new RuntimeException('Palautuksen väliaikaishakemistoa ei saatu luotua.');
    $isZip=str_starts_with((string)file_get_contents($upload,false,null,0,4),'PK');$stamp=date('Ymd-His').'-'.bin2hex(random_bytes(5));
    $pre=dirname(DB_FILE).'/.pre-restore-'.$stamp.'.sqlite3';$old=dirname(DB_FILE).'/.restore-old-'.$stamp.'.sqlite3';$incoming=dirname(DB_FILE).'/.restore-incoming-'.$stamp.'.sqlite3';$preApp=dirname(DB_FILE).'/.pre-restore-app-'.$stamp;$oldMoved=false;$newMoved=false;$preAppCreated=false;
    try{
        $payload=$isZip?backupExtractZip($upload,$stage):['db'=>$upload,'application_root'=>null,'full_application'=>false,'manifest'=>null];
        if($payload['full_application']){
            $required=['index.php','CHANGELOG.md','app/actions.php','assets/app.css','assets/app.js'];foreach($required as $r)if(!is_file($payload['application_root'].'/'.$r))throw new RuntimeException('Täydestä varmuuskopiosta puuttuu ohjelman tärkeä tiedosto: '.$r.'.');
            /* Kaikki tiedostot, jotka paketin index.php lataa (require __DIR__ . '/...'), on oltava mukana. Muuten palautettu ohjelma ei käynnistyisi. */
            $indexSource=(string)file_get_contents($payload['application_root'].'/index.php');
            if(preg_match_all("~require(?:_once)?\s+__DIR__\s*\.\s*'/([A-Za-z0-9_./-]+)'~",$indexSource,$needed))foreach(array_unique($needed[1]) as $r)if(!backupRelativeSafe($r)||!is_file($payload['application_root'].'/'.$r))throw new RuntimeException('Täydestä varmuuskopiosta puuttuu tiedosto, jota index.php tarvitsee: '.$r.'.');
            $downgradeNote=backupCheckVersionDowngrade($payload['application_root'],!empty($_POST['restore_allow_older']));
            if(is_array($payload['manifest']))backupValidateManifest($payload['manifest'],$payload['application_root'],$payload['db']);
        }
        validateRestoreDatabase($payload['db'],$payload['full_application']?$payload['application_root'].'/kuvat':null);
        $sourceDb=new PDO('sqlite:'.$payload['db'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);consistentDatabaseCopy($sourceDb,$incoming);$sourceDb=null;
        validateRestoreDatabase($incoming,$payload['full_application']?$payload['application_root'].'/kuvat':null);
        consistentDatabaseCopy($db,$pre);
        $checkpoint=$db->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);if(!$checkpoint||(int)$checkpoint[0]!==0)throw new RuntimeException('Tietokanta on käytössä toisessa yhteydessä. Palautusta ei tehty.');
        foreach(array_keys($GLOBALS) as $key)if(($GLOBALS[$key]??null) instanceof PDOStatement)$GLOBALS[$key]=null;
        $db=null;
        foreach(['-wal','-shm'] as $suffix)if(is_file(DB_FILE.$suffix)&&!unlink(DB_FILE.$suffix))throw new RuntimeException('Tietokannan sivutiedoston vapautus epäonnistui.');
        if(!rename(DB_FILE,$old))throw new RuntimeException('Nykyistä tietokantaa ei saatu siirrettyä turvaan.');$oldMoved=true;
        if(!rename($incoming,DB_FILE))throw new RuntimeException('Tietokannan korvaaminen epäonnistui.');$newMoved=true;@chmod(DB_FILE,0600);
        $newDb=new PDO('sqlite:'.DB_FILE,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if((int)$newDb->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='auth_users'")->fetchColumn()>0)appSet($newDb,'auth_install_id',bin2hex(random_bytes(24)));
        $newDb=null;
        $skipped=0;
        if($payload['full_application']){
            $srcTree=backupApplicationTreeEntries($payload['application_root']);
            foreach(array_merge($srcTree['files'],$srcTree['directories']) as $e)if(!backupRestoreManaged((string)$e['path'])&&!backupRuntimeExcluded((string)$e['path']))$skipped++;
            backupCopyTreeSnapshot(__DIR__.'/..',$preApp);$preAppCreated=true;backupRestoreTree($payload['application_root'],__DIR__.'/..');
        }
        @unlink($old);$oldMoved=false;
        $pruned=backupPruneOldSafetyCopies(dirname(DB_FILE),$stamp);
        $msg='Varmuuskopio palautettiin. Ennen palautusta tehty tietokannan turvakopio: '.basename($pre).'.';
        $msg.=$payload['full_application']?' Koko sovelluspuu, tietokanta, kuvat sekä backupin käyttäjät ja oikeudet palautettiin.':' Tietokanta palautettiin; sovellustiedostoihin ja kuvakansioon ei koskettu. Varmuuskopion käyttäjät ja oikeudet palautettiin.';
        if(!empty($downgradeNote))$msg.=' '.$downgradeNote;
        if($skipped>0)$msg.=' Ohjelmaan kuulumattomat '.$skipped.' tiedostoa tai kansiota ohitettiin ja jätettiin ennalleen.';
        if($pruned>0)$msg.=' Yli '.BACKUP_KEEP_DAYS.' päivää vanhat palautuksen turvakopiot ('.$pruned.' kpl) siivottiin.';
        return $msg;
    }catch(Throwable $e){
        $ok=true;
        if($newMoved)$ok=@rename(DB_FILE,$incoming)&&$ok;
        if($oldMoved)$ok=@rename($old,DB_FILE)&&$ok;
        if($preAppCreated){try{backupRestoreTree($preApp,__DIR__.'/..');}catch(Throwable){$ok=false;}backupRemoveTree($preApp);}
        if(!$ok)throw new RuntimeException('Palautus epäonnistui ja vanhan tilanteen automaattinen palautus jäi vajaaksi. Turvakopio: '.basename($pre).'. '.$e->getMessage(),0,$e);
        throw new RuntimeException('Palautusta ei tehty; nykyiset tiedot säilyivät. '.$e->getMessage(),0,$e);
    }finally{@unlink($incoming);backupRemoveTree($stage);}
}

/* GET: tietokanta tai koko sovellus ZIPiksi. */
if(isset($_GET['backup'])){
    if(!canAction('save_app_settings'))authDeny();
    $temp=null;
    try{
        $temp=backupTempDir();
        $kind=(string)$_GET['backup'];
        if($kind==='db'){
            $download=$temp.'/autohuolto.sqlite3';consistentDatabaseCopy($db,$download);$filename='autohuolto-db-backup-v'.backupFileVersion().'-'.date('Y-m-d-His').'.sqlite3';$type='application/octet-stream';
        }elseif($kind==='full'){
            $snapshot=$temp.'/autohuolto.sqlite3';
            $download=$temp.'/autohuolto-full-backup.zip';
            consistentDatabaseCopy($db,$snapshot);
            backupCreateFullZip($snapshot,$download);
            $filename='autohuolto-taysi-backup-v'.backupFileVersion().'-'.date('Y-m-d-His').'.zip';$type='application/zip';
        }else{http_response_code(404);exit('Tuntematon varmuuskopiotyyppi.');}
        header('Content-Type: '.$type);header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($download));header('Cache-Control: no-store');readfile($download);
    }catch(Throwable $e){
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'Varmuuskopio epäonnistui: '.$e->getMessage();
    }
    finally{if(is_string($temp)&&$temp!=='')backupRemoveTree($temp);}exit;
}

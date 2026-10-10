<?php
declare(strict_types=1);

/**
 * app/images.php – huoltokuvat ja logo: tiedostopolut, pienennetyt versiot, tallennus ja poisto.
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

function getServicePhotos(PDO $db,int $sid): array { $st=$db->prepare("SELECT * FROM service_photos WHERE service_id=? ORDER BY id");$st->execute([$sid]);return $st->fetchAll(); }
function photoFolderName(array $car): string { $raw=trim((string)($car['reg_plate']??'')); if($raw==='')$raw='auto-'.(int)($car['id']??0); $raw=mb_strtolower($raw,'UTF-8'); $raw=preg_replace('/[^a-z0-9-]+/u','-',$raw)??''; return trim($raw,'-')?:('auto-'.(int)($car['id']??0)); }
function photoAbsolutePath(string $relative): ?string { $relative=str_replace('\\','/',$relative); if(!str_starts_with($relative,'kuvat/')||str_contains($relative,'..'))return null; return APP_DIR.'/'.ltrim($relative,'/'); }
function logoAbsolutePath(string $relative): ?string { $relative=str_replace('\\','/',$relative); if(!preg_match('~^kuvat/logo/[A-Za-z0-9._-]+$~',$relative)||str_contains($relative,'..'))return null; return APP_DIR.'/'.ltrim($relative,'/'); }
function logoOptimizationAvailable(): bool { return (extension_loaded('gd')&&function_exists('imagecreatetruecolor'))||class_exists('Imagick'); }
function logoVariantSpec(string $variant): array { return $variant==='header'?[360,120]:[1200,500]; }
function logoDerivativeRelative(string $relative,string $variant='web'): ?string {
    $variant=$variant==='header'?'header':'web';$source=logoAbsolutePath($relative);if(!$source||!is_file($source))return null;
    $info=@getimagesize($source);if(!$info)return null;$mime=(string)($info['mime']??'');$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??'';if($ext==='')return null;
    $base=pathinfo(basename($relative),PATHINFO_FILENAME);if(str_ends_with($base,'.header')||str_ends_with($base,'.web'))return $relative;
    $targetRel='kuvat/logo/'.$base.'.'.$variant.'.'.$ext;$target=logoAbsolutePath($targetRel);if(!$target)return null;
    if(is_file($target)&&filesize($target)>0&&filemtime($target)>=filemtime($source))return $targetRel;
    if(!logoOptimizationAvailable())return null;[$maxW,$maxH]=logoVariantSpec($variant);$ok=false;
    try{
        if(extension_loaded('gd')&&function_exists('imagecreatetruecolor')){
            $src=null;if($mime==='image/jpeg'&&function_exists('imagecreatefromjpeg'))$src=@imagecreatefromjpeg($source);elseif($mime==='image/png'&&function_exists('imagecreatefrompng'))$src=@imagecreatefrompng($source);elseif($mime==='image/webp'&&function_exists('imagecreatefromwebp'))$src=@imagecreatefromwebp($source);
            if($src){$sw=imagesx($src);$sh=imagesy($src);$scale=min($maxW/max(1,$sw),$maxH/max(1,$sh),1.0);$tw=max(1,(int)round($sw*$scale));$th=max(1,(int)round($sh*$scale));$dst=imagecreatetruecolor($tw,$th);
                if($mime==='image/png'||$mime==='image/webp'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$tw,$th,$transparent);}
                if(imagecopyresampled($dst,$src,0,0,0,0,$tw,$th,$sw,$sh)){
                    if($mime==='image/jpeg'&&function_exists('imagejpeg'))$ok=@imagejpeg($dst,$target,85);elseif($mime==='image/png'&&function_exists('imagepng'))$ok=@imagepng($dst,$target,7);elseif($mime==='image/webp'&&function_exists('imagewebp'))$ok=@imagewebp($dst,$target,82);
                }
                imagedestroy($dst);imagedestroy($src);
            }
        }
        if(!$ok&&class_exists('Imagick')){
            $im=new Imagick($source);if($im->getNumberImages()>1)$im->setIteratorIndex(0);$sw=$im->getImageWidth();$sh=$im->getImageHeight();$scale=min($maxW/max(1,$sw),$maxH/max(1,$sh),1.0);$tw=max(1,(int)round($sw*$scale));$th=max(1,(int)round($sh*$scale));if($tw!==$sw||$th!==$sh)$im->resizeImage($tw,$th,Imagick::FILTER_LANCZOS,1,true);$im->setImagePage(0,0,0,0);$im->stripImage();
            if($mime==='image/jpeg'){$im->setImageFormat('jpeg');$im->setImageCompressionQuality(85);}elseif($mime==='image/png'){$im->setImageFormat('png');$im->setOption('png:compression-level','7');}else{$im->setImageFormat('webp');$im->setImageCompressionQuality(82);} $ok=$im->writeImage($target);$im->clear();$im->destroy();
        }
    }catch(Throwable $e){$ok=false;}
    if($ok&&is_file($target)&&filesize($target)>0){@chmod($target,0660);return $targetRel;}@unlink($target);return null;
}
function logoDisplayRelative(string $relative,string $variant='web'): string { return logoDerivativeRelative($relative,$variant)??$relative; }
function logoUrl(string $relative,string $variant='web'): string { $display=logoDisplayRelative($relative,$variant);return '?brand_logo='.rawurlencode(basename($display)); }
function logoFileSizeText(?string $relative): string { if(!$relative)return '–';$abs=logoAbsolutePath($relative);if(!$abs||!is_file($abs))return '–';return fileSizeText((int)filesize($abs)); }
function ensureLogoDerivatives(string $relative): array { if($relative===''||!logoAbsolutePath($relative)||!is_file((string)logoAbsolutePath($relative)))return ['header'=>null,'web'=>null];return ['header'=>logoDerivativeRelative($relative,'header'),'web'=>logoDerivativeRelative($relative,'web')]; }
function storeUploadedLogo(?array $file): ?string {
    if(!$file)return null; $err=(int)($file['error']??UPLOAD_ERR_NO_FILE); if($err===UPLOAD_ERR_NO_FILE)return null;
    if($err!==UPLOAD_ERR_OK){$msg=in_array($err,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)?t('img.logo_too_large_server'):t('img.logo_upload_failed',['code'=>$err]);throw new RuntimeException($msg);}
    if((int)($file['size']??0)>5*1024*1024)throw new RuntimeException(t('img.logo_over_5mb'));
    $tmp=(string)($file['tmp_name']??'');$img=@getimagesize($tmp);if(!$img)throw new RuntimeException(t('img.logo_invalid_image'));
    $mime=(string)($img['mime']??'');$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime]))throw new RuntimeException(t('img.logo_format_unsupported'));
    $dir=IMAGE_DIR.'/logo';if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException(t('img.logo_dir_failed'));if(!is_file(IMAGE_DIR.'/index.html'))@file_put_contents(IMAGE_DIR.'/index.html','');if(!is_file($dir.'/index.html'))@file_put_contents($dir.'/index.html','');
    $name='logo-'.date('Ymd-His').'-'.bin2hex(random_bytes(6)).'.'.$allowed[$mime];$abs=$dir.'/'.$name;if(!move_uploaded_file($tmp,$abs))throw new RuntimeException(t('img.logo_save_failed'));@chmod($abs,0660);$rel='kuvat/logo/'.$name;ensureLogoDerivatives($rel);return $rel;
}
function photoVariantSpec(string $variant): array { return $variant==='thumb'?[480,360]:[1800,1400]; }
function photoDerivativeRelative(string $relative,string $variant='web'): ?string {
    $variant=$variant==='thumb'?'thumb':'web';$source=photoAbsolutePath($relative);if(!$source||!is_file($source))return null;$info=@getimagesize($source);if(!$info)return null;$mime=(string)($info['mime']??'');$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??'';if($ext==='')return null;
    $dirRel=str_replace('\\','/',dirname($relative));$base=pathinfo(basename($relative),PATHINFO_FILENAME);if(str_ends_with($base,'.thumb')||str_ends_with($base,'.web'))return $relative;$targetRel=$dirRel.'/'.$base.'.'.$variant.'.'.$ext;$target=photoAbsolutePath($targetRel);if(!$target)return null;
    if(is_file($target)&&filesize($target)>0&&filemtime($target)>=filemtime($source))return $targetRel;if(!logoOptimizationAvailable())return null;[$maxW,$maxH]=photoVariantSpec($variant);$ok=false;
    try{
        if(extension_loaded('gd')&&function_exists('imagecreatetruecolor')){$src=null;if($mime==='image/jpeg'&&function_exists('imagecreatefromjpeg'))$src=@imagecreatefromjpeg($source);elseif($mime==='image/png'&&function_exists('imagecreatefrompng'))$src=@imagecreatefrompng($source);elseif($mime==='image/webp'&&function_exists('imagecreatefromwebp'))$src=@imagecreatefromwebp($source);if($src){$sw=imagesx($src);$sh=imagesy($src);$scale=min($maxW/max(1,$sw),$maxH/max(1,$sh),1.0);$tw=max(1,(int)round($sw*$scale));$th=max(1,(int)round($sh*$scale));$dst=imagecreatetruecolor($tw,$th);if($mime==='image/png'||$mime==='image/webp'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$tw,$th,$transparent);}if(imagecopyresampled($dst,$src,0,0,0,0,$tw,$th,$sw,$sh)){if($mime==='image/jpeg'&&function_exists('imagejpeg'))$ok=@imagejpeg($dst,$target,$variant==='thumb'?78:84);elseif($mime==='image/png'&&function_exists('imagepng'))$ok=@imagepng($dst,$target,7);elseif($mime==='image/webp'&&function_exists('imagewebp'))$ok=@imagewebp($dst,$target,$variant==='thumb'?78:82);}imagedestroy($dst);imagedestroy($src);}}
        if(!$ok&&class_exists('Imagick')){$im=new Imagick($source);if($im->getNumberImages()>1)$im->setIteratorIndex(0);$sw=$im->getImageWidth();$sh=$im->getImageHeight();$scale=min($maxW/max(1,$sw),$maxH/max(1,$sh),1.0);$tw=max(1,(int)round($sw*$scale));$th=max(1,(int)round($sh*$scale));if($tw!==$sw||$th!==$sh)$im->resizeImage($tw,$th,Imagick::FILTER_LANCZOS,1,true);$im->setImagePage(0,0,0,0);$im->stripImage();if($mime==='image/jpeg'){$im->setImageFormat('jpeg');$im->setImageCompressionQuality($variant==='thumb'?78:84);}elseif($mime==='image/png'){$im->setImageFormat('png');$im->setOption('png:compression-level','7');}else{$im->setImageFormat('webp');$im->setImageCompressionQuality($variant==='thumb'?78:82);}$ok=$im->writeImage($target);$im->clear();$im->destroy();}
    }catch(Throwable $e){$ok=false;}
    if($ok&&is_file($target)&&filesize($target)>0){@chmod($target,0660);return $targetRel;}@unlink($target);return null;
}
function photoDisplayRelative(string $relative,string $variant='web'): string { return photoDerivativeRelative($relative,$variant)??$relative; }
function photoUrl(int $photoId,string $variant='thumb'): string { $variant=in_array($variant,['thumb','web','original'],true)?$variant:'thumb';return '?image='.$photoId.'&variant='.$variant; }
function ensurePhotoDerivatives(string $relative): array { return ['thumb'=>photoDerivativeRelative($relative,'thumb'),'web'=>photoDerivativeRelative($relative,'web')]; }
function deletePhotoFile(string $relative): void {
    $paths=[$relative];foreach(['thumb','web'] as $v){$source=photoAbsolutePath($relative);if($source&&is_file($source)){$info=@getimagesize($source);$mime=(string)($info['mime']??'');$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??'';if($ext!==''){$dirRel=str_replace('\\','/',dirname($relative));$base=pathinfo(basename($relative),PATHINFO_FILENAME);$paths[]=$dirRel.'/'.$base.'.'.$v.'.'.$ext;}}}
    foreach(array_unique($paths) as $rel){$abs=photoAbsolutePath($rel);if($abs&&is_file($abs))@unlink($abs);} $abs=photoAbsolutePath($relative);$dir=$abs?dirname($abs):'';if($dir!==''&&$dir!==IMAGE_DIR&&is_dir($dir)){$left=array_diff(scandir($dir)?:[],['.','..','index.html']);if(!$left)@rmdir($dir);}
}
function storeUploadedPhotos(PDO $db,int $sid,array $car,?array $files,array $captions=[]): int {
    if(!$files)return 0; $list=normalizeFilesArray($files); if(count($list)>20)throw new RuntimeException(t('img.max_20_photos'));
    $folder=photoFolderName($car); $dir=IMAGE_DIR.'/'.$folder; if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException(t('img.photo_dir_failed')); if(!is_file(IMAGE_DIR.'/index.html'))@file_put_contents(IMAGE_DIR.'/index.html',''); if(!is_file($dir.'/index.html'))@file_put_contents($dir.'/index.html','');
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; $ins=$db->prepare("INSERT INTO service_photos(service_id,car_id,file_path,original_name,mime_type,file_size,caption) VALUES(?,?,?,?,?,?,?)"); $count=0;$captionIndex=0;
    foreach($list as $f){ $err=(int)($f['error']??UPLOAD_ERR_NO_FILE); if($err===UPLOAD_ERR_NO_FILE){$captionIndex++;continue;} if($err!==UPLOAD_ERR_OK){$msg=in_array($err,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)?t('img.photo_too_large_server'):t('img.photo_upload_failed',['code'=>$err]);throw new RuntimeException($msg);} if((int)$f['size']>15*1024*1024)throw new RuntimeException(t('img.photo_over_15mb')); $tmp=(string)$f['tmp_name']; $img=@getimagesize($tmp); if(!$img)throw new RuntimeException(t('img.photo_invalid_image')); $mime=(string)($img['mime']??''); if(!isset($allowed[$mime]))throw new RuntimeException(t('img.photo_format_unsupported')); $name='huolto-'.$sid.'-'.bin2hex(random_bytes(10)).'.'.$allowed[$mime]; $abs=$dir.'/'.$name; if(!move_uploaded_file($tmp,$abs))throw new RuntimeException(t('img.photo_save_failed')); @chmod($abs,0660); $rel='kuvat/'.$folder.'/'.$name;$caption=trim((string)($captions[$captionIndex]??''));if(mb_strlen($caption)>240)$caption=mb_substr($caption,0,240); try{$ins->execute([$sid,(int)$car['id'],$rel,(string)($f['name']??''),$mime,(int)filesize($abs),$caption]);ensurePhotoDerivatives($rel);}catch(Throwable $e){deletePhotoFile($rel);throw $e;} $count++;$captionIndex++; } return $count;
}

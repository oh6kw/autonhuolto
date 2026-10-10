<?php
declare(strict_types=1);

/**
 * app/mail.php – sähköpostin lähetys ilman ulkoisia kirjastoja.
 * Kaksi tapaa (asetus mail_method): 'smtp' = oma SMTP-asiakas (SSL/TLS, STARTTLS tai salaamaton; AUTH PLAIN/LOGIN),
 * 'php' = palvelimen oma lähetys PHP:n mail()-funktiolla. 'none' = sähköposti pois käytöstä.
 * Vain funktiot; ei suoriteta mitään latauksessa. Ladataan index.php:n alussa.
 */

function mailMethod(array $app): string { $m=(string)($app['mail_method']??'none');return in_array($m,['smtp','php'],true)?$m:'none'; }
/** Poistaa rivinvaihdot ja ohjausmerkit otsikkokentän arvosta (otsikkoinjektion esto). */
function mailHeaderValue(string $v): string { return trim((string)preg_replace('/[\x00-\x1F\x7F]+/',' ',$v)); }
function mailValidAddress(string $a): bool { return $a!==''&&strlen($a)<=254&&filter_var($a,FILTER_VALIDATE_EMAIL)!==false; }
/** Onko lähetys käytössä ja asetettu niin, että viestin voi yrittää lähettää? */
function mailConfigured(array $app): bool {
    $m=mailMethod($app);if($m==='none')return false;
    if(!mailValidAddress(trim((string)($app['mail_from']??''))))return false;
    if($m==='smtp'&&(trim((string)($app['smtp_host']??''))===''||(int)($app['smtp_port']??0)<=0))return false;
    return true;
}
/** Otsikon enkoodaus (RFC 2047) tarvittaessa. */
function mailEncodeHeader(string $text): string {
    $text=mailHeaderValue($text);
    return preg_match('/^[\x20-\x7E]*$/D',$text)?$text:'=?UTF-8?B?'.base64_encode($text).'?=';
}
function mailAddressHeader(string $address,string $name=''): string {
    $address=mailHeaderValue($address);$name=mailHeaderValue($name);
    if($name==='')return '<'.$address.'>';
    $enc=preg_match('/^[\x20-\x7E]*$/D',$name)?'"'.addcslashes($name,'"\\').'"':'=?UTF-8?B?'.base64_encode($name).'?=';
    return $enc.' <'.$address.'>';
}
function mailDomain(string $address): string { $p=strrchr($address,'@');return $p!==false?preg_replace('/[^A-Za-z0-9.-]/','',substr($p,1)):'localhost'; }
/**
 * Rakentaa MIME-viestin. $msg: to (merkkijono tai taulukko), subject, text, html (valinnainen), reply_to (valinnainen),
 * attachments: lista [nimi, mime, sisältö]. Palauttaa [otsikot (taulukko riveinä), runko] – otsikot ilman To/Subject mail()-käyttöä varten erikseen.
 */
function mailBuild(array $app,array $msg): array {
    $from=trim((string)$app['mail_from']);$fromName=(string)($app['mail_from_name']??'');
    $to=array_values(array_filter(array_map('trim',(array)$msg['to'])));
    $boundaryMixed='=_m_'.bin2hex(random_bytes(12));$boundaryAlt='=_a_'.bin2hex(random_bytes(12));
    $h=[];
    $h[]='Date: '.date('r');
    $h[]='From: '.mailAddressHeader($from,$fromName);
    $h[]='To: '.implode(', ',array_map(fn($a)=>mailAddressHeader($a),$to));
    $reply=trim((string)($msg['reply_to']??($app['mail_reply_to']??'')));if(mailValidAddress($reply))$h[]='Reply-To: '.mailAddressHeader($reply);
    $h[]='Subject: '.mailEncodeHeader((string)$msg['subject']);
    $h[]='Message-ID: <'.bin2hex(random_bytes(12)).'@'.mailDomain($from).'>';
    $h[]='MIME-Version: 1.0';
    $h[]='X-Mailer: Autonhuolto '.APP_VERSION;
    $att=(array)($msg['attachments']??[]);$b64=fn(string $d)=>chunk_split(base64_encode($d),76,"\r\n");
    $text=str_replace(["\r\n","\r"],"\n",(string)$msg['text']);$text=str_replace("\n","\r\n",$text);
    $alt='--'.$boundaryAlt."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$b64($text);
    $html=trim((string)($msg['html']??''));
    if($html!=='')$alt.='--'.$boundaryAlt."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$b64($html);
    $alt.='--'.$boundaryAlt."--\r\n";
    if(!$att){
        $h[]='Content-Type: multipart/alternative; boundary="'.$boundaryAlt.'"';
        return [$h,$alt];
    }
    $h[]='Content-Type: multipart/mixed; boundary="'.$boundaryMixed.'"';
    $body='--'.$boundaryMixed."\r\nContent-Type: multipart/alternative; boundary=\"".$boundaryAlt."\"\r\n\r\n".$alt;
    foreach($att as [$name,$mime,$data]){
        $safe=preg_replace('/[^A-Za-z0-9._-]+/','_',(string)$name)?:'liite';
        $body.='--'.$boundaryMixed."\r\nContent-Type: ".mailHeaderValue((string)$mime).'; name="'.$safe."\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"".$safe."\"\r\n\r\n".$b64((string)$data);
    }
    $body.='--'.$boundaryMixed."--\r\n";
    return [$h,$body];
}
/** Lukee SMTP-vastauksen (myös usean rivin). Palauttaa [koodi, teksti]. */
function mailSmtpRead($fp): array {
    $text='';$code=0;
    while(true){
        $line=fgets($fp,2048);
        if($line===false){$meta=stream_get_meta_data($fp);throw new RuntimeException(t($meta['timed_out']?'mail.err_timeout':'mail.err_connection_lost'));}
        $text.=$line;
        if(strlen($line)>=4&&ctype_digit(substr($line,0,3))){$code=(int)substr($line,0,3);if($line[3]===' ')break;}
        else break;
    }
    return [$code,trim($text)];
}
function mailSmtpCmd($fp,string $cmd,array $okCodes,bool $secret=false): array {
    fwrite($fp,$cmd."\r\n");
    [$code,$text]=mailSmtpRead($fp);
    if(!in_array($code,$okCodes,true))throw new RuntimeException(t('mail.err_smtp_reply',['cmd'=>$secret?'(salattu)':strtok($cmd,' '),'reply'=>$text]));
    return [$code,$text];
}
/** Lähettää viestin SMTP:llä. Heittää RuntimeExceptionin ymmärrettävällä virheellä. */
function mailSendSmtp(array $app,array $msg): void {
    $host=trim((string)$app['smtp_host']);$port=(int)$app['smtp_port'];$sec=(string)($app['smtp_security']??'tls');if(!in_array($sec,['ssl','tls','none'],true))$sec='tls';
    $verify=(string)($app['smtp_verify']??'1')!=='0';
    $from=trim((string)$app['mail_from']);$to=array_values(array_filter(array_map('trim',(array)$msg['to'])));
    foreach($to as $a)if(!mailValidAddress($a))throw new RuntimeException(t('mail.err_bad_recipient',['address'=>$a]));
    $ctx=stream_context_create(['ssl'=>['verify_peer'=>$verify,'verify_peer_name'=>$verify,'allow_self_signed'=>!$verify,'peer_name'=>$host,'SNI_enabled'=>true]]);
    $errno=0;$errstr='';
    $fp=@stream_socket_client(($sec==='ssl'?'ssl://':'tcp://').$host.':'.$port,$errno,$errstr,20,STREAM_CLIENT_CONNECT,$ctx);
    if(!$fp)throw new RuntimeException(t('mail.err_connect',['host'=>$host,'port'=>$port,'error'=>$errstr!==''?$errstr:('#'.$errno)]));
    stream_set_timeout($fp,30);
    try{
        [$c,$t]=mailSmtpRead($fp);if($c!==220)throw new RuntimeException(t('mail.err_smtp_reply',['cmd'=>'CONNECT','reply'=>$t]));
        $ehlo='EHLO '.(preg_replace('/[^A-Za-z0-9.-]/','',gethostname()?:'')?:'localhost');
        [, $caps]=mailSmtpCmd($fp,$ehlo,[250]);
        if($sec==='tls'){
            if(stripos($caps,'STARTTLS')===false)throw new RuntimeException(t('mail.err_no_starttls'));
            mailSmtpCmd($fp,'STARTTLS',[220]);
            $cryptoOk=@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if($cryptoOk!==true)throw new RuntimeException(t('mail.err_tls_failed'));
            [, $caps]=mailSmtpCmd($fp,$ehlo,[250]);
        }
        $user=(string)($app['smtp_user']??'');$pass=(string)($app['smtp_pass']??'');
        if($user!==''){
            if(preg_match('/\bAUTH\b[^\r\n]*\bPLAIN\b/i',$caps)){
                mailSmtpCmd($fp,'AUTH PLAIN '.base64_encode("\0".$user."\0".$pass),[235],true);
            }elseif(preg_match('/\bAUTH\b[^\r\n]*\bLOGIN\b/i',$caps)){
                mailSmtpCmd($fp,'AUTH LOGIN',[334]);mailSmtpCmd($fp,base64_encode($user),[334],true);mailSmtpCmd($fp,base64_encode($pass),[235],true);
            }else throw new RuntimeException(t('mail.err_no_auth'));
        }
        mailSmtpCmd($fp,'MAIL FROM:<'.mailHeaderValue($from).'>',[250]);
        $rcpts=$to;$bcc=trim((string)($msg['bcc']??''));if(mailValidAddress($bcc)&&!in_array($bcc,$rcpts,true))$rcpts[]=$bcc;
        foreach($rcpts as $a)mailSmtpCmd($fp,'RCPT TO:<'.mailHeaderValue($a).'>',[250,251]);
        mailSmtpCmd($fp,'DATA',[354]);
        [$headers,$body]=mailBuild($app,$msg);
        $data=implode("\r\n",$headers)."\r\n\r\n".$body;
        $data=preg_replace('/(?<!\r)\n/',"\r\n",$data);
        $data=preg_replace('/^\./m','..',$data); /* pisteen kaksinnus (dot-stuffing) */
        fwrite($fp,rtrim($data,"\r\n")."\r\n.\r\n");
        [$c,$t]=mailSmtpRead($fp);if($c!==250)throw new RuntimeException(t('mail.err_smtp_reply',['cmd'=>'DATA','reply'=>$t]));
        @fwrite($fp,"QUIT\r\n");
    }finally{ if(is_resource($fp))@fclose($fp); }
}
/** Lähettää viestin palvelimen omalla lähetyksellä (PHP mail()). */
function mailSendPhp(array $app,array $msg): void {
    if(!function_exists('mail'))throw new RuntimeException(t('mail.err_php_mail_missing'));
    $to=array_values(array_filter(array_map('trim',(array)$msg['to'])));
    foreach($to as $a)if(!mailValidAddress($a))throw new RuntimeException(t('mail.err_bad_recipient',['address'=>$a]));
    [$headers,$body]=mailBuild($app,$msg);
    $headers=array_values(array_filter($headers,fn($l)=>!preg_match('/^(To|Subject):/i',$l)));
    $bcc=trim((string)($msg['bcc']??''));if(mailValidAddress($bcc))$headers[]='Bcc: '.mailAddressHeader($bcc);
    $ok=@mail(implode(', ',$to),mailEncodeHeader((string)$msg['subject']),$body,implode("\r\n",$headers),'-f'.trim((string)$app['mail_from']));
    if(!$ok)throw new RuntimeException(t('mail.err_php_mail_failed'));
}
function mailSend(array $app,array $msg): void {
    if(!mailConfigured($app))throw new RuntimeException(t('mail.err_not_configured'));
    $msg['to']=array_values(array_filter(array_map('trim',(array)$msg['to'])));
    if(!$msg['to'])throw new RuntimeException(t('mail.err_no_recipient'));
    if(trim((string)$msg['subject'])==='')throw new RuntimeException(t('mail.err_no_subject'));
    if((string)($app['mail_copy_self']??'0')==='1'&&empty($msg['bcc']))$msg['bcc']=trim((string)$app['mail_from']);
    if(mailMethod($app)==='smtp')mailSendSmtp($app,$msg);else mailSendPhp($app,$msg);
}

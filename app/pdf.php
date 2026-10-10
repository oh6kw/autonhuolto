<?php
declare(strict_types=1);

/**
 * app/pdf.php – oma pieni PDF-kirjoitin ja laskun PDF (sähköpostin liitteeksi). Ei ulkoisia kirjastoja.
 * Käyttää PDF:n vakiofontteja (Helvetica, WinAnsi), joten ä, ö, å ja € toimivat; muut merkit korvataan kysymysmerkillä.
 * Laskun asettelu vastaa selaimen tulostetta: logo/nimi, laskutettava, laskun tiedot, työ/ajoneuvo-palkki, rivitaulukko, yhteenveto,
 * myyjän tiedot ja alareunaan suomalainen tilisiirtolomake pankkiviivakoodeineen (vain avoimelle laskulle).
 * Vain funktiot ja luokka; mitään ei suoriteta latauksessa. Ladataan index.php:n alussa.
 */

/* Helvetica-fonttien merkkileveydet (1/1000 em) koodeille 32–255 (Windows-1252). */
const PDF_W_REGULAR=[278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,0,222,556,333,1000,556,556,333,1000,667,333,1000,0,611,0,0,222,222,333,333,350,556,1000,333,1000,500,333,944,0,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500];
const PDF_W_BOLD=[278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,0,278,556,500,1000,556,556,333,1000,667,333,1000,0,611,0,0,278,278,500,500,350,556,1000,333,1000,556,333,944,0,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556];

final class PdfDocument {
    public const PT=2.834645669; /* pistettä / mm */
    public const W=210.0;
    public const H=297.0;
    private array $pages=[];
    private string $buf='';
    private array $images=[];
    private string $title;
    public function __construct(string $title='') { $this->title=$title; }
    public function addPage(): void { $this->pages[]=$this->buf;$this->buf=''; }
    public function pageCount(): int { return count($this->pages)+1; }
    private static function n(float $v): string { return rtrim(rtrim(number_format($v,3,'.',''),'0'),'.')?:'0'; }
    private function px(float $mm): string { return self::n($mm*self::PT); }
    private function py(float $mm): string { return self::n((self::H-$mm)*self::PT); }
    private static function color(array $c,bool $stroke=false): string { return self::n($c[0]/255).' '.self::n($c[1]/255).' '.self::n($c[2]/255).($stroke?' RG':' rg'); }
    /** UTF-8 → Windows-1252 (tavut). */
    public static function enc(string $s): string {
        $s=str_replace(["\u{2013}","\u{2014}","\u{00A0}","\u{2009}","\u{202F}"],['-','-',' ',' ',' '],$s);
        $out=@mb_convert_encoding($s,'Windows-1252','UTF-8');return $out===false?$s:$out;
    }
    public static function width(string $s,float $size,bool $bold=false): float {
        $tbl=$bold?PDF_W_BOLD:PDF_W_REGULAR;$w=0;
        foreach(str_split(self::enc($s)) as $ch){$o=ord($ch);$w+=($o>=32&&$o<=255)?($tbl[$o-32]??500):0;}
        return $w*$size/1000/self::PT; /* mm */
    }
    /** Rivittää tekstin annettuun leveyteen (mm). Palauttaa rivit. */
    public static function wrap(string $s,float $size,bool $bold,float $maxW): array {
        $lines=[];
        foreach(preg_split('/\r\n|\r|\n/',$s) as $para){
            $para=trim($para);if($para===''){$lines[]='';continue;}
            $cur='';
            foreach(preg_split('/\s+/u',$para) as $word){
                $try=$cur===''?$word:$cur.' '.$word;
                if(self::width($try,$size,$bold)<=$maxW){$cur=$try;continue;}
                if($cur!==''){$lines[]=$cur;$cur='';}
                while(self::width($word,$size,$bold)>$maxW&&mb_strlen($word)>1){
                    $cut=mb_strlen($word);while($cut>1&&self::width(mb_substr($word,0,$cut),$size,$bold)>$maxW)$cut--;
                    $lines[]=mb_substr($word,0,$cut);$word=mb_substr($word,$cut);
                }
                $cur=$word;
            }
            $lines[]=$cur;
        }
        return $lines;
    }
    /** Teksti; $y on rivin yläreuna (mm). $align: l = x vasen reuna, r = x oikea reuna, c = x keskikohta. */
    public function text(float $x,float $y,string $s,float $size,bool $bold=false,string $align='l',array $color=[0,0,0]): void {
        if($s==='')return;
        $w=self::width($s,$size,$bold);$x0=$align==='r'?$x-$w:($align==='c'?$x-$w/2:$x);
        $base=$y+$size*0.3528*0.82;
        $this->buf.='BT '.self::color($color).' /'.($bold?'F2':'F1').' '.self::n($size).' Tf '.$this->px($x0).' '.$this->py($base).' Td <'.bin2hex(self::enc($s)).'> Tj ET'."\n";
    }
    /** Pystysuuntainen teksti (luetaan alhaalta ylös); $x,$y = tekstin alkupiste (vasen alakulma, mm). */
    public function textUp(float $x,float $y,string $s,float $size,bool $bold=false): void {
        $this->buf.='q 0 1 -1 0 '.$this->px($x).' '.$this->py($y).' cm BT 0 g /'.($bold?'F2':'F1').' '.self::n($size).' Tf 0 0 Td <'.bin2hex(self::enc($s)).'> Tj ET Q'."\n";
    }
    public function rect(float $x,float $y,float $w,float $h,?array $fill=null,?array $stroke=null,float $lw=0.5): void {
        $op=$fill&&$stroke?'B':($fill?'f':'S');
        $this->buf.=($fill?self::color($fill)."\n":'').($stroke?self::color($stroke,true).' '.self::n($lw)." w\n":'').$this->px($x).' '.$this->py($y+$h).' '.$this->px($w).' '.self::n($h*self::PT).' re '.$op."\n";
    }
    public function line(float $x1,float $y1,float $x2,float $y2,float $lw=0.5,array $color=[0,0,0],?array $dash=null): void {
        $this->buf.='q '.self::color($color,true).' '.self::n($lw).' w '.($dash?'['.implode(' ',array_map(fn($d)=>self::n($d),$dash)).'] 0 d ':'').$this->px($x1).' '.$this->py($y1).' m '.$this->px($x2).' '.$this->py($y2)." l S Q\n";
    }
    /** Lisää kuvan (RGB, raaka, deflate-pakattu); palauttaa kuvan nimen. */
    public function addImage(int $w,int $h,string $rgbRaw): string {
        $name='Im'.(count($this->images)+1);$this->images[$name]=['w'=>$w,'h'=>$h,'data'=>$rgbRaw];return $name;
    }
    public function image(string $name,float $x,float $y,float $w,float $h): void {
        $this->buf.='q '.$this->px($w).' 0 0 '.self::n($h*self::PT).' '.$this->px($x).' '.$this->py($y+$h).' cm /'.$name." Do Q\n";
    }
    private static function stream(string $data,string $extra=''): string {
        $filter='';if(function_exists('gzcompress')){$z=gzcompress($data,6);if($z!==false){$data=$z;$filter=' /Filter /FlateDecode';}}
        return '<< /Length '.strlen($data).$filter.$extra.' >>'."\nstream\n".$data."\nendstream";
    }
    public function output(): string {
        $pages=$this->pages;$pages[]=$this->buf;
        $objs=[];$add=function(string $body) use (&$objs): int { $objs[]=$body;return count($objs); };
        $add('<< /Type /Catalog /Pages 2 0 R >>');           /* 1 */
        $add('PAGES_PLACEHOLDER');                             /* 2 */
        $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');      /* 3 */
        $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'); /* 4 */
        $imgRefs=[];
        foreach($this->images as $name=>$im)$imgRefs[$name]=$add(self::stream($im['data'],' /Type /XObject /Subtype /Image /Width '.$im['w'].' /Height '.$im['h'].' /ColorSpace /DeviceRGB /BitsPerComponent 8'));
        $xo='';foreach($imgRefs as $name=>$ref)$xo.='/'.$name.' '.$ref.' 0 R ';
        $kids=[];
        foreach($pages as $content){
            $c=$add(self::stream($content));
            $p=$add('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::n(self::W*self::PT).' '.self::n(self::H*self::PT).'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'.($xo!==''?' /XObject << '.$xo.'>>':'').' >> /Contents '.$c.' 0 R >>');
            $kids[]=$p.' 0 R';
        }
        $objs[1]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
        $info=$add('<< /Title <'.bin2hex(self::enc($this->title)).'> /Producer (Autonhuolto) /CreationDate (D:'.date('YmdHis').') >>');
        $out="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offs=[];
        foreach($objs as $i=>$body){$offs[$i+1]=strlen($out);$out.=($i+1)." 0 obj\n".$body."\nendobj\n";}
        $xref=strlen($out);$out.="xref\n0 ".(count($objs)+1)."\n0000000000 65535 f \n";
        foreach($offs as $o)$out.=sprintf("%010d 00000 n \n",$o);
        $out.="trailer\n<< /Size ".(count($objs)+1).' /Root 1 0 R /Info '.$info." 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        return $out;
    }
}

/** Lataa logon PDF:ään: palauttaa [leveys px, korkeus px, RGB-data (deflate)] tai null. Läpinäkyvyys litistetään valkoiseen. */
function pdfLogoData(string $logoRel): ?array {
    if(!extension_loaded('gd'))return null;
    $rel=logoDisplayRelative($logoRel,'web');$abs=logoAbsolutePath($rel);if(!$abs||!is_file($abs))return null;
    $info=@getimagesize($abs);if(!$info)return null;$mime=(string)($info['mime']??'');
    $img=null;if($mime==='image/jpeg'&&function_exists('imagecreatefromjpeg'))$img=@imagecreatefromjpeg($abs);elseif($mime==='image/png'&&function_exists('imagecreatefrompng'))$img=@imagecreatefrompng($abs);elseif($mime==='image/webp'&&function_exists('imagecreatefromwebp'))$img=@imagecreatefromwebp($abs);
    if(!$img)return null;
    $sw=imagesx($img);$sh=imagesy($img);$scale=min(1.0,700/max(1,$sw));$tw=max(1,(int)round($sw*$scale));$th=max(1,(int)round($sh*$scale));
    $dst=imagecreatetruecolor($tw,$th);imagefill($dst,0,0,imagecolorallocate($dst,255,255,255));imagealphablending($dst,true);
    imagecopyresampled($dst,$img,0,0,0,0,$tw,$th,$sw,$sh);
    $raw='';for($y=0;$y<$th;$y++)for($x=0;$x<$tw;$x++){$c=imagecolorat($dst,$x,$y);$raw.=chr(($c>>16)&255).chr(($c>>8)&255).chr($c&255);}
    imagedestroy($dst);imagedestroy($img);
    return [$tw,$th,$raw];
}

/** Laskun PDF merkkijonona. Heittää RuntimeExceptionin, jos laskua ei löydy. */
function invoicePdf(PDO $db,array $app,int $invoiceId): string {
    $st=$db->prepare("SELECT i.*,s.title,s.service_date,s.odometer,c.reg_plate,c.nickname,c.make,c.model FROM invoices i JOIN services s ON s.id=i.service_id JOIN cars c ON c.id=s.car_id WHERE i.id=?");$st->execute([$invoiceId]);$inv=$st->fetch();
    if(!$inv)throw new RuntimeException(t('print.err_invoice_not_found'));
    $st=$db->prepare("SELECT * FROM invoice_lines WHERE invoice_id=? ORDER BY sort_order,id");$st->execute([$invoiceId]);$lines=$st->fetchAll();
    $net=$vat=0.0;$vatGroups=[];foreach($lines as $l){$n=(float)$l['qty']*(float)$l['unit_price_net'];$v=$n*(float)$l['vat_rate']/100;$net+=$n;$vat+=$v;$vk=number_format((float)$l['vat_rate'],3,'.','');$vatGroups[$vk]=($vatGroups[$vk]??0)+$v;}ksort($vatGroups,SORT_NUMERIC);
    $gross=$net+$vat;$ref=invoiceRef($inv);
    $pdf=new PdfDocument(t('print.title_invoice',['number'=>$inv['invoice_number'],'car'=>($inv['reg_plate']?:$inv['nickname'])]));
    $L=15.0;$R=195.0;$W=$R-$L;$bottom=282.0;$grey=[90,90,90];$lightLine=[205,205,205];
    $mon=fn(float $v)=>number_format($v,2,',',' ').' €';$dec=fn(float $v,int $d=2)=>number_format($v,$d,',',' ');
    $payForm=trim((string)$inv['seller_iban'])!==''&&invoiceShowsPaymentForm($inv);
    $giroH=68.0+5.0+17.0; /* lomake + leikkausviiva + viivakoodialue */

    /* --- Ylälaita: logo tai nimi, laskutettava ja laskun tiedot --- */
    $y=$L;$leftBottom=$y;
    $logoRel=trim((string)($inv['seller_logo_path']??''));if($logoRel==='')$logoRel=(string)($app['logo_path']??'');
    $logoShown=false;
    if(($app['show_logo_invoice']??'1')==='1'&&$logoRel!==''){
        $ld=pdfLogoData($logoRel);
        if($ld){[$lw,$lh,$raw]=$ld;$maxW=62.0;$maxH=24.0;$k=min($maxW/$lw,$maxH/$lh);$mw=$lw*$k;$mh=$lh*$k;
            $name=$pdf->addImage($lw,$lh,$raw);$pdf->image($name,$L,$y,$mw,$mh);$leftBottom=$y+$mh+4;$logoShown=true;}
    }
    if(!$logoShown){$pdf->text($L,$y,(string)$inv['seller_name'],17,true);$leftBottom=$y+9;}
    $cy=$leftBottom+3;
    $pdf->text($L,$cy,mb_strtoupper(t('print.inv_billed_to')),7.5,true,'l',$grey);$cy+=4.2;
    $pdf->text($L,$cy,(string)($inv['customer_name']?:'—'),11,true);$cy+=5.2;
    $custLines=[];foreach(preg_split('/\r\n|\r|\n/',(string)$inv['customer_address']) as $a)if(trim($a)!=='')$custLines[]=trim($a);
    if(trim((string)$inv['customer_business_id'])!=='')$custLines[]=t('print.business_id',['id'=>(string)$inv['customer_business_id']]);
    if(trim((string)$inv['customer_email'])!=='')$custLines[]=(string)$inv['customer_email'];
    foreach($custLines as $cl){$pdf->text($L,$cy,$cl,10);$cy+=4.6;}
    $rx=118.0;$ry=$y;
    $pdf->text($R,$ry,t('print.invoice_kicker'),24,true,'r');$ry+=13;
    $termDays=max(0,(int)round((strtotime((string)$inv['due_date'])-strtotime((string)$inv['issue_date']))/86400));
    $meta=[[t('print.inv_number'),(string)$inv['invoice_number']],[t('print.inv_date'),fiDate($inv['issue_date'])],[t('print.inv_terms'),t('print.inv_terms_days',['n'=>$termDays])],[t('print.inv_due_date'),fiDate($inv['due_date'])]];
    if($ref!=='')$meta[]=[t('print.inv_reference'),invoiceReferenceFormatted($ref)];
    foreach($meta as [$ml,$mv]){$pdf->text($rx,$ry,$ml,9,false,'l',$grey);$pdf->text($R,$ry,$mv,10,true,'r');$ry+=5.4;}
    $y=max($cy,$ry)+4;

    /* --- Työ / ajoneuvo -palkki --- */
    $veh=trim(($inv['reg_plate']?:$inv['nickname']).' · '.$inv['make'].' '.$inv['model']);
    $workText=$veh.' · '.$inv['title'].' · '.fiDate($inv['service_date']).' · '.recordedKm((int)$inv['odometer']);
    $label=mb_strtoupper(t('print.inv_work_vehicle'));$labW=PdfDocument::width($label,7.5,true)+3;
    $wl=PdfDocument::wrap($workText,9.5,false,$W-4-$labW);$barH=max(8.0,count($wl)*4.6+3.4);
    $pdf->rect($L,$y,$W,$barH,[220,220,220]);
    $pdf->text($L+2,$y+($barH-2.6)/2-0.2,$label,7.5,true,'l',$grey);
    foreach($wl as $i=>$ln)$pdf->text($L+2+$labW,$y+1.7+$i*4.6,$ln,9.5);
    $y+=$barH+5;

    /* --- Rivitaulukko --- */
    $cols=[['x'=>$L,'w'=>82.0,'a'=>'l'],['x'=>$L+82.0,'w'=>22.0,'a'=>'l'],['x'=>$L+104.0,'w'=>22.0,'a'=>'r'],['x'=>$L+126.0,'w'=>14.0,'a'=>'r'],['x'=>$L+140.0,'w'=>20.0,'a'=>'r'],['x'=>$L+160.0,'w'=>20.0,'a'=>'r']];
    $heads=[t('print.inv_col_description'),t('print.inv_col_qty'),t('print.inv_col_unit_price'),t('print.inv_col_vat'),t('print.inv_col_net'),t('print.inv_col_gross')];
    $header=function() use (&$pdf,&$y,$cols,$heads,$grey,$L,$R): void {
        foreach($cols as $i=>$c){$px=$c['a']==='r'?$c['x']+$c['w']-1.5:$c['x']+1.5;$pdf->text($px,$y,mb_strtoupper($heads[$i]),7.5,true,$c['a'],$grey);}
        $y+=5;$pdf->line($L,$y,$R,$y,0.8,[40,40,40]);$y+=1.2;
    };
    $header();
    foreach($lines as $l){
        $ln=(float)$l['qty']*(float)$l['unit_price_net'];$lg=$ln*(1+(float)$l['vat_rate']/100);
        $dl=PdfDocument::wrap((string)$l['description'],9,false,$cols[0]['w']-3);$rowH=max(1,count($dl))*4.2+3.2;
        if($y+$rowH>$bottom-8){$pdf->addPage();$y=$L;$header();}
        foreach($dl as $i=>$d)$pdf->text($cols[0]['x']+1.5,$y+1.6+$i*4.2,$d,9);
        $vals=[null,$dec((float)$l['qty'],2).' '.unitLabel((string)$l['unit']),$mon((float)$l['unit_price_net']),$dec((float)$l['vat_rate'],1).' %',$mon($ln),$mon($lg)];
        foreach($vals as $i=>$v){if($v===null)continue;$c=$cols[$i];$px=$c['a']==='r'?$c['x']+$c['w']-1.5:$c['x']+1.5;$pdf->text($px,$y+1.6,$v,9,$i===5,$c['a']);}
        $y+=$rowH;$pdf->line($L,$y,$R,$y,0.3,$lightLine);
    }
    $y+=5;

    /* --- Yhteenveto ja MobilePay --- */
    $sumRows=[[t('print.inv_total_net'),$mon($net),false]];foreach($vatGroups as $vr=>$vv)$sumRows[]=[t('print.vat_row',['rate'=>$dec((float)$vr,1)]),$mon($vv),false];
    $sumH=count($sumRows)*5.2+10.0;
    $hasMobilePay=(int)($inv['seller_mobilepay_enabled']??0)===1&&trim((string)($inv['seller_mobilepay_number']??''))!=='';
    if($y+$sumH>$bottom-8){$pdf->addPage();$y=$L;}
    $sx=$R-72.0;$sy=$y;
    foreach($sumRows as [$sl,$sv]){$pdf->text($sx,$sy,$sl,9.5);$pdf->text($R,$sy,$sv,9.5,true,'r');$sy+=5.2;}
    $pdf->line($sx,$sy+0.6,$R,$sy+0.6,0.8,[40,40,40]);$sy+=2.6;
    $pdf->text($sx,$sy,t('print.inv_payable'),12,true);$pdf->text($R,$sy,$mon($gross),12,true,'r');$sy+=7.5;
    if($hasMobilePay){
        $mp=['MobilePay '.(string)$inv['seller_mobilepay_number'].(trim((string)($inv['seller_mobilepay_name']??''))!==''?' · '.$inv['seller_mobilepay_name']:''),t('print.mobilepay_message',['number'=>(string)$inv['invoice_number']]).' · '.t('print.mobilepay_amount',['amount'=>$mon($gross)])];
        $pdf->rect($L,$y,92.0,12.0,[246,246,246],[200,200,200],0.4);
        $pdf->text($L+2.5,$y+1.8,$mp[0],9,true);
        $pdf->text($L+2.5,$y+6.6,$mp[1],8);
    }
    $y=max($sy,$y+($hasMobilePay?12.0:0))+3;

    /* --- Lisätietoja (huomautus) --- */
    $note=trim((string)($inv['note']??''));
    if($note!==''){
        $nl=PdfDocument::wrap($note,9,false,$W);$need=4.6+count($nl)*4.2+3;
        if($y+$need>$bottom-8){$pdf->addPage();$y=$L;}
        $pdf->text($L,$y,mb_strtoupper(t('print.inv_note')),7.5,true,'l',$grey);$y+=4.4;
        foreach($nl as $i=>$ln)$pdf->text($L,$y+$i*4.2,$ln,9);
        $y+=count($nl)*4.2+3;
    }

    /* --- Myyjän tiedot --- */
    $parts=[];foreach([['company',$inv['seller_name']],['phone',$inv['seller_phone']],['address',str_replace(["\r\n","\n"],', ',(string)$inv['seller_address'])],['email',$inv['seller_email']],['business_id',$inv['seller_business_id']]] as [$k,$v])if(trim((string)$v)!=='')$parts[]=t('print.inv_seller_'.$k).': '.$v;
    if($parts){
        $sl=PdfDocument::wrap(implode('   ·   ',$parts),8,false,$W);$need=count($sl)*3.8+3;
        if($y+$need>$bottom-8){$pdf->addPage();$y=$L;}
        $y+=2;$pdf->line($L,$y,$R,$y,0.3,$lightLine);$y+=2;
        foreach($sl as $i=>$ln)$pdf->text($L,$y+$i*3.8,$ln,8,false,'l',$grey);
        $y+=count($sl)*3.8+2;
    }

    /* --- Tilisiirtolomake sivun alareunaan --- */
    if($payForm){
        if($y+4>$bottom-$giroH)$pdf->addPage();
        pdfDrawGiro($pdf,$app,$inv,$gross,$ref,$L,$bottom-$giroH+1.0,$W);
    }
    return $pdf->output();
}

/** Piirtää suomalaisen tilisiirtolomakkeen ($top = leikkausviivan y, mm) ja sen alle viivakoodin. */
function pdfDrawGiro(PdfDocument $pdf,array $app,array $inv,float $gross,string $ref,float $L,float $top,float $W): void {
    $lab=fn(string $k)=>t('print.giro_'.$k);$lw=1.5; $black=[0,0,0];
    /* leikkausviiva */
    $pdf->line($L,$top,$L+$W,$top,1.0,[130,130,130],[3,2]);
    $gy=$top+5.0;
    $fr=[185,392,105,360,350];$sum=array_sum($fr);$cw=[];foreach($fr as $f)$cw[]=$W*$f/$sum;
    $cx=[$L];for($i=0;$i<5;$i++)$cx[]=$cx[$i]+$cw[$i];  /* sarakkeiden vasemmat reunat + oikea reuna */
    $rh=[14.5,17.0,19.0,8.0,9.5];$ry=[$gy];for($i=0;$i<5;$i++)$ry[]=$ry[$i]+$rh[$i];
    $pad=1.6;
    /* viivat (vastaa selaintulosteen ruudukkoa) */
    $pdf->line($cx[0],$ry[1],$cx[5],$ry[1],$lw,$black);                 /* rivin 1 alareuna: koko leveys */
    $pdf->line($cx[0],$ry[2],$cx[2],$ry[2],$lw,$black);                 /* rivin 2 alareuna: sarakkeet 1–2 */
    $pdf->line($cx[2],$ry[3],$cx[5],$ry[3],$lw,$black);                 /* viestin alareuna */
    $pdf->line($cx[0],$ry[4],$cx[5],$ry[4],$lw,$black);                 /* rivin 4 alareuna: koko leveys */
    $pdf->line($cx[0],$ry[5],$cx[5],$ry[5],$lw,$black);                 /* lomakkeen alareuna */
    $pdf->line($cx[1],$ry[0],$cx[1],$ry[5],$lw,$black);                 /* sarakkeen 1 oikea reuna */
    $pdf->line($cx[2],$ry[0],$cx[2],$ry[5],$lw,$black);                 /* sarakkeen 2 oikea reuna */
    $pdf->line($cx[3],$ry[3],$cx[3],$ry[5],$lw,$black);                 /* sarakkeen 3 oikea reuna: rivit 4–5 */
    $pdf->line($cx[4],$ry[4],$cx[4],$ry[5],$lw,$black);                 /* sarakkeen 4 oikea reuna: rivi 5 */
    $label=function(int $row0,int $row1,string $key) use ($pdf,$cx,$ry,$lab,$pad): void {
        $lines=explode("\n",$lab($key));$yy=$ry[$row0]+1.4;
        foreach($lines as $ln){$pdf->text($cx[1]-$pad,$yy,$ln,7.0,false,'r');$yy+=3.1;}
    };
    $label(0,1,'payee_account');$label(1,2,'payee');
    /* rivi 1: IBAN ja BIC */
    $pdf->text($cx[1]+$pad,$ry[0]+1.2,'IBAN',7.0);
    $iban=trim(chunk_split(strtoupper((string)preg_replace('/\s+/','',(string)$inv['seller_iban'])),4,' '));
    $pdf->text($cx[1]+$pad,$ry[0]+5.6,$iban,10.5);
    $pdf->text($cx[2]+$pad,$ry[0]+1.2,'BIC',7.0);$pdf->text($cx[2]+$pad,$ry[0]+5.6,(string)$inv['seller_bic'],10.5);
    /* rivi 2: saaja */
    $payee=[trim((string)$inv['seller_name'])];foreach(preg_split('/\r\n|\r|\n/',(string)$inv['seller_address']) as $a)if(trim($a)!=='')$payee[]=trim($a);
    $yy=$ry[1]+1.6;foreach($payee as $pl){foreach(PdfDocument::wrap($pl,10.0,false,$cw[1]-2*$pad) as $w){$pdf->text($cx[1]+$pad,$yy,$w,10.0);$yy+=4.2;}}
    /* viesti (vain jos viitettä ei ole) */
    $pdf->text($cx[2]+$pad,$ry[1]+1.2,$lab('message'),7.0);
    if($ref==='')$pdf->text($cx[2]+$pad,$ry[1]+5.6,t('print.giro_invoice_ref',['number'=>(string)$inv['invoice_number']]),10.0);
    /* maksaja */
    $pdf->textUp($cx[0]+3.2,$ry[4]-1.2,$lab('title'),6.5,true);
    $pl=explode("\n",$lab('payer'));$yy=$ry[2]+1.4;foreach($pl as $ln){$pdf->text($cx[1]-$pad,$yy,$ln,7.0,false,'r');$yy+=3.1;}
    $sg=explode("\n",$lab('signature'));$yy=$ry[4]-1.6-3.1*count($sg);foreach($sg as $ln){$pdf->text($cx[1]-$pad,$yy,$ln,7.0,false,'r');$yy+=3.1;}
    $payer=[trim((string)$inv['customer_name'])];foreach(preg_split('/\r\n|\r|\n/',(string)$inv['customer_address']) as $a)if(trim($a)!=='')$payer[]=trim($a);
    $yy=$ry[2]+1.6;foreach($payer as $pl2){foreach(PdfDocument::wrap($pl2,10.0,false,$cw[1]-2*$pad) as $w){$pdf->text($cx[1]+$pad,$yy,$w,10.0);$yy+=4.2;}}
    $pdf->line($cx[1]+2.0,$ry[4]-3.0,$cx[2]-2.0,$ry[4]-3.0,0.5,$black);
    /* viite */
    $rl=explode("\n",$lab('reference'));$yy=$ry[3]+1.0;foreach($rl as $ln){$pdf->text($cx[2]+1.2,$yy,$ln,7.0);$yy+=3.1;}
    $pdf->text($cx[3]+$pad,$ry[3]+1.6,invoiceReferenceFormatted($ref),10.5);
    /* rivi 5: tililtä, eräpäivä, summa */
    $fl=explode("\n",$lab('from_account'));$yy=$ry[4]+1.0;foreach($fl as $ln){$pdf->text($cx[1]-$pad,$yy,$ln,7.0,false,'r');$yy+=3.1;}
    $dl=explode("\n",$lab('due'));$yy=$ry[4]+1.0;foreach($dl as $ln){$pdf->text($cx[2]+1.2,$yy,$ln,7.0);$yy+=3.1;}
    $pdf->text($cx[3]+$pad,$ry[4]+2.6,fiDate((string)$inv['due_date']),11.5);
    $pdf->text($cx[4]+$pad,$ry[4]+2.8,$lab('sum'),7.0);
    $pdf->text($cx[5]-$pad,$ry[4]+2.6,number_format($gross,2,',',' ').' EUR',11.5,false,'r');
    /* alaosa: viivakoodi ja kiinteä teksti */
    $by=$ry[5]+3.0;
    $digits=invoiceBarcodeDigitsFor($app,$inv,$gross);
    if($digits!==''){
        $bc=code128cBars($digits);$mod=0.33;
        foreach($bc['bars'] as [$bx,$bw])$pdf->rect($L+$bx*$mod,$by,$bw*$mod,14.0,[0,0,0]);
    }
    $lx=$L+$W-60.0;$yy=$by;
    foreach([$lab('legal_fi'),$lab('legal_sv')] as $para){foreach(PdfDocument::wrap($para,6.5,false,60.0) as $w){$pdf->text($lx,$yy,$w,6.5);$yy+=2.9;}$yy+=0.8;}
    $pdf->text($L+$W,$yy+0.4,'PANKKI BANKEN',7.5,true,'r');
}

/** Laskun PDF-tiedoston nimi (vain ASCII). */
function invoicePdfFileName(string $invoiceNumber): string { return 'Lasku_'.(preg_replace('/[^A-Za-z0-9_-]+/','-',$invoiceNumber)?:'lasku').'.pdf'; }

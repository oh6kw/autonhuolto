<?php
declare(strict_types=1);

/**
 * app/vocab.php – kielineutraalit tietokanta-arvot.
 * Tietokantaan tallennetaan valmiit arvot englanninkielisinä koodeina (toimenpide 'replaced', yksikkö 'pcs', laskun tila 'paid' jne.),
 * ja ne näytetään käyttäjälle kielitiedoston (lang/<kieli>/vocab.php) mukaan. Käyttäjän itse kirjoittama teksti
 * (oma kohteen nimi, oma ryhmä, oma yksikkö) tallennetaan sellaisenaan ja näytetään sellaisenaan.
 * Ohjelman itse tekemät merkinnät (esim. varaston alkusaldo) tallennetaan muodossa '@koodi' ja näytetään käännettynä.
 * Ladataan index.php:n alussa (i18n.php:n jälkeen).
 */

/** Sallitut koodit ryhmittäin. Ryhmä näkyy kieliavaimessa: vocab.<ryhmä>.<koodi>. */
function vocabCodes(string $group): array {
    static $codes=[
        'action'=>['replaced','done','inspected','repaired','cleaned','added'],
        'stype'=>['scheduled','repair','inspection','tyres','other'],
        'unit'=>['pcs','l','set','pack','h'],
        'invstatus'=>['draft','sent','paid','credited'],
        'section'=>['engine','interior','other','chassis','driveline','electrical','climate'],
    ];
    return $codes[$group]??[];
}
/** Vanhan (suomenkielisen) kannan tekstit → koodit. Käytetään vain migraatiossa ja vanhan lomakkeen syötteen tunnistamisessa. */
function vocabLegacyFi(string $group): array {
    static $map=[
        'action'=>['Vaihdettu / tehty'=>'replaced','Tehty'=>'done','Tarkastettu'=>'inspected','Korjattu'=>'repaired','Puhdistettu'=>'cleaned','Lisätty / täytetty'=>'added'],
        'stype'=>['Määräaikaishuolto'=>'scheduled','Korjaus'=>'repair','Katsastus'=>'inspection','Rengastyö'=>'tyres','Muu'=>'other'],
        'unit'=>['kpl'=>'pcs','L'=>'l','sarja'=>'set','pkt'=>'pack','h'=>'h'],
        'invstatus'=>['Luonnos'=>'draft','Lähetetty'=>'sent','Maksettu'=>'paid','Hyvitetty'=>'credited'],
        'section'=>['Moottori'=>'engine','Sisätila'=>'interior','Muut'=>'other','Alusta & nesteet'=>'chassis','Voimansiirto'=>'driveline','Sähkö'=>'electrical','Ilmastointi'=>'climate'],
    ];
    return $map[$group]??[];
}
function vocabKey(string $group,string $code): string { return 'vocab.'.$group.'.'.$code; }
/** Koodin nimi käytössä olevalla kielellä. Jos arvo ei ole koodi (käyttäjän oma teksti), se palautetaan sellaisenaan. */
function vocabLabel(string $group,string $value): string {
    return in_array($value,vocabCodes($group),true)?t(vocabKey($group,$value)):$value;
}
/** Koodi => nimi, esim. valintalistoihin. */
function vocabOptions(string $group): array {
    $out=[];foreach(vocabCodes($group) as $code)$out[$code]=t(vocabKey($group,$code));return $out;
}
/** Tunnistaa syötteestä koodin: itse koodi, minkä tahansa kielen nimi tai vanha suomenkielinen teksti. Muuten null. */
function vocabMatch(string $group,string $input): ?string {
    $v=trim($input);if($v==='')return null;
    if(in_array($v,vocabCodes($group),true))return $v;
    $lower=mb_strtolower($v);
    foreach(i18nAvailableLanguages() as $lang){
        $cat=i18nCatalog($lang);
        foreach(vocabCodes($group) as $code)if(isset($cat[vocabKey($group,$code)])&&mb_strtolower($cat[vocabKey($group,$code)])===$lower)return $code;
    }
    foreach(vocabLegacyFi($group) as $text=>$code)if(mb_strtolower($text)===$lower)return $code;
    return null;
}
/** Pakollinen koodi: tuntematon syöte korvataan oletuksella. */
function vocabCode(string $group,string $input,string $default): string {
    return vocabMatch($group,$input)??$default;
}
/** Vapaatekstikentät (yksikkö, ryhmä): tunnettu nimi tallennetaan koodina, muu teksti sellaisenaan. */
function vocabNormalize(string $group,string $input): string {
    $v=trim($input);return vocabMatch($group,$v)??$v;
}

/* ------------------------------ Yksiköt ------------------------------ */
/** Yksikön näkyvä nimi; tyhjä yksikkö tarkoittaa kappaletta. */
function unitLabel(?string $unit): string {
    $u=trim((string)$unit);return vocabLabel('unit',$u===''?'pcs':$u);
}

/* ------------------------------ Toimenpiteet, huoltotyypit, laskun tila ------------------------------ */
function actionLabel(string $code): string { return vocabLabel('action',$code); }
function serviceTypeLabel(string $code): string { return vocabLabel('stype',$code); }
function invoiceStatusLabel(string $code): string { return vocabLabel('invstatus',$code); }

/* ------------------------------ Huoltokohteet ------------------------------ */
/**
 * Kohteen nimi. Valmiin kohteen tyhjä nimi tarkoittaa oletusnimeä kielitiedostosta (item.<tunniste>);
 * käyttäjän antama nimi (oma kohde tai uudelleennimetty valmis kohde) näytetään sellaisenaan.
 */
function itemLabelOf(string $itemKey,?string $label): string {
    $label=trim((string)$label);if($label!=='')return $label;
    $k='item.'.$itemKey;return i18nHas($k)?t($k):$itemKey;
}
/** Valmiin kohteen oletusnimet kaikilla kielillä (nimen palautus oletukseksi tunnistetaan niistä). */
function itemDefaultNames(string $itemKey): array {
    $out=[];foreach(i18nAvailableLanguages() as $lang){$cat=i18nCatalog($lang);if(isset($cat['item.'.$itemKey]))$out[]=mb_strtolower($cat['item.'.$itemKey]);}
    return $out;
}
/** Tallennettava nimi: valmiin kohteen oletusnimi tallennetaan tyhjänä, jolloin se seuraa käyttöliittymän kieltä. */
function itemLabelForStorage(string $itemKey,bool $custom,string $typed): string {
    $typed=trim($typed);
    if(!$custom&&in_array(mb_strtolower($typed),itemDefaultNames($itemKey),true))return '';
    return $typed;
}
/** Jos nimi on jonkin kielen valmiin huoltokohteen oletusnimi ja kohde on kannassa, palauttaa kohteen tunnisteen. */
function standardItemKeyForName(PDO $db,string $name): ?string {
    $lower=mb_strtolower(trim($name));if($lower==='')return null;
    foreach($db->query('SELECT item_key FROM item_catalog WHERE is_custom=0')->fetchAll(PDO::FETCH_COLUMN) as $key)
        if(in_array($lower,itemDefaultNames((string)$key),true))return (string)$key;
    return null;
}
function sectionLabel(?string $section): string {
    $s=trim((string)$section);return vocabLabel('section',$s===''?'other':$s);
}

/* ------------------------------ Ohjelman tekemät merkinnät (@koodi) ------------------------------ */
function noteCodes(): array { return ['initial_balance','stock_correction','initial_km','accepted_deviation','auto_added']; }
/** Muistiinpano näkyviin: '@koodi' käännetään, muu teksti pysyy ennallaan. */
function noteLabel(?string $note): string {
    $n=(string)$note;
    if($n!==''&&$n[0]==='@'&&in_array(substr($n,1),noteCodes(),true))return t('vocab.note.'.substr($n,1));
    return $n;
}
/** Lomakkeelta tullut muistiinpano talteen: ohjelman oma teksti (missä tahansa kielessä) palautuu koodiksi. */
function noteForStorage(string $text): string {
    $v=trim($text);if($v==='')return $text;
    $lower=mb_strtolower($v);
    foreach(i18nAvailableLanguages() as $lang){$cat=i18nCatalog($lang);foreach(noteCodes() as $c)if(isset($cat['vocab.note.'.$c])&&mb_strtolower($cat['vocab.note.'.$c])===$lower)return '@'.$c;}
    return $text;
}
/** Vanhan suomenkielisen kannan muistiinpanoteksti → koodi (migraatio). */
function noteLegacyFi(): array {
    return ['Alkusaldo'=>'@initial_balance','Saldon korjaus Varasto-näkymästä'=>'@stock_correction','Auton lähtökilometrit'=>'@initial_km','Hyväksytty lähdeaineiston poikkeamaksi'=>'@accepted_deviation','Lisätty automaattisesti huollosta'=>'@auto_added'];
}
/** Mekaanikko "Muu" (ulkopuolinen) tallennetaan koodina @other. */
const MECHANIC_OTHER = '@other';
function mechanicLabel(?string $name): string {
    $n=(string)$name;return $n===MECHANIC_OTHER?t('vocab.mechanic_other'):$n;
}
function isMechanicOther(?string $name): bool {
    $n=trim((string)$name);return $n===MECHANIC_OTHER||in_array(mb_strtolower($n),array_map('mb_strtolower',array_filter(array_map(fn($l)=>i18nCatalog($l)['vocab.mechanic_other']??'',i18nAvailableLanguages()))),true);
}
/** Huollon oletusotsikko, jos otsikko on tyhjä. */
function serviceTitleLabel(?string $title,?string $type=''): string {
    $t=trim((string)$title);if($t==='')return t('vocab.service_title_default');
    return $t;
}

/* ------------------------------ Etusivun oletusotsikko ja -alaotsikko ------------------------------ */
/** Tallennettu arvo '@default' tarkoittaa kielen oletustekstiä. Tyhjä alaotsikko tarkoittaa, ettei alaotsikkoa näytetä. */
const HOME_TEXT_DEFAULT = '@default';
function homeTitleText(array $app): string {
    $v=trim((string)($app['home_title']??HOME_TEXT_DEFAULT));return ($v===''||$v===HOME_TEXT_DEFAULT)?t('vocab.home_title_default'):$v;
}
function homeSubtitleText(array $app): string {
    $v=trim((string)($app['home_subtitle']??HOME_TEXT_DEFAULT));return $v===HOME_TEXT_DEFAULT?t('vocab.home_subtitle_default'):$v;
}
/** Lomakkeen teksti talteen: minkä tahansa kielen oletusteksti tallennetaan merkkinä '@default'. */
function homeTextForStorage(string $key,string $typed): string {
    $v=trim($typed);if($v==='')return '';
    foreach(i18nAvailableLanguages() as $lang){$cat=i18nCatalog($lang);if(isset($cat['vocab.'.$key.'_default'])&&mb_strtolower($cat['vocab.'.$key.'_default'])===mb_strtolower($v))return HOME_TEXT_DEFAULT;}
    return $v;
}

<?php
declare(strict_types=1);
/**
 * Kielituki. Kaikki käyttäjälle näkyvät tekstit ovat kielitiedostoissa: lang/<kieli>/<alue>.php,
 * joista jokainen palauttaa taulukon avain => teksti. Avaimet ovat muotoa "alue.nimi" (esim. "car.log_service").
 *
 *   t('car.log_service')                       → "Kirjaa huolto"
 *   t('home.cars_count',['n'=>count($cars)])   → "3 autoa"   (tekstissä paikkamerkki {n})
 *
 * Muuttujien arvot lisätään tekstiin sellaisinaan; kutsuja vastaa HTML-suojauksesta (h()) ennen kuin antaa
 * käyttäjän syöttämän arvon. Kielitiedoston teksti on luotettua sisältöä: HTML-kohdissa se tulostetaan sellaisenaan,
 * joten tekstissä saa olla HTML-entiteettejä (&amp;) ja yksinkertaisia merkkejä (<strong>), jos alkuperäisessäkin oli.
 * Puuttuva käännös haetaan oletuskielestä (suomi), ja jos sitäkään ei ole, näytetään avain.
 * Selainpuolen (JS) tekstit ovat avaimia "js.*"; ne välitetään sivulle i18nJsPayload()-funktiolla.
 */
const I18N_DEFAULT_LANG = 'fi';
const I18N_DIR = __DIR__ . '/../lang';

/** Käytössä oleva kieli; asetetaan asetuksista heti kun ne on luettu. */
function i18nLang(?string $set=null): string {
    static $lang=I18N_DEFAULT_LANG;
    if($set!==null)$lang=in_array($set,i18nAvailableLanguages(),true)?$set:I18N_DEFAULT_LANG;
    return $lang;
}
/** Kielet, joille on kielikansio (lang/<koodi>/). */
function i18nAvailableLanguages(): array {
    static $list=null;
    if($list===null){$list=[];foreach(glob(I18N_DIR.'/*',GLOB_ONLYDIR)?:[] as $d){$code=basename($d);if(preg_match('/^[a-z]{2}$/',$code))$list[]=$code;}sort($list);if(!in_array(I18N_DEFAULT_LANG,$list,true))array_unshift($list,I18N_DEFAULT_LANG);}
    return $list;
}
/** Lataa kielen kaikki tiedostot yhdeksi taulukoksi (välimuistissa pyynnön ajan). */
function i18nCatalog(string $lang): array {
    static $cache=[];
    if(!isset($cache[$lang])){
        $cache[$lang]=[];
        foreach(glob(I18N_DIR.'/'.$lang.'/*.php')?:[] as $file){$part=require $file;if(is_array($part))$cache[$lang]=array_replace($cache[$lang],$part);}
    }
    return $cache[$lang];
}
/** Käännä avain. Paikkamerkit {nimi} korvataan $vars-taulukon arvoilla. */
function t(string $key,array $vars=[]): string {
    $text=i18nCatalog(i18nLang())[$key]??i18nCatalog(I18N_DEFAULT_LANG)[$key]??$key;
    if($vars){$map=[];foreach($vars as $name=>$value)$map['{'.$name.'}']=(string)$value;$text=strtr($text,$map);}
    return $text;
}
/** Onko avaimelle käännös käytössä olevalla kielellä tai oletuskielellä? */
function i18nHas(string $key): bool {
    return isset(i18nCatalog(i18nLang())[$key])||isset(i18nCatalog(I18N_DEFAULT_LANG)[$key]);
}
/** Kielen oma nimi (kielitiedoston avain lang.name), esim. "Suomi", "Svenska". */
function i18nLanguageName(string $code): string {
    return i18nCatalog($code)['lang.name']??strtoupper($code);
}
/** Kielivalinnat valikkoon: koodi => oma nimi. */
function i18nLanguageOptions(): array {
    $out=[];foreach(i18nAvailableLanguages() as $code)$out[$code]=i18nLanguageName($code);return $out;
}
/** Käyttökelpoinen kielikoodi tai '' (esim. lomakkeelta tai osoitteesta tullut arvo). */
function i18nValidLanguage(?string $code): string {
    $c=strtolower(trim((string)$code));return in_array($c,i18nAvailableLanguages(),true)?$c:'';
}
/** Selaimen Accept-Language-otsikon ensimmäinen tuettu kieli (oletus: oletuskieli). Vain asennusvaiheen esivalintaan. */
function i18nFromBrowser(): string {
    foreach(explode(',',(string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??'')) as $part){
        $c=strtolower(substr(trim(explode(';',$part)[0]),0,2));
        $v=i18nValidLanguage($c);if($v!=='')return $v;
    }
    return I18N_DEFAULT_LANG;
}
/** Selaimen (assets/app.js) tarvitsemat tekstit: kaikki avaimet, jotka alkavat "js.". */
function i18nJsPayload(): array {
    $out=[];
    foreach(i18nCatalog(I18N_DEFAULT_LANG)+i18nCatalog(i18nLang()) as $key=>$_)if(str_starts_with($key,'js.'))$out[$key]=t($key);
    return $out;
}

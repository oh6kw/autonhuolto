# Autonhuolto – muutoshistoria

Tämä tiedosto kertoo, mitä on muuttunut versiosta toiseen. Tiedostorakenne, asennus-, käyttö- ja varmuuskopio-ohje ovat tiedostossa `SETUP.md`; varmuuskopioinnin tekninen tiivistelmä on tiedostossa `BACKUP.md`.

Versiointi: `pääversio.toinen.korjaus` (esim. 1.0.1 = korjaus, 1.1.0 = uusi ominaisuus). Tietokannan skeemaversio on 13.

## 1.2.2

Pieni ulkoasukorjaus laskutulosteeseen. Ei tietokantamuutoksia (skeema 13).

- **Työ / ajoneuvo -rivin harmaa pohja:** pohja on tummennettu (vaaleanharmaa #dcdcdc), jotta se erottuu paperilla selvemmin, ja sille on asetettu tulostusväri "exact". Näin pohja tulostuu myös silloin, kun selaimen tulostusasetuksissa "Taustagrafiikka" ei ole valittuna.

## 1.2.1

Korjausversio. Ei tietokantamuutoksia (skeema 13).

- **Korjaus:** työtunnin yksikkö näkyi laskulla (näyttö ja tuloste) tekstinä `vocab.unit.h` (esim. "1,50 vocab.unit.h") versioissa 1.1.0 ja 1.2.0. Yksikön "h" nimi puuttui kielitiedostoista; lisätty suomeksi ja ruotsiksi (`vocab.unit.h`). Löytyi esimerkkilaskua tehdessä.
- **Tarkistus:** kaikkien tietokannan koodien (toimenpiteet, huoltotyypit, yksiköt, laskun tilat, ryhmät) näyttönimet on tarkistettu molemmista kielitiedostoista; muita puuttuvia ei löytynyt.
- **README:** laskun esimerkkikuvat (näyttö ja PDF-tuloste) on tehty uudella laskupohjalla.

## 1.2.0

Laskun tulostus uudistettu: kompakti laskupohja, suomalainen tilisiirtolomake ja pankkiviivakoodi. Ei tietokantamuutoksia (skeema 13); päivitys vain kopioimalla tiedostot vanhojen päälle.

- **Uusi laskupohja:** logo ja laskutettavan tiedot vasemmalla, laskun tiedot (laskunumero, päiväys, maksuaika, eräpäivä, viitenumero, tila) oikealla, työ ja ajoneuvo vaaleanharmaalla rivillä, laskurivit, yhteissummat ja MobilePay rinnakkain sekä korjaamon tiedot pienellä alareunassa.
- **Tilisiirtolomake:** kaksikielinen (suomi / ruotsi) lomake laskun alareunassa. Lomake asettuu aina viimeisen sivun alareunaan; pitkä lasku jakautuu kahdelle sivulle, taulukon otsikkorivi toistuu.
- **Pankkiviivakoodi (versio 4):** Code 128 -viivakoodi lomakkeen alla ja laskun näytöllä, kun laskuttajan IBAN on suomalainen. Piirretään ilman ulkoisia kirjastoja. Asetus: Asetukset → Yleiset → Pankkiviivakoodi laskulla (oletuksena päällä).
- **Alatunniste:** SQLite-koon viereen tietokannan versio ja kuvahakemiston koko.
- **Kielet:** uudet tekstit suomeksi ja ruotsiksi.
- Yksityiskohtaiset muutokset ovat esiversioiden 1.2.0-alpha.1 ja alpha.2 kohdissa.

## 1.2.0-alpha.2

Esiversio, joka ei ole erillisenä julkaisuna GitHubissa; muutokset sisältyvät versioon 1.2.0. Laskun tulostus uudistettu esimerkkimallin pohjalta. Ei tietokantamuutoksia (skeema 13).

- **Uusi laskupohja (tuloste):** yläosassa logo (tai korjaamon nimi, jos logoa ei ole) ja laskutettavan tiedot vasemmalla, otsikko "Lasku" ja laskun tiedot oikealla (laskunumero, päiväys, maksuaika päivinä, eräpäivä, viitenumero, tila). Sen alla yksi rivi työstä ja ajoneuvosta, laskurivit ja alareunassa korjaamon tiedot pienellä (yritys, osoite, Y-tunnus, puhelin, sähköposti; tyhjät kentät jätetään pois). Isot kehykset ja erillinen "Laskutettava / Työ" -ruudut poistuivat, jolloin lasku vie vähemmän tilaa.
- **Yhteissummat ja MobilePay rinnakkain:** Veroton yhteensä / ALV / Maksettavaa on oikealla ja MobilePay-ruutu vasemmalla samalla rivillä (aiemmin allekkain).
- **Lomake sivun alareunaan:** tilisiirtolomake ja viivakoodi asettuvat aina viimeisen sivun alareunaan. Pieni apuskripti mittaa sivun pituuden ja lisää tyhjää tilaa lomakkeen yläpuolelle; pitkällä laskulla lomake on siis toisen sivun alareunassa eikä sivun ylälaidassa. Jos selaimen skripti ei ole käytössä, lomake tulee sisällön perään.
- **Lomakkeen pieni korjaus:** vasemman reunan otsikko "Saajan tilinumero / Mottagarens kontonummer" ei enää leikkaudu.
- **Kielet:** uudet tekstit suomeksi ja ruotsiksi (1 747 tekstiä per kieli); ruotsiksi mm. "Betalningstid", "Referensnummer", "Företag", "FO-nummer".
- **Tarkistukset:** laskutuloste testattu kolmella laskulla (2 riviä, 5 riviä ja 35 riviä): lyhyet laskut mahtuvat yhdelle sivulle ja pitkä lasku jakautuu kahdelle sivulle lomake viimeisen sivun alareunassa, taulukon otsikkorivi toistuu. Kaikki muut sivut, tulosteet ja Excel-viennit ovat samat kuin versiossa 1.2.0-alpha.1 (suomi ja ruotsi). Viivakoodi luettiin PDF:stä lukijakirjastolla.

## 1.2.0-alpha.1

Esiversio, joka ei ole erillisenä julkaisuna GitHubissa; muutokset sisältyvät versioon 1.2.0. Laskun tulostus saa suomalaisen tilisiirtolomakkeen ja pankkiviivakoodin. Ei tietokantamuutoksia (skeema 13), joten varmuuskopiot ja päivitys toimivat kuten ennen.

- **Pankkiviivakoodi (versio 4):** laskun näytölle ja tulosteeseen piirtyy Code 128 -viivakoodi (54 numeroa: versio, IBAN, summa, viite, eräpäivä). Se piirretään itse SVG:nä (`code128cSvg()`, `invoiceBarcodeDigits()` tiedostossa `app/invoices.php`), joten ulkoisia kirjastoja ei tarvita. Koodi näkyy myös vanhoilla laskuilla, koska se lasketaan laskun omista tiedoista. Koodia ei piirretä, jos laskuttajan IBAN ei ole suomalainen, viitettä ei voi muodostaa tai summa on alle 0,01 € tai vähintään 1 000 000 €.
- **Asetus:** Asetukset → Yleiset → laskuttajan tiedot → **Pankkiviivakoodi laskulla** (oletuksena päällä, myös vanhoissa asennuksissa, joissa asetusta ei vielä ole).
- **Tilisiirtolomake laskutulosteessa:** tulosteen alareunassa on tilisiirtolomake (saajan tilinumero IBAN ja BIC, saaja, maksaja, allekirjoitus, viite, eräpäivä, summa, viesti ja pankkiviivakoodi). Lomakkeen kiinteät tekstit ovat kaksikielisiä (suomi / ruotsi) kummallakin kielellä. Lomake näytetään, kun laskulla on IBAN. Pankkisiirron tiedot (IBAN, viite, eräpäivä) eivät enää toistu erillisessä ruudussa; MobilePay-ruutu säilyy ja on tiivistetty yhdelle riville.
- **Tulosteen asettelu:** lomake asettuu sivun alareunaan. Tulosteen rivi- ja ruutuvälejä on tiivistetty, jotta tavallinen lasku (noin 5–6 riviä) mahtuu lomakkeineen yhdelle A4-sivulle; pidempi lasku siirtää lomakkeen viimeisen sivun alareunaan.
- **Alatunniste:** SQLite-tiedoston koon viereen on lisätty tietokannan versio (esim. `tietokanta v13`) ja kuvahakemiston koko (`kuvat 12,3 Mt`). Kuvahakemiston koko lasketaan korkeintaan kerran minuutissa istunnossa.
- **Kielet:** uudet tekstit suomeksi ja ruotsiksi (1 739 tekstiä per kieli).
- **Tarkistukset:** viivakoodi luettiin lukijakirjastolla sekä kuvasta että PDF-tulosteesta (300 dpi) ja sisältö täsmäsi laskettuun 54 numeroon (kaksi testilaskua ja kaksi esimerkkiä). Vertailussa muut 35 sivua, tulostetta ja Excel-vientiä (suomi ja ruotsi) ovat samat kuin versiossa 1.1.0; erot ovat vain laskun näyttö ja tuloste, asetukset ja alatunniste. Asetuksen päälle/pois, ei-suomalainen IBAN ja puuttuva IBAN tarkistettu.
- **Muistettavaa:** viivakoodin luku on testattu lukijakirjastolla; testaa se vielä oikealla pankkisovelluksella tulostetusta laskusta.

## 1.1.0

Monikielisyys: ohjelma toimii suomeksi ja ruotsiksi (svenska). Kieli valitaan asennuksessa, järjestelmän oletuskieli vaihdetaan Asetuksissa ja jokainen käyttäjä voi valita oman kielensä Oma tili -sivulla. Tietokannan skeema päivittyy versiosta 12 versioon 13 automaattisesti (turvakopio otetaan ensin). Alla yhteenveto; tarkemmat tiedot ovat esiversioiden (1.1.0-alpha.1 … alpha.3) kohdissa.

- **Ruotsi ja kielivalinnat:** `lang/sv/` (1 721 tekstiä), järjestelmän oletuskieli, käyttäjäkohtainen kieli, kielen vaihto kirjautumis- ja käyttöönottosivulla. Uuden kielen voi lisätä kopioimalla `lang/fi/`.
- **Kielitiedostot:** kaikki käyttäjälle näkyvät tekstit ovat kielitiedostoissa (`lang/<kieli>/*.php`, `app/i18n.php`); suomenkielinen ulkoasu on ennallaan.
- **Kielineutraali tietokanta:** toimenpiteet, huoltotyypit, yksiköt, laskun tilat ja ohjelman omat merkinnät tallentuvat englanninkielisinä koodeina ja näytetään lukijan kielellä. Itse kirjoitetut tekstit eivät käänny.
- **Uudet valmiit huoltokohteet:** Ilmastointihuolto ja Vararenkaan / renkaanpaikkaussarjan tarkastus. Päällekkäisten nimien esto estää omaa kohdetta saamasta valmiin kohteen nimeä missä tahansa kielessä.
- **Korjauksia:** täyden ZIP-palautuksen jälkeinen PHP-välimuistiongelma, huollon tallennus tyhjällä otsikolla ja koodien vuotaminen käyttäjälle (laskun tulostus, alkusaldo, yksikkövalinnat).
- **Asennuspaketti:** julkaisun lataus-zip sisältää nyt `lang/`-kansion, mutta ei enää `docs/`-kuvia eikä `.github/`-kansiota. Varmuuskopion palautus ei vaadi `docs/`-kansiota.
- **Päivitys versiosta 1.0.x:** ota ensin SQLite-varmuuskopio, kopioi uudet tiedostot vanhojen päälle (muista `lang/`) ja avaa ohjelma; tietokanta päivittyy itsestään.

## 1.1.0-alpha.3

Esiversio, joka ei ole erillisenä julkaisuna GitHubissa; muutokset sisältyvät versioon 1.1.0. Vaihe 3/3 monikielisyyteen: ruotsin kieli ja kielen valinta asennuksessa, asetuksissa ja käyttäjäkohtaisesti. Suomenkielinen toiminta, tulosteet ja Excel-viennit ovat ennallaan. Tietokannan skeema on edelleen 13 (ei tietokantamuutoksia).

- **Ruotsi (svenska):** uusi `lang/sv/`-kansio (22 tiedostoa, 1 721 tekstiä, samat avaimet kuin suomessa). Ruotsinkielinen käyttöliittymä, valmiiden huoltokohteiden ja ryhmien nimet, laskut, tulosteet, Excel-viennit (välilehtien nimet enintään 31 merkkiä), selaimen tekstit ja virheilmoitukset. Ruotsalaisia viitenumeroita tai markkinakohtaisia erityispiirteitä ei ole: kohderyhmä on Suomen ruotsinkieliset. Päivämäärät, lukumuodot ja lajittelu pysyvät suomalaisina.
- **Järjestelmän oletuskieli:** valitaan asennuksessa (ehdotus tulee selaimen `Accept-Language`-otsikosta) ja vaihdetaan Asetukset → Yleiset → Kieli. Tallentuu asetukseen `language`. Virheellinen kielikoodi hylätään ja vanha kieli säilyy.
- **Oma kieli käyttäjälle:** sivulla Oma tili voi valita oman kielen tai "järjestelmän oletus". Tallentuu asetukseen `user_lang_<käyttäjän id>` (ei skeemamuutosta); tyhjä valinta poistaa rivin, ja käyttäjän poisto poistaa myös hänen kielirivinsä. Toiminto on kaikkien roolien (katselija, tallentaja, ylläpitäjä) käytössä ja kirjautuu käyttäjähallinnan tapahtumalokiin ("Oman kielen muutos"); tapahtuman nimi näytetään lukijan kielellä.
- **Kirjautumis- ja käyttöönottosivu:** kielen voi vaihtaa sivun linkeistä (`?lang=sv`); valinta pysyy istunnossa. Käyttöönotossa kieli on lomakkeen ensimmäinen kenttä ja vaihtaa sivun kielen heti. Linkkejä näytetään vain, kun kieliä on useampi kuin yksi.
- **Uudet kielifunktiot:** `i18nLanguageName()`, `i18nLanguageOptions()`, `i18nValidLanguage()`, `i18nFromBrowser()` (`app/i18n.php`). Uuden kielen voi lisätä pelkästään kopioimalla `lang/fi/` kansioksi `lang/<kieli>/` ja kääntämällä; kieli ilmestyy valintoihin itsestään.
- **Varmuuskopiointi ja palautus ruotsiksi:** vahvistussana on kielikohtainen (suomeksi PALAUTA, ruotsiksi ÅTERSTÄLL); isot ja pienet kirjaimet eivät ratkaise. Käyttäjän ja järjestelmän kielivalinnat ovat kannassa mukana, joten ne siirtyvät varmuuskopion mukana.
- **Kielitiedostojen tarkistus:** ruotsin kielitiedostoista tarkistettiin avainten, muuttujien (`{nimi}`), HTML-tagien ja merkkikoodausten vastaavuus suomeen nähden (ei yhtään eroa); Y-tunnus on ruotsiksi FO-nummer ("företags- och organisationsnummer").
- **Muistettavaa:** huollon otsikko, jos jätät sen tyhjäksi, ja laskurivin "Työ:"-etuliite tallentuvat sillä kielellä, joka on käytössä tallennushetkellä (suunnitteluratkaisu: ne ovat tallennettua tekstiä, ei koodeja). Itse kirjoitetut tekstit eivät käänny. Valmiiden huoltokohteiden nimet, toimenpiteet, yksiköt, laskun tilat ja ohjelman omat merkinnät (esim. varaston alkusaldo) näytetään aina lukijan kielellä.
- **Tarkistukset:** suomenkielinen ulkoasu (40 sivua, tulostetta ja Excel-vientiä oikean kannan kopiolla) samanlainen kuin 1.1.0-alpha.2; ainoat erot ovat uudet kielivalinnat Asetuksissa ja Oma tili -sivulla. Ruotsinkielinen sivukierros (35 sivua ja 5 Excel-vientiä) ilman PHP-virheitä eikä suomenkielisiä ohjelmatekstejä (jäljellä vain omaa dataasi). Kirjoitusvirrat ruotsiksi (huolto tyhjällä otsikolla, laskun tilat, oma huoltokohde, kaksoiskappaleiden esto suomen- ja ruotsinkielisillä vakionimillä, yksiköt, mekaanikko, hyväksytyt poikkeamat, uusi auto) ja kielivalintojen testit (asennus, kirjautumissivu, oletuskieli, oma kieli, käyttäjän poisto, virheelliset arvot, palautus ruotsiksi) onnistuivat.
- **Dokumentaatio:** `SETUP.md` (tiedostorakenne `lang/sv/`, kielen valinta käyttöönotossa ja asetuksissa, uuden kielen lisääminen) ja `README.md` päivitetty. Versio 1.1.0-alpha.3.

## 1.1.0-alpha.2

Esiversio, joka ei ole erillisenä julkaisuna GitHubissa; muutokset sisältyvät versioon 1.1.0. Vaihe 2/3 monikielisyyteen: tietokantaan tallennetut vakioarvot ovat kielineutraaleja englanninkielisiä koodeja, ja ne näytetään kielitiedoston kautta. Ohjelma näyttää suomeksi saman kuin ennen (poikkeus: alla "Oma" → "Valmis"). Tietokannan skeemaversio on nyt 13; vanha kanta (skeema 12) päivittyy automaattisesti.

- **Koodit tietokannassa:** toimenpiteet (`replaced`, `done`, `inspected`, `repaired`, `cleaned`, `added`), huoltotyypit (`scheduled`, `repair`, `inspection`, `tyres`, `other`), yksiköt (`pcs`, `l`, `set`, `pack`, `h`), laskun tilat (`draft`, `sent`, `paid`, `credited`) ja huoltokohteiden ryhmät (`engine`, `interior`, `other`, `chassis`, `driveline`, `electrical`, `climate`). Näyttönimet ovat tiedostossa `lang/fi/vocab.php`, ja koodista nimeen muuntavat funktiot ovat uudessa tiedostossa `app/vocab.php`. Käyttäjän itse kirjoittama ryhmä tai yksikkö säilyy sellaisenaan; jos kirjoitettu nimi vastaa jonkin kielen valmista nimeä, se muuttuu koodiksi.
- **Valmiit huoltokohteet:** valmiin kohteen nimi tulee kielitiedostosta (`item.<tunniste>`) ja tietokannan `label` on tyhjä, kun käytössä on oletusnimi. Käsin muutettu nimi tallentuu sellaisenaan. Oma kohde näyttää aina kirjoitetun nimen.
- **Ohjelman omat merkinnät:** varaston alkusaldo, saldokorjaus, alkukilometrit, hyväksytty poikkeama ja varaston automaattinen lisäys tallentuvat koodeina (`@initial_balance`, `@stock_correction`, `@initial_km`, `@accepted_deviation`, `@auto_added`) ja näytetään kielellä. Mekaanikon "Muu" on `@other`; etusivun oletusotsikko ja -alaotsikko ovat `@default`. Laskurivin "Työ: …"-etuliite, varaston palautusmerkintä ja huollon tyhjästä otsikosta syntyvä oletusotsikko tallentuvat tekstinä sillä kielellä, joka oli käytössä tallennushetkellä.
- **Uudet valmiit huoltokohteet:** "Ilmastointihuolto" (`ac_service`, ryhmä Ilmastointi) ja "Vararenkaan / renkaanpaikkaussarjan tarkastus" (`spare_tyre_kit`, ryhmä Alusta). Aiemmin itse lisätyt, samannimiset kohteet muutetaan valmiiksi kohteiksi niin, että historia, huoltovälit ja kohteen omat asetukset säilyvät ("Oma"-merkki muuttuu merkiksi "Valmis"). Uudet valmiit kohteet, joita kannassa ei ollut, lisätään käyttämättöminä.
- **Päällekkäisten nimien esto:** uutta omaa huoltokohdetta ei voi lisätä nimellä, joka on jonkin valmiin kohteen nimi missä tahansa kielessä. Virheilmoitus neuvoo ottamaan valmiin kohteen käyttöön kohdeluettelosta.
- **Tietojen tarkistus:** tarkistuksen löydösten tunnisteet ovat kielineutraaleja (esim. `service-no-km-<id>`), joten hyväksytyt poikkeamat säilyvät kielen vaihtuessa. Asetuksissa hyväksyttyjen poikkeamien otsikko ja kuvaus päivitetään nykyisellä kielellä.
- **Tietokannan päivitys (skeema 12 → 13):** `app/migrate.php`. Ohjelma ottaa ensin turvakopion `.pre-migration-12-to-13-<aika>.sqlite3` tietokannan viereen (jos kopiointi epäonnistuu, mitään ei muuteta), ajaa päivityksen yhdessä tapahtumassa ja tarkistaa vierasavaimet ennen vahvistusta; virheessä kaikki perutaan. Päivitys muuntaa vanhat tekstiarvot koodeiksi, tunnistaa valmiit kohteet ja siirtää hyväksytyt poikkeamat uusiin tunnisteisiin. Taulujen rakenne ei muutu (vain oletusarvot).
- **Varmuuskopio ja palautus:** skeemaversion 12 varmuuskopio (SQLite tai täysi ZIP) kelpaa palautukseen ja päivittyy heti. Palautuksen tarkistus ei muuta tarkistettavaa kantaa. `.pre-migration-*` -tiedostot jäävät pois täydestä ZIP:stä ja siivotaan 30 päivän jälkeen samalla tavalla kuin `.pre-restore-*`.
- **Korjaus (löytyi testissä):** täyden ZIP-palautuksen jälkeen PHP:n koodivälimuisti saattoi ajaa muutaman sekunnin vanhaa koodia uutta kantaa vasten; esimerkiksi vanhempaan versioon palattaessa kanta ehti päivittyä skeemaan 13. Palautus tyhjentää nyt koodivälimuistin (`opcache_reset()`).
- **Korjaus (löytyi testissä):** huollon tallennus tyhjällä otsikolla tallensi otsikoksi huoltotyypin koodin (esim. `repair`); nyt otsikoksi tulee huoltotyypin nimi.
- **Korjaus (löytyi testissä):** yksikön ja toimenpiteen koodit eivät saa näkyä käyttäjälle (laskun tulostuksen tila, auton sivun alkusaldomerkintä ja yksikkövalinnat korjattu).
- **Dokumentaatio:** `SETUP.md` (tiedostorakenne, päivitysohje, `.pre-migration-*` palvelinsääntöihin) ja `BACKUP.md` päivitetty.
- **Tarkistukset:** 116 sivua, tulostetta ja Excel-vientiä (3 käyttäjäroolia) samat kuin versiossa 1.1.0-alpha.1 (ainoat erot: asetusten "Oma" → "Valmis" ja tietojen tarkistuksen tunnisteet); 182 lomaketoimintoa antavat samat ilmoitukset; oikean tietokannan kopiolla päivitys, kirjoitusvirrat, vieraskielinen (keksitty testikieli) kierros, uusi asennus ja palautukset (SQLite ja täysi ZIP, skeema 12 ja 13, myös paluu vanhaan versioon) testattu.
- **Muistettavaa:** huollon otsikko ja hinta- ja kuittitekstit, jotka olet kirjoittanut itse, pysyvät sellaisinaan, eikä niitä käännetä. Kielivalinta ja ruotsi tulivat versiossa 1.1.0-alpha.3.

## 1.1.0-alpha.1

Esiversio, joka ei ole erillisenä julkaisuna GitHubissa; muutokset sisältyvät versioon 1.1.0. Vaihe 1/3 monikielisyyteen: kaikki käyttäjälle näkyvät tekstit on siirretty kielitiedostoihin. Ohjelman toiminta ja tulostus suomeksi ovat ennallaan.

- **Kielitiedostot:** uusi `lang/fi/`-kansio (21 tiedostoa, 1 641 tekstiä) ja `app/i18n.php` (`t('avain')`; selaimessa `tt('js.avain')`). Tekstit ovat avain => teksti -taulukoita, ja tekstien muuttujat ovat muotoa `{nimi}`. Puuttuva käännös haetaan suomesta. Kieli on asetus `language` (oletus `fi`); valinta käyttöliittymästä tulee vaiheessa 3.
- **Mikä ei vielä ole kielitiedostoissa (vaihe 2):** tietokantaan tallennetut vakioarvot (laskun tilat, huoltotoimenpiteet, yksiköt, huoltotyypit, huoltokohteiden oletusnimet ja osiot, tallennetut muistiinpanot). Päivämäärä- ja lukumuodot sekä kuukausien nimien käyttö kuuluvat vaiheeseen 3.
- **Asennuspaketti ja GitHub erilleen:** asennuspaketti ei enää sisällä `docs/`-kuvia eikä `.github/`-kansiota (ne ovat vain GitHub-koodivarastossa). Varmuuskopion palautus ei vaadi `docs/`-kansiota; testattu palauttamalla täysi varmuuskopio kevyeen asennukseen. Release-työnkulku (`.github/workflows/release.yml`) rakentaa saman kevyen paketin.
- **Varmuuskopio:** `lang/` on ohjelman hallitsemien kansioiden joukossa. Palautus vaatii `lang/fi/layout.php`-tiedoston vain, jos varmuuskopion ohjelmaversio käyttää kielitiedostoja (vanhat varmuuskopiot palautuvat ennallaan).
- **Koodin pienet muutokset:** `<html lang>` tulee aktiivisesta kielestä; huoltohinnan "veroton"-osan tunnistus ei enää perustu tekstiin (`servicePriceParts()`); `index.php`:n ympäristötarkistuksen viesti lataa kielitiedoston itse, koska se ajetaan ennen muuta latausta.
- **Tarkistukset:** 116 sivua, tulostetta ja Excel-vientiä (3 käyttäjäroolia) tavu tavulta samat kuin versiossa 1.0.6; 182 lomaketoimintoa (virheet, onnistumiset, oikeudet) antavat samat ilmoitukset. Ei tietokantamuutoksia (skeema 12).

## 1.0.6

- **Automaattinen GitHub-julkaisu:** uusi `.github/workflows/release.yml`. Kun `main`-haaraan viedään uusi versio (`APP_VERSION` tiedostossa `index.php`), GitHub luo itse julkaisun `vX.Y.Z`, rakentaa lataus-zipin (ilman tietokantaa) ja liittää siihen kyseisen version muutoslokin. Jo olemassa olevaa julkaisua ei luoda uudelleen. Työnkulun voi ajaa myös käsin Actions-välilehdeltä.
- Ohjelman toiminta ei muutu. Versio 1.0.6, ei tietokantamuutoksia (skeema 12).

## 1.0.5

Siivous: kaikki viittaukset ennen julkaisua käytettyihin 0.x-kehitysversioihin poistettu.

- **SETUP.md:** poistettu maininta "versiossa 0.8.46-dev" (lataus-järjestyksen selitys).
- **Käyttöliittymän tekstit:** laskujen yhteenvedon ohjeteksti ja tietokannan liian vanhan skeeman virheilmoitus eivät enää nimeä vanhoja versioita.
- **Lähdekoodin kommentit** (`index.php`, `app/`, `assets/`): poistettu versiotunnisteet kuten "0.8.55:".
- Ohjelman toiminta ei muutu. Versio 1.0.5, ei tietokantamuutoksia (skeema 12).

## 1.0.4

Dokumentaatiopäivitys; ohjelman toiminta ei muutu.

- **README.md:** uusi osio "Toimii puhelimella ja tietokoneella" (mekaanikko voi kirjata huollon ja ottaa kuvat työn ohessa puhelimella), maininta ominaisuuslistassa ja englanninkielisessä yhteenvedossa sekä mobiilikuvakaappaus (`docs/screenshots/mobiili.png`: huoltotilanne, huoltolomake, kuvien lisäys ja työajastin).
- Versio 1.0.4. Ei tietokantamuutoksia (skeema 12).

## 1.0.3

- **Korjaus:** varastosivun "⚠️ N varoitusrajalla" -merkin teksti oli tummalla tummaa taustaa vasten (selaimen oletusväri painikkeelle). Merkki käyttää nyt teeman tekstiväriä (`assets/`-tyylitiedosto, `.badge-btn`).
- **README.md:** kuvakaappauksiin lisätty huoltotilanteen tuloste, huoltolomake varastoehdotuksineen, varaosavarasto sekä lasku näytöllä ja PDF-tulosteena (`docs/screenshots/`). Kuvat on otettu kuvitteellisesta esimerkkikorjaamosta.
- Versio 1.0.3. Ei tietokantamuutoksia (skeema 12).

## 1.0.2

Dokumentaatiopäivitys; ohjelman toiminta ei muutu.

- **README.md:** kuvakaappauksiin lisätty "Huoltotilanne ja ennuste" -näkymä (`docs/screenshots/huoltotilanne.png`), jossa näkyvät ennakkovaroitus, jäljellä olevat kilometrit ja päivät sekä arvioitu huoltopäivä. Kuva on otettu kuvitteellisesta esimerkkikorjaamosta.
- Versio 1.0.2. Ei tietokantamuutoksia (skeema 12).

## 1.0.1

Dokumentaatiopäivitys; ohjelman toiminta ei muutu.

- **README.md:** lisätty osio "Kuvakaappauksia" kolmella kuvalla (`docs/screenshots/`: etusivu, auton sivu, huoltohistoria). Kuvat on otettu kuvitteellisesta esimerkkikorjaamosta (keksityt autot, nimet ja rekisterinumerot); niissä ei ole oikeita henkilö- tai ajoneuvotietoja.
- **Palautus:** `docs/` lisätty palautuksen hallinnoimiin kansioihin (`BACKUP_MANAGED_DIRS`), jotta täyden ZIP:n palautus ei ilmoita kuvia ohitetuiksi. Sinne palautetaan vain kuvia (`.png`, `.jpg`, `.jpeg`, `.webp`) ja `.md`-ohjeita, ei koskaan ajettavaa koodia (sama periaate kuin `kuvat/`-kansiossa).
- `SETUP.md` ja `BACKUP.md`: tiedostopuu ja päivitysohje mainitsevat `docs/`-kansion.
- Versio 1.0.1. Ei tietokantamuutoksia (skeema 12).

## 1.0.0 – ensimmäinen julkinen julkaisu

Ohjelma julkaistu avoimena lähdekoodina (GNU AGPL v3): https://github.com/oh6kw/autonhuolto

Ennen julkaisua ohjelmaa kehitettiin versioina 0.x. Sen historia on arkistoitu eikä kuulu tähän julkiseen muutoshistoriaan. Versio 1.0.0 sisältää seuraavat ominaisuudet.

- **Autot ja kilometrit:** autojen tiedot, mittarilukemahistoria, vanhojen mittarilukemien lisäys päivämäärällä ja ristiriitojen tarkistus sekä ajomääräarvio.
- **Huolto:** kohdekohtaiset huoltovälit, huoltotilanne ja erääntymisennusteet, huoltohistoria kuvineen, mekaanikot ja työajastin.
- **Varaosat ja varasto:** varaosamuistio ja moniautosopivuus, varastosaldot ja -tapahtumat, inventointilista ja varaston tiedot -tuloste, hankintahinnat verottomana tai verollisena (ALV-muunnos vaihdettaessa).
- **Asiakkaat ja laskut:** asiakkaat, auton omistushistoria, laskut MobilePay-tiedoin sekä jakso- ja kuukausiyhteenvedot.
- **Tulosteet ja viennit:** tulosteet / PDF-näkymät ja Excel-viennit (auto, kaikki autot, varasto; hinnat ALV 0 % ja sis. ALV).
- **Käyttäjät ja turvallisuus:** roolit (Ylläpitäjä, Tallentaja, Katselija), kirjautumisen rajoitukset, CSRF-suojaus, tapahtumaloki. Ei hätäpalautuskoodia: suositus on vähintään kaksi ylläpitäjää, ja salasanan voi nollata myös palvelimelta (`SETUP.md` kohta 7.1).
- **Varmuuskopiot:** SQLite- ja täysi ZIP-varmuuskopio (versio tiedoston nimessä), palautus selaimesta turvakopioineen, kuvat/-suojaus ja versiotarkistus.
- **Ohjeet ja lisenssi:** `README.md`, `SETUP.md` (asennus Apache/Nginx, käyttöohje, varmuuskopiointi, päivitys, vianhaku), `BACKUP.md`, `SECURITY.md` ja `LICENSE`.
- Ei ulkoisia kirjastoja. Vaatii PHP 8.2+, `pdo_sqlite` ja `mbstring` (suositus: `zip`, `gd`).

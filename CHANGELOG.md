# Autonhuolto – muutoshistoria

Tämä tiedosto kertoo vain, mitä on muuttunut versiosta toiseen. Tiedostorakenne ja tiedostojen selitykset, asennusohje palvelimelle, käyttöohje ja varmuuskopioinnin ohje ovat tiedostossa `SETUP.md`; varmuuskopioinnin tekninen erityisohje on tiedostossa `BACKUP.md`.

# Muutoshistoria

## 0.8.70-dev

Ohjelma julkaistu GitHubissa: https://github.com/oh6kw/autonhuolto

- **Lähdekoodilinkki käyttöön:** `APP_SOURCE_URL` (`index.php`) osoittaa nyt julkiseen koodivarastoon `https://github.com/oh6kw/autonhuolto`, joten alatunnisteessa näkyy linkki "Lähdekoodi (AGPL v3)" (AGPL v3 kohta 13). Ohjelman toiminta ei muutu muuten.
- Versio 0.8.70-dev. Ei tietokantamuutoksia (skeema 12).

## 0.8.69-dev

Vanhan mittarilukeman lisäys.

- **Uusi: ＋ Lisää vanha mittarilukema** (auton sivu, Mittarilukema-osio, "Päivitä km" -kentän alla). Aiemmin vanhaa lukemaa ei voinut kirjata mistään: "Päivitä km" kirjaa aina tämän päivän lukeman eikä hyväksy nykyistä pienempää. Uusi lomake pyytää päivän, mittarilukeman ja valinnaisen huomion (esim. "luettu vanhasta valokuvasta"). Lukema tallentuu valitulle päivälle kilometrihistoriaan lähteellä "Vanha lukema" (📷) ja näkyy historiassa pelkkänä päivämääränä.
- **Tarkistukset:** päivä ei voi olla tulevaisuudessa eikä virheellinen (esim. 31.2.); lukema on pakollinen ja välilyönnit sallitaan (`94 992`); sama lukema samalle päivälle ei tallennu kahdesti. Lukeman on sovittava historian ketjuun: aiemmalle päivälle se ei voi olla suurempi eikä myöhemmälle päivälle pienempi kuin jo kirjatut mittarilukemat ja huollot. Ristiriidassa ohjelma kertoo, mikä päivä ja lukema ristiriidan aiheuttaa, eikä tallenna mitään.
- **Nykyinen km:** auton nykyinen lukema muuttuu vain, jos uusi lukema on suurempi kuin nykyinen. Vanha lukema ei siis alenna sitä.
- **Oikeudet:** toiminto `add_old_odometer` on Tallentajalle ja Ylläpitäjälle; Katselija ei näe lomaketta eikä palvelin hyväksy sitä (kokeiltu: 403, ei tallennu).
- **Muut paikat:** vanha lukema näkyy myös Excel-viennin kilometrihistoriassa (lähde "Vanha lukema", päivä ilman kellonaikaa), ajomääräarviossa ja kilometrikuvaajassa, ja sen voi poistaa kuten muutkin mittarimerkinnät.
- Versio 0.8.69-dev. Ei tietokantamuutoksia (skeema 12): käytössä on olemassa oleva `odometer_readings`-taulu uudella `source`-arvolla `old`.

## 0.8.68-dev

Hätäpalautuskoodi poistettu: se oli käyttämätön ominaisuus, jonka lupaus ei pitänyt paikkaansa.

- **Poistettu: hätäpalautuskoodi.** Ohjelma loi ja näytti kerran 64-merkkisen koodin ja tallensi sen tiivisteen (`app_settings.auth_recovery_hash`), mutta mikään koodi ei käyttänyt sitä: hätäpalautussivua, jota ohjeet ja teksti lupasivat, ei ollut olemassa. Koodi oli siis vain harhaanjohtava, ja sen säilytys antoi väärän turvallisuudentunteen. Poistettu: `authRecoveryKey()`, toiminto `regenerate_recovery_key` (käsittelijä, roolilista, kohde-uudelleenohjaus), koodin luonti ensimmäisessä käyttöönotossa sekä Asetukset-sivun osiot "Tallenna hätäpalautuskoodi" ja "Luo uusi hätäpalautuskoodi". Osio on nyt nimeltään "Kirjautumisen suojaus".
- **Vanhat asennukset:** olemassa oleva rivi `auth_recovery_hash` jää tietokantaan vaarattomana (ei käytössä, ei tietokantamuutosta). Vanhat tapahtumalokimerkinnät ("Palautuskoodin uusiminen") näkyvät edelleen luettavina.
- **Uusi käytäntö: vähintään kaksi ylläpitäjää.** Ensimmäisen käyttöönoton jälkeen ohjelma ohjaa Käyttäjät-osioon ja ehdottaa toisen ylläpitäjän luomista. Käyttäjät-osiossa näkyy varoitus, kun aktiivisia ylläpitäjiä on alle kaksi. Toinen ylläpitäjä voi asettaa unohtuneen salasanan tilalle väliaikaisen (toiminto oli jo olemassa).
- **Salasanan nollaus palvelimelta:** `SETUP.md` kohta 7.1 sisältää komennon, jolla ainoan ylläpitäjän salasanan voi nollata palvelimelta (väliaikainen salasana, pakollinen vaihto, vanhat istunnot päättyvät). Kokeiltu testitietokannalla: kirjautuminen väliaikaisella salasanalla ohjaa salasanan vaihtoon.
- **Korjattu: virheellinen dokumentaatio.** `SETUP.md` (0.8.66) lupasi hätäpalautussivun, jota ei ole; teksti poistettu. Lisäksi `SETUP.md`:n ja `README.md`:n rooli "Muokkaaja" korjattu ohjelmassa käytettyyn nimeen "Tallentaja". Settings-sivun tekstiin lisätty, ettei varmuuskopion palautus auta unohtuneeseen salasanaan.
- **README.md:** lisätty tekijä ja yhteystiedot sekä Ko-fi-linkki. `.github/FUNDING.yml`: `ko_fi: oh6kw`.
- Versio 0.8.68-dev. Ei tietokantamuutoksia (skeema 12).

## 0.8.67-dev

Ohjelma valmisteltu julkaistavaksi avoimena lähdekoodina (GitHub).

- **Lisenssi: GNU AGPL v3.** Uusi tiedosto `LICENSE` (virallinen AGPL v3 -teksti). `index.php`:n alkuun lisätty lisenssi- ja tekijänoikeusmerkintä (Copyright (C) 2026 Jarno Jaskari). AGPL:n kohta 13 (verkossa ajettavan ohjelman lähdekoodin tarjoaminen): uusi vakio `APP_SOURCE_URL` (`index.php`); kun se on asetettu, alatunnisteessa näkyy linkki "Lähdekoodi (AGPL v3)". Oletus on tyhjä, jolloin linkkiä ei näytetä.
- **Uudet GitHub-tiedostot:** `README.md` (esittely, ominaisuudet, vaatimukset, pika-aloitus, lisenssi, englanninkielinen yhteenveto), `.gitignore` (tietokanta, sen varmuuskopiot, lukitustiedostot, ladatut kuvat, ZIP-tiedostot ja palvelinkohtainen `.htaccess` eivät pääse versionhallintaan), `SECURITY.md` (haavoittuvuuksien ilmoitusohje ja suojauksen kuvaus), `.github/FUNDING.yml` (Sponsor-napin pohja, valmiiksi kommentoituna).
- **Yleiset oletusnimet:** oletusnimi "Pärnäsen Korjaamo" vaihdettu nimeksi "Autonhuolto", ja asetusten esimerkkikentistä "esim. Jarno" on tullut "esim. Matti". Muutos koskee vain uusia asennuksia ja tyhjiä kenttiä: jo asennetun ohjelman korjaamon nimi, otsikot ja muut asetukset luetaan tietokannasta (`app_settings`), joten ne säilyvät päivityksessä ennallaan.
- Palautuksen hallinnoimiin juuritiedostoihin lisätty `LICENSE`, `SECURITY.md` ja `.gitignore`, jotta täyden ZIP:n palautus ei ilmoita niitä ohitetuiksi.
- `SETUP.md` ja `BACKUP.md` päivitetty: tiedostopuu, päivitysohje (kopioitavat tiedostot) ja lisenssimaininta.
- Versio 0.8.67-dev. Ei tietokantamuutoksia (skeema 12).

## 0.8.66-dev

Koodikatselmoinnin (ChatGPT) havaintojen tarkistus ja korjaus sekä uusi ohjetiedosto.

- **Korjattu: virheellinen restore-teksti** (Asetukset → Turva → Kirjautumisen suojaus → Lue lisää suojauksesta). Teksti väitti, että palautus "säilyttää tämän asennuksen käyttäjät, salasanat ja palautuskoodin". Todellisuudessa palautus korvaa koko tietokannan, joten käyttäjät, salasanatiivisteet, roolit, oikeudet ja hätäpalautuskoodi tulevat varmuuskopiosta ja kaikki istunnot päätetään. Teksti korjattu vastaamaan toimintaa; siihen lisättiin maininta, että täyden ZIP:n palautus kirjoittaa vain ohjelman omat tiedostot.
- **Korjattu: `kuvat/`-kansion palautussuojaus oli kommenttiaan löysempi** (`app/backup.php`, `backupRestoreManaged()`). Ehto `||!str_contains($base,'.')` päästi palautukseen myös tiedostot ilman päätettä. Nyt sinne palautetaan vain `.jpg`, `.jpeg`, `.png`, `.webp` ja suojaava `index.html`, kuten kommentti ja ohje sanovat. Ohjelma itse tallentaa kuvat vain näillä päätteillä, joten normaalikäyttö ei muutu.
- **Korjattu: `views/*.php` -kuvaus oli epätarkka.** Kuvaus sanoi "Pelkkää HTML:ää", vaikka näkymät lukevat tietokannasta. Uusi kuvaus: näkymät ovat HTML-näkymiä ja niiden esitykseen tarvittavaa PHP:tä; ne voivat lukea tietokannasta mutta eivät tee tallennuksia, vaan kaikki kirjoittaminen on `app/actions.php`:ssa.
- **Dokumentoitu: täyden ZIP:n varmuuskopio ja palautus eivät ole symmetriset.** Palautus kirjoittaa vain `index.php`, `CHANGELOG.md`, `SETUP.md`, `BACKUP.md`, `README.md` sekä kansiot `app/`, `assets/`, `views/` ja `kuvat/`, eikä poista tai ylikirjoita muita tiedostoja (esim. oma `.htaccess`). Tämä oli jo tarkoituksellista koodissa; nyt se on kerrottu selvästi tiedostoissa `SETUP.md`, `BACKUP.md` ja Asetukset-sivulla.
- **Uusi `SETUP.md`**: tiedostorakenne ja tiedostojen selitykset (siirretty tästä tiedostosta), asennusohje palvelimelle vasta-alkajalle (Apache ja Nginx, oikeudet, HTTPS, tietokannan suoran latauksen esto ja sen tarkistus), käyttöohje, varmuuskopioinnin ohje, päivitysohje ja vianhaku. Settings-sivun maininta "mukana olevista asennusohjeista Apache- ja Nginx-sääntöineen" pitää nyt paikkansa.
- **Tämä tiedosto** (`CHANGELOG.md`) sisältää jatkossa vain muutoshistorian; hakemistopuu ja tiedostotaulukko ovat `SETUP.md`:ssä.
- `SETUP.md` lisätty palautuksen hallinnoimiin juuritiedostoihin (`BACKUP_MANAGED_ROOT_FILES`) ja päivitysohjeen kopioitavien tiedostojen luetteloon (`BACKUP.md`).
- Versio 0.8.66-dev. Ei tietokantamuutoksia (skeema 12).

## 0.8.65-dev

- Varastosivun "Tulosta näkyvät" jaettu kahdeksi napiksi: **🖨 Inventointilista** (entinen tuloste: tyhjä "Laskettu"- ja "Huomio"-sarake käsin täytettäväksi hyllyllä, pysty A4) ja **🖨 Varaston tiedot** (uusi: ei Laskettu-saraketta, vaan hankintahinta, saldoarvo, tila ja muistiinpano; vaaka A4). Napit ovat Excel-napin kanssa samassa rivissä; molemmat noudattavat haun, auton, "vain vähissä" -rajauksen ja lajittelun valintoja.
- Varaston tiedot -tuloste: yläreunassa yhteenveto (nimikkeet, varoitusrajalla olevat, varaston arvo sekä ALV 0 % että sis. ALV). Sarakkeet: sopii autoihin, varaosa, tuotetiedot (merkki, tuotenumero, OEM), hylly, saldo, varoitusraja, hankintahinta / yks. (otsikko kertoo ALV-tulkinnan asetuksen mukaan), saldoarvo, tila (OK / Varoitusrajalla), muistiinpano. Riveillä ei katkaista kesken solun, pienempi fontti → n. 16 riviä / sivu.
- Toteutus: sama osoite `?print=inventory`, uusi parametri `mode=info`; ei uusia tiedostoja eikä tietokantamuutoksia (skeema 12).

## 0.8.64-dev

- Korjaus: täysi ZIP-varmuuskopio ei onnistunut ("ZIP-varmuuskopion viimeistely epäonnistui"), kun ohjelman hakemistossa oli tiedosto, jota PHP (verkkopalvelimen käyttäjä) ei voinut lukea. Tyypillinen syy: komentoriviltä (eri käyttäjänä) otettu SQLite-kopio, esim. `autohuolto.sqlite3.ennen-…` (oikeudet 0600, eri omistaja). Toistettu testissä: PHP ajettuna eri käyttäjänä, root-omisteinen 0600-tiedosto ohjelmahakemistossa → sama virhe.
- Täysi ZIP jättää nyt pois kaikki SQLite-tiedoston kopiot ja muunnelmat (`*.sqlite3`, `*.sqlite3.*`, `*.sqlite3-…`). Ne eivät kuulu ohjelmapuuhun, ja ne ovat aina erillisiä kopioita tietokannasta (kanta tulee ZIPiin omana turvallisena snapshotinaan).
- Jos ohjelmapuussa on muu tiedosto, jota ei voi lukea, virheilmoitus nimeää tiedoston ja neuvoo siirtämään sen pois tai korjaamaan oikeudet (aiemmin ilmoitus oli epämääräinen "viimeistely epäonnistui").
- Tietokannan rakenne ei muutu (skeema 12). Muutos koskee vain app/backup.php:tä.

## 0.8.63-dev

- Hintojen syöttötavan vaihto (Asetukset → veroton / verollinen) muuntaa nyt myös varaston hankintahinnat uuteen tapaan, jotta todellinen hinta pysyy samana. Muunnos käyttää vaihtohetkellä voimassa ollutta ALV-kantaa ja pyöristää kahteen desimaaliin; se tehdään samassa tietokantatapahtumassa asetusten kanssa (virhe peruu kaiken). Vahvistusviesti kertoo muunnettujen hintojen määrän. Asetussivun ohjeteksti kertoo muunnoksesta. Testattu oikealla kannalla: veroton → verollinen → veroton palautti kaikki 26 hintaa täsmälleen ennalleen.
- Excel-viennit näyttävät varaston hankintahinnat sekä ALV 0 % että sis. ALV -muodossa (auto-, varasto- ja koko rekisterin vienti). Varastovienti: lisäksi "Varaston arvo" molemmilla tavoilla sarakkeina ja Yhteenveto-välilehdellä. Lasku tehdään oikeasta ALV-kannasta riippumatta syöttötavasta.
- Varmuuskopiotiedostojen nimiin tulee ohjelmaversio: `autohuolto-db-backup-v0.8.63-dev-2026-10-08-055913.sqlite3` ja `autohuolto-taysi-backup-v0.8.63-dev-….zip`. Palautus ei riipu tiedoston nimestä; vanhan nimiset tiedostot toimivat edelleen. Uusi apufunktio `backupFileVersion()`.
- Uudet apufunktiot `purchasePriceNetGross()` ja (0.8.62) `purchasePriceLabel()` tiedostossa app/helpers.php. Tietokannan rakenne ei muutu (skeema 12).

## 0.8.62-dev

- Varaston hankintahinnan otsikko kertoo nyt ALV-tulkinnan Hintojen syöttötavan (Asetukset) mukaan: "Hankintahinta sis. ALV 25,5 %" tai "Hankintahinta veroton". Sama otsikko näkyy varastosivun taulukossa (myös mobiilin rivitunniste), "Lisää varaosa" -lomakkeessa, auton Varaosamuistion lisäys- ja muokkauslomakkeessa sekä Excel-vienneissä (auto-, varasto- ja koko rekisterin vienti).
- Auton Varaosamuistion hintarivin perään tulee "· sis. ALV" tai "· veroton".
- Uusi apufunktio `purchasePriceLabel()` (app/helpers.php). Tallennettuja hintoja ei muunneta; asetuksen vaihto muuttaa vain otsikkoa. Tietokanta ei muutu (skeema 12).

## 0.8.61-dev

**Auton sivu: vuosittaiset huoltokulut ja arvio ajetuista kilometreistä samassa taulukossa.** (Kustannus €/100 km jätettiin pois tarkoituksella, koska kaikkia huoltojen hintoja ei ole kirjattu.) Ei tietokantamuutoksia (skeema pysyy 12): laskenta tehdään aina olemassa olevista mittarilukemista ja huolloista.

- Vuosikustannukset-laatikko on nyt taulukko: **Vuosi · Huoltokulut · Ajettu (arvio)**.
- **Ajetut kilometrit vuodessa** arvioidaan mittarilukemista (huoltojen kilometrit + Päivitä km -merkinnät): vuoden rajat (1.1.) interpoloidaan lähimpien lukemien väliltä tasaisella ajotahdilla. Lukemista tehdään kasvava jono (päivän suurin lukema; aiempaa pienemmät poikkeamat ohitetaan).
- **Rivin lisämerkinnät:** `≈` = arvio; `*` = osittainen vuosi; käynnissä olevalle vuodelle "vuositahdilla ≈ N km"; ensimmäiselle vuodelle "lukemista alkaen pvm"; "viimeisin lukema pvm", kun lukemat eivät ulotu vuoden loppuun; **"karkea arvio"**, kun vuoden rajan ympäriltä puuttuu alle 180 päivän välein oleva lukema (silloin arvio perustuu pitkään lukemien väliin).
- **Yhteenveto:** keskimääräinen vuosimatka (kokonaismatka ajanjaksolta ÷ ajanjakso vuosina; ei arvio vaan lukemien erotus) sekä ajanjakso ja kokonaismatka.
- Selite taulukon alla kertoo, että kilometrit ovat suuntaa antava arvio ja että tarkkuus paranee, kun lukemia päivitetään tiheästi.
- Mukana ovat myös vuodet, joilta on kilometrejä mutta ei huoltokuluja (kulu "–"). Vanhimmat vuodet ovat edelleen "Näytä vanhemmat" -painikkeen takana (5 uusinta näkyy).

**Muutetut tiedostot:** `app/cars.php` (`annualKmEstimates()`), `views/car.php`, `assets/app.css`, `index.php` (versio).

**Testattu:** laskenta synteettisellä aineistolla (tasainen ajotahti, aiempaa pienempi poikkeama ohitetaan, osittaiset vuodet, vain yksi lukema = ei arviota); autosivut 6, 2 ja 1 oikeilla testitiedoilla työpöydällä ja puhelimella; ei PHP-varoituksia.

## 0.8.60-dev

**Viitenumero laskuille ja tulosteeseen.** Ei tietokantamuutoksia (skeema pysyy 12): viite lasketaan aina laskunumerosta, joten se on sama joka kerta, eikä sitä tarvitse tallentaa. Siksi myös jo luodut laskut saavat viitteen.

- Viite muodostuu laskunumeron numeroista ja suomalaisesta tarkisteesta (painot 7-3-1 oikealta vasemmalle): laskunumero `2026-0003` → `202600033`, tulostettuna viiden numeron ryhmissä oikealta lukien: **2026 00033**. Funktiot `invoiceReferenceCheckDigit()`, `invoiceReference()` ja `invoiceReferenceFormatted()` (`app/invoices.php`).
- Viite näkyy ruudulla (`views/invoice.php`) ja tulosteessa (`app/printing.php`) Tilisiirto-kortissa. Kun viite on, "Viesti: Lasku …" -rivi korvataan viitteellä ja ohjeella "Käytä viitettä maksaessasi. Viestiä ei silloin tarvita." MobilePay-kortissa viestinä on edelleen laskunumero (MobilePay ei käytä viitettä).
- Jos laskunumerosta ei saa kelvollista viitettä (alle 3 tai yli 19 numeroa), näytetään vanha viestirivi.
- Laskennan oikeellisuus tarkistettu julkisen esimerkin avulla: 123456 → 1234561 (paperimuoto "12 34561").

**Muutetut tiedostot:** `app/invoices.php`, `views/invoice.php`, `app/printing.php`, `assets/app.css`, `index.php` (versio).

**Testattu:** viitteen laskenta (123456, 1234, 2026-0001…), laskun ruutu- ja tulostenäkymä selaimella, ei PHP-varoituksia. Regressio: vain laskusivut ja laskutuloste muuttuivat.

## 0.8.59-dev

**Varaosakate.** Ei tietokantamuutoksia (skeema pysyy 12): kaksi uutta asetusriviä `parts_markup_enabled` ja `parts_markup_percent` samassa `app_settings`-taulussa kuin muutkin asetukset.

- **Asetukset → Yleiset → Korjaamon / laskuttajan tiedot → Varaosakate:** valinta "Varaosakate käytössä" ja "Kate, %" (oletus 15; voi muuttaa tai ottaa pois). Kate toimii vain, kun sekä laskutus että varasto ovat käytössä.
- **Varastosta otettu osa:** kun huollon osarivillä valitaan "Käytetäänkö varastosta? Kyllä", á-hinnaksi ehdotetaan automaattisesti **hankintahinta × (1 + kate)**, ja rivillä näkyy "varaston hankintahinta 20,00 + 15 % kate". Hinnan voi muuttaa käsin. Ehdotus tehdään vain, kun hintakenttä on tyhjä tai sisältää aiemman automaattisen ehdotuksen; käsin kirjoitettua hintaa ei ylikirjoiteta, eikä jo tallennetun huollon avaaminen muokattavaksi muuta hintaa. Jos varastovalinta otetaan pois, automaattinen ehdotus poistuu.
- **Käsin hinnoiteltu osa:** kate ei lisäänny itsestään. Hintakentän vieressä on **＋ 15 % kate** -painike, joka lisää katteen kentän hintaan (esim. 6,00 → 6,90). Painikkeen painalluksen näkee heti hinnassa, joten mitään ei tapahdu huomaamatta.
- Hankintahinta tulkitaan **Hintojen syöttötavan mukaan** (veroton tai sis. ALV), kuten muutkin hinnat. Huoltoon tallentuu tavalliseen tapaan veroton hinta, ja lasku käyttää huollon hintoja, joten laskun luontiin ei tarvittu muutoksia.
- Kate on pelkkä hinnan apulaskuri lomakkeella: tallennettuun dataan tulee vain lopullinen hinta. Kun kate ei ole käytössä, huoltolomake toimii ennallaan (varaston osavalinnoihin on lisätty vain näkymätön `data-price`-tieto).

**Muutetut tiedostot:** `app/db.php` (oletusasetukset, `partsMarkupPercent()`), `app/actions.php` (asetusten tallennus), `views/settings.php`, `views/car.php` (`data-markup`, `data-price`, painike), `assets/app.js`, `assets/app.css`, `index.php` (versio).

**Testattu:** Oikealla selaimella: asetusten tallennus, painike näkyy vain kun kate on käytössä, varastovalinta antaa 20 € → 23,00 (syöttötapa sis. ALV), käsin 6 → 6,90, käsin kirjoitettu hinta ei ylikirjoitu varastovalinnassa, varastovalinnan poisto tyhjentää automaattisen ehdotuksen, huolto tallentui (veroton hinta 18,33 = 23 / 1,255) ja varastosaldo täsmäytyi. Regressio: muut sivut ennallaan.

## 0.8.58-dev

**Palautuksen versiotarkistus ja selkeämmät backup-selitykset.** Ei tietokantamuutoksia (skeema pysyy 12).

- **Täyden ZIP:n palautus tarkistaa ohjelmaversion.** Palautus lukee varmuuskopion `index.php`:stä `APP_VERSION`-arvon ja vertaa sitä käynnissä olevaan versioon (`version_compare`). Jos varmuuskopio on vanhempi, palautus keskeytyy ennen kuin mitään muutetaan, ja ilmoitus kertoo molemmat versiot sekä neuvoo palauttamaan pelkän SQLite-kannan (ohjelma säilyy). Jos versiota ei saada luettua, palautus keskeytyy samoin.
- Tarkoituksellinen paluu vanhaan versioon onnistuu rastittamalla palautuslomakkeessa uusi valinta **"Salli palautus vanhempaan ohjelmaversioon"**; onnistumisilmoitus kertoo, mihin versioon ohjelma palautui. Saman tai uudemman version ZIP palautuu kuten ennen. SQLite-palautus ei koske ohjelmaan, joten se ei tarvitse tarkistusta.
- Backup-osion selitys kirjoitettu uusiksi kahden kortin muotoon: **SQLite** (vain tietokanta, turvallinen päivityksen jälkeenkin, sopii päivittäin ja ennen testausta) ja **Täysi ZIP + kuvat** (koko asennus, sopii siirtoon ja viikoittaiseksi kokovarmuuskopioksi; palautus vaihtaa myös ohjelmatiedostot).

**Muutetut tiedostot:** `app/backup.php` (`backupAppVersion()`, `backupCheckVersionDowngrade()`), `views/settings.php`, `assets/app.css`, `index.php` (versio).

**Testattu:** Vanhan version (0.8.54) täysi ZIP palautettiin uuteen: ilman sallintaa estettiin ja ohjelma ja tiedot säilyivät; saman version ZIP palautui normaalisti; sallinnalla vanha ZIP palautui ja ohjelma oli sen jälkeen 0.8.54. Ei PHP-varoituksia.

## 0.8.57-dev

**Mekaanikon poisto kokonaan** (aiemmin mekaanikon sai vain poistaa käytöstä). Ei tietokantamuutoksia (skeema pysyy 12).

- Jokaisen mekaanikon kortissa on **Poista mekaanikko** -painike ja rivi "Merkitty N huoltotyölle · M käyttäjän oletus".
- Ennen poistoa selain kysyy vahvistuksen, esim. "Hänet on merkitty 14 huoltotyölle. Näiden huoltojen mekaanikoksi tulee "Muu". Käyttäjien oletusmekaanikko-kytkentä poistuu 1 käyttäjältä. Toimintoa ei voi perua."
- Poiston jälkeen kaikkien kyseisen mekaanikon huoltojen mekaanikoksi tulee **Muu** (sekä viittaus että snapshot-nimi). Jos Muu-nimistä mekaanikkoa ei ole, se luodaan automaattisesti (käytössä, ei oletus) ja siitä kerrotaan ilmoituksessa.
- Jos poistetaan itse "Muu"-mekaanikko, hänen huoltojensa mekaanikko jää tyhjäksi (Tietojen tarkistus näyttää ne "mekaanikko puuttuu" -huomautuksina) ja käyttäjän vahvistuskysely kertoo tämän.
- Käyttäjien oletusmekaanikko-kytkentä (`auth_users.mechanic_id`) nollataan. Jos poistettu oli oletusmekaanikko, uusi oletus valitaan automaattisesti.
- Suojaus: toiminto `delete_mechanic` on vain ylläpitäjälle, vaatii vahvistuksen, ja palvelin tarkistaa, että huoltojen määrä on edelleen sama kuin vahvistuksessa (muuten poisto keskeytyy ja pyytää avaamaan sivun uudelleen). Koko poisto tehdään yhdessä tietokantatapahtumassa (virheessä mitään ei muutu). Historia, laskut ja Excel-viennit säilyvät, koska ne käyttävät huollon nimi-snapshotia.

**Muutetut tiedostot:** `app/actions.php` (`delete_mechanic`), `app/auth.php` (oikeustaulu), `views/settings.php` (mekaanikkokortit), `assets/app.css`, `index.php` (versio).

**Testattu:** Oikealla selaimella: poisto huoltotöillä (14 huoltoa → Muu), poisto ilman huoltoja, oletusmekaanikon poisto (uusi oletus), "Muu"-mekaanikon poisto (huollot tyhjiksi), poisto kun Muu-mekaanikkoa ei ole (luodaan automaattisesti), vanhentunut huoltomäärä (keskeytyy ilmoituksella), vahvistusteksti, paluu Mekaanikot-välilehdelle. Ei PHP-varoituksia.

## 0.8.56-dev

Asetukset-sivun (`?view=settings`) ja Omat tiedot -sivun (`?view=account`) selkeytys "ihmisen silmällä". Ei uusia tiedostoja, ei tietokantamuutoksia (skeema pysyy 12).

**Asetukset**
1. **Välilehdet.** Sivu jaettu osiin: ⚙️ Yleiset, 🛠 Huoltokohteet, 🔧 Mekaanikot (vain jos ominaisuus on käytössä), 👥 Käyttäjät, 🔎 Tarkistus (huomautusten määrä merkkinä) ja 🔐 Tietoturva ja varmuuskopio. Näkyvissä on yksi osio kerrallaan; sivun korkeus työpöydällä n. 10 400 → 950–2 100 px, puhelimella n. 23 300 → 1 300–3 700 px. Ilman JavaScriptiä kaikki osiot näkyvät peräkkäin.
2. **Uusi järjestys:** Yleiset, Huoltokohteet, Mekaanikot, Käyttäjät, Tarkistus, Tietoturva (varmuuskopio ensin, suojaustiedot sen jälkeen). Käyttäjät eivät enää ole ylimpänä.
3. **Linkit ja uudelleenohjaukset toimivat välilehtien kanssa.** `#kohteet`, `#mekaanikot`, `#kayttajat`, `#datacheck`, `#backup`, `#kayttajaloki` jne. avaavat oikean välilehden ja sisällä olevat avattavat osiot. Ilman ankkuria avataan viimeksi katsottu välilehti (sessionStorage).
4. **Käyttäjät tiiviiksi riveiksi.** Jokaisesta käyttäjästä yksi rivi (tunnus, rooli, "sinä", nimi, viimeisin kirjautuminen) ja "Muokkaa ▾", jonka alta aukeavat kentät, salasanan asetus ja poisto. 7 käyttäjää vie n. 400 px (aiemmin n. 2 000 px). Käytössä-ruksi ja Tallenna on kohdistettu samalle riville.
5. **Tietojen tarkistus ryhmiteltynä:** Virheet / Puuttuvat kuvatiedostot / Varoitukset / Huomiot. Pienet ryhmät (≤3) ja virheet ovat auki, muut kiinni. Uusi **"✓ Hyväksy kaikki (N)"** ryhmälle (uusi toiminto `accept_data_issues`, vain ylläpitäjä; palvelin laskee ryhmän itse, virheitä ei voi hyväksyä, vahvistuskysely ennen).
6. **Huoltotoimien hallinta kompaktiksi:** kohteet ryhmitelty Ryhmän mukaan suljettaviin osiin (kohteiden ja pois käytöstä olevien määrä näkyy), yksi rivi per kohde (nimi, ryhmä, tyyppi, käytössä, Tallenna). Tallennuksen jälkeen palataan samaan kohteeseen (`#kohde-<tunniste>`) ja ryhmä aukeaa. "Lisää"-lomakkeet ovat oletuksena suljettuja.
7. **Tietoturva:** neljä pitkää selitystekstiä siirretty "Lue lisää suojauksesta" -osioon; näkyviin tilamerkit (HTTPS, tietokannan oikeudet, istunnon kesto).
8. **Avattavat osiot näyttävät avattavilta:** ▸-nuoli ja kehys ("Luo käyttäjä", "Aseta uusi väliaikainen salasana", tapahtumaloki jne.). Vain asetussivulla.
9. **Pienet:** "Ominaisuudet"-otsikko ominaisuusvalinnoille; "⚠ Y-tunnus puuttuu" -merkki, kun laskutus on käytössä mutta Y-tunnus tyhjä; mekaanikon valinta mahtuu käyttäjärivillä.
10. **Kelluva Tallenna myös työpöydälle.** Asetukset-lomakkeen Tallenna-palkki pysyy näkyvissä (sticky), ja palkissa näkyy "● Tallentamattomia muutoksia", kun lomaketta on muutettu.

**Varasto:** kelluva Tallenna-palkki (`.inventory-savebar`) on nyt kelluva myös työpöydällä (aiemmin vain mobiilissa).

**Omat tiedot**
- Otsikossa nimi, tunnus, rooli ja viimeisin kirjautuminen ("tester · tester" -toisto poistettu).
- **Näytä salasanat** -valinta salasanalomakkeessa.
- **Oletusmekaanikko** voi valita itse (uusi toiminto `update_own_mechanic`, kaikki roolit; vain kun mekaanikot-ominaisuus on käytössä; käyttää `authMechanicId()`-tarkistusta).
- **Kirjaa ulos muut istunnot** (uusi toiminto `logout_others`, vaatii oman salasanan, nostaa `session_version`-arvoa; nykyinen istunto jatkuu). Kirjautuu tapahtumalokiin.

**Muutetut tiedostot:** `views/settings.php` (rakenne uusittu), `views/account.php`, `app/cars.php` (`dataIssueGroup()`), `app/actions.php` (`accept_data_issues`, kohde-ankkuri), `app/auth.php` (`logout_others`, `update_own_mechanic`, oikeustaulu, lokiotsikot), `assets/app.css`, `assets/app.js`, `index.php` (versio).

**Testattu:** php -l kaikki tiedostot, node --check app.js. 72 sivua × 3 roolia + 4 Excel-vientiä verrattu 0.8.55:een: vain Asetukset ja Omat tiedot eroavat (sekä varastosivu aikaleimallisten allekirjoitustunnisteiden osalta). POST-sarjojen tietokantatila identtinen. Oikealla selaimella: kaikki välilehdet, ankkurit (`#kohteet`, `#kayttajaloki`), huoltokohteen tallennus ja paluu samaan riviin, Hyväksy kaikki, asetusten tallennus ja kelluva palkki, käyttäjän luonti/muokkaus/salasanan asetus/poisto, oma mekaanikko, istuntojen päättäminen, salasanojen näyttö, mobiili (ei vaakavieritystä), roolit (editori ja katselija), ei JS-virheitä.

## 0.8.55-dev

Varastosivun (`?view=inventory`) selkeytys "ihmisen silmällä". Ei uusia tiedostoja, ei tietokantamuutoksia (skeema pysyy 12).

**A. Pienet korjaukset**
1. Poistettu kehittäjän huomautus "Työpöydällä taulukko inventointiin, puhelimella selkeät kortit."; tilalla lyhyt käyttöohje.
2. "Arvo0,00 €" korjattu: väli lisätty, ja kun hankintahintoja ei ole, näytetään "–" (sekä otsikossa että rivin saldoarvossa).
3. Hylly-kenttä levennetty (172 px), numerokentät kavennettu jotta taulukko mahtuu 1280 px leveälle ilman vaakavieritystä.
4. Yli kahdelle autolle sopivasta osasta näytetään "N autoa" (rekisterilista vihjeessä). Auton solussa rekisteri ja malli sen alla.
5. Epäselvä "Avaa"-painike nimetty "Auton osat ›" (title: "Avaa auton varaosat").
6. "N varoitusrajalla" -merkki on nyt painike, joka suodattaa vähissä olevat rivit (uusi klikkaus palauttaa kaikki). Rivillä "⚠️ Vähissä" -merkintä saldon alla.

**B. Mobiili**
- Rivit ovat tiiviitä kortteja; yksikkö, varoitusraja, hankintahinta ja saldoarvo ovat "Lisätiedot ▾" -painikkeen takana (`.inv-extra` / `.is-open`). Mobiilisivun korkeus n. 18 000 → 11 600 px.

**C. Pikatoiminnot**
- Jokaisella rivillä −/＋ -painikkeet: avaavat dialogin (suunta, määrä, huomautus) ja kirjaavat varastotapahtuman tulleena/otettuna (ei "saldokorjauksena"). Käyttää olemassa olevaa `adjust_part_stock`-toimintoa uudella piilokentällä `return=inventory`. Selain estää suuremman oton kuin saldo; palvelin estää senkin ja palaa varastosivulle virheilmoituksella.
- "＋ Lisää varaosa" -painike ja dialogi varastosivulla (`add_part`, `return=inventory`): auto, nimike, yhteensopivat autot, saldo ym.
- Jos massatallennuslomakkeella on tallentamattomia muutoksia, dialogit eivät avaudu (varoitus), jotta muutokset eivät katoa.
- Painikkeet näkyvät vain oikeuksilla: `canAction('adjust_part_stock')` ja `canAction('add_part')` (katselija ei näe niitä).

**Muutetut tiedostot:** `views/inventory.php`, `app/actions.php` (return=inventory -uudelleenohjaus onnistumisessa ja virheessä), `assets/app.js`, `assets/app.css`, `index.php` (versio).

**Testattu:** php -l kaikki tiedostot, node --check app.js; 72 sivua × 3 roolia + 4 Excel-vientiä verrattu 0.8.54:ään: vain varastosivut (2 × 3) eroavat; POST-sarjojen tietokantatila identtinen. Oikealla selaimella: +/−, yli saldon -esto, lisäysdialogi, matala-saldo-suodatin, tallentamattoman lomakkeen esto, massatallennus, mobiilin Lisätiedot, roolit (katselija/editori), ei JS-virheitä.

## 0.8.54-dev
Auton sivun selkeytys: sivu on noin 58 % lyhyempi työpöydällä (18 100 → 7 600 px, mitattu esimerkkiautolla, jolla on 38 huoltoa) ja noin 45 % lyhyempi puhelimella (27 200 → 14 800 px). Vain ulkoasu ja näkyvyys muuttuvat: tallennus, kentät ja tietokanta ovat täsmälleen ennallaan.

- **Kirjaa huolto -lomake:** huoltokohteet ovat ensin pelkkä rastilista (rasti ja nimi). Toimenpide-, määrä-, merkki-, varaosanumero-, OEM-, huomio-, hinta- ja varastokentät avautuvat vasta, kun kohde rastitetaan. Sama toimi aiemmin vain puhelimella (leveys alle 900 px), nyt myös työpöydällä. Vihjetekstissä näkyy lisäksi laskuri "Valittu N kohdetta". Ajankohtaisten kohteiden nimen vieressä on pieni tilamerkki (esim. HUOLTOAJANKOHTA), jotta ne löytää listasta.
- **Huoltotilanne ja ennuste:** keltainen ennakkovaroitus (kiireelliset kohteet) näytetään heti, ja koko kohdetaulukko on painikkeen takana: "Näytä kaikki N huoltokohdetta". Jos mikään ei ole kiireellinen, taulukko on auki. Taulukon lajittelu toimii kuten ennenkin.
- **Toistuva teksti pois:** sininen rivi "Huolto on jo ajankohtainen" näytetään enää vain, kun siinä on uutta tietoa. Tilamerkki kertoo saman.
- **Huoltohistoria:** 10 uusinta huoltoa näkyy heti, ja loput ovat painikkeen "Näytä vanhemmat N huoltoa" takana. Haku ja suodattimet (teksti, tapahtumatyyppi, vuosi) hakevat aina kaikista huolloista, ja painike piiloutuu haun ajaksi.
- **Visuaalinen korjaus:** ennakkovaroituksen tilamerkit ja historiakorttien "N työtä" -merkit venyivät rivin korkuisiksi pulloiksi. Ne ovat nyt pieniä tunnisteita.
- **Muutetut tiedostot:** `views/car.php`, `assets/app.css`, `assets/app.js`.
- **Tarkistettu:** auton sivun lomakkeen kaikki kentät, arvot ja valinnat ovat täsmälleen samat kuin 0.8.53:ssa (21 sivua, 3 roolia); kaikki muut 51 sivua identtisiä; selaimessa lomake täytettiin ja tallennettiin molemmilla versioilla ja tietokantaan tuli täsmälleen samat rivit; hakusuodatus, "Näytä vanhemmat", "Valitse nämä huoltolomakkeelle" ja taulukon avaus toimivat; ei JavaScript-virheitä.

## 0.8.53-dev
Pilkonnan vaihe 6: loput funktiot omiin tiedostoihinsa. `index.php` on nyt pääohjain: siinä ei ole enää funktioita (paitsi `authHttps()`). Tietokanta (skeema 12) käy sellaisenaan.

- **Siirretty:** `app/invoices.php` (4 funktiota), `app/inventory.php` (18), `app/customers.php` (11), `app/images.php` (19) ja `app/edits.php` (8). Lisäksi `mechanicsList()` ja `defaultMechanicId()` siirtyivät `app/maintenance.php`:hen.
- **Ainoa koodimuutos:** `photoAbsolutePath()` ja `logoAbsolutePath()` käyttivät `__DIR__`-vakiota, joka olisi osoittanut `app/`-kansioon. Ne käyttävät nyt `APP_DIR`-vakiota, joka on sovelluksen juurikansio. Tulos on sama kuin ennen.
- **Lataus:** `index.php` lataa funktiotiedostot heti vakioiden jälkeen järjestyksessä `helpers` → `db` → `auth` → `exports` → `cars` → `maintenance` → `invoices` → `inventory` → `customers` → `images` → `edits`.
- **Ei `bootstrap.php`:ta:** latausrivit jätettiin tarkoituksella `index.php`:hen. Palautuksen tarkistus (`app/backup.php`) lukee ZIP:n `index.php`:n `require`-rivit ja varmistaa, että kaikki tiedostot ovat mukana; erillinen bootstrap-tiedosto piilottaisi rivit tarkistukselta.
- **Jäi `index.php`:hen:** istunnon ja suojaotsikoiden alustus, vakiot, tietokannan avaus, kuvien ja logon tarjoilu (`?image=`, `?brand_logo=`), sivun datan haku ja näkymän valinta. Nämä ovat ohjaimen työtä, ei funktioita.
- **Siivottu:** tyhjiksi jääneet osio-otsikot ja ylimääräiset tyhjät rivit pois, tiedoston ylin kommentti päivitetty.
- **Koko:** `index.php` noin 60 kt → noin 17 kt (152 riviä), 63 → 1 funktiota. Funktioita on edelleen yhteensä 214.
- **Tarkistettu:** kaikki 72 sivua identtisiä 0.8.52:n kanssa; 4 Excel-vientiä samat; 12 lisäsivua ja tulostetta samat; asiakkaan lisäys ja poisto, varaosan lisäys, varastosaldon muutokset (myös hylätty liian suuri otto), laskun luonti sekä auton asiakkaan asetus antoivat saman tietokannan sisällön; huoltokuvan kolme kokoa (`thumb`, `web`, `original`) ja logo palautuivat tavu tavulta samoina, luodut pienennetyt tiedostot samat ja polkuhyökkäys logon kautta hylättiin (404); ei PHP-varoituksia.

## 0.8.52-dev
Pilkonnan vaihe 5: autot ja huollot omiin tiedostoihinsa. Pelkkä koodin siirto: funktioiden sisältö ei muutu, ja tietokanta (skeema 12) käy sellaisenaan.

- **Siirretty:** `app/cars.php`: 10 funktiota (`getCar`, `getAnnualCosts`, `getOdometerReadings`, `historyMaxOdometer`, `recalculateCurrentKm`, `kilometerHistoryEntries`, `odometerTimelinePoints`, `drivingRateEstimate`, `biltemaVehicleLink`, `dataCheckIssues`).
- **Siirretty:** `app/maintenance.php`: 35 funktiota (huoltotyypit ja toimenpidelajit, `allItems`, `itemMap`, hihnatarkastukset, huoltorivien tekstit ja hinnat, huoltovälien seuranta, `calculateMaintenanceStatus`, `forecastForDue`, `dueInfo` ym.).
- **Lataus:** `index.php` lataa nyt `helpers.php` → `db.php` → `auth.php` → `exports.php` → `cars.php` → `maintenance.php` heti vakioiden jälkeen.
- **Jäi `index.php`:hen:** varasto, asiakkaat, laskut, kuvat ja logot sekä muokkausristiriitojen käsittely (siirtyvät seuraavissa vaiheissa).
- **Koko:** `index.php` noin 97 kt → noin 60 kt, 108 → 63 funktiota. Funktioita on edelleen yhteensä 214 (mukana `app/`- ja `views/`-tiedostot).
- **Tarkistettu:** kaikki 72 sivua (3 roolia × 24) identtisiä 0.8.51:n kanssa; 4 Excel-vientiä samat; 12 lisäsivua (auton sivu, muokkaus, kaikki tulosteet, Kaikki huollot) samat; lisäksi mittarin päivitys (hyväksytty ja hylätty), huollon lisäys ja muut tallennukset antoivat täsmälleen saman tietokannan sisällön kuin ennen; ei PHP-varoituksia.

## 0.8.51-dev
Siivous: vanhan tietokantanimen tuki poistettu kokonaan, koska kaikki kannat on jo nimetty `autohuolto.sqlite3`:ksi.

- **Poistettu:** `dbRejectLegacyName()` (`app/db.php`), sen kutsu `index.php`:stä ja vakio `LEGACY_DB_FILE`. Jos tietokantaa ei löydy, ohjelma toimii kuten uudessa asennuksessa eikä enää neuvo `.autohuolto_7f3c91.sqlite3`-nimen muuttamista.
- **Poistettu:** vanhan nimen ohitus varmuuskopion tiedostosuodatuksessa (`app/backup.php`).
- **Dokumentaatio:** nimeämisohje poistettu `BACKUP.md`:stä; latausjärjestys päivitetty tähän tiedostoon.
- Muu koodi ennallaan.

## 0.8.50-dev
Pilkonnan vaihe 4: käyttäjät ja Excel-viennit omiin tiedostoihinsa. Pelkkä koodin siirto: funktioiden sisältö ei muutu, ja tietokanta (skeema 12) käy sellaisenaan.

- **Siirretty:** `app/auth.php`: 27 funktiota (`auth*`, `roleLabel`, `canAction`, `loggedIn`) ja vakiot `AUTH_DUMMY_HASH`, `AUTH_IDLE_SECONDS`, `AUTH_MAX_SECONDS`, `AUTH_AUDIT_PAGE_SIZE`.
- **Siirretty:** `app/exports.php`: 11 funktiota (`xlsxXml`, `xlsxCol`, `xlsxSafeSheetName`, `xlsxSheetXml`, `outputXlsx`, `carExportSheets`, `exportCarXlsx`, `inventoryExportPartRows`, `inventoryExportSheets`, `exportInventoryXlsx`, `exportAllCarsXlsx`).
- **Jäi `index.php`:hen tarkoituksella:** `authHttps()` (istunto alustetaan ennen tiedostojen latausta) sekä `inventoryEventLabel()` ja `inventoryHistoryPage()` (varastoalue, siirtyvät myöhemmin `inventory.php`:hen).
- **Lataus:** `index.php` lataa nyt `helpers.php` → `db.php` → `auth.php` → `exports.php` heti vakioiden jälkeen.
- **Dokumentaatio:** hakemistopuu, tiedostotaulukko ja latausjärjestys päivitetty; poistettu tyhjäksi jäänyt otsikko.
- **Koko:** `index.php` 146 kt → noin 97 kt, 146 → 108 funktiota. Kaikkiaan funktioita on edelleen 196.
- **Tarkistettu:** kaikkien 72 sivun tuloste (3 roolia × 24 sivua) identtinen 0.8.49:n kanssa; neljä Excel-vientiä (auto 2, auto 3, kaikki autot, varasto) sisällöltään tavu tavulta samat; väärä salasana, kirjautuminen, uloskirjautuminen ja katselijan lomakkeen esto (403) toimivat; ei PHP-varoituksia.

## 0.8.49-dev
Pilkonnan vaihe 3: tietokanta ja yleiset apufunktiot omiin tiedostoihinsa sekä vanhojen migraatioiden poisto. Skeema säilyy versiossa 12, joten nykyinen tietokanta käy sellaisenaan.

**Tärkeää päivityksessä:** tietokannan nimi on nyt aina `autohuolto.sqlite3`. Jos tietokantasi on vielä vanhalla nimellä `.autohuolto_7f3c91.sqlite3`, ohjelma ei luo tyhjää kantaa vaan näyttää ohjeen. Nimeä tiedosto sovelluskansiossa: `mv .autohuolto_7f3c91.sqlite3 autohuolto.sqlite3` (ja jos olemassa, samoin `-wal` ja `-shm`). Tee tämä sekä testi- että tuotantokansiossa. Tiedot eivät muutu.

- **Siirretty:** `app/helpers.php` (29 yleistä apufunktiota: lomakkeen luku, muotoilu, päivämäärät, hinnat ja ALV) ja `app/db.php` (18 siirrettyä funktiota: skeema, alustus, yhteys, sovellusasetukset ja ominaisuuskytkimet; `authSchema()` siirtyi tänne skeeman mukana). Funktiot on siirretty muuttamatta niiden koodia. `index.php` pieneni 177 kt:sta noin 146 kt:iin.
- **Uutta:** `index.php`:n tietokannan avauslohko jaettiin kolmeksi funktioksi `app/db.php`:ssa: `dbRejectLegacyName()`, `dbAcquireLock()` ja `dbConnect()`. Toiminta on sama: yksi pyyntö kerrallaan -lukko, versiotarkistus ennen kirjoittamista, WAL-tila, alustus ja tiedosto-oikeus 0600.
- **Muutettu: tietokannan nimi.** Oletus on aina `autohuolto.sqlite3` sovelluskansiossa. Vanhan pistenimen `.autohuolto_7f3c91.sqlite3` automaattinen käyttö poistettiin. Ympäristömuuttuja `AUTOHUOLTO_DB_PATH` toimii ennallaan ja ohittaa nimentarkistuksen. Vanha pistenimi tunnistetaan edelleen kahdessa kohdassa: opasteviestissä ja siinä, ettei ZIP-backup sisällytä sitä itseensä.
- **Poistettu: vanhojen versioiden migraatiot.** `repairPartCompatibilityOnce()` (0.8.22:n kertakorjaus) ja `ensureBeltInspectionItems()` (0.8.24:n kertakorjaus) sekä niiden kutsut joka sivulatauksella. Uuden kannan alustus ei enää kirjoita lippua `migration_0822_part_compatibility`. Skeemaversion tarkistus säilyy: vain skeema 12 hyväksytään, ja vanhempi tai uudempi kanta hylätään selkeällä virheellä.
- **Poistettu: kirjautumattoman vanhan kannan päivityspolku.** `authBootstrap()` ei enää tee `.pre-users-*`-turvakopiota eikä kirjoita kuollutta asetusta `auth_legacy_setup` (sitä ei luettu missään). Käyttäjätaulut luodaan edelleen automaattisesti, jos niitä ei ole.
- **Huomio olemassa oleviin kantoihin:** asetustaulussa saattaa olla jäljellä vanhoja rivejä (`migration_0822_part_compatibility`, `feature_0824_belt_inspections`, `auth_legacy_setup`). Ne eivät haittaa mitään, eikä niitä tarvitse poistaa.
- **Testattu:** 72 sivua (24 osoitetta × ylläpitäjä, muokkaaja ja katselija) renderöitiin versiolla 0.8.48-dev ja uudella versiolla samalla tietokannalla (sinun tietokantasi kopio): HTML on identtinen. Lisäksi: uuden asennuksen alustus (hihnatarkastuskohteet ovat mukana), SQLite- ja ZIP-backup ja -palautus, vanhan 0.8.48-ZIPin palautus uuteen asennukseen, puutteellisen ZIPin hylkäys, vanhan kantanimen opaste ja uudelleennimeäminen, sekä `AUTOHUOLTO_DB_PATH`.
- **Jäljellä `index.php`:ssä:** 146 funktiota (noin 120 kt). Suurimmat ryhmät: kirjautuminen ja käyttöoikeudet (`auth*`, 40 kpl), Excel-viennit (`xlsx*`, `export*`), tiedonhaku (autot, huollot, varaosat, asiakkaat, laskut, kuvat), huoltoennusteet ja tietojen tarkistus sekä muokkauskonfliktien hallinta (`edit*`). Ne on tarkoitus siirtää seuraavissa vaiheissa toiminnoittain.

## 0.8.48-dev
Pilkonnan vaihe 2: sivun HTML siirretty `index.php`:stä kansioon `views/`. Toiminta, osoitteet, lomakkeet ja tietokanta ovat ennallaan (skeema 12), joten nykyinen tietokanta käy sellaisenaan.

- **Siirretty:** `index.php`:n HTML-osa jaettu yhdeksään näkymään (`home`, `car`, `all_services`, `customers`, `invoices`, `invoice`, `inventory`, `settings`, `account`) sekä sivun ylä- ja alaosaan (`layout_top`, `layout_bottom`). `index.php` pieneni noin 323 kt:sta 178 kt:iin. Siirrettävä koodi on kopioitu sellaisenaan, vain haaravalinta on nyt `index.php`:n lopussa `if/elseif`-ketjussa.
- **Korjattu siirron yhteydessä:** auton huoltolomakkeen luonnosavain laskettiin `__DIR__`-vakiosta. Se korvattiin uudella `APP_DIR`-vakiolla, jotta avain pysyy samana ja selaimeen tallennetut keskeneräiset huoltoluonnokset säilyvät.
- **Uutta:** palautus tarkistaa ennen muutoksia, että kaikki tiedostot, joita ZIPin `index.php` lataa (`require __DIR__ . '/...'`), ovat paketissa. Keskeneräinen tai vioittunut ZIP ei voi enää korvata toimivaa ohjelmaa sellaisella, joka ei käynnisty.
- **Huomio vanhoista backupeista:** versiota 0.8.47-dev tai vanhempaa vastaava täysi ZIP palauttaa vanhan `index.php`:n, ja palautus poistaa samalla `views/`-tiedostot, joita vanha versio ei tunne. Tulos on yhtenäinen vanha asennus. SQLite-palautus ei koske ohjelmatiedostoihin.
- **Testattu:** 24 sivua kolmella käyttöoikeustasolla (ylläpitäjä, muokkaaja, katselija) eli 72 sivua renderöitiin vanhalla ja uudella versiolla samalla tietokannalla; HTML on identtinen (lukuun ottamatta joka latauksella vaihtuvia tunnisteita ja rivinvaihtoja tagien välissä).
- **Ei muutettu:** kirjautumissivut ja virhesivut (`authPage`, "Tallennus estettiin") ovat edelleen `index.php`:ssä. `app/actions.php` ja `app/printing.php` ovat ennallaan.

## 0.8.47-dev
Korjaukset 0.8.46-dev:n katselmoinnin pohjalta. Tietokannan skeema säilyy versiossa 12, joten nykyinen tietokanta käy sellaisenaan.

- **Korjattu: palautus ei toiminut lainkaan.** `index.php` latasi `app/actions.php`:n ennen `app/backup.php`:ta, joten `restore_db` päätyi virheeseen `Call to undefined function backupRestore()`. Latausjärjestys on nyt `backup.php` → `printing.php` → `actions.php`. Kokeiltu: sekä SQLite- että ZIP-palautus toimivat.
- **Korjattu: täyden ZIP-palautuksen jälkeen tiedostojen oikeudet menivät 0600:ksi.** `umask(0077)` teki palautetuista tiedostoista web-palvelimelle lukukelvottomia (esim. `assets/app.css`, `assets/app.js`). Nyt jo olemassa olevan tiedoston tai kansion oikeudet säilytetään, ja uudet saavat 0644 / 0755 (`kuvat/`: 0660 / 0770). Tietokannan oikeudet (0600) eivät muutu.
- **Muutettu: palautus koskee vain ohjelman omia tiedostoja.** Palautettavaa ZIPiä kopioidaan vain `index.php`, `CHANGELOG.md`, `BACKUP.md`, `README.md` sekä kansiot `app/`, `assets/`, `views/` ja `kuvat/`. Muut tiedostot (esim. oma `.htaccess`, `robots.txt`, `shell.php`) ohitetaan eikä niitä kirjoiteta, ja palautus ei enää poista kansiosta tiedostoja, joita ZIPissä ei ole. Ohitettujen määrä kerrotaan palautuksen jälkeen. Backup-ZIP sisältää edelleen koko kansion.
- **Muutettu: `kuvat/`-kansioon palautetaan vain kuvia** (`jpg`, `jpeg`, `png`, `webp`) ja suojaava `index.html`. Ajettavaa koodia ei palauteta sinne.
- **Uutta: palautuksen vanhat turvakopiot siivotaan.** Onnistuneen palautuksen yhteydessä poistetaan yli 30 päivää vanhat `.pre-restore-*`-tiedostot ja -kansiot (tuore turvakopio säilyy). Aiemmin ne kasautuivat pysyvästi.
- **Uutta: ympäristötarkistus käynnistyksessä.** Jos PHP on vanhempi kuin 8.2 tai `pdo_sqlite` / `mbstring` puuttuu, näkyy selkeä virheilmoitus eikä `Call to undefined function mb_strlen()`. `zip` ja `gd` ovat edelleen valinnaisia.
- **Korjattu: ZIP-palautus hylkäsi ZIPit, joissa on hakemistomerkintöjä** (esim. `zip -r`-komennolla tai muulla työkalulla tehty paketti): loppukauttaviiva tulkittiin virheelliseksi poluksi. Ohjelman oma backup ei sisällä hakemistomerkintöjä, joten se ei ollut vaikuttanut sinun varmuuskopioihisi.
- **Siivottu: käyttämätön `backupManifest()` poistettu** `app/backup.php`:sta. Vanhojen manifestillisten backupien palautustuki (`backupValidateManifest`) säilyy.
- **Siivottu: CHANGELOG** järjestetty uudelleen (versiot samassa laskevassa järjestyksessä yhden otsikon alla) ja lisätty tämä tiedostorakenne.
- **Ei muutettu:** tietokannan sijainti ja nimi, skeema, käyttäjät, näkymien logiikka. `$GLOBALS`-silmukka `backupRestore()`:ssa jätettiin ennalleen, koska sen poistaminen ei ollut testattavissa kaikissa palvelinympäristöissä.

## 0.8.46-dev
- Korjattu varmuuskopioiden HTTP-vastaus: `Content-Length`-headeri muodostettiin aiemmin virheellisellä `header()`-kutsulla, mikä saattoi rikkoa vastauksen ja aiheuttaa selaimessa `ERR_INVALID_RESPONSE`-virheen sekä sekä SQLite- että full-backupissa.
- Backupin muu toimiva 0.8.45-rakenne säilytettiin ennallaan.

## 0.8.45-dev
- Korjattu backupin väliaikaishakemiston luonti palauttamalla 0.8.38:ssa toimivaksi todettu `sys_get_temp_dir()`-pohjainen toteutus.
- Backupin temp-hakemiston luonti tapahtuu nyt `try`-lohkon sisällä, jotta palvelinympäristön mahdollinen kirjoitusvirhe näkyy sovelluksen omana virheilmoituksena eikä paljaana HTTP 500 -virheenä.
- Muuhun auton, huolto-ohjelman, varaosien ja varaston toimintaan ei tehty muutoksia.

## 0.8.44-dev
- Korjattu varmuuskopioinnin väliaikaishakemisto: backup yrittää nyt käyttää ensin tietokannan/sovelluksen omaa kirjoitettavaa hakemistoa eikä oletusarvoisesti palvelimen yleistä `sys_get_temp_dir()`-hakemistoa.
- Pelkkä SQLite-backup ja täysi ZIP-backup käyttävät samaa varmistettua snapshot-polkuja, mikä poistaa aiemman erillisen ZIP-työtilan.
- ZIP-backupin `ZipArchive`-elinkaari yksinkertaistettiin: avaus ja sulkeminen tehdään tasan kerran, virheellinen valmis ZIP poistetaan.

## 0.8.43-dev
- Korjattu täyden ZIP-backupin palvelinvirhe. Backupin luontiin ei enää rakenneta eikä tarkisteta erillistä SHA-256-manifestia; ZIP sisältää suoraan eheän tietokantasnapshotin ja koko sovelluspuun tiedostot.
- ZIP-luonti on palautettu mahdollisimman yksinkertaiseksi `ZipArchive`-poluksi, jotta se toimii paremmin jaetuilla palvelimilla.
- Korjattu `app.js`:n `serviceForm`-alustuksen järjestys. Selain ei enää pysäytä koko sovelluslogiikkaa `Cannot access 'serviceForm' before initialization` -virheeseen.
- Palautus tukee edelleen myös aiempia manifestillisia täysiä backupeja.

## 0.8.42-dev
- Korjattu täyden ZIP-backupin luonti.
- ZIP muodostetaan nyt yhdestä etukäteen kerätystä tiedostolistasta, joten manifesti ja varsinainen paketti vastaavat toisiaan.
- Poistettu tyhjien hakemistojen erillinen lisääminen ZIPiin, joka aiheutti palvelinkohtaisia `ZipArchive`-ongelmia.
- ZIP suljetaan vain kerran onnistuneella polulla, eikä suljettua tai alustamatonta `ZipArchive`-oliota enää yritetä sulkea virhepolulla.
- Täysi backup sisältää edelleen koko sovelluspuun, kuvat, tietokannan sekä käyttäjät ja käyttöoikeudet.

## 0.8.41-dev
- Palautettu auton, huolto-ohjelman, varaosien ja varaston näkymien päälogiikka 0.8.34-version tunnetusti toimivaan pohjaan. Rakenteelliset refaktoroinnit eivät enää muuta näiden näkymien suorituspolkua.
- Vahvistettu tietokannan valinta: näkyvä `autohuolto.sqlite3` voittaa, vanha `.autohuolto_7f3c91.sqlite3` toimii yhteensopivana vaihtoehtona, jos näkyvää kantaa ei ole.
- Täysi backup sisältää koko sovelluspuun, tiedostot, alihakemistot, kuvat, tietokannan sekä käyttäjät ja oikeudet.
- Täysi backup on suoraan kopioitavissa uuteen kansioon; mukana on SHA-256-manifesti tiedostojen eheyden tarkistamiseen.
- Täysi ZIP-palautus palauttaa myös sovellustiedostot ja käyttää backupin käyttäjiä sekä oikeuksia. SQLite-palautus palauttaa tietokannan käyttäjineen.
- Käyttöönoton erillinen käyttöönottokoodi poistettu. Ensimmäinen ylläpitäjä luodaan suoraan tyhjään kantaan.
- `app/printing.php` sisältää edelleen tulostus/PDF-toiminnot, mutta tulostuksen lisäksi varsinaisten näkymien ydinkoodi pidettiin 0.8.34-pohjassa vakauden vuoksi.
- Asiakkaan pysyvä poistaminen säilytettiin mukana vain aidosti viitteettömälle asiakkaalle.

## 0.8.34-dev
- Suuri refaktorointivaihe: POST-toiminnot irrotettu `index.php`:stä erilliseen `app/actions.php`-tiedostoon.
- `index.php` jäi tässä vaiheessa pääohjaimeksi ja jakaa edelleen saman tietokanta- ja käyttöoikeuskontekstin `app/actions.php`:n kanssa.
- Toiminnallisuutta, lomakkeita tai URL-osoitteita ei tarkoituksellisesti muutettu.

## 0.8.33-dev
- Huoltotilanteen seuranta-taulukon sarakkeet ovat klikattavasti lajiteltavia.

## 0.8.32
- Lisätty käsin kustannusten vuosilaskelmat.


## 0.8.31
- Varasto-sivun yhteinen tapahtumahistoria, rajaukset ja sivutus.
- Ylläpitäjän vahvistettu lokin tyhjennys säilyttää saldot ja huoltojen varastokäytön.

## 0.8.30
- Käyttäjähallinnan loki sivutettu, ylläpitäjän vahvistettu tyhjennys.
- Poistettavaksi voi valita vain kirjautumiset tai koko tapahtumalokin.
- Auton huollot poistetaan ennen omia varasto-osia viiteavainten vuoksi.

## 0.8.29
- Huoltolomakkeen määrä ja yksikkö pysyvät omassa sarakkeessaan.
- Keskikokoinen tietokoneikkuna järjestää kentät useammalle riville.

## 0.8.28
- Vanhentuneiden muokkauslomakkeiden tarkistus ja nimikekohtainen varastotallennus.
- Ristiriita ei ylikirjoita uudempaa tietoa; hylätyt syötteet näytetään kopioitavina.
- Huoltoluonnos on käyttäjäkohtainen, ja palautus tarjotaan sivulla ilman ponnahdusikkunaa.

## 0.8.27
- Käyttäjän pysyvä poisto sekä katselijan varaston nimikkeet ja saldot.
- Viimeinen ylläpitäjä ja tapahtumaloki säilyvät; varaston haku toimii myös katselussa.

## 0.8.26
- Henkilökohtaiset käyttäjät, roolit, salasanatiivisteet ja kirjautumissuoja.

## 0.8.25
- Verolliset tulostehinnat lihavoitu, toimenpiteet tasattu omaan sarakkeeseen.

## 0.8.24
- Selkeät verottomat/verolliset hinnat, sivunumerot ja puuttuvien km:n tekstit.
- Hihnojen tarkastus ja vaihto seurataan erikseen; alkuperäinen historia säilyy.

## 0.8.23
- Suora nykyisen skeeman luonti, vanhat rakenne- ja datamigraatiot poistettu.
- Nykyinen skeema-12-kanta toimii sellaisenaan. Vanhemmat kannat päivitetään ensin v0.8.22-dev:llä; liian vanhat/uudet kannat ja palautukset hylätään ennen korvaamista.
- Uusi kanta ja sen valmiit huoltokohteet alustetaan yhdessä transaktiossa.
- Nykyisen kannan asetuksia, kohdeprofiileja tai historiallisia snapshotteja ei kirjoiteta sivua avatessa uudelleen. 0.8.22:n varaosasopivuuksien kertakorjaus säilyy.

## 0.8.22-dev
- Käyttöönotto ja skeema-/palautusrajaukset ylläpidetään aiemman version mukaisesti.

## Huomioita
- 0.8.26 käyttöönotto: luo oma ylläpitäjätunnus palvelimelta luettavan `.autohuolto-setup-7f3c91.php`-koodin avulla. Tallenna näytetty palautuskoodi. Käyttäjät: Asetukset; oma salasana: Omat tiedot.
- Asennuspaketin `asennus.md` sisältää palvelimen lataussuojan ja hätäpalautuksen ohjeet.
- Asennus: korvaa aiempi PHP-tiedosto, säilytä sen tietokanta ja `kuvat`-hakemisto.
- Uusi asennus tarvitsee kirjoitusoikeuden ohjelman hakemistoon; ZIP ja Excel vaativat `ZipArchive`:n.
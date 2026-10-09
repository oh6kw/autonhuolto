# Autonhuolto – ohje: asennus, käyttö ja varmuuskopiointi

Tämä ohje kattaa neljä asiaa:

1. [Mikä Autonhuolto on ja mitä se vaatii](#1-mikä-autonhuolto-on-ja-mitä-se-vaatii)
2. [Tiedostorakenne](#2-tiedostorakenne) (mikä tiedosto tekee mitä)
3. [Asennus palvelimelle vasta-alkajalle](#3-asennus-palvelimelle-vasta-alkajalle)
4. [Käyttöohje](#4-käyttöohje)
5. [Varmuuskopiointi ja palautus](#5-varmuuskopiointi-ja-palautus)
6. [Ohjelman päivitys](#6-ohjelman-päivitys)
7. [Jos jokin ei toimi](#7-jos-jokin-ei-toimi)

Muutoshistoria on erillisessä tiedostossa `CHANGELOG.md`.

---

## 1. Mikä Autonhuolto on ja mitä se vaatii

Autonhuolto on vapaa ohjelmisto (lisenssi GNU AGPL v3, ks. `LICENSE`). Jos ajat muokattua versiota palveluna muille, sinun on tarjottava heille lähdekoodi; aseta osoite vakioon `APP_SOURCE_URL` tiedostossa `index.php`.

Autonhuolto on kevyt huoltokirja- ja pienkorjaamo-ohjelma: autot, mittarilukemat, huoltohistoria, huoltoennusteet, varaosat ja varasto, asiakkaat ja omistushistoria, mekaanikot, laskut, tulosteet sekä Excel-viennit. Ohjelma on yksi PHP-sovellus ja yksi SQLite-tietokantatiedosto. Erillistä tietokantapalvelinta ei tarvita.

**Palvelimelta vaaditaan**

| Vaatimus | Huomio |
|---|---|
| PHP 8.2 tai uudempi | Vanhemmalla PHP:llä ohjelma kertoo puuttuvan version eikä käynnisty |
| PHP-laajennus `pdo_sqlite` | Pakollinen (tietokanta) |
| PHP-laajennus `mbstring` | Pakollinen |
| PHP-laajennus `zip` | Suositeltu: täysi ZIP-varmuuskopio, ZIP-palautus ja Excel-viennit |
| PHP-laajennus `gd` | Suositeltu: kuvien ja logon pienentäminen |
| Web-palvelin | Apache (mod_php tai PHP-FPM) tai Nginx + PHP-FPM |
| HTTPS | Vahvasti suositeltu, koska ohjelmassa on salasanat |

Ohjelmassa ei ole ulkoisia kirjastoja eikä asennusvaihetta (ei Composeria, ei npm:ää).

## 2. Tiedostorakenne

```
autonhuolto/
├── index.php            Pääohjain: asetukset, istunto, tietokannan avaus, kuvien tarjoilu, sivun datan haku ja näkymän valinta. Ei omia funktioita (paitsi authHttps)
├── app/
│   ├── helpers.php      Yleiset apufunktiot: lomakkeen luku, muotoilu, päivämäärät, hinnat ja ALV (ei tietokantaa)
│   ├── db.php           Tietokanta: tiedoston valinta, lukko, yhteys, skeema (versio 12), alustus ja sovellusasetukset
│   ├── auth.php         Käyttäjät ja kirjautuminen: salasanat, istunto, roolit ja oikeudet, käyttäjähallinta, tapahtumaloki
│   ├── exports.php      Excel-viennit (.xlsx): auton, kaikkien autojen ja varaston vienti
│   ├── cars.php         Auton tiedot: haku, vuosikulut, kilometrihistoria ja -aikajana, ajomääräarvio, tietojen tarkistus
│   ├── maintenance.php  Huolto-ohjelma: huoltotyypit ja kohteet, huoltovälit, huoltotilanne, ennusteet, huoltorivien tekstit ja mekaanikot
│   ├── invoices.php     Laskut: laskunumerointi, jaksoyhteenveto, sallitut tilat ja poistotarkistus
│   ├── inventory.php    Varaosat ja varasto: yhteensopivuudet, saldot, varastotapahtumat ja huoltojen varastokäyttö
│   ├── customers.php    Asiakkaat: haku, asiakasnumerot, poistotarkistukset, auton omistaja ja omistajahistoria
│   ├── images.php       Huoltokuvat ja logo: tiedostopolut, pienennetyt versiot, tallennus ja poisto
│   ├── edits.php        Lomakkeiden muokkausristiriidat: allekirjoitettu tilannekuva ja ristiriitojen tunnistus
│   ├── backup.php       Varmuuskopiot (lataus) ja palautus (SQLite / täysi ZIP)
│   ├── printing.php     Tulosteet ja PDF-näkymät (?print=...)
│   └── actions.php      Kaikki lomakkeiden tallennukset (POST) sekä vientien käynnistys (?export=...)
├── views/               Sivun HTML ja sen esitys. Jokainen tiedosto on yksi näkymä; ne ladataan index.php:stä
│   ├── layout_top.php   Sivun alku: <head>, yläpalkki ja valikko, ilmoitukset
│   ├── layout_bottom.php  Sivun loppu: alatunniste
│   ├── home.php         Etusivu: autolista ja auton lisäys
│   ├── car.php          Yksittäisen auton sivu: kilometrit, huoltoennusteet, huollot, varaosat, auton tiedot
│   ├── all_services.php Kaikki huollot -lista (?view=all)
│   ├── customers.php    Asiakaslista ja asiakkaan tiedot (?view=customers)
│   ├── invoices.php     Laskulista ja yhteenvedot (?view=invoices)
│   ├── invoice.php      Yksittäinen lasku (?invoice=N)
│   ├── inventory.php    Varaosavarasto ja varastotapahtumat (?view=inventory)
│   ├── settings.php     Asetukset: käyttäjät, ulkoasu, mekaanikot, huoltokohteet, backup (?view=settings)
│   └── account.php      Oma tili ja salasanan vaihto (?view=account)
├── assets/
│   ├── app.css          Ulkoasu ja teemat
│   └── app.js           Selaimen toiminnot (lomakkeet, lajittelu, laskurit, varaston +/− ja lisäysdialogit)
├── docs/screenshots/    Esimerkkikuvakaappaukset README:tä varten (keksittyä tietoa; ei tarvita palvelimella)
├── LICENSE              GNU AGPL v3 -lisenssi
├── README.md            Lyhyt esittely (GitHubin etusivu)
├── SECURITY.md          Tietoturvailmoitusten ohje
├── .gitignore           Git: tietokanta, kuvat ja varmuuskopiot eivät mene versionhallintaan
├── .github/FUNDING.yml  GitHubin Sponsor-napin asetus (valinnainen; ei tarvita palvelimella)
├── SETUP.md       Tämä tiedosto: tiedostorakenne, asennus, käyttö ja varmuuskopiointi
├── CHANGELOG.md         Muutoshistoria (vain muutokset versiosta toiseen)
├── BACKUP.md            Varmuuskopioinnin ja palautuksen tekninen ohje
│
│   Syntyvät käytössä (eivät kuulu ohjelmapakettiin):
├── autohuolto.sqlite3   Tietokanta (chmod 600!). Mukana -wal ja -shm -tiedostot käytön aikana
├── kuvat/               Ladatut huoltokuvat ja logot (ohjelma tarjoilee ne PHP:n kautta)
├── .autohuolto-access-7f3c91.lock   Lukitustiedosto tietokantakirjoituksille
└── .pre-restore-*       Palautuksen turvakopiot (siivotaan automaattisesti 30 päivän jälkeen)
```

### Mitä mikäkin tiedosto tekee

| Tiedosto | Tehtävä |
|---|---|
| `index.php` | Käynnistyy ensimmäisenä. Tarkistaa PHP-ympäristön, avaa istunnon ja suojaotsikot, määrittelee vakiot ja lataa kaikki funktiotiedostot (`app/helpers.php` … `app/edits.php`). Avaa tietokannan, tarjoilee kuvat (`?image=`, `?brand_logo=`), lataa lopuksi `app/backup.php`, `app/printing.php` ja `app/actions.php`, hakee sivun datan ja valitsee, mikä `views/`-tiedosto näytetään. Funktioita siinä ei ole (paitsi `authHttps()`).|
| `app/helpers.php` | Pieniä, yleisiä apufunktioita: `post()`, `intpost()`, `floatpost()` (lomakkeen luku), `h()` (HTML-escape), `money()`, `km()`, `fiDate()`, `addMonths()`, `priceNetFromInput()` ym. Ei tietokantaa, joten näitä voi kutsua mistä tahansa. |
| `app/db.php` | Tietokannan tiedosto (`autohuolto.sqlite3`), käyttölukko, yhteys ja versiotarkistus, koko skeema (`currentDatabaseSql()`), uuden kannan alustus ja sovellusasetusten luku/kirjoitus (`appSettings()`, `appSet()`). Ei migraatioita: vain skeemaversio 12 hyväksytään. |
| `app/auth.php` | Kaikki käyttäjiin liittyvä: salasanan tarkistus ja tiivisteet, kirjautumisen epäonnistumisten rajoitus, istunto (`authCurrent()`), käyttäjähallinnan lomakkeiden käsittely (`authHandlePost()`), roolit (admin / muokkaaja / katselija), `canAction()` ja katselijan lomakkeiden suodatus (`authFilterHtml()`), tapahtumaloki (`authAudit()`) ja ensimmäisen käyttäjän luonti (`authBootstrap()`). |
| `app/exports.php` | Excel-tiedoston rakennus (`outputXlsx()`, ilman ulkoisia kirjastoja) sekä yhden auton (`exportCarXlsx()`), kaikkien autojen (`exportAllCarsXlsx()`) ja varaston (`exportInventoryXlsx()`) vienti. Tulostuspohjaiset PDF-näkymät ovat `app/printing.php`:ssa. |
| `app/cars.php` | Auton ja sen mittarilukemien funktiot: `getCar()`, `getAnnualCosts()`, `kilometerHistoryEntries()`, `odometerTimelinePoints()`, `drivingRateEstimate()` (ajomäärä/vuosi), `recalculateCurrentKm()` ja `dataCheckIssues()` (tietojen tarkistuslista). |
| `app/maintenance.php` | Huolto-ohjelma: huoltotyypit ja toimenpidelajit, huoltokohteet (`allItems()`, `itemMap()`), hihnatarkastukset, huoltorivien tekstit ja hinnat, huoltovälien seuranta (`intervalStartKeys()`, `attachServiceIntervals()`), huoltotilanne (`calculateMaintenanceStatus()`) ja erääntymisennusteet (`forecastForDue()`, `dueInfo()`). |
| `app/invoices.php` | `nextInvoiceNumber()`, `invoicePeriodSummary()` (kuukausi-/vuosiyhteenveto), `invoiceAllowedStatuses()` ja `invoiceCanDelete()`. Laskun luonti ja tilan muutos tehdään `app/actions.php`:ssa. |
| `app/inventory.php` | Varaosamuistio ja varasto: `partsForCar()`, `inventoryPartRows()`, yhteensopivuus autoihin (`syncPartCompatibility()`), saldon muutokset (`inventoryApplyPartDelta()`), huollon varastokäyttö (`reconcileServiceInventoryUsage()`) ja varastohistoria (`inventoryHistoryPage()`). |
| `app/customers.php` | `customersList()`, `getCustomer()`, `nextCustomerNumber()`, poistotarkistukset, auton nykyinen omistaja (`currentCustomerForCar()`) ja omistajahistoria (`carCustomerHistory()`). |
| `app/images.php` | Huoltokuvien ja logon polut (`photoAbsolutePath()`, `logoAbsolutePath()`), pienennetyt versiot (`photoDerivativeRelative()`, `logoDerivativeRelative()`), tallennus (`storeUploadedPhotos()`, `storeUploadedLogo()`) ja poisto. Polut lasketaan `APP_DIR`-vakiosta. |
| `app/edits.php` | Muokkausristiriidat: lomake saa allekirjoitetun tilannekuvan (`editToken()`), ja tallennus tarkistaa, ettei joku muu ole muuttanut tietoa (`editConflict()`). |
| `views/*.php` | HTML-näkymät ja niiden esitykseen tarvittava PHP (silmukat, ehdot, muotoilu). Näkymät ladataan `index.php`:n kautta ja käyttävät samoja muuttujia ($db, $car, $app, $currentUser jne.). Ne voivat lukea tietokannasta (esim. listoja ja laskelmia), mutta eivät tee tallennuksia: kaikki tietokantaan kirjoittaminen on `app/actions.php`:ssa. |
| `app/backup.php` | Funktiot ja käsittelijät varmuuskopion lataamiseen (`?backup=db`, `?backup=full`) sekä palautukseen. Palautus validoi kannan, ottaa turvakopion ja vaihtaa tiedostot. |
| `app/printing.php` | Tulostettavat näkymät: huoltotilanne, huoltohistoria, kaikki huollot, yksittäinen huolto, varasto ja lasku (`?print=maintenance|history|all_history|service|inventory|invoice`). |
| `app/actions.php` | Jokainen lomakkeen tallennus (`action=...`): auto, huolto, varaosa, asiakas, lasku, asetukset, palautus. Lopussa vientien käynnistys. |
| `assets/app.css`, `assets/app.js` | Selainpuolen ulkoasu ja toiminnot. |

### Lataus- ja suoritusjärjestys (tärkeä kehittäjälle)

1. `index.php` alkaa: ympäristötarkistus → istunto ja suojaotsikot → vakiot (mm. `DB_FILE`, `APP_DIR`).
2. Heti vakioiden jälkeen ladataan **`app/helpers.php` → `db.php` → `auth.php` → `exports.php` → `cars.php` → `maintenance.php` → `invoices.php` → `inventory.php` → `customers.php` → `images.php` → `edits.php`**. Näiden funktioita käytetään kaikkialla (auth jo tietokannan avauksessa: `authBootstrap()`), joten ne ladataan ensimmäisinä. Ne sisältävät vain funktioita ja vakioita eivätkä suorita mitään latautuessaan.
3. Tietokanta avataan: `dbAcquireLock()` → `dbConnect()` → `authBootstrap()` → asetukset.
4. `index.php` lataa tiedostot tässä järjestyksessä: **`app/backup.php` → `app/printing.php` → `app/actions.php`**.
5. `app/actions.php` ajaa POST-käsittelijänsä heti latautuessaan ja kutsuu mm. `backupRestore()`-funktiota. Siksi sen täytyy olla ladattuna **viimeisenä**. Väärä järjestys rikkoi palautuksen versiossa 0.8.46-dev.
6. `index.php` hakee sivun datan ja lataa lopuksi näkymät: `views/layout_top.php` → yksi näkymä (valinta `if/elseif`-ketjussa tiedoston lopussa) → `views/layout_bottom.php`.

Funktiot, jotka on siirretty omaan tiedostoonsa, ovat käytössä vasta kun tiedosto on ladattu (toisin kuin `index.php`:n omat funktiot, jotka PHP tuntee jo ennen suoritusta). Siksi uuden tiedoston lataus pitää lisätä kohtaan, jossa mikään sen funktioita käyttävä koodi ei ehdi ajaa ennen sitä.

Näkymiä ladataan `index.php`:n globaalissa kontekstissa, joten ne näkevät kaikki sen muuttujat. Käytä näkymissä ja `app/`-tiedostoissa `APP_DIR`-vakiota `__DIR__`:n sijaan, kun tarkoitat sovelluksen juurikansiota.

Kun lisäät uuden tiedoston, lisää se tähän puuhun ja mieti, kutsuuko jokin aiemmin ladattava koodi sen funktioita.

---

---

## 3. Asennus palvelimelle vasta-alkajalle

Alla oleva esimerkki on tehty Debianille tai Ubuntulle (esim. VPS). Komennot ajetaan palvelimella SSH-yhteyden kautta. `sudo` tarkoittaa, että komento ajetaan pääkäyttäjänä.

### 3.1 Asenna PHP ja web-palvelin

**Apache:**

```bash
sudo apt update
sudo apt install apache2 php libapache2-mod-php php-sqlite3 php-mbstring php-zip php-gd
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Nginx:**

```bash
sudo apt update
sudo apt install nginx php-fpm php-sqlite3 php-mbstring php-zip php-gd
```

Tarkista PHP:n versio: `php -v` (pitää olla 8.2 tai uudempi). Jos versio on vanhempi, päivitä käyttöjärjestelmä tai käytä uudempaa PHP-pakettilähdettä.

### 3.2 Kopioi ohjelma palvelimelle

1. Pura ohjelman ZIP omalla koneellasi.
2. Siirrä sisältö palvelimelle esimerkiksi kansioon `/var/www/html/autonhuolto/` (`scp`, `rsync` tai FTP/SFTP-ohjelma, kuten FileZilla).
3. Kansion juuressa pitää olla suoraan `index.php`, `app/`, `assets/` ja `views/`. Jos ZIP purkautui ylimääräisen kansion sisään, siirrä sisältö yhtä tasoa ylemmäs.

**Älä kopioi mukaan vanhaa `.sqlite3`-tiedostoa**, ellet halua siirtää vanhan asennuksen tietoja. Uusi tietokanta luodaan automaattisesti ensimmäisellä käynnistyksellä.

### 3.3 Aseta omistaja ja oikeudet

Web-palvelimen käyttäjä (Debianilla ja Ubuntulla `www-data`) tarvitsee oikeuden **kirjoittaa** ohjelman kansioon: sinne syntyvät tietokanta, lukitustiedosto ja `kuvat/`-kansio.

```bash
cd /var/www/html
sudo chown -R www-data:www-data autonhuolto
sudo find autonhuolto -type d -exec chmod 755 {} \;
sudo find autonhuolto -type f -exec chmod 644 {} \;
```

Tietokantatiedoston oikeudet (`chmod 600`) ohjelma asettaa itse. Jos siirrät vanhan tietokannan käsin, aja `sudo chmod 600 autonhuolto/autohuolto.sqlite3` ja varmista, että omistaja on `www-data`.

### 3.4 Estä tietokannan ja sisäisten tiedostojen suora lataus (tärkeä)

PHP ei voi estää web-palvelinta jakamasta tiedostoja suoraan. Jos tietokantatiedosto on web-kansiossa ilman suojaa, joku voi ladata sen selaimella (`https://osoite/autonhuolto/autohuolto.sqlite3`) ja saa kaikki tiedot ja salasanatiivisteet. Tee siksi **yksi** seuraavista:

**A) Paras: siirrä tietokanta web-juuren ulkopuolelle.** Luo kansio (`sudo mkdir /var/lib/autonhuolto && sudo chown www-data:www-data /var/lib/autonhuolto && sudo chmod 700 /var/lib/autonhuolto`) ja kerro ohjelmalle polku ympäristömuuttujalla `AUTOHUOLTO_DB_PATH`, esimerkiksi `/var/lib/autonhuolto/autohuolto.sqlite3`.
- Apache (mod_php): lisää sivuston asetuksiin `SetEnv AUTOHUOLTO_DB_PATH /var/lib/autonhuolto/autohuolto.sqlite3`
- PHP-FPM: lisää poolin asetustiedostoon (esim. `/etc/php/8.3/fpm/pool.d/www.conf`) rivi `env[AUTOHUOLTO_DB_PATH] = /var/lib/autonhuolto/autohuolto.sqlite3` ja käynnistä PHP-FPM uudelleen.

**B) Estä lataus palvelimen säännöillä** (tee tämä A:n lisäksi tai sen sijaan).

*Apache*: luo ohjelman kansioon tiedosto `.htaccess` (palautus ei koske omaan `.htaccess`-tiedostoosi):

```apache
# Tietokanta, lukitus, turvakopiot ja ohjetiedostot eivät saa latautua selaimella
<FilesMatch "(\.sqlite3|\.lock|\.md)(-wal|-shm)?$|^\.pre-restore-|^\.autohuolto-">
    Require all denied
</FilesMatch>

# Ohjelman sisäiset kansiot: vain index.php saa ajaa niitä. Kuvat tarjoillaan PHP:n kautta.
RewriteEngine On
RewriteRule ^(app|views|kuvat)/ - [F]
```

Apachen sivuston asetuksessa pitää olla `AllowOverride All` (tai vähintään `FileInfo AuthConfig Limit`) ohjelman kansiolle, muuten `.htaccess` ei vaikuta.

*Nginx*: lisää nämä `server { ... }`-lohkoon **ennen** `location ~ \.php$` -lohkoa (korvaa `/autonhuolto` omalla alikansiollasi tai poista se, jos ohjelma on sivuston juuressa):

```nginx
location ~ ^/autonhuolto/(app|views|kuvat)/ { deny all; }
location ~ ^/autonhuolto/.*\.(sqlite3|lock|md)(-wal|-shm)?$ { deny all; }
location ~ ^/autonhuolto/\.(pre-restore|autohuolto) { deny all; }
```

Tarkista Nginx-asetus komennolla `sudo nginx -t` ja lataa se: `sudo systemctl reload nginx`.

**Tarkista, että suoja toimii.** Näiden kaikkien pitää antaa virhe (403 tai 404), ei tiedostoa:

```bash
curl -I https://osoite/autonhuolto/autohuolto.sqlite3
curl -I https://osoite/autonhuolto/app/db.php
curl -I https://osoite/autonhuolto/CHANGELOG.md
```

Jos jokin vastaa `200 OK`, suoja ei toimi.

### 3.5 Ota HTTPS käyttöön

Ilman HTTPS:ää salasanat kulkevat selkokielisinä. Ilmainen varmenne Let's Encryptiltä:

```bash
sudo apt install certbot python3-certbot-apache     # Nginxillä: python3-certbot-nginx
sudo certbot --apache                               # Nginxillä: sudo certbot --nginx
```

Jos ohjelma on HTTPS-käänteisproxyn takana eikä näe HTTPS:ää itse, aseta ympäristömuuttuja `AUTOHUOLTO_FORCE_HTTPS=1` (samalla tavalla kuin `AUTOHUOLTO_DB_PATH` yllä).

### 3.6 Ensimmäinen käynnistys

1. Avaa selaimessa `https://osoite/autonhuolto/`.
2. Ohjelma näyttää sivun **Ensimmäinen käyttöönotto**. Täytä korjaamon nimi, oma nimi, käyttäjätunnus ja salasana (vähintään 12 merkkiä). Erillistä käyttöönottokoodia ei tarvita.
3. Kirjautumisen jälkeen ohjelma ehdottaa toisen ylläpitäjän luomista. **Luo vähintään kaksi ylläpitäjää** (Asetukset → Käyttäjät → Luo käyttäjä, rooli Ylläpitäjä). Jos toinen unohtaa salasanansa, toinen ylläpitäjä asettaa hänelle uuden väliaikaisen salasanan. Ohjelmassa ei ole erillistä hätäpalautuskoodia eikä hätäpalautussivua.
4. Tarkista Asetukset → Turva → Backup & Restore: ota ensimmäinen SQLite-varmuuskopio heti.

Jos sivu antaa virheen, katso kohta [7. Jos jokin ei toimi](#7-jos-jokin-ei-toimi).

### 3.7 Testi- ja tuotantoasennus rinnakkain

Jos haluat sekä testiympäristön että tuotannon samalle palvelimelle, asenna ohjelma kahteen eri kansioon (esim. `/testii` ja `/autonhuolto`). Kummallakin on oma tietokanta, omat käyttäjät ja omat kuvat. Käytä kahdelle asennukselle eri tietokantapolkuja, jos käytät `AUTOHUOLTO_DB_PATH`-muuttujaa. Ota aina varmuuskopio ennen kuin kokeilet uutta versiota oikealla tiedolla.

---

## 4. Käyttöohje

### 4.1 Kirjautuminen ja roolit

Jokaisella käyttäjällä on yksi rooli:

| Rooli | Saa tehdä |
|---|---|
| **Ylläpitäjä (admin)** | Kaiken: asetukset, käyttäjät, poistot, varmuuskopion palautus, huoltokohteiden ja mekaanikkojen hallinta |
| **Tallentaja** | Lisätä ja muokata autoja, huoltoja, varaosia, varastoa, asiakkaita ja laskuja. Ei poistoja eikä asetuksia |
| **Katselija** | Vain katsella ja tulostaa. Lomakkeet, joita hän ei saa käyttää, eivät näy |

Ylläpitäjä luo käyttäjät kohdassa Asetukset → Käyttäjät. Uusi käyttäjä saa väliaikaisen salasanan ja vaihtaa sen ensimmäisellä kirjautumisella. Oma salasana vaihdetaan sivulla **Oma tili**. Kirjautuminen lukittuu hetkeksi, jos salasana on väärin monta kertaa peräkkäin.

### 4.2 Valikko

Yläpalkin valikosta pääsee näihin sivuihin: **Autot** (etusivu), **Kaikki huollot**, **Asiakkaat**, **Laskut**, **Varasto**, **Asetukset** ja **Oma tili**.

### 4.3 Auton lisääminen ja kilometrit

1. Etusivulla **＋ Lisää auto**: täytä rekisterinumero, merkki, malli ja vuosimalli sekä nykyinen mittarilukema.
2. Auton sivulla **Päivitä km** kirjaa uusi mittarilukema aina kun tiedät sen. Jos löydät vanhan lukeman (esim. valokuvasta tai paperista), käytä **＋ Lisää vanha mittarilukema**: anna päivä ja lukema, jolloin se tallentuu historiaan omalle päivälleen eikä muuta nykyistä lukemaa. Lukeman pitää sopia ympäröiviin lukemiin ja huoltoihin. Ohjelma pitää kilometrihistoriaa ja laskee sen perusteella ajomäärän vuodessa, jota se käyttää huoltoennusteissa.
3. **Auton tiedot** -osiossa voi muokata auton tietoja, öljymääriä ja varoitusrajaa ("Ennakkovaroitus": kuinka monta kilometriä ennen eräpäivää huomautus tulee näkyviin).

### 4.4 Huollon kirjaaminen

1. Auton sivulla **🔧 Kirjaa huolto**. Valitse tyyppi (Määräaikaishuolto, Korjaus, Katsastus, Rengastyö tai Muu), päivämäärä, mittarilukema ja mekaanikko.
2. Valitse tehdyt huoltokohteet (esim. moottoriöljy, öljynsuodatin) ja toimenpide (vaihdettu, tarkastettu, puhdistettu ...). Lisää tarvittaessa varaosat, työ, hinnat ja kuvat kuvateksteineen.
3. **Tallenna huolto.** Tallennus päivittää samalla huoltotilanteen ja ennusteet sekä auton mittarilukeman, jos se on suurempi kuin nykyinen.
4. Jälkikäteen huoltoa voi muokata huoltohistoriasta. Jos kaksi käyttäjää muokkaa samaa tietoa yhtä aikaa, ohjelma varoittaa ristiriidasta eikä ylikirjoita hiljaa toisen muutosta.
5. Muu työ (esim. tuntiveloitus): **＋ Lisää muu työ**. Työajastimella (▶ Aloita, ⏸ Tauko, ■ Lopeta) voi mitata tehdyn työn ajan.

### 4.5 Huoltotilanne ja ennusteet

Auton sivun **🔔 Huoltotilanne ja ennuste** näyttää, mitkä huoltokohteet ovat myöhässä, lähestyvät eräpäivää tai ovat kunnossa. Eräpäivä lasketaan kilometreistä ja ajasta, sen mukaan kumpi tulee ensin. Ennuste käyttää auton ajomäärää vuodessa.

Huoltovälit määritellään kohdassa **⚙ Huolto-ohjelma ja vaihtovälit**: jokaiselle kohteelle voi asettaa välin kilometreinä ja kuukausina sekä ensimmäisen eräpäivän, jos huoltohistoriaa ei vielä ole. Yleiset huoltokohteet ja toimet hallitaan Asetukset → Huoltotoimet (ylläpitäjä).

### 4.6 Varaosat ja varasto

- **Varaosamuistio** (auton sivulla) on auton osalista: osan nimi, osanumero ja hankintahinta. Sama osa voi sopia useaan autoon.
- **Varasto**-sivulla näkyy kaikki varaosat saldoineen. Saldoa muutetaan **+/−**-napeilla, ja jokaisesta muutoksesta jää merkintä **Varastotapahtumat**-historiaan. Kun huoltoon merkitään käytetty varaosa, varaston saldo vähenee automaattisesti.
- **Hankintahinta ja ALV**: Asetukset → Käyttö ja ulkoasu -kohdassa valitaan, syötetäänkö hinnat verottomina vai verollisina. Hintakentän otsikko kertoo aina, kumpaa tilaa käytetään. Kun tilaa vaihdetaan, varaston hankintahinnat **muunnetaan** uuteen tilaan ALV-prosentin mukaan.
- Varaston tulosteet: **🖨 Inventointilista** (laskentalista käsin täytettäväksi) ja **🖨 Varaston tiedot** (hinnat ja saldoarvo). **📊 Excel** vie varaston taulukkoon.

### 4.7 Asiakkaat ja omistushistoria

Asiakkaat-sivulla voi lisätä, muokata ja hakea asiakkaita. Asiakkaalle voi liittää auton, ja auton sivulla **👤 Asiakas ja omistushistoria** -kohdassa näkyy nykyinen omistaja ja aiemmat omistajat päivämäärineen. Huoltoon tallentuu kopio asiakkaan nimestä ja osoitteesta huoltohetkellä, joten vanhat huollot säilyttävät oikean omistajan, vaikka asiakkaan tiedot muuttuisivat myöhemmin.

### 4.8 Laskut

Huollosta voi muodostaa laskun (**🧾 Muodosta lasku**). Laskut numeroidaan automaattisesti, ja Laskut-sivulla on yhteenveto kuukausittain ja vuosittain sekä lista muodostetuista laskuista. Laskun tilaa voi muuttaa (esim. maksettu). Laskulle tulostuu korjaamon tiedot ja maksutiedot; ne täytetään Asetukset → Korjaamon / laskuttajan ja maksutapojen tiedot. Lasku, josta on olemassa maksutieto, voidaan poistaa vain ylläpitäjän toimesta.

### 4.9 Tulosteet ja Excel

Auton sivulta ja Varasto-sivulta löytyvät 🖨-napit (huoltotilanne, koko huoltohistoria, historia kuvilla, yksittäinen huoltosivu, laskut ja varaston listat). Tulosteet avautuvat selaimen tulostusnäkymään, josta ne voi tallentaa PDF:ksi. **📊 Excel** vie auton tai varaston tiedot `.xlsx`-tiedostoon.

### 4.10 Asetukset

| Välilehti | Sisältö |
|---|---|
| Yleiset | Korjaamon nimi ja logo, teema (tumma / hämärä / vaalea), hintojen syöttötapa ja ALV-prosentti, korjaamon ja laskuttajan tiedot |
| Mekaanikot | Mekaanikkojen lisäys ja käytöstä poisto. Poistettu mekaanikko säilyy vanhoissa huolloissa |
| Huoltotoimet | Huoltokohteet ja toimenpiteet, joita huoltolomakkeella tarjotaan |
| Käyttäjät | Käyttäjät, roolit ja salasanojen nollaus |
| Tietojen tarkistus | Ohjelma etsii epäjohdonmukaisuuksia (esim. mittarilukemat menevät taaksepäin). Hyväksyttyjä poikkeamia voi merkitä tarkoituksellisiksi |
| Turva | Backup & Restore, kirjautumisen suojaus, kirjautumisen suojaus ja käyttäjähallinnan tapahtumaloki |

---

## 5. Varmuuskopiointi ja palautus

**Ota varmuuskopio aina ennen** ohjelman päivitystä, tietojen massamuutosta, tietokantaan kohdistuvaa skriptiä tai uuden version kokeilua oikealla tiedolla.

### 5.1 Kaksi varmuuskopiotyyppiä

| Tyyppi | Sisältää | Käyttö |
|---|---|---|
| **⬇ SQLite** | Vain tietokanta: käyttäjät, salasanatiivisteet, roolit, huollot, laskut, varasto, asetukset | Pieni ja nopea. Ota säännöllisesti, ainakin ennen jokaista päivitystä |
| **⬇ Täysi ZIP + kuvat** | Tietokanta, ohjelmatiedostot ja kuvat | Siirto toiselle palvelimelle ja viikoittainen kokovarmuuskopio |

Molemmat otetaan kohdasta **Asetukset → Turva → Backup & Restore**. Tiedoston nimessä on ohjelman versio ja päivämäärä, esimerkiksi `autohuolto-db-backup-v1.0.0-2026-10-09.sqlite3`.

Täyteen ZIP:iin tulevat vain ohjelman omat tiedostot: `index.php`, `app/`, `assets/`, `views/`, `kuvat/` sekä ohjetiedostot. Palvelimen muut tiedostot, kuten oma `.htaccess` tai toisen sivuston tiedostot samassa kansiossa, **eivät** tule mukaan.

### 5.2 Palautus käyttöliittymästä

Asetukset → Turva → **⚠ Palauta varmuuskopio**: valitse tiedosto, kirjoita `PALAUTA` ja vahvista.

- **SQLite-tiedosto** korvaa koko tietokannan. Käyttäjät, salasanat, roolit ja oikeudet tulevat varmuuskopiosta, joten vanhan varmuuskopion salasanat ovat voimassa palautuksen jälkeen. Ohjelmatiedostoihin ja kuviin ei kosketa.
- **Täysi ZIP** palauttaa tietokannan lisäksi ohjelman omat tiedostot ja kuvat. Palautus ei ole täysin peilikuva varmuuskopiosta: se kirjoittaa vain ohjelman omat tiedostot (`index.php`, `CHANGELOG.md`, `SETUP.md`, `BACKUP.md`, `app/`, `assets/`, `views/`, `kuvat/`) eikä poista tai ylikirjoita muita kansion tiedostoja. Kansioon `kuvat/` palautetaan vain kuvia (`.jpg`, `.jpeg`, `.png`, `.webp`) ja suojaava `index.html`.
- Ennen palautusta ohjelma ottaa turvakopion nykyisestä tilanteesta (`.pre-restore-...`) ja päättää kaikki kirjautumisistunnot. Kirjaudu uudelleen palautuksen jälkeen. Yli 30 päivää vanhat turvakopiot siivotaan automaattisesti.
- Jos ZIP on vanhempaa ohjelmaversiota kuin käytössä oleva, ohjelma keskeyttää palautuksen, ellet erikseen salli palautusta vanhempaan versioon. Vanhempi ohjelma palaisi muuten käyttöön.
- Jos palautus epäonnistuu, vanha tila palautetaan ja virheilmoitus kertoo syyn.

### 5.3 Käsin palauttaminen (esim. uudelle palvelimelle)

1. Asenna ohjelma tämän ohjeen kohdan 3 mukaan **ilman tietokantaa**.
2. Pura täysi ZIP asennuskansioon (tai kopioi vain `autohuolto.sqlite3` ja `kuvat/`).
3. `sudo chown -R www-data:www-data kansio` ja `sudo chmod 600 kansio/autohuolto.sqlite3`.
4. Avaa osoite selaimessa ja kirjaudu varmuuskopion käyttäjätunnuksella.

### 5.4 Säännöllinen varmuuskopiointi

Ohjelma ei ota varmuuskopioita itsestään. Suositus pienelle käytölle: kerran viikossa täysi ZIP ja ennen jokaista päivitystä SQLite. Tallenna kopiot **palvelimen ulkopuolelle** (oma kone, pilvitallennus), sillä saman levyn kopio ei auta, jos levy menee rikki.

Automaattinen kopio palvelimella (jos `sqlite3`-työkalu on asennettu: `sudo apt install sqlite3`). Aja komento ajastettuna (`sudo crontab -e`), esimerkiksi joka yö kello 3:

```cron
0 3 * * * sqlite3 /var/lib/autonhuolto/autohuolto.sqlite3 ".backup '/var/backups/autonhuolto-$(date +\%F).sqlite3'"
```

Käytä `.backup`-komentoa, älä tavallista tiedoston kopiointia, koska tietokanta voi olla käytössä kopioinnin aikana. Kopioi tiedosto sen jälkeen palvelimelta pois (esim. `rsync` tai `scp`). Tarkista väliajoin, että varmuuskopio todella avautuu ja palautuu testiasennukseen.

### 5.5 Backup-ongelmat

- **Täyttä ZIP:iä ei saa**: tarkista, että PHP:n `zip`-laajennus on asennettu (`php -m | grep zip`) ja että ohjelman kansiossa ei ole tiedostoja, joita web-palvelimen käyttäjä ei voi lukea (esim. pääkäyttäjän tekemä 0600-kopio). Virheilmoitus kertoo ongelmatiedoston nimen. Siirrä tai poista se, tai korjaa omistaja (`chown www-data`).
- Lisätietoja ja tiedosto-oikeuksien korjaus: `BACKUP.md`.

---

## 6. Ohjelman päivitys

1. **Ota SQLite-varmuuskopio** (ja mielellään täysi ZIP) ja lataa se omalle koneelle.
2. Pura uuden version ZIP omalla koneellasi.
3. Kopioi uudet `index.php`, `app/`, `assets/`, `views/`, `CHANGELOG.md`, `SETUP.md`, `BACKUP.md`, `README.md`, `LICENSE`, `SECURITY.md` ja `docs/` palvelimella vanhojen päälle. **Älä koske** tietokantaan, `kuvat/`-kansioon tai omaan `.htaccess`-tiedostoosi.
4. Varmista oikeudet: `sudo chown -R www-data:www-data kansio` ja `sudo find kansio -type f -exec chmod 644 {} \;` (tietokanta pysyy 600).
5. Avaa ohjelma ja tarkista versionumero sivun alatunnisteesta. Lue muutokset tiedostosta `CHANGELOG.md`.

Tietokannan skeemaversion on oltava 12. Ohjelma ei päivitä tietokantaa itse: eri skeemaversion kanta hylätään selkeällä virheilmoituksella.

---

## 7. Jos jokin ei toimi

| Oire | Syy ja korjaus |
|---|---|
| "Autonhuolto ei voi käynnistyä, koska palvelimelta puuttuu ..." | PHP-versio on liian vanha tai laajennus puuttuu. Asenna `php-sqlite3` ja `php-mbstring` ja käynnistä web-palvelin tai PHP-FPM uudelleen |
| Valkoinen sivu tai 500-virhe | Katso web-palvelimen virheloki (`/var/log/apache2/error.log` tai `/var/log/nginx/error.log`) |
| Ei voi kirjautua tai tallentaa, vaikka salasana on oikein | Ohjelman kansio ei ole web-palvelimen käyttäjän kirjoitettavissa (`chown www-data`) |
| Kuvia ei tallennu | `kuvat/`-kansio ei ole kirjoitettavissa tai PHP:n `gd` puuttuu (kuvia ei silloin pienennetä) |
| Tyylit puuttuvat tai sivu näyttää rikkinäiseltä | `assets/`-tiedostot eivät ole luettavissa: `chmod 755 assets app` ja `chmod 644 assets/*` |
| Istunto katkeaa jatkuvasti HTTPS-käänteisproxyn takana | Aseta `AUTOHUOLTO_FORCE_HTTPS=1` |
| Unohtui salasana | Toinen ylläpitäjä asettaa uuden väliaikaisen salasanan (Asetukset → Käyttäjät → Aseta uusi väliaikainen salasana). Jos ylläpitäjiä on vain yksi, katso kohta 7.1 |
| Tietokantatiedosto latautuu selaimella | Suoja puuttuu: tee kohta 3.4 heti ja vaihda kaikki salasanat |

### 7.1 Unohtunut salasana, kun ylläpitäjiä on vain yksi

Ohjelmassa ei ole hätäpalautuskoodia, joten kun ainoa ylläpitäjä unohtaa salasanansa, sen voi nollata vain palvelimelta. Varmuuskopion palauttaminen ei auta, koska varmuuskopiossa ovat samat salasanatiivisteet kuin nykyisessä tietokannassa (poikkeus: vanhassa varmuuskopiossa voi olla salasana, jonka vielä muistat). Siksi **luo aina vähintään kaksi ylläpitäjää**.

Jos olet jo lukossa, nollaa salasana palvelimelta. Ota ensin kopio tietokannasta, ja aja komento sen kansion juuressa, jossa tietokanta on. Korvaa `/POLKU/autohuolto.sqlite3` tietokannan polulla ja `KÄYTTÄJÄTUNNUS` unohtuneen tunnuksen nimellä:

```bash
php -r '$db=new PDO("sqlite:".$argv[1]);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pw=bin2hex(random_bytes(6))."Aa1";$st=$db->prepare("UPDATE auth_users SET password_hash=?,force_password_change=1,session_version=session_version+1,updated_at=? WHERE username=?");$st->execute([password_hash($pw,PASSWORD_BCRYPT,["cost"=>12]),date("c"),strtolower(trim($argv[2]))]);echo $st->rowCount()?"Väliaikainen salasana: $pw\n":"Käyttäjää ei löytynyt.\n";' /POLKU/autohuolto.sqlite3 KÄYTTÄJÄTUNNUS
```

Komento tulostaa uuden väliaikaisen salasanan. Kirjaudu sillä, ohjelma pyytää heti vaihtamaan sen omaksi salasanaksi, ja kaikki vanhat istunnot päättyvät. Jos komento ajetaan eri käyttäjänä kuin web-palvelin (esim. `sudo`), tarkista tietokannan omistaja jälkeenpäin: `sudo chown www-data:www-data autohuolto.sqlite3`.

# Autonhuolto – varmuuskopiointi ja palautus

Yleisohje asennuksesta, käytöstä ja varmuuskopioinnista on tiedostossa `SETUP.md`. Tämä tiedosto on varmuuskopioinnin tekninen tiivistelmä.

## Kaksi varmuuskopiotyyppiä
- **SQLite-backup**: pelkkä tietokanta käyttäjineen ja oikeuksineen. Pieni ja nopea; ota säännöllisesti.
- **Täysi ZIP**: koko sovelluskansio (ohjelmatiedostot, alihakemistot, kuvat) sekä tietokanta. Sopii siirtoon uudelle palvelimelle.

Molemmat löytyvät kohdasta Asetukset → Backup & Restore.

## Palautus uuteen kansioon (käsin)
1. Pura ZIP kansioon.
2. Varmista, että `index.php`, `app/`, `assets/`, `views/`, `kuvat/` ja `autohuolto.sqlite3` ovat samassa juurikansiossa.
3. Aseta tietokannalle `chmod 600 autohuolto.sqlite3`.
4. Avaa kansio selaimella.

Erillistä käyttöönottokoodia ei tarvita. Backupin käyttäjät ja oikeudet tulevat mukana.

## Tietokannan nimi
Tietokanta on aina `autohuolto.sqlite3` sovelluskansiossa (tai ympäristömuuttujan `AUTOHUOLTO_DB_PATH` osoittama tiedosto). Aseta sille `chmod 600`.

## Vanhan asennuksen päivitys
Ota ensin kopio tietokannasta. Kopioi sitten uudet `index.php`, `app/`, `assets/`, `views/`, `lang/`, `CHANGELOG.md`, `SETUP.md`, `BACKUP.md`, `README.md`, `LICENSE` ja `SECURITY.md` vanhojen päälle; tietokantaa ja `kuvat/`-kansiota ei kosketa. Tietokanta voi olla skeemaversiota 12 tai 13: skeema 12 päivitetään automaattisesti ensimmäisellä avauksella (ohjelma ottaa ensin turvakopion `.pre-migration-12-to-13-*`). Päivityksen jälkeen kantaa ei voi käyttää vanhalla ohjelmaversiolla.

## Palautus käyttöliittymästä
Backup & Restore → Palauta varmuuskopio.

Huomaa: palautus ei ole täysi peilikuva. ZIP:stä kirjoitetaan vain ohjelman omat tiedostot (ks. alla), ja muita kansion tiedostoja ei poisteta eikä ylikirjoiteta. SQLite-palautus korvaa koko tietokannan, joten käyttäjät, salasanatiivisteet ja oikeudet tulevat varmuuskopiosta.

- **SQLite-tiedosto** palauttaa tietokannan käyttäjineen. Sovellustiedostoihin ja kuviin ei kosketa.
- **Täysi ZIP** palauttaa tietokannan ja ohjelman omat tiedostot (`index.php`, `app/`, `assets/`, `views/`, `kuvat/`, `docs/`, `CHANGELOG.md`, `SETUP.md`, `BACKUP.md`, `README.md`, `LICENSE`, `SECURITY.md`, `.gitignore`). Muita kansion tiedostoja (esim. oma `.htaccess`) ei kirjoiteta eikä poisteta. Kansioon `kuvat/` palautetaan vain kuvia.
- Skeemaversion 12 varmuuskopio (versiot 1.0.x ja 1.1.0-alpha.1) kelpaa palautukseen: kanta päivitetään skeemaan 13 heti palautuksen jälkeen, eikä palautus tarvitse `docs/`-kansiota. Skeemaa 13 uudempaa varmuuskopiota ei palauteta.
- Täyden ZIP-palautuksen jälkeen ohjelma tyhjentää PHP:n koodivälimuistin, jotta seuraava pyyntö käyttää palautettuja tiedostoja.
- Palautus tekee ensin turvakopion nykyisestä tilanteesta (`.pre-restore-*`) ja päättää kaikki kirjautumisistunnot. Yli 30 päivää vanhat turvakopiot siivotaan automaattisesti seuraavan onnistuneen palautuksen yhteydessä.
- Jos palautus epäonnistuu, vanha tila palautetaan ja virheilmoitus kertoo syyn. Palautus myös tarkistaa ennen muutoksia, että ZIPissä ovat kaikki tiedostot, joita sen `index.php` tarvitsee.

## Tiedostojen oikeudet
Palautuksessa jo olemassa olevien tiedostojen oikeudet säilyvät. Uudet ohjelmatiedostot saavat 0644 ja kansiot 0755. Jos jokin `assets/`-tiedosto antaa palvelimella 403-virheen aiemman palautuksen jäljiltä, aseta `chmod 644 assets/*` ja `chmod 755 assets app`.

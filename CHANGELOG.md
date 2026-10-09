# Autonhuolto – muutoshistoria

Tämä tiedosto kertoo, mitä on muuttunut versiosta toiseen. Tiedostorakenne, asennus-, käyttö- ja varmuuskopio-ohje ovat tiedostossa `SETUP.md`; varmuuskopioinnin tekninen tiivistelmä on tiedostossa `BACKUP.md`.

Versiointi: `pääversio.toinen.korjaus` (esim. 1.0.1 = korjaus, 1.1.0 = uusi ominaisuus). Tietokannan skeemaversio on 12.

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

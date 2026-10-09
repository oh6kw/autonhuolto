# Tietoturva

## Haavoittuvuuden ilmoittaminen

Jos löydät tietoturvaongelman, **älä kirjoita siitä julkiseen issueen**. Käytä GitHubin yksityistä ilmoitusta: repon sivulla **Security → Report a vulnerability** (Private vulnerability reporting). Kerro, mikä versio on käytössä (näkyy sivun alatunnisteessa), miten ongelman voi toistaa ja mitä vaikutusta sillä on.

Tämä on harrastuspohjainen ohjelma, joten vastausaika ei ole taattu, mutta tietoturvailmoitukset käsitellään ensimmäisenä.

## Tuetut versiot

Tuettu on vain uusin julkaistu versio. Päivitä aina uusimpaan, ennen kuin ilmoitat ongelmasta.

## Mitä ohjelma tekee suojauksen eteen

- Salasanoista tallennetaan vain tiiviste; kirjautumisyrityksiä rajoitetaan.
- Lomakkeet suojataan CSRF-tunnisteella, ja roolit rajoittavat toimintoja palvelinpuolella.
- Istuntoevästeet ovat `HttpOnly` ja `SameSite=Lax`, sekä `Secure` HTTPS:llä.
- Tietokanta luodaan oikeuksilla 0600.

## Mitä sinun pitää tehdä itse

PHP ei voi estää web-palvelinta jakamasta tiedostoja suoraan. Estä tietokantatiedoston (`*.sqlite3`), lukitustiedostojen ja sisäisten kansioiden (`app/`, `views/`, `kuvat/`) suora lataus ja ota HTTPS käyttöön. Ohje on tiedostossa [SETUP.md](SETUP.md), kohta 3.4–3.5.

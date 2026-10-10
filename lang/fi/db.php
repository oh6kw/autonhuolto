<?php
declare(strict_types=1);
/* Suomi – tietokannan käyttäjälle näkyvät virheilmoitukset (app/db.php). Katso app/i18n.php. */
return [
    /* --- käyttölukko --- */
    'db.err_lock_open'=>'Huoltokirjan käyttölukkoa ei saatu avattua. Tarkista hakemiston kirjoitusoikeus.',
    'db.err_lock_busy'=>'Huoltokirja tekee parhaillaan toista tallennusta tai varmuuskopiota. Yritä hetken kuluttua uudelleen.',

    /* --- tietokantakopio --- */
    'db.err_copy_target_exists'=>'Turvakopion kohdetiedosto on jo olemassa.',
    'db.err_copy_integrity'=>'Tietokantakopion eheystarkistus epäonnistui.',
    'db.err_copy_failed'=>'Eheää tietokantakopiota ei saatu luotua: {message}',

    /* --- skeemaversion tarkistus --- */
    'db.err_settings_table_missing'=>'Tietokannasta puuttuu huoltokirjan asetustaulu.',
    'db.err_schema_invalid'=>'Tietokannan skeemaversio puuttuu tai on virheellinen.',
    'db.err_migration_backup'=>'Tietokannan päivitystä ei aloitettu, koska turvakopiota ei saatu otettua: {message}',
    'db.err_migration_failed'=>'Tietokannan päivitys uuteen muotoon epäonnistui, eikä kantaan tehty muutoksia: {message}. Turvakopio on tallessa tiedostossa {copy}.',
    'db.err_migration_fk'=>'Tietokannan päivityksen tarkistus löysi {n} rikkinäistä viittausta.',
    'db.err_schema_too_old'=>'Tietokannan skeemaversio {version} on liian vanha. Tämä ohjelma tukee skeemaversiota {supported}. Kanta on luotu ohjelman varhaisella kehitysversiolla, jota tämä versio ei enää päivitä (päivitys onnistuu vain skeemaversiosta 12).',
    'db.err_schema_too_new'=>'Tietokanta on tehty uudemmalla ohjelmaversiolla. Päivitä ohjelma ennen kannan käyttöä tai palautusta.',
];

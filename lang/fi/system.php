<?php
declare(strict_types=1);
/* Suomi – järjestelmäviestit (index.php): ympäristötarkistus, tietokannan avausvirhe, kuva- ja logovirheet. Katso app/i18n.php. */
return [
    /* --- ympäristötarkistus --- */
    'sys.env_missing'=>'Autonhuolto ei voi käynnistyä, koska palvelimelta puuttuu:
- {list}

Suositeltavia lisäksi: zip (ZIP-backup, Excel-viennit) ja gd (kuvien ja logon pienennys).',
    'sys.env_php_version'=>'PHP 8.2 tai uudempi (nyt {version})',

    /* --- tietokannan avaus --- */
    'sys.err_db_open'=>'Huoltokirjan tietokantaa ei voitu avata: {message}',

    /* --- kuvat ja logo --- */
    'sys.err_image_not_found'=>'Kuvaa ei löytynyt.',
    'sys.err_image_file_not_found'=>'Kuvatiedostoa ei löytynyt.',
    'sys.err_logo_not_found'=>'Logoa ei löytynyt.',
    'sys.err_logo_invalid'=>'Virheellinen logotiedosto.',
];

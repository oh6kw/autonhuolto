<?php
declare(strict_types=1);
/* Svenska – systemmeddelanden (index.php): miljökontroll, fel vid öppning av databasen, bild- och logofel. Se app/i18n.php. */
return [
    /* --- miljökontroll --- */
    'sys.env_missing'=>'Autonhuolto kan inte starta eftersom servern saknar:
- {list}

Rekommenderas dessutom: zip (ZIP-säkerhetskopia, Excel-export) och gd (förminskning av bilder och logo).',
    'sys.env_php_version'=>'PHP 8.2 eller nyare (nu {version})',

    /* --- öppning av databasen --- */
    'sys.err_db_open'=>'Servicebokens databas kunde inte öppnas: {message}',

    /* --- bilder och logo --- */
    'sys.err_image_not_found'=>'Bilden hittades inte.',
    'sys.err_image_file_not_found'=>'Bildfilen hittades inte.',
    'sys.err_logo_not_found'=>'Logon hittades inte.',
    'sys.err_logo_invalid'=>'Ogiltig logofil.',
];

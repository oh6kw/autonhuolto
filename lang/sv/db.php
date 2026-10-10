<?php
declare(strict_types=1);
/* Svenska – databasens felmeddelanden till användaren (app/db.php). Se app/i18n.php. */
return [
    /* --- användningslås --- */
    'db.err_lock_open'=>'Servicebokens användningslås kunde inte öppnas. Kontrollera skrivrättigheterna för katalogen.',
    'db.err_lock_busy'=>'Servicebokens databas används just nu av en annan sparning eller säkerhetskopiering. Försök igen om en stund.',

    /* --- databaskopia --- */
    'db.err_copy_target_exists'=>'Målfilen för säkerhetskopian finns redan.',
    'db.err_copy_integrity'=>'Integritetskontrollen av databaskopian misslyckades.',
    'db.err_copy_failed'=>'Det gick inte att skapa en hel databaskopia: {message}',

    /* --- kontroll av schemaversion --- */
    'db.err_settings_table_missing'=>'Databasen saknar servicebokens inställningstabell.',
    'db.err_schema_invalid'=>'Databasens schemaversion saknas eller är ogiltig.',
    'db.err_migration_backup'=>'Uppdateringen av databasen påbörjades inte eftersom säkerhetskopian inte kunde tas: {message}',
    'db.err_migration_failed'=>'Uppdateringen av databasen till det nya formatet misslyckades och inga ändringar gjordes i databasen: {message}. Säkerhetskopian finns kvar i filen {copy}.',
    'db.err_migration_fk'=>'Kontrollen efter databasuppdateringen hittade {n} trasiga referenser.',
    'db.err_schema_too_old'=>'Databasens schemaversion {version} är för gammal. Det här programmet stöder schemaversion {supported}. Databasen har skapats med en tidig utvecklingsversion som den här versionen inte längre uppdaterar (uppdatering lyckas bara från schemaversion 12).',
    'db.err_schema_too_new'=>'Databasen har skapats med en nyare programversion. Uppdatera programmet innan du använder eller återställer databasen.',
];

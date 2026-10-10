<?php
declare(strict_types=1);
/* Suomi – kirjautuminen, käyttäjähallinta ja tapahtumaloki (app/auth.php). Katso app/i18n.php. */
return [
    /* --- roolien nimet (roleLabel) --- */
    'auth.role_viewer'=>'Katselija',
    'auth.role_editor'=>'Tallentaja',
    'auth.role_admin'=>'Ylläpitäjä',
    'auth.role_none'=>'Ei oikeutta',

    /* --- tapahtumalokin nimet (authEventLabel) --- */
    'auth.event_clear_login'=>'Lokista poistettu {n} tapahtumaa · kirjautumiset',
    'auth.event_clear_all'=>'Lokista poistettu {n} tapahtumaa · koko loki',
    'auth.event_delete_user'=>'Käyttäjän poisto · {username}',
    'auth.event_initial_setup'=>'Ylläpitäjän käyttöönotto',
    'auth.event_login'=>'Kirjautuminen',
    'auth.event_change_password'=>'Oman salasanan vaihto',
    'auth.event_add_user'=>'Käyttäjän luonti',
    'auth.event_update_user'=>'Käyttäjätietojen / oikeuksien muutos',
    'auth.event_reset_user_password'=>'Väliaikaisen salasanan asetus',
    'auth.event_regenerate_recovery_key'=>'Palautuskoodin uusiminen',
    'auth.event_logout_others'=>'Muiden istuntojen päättäminen',
    'auth.event_update_own_mechanic'=>'Oman oletusmekaanikon muutos',
    'auth.event_emergency_password_reset'=>'Salasanan hätäpalautus',

    /* --- käyttöoikeudet ja tarkistukset --- */
    'auth.err_forbidden'=>'Käyttöoikeutesi eivät riitä tähän toimintoon.',
    'auth.err_password_mismatch'=>'Uudet salasanat eivät täsmää.',
    'auth.err_password_length'=>'Salasanan tulee olla vähintään 12 merkkiä ja enintään 72 tavua. Voit käyttää usean sanan lausetta.',
    'auth.err_username_format'=>'Käyttäjätunnus: 3–64 merkkiä, pienet a–z, numerot, piste, alaviiva tai yhdysmerkki.',
    'auth.err_name_required'=>'Anna nimi (enintään 100 merkkiä).',
    'auth.err_invalid_role'=>'Virheellinen käyttäjärooli.',
    'auth.err_mechanic_inactive'=>'Valitse käytössä oleva mekaanikko.',
    'auth.err_too_many_attempts'=>'Liian monta epäonnistunutta yritystä. Odota 15 minuuttia.',

    /* --- kirjautumis- ja käyttöönottosivu --- */
    'auth.version'=>'Versio {version}',
    'auth.err_no_admins'=>'Ylläpitäjätunnukset puuttuvat. Palauta tietokanta palvelimen turvakopiosta.',
    'auth.flash_admin_created'=>'Ylläpitäjätunnus luotu. Luo vielä toinen ylläpitäjä (Asetukset → Käyttäjät), jos salasana unohtuu.',
    'auth.setup_title'=>'Ensimmäinen käyttöönotto',
    'auth.setup_intro'=>'Luo ensimmäinen ylläpitäjätunnus. Erillistä käyttöönottokoodia ei tarvita.',
    'auth.setup_shop_name'=>'Yrityksen / korjaamon nimi',
    'auth.setup_display_name'=>'Oma nimi',
    'auth.setup_password'=>'Uusi salasana (vähintään 12 merkkiä)',
    'auth.setup_password_again'=>'Uusi salasana uudelleen',
    'auth.setup_button'=>'Luo ylläpitäjätunnus',
    'auth.login_title'=>'Kirjautuminen',
    'auth.login_username'=>'Käyttäjätunnus',
    'auth.login_password'=>'Salasana',
    'auth.login_button'=>'Kirjaudu',
    'auth.err_login_failed'=>'Virheellinen käyttäjätunnus tai salasana.',

    /* --- käyttäjähallinnan viestit (authHandlePost) --- */
    'auth.err_current_password_wrong'=>'Nykyinen salasana on väärä.',
    'auth.err_password_same'=>'Valitse aiemmasta poikkeava salasana.',
    'auth.flash_password_changed'=>'Salasana vaihdettiin. Muut istuntosi päättyivät.',
    'auth.flash_sessions_ended'=>'Kaikki muut kirjautumisistuntosi päättyivät. Tämä istunto jatkuu.',
    'auth.flash_mechanic_saved'=>'Oletusmekaanikko tallennettiin.',
    'auth.err_audit_clear_invalid'=>'Virheellinen lokin tyhjennys. Avaa tapahtumaloki uudelleen.',
    'auth.err_audit_clear_unconfirmed'=>'Vahvista lokitietojen poistaminen rastittamalla varmistus.',
    'auth.flash_audit_cleared'=>'Lokista poistettiin {n} tapahtumaa. Tyhjennyksestä jäi merkintä. Käyttäjät, salasanat ja huoltotiedot säilyivät.',
    'auth.flash_user_created'=>'Käyttäjä luotiin. Hän vaihtaa salasanan ensimmäisellä kirjautumisella.',
    'auth.err_user_not_found'=>'Käyttäjää ei löytynyt.',
    'auth.err_user_not_found_deleted'=>'Käyttäjää ei löytynyt. Se on ehkä jo poistettu.',
    'auth.err_last_admin_change'=>'Viimeistä aktiivista ylläpitäjää ei voi poistaa käytöstä tai muuttaa toiseksi rooliksi.',
    'auth.flash_user_updated'=>'Käyttäjän tiedot tallennettiin. Oikeuksien muutos päättää hänen aiemmat istuntonsa.',
    'auth.err_delete_unconfirmed'=>'Käyttäjän poistoa ei vahvistettu. Avaa käyttäjälista uudelleen.',
    'auth.err_last_admin_delete'=>'Viimeistä aktiivista ylläpitäjää ei voi poistaa.',
    'auth.flash_user_deleted'=>'Käyttäjä {username} poistettiin pysyvästi. Huoltotiedot, mekaanikot ja tapahtumaloki säilyivät.',
    'auth.err_own_password_via_account'=>'Vaihda oma salasanasi Omat tiedot -sivulta.',
    'auth.flash_password_reset'=>'Uusi väliaikainen salasana tallennettiin. Käyttäjä vaihtaa sen kirjautuessaan.',
    'auth.err_audit_clear_failed'=>'Lokitietojen tyhjennys epäonnistui. Tietoja ei poistettu.',
    'auth.err_user_save_failed'=>'Käyttäjätietojen tallennus epäonnistui. Tarkista, ettei tunnus ole jo käytössä.',

    /* --- katselijan lukittu lomake (authFilterHtml) --- */
    'auth.readonly_notice'=>'Katseluoikeus · tietoja ei voi muuttaa.',
'auth.setup_language'=>'Kieli / Språk',
'auth.setup_language_help'=>'Järjestelmän oletuskieli. Sen voi vaihtaa myöhemmin asetuksista, ja jokainen käyttäjä voi valita oman kielensä.',
'auth.flash_language_saved'=>'Kieli tallennettiin.',
'auth.event_update_own_language'=>'Oman kielen muutos',
];

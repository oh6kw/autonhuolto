<?php
declare(strict_types=1);
/* Svenska – inloggning, användarhantering och händelselogg (app/auth.php). Se app/i18n.php. */
return [
    /* --- rollernas namn (roleLabel) --- */
    'auth.role_viewer'=>'Läsare',
    'auth.role_editor'=>'Redigerare',
    'auth.role_admin'=>'Administratör',
    'auth.role_none'=>'Ingen behörighet',

    /* --- händelseloggens namn (authEventLabel) --- */
    'auth.event_clear_login'=>'{n} händelser raderade ur loggen · inloggningar',
    'auth.event_clear_all'=>'{n} händelser raderade ur loggen · hela loggen',
    'auth.event_delete_user'=>'Användare raderad · {username}',
    'auth.event_initial_setup'=>'Administratör skapad vid installation',
    'auth.event_login'=>'Inloggning',
    'auth.event_change_password'=>'Byte av eget lösenord',
    'auth.event_add_user'=>'Användare skapad',
    'auth.event_update_user'=>'Ändring av användaruppgifter / behörigheter',
    'auth.event_reset_user_password'=>'Tillfälligt lösenord satt',
    'auth.event_regenerate_recovery_key'=>'Återställningskod förnyad',
    'auth.event_logout_others'=>'Övriga sessioner avslutade',
    'auth.event_update_own_mechanic'=>'Ändring av egen standardmekaniker',
    'auth.event_emergency_password_reset'=>'Nödåterställning av lösenord',

    /* --- behörigheter och kontroller --- */
    'auth.err_forbidden'=>'Dina behörigheter räcker inte till för den här åtgärden.',
    'auth.err_password_mismatch'=>'De nya lösenorden stämmer inte överens.',
    'auth.err_password_length'=>'Lösenordet ska vara minst 12 tecken och högst 72 byte. Du kan använda en mening med flera ord.',
    'auth.err_username_format'=>'Användarnamn: 3–64 tecken, små bokstäver a–z, siffror, punkt, understreck eller bindestreck.',
    'auth.err_name_required'=>'Ange ett namn (högst 100 tecken).',
    'auth.err_invalid_role'=>'Ogiltig användarroll.',
    'auth.err_mechanic_inactive'=>'Välj en mekaniker som är i bruk.',
    'auth.err_too_many_attempts'=>'För många misslyckade försök. Vänta 15 minuter.',

    /* --- inloggnings- och installationssidan --- */
    'auth.version'=>'Version {version}',
    'auth.err_no_admins'=>'Administratörskonton saknas. Återställ databasen från serverns säkerhetskopia.',
    'auth.flash_admin_created'=>'Administratörskontot skapades. Skapa ännu en administratör (Inställningar → Användare) ifall lösenordet glöms bort.',
    'auth.setup_title'=>'Första installationen',
    'auth.setup_intro'=>'Skapa det första administratörskontot. Någon separat installationskod behövs inte.',
    'auth.setup_shop_name'=>'Företagets / verkstadens namn',
    'auth.setup_display_name'=>'Ditt namn',
    'auth.setup_password'=>'Nytt lösenord (minst 12 tecken)',
    'auth.setup_password_again'=>'Nytt lösenord igen',
    'auth.setup_button'=>'Skapa administratörskonto',
    'auth.login_title'=>'Inloggning',
    'auth.login_username'=>'Användarnamn',
    'auth.login_password'=>'Lösenord',
    'auth.login_button'=>'Logga in',
    'auth.err_login_failed'=>'Fel användarnamn eller lösenord.',

    /* --- meddelanden i användarhanteringen (authHandlePost) --- */
    'auth.err_current_password_wrong'=>'Det nuvarande lösenordet är fel.',
    'auth.err_password_same'=>'Välj ett lösenord som skiljer sig från det tidigare.',
    'auth.flash_password_changed'=>'Lösenordet byttes. Dina övriga sessioner avslutades.',
    'auth.flash_sessions_ended'=>'Alla dina övriga inloggade sessioner avslutades. Den här sessionen fortsätter.',
    'auth.flash_mechanic_saved'=>'Standardmekanikern sparades.',
    'auth.err_audit_clear_invalid'=>'Ogiltig tömning av loggen. Öppna händelseloggen på nytt.',
    'auth.err_audit_clear_unconfirmed'=>'Bekräfta raderingen av loggdata genom att kryssa i bekräftelsen.',
    'auth.flash_audit_cleared'=>'{n} händelser raderades ur loggen. En anteckning om tömningen lämnades kvar. Användare, lösenord och servicedata finns kvar.',
    'auth.flash_user_created'=>'Användaren skapades. Hen byter lösenord vid den första inloggningen.',
    'auth.err_user_not_found'=>'Användaren hittades inte.',
    'auth.err_user_not_found_deleted'=>'Användaren hittades inte. Den kan redan vara raderad.',
    'auth.err_last_admin_change'=>'Den sista aktiva administratören kan inte inaktiveras eller få en annan roll.',
    'auth.flash_user_updated'=>'Användaruppgifterna sparades. Ändrade behörigheter avslutar hens tidigare sessioner.',
    'auth.err_delete_unconfirmed'=>'Raderingen av användaren bekräftades inte. Öppna användarlistan på nytt.',
    'auth.err_last_admin_delete'=>'Den sista aktiva administratören kan inte raderas.',
    'auth.flash_user_deleted'=>'Användaren {username} raderades permanent. Servicedata, mekaniker och händelselogg finns kvar.',
    'auth.err_own_password_via_account'=>'Byt ditt eget lösenord på sidan Mitt konto.',
    'auth.flash_password_reset'=>'Ett nytt tillfälligt lösenord sparades. Användaren byter det vid inloggningen.',
    'auth.err_audit_clear_failed'=>'Tömningen av loggdata misslyckades. Inga data raderades.',
    'auth.err_user_save_failed'=>'Användaruppgifterna kunde inte sparas. Kontrollera att användarnamnet inte redan används.',

    /* --- läsarens låsta formulär (authFilterHtml) --- */
    'auth.readonly_notice'=>'Läsbehörighet · uppgifterna kan inte ändras.',
    /* --- språk --- */
    'auth.setup_language'=>'Kieli / Språk',
    'auth.setup_language_help'=>'Systemets standardspråk. Det kan ändras senare i inställningarna, och varje användare kan välja sitt eget språk.',
    'auth.flash_language_saved'=>'Språket sparades.',
    'auth.event_update_own_language'=>'Ändring av eget språk',
];

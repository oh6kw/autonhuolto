<?php
declare(strict_types=1);
/* Suomi – mail. Katso app/i18n.php. */
return [
/* --- 1.2.4 --- */
'mail.invoice_subject'=>"Lasku {number} – {shop}",
'mail.invoice_pay_iban'=>"Tilinumero: {iban}",
'mail.invoice_pay_reference'=>"Viitenumero: {ref}",
'mail.invoice_pay_message'=>"Viesti: Lasku {number}",
'mail.invoice_body'=>"Hei,\n\nliitteenä lasku {number}.\n\nMaksettava summa: {amount}\nEräpäivä: {due}\n{pay}\n\nYstävällisin terveisin\n{shop}",
'mail.test_subject'=>"Testiviesti – {app}",
'mail.test_body'=>"Tämä on testiviesti ohjelmasta {app} (versio {version}).\nLähetystapa: {method}.\n\nJos luet tämän, sähköpostin lähetys toimii.",
'mail.err_not_configured'=>"Sähköpostia ei ole otettu käyttöön. Täytä Asetukset → Sähköposti.",
'mail.err_no_recipient'=>"Vastaanottajan sähköpostiosoite puuttuu.",
'mail.err_too_many_recipients'=>"Vastaanottajia saa olla enintään 5.",
'mail.err_bad_recipient'=>"Virheellinen sähköpostiosoite: {address}",
'mail.err_no_subject'=>"Viestin otsikko puuttuu.",
'mail.err_message_long'=>"Viesti on liian pitkä (enintään 5 000 merkkiä).",
'mail.err_timeout'=>"Sähköpostipalvelin ei vastannut ajoissa.",
'mail.err_connection_lost'=>"Yhteys sähköpostipalvelimeen katkesi.",
'mail.err_smtp_reply'=>"Sähköpostipalvelin hylkäsi komennon {cmd}: {reply}",
'mail.err_connect'=>"Yhteyttä palvelimeen {host}:{port} ei saatu: {error}",
'mail.err_no_starttls'=>"Palvelin ei tue STARTTLS-salausta. Valitse toinen salaustapa tai portti.",
'mail.err_tls_failed'=>"Salatun yhteyden muodostus epäonnistui (varmenne tai TLS-versio). Jos palvelin käyttää omaa varmennetta, voit poistaa varmenteen tarkistuksen asetuksista.",
'mail.err_no_auth'=>"Palvelin ei tarjoa tuettua kirjautumistapaa (PLAIN tai LOGIN).",
'mail.err_php_mail_missing'=>"PHP:n mail()-funktio ei ole käytettävissä tällä palvelimella. Käytä SMTP:tä.",
'mail.err_php_mail_failed'=>"Palvelin ei hyväksynyt viestiä lähetettäväksi (mail() epäonnistui). Todennäköisesti palvelimella ei ole sähköpostilähetystä; käytä SMTP:tä.",
];

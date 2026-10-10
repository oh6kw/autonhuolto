<?php
declare(strict_types=1);
/* Svenska – mail. Katso app/i18n.php. */
return [
/* --- 1.2.4 --- */
'mail.invoice_subject'=>"Faktura {number} – {shop}",
'mail.invoice_pay_iban'=>"Kontonummer: {iban}",
'mail.invoice_pay_reference'=>"Referensnummer: {ref}",
'mail.invoice_pay_message'=>"Meddelande: Faktura {number}",
'mail.invoice_body'=>"Hej,\n\nbifogat finns faktura {number}.\n\nBelopp att betala: {amount}\nFörfallodag: {due}\n{pay}\n\nMed vänliga hälsningar\n{shop}",
'mail.test_subject'=>"Testmeddelande – {app}",
'mail.test_body'=>"Detta är ett testmeddelande från programmet {app} (version {version}).\nSändningssätt: {method}.\n\nOm du läser detta fungerar e-postsändningen.",
'mail.err_not_configured'=>"E-post är inte aktiverad. Fyll i Inställningar → E-post.",
'mail.err_no_recipient'=>"Mottagarens e-postadress saknas.",
'mail.err_too_many_recipients'=>"Högst 5 mottagare tillåts.",
'mail.err_bad_recipient'=>"Ogiltig e-postadress: {address}",
'mail.err_no_subject'=>"Meddelandets ämne saknas.",
'mail.err_message_long'=>"Meddelandet är för långt (högst 5 000 tecken).",
'mail.err_timeout'=>"E-postservern svarade inte i tid.",
'mail.err_connection_lost'=>"Anslutningen till e-postservern bröts.",
'mail.err_smtp_reply'=>"E-postservern avvisade kommandot {cmd}: {reply}",
'mail.err_connect'=>"Kunde inte ansluta till servern {host}:{port}: {error}",
'mail.err_no_starttls'=>"Servern stöder inte STARTTLS-kryptering. Välj annat krypteringssätt eller annan port.",
'mail.err_tls_failed'=>"Det gick inte att upprätta en krypterad anslutning (certifikat eller TLS-version). Om servern använder ett eget certifikat kan du stänga av certifikatkontrollen i inställningarna.",
'mail.err_no_auth'=>"Servern erbjuder ingen inloggningsmetod som stöds (PLAIN eller LOGIN).",
'mail.err_php_mail_missing'=>"PHP:s mail()-funktion finns inte på servern. Använd SMTP.",
'mail.err_php_mail_failed'=>"Servern godtog inte meddelandet för sändning (mail() misslyckades). Troligen saknar servern e-postsändning; använd SMTP.",
];

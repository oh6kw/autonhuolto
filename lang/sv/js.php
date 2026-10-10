<?php
declare(strict_types=1);
/** Svenska – webbläsarens (assets/app.js) texter; nycklar tt('js.…'). Platshållare {namn}. */
return [
    /* --- Lagersidan --- */
    'js.inv_readonly'=>'Läsbehörighet · ingen redigering.',
    'js.inv_low_badge'=>'⚠️ {n} vid varningsgränsen',
    'js.inv_visible_count'=>'{n} rader synliga',
    'js.inv_dirty'=>'● Ändringar osparade',
    'js.inv_hide_details'=>'Dölj mer information ▴',
    'js.inv_show_details'=>'Mer information ▾',
    'js.inv_save_before_print'=>'Spara lagerändringarna före utskriften så att inventeringslistan använder de nya saldona.',
    'js.inv_save_before_excel'=>'Spara lagerändringarna före Excel-exporten så att filen använder de nya saldona.',
    /* --- Registernummer och Biltema --- */
    'js.biltema_enter_plate'=>'Skriv först registernumret, till exempel ABC-123.',
    'js.biltema_see_details'=>'Se uppgifterna hos Biltema och fyll i fälten för hand.',
    /* --- Serviceformuläret --- */
    'js.service_selected_count'=>'{n} objekt valda.',
    'js.stock_balance_now'=>'Saldo nu ',
    'js.stock_linked'=>'Redan kopplat till servicetillfället {linked} {unit} · tillgängligt vid redigering totalt {available} {unit}',
    'js.stock_exceeds'=>'⚠ Mängden överstiger det tillgängliga saldot',
    'js.photo_caption_placeholder'=>'Bildtext, t.ex. Bromsar bak före bytet',
    /* --- Inställningar: prissättning --- */
    'js.hourly_rate_gross'=>'Standardtimpris inkl. moms {vat} %',
    'js.hourly_rate_net'=>'Standardtimpris moms 0 %',
    'js.price_mode_gross'=>'Inkl. moms {vat} % · med moms',
    /* --- Serviceformuläret: egna arbetsrader --- */
    'js.custom_price_default'=>'Pris',
    'js.custom_desc_placeholder'=>'Arbete som inte finns i den färdiga listan',
    'js.custom_notes_placeholder'=>'Tilläggsuppgift',
    'js.remove'=>'Ta bort',
    /* --- Bekräftelser --- */
    'js.confirm_clear_stock_history'=>"Ska alla artiklars lagerhändelser raderas från alla sidor?\n\nSaldona, servicetillfällena och lageranvändningen i servicetillfällen finns kvar. Raderingen kan inte ångras utan säkerhetskopia.",
    'js.save_before_clear_history'=>'Spara lagerändringarna innan händelsehistoriken töms.',
    'js.confirm_clear_audit_all'=>"Ska hela användarhanteringens händelselogg raderas från alla sidor?\n\nEn anteckning om tömningen lämnas kvar. Användare och servicedata finns kvar.",
    'js.confirm_clear_audit_logins'=>"Ska alla inloggningshändelser som hittills antecknats raderas?\n\nÖvriga händelser i användarhanteringen finns kvar.",
    'js.this_car'=>'den här bilen',
    'js.confirm_delete_car_1'=>"Du håller på att radera bilen {label}.\n\nALLA bilens servicetillfällen, reservdelar som hör enbart till den här bilen och servicebilder raderas permanent. Gemensamma reservdelar som passar också andra bilar bevaras.\n\nÄr du säker?",
    'js.confirm_delete_car_2'=>"Är du 100 % säker på att du vill radera bilen {label}?\n\nDen här åtgärden kan inte ångras.",
    'js.this_service'=>'det här servicetillfället',
    'js.confirm_delete_service'=>"Du håller på att radera servicetillfället:\n\n{label}\n\nServicetillfället och dess servicebilder raderas permanent.\n\nÄr du säker?",
    'js.confirm_delete_service_draft'=>"Du håller på att radera servicetillfället:\n\n{label}\n\nServicetillfället och dess servicebilder raderas permanent.\n\nFakturautkastet {draft} som hör till servicetillfället raderas samtidigt.\n\nÄr du säker?",
    'js.this_invoice_draft'=>'det här fakturautkastet',
    'js.confirm_delete_invoice'=>"Ska fakturautkastet {label} raderas permanent?\n\nServicetillfället och dess uppgifter finns kvar. Vid behov kan en ny faktura skapas från servicetillfället senare.",
    'js.confirm_delete_photo'=>'Den här servicebilden raderas permanent. Är du säker?',
    'js.confirm_delete_odometer'=>"Ska den här mätarnoteringen raderas?\n\nServicehistoriken raderas inte. Om avläsningen som raderas är bilens nuvarande km återställs den nuvarande ställningen till det största värdet bland de kvarvarande service- och mätarpunkterna.",
    /* --- Varning för mätarställning --- */
    'js.odo_older'=>'Den tidigare noteringen {date} är {odo} km, alltså minskar den nya avläsningen med {diff} km.',
    'js.odo_newer'=>'Den senare noteringen {date} är {odo} km, alltså är den nya avläsningen {diff} km större än den.',
    'js.odo_warn_title'=>'⚠ Kilometerställningen passar inte in i servicehistoriken enligt datum.',
    'js.odo_warn_note'=>'Noteringen får ändå sparas. Varningen hjälper dig att upptäcka ett möjligt skrivfel eller en avvikelse i källmaterialet.',
    /* --- Sparande av serviceformuläret --- */
    'js.confirm_delete_photos_one'=>"Du har markerat {n} servicebild för radering.\n\nBilderna raderas permanent när du sparar servicetillfället. Fortsätta?",
    'js.confirm_delete_photos_many'=>"Du har markerat {n} servicebilder för radering.\n\nBilderna raderas permanent när du sparar servicetillfället. Fortsätta?",
    'js.stock_qty_required'=>'För en del som används från lagret måste en positiv mängd väljas.',
    'js.stock_insufficient'=>'Lagersaldot räcker inte. Tillgängligt {available} {unit}.',
    'js.confirm_odo_mismatch'=>"VARNING: kilometerställningen passar inte in i servicehistoriken enligt datum.\n\n{issues}\n\nDet kan vara avsiktligt om källuppgiften är sådan, mätaren har bytts eller historiken har ett gammalt skrivfel.\n\nSparas noteringen ändå?",
    /* --- Arbetstidsräknare --- */
    'js.timer_running'=>'Arbete pågår',
    'js.timer_idle'=>'Inte igång',
    'js.timer_paused'=>'På paus',
    'js.timer_stopped'=>'Arbetet avslutat',
    'js.timer_confirm_reset'=>'Ska det här servicetillfällets arbetstidsräknare nollställas?',
    /* --- Sökning i servicehistoriken --- */
    'js.history_showing'=>'Visar {visible} / {total} noteringar',
    /* --- Utkast till serviceformuläret --- */
    'js.draft_found_legacy'=>'Ett serviceutkast från en tidigare version hittades i den här webbläsaren.',
    'js.draft_found'=>'Det finns ett ofullbordat serviceutkast för den här bilen i den här webbläsaren.',
    'js.draft_info'=>'Du kan återställa utkastet, radera det eller börja en ny notering. Utkastet har inte sparats i databasen.',
    'js.draft_restore'=>'Återställ utkastet',
    'js.draft_discard'=>'Radera utkastet',
    /* --- Lagerhändelse och tillägg av reservdel --- */
    'js.stock_in_stock'=>'I lager {n}',
    'js.stock_move_save_first'=>'Spara eller förkasta ändringarna i saldotabellen innan du antecknar en lagerhändelse (sidan laddas om).',
    'js.stock_current_balance'=>'Nuvarande saldo: {stock} {unit}',
    'js.stock_add_part_save_first'=>'Spara eller förkasta ändringarna i saldotabellen innan du lägger till en ny reservdel (sidan laddas om).',
    /* --- Påslag på reservdelar --- */
    'js.markup_enter_price'=>'Skriv först inköpspriset och tryck sedan på knappen.',
    'js.markup_note'=>'inkl. {pct} % påslag',
    'js.markup_note_stock'=>'lagrets inköpspris {buy} + {pct} % påslag',
    'js.unit_default'=>'st',
];

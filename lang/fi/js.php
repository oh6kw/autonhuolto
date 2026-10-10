<?php
declare(strict_types=1);
/** Selaimen (assets/app.js) tekstit; avaimet tt('js.…'). Paikkamerkit {nimi}. */
return [
    /* --- Varastosivu --- */
    'js.inv_readonly'=>'Katseluoikeus · ei muokkausta.',
    'js.inv_low_badge'=>'⚠️ {n} varoitusrajalla',
    'js.inv_visible_count'=>'{n} riviä näkyvissä',
    'js.inv_dirty'=>'● Muutoksia tallentamatta',
    'js.inv_hide_details'=>'Piilota lisätiedot ▴',
    'js.inv_show_details'=>'Lisätiedot ▾',
    'js.inv_save_before_print'=>'Tallenna varaston muutokset ennen tulostusta, jotta inventointilista käyttää uusia saldoja.',
    'js.inv_save_before_excel'=>'Tallenna varaston muutokset ennen Excel-vientiä, jotta tiedosto käyttää uusia saldoja.',
    /* --- Rekisteritunnus ja Biltema --- */
    'js.biltema_enter_plate'=>'Kirjoita ensin rekisteritunnus, esimerkiksi ABC-123.',
    'js.biltema_see_details'=>'Katso tiedot Biltemasta ja täytä kentät käsin.',
    /* --- Huoltolomake --- */
    'js.service_selected_count'=>'Valittu {n} kohdetta.',
    'js.stock_balance_now'=>'Saldo nyt ',
    'js.stock_linked'=>'Huoltoon jo linkitetty {linked} {unit} · muokkauksessa käytettävissä yhteensä {available} {unit}',
    'js.stock_exceeds'=>'⚠ Määrä ylittää käytettävissä olevan saldon',
    'js.photo_caption_placeholder'=>'Kuvateksti, esim. Takajarrut ennen vaihtoa',
    /* --- Asetukset: hinnoittelu --- */
    'js.hourly_rate_gross'=>'Oletustuntihinta sis. ALV {vat} %',
    'js.hourly_rate_net'=>'Oletustuntihinta ALV 0 %',
    'js.price_mode_gross'=>'Sis. ALV {vat} % · verollinen',
    /* --- Huoltolomake: omat työrivit --- */
    'js.custom_price_default'=>'Hinta',
    'js.custom_desc_placeholder'=>'Työ, jota ei ole valmiissa listassa',
    'js.custom_notes_placeholder'=>'Lisätieto',
    'js.remove'=>'Poista',
    /* --- Vahvistukset --- */
    'js.confirm_clear_stock_history'=>"Tyhjennetäänkö kaikkien nimikkeiden varastotapahtumat kaikilta sivuilta?\n\nSaldot, huollot ja huoltojen varastokäyttö säilyvät. Poistoa ei voi perua ilman varmuuskopiota.",
    'js.save_before_clear_history'=>'Tallenna varaston muutokset ennen tapahtumahistorian tyhjennystä.',
    'js.confirm_clear_audit_all'=>"Poistetaanko koko käyttäjähallinnan tapahtumaloki kaikilta sivuilta?\n\nTyhjennyksestä jää yksi merkintä. Käyttäjät ja huoltotiedot säilyvät.",
    'js.confirm_clear_audit_logins'=>"Poistetaanko kaikki tähän mennessä kirjatut kirjautumistapahtumat?\n\nMuut käyttäjähallinnan tapahtumat säilyvät.",
    'js.this_car'=>'tämä auto',
    'js.confirm_delete_car_1'=>"Olet poistamassa autoa {label}.\n\nKAIKKI auton huollot, vain tähän autoon kuuluvat varaosat ja huoltokuvat poistetaan pysyvästi. Muille autoillekin sopivat yhteiset varaosat säilyvät.\n\nOletko varma?",
    'js.confirm_delete_car_2'=>"Oletko 100 % varma, että haluat poistaa auton {label}?\n\nTätä toimintoa ei voi perua.",
    'js.this_service'=>'tämä huolto',
    'js.confirm_delete_service'=>"Olet poistamassa huollon:\n\n{label}\n\nHuolto ja siihen liitetyt huoltokuvat poistetaan pysyvästi.\n\nOletko varma?",
    'js.confirm_delete_service_draft'=>"Olet poistamassa huollon:\n\n{label}\n\nHuolto ja siihen liitetyt huoltokuvat poistetaan pysyvästi.\n\nHuoltoon liittyvä laskuluonnos {draft} poistetaan samalla.\n\nOletko varma?",
    'js.this_invoice_draft'=>'tämä laskuluonnos',
    'js.confirm_delete_invoice'=>"Poistetaanko laskuluonnos {label} pysyvästi?\n\nHuolto ja sen tiedot säilyvät. Tarvittaessa huollosta voi muodostaa myöhemmin uuden laskun.",
    'js.confirm_delete_photo'=>'Tämä huoltokuva poistetaan pysyvästi. Oletko varma?',
    'js.confirm_delete_odometer'=>"Poistetaanko tämä mittarilukemamerkintä?\n\nHuoltohistoriaa ei poisteta. Jos poistettava lukema on auton nykyinen km, nykyinen lukema palautetaan jäljelle jäävien huolto- ja mittaripisteiden suurimpaan arvoon.",
    /* --- Mittarilukeman varoitus --- */
    'js.odo_older'=>'Aiempi merkintä {date} on {odo} km, eli uusi lukema pienenee {diff} km.',
    'js.odo_newer'=>'Myöhempi merkintä {date} on {odo} km, eli uusi lukema on sitä {diff} km suurempi.',
    'js.odo_warn_title'=>'⚠ Kilometrilukema ei sovi päivämäärän mukaiseen huoltohistoriaan.',
    'js.odo_warn_note'=>'Merkinnän saa silti tallentaa. Varoitus auttaa huomaamaan mahdollisen kirjausvirheen tai lähdeaineiston poikkeaman.',
    /* --- Huoltolomakkeen tallennus --- */
    'js.confirm_delete_photos_one'=>"Olet merkinnyt {n} huoltokuvan poistettavaksi.\n\nKuvat poistetaan pysyvästi, kun tallennat huollon. Jatketaanko?",
    'js.confirm_delete_photos_many'=>"Olet merkinnyt {n} huoltokuva poistettavaksi.\n\nKuvat poistetaan pysyvästi, kun tallennat huollon. Jatketaanko?",
    'js.stock_qty_required'=>'Varastosta käytettävälle osalle pitää valita positiivinen määrä.',
    'js.stock_insufficient'=>'Varastosaldo ei riitä. Käytettävissä {available} {unit}.',
    'js.confirm_odo_mismatch'=>"VAROITUS: kilometrilukema ei sovi päivämäärän mukaiseen huoltohistoriaan.\n\n{issues}\n\nTämä voi olla tarkoituksellista, jos lähdetieto on tällainen, mittari on vaihdettu tai historiassa on vanha kirjausvirhe.\n\nTallennetaanko merkintä silti?",
    /* --- Työaikalaskuri --- */
    'js.timer_running'=>'Työ käynnissä',
    'js.timer_idle'=>'Ei käynnissä',
    'js.timer_paused'=>'Tauolla',
    'js.timer_stopped'=>'Työ lopetettu',
    'js.timer_confirm_reset'=>'Nollataanko tämän huollon työaikalaskuri?',
    /* --- Huoltohistorian haku --- */
    'js.history_showing'=>'Näytetään {visible} / {total} merkintää',
    /* --- Huoltolomakkeen luonnos --- */
    'js.draft_found_legacy'=>'Aiemman version huoltoluonnos löytyi tältä selaimelta.',
    'js.draft_found'=>'Tälle autolle on keskeneräinen huoltoluonnos tässä selaimessa.',
    'js.draft_info'=>'Voit palauttaa luonnoksen, poistaa sen tai aloittaa uuden kirjauksen. Luonnosta ei ole tallennettu tietokantaan.',
    'js.draft_restore'=>'Palauta luonnos',
    'js.draft_discard'=>'Poista luonnos',
    /* --- Varastotapahtuma ja varaosan lisäys --- */
    'js.stock_in_stock'=>'Varastossa {n}',
    'js.stock_move_save_first'=>'Tallenna tai hylkää saldotaulukon muutokset ennen varastotapahtuman kirjausta (sivu latautuu uudelleen).',
    'js.stock_current_balance'=>'Nykyinen saldo: {stock} {unit}',
    'js.stock_add_part_save_first'=>'Tallenna tai hylkää saldotaulukon muutokset ennen uuden varaosan lisäämistä (sivu latautuu uudelleen).',
    /* --- Varaosakate --- */
    'js.markup_enter_price'=>'Kirjoita ensin hankintahinta, sitten paina painiketta.',
    'js.markup_note'=>'sis. {pct} % kate',
    'js.markup_note_stock'=>'varaston hankintahinta {buy} + {pct} % kate',
    'js.unit_default'=>'kpl',
];

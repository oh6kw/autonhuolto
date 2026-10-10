<?php
declare(strict_types=1);
/**
 * Tietokannassa olevat valmiit arvot tallennetaan kielineutraaleina koodeina (esim. 'replaced', 'pcs', 'paid').
 * Tässä tiedostossa ovat niiden näkyvät nimet. Valmiiden huoltokohteiden nimet ovat avaimia item.<item_key>.
 * Käyttäjän itse kirjoittamat nimet, ryhmät ja yksiköt (jotka eivät ole koodeja) näytetään sellaisinaan.
 */
return [
/* --- toimenpiteet (service_actions.action, item_catalog.default_action) --- */
'vocab.action.replaced'=>'Vaihdettu / tehty',
'vocab.action.done'=>'Tehty',
'vocab.action.inspected'=>'Tarkastettu',
'vocab.action.repaired'=>'Korjattu',
'vocab.action.cleaned'=>'Puhdistettu',
'vocab.action.added'=>'Lisätty / täytetty',

/* --- huollon tyyppi (services.service_type) --- */
'vocab.stype.scheduled'=>'Määräaikaishuolto',
'vocab.stype.repair'=>'Korjaus',
'vocab.stype.inspection'=>'Katsastus',
'vocab.stype.tyres'=>'Rengastyö',
'vocab.stype.other'=>'Muu',

/* --- yksiköt --- */
'vocab.unit.pcs'=>'kpl',
'vocab.unit.l'=>'L',
'vocab.unit.set'=>'sarja',
'vocab.unit.pack'=>'pkt',

/* --- laskun tila (invoices.status) --- */
'vocab.invstatus.draft'=>'Luonnos',
'vocab.invstatus.sent'=>'Lähetetty',
'vocab.invstatus.paid'=>'Maksettu',
'vocab.invstatus.credited'=>'Hyvitetty',

/* --- huoltokohteiden ryhmät --- */
'vocab.section.engine'=>'Moottori',
'vocab.section.interior'=>'Sisätila',
'vocab.section.other'=>'Muut',
'vocab.section.chassis'=>'Alusta & nesteet',
'vocab.section.driveline'=>'Voimansiirto',
'vocab.section.electrical'=>'Sähkö',
'vocab.section.climate'=>'Ilmastointi',

/* --- valmiit huoltokohteet --- */
'item.engine_oil'=>'Moottoriöljy',
'item.oil_filter'=>'Moottoriöljyn suodatin',
'item.cabin_filter'=>'Raitisilmasuodatin',
'item.air_filter'=>'Ilmansuodatin',
'item.fuel_filter'=>'Polttoainesuodatin',
'item.wipers'=>'Pyyhkijänsulat',
'item.brake_fluid'=>'Jarruneste',
'item.brakes_front'=>'Etujarrut',
'item.brakes_rear'=>'Takajarrut',
'item.spark_plugs'=>'Sytytystulpat',
'item.gearbox_oil'=>'Vaihteistoöljy',
'item.gearbox_filter'=>'Vaihteistoöljyn suodatin',
'item.coolant'=>'Jäähdytysneste',
'item.aux_belt'=>'Apulaitehihna',
'item.aux_belt_inspect'=>'Apulaitehihnan tarkastus',
'item.timing_belt'=>'Jakohihna / jakopää',
'item.timing_belt_inspect'=>'Jakohihnan tarkastus',
'item.battery'=>'Akku',
'item.power_steering'=>'Ohjaustehostimen neste',
'item.diff_oil'=>'Tasauspyörästön öljy',
'item.transfer_oil'=>'Jakolaatikko / Haldex-öljy',
'item.transfer_filter'=>'Haldex / voimansiirron suodatin',
'item.inspection'=>'Katsastus',
'item.spare_tyre_kit'=>'Vararenkaan / renkaanpaikkaussarjan tarkastus',
'item.ac_service'=>'Ilmastointihuolto',

/* --- ohjelman itse tallentamat merkinnät (kantaan koodi @nimi) --- */
'vocab.note.initial_balance'=>'Alkusaldo',
'vocab.note.stock_correction'=>'Saldon korjaus Varasto-näkymästä',
'vocab.note.initial_km'=>'Auton lähtökilometrit',
'vocab.note.accepted_deviation'=>'Hyväksytty lähdeaineiston poikkeamaksi',
'vocab.note.auto_added'=>'Lisätty automaattisesti huollosta',
'vocab.note.work_prefix'=>'Työ: ',
'vocab.mechanic_other'=>'Muu',
'vocab.service_title_default'=>'Huolto',
'vocab.home_title_default'=>'Huoltokirja',
'vocab.home_subtitle_default'=>'Pidä omat, perheen ja tuttujen autot samassa huoltohistoriassa.',
];

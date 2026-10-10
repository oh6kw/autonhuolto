<?php
declare(strict_types=1);
/**
 * Svenska – värden som lagras som språkoberoende koder i databasen (t.ex. 'replaced', 'pcs', 'paid') och deras visningsnamn.
 * Namnen på färdiga serviceobjekt är nycklar item.<item_key>. Namn, grupper och enheter som användaren själv skrivit visas som de är.
 */
return [
/* --- åtgärder (service_actions.action, item_catalog.default_action) --- */
'vocab.action.replaced'=>'Bytt / utfört',
'vocab.action.done'=>'Utfört',
'vocab.action.inspected'=>'Kontrollerat',
'vocab.action.repaired'=>'Reparerat',
'vocab.action.cleaned'=>'Rengjort',
'vocab.action.added'=>'Tillagt / påfyllt',

/* --- typ av service (services.service_type) --- */
'vocab.stype.scheduled'=>'Periodisk service',
'vocab.stype.repair'=>'Reparation',
'vocab.stype.inspection'=>'Besiktning',
'vocab.stype.tyres'=>'Däckarbete',
'vocab.stype.other'=>'Annat',

/* --- enheter --- */
'vocab.unit.pcs'=>'st',
'vocab.unit.l'=>'L',
'vocab.unit.set'=>'sats',
'vocab.unit.pack'=>'förp.',

/* --- fakturans status (invoices.status) --- */
'vocab.invstatus.draft'=>'Utkast',
'vocab.invstatus.sent'=>'Skickad',
'vocab.invstatus.paid'=>'Betald',
'vocab.invstatus.credited'=>'Krediterad',

/* --- grupper för serviceobjekt --- */
'vocab.section.engine'=>'Motor',
'vocab.section.interior'=>'Interiör',
'vocab.section.other'=>'Övrigt',
'vocab.section.chassis'=>'Chassi & vätskor',
'vocab.section.driveline'=>'Drivlina',
'vocab.section.electrical'=>'El',
'vocab.section.climate'=>'Klimat',

/* --- färdiga serviceobjekt --- */
'item.engine_oil'=>'Motorolja',
'item.oil_filter'=>'Oljefilter',
'item.cabin_filter'=>'Pollenfilter',
'item.air_filter'=>'Luftfilter',
'item.fuel_filter'=>'Bränslefilter',
'item.wipers'=>'Torkarblad',
'item.brake_fluid'=>'Bromsvätska',
'item.brakes_front'=>'Bromsar fram',
'item.brakes_rear'=>'Bromsar bak',
'item.spark_plugs'=>'Tändstift',
'item.gearbox_oil'=>'Växellådsolja',
'item.gearbox_filter'=>'Växellådsfilter',
'item.coolant'=>'Kylvätska',
'item.aux_belt'=>'Drivrem',
'item.aux_belt_inspect'=>'Kontroll av drivrem',
'item.timing_belt'=>'Kamrem / kamkedja',
'item.timing_belt_inspect'=>'Kontroll av kamrem',
'item.battery'=>'Batteri',
'item.power_steering'=>'Servostyrningsolja',
'item.diff_oil'=>'Differentialolja',
'item.transfer_oil'=>'Fördelningslåda / Haldex-olja',
'item.transfer_filter'=>'Haldex- / drivlinefilter',
'item.inspection'=>'Besiktning',
'item.spare_tyre_kit'=>'Kontroll av reservhjul / däckreparationssats',
'item.ac_service'=>'AC-service',

/* --- anteckningar som programmet själv sparar (i databasen koden @namn) --- */
'vocab.note.initial_balance'=>'Ingående saldo',
'vocab.note.stock_correction'=>'Saldokorrigering från lagervyn',
'vocab.note.initial_km'=>'Bilens startkilometer',
'vocab.note.accepted_deviation'=>'Godkänd som avvikelse i källmaterialet',
'vocab.note.auto_added'=>'Tillagd automatiskt från service',
'vocab.note.work_prefix'=>'Arbete: ',
'vocab.mechanic_other'=>'Annan',
'vocab.service_title_default'=>'Service',
'vocab.home_title_default'=>'Servicebok',
'vocab.home_subtitle_default'=>'Ha dina egna bilar, familjens och bekantas bilar i samma servicehistorik.',
];

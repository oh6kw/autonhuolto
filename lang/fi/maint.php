<?php
declare(strict_types=1);
return [
/* --- huoltokohteen tyypit ja seurantatavat --- */
'maint.kind_generic'=>'Yleinen',
'maint.kind_fluid'=>'Neste',
'maint.kind_part'=>'Varaosa',
'maint.kind_inspection'=>'Tarkastus',
'maint.kind_service'=>'Palvelu',
'maint.schedule_replace'=>'Vaihto',
'maint.schedule_inspect'=>'Tarkastus',
'maint.schedule_condition'=>'Kunnon mukaan',

/* --- huoltorivin määrä, hinta ja huomautukset --- */
'maint.unit_whole'=>'kokonaisuus',
'maint.price_unit'=>'Yksikköhinta {price}{suffix} sis. ALV {vat} %',
'maint.price_custom'=>'Hinta {price}{suffix} sis. ALV {vat} %',
'maint.price_net'=>'Veroton {price}{suffix}',
'maint.price_total'=>'Yhteensä {price} sis. ALV',
'maint.note_inspection_logged'=>'Tarkastus kirjattu · Vaihtoväliä ei nollattu',
'maint.note_interval_not_reset'=>'Huoltoväliä ei nollattu',

/* --- toteutuneet välit (actionIntervalText) --- */
'maint.no_previous_belt_inspection'=>'Ei aiempaa kirjattua hihnan tarkastusta tai vaihtoa',
'maint.no_previous_belt_replace'=>'Ei aiempaa kirjattua hihnan vaihtoa',
'maint.no_previous_start'=>'Ei aiempaa kirjattua huoltovälin aloitusta',
'maint.interval_unknown'=>'Väliä ei voida laskea',
'maint.interval_inspection'=>'Tarkastusväli: {interval} (edellinen {baseline})',
'maint.interval_belt_actual'=>'Toteutunut vaihtoväli: {interval} (edellinen {baseline})',
'maint.interval_belt_since_replace'=>'Edellisestä vaihdosta: {interval} (edellinen {baseline})',
'maint.interval_actual'=>'Toteutunut huoltoväli: {interval} (edellinen {baseline})',
'maint.interval_since_start'=>'Edellisestä huoltovälin aloituksesta: {interval} (edellinen {baseline})',
'maint.interval_since_replace'=>'Edellisestä vaihdosta: {interval} ({date})',

/* --- huoltotilanteen varoitukset --- */
'maint.warn_km_lower'=>'Nykyinen mittarilukema on pienempi kuin tämän kohteen viimeisessä huollossa. Tarkista kilometrit.',
'maint.warn_start_future'=>'Huoltovälin aloitus on tulevaisuudessa. Tarkista päiväys.',

/* --- ennusteet --- */
'maint.forecast_due_now'=>'Huolto on jo ajankohtainen',
'maint.forecast_basis'=>'perustuu {date} mittarilukemaan',
'maint.forecast_km_first'=>'Arvioitu huolto {date} · kilometriraja tulee arviolta ensin{basis}',
'maint.forecast_time_first'=>'Arvioitu huolto {date} · aikaraja tulee arviolta ensin (km-raja nykyajolla noin {km_date}){basis}',
'maint.forecast_km_only'=>'Km-raja saavutetaan nykyajolla arviolta {date}{basis}',

/* --- huoltoajankohta ja tilat --- */
'maint.no_interval'=>'Ei huoltoväliä',
'maint.no_baseline'=>'Ei lähtötietoa',
'maint.deadline'=>'viimeistään {value}',
'maint.km_over'=>'{n} km yli',
'maint.km_left'=>'{n} km jäljellä',
'maint.days_over'=>'{n} pv yli',
'maint.days_left'=>'{n} pv jäljellä',
'maint.status_due_inspect'=>'TARKASTUS AJANKOHTA',
'maint.status_due_condition'=>'TARKISTA KUNTO',
'maint.status_due'=>'HUOLTOAJANKOHTA',
'maint.status_soon_inspect'=>'TARKASTUS LÄHESTYY',
'maint.status_soon'=>'LÄHESTYY',
'maint.status_ok'=>'OK',
];

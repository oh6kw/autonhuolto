<?php
declare(strict_types=1);
return [
/* --- typer av serviceobjekt och uppföljningssätt --- */
'maint.kind_generic'=>'Allmänt',
'maint.kind_fluid'=>'Vätska',
'maint.kind_part'=>'Reservdel',
'maint.kind_inspection'=>'Kontroll',
'maint.kind_service'=>'Tjänst',
'maint.schedule_replace'=>'Byte',
'maint.schedule_inspect'=>'Kontroll',
'maint.schedule_condition'=>'Enligt skick',

/* --- mängd, pris och anmärkningar på serviceraden --- */
'maint.unit_whole'=>'helhet',
'maint.price_unit'=>'Enhetspris {price}{suffix} inkl. moms {vat} %',
'maint.price_custom'=>'Pris {price}{suffix} inkl. moms {vat} %',
'maint.price_net'=>'Utan moms {price}{suffix}',
'maint.price_total'=>'Totalt {price} inkl. moms',
'maint.note_inspection_logged'=>'Kontroll antecknad · Bytesintervallet nollställdes inte',
'maint.note_interval_not_reset'=>'Serviceintervallet nollställdes inte',

/* --- faktiska intervall (actionIntervalText) --- */
'maint.no_previous_belt_inspection'=>'Ingen tidigare antecknad kontroll eller byte av remmen',
'maint.no_previous_belt_replace'=>'Inget tidigare antecknat byte av remmen',
'maint.no_previous_start'=>'Ingen tidigare antecknad start av serviceintervallet',
'maint.interval_unknown'=>'Intervallet kan inte beräknas',
'maint.interval_inspection'=>'Kontrollintervall: {interval} (föregående {baseline})',
'maint.interval_belt_actual'=>'Faktiskt bytesintervall: {interval} (föregående {baseline})',
'maint.interval_belt_since_replace'=>'Sedan föregående byte: {interval} (föregående {baseline})',
'maint.interval_actual'=>'Faktiskt serviceintervall: {interval} (föregående {baseline})',
'maint.interval_since_start'=>'Sedan föregående start av serviceintervallet: {interval} (föregående {baseline})',
'maint.interval_since_replace'=>'Sedan föregående byte: {interval} ({date})',

/* --- varningar i servicesituationen --- */
'maint.warn_km_lower'=>'Den nuvarande mätarställningen är lägre än vid det här objektets senaste service. Kontrollera kilometerna.',
'maint.warn_start_future'=>'Starten av serviceintervallet ligger i framtiden. Kontrollera datumet.',

/* --- prognoser --- */
'maint.forecast_due_now'=>'Servicen är redan aktuell',
'maint.forecast_basis'=>'baserat på mätarställningen {date}',
'maint.forecast_km_first'=>'Beräknad service {date} · kilometergränsen nås enligt uppskattning först{basis}',
'maint.forecast_time_first'=>'Beräknad service {date} · tidsgränsen nås enligt uppskattning först (km-gränsen vid nuvarande körning omkring {km_date}){basis}',
'maint.forecast_km_only'=>'Km-gränsen nås vid nuvarande körning enligt uppskattning {date}{basis}',

/* --- servicetidpunkt och status --- */
'maint.no_interval'=>'Inget serviceintervall',
'maint.no_baseline'=>'Inga utgångsuppgifter',
'maint.deadline'=>'senast {value}',
'maint.km_over'=>'{n} km över',
'maint.km_left'=>'{n} km kvar',
'maint.days_over'=>'{n} d över',
'maint.days_left'=>'{n} d kvar',
'maint.status_due_inspect'=>'KONTROLL AKTUELL',
'maint.status_due_condition'=>'KONTROLLERA SKICKET',
'maint.status_due'=>'DAGS FÖR SERVICE',
'maint.status_soon_inspect'=>'KONTROLL NÄRMAR SIG',
'maint.status_soon'=>'NÄRMAR SIG',
'maint.status_ok'=>'OK',
];

<?php
declare(strict_types=1);
/* Svenska – kunder (views/customers.php, app/customers.php). Se app/i18n.php. */
return [
    /* --- kundtyper och status --- */
    'cust.type_private'=>'Privatperson',
    'cust.type_company'=>'Företag',
    'cust.active'=>'I bruk',
    'cust.archived'=>'Arkiverad',

    /* --- enskild kund --- */
    'cust.customer_eyebrow'=>'Kund {num}',
    'cust.back_to_list'=>'← Kunder',
    'cust.details_title'=>'Kunduppgifter',
    'cust.edit_note'=>'Att redigera kunden ändrar inte ögonblicksbilderna i gamla servicetillfällen eller fakturor.',
    'cust.number'=>'Kundnummer',
    'cust.type'=>'Typ',
    'cust.name'=>'Namn / företag',
    'cust.contact'=>'Kontaktperson',
    'cust.phone'=>'Telefon',
    'cust.email'=>'E-post',
    'cust.street'=>'Gatuadress',
    'cust.postal_code'=>'Postnummer',
    'cust.city'=>'Postanstalt',
    'cust.business_id'=>'FO-nummer',
    'cust.notes'=>'Anteckningar',
    'cust.save'=>'Spara kund',

    /* --- radering --- */
    'cust.delete_summary'=>'🗑 Radera kunden permanent',
    'cust.delete_warning'=>'Permanent radering lyckas bara för en kund som inte är kopplad till någon bil och inte används i ägarhistoriken eller i servicetillfällenas kunduppgifter. En kund som är i bruk lönar det sig att arkivera.',
    'cust.delete_admin_only'=>'Permanent radering av kund är endast en administratörsåtgärd.',
    'cust.delete_confirm'=>'Raderas kunden permanent? Åtgärden kan inte ångras.',
    'cust.delete_btn'=>'Radera kunden permanent',
    'cust.delete_no_refs'=>'För tillfället har kunden inga kopplingar till bilar, ägarhistorik eller servicetillfällen.',
    'cust.delete_blocked'=>'<strong>Radering förhindrad:</strong> kunden har {cars} nuvarande bilar, {history} rader i ägarhistoriken och {services} servicetillfällen.',

    /* --- bilar, ägarhistorik, servicehistorik --- */
    'cust.cars_title'=>'🚗 Nuvarande bilar',
    'cust.cars_count'=>'{n} bilar är för tillfället kopplade till den här kunden.',
    'cust.no_cars'=>'Inga nuvarande bilar.',
    'cust.car_fallback'=>'BIL',
    'cust.odometer'=>'Mätare',
    'cust.services'=>'Servicetillfällen',
    'cust.last'=>'Senaste',
    'cust.history_summary'=>'🕘 Ägarhistorik',
    'cust.no_history'=>'Ingen ägarhistorik ännu.',
    'cust.col_car'=>'Bil',
    'cust.col_from'=>'Från',
    'cust.col_to'=>'Till',
    'cust.col_name_snapshot'=>'Namn (ögonblicksbild)',
    'cust.unknown'=>'okänd',
    'cust.current'=>'nuvarande',
    'cust.services_summary'=>'🔧 Servicehistorik för kundens bilar',
    'cust.services_note'=>'Vid nya servicetillfällen identifieras kunden utifrån ögonblicksbilden från tidpunkten. För gamla noteringar som gjorts före kundregistret visas också historiken för kundens nuvarande bilar.',
    'cust.no_services'=>'Inga servicetillfällen hittades.',

    /* --- kundlista --- */
    'cust.list_eyebrow'=>'Kundregister',
    'cust.list_title'=>'Kunder',
    'cust.list_intro'=>'En kund kan äga flera bilar. Kundens uppgifter underhålls på ett ställe.',
    'cust.count'=>'{n} kunder',
    'cust.archive_note'=>'Arkivering raderar varken kunden eller historiken.',
    'cust.search_placeholder'=>'Sök med namn, nummer, telefon, e-post...',
    'cust.cars'=>'Bilar',
    'cust.status'=>'Status',
    'cust.add'=>'＋ Lägg till kund',
];

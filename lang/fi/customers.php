<?php
declare(strict_types=1);
/* Suomi – asiakkaat (views/customers.php, app/customers.php). Katso app/i18n.php. */
return [
    /* --- asiakastyypit ja tilat --- */
    'cust.type_private'=>'Yksityinen',
    'cust.type_company'=>'Yritys',
    'cust.active'=>'Käytössä',
    'cust.archived'=>'Arkistoitu',

    /* --- yksittäinen asiakas --- */
    'cust.customer_eyebrow'=>'Asiakas {num}',
    'cust.back_to_list'=>'← Asiakkaat',
    'cust.details_title'=>'Asiakastiedot',
    'cust.edit_note'=>'Asiakkaan muokkaaminen ei muuta vanhojen huoltojen tai laskujen snapshot-tietoja.',
    'cust.number'=>'Asiakasnumero',
    'cust.type'=>'Tyyppi',
    'cust.name'=>'Nimi / yritys',
    'cust.contact'=>'Yhteyshenkilö',
    'cust.phone'=>'Puhelin',
    'cust.email'=>'Sähköposti',
    'cust.street'=>'Katuosoite',
    'cust.postal_code'=>'Postinumero',
    'cust.city'=>'Postitoimipaikka',
    'cust.business_id'=>'Y-tunnus',
    'cust.notes'=>'Muistiinpanot',
    'cust.save'=>'Tallenna asiakas',

    /* --- poisto --- */
    'cust.delete_summary'=>'🗑 Poista asiakas pysyvästi',
    'cust.delete_warning'=>'Pysyvä poisto onnistuu vain asiakkaalle, jota ei ole liitetty autoon eikä käytetty omistushistoriassa tai huoltojen asiakastiedoissa. Käytössä oleva asiakas kannattaa arkistoida.',
    'cust.delete_admin_only'=>'Pysyvä asiakaspoisto on vain ylläpitäjän toiminto.',
    'cust.delete_confirm'=>'Poistetaanko asiakas pysyvästi? Toimintoa ei voi perua.',
    'cust.delete_btn'=>'Poista asiakas pysyvästi',
    'cust.delete_no_refs'=>'Tällä hetkellä asiakkaalla ei ole sidoksia autoihin, omistushistoriaan tai huoltoihin.',
    'cust.delete_blocked'=>'<strong>Poisto estetty:</strong> asiakkaalla on {cars} nykyistä autoa, {history} omistushistorian riviä ja {services} huoltoa.',

    /* --- autot, omistushistoria, huoltohistoria --- */
    'cust.cars_title'=>'🚗 Nykyiset autot',
    'cust.cars_count'=>'{n} autoa on tällä hetkellä liitetty tähän asiakkaaseen.',
    'cust.no_cars'=>'Ei nykyisiä autoja.',
    'cust.car_fallback'=>'AUTO',
    'cust.odometer'=>'Mittari',
    'cust.services'=>'Huoltoja',
    'cust.last'=>'Viimeisin',
    'cust.history_summary'=>'🕘 Omistushistoria',
    'cust.no_history'=>'Omistushistoriaa ei ole vielä.',
    'cust.col_car'=>'Auto',
    'cust.col_from'=>'Alkaen',
    'cust.col_to'=>'Päättyen',
    'cust.col_name_snapshot'=>'Nimi snapshot',
    'cust.unknown'=>'ei tiedossa',
    'cust.current'=>'nykyinen',
    'cust.services_summary'=>'🔧 Huoltohistoria asiakkaan autoista',
    'cust.services_note'=>'Uusissa huolloissa asiakas tunnistetaan tapahtumahetken snapshotista. Vanhoista, ennen asiakasrekisteriä tehdyistä merkinnöistä näytetään myös asiakkaan nykyisten autojen historia.',
    'cust.no_services'=>'Huoltoja ei löytynyt.',

    /* --- asiakaslista --- */
    'cust.list_eyebrow'=>'Asiakasrekisteri',
    'cust.list_title'=>'Asiakkaat',
    'cust.list_intro'=>'Yksi asiakas voi omistaa useita autoja. Asiakkaan tiedot ylläpidetään yhdessä paikassa.',
    'cust.count'=>'{n} asiakasta',
    'cust.archive_note'=>'Arkistointi ei poista asiakasta eikä historiaa.',
    'cust.search_placeholder'=>'Hae nimellä, numerolla, puhelimella, sähköpostilla...',
    'cust.cars'=>'Autot',
    'cust.status'=>'Tila',
    'cust.add'=>'＋ Lisää asiakas',
];

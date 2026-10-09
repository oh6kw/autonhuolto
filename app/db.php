<?php
declare(strict_types=1);

/**
 * app/db.php – tietokanta.
 * Tiedoston valinta, käyttölukko ja yhteys; skeema ja sen tarkistus; uuden kannan alustus; sovellusasetukset.
 * Tietokannan skeemaversio on 12 (SCHEMA_VERSION). Vanhempaa tai uudempaa kantaa ei avata (requireCurrentDatabaseVersion).
 * Ei migraatioita: jos skeema muuttuu, lisää migraatio tähän ja nosta SCHEMA_VERSION.
 * Ladataan index.php:n alussa.
 */

/* ------------------------------ Tiedosto, lukko ja yhteys ------------------------------ */
/** Yksi pyyntö kerrallaan: lukko estää tietokannan ja kuvien vaihdon toisen sovelluspyynnön ollessa kesken. Vapautuu pyynnön päättyessä. */
function dbAcquireLock(): void {
    $lock=@fopen(APP_DIR.'/.autohuolto-access-7f3c91.lock','c');
    if(!$lock){http_response_code(503);exit('Huoltokirjan käyttölukkoa ei saatu avattua. Tarkista hakemiston kirjoitusoikeus.');}
    $deadline=microtime(true)+12;
    while(!flock($lock,LOCK_EX|LOCK_NB)){
        if(microtime(true)>=$deadline){http_response_code(503);exit('Huoltokirja tekee parhaillaan toista tallennusta tai varmuuskopiota. Yritä hetken kuluttua uudelleen.');}
        usleep(100000);
    }
    register_shutdown_function(static function() use ($lock){if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}});
}
/** Avaa tietokannan. Vanhan kannan versio tarkistetaan ennen kirjoittamista tai WAL-tilan vaihtamista; tyhjä kanta alustetaan. */
function dbConnect(): PDO {
    $db=new PDO('sqlite:'.DB_FILE,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=5000');
    $fresh=(int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name<>'sqlite_sequence'")->fetchColumn()===0;
    if(!$fresh)requireCurrentDatabaseVersion($db);
    $db->exec('PRAGMA journal_mode=WAL');
    if($fresh)initializeFreshDatabase($db);
    @chmod(DB_FILE,0600);
    return $db;
}
function sqliteLooksValid(string $file): bool {
    $fh=@fopen($file,'rb'); if(!$fh)return false; $sig=fread($fh,16); fclose($fh); return $sig==="SQLite format 3\0";
}
function initialAppName(): string {
    if(!is_file(DB_FILE)) return DEFAULT_APP_NAME;
    try {
        $pdo=new PDO('sqlite:'.DB_FILE,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $exists=(int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='app_settings'")->fetchColumn();
        if(!$exists) return DEFAULT_APP_NAME;
        $st=$pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key='shop_name'");
        $st->execute(); $v=trim((string)($st->fetchColumn() ?: ''));
        return $v!==''?$v:DEFAULT_APP_NAME;
    } catch(Throwable) { return DEFAULT_APP_NAME; }
}
function consistentDatabaseCopy(PDO $db,string $target): void {
    if(file_exists($target))throw new RuntimeException('Turvakopion kohdetiedosto on jo olemassa.');
    try {
        // VACUUM INTO lukee yhtenäisen SQLite-snapshotin, myös WAL:ssa olevat tallennukset.
        $db->exec('VACUUM INTO '.$db->quote($target));
        @chmod($target,0600);
        $check=new PDO('sqlite:'.$target,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if($check->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException('Tietokantakopion eheystarkistus epäonnistui.');
        $check=null;
    } catch(Throwable $e) {
        @unlink($target);
        throw new RuntimeException('Eheää tietokantakopiota ei saatu luotua: '.$e->getMessage(),0,$e);
    }
}

/* ------------------------------ Skeema ja rakenteen tarkistus ------------------------------ */
/** Yksi täydellinen skeema tyhjän kannan luontiin ja palautuksen rakennetarkistukseen. */
function currentDatabaseSql(): string {
    return <<<'SQL'
CREATE TABLE app_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL DEFAULT ''
);
CREATE TABLE customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_number TEXT NOT NULL DEFAULT '',
    customer_type TEXT NOT NULL DEFAULT 'private',
    name TEXT NOT NULL,
    contact_name TEXT NOT NULL DEFAULT '',
    phone TEXT NOT NULL DEFAULT '',
    email TEXT NOT NULL DEFAULT '',
    address TEXT NOT NULL DEFAULT '',
    postal_code TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    business_id TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE cars (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reg_plate TEXT NOT NULL DEFAULT '', nickname TEXT NOT NULL DEFAULT '', owner TEXT NOT NULL DEFAULT '',
    make TEXT NOT NULL DEFAULT '', model TEXT NOT NULL DEFAULT '', year TEXT NOT NULL DEFAULT '', engine TEXT NOT NULL DEFAULT '', vin TEXT NOT NULL DEFAULT '',
    current_km INTEGER NOT NULL DEFAULT 0, notes TEXT NOT NULL DEFAULT '', first_registration_date TEXT NOT NULL DEFAULT '',
    warning_km INTEGER NOT NULL DEFAULT 3000, warning_months INTEGER NOT NULL DEFAULT 3,
    engine_oil_capacity REAL DEFAULT NULL, engine_oil_spec TEXT NOT NULL DEFAULT '',
    gearbox_oil_capacity REAL DEFAULT NULL, gearbox_oil_spec TEXT NOT NULL DEFAULT '',
    coolant_capacity REAL DEFAULT NULL, coolant_spec TEXT NOT NULL DEFAULT '',
    customer_address TEXT NOT NULL DEFAULT '', customer_email TEXT NOT NULL DEFAULT '', customer_phone TEXT NOT NULL DEFAULT '', customer_business_id TEXT NOT NULL DEFAULT '',
    current_customer_id INTEGER DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE odometer_readings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    car_id INTEGER NOT NULL,
    odometer INTEGER NOT NULL,
    recorded_at TEXT NOT NULL,
    source TEXT NOT NULL DEFAULT 'manual',
    note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(car_id) REFERENCES cars(id) ON DELETE CASCADE
);
CREATE TABLE car_customer_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    car_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    start_date TEXT NOT NULL DEFAULT '',
    end_date TEXT NOT NULL DEFAULT '',
    customer_name_snapshot TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(car_id) REFERENCES cars(id) ON DELETE CASCADE,
    FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT
);
CREATE TABLE item_catalog (
    item_key TEXT PRIMARY KEY, label TEXT NOT NULL, section TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0,
    is_custom INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1,
    item_kind TEXT NOT NULL DEFAULT 'generic', default_action TEXT NOT NULL DEFAULT 'Vaihdettu / tehty',
    default_unit TEXT NOT NULL DEFAULT 'kpl', default_quantity REAL DEFAULT NULL, default_resets_interval INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE car_item_settings (
    car_id INTEGER NOT NULL, item_key TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1,
    interval_km INTEGER NOT NULL DEFAULT 0, interval_months INTEGER NOT NULL DEFAULT 0,
    schedule_type TEXT NOT NULL DEFAULT 'replace',
    first_due_km INTEGER NOT NULL DEFAULT 0, first_due_date TEXT NOT NULL DEFAULT '',
    preferred_brand TEXT NOT NULL DEFAULT '', supplier_sku TEXT NOT NULL DEFAULT '', oem_number TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '',
    PRIMARY KEY(car_id,item_key), FOREIGN KEY(car_id) REFERENCES cars(id) ON DELETE CASCADE, FOREIGN KEY(item_key) REFERENCES item_catalog(item_key) ON DELETE CASCADE
);
CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT, car_id INTEGER NOT NULL, service_date TEXT NOT NULL, odometer INTEGER NOT NULL DEFAULT 0,
    title TEXT NOT NULL DEFAULT 'Huolto', service_type TEXT NOT NULL DEFAULT 'Määräaikaishuolto', workshop TEXT NOT NULL DEFAULT '', total_cost REAL DEFAULT NULL, notes TEXT NOT NULL DEFAULT '',
    labor_hours REAL NOT NULL DEFAULT 0, labor_rate REAL DEFAULT NULL, labor_vat_rate REAL DEFAULT NULL,
    actual_work_seconds INTEGER NOT NULL DEFAULT 0,
    service_origin TEXT NOT NULL DEFAULT '',
    customer_id INTEGER DEFAULT NULL, customer_name_snapshot TEXT NOT NULL DEFAULT '',
    customer_address_snapshot TEXT NOT NULL DEFAULT '', customer_email_snapshot TEXT NOT NULL DEFAULT '', customer_phone_snapshot TEXT NOT NULL DEFAULT '', customer_business_id_snapshot TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mechanic_id INTEGER DEFAULT NULL,
    mechanic_name_snapshot TEXT NOT NULL DEFAULT '',
    FOREIGN KEY(car_id) REFERENCES cars(id) ON DELETE CASCADE
);
CREATE TABLE service_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, service_id INTEGER NOT NULL, item_key TEXT NOT NULL, action TEXT NOT NULL DEFAULT 'Vaihdettu / tehty',
    brand TEXT NOT NULL DEFAULT '', supplier_sku TEXT NOT NULL DEFAULT '', oem_number TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '',
    quantity REAL DEFAULT NULL, unit TEXT NOT NULL DEFAULT '', price_net REAL DEFAULT NULL, vat_rate REAL DEFAULT NULL,
    item_label_snapshot TEXT NOT NULL DEFAULT '', item_section_snapshot TEXT NOT NULL DEFAULT '', resets_interval INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE, FOREIGN KEY(item_key) REFERENCES item_catalog(item_key) ON DELETE RESTRICT
);
CREATE TABLE service_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT, service_id INTEGER NOT NULL, car_id INTEGER NOT NULL,
    file_path TEXT NOT NULL UNIQUE, original_name TEXT NOT NULL DEFAULT '', mime_type TEXT NOT NULL DEFAULT '', file_size INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    caption TEXT NOT NULL DEFAULT '',
    FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE, FOREIGN KEY(car_id) REFERENCES cars(id) ON DELETE CASCADE
);
CREATE TABLE service_custom_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, service_id INTEGER NOT NULL, description TEXT NOT NULL, notes TEXT NOT NULL DEFAULT '', price_net REAL DEFAULT NULL, vat_rate REAL DEFAULT NULL,
    FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE
);
CREATE TABLE mechanics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    is_default INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE parts (
    id INTEGER PRIMARY KEY AUTOINCREMENT, car_id INTEGER NOT NULL, item_key TEXT NOT NULL DEFAULT '', part_name TEXT NOT NULL,
    brand TEXT NOT NULL DEFAULT '', supplier_sku TEXT NOT NULL DEFAULT '', oem_number TEXT NOT NULL DEFAULT '', url TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, stock_qty REAL NOT NULL DEFAULT 0, stock_unit TEXT NOT NULL DEFAULT 'kpl', shelf_location TEXT NOT NULL DEFAULT '', reorder_level REAL DEFAULT NULL, purchase_price REAL DEFAULT NULL, FOREIGN KEY(car_id) REFERENCES cars(id) ON DELETE CASCADE
);
CREATE TABLE invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT, service_id INTEGER NOT NULL UNIQUE, invoice_number TEXT NOT NULL UNIQUE,
    issue_date TEXT NOT NULL, due_date TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'Luonnos',
    seller_name TEXT NOT NULL DEFAULT '', seller_business_id TEXT NOT NULL DEFAULT '', seller_address TEXT NOT NULL DEFAULT '', seller_email TEXT NOT NULL DEFAULT '', seller_phone TEXT NOT NULL DEFAULT '', seller_iban TEXT NOT NULL DEFAULT '', seller_bic TEXT NOT NULL DEFAULT '',
    seller_mobilepay_enabled INTEGER NOT NULL DEFAULT 0, seller_mobilepay_number TEXT NOT NULL DEFAULT '', seller_mobilepay_name TEXT NOT NULL DEFAULT '', seller_logo_path TEXT NOT NULL DEFAULT '',
    customer_name TEXT NOT NULL DEFAULT '', customer_address TEXT NOT NULL DEFAULT '', customer_email TEXT NOT NULL DEFAULT '', customer_business_id TEXT NOT NULL DEFAULT '',
    sent_at TEXT NOT NULL DEFAULT '', paid_at TEXT NOT NULL DEFAULT '', credited_at TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE RESTRICT
);
CREATE TABLE invoice_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL, description TEXT NOT NULL, qty REAL NOT NULL DEFAULT 1, unit TEXT NOT NULL DEFAULT 'kpl',
    unit_price_net REAL NOT NULL DEFAULT 0, vat_rate REAL NOT NULL DEFAULT 25.5, sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);
CREATE TABLE data_issue_ignores (
    issue_key TEXT PRIMARY KEY,
    issue_title TEXT NOT NULL DEFAULT '',
    issue_detail TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    accepted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE inventory_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, part_id INTEGER NOT NULL, quantity_change REAL NOT NULL,
    event_type TEXT NOT NULL DEFAULT 'adjustment', note TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(part_id) REFERENCES parts(id) ON DELETE CASCADE
);
CREATE TABLE part_car_compatibility (
    part_id INTEGER NOT NULL,
    car_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(part_id,car_id),
    FOREIGN KEY(part_id) REFERENCES parts(id) ON DELETE CASCADE,
    FOREIGN KEY(car_id) REFERENCES cars(id) ON DELETE CASCADE
);
CREATE TABLE service_inventory_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL,
    item_key TEXT NOT NULL,
    part_id INTEGER NOT NULL,
    quantity REAL NOT NULL,
    unit TEXT NOT NULL DEFAULT 'kpl',
    shelf_snapshot TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(service_id,item_key),
    FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY(part_id) REFERENCES parts(id) ON DELETE RESTRICT
);

CREATE INDEX idx_inventory_transactions_part_time ON inventory_transactions(part_id,created_at,id);
CREATE INDEX idx_part_car_compatibility_car ON part_car_compatibility(car_id,part_id);
CREATE INDEX idx_service_inventory_usage_service ON service_inventory_usage(service_id,item_key);
CREATE INDEX idx_service_inventory_usage_part ON service_inventory_usage(part_id,service_id);
CREATE INDEX idx_services_car_date ON services(car_id,service_date,id);
CREATE INDEX idx_service_actions_service ON service_actions(service_id,item_key);
CREATE INDEX idx_service_actions_item ON service_actions(item_key,service_id);
CREATE INDEX idx_parts_car_item ON parts(car_id,item_key,id);
CREATE INDEX idx_mechanics_active_sort ON mechanics(active,is_default DESC,sort_order,name,id);
CREATE UNIQUE INDEX idx_customers_number ON customers(customer_number) WHERE TRIM(customer_number)<>'';
CREATE INDEX idx_customers_name ON customers(active,name,id);
CREATE INDEX idx_car_customer_history_car ON car_customer_history(car_id,start_date,id);
CREATE INDEX idx_car_customer_history_customer ON car_customer_history(customer_id,car_id,id);
CREATE INDEX idx_services_customer ON services(customer_id,service_date,id);
CREATE INDEX idx_services_mechanic ON services(mechanic_id,service_date,id);
CREATE INDEX idx_cars_current_customer ON cars(current_customer_id,id);
CREATE INDEX idx_odometer_readings_car_time ON odometer_readings(car_id,recorded_at,id);
CREATE INDEX idx_service_custom_actions_service ON service_custom_actions(service_id,id);
CREATE INDEX idx_service_photos_service ON service_photos(service_id,id);
CREATE INDEX idx_service_photos_car ON service_photos(car_id,id);
CREATE INDEX idx_invoice_lines_invoice ON invoice_lines(invoice_id,sort_order,id);
CREATE INDEX idx_invoices_issue_status ON invoices(issue_date,status,id);
CREATE INDEX idx_invoices_paid_at ON invoices(paid_at,id);
SQL;
}
function currentDatabaseDefinition(): array {
    static $definition=null;
    if($definition!==null)return $definition;
    $reference=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $reference->exec(currentDatabaseSql());$definition=[];
    foreach($reference->query("SELECT name FROM sqlite_master WHERE type='table' AND name<>'sqlite_sequence'")->fetchAll(PDO::FETCH_COLUMN) as $table){
        $columns=[];foreach($reference->query('PRAGMA table_info("'.$table.'")')->fetchAll() as $col)$columns[$col['name']]=['type'=>(string)$col['type'],'pk'=>(int)$col['pk']];
        $foreignKeys=[];foreach($reference->query('PRAGMA foreign_key_list("'.$table.'")')->fetchAll() as $fk)$foreignKeys[]=[(string)$fk['table'],(string)$fk['from'],(string)$fk['to']];
        $unique=[];foreach($reference->query('PRAGMA index_list("'.$table.'")')->fetchAll() as $idx){if(!(int)$idx['unique']||(int)$idx['partial'])continue;$unique[]=array_column($reference->query('PRAGMA index_info('.$reference->quote((string)$idx['name']).')')->fetchAll(),'name');}
        $definition[$table]=['columns'=>$columns,'foreign_keys'=>$foreignKeys,'unique'=>$unique];
    }
    return $definition;
}
function requireCurrentDatabaseVersion(PDO $db): void {
    if(!(int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='app_settings'")->fetchColumn())throw new RuntimeException('Tietokannasta puuttuu huoltokirjan asetustaulu.');
    $raw=$db->query("SELECT setting_value FROM app_settings WHERE setting_key='schema_version'")->fetchColumn();
    if($raw===false||!preg_match('/^[0-9]+$/D',(string)$raw))throw new RuntimeException('Tietokannan skeemaversio puuttuu tai on virheellinen.');
    $version=(int)$raw;
    if($version<SCHEMA_VERSION)throw new RuntimeException('Tietokannan skeemaversio '.$version.' on liian vanha. Tämä ohjelma tukee skeemaversiota '.SCHEMA_VERSION.'. Päivitä vanha kanta ensin ohjelmalla v0.8.22-dev.');
    if($version>SCHEMA_VERSION)throw new RuntimeException('Tietokanta on tehty uudemmalla ohjelmaversiolla. Päivitä ohjelma ennen kannan käyttöä tai palautusta.');
}
function authSchema(PDO $db): void {
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS auth_users (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 username TEXT NOT NULL COLLATE NOCASE UNIQUE,
 display_name TEXT NOT NULL,
 password_hash TEXT NOT NULL,
 role TEXT NOT NULL CHECK(role IN ('viewer','editor','admin')),
 active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
 session_version INTEGER NOT NULL DEFAULT 1,
 force_password_change INTEGER NOT NULL DEFAULT 0,
 mechanic_id INTEGER NOT NULL DEFAULT 0,
 created_at TEXT NOT NULL,
 updated_at TEXT NOT NULL,
 last_login_at TEXT
);
CREATE TABLE IF NOT EXISTS auth_attempts (
 scope TEXT NOT NULL,
 attempt_key TEXT NOT NULL,
 window_started INTEGER NOT NULL,
 failures INTEGER NOT NULL,
 locked_until INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(scope,attempt_key)
);
CREATE TABLE IF NOT EXISTS auth_audit (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 actor_id INTEGER,
 event TEXT NOT NULL,
 target_id INTEGER,
 created_at TEXT NOT NULL
);
SQL);
}

/* ------------------------------ Uuden tietokannan alustus ------------------------------ */
function defaultAppSettings(): array {
    return [
        'feature_invoicing'=>'0','feature_mechanics'=>'0','feature_customers'=>'0','feature_time_tracking'=>'0','feature_inventory'=>'0',
        'parts_markup_enabled'=>'0','parts_markup_percent'=>'15',
        'theme'=>'dark','hourly_rate'=>'65.00','vat_rate'=>'25.5','price_input_mode'=>'net','payment_days'=>'14',
        'shop_name'=>DEFAULT_APP_NAME,'home_title'=>DEFAULT_HOME_TITLE,'home_subtitle'=>DEFAULT_HOME_SUBTITLE,
        'business_id'=>'','shop_address'=>'','shop_email'=>'','shop_phone'=>'','iban'=>'','bic'=>'',
        'mobilepay_enabled'=>'0','mobilepay_number'=>'','mobilepay_name'=>'','logo_path'=>'',
        'show_logo_invoice'=>'1','show_logo_service_print'=>'1','show_logo_car_history'=>'1','show_logo_all_history'=>'1','show_logo_header'=>'0'
    ];
}
/** Valmiit kohteet saavat profiilinsa suoraan luonnissa, eivät vanhan kannan migraatiossa. */
function defaultMaintenanceItems(): array {
    return [
        ['engine_oil','Moottoriöljy','Moottori',10,'fluid','Vaihdettu / tehty','L',null,1],
        ['oil_filter','Moottoriöljyn suodatin','Moottori',20,'part','Vaihdettu / tehty','kpl',1,1],
        ['cabin_filter','Raitisilmasuodatin','Sisätila',30,'part','Vaihdettu / tehty','kpl',1,1],
        ['air_filter','Ilmansuodatin','Moottori',40,'part','Vaihdettu / tehty','kpl',1,1],
        ['fuel_filter','Polttoainesuodatin','Moottori',50,'part','Vaihdettu / tehty','kpl',1,1],
        ['wipers','Pyyhkijänsulat','Muut',60,'part','Vaihdettu / tehty','kpl',1,1],
        ['brake_fluid','Jarruneste','Alusta & nesteet',70,'fluid','Vaihdettu / tehty','L',null,1],
        ['brakes_front','Etujarrut','Alusta & nesteet',80,'inspection','Tarkastettu','kpl',1,1],
        ['brakes_rear','Takajarrut','Alusta & nesteet',90,'inspection','Tarkastettu','kpl',1,1],
        ['spark_plugs','Sytytystulpat','Moottori',100,'part','Vaihdettu / tehty','kpl',null,1],
        ['gearbox_oil','Vaihteistoöljy','Voimansiirto',110,'fluid','Vaihdettu / tehty','L',null,1],
        ['gearbox_filter','Vaihteistoöljyn suodatin','Voimansiirto',120,'part','Vaihdettu / tehty','kpl',1,1],
        ['coolant','Jäähdytysneste','Alusta & nesteet',130,'fluid','Vaihdettu / tehty','L',null,1],
        ['aux_belt','Apulaitehihna','Moottori',140,'part','Vaihdettu / tehty','kpl',1,1],
        ['aux_belt_inspect','Apulaitehihnan tarkastus','Moottori',141,'inspection','Tarkastettu','kpl',null,1],
        ['timing_belt','Jakohihna / jakopää','Moottori',150,'part','Vaihdettu / tehty','kpl',1,1],
        ['timing_belt_inspect','Jakohihnan tarkastus','Moottori',151,'inspection','Tarkastettu','kpl',null,1],
        ['battery','Akku','Sähkö',160,'part','Vaihdettu / tehty','kpl',1,1],
        ['power_steering','Ohjaustehostimen neste','Alusta & nesteet',170,'fluid','Vaihdettu / tehty','L',null,1],
        ['diff_oil','Tasauspyörästön öljy','Voimansiirto',180,'fluid','Vaihdettu / tehty','L',null,1],
        ['transfer_oil','Jakolaatikko / Haldex-öljy','Voimansiirto',190,'fluid','Vaihdettu / tehty','L',null,1],
        ['transfer_filter','Haldex / voimansiirron suodatin','Voimansiirto',200,'part','Vaihdettu / tehty','kpl',1,1],
        ['inspection','Katsastus','Muut',210,'inspection','Tehty','kpl',null,1],
    ];
}
function initializeFreshDatabase(PDO $db): void {
    $db->beginTransaction();
    try{
        $db->exec(currentDatabaseSql());
        $setting=$db->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?)');
        foreach(defaultAppSettings()+['schema_version'=>(string)SCHEMA_VERSION] as $key=>$value)$setting->execute([$key,$value]);
        $item=$db->prepare('INSERT INTO item_catalog(item_key,label,section,sort_order,item_kind,default_action,default_unit,default_quantity,default_resets_interval) VALUES(?,?,?,?,?,?,?,?,?)');
        foreach(defaultMaintenanceItems() as $row)$item->execute($row);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/* ------------------------------ Asetukset ja ominaisuuskytkimet ------------------------------ */
function appSettings(PDO $db): array { $a=[]; foreach($db->query("SELECT setting_key,setting_value FROM app_settings")->fetchAll() as $r)$a[$r['setting_key']]=$r['setting_value']; return $a; }
function appSet(PDO $db,string $key,string $value): void { $db->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value")->execute([$key,$value]); }
function featureEnabled(array $app,string $key): bool { return (string)($app[$key]??'0')==='1'; }
/** Varaosakate prosentteina, tai null jos kate ei ole käytössä (vaatii laskutuksen ja varaston). */
function partsMarkupPercent(array $app): ?float {
    if(!invoicingEnabled($app)||!inventoryEnabled($app)||($app['parts_markup_enabled']??'0')!=='1')return null;
    $v=(float)str_replace(',','.',(string)($app['parts_markup_percent']??'15'));return ($v>0&&$v<=500)?$v:null;
}
function invoicingEnabled(array $app): bool { return featureEnabled($app,'feature_invoicing'); }
function mechanicsEnabled(array $app): bool { return featureEnabled($app,'feature_mechanics'); }
function customersEnabled(array $app): bool { return featureEnabled($app,'feature_customers'); }
function timeTrackingEnabled(array $app): bool { return featureEnabled($app,'feature_time_tracking'); }
function inventoryEnabled(array $app): bool { return featureEnabled($app,'feature_inventory'); }

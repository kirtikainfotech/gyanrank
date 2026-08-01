<?php

function institution_type_options(): array
{
    return [
        'school' => 'School / College',
        'degree_college' => 'Degree College',
        'institute' => 'Institute / Coaching Center',
    ];
}

function institution_db_exec(string $sql): void
{
    try {
        db()->query($sql);
    } catch (Throwable $e) {
        // Keep public pages usable even if one optional migration is unavailable.
    }
}

function institution_ensure_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_states (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(160) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_states_name_unique (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_boards (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(220) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_boards_name_unique (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_districts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        state_id INT UNSIGNED NOT NULL,
        name VARCHAR(160) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_districts_state_name_unique (state_id, name),
        KEY institution_districts_state_index (state_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_universities (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        state_id INT UNSIGNED NOT NULL,
        name VARCHAR(220) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY institution_universities_state_name_unique (state_id, name),
        KEY institution_universities_state_index (state_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_registration_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        institution_type ENUM('school','degree_college','institute') NOT NULL DEFAULT 'school',
        contact_name VARCHAR(160) NOT NULL,
        email VARCHAR(180) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        institution_name VARCHAR(220) NOT NULL,
        state_id INT UNSIGNED NOT NULL,
        state_name VARCHAR(160) NOT NULL,
        district_id INT UNSIGNED NOT NULL,
        district_name VARCHAR(160) NOT NULL,
        board_id INT UNSIGNED NULL,
        board_name VARCHAR(220) NULL,
        university_id INT UNSIGNED NULL,
        university_name VARCHAR(220) NULL,
        address VARCHAR(255) NOT NULL,
        pincode VARCHAR(10) NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_note VARCHAR(255) NULL,
        reviewed_by INT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY institution_requests_status_index (status),
        KEY institution_requests_type_index (institution_type),
        KEY institution_requests_mobile_index (mobile)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_db_exec("CREATE TABLE IF NOT EXISTS institution_accounts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        request_id BIGINT UNSIGNED NULL,
        institution_type ENUM('school','degree_college','institute') NOT NULL DEFAULT 'school',
        institution_name VARCHAR(220) NOT NULL,
        contact_name VARCHAR(160) NOT NULL,
        email VARCHAR(180) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        status ENUM('active','blocked') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY institution_accounts_request_unique (request_id),
        UNIQUE KEY institution_accounts_email_unique (email),
        KEY institution_accounts_mobile_index (mobile)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    institution_seed_master_data();
}

function institution_seed_master_data(): void
{
    $row = db()->query('SELECT COUNT(*) AS total FROM institution_states')->fetch_assoc();
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $states = [
        'Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Andaman and Nicobar Islands','Chandigarh','Dadra and Nagar Haveli and Daman and Diu','Delhi','Jammu and Kashmir','Ladakh','Lakshadweep','Puducherry',
    ];
    $stmt = db()->prepare('INSERT IGNORE INTO institution_states (name, status) VALUES (?, 1)');
    foreach ($states as $name) {
        $stmt->bind_param('s', $name);
        $stmt->execute();
    }

    $boards = [
        'CBSE','CISCE (ICSE/ISC)','NIOS','IB','Cambridge International','Andhra Pradesh Board of Secondary Education','Andhra Pradesh Board of Intermediate Education','Arunachal Pradesh State School Education Board','Assam State School Education Board','Bihar School Examination Board','Chhattisgarh Board of Secondary Education','Goa Board of Secondary and Higher Secondary Education','Gujarat Secondary and Higher Secondary Education Board','Board of School Education Haryana','Himachal Pradesh Board of School Education','Jharkhand Academic Council','Karnataka School Examination and Assessment Board','Kerala Board of Public Examinations','Madhya Pradesh Board of Secondary Education','Maharashtra State Board of Secondary and Higher Secondary Education','Manipur Board of Secondary Education','Council of Higher Secondary Education Manipur','Meghalaya Board of School Education','Mizoram Board of School Education','Nagaland Board of School Education','Board of Secondary Education Odisha','Council of Higher Secondary Education Odisha','Punjab School Education Board','Board of Secondary Education Rajasthan','Rajasthan State Open School','Sikkim Board of Secondary Education','Directorate of Government Examinations Tamil Nadu','Board of Intermediate Education Telangana','Board of Secondary Education Telangana','Tripura Board of Secondary Education','Board of High School and Intermediate Education Uttar Pradesh','Uttarakhand Board of School Education','West Bengal Board of Secondary Education','West Bengal Council of Higher Secondary Education','Jammu and Kashmir Board of School Education','Council for the Indian School Certificate Examinations Affiliate','State Open School','Other',
    ];
    $stmt = db()->prepare('INSERT IGNORE INTO institution_boards (name, status) VALUES (?, 1)');
    foreach ($boards as $name) {
        $stmt->bind_param('s', $name);
        $stmt->execute();
    }

    institution_seed_universities();
    institution_seed_districts();
}

function institution_state_id(string $name): int
{
    $stmt = db()->prepare('SELECT id FROM institution_states WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row['id'] ?? 0);
}

function institution_seed_universities(): void
{
    $universitiesByState = [
        'Andhra Pradesh' => ['Andhra University', 'Sri Venkateswara University', 'Acharya Nagarjuna University'],
        'Assam' => ['Gauhati University', 'Dibrugarh University', 'Assam University'],
        'Bihar' => ['Patna University', 'Magadh University', 'Lalit Narayan Mithila University'],
        'Delhi' => ['University of Delhi', 'Jawaharlal Nehru University', 'Jamia Millia Islamia'],
        'Gujarat' => ['Gujarat University', 'The Maharaja Sayajirao University of Baroda', 'Sardar Patel University'],
        'Karnataka' => ['Bangalore University', 'University of Mysore', 'Karnatak University'],
        'Madhya Pradesh' => ['Devi Ahilya Vishwavidyalaya', 'Barkatullah University', 'Rani Durgavati Vishwavidyalaya'],
        'Maharashtra' => ['University of Mumbai', 'Savitribai Phule Pune University', 'Rashtrasant Tukadoji Maharaj Nagpur University'],
        'Punjab' => ['Panjab University', 'Guru Nanak Dev University', 'Punjabi University'],
        'Rajasthan' => ['University of Rajasthan', 'Jai Narain Vyas University', 'Mohanlal Sukhadia University'],
        'Tamil Nadu' => ['University of Madras', 'Madurai Kamaraj University', 'Bharathiar University'],
        'Telangana' => ['Osmania University', 'Kakatiya University', 'Jawaharlal Nehru Technological University Hyderabad'],
        'Uttar Pradesh' => ['University of Lucknow', 'Banaras Hindu University', 'Chhatrapati Shahu Ji Maharaj University'],
        'Uttarakhand' => ['Hemwati Nandan Bahuguna Garhwal University', 'Kumaun University', 'Doon University'],
        'West Bengal' => ['University of Calcutta', 'Jadavpur University', 'University of Burdwan'],
    ];
    $stmt = db()->prepare('INSERT IGNORE INTO institution_universities (state_id, name, status) VALUES (?, ?, 1)');
    foreach ($universitiesByState as $state => $names) {
        $stateId = institution_state_id($state);
        if ($stateId <= 0) {
            continue;
        }
        foreach ($names as $name) {
            $stmt->bind_param('is', $stateId, $name);
            $stmt->execute();
        }
    }
}

function institution_seed_districts(): void
{
    $districtsByState = [
        'Delhi' => ['Central Delhi','East Delhi','New Delhi','North Delhi','North East Delhi','North West Delhi','Shahdara','South Delhi','South East Delhi','South West Delhi','West Delhi'],
        'Uttar Pradesh' => ['Agra','Aligarh','Allahabad','Ambedkar Nagar','Amethi','Amroha','Auraiya','Azamgarh','Baghpat','Bahraich','Ballia','Balrampur','Banda','Barabanki','Bareilly','Basti','Bijnor','Budaun','Bulandshahr','Chandauli','Chitrakoot','Deoria','Etah','Etawah','Faizabad','Farrukhabad','Fatehpur','Firozabad','Gautam Buddha Nagar','Ghaziabad','Ghazipur','Gonda','Gorakhpur','Hamirpur','Hapur','Hardoi','Hathras','Jalaun','Jaunpur','Jhansi','Kannauj','Kanpur Dehat','Kanpur Nagar','Kasganj','Kaushambi','Kheri','Kushinagar','Lalitpur','Lucknow','Maharajganj','Mahoba','Mainpuri','Mathura','Mau','Meerut','Mirzapur','Moradabad','Muzaffarnagar','Pilibhit','Pratapgarh','Raebareli','Rampur','Saharanpur','Sambhal','Sant Kabir Nagar','Shahjahanpur','Shamli','Shravasti','Siddharthnagar','Sitapur','Sonbhadra','Sultanpur','Unnao','Varanasi'],
        'Madhya Pradesh' => ['Bhopal','Indore','Jabalpur','Gwalior','Ujjain','Sagar','Rewa','Satna','Chhindwara','Dhar','Ratlam','Shivpuri','Vidisha'],
        'Rajasthan' => ['Ajmer','Alwar','Bikaner','Jaipur','Jodhpur','Kota','Udaipur','Bharatpur','Sikar','Tonk'],
        'Bihar' => ['Araria','Arwal','Aurangabad','Banka','Begusarai','Bhagalpur','Bhojpur','Buxar','Darbhanga','Gaya','Katihar','Madhubani','Muzaffarpur','Nalanda','Patna','Purnia','Rohtas','Samastipur','Saran','Siwan'],
        'Maharashtra' => ['Mumbai City','Mumbai Suburban','Pune','Nagpur','Nashik','Thane','Aurangabad','Kolhapur','Solapur','Amravati'],
    ];
    $stmt = db()->prepare('INSERT IGNORE INTO institution_districts (state_id, name, status) VALUES (?, ?, 1)');
    foreach ($districtsByState as $state => $names) {
        $stateId = institution_state_id($state);
        if ($stateId <= 0) {
            continue;
        }
        foreach ($names as $name) {
            $stmt->bind_param('is', $stateId, $name);
            $stmt->execute();
        }
    }
}

function institution_rows(string $table, int $stateId = 0): array
{
    institution_ensure_tables();
    $allowed = ['institution_states', 'institution_boards', 'institution_districts', 'institution_universities'];
    if (!in_array($table, $allowed, true)) {
        return [];
    }
    if ($stateId > 0 && in_array($table, ['institution_districts', 'institution_universities'], true)) {
        $stmt = db()->prepare("SELECT id, name FROM {$table} WHERE state_id = ? AND status = 1 ORDER BY name ASC");
        $stmt->bind_param('i', $stateId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    if (in_array($table, ['institution_districts', 'institution_universities'], true)) {
        $result = db()->query("SELECT MIN(id) AS id, name FROM {$table} WHERE status = 1 GROUP BY name ORDER BY name ASC");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    $result = db()->query("SELECT id, name FROM {$table} WHERE status = 1 ORDER BY name ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

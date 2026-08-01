<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Gyanranksetup extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->ensure_template_tables();
    }

    public function index()
    {
        $this->session->set_userdata('top_menu', 'System Settings');
        $this->session->set_userdata('sub_menu', 'admin/gyanranksetup');

        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $this->handle_post();
            redirect('admin/gyanranksetup');
        }

        $template = $this->gyanrank_erp_template_type();
        $data = array(
            'template' => $template,
            'settings' => $this->template_settings(),
            'degree_programs' => $this->rows('gr_degree_programs', 'id ASC'),
            'degree_terms' => $this->rows('gr_degree_terms', 'program_id ASC, term_no ASC'),
            'degree_subjects' => $this->rows('gr_degree_subjects', 'program_id ASC, id ASC'),
            'degree_fees' => $this->rows('gr_degree_fee_structures', 'program_id ASC, id ASC'),
            'coaching_courses' => $this->rows('gr_coaching_courses', 'id ASC'),
            'coaching_batches' => $this->rows('gr_coaching_batches', 'course_id ASC, id ASC'),
            'coaching_fees' => $this->rows('gr_coaching_fee_plans', 'course_id ASC, id ASC'),
        );

        $this->load->view('layout/header');
        $this->load->view('admin/gyanranksetup/index', $data);
        $this->load->view('layout/footer');
    }

    private function handle_post()
    {
        $action = (string) $this->input->post('action');
        if ($action === 'add_degree_program') {
            $this->add_degree_program();
        } elseif ($action === 'add_degree_subject') {
            $this->add_degree_subject();
        } elseif ($action === 'add_degree_fee') {
            $this->add_degree_fee();
        } elseif ($action === 'add_coaching_course') {
            $this->add_coaching_course();
        } elseif ($action === 'add_coaching_batch') {
            $this->add_coaching_batch();
        } elseif ($action === 'add_coaching_fee') {
            $this->add_coaching_fee();
        } elseif ($action === 'sync_template_master') {
            $this->sync_template_master();
        }
    }

    private function add_degree_program()
    {
        $code = strtoupper(trim((string) $this->input->post('program_code')));
        $name = trim((string) $this->input->post('program_name'));
        $years = max(1, min(6, (int) $this->input->post('duration_years')));
        $pattern = (string) $this->input->post('academic_pattern') === 'semester' ? 'semester' : 'yearly';
        $terms = $pattern === 'semester' ? $years * 2 : $years;
        $feeMode = in_array((string) $this->input->post('fee_mode'), array('full_course', 'yearly', 'semester'), true) ? (string) $this->input->post('fee_mode') : ($pattern === 'semester' ? 'semester' : 'yearly');
        if ($code === '' || $name === '') {
            return;
        }
        $this->db->query(
            'INSERT IGNORE INTO gr_degree_programs (program_code, program_name, duration_years, academic_pattern, total_terms, fee_mode) VALUES (?, ?, ?, ?, ?, ?)',
            array($code, $name, $years, $pattern, $terms, $feeMode)
        );
        $programId = (int) $this->db->insert_id();
        if ($programId > 0) {
            $this->seed_terms($programId, $pattern, $terms);
        }
        $this->sync_degree_master_to_class_sections(false);
    }

    private function add_degree_subject()
    {
        $programId = (int) $this->input->post('program_id');
        $termId = (int) $this->input->post('term_id');
        $code = strtoupper(trim((string) $this->input->post('subject_code')));
        $name = trim((string) $this->input->post('subject_name'));
        $type = in_array((string) $this->input->post('subject_type'), array('core', 'elective', 'optional', 'practical', 'project'), true) ? (string) $this->input->post('subject_type') : 'core';
        if ($programId <= 0 || $name === '') {
            return;
        }
        $termId = $termId > 0 ? $termId : null;
        $this->db->query(
            'INSERT INTO gr_degree_subjects (program_id, term_id, subject_code, subject_name, subject_type) VALUES (?, ?, ?, ?, ?)',
            array($programId, $termId, $code, $name, $type)
        );
    }

    private function add_degree_fee()
    {
        $programId = (int) $this->input->post('program_id');
        $termId = (int) $this->input->post('term_id');
        $mode = in_array((string) $this->input->post('fee_mode'), array('full_course', 'yearly', 'semester'), true) ? (string) $this->input->post('fee_mode') : 'yearly';
        $title = trim((string) $this->input->post('fee_title'));
        $amount = max(0, (float) $this->input->post('amount'));
        if ($programId <= 0 || $title === '') {
            return;
        }
        $termId = $termId > 0 ? $termId : null;
        $this->db->query(
            'INSERT INTO gr_degree_fee_structures (program_id, term_id, fee_mode, fee_title, amount, due_days) VALUES (?, ?, ?, ?, ?, 30)',
            array($programId, $termId, $mode, $title, $amount)
        );
    }

    private function add_coaching_course()
    {
        $code = strtoupper(trim((string) $this->input->post('course_code')));
        $name = trim((string) $this->input->post('course_name'));
        $months = max(1, min(60, (int) $this->input->post('duration_months')));
        $mode = in_array((string) $this->input->post('fee_mode'), array('full_course', 'monthly', 'installment'), true) ? (string) $this->input->post('fee_mode') : 'full_course';
        $fee = max(0, (float) $this->input->post('course_fee'));
        if ($code === '' || $name === '') {
            return;
        }
        $this->db->query(
            'INSERT IGNORE INTO gr_coaching_courses (course_code, course_name, duration_months, fee_mode, course_fee) VALUES (?, ?, ?, ?, ?)',
            array($code, $name, $months, $mode, $fee)
        );
    }

    private function add_coaching_batch()
    {
        $courseId = (int) $this->input->post('course_id');
        $name = trim((string) $this->input->post('batch_name'));
        $start = trim((string) $this->input->post('start_date')) ?: null;
        $end = trim((string) $this->input->post('end_date')) ?: null;
        $timing = trim((string) $this->input->post('timing'));
        $capacity = (int) $this->input->post('capacity');
        $trainer = trim((string) $this->input->post('trainer_name'));
        if ($courseId <= 0 || $name === '') {
            return;
        }
        $this->db->query(
            'INSERT INTO gr_coaching_batches (course_id, batch_name, start_date, end_date, timing, capacity, trainer_name) VALUES (?, ?, ?, ?, ?, ?, ?)',
            array($courseId, $name, $start, $end, $timing, $capacity > 0 ? $capacity : null, $trainer)
        );
        $this->sync_coaching_master_to_class_sections(false);
    }

    private function add_coaching_fee()
    {
        $courseId = (int) $this->input->post('course_id');
        $title = trim((string) $this->input->post('fee_title'));
        $mode = in_array((string) $this->input->post('fee_mode'), array('full_course', 'monthly', 'installment'), true) ? (string) $this->input->post('fee_mode') : 'full_course';
        $count = max(1, (int) $this->input->post('installment_count'));
        $amount = max(0, (float) $this->input->post('amount'));
        if ($courseId <= 0 || $title === '') {
            return;
        }
        $this->db->query(
            'INSERT INTO gr_coaching_fee_plans (course_id, fee_title, fee_mode, installment_count, amount, due_days) VALUES (?, ?, ?, ?, ?, 30)',
            array($courseId, $title, $mode, $count, $amount)
        );
    }

    private function sync_template_master()
    {
        $template = $this->gyanrank_erp_template_type();
        if ($template === 'degree_college') {
            $this->sync_degree_master_to_class_sections();
        } elseif ($template === 'coaching') {
            $this->sync_coaching_master_to_class_sections();
        }
    }

    private function sync_degree_master_to_class_sections($flash = true)
    {
        $programs = $this->rows('gr_degree_programs', 'id ASC');
        $terms = $this->rows('gr_degree_terms', 'program_id ASC, term_no ASC');
        $termsByProgram = array();
        foreach ($terms as $term) {
            $termsByProgram[(int) $term['program_id']][] = $term;
        }

        foreach ($programs as $program) {
            $className = trim((string) $program['program_code'] . ' - ' . (string) $program['program_name']);
            $classId = $this->ensure_class($className);
            foreach ($termsByProgram[(int) $program['id']] ?? array() as $term) {
                $sectionId = $this->ensure_section((string) $term['term_name']);
                $this->ensure_class_section($classId, $sectionId);
            }
        }
        if ($flash) {
            $this->session->set_flashdata('msg', '<div class="alert alert-success">Degree programs synced to admission master.</div>');
        }
    }

    private function sync_coaching_master_to_class_sections($flash = true)
    {
        $courses = $this->rows('gr_coaching_courses', 'id ASC');
        $batches = $this->rows('gr_coaching_batches', 'course_id ASC, id ASC');
        $batchesByCourse = array();
        foreach ($batches as $batch) {
            $batchesByCourse[(int) $batch['course_id']][] = $batch;
        }

        foreach ($courses as $course) {
            $className = trim((string) $course['course_code'] . ' - ' . (string) $course['course_name']);
            $classId = $this->ensure_class($className);
            foreach ($batchesByCourse[(int) $course['id']] ?? array() as $batch) {
                $sectionId = $this->ensure_section((string) $batch['batch_name']);
                $this->ensure_class_section($classId, $sectionId);
            }
        }
        if ($flash) {
            $this->session->set_flashdata('msg', '<div class="alert alert-success">Coaching courses and batches synced to admission master.</div>');
        }
    }

    private function ensure_class($className)
    {
        $className = trim($className);
        $row = $this->db->get_where('classes', array('class' => $className), 1)->row_array();
        if ($row) {
            return (int) $row['id'];
        }
        $this->db->insert('classes', array('class' => $className, 'is_active' => 'yes'));
        return (int) $this->db->insert_id();
    }

    private function ensure_section($sectionName)
    {
        $sectionName = trim($sectionName);
        $row = $this->db->get_where('sections', array('section' => $sectionName), 1)->row_array();
        if ($row) {
            return (int) $row['id'];
        }
        $this->db->insert('sections', array('section' => $sectionName, 'is_active' => 'yes'));
        return (int) $this->db->insert_id();
    }

    private function ensure_class_section($classId, $sectionId)
    {
        $exists = $this->db->get_where('class_sections', array('class_id' => $classId, 'section_id' => $sectionId), 1)->row_array();
        if (!$exists) {
            $this->db->insert('class_sections', array('class_id' => $classId, 'section_id' => $sectionId));
        }
    }

    private function seed_terms($programId, $pattern, $terms)
    {
        for ($termNo = 1; $termNo <= $terms; $termNo++) {
            $termType = $pattern === 'semester' ? 'semester' : 'year';
            $termName = $pattern === 'semester' ? 'Semester ' . $termNo : $termNo . ($termNo === 1 ? 'st' : ($termNo === 2 ? 'nd' : 'rd')) . ' Year';
            $this->db->query(
                'INSERT IGNORE INTO gr_degree_terms (program_id, term_no, term_name, term_type, sort_order) VALUES (?, ?, ?, ?, ?)',
                array($programId, $termNo, $termName, $termType, $termNo)
            );
        }
    }

    private function rows($table, $order)
    {
        if (!$this->table_exists($table)) {
            return array();
        }
        return $this->db->query('SELECT * FROM `' . $table . '` ORDER BY ' . $order)->result_array();
    }

    private function template_settings()
    {
        $row = $this->db->query('SELECT * FROM gr_erp_template_settings ORDER BY id DESC LIMIT 1')->row_array();
        return $row ?: array('template_type' => 'school', 'label_class' => 'Class', 'label_section' => 'Section', 'label_session' => 'Session');
    }

    private function table_exists($table)
    {
        return $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table))->num_rows() > 0;
    }

    private function ensure_template_tables()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS gr_erp_template_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_type ENUM('school','degree_college','coaching') NOT NULL DEFAULT 'school',
            label_class VARCHAR(80) NOT NULL DEFAULT 'Class',
            label_section VARCHAR(80) NOT NULL DEFAULT 'Section',
            label_session VARCHAR(80) NOT NULL DEFAULT 'Session',
            academic_pattern ENUM('school','yearly','semester','duration_batch') NOT NULL DEFAULT 'school',
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
        $settingsCount = $this->db->query('SELECT COUNT(*) AS total FROM gr_erp_template_settings')->row();
        if ((int) ($settingsCount->total ?? 0) === 0) {
            $template = $this->gyanrank_erp_template_type();
            $this->db->query(
                'INSERT INTO gr_erp_template_settings (template_type, label_class, label_section, label_session, academic_pattern, notes) VALUES (?, ?, ?, ?, ?, ?)',
                $template === 'degree_college'
                    ? array('degree_college', 'Program / Year', 'Subject Group / Semester', 'Academic Year', 'yearly', 'Degree college template enabled.')
                    : ($template === 'coaching'
                        ? array('coaching', 'Course', 'Batch', 'Training Session', 'duration_batch', 'Coaching template enabled.')
                        : array('school', 'Class', 'Section', 'Session', 'school', 'School template enabled.'))
            );
        }
        $this->ensure_degree_tables();
        $this->ensure_coaching_tables();
    }

    private function ensure_degree_tables()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS gr_degree_programs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_code VARCHAR(40) NOT NULL,
            program_name VARCHAR(160) NOT NULL,
            duration_years TINYINT UNSIGNED NOT NULL DEFAULT 3,
            academic_pattern ENUM('yearly','semester') NOT NULL DEFAULT 'yearly',
            total_terms TINYINT UNSIGNED NOT NULL DEFAULT 3,
            fee_mode ENUM('full_course','yearly','semester') NOT NULL DEFAULT 'yearly',
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY gr_degree_programs_code_unique (program_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS gr_degree_terms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_id INT UNSIGNED NOT NULL,
            term_no TINYINT UNSIGNED NOT NULL,
            term_name VARCHAR(80) NOT NULL,
            term_type ENUM('year','semester') NOT NULL DEFAULT 'year',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY gr_degree_terms_program_term_unique (program_id, term_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS gr_degree_subjects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_id INT UNSIGNED NOT NULL,
            term_id INT UNSIGNED NULL,
            subject_code VARCHAR(40) NULL,
            subject_name VARCHAR(160) NOT NULL,
            subject_type ENUM('core','elective','optional','practical','project') NOT NULL DEFAULT 'core',
            status TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS gr_degree_fee_structures (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_id INT UNSIGNED NOT NULL,
            term_id INT UNSIGNED NULL,
            fee_mode ENUM('full_course','yearly','semester') NOT NULL DEFAULT 'yearly',
            fee_title VARCHAR(160) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            due_days INT UNSIGNED NOT NULL DEFAULT 30,
            status TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    }

    private function ensure_coaching_tables()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS gr_coaching_courses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_code VARCHAR(40) NOT NULL,
            course_name VARCHAR(160) NOT NULL,
            duration_months INT UNSIGNED NOT NULL DEFAULT 3,
            fee_mode ENUM('full_course','monthly','installment') NOT NULL DEFAULT 'full_course',
            course_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY gr_coaching_courses_code_unique (course_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS gr_coaching_batches (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NOT NULL,
            batch_name VARCHAR(120) NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            timing VARCHAR(80) NULL,
            capacity INT UNSIGNED NULL,
            trainer_name VARCHAR(120) NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS gr_coaching_fee_plans (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NOT NULL,
            fee_title VARCHAR(160) NOT NULL,
            fee_mode ENUM('full_course','monthly','installment') NOT NULL DEFAULT 'full_course',
            installment_count INT UNSIGNED NOT NULL DEFAULT 1,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            due_days INT UNSIGNED NOT NULL DEFAULT 30,
            status TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    }
}

<?php

define('THEMES_DIR', 'themes');
define('BASE_URI', str_replace('index.php', '', $_SERVER['SCRIPT_NAME']));

class MY_Controller extends CI_Controller
{

    protected $langs = array();
    protected $GYANNEXA_plan_features = null;
    protected $GYANNEXA_erp_template_type = 'school';

    public function __construct()
    {

        parent::__construct();
        $this->enforce_GYANNEXA_subscription_gate();
        $this->config->load('license');
        $this->load->helper('language');
        $this->load->helper(array('directory', 'customfield', 'custom'));
        $this->load->model(array('setting_model', 'Module_model', 'customfield_model', 'onlinestudent_model', 'houselist_model', 'onlineexam_model', 'onlineexamquestion_model', 'onlineexamresult_model', 'examstudent_model', 'admitcard_model', 'marksheet_model', 'chatuser_model', 'examgroupstudent_model', 'examgroup_model', 'batchsubject_model', 'filetype_model', 'rolepermission_model'));
        $this->load->library('customlib');
        $this->load->library('auth');
        $this->load->library('module_lib');
        $this->load->library('pushnotification');
        $this->load->library('jsonlib');
        $this->refresh_GYANNEXA_branding_session();

        if ($this->session->has_userdata('admin')) {

            $admin    = $this->session->userdata('admin');
            $language = ($admin['language']['language']);
        } else if ($this->session->has_userdata('student')) {

            $student  = $this->session->userdata('student');
            $language = ($student['language']['language']);
        } else {
            $this->school_details = $this->setting_model->getSchoolDetail();
            $language             = ($this->school_details->language);
        }

        $this->config->set_item('language', $language);
        $lang_array = array('form_validation_lang');
        $map        = directory_map(APPPATH . "./language/" . $language . "/app_files");
        foreach ($map as $lang_key => $lang_value) {
            $lang_array[] = 'app_files/' . str_replace(".php", "", $lang_value);
        }

        $this->load->language($lang_array, $language);
    }

    private function refresh_GYANNEXA_branding_session()
    {
        if (!$this->session->has_userdata('admin') && !$this->session->has_userdata('student')) {
            return;
        }

        $settings = $this->setting_model->get();
        if (!isset($settings[0]) || !is_array($settings[0])) {
            return;
        }

        foreach (array('admin', 'student') as $sessionKey) {
            if (!$this->session->has_userdata($sessionKey)) {
                continue;
            }

            $session = $this->session->userdata($sessionKey);
            if (!is_array($session)) {
                continue;
            }

            $session['currency_symbol'] = $settings[0]['currency_symbol'];
            $session['currency_place'] = $settings[0]['currency_place'];
            $session['sch_name'] = $settings[0]['name'];
            if ($sessionKey === 'admin') {
                $session['school_name'] = $settings[0]['name'];
            }

            $this->session->set_userdata($sessionKey, $session);
        }
    }

    private function enforce_GYANNEXA_subscription_gate()
    {
        $loaded = $this->config->load('GYANNEXA', true, true);
        $account_id = $loaded ? (int) $this->config->item('GYANNEXA_institution_account_id', 'GYANNEXA') : 0;
        $parent_db  = $loaded ? $this->config->item('GYANNEXA_parent_db', 'GYANNEXA') : array();
        if ($account_id <= 0 && defined('GYANNEXA_INSTITUTION_ACCOUNT_ID')) {
            $account_id = (int) GYANNEXA_INSTITUTION_ACCOUNT_ID;
        }
        if ((!is_array($parent_db) || empty($parent_db)) && defined('GYANNEXA_PARENT_DB_NAME')) {
            $parent_db = array(
                'hostname' => defined('GYANNEXA_PARENT_DB_HOST') ? GYANNEXA_PARENT_DB_HOST : 'localhost',
                'username' => defined('GYANNEXA_PARENT_DB_USER') ? GYANNEXA_PARENT_DB_USER : 'root',
                'password' => defined('GYANNEXA_PARENT_DB_PASS') ? GYANNEXA_PARENT_DB_PASS : '',
                'database' => GYANNEXA_PARENT_DB_NAME,
            );
        }
        if ($account_id <= 0 || !is_array($parent_db)) {
            return;
        }

        if ($this->GYANNEXA_is_auth_route()) {
            return;
        }

        $hasActivePlan = $this->GYANNEXA_has_active_erp_plan($parent_db, $account_id);
        if ($this->GYANNEXA_is_reporting_route()) {
            return;
        }

        $method = strtoupper((string) $this->input->method(true));
        if (!in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true)) {
            return;
        }

        if ($hasActivePlan) {
            return;
        }

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_content_type('application/json')
                ->set_status_header(403)
                ->set_output(json_encode(array(
                    'status'  => 0,
                    'error'   => 'plan_expired',
                    'message' => 'ERP plan expired. New entries are locked; reporting remains available.',
                )));
            $this->output->_display();
            exit;
        }

        show_error('ERP plan expired. New entries are locked; reporting remains available. Please renew your Gyan Nexa ERP plan.', 403, 'ERP Locked');
    }

    private function GYANNEXA_is_reporting_route()
    {
        $class  = strtolower((string) $this->router->fetch_class());
        $method = strtolower((string) $this->router->fetch_method());
        $uri    = strtolower((string) uri_string());
        $allow  = array('report', 'reports', 'dashboard', 'print', 'pdf');

        foreach ($allow as $token) {
            if (strpos($class, $token) !== false || strpos($method, $token) !== false || strpos($uri, $token) !== false) {
                return true;
            }
        }

        return false;
    }

    private function GYANNEXA_is_auth_route()
    {
        $class  = strtolower((string) $this->router->fetch_class());
        $method = strtolower((string) $this->router->fetch_method());
        $allowed = array('login', 'userlogin', 'logout', 'forgotpassword', 'ufpassword', 'resetpassword', 'admin_resetpassword', 'check_captcha', 'refreshcaptcha');

        return $class === 'site' && in_array($method, $allowed, true);
    }

    private function GYANNEXA_has_active_erp_plan($parent_db, $account_id)
    {
        $mysqli = @new mysqli(
            (string) ($parent_db['hostname'] ?? ''),
            (string) ($parent_db['username'] ?? ''),
            (string) ($parent_db['password'] ?? ''),
            (string) ($parent_db['database'] ?? '')
        );

        if ($mysqli->connect_errno) {
            return false;
        }

        $mysqli->set_charset('utf8mb4');
        $stmt = $mysqli->prepare("SELECT s.status, s.expires_at, t.erp_status, t.erp_template_type, p.features_json
            FROM institution_erp_subscriptions s
            LEFT JOIN institution_erp_tenants t ON t.id = s.tenant_id
            LEFT JOIN institution_erp_plans p ON p.id = s.plan_id
            WHERE s.institution_account_id = ?
            ORDER BY s.expires_at DESC, s.id DESC
            LIMIT 1");
        if (!$stmt) {
            $mysqli->close();
            return false;
        }

        $stmt->bind_param('i', $account_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $mysqli->close();

        if (!$row) {
            return false;
        }

        $this->GYANNEXA_plan_features = $this->GYANNEXA_decode_plan_features((string) ($row['features_json'] ?? '[]'));
        $this->GYANNEXA_erp_template_type = in_array((string) ($row['erp_template_type'] ?? ''), array('school', 'degree_college', 'coaching'), true)
            ? (string) $row['erp_template_type']
            : (defined('GYANNEXA_ERP_TEMPLATE_TYPE') ? (string) GYANNEXA_ERP_TEMPLATE_TYPE : 'school');

        return in_array((string) $row['status'], array('trial', 'active'), true)
            && strtotime((string) $row['expires_at']) >= strtotime(date('Y-m-d'))
            && (string) $row['erp_status'] === 'active';
    }

    protected function GYANNEXA_erp_template_type()
    {
        if (defined('GYANNEXA_ERP_TEMPLATE_TYPE') && in_array((string) GYANNEXA_ERP_TEMPLATE_TYPE, array('school', 'degree_college', 'coaching'), true)) {
            return (string) GYANNEXA_ERP_TEMPLATE_TYPE;
        }
        return $this->GYANNEXA_erp_template_type ?: 'school';
    }

    public function GYANNEXA_erp_label($key)
    {
        $template = $this->GYANNEXA_erp_template_type();
        $labels = array(
            'school' => array(
                'class' => $this->lang->line('class'),
                'section' => $this->lang->line('section'),
                'class_section' => $this->lang->line('class_section'),
                'student' => $this->lang->line('student'),
                'student_list' => $this->lang->line('student') . ' ' . $this->lang->line('list'),
                'fees_group' => $this->lang->line('fees_group'),
            ),
            'degree_college' => array(
                'class' => 'Program / Year',
                'section' => 'Semester / Group',
                'class_section' => 'Program / Semester',
                'student' => $this->lang->line('student'),
                'student_list' => $this->lang->line('student') . ' ' . $this->lang->line('list'),
                'fees_group' => 'Program Fee Group',
            ),
            'coaching' => array(
                'class' => 'Course',
                'section' => 'Batch',
                'class_section' => 'Course / Batch',
                'student' => 'Learner',
                'student_list' => 'Learner List',
                'fees_group' => 'Course Fee Group',
            ),
        );

        return $labels[$template][$key] ?? $labels['school'][$key] ?? (string) $key;
    }

    public function GYANNEXA_has_plan_feature($feature)
    {
        if ($this->GYANNEXA_plan_features === null) {
            return true;
        }
        return in_array('all_modules', $this->GYANNEXA_plan_features, true) || in_array((string) $feature, $this->GYANNEXA_plan_features, true);
    }

    private function GYANNEXA_decode_plan_features($features_json)
    {
        $features = json_decode((string) $features_json, true);
        if (!is_array($features)) {
            return array();
        }

        $clean = array();
        foreach ($features as $feature) {
            $feature = trim((string) $feature);
            if ($feature !== '' && !in_array($feature, $clean, true)) {
                $clean[] = $feature;
            }
        }

        return $clean;
    }

    protected function enforce_GYANNEXA_feature_gate()
    {
        if ($this->GYANNEXA_plan_features === null) {
            return;
        }

        $feature = $this->GYANNEXA_route_feature();
        if ($feature === '' || $this->GYANNEXA_has_plan_feature($feature)) {
            return;
        }

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_content_type('application/json')
                ->set_status_header(403)
                ->set_output(json_encode(array(
                    'status' => 0,
                    'error' => 'feature_locked',
                    'message' => 'This ERP module is not included in the active GYANNEXA plan.',
                )));
            $this->output->_display();
            exit;
        }

        show_error('This ERP module is not included in the active GYANNEXA plan. Please upgrade the GYANNEXA ERP plan to use it.', 403, 'Module Locked');
    }

    private function GYANNEXA_route_feature()
    {
        $class = strtolower((string) $this->router->fetch_class());
        $uri = strtolower((string) uri_string());
        if ($class === '' || $class === 'admin' || $class === 'GYANNEXAsetup' || strpos($uri, 'report') !== false) {
            return '';
        }

        $map = array(
            'student' => 'student',
            'onlinestudent' => 'admission',
            'category' => 'student',
            'schoolhouse' => 'student',
            'disable_reason' => 'student',
            'studentfee' => 'fees',
            'feemaster' => 'fees',
            'feegroup' => 'fees',
            'feetype' => 'fees',
            'feediscount' => 'fees',
            'income' => 'income',
            'incomehead' => 'income',
            'expense' => 'expenses',
            'expensehead' => 'expenses',
            'stuattendence' => 'attendance',
            'exam' => 'exams',
            'examgroup' => 'exams',
            'examschedule' => 'exams',
            'examresult' => 'exams',
            'onlineexam' => 'online_exam',
            'onlineexamquestion' => 'online_exam',
            'lessonplan' => 'lesson_plan',
            'syllabus' => 'lesson_plan',
            'homework' => 'homework',
            'timetable' => 'academics',
            'subject' => 'academics',
            'subjectgroup' => 'academics',
            'classes' => 'academics',
            'sections' => 'academics',
            'mailsms' => 'communicate',
            'notification' => 'communicate',
            'content' => 'download_center',
            'book' => 'library',
            'librarian' => 'library',
            'librarymember' => 'library',
            'bookissue' => 'library',
            'route' => 'transport',
            'vehicle' => 'transport',
            'vehroute' => 'transport',
            'hostel' => 'hostel',
            'hostelroom' => 'hostel',
            'roomtype' => 'hostel',
            'certificate' => 'certificate',
            'generatecertificate' => 'certificate',
            'student_id_card' => 'certificate',
            'staff' => 'human_resource',
            'payroll' => 'human_resource',
            'department' => 'human_resource',
            'designation' => 'human_resource',
            'item' => 'inventory',
            'itemstock' => 'inventory',
            'itemstore' => 'inventory',
            'itemsupplier' => 'inventory',
            'itemcategory' => 'inventory',
            'issueitem' => 'inventory',
            'alumni' => 'alumni',
        );

        return $map[$class] ?? '';
    }

}

class Admin_Controller extends MY_Controller
{

    protected $aaaa = false;

    public function __construct()
    {
        parent::__construct();
        $this->auth->is_logged_in();
        $this->check_license();
        $this->load->library('rbac');
        $this->config->load('app-config');
        $this->load->model(array('batchsubject_model', 'examgroup_model', 'examsubject_model', 'examgroupstudent_model', 'feereminder_model', 'filetype_model', 'rolepermission_model'));
        $this->enforce_GYANNEXA_feature_gate();

        $this->config->load('ci-blog');
        $this->config->load('custom_filed-config');
    }

    public function check_license()
    {

        $license = $this->config->item('SSLK');

        if (!empty($license)) {

            $regex = "/^[A-Z0-9]{6}-[A-Z0-9]{6}-[A-Z0-9]{6}-/";

            if (preg_match($regex, $license)) {
                $valid_string = $this->aes->validchk('encrypt', base_url());

                if (strpos($license, $valid_string) !== false) {

                    true; //valid
                } else {
                    $this->update_ss_routine();
                }
            } else {

                $this->update_ss_routine();
            }
        }
    }

    public function update_ss_routine()
    {

        $license       = $this->config->item('SSLK');
        $fname         = APPPATH . 'config/license.php';
        $update_handle = fopen($fname, "r");
        $content       = fread($update_handle, filesize($fname));
        $file_contents = str_replace('$config[\'SSLK\'] = \'' . $license . '\'', '$config[\'SSLK\'] = \'\'', $content);
        $update_handle = fopen($fname, 'w') or die("can't open file");
        if (fwrite($update_handle, $file_contents)) {

        }
        fclose($update_handle);

        $this->config->set_item('SSLK', '');
    }

}

class Student_Controller extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->library('studentmodule_lib');
        $this->config->load('app-config');
        $this->auth->is_logged_in_user('student');
    }

}

class Public_Controller extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

}

class Parent_Controller extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->auth->is_logged_in_user('parent');
        $this->config->load('app-config');
        $this->load->library('parentmodule_lib');
    }

}

class Front_Controller extends CI_Controller
{

    protected $data           = array();
    protected $school_details = array();
    protected $parent_menu    = '';
    protected $page_title     = '';
    protected $theme_path     = '';
    protected $front_setting  = '';

    public function __construct()
    {

        parent::__construct();

        $this->check_installation();

        if ($this->config->item('installed') == true) {

            $this->db->reconnect();
        }
        $this->load->helper('language');
        $this->load->model(array('setting_model', 'cms_menu_model', 'cms_menuitems_model'));
        $this->load->library('captchalib');
        $this->school_details = $this->setting_model->getSchoolDetail();

        $this->load->model('frontcms_setting_model');

        $this->front_setting = $this->frontcms_setting_model->get();

        if (!$this->front_setting) {

            redirect('site/userlogin');
        } else {
            $front_cms_class  = $this->router->fetch_class();
            $front_cms_method = $this->router->fetch_method();
            if ($this->front_setting->is_active_front_cms) {
                $this->config->set_item('front_layout', true);
            }
            if (!$this->front_setting->is_active_front_cms) {
                $this->config->set_item('front_layout', false);
            }

            if (!$this->front_setting->is_active_front_cms && !($front_cms_class == "welcome" && $front_cms_method == "admission")) {

                redirect('site/userlogin');
            }
        }

        $this->theme_path = $this->front_setting->theme;
//================
        $language = ($this->school_details->language);
        $this->load->helper('directory');
        $lang_array = array('form_validation_lang');
        $map        = directory_map(APPPATH . "./language/" . $language . "/app_files");
        foreach ($map as $lang_key => $lang_value) {
            $lang_array[] = 'app_files/' . str_replace(".php", "", $lang_value);
        }

        $this->load->language($lang_array, $language);
//===============

        $this->load->config('ci-blog');
    }

    protected function load_theme($content = null, $layout = true)
    {

        $this->data['main_menus']     = '';
        $this->data['school_setting'] = $this->school_details;
        $this->data['front_setting']  = $this->front_setting;
        $menu_list                    = $this->cms_menu_model->getBySlug('main-menu');

        $footer_menu_list = $this->cms_menu_model->getBySlug('bottom-menu');
        if (count($menu_list) > 0) {
            $this->data['main_menus'] = $this->cms_menuitems_model->getMenus($menu_list['id']);
        }

        if (count($footer_menu_list) > 0) {
            $this->data['footer_menus'] = $this->cms_menuitems_model->getMenus($footer_menu_list['id']);
        }
        $this->data['layout_type'] = $layout;
        $this->data['header']      = $this->load->view('themes/' . $this->theme_path . '/header', $this->data, true);

        $this->data['slider'] = $this->load->view('themes/' . $this->theme_path . '/home_slider', $this->data, true);

        $this->data['footer'] = $this->load->view('themes/' . $this->theme_path . '/footer', $this->data, true);

        $this->base_assets_url = 'backend/' . THEMES_DIR . '/' . $this->theme_path . '/';

        $this->data['base_assets_url'] = BASE_URI . $this->base_assets_url;
        $is_captcha                    = $this->captchalib->is_captcha('admission');
        $this->data["is_captcha"]      = $is_captcha;

        if ($layout == true) {
            $this->data['content'] = (is_null($content)) ? '' : $this->load->view(THEMES_DIR . '/' . $this->theme_path . '/' . $content, $this->data, true);
            $this->load->view(THEMES_DIR . '/' . $this->theme_path . '/layout', $this->data);
        } else {
            $this->data['content'] = (is_null($content)) ? '' : $this->load->view(THEMES_DIR . '/' . $this->theme_path . '/' . $content, $this->data, true);
            $this->load->view(THEMES_DIR . '/' . $this->theme_path . '/base_layout', $this->data);
        }
    }

    protected function load_theme_form($content = null, $layout = true)
    {

        $this->data['main_menus']     = '';
        $this->data['school_setting'] = $this->school_details;
        $this->data['front_setting']  = $this->front_setting;
        $menu_list                    = $this->cms_menu_model->getBySlug('main-menu');
        $footer_menu_list             = $this->cms_menu_model->getBySlug('bottom-menu');
        if (count($menu_list > 0)) {
            $this->data['main_menus'] = $this->cms_menuitems_model->getMenus($menu_list['id']);
        }

        if (count($footer_menu_list > 0)) {
            $this->data['footer_menus'] = $this->cms_menuitems_model->getMenus($footer_menu_list['id']);
        }
        $this->data['header'] = $this->load->view('themes/' . $this->theme_path . '/header', $this->data, true);

        $this->data['slider'] = $this->load->view('themes/' . $this->theme_path . '/home_slider', $this->data, true);

        $this->data['footer'] = $this->load->view('themes/' . $this->theme_path . '/footer', $this->data, true);

        $this->base_assets_url = 'backend/' . THEMES_DIR . '/' . $this->theme_path . '/';

        $this->data['base_assets_url'] = BASE_URI . $this->base_assets_url;

        $this->data['content'] = (is_null($content)) ? '' : $this->load->view(THEMES_DIR . '/' . $this->theme_path . '/' . $content, $this->data, true);
        $this->load->view(THEMES_DIR . '/' . $this->theme_path . '/layout', $this->data);

    }

    private function check_installation()
    {

        if ($this->uri->segment(1) !== 'install') {
            $this->load->config('migration');
            if ($this->config->item('installed') == false && $this->config->item('migration_enabled') == false) {
                redirect(base_url() . 'install/start');
            } else {
                if (!defined('GYANNEXA_TENANT_CODE') && is_dir(APPPATH . 'controllers/install')) {
                    echo '<h3>Delete the install folder from application/controllers/install</h3>';
                    die;
                }
            }
        }
    }

}

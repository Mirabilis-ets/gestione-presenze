<?php
/*
Plugin Name: Gestione Presenze
Version: 3.8.3
Author: Mirabilis
*/
defined('ABSPATH') || exit;

function gp_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    // Aggiungi questa funzione
    function gp_activate_plugin() {
        gp_create_tables();
        gp_add_expert_capabilities();
        gp_sync_existing_experts(); // Aggiungi questa linea
    }
    register_activation_hook(__FILE__, 'gp_activate_plugin');

    // Tabella Studenti (aggiunto 'school')
    $students_table = $wpdb->prefix . 'gp_students';
    $sql_students = "CREATE TABLE IF NOT EXISTS $students_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        surname VARCHAR(255) NOT NULL,
        project_code VARCHAR(255) NOT NULL,
        class VARCHAR(50) NOT NULL,
        expert VARCHAR(255) NOT NULL,
        school VARCHAR(255) NOT NULL DEFAULT 'Scuola Default',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_expert (expert),
        KEY idx_project_code (project_code),
        KEY idx_school (school)
    ) $charset;";

    // Tabella Esperti (aggiunto 'hourly_rate')
    $experts_table = $wpdb->prefix . 'gp_experts';
    $sql_experts = "CREATE TABLE IF NOT EXISTS $experts_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        surname VARCHAR(255) NOT NULL,
        username VARCHAR(255) NOT NULL UNIQUE,
        hourly_rate DECIMAL(5,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset;";

    // Tabella Lezioni
    $lessons_table = $wpdb->prefix . 'gp_lessons';
    $sql_lessons = "CREATE TABLE IF NOT EXISTS $lessons_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id BIGINT(20) UNSIGNED NOT NULL,
        lesson_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES $students_table(id) ON DELETE CASCADE,
        KEY idx_student_id (student_id),
        KEY idx_lesson_date (lesson_date)
    ) $charset;";
    // Tabella Scuola
    $schools_table = $wpdb->prefix . 'gp_schools';
    $sql_schools = "CREATE TABLE IF NOT EXISTS $schools_table (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_students);
    dbDelta($sql_experts);
    dbDelta($sql_lessons);
    dbDelta($sql_schools);
}
register_activation_hook(__FILE__, 'gp_create_tables');


// Registrazione ruolo Esperto
function gp_register_roles() {
    add_role('esperto', 'Esperto', [
        'read' => true,
        'edit_students' => true,
        'delete_students' => true,
        'manage_lessons' => true,
    ]);
}
add_action('init', 'gp_register_roles');


// Enqueue scripts/stili
function gp_enqueue_scripts() {
    $dashboard_page = get_page_by_title('Dashboard Esperto');
    $gestione_experti_page = get_page_by_title('Gestione Esperti');
    
    if (!$dashboard_page || !$gestione_experti_page) return;

    if (is_page([$dashboard_page->ID, $gestione_experti_page->ID])) {
        wp_enqueue_style('gp-styles', plugins_url('/assets/css/styles.css', __FILE__), [], '3.8.3');
        wp_enqueue_script('jquery');
        wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', [], null, true);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('gp-scripts', plugins_url('/assets/js/main.js', __FILE__), ['jquery', 'chart-js'], '3.8.3', true);

        
        if (current_user_can('administrator')) {
            wp_enqueue_script('gp-experts-scripts', plugins_url('/assets/js/main-experts.js', __FILE__), ['jquery', 'chart-js'], '3.8.3', true);
        }

        wp_localize_script('gp-scripts', 'gpData', [
            'nonce' => wp_create_nonce('wp_rest'),
            'apiUrl' => rest_url('gp/v1/'),
            'dashboardUrl' => get_permalink($dashboard_page),
            'currentUser' => [
                'username' => wp_get_current_user()->user_login,
                'isAdmin' => current_user_can('administrator'),
            ],
        ]);
    }
}
add_action('wp_enqueue_scripts', 'gp_enqueue_scripts');

// Registrazione endpoint REST
require_once(plugin_dir_path(__FILE__) . 'includes/api-callbacks.php');
require_once(plugin_dir_path(__FILE__) . 'includes/experts-callbacks.php');
function gp_register_rest_endpoints() {
    // Studenti
    register_rest_route('gp/v1', '/students', [
        'methods' => 'GET',
        'callback' => 'gp_get_students',
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);
    register_rest_route('gp/v1', '/students', [
        'methods' => 'POST',
        'callback' => 'gp_add_student',
        'permission_callback' => function() { return current_user_can('esperto'); },
    ]);
    register_rest_route('gp/v1', '/students/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'gp_get_student',
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);
    register_rest_route('gp/v1', '/students/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'gp_update_student',
        'permission_callback' => function() {
            return current_user_can('administrator') || current_user_can('esperto');
        },
    ]);
    register_rest_route('gp/v1', '/students/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'gp_delete_student',
        'permission_callback' => function() { return current_user_can('esperto'); },
    ]);

    // Lezioni
    register_rest_route('gp/v1', '/lessons', [
        'methods' => 'POST',
        'callback' => 'gp_add_lesson',
        'permission_callback' => function() { return current_user_can('esperto'); },
    ]);
    register_rest_route('gp/v1', '/lessons', [
        'methods' => 'GET',
        'callback' => 'gp_get_lessons',
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);
    register_rest_route('gp/v1', '/lessons/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'gp_get_lesson',
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);
    register_rest_route('gp/v1', '/lessons/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'gp_update_lesson',
        'permission_callback' => function() { return current_user_can('esperto'); },
    ]);
    register_rest_route('gp/v1', '/lessons/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'gp_delete_lesson',
        'permission_callback' => function() { return current_user_can('esperto'); },
    ]);

    // Esperti (Solo Admin)
    register_rest_route('gp/v1', '/experts/me', [
        'methods' => 'GET',
        'callback' => 'gp_get_current_expert',
        'permission_callback' => function() { 
            return is_user_logged_in() && 
                   (current_user_can('esperto') || current_user_can('administrator'));
        },
    ]);
    register_rest_route('gp/v1', '/experts', [
        'methods' => 'GET',
        'callback' => 'gp_get_experts',
        'permission_callback' => function() { 
            return current_user_can('administrator'); // Solo admin
        },
    ]);
    register_rest_route('gp/v1', '/experts', [
        'methods' => 'POST',
        'callback' => 'gp_add_expert',
        'permission_callback' => function() { return current_user_can('administrator'); },
    ]);
    register_rest_route('gp/v1', '/experts/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'gp_get_expert',
        'permission_callback' => function() { return current_user_can('administrator'); },
    ]);
    register_rest_route('gp/v1', '/experts/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'gp_update_expert',
        'permission_callback' => function() { return current_user_can('administrator'); },
    ]);
    register_rest_route('gp/v1', '/experts/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'gp_delete_expert',
        'permission_callback' => function() { return current_user_can('administrator'); },
    ]);

    // Report attività (Solo Admin)
    register_rest_route('gp/v1', '/activity-report', [
        'methods' => 'GET',
        'callback' => 'gp_get_activity_report',
        'permission_callback' => function() { 
            return is_user_logged_in(); 
        },
    ]);
// Registrazione endpoint
 
register_rest_route('gp/v1', '/schools', [
    'methods' => 'GET',
    'callback' => 'gp_get_schools',
    'permission_callback' => '__return_true',
]);
register_rest_route('gp/v1', '/schools', [
    'methods' => 'POST',
    'callback' => 'gp_add_school',
    'permission_callback' => function() { return current_user_can('administrator'); },
]);
register_rest_route('gp/v1', '/schools/(?P<id>\d+)', [
    'methods' => 'DELETE',
    'callback' => 'gp_delete_school',
    'permission_callback' => function() { return current_user_can('administrator'); },
]);
    
    // Esperti (username)
    register_rest_route('gp/v1', '/experts-username', [
        'methods' => 'GET',
        'callback' => 'gp_get_experts_for_filters',
        'permission_callback' => '__return_true',
    ]);
    
    // Codici Progetto
    register_rest_route('gp/v1', '/project-codes', [
        'methods' => 'GET',
        'callback' => 'gp_get_project_codes',
        'permission_callback' => '__return_true',
    ]);
    // Aggiungi questa funzione in gp-plugin.php
function gp_add_expert_capabilities() {
    $role = get_role('esperto');
    if ($role) {
        // Aggiungi permessi se mancano
        if (!$role->has_cap('edit_students')) {
            $role->add_cap('edit_students');
        }
        if (!$role->has_cap('delete_students')) {
            $role->add_cap('delete_students');
        }
        if (!$role->has_cap('manage_lessons')) {
            $role->add_cap('manage_lessons');
        }
    }
}
function gp_add_admin_capabilities() {
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('edit_students');
        $admin->add_cap('delete_students');
        $admin->add_cap('manage_lessons');
        $admin->add_cap('read_experts'); // Per /experts/me
    }
}
 add_action('admin_init', 'gp_add_expert_capabilities');
 
    // Autenticazione
    register_rest_route('gp/v1', '/auth/register', [
        'methods' => 'POST',
        'callback' => 'gp_register_expert',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('gp/v1', '/auth/login', [
        'methods' => 'POST',
        'callback' => 'gp_login_expert',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('gp/v1', '/auth/logout', [
        'methods' => 'POST',
        'callback' => function() {
            wp_logout();
            return rest_ensure_response(['message' => 'Logout effettuato']);
        },
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'gp_register_rest_endpoints');

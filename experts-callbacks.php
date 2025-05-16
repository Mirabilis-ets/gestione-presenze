<?php
// Funzione di registrazione esperto
function gp_register_expert($request) {
    global $wpdb;
    $data = $request->get_params();
    $name = sanitize_text_field($data['name'] ?? '');
    $surname = sanitize_text_field($data['surname'] ?? '');
    $username = sanitize_user($data['username'] ?? '');
    $password = esc_attr($data['password'] ?? '');

    if (empty($name) || empty($surname) || empty($username) || empty($password)) {
        return new WP_Error('invalid_data', 'Tutti i campi sono obbligatori', ['status' => 400]);
    }

    if (username_exists($username)) {
        return new WP_Error('username_exists', 'Username già in uso', ['status' => 409]);
    }

    $user_id = wp_create_user($username, $password, $username . '@example.com');
    if (is_wp_error($user_id)) {
        return new WP_Error('user_creation', 'Errore creazione utente', ['status' => 500]);
    }

    $wpdb->insert(
        $wpdb->prefix . 'gp_experts',
        [
            'name' => $name,
            'surname' => $surname,
            'username' => $username,
            'password' => wp_hash_password($password),
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s']
    );

    $user = new WP_User($user_id);
    $user->set_role('esperto');
    return rest_ensure_response(['message' => 'Registrazione avvenuta']);
}

// Funzione di login esperto
function gp_login_expert($request) {
    $data = $request->get_json_params();
    $username = sanitize_user($request['username']);
    $password = $request['password'];
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        return new WP_Error('login_failed', 'Credenziali non valide', ['status' => 401]);
    }

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    
    return rest_ensure_response(['message' => 'Accesso effettuato']);
}

// Report attività (MODIFICATO)
function gp_get_activity_report($request) {
    global $wpdb;
    $students_table = $wpdb->prefix . 'gp_students';
    $lessons_table = $wpdb->prefix . 'gp_lessons';

    // Ottieni parametri dei filtri
    $school = sanitize_text_field($request->get_param('school') ?? '');
    $expert = sanitize_text_field($request->get_param('expert') ?? '');
    $project_code = sanitize_text_field($request->get_param('project_code') ?? '');
    $start_date = sanitize_text_field($request->get_param('start_date') ?? '');
    $end_date = sanitize_text_field($request->get_param('end_date') ?? '');
        // Filtri base per non-admin
        $expert = sanitize_text_field($request->get_param('expert') ?? '');
        if (!current_user_can('administrator')) {
            $expert = wp_get_current_user()->user_login;
        }

    // Costruisci clausola WHERE per entrambe le query
    $where = [];
    if ($school) $where[] = $wpdb->prepare("s.school = %s", $school);
    if ($expert) $where[] = $wpdb->prepare("s.expert = %s", $expert);
    if ($project_code) $where[] = $wpdb->prepare("s.project_code = %s", $project_code);
    if ($start_date) $where[] = $wpdb->prepare("l.lesson_date >= %s", $start_date);
    if ($end_date) $where[] = $wpdb->prepare("l.lesson_date <= %s", $end_date);
    $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Statistiche esperti (con filtri)
    $expert_activities = $wpdb->get_results("
        SELECT 
            s.expert AS expert_name,
            COUNT(l.id) AS total_lessons,
            ROUND(SUM(TIME_TO_SEC(TIMEDIFF(l.end_time, l.start_time)))/3600, 2) AS total_hours
        FROM $students_table s
        LEFT JOIN $lessons_table l ON l.student_id = s.id
        $where_clause
        GROUP BY s.expert
    ", ARRAY_A);

    // Dettagli lezioni (con filtri)
    $lesson_details = $wpdb->get_results("
        SELECT 
            s.expert AS expert_name,
            s.name AS student_name,
            s.school,
            l.lesson_date,
            l.start_time,
            l.end_time
        FROM $lessons_table l
        INNER JOIN $students_table s ON l.student_id = s.id
        $where_clause
    ", ARRAY_A);

    return rest_ensure_response([
        'expert_activities' => $expert_activities ?: [],
        'lesson_details' => $lesson_details ?: []
    ]);
}
function gp_sync_experts_on_role_change($user_id, $role) {
    global $wpdb; // Aggiungi questa linea
    $user = get_userdata($user_id);
    
    // Controlla se il ruolo 'esperto' è presente
    if (in_array('esperto', $user->roles)) {
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$wpdb->prefix}gp_experts WHERE username = %s", $user->user_login)
        );
        
        if (!$existing) {
            $wpdb->insert(
                $wpdb->prefix . 'gp_experts',
                [
                    'name' => $user->first_name,
                    'surname' => $user->last_name,
                    'username' => $user->user_login,
                    'password' => 'synced_via_wordpress',
                    'hourly_rate' => 0.00,
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%f', '%s']
            );
        }
    }
}
// Aggiungi hook per entrambi gli eventi
 
add_action('add_user_role', 'gp_sync_experts_on_role_change', 10, 2); // Nuovo hook
// Ottieni esperti
function gp_get_experts($request) {
    global $wpdb;
    // Ottieni tutti gli utenti WordPress con ruolo 'esperto'
    $args = [
        'role' => 'esperto',
        'fields' => ['user_login']
    ];
    $user_query = new WP_User_Query($args);
    $user_logins = array_map(fn($u) => $u->user_login, $user_query->get_results());
    
    if (empty($user_logins)) {
        return rest_ensure_response([]);
    }
    
    // Ottieni dati degli esperti dalla tabella gp_experts
    $placeholders = implode(',', array_fill(0, count($user_logins), '%s'));
    $experts = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gp_experts WHERE username IN ($placeholders)", $user_logins),
        ARRAY_A
    );
    
    return rest_ensure_response($experts ?: []);
}

// Ottieni esperto specifico
function gp_get_expert($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $expert = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gp_experts WHERE id = %d", $id),
        ARRAY_A
    );
    if (!$expert) {
        return new WP_Error('expert_not_found', 'Esperto non trovato', ['status' => 404]);
    }
    return rest_ensure_response($expert);
}

// Aggiungi esperto
function gp_add_expert($request) {
    global $wpdb;
    $data = $request->get_params();
    $name = sanitize_text_field($data['name']);
    $surname = sanitize_text_field($data['surname']);
    $username = sanitize_user($data['username']);
    $password = esc_attr($data['password']);
    $hourly_rate = floatval($data['hourly_rate']); // Aggiungi questa riga

    // Controlla campi obbligatori
    if (empty($name) || empty($surname) || empty($username) || empty($password) || empty($hourly_rate)) {
        return new WP_Error('invalid_data', 'Tutti i campi sono obbligatori', ['status' => 400]);
    }

    // Controlla se l'username esiste nel plugin
    $existing_expert = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$wpdb->prefix}gp_experts WHERE username = %s", $username)
    );
    if ($existing_expert) {
        return new WP_Error('username_exists', 'Username già in uso', ['status' => 409]);
    }

    // Controlla se esiste in WordPress
    if (username_exists($username)) {
        return new WP_Error('username_exists', 'Username già in uso', ['status' => 409]);
    }

    // Inserisci nel plugin
    $wpdb->insert(
        $wpdb->prefix . 'gp_experts',
        [
            'name' => $name,
            'surname' => $surname,
            'username' => $username,
            'password' => wp_hash_password($password),
            'hourly_rate' => $hourly_rate, // Aggiungi il campo
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%f', '%s'] // Aggiorna i tipi di dati
    );

    // Crea l'utente WordPress
    $user_id = wp_create_user($username, $password, $username . '@example.com');
    if (is_wp_error($user_id)) {
        $wpdb->delete($wpdb->prefix . 'gp_experts', ['id' => $wpdb->insert_id]); // Rollback
        return new WP_Error('user_creation', 'Errore creazione utente', ['status' => 500]);
    }
    $user = new WP_User($user_id);
    $user->set_role('esperto');

    return rest_ensure_response(['message' => 'Esperto creato']);
}

// Modifica esperto
function gp_update_expert($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $data = $request->get_json_params();
    $name = sanitize_text_field($data['name'] ?? '');
    $surname = sanitize_text_field($data['surname'] ?? '');
    $username = sanitize_user($data['username'] ?? '');
    $password = esc_attr($data['password'] ?? '');

    if (empty($name) || empty($surname) || empty($username)) {
        return new WP_Error('invalid_data', 'Tutti i campi sono obbligatori', ['status' => 400]);
    }

    $current_username = $wpdb->get_var(
        $wpdb->prepare("SELECT username FROM {$wpdb->prefix}gp_experts WHERE id = %d", $id)
    );

    if ($username !== $current_username && username_exists($username)) {
        return new WP_Error('username_exists', 'Username già in uso', ['status' => 409]);
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'gp_experts',
        [
            'name' => $name,
            'surname' => $surname,
            'username' => $username,
            'password' => !empty($password) ? wp_hash_password($password) : null
        ],
        ['id' => $id],
        ['%s', '%s', '%s', '%s'],
        ['%d']
    );

    if (false === $updated) {
        return new WP_Error('db_error', 'Errore aggiornamento esperto', ['status' => 500]);
    }

    $user = get_user_by('login', $current_username);
    if ($user) {
        wp_update_user([
            'ID' => $user->ID,
            'user_login' => $username,
            'display_name' => "$name $surname",
        ]);
    }

    return rest_ensure_response(['message' => 'Esperto modificato']);
}

// Elimina esperto
function gp_delete_expert($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $expert = $wpdb->get_row(
        $wpdb->prepare("SELECT username FROM {$wpdb->prefix}gp_experts WHERE id = %d", $id),
        ARRAY_A
    );

    if ($expert && !empty($expert['username'])) {
        $user = get_user_by('login', $expert['username']);
        if ($user) {
            wp_delete_user($user->ID);
        }
    }

    $wpdb->delete($wpdb->prefix . 'gp_experts', ['id' => $id]);
    return rest_ensure_response(['message' => 'Esperto eliminato']);
}
function gp_get_current_expert() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $expert = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gp_experts WHERE username = %s", $current_user->user_login),
        ARRAY_A
    );
    if (!$expert) {
        return new WP_Error('expert_not_found', 'Esperto non trovato', ['status' => 404]);
    }
    return rest_ensure_response($expert);
}


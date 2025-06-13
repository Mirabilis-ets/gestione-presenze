<?php
// Funzione di registrazione esperto
function gp_register_expert($request) {
    global $wpdb;
    $data = $request->get_params();
    $name = sanitize_text_field($data['name'] ?? '');
    $surname = sanitize_text_field($data['surname'] ?? '');
    $username = sanitize_user($data['username'] ?? '');
    $password = $data['password'] ?? ''; // Password for wp_create_user

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

    // Hourly rate is not set during registration, defaults to 0 or a predefined value in DB schema if any
    $wpdb->insert(
        $wpdb->prefix . 'gp_experts',
        [
            'name' => $name,
            'surname' => $surname,
            'username' => $username,
            // 'password' field removed
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s'] // Adjusted format array
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
    $expert_username_filter = sanitize_text_field($request->get_param('expert') ?? ''); // Renamed to avoid conflict
    $project_code = sanitize_text_field($request->get_param('project_code') ?? '');
    $start_date = sanitize_text_field($request->get_param('start_date') ?? '');
    $end_date = sanitize_text_field($request->get_param('end_date') ?? '');

    $current_user_expert_username = '';
    if (!current_user_can('administrator')) {
        $current_user_expert_username = wp_get_current_user()->user_login;
    }

    // Costruisci clausola WHERE per entrambe le query
    $where = [];
    if ($school) $where[] = $wpdb->prepare("s.school = %s", $school);

    if ($current_user_expert_username) { // Non-admin sees only their data
        $where[] = $wpdb->prepare("s.expert = %s", $current_user_expert_username);
    } elseif ($expert_username_filter) { // Admin can filter by expert
        $where[] = $wpdb->prepare("s.expert = %s", $expert_username_filter);
    }

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
    global $wpdb;
    $user = get_userdata($user_id);

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
                    // 'password' field removed
                    'hourly_rate' => 0.00, // Default hourly rate
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%f', '%s'] // Adjusted format array
            );
        }
    }
}

add_action('add_user_role', 'gp_sync_experts_on_role_change', 10, 2);
add_action('set_user_role', 'gp_sync_experts_on_role_change', 10, 2); // Handles role changes too

// Ottieni esperti
function gp_get_experts($request) {
    global $wpdb;
    $args = [
        'role' => 'esperto',
        'fields' => ['user_login']
    ];
    $user_query = new WP_User_Query($args);
    $user_logins = array_map(fn($u) => $u->user_login, $user_query->get_results());

    if (empty($user_logins)) {
        return rest_ensure_response([]);
    }

    $placeholders = implode(',', array_fill(0, count($user_logins), '%s'));
    $experts = $wpdb->get_results(
        $wpdb->prepare("SELECT id, name, surname, username, hourly_rate, created_at FROM {$wpdb->prefix}gp_experts WHERE username IN ($placeholders)", $user_logins),
        ARRAY_A
    );

    return rest_ensure_response($experts ?: []);
}

// Ottieni esperto specifico
function gp_get_expert($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $expert = $wpdb->get_row(
        $wpdb->prepare("SELECT id, name, surname, username, hourly_rate, created_at FROM {$wpdb->prefix}gp_experts WHERE id = %d", $id),
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
    $name = sanitize_text_field($data['name'] ?? '');
    $surname = sanitize_text_field($data['surname'] ?? '');
    $username = sanitize_user($data['username'] ?? '');
    $password = $data['password'] ?? ''; // Password for wp_create_user
    $hourly_rate = floatval($data['hourly_rate'] ?? 0.00);

    if (empty($name) || empty($surname) || empty($username) || empty($password) || $hourly_rate <= 0) { // Basic validation for hourly_rate
        return new WP_Error('invalid_data', 'Nome, cognome, username, password e tariffa oraria (positiva) sono obbligatori', ['status' => 400]);
    }

    $existing_expert = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$wpdb->prefix}gp_experts WHERE username = %s", $username)
    );
    if ($existing_expert) {
        return new WP_Error('username_exists_plugin', 'Username già in uso nella tabella esperti.', ['status' => 409]);
    }

    if (username_exists($username)) {
        return new WP_Error('username_exists_wp', 'Username già registrato in WordPress.', ['status' => 409]);
    }

    $wpdb->insert(
        $wpdb->prefix . 'gp_experts',
        [
            'name' => $name,
            'surname' => $surname,
            'username' => $username,
            // 'password' field removed
            'hourly_rate' => $hourly_rate,
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%f', '%s'] // Adjusted format array
    );

    $expert_id = $wpdb->insert_id;

    $user_id = wp_create_user($username, $password, $username . '@example.com');
    if (is_wp_error($user_id)) {
        $wpdb->delete($wpdb->prefix . 'gp_experts', ['id' => $expert_id]); // Rollback plugin table insert
        return new WP_Error('user_creation_failed', 'Errore durante la creazione dell\'utente WordPress: ' . $user_id->get_error_message(), ['status' => 500]);
    }
    $user = new WP_User($user_id);
    $user->set_role('esperto');

    return rest_ensure_response(['message' => 'Esperto creato con successo', 'id' => $expert_id]);
}

// Modifica esperto
function gp_update_expert($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $data = $request->get_json_params();

    $name = sanitize_text_field($data['name'] ?? '');
    $surname = sanitize_text_field($data['surname'] ?? '');
    $username = sanitize_user($data['username'] ?? '');
    $hourly_rate = isset($data['hourly_rate']) ? floatval($data['hourly_rate']) : null;
    // Password is not updated here as it's managed by WordPress user profile

    if (empty($name) || empty($surname) || empty($username)) {
        return new WP_Error('invalid_data', 'Nome, cognome e username sono obbligatori', ['status' => 400]);
    }
     if ($hourly_rate !== null && $hourly_rate <= 0) {
        return new WP_Error('invalid_hourly_rate', 'La tariffa oraria deve essere un valore positivo.', ['status' => 400]);
    }

    $current_expert_data = $wpdb->get_row(
        $wpdb->prepare("SELECT username, hourly_rate FROM {$wpdb->prefix}gp_experts WHERE id = %d", $id)
    );

    if (!$current_expert_data) {
        return new WP_Error('expert_not_found', 'Esperto non trovato per l\'ID fornito.', ['status' => 404]);
    }
    $current_username = $current_expert_data->username;

    if ($username !== $current_username && username_exists($username)) {
        // Check if the new username is taken by another WordPress user
        $user_exists = get_user_by('login', $username);
        if ($user_exists) {
             // Check if this existing WP user is also in gp_experts and is not the current expert we are trying to update
            $other_expert_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}gp_experts WHERE username = %s AND id != %d", $username, $id));
            if($other_expert_id){
                 return new WP_Error('username_exists_다른_전문가', 'Nuovo username già in uso da un altro esperto.', ['status' => 409]);
            }
        }
        // If the new username is not taken by another expert, but exists in WP (e.g. a subscriber),
        // we might need to decide if we allow "taking over" that username if it's not an 'esperto' yet,
        // or just block it. For now, let's be strict.
        if (username_exists($username)) {
             return new WP_Error('username_exists_wp', 'Nuovo username già in uso in WordPress.', ['status' => 409]);
        }
    }

    $update_data = [
        'name' => $name,
        'surname' => $surname,
        'username' => $username,
    ];
    $format = ['%s', '%s', '%s'];

    if ($hourly_rate !== null) {
        $update_data['hourly_rate'] = $hourly_rate;
        $format[] = '%f';
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'gp_experts',
        $update_data,
        ['id' => $id],
        $format,
        ['%d']
    );

    if (false === $updated) {
        // Not necessarily an error, could be that no data changed.
        // $wpdb->last_error can be checked for actual DB error.
        if($wpdb->last_error){
            return new WP_Error('db_error', 'Errore durante l\'aggiornamento dell\'esperto: ' . $wpdb->last_error, ['status' => 500]);
        }
         // If no rows affected, and no error, it means data was the same.
        return rest_ensure_response(['message' => 'Nessun dato modificato o errore durante l\'aggiornamento.']);
    }

    // Update WordPress user if username changed
    if ($username !== $current_username) {
        $user = get_user_by('login', $current_username);
        if ($user) {
            $user_update_result = wp_update_user([
                'ID' => $user->ID,
                'user_login' => $username,
                'display_name' => "$name $surname",
                // Email update might be needed if it's derived from username or stored separately
            ]);
            if(is_wp_error($user_update_result)){
                 // Potentially rollback gp_experts change or log this inconsistency
                return new WP_Error('wp_user_update_failed', 'Esperto aggiornato nella tabella plugin, ma errore aggiornamento utente WordPress: ' . $user_update_result->get_error_message(), ['status' => 500]);
            }
        } else {
             // This case should ideally not happen if data is consistent
             return new WP_Error('wp_user_not_found_for_update', 'Esperto aggiornato, ma utente WordPress corrispondente al vecchio username non trovato.', ['status' => 500]);
        }
    } elseif ($name !== $current_expert_data->name || $surname !== $current_expert_data->surname) {
        // Update display name if only name/surname changed
         $user = get_user_by('login', $username); // username is current_username here
         if ($user) {
            wp_update_user([
                'ID' => $user->ID,
                'display_name' => "$name $surname",
            ]);
        }
    }


    return rest_ensure_response(['message' => 'Esperto modificato con successo']);
}

// Elimina esperto
function gp_delete_expert($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $expert_data = $wpdb->get_row(
        $wpdb->prepare("SELECT username FROM {$wpdb->prefix}gp_experts WHERE id = %d", $id),
        ARRAY_A // Fetch as associative array
    );

    if ($expert_data && !empty($expert_data['username'])) {
        $user = get_user_by('login', $expert_data['username']);
        if ($user) {
            // Remove 'esperto' role first
            $wp_user = new WP_User($user->ID);
            $wp_user->remove_role('esperto');
            // Decide if the WP user should be deleted or just unlinked
            // For now, let's just remove the role, not delete the WP user
            // wp_delete_user($user->ID); // Uncomment to delete WP user as well
        }
    }

    $deleted = $wpdb->delete($wpdb->prefix . 'gp_experts', ['id' => $id], ['%d']);

    if(!$deleted){
        return new WP_Error('db_delete_failed', 'Errore durante l\'eliminazione dell\'esperto dalla tabella plugin.', ['status' => 500]);
    }

    return rest_ensure_response(['message' => 'Esperto eliminato dalla tabella plugin e ruolo rimosso da WordPress.']);
}

function gp_get_current_expert() {
    global $wpdb;
    $current_user = wp_get_current_user();

    if (!$current_user || $current_user->ID === 0) {
        return new WP_Error('not_logged_in', 'Utente non autenticato.', ['status' => 401]);
    }

    $expert = $wpdb->get_row(
        $wpdb->prepare("SELECT id, name, surname, username, hourly_rate, created_at FROM {$wpdb->prefix}gp_experts WHERE username = %s", $current_user->user_login),
        ARRAY_A
    );

    if (!$expert) {
        // If user is logged in but not in gp_experts, could be an admin or other role
        // Or data inconsistency. For now, treat as "expert profile not found for this user"
        return new WP_Error('expert_profile_not_found', 'Profilo esperto non trovato per l\'utente corrente.', ['status' => 404]);
    }
    return rest_ensure_response($expert);
}

?>

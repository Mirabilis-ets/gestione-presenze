<?php
// Ottieni studenti
function gp_get_students($request) {
    global $wpdb;
    $expert = sanitize_text_field($request->get_param('expert') ?? '');
    $table = $wpdb->prefix . 'gp_students';
    if (current_user_can('administrator')) {
        $query = "SELECT * FROM $table";
    } else {
        $query = $wpdb->prepare("SELECT * FROM $table WHERE expert = %s", $expert);
    }
    $results = $wpdb->get_results($query, ARRAY_A);
    return rest_ensure_response($results ?? []);
}

// Ottieni studente specifico
function gp_get_student($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $student = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gp_students WHERE id = %d", $id),
        ARRAY_A
    );
    if (!$student) {
        return new WP_Error('student_not_found', 'Studente non trovato', ['status' => 404]);
    }
    
    return rest_ensure_response($student);
}
// Scuole
function gp_get_schools() {
    global $wpdb;
    $schools = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}gp_schools", ARRAY_A);
    return rest_ensure_response($schools ? array_column($schools, 'name') : []);
}
// Modifica scuola
function gp_update_school($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $name = sanitize_text_field($request['name']);
    
    $wpdb->update(
        $wpdb->prefix . 'gp_schools',
        ['name' => $name],
        ['id' => $id],
        ['%s'],
        ['%d']
    );
    
    return rest_ensure_response(['message' => 'Scuola aggiornata']);
}

// Elimina scuola
function gp_delete_school($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $wpdb->delete($wpdb->prefix . 'gp_schools', ['id' => $id]);
    return rest_ensure_response(['message' => 'Scuola eliminata']);
}

// Aggiungi nuova scuola
function gp_add_school($request) {
    global $wpdb;
    $name = sanitize_text_field($request['name']);
    if (empty($name)) {
        return new WP_Error('invalid_data', 'Nome scuola obbligatorio', ['status' => 400]);
    }
    $wpdb->insert(
        $wpdb->prefix . 'gp_schools',
        ['name' => $name],
        ['%s']
    );
    return rest_ensure_response(['id' => $wpdb->insert_id, 'name' => $name]);
}


// Esperti (username)
function gp_get_experts_for_filters() {
    global $wpdb;
    $experts = $wpdb->get_col("SELECT username FROM {$wpdb->prefix}gp_experts");
    return rest_ensure_response($experts ?: []);
}


// Codici progetto
function gp_get_project_codes() {
    global $wpdb;
    $codes = $wpdb->get_col("SELECT DISTINCT project_code FROM {$wpdb->prefix}gp_students");
    return rest_ensure_response($codes ?: []);
}

// Aggiungi studente
function gp_add_student($request) {
    global $wpdb;
    $data = $request->get_params();
    $name = sanitize_text_field($data['name'] ?? '');
    $project_code = sanitize_text_field($data['project_code'] ?? '');
    $class = sanitize_text_field($data['class'] ?? '');
    $school = sanitize_text_field($data['school'] ?? '');
     

    if (empty($name) || empty($project_code) || empty($class)) {
        return new WP_Error('invalid_data', 'Tutti i campi sono obbligatori', ['status' => 400]);
    }
    $expert = wp_get_current_user()->user_login; // Ottieni l'username corrente
    $wpdb->insert(
        $wpdb->prefix . 'gp_students',
        [
            'name' => $name,
            'project_code' => $project_code,
            'class' => $class,
            'school'=> $school,
            'expert' => $expert,
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s','%s']
    );

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'Errore durante l\'inserimento studente', ['status' => 500]);
    }

    return rest_ensure_response(['message' => 'Studente creato', 'id' => $wpdb->insert_id]);
}

// Modifica studente
function gp_update_student($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $data = $request->get_json_params(); // Legge i dati JSON

    $name = sanitize_text_field($data['name']);
    $project_code = sanitize_text_field($data['project_code']);
    $class = sanitize_text_field($data['class']);
    $school = sanitize_text_field($data['school']); // AGGIUNTA

    // Controllo permessi
    $student_expert = $wpdb->get_var(
        $wpdb->prepare("SELECT expert FROM {$wpdb->prefix}gp_students WHERE id = %d", $id)
    );
    if (!current_user_can('administrator')) {
        $current_user = wp_get_current_user()->user_login;
        if ($student_expert !== $current_user) {
            return new WP_Error('forbidden', 'Non autorizzato', ['status' => 403]);
        }
    }

    // Aggiorna il database
    $wpdb->update(
        $wpdb->prefix . 'gp_students',
        [
            'name' => $name,
            'project_code' => $project_code,
            'class' => $class,
            'school' => $school // Incluso nel database
        ],
        ['id' => $id],
        ['%s', '%s', '%s', '%s'], // Specifica i formati per i campi
        ['%d']
    );

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'Errore durante l\'aggiornamento', ['status' => 500]);
    }

    return rest_ensure_response(['message' => 'Alunno modificato']);
}
// Elimina studente
function gp_delete_student($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $wpdb->delete($wpdb->prefix . 'gp_students', ['id' => $id]);
    return rest_ensure_response(['message' => 'Studente eliminato']);
}

// Ottieni lezioni
function gp_get_lessons($request) {
    global $wpdb;
    $current_expert = wp_get_current_user()->user_login;
    $query = $wpdb->prepare("
        SELECT 
            l.id AS lesson_id,
            l.lesson_date,
            l.start_time,
            l.end_time,
            s.name AS student_name,
            s.class AS student_class,
            s.project_code
        FROM {$wpdb->prefix}gp_lessons l
        INNER JOIN {$wpdb->prefix}gp_students s ON l.student_id = s.id
        WHERE s.expert = %s
    ", $current_expert);
    
    $results = $wpdb->get_results($query, ARRAY_A);
    return rest_ensure_response($results);
}

// Ottieni lezione specifica
function gp_get_lesson($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $lesson = $wpdb->get_row(
        $wpdb->prepare("
            SELECT 
                l.id AS lesson_id,
                l.student_id,
                l.lesson_date,
                l.start_time,
                l.end_time,
                s.name AS student_name
            FROM {$wpdb->prefix}gp_lessons l
            INNER JOIN {$wpdb->prefix}gp_students s ON l.student_id = s.id
            WHERE l.id = %d
        ", $id),
        ARRAY_A
    );
    if (!$lesson) {
        return new WP_Error('lesson_not_found', 'Lezione non trovata', ['status' => 404]);
    }
    return rest_ensure_response($lesson);
}
// Aggiungi lezione
function gp_add_lesson($request) {
    global $wpdb;
    $data = $request->get_json_params(); // Legge i dati JSON
    $student_id = absint($data['student_id'] ?? 0);
    $lesson_date = sanitize_text_field($data['lesson_date'] ?? '');
    $start_time = sanitize_text_field($data['start_time'] ?? '');
    $end_time = sanitize_text_field($data['end_time'] ?? '');

    if (!$student_id || empty($lesson_date) || empty($start_time) || empty($end_time)) {
        return new WP_Error('invalid_data', 'Tutti i campi sono obbligatori', ['status' => 400]);
    }

    $wpdb->insert(
        $wpdb->prefix . 'gp_lessons',
        [
            'student_id' => $student_id,
            'lesson_date' => $lesson_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s', '%s']
    );

    return rest_ensure_response(['message' => 'Lezione creata']);
}

// Modifica lezione
function gp_update_lesson($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $data = $request->get_json_params();
    $student_id = absint($data['student_id'] ?? 0);
    $lesson_date = sanitize_text_field($data['lesson_date'] ?? '');
    $start_time = sanitize_text_field($data['start_time'] ?? '');
    $end_time = sanitize_text_field($data['end_time'] ?? '');

    if (!$student_id || empty($lesson_date) || empty($start_time) || empty($end_time)) {
        return new WP_Error('invalid_data', 'Tutti i campi sono obbligatori', ['status' => 400]);
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'gp_lessons',
        ['student_id' => $student_id, 'lesson_date' => $lesson_date, 'start_time' => $start_time, 'end_time' => $end_time],
        ['id' => $id]
    );

    if (false === $updated) {
        return new WP_Error('db_error', 'Errore durante l\'aggiornamento lezione', ['status' => 500]);
    }

    return rest_ensure_response(['message' => 'Lezione modificata']);
}

// Elimina lezione
function gp_delete_lesson($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $wpdb->delete($wpdb->prefix . 'gp_lessons', ['id' => $id]);
    return rest_ensure_response(['message' => 'Lezione eliminata']);
}


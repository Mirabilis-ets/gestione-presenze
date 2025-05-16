<?php
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

// Aggiungi lezione
function gp_add_lesson($request) {
    global $wpdb;
    $data = $request->get_params();
    $student_id = absint($data['student_id']);
    $lesson_date = sanitize_text_field($data['lesson_date']);
    $start_time = sanitize_text_field($data['start_time']);
    $end_time = sanitize_text_field($data['end_time']);

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

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'Errore inserimento lezione', ['status' => 500]);
    }

    return rest_ensure_response(['message' => 'Lezione creata', 'id' => $wpdb->insert_id]);
}

// Modifica lezione
function gp_update_lesson($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $data = $request->get_json_params();
    $student_id = absint($data['student_id']);
    $lesson_date = sanitize_text_field($data['lesson_date']);
    $start_time = sanitize_text_field($data['start_time']);
    $end_time = sanitize_text_field($data['end_time']);

    if (!$student_id || empty($lesson_date) || empty($start_time) || empty($end_time)) {
        return new WP_Error('invalid_data', 'Tutti i campi sono obbligatori', ['status' => 400]);
    }

    $wpdb->update(
        $wpdb->prefix . 'gp_lessons',
        [
            'student_id' => $student_id,
            'lesson_date' => $lesson_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ],
        ['id' => $id]
    );

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'Errore aggiornamento lezione', ['status' => 500]);
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
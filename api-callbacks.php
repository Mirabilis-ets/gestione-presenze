<?php
// Ottieni studenti
function gp_get_students($request) {
    global $wpdb;
    $expert_username = sanitize_text_field($request->get_param('expert') ?? ''); // Changed variable name for clarity
    $table = $wpdb->prefix . 'gp_students';
    if (current_user_can('administrator')) {
        // Admin can see all students or filter by expert
        if (!empty($expert_username)) {
            $query = $wpdb->prepare("SELECT * FROM $table WHERE expert = %s", $expert_username);
        } else {
            $query = "SELECT * FROM $table";
        }
    } else {
        // Non-admin (esperto) sees only their students
        $current_user_login = wp_get_current_user()->user_login;
        $query = $wpdb->prepare("SELECT * FROM $table WHERE expert = %s", $current_user_login);
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
    // Security check: Non-admins can only get their own students
    if (!current_user_can('administrator')) {
        $current_user_login = wp_get_current_user()->user_login;
        if ($student['expert'] !== $current_user_login) {
            return new WP_Error('forbidden_student_access', 'Non autorizzato a visualizzare questo studente.', ['status' => 403]);
        }
    }
    return rest_ensure_response($student);
}

// Scuole
function gp_get_schools() {
    global $wpdb;
    $schools = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}gp_schools", ARRAY_A);
    return rest_ensure_response($schools ? array_column($schools, 'name') : []);
}

// Elimina scuola
function gp_delete_school($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    // Consider adding checks if this school is in use by students before deleting.
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
    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'Errore inserimento scuola: ' . $wpdb->last_error, ['status' => 500]);
    }
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

    if (empty($name) || empty($project_code) || empty($class) || empty($school)) {
        return new WP_Error('invalid_data', 'Nome, codice progetto, classe e scuola sono obbligatori.', ['status' => 400]);
    }

    // Experts can only add students to themselves. Admins can specify an expert or defaults to self.
    $expert_username = wp_get_current_user()->user_login;
    if (current_user_can('administrator') && !empty($data['expert_username'])) {
        // Check if provided expert username exists
        $expert_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gp_experts WHERE username = %s", $data['expert_username']));
        if(!$expert_exists){
            return new WP_Error('invalid_expert', 'L\'esperto specificato non esiste.', ['status' => 400]);
        }
        $expert_username = $data['expert_username'];
    }

    $wpdb->insert(
        $wpdb->prefix . 'gp_students',
        [
            'name' => $name,
            'project_code' => $project_code,
            'class' => $class,
            'school'=> $school,
            'expert' => $expert_username,
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s','%s']
    );

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'Errore durante l\'inserimento studente: ' . $wpdb->last_error, ['status' => 500]);
    }

    return rest_ensure_response(['message' => 'Studente creato', 'id' => $wpdb->insert_id]);
}

// Modifica studente
function gp_update_student($request) {
    global $wpdb;
    $id = absint($request->get_param('id'));
    $data = $request->get_json_params();

    $name = sanitize_text_field($data['name'] ?? '');
    $project_code = sanitize_text_field($data['project_code'] ?? '');
    $class = sanitize_text_field($data['class'] ?? '');
    $school = sanitize_text_field($data['school'] ?? '');
    $new_expert_username = sanitize_text_field($data['expert'] ?? '');


    if (empty($name) || empty($project_code) || empty($class) || empty($school)) {
        return new WP_Error('invalid_data', 'Nome, codice progetto, classe e scuola sono obbligatori.', ['status' => 400]);
    }

    $student_table = $wpdb->prefix . 'gp_students';
    $student_expert = $wpdb->get_var(
        $wpdb->prepare("SELECT expert FROM $student_table WHERE id = %d", $id)
    );

    if (!$student_expert) {
        return new WP_Error('student_not_found', 'Studente non trovato.', ['status' => 404]);
    }

    if (!current_user_can('administrator')) {
        $current_user_login = wp_get_current_user()->user_login;
        if ($student_expert !== $current_user_login) {
            return new WP_Error('forbidden_update', 'Non autorizzato a modificare questo studente.', ['status' => 403]);
        }
        // If an expert tries to change the expert field, block it.
        if (!empty($new_expert_username) && $new_expert_username !== $current_user_login) {
            return new WP_Error('forbidden_expert_change', 'Non puoi cambiare l\'esperto assegnato. Solo un amministratore può farlo.', ['status' => 403]);
        }
        $new_expert_username = $current_user_login; // Ensure expert remains the same
    } else {
        // Admin is changing the expert, validate the new expert username
        if (!empty($new_expert_username) && $new_expert_username !== $student_expert) {
            $expert_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gp_experts WHERE username = %s", $new_expert_username));
            if(!$expert_exists){
                return new WP_Error('invalid_new_expert', 'Il nuovo esperto specificato non esiste.', ['status' => 400]);
            }
        } elseif (empty($new_expert_username)) {
            $new_expert_username = $student_expert; // Keep original if not provided by admin
        }
    }


    $update_result = $wpdb->update(
        $student_table,
        [
            'name' => $name,
            'project_code' => $project_code,
            'class' => $class,
            'school' => $school,
            'expert' => $new_expert_username
        ],
        ['id' => $id],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if (false === $update_result) {
         if($wpdb->last_error){
            return new WP_Error('db_error', 'Errore durante l\'aggiornamento: ' . $wpdb->last_error, ['status' => 500]);
        }
        return rest_ensure_response(['message' => 'Nessun dato modificato.']);
    }

    return rest_ensure_response(['message' => 'Alunno modificato']);
}

// Elimina studente
function gp_delete_student($request) {
    global $wpdb;
    $student_id = absint($request->get_param('id'));

    if (!current_user_can('administrator')) {
        $student_expert = $wpdb->get_var(
            $wpdb->prepare("SELECT expert FROM {$wpdb->prefix}gp_students WHERE id = %d", $student_id)
        );
        if (!$student_expert) {
            return new WP_Error('student_not_found', 'Studente non trovato.', ['status' => 404]);
        }
        $current_user_login = wp_get_current_user()->user_login;
        if ($student_expert !== $current_user_login) {
            return new WP_Error('forbidden_delete', 'Non autorizzato ad eliminare questo studente. Appartiene ad un altro esperto.', ['status' => 403]);
        }
    }
    // Consider deleting associated lessons or handle via FOREIGN KEY ON DELETE CASCADE (already in schema)
    $deleted = $wpdb->delete($wpdb->prefix . 'gp_students', ['id' => $student_id], ['%d']);
    if (!$deleted) {
        if($wpdb->last_error){
             return new WP_Error('db_error', 'Errore eliminazione studente: ' . $wpdb->last_error, ['status' => 500]);
        }
        return new WP_Error('delete_failed', 'Eliminazione studente fallita, lo studente potrebbe non esistere più.', ['status' => 404]);
    }
    return rest_ensure_response(['message' => 'Studente eliminato']);
}

// Ottieni lezioni
function gp_get_lessons($request) {
    global $wpdb;
    $student_id_filter = absint($request->get_param('student_id') ?? 0);
    $current_user_login = wp_get_current_user()->user_login;
    $students_table = $wpdb->prefix . 'gp_students';
    $lessons_table = $wpdb->prefix . 'gp_lessons';

    $sql = "
        SELECT
            l.id AS lesson_id,
            l.lesson_date,
            l.start_time,
            l.end_time,
            s.id AS student_id,
            s.name AS student_name,
            s.class AS student_class,
            s.project_code,
            s.expert AS expert_username
        FROM $lessons_table l
        INNER JOIN $students_table s ON l.student_id = s.id
    ";
    $where_conditions = [];

    if (current_user_can('administrator')) {
        if ($student_id_filter) {
            $where_conditions[] = $wpdb->prepare("s.id = %d", $student_id_filter);
        }
        // Admin can also filter by expert if needed, add another param like 'expert_username'
        $expert_filter = sanitize_text_field($request->get_param('expert_username') ?? '');
        if ($expert_filter) {
             $where_conditions[] = $wpdb->prepare("s.expert = %s", $expert_filter);
        }
    } else {
        // Expert sees their lessons. Can filter by their own student_id.
        $where_conditions[] = $wpdb->prepare("s.expert = %s", $current_user_login);
        if ($student_id_filter) {
            // Ensure the student_id provided belongs to this expert
            $student_owner = $wpdb->get_var($wpdb->prepare("SELECT expert FROM $students_table WHERE id = %d", $student_id_filter));
            if ($student_owner !== $current_user_login) {
                return new WP_Error('forbidden_lesson_filter', 'Puoi filtrare le lezioni solo per i tuoi studenti.', ['status' => 403]);
            }
            $where_conditions[] = $wpdb->prepare("s.id = %d", $student_id_filter);
        }
    }

    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    $sql .= " ORDER BY l.lesson_date DESC, l.start_time DESC";

    $results = $wpdb->get_results($sql, ARRAY_A);
    return rest_ensure_response($results ?: []);
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
                s.name AS student_name,
                s.expert AS expert_username
            FROM {$wpdb->prefix}gp_lessons l
            INNER JOIN {$wpdb->prefix}gp_students s ON l.student_id = s.id
            WHERE l.id = %d
        ", $id),
        ARRAY_A
    );
    if (!$lesson) {
        return new WP_Error('lesson_not_found', 'Lezione non trovata', ['status' => 404]);
    }
    // Security check: Non-admins can only get lessons of their own students
    if (!current_user_can('administrator')) {
        $current_user_login = wp_get_current_user()->user_login;
        if ($lesson['expert_username'] !== $current_user_login) {
            return new WP_Error('forbidden_lesson_access', 'Non autorizzato a visualizzare questa lezione.', ['status' => 403]);
        }
    }
    return rest_ensure_response($lesson);
}

// Aggiungi lezione
function gp_add_lesson($request) {
    global $wpdb;
    $data = $request->get_json_params();
    $student_id = absint($data['student_id'] ?? 0);
    $lesson_date = sanitize_text_field($data['lesson_date'] ?? '');
    $start_time = sanitize_text_field($data['start_time'] ?? '');
    $end_time = sanitize_text_field($data['end_time'] ?? '');

    if (!$student_id || empty($lesson_date) || empty($start_time) || empty($end_time)) {
        return new WP_Error('invalid_data', 'ID studente, data, ora inizio e ora fine sono obbligatori.', ['status' => 400]);
    }

    if (strtotime($end_time) <= strtotime($start_time)) {
        return new WP_Error('invalid_time', 'L\'ora di fine deve essere successiva all\'ora di inizio.', ['status' => 400]);
    }

    if (!current_user_can('administrator')) {
        $student_expert = $wpdb->get_var(
            $wpdb->prepare("SELECT expert FROM {$wpdb->prefix}gp_students WHERE id = %d", $student_id)
        );
        if (!$student_expert) {
            return new WP_Error('student_not_found_for_lesson', 'Studente non trovato per cui aggiungere la lezione.', ['status' => 404]);
        }
        $current_user_login = wp_get_current_user()->user_login;
        if ($student_expert !== $current_user_login) {
            return new WP_Error('forbidden_add_lesson', 'Non puoi aggiungere lezioni per studenti di altri esperti.', ['status' => 403]);
        }
    } else { // Admin is adding a lesson, ensure student exists
         $student_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gp_students WHERE id = %d", $student_id));
         if(!$student_exists){
             return new WP_Error('student_not_found_for_lesson_admin', 'Studente specificato non trovato.', ['status' => 404]);
         }
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
        return new WP_Error('db_error', 'Errore inserimento lezione: ' . $wpdb->last_error, ['status' => 500]);
    }

    return rest_ensure_response(['message' => 'Lezione creata', 'id' => $wpdb->insert_id]);
}

// Modifica lezione
function gp_update_lesson($request) {
    global $wpdb;
    $lesson_id = absint($request->get_param('id'));
    $data = $request->get_json_params();

    $new_student_id = absint($data['student_id'] ?? 0); // Student ID might be changed by admin
    $lesson_date = sanitize_text_field($data['lesson_date'] ?? '');
    $start_time = sanitize_text_field($data['start_time'] ?? '');
    $end_time = sanitize_text_field($data['end_time'] ?? '');

    if (empty($lesson_date) || empty($start_time) || empty($end_time) || !$new_student_id) {
        return new WP_Error('invalid_data', 'ID studente, data, ora inizio e ora fine sono obbligatori per l\'aggiornamento.', ['status' => 400]);
    }
    if (strtotime($end_time) <= strtotime($start_time)) {
        return new WP_Error('invalid_time', 'L\'ora di fine deve essere successiva all\'ora di inizio.', ['status' => 400]);
    }

    $lesson_table = $wpdb->prefix . 'gp_lessons';
    $students_table = $wpdb->prefix . 'gp_students';

    // Get current lesson's student_id to check ownership before update
    $current_lesson_student_id = $wpdb->get_var($wpdb->prepare("SELECT student_id FROM $lesson_table WHERE id = %d", $lesson_id));
    if (!$current_lesson_student_id) {
        return new WP_Error('lesson_not_found_for_update', 'Lezione non trovata da aggiornare.', ['status' => 404]);
    }

    if (!current_user_can('administrator')) {
        $current_user_login = wp_get_current_user()->user_login;

        // Check ownership of the original lesson's student
        $original_student_expert = $wpdb->get_var($wpdb->prepare("SELECT expert FROM $students_table WHERE id = %d", $current_lesson_student_id));
        if ($original_student_expert !== $current_user_login) {
            return new WP_Error('forbidden_update_lesson_original', 'Non puoi modificare lezioni di studenti di altri esperti.', ['status' => 403]);
        }

        // If expert tries to change student_id, it must be to another of their own students
        if ($new_student_id !== $current_lesson_student_id) {
            $new_student_expert = $wpdb->get_var($wpdb->prepare("SELECT expert FROM $students_table WHERE id = %d", $new_student_id));
            if (!$new_student_expert) {
                 return new WP_Error('new_student_not_found', 'Il nuovo studente specificato non esiste.', ['status' => 404]);
            }
            if ($new_student_expert !== $current_user_login) {
                return new WP_Error('forbidden_change_student_for_lesson', 'Non puoi assegnare la lezione a studenti di altri esperti.', ['status' => 403]);
            }
        }
    } else { // Admin is updating
        // Ensure the new student_id exists if it's being changed
        if ($new_student_id !== $current_lesson_student_id) {
            $new_student_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $students_table WHERE id = %d", $new_student_id));
            if (!$new_student_exists) {
                return new WP_Error('new_student_not_found_admin', 'Il nuovo studente specificato dall\'admin non esiste.', ['status' => 404]);
            }
        }
    }


    $updated = $wpdb->update(
        $lesson_table,
        [
            'student_id' => $new_student_id,
            'lesson_date' => $lesson_date,
            'start_time' => $start_time,
            'end_time' => $end_time
        ],
        ['id' => $lesson_id],
        ['%d', '%s', '%s', '%s'], // Formats for values
        ['%d']  // Format for where clause
    );

    if (false === $updated) {
        if($wpdb->last_error){
             return new WP_Error('db_error', 'Errore aggiornamento lezione: ' . $wpdb->last_error, ['status' => 500]);
        }
        return rest_ensure_response(['message' => 'Nessun dato modificato.']);
    }

    return rest_ensure_response(['message' => 'Lezione modificata']);
}

// Elimina lezione
function gp_delete_lesson($request) {
    global $wpdb;
    $lesson_id = absint($request->get_param('id'));

    if (!current_user_can('administrator')) {
        $lesson_student_id = $wpdb->get_var(
            $wpdb->prepare("SELECT student_id FROM {$wpdb->prefix}gp_lessons WHERE id = %d", $lesson_id)
        );
        if (!$lesson_student_id) {
            return new WP_Error('lesson_not_found_for_delete', 'Lezione non trovata.', ['status' => 404]);
        }
        $student_expert = $wpdb->get_var(
            $wpdb->prepare("SELECT expert FROM {$wpdb->prefix}gp_students WHERE id = %d", $lesson_student_id)
        );
         if (!$student_expert) { // Should not happen if DB is consistent
            return new WP_Error('student_for_lesson_not_found', 'Studente associato alla lezione non trovato.', ['status' => 404]);
        }
        $current_user_login = wp_get_current_user()->user_login;
        if ($student_expert !== $current_user_login) {
            return new WP_Error('forbidden_delete_lesson', 'Non puoi eliminare lezioni di studenti di altri esperti.', ['status' => 403]);
        }
    }

    $deleted = $wpdb->delete($wpdb->prefix . 'gp_lessons', ['id' => $lesson_id], ['%d']);
    if (!$deleted) {
         if($wpdb->last_error){
             return new WP_Error('db_error', 'Errore eliminazione lezione: ' . $wpdb->last_error, ['status' => 500]);
        }
        return new WP_Error('delete_failed', 'Eliminazione lezione fallita, la lezione potrebbe non esistere più.', ['status' => 404]);
    }
    return rest_ensure_response(['message' => 'Lezione eliminata']);
}

?>

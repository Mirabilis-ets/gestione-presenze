document.addEventListener('DOMContentLoaded', () => {
    if (!gpData.currentUser) {
        return; // Non eseguire nulla se non autenticato
    }
    const GP_API_URL = gpData.apiUrl;

    // Funzione per mostrare alert
    function showAlert(message, type) {
        const div = document.createElement('div');
        div.className = `alert alert-${type}`;
        div.textContent = message;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 3000);
    }

    // Gestione del login
    document.querySelector('#loginForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('loginUsername').value.trim();
        const password = document.getElementById('loginPassword').value.trim();
        if (!username || !password) {
            return showAlert('Username e password sono obbligatori.', 'warning');
        }
        try {
            const res = await fetch(GP_API_URL + 'auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': gpData.nonce
                },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();
            if (res.ok) {
                showAlert('Accesso effettuato.', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                throw new Error(data.message || 'Credenziali non valide');
            }
        } catch (err) {
            showAlert(err.message, 'danger');
        }
    });
    // Carica Scuola
    function loadSchools() {
        fetch(GP_API_URL + 'schools', {
            headers: { 'X-WP-Nonce': gpData.nonce }
        })
        .then(res => res.json())
        .then(schools => {
            const selects = document.querySelectorAll('#studentSchool, #editStudentSchool');
            selects.forEach(select => {
                select.innerHTML = '<option value="">Seleziona una scuola</option>';
                schools.forEach(school => {
                    const option = document.createElement('option');
                    option.value = school;
                    option.textContent = school;
                    select.appendChild(option);
                });
            });
        });
    }
    // Caricamento Studenti Select Dashboard
    function loadStudentsSelect() {
        const expert = gpData.currentUser.username;
        fetch(`${GP_API_URL}students?expert=${expert}`, {
            headers: { 'X-WP-Nonce': gpData.nonce }
        })
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('studentSelect');
            select.innerHTML = '<option value="">Seleziona uno studente</option>';
            data.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id; // ID studente per il form
                option.textContent = `${student.name} - ${student.class} (${student.school})`;
                select.appendChild(option);
            });
        })
        .catch(() => showAlert('Errore caricamento studenti', 'danger'));
    }

// Carica studenti
function loadStudents() {
    const expert = gpData.currentUser.username; // Ottieni l'username dell'utente corrente
    fetch(`${GP_API_URL}students?expert=${expert}`, { // Aggiungi il parametro 'expert'
        headers: { 'X-WP-Nonce': gpData.nonce }
    })
    .then(res => res.json())
    .then(data => {
        const tbody = document.querySelector('#studentsTable tbody');
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nessuno studente trovato.</td></tr>';
            return;
        }
        tbody.innerHTML = data.map(student => `
            <tr>
                <td>${student.id}</td>
                <td>${student.name}</td>
                <td>${student.project_code}</td>
                <td>${student.class}</td>
                <td>${student.school}</td>
                <td>
                    <button class="btn btn-warning me-2" onclick="editStudent(${student.id})">Modifica</button>
                    <button class="btn btn-danger" onclick="deleteStudent(${student.id})">Elimina</button>
                </td>
            </tr>
        `).join('');
    })
    .catch(() => showAlert('Errore caricamento studenti', 'danger'));
}
    // Modale per modificare studente
    window.editStudent = async (id) => {
        try {
            const student = await fetch(`${gpData.apiUrl}students/${id}`, {
                headers: { 'X-WP-Nonce': gpData.nonce }
            }).then(res => res.json());
            if (!student) throw new Error('Studente non trovato');
    
            // Carica scuole disponibili
            const schools = await fetch(GP_API_URL + 'schools').then(res => res.json());
            const schoolSelect = document.getElementById('editStudentSchool');
            schoolSelect.innerHTML = '<option value="">Seleziona una scuola</option>';
            schools.forEach(school => {
                const option = document.createElement('option');
                option.value = school;
                option.textContent = school;
                if (school === student.school) option.selected = true;
                schoolSelect.appendChild(option);
            });
    
            // Popola i campi
            document.getElementById('editStudentId').value = student.id;
            document.getElementById('editStudentName').value = student.name;
            document.getElementById('editProjectCode').value = student.project_code;
            document.getElementById('editStudentClass').value = student.class;
            document.getElementById('editStudentSchool').value = student.school;
            // La select è già gestita sopra
            $('#editStudentModal').modal('show');
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    };
    
    // Salva modifica studente
    window.saveStudent = async () => {
        const id = document.getElementById('editStudentId').value;
        const name = document.getElementById('editStudentName').value.trim();
        const projectCode = document.getElementById('editProjectCode').value.trim();
        const classField = document.getElementById('editStudentClass').value.trim();
        const school = document.getElementById('editStudentSchool').value.trim();
        if (!name || !projectCode || !classField|| !school) {
            return showAlert('Compila tutti i campi obbligatori.', 'warning');
        }
        try {
            const res = await fetch(GP_API_URL + `students/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gpData.nonce },
                body: JSON.stringify({ name, project_code: projectCode, class: classField, school })
            });
            if (!res.ok) {
                const error = await res.json();
                throw new Error(error.message || 'Errore server.');
            }
            $('#editStudentModal').modal('hide');
            loadStudents();
            showAlert('Alunno modificato!', 'success');
        } catch (err) {
            showAlert(err.message, 'danger'); // Mostra l'errore specifico
        }
    };

    // Elimina studente
    window.deleteStudent = function(id) {
        if (!confirm('Sei sicuro di voler eliminare questo studente?')) return;
        fetch(GP_API_URL + `students/${id}`, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': gpData.nonce }
        })
        .then(() => {
            loadStudents();
            showAlert('Studente eliminato.', 'success');
        })
        .catch(() => showAlert('Errore eliminazione studente.', 'danger'));
    };

    // Carica lezioni
    function loadLessons() {
        fetch(GP_API_URL + 'lessons', {
            headers: { 'X-WP-Nonce': gpData.nonce }
        })
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#lessonsTable tbody');
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">Nessuna lezione trovata.</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(lesson => `
                <tr>
                    <td>${lesson.student_name}</td>
                    <td>${lesson.student_class}</td>
                    <td>${lesson.project_code}</td>
                    <td>${lesson.lesson_date}</td>
                    <td>${lesson.start_time}</td>
                    <td>${lesson.end_time}</td>
                    <td>
                        <button class="btn btn-warning me-2" onclick="openEditLessonModal(${lesson.lesson_id})">Modifica</button>
                        <button class="btn btn-danger" onclick="deleteLesson(${lesson.lesson_id})">Elimina</button>
                    </td>
                </tr>
            `).join('');
        })
        .catch(() => showAlert('Errore caricamento lezioni.', 'danger'));
        loadStudentsSelect();
    }
        // Nuova funzione per popolare la select della modale di modifica
        function loadStudentsForLessonModal() {
            fetch(`${GP_API_URL}students?expert=${gpData.currentUser.username}`, {
                headers: { 'X-WP-Nonce': gpData.nonce }
            })
            .then(res => res.json())
            .then(data => {
                const select = document.getElementById('editStudentSelect');
                select.innerHTML = '<option value="">Seleziona uno studente</option>';
                data.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = student.name;
                    select.appendChild(option);
                });
            })
            .catch(() => showAlert('Errore caricamento studenti.', 'danger'));
        }
        

    // Modale per modificare lezione
    window.openEditLessonModal = async (id) => {
        try {
            const lesson = await fetch(`${gpData.apiUrl}lessons/${id}`, {
                headers: { 'X-WP-Nonce': gpData.nonce }
            }).then(res => res.json());
            if (!lesson) {
                return showAlert('Lezione non trovata.', 'warning');
            }
            
            // Imposta i campi
            document.getElementById('editLessonId').value = lesson.lesson_id;
            document.getElementById('editStudentId').value = lesson.student_id;
            document.getElementById('editLessonDate').value = lesson.lesson_date;
            document.getElementById('editStartTime').value = lesson.start_time;
            document.getElementById('editEndTime').value = lesson.end_time;
    
            // Mostra la modale con Bootstrap 5
            const modalElement = document.getElementById('editLessonModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    };
    // Popola Select Modifica Lezione
    async function populateEditStudentSelect() {
        const expert = gpData.currentUser.username;
        const students = await fetch(`${GP_API_URL}students?expert=${expert}`, {
            headers: { 'X-WP-Nonce': gpData.nonce }
        }).then(res => res.json());
        
        const select = document.getElementById('editStudentSelect');
        select.innerHTML = '<option value="">Seleziona uno studente</option>';
        
        students.forEach(student => {
            const option = document.createElement('option');
            option.value = student.id;
            option.textContent = `${student.name} - ${student.class} (${student.school})`;
            select.appendChild(option);
        });
    }

    // Salva modifica lezione
    window.saveLesson = async () => {
        const id = document.getElementById('editLessonId').value;
        const studentId = document.getElementById('editStudentSelect').value.trim();
        const lessonDate = document.getElementById('editLessonDate').value.trim();
        const startTime = document.getElementById('editStartTime').value.trim();
        const endTime = document.getElementById('editEndTime').value.trim();
    
        if (!studentId || !lessonDate || !startTime || !endTime) {
            return showAlert('Compila tutti i campi obbligatori.', 'warning');
        }
    
        try {
            const res = await fetch(GP_API_URL + `lessons/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gpData.nonce },
                body: JSON.stringify({
                    student_id: studentId,
                    lesson_date: lessonDate,
                    start_time: startTime,
                    end_time: endTime
                })
            });
            if (!res.ok) {
                throw new Error('Errore durante il salvataggio.');
            }
            $('#editLessonModal').modal('hide');
            loadLessons();
            populateStudentSelect();
            showAlert('Lezione modificata!', 'success');
        } catch (err) {
            showAlert(err.message, 'danger');
        }
    };
    // Elimina lezione
    window.deleteLesson = function(id) {
        if (!confirm('Sei sicuro di voler eliminare questa lezione?')) return;
        fetch(GP_API_URL + `lessons/${id}`, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': gpData.nonce }
        })
        .then(() => {
            loadLessons();
            showAlert('Lezione eliminata.', 'success');
        })
        .catch(() => showAlert('Errore eliminazione lezione.', 'danger'));
    };

    // Aggiungi studente
    document.querySelector('#studentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('studentName').value.trim();
        const projectCode = document.getElementById('projectCode').value.trim();
        const classField = document.getElementById('studentClass').value.trim();
        const school = document.getElementById('studentSchool').value.trim();
        if (!name || !projectCode || !classField || !school) {
            return showAlert('Compila tutti i campi obbligatori.', 'warning');
        }
        try { 
            const res = await fetch(GP_API_URL + 'students', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gpData.nonce },
                body: JSON.stringify({ name, project_code: projectCode, class: classField, school })
            });
            if (!res.ok) {
                const error = await res.json();
                throw new Error(error.message || 'Errore server.');
            }
            document.querySelector('#studentForm').reset();
            loadStudents();
            populateStudentSelect();
            showAlert('Studente creato!', 'success');
        } catch (err) {
            showAlert(err.message, 'danger');
        }
    });

    // Aggiungi lezione
    document.querySelector('#lessonForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const studentSelect = document.getElementById('studentSelect');
        const studentId = studentSelect.value.trim(); // Legge la select corretta
        const lessonDate = document.getElementById('lessonDate').value.trim();
        const startTime = document.getElementById('startTime').value.trim();
        const endTime = document.getElementById('endTime').value.trim();
        if (!studentId || !lessonDate || !startTime || !endTime) {
            return showAlert('Compila tutti i campi obbligatori.', 'warning');
        }
        try {
            const res = await fetch(GP_API_URL + 'lessons', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gpData.nonce },
                body: JSON.stringify({ student_id: studentId, lesson_date: lessonDate, start_time: startTime, end_time: endTime })
            });
            if (!res.ok) {
                const error = await res.json();
                throw new Error(error.message || 'Errore server.');
            }
            document.querySelector('#lessonForm').reset();
            loadLessons();
            showAlert('Lezione creata!', 'success');
        } catch (err) {
            showAlert(err.message, 'danger');
        }
    });
    // Gestione form Aggiungi Esperto
    document.querySelector('#addExpertForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('name').value.trim();
        const surname = document.getElementById('surname').value.trim();
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();
        const hourlyRate = parseFloat(document.getElementById('hourlyRate').value); // Converti a float
    
        if (!name || !surname || !username || !password || isNaN(hourlyRate)) {
            showAlert('Compila tutti i campi obbligatori.', 'warning');
            return;
        }
    try {
        const res = await fetch(GP_API_URL + 'experts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': gpData.nonce
            },
            body: JSON.stringify({ name, surname, username, password, hourly_rate: hourlyRate })
        });
        if (res.ok) {
            document.getElementById('addExpertForm').reset();
            loadExperts();
            showAlert('Esperto creato!', 'success');
        } else {
            throw new Error('Errore durante la creazione dell\'esperto.');
        }
    } catch (err) {
        showAlert(err.message, 'danger');
    }
});
async function calculateBalance() {
    try {
        const expert = gpData.currentUser.username;
        // Aggiungi il nonce e rimuovi il parametro 'expert' dalla URL
        const res = await fetch(GP_API_URL + 'lessons', {
            headers: { 'X-WP-Nonce': gpData.nonce }
        });
        if (!res.ok) throw new Error('Accesso non autorizzato');
        const lessons = await res.json();
        
        // Ottieni il costo orario dell'esperto tramite nuovo endpoint
        const expertRes = await fetch(GP_API_URL + 'experts/me', {
            headers: { 'X-WP-Nonce': gpData.nonce }
        });
        if (!expertRes.ok) throw new Error('Errore recupero dati esperto');
        const expertData = await expertRes.json();
        // All'interno di calculateBalance()
if (!Array.isArray(lessons)) {
    return showAlert('Formato dati lezioni non valido', 'danger');
}
        
        // Calcolo ore e saldo
        const totalHours = lessons.reduce((acc, lesson) => {
            const start = new Date(`1970-01-01T${lesson.start_time}`);
            const end = new Date(`1970-01-01T${lesson.end_time}`);
            return acc + (end - start) / 3600000; // Converti ms in ore
        }, 0);
        const balance = totalHours * expertData.hourly_rate;
        document.getElementById('balanceDisplay').textContent = `${balance.toFixed(2)} €`;
    } catch (error) {
        showAlert(error.message, 'danger');
        document.getElementById('balanceDisplay').textContent = '0.00 €';
    }
}
 

// Aggiungi nel DOMContentLoaded
calculateBalance();
//Popola Filtri Esperto
async function populateFilters() {
    // Carica scuole
    const schools = await fetch(GP_API_URL + 'schools').then(res => res.json());
    populateSelect('#schoolFilter', schools);
    
    // Carica codici progetto
    const codes = await fetch(GP_API_URL + 'project-codes').then(res => res.json());
    populateSelect('#projectCodeFilter', codes);
}

// Funzione ausiliaria per popolare select
function populateSelect(selector, options) {
    const select = document.querySelector(selector);
    if (!select) return;
    select.innerHTML = '<option value="">Tutti</option>';
    options.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt;
        option.textContent = opt;
        select.appendChild(option);
    });
}


// Funzione per applicare i filtri
window.applyFilters = () => {
    const school = document.getElementById('schoolFilter')?.value || '';
    const projectCode = document.getElementById('projectCodeFilter')?.value || '';
    const startDate = document.getElementById('startDate')?.value || '';
    const endDate = document.getElementById('endDate')?.value || '';
    
    loadActivityReport(school, projectCode, startDate, endDate);
};
// Attività Report
async function loadActivityReport() {
    try {
        const expert = gpData.currentUser.username;
        const params = new URLSearchParams({
            expert: expert,
            school: '',
            project_code: '',
            start_date: '',
            end_date: ''
        }).toString();

        const data = await fetch(GP_API_URL + 'activity-report?' + params, {
            headers: { 'X-WP-Nonce': gpData.nonce }
        }).then(res => res.json());

        const ctx = document.getElementById('activityChart');
        if (!ctx) {
            return showAlert('Canvas del grafico non trovato.', 'danger');
        }

        // Elimina il grafico esistente
        if (Chart.getChart(ctx.id)) {
            Chart.getChart(ctx.id).destroy();
        }

        if (data.expert_activities.length > 0) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.expert_activities.map(a => a.expert_name),
                    datasets: [{
                        label: 'Ore Totali',
                        data: data.expert_activities.map(a => a.total_hours),
                        backgroundColor: '#007BFF',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => `${v}h` }
                        }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        } else {
            ctx.style.display = 'none';
            showAlert('Nessun dato disponibile per il grafico.', 'warning');
        }
    } catch (err) {
        console.error('Errore caricamento grafico:', err);
    }
}
    // Logout
    document.querySelector('#logoutButton')?.addEventListener('click', async () => {
        try {
            const res = await fetch(GP_API_URL + 'auth/logout', {
                method: 'POST',
                headers: { 'X-WP-Nonce': gpData.nonce }
            });
            if (res.ok) {
                window.location.href = gpData.dashboardUrl;
            }
        } catch (err) {
            showAlert('Errore durante il logout.', 'danger');
        }
    });
    async function populateStudentSelect() {
        const expert = gpData.currentUser.username;
        const students = await fetch(`${GP_API_URL}students?expert=${expert}`, {
            headers: { 'X-WP-Nonce': gpData.nonce }
        }).then(res => res.json());
        
        // Popola la select principale (aggiunta lezione)
        const mainSelect = document.getElementById('studentSelect');
        mainSelect.innerHTML = '<option value="">Seleziona uno studente</option>';
        students.forEach(student => {
            const option = document.createElement('option');
            option.value = student.id;
            option.textContent = `${student.name} - ${student.class} (${student.school})`;
            mainSelect.appendChild(option);
        });
    
        // Popola la select della modale (modifica lezione)
        const modalSelect = document.getElementById('editStudentSelect');
        if (modalSelect) {
            modalSelect.innerHTML = '<option value="">Seleziona uno studente</option>';
            students.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id;
                option.textContent = `${student.name} - ${student.class} (${student.school})`;
                modalSelect.appendChild(option);
            });
        }
    }
    // Carica dati all'avvio
    if (gpData.currentUser && gpData.currentUser.username) {
        loadSchools(); // Carica scuole
        loadStudents();
        loadLessons();
        populateStudentSelect();
        populateEditStudentSelect();
        loadStudentsSelect();
        populateFilters(); // <--- AGGIUNTA
        loadActivityReport(); // <--- AGGIUNTA
        loadStudentsForLessonModal();
        calculateBalance(); // Calcola saldo
        
    }
    
});
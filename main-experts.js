document.addEventListener('DOMContentLoaded', () => {
    const GP_API_URL = gpData.apiUrl;
    const IS_ADMIN = gpData.currentUser.isAdmin;

    // 1. Funzione per alert
    function showAlert(message, type) {
        const div = document.createElement('div');
        div.className = `alert alert-${type} fixed-top m-3`;
        div.textContent = message;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 3000);
    }
 

    // 2. Popola filtri (Scuola, Esperti, Codici Progetto)
    async function populateFilters() {
        try {
            // Ottieni scuole
            const schools = await fetch(GP_API_URL + 'schools')
                .then(res => res.json());
            populateSelect('#schoolFilter', schools);

            // Ottieni esperti
            const experts = await fetch(GP_API_URL + 'experts-username')
                .then(res => res.json());
            populateSelect('#expertFilter', experts);

            // Ottieni codici progetto
            const codes = await fetch(GP_API_URL + 'project-codes')
                .then(res => res.json());
            populateSelect('#projectCodeFilter', codes);

        } catch (err) {
            showAlert('Errore caricamento filtri', 'danger');
        }
    }

    // 3. Funzione ausiliaria per popolare select
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

    // 4. Applica filtri
    window.applyFilters = () => {
        const school = document.getElementById('schoolFilter').value;
        const expert = document.getElementById('expertFilter').value;
        const projectCode = document.getElementById('projectCodeFilter').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        loadActivityReport(school, expert, projectCode, startDate, endDate);
    };

    // 5. Carica report attività con filtri
    function loadActivityReport(
        school = '',
        expert = '',
        projectCode = '',
        startDate = '',
        endDate = ''
    ) {
        const params = new URLSearchParams({
            school,
            expert,
            project_code: projectCode,
            start_date: startDate,
            end_date: endDate
        }).toString();

        fetch(`${GP_API_URL}activity-report?${params}`, {
            headers: { 'X-WP-Nonce': gpData.nonce }
        })
        .then(res => res.json())
        .then(data => {
            // Popola tabella attività esperti
            const activityTable = document.querySelector('#activityTable tbody');
            activityTable.innerHTML = data.expert_activities?.length > 0
                ? data.expert_activities.map(row => `
                    <tr>
                        <td>${row.expert_name}</td>
                        <td>${row.total_lessons}</td>
                        <td>${parseFloat(row.total_hours).toFixed(2)}h</td>
                    </tr>
                `).join('')
                : '<tr><td colspan="3" class="text-center">Nessuna attività trovata</td></tr>';

            // Popola tabella dettagli lezioni (aggiunto campo 'school')
            const lessonDetailsTable = document.querySelector('#lessonDetailsTable tbody');
            lessonDetailsTable.innerHTML = data.lesson_details?.length > 0
                ? data.lesson_details.map(row => `
                    <tr>
                        <td>${row.expert_name}</td>
                        <td>${row.student_name}</td>
                        <td>${row.school}</td> <!-- Campo critico aggiunto -->
                        <td>${row.lesson_date}</td>
                        <td>${row.start_time}</td>
                        <td>${row.end_time}</td>
                    </tr>
                `).join('')
                : '<tr><td colspan="6" class="text-center">Nessun dettaglio lezioni</td></tr>';

            // Aggiorna grafico
            const ctx = document.getElementById('activityChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.expert_activities.map(a => a.expert_name),
                    datasets: [{
                        label: 'Ore Totali',
                        data: data.expert_activities.map(a => a.total_hours),
                        backgroundColor: '#007BFF',
                        borderColor: '#0056b3',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => `${v}h` }
                        }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        })
        .catch(() => showAlert('Errore caricamento report', 'danger'));
    }

    // 6. Carica esperti
    function loadExperts() {
        fetch(GP_API_URL + 'experts', { headers: { 'X-WP-Nonce': gpData.nonce } })
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#expertsTable tbody');
                tbody.innerHTML = data.map(expert => `
                    <tr>
                        <td>${expert.id}</td>
                        <td>${expert.name}</td>
                        <td>${expert.surname}</td>
                        <td>${expert.username}</td>
                        <td>${expert.hourly_rate} €</td>
                        <td>
                            <button class="btn btn-warning me-2" onclick="editExpert(${expert.id})">Modifica</button>
                            <button class="btn btn-danger" onclick="deleteExpert(${expert.id})">Elimina</button>
                        </td>
                    </tr>
                `).join('');
            })
            .catch(() => showAlert('Errore caricamento esperti', 'danger'));
    }

    // 7. Modale modifica esperto
    window.editExpert = async (id) => {
        try {
            const expert = await fetch(`${GP_API_URL}experts/${id}`, {
                headers: { 'X-WP-Nonce': gpData.nonce }
            }).then(res => res.json());

            if (!expert) throw new Error('Esperto non trovato');
            
            document.getElementById('editExpertId').value = expert.id;
            document.getElementById('editEName').value = expert.name;
            document.getElementById('editESurname').value = expert.surname;
            document.getElementById('editEUsername').value = expert.username;
            document.getElementById('editEHourlyRate').value = expert.hourly_rate;
            document.getElementById('editEPassword').value = '';
            
            $('#editExpertModal').modal('show');
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    };

    // 8. Salva modifica esperto
    window.saveExpert = async () => {
        const id = document.getElementById('editExpertId').value;
        const name = document.getElementById('editEName').value.trim();
        const surname = document.getElementById('editESurname').value.trim();
        const username = document.getElementById('editEUsername').value.trim();
        const hourlyRate = parseFloat(document.getElementById('editEHourlyRate').value);
        const password = document.getElementById('editEPassword').value.trim();

        if (!name || !surname || !username || isNaN(hourlyRate)) {
            return showAlert('Compila tutti i campi obbligatori.', 'warning');
        }

        try {
            const res = await fetch(`${GP_API_URL}experts/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': gpData.nonce
                },
                body: JSON.stringify({
                    name, surname, username, password,
                    hourly_rate: parseFloat(hourlyRate) // Aggiungi questo campo
                })
            });
            
            if (!res.ok) throw new Error('Errore durante il salvataggio');
            $('#editExpertModal').modal('hide');
            loadExperts();
            showAlert('Esperto modificato!', 'success');
        } catch (err) {
            showAlert(err.message, 'danger');
        }
    };

    // 9. Elimina esperto
    window.deleteExpert = (id) => {
        if (!confirm('Sei sicuro di voler eliminare questo esperto?')) return;
        fetch(`${GP_API_URL}experts/${id}`, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': gpData.nonce }
        })
        .then(() => {
            loadExperts();
            showAlert('Esperto eliminato.', 'success');
        })
        .catch(() => showAlert('Errore eliminazione esperto.', 'danger'));
    };

    // 10. Carica studenti (con filtri)
    function loadStudents(expert = '', projectCode = '', school = '') {
        const params = new URLSearchParams({
            expert,
            project_code: projectCode,
            school
        }).toString();

        fetch(`${GP_API_URL}students?${params}`, {
            headers: { 'X-WP-Nonce': gpData.nonce }
        })
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#studentsTable tbody');
            tbody.innerHTML = data?.length > 0
                ? data.map(student => `
                    <tr>
                        <td>${student.id}</td>
                        <td>${student.name}</td>
                        <td>${student.project_code}</td>
                        <td>${student.class}</td>
                        <td>${student.school}</td>
                        <td>${student.expert}</td>
                        <td>
                            <button class="btn btn-success me-2" onclick="editStudent(${student.id})">Modifica</button>
                            <button class="btn btn-danger" onclick="deleteStudent(${student.id})">Elimina</button>
                        </td>
                    </tr>
                `).join('')
                : '<tr><td colspan="7" class="text-center">Nessuno studente trovato</td></tr>';
        })
        .catch(() => showAlert('Errore caricamento studenti', 'danger'));
    }

    // 11. Modale modifica studente
    window.editStudent = async (id) => {
        try {
            const student = await fetch(`${gpData.apiUrl}students/${id}`, {
                headers: { 'X-WP-Nonce': gpData.nonce }
            }).then(res => res.json());
            
            // Carica scuole nella select
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
            if (!student) throw new Error('Studente non trovato');
            
            document.getElementById('editStudentId').value = student.id;
            document.getElementById('editStudentName').value = student.name;
            document.getElementById('editProjectCode').value = student.project_code;
            document.getElementById('editStudentClass').value = student.class;
            document.getElementById('editStudentSchool').value = student.school; // Popola 'school'
            
            $('#editStudentModal').modal('show');
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    };

    // 12. Salva modifica studente
    window.saveStudent = async () => {
        const id = document.getElementById('editStudentId').value;
        const name = document.getElementById('editStudentName').value.trim();
        const projectCode = document.getElementById('editProjectCode').value.trim();
        const classField = document.getElementById('editStudentClass').value.trim();
        const school = document.getElementById('editStudentSchool').value.trim();

        if (!name || !projectCode || !classField || !school) {
            return showAlert('Compila tutti i campi obbligatori.', 'warning');
        }

        try {
            const res = await fetch(`${GP_API_URL}students/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': gpData.nonce
                },
                body: JSON.stringify({
                    name,
                    project_code: projectCode,
                    class: classField,
                    school: document.getElementById('editStudentSchool').value// Aggiunto campo 'school'
                })
            });
            
            if (!res.ok) {
                const error = await res.json();
                throw new Error(error.message || 'Errore server.');
            }
            $('#editStudentModal').modal('hide');
            loadStudents();
            showAlert('Alunno modificato!', 'success');
        } catch (err) {
            showAlert(err.message, 'danger');
        }
    };
// Caricamento scuole
function loadSchools() {
    fetch(GP_API_URL + 'schools')
        .then(res => res.json())
        .then(schools => {
            const tbody = document.querySelector('#schoolsTable tbody');
            tbody.innerHTML = schools.map(school => `
                <tr>
                    <td>${school.id}</td>
                    <td>${school.name}</td>
                    <td>
                        <button class="btn btn-warning btn-sm" 
                                onclick="openEditSchoolModal(${school.id}, '${school.name}')">
                            Modifica
                        </button>
                        <button class="btn btn-danger btn-sm" 
                                onclick="deleteSchool(${school.id})">
                            Elimina
                        </button>
                    </td>
                </tr>
            `).join('');
        });
}
// Apri modale modifica
window.openEditSchoolModal = (id, name) => {
    document.getElementById('editSchoolId').value = id;
    document.getElementById('editSchoolName').value = name;
    $('#editSchoolModal').modal('show');
};

// Salva modifica
window.saveSchool = () => {
    const id = document.getElementById('editSchoolId').value;
    const name = document.getElementById('editSchoolName').value.trim();
    
    fetch(`${GP_API_URL}schools/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': gpData.nonce
        },
        body: JSON.stringify({ name })
    })
    .then(() => {
        $('#editSchoolModal').modal('hide');
        loadSchools();
        showAlert('Scuola aggiornata', 'success');
    });
};

// Aggiungi scuola
document.querySelector('#schoolForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('schoolName').value.trim();
    if (!name) return;
    try {
        const res = await fetch(GP_API_URL + 'schools', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-WP-Nonce': gpData.nonce 
            },
            body: JSON.stringify({ name })
        });
        if (res.ok) {
            document.getElementById('schoolForm').reset();
            loadSchools();
            showAlert('Scuola creata!', 'success');
        }
    } catch (err) {
        showAlert('Errore creazione scuola', 'danger');
    }
});

// Elimina scuola
window.deleteSchool = (id) => {
    if (!confirm('Sei sicuro?')) return;
    fetch(GP_API_URL + `schools/${id}`, {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': gpData.nonce }
    })
    .then(() => {
        loadSchools();
        showAlert('Scuola eliminata', 'success');
    });
};
async function populateStudentSelect() {
    const expert = gpData.currentUser.username;
    const students = await fetch(`${GP_API_URL}students?expert=${expert}`, {
        headers: { 'X-WP-Nonce': gpData.nonce }
    }).then(res => res.json());
    
    const select = document.getElementById('studentSelect');
    select.innerHTML = '<option value="">Seleziona uno studente</option>';
    
    students.forEach(student => {
        const option = document.createElement('option');
        option.value = student.id;
        option.textContent = `${student.name} - ${student.class} (${student.school})`;
        select.appendChild(option);
    });
}
 

    // 13. Avvio iniziale
    if (IS_ADMIN) {
        populateFilters();
        loadExperts();
        loadActivityReport(); // Carica report senza filtri all'avvio
        loadStudents();
        loadSchools();
        populateStudentSelect();
        
    }
});
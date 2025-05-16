<?php
/* Template Name: Dashboard Esperto */
get_header();

?>

<div class="container-fluid">
    <?php if (!is_user_logged_in()): ?>
        <!-- Form di login -->
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">Accedi</div>
                    <div class="card-body">
                        <form id="loginForm">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="loginUsername" placeholder="Username" required autocomplete="username">
                            </div>
                            <div class="mb-3">
                                <input type="password" class="form-control" id="loginPassword" placeholder="Password" required autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Accedi</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
           <!-- Sidebar -->
           <div class="col-md-3 d-none d-md-block">
                <div class="sidebar-menu">
                    <a href="#activity-summary" class="btn btn-primary w-100 mb-3">Riepilogo Attività</a>
                    <a href="#students" class="btn btn-success w-100 mb-3">Elenco Alunni</a>
                    <a href="#lessons" class="btn btn-warning w-100 mb-3">Elenco Lezioni</a>
                
                </div>
            </div>

            <!-- Contenuto Principale -->
            <div class="col-md-9">
                <!-- Sezione Saldo Maturato -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Saldo Maturato</h5>
                        <h2 id="balanceDisplay">0.00 €</h2>
                    </div>
                </div>

                <!-- Riepilogo Attività -->
                <div id="activity-summary" class="card mb-4">
                    <div class="card-header">Riepilogo Attività</div>
                    <div class="card-body">
                    <div class="row g-3 mb-4">
                    <div class="row g-3 mb-4">
    <div class="col-md-3">
        <select class="form-control" id="schoolFilter"></select>
    </div>
    <div class="col-md-3">
        <input type="date" class="form-control" id="startDate">
    </div>
    <div class="col-md-3">
        <input type="date" class="form-control" id="endDate">
    </div>
    <div class="col-md-3">
        <button class="btn btn-primary w-100" onclick="applyFilters()">Applica Filtri</button>
    </div>
</div>
<div id="chart-container">
            <canvas id="activityChart" width="400" height="300"></canvas>
        </div>
        <!-- Messaggio per dati vuoti -->
        <div id="chart-message" class="text-center mt-5" style="display: none">
            Nessun dato per il grafico.
        </div>
                    </div>
                </div>

                <!-- Elenco Alunni -->
                <div id="students" class="card mb-4">
                    <div class="card-header">Aggiungi Alunno</div>
                    <div class="card-body">
                        <form id="studentForm">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="studentName" placeholder="Nome e Cognome" required>
                            </div>
                          
                            <div class="mb-3">
                                <input type="text" class="form-control" id="projectCode" placeholder="Codice Progetto" required>
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control" id="studentClass" placeholder="Classe" required>
                            </div>
                            <div class="mb-3">
    <select class="form-control" id="studentSchool" required>
        <option value="">Seleziona una scuola</option>
    </select>
</div>
                            <button type="submit" class="btn btn-primary w-100">Aggiungi</button>
                        </form>
                    </div>
                    <div id="students-list" class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Elenco Alunni</h5>
                    <table id="studentsTable" class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Codice Progetto</th>
                                <th>Classe</th>
                                <th>Scuola</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

                <!-- Elenco Lezioni -->
                <div id="lessons" class="card mb-4">
                    <div class="card-header">Aggiungi Lezione</div>
                    <div class="card-body">
                        <form id="lessonForm">
                            <div class="mb-3">
                                <select class="form-control" id="studentSelect" required>
                                    <option value="">Seleziona uno studente</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <input type="date" class="form-control" id="lessonDate" required>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="time" class="form-control" id="startTime" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="time" class="form-control" id="endTime" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning w-100 mt-3">Aggiungi Lezione</button>
                        </form>
                    </div>
                    <div class="card-header mt-4">Elenco Lezioni</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="lessonsTable" class="table">
                                <thead>
                                    <tr>
                                        <th>Alunno</th>
                                        <th>Classe</th>
                                        <th>Codice Progetto</th>
                                        <th>Data</th>
                                        <th>Ora Inizio</th>
                                        <th>Ora Fine</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
     
</div>
        <!-- Modali -->
        <!-- Modale per modifica studente -->
        <div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5>Modifica Alunno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editStudentForm">
                    <input type="hidden" id="editStudentId">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="editStudentName" placeholder="Nome" required>
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="editProjectCode" placeholder="Codice Progetto" required>
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="editStudentClass" placeholder="Classe" required>
                    </div>
                    <div class="mb-3">
    <select class="form-control" id="editStudentSchool" required>
        <option value="">Seleziona una scuola</option>
    </select>
</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="button" class="btn btn-success" onclick="saveStudent()">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

        <!-- Modale per modifica lezione -->
        <div class="modal fade" id="editLessonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5>Modifica Lezione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editLessonForm">
                    <input type="hidden" id="editLessonId">
                    <div class="mb-3">
                        <select class="form-control" id="editStudentSelect" required>
                            <option value="">Seleziona uno studente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <input type="date" class="form-control" id="editLessonDate" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="time" class="form-control" id="editStartTime" required>
                        </div>
                        <div class="col-md-6">
                            <input type="time" class="form-control" id="editEndTime" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="button" class="btn btn-warning" onclick="saveLesson()">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Carica React, Babel e Bootstrap -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Contenitore React -->
<div id="react-root"></div>
        
        <div id="react-root"></div>
 
<div class="container-fluid mt-4">
    <button id="logoutButton" class="btn btn-danger w-100">Logout</button>
</div>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
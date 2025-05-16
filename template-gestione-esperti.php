<?php
/* Template Name: Gestione Esperti */
get_header();
?>
<?php if (current_user_can('administrator')): ?>
<div class="container-fluid">
    <!-- Contenitore principale con griglia Bootstrap -->
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 d-none d-md-block">
            
            <div class="sidebar-menu">
                <ul>
               <li><a href="#add-expert" class="btn btn-primary w-100 mb-3">Aggiungi Esperto</a></li>
               <li><a href="#experts-list" class="btn btn-secondary w-100 mb-3">Elenco Esperti</a></li>
               <li><a href="#experts-list" class="btn btn-secondary w-100 mb-3">Aggiungi Scuola</a></li>
               <li><a href="#activity-report" class="btn btn-warning w-100 mb-3">Report Attività</a></li>
               <li><a href="#students-list" class="btn btn-success w-100 mb-3">Elenco Alunni</a></li>
             
            </ul>
            </div>
        </div>

        <!-- Contenuto principale con classe per margini -->
        <div class="col-md-9 main-content">
            <!-- Form Aggiunta Esperto -->
            <div id="add-expert" class="card mb-4">
                <div class="card-header bg-primary text-white">Aggiungi Esperto</div>
                <div class="card-body">
                    <form id="addExpertForm">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="name" 
                                   placeholder="Nome" required>
                        </div>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="surname" 
                                   placeholder="Cognome" required>
                        </div>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="username" 
                                   placeholder="Username" required autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <input type="password" class="form-control" id="password" 
                                   placeholder="Password" required autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <input type="number" step="0.01" class="form-control" 
                                   id="hourlyRate" placeholder="Costo Orario (€)" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Salva</button>
                    </form>
                </div>
            </div>

            <!-- Elenco Esperti -->
            <div id="experts-list" class="card mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="expertsTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Cognome</th>
                                    <th>Username</th>
                                    <th> Costo Orario </th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
           <!---- Elenco Scuole ------->
            <div class="card mb-4">
    <div class="card-header bg-primary text-white">Gestione Scuole</div>
    <div class="card-body">
        <form id="schoolForm">
            <div class="mb-3">
                <input type="text" class="form-control" id="schoolName" placeholder="Nome Scuola" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Aggiungi Scuola</button>
        </form>
        <table id="schoolsTable" class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
    </tbody>
        </table>
    </div>
</div>
<!-- Aggiungi il modal di modifica -->
<div class="modal fade" id="editSchoolModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5>Modifica Scuola</h5>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editSchoolId">
                <input type="text" class="form-control" id="editSchoolName" required>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="saveSchool()">Salva</button>
            </div>
        </div>
    </div>
</div>

            <!-- Report Attività -->
            <div id="activity-report" class="card mb-4">
                <div class="card-body">
                    <!-- Form con il nuovo campo projectCodeFilter -->
                    <form id="filterForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                            <h6>Scuole</h6>
                                <select class="form-control" id="schoolFilter">
                                    <option value="">Tutte le Scuole</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                            <h6>Esperti</h6>
                                <select class="form-control" id="expertFilter">
                                    <option value="">Tutti gli Esperti</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                            <h6>Codice Progetto</h6>
                                <select class="form-control" id="projectCodeFilter">
                                    <option value="">Tutti i Codici Progetto</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                            <h6>Da:</h6>
        <input type="date" class="form-control" id="startDate">
    </div>
    <div class="col-md-3">
    <h6>A:</h6>
        <input type="date" class="form-control" id="endDate">
    </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-primary w-100" onclick="applyFilters()">Filtra</button>
                            </div>
                        </div>
                    </form>
                    <div class="row g-3 mt-4">
                        <div class="col-md-6">
                        <h6>Dettagli Esperti</h6>
                            <table id="activityTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Esperto</th>
                                        <th>Lezioni Totali</th>
                                        <th>Ore Totali</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                        <h6>Dettagli Lezioni</h6>
                        <div class="table-responsive">                        
                            <table id="lessonDetailsTable" class="table table-bordered">
                           
                                <thead>
                                    <tr>
                                        <th>Esperto</th>
                                        <th>Alunno</th>
                                        <th>Scuola</th>
                                        <th>Data</th>
                                        <th>Orario Inizio</th>
                                        <th>Orario Fine </th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
</div>
 
                <canvas id="activityChart" height="400"></canvas>
                </div>
            </div>

            <!-- Elenco Alunni -->
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
                                <th>Esperto</th>
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


<!-- Modali -->
<!-- Modale Modifica Esperto -->
<div class="modal fade" id="editExpertModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-white">
        <h5>Modifica Esperto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editExpertForm">
          <input type="hidden" id="editExpertId">
          <div class="mb-3">
            <input type="text" class="form-control" id="editEName" required>
          </div>
          <div class="mb-3">
            <input type="text" class="form-control" id="editESurname" required>
          </div>
          <div class="mb-3">
            <input type="text" class="form-control" id="editEUsername" required>
          </div>
          <div class="mb-3">
    <input type="number" step="0.01" class="form-control" 
           id="editEHourlyRate" placeholder="Costo Orario" required>
</div>
          <div class="mb-3">
            <input type="password" class="form-control" id="editEPassword"
                   placeholder="Lascia vuoto per mantenere la password attuale">
          </div>
          <button type="button" class="btn btn-success" onclick="saveExpert()">
            Salva Modifiche
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
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
                        <input type="text" class="form-control" id="editStudentName" placeholder="Nome" required >
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="editProjectCode" placeholder="Codice Progetto" required >
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="editStudentClass" placeholder="Classe" required >
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
<!-- Carica React, Babel e Bootstrap -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Contenitore React -->
<div id="react-root"></div>
        <!-- Pulsante di logout -->
        <div class="col-12 mt-4">
            <button id="logoutButton" class="btn btn-danger w-100">Esci</button>
        </div>
        <div id="react-root"></div>
        

<?php else: ?>
<div class="container mt-5">
    <div class="alert alert-danger text-center">Accesso non autorizzato.</div>
</div>
<?php endif; ?>
<?php get_footer(); ?>
# Gestione Presenze WordPress Plugin

**Versione**: 3.8.3  
**Autore**: Mirabilis  
**Licenza**: GPLv2 o successiva (https://www.gnu.org/licenses/gpl-2.0.html )

---

## 📌 Funzionalità Principali

### 1. **Gestione Esperti**
- **Registrazione/Login**: Esperti possono registrarsi e accedere tramite username/password.
- **Ruoli e Permessi**:
  - **Esperti**: Gestione studenti e lezioni assegnati a loro.
  - **Admin**: Accesso completo a tutte le funzionalità, inclusa la gestione degli esperti.
- **Modifica/Eliminazione Esperti**: Solo per Admin.

### 2. **Gestione Alunni**
- **Aggiungi/Modifica/Elenco Studenti**: Gli esperti possono gestire gli studenti assegnati.
- **Campi Dettagliati**: Nome, codice progetto, classe, scuola e esperto responsabile.
- **Filtro per Scuola e Codice Progetto**: Esperti e Admin possono filtrare gli studenti per scuola o progetto.

### 3. **Gestione Lezioni**
- **Pianificazione Lezioni**: Aggiunta di lezioni con data, orario inizio/fine e assegnazione all'esperto.
- **Elenco Lezioni**: Visualizzazione delle lezioni con dettagli studente, codice progetto e scuola.
- **Modifica/Eliminazione Lezioni**: Accesso per gli esperti.

### 4. **Report Attività**
- **Dashboard Grafica**: Grafico a barre che mostra le ore totali per esperto.
- **Filtraggio Avanzato**: Filtri per scuola, codice progetto, data e esperto (solo Admin).
- **Dettagli Lezioni**: Tabella con lezioni dettagliate, inclusa la scuola associata.

### 5. **Interfaccia Utente**
- **Dashboard Esperto**: Interfaccia dedicata per gli esperti con sezioni:
  - Riepilogo Attività
  - Aggiungi Alunno
  - Elenco Alunni
  - Aggiungi Lezione
  - Elenco Lezioni
- **Dashboard Admin**: Gestione globale con:
  - Elenco Esperti (con saldo maturato)
  - Report Attività con filtri
  - Gestione studenti a livello globale

---

## 🚀 Installazione

### Requisiti
- WordPress 5.8 o successiva
- PHP 7.4 o successiva
- MySQL 5.6 o successiva

### Passaggi di Installazione
1. **Carica il Plugin**:
   - Scarica il plugin e decomprimi i file.
   - Carica il plugin su WordPress tramite `Plugins > Aggiungi Nuovo > Carica` e attivalo.

2. **Crea Pagine Richieste**:
   - Crea due pagine in WordPress:
     - **Dashboard Esperto**: Imposta "Template" → "Dashboard Esperto".
     - **Gestione Esperti**: Imposta "Template" → "Gestione Esperti" (solo Admin).

3. **Database**:
   - Al primo accesso, il plugin creerà automaticamente le tabelle:
     - `wp_gp_students` (studenti)
     - `wp_gp_experts` (esperti)
     - `wp_gp_lessons` (lezioni)

4. **Ruoli WordPress**:
   - Il plugin aggiunge il ruolo **Esperto** con permessi limitati.

---

## 🛠 Configurazione

### 1. **Impostazioni Pagina**
- **Dashboard Esperto**: Usata dagli esperti per gestire lezioni e studenti.
- **Gestione Esperti**: Disponibile solo per gli Admin per:
  - Aggiungere/rimuovere esperti.
  - Visualizzare report attività e dettagli.

### 2. **Assegnazione Scuola (Opzionale)**
- Se non hai assegnato scuole agli studenti, esegui questa query SQL:
  ```sql
  UPDATE wp_gp_students
  SET school = CASE expert
      WHEN 'Nome Cognome' THEN 'ISTITUTO DEMO'
      WHEN 'Nome Cognome' THEN 'ISTITUTO DEMO'
      -- Aggiungi altri casi come mostrato nel Knowledge Base
  END
  WHERE expert IN ('Nome Cognome', 'Nome Cognome', ...);

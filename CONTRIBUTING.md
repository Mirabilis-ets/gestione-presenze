# Guidelines per le Contribuzioni al Progetto "Gestione Presenze"

Benvenuto! Siamo lieti che tu voglia contribuire al progetto **Gestione Presenze**. Questo file spiega come collaborare, mantenendo la qualità e l'ordine del codice.

---

## **Come Contribuire**

### 1. **Fork e Clone del Repository**
- Fork il repository su GitHub.
- Clona il tuo fork locale:
  ```bash
  git clone https://github.com/tuo-username/gestione-presenze.git
  ```

### 2. **Crea una Branch**
- Crea una nuova branch per ogni feature/fix:
  ```bash
  git checkout -b feature/nome-della-funzione
  ```

### 3. **Sviluppa e Testa**
- Sviluppa la tua modifica seguendo gli standard di codifica.
- Testa localmente:
  - **PHP**: Utilizza `PHPUnit` per test automatici.
  - **JavaScript**: Verifica funzionalità nel browser (es. filtri, grafici, form).
  - **Database**: Controlla le query SQL con `phpMyAdmin` o un tool simile.

### 4. **Apri una Pull Request (PR)**
- Apri una PR da GitHub con:
  - Titolo chiaro (es. `[BUGFIX] Risolto errore grafico`).
  - Descrizione dettagliata della modifica.
  - Screenshot o GIF se necessario.
  - Collegamento all'issue se presente.

---

## **Standard di Codifica**

### **PHP**
- Segui le **[WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/)**.
- Utilizza `wpdb` con `prepare()` per evitare SQL injection.
- Gestisci gli errori con `WP_Error`.
- Mantieni la struttura dei file esistenti (es. `includes/api-callbacks.php` per le API).

### **JavaScript**
- Usa `async/await` per le chiamate API.
- Segui il **[Airbnb JavaScript Style Guide](https://github.com/airbnb/javascript)**.
- Mantieni il codice modularizzato:
  - `main.js` per gli esperti.
  - `main-experts.js` per gli admin.
- Gestisci gli errori con `try/catch` e visualizza notifiche via `showAlert()`.

### **CSS**
- Usa **Bootstrap 5** e le variabili esistenti (es. `--primary`, `--secondary`).
- Organizza il codice in sezioni con commenti.
- Evita dipendenze esterne non dichiarate.

---

## **Processo di Pull Request (PR)**

### **Requisiti Minimi per una PR**
- **Titolo della PR**: Inizia con `[BUGFIX]`, `[FEATURE]`, `[DOC]`, `[REFACTOR]` (es. `[FEATURE] Aggiunta filtri per codice progetto`).
- **Descrizione**:
  - Spiega **cosa** hai modificato e **perché**.
  - Includi passaggi per riprodurre il bug (se applicabile).
  - Riporta il numero dell'issue collegata (es. `Fix #123`).
- **Codice**:
  - Mantieni la compatibilità con WordPress 5.8+.
  - Aggiungi test unitari se modifichi funzionalità critiche.
  - Non includere modifiche non correlate alla PR.

### **Processo di Review**
- Le PR vengono rivedute per:
  - **Qualità del codice** (standard, sicurezza).
  - **Test di funzionalità** (es. form, grafici, filtri).
  - **Documentazione aggiornata** (README.md, commenti inline).
- I maintainers potrebbero richiedere modifiche via *suggestions*.

---

## **Come Segnalare un Bug**

Per aprire un'**issue**, fornisci:
1. **Titolo** conciso e chiaro.
2. **Passaggi per riprodurre** il bug.
3. **Esito atteso** vs **esito reale**.
4. **Screenshot/GIF** optional ma consigliato.
5. **Ambiente**:
   - Versione di WordPress.
   - PHP e MySQL.
   - Browser (se applicabile).

---

## **Come Proporre una Nuova Funzione**

Per aprire un'**issue** di feature:
1. Descrivi la funzione e il motivo per cui è necessaria.
2. Fornisci uno schema di implementazione (se disponibile).
3. Aspetta l'approvazione dei maintainers prima di iniziare.

---

## **Testing Obbligatorio**

### **Ambiente di Test**
- **WordPress 5.8+**.
- **PHP 7.4+**.
- **MySQL 5.6+**.
- Browser moderni (Chrome, Firefox, Safari).

### **Strumenti Consigliati**
- **Postman**: Per testare gli endpoint REST.
- **PHPUnit**: Per test automatici.
- **phpcs**: Per verificare gli standard PHP:
  ```bash
  vendor/bin/phpcs --standard=WordPress src/
  ```

---

## **Regole per il Codice**

### **PHP**
- **Security**: Tutte le query SQL devono usare `prepare()`.
- **Ruoli e Permessi**: Mantieni i controlli tramite `current_user_can()`.
- **REST API**: Aggiungi nuovi endpoint in `gp-plugin.php` con `register_rest_route()`.

### **JavaScript**
- **Modularità**: Mantieni `main.js` e `main-experts.js` separati per ruoli.
- **Grafici**: Usa `Chart.js` come nel file esistente.
- **Form**: Gestisci i dati con `fetch()` e `URLSearchParams`.

### **CSS**
- **Bootstrap 5**: Mantieni la struttura esistente (es. classi `card`, `table`).
- **Variabili**: Usa le variabili CSS definite in `styles.css` (es. `--primary`).
- **Responsive**: Testa il layout su dispositivi mobili.

---

## **Checklist per le Pull Request**

- [ ] Il codice rispetta gli standard di sicurezza (es. SQL injection).
- [ ] Sono stati aggiunti test unitari (se necessario).
- [ ] La documentazione è stata aggiornata.
- [ ] La traduzione è compatibile con WordPress.
- [ ] Non sono presenti dipendenze non dichiarate.
- [ ] Il codice è formattato correttamente (es. indentazione, commenti).

---

## **Codice di Condotta**

- Segui il **[Contributor Covenant](https://www.contributor-covenant.org/)**.
- Mantieni un comportamento rispettoso e collaborativo.
- Risolvi i conflitti via discussione, non con commenti offensivi.

---

## **Directory e File Principali**

### **PHP**
- `gp-plugin.php`: Gestione attivazione, ruoli e enqueue.
- `includes/api-callbacks.php`: Endpoint REST per studenti/lezioni.
- `includes/experts-callbacks.php`: Gestione esperti e autenticazione.

### **JavaScript**
- `assets/js/main.js`: Funzionalità per gli esperti.
- `assets/js/main-experts.js`: Funzionalità per gli admin.

### **Template**
- `template-dashboard-esperto.php`: Interfaccia per gli esperti.
- `template-gestione-esperti.php`: Dashboard admin.

### **CSS**
- `assets/css/styles.css`: Stili globali e responsive.

---

## **Licenza**

- Tutte le contribuzioni devono rispettare la **GPLv2+**.
- Non includere codice con licenze incompatibili.

---

 

---

## **Note Finali**

- **Merge**: Le PR vengono messe in coda per le release mensili.
- **Backup**: Fai sempre backup del database prima di testare modifiche critiche.
- **Ambiente**: Sviluppa in locale con WordPress, PHP e MySQL supportati.

Grazie per il tuo impegno! 🚀

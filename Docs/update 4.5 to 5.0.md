# FLUSSU v4.5 → v5.0 - Analisi Architettura Completa

## 📋 Sommario Esecutivo

FLUSSU è un **workflow server BPM (Business Process Management)** basato su architettura SOA (Service Oriented Architecture). Il sistema gestisce l'esecuzione di processi (workflow) composti da blocchi interconnessi, dove ogni blocco può contenere codice PHP eseguibile, elementi UI multilingua e logica di flusso.

**Versione Attuale**: 4.5 (in transizione verso 5.0)  
**Database**: MySQL/MariaDB 10.8+  
**Linguaggio**: PHP 8.x  
**Architettura**: SOA + MVC con pattern Handler-Worker-Session  
**Nome Motore**: WoFoBot (WOrkFlOw-roBot)  

### 🎯 Caratteristiche Principali
- ✅ **Multilingua**: Supporto completo multi-language per UI ed elementi
- ✅ **BPM Editor**: Editor grafico per progettazione processi
- ✅ **SOA Ready**: API REST/JSON per integrazioni esterne
- ✅ **Automi a Stati Finiti**: Gestione processi deterministici e non
- ✅ **Multi-Channel**: Browser, Chat apps (Telegram/WhatsApp), System APIs
- ✅ **Process Versioning**: Sistema di backup e versioning workflow
- ✅ **Sub-Processes**: Supporto processi annidati
- ✅ **Multi-Flow v3.0**: Stesso processo con variabili diverse per più clienti

---

## 🏗️ Architettura del Sistema

### Diagramma Architettura Complessiva

```
┌─────────────────────────────────────────────────────────────────┐
│                    FLUSSU v2.2 ARCHITECTURE                     │
└─────────────────────────────────────────────────────────────────┘

┌──────────────────┐                  ┌──────────────────────┐
│   VERSIONING     │                  │   BPM EDITOR         │
│                  │                  │   (Frontend)         │
│  Remote/Personal │◄────────────────►│                      │
│  + Local Backup  │                  │   - Visual Designer  │
└──────────────────┘                  │   - Block Editor     │
         │                            └──────────────────────┘
         │                                      │
         ▼                                      │
┌──────────────────────────────────────────────────┐
│           REPOSITORY                             │
│      PROCESS & SUB-PROCESS                       │    ┌──────────┐
│       (Database Storage)                         │◄───│   LOG    │
│                                                  │    │ (History)│
│   - Workflow Definitions                         │    └──────────┘
│   - Block Code & UI Elements                     │
│   - Multi-language Support                       │
└──────────┬───────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────────┐
│         FLUSSU PROCESS ENGINE                    │
│           (WoFoBot Core)                         │
│                                                  │
│   ┌────────────┐  ┌────────────┐  ┌───────────┐  │
│   │  Engine.php│  │ Worker.php │  │Handler.php│  │
│   │  (Entry)   │→ │ (Execute)  │→ │(Cache/DB) │  │
│   └────────────┘  └────────────┘  └───────────┘  │
│          │              │                        │
│          └──────────────┴───────────┐            │
│                                     │            │
│                            ┌────────▼────────┐   │
│                            │   Session.php   │   │
│                            │  (State Mgmt)   │   │
│                            └─────────────────┘   │
└──────────────────────┬───────────────────────────┘
                       │
          ┌────────────┴────────────┐
          │                         │
          ▼                         ▼
┌──────────────────┐      ┌──────────────────┐
│   FLX API        │      │   MONITORING     │
│   (REST/JSON)    │      │                  │
└────────┬─────────┘      └──────────────────┘
         │
    ┌────┴────────────────────────────┐
    │                                 │
    ▼                                 ▼
┌─────────────────────┐    ┌──────────────────────┐
│  FRONTEND CHANNELS  │    │   BACKEND SYSTEMS    │
│                     │    │                      │
│  • Browsers         │    │  • PHP Integration   │
│  • Telegram         │    │  • C# Integration    │
│  • WhatsApp         │    │  • Node.js           │
│  • Typeform         │    │  • Python            │
│  • Webchat          │    │  • Other APIs        │
└─────────────────────┘    └──────────────────────┘
```

---

## 💾 Schema Database Completo

### 📊 Macro Aree Database

Il database di FLUSSU è suddiviso in **5 macro-aree logiche**:

1. **WORKFLOW** (t00-t50): Definizioni processi, blocchi, elementi
2. **MULTI-FLOW** (t60): Gestione processi multipli v3.0
3. **USERS & PROJECTS** (t80-t90): Utenti, progetti, ruoli
4. **EXECUTION** (t200-t209): Sessioni, variabili, history, logs
5. **STATISTICS** (t220-t250): Statistiche uso e performance
6. **CALENDAR** (t300-t315): Scheduling e pianificazione
7. **APP** (t01-t05): Configurazioni applicazioni

---

### 1️⃣ AREA WORKFLOW - Definizioni Processi

```sql
-- ============================================
-- TABELLA WORKFLOW (Processi)
-- ============================================
CREATE TABLE t10_workflow (
  c10_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  c10_uuid VARCHAR(36),                    -- UUID workflow
  c10_name VARCHAR(128) NOT NULL,          -- Nome processo
  c10_description TINYTEXT,                -- Descrizione
  c10_supp_langs VARCHAR(128) DEFAULT 'EN', -- Lingue supportate (es: "EN,IT,FR")
  c10_def_lang VARCHAR(5) DEFAULT 'EN',    -- Lingua default
  c10_userid INT(10) UNSIGNED NOT NULL,    -- Proprietario processo
  c10_active INT(2) UNSIGNED DEFAULT 1,    -- Processo attivo (0/1)
  c10_validfrom DATETIME,                  -- Valido da
  c10_validuntil DATETIME,                 -- Valido fino a
  c10_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c10_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c10_deleted DATETIME DEFAULT '1899-12-31 23:59:59',
  c10_deleted_by INT(11) UNSIGNED DEFAULT 0,
  
  INDEX idx_workflow_uuid (c10_uuid),
  INDEX idx_workflow_user (c10_userid),
  INDEX idx_workflow_active (c10_active, c10_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELLA BLOCK (Blocchi del Processo)
-- ============================================
CREATE TABLE t20_block (
  c20_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  c20_uuid VARCHAR(36) UNIQUE,             -- UUID blocco
  c20_flofoid INT(10) UNSIGNED NOT NULL,   -- FK → t10_workflow
  c20_start INT(2) UNSIGNED DEFAULT 0,     -- Blocco START (1=start, 0=normal)
  c20_type VARCHAR(3),                     -- Tipo blocco
  c20_desc VARCHAR(128),                   -- Descrizione blocco
  c20_exec MEDIUMTEXT,                     -- CODICE PHP DA ESEGUIRE ⚡
  c20_xpos FLOAT DEFAULT 0,                -- Posizione X editor grafico
  c20_ypos FLOAT DEFAULT 0,                -- Posizione Y editor grafico
  c20_note TINYTEXT,                       -- Note sviluppatore
  c20_error TEXT DEFAULT '',               -- Errori compilazione
  c20_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c20_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c20_deleted DATETIME DEFAULT '1899-12-31 23:59:59',
  c20_deleted_by INT(11) UNSIGNED DEFAULT 0,
  
  UNIQUE KEY ix20_uuid (c20_uuid),
  KEY ix20_flofoid (c20_flofoid),
  KEY idx_block_start (c20_flofoid, c20_start),
  
  FOREIGN KEY (c20_flofoid) REFERENCES t10_workflow(c10_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA EXIT (Uscite dei Blocchi)
-- ============================================
CREATE TABLE t30_exit (
  c30_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  c30_uuid VARCHAR(36),                    -- UUID uscita
  c30_blockid INT(10) UNSIGNED NOT NULL,   -- FK → t20_block
  c30_number INT(2) UNSIGNED NOT NULL,     -- Numero uscita (0,1,2...)
  c30_dir VARCHAR(36) DEFAULT '0',         -- UUID blocco destinazione
  c30_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c30_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  KEY ix30_blockid (c30_blockid),
  KEY idx_exit_block (c30_blockid, c30_number),
  
  FOREIGN KEY (c30_blockid) REFERENCES t20_block(c20_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA ELEMENT (Elementi UI)
-- ============================================
CREATE TABLE t40_element (
  c40_id INT(10) UNSIGNED NOT NULL,        -- ID elemento
  c40_lang VARCHAR(5) NOT NULL DEFAULT 'EN', -- Lingua (EN, IT, FR...)
  c40_text MEDIUMTEXT,                     -- Testo elemento
  c40_url VARCHAR(255),                    -- URL (per media/link)
  c40_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c40_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c40_deleted DATETIME DEFAULT '1899-12-31 23:59:59',
  c40_deleted_by INT(11) UNSIGNED DEFAULT 0,
  
  PRIMARY KEY (c40_id, c40_lang),
  KEY idx_element_lang (c40_lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA OTC (One-Time Commands)
-- ============================================
CREATE TABLE t50_otcmd (
  c50_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  c50_key VARCHAR(36) NOT NULL,            -- Key univoca comando
  c50_command VARCHAR(50) NOT NULL,        -- Comando da eseguire
  c50_uid INT(10) UNSIGNED DEFAULT 0,      -- User ID
  c50_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  KEY ix_Key (c50_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2️⃣ AREA MULTI-FLOW - Processi Multipli

```sql
-- ============================================
-- TABELLA MULTI FLOW (v3.0 - Processi Multipli)
-- ============================================
-- Permette di eseguire lo stesso workflow con variabili diverse
-- per clienti/utenti diversi
CREATE TABLE t60_multi_flow (
  c60_id VARCHAR(36) NOT NULL PRIMARY KEY,     -- MWID (Multi-Workflow ID)
  c60_workflow_id INT(10) UNSIGNED NOT NULL,   -- FK → t10_workflow
  c60_user_id INT(10) UNSIGNED NOT NULL,       -- User/Client ID
  c60_email VARCHAR(255),                      -- Email utente
  c60_json_data LONGTEXT,                      -- Dati variabili JSON
  c60_assigned_server VARCHAR(50),             -- Server assegnato
  c60_open_count INT(10) UNSIGNED DEFAULT 0,   -- Contatore aperture
  c60_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c60_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  KEY idx_mwf_workflow (c60_workflow_id),
  KEY idx_mwf_user (c60_user_id),
  
  FOREIGN KEY (c60_workflow_id) REFERENCES t10_workflow(c10_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3️⃣ AREA USERS & PROJECTS - Gestione Utenti

```sql
-- ============================================
-- TABELLA USER (Utenti Sistema)
-- ============================================
CREATE TABLE t80_user (
  c80_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  c80_email VARCHAR(255) NOT NULL UNIQUE,
  c80_name VARCHAR(100),
  c80_password VARCHAR(255),               -- Password hash
  c80_role_id INT(4) UNSIGNED,            -- FK → t90_role
  c80_active INT(2) UNSIGNED DEFAULT 1,
  c80_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c80_last_login TIMESTAMP NULL,
  
  KEY idx_user_email (c80_email),
  KEY idx_user_active (c80_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA PROJECT (Progetti)
-- ============================================
CREATE TABLE t83_project (
  c83_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  c83_desc VARCHAR(255) NOT NULL,          -- Descrizione progetto
  c83_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c83_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA PROJECT-WORKFLOW (Relazione M:N)
-- ============================================
CREATE TABLE t85_prj_wflow (
  c85_prj_id INT(10) UNSIGNED NOT NULL,    -- FK → t83_project
  c85_flofoid INT(10) UNSIGNED NOT NULL,   -- FK → t10_workflow
  
  PRIMARY KEY (c85_prj_id, c85_flofoid),
  
  FOREIGN KEY (c85_prj_id) REFERENCES t83_project(c83_id) ON DELETE CASCADE,
  FOREIGN KEY (c85_flofoid) REFERENCES t10_workflow(c10_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA PROJECT-USER (Utenti Progetto)
-- ============================================
CREATE TABLE t87_prj_user (
  c87_prj_id INT(10) UNSIGNED NOT NULL,    -- FK → t83_project
  c87_usr_id INT(10) UNSIGNED NOT NULL,    -- FK → t80_user
  
  PRIMARY KEY (c87_prj_id, c87_usr_id),
  
  FOREIGN KEY (c87_prj_id) REFERENCES t83_project(c83_id) ON DELETE CASCADE,
  FOREIGN KEY (c87_usr_id) REFERENCES t80_user(c80_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA ROLE (Ruoli Utente)
-- ============================================
CREATE TABLE t90_role (
  c90_id INT(4) UNSIGNED NOT NULL PRIMARY KEY,
  c90_name VARCHAR(30) NOT NULL,
  c90_crud VARCHAR(5) NOT NULL DEFAULT 'CRUDX'  -- Permessi CRUD
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4️⃣ AREA EXECUTION - Sessioni e Variabili ⚡ (CRUCIALE)

Questa è l'area **PIÙ IMPORTANTE** per le performance!

```sql
-- ============================================
-- TABELLA WORKER (Sessioni Esecuzione) 🔥
-- ============================================
-- OGNI ESECUZIONE WORKFLOW = 1 RIGA
-- Emivita: 3 ore dall'ultimo accesso (EVENT database)
CREATE TABLE t200_worker (
  c200_sess_id VARCHAR(36) NOT NULL PRIMARY KEY, -- SID (Session ID)
  c200_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c200_wid INT(10) UNSIGNED NOT NULL,           -- FK → t10_workflow
  c200_lang VARCHAR(5),                         -- Lingua sessione
  c200_thisblock VARCHAR(36) DEFAULT '0',       -- UUID blocco corrente
  c200_time_start DATETIME DEFAULT CURRENT_TIMESTAMP,
  c200_state_error INT(2) UNSIGNED DEFAULT 0,   -- Flag errore interno
  c200_state_usererr INT(2) UNSIGNED DEFAULT 0, -- Flag errore utente
  c200_state_exterr INT(2) UNSIGNED DEFAULT 0,  -- Flag errore esterno
  c200_blk_end INT(10) UNSIGNED,                -- Blocco finale
  c200_time_end DATETIME DEFAULT CURRENT_TIMESTAMP,
  c200_user INT(10) UNSIGNED DEFAULT 0,         -- User ID
  
  KEY idx_worker_wid (c200_wid),
  KEY idx_worker_user (c200_user),
  KEY idx_worker_time (c200_start),
  
  FOREIGN KEY (c200_wid) REFERENCES t10_workflow(c10_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA VARS (Variabili Sessione) 🔥🔥
-- ============================================
-- SERIALIZZAZIONE STATO = BOTTLENECK CRITICO!
-- Una delle tabelle più usate - OPTIMIZE HEAVILY!
CREATE TABLE t205_vars (
  c205_sess_id VARCHAR(36) NOT NULL PRIMARY KEY, -- FK → t200_worker
  c205_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  c205_vars LONGTEXT,                           -- VARIABILI SERIALIZZATE 💾
  
  KEY idx_vars_timestamp (c205_timestamp),
  
  FOREIGN KEY (c205_sess_id) REFERENCES t200_worker(c200_sess_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA HISTORY (History Esecuzione) 🔥
-- ============================================
CREATE TABLE t207_history (
  c207_sess_id VARCHAR(36) NOT NULL PRIMARY KEY,
  c207_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  c207_history LONGTEXT,                        -- History serializzata
  c207_count INT(10) UNSIGNED DEFAULT 0,        -- Contatore eventi
  
  FOREIGN KEY (c207_sess_id) REFERENCES t200_worker(c200_sess_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABELLA WORK LOG (Logs Esecuzione)
-- ============================================
CREATE TABLE t209_work_log (
  c209_sess_id VARCHAR(36) NOT NULL,
  c209_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  c209_tpinfo INT(4) UNSIGNED DEFAULT 0 COMMENT 
    '0=log row, 1=user id, 2=IP, 3=user agent, 4=internal error, 
     5=external error, 6=user error, 7=special info',
  c209_row MEDIUMTEXT,                          -- Contenuto log
  
  KEY ix_t200_log (c209_sess_id, c209_timestamp),
  
  FOREIGN KEY (c209_sess_id) REFERENCES t200_worker(c200_sess_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5️⃣ AREA STATISTICS - Statistiche

```sql
-- ============================================
-- TABELLA STAT (Statistiche Uso)
-- ============================================
CREATE TABLE t220_stat (
  c220_date DATE NOT NULL,
  c220_wf_id INT(10) UNSIGNED NOT NULL,
  c220_blk_id INT(10) UNSIGNED NOT NULL,
  c220_count INT(10) UNSIGNED DEFAULT 1,
  c220_arbitrary MEDIUMTEXT,                    -- Dati arbitrari JSON
  
  PRIMARY KEY (c220_date, c220_wf_id, c220_blk_id),
  KEY idx_stat_wf (c220_wf_id),
  KEY idx_stat_date (c220_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Altre tabelle statistiche
-- t221_user_session
-- t222_access_channel
-- ...
```

### 6️⃣ AREA CALENDAR - Scheduling

```sql
-- ============================================
-- TABELLA CALENDAR (Eventi Pianificati)
-- ============================================
CREATE TABLE t310_calendar (
  c310_cal_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  c310_wf_id INT(10) UNSIGNED NOT NULL,
  c310_title VARCHAR(255),
  c310_start DATETIME NOT NULL,
  c310_end DATETIME NOT NULL,
  c310_recurrence VARCHAR(20),                  -- daily, weekly, monthly...
  c310_status TINYINT(3) UNSIGNED DEFAULT 0,
  
  KEY idx_calendar_wf (c310_wf_id),
  KEY idx_calendar_date (c310_start, c310_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 📊 Views (Viste Database)

```sql
-- Vista workflow-progetti
CREATE VIEW v10_wf_prj AS 
SELECT wf.c10_id AS wf_id, 
       IFNULL(pw.c85_prj_id, 0) AS prj_id
FROM t10_workflow wf
LEFT JOIN t85_prj_wflow pw ON pw.c85_flofoid = wf.c10_id
LEFT JOIN t83_project pr ON pr.c83_id = pw.c85_prj_id;

-- Vista progetti-workflow-utenti
CREATE VIEW v15_prj_wf_usr AS
SELECT v2.wf_id, v2.prj_id, 
       IFNULL(p.c83_desc, '@GENERIC') AS c83_desc,
       w2.c10_name, 
       IFNULL(u.c87_usr_id, w2.c10_userid) AS c87_usr_id
FROM t10_workflow w2
LEFT JOIN v10_wf_prj v2 ON w2.c10_id = v2.wf_id
LEFT JOIN t83_project p ON p.c83_id = v2.prj_id
LEFT JOIN t87_prj_user u ON u.c87_prj_id = v2.prj_id
ORDER BY IFNULL(p.c83_desc, '@GENERIC'), w2.c10_name;

-- Vista completa workflow attivi
CREATE VIEW v20_prj_wf_all AS
SELECT v.wf_id, v.prj_id, v.c83_desc AS prj_name, 
       v.c10_name AS wf_name, v.c87_usr_id AS wf_user,
       u.c80_email AS usr_email, u.c80_name AS usr_name,
       w.c10_active AS wf_active,
       w.c10_deleted AS dt_deleted,
       w.c10_validfrom AS dt_validfrom,
       w.c10_validuntil AS dt_validuntil
FROM v15_prj_wf_usr v
JOIN t10_workflow w ON v.wf_id = w.c10_id
JOIN t80_user u ON u.c80_id = v.c87_usr_id
ORDER BY v.c83_desc, v.c10_name;
```

---

## 🔧 Componenti Software Principali

### Flusso di Esecuzione Dettagliato

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. CLIENT REQUEST                                               │
└─────────────────────────────────────────────────────────────────┘
   POST /flussueng
   ├─ WID: [workflow_id]        (es: "[MYR0C3SS]" o numeric)
   ├─ SID: [session_id]         (UUID - opzionale per nuovo)
   ├─ BID: [block_id]           (UUID blocco - opzionale)
   ├─ CMD: [command]            (start, set, info...)
   ├─ TRM: [terms_json]         (parametri input)
   ├─ LNG: [language]           (IT, EN, FR...)
   └─ SET: [settings_json]      (configurazioni)

┌─────────────────────────────────────────────────────────────────┐
│ 2. ENGINE.PHP - Entry Point API                                 │
└─────────────────────────────────────────────────────────────────┘
   ├─ Validazione parametri
   ├─ Conversione WID (se numerico → camoufflato)
   ├─ Gestione CORS headers
   ├─ Creazione/Recupero Session
   └─ Inizializzazione Worker

┌─────────────────────────────────────────────────────────────────┐
│ 3. SESSION.PHP - Gestione Stato (60KB code)                     │
└─────────────────────────────────────────────────────────────────┘
   SESSION.__construct($SessionId)
   │
   ├─ [NEW SESSION]
   │  ├─ Genera UUID nuovo
   │  ├─ Inizializza $_MemSeStat
   │  └─ Set $_is_starting = true
   │
   └─ [EXISTING SESSION]
      ├─ _chkExists($SessionId) → Query t200_worker
      ├─ _loadHistory() → Query t207_history
      ├─ _ensureVarsLoaded() → Query t205_vars
      │  └─ UNSERIALIZE variabili 📦
      ├─ Carica $_arVars (array variabili)
      ├─ _initMemSeStat($initWid)
      └─ _checkIsStarting()

┌─────────────────────────────────────────────────────────────────┐
│ 4. WORKER.PHP - Esecuzione Blocchi (61KB code)                  │
└─────────────────────────────────────────────────────────────────┘
   WORKER.__construct($Session)
   │
   ├─ Associa Session
   ├─ Crea Handler (cache/DB)
   ├─ [IF isStarting] → _execStartBlock()
   └─ Pronto per execNextBlock()

   WORKER.execNextBlock($bid, $terms, $restart)
   │
   ├─ FASE 1: Parsing Input Terms
   │  ├─ Estrae parametri da $terms (JSON)
   │  ├─ pushValue() → Assegna variabili session
   │  └─ Gestisce $ex! (exit explicit)
   │
   ├─ FASE 2: Risoluzione Blocco Corrente
   │  ├─ Se $exitNum > -1 → Determina blocco da exit
   │  ├─ buildFlussuBlock() → Handler (cache/DB)
   │  └─ Verifica hasExit
   │
   ├─ FASE 3: LOOP ESECUZIONE PRINCIPALE 🔄
   │  │
   │  WHILE (ha_NextBlock) {
   │     │
   │     ├─ buildFlussuBlock($frmXctdBid) → Handler
   │     │  └─ Cache hit/miss → Query t20_block + t30_exit + t40_element
   │     │
   │     ├─ _doBlockExec($theBlk) 💥 EVAL PHP!
   │     │  ├─ removeComments($block["exec"])
   │     │  ├─ sanitizeExec()
   │     │  ├─ Prepara $theCode con Environment
   │     │  ├─ Inietta workflow vars: getWorkflowVars()
   │     │  └─ **eval($theCode)** ⚡ BOTTLENECK!
   │     │
   │     ├─ _buildElementsAndExits($theBlk)
   │     │  ├─ Parsing elementi UI (label, input, button...)
   │     │  └─ _strReplace() sostituisce variabili
   │     │
   │     ├─ _processElements($elements)
   │     │  ├─ Costruisce $_xcelm array elementi
   │     │  └─ Gestisce button exit
   │     │
   │     └─ Determina next block da exit
   │  }
   │
   └─ RETURN elementi UI per render

┌───────────────────────────────────────────────────────────────────┐
│ 5. HANDLER.PHP - Cache & Database Access (13KB) [OK] OTTIMIZZATO  │
└───────────────────────────────────────────────────────────────────┘
   HANDLER._cachedCall($prefix, $keyParts, $type, $tag, $method, $params)
   │
   ├─ _buildCacheKey() → Genera chiave cache
   ├─ General::GetCache($key) → Verifica cache
   │  ├─ [HIT] → Return cached data ⚡
   │  └─ [MISS] → Continua
   ├─ _getHNC() → Lazy load HandlerNC
   ├─ call_user_func_array() → Query database
   ├─ General::PutCache($key, $result) → Salva cache
   └─ Return result

┌─────────────────────────────────────────────────────────────────┐
│ 6. HANDLERNC.PHP - Database Queries (90KB) 🔴 OTTIMIZZARE!     │
└─────────────────────────────────────────────────────────────────┘
   HANDLERNC.buildFlussuBlock($WoFoId, $BlkUuid, $LNG)
   │
   ├─ Query t20_block → block data + c20_exec
   ├─ Query t30_exit → exit directions
   ├─ Query t40_element → UI elements per lingua
   ├─ Costruisce array complesso
   └─ Return block structure

   Esempi query raw SQL:

   ```sql
   -- Workflow name
   SELECT c10_name FROM t10_workflow WHERE c10_id = ?
   
   -- First block
   SELECT c10_name, c20_uuid as start_blk, c20_id as bid, c10_active 
   FROM t10_workflow 
   INNER JOIN t20_block ON c20_flofoid = c10_id 
   WHERE c10_id = ? AND c20_start = 1
   
   -- Block complete
   SELECT b.*, e.c30_number, e.c30_dir 
   FROM t20_block b
   LEFT JOIN t30_exit e ON e.c30_blockid = b.c20_id
   WHERE b.c20_uuid = ?
   ```

┌─────────────────────────────────────────────────────────────────┐
│     7. RESPONSE JSON                                            │
└─────────────────────────────────────────────────────────────────┘
```json
   {
     "sid": "550e8400-e29b-41d4-a716-446655440000",
     "bid": "block-uuid-here",
     "elms": {
       "L$0": ["Benvenuto!", {"css": {...}}],
       "ITT$nome": ["Il tuo nome:", {...}, "[val]:"],
       "ITB$ex!0;encoded": ["Continua", {...}]
     }
   }
```

---

## 🎯 Ottimizzazioni v5.0 - Soluzioni INTERNE

### ⚠️ REQUISITO: NO Servizi Esterni

**Non useremo**:
- ❌ Redis
- ❌ Memcached
- ❌ ElasticSearch
- ❌ RabbitMQ
- ❌ Servizi cloud esterni

**Useremo solo**:
- ✅ APCu (PHP native extension) - se disponibile
- ✅ File-based cache (filesystem locale)
- ✅ MySQL per cache persistente
- ✅ OpCache PHP (già integrato PHP 8.x)
- ✅ Classi PHP custom (stile HandlerNC)

---

## 💾 Sistema Cache Interno Custom

### Architettura Cache a 3 Livelli

```
┌─────────────────────────────────────────────────────────────────┐
│                    FLUSSU CACHE SYSTEM v5.0                     │
└─────────────────────────────────────────────────────────────────┘

  REQUEST
     │
     ▼
┌────────────────────┐
│   Level 1: APCu    │  ← Più veloce (RAM nativa PHP)
│   (in-memory)      │    Hit: ~0.01ms
│   TTL: 30min       │
└────────┬───────────┘
         │ miss
         ▼
┌────────────────────┐
│ Level 2: FileCache │  ← Veloce (filesystem locale)
│ (disk - serialized)│    Hit: ~1-2ms
│   TTL: 1h          │
└────────┬───────────┘
         │ miss
         ▼
┌────────────────────┐
│ Level 3: Database  │  ← Fallback (MySQL)
│   (MySQL query)    │    Query: ~5-50ms
└────────────────────┘
```

### Implementazione: CacheManager.php

```php
// File: Flussu/Cache/CacheManager.php
<?php
namespace Flussu\Cache;

use Flussu\General;

/**
 * Sistema cache interno a 3 livelli
 * Nessuna dipendenza esterna (no Redis, no Memcached)
 */
class CacheManager {
    
    private $cacheDir;
    private $useAPCu;
    private $useFileCache;
    
    // TTL per livelli
    const TTL_APCU = 1800;       // 30 min
    const TTL_FILE = 3600;       // 1 ora
    const TTL_WORKFLOW = 7200;   // 2 ore (cambia raramente)
    const TTL_BLOCK = 3600;      // 1 ora
    const TTL_SESSION = 600;     // 10 min (cambia spesso)
    
    public function __construct() {
        // Verifica disponibilità APCu
        $this->useAPCu = extension_loaded('apcu') && ini_get('apc.enabled');
        
        // Setup directory cache file
        $this->cacheDir = $this->determineCacheDir();
        $this->useFileCache = $this->initFileCacheDir();
        
        if (!$this->useAPCu && !$this->useFileCache) {
            General::log('WARNING: Cache system degraded - using only database');
        }
    }
    
    /**
     * Determina directory cache
     */
    private function determineCacheDir(): string {
        // Prova in ordine di preferenza
        $candidates = [
            '/var/cache/flussu',
            sys_get_temp_dir() . '/flussu_cache',
            $_SERVER['DOCUMENT_ROOT'] . '/../cache/flussu'
        ];
        
        foreach ($candidates as $dir) {
            if (is_writable(dirname($dir))) {
                return $dir;
            }
        }
        
        return sys_get_temp_dir() . '/flussu_cache';
    }
    
    /**
     * Inizializza directory file cache
     */
    private function initFileCacheDir(): bool {
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                General::log('ERROR: Cannot create cache directory: ' . $this->cacheDir);
                return false;
            }
        }
        
        if (!is_writable($this->cacheDir)) {
            General::log('ERROR: Cache directory not writable: ' . $this->cacheDir);
            return false;
        }
        
        return true;
    }
    
    /**
     * GET con cascata 3 livelli
     */
    public function get($key, $type = 'default') {
        // Livello 1: APCu (più veloce)
        if ($this->useAPCu) {
            $value = apcu_fetch($this->buildKey($key, $type), $success);
            if ($success) {
                $this->recordHit('apcu', $type);
                return $this->unserializeValue($value);
            }
        }
        
        // Livello 2: File Cache
        if ($this->useFileCache) {
            $value = $this->getFromFileCache($key, $type);
            if ($value !== false) {
                // Prewarm APCu per next hit
                if ($this->useAPCu) {
                    $this->setInAPCu($key, $type, $value);
                }
                $this->recordHit('file', $type);
                return $value;
            }
        }
        
        // Livello 3: Miss completo
        $this->recordMiss($type);
        return null;
    }
    
    /**
     * SET in tutti i livelli
     */
    public function set($key, $value, $type = 'default', $ttl = null) {
        // Determina TTL appropriato
        if ($ttl === null) {
            $ttl = $this->getTTLForType($type);
        }
        
        $serialized = $this->serializeValue($value);
        
        // Livello 1: APCu
        if ($this->useAPCu) {
            $this->setInAPCu($key, $type, $value, $ttl);
        }
        
        // Livello 2: File Cache
        if ($this->useFileCache) {
            $this->setInFileCache($key, $type, $serialized, $ttl);
        }
        
        return true;
    }
    
    /**
     * DELETE da tutti i livelli
     */
    public function delete($key, $type = 'default') {
        $fullKey = $this->buildKey($key, $type);
        
        // APCu
        if ($this->useAPCu) {
            apcu_delete($fullKey);
        }
        
        // File Cache
        if ($this->useFileCache) {
            $filepath = $this->buildFilePath($key, $type);
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }
    }
    
    /**
     * CLEAR intero tipo cache
     */
    public function clear($type = null) {
        if ($type === null) {
            // Clear tutto
            if ($this->useAPCu) {
                apcu_clear_cache();
            }
            
            if ($this->useFileCache) {
                $this->clearFileCache();
            }
        } else {
            // Clear specifico tipo
            if ($this->useAPCu) {
                $this->clearAPCuByType($type);
            }
            
            if ($this->useFileCache) {
                $this->clearFileCacheByType($type);
            }
        }
    }
    
    /**
     * ==================================
     * PRIVATE METHODS - APCu
     * ==================================
     */
    
    private function setInAPCu($key, $type, $value, $ttl) {
        $fullKey = $this->buildKey($key, $type);
        $serialized = $this->serializeValue($value);
        apcu_store($fullKey, $serialized, $ttl);
    }
    
    private function clearAPCuByType($type) {
        // APCu non ha clear per pattern, iteriamo
        $prefix = "flussu_{$type}_";
        $iterator = new \APCUIterator('/^' . preg_quote($prefix, '/') . '/');
        
        foreach ($iterator as $entry) {
            apcu_delete($entry['key']);
        }
    }
    
    /**
     * ==================================
     * PRIVATE METHODS - File Cache
     * ==================================
     */
    
    private function getFromFileCache($key, $type) {
        $filepath = $this->buildFilePath($key, $type);
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        // Verifica TTL
        $mtime = filemtime($filepath);
        $ttl = $this->getTTLForType($type);
        
        if (time() - $mtime > $ttl) {
            // Expired
            @unlink($filepath);
            return false;
        }
        
        // Leggi e deserializza
        $serialized = file_get_contents($filepath);
        return $this->unserializeValue($serialized);
    }
    
    private function setInFileCache($key, $type, $serialized, $ttl) {
        $filepath = $this->buildFilePath($key, $type);
        $dir = dirname($filepath);
        
        // Crea sottodirectory se non esiste
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Scrivi atomicamente
        $tmpfile = $filepath . '.tmp';
        file_put_contents($tmpfile, $serialized);
        rename($tmpfile, $filepath);
        chmod($filepath, 0644);
    }
    
    private function clearFileCache() {
        $this->deleteDirectory($this->cacheDir);
        $this->initFileCacheDir();
    }
    
    private function clearFileCacheByType($type) {
        $typeDir = $this->cacheDir . '/' . $type;
        if (is_dir($typeDir)) {
            $this->deleteDirectory($typeDir);
        }
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDirectory($file);
            } else {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
    
    /**
     * ==================================
     * HELPER METHODS
     * ==================================
     */
    
    private function buildKey($key, $type): string {
        return "flussu_{$type}_" . $key;
    }
    
    private function buildFilePath($key, $type): string {
        // Crea struttura gerarchica per evitare troppe file in una dir
        $hash = md5($key);
        $subdir1 = substr($hash, 0, 2);
        $subdir2 = substr($hash, 2, 2);
        
        return $this->cacheDir . "/{$type}/{$subdir1}/{$subdir2}/{$hash}.cache";
    }
    
    private function getTTLForType($type): int {
        return match($type) {
            'workflow', 'wid' => self::TTL_WORKFLOW,
            'block', 'blk' => self::TTL_BLOCK,
            'session' => self::TTL_SESSION,
            default => self::TTL_FILE
        };
    }
    
    private function serializeValue($value): string {
        // Usa serialize PHP nativo (veloce)
        // Alternativamente: igbinary se disponibile (più veloce)
        if (extension_loaded('igbinary')) {
            return igbinary_serialize($value);
        }
        return serialize($value);
    }
    
    private function unserializeValue($serialized) {
        if (extension_loaded('igbinary')) {
            return @igbinary_unserialize($serialized);
        }
        return @unserialize($serialized);
    }
    
    /**
     * ==================================
     * STATISTICS & MONITORING
     * ==================================
     */
    
    private static $stats = [
        'hits' => ['apcu' => 0, 'file' => 0],
        'misses' => 0
    ];
    
    private function recordHit($level, $type) {
        self::$stats['hits'][$level]++;
    }
    
    private function recordMiss($type) {
        self::$stats['misses']++;
    }
    
    public static function getStats() {
        $total = array_sum(self::$stats['hits']) + self::$stats['misses'];
        $hitRate = $total > 0 ? (array_sum(self::$stats['hits']) / $total) * 100 : 0;
        
        return [
            'hits_apcu' => self::$stats['hits']['apcu'],
            'hits_file' => self::$stats['hits']['file'],
            'misses' => self::$stats['misses'],
            'hit_rate' => round($hitRate, 2),
            'total_requests' => $total
        ];
    }
    
    /**
     * ==================================
     * UTILITY: Warm Cache
     * ==================================
     */
    
    public function warmWorkflowCache($workflowId) {
        $handler = new \Flussu\Flussuserver\NC\HandlerNC();
        
        // Prewarm workflow data
        $wfData = $handler->getFlussuNameFirstBlock($workflowId);
        $this->set($workflowId, $wfData, 'workflow');
        
        // Prewarm first block
        if (!empty($wfData) && isset($wfData[0]['start_blk'])) {
            $blockUuid = $wfData[0]['start_blk'];
            $blockData = $handler->buildFlussuBlock($workflowId, $blockUuid, 'EN');
            $this->set($blockUuid, $blockData, 'block');
        }
    }
}
```

### Integrazione in General.php

```php
// File: General.php
<?php
namespace Flussu;

use Flussu\Cache\CacheManager;

class General {
    private static $cacheManager = null;
    
    private static function getCacheManager(): CacheManager {
        if (self::$cacheManager === null) {
            self::$cacheManager = new CacheManager();
        }
        return self::$cacheManager;
    }
    
    /**
     * Get from cache
     */
    public static function GetCache($key, $type, $tag) {
        return self::getCacheManager()->get($key, $type);
    }
    
    /**
     * Put in cache
     */
    public static function PutCache($key, $value, $type, $tag, $ttl = null) {
        return self::getCacheManager()->set($key, $value, $type, $ttl);
    }
    
    /**
     * Clear cache
     */
    public static function ClearCache($type, $tag) {
        return self::getCacheManager()->clear($type);
    }
    
    /**
     * Get cache statistics
     */
    public static function GetCacheStats() {
        return CacheManager::getStats();
    }
}
```

---

## 🚀 Ottimizzazioni Prioritarie v5.0

### 🔴 PRIORITÀ 1: HandlerNC Query Optimization

**File**: `HandlerNC.php` (90KB)  
**Problema**: SQL raw ripetitivo, query N+1, no prepared statement pool  
**Impatto**: 40-60% improvement database performance

**Soluzione**:
1. **Query Builder interno** (no Doctrine, no Eloquent)
2. **Prepared Statement Pool**
3. **Batch Loading** (carica blocchi multipli in 1 query)

```php
// Esempio: Query Builder interno lightweight
class QueryBuilder {
    private $table;
    private $select = ['*'];
    private $where = [];
    private $joins = [];
    private $bindings = [];
    
    public function table($table) {
        $this->table = $table;
        return $this;
    }
    
    public function select(...$columns) {
        $this->select = $columns;
        return $this;
    }
    
    public function where($column, $operator, $value = null) {
        // ... implementation
        return $this;
    }
    
    public function join($table, $col1, $op, $col2) {
        // ... implementation
        return $this;
    }
    
    public function get() {
        $sql = $this->build();
        return $this->execute($sql, $this->bindings);
    }
}

// Uso in HandlerNC
$result = DB::table('t10_workflow')
    ->select('c10_name', 'c10_active')
    ->where('c10_id', $wofoId)
    ->where('c10_deleted', '1899-12-31 23:59:59')
    ->first();
```

---

### 🟡 PRIORITÀ 2: Worker._doBlockExec() - Compiled Blocks

**File**: `Worker.php` (61KB)  
**Problema**: `eval()` ogni esecuzione blocco - no OpCache  
**Impatto**: 20-30% improvement block execution

**Soluzione**: Block Compiler con cache compilata

```php
// File: Flussu/Compiler/BlockCompiler.php
class BlockCompiler {
    private $cacheDir;
    
    public function compile($block, $session) {
        $blockId = $block['block_id'];
        $codeHash = md5($block['exec']);
        $cacheFile = $this->cacheDir . "/block_{$blockId}_{$codeHash}.php";
        
        // Check cache
        if (file_exists($cacheFile)) {
            return include $cacheFile; // OpCache accelerated!
        }
        
        // Compile
        $compiled = $this->compileToFile($block, $session);
        file_put_contents($cacheFile, $compiled);
        
        return include $cacheFile;
    }
    
    private function compileToFile($block, $session) {
        $funcName = 'block_' . str_replace('-', '_', $block['uuid']);
        
        $code = "<?php\n";
        $code .= "// Compiled Block: {$block['block_id']}\n";
        $code .= "// Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $code .= "if (!function_exists('$funcName')) {\n";
        $code .= "    function $funcName(\$session) {\n";
        $code .= "        use Flussu\\Flussuserver\\Environment;\n";
        $code .= "        \$wofoEnv = new Environment(\$session);\n";
        $code .= "        \$Flussu = new \\stdClass;\n";
        // ... setup context
        $code .= "        // User code:\n";
        $code .= $this->indentCode($block['exec'], 2);
        $code .= "\n        return \$wofoEnv->endScript();\n";
        $code .= "    }\n";
        $code .= "}\n";
        $code .= "return $funcName(\$session);\n";
        
        return $code;
    }
}
```

---

### 🟢 PRIORITÀ 3: Session Serialization Optimization

**File**: `Session.php` (60KB)  
**Problema**: serialize/unserialize TUTTO lo stato ogni volta  
**Impatto**: 30-40% improvement I/O

**Soluzione 1**: Incremental Updates

```php
// Salva solo variabili modificate
class SessionIncrementalStore {
    private $changedVars = [];
    
    public function markChanged($varName) {
        $this->changedVars[$varName] = true;
    }
    
    public function saveIncremental() {
        if (empty($this->changedVars)) return;
        
        // Estrai solo vars cambiate
        $toSave = array_intersect_key(
            $this->_arVars, 
            $this->changedVars
        );
        
        // Serializza solo queste
        $serialized = serialize($toSave);
        
        // Update database
        $this->updateVarsInDB($serialized, array_keys($toSave));
        
        $this->changedVars = [];
    }
}
```

**Soluzione 2**: igbinary (se disponibile)

```php
// Più veloce di serialize() nativo
if (extension_loaded('igbinary')) {
    $data = igbinary_serialize($vars);  // 40% più veloce
    // ...
    $vars = igbinary_unserialize($data);
}
```

---

## 📊 Metriche Performance Attese

### Before v5.0 (Baseline - TBD con benchmark)

```
Response Time (p50):     ~300ms
Response Time (p99):     ~800ms
DB Queries per Request:  15-25
Cache Hit Rate:          ~40%
Memory per Request:      8-12 MB
Throughput:              ~50 req/s
```

### After v5.0 (Target)

```
Response Time (p50):     ~120ms  (-60%)
Response Time (p99):     ~350ms  (-56%)
DB Queries per Request:  5-10    (-60%)
Cache Hit Rate:          ~80%    (+100%)
Memory per Request:      5-8 MB  (-40%)
Throughput:              150 req/s (+200%)
```

---

## 🔧 Roadmap Implementazione

### Fase 1: Baseline & Infra (2 settimane)
- [ ] Setup benchmark script
- [ ] Misurare performance baseline
- [ ] Implementare CacheManager.php
- [ ] Testare APCu vs FileCache
- [ ] Integrare in General.php

### Fase 2: Database Optimization (3 settimane)
- [ ] Analizzare indici database mancanti
- [ ] Aggiungere indici critici
- [ ] Implementare QueryBuilder base
- [ ] Refactorare 30% HandlerNC methods
- [ ] Implementare Prepared Statement Pool

### Fase 3: Block Compiler (3 settimane)
- [ ] Implementare BlockCompiler
- [ ] Security validation codice blocchi
- [ ] Feature flag per enable/disable
- [ ] Test tutti i blocchi esistenti
- [ ] Benchmark compilati vs eval()

### Fase 4: Session Optimization (2 settimane)
- [ ] Implementare incremental serialization
- [ ] Testare igbinary
- [ ] Ottimizzare _ensureVarsLoaded()
- [ ] Batch updates

### Fase 5: Testing & Deploy (2 settimane)
- [ ] Load testing completo
- [ ] Fix bugs identificati
- [ ] Deploy staging → production
- [ ] Monitoring 1 settimana
- [ ] Documentazione finale

**TOTALE**: ~12 settimane (3 mesi)

---

## 🎯 Conclusioni

FLUSSU ha un'architettura SOA solida e ben progettata. Le aree principali di ottimizzazione sono:

1. **HandlerNC (90KB)** - Query database → Query Builder + caching aggressivo
2. **Worker._doBlockExec()** - eval() → Block compilation con OpCache
3. **Session serialization** - Full state → Incremental updates + igbinary
4. **Cache System** - Implementazione a 3 livelli (APCu + File + DB)

Con queste ottimizzazioni, FLUSSU v5.0 può raggiungere **2-3x miglioramento performance** mantenendo:
- ✅ Nessuna dipendenza esterna (no Redis, no servizi cloud)
- ✅ Backward compatibility completa
- ✅ Architettura pulita e mantenibile
- ✅ Deployment semplice (solo PHP + MySQL)

---

**Documento creato**: 2025-11-02  
**Versione**: 2.0 - COMPLETO CON DATABASE SCHEMA  
**Prossimi passi**: Implementazione CacheManager e benchmark baseline

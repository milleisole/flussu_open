# Flussu JSON Workflow Specification

**Versione**: 4.5
**Obiettivo**: Generare workflow JSON fuori da Flussu (es. Claude Code, chat AI) e caricarli su un server Flussu per l'esecuzione.

---

## 1. Architettura Generale

Flussu gestisce workflow come grafi di **blocchi** (nodi) collegati da **uscite** (archi direzionali). Ogni blocco contiene **elementi** UI (etichette, input, bottoni, selezioni, media) e codice di esecuzione PHP.

```
Workflow
├── Metadati (nome, descrizione, lingue)
├── Blocco START (is_start=1)
│   ├── Elementi (label, input, bottone, ...)
│   │   └── Traduzioni per lingua
│   └── Uscite (exit_0 → blocco_B, exit_1 → blocco_C)
├── Blocco B
│   ├── Elementi
│   └── Uscite
└── Blocco C
    ├── Elementi
    └── Uscite (exit_0 → "0" = FINE)
```

---

## 2. Struttura JSON Completa del Workflow

Questa è la struttura JSON che Flussu esporta con il comando `G` (Get) e che accetta con `U` (Update), `IS` (Import Substitute), `IN` (Import New) e `RD` (Receive Deploy).

```json
{
  "workflow": [
    {
      "wid": "[w73385d6787117396]",
      "wfauid": "dcb13393-9f47-11ef-a70a-005056035b78",
      "name": "Nome del Workflow",
      "description": "Descrizione del workflow",
      "userId": "123",
      "is_active": 1,
      "supp_langs": "IT,EN",
      "lang": "IT",
      "valid_from": "1899-12-31 23:59:59",
      "valid_until": "2100-01-01 00:00:00",
      "last_mod": "2025-06-25 10:30:00",
      "svc1": "",
      "svc2": "",
      "svc3": "",
      "app_id": 0,
      "prj_id": 0,
      "start_block_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "blocks": [
        {
          "block_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
          "type": "",
          "exec": "wofoEnv->init();\r\n",
          "description": "START BLOCK",
          "is_start": 1,
          "x_pos": 100,
          "y_pos": 200,
          "note": "",
          "error": "",
          "last_mod": "2025-06-25 10:30:00",
          "elements": [
            {
              "elem_id": "11111111-2222-3333-4444-555555555555",
              "var_name": "$nome",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": {
                "display_info": {
                  "type": "",
                  "subtype": "",
                  "mandatory": false
                },
                "class": ""
              },
              "note": "",
              "langs": {
                "IT": {
                  "label": "Come ti chiami?",
                  "uri": ""
                },
                "EN": {
                  "label": "What is your name?",
                  "uri": ""
                }
              }
            }
          ],
          "exits": [
            { "exit_dir": "b2c3d4e5-f6a7-8901-bcde-f23456789012" },
            { "exit_dir": "0" }
          ]
        }
      ]
    }
  ]
}
```

---

## 3. Tipi di Elemento (c_type)

Ogni elemento in un blocco ha un `c_type` numerico che determina il suo comportamento:

| c_type | d_type (nome) | Prefisso Runtime | Descrizione |
|--------|---------------|------------------|-------------|
| `0`    | LABEL         | `L$`             | Testo/messaggio visualizzato all'utente. Supporta HTML. |
| `1`    | INPUT         | `ITT$`           | Campo di input testo. Può essere singola riga o multilinea. |
| `2`    | BUTTON        | `ITB$`           | Bottone cliccabile. Necessita `exit_num` per collegare l'uscita. |
| `3`    | MEDIA         | `M$`             | Immagine, video o file. L'URL va nel campo `uri` della lingua. |
| `4`    | LINK          | `A$`             | Link ipertestuale (`target="_blank"`). |
| `5`    | VAR_ASSIGN    | `GUI$`           | Assegnazione nascosta di variabile (hidden input). |
| `6`    | SELECTION     | `ITS$`           | Select/Radio/Checkbox. Il label è un JSON di opzioni. |
| `7`    | GET_MEDIA     | `ITM$`           | Richiesta upload file dall'utente. |

---

## 4. Dettaglio di Ogni Tipo di Elemento

### 4.1 LABEL (c_type=0)

Mostra testo all'utente. Supporta HTML inline.

```json
{
  "elem_id": "uuid-v4",
  "var_name": "",
  "c_type": "0",
  "d_type": "LABEL",
  "e_order": "0",
  "exit_num": "",
  "css": { "display_info": {} },
  "note": "",
  "langs": {
    "IT": { "label": "Benvenuto nel nostro servizio!", "uri": "" },
    "EN": { "label": "Welcome to our service!", "uri": "" }
  }
}
```

### 4.2 INPUT (c_type=1)

Campo di input testo. Il `var_name` è la variabile di sessione dove viene salvato il valore inserito dall'utente.

```json
{
  "elem_id": "uuid-v4",
  "var_name": "$nome",
  "c_type": "1",
  "d_type": "INPUT",
  "e_order": "1",
  "exit_num": "",
  "css": {
    "display_info": {
      "type": "",
      "subtype": "",
      "mandatory": true
    }
  },
  "note": "",
  "langs": {
    "IT": { "label": "Inserisci il tuo nome", "uri": "" },
    "EN": { "label": "Enter your name", "uri": "" }
  }
}
```

**Subtypes per INPUT**:
- `""` (vuoto) = input testo singola riga
- `"multiline"` = textarea multilinea
- Il campo `mandatory: true` nel `display_info` rende il campo obbligatorio

### 4.3 BUTTON (c_type=2)

Bottone cliccabile. **IMPORTANTE**: `exit_num` deve corrispondere all'indice dell'uscita del blocco.

```json
{
  "elem_id": "uuid-v4",
  "var_name": "$scelta",
  "c_type": "2",
  "d_type": "BUTTON",
  "e_order": "2",
  "exit_num": "0",
  "css": { "display_info": {} },
  "note": "",
  "langs": {
    "IT": { "label": "Avanti", "uri": "" },
    "EN": { "label": "Next", "uri": "" }
  }
}
```

- `exit_num`: `"0"` = primo percorso (exits[0]), `"1"` = secondo percorso (exits[1]), ecc.
- Quando il bottone viene premuto, il runtime segue l'uscita corrispondente.
- Se `exit_num` è vuoto, viene impostato automaticamente a `"0"`.

### 4.4 MEDIA (c_type=3)

Visualizza immagini, video o file. L'URL va nel campo `uri` delle `langs`.

```json
{
  "elem_id": "uuid-v4",
  "var_name": "",
  "c_type": "3",
  "d_type": "MEDIA",
  "e_order": "0",
  "exit_num": "",
  "css": { "display_info": {} },
  "note": "",
  "langs": {
    "IT": { "label": "", "uri": "https://example.com/immagine.jpg" }
  }
}
```

Il client determina il tipo di media dall'estensione:
- **Immagini**: jpg, jpeg, gif, svg, png
- **Video**: mp4, avi, mpg, mpeg
- **File**: qualsiasi altra estensione (mostra link download)
- **QR Code**: URL contenente `flussu_qrc`

### 4.5 LINK (c_type=4)

Link ipertestuale esterno.

```json
{
  "elem_id": "uuid-v4",
  "var_name": "",
  "c_type": "4",
  "d_type": "LINK",
  "e_order": "1",
  "exit_num": "",
  "css": { "display_info": {} },
  "note": "",
  "langs": {
    "IT": { "label": "Visita il sito", "uri": "https://example.com" }
  }
}
```

### 4.6 VAR_ASSIGN (c_type=5) - Hidden

Assegna un valore a una variabile di sessione senza mostrare nulla all'utente.

```json
{
  "elem_id": "uuid-v4",
  "var_name": "$variabile_nascosta",
  "c_type": "5",
  "d_type": "VAR_ASSIGN",
  "e_order": "0",
  "exit_num": "",
  "css": { "display_info": {} },
  "note": "",
  "langs": {
    "IT": { "label": "valore_predefinito", "uri": "" }
  }
}
```

### 4.7 SELECTION (c_type=6)

Menu a tendina, radio buttons o checkboxes. Il `label` nelle `langs` è un **oggetto JSON** (non una stringa).

```json
{
  "elem_id": "uuid-v4",
  "var_name": "$colore",
  "c_type": "6",
  "d_type": "SELECTION",
  "e_order": "1",
  "exit_num": "",
  "css": {
    "display_info": {
      "subtype": "exclusive"
    }
  },
  "note": "",
  "langs": {
    "IT": {
      "label": {
        "rosso,0": "Rosso",
        "blu,0": "Blu",
        "verde,0": "Verde"
      },
      "uri": ""
    },
    "EN": {
      "label": {
        "red,0": "Red",
        "blue,0": "Blue",
        "green,0": "Green"
      },
      "uri": ""
    }
  }
}
```

**Formato delle opzioni**: Le chiavi dell'oggetto label sono `"valore,flag"` dove:
- `valore` = il valore inviato al server quando selezionato
- `flag` = `0` per selezione normale, `1` per pre-selezionato

**Subtypes per SELECTION** (`css.display_info.subtype`):
- `""` o `"default"` = dropdown select
- `"exclusive"` = radio buttons (selezione singola)
- `"multiple"` = checkboxes (selezione multipla)

### 4.8 GET_MEDIA (c_type=7)

Richiede all'utente di caricare un file.

```json
{
  "elem_id": "uuid-v4",
  "var_name": "$documento",
  "c_type": "7",
  "d_type": "GET_MEDIA",
  "e_order": "1",
  "exit_num": "",
  "css": { "display_info": {} },
  "note": "",
  "langs": {
    "IT": { "label": "Carica il tuo documento", "uri": "" }
  }
}
```

---

## 5. Blocco `exec` - Codice di Esecuzione

Il campo `exec` di ogni blocco contiene codice PHP-like che viene eseguito dal Worker. La variabile speciale `wofoEnv` (Environment) fornisce accesso al contesto di esecuzione.

### Comandi principali:

```php
// Inizializzazione (primo blocco)
wofoEnv->init();

// Assegnazione variabile
wofoEnv->setVar("$nome", "valore");

// Lettura variabile
$val = wofoEnv->getVar("$nome");

// Chiamata API esterna
wofoEnv->callApi("URL", "metodo", parametri);

// Invio email
wofoEnv->sendMail("destinatario", "oggetto", "corpo");

// Log
wofoEnv->log("messaggio di log");

// Condizionale per uscita
// L'exit viene determinata dal bottone premuto dall'utente
// oppure può essere forzata nel codice
```

**IMPORTANTE**: Nel JSON il codice exec **non** ha il prefisso `$` sulla variabile `wofoEnv`. Viene scritto come `wofoEnv->metodo()`. Il sistema aggiunge internamente il `$` prima dell'esecuzione.

---

## 6. Uscite dei Blocchi (exits)

Ogni blocco ha un array di uscite. L'ordine nell'array determina l'indice dell'uscita.

```json
"exits": [
  { "exit_dir": "uuid-blocco-destinazione" },
  { "exit_dir": "uuid-altro-blocco" },
  { "exit_dir": "0" }
]
```

- `exit_dir`: UUID del blocco di destinazione, oppure `"0"` per nessun collegamento (fine percorso o non collegato).
- `exits[0]` = uscita 0 (collegata a `exit_num: "0"` dei bottoni)
- `exits[1]` = uscita 1 (collegata a `exit_num: "1"` dei bottoni)
- Un blocco deve avere **almeno 2 uscite** (uscita 0 e uscita 1)

---

## 7. Come Caricare un JSON su Flussu

### 7.1 Metodo 1: Receive Deploy (RD) - Creazione Completa

Questo è il metodo più diretto. Se il workflow non esiste, lo crea; se esiste, lo aggiorna.

**Endpoint**: `POST /api.php?C=RD&WID=[wXXXXXXXXXXXX]`

**Body**: JSON del workflow completo (come nella sezione 2)

**Funzionamento interno** (`receiveFlofo()`):
1. Decodifica il WID
2. Se il workflow non esiste nel DB, crea la riga in `t10_workflow` e il blocco START
3. Chiama `updateFlofo()` per sincronizzare tutti i blocchi, elementi e uscite

### 7.2 Metodo 2: Create + Update (C + U)

**Step 1 - Crea il workflow**:
```
POST /api.php?C=C&N=NomeWorkflow
Body: {"name":"NomeWorkflow","description":"Descrizione","supp_langs":"IT,EN"}
```
Risposta: `{"result":"OK","WID":"[wXXXXXXXXXXX]"}`

**Step 2 - Aggiorna con i blocchi**:
```
POST /api.php?C=U&WID=[wXXXXXXXXXXX]
Body: { JSON completo del workflow con tutti i blocchi }
```

### 7.3 Metodo 3: Import Substitute (IS) - Sostituzione totale

Importa e sostituisce completamente i blocchi di un workflow esistente. I dati devono essere codificati in **doppio base64**.

**Endpoint**: `POST /api.php?C=IS&WID=[wXXXXXXXXXXX]`

**Body**: `"base64(base64(JSON_workflow))"`

**Codifica**:
```php
$json = json_encode($workflowData);
$encoded = '"' . base64_encode(base64_encode($json)) . '"';
```

**Funzionamento interno** (`importFlofo()`):
1. Decodifica doppio base64
2. Fa backup del workflow esistente
3. Cancella tutti i blocchi/elementi/uscite esistenti
4. Rigenera tutti gli UUID (block_id, elem_id) per evitare conflitti
5. Ri-crea il workflow con `updateFlofo()`

### 7.4 Metodo 4: Import New (IN) - Aggiunta blocchi

Aggiunge blocchi di un altro workflow a un workflow esistente senza cancellare i blocchi attuali.

**Endpoint**: `POST /api.php?C=IN&WID=[wXXXXXXXXXXX]`

**Body**: `"base64(base64(JSON_workflow))"`

**Funzionamento interno** (`addToFlofo()`):
1. Rigenera UUID per i nuovi blocchi
2. Imposta `is_start=0` per tutti i blocchi importati (lo start block rimane quello originale)
3. Posiziona i blocchi importati più in alto (y_pos -= 1000)
4. Aggiunge i blocchi al workflow esistente

---

## 8. Autenticazione API

L'API Flow richiede un utente autenticato per le operazioni di modifica (C, U, IS, IN, D). L'autenticazione è gestita a livello di controller tramite il parametro `CWID` che contiene un token di autenticazione combinato con il WID.

Il comando `RD` (Receive Deploy) non richiede autenticazione utente esplicita (è progettato per comunicazione server-to-server).

---

## 9. Esempio Completo: Workflow Semplice "Saluto"

Un workflow che chiede il nome e saluta l'utente:

```json
{
  "workflow": [
    {
      "name": "Workflow Saluto",
      "description": "Chiede il nome e saluta",
      "is_active": 1,
      "supp_langs": "IT",
      "lang": "IT",
      "svc1": "",
      "svc2": "",
      "svc3": "",
      "start_block_id": "block-start-0001-0001-000000000001",
      "blocks": [
        {
          "block_id": "block-start-0001-0001-000000000001",
          "type": "",
          "exec": "wofoEnv->init();\r\n",
          "description": "Chiedi nome",
          "is_start": 1,
          "x_pos": 200,
          "y_pos": 100,
          "elements": [
            {
              "elem_id": "elem-label-0001-0001-000000000001",
              "var_name": "",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Ciao! Come ti chiami?", "uri": "" }
              }
            },
            {
              "elem_id": "elem-input-0001-0001-000000000002",
              "var_name": "$nome",
              "c_type": "1",
              "d_type": "INPUT",
              "e_order": "1",
              "exit_num": "",
              "css": { "display_info": { "mandatory": true } },
              "note": "",
              "langs": {
                "IT": { "label": "Il tuo nome", "uri": "" }
              }
            },
            {
              "elem_id": "elem-btn-0001-0001-000000000003",
              "var_name": "",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "2",
              "exit_num": "0",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Invia", "uri": "" }
              }
            }
          ],
          "exits": [
            { "exit_dir": "block-resp-0002-0001-000000000002" },
            { "exit_dir": "0" }
          ]
        },
        {
          "block_id": "block-resp-0002-0001-000000000002",
          "type": "",
          "exec": "",
          "description": "Mostra saluto",
          "is_start": 0,
          "x_pos": 200,
          "y_pos": 400,
          "elements": [
            {
              "elem_id": "elem-label-0002-0001-000000000004",
              "var_name": "",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Piacere di conoscerti, {$nome}!", "uri": "" }
              }
            },
            {
              "elem_id": "elem-btn-0002-0001-000000000005",
              "var_name": "",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "1",
              "exit_num": "0",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Fine", "uri": "" }
              }
            }
          ],
          "exits": [
            { "exit_dir": "0" },
            { "exit_dir": "0" }
          ]
        }
      ]
    }
  ]
}
```

---

## 10. Esempio Avanzato: Questionario con Scelte

```json
{
  "workflow": [
    {
      "name": "Questionario Preferenze",
      "description": "Raccoglie preferenze dell'utente",
      "is_active": 1,
      "supp_langs": "IT,EN",
      "lang": "IT",
      "svc1": "",
      "svc2": "",
      "svc3": "",
      "start_block_id": "blk-00000001-0001-0001-000000000001",
      "blocks": [
        {
          "block_id": "blk-00000001-0001-0001-000000000001",
          "type": "",
          "exec": "wofoEnv->init();\r\n",
          "description": "Benvenuto",
          "is_start": 1,
          "x_pos": 200,
          "y_pos": 50,
          "elements": [
            {
              "elem_id": "elm-00000001-0001-0001-000000000001",
              "var_name": "",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "<b>Benvenuto!</b><br>Ti faremo alcune domande.", "uri": "" },
                "EN": { "label": "<b>Welcome!</b><br>We'll ask you some questions.", "uri": "" }
              }
            },
            {
              "elem_id": "elm-00000001-0001-0001-000000000002",
              "var_name": "",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "1",
              "exit_num": "0",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Iniziamo!", "uri": "" },
                "EN": { "label": "Let's start!", "uri": "" }
              }
            }
          ],
          "exits": [
            { "exit_dir": "blk-00000002-0001-0001-000000000002" },
            { "exit_dir": "0" }
          ]
        },
        {
          "block_id": "blk-00000002-0001-0001-000000000002",
          "type": "",
          "exec": "",
          "description": "Scelta colore",
          "is_start": 0,
          "x_pos": 200,
          "y_pos": 300,
          "elements": [
            {
              "elem_id": "elm-00000002-0001-0001-000000000003",
              "var_name": "",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Qual e' il tuo colore preferito?", "uri": "" },
                "EN": { "label": "What is your favourite colour?", "uri": "" }
              }
            },
            {
              "elem_id": "elm-00000002-0001-0001-000000000004",
              "var_name": "$colore",
              "c_type": "6",
              "d_type": "SELECTION",
              "e_order": "1",
              "exit_num": "",
              "css": { "display_info": { "subtype": "exclusive" } },
              "note": "",
              "langs": {
                "IT": {
                  "label": {
                    "rosso,0": "Rosso",
                    "blu,0": "Blu",
                    "verde,0": "Verde",
                    "giallo,0": "Giallo"
                  },
                  "uri": ""
                },
                "EN": {
                  "label": {
                    "red,0": "Red",
                    "blue,0": "Blue",
                    "green,0": "Green",
                    "yellow,0": "Yellow"
                  },
                  "uri": ""
                }
              }
            },
            {
              "elem_id": "elm-00000002-0001-0001-000000000005",
              "var_name": "",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "2",
              "exit_num": "0",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Conferma", "uri": "" },
                "EN": { "label": "Confirm", "uri": "" }
              }
            }
          ],
          "exits": [
            { "exit_dir": "blk-00000003-0001-0001-000000000003" },
            { "exit_dir": "0" }
          ]
        },
        {
          "block_id": "blk-00000003-0001-0001-000000000003",
          "type": "",
          "exec": "",
          "description": "Riepilogo",
          "is_start": 0,
          "x_pos": 200,
          "y_pos": 550,
          "elements": [
            {
              "elem_id": "elm-00000003-0001-0001-000000000006",
              "var_name": "",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Hai scelto: {$colore}. Grazie!", "uri": "" },
                "EN": { "label": "You chose: {$colore}. Thank you!", "uri": "" }
              }
            },
            {
              "elem_id": "elm-00000003-0001-0001-000000000007",
              "var_name": "",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "1",
              "exit_num": "0",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Chiudi", "uri": "" },
                "EN": { "label": "Close", "uri": "" }
              }
            }
          ],
          "exits": [
            { "exit_dir": "0" },
            { "exit_dir": "0" }
          ]
        }
      ]
    }
  ]
}
```

---

## 11. Esempio con Branching (Percorsi Multipli)

Un workflow dove il bottone premuto determina il percorso:

```json
{
  "workflow": [
    {
      "name": "Workflow con Branching",
      "description": "Due percorsi in base alla scelta",
      "is_active": 1,
      "supp_langs": "IT",
      "lang": "IT",
      "svc1": "",
      "svc2": "",
      "svc3": "",
      "start_block_id": "blk-branch-0001-0001-000000000001",
      "blocks": [
        {
          "block_id": "blk-branch-0001-0001-000000000001",
          "type": "",
          "exec": "wofoEnv->init();\r\n",
          "description": "Domanda iniziale",
          "is_start": 1,
          "x_pos": 300,
          "y_pos": 50,
          "elements": [
            {
              "elem_id": "elm-branch-0001-0001-000000000001",
              "var_name": "",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Cosa vuoi fare?", "uri": "" }
              }
            },
            {
              "elem_id": "elm-branch-0001-0001-000000000002",
              "var_name": "$scelta",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "1",
              "exit_num": "0",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Percorso A", "uri": "" }
              }
            },
            {
              "elem_id": "elm-branch-0001-0001-000000000003",
              "var_name": "$scelta",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "2",
              "exit_num": "1",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Percorso B", "uri": "" }
              }
            }
          ],
          "exits": [
            { "exit_dir": "blk-branch-0002-0001-000000000002" },
            { "exit_dir": "blk-branch-0003-0001-000000000003" }
          ]
        },
        {
          "block_id": "blk-branch-0002-0001-000000000002",
          "type": "",
          "exec": "",
          "description": "Percorso A",
          "is_start": 0,
          "x_pos": 100,
          "y_pos": 350,
          "elements": [
            {
              "elem_id": "elm-branch-0002-0001-000000000004",
              "var_name": "",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Sei nel Percorso A!", "uri": "" }
              }
            },
            {
              "elem_id": "elm-branch-0002-0001-000000000005",
              "var_name": "",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "1",
              "exit_num": "0",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Fine", "uri": "" }
              }
            }
          ],
          "exits": [
            { "exit_dir": "0" },
            { "exit_dir": "0" }
          ]
        },
        {
          "block_id": "blk-branch-0003-0001-000000000003",
          "type": "",
          "exec": "",
          "description": "Percorso B",
          "is_start": 0,
          "x_pos": 500,
          "y_pos": 350,
          "elements": [
            {
              "elem_id": "elm-branch-0003-0001-000000000006",
              "var_name": "",
              "c_type": "0",
              "d_type": "LABEL",
              "e_order": "0",
              "exit_num": "",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Sei nel Percorso B!", "uri": "" }
              }
            },
            {
              "elem_id": "elm-branch-0003-0001-000000000006",
              "var_name": "",
              "c_type": "2",
              "d_type": "BUTTON",
              "e_order": "1",
              "exit_num": "0",
              "css": { "display_info": {} },
              "note": "",
              "langs": {
                "IT": { "label": "Fine", "uri": "" }
              }
            }
          ],
          "exits": [
            { "exit_dir": "0" },
            { "exit_dir": "0" }
          ]
        }
      ]
    }
  ]
}
```

---

## 12. Regole per la Generazione di JSON Validi

### UUID
- Tutti i `block_id` e `elem_id` devono essere UUID v4 validi (formato: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`)
- Ogni UUID deve essere unico all'interno del workflow
- In fase di import (IS/IN), gli UUID vengono rigenerati automaticamente da Flussu

### Blocco START
- Esattamente **un** blocco deve avere `is_start: 1`
- Il blocco START deve contenere `wofoEnv->init();` nel campo `exec`
- Il `start_block_id` nel workflow deve corrispondere al `block_id` del blocco START

### Uscite
- Ogni blocco deve avere **almeno 2 uscite** (exits[0] e exits[1])
- `exit_dir: "0"` = non collegato (fine percorso)
- `exit_dir: "uuid-blocco"` = collegato al blocco con quel UUID
- I bottoni con `exit_num: "0"` attivano `exits[0]`, quelli con `exit_num: "1"` attivano `exits[1]`

### Elementi
- `e_order` determina l'ordine di visualizzazione (0, 1, 2, ...)
- `var_name` per INPUT e SELECTION deve iniziare con `$` (es. `$nome`, `$email`)
- Per i BUTTON, `exit_num` deve corrispondere all'indice dell'exit desiderata
- Per i LABEL, il testo in `label` supporta HTML

### Lingue
- `supp_langs` contiene le lingue supportate separate da virgola (es. `"IT,EN,FR"`)
- `lang` è la lingua predefinita (prima di `supp_langs`)
- Ogni elemento deve avere almeno le traduzioni per la lingua predefinita
- Le chiavi delle `langs` sono codici lingua a 2 caratteri maiuscoli

### CSS / display_info
- Il campo `css` è un oggetto JSON che contiene metadati di visualizzazione
- `display_info.mandatory: true` rende un campo obbligatorio
- `display_info.subtype` specifica il sottotipo (multiline per INPUT, exclusive/multiple per SELECTION)
- `class` può contenere classi CSS aggiuntive

---

## 13. Flusso di Esecuzione Runtime

Quando un utente interagisce con un workflow caricato:

```
1. Client chiama POST /flussueng.php con WID (primo avvio, SID vuoto)
   ↓
2. Engine crea sessione, restituisce SID + BID + elementi del blocco START
   ↓
3. Client mostra gli elementi (label, input, bottoni)
   ↓
4. Utente compila i campi e preme un bottone
   ↓
5. Client invia POST /flussueng.php con:
   - WID, SID, BID
   - TRM = JSON delle variabili: {"$nome":"Mario", "$ex!0":"Avanti"}
   ↓
6. Engine:
   - Salva le variabili nella sessione
   - Esegue il codice `exec` del blocco successivo
   - Determina l'uscita in base al bottone premuto ($ex!N → exits[N])
   - Restituisce gli elementi del blocco successivo
   ↓
7. Ripeti da 3 fino alla fine del workflow
```

### Formato TRM (Terminal Data):

```json
{
  "$nome": "Mario Rossi",
  "$email": "mario@example.com",
  "$ex!0": "Avanti"
}
```

- `$varname`: valore inserito nell'input con `var_name: "$varname"`
- `$ex!N`: bottone premuto, dove N è il `exit_num` del bottone. Il valore è il testo del bottone.
- Per le SELECTION: `$colore` conterrà `@OPT["valore","Etichetta"]`

---

## 14. Schema Riassuntivo Database

| Tabella | Contenuto |
|---------|-----------|
| `t10_workflow` | Metadati workflow (nome, descrizione, lingue, stato) |
| `t15_workflow_backup` | Backup JSON dei workflow (max 10 per workflow) |
| `t20_block` | Blocchi (UUID, exec, posizione, tipo, start flag) |
| `t25_blockexit` | Uscite dei blocchi (blocco sorgente → blocco destinazione) |
| `t30_blk_elm` | Elementi dei blocchi (tipo, variabile, ordine, CSS) |
| `t40_element` | Testi/traduzioni degli elementi per lingua |

---

## 15. Riferimenti File Sorgente

| File | Ruolo |
|------|-------|
| `src/Flussu/Api/V40/Flow.php` | API gestione workflow (CRUD, import/export) |
| `src/Flussu/Api/V40/Engine.php` | API esecuzione workflow runtime |
| `src/Flussu/Flussuserver/NC/HandlerNC.php` | Core CRUD workflow, blocchi, elementi |
| `src/Flussu/Flussuserver/NC/HandlerBaseNC.php` | Base handler con tipi elemento |
| `src/Flussu/Flussuserver/Worker.php` | Esecutore blocchi e logica |
| `src/Flussu/Flussuserver/Session.php` | Gestione sessione e variabili |
| `webroot/client/api/FlussuApiClient.php` | Client PHP per esecuzione workflow |
| `webroot/flucli/client_api.dev.js` | Client JS per rendering UI chat |

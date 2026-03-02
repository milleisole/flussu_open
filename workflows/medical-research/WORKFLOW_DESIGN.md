# Medical Evidence Research - Flussu Workflow

Workflow che replica la funzionalita' di [Medical-Research](https://github.com/Pinperepette/Medical-Research)
usando il motore workflow Flussu con l'API di Claude Sonnet.

## Import del workflow

Il file `medical_research_workflow.json` contiene il workflow completo in formato
JSON nativo di Flussu. Per importarlo:

### Metodo 1: API Update (workflow esistente)
```
POST /api.php?C=U&WID=[tuo_workflow_id]
Content-Type: application/json
Body: (contenuto del file JSON)
```

### Metodo 2: API Receive Deploy (crea nuovo se non esiste)
```
POST /api.php?C=RD
Content-Type: application/json
Body: (contenuto del file JSON)
```

### Metodo 3: API Import & Substitute
```
POST /api.php?C=IS&WID=[tuo_workflow_id]
Body: base64(base64(contenuto del file JSON))
```

## Architettura

Il workflow interroga 3 database medici (PubMed, ClinicalTrials.gov, Europe PMC)
partendo da un singolo prompt dell'utente, analizza gli articoli trovati con Claude Sonnet
e restituisce una sintesi clinica completa.

### Flusso dei blocchi

```
[Block 1: INPUT]          Raccolta query medica + lingua
       |  (utente clicca "Cerca")
       v
[Block 2: QUERY PLAN]    Claude Sonnet genera query ottimizzate per ogni database
       |  (auto-exit)
       v
[Block 3: API SEARCH]    Interroga PubMed, ClinicalTrials.gov, Europe PMC
       |                  + Invia articoli a Claude per analisi e sintesi
       |  (auto-exit)
       v
[Block 4: RESULTS]       Formatta e mostra sintesi clinica + articoli ranked
       |  (utente clicca "Nuova Ricerca")
       v
[Block 1: INPUT]          (loop)
```

### Dettaglio blocchi

**Block 1 - INPUT** (start block)
- Messaggio di benvenuto multilingua
- Campo input: `$query` (domanda medica)
- Campo input: `$language` (en/it/es/fr/de)
- Bottone "Cerca" -> exit 0 -> Block 2

**Block 2 - AI QUERY PLANNING**
- `initAiAgent()` con system prompt per query planning
- `sendToAi()` chiede a Claude di generare query ottimizzate per i 3 database
- Risultato in `$planned_queries` (JSON con chiavi pubmed, clinicaltrials, europepmc)
- Auto-exit -> Block 3

**Block 3 - DATABASE SEARCH + AI ANALYSIS**
- Parsa `$planned_queries` dall'AI
- Chiama PubMed esearch+efetch (sincrono via `getResultFromHttpApi`)
- Chiama ClinicalTrials.gov API v2 (sincrono)
- Chiama Europe PMC REST API (sincrono)
- Deduplicazione articoli per ID
- Prepara testo riepilogativo (max 25 articoli)
- `initAiAgent()` + `sendToAi()` per analisi + sintesi clinica
- Risultato in `$analysis_result` (JSON con synthesis + top_articles + suggested_queries)
- Auto-exit -> Block 4

**Block 4 - RESULTS**
- Parsa `$analysis_result` dall'AI
- Genera HTML formattato con:
  - Sintesi clinica (Key Findings, Therapeutic Options, Clinical Trials, Evidence Gaps, Recommendation)
  - Articoli top-ranked con punteggio, livello evidenza, tipo articolo
  - Query suggerite per approfondimento
- Variabile `$result_html` con output formattato
- Bottone "Nuova Ricerca" -> exit 0 -> Block 1

### Variabili di sessione

| Variabile | Descrizione | Blocco |
|-----------|-------------|--------|
| `$query` | Domanda medica dell'utente | Block 1 (input) |
| `$language` | Lingua risultati (en/it/es/fr/de) | Block 1 (input) |
| `$planned_queries` | JSON query ottimizzate per ogni DB | Block 2 (output AI) |
| `$article_count` | Numero articoli trovati | Block 3 (calcolato) |
| `$analysis_result` | JSON sintesi + articoli ranked | Block 3 (output AI) |
| `$result_html` | HTML formattato dei risultati | Block 4 (generato) |

## Prerequisiti

1. Claude API key configurata in `config/.services.json`:
```json
{
  "services": {
    "ai_provider": {
      "ant_claude": {
        "auth_key": "sk-ant-...",
        "model": "claude-sonnet-4-20250514",
        "chat-model": "claude-sonnet-4-20250514"
      }
    }
  }
}
```

2. Nessuna API key necessaria per PubMed, ClinicalTrials.gov, Europe PMC
   (API pubbliche con rate limiting)

### Provider AI

- Claude Sonnet = provider 4 (enum Platform::CLAUDE)
- Il workflow usa `$F->sendToAi($text, '$var', 4)` per tutte le chiamate AI
- `$F->initAiAgent($systemPrompt)` per impostare il contesto AI
- `$F->getResultFromHttpApi($url, "GET")` per chiamate HTTP sincrone ai database medici

### API mediche utilizzate

| Database | Base URL | Metodo |
|----------|----------|--------|
| PubMed (NCBI) | `https://eutils.ncbi.nlm.nih.gov/entrez/eutils/` | esearch + efetch |
| ClinicalTrials.gov | `https://clinicaltrials.gov/api/v2/studies` | REST JSON |
| Europe PMC | `https://www.ebi.ac.uk/europepmc/webservices/rest/` | REST JSON |

## Analisi del repository originale

Il workflow replica la funzionalita' di [Medical-Research](https://github.com/Pinperepette/Medical-Research),
un'applicazione FastAPI/Python che:

1. **Query Planning**: usa AI per generare query ottimizzate per ogni database
2. **Multi-source Search**: interroga PubMed, ClinicalTrials.gov, Europe PMC in parallelo
3. **AI Analysis**: analizza ogni articolo con punteggio di rilevanza (0-100),
   classificazione tipo e livello evidenza (1-5 scala Oxford)
4. **Clinical Synthesis**: genera una sintesi clinica strutturata con raccomandazioni

Nella versione Flussu, il flusso e' adattato al modello a blocchi del workflow engine:
- Le chiamate API sono sincrone (`getResultFromHttpApi`) dentro i blocchi
- Le chiamate AI sono asincrone (`sendToAi`) e il risultato e' disponibile nel blocco successivo
- L'analisi per-articolo e la sintesi sono combinate in una singola chiamata AI per efficienza

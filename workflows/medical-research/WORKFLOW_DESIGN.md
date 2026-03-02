# Medical Evidence Research - Flussu Workflow

Workflow che replica la funzionalita' di [Medical-Research](https://github.com/Pinperepette/Medical-Research)
usando il motore workflow Flussu con l'API di Claude Sonnet.

## Architettura

Il workflow interroga 3 database medici (PubMed, ClinicalTrials.gov, Europe PMC)
partendo da un singolo prompt dell'utente, analizza gli articoli trovati con Claude Sonnet
e restituisce una sintesi clinica completa.

### Flusso dei blocchi

```
[Block 1: INPUT]          Raccolta query medica + lingua
       |
       v
[Block 2: QUERY PLAN]    Claude Sonnet genera query ottimizzate per ogni database
       |
       v
[Block 3: API SEARCH]    Interroga PubMed, ClinicalTrials.gov, Europe PMC
       |                  + Invia articoli a Claude per analisi
       v
[Block 4: RESULTS]       Formatta e mostra sintesi clinica + articoli
       |
       v
[Block 1: INPUT]          (loop per nuova ricerca)
```

### Codice dei blocchi

Il codice PHP di ogni blocco e' nel file `block_code.php`.
Lo script SQL per importare il workflow e' in `import_workflow.sql`.

### Prerequisiti

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

### API mediche utilizzate

| Database | Base URL | Metodo |
|----------|----------|--------|
| PubMed (NCBI) | `https://eutils.ncbi.nlm.nih.gov/entrez/eutils/` | esearch + efetch |
| ClinicalTrials.gov | `https://clinicaltrials.gov/api/v2/studies` | REST JSON |
| Europe PMC | `https://www.ebi.ac.uk/europepmc/webservices/rest/` | REST JSON |

# Design Document: AI Document/Media Exchange

**Version**: 1.0
**Date**: 2026-02-22
**Author**: Claude Code / Aldo Prinzi
**Flussu Version**: 4.5.2+

---

## 1. Obiettivo

Estendere Flussu Server per supportare lo scambio di documenti e media con i provider AI:

- **Scenario A**: L'utente carica un'immagine/PDF nel workflow → l'AI lo analizza (vision, OCR evoluto, descrizione immagine)
- **Scenario B**: L'AI genera un'immagine su richiesta del workflow → file salvato e reso disponibile come link

---

## 2. Decisioni Architetturali

| # | Decisione | Scelta |
|---|-----------|--------|
| 1 | Storage AI media | `/Uploads/ai_media/` - sottocartella dedicata |
| 2 | Naming convention | UUID-based (coerente con Fileuploader esistente) |
| 3 | History | Solo riferimento al file (path/URL), no duplicazione |
| 4 | API exchange | Dettagliato in questo documento (sezione 4) |
| 5 | Token tracking | Solo conteggio (input/output), no dettaglio per media |

---

## 3. Architettura dei Componenti

### 3.1 Nuovi Metodi nell'Interfaccia `IAiProvider`

```php
interface IAiProvider
{
    // Esistenti
    public function canBrowseWeb();
    public function chat($preChat, $text, $role);
    public function chat_WebPreview($text, $session, $maxTokens, $temperature);

    // Nuovi - Vision (Scenario A)
    public function canAnalyzeMedia(): bool;
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role): array;

    // Nuovi - Image Generation (Scenario B)
    public function canGenerateImages(): bool;
    public function generateImage($prompt, $size, $quality): array;
}
```

### 3.2 Supporto per Provider

| Provider | Vision (A) | Image Gen (B) | API Nativa |
|----------|-----------|----------------|------------|
| OpenAI   | GPT-4o    | DALL-E 3       | images.generate() |
| Claude   | claude-3-5-sonnet | No (futuro) | content[type=image] |
| Gemini   | gemini-2.0-flash | Imagen (futuro) | inlineData |
| **Stability AI** | **No** | **SD3.5 Large** | **v2beta/stable-image/generate/sd3** |
| Grok     | No        | No             | - |
| DeepSeek | No        | No             | - |
| Others   | No        | No             | - |

### 3.2.1 Stability AI (Stable Diffusion)

Provider dedicato alla generazione immagini via API Stability AI (`api.stability.ai`).

**Modelli disponibili**: sd3.5-large, sd3.5-large-turbo, sd3.5-medium, sd3-large, sd3-large-turbo, sd3-medium

**Platform enum**: `STABILITY = 9`

**Particolarità**:
- Solo image generation (no chat, no vision)
- Usa `aspect_ratio` invece di dimensioni pixel (mapping automatico: "1024x1024" → "1:1")
- Supporta `style_preset` via parametro quality
- API multipart/form-data (non JSON)

### 3.3 Nuovo Controller: `AiMediaController`

Orchestratore centralizzato per le operazioni media:

```
AiMediaController
├── analyzeMedia($sessId, $mediaPath, $prompt, $platform)
│   ├── Valida il file (esiste, tipo supportato)
│   ├── Converte in base64 o URL accessibile
│   ├── Chiama provider.analyzeMedia()
│   └── Ritorna [status, testo_analisi, tokenUsage]
│
└── generateImage($sessId, $prompt, $size, $quality, $platform)
    ├── Chiama provider.generateImage()
    ├── Salva immagine in /Uploads/ai_media/
    ├── Genera URL accessibile
    └── Ritorna [status, file_url, tokenUsage]
```

### 3.4 Workflow Integration (Environment → Executor)

**Nuove funzioni Environment**:
```php
// Scenario A - Analisi media con AI
$F->analyzeMediaWithAi($filePath, $prompt, $varResponseName, $provider);

// Scenario B - Generazione immagine con AI
$F->generateImageWithAi($prompt, $varFileUrlName, $provider, $size, $quality);
```

**Nuovi case Executor**:
- `analyzeMedia` → Chiama `AiMediaController::analyzeMedia()`
- `generateImage` → Chiama `AiMediaController::generateImage()`

---

## 4. Dettaglio API Exchange per Provider

### 4.1 OpenAI Vision (GPT-4o)

Formato messaggio multimodale:
```php
$messages[] = [
    'role' => 'user',
    'content' => [
        ['type' => 'text', 'text' => $prompt],
        ['type' => 'image_url', 'image_url' => [
            'url' => 'data:image/jpeg;base64,' . $base64Data
        ]]
    ]
];
```

### 4.2 OpenAI Image Generation (DALL-E 3)

```php
$response = $client->images()->create([
    'model' => 'dall-e-3',
    'prompt' => $prompt,
    'n' => 1,
    'size' => $size,        // "1024x1024", "1792x1024", "1024x1792"
    'quality' => $quality,   // "standard" o "hd"
    'response_format' => 'b64_json'
]);
// Decode base64 e salva su disco
```

### 4.3 Claude Vision (Anthropic)

Formato messaggio multimodale:
```php
$messages[] = [
    'role' => 'user',
    'content' => [
        ['type' => 'text', 'text' => $prompt],
        ['type' => 'image', 'source' => [
            'type' => 'base64',
            'media_type' => 'image/jpeg',
            'data' => $base64Data
        ]]
    ]
];
```

### 4.4 Gemini Vision

Formato con inlineData:
```php
$response = $gemini->generativeModel($model)->generateContent([
    $prompt,
    new Blob(
        mimeType: MimeType::IMAGE_JPEG,
        data: $base64Data
    )
]);
```

### 4.5 Stability AI (Stable Diffusion)

API multipart/form-data:
```php
// POST https://api.stability.ai/v2beta/stable-image/generate/sd3
$multipart = [
    ['name' => 'prompt', 'contents' => $prompt],
    ['name' => 'model', 'contents' => 'sd3.5-large'],
    ['name' => 'output_format', 'contents' => 'png'],
    ['name' => 'aspect_ratio', 'contents' => '1:1'],  // 1:1, 16:9, 9:16, 3:2, 2:3, 5:4, 4:5
];
// Accept: application/json → risposta con {"image": "base64...", "seed": 12345}
```

**Mapping dimensioni → aspect_ratio**:
| Dimensioni pixel | Aspect Ratio |
|-----------------|--------------|
| 1024x1024       | 1:1          |
| 1792x1024       | 16:9         |
| 1024x1792       | 9:16         |
| 1536x1024       | 3:2          |
| 1024x1536       | 2:3          |

### 4.6 PDF Handling

Per i PDF, il flusso è:
1. Verifica se il provider supporta PDF nativamente (Gemini sì, Claude sì)
2. Per OpenAI: converti pagine in immagini (via Imagick) e invia come array multimodale
3. Il prompt include indicazione che si tratta di un documento PDF

---

## 5. Storage e File Management

### 5.1 Directory Structure

```
/Uploads/
├── ai_media/              ← NUOVA
│   ├── analysis/          ← File inviati per analisi (temporanei)
│   └── generated/         ← Immagini generate dall'AI
├── flussus_01/            ← Upload utente esistente
├── flussus_02/            ← Upload utente esistente
└── temp/                  ← File temporanei esistenti
```

### 5.2 Naming Convention

File generati: `{prefix}_{uniqid}.{ext}`
- Analisi: `anlz_67abc123def45.jpg` (copia temporanea)
- Generati: `gen_67abc123def45.png` (permanente)

### 5.3 Cleanup

- File in `analysis/` → cleanup automatico dopo 24h
- File in `generated/` → persistenti finché il workflow/sessione esiste

---

## 6. Formato Risposta Unificato

```php
// Scenario A - Analisi
[
    "status" => "Ok",           // "Ok" o "Error: ..."
    "text" => "Descrizione...", // Testo dell'analisi AI
    "tokens" => [               // Token usage
        "model" => "gpt-4o",
        "input" => 1500,
        "output" => 200
    ]
]

// Scenario B - Generazione
[
    "status" => "Ok",
    "file_url" => "https://fdwn.site.com/Uploads/ai_media/generated/gen_xxx.png",
    "file_path" => "/Uploads/ai_media/generated/gen_xxx.png",
    "tokens" => [...]
]
```

---

## 7. Flusso di Esecuzione

### Scenario A - Analisi Media

```
Workflow Block Code:
  $F->analyzeMediaWithAi($filePath, "Descrivi questa immagine", '$risultato', 0);

Environment::analyzeMediaWithAi()
  → _addToResArray("analyzeMedia", [...])

Executor::execute() case "analyzeMedia"
  → AiMediaController::analyzeMedia($sessId, $filePath, $prompt, Platform::CHATGPT)
    → Valida file, legge, converte base64
    → FlussuOpenAi::analyzeMedia($preChat, $mediaPath, $prompt)
      → OpenAI API con content multimodale
    → Return [status, text, tokens]
  → $Sess->assignVars($varName, $risultato)
  → $Sess->assignVars('$INFO', json_encode($tokenInfo))
```

### Scenario B - Generazione Immagine

```
Workflow Block Code:
  $F->generateImageWithAi("Un tramonto sul mare", '$url_immagine', 0, "1024x1024", "standard");

Environment::generateImageWithAi()
  → _addToResArray("generateImage", [...])

Executor::execute() case "generateImage"
  → AiMediaController::generateImage($sessId, $prompt, $size, $quality, Platform::CHATGPT)
    → FlussuOpenAi::generateImage($prompt, $size, $quality)
      → OpenAI DALL-E API
    → Decode base64, salva in /Uploads/ai_media/generated/
    → Genera URL pubblico
    → Return [status, file_url, tokens]
  → $Sess->assignVars($varName, $file_url)
```

---

## 8. File Modificati

| File | Tipo Modifica | Descrizione |
|------|--------------|-------------|
| `Contracts/IAiProvider.php` | Estensione | +4 nuovi metodi (can*, analyze*, generate*) |
| `Api/Ai/FlussuOpenAi.php` | Implementazione | Vision + DALL-E |
| `Api/Ai/FlussuClaudeAi.php` | Implementazione | Vision |
| `Api/Ai/FlussuGeminAi.php` | Implementazione | Vision |
| `Api/Ai/FlussuGrokAi.php` | Stub | return false/[] |
| `Api/Ai/FlussuDeepSeekAi.php` | Stub | return false/[] |
| `Api/Ai/FlussuHuggingFaceAi.php` | Stub | return false/[] |
| `Api/Ai/FlussuZeroOneAi.php` | Stub | return false/[] |
| `Api/Ai/FlussuKimiAi.php` | Stub | return false/[] |
| `Api/Ai/FlussuQwenAi.php` | Stub | return false/[] |
| `Api/Ai/FlussuStabilityAi.php` | **NUOVO** | Stable Diffusion image generation |
| `Controllers/AiMediaController.php` | **NUOVO** | Orchestratore media AI |
| `Controllers/AiChatController.php` | Estensione | +case STABILITY |
| `Controllers/Platform.php` | Estensione | +STABILITY = 9 |
| `Flussuserver/Environment.php` | Estensione | +2 funzioni workflow |
| `Flussuserver/Executor.php` | Estensione | +2 case nel switch |
| `config/.services.json.sample` | Estensione | +stability_ai config |

---

## 9. Tipi Media Supportati

### Per Analisi (Scenario A)
- **Immagini**: jpg, jpeg, png, gif, webp
- **Documenti**: pdf (con conversione pagine se necessario)

### Per Generazione (Scenario B)
- **Output**: png (default DALL-E 3 e Stable Diffusion)
- **Provider**: OpenAI (DALL-E 3, provider=0), Stability AI (SD3.5, provider=9)

### Esempio Generazione con Stable Diffusion
```php
// Genera con Stable Diffusion (provider 9 = STABILITY)
$F->generateImageWithAi("A watercolor painting of a Sardinian sunset", '$url_immagine', 9, "1024x1024", "standard");

// Con aspect ratio landscape
$F->generateImageWithAi("Professional logo design", '$logo_url', 9, "16:9", "standard");
```

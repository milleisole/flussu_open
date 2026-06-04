# Flussu Forms v5 — Integrazione 5 preset di design + a11y (evolvendo il v4.1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Portare i 5 preset estetici del design package (`Docs/flussu-new-form/`) e le funzioni di accessibilità nel client form reale `webroot/client/form/`, **evolvendo** il v4.1 esistente senza riscriverlo né rompere il protocollo server.

**Architecture:** Il CSS reale è già interamente guidato da custom properties `--flussu-*` su `:root`, e il dark mode è la classe `body.dark-mode`. Introduciamo un **token-bridge**: un layer di design tokens neutri (`--c-*`, `--fs-*`, `--font-*`, `--r`, `--text-scale`, `--motion` — lo stesso namespace dei mockup) e ri-sorgentiamo i `--flussu-*` su questi. Ogni preset diventa quindi un blocco `[data-theme="<preset>"]` copiabile quasi 1:1 dai `mockup-*.html`; gli a11y (text-scale, reduce-motion, high-contrast) vivono nello stesso layer e funzionano per tutti i preset. Il JS guadagna un `applyTheme()` + lettura del theme JSON di pagina + pannello preferenze in sidebar, persistite in `localStorage['flussu-prefs-v5']` con try/catch.

**Tech Stack:** Vanilla JS (ES2022), CSS custom properties. Nessuna dipendenza nuova. Verifica visiva via pagine di test statiche in `webroot/client/form/test/` + screenshot Chrome DevTools (non esiste test-runner JS nel repo; CLAUDE.md: "no PHPUnit, no linting, no CI").

**Vincolo non negoziabile:** il server resta invariato. Il client continua a parlare con `/api/v2.0/flussueng.php` con lo stesso encoding TRM (`$id`, `$ex!id`, `@OPT[]`, `@SCALE[]`).

---

## File Structure

| File | Responsabilità | Azione |
|---|---|---|
| `webroot/client/form/css/flussu-form.css` | Stylesheet unico. Aggiungiamo: token-bridge in `:root`, 5 blocchi `[data-theme]`, blocchi a11y `[data-a11y-*]`, e ri-aggancio di poche regole-chiave (title/label/input/btn) alla tipografia fluida. Le 2000 righe esistenti restano. | Modify |
| `webroot/client/form/css/presets/_tokens.md` | Tabella di mappatura token mockup→`--flussu-*` (documentazione di riferimento per chi estrae i preset). | Create |
| `webroot/client/form/js/flussu-form.js` | Aggiungiamo: `readThemeConfig()`, `applyTheme()`, `loadPrefs()/savePrefs()`, pannello sidebar (Display/Opzioni), TTS auto. Modifichiamo dark-mode per confluire in `flussu-prefs-v5`. | Modify |
| `webroot/client/form/test/preset-gallery.html` | Pagina statica di coverage: render di ogni tipo elemento (`L/ITT/ITS/ITB/scale/M/A`) con switcher dei 5 preset + toggle a11y. Verifica visiva senza backend. | Create |
| `webroot/client/form/css/flussu-form-PROD.css` / `js/flussu-form-PROD.js` | Versioni minificate di produzione. Rigenerare a fine integrazione. | Modify (fase finale) |

**Decisione di naming/versioning** (risolve REQUISITI §8.3): NON creiamo `flussu-form-5.0.0.js`. Evolviamo i file esistenti e bumpiamo `FlussuClient.version` a `"5.0"`. Coesistenza col v2.0.0 legacy non richiesta (è "lasciato morire").

---

## Token-bridge: la mappatura (riferimento per tutte le task CSS)

I mockup espongono questi token; li mappiamo sui `--flussu-*` esistenti. Dove il mockup ha un token non presente nel baseline, lo aggiungiamo come nuovo `--flussu-*`.

| Mockup token | `--flussu-*` target | Note |
|---|---|---|
| `--c-primary` | `--flussu-primary` | |
| `--c-primary-dark` | `--flussu-primary-dark` | |
| `--c-primary-soft` | `--flussu-primary-light` | |
| `--c-accent` | `--flussu-secondary` | l'"accent" del baseline è `secondary` |
| `--c-ink` | `--flussu-gray-900` | testo principale |
| `--c-ink-2` | `--flussu-gray-600` | testo secondario |
| `--c-ink-muted` | `--flussu-gray-500` | |
| `--c-rule` | `--flussu-gray-200` | bordi/regole |
| `--c-paper` | `--flussu-surface` (nuovo) | superficie card/header |
| `--c-bg` | `--flussu-bg` (nuovo) | sfondo app (gradiente/solid) |
| `--c-focus` | `--flussu-focus` (nuovo) | ring focus |
| `--font-sans` | `--flussu-font-family` | |
| `--fs-title/-label/-body/-input/-btn` | `--flussu-fs-*` (nuovi, clamp) | tipografia fluida |
| `--r`, `--r-sm`, `--r-lg` | `--flussu-border-radius*` | raggi |
| `--text-scale`, `--motion` | idem (nuovi) | a11y |

---

## Task 1: Token-bridge in `:root` (fondamenta, default = look "flussu-classic" attuale)

**Files:**
- Modify: `webroot/client/form/css/flussu-form.css:9-61` (blocco `:root`)

- [ ] **Step 1: Aggiungere il design-token layer + alias dentro `:root`**

In coda al blocco `:root` (prima della `}` a riga ~61) inserire:

```css
    /* ===== v5 design-token layer (token-bridge) ===== */
    /* Sorgente neutra; i preset [data-theme] sovrascrivono SOLO questi. */
    --c-primary: #5B5FCF;
    --c-primary-dark: #4B4FBF;
    --c-primary-soft: #F5F3FF;
    --c-accent: #9333EA;
    --c-ink: #111827;
    --c-ink-2: #4B5563;
    --c-ink-muted: #6B7280;
    --c-rule: #E5E7EB;
    --c-paper: #FFFFFF;
    --c-bg: #FFFFFF;
    --c-focus: rgba(91,95,207,0.45);
    --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
    --r: 0.5rem; --r-sm: 0.375rem; --r-lg: 0.75rem;
    --fs-title: clamp(1.5rem, 1.15rem + 1.5vw, 2.5rem);
    --fs-label: clamp(1rem, 0.92rem + 0.55vw, 1.3rem);
    --fs-body: clamp(0.95rem, 0.9rem + 0.3vw, 1.1rem);
    --fs-input: clamp(1.05rem, 0.95rem + 0.55vw, 1.35rem);
    --fs-btn: clamp(0.98rem, 0.92rem + 0.3vw, 1.12rem);
    --text-scale: 1;
    --motion: 1;
    /* ===== alias: i token storici puntano al nuovo layer ===== */
    --flussu-primary: var(--c-primary);
    --flussu-primary-dark: var(--c-primary-dark);
    --flussu-primary-light: var(--c-primary-soft);
    --flussu-secondary: var(--c-accent);
    --flussu-gray-900: var(--c-ink);
    --flussu-gray-600: var(--c-ink-2);
    --flussu-gray-500: var(--c-ink-muted);
    --flussu-gray-200: var(--c-rule);
    --flussu-font-family: var(--font-sans);
    --flussu-border-radius: var(--r);
    --flussu-border-radius-lg: var(--r-lg);
    /* nuovi token usati direttamente dalle regole v5 */
    --flussu-surface: var(--c-paper);
    --flussu-bg: var(--c-bg);
    --flussu-focus: var(--c-focus);
```

> ⚠️ Le righe esistenti `--flussu-primary: #5B5FCF;` ecc. (9-30) vanno **rimosse** o sostituite dagli alias sopra per evitare duplicati che vincono per ordine. Tenere invece intatti i grigi 50/100/300/400/700/800 non mappati.

- [ ] **Step 2: Agganciare lo sfondo app e la tipografia fluida**

Modificare le regole chiave per consumare i nuovi token:

```css
/* flussu-form.css:139 — sfondo app dal token */
.flussu-app { min-height: 100vh; display: flex; flex-direction: column; position: relative;
    background: var(--flussu-bg); color: var(--c-ink); font-family: var(--font-sans); }

/* :147 header → superficie token invece di white hardcoded */
.flussu-header { background: var(--flussu-surface); border-bottom: 1px solid var(--c-rule);
    position: sticky; top: 0; z-index: 40; }

/* :291 titolo blocco → fluido + text-scale */
.flussu-block-title { font-size: calc(var(--fs-title) * var(--text-scale)); /* (resto invariato) */ }
/* :299 label */
.flussu-label { font-size: calc(var(--fs-label) * var(--text-scale)); /* (resto invariato) */ }
/* :337 input */
.flussu-input { font-size: calc(var(--fs-input) * var(--text-scale)); /* (resto invariato) */ }
/* :468 bottone */
.flussu-btn { font-size: calc(var(--fs-btn) * var(--text-scale)); /* (resto invariato) */ }
```

- [ ] **Step 3: Rendere `--motion` effettivo sulle transizioni globali**

Aggiungere dopo il blocco `:root`:

```css
[data-a11y-motion="reduced"], :root { } /* placeholder, vedi Task 4 */
@media (prefers-reduced-motion: reduce) { :root { --motion: 0; } }
```

E nelle 3 variabili di transizione, moltiplicare la durata per `--motion` non è possibile inline; invece la Task 4 introdurrà `[data-a11y-motion="reduced"] *{transition-duration:.01ms!important;animation-duration:.01ms!important}`.

- [ ] **Step 4: Verifica visiva (no regressione)**

Aprire `webroot/client/form/test/form-test.html` in Chrome. Atteso: il form rende **identico a prima** (default = flussu-classic), perché gli alias riproducono i valori storici. Screenshot di confronto.

- [ ] **Step 5: Commit**

```bash
git add webroot/client/form/css/flussu-form.css
git commit -m "v5 - flussu-form.css: token-bridge layer (no visual change, default=classic)"
```

---

## Task 2: Primo preset — `clinical-calm` (light + dark) — PROTOTIPO

**Files:**
- Modify: `webroot/client/form/css/flussu-form.css` (append in coda)
- Create: `webroot/client/form/test/preset-gallery.html`

- [ ] **Step 1: Aggiungere il blocco preset (estratto da `mockup-clinical-calm.html`)**

In coda a `flussu-form.css`:

```css
/* ===================== PRESET: clinical-calm ===================== */
[data-theme="clinical-calm"] {
    --c-bg: linear-gradient(180deg, #F4FBFA, #FFFFFF 70%);
    --c-paper: #FFFFFF;
    --c-ink: #0F2A2A; --c-ink-2: #3F5B5C; --c-ink-muted: #6E8788;
    --c-rule: rgba(14,110,99,0.16);
    --c-primary: #0E6E63; --c-primary-dark: #08433C; --c-primary-soft: rgba(14,110,99,0.08);
    --c-accent: #0FA89A; --c-focus: rgba(14,110,99,0.55);
    --font-sans: 'IBM Plex Sans', system-ui, -apple-system, sans-serif;
    --r: 14px; --r-sm: 8px; --r-lg: 22px;
}
/* dark variant: confluisce nella classe body.dark-mode già usata dal client */
body.dark-mode[data-theme="clinical-calm"],
[data-theme="clinical-calm"][data-theme-mode="dark"] {
    --c-bg: linear-gradient(180deg, #08171A, #0E2528);
    --c-paper: #112C30;
    --c-ink: #E8F1F1; --c-ink-2: #B5CCCD; --c-ink-muted: #7A9697;
    --c-rule: rgba(173,221,217,0.16);
    --c-primary: #5CC9BC; --c-primary-dark: #34A99B; --c-primary-soft: rgba(92,201,188,0.10);
    --c-accent: #5CC9BC; --c-focus: rgba(92,201,188,0.55);
}
```

- [ ] **Step 2: Caricare il font IBM Plex Sans solo quando il preset è attivo**

Il font va incluso dalla pagina ospite o iniettato dal JS (Task 5). Per la gallery di test, aggiungere il `<link>` Google Fonts nell'`<head>`.

- [ ] **Step 3: Creare `preset-gallery.html` (coverage statica)**

Pagina che linka `flussu-form.css`, imposta `<html data-theme="clinical-calm">`, e contiene markup statico con le classi reali (`flussu-block`, `flussu-block-title`, `flussu-label`, `flussu-input-group`+`flussu-input`, `flussu-choice`, `flussu-btn-primary/secondary`) per i tipi `L/ITT/ITS/ITB`. Includere un `<select>` per cambiare `data-theme` e checkbox per `data-a11y-*`.

- [ ] **Step 4: Verifica visiva + contrasto**

Render in Chrome DevTools. Atteso: palette teal, card morbide, tipografia IBM Plex. Verificare contrasto testo `#0F2A2A` su `#FFFFFF` ≈ 16:1 e primary `#0E6E63` su bianco ≈ 5.6:1 (≥ WCAG AA). Screenshot light + dark.

- [ ] **Step 5: Commit**

```bash
git add webroot/client/form/css/flussu-form.css webroot/client/form/test/preset-gallery.html
git commit -m "v5 - flussu-form: preset clinical-calm (light+dark) + gallery di test"
```

---

## Task 3: Restanti 4 preset (`editorial-noir`, `minimal-radical`, `swiss-brutalist`, `split-editorial`)

**Files:**
- Modify: `webroot/client/form/css/flussu-form.css` (append)

Per **ciascuno** dei 4 preset, ripetere il pattern di Task 2 Step 1 estraendo i valori dai rispettivi `mockup-*.html` (blocchi `:root`, `:root.dark`, `[data-bg=...]`). Valori sorgente in `DESIGN_BRIEFS.md` §Variante 1/2/4/5.

- [ ] **Step 1: `editorial-noir`** — primary `#2B1F12`, accent `#7A1F2B`, paper `rgba(255,253,247,0.7)`, bg `#F4EFE6`, font `'EB Garamond', serif`, `--r: 2px` (editoriale, raggi minimi). Dark da `[data-bg="notte"]`.
- [ ] **Step 2: `minimal-radical`** — tutto `#000`/`#FFF`, accent `#000`, font system-ui, `--r: 0`, bordi netti. È già "high-contrast" per costruzione.
- [ ] **Step 3: `swiss-brutalist`** — primary `#0A0A0A`, accent `#FFE500`, paper `#FFFFFF`, bg `#FAFAF7`, font `'Space Grotesk'`, `--r: 0`, focus giallo.
- [ ] **Step 4: `split-editorial`** — primary `#1A1714`, accent `#B85C2E`, bg gradient warm-paper, font `'Inter'` (titoli serif `Newsreader` — richiede token font separato `--font-title`, vedi nota sotto). Dark da `[data-bg="inchiostro"]`.

> **Nota font-pair**: `editorial-noir`, `swiss-brutalist` e `split-editorial` usano due famiglie (titolo vs corpo). Aggiungere token `--font-title: var(--font-sans)` nel bridge (Task 1) e farlo consumare a `.flussu-block-title`. Ogni preset con coppia setta `--font-title` proprio.

- [ ] **Step 5: Verifica visiva tutti i preset** nella gallery (switcher) — screenshot di ciascuno light+dark.
- [ ] **Step 6: Commit** `v5 - flussu-form: preset editorial-noir/minimal-radical/swiss-brutalist/split-editorial`

---

## Task 4: Accessibilità — high-contrast, text-size, reduce-motion (data-attribute)

**Files:**
- Modify: `webroot/client/form/css/flussu-form.css` (append)

- [ ] **Step 1: High contrast (override neutro, indipendente dal preset)**

```css
[data-a11y-contrast="high"] {
    --c-bg: #fff; --c-paper: #fff;
    --c-ink: #000; --c-ink-2: #000; --c-ink-muted: #000;
    --c-rule: #000; --c-primary: #000; --c-primary-dark: #000;
    --c-primary-soft: #ff0; --c-accent: #000; --c-focus: #000;
}
body.dark-mode[data-a11y-contrast="high"] {
    --c-bg: #000; --c-paper: #000; --c-ink: #fff; --c-ink-2: #fff;
    --c-ink-muted: #fff; --c-rule: #fff; --c-primary: #fff;
    --c-primary-dark: #fff; --c-primary-soft: #00f; --c-accent: #fff; --c-focus: #ff0;
}
[data-a11y-contrast="high"] * { box-shadow: none !important; }
```

- [ ] **Step 2: Dimensione testo (3 livelli via `--text-scale`)**

```css
[data-a11y-textsize="large"]      { --text-scale: 1.25; }
[data-a11y-textsize="extra-large"]{ --text-scale: 1.5; }
```
(Funziona perché title/label/input/btn già moltiplicano per `--text-scale` — Task 1 Step 2.)

- [ ] **Step 3: Reduce motion**

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; }
}
[data-a11y-motion="reduced"] *,
[data-a11y-motion="reduced"] *::before,
[data-a11y-motion="reduced"] *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; }
```

- [ ] **Step 4: Focus ring amplificato in high-contrast**

```css
[data-a11y-contrast="high"] :focus-visible { outline: 4px solid var(--c-focus); outline-offset: 2px; }
```

- [ ] **Step 5: Verifica** ogni toggle nella gallery × 2 preset. Screenshot.
- [ ] **Step 6: Commit** `v5 - flussu-form: a11y high-contrast/text-size/reduce-motion (data-attr)`

---

## Task 5: JS — lettura theme di pagina, `applyTheme()`, preferenze persistite

**Files:**
- Modify: `webroot/client/form/js/flussu-form.js`

- [ ] **Step 1: `readThemeConfig()`** — legge in ordine: `<script type="application/json" id="flussu-theme">`, poi `window.flussuTheme`, poi `data-flussu-theme` sul container. Ritorna `{preset, darkMode, colors?, typography?}`. (REQUISITI §5bis.4)

- [ ] **Step 2: `applyTheme(cfg)`** — `document.documentElement.setAttribute('data-theme', cfg.preset || '')`; se `cfg.typography.fontUrl` inietta un `<link>` Google Fonts; se `cfg.colors` setta override inline `--c-*` su `:root`. Chiamare in `buildInterface()` prima del render.

- [ ] **Step 3: Preferenze unificate** — `loadPrefs()/savePrefs(obj)` su `localStorage['flussu-prefs-v5']` con try/catch + fallback in-memory. Migrare dark-mode (`flussuDarkMode`) e theme/textsize/contrast/motion/ttsAuto qui. (REQUISITI §4.2.6, §5bis.8)

- [ ] **Step 4: Applicare le pref a11y all'avvio** — settare `data-a11y-contrast/-textsize/-motion` su `<html>` da `loadPrefs()`.

- [ ] **Step 5: Verifica** — pagina di test con `<script id="flussu-theme">{"preset":"swiss-brutalist","darkMode":"user"}</script>`; ricaricare e confermare che il preset si applica e persiste. Screenshot.

- [ ] **Step 6: Commit** `v5 - flussu-form.js: theme config + applyTheme + prefs-v5 persistite`

---

## Task 6: JS — sidebar a pannelli (Opzioni / Display) con i nuovi controlli

**Files:**
- Modify: `webroot/client/form/js/flussu-form.js:134-173` (markup sidebar) + nuovi handler

- [ ] **Step 1: Riscrivere il markup sidebar** in due sezioni (REQUISITI §5bis.8), restando sulla sidebar **a sinistra** (HANDOFF §12.4): Opzioni (Lingua, Ricomincia, Lettura auto) + Display (Tema=grid preset, Dimensione testo, Alto contrasto, Modalità scura, Riduci animazioni).
- [ ] **Step 2: Grid preset** — bottoni per i 5 preset; click → `applyTheme({preset})` + `savePrefs`.
- [ ] **Step 3: Toggle a11y** — onchange → set `data-a11y-*` su `<html>` + `savePrefs`.
- [ ] **Step 4: TTS auto** — toggle che, in `renderElements()`, dopo il render legge titolo+label via `textToSpeech()` (già esistente, riga ~1577). Lingua da `LNG`.
- [ ] **Step 5: Verifica** — interazione completa nella gallery + un workflow reale. Screenshot.
- [ ] **Step 6: Commit** `v5 - flussu-form.js: sidebar Opzioni/Display + preset grid + a11y + TTS auto`

---

## Task 7: Tipi elemento mancanti (gap HANDOFF §6 / REQUISITI §3)

**Files:**
- Modify: `webroot/client/form/js/flussu-form.js` (funzioni `renderSelection`, `renderTextInput`, `renderButton`, `renderMedia`, `renderAnchor`) + CSS per `.flussu-matrix`/`.flussu-scale-btn`

- [ ] **Step 1: `ITS` multiple (checkbox)** — in `renderSelection`, subtype `multiple` → `<input type=checkbox>`, indicatore quadrato; encoding `@OPT["a","Label A","b","Label B"]`.
- [ ] **Step 2: `ITS` dropdown** — subtype `selection/default` → `<select>` stilizzato sui token.
- [ ] **Step 3: `ITT` semantico** — `display_info.type` ∈ {email,phone,number,date,url} → `input type` corrispondente + validazione; `textarea` per multilinea.
- [ ] **Step 4: `ITB` primary/secondary** — risolvere il bug del primary hardcoded `id==0` (REQUISITI §4.1.1): se `display_info.role` assente e ci sono ≥2 bottoni → tutti `secondary`; 1 solo bottone → primary. `skip-validation` → bypass validazione.
- [ ] **Step 5: `M` url vuoto** — non emettere container se `content===""` (REQUISITI §3.6).
- [ ] **Step 6: `A` come bottone** — `display_info.subtype:"button"` → render bottone-link.
- [ ] **Step 7: Verifica** coverage nella gallery (ogni tipo × ogni preset). Screenshot.
- [ ] **Step 8: Commit** `v5 - flussu-form.js: coprire ITS multiple/dropdown, ITT semantico, ITB role, M/A`

---

## Task 8: Pulizia debiti tecnici (REQUISITI §4.2) + build PROD

**Files:**
- Modify: `webroot/client/form/js/flussu-form.js`, `css/flussu-form.css`, file `-PROD.*`

- [ ] **Step 1: Rimuovere Tailwind CDN** (`flussu-form.js:93`) — non usato seriamente.
- [ ] **Step 2: Rimuovere `flagcdn.com`** (`flussu-form.js:357`) — bandiere come emoji unicode (HANDOFF §9) o SVG inline.
- [ ] **Step 3: i18n thank-you/errori** — schermo "Grazie" e messaggi E00/E89/E99 tradotti (REQUISITI §4.3.5).
- [ ] **Step 4: Rigenerare** `flussu-form-PROD.css` e `flussu-form-PROD.js` minificati.
- [ ] **Step 5: Bump** `FlussuClient.version = "5.0"`, `serverVersion = "5.0"`.
- [ ] **Step 6: Commit** `v5 - flussu-form: drop Tailwind/flagcdn CDN, i18n thank-you/errori, build PROD, bump v5.0`

---

## Domande aperte da risolvere con l'utente prima delle Task 6-7

(da REQUISITI §8 / HANDOFF §12 — non bloccano Task 1-5)

1. **`GUI` element**: comportamento reale atteso? (REQUISITI §3.8 — oggi solo placeholder hidden). → Task 7 lo lascia invariato finché non specificato.
2. **`display_info.role` per il primary button**: il server lo può emettere in modo retrocompatibile, o lo inferiamo solo client-side? (REQUISITI §8.6)
3. **TTS lingua**: seguire `LNG` del workflow o lingua browser? (HANDOFF §12.1) → proposta: `LNG`.
4. **Scala 1-10** vs 1-5: testare overflow layout matrice lunga. (HANDOFF §12.2)
5. **Font Google opzionali**: ok il fetch a fonts.googleapis.com (unica dipendenza CDN ammessa da HANDOFF §9) o servirli self-hosted?

---

## Self-Review

- **Spec coverage**: i 5 preset (Task 2-3), a11y high-contrast/text/motion/TTS (Task 4,6), theme JSON di pagina (Task 5), sidebar 2-tab (Task 6), tipi mancanti + bug primary (Task 7), pulizia CDN/i18n (Task 8). Rename `flussu-*`→`fl-*` dell'HANDOFF §3 **scartato di proposito**: il baseline reale usa già `flussu-*`, il rename sarebbe puro churn ad alto rischio senza valore — documentato come deviazione consapevole.
- **Token consistency**: nomi token (`--c-*`, `--fs-*`, `--text-scale`, `--motion`, `--flussu-surface/-bg/-focus/-font-title`) usati coerentemente tra Task 1→4. I `data-a11y-*` (`contrast`/`textsize`/`motion`) coerenti tra CSS (Task 4) e JS (Task 5-6).
- **Deviazione dall'HANDOFF**: HANDOFF assume baseline `fl-*` + scaffold `mockup-typeform.html`; noi evolviamo il `flussu-form.js`/`.css` reali (`flussu-*`). Il design (palette/tipografia/a11y) è identico; cambia solo il substrato di classi.

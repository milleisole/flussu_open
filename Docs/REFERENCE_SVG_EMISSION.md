# Reference — Inline SVG emission & rendering contract

How Flussu emits AI-generated inline SVG (charts/diagrams) and how a consuming
client (e.g. u-prompt / Canonity workflow editor) should render it.

## What Flussu guarantees

1. **Complete SVG.** The reasoning models used for chart steps (qwen3.x,
   GLM-4.5/4.6) have their *thinking* disabled by default and a raised output
   budget, so long SVGs are no longer cut off mid-render. The response ends with
   `</svg>`. (Config: `<provider>.enable_thinking` / `.thinking`, `.max_tokens`.)

2. **Verbatim SVG.** `Command::htmlSanitize` (the output HTML-safener) extracts
   `<svg>...</svg>` blocks **before** the Markdown pass (Parsedown) and reinserts
   them untouched. The SVG leaves Flussu exactly as the model produced it —
   `xmlns` intact, no `<a>`/`<p>`/`<pre>` injected around or inside it.

> Historical note: before this fix, Parsedown safe-mode escaped the `<svg>` tags
> but still autolinked the bare `xmlns` URL (`xmlns="<a href=...>...</a>"`),
> paragraph-wrapped blank-line blocks (`<p>`), and turned 4-space-indented lines
> into `<pre><code>` — fragmenting the SVG into an unrenderable mess. That no
> longer happens.

## What the consuming client must do

The SVG now arrives clean. To turn it into an `<img>` data-URI:

- **Remove any defensive un-mangling** (un-autolinking `<a>`, stripping
  `<p>/<pre>/<code>`, entity-decoding). It is now a no-op.
- **Keep the SVG out of your own Markdown renderer.** If you still run the reply
  through CommonMark/markdown-it, extract `<svg>...</svg>` first and reinsert the
  `<img>` afterwards — otherwise *your* renderer re-introduces the exact same
  corruption (autolinked `xmlns`, `<p>`, code blocks).
- **Base64 the UTF-8 bytes**, not `btoa` (SVG text contains accented chars like
  "più/quantità/à"), and set `charset=utf-8` in the data-URI.

### Case A — content still goes through a Markdown renderer
```js
function normalizeFlussuOutput(raw, renderMarkdown /* (txt) => html */) {
  const svgs = [];
  const withPlaceholders = raw.replace(/<svg[\s\S]*?<\/svg>/gi, (svg) => {
    const i = svgs.length;
    svgs.push(svg);                       // verbatim: no cleanup needed
    return `\n\nFLUSSU_SVG_PLACEHOLDER_${i}\n\n`;
  });
  let html = renderMarkdown(withPlaceholders);
  html = html.replace(/FLUSSU_SVG_PLACEHOLDER_(\d+)/g, (_, n) => {
    const b64 = Buffer.from(svgs[Number(n)], 'utf8').toString('base64');
    return `<img src="data:image/svg+xml;charset=utf-8;base64,${b64}" alt="chart" style="max-width:100%">`;
  });
  return html;
}
```

### Case B — SVG does not pass through any Markdown renderer
```js
function normalizeFlussuOutput(raw) {
  return raw.replace(/<svg[\s\S]*?<\/svg>/gi, (svg) => {
    const b64 = Buffer.from(svg, 'utf8').toString('base64');
    return `<img src="data:image/svg+xml;charset=utf-8;base64,${b64}" alt="chart" style="max-width:100%">`;
  });
}
```

### PHP (league/commonmark) — Case A
```php
function normalizeFlussuOutput(string $raw, callable $renderMarkdown): string {
    $svgs = [];
    $withPlaceholders = preg_replace_callback('/<svg[\s\S]*?<\/svg>/i', function ($m) use (&$svgs) {
        $i = count($svgs); $svgs[] = $m[0];   // verbatim
        return "\n\nFLUSSU_SVG_PLACEHOLDER_{$i}\n\n";
    }, $raw);
    $html = $renderMarkdown($withPlaceholders);
    return preg_replace_callback('/FLUSSU_SVG_PLACEHOLDER_(\d+)/', function ($m) use ($svgs) {
        $b64 = base64_encode($svgs[(int)$m[1]]);
        return '<img src="data:image/svg+xml;charset=utf-8;base64,' . $b64
             . '" alt="chart" style="max-width:100%">';
    }, $html);
}
```

<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Capability prompt + parser for the `flussu-file` fenced block.
 *
 * See Docs/DESIGN_AI_FILE_EMISSION.md for the full contract.
 * -------------------------------------------------------*/
namespace Flussu\Helpers;

final class FlussuFileBlock {

    /** Hard cap on the decoded `content` string (bytes). Over cap → reject. */
    public const MAX_CONTENT_BYTES = 262144; // 256 KB, decision §12.2

    /** Common format hints — NOT enforced. `format` is free-form metadata: the
     *  content body is always Markdown (or raw text for structured formats) and
     *  the CALLER converts it into the requested file format. */
    public const COMMON_FORMATS = ['md', 'txt', 'pdf', 'docx', 'html', 'json', 'csv', 'svg', 'xlsx'];

    /** Default format when the LLM omits the field. */
    public const DEFAULT_FORMAT = 'md';

    /**
     * Capability prompt prepended to $sendText when `allowFile=true`.
     * English by design (§12.1) — independent of conversation language.
     * One source of truth: any tuning happens HERE.
     */
    public const CAPABILITY_PROMPT = <<<TXT
# File-emission capability (active in this environment)

You can generate real files for the user. When the user asks for a file (markdown, pdf, docx, html, json, plan, report, contract, document, table-to-download, etc.), you produce the file by closing your reply with EXACTLY ONE fenced block in this exact form, with nothing after it:

```flussu-file
{"format":"md","filename":"report.md","content":"<the entire file body as a string>"}
```

**This block IS the file.** A backend service reads it, materialises an actual file in the requested format, and delivers it to the user. You do NOT need any other tool, you do NOT need to ask permission, and you must NEVER tell the user "I cannot generate files", "I am text-only", "you can paste this into Word / Google Docs", "I will give you the text instead", or any similar disclaimer — those statements are false in this environment. Never apologise about format limitations and never say a format is unsupported.

How to choose `format`:
- Set `format` to the file format the user actually asked for — e.g. `md`, `txt`, `pdf`, `docx`, `html`, `json`, `csv`, `svg`, `xlsx`, etc. If the user did not specify one, use `md`.
- ALWAYS write `content` as Markdown (the full document body, in plain Markdown text). The delivery layer converts that Markdown into the requested `format` and assigns the right extension — do NOT try to produce pdf/docx/binary bytes yourself, and do NOT mention this conversion step to the user.
- Only exception: for inherently-structured formats (`json`, `csv`, `svg`) write the raw structured content directly instead of Markdown.

Images are NOT files — NEVER emit a flussu-file block for a raster image:
- A flussu-file block carries a TEXT document body; it can NEVER carry an actual picture.
- If the user asks for a photo, picture, illustration, render, ritratto, foto, immagine, logo, poster, icon or any RASTER image, DO NOT emit a flussu-file block and DO NOT describe it in prose. Reply with EXACTLY one line of JSON, nothing before or after:
{"FLUSSU_IMG":"<a detailed English prompt suitable for an image generation model>"}
  The backend generates the actual image with the configured image-generation provider. Inline SVG is allowed only for charts/diagrams/schematics.

Content rules:
- `content` MUST be the entire file body, complete. Do NOT abbreviate, do NOT truncate with "...", do NOT write "and so on". If the user asks for a 7-day plan, write all 7 days. If they ask for 50 items, write all 50.
- `filename` is a suggestion (e.g. "weekly_plan.md"). The backend may sanitise or override it.
- The block MUST be the LAST thing in your reply. Nothing after the closing ```.
- Before the block, write ONE short natural sentence in the user's language (e.g. "Ecco il piano richiesto." / "Here's the report.") and NOTHING ELSE. Do NOT preview the file's contents in prose. Do NOT describe what's inside. Do NOT introduce it with phrases like "you will find inside…". Just one short sentence, then the block.
- Emit at most ONE block per reply.

When NOT to emit a block:
- The user is asking a question, having a conversation, brainstorming, or wants an analysis to read inline.
- In those cases, reply normally in prose with NO block.
TXT;

    /**
     * Inspect a raw LLM reply and return a structured result.
     *
     * Return shape:
     *   [
     *     'found'    => bool,          // a `flussu-file` fence was present
     *     'valid'    => bool,          // payload parsed AND validated
     *     'reason'   => string|null,   // when !valid: short tag; null when valid or !found
     *     'stripped' => string,        // reply with the (first) block removed; equals input when !found
     *     'trailing' => bool,          // true when non-whitespace text follows the first fence (warn-log fodder)
     *     'extras'   => int,           // extra blocks found beyond the first (warn-log fodder)
     *     'payload'  => array|null,    // validated payload: ['format'=>..,'content'=>..,'filename'=>?]
     *   ]
     *
     * NOTE: `payload['filename']` is caller-suggested and unsanitised. Apply
     * basename() + null-byte strip before any filesystem use.
     */
    public static function parse(string $reply): array {
        $out = [
            'found' => false, 'valid' => false, 'reason' => null,
            'stripped' => $reply, 'trailing' => false, 'extras' => 0, 'payload' => null,
        ];

        // Tolerant fence regex: accepts the JSON payload on its own line
        // (Mistral/GPT style) OR on the same line as the opening fence
        // (Claude style, observed in production). `\s` includes \n/\r so
        // the `s` modifier + lazy `.*?` correctly handles either layout.
        if (!preg_match_all('~```flussu-file\s*(.*?)\s*```~s', $reply, $m, PREG_OFFSET_CAPTURE)) {
            return $out;
        }
        $out['found']  = true;
        $out['extras'] = count($m[0]) - 1;

        // First match drives behaviour; extras are reported, not applied.
        [$firstFenceText, $firstFenceOffset] = $m[0][0];
        $firstFenceLen = strlen($firstFenceText);
        $innerJson     = $m[1][0][0];

        $out['stripped'] = substr($reply, 0, $firstFenceOffset)
                         . substr($reply, $firstFenceOffset + $firstFenceLen);
        $out['trailing'] = (trim(substr($reply, $firstFenceOffset + $firstFenceLen)) !== '');

        $decoded = json_decode($innerJson, true);
        if (!is_array($decoded)) {
            $out['reason'] = 'json_decode_failed';
            return $out;
        }
        if (!isset($decoded['content']) || !is_string($decoded['content'])) {
            $out['reason'] = 'missing_or_nonstring_content';
            return $out;
        }
        if (strlen($decoded['content']) > self::MAX_CONTENT_BYTES) {
            $out['reason'] = 'oversize';
            return $out;
        }
        if (!mb_check_encoding($decoded['content'], 'UTF-8')) {
            $out['reason'] = 'invalid_utf8';
            return $out;
        }
        // `format` is free-form metadata: the caller (frontend) converts the
        // Markdown body into whatever the user requested (pdf, docx, txt, svg,
        // xlsx, ...). Flussu does NOT render — it only passes the tag through.
        $format = strtolower(trim((string)($decoded['format'] ?? self::DEFAULT_FORMAT)));
        $format = preg_replace('/[^a-z0-9]+/', '', $format);
        if ($format === '') {
            $format = self::DEFAULT_FORMAT;
        }

        $payload = ['format' => $format, 'content' => $decoded['content']];
        if (isset($decoded['filename']) && is_string($decoded['filename'])) {
            $payload['filename'] = $decoded['filename'];
        }

        $out['valid']   = true;
        $out['payload'] = $payload;
        return $out;
    }
}

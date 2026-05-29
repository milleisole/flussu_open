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

    /** Allowed values for the optional `format` field. */
    public const ALLOWED_FORMATS = ['md', 'json', 'html'];

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

**This block IS the file.** A backend service reads it, materialises an actual file, and delivers it to the user. You do NOT need any other tool, you do NOT need to ask permission, and you must NEVER tell the user "I cannot generate files", "I am text-only", "you can paste this into Word / Google Docs", "I will give you the text instead", or any similar disclaimer — those statements are false in this environment. Never apologise about format limitations. Never say a format is unsupported: pick the closest of the three below.

How to choose `format`:
- `md`   — every text/markdown document. If the user asks for a **pdf or docx**, still emit `md` — the backend renders the final pdf/docx automatically. Do NOT mention this conversion step to the user.
- `html` — only when the user explicitly needs rich visual layout (complex styling, custom tables, embedded media references).
- `json` — only when the user asks for structured data, a configuration, or a list of records.

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
        $format = $decoded['format'] ?? self::DEFAULT_FORMAT;
        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            $out['reason'] = 'unknown_format';
            return $out;
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

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
# File-emission capability
You may, **only when the user explicitly asks for a file** (e.g. "make me a markdown of…", "give me a pdf with…", "send me a json containing…"), close your reply with EXACTLY ONE fenced block in this format and nothing after it:

```flussu-file
{"format":"md","filename":"report.md","content":"<file body as a string>"}
```

Rules:
- `format` must be one of: md, json, html. Default md if omitted.
- `content` is the file body as a string (also for json/html — emit the raw text).
- `filename` is optional and only a suggestion.
- Any prose BEFORE the block is your normal message to the user. Do NOT describe the file there.
- Emit at most ONE block per reply. If unsure whether the user wants a file, do NOT emit the block — just reply in prose.
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

        if (!preg_match_all('~```flussu-file\s*\R(.*?)\R```~s', $reply, $m, PREG_OFFSET_CAPTURE)) {
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

<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0- Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * --------------------------------------------------------------------*
 * TBD- UNFINISHED
 * 
 * CLASS-NAME:       Flussu OpenAi Controller - v3.0
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.4
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 * New: Whe AI reply with a Flussu Command, the result contains
 * an ARRAY: ["FLUSSU_CMD"=>the command and parameters] and
 *           ["TEXT"=>the text part to show to the user]
 * if it's not an ARRAY it's just text to show to the user
 * Added "translate" function for internal labels translation
 * -------------------------------------------------------------------*/
namespace Flussu\Controllers;

use Flussu\General;
use Flussu\Contracts\IAiProvider;
use Flussu\Contracts\IAiProviderExtra;
use Flussu\Api\Ai\FlussuOpenAi;
use Flussu\Api\Ai\FlussuGrokAi;
use Flussu\Api\Ai\FlussuGeminAi;
use Flussu\Api\Ai\FlussuClaudeAi;
use Flussu\Api\Ai\FlussuDeepSeekAi;
use Flussu\Api\Ai\FlussuHuggingFaceAi;
use Flussu\Api\Ai\FlussuZeroOneAi;
use Flussu\Api\Ai\FlussuKimiAi;
use Flussu\Api\Ai\FlussuQwenAi;
use Flussu\Api\Ai\FlussuStabilityAi;
use Flussu\Api\Ai\FlussuMistralAi;
use Flussu\Api\Ai\FlussuZaiGlmAi;
use Flussu\Api\Ai\FlussuTogetherAi;
use Log;

class AiChatController
{
    private $_linkify=0;
    private IAiProvider $_aiClient;
    private Platform $_platform;
    public function __construct(Platform $platform=Platform::CHATGPT,$model="",$chat_model=""){
        $this->_platform = $platform;
        switch ($platform) {
            case Platform::CHATGPT:
                $this->_aiClient= new FlussuOpenAi($model,$chat_model);
                $this->_linkify=0;
                break;
            case Platform::GROK:
                $this->_aiClient= new FlussuGrokAi($model);
                $this->_linkify=0;
                break;
            case Platform::GEMINI:
                $this->_aiClient= new FlussuGeminAi($model);
                $this->_linkify=0;
                break;
            case Platform::CLAUDE:
                $this->_aiClient= new FlussuClaudeAi($model);
                $this->_linkify=1;
                break;
            case Platform::DEEPSEEK:
                $this->_aiClient= new FlussuDeepSeekAi($model);
                $this->_linkify=0;
                break;
            case Platform::HUGGINGFACE:
                $this->_aiClient= new FlussuHuggingFaceAi($model);
                $this->_linkify=0;
                break;
            case Platform::ZEROONE:
                $this->_aiClient= new FlussuZeroOneAi($model, $chat_model);
                $this->_linkify=0;
                break;
            case Platform::KIMI:
                $this->_aiClient= new FlussuKimiAi($model, $chat_model);
                $this->_linkify=0;
                break;
            case Platform::QWEN:
                $this->_aiClient= new FlussuQwenAi($model, $chat_model);
                $this->_linkify=0;
                break;
            case Platform::STABILITY:
                $this->_aiClient= new FlussuStabilityAi($model);
                $this->_linkify=0;
                break;
            case Platform::MISTRAL:
                $this->_aiClient= new FlussuMistralAi($model, $chat_model);
                $this->_linkify=0;
                break;
            case Platform::ZAI_GLM:
                $this->_aiClient= new FlussuZaiGlmAi($model, $chat_model);
                $this->_linkify=0;
                break;
            case Platform::TOGETHER:
                $this->_aiClient= new FlussuTogetherAi($model, $chat_model);
                $this->_linkify=0;
                break;
        }
        //$this->_aiClient= new FlussuOpenAi($model,$chat_model);
    }

    function initAgent($sessId,$initChatText="") {
$today = (new \DateTime('now', new \DateTimeZone('Europe/Rome')))->format('l, j F Y');
$initChatText2=<<<TXT
    You are a standard AI assistant designed to assist users answering in the same language the users write the questions, if the user writes the questions, so if the user writes in italian, you should reply in italian, if the user writes in english, you should reply in english, and so on.
    Your name is Flussu-AI and be aware of your responses, it should be clear, concise, and helpful.
    Today's date is $today.
    # When asked to generate images:
    ## If the user asks for a chart, diagram, schematic or anything structural that fits SVG, produce it as inline SVG.
    ## If the user asks for a photo, picture, illustration, render, ritratto, foto, immagine, painting or any RASTER image (jpg/png), DO NOT describe it and DO NOT draw it as SVG. Reply with EXACTLY one line of JSON in this format, with no text before or after:
    {"FLUSSU_IMG":"<a detailed English prompt suitable for an image generation model>"}
    The backend will detect this marker, generate the actual raster image and replace your reply with the resulting picture. Never include explanations alongside the JSON marker.
    ## CRITICAL — when NOT to emit FLUSSU_IMG:
    Only emit the FLUSSU_IMG marker when the user explicitly asks to GENERATE, CREATE, DRAW, RENDER, MAKE or PRODUCE a visual artifact (or uses Italian equivalents: genera, crea, disegna, fammi, mostrami, dipingi, raffigura, illustra). Merely mentioning words like "chatbot", "AI", "interface", "image", "foto", "immagine" inside a discussion is NOT a request — reply with normal text in those cases.
    ### Examples that MUST emit the marker:
    - "fammi un'immagine di un gatto"
    - "disegnami un tramonto"
    - "create a portrait of an astronaut cat"
    - "render a photo of a futuristic city"
    ### Examples that MUST NOT emit the marker (reply with text):
    - "i chatbot rispondono sempre in modo strutturato" (talking ABOUT chatbots)
    - "questo messaggio è tipico dei chatbot" (meta-comment, no visual asked)
    - "what is an image generation model?" (asking ABOUT image gen, not asking to generate one)
    - "explain how diffusion models create pictures" (explanation, not generation)
    - "le foto del concerto erano belle" (talking about photos, not asking for one)
    # When asked to look something up on the web / scrape a site / get fresh information online:
    ## If the user says things like "cerca su internet", "search the web", "look it up online", "aggiornati con i dati disponibili sul web", or asks about events/people/products/news that may have changed since your training, OR mentions a specific URL/site to consult, reply with EXACTLY one line of JSON, no text before or after:
    {"FLUSSU_WEB":{"action":"search","query":"<concise web search query>","lang":"<two-letter language code>"}}
    ## To fetch a specific URL the user provided:
    {"FLUSSU_WEB":{"action":"fetch","url":"https://...","intent":"<what to extract or summarize>"}}
    ## To research with deeper scraping of the top hits:
    {"FLUSSU_WEB":{"action":"research","query":"<query>","lang":"<lang>","top":5,"deep":2}}
    The backend will run the search/scrape and synthesize the answer for you. Never include explanations or extra text alongside the JSON marker.
    ---
    * WARNING! * : If you do not know the right answer to a question, you should politely inform the user that you do not have that information.
    ---
    If anyone ask info about the client they are using to chat with you, you can say that the client is 'the standard Canonity client', a web application that allows users to interact with Canonity Server and any API solution like the AI assistants like you.
    If anyone asks something about the following products or persons or companies here are some info you can reply to check official websites:
    # For Canonity (an italian startup who build a multi LLM text and graphic tool) check https://canonity.com
    # For U-PROMPT, the italian-europaen platform for democratizing the AI check https://u-prompt.com
TXT;
        $preChat=General::ObjRestore("AiCht".$sessId,true); 
        if (is_null($preChat) || empty($preChat) || count($preChat)==0 || is_null($preChat[0]['content'])) {
            $preChat[]=['role'=>'user','content'=>$initChatText2."\r\n---\r\n".$initChatText];
            General::ObjPersist("AiCht".$sessId,$preChat); 
        }
    }


    function translate($instructions,$elems, $langFrom, $langTo) {
        //$theElems=json_encode($elemsArray);
        $preChat=[];
        $preChat[0]["role"]="user";
        if ($langFrom=="")
            $preChat[0]["content"]="Translate the following labels from to ".$langTo.", be aware the first element of the json (name) must not be translated, leave it as is. Translate just the label text, then mantaining the same json format.\n".$instructions."\n";
        else
            $preChat[0]["content"]="Translate the following labels from ".$langFrom." to ".$langTo.", be aware the first element of the json (name) must not be translated, leave it as is. Translate just the label text, then mantaining the same json format.\n".$instructions."\n";
        try{
            $result=$this->_aiClient->Chat($preChat,$elems, "user");
            $ret=$result[1];
            return ["Ok",$ret];
        } catch (\Throwable $e) {
            return ["Error: ",$e->getMessage()];
        }
    }

    /**
     * @param string $sessId
     * @param string $sendText
     * @param bool $webPreview
     * @param string $role
     * @param int $maxTokens
     * @param float $temperature
     * @return array:
     *      [0] string: status ("Ok", "Error", etc.)
     *      [1] string|array: textual reply or command for the client
     *      [2] array|null: token usage ["input" => X, "output" => Y] or null
     *
     */
    function chat($sessId, $sendText, $webPreview=false, $role="user", $maxTokens=150, $temperature=null) {
        if ($temperature === null) {
            $temperature = (float) config('services.ai_provider.default_temperature', 0.65);
        }
        $result="(no result)";
        $tokenUsage = null;

        // v5.x — extract any <client_special_request> envelope BEFORE the model
        // sees the prompt: the tag is metadata for the server, not for the LLM.
        $clientSpecialReq = $this->_extractClientSpecialRequest($sendText);
        // v5.x — populated by the FLUSSU_IMG dispatch so the final envelope can
        // carry a meaningful filename for the client.
        $generatedFile = null;

        $preChat=General::ObjRestore("AiCht".$sessId,true);

        if (is_null($preChat) || empty($preChat))
            $preChat=[];

        // [DIAG_QWEN_v1] inbound to chat()
        General::Log_nocaller('[DIAG_QWEN_v1] chat() IN platform=' . $this->_platform->name
            . ' sess=' . $sessId
            . ' webPreview=' . ($webPreview ? '1' : '0')
            . ' role=' . $role
            . ' sendText[0..200]=' . substr((string)$sendText, 0, 200), true);
        $diagRolesIn = [];
        foreach ($preChat as $i => $m) {
            $r = $m['role'] ?? ($m['type'] ?? '?');
            $c = (string)($m['content'] ?? ($m['message'] ?? ''));
            $diagRolesIn[] = $r . '(' . strlen($c) . 'c)';
        }
        General::Log_nocaller('[DIAG_QWEN_v1] chat() preChat count=' . count($preChat)
            . ' roles=[' . implode(',', $diagRolesIn) . ']', true);

        try{
            if (!$this->_aiClient->canBrowseWeb()){
                $sendText=$this->sostituisciURLconHTML($sendText);
            }
            if (!$webPreview)
                $result=$this->_aiClient->Chat($preChat,$sendText, $role);
            else
                $result=$this->_aiClient->Chat_WebPreview($sendText, $sessId,$maxTokens,$temperature);

            // Extract token usage from result (third element if available)
            if (is_array($result) && isset($result[2])) {
                $tokenUsage = $result[2];
            }

            // v5.0 — auto-dispatch to image generation if the model emitted the FLUSSU_IMG marker
            if (is_array($result) && isset($result[1]) && is_string($result[1])) {
                $rawReply = $result[1];
                General::addRowLog("AI chat raw reply (first 300 chars) [platform=" . $this->_platform->name . "]: " . substr($rawReply, 0, 300));
                // [DIAG_QWEN_v1] full raw reply (up to 1500 chars) for marker analysis
                General::Log_nocaller('[DIAG_QWEN_v1] rawReply length=' . strlen($rawReply)
                    . ' contains_FLUSSU_IMG_substr=' . (stripos($rawReply, 'FLUSSU_IMG') !== false ? 'yes' : 'no')
                    . ' contains_FLUSSU_WEB_substr=' . (stripos($rawReply, 'FLUSSU_WEB') !== false ? 'yes' : 'no')
                    . ' first1500=' . substr($rawReply, 0, 1500), true);
                $imgPrompt = $this->extractFlussuImgPrompt($rawReply);
                $webPayload = ($imgPrompt === '') ? $this->extractFlussuWebPayload($rawReply) : [];
                General::Log_nocaller('[DIAG_QWEN_v1] extract imgPrompt=' . ($imgPrompt !== '' ? 'YES("' . substr($imgPrompt, 0, 100) . '")' : 'no')
                    . ' webPayload=' . (!empty($webPayload) ? 'YES(' . json_encode($webPayload) . ')' : 'no'), true);
                // v5.x — sanity check: small models (e.g. qwen-turbo) over-fit on the
                // FLUSSU_IMG example pattern when the user message is long/noisy and
                // contains visual-sounding nouns (e.g. "chatbot"). Drop the marker
                // unless the user actually asked for an image, and rewrite the reply
                // so the user does not see the raw JSON marker.
                if ($imgPrompt !== '' && !self::hasImageIntent($sendText)) {
                    General::Log_nocaller('FLUSSU_IMG marker IGNORED (no image intent in user prompt) — sendText[0..200]=' . substr((string)$sendText, 0, 200), true);
                    $imgPrompt = '';
                    $result[1] = "_(Ho frainteso la tua richiesta. Puoi riformularla? Se vuoi un'immagine, scrivi esplicitamente 'genera un'immagine di ...' o 'fammi una foto di ...'.)_";
                    $rawReply  = $result[1];
                }
                if ($imgPrompt !== '') {
                    General::Log_nocaller("FLUSSU_IMG marker matched, prompt: " . substr($imgPrompt, 0, 200), true);
                    [$imgReply, $imgTokenUsage, $imgUrl] = $this->_dispatchImageGeneration($sessId, $imgPrompt);
                    $result[1] = $imgReply;
                    // remember the generated file so the response envelope can carry a meaningful filename
                    if (!empty($imgUrl)) {
                        $generatedFile = ['prompt' => $imgPrompt, 'url' => $imgUrl];
                    }
                    if (is_array($imgTokenUsage)) {
                        if (is_array($tokenUsage)) {
                            // Merge chat tokens (text routing + marker emission) with image-gen synthetic usage
                            $tokenUsage = [
                                'model'  => ($tokenUsage['model'] ?? '') . '+' . ($imgTokenUsage['model'] ?? ''),
                                'input'  => ($tokenUsage['input']  ?? 0) + ($imgTokenUsage['input']  ?? 0),
                                'output' => ($tokenUsage['output'] ?? 0) + ($imgTokenUsage['output'] ?? 0),
                            ];
                        } else {
                            $tokenUsage = $imgTokenUsage;
                        }
                        // Reflect the merged usage back into $result[2] so the rest of chat() sees it
                        $result[2] = $tokenUsage;
                    }
                } else if (stripos($rawReply, 'FLUSSU_IMG') !== false) {
                    General::addRowLog("FLUSSU_IMG mentioned but parser failed to extract a prompt");
                } else if (!empty($webPayload)) {
                    // v5.x — auto-dispatch to web research if the model emitted the FLUSSU_WEB marker
                    General::addRowLog("FLUSSU_WEB marker matched, action=" . ($webPayload['action'] ?? 'search')
                        . ", query=" . substr((string)($webPayload['query'] ?? ($webPayload['url'] ?? '')), 0, 200));
                    [$webReply, $webTokenUsage] = $this->_dispatchWebResearch($sessId, $webPayload, $sendText);
                    $result[1] = $webReply;
                    if (is_array($webTokenUsage)) {
                        if (is_array($tokenUsage)) {
                            $tokenUsage = [
                                'model'  => ($tokenUsage['model'] ?? '') . '+' . ($webTokenUsage['model'] ?? ''),
                                'input'  => ($tokenUsage['input']  ?? 0) + ($webTokenUsage['input']  ?? 0),
                                'output' => ($tokenUsage['output'] ?? 0) + ($webTokenUsage['output'] ?? 0),
                            ];
                        } else {
                            $tokenUsage = $webTokenUsage;
                        }
                        $result[2] = $tokenUsage;
                    }
                } else if (stripos($rawReply, 'FLUSSU_WEB') !== false) {
                    General::addRowLog("FLUSSU_WEB mentioned but parser failed to extract a payload");
                }
            }

            $limitReached=$this->_checkLimitReached($result);

            $res=$this->replyIsCommand($result[1]);
            $ret=$res[1];
            $pReslt="";
            if ($res[0]){
                $replaceText="```json\r\n".$res[2]."\r\n```";
                $pReslt=str_replace($replaceText,"",$result[1]);
                if ($pReslt==$result[1]){
                    $replaceText="```json\n".$res[2]."\n```";
                    $pReslt=str_replace($replaceText,"",$result[1]);
                }
                if ($pReslt==$result[1]){
                    $replaceText=$res[2];
                    $pReslt=str_replace($replaceText,"",$result[1]);
                }
                $ret["TEXT"]="";
                if (strlen(trim($pReslt))>1)
                    $ret["TEXT"]="{MD}".$pReslt."{/MD}";
            } else {
                if (is_array($result) && isset($result[1]))
                    $pReslt=trim($result[1]);
                else
                    $pReslt=json_encode($result[1]);
            }
            $status="Unknown...";
            if ($limitReached){
                //$pReslt="(AI response limit reached.)";
                $ret=$result;
                $status="Error";
            } else {
                if (!empty($pReslt) && is_array($result)){
                    $History=$result[0];
                    $History[]= [
                        'role' => 'assistant',
                        'content' => $pReslt,
                    ];
                    General::ObjPersist("AiCht".$sessId,$History);
                    if (!$res[0])
                        $ret="{MD}".$pReslt."{/MD}";
                }
                $status="Ok";
            }
            // v5.x — emit a <server_special_response> envelope when either the
            // client asked for server metadata (chat summary title, ...) or
            // server-side artefacts were produced this turn (e.g. a generated
            // file whose filename the client needs).
            $serverMeta = [];
            if ($generatedFile !== null) {
                $serverMeta['filename'] = $this->_buildGeneratedFilename($generatedFile['prompt'], $generatedFile['url']);
            }
            if ($clientSpecialReq !== null || !empty($serverMeta)) {
                $ret = $this->_appendServerSpecialResponse($ret, $clientSpecialReq, $serverMeta, $sessId);
            }
            return [$status, $ret, $tokenUsage];
        } catch (\Throwable $e) {
            return ["Error: ", $e->getMessage(), null];
        }
    }

    /**
     * v4.6 - $LLMextra tool-use path.
     *
     * Bypasses the standard chat history and $setAgent-based flow: forwards the
     * parsed $LLMextra payload (model, tools, tool_choice, system, temperature,
     * max_tokens) to the underlying provider and returns a normalized result.
     *
     * @param string $sessId   Session id (reserved for future logging/history).
     * @param string $sendText User prompt (goes into the single "user" message).
     * @param array  $extra    Parsed $LLMextra object (already JSON-decoded).
     * @return array           [string $status, string $textReply, ?array $tokenUsage, array $llmExtra]
     *                         $status: "Ok" or "Error: ..."
     *                         $textReply: assistant text ('' on tool_use)
     *                         $tokenUsage: ['model','input','output'] for $INFO (or null)
     *                         $llmExtra: normalized object returned to the client
     */
    function chatExtra($sessId, $sendText, array $extra): array {
        if (!($this->_aiClient instanceof IAiProviderExtra)) {
            $err = 'Provider does not support $LLMextra / tool-use';
            return ["Error", $err, null, ['type' => 'error', 'error' => $err]];
        }
        try {
            [$textReply, $tokenUsage, $llmExtra] = $this->_aiClient->chatExtra($sendText, $extra);
            $status = isset($llmExtra['type']) && $llmExtra['type'] === 'error' ? 'Error' : 'Ok';
            return [$status, $textReply, $tokenUsage, $llmExtra];
        } catch (\Throwable $e) {
            return ["Error: ", $e->getMessage(), null, ['type' => 'error', 'error' => $e->getMessage()]];
        }
    }

    /**
     * v5.x — Extract a <client_special_request>{...JSON...}</client_special_request>
     * envelope from the user prompt. The tag is metadata for the server (e.g.
     * "give me a chat summary title for this conversation") and must never
     * reach the underlying LLM, so this method also strips the tag from $text
     * in place. Returns the decoded JSON payload, or null if absent/malformed.
     */
    private function _extractClientSpecialRequest(string &$text): ?array {
        if ($text === '' || stripos($text, '<client_special_request>') === false) return null;
        if (!preg_match('#<client_special_request>\s*(\{.*?\})\s*</client_special_request>#is', $text, $m)) return null;
        $payload = json_decode($m[1], true);
        if (!is_array($payload)) {
            General::Log_nocaller('client_special_request: malformed JSON, ignoring. body[0..200]=' . substr($m[1], 0, 200), true);
            return null;
        }
        $text = trim(preg_replace('#<client_special_request>.*?</client_special_request>#is', '', $text));
        General::Log_nocaller('client_special_request received: request_id=' . ($payload['request_id'] ?? '?')
            . ' request=' . ($payload['request'] ?? '?'), true);
        return $payload;
    }

    /**
     * v5.x — Build and append the <server_special_response>{...}</server_special_response>
     * envelope to the user-facing reply. The envelope carries:
     *   - the answer to a <client_special_request> (currently only
     *     "chat summary title", delegated to MISTRAL), AND/OR
     *   - server-generated metadata about artefacts produced this turn
     *     (e.g. a meaningful filename for a generated image).
     * Both kinds are merged into one envelope so the client has a single
     * parsing path.
     *
     * $ret is either a string ("{MD}...{/MD}") for plain replies, or an array
     * with a "TEXT" key when the underlying provider emitted a FLUSSU_CMD —
     * we append in both shapes.
     */
    private function _appendServerSpecialResponse($ret, ?array $clientReq, array $serverMeta, string $sessId) {
        if ($clientReq === null && empty($serverMeta)) return $ret;

        $payload = [];
        if ($clientReq !== null) {
            $reqId   = (string)($clientReq['request_id'] ?? '');
            $reqType = strtolower(trim((string)($clientReq['request'] ?? '')));
            $payload['request_id'] = $reqId;
            switch ($reqType) {
                case 'chat summary title':
                    $payload['chat summary title'] = $this->_generateChatSummaryTitle($sessId);
                    break;
                default:
                    // unknown request type: surface it back so the client knows we saw it
                    General::Log_nocaller('client_special_request: unknown request type "' . $reqType . '", echoing without payload', true);
                    $payload['error'] = 'unknown request type';
            }
        }
        if (!empty($serverMeta)) {
            // server-generated metadata wins over any same-named client field
            $payload = array_merge($payload, $serverMeta);
        }

        $tag = '<server_special_response>'
            . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</server_special_response>';

        if (is_string($ret)) {
            return $ret . $tag;
        }
        if (is_array($ret)) {
            $ret['TEXT'] = (string)($ret['TEXT'] ?? '') . $tag;
            return $ret;
        }
        return $ret;
    }

    /**
     * v5.x — Build a meaningful, filesystem-safe filename for a generated file.
     * Strategy:
     *   1. Determine extension from the URL path (default: png).
     *   2. Ask MISTRAL for a snake_case, max-5-words stem based on the prompt.
     *   3. If MISTRAL fails, fall back to a slugified prefix of the prompt.
     *   4. If even that yields nothing, fall back to "generated_file".
     */
    private function _buildGeneratedFilename(string $imgPrompt, string $imageUrl): string {
        $ext = 'png';
        $path = parse_url($imageUrl, PHP_URL_PATH) ?: '';
        if ($path !== '' && preg_match('#\.([A-Za-z0-9]{2,5})$#', $path, $m)) {
            $candidate = strtolower($m[1]);
            if (in_array($candidate, ['png','jpg','jpeg','webp','gif','svg','bmp'], true)) {
                $ext = $candidate;
            }
        }

        $stem = $this->_askServiceForFilenameStem($imgPrompt);
        if ($stem === '') {
            $stem = $this->_slugifyShort($imgPrompt, 5);
        }
        if ($stem === '') {
            $stem = 'generated_file';
        }
        return $stem . '.' . $ext;
    }

    /**
     * v5.x — Ask the configured service model for a short, filesystem-safe
     * filename stem based on the image prompt. Returns the sanitized stem,
     * or '' on failure.
     */
    private function _askServiceForFilenameStem(string $imgPrompt): string {
        if ($imgPrompt === '') return '';
        $instruction = "Given this image generation prompt, return ONLY a short, filesystem-safe filename in snake_case, lowercase, ASCII only, max 5 words, no extension, no quotes, no path, no explanation — just the bare filename stem.\n\nPROMPT: " . $imgPrompt;
        $stem = $this->_callServiceModel($instruction);
        if ($stem === '') return '';
        $stem = trim($stem, "\"' \t\n\r.");
        return $this->_sanitizeFilenameStem($stem);
    }

    /**
     * v5.x — Service-model factory. Reads `services.ai_provider.service_model`
     * (default: "mistral") and returns a fresh provider instance for internal
     * tasks: chat-summary titles, filename generation, future server-side
     * helpers. Operators can switch the underlying model without touching
     * code by editing config/.services.json.
     *
     * Returns null if the configured provider cannot be instantiated (e.g.
     * missing API key); callers must tolerate that path.
     */
    private function _getServiceProvider(): ?IAiProvider {
        $modelKey = strtolower(trim((string) config('services.ai_provider.service_model', 'mistral')));
        try {
            switch ($modelKey) {
                case 'mistral':                              return new FlussuMistralAi();
                case 'open_ai': case 'openai': case 'chatgpt': case 'gpt':
                                                             return new FlussuOpenAi();
                case 'ant_claude': case 'claude':            return new FlussuClaudeAi();
                case 'ggl_gemini': case 'gemini':            return new FlussuGeminAi();
                case 'xai_grok': case 'grok':                return new FlussuGrokAi();
                case 'deepseek':                             return new FlussuDeepSeekAi();
                case 'qwen':                                 return new FlussuQwenAi();
                case 'kimi': case 'moonshot':                return new FlussuKimiAi();
                case 'zeroone_ai': case 'zeroone': case '01ai':
                                                             return new FlussuZeroOneAi();
                case 'zai_glm': case 'zai': case 'glm':      return new FlussuZaiGlmAi();
                case 'together':                             return new FlussuTogetherAi();
                case 'huggingface':                          return new FlussuHuggingFaceAi();
                default:
                    General::Log_nocaller('service_model: unknown identifier "' . $modelKey . '", falling back to mistral', true);
                    return new FlussuMistralAi();
            }
        } catch (\Throwable $e) {
            General::Log_nocaller('service_model: instantiation of "' . $modelKey . '" failed: ' . $e->getMessage(), true);
            return null;
        }
    }

    /**
     * v5.x — Send a one-shot prompt to the configured service model and
     * return the trimmed text reply (or '' on any failure). Used by the
     * server-side helpers (chat title, filename) that don't need history.
     */
    private function _callServiceModel(string $userPrompt): string {
        $svc = $this->_getServiceProvider();
        if ($svc === null) return '';
        try {
            $result = $svc->chat([], $userPrompt, "user");
            if (!is_array($result) || !isset($result[1]) || !is_string($result[1])) return '';
            return trim($result[1]);
        } catch (\Throwable $e) {
            General::Log_nocaller('service_model call error: ' . $e->getMessage(), true);
            return '';
        }
    }

    /**
     * v5.x — Reduce $text to a snake_case ASCII slug of at most $maxWords words.
     * Used as fallback when MISTRAL is unavailable.
     */
    private function _slugifyShort(string $text, int $maxWords): string {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words) || empty($words)) return '';
        $slug = implode('_', array_slice($words, 0, $maxWords));
        return $this->_sanitizeFilenameStem($slug);
    }

    /**
     * v5.x — Filesystem-safe stem: lowercase, ASCII-only, _-separated, max 5
     * underscore-segments, max 60 chars.
     */
    private function _sanitizeFilenameStem(string $s): string {
        if ($s === '') return '';
        // strip diacritics best-effort
        if (function_exists('iconv')) {
            $tr = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($tr !== false) $s = $tr;
        }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        $s = preg_replace('/_+/', '_', $s);
        $s = trim((string)$s, '_-');
        if ($s === '') return '';
        // cap to 5 segments
        $parts = explode('_', $s);
        if (count($parts) > 5) $s = implode('_', array_slice($parts, 0, 5));
        if (strlen($s) > 60) {
            $s = substr($s, 0, 60);
            $s = trim((string)$s, '_-');
        }
        return $s;
    }

    /**
     * v5.x — Ask the configured service model for a short title that summarises
     * the persisted chat history of $sessId. The history is filtered to skip
     * the system-prompt seed (stored as role=user containing the FLUSSU_IMG
     * instructions). Returns '' on any failure (no service-model key, network
     * error, etc.) — the caller still emits the envelope so the client can
     * correlate by request_id.
     */
    private function _generateChatSummaryTitle(string $sessId): string {
        $history = General::ObjRestore("AiCht" . $sessId, true);
        if (!is_array($history) || empty($history)) return '';

        // skip the seed system prompt (it always carries the FLUSSU_IMG marker text)
        $convo = $history;
        if (isset($convo[0]['content']) && stripos((string)$convo[0]['content'], 'FLUSSU_IMG') !== false) {
            array_shift($convo);
        }
        if (empty($convo)) return '';

        $convoText = '';
        foreach ($convo as $msg) {
            $r = strtoupper((string)($msg['role'] ?? '?'));
            $c = (string)($msg['content'] ?? '');
            if (strlen($c) > 2000) $c = substr($c, 0, 2000) . '…';
            $convoText .= $r . ": " . $c . "\n\n";
        }

        $instruction = "Given the following conversation, return ONLY a short summary title in the user's language. STRICT LIMIT: maximum 7 words — count carefully and stay under this limit. No quotes, no explanation, no leading/trailing punctuation, no period at the end — just the bare title text.\n\nCONVERSATION:\n" . $convoText;

        $title = $this->_callServiceModel($instruction);
        if ($title === '') return '';

        $title = trim($title, "\"' \t\n\r.");
        if ($title === '') return '';
        // Defensive cap: if the service model ignored the 7-word constraint, hard-trim here.
        $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($words) && count($words) > 7) {
            $title = implode(' ', array_slice($words, 0, 7));
        }
        return $title;
    }

    function replyIsCommand(string $text): array {
        try{
            if (!is_null($text) && strlen($text)>10) {
                $text2=$this->extractFlussuJson($text);   
                if (!is_null($text2) && strlen($text2)>10 && strlen($text2)<300) {
                    $abc=json_decode($text2,true);
                    if (count($abc)>0 && is_array($abc) && isset($abc['FLUSSU_CMD']) )
                        return [true, $abc,$text2]; // not a command
                }
            }
        } catch (\Throwable $e){
            // do nothing... $e is just a debuggable point
        }
        return [false, $text,""]; // not a command
    }
    function extractFlussuJson($inputString) {
        // Trova la posizione di "FLUSSU_CMD" nella stringa
        $flussuPos = strpos($inputString, '"FLUSSU_CMD"');
        if ($flussuPos === false) {
            return ""; // "FLUSSU_CMD" non trovato
        }

        // Trova la parentesi graffa aperta più vicina prima di "FLUSSU_CMD"
        $startPos = strrpos($inputString, '{', $flussuPos - strlen($inputString));
        if ($startPos === false) {
            return ""; // Nessuna parentesi graffa aperta trovata
        }

        // Conta le parentesi graffe per trovare la chiusura corrispondente
        $braceCount = 1;
        $currentPos = $startPos + 1;
        $length = strlen($inputString);

        while ($currentPos < $length && $braceCount > 0) {
            if ($inputString[$currentPos] === '{') {
                $braceCount++;
            } elseif ($inputString[$currentPos] === '}') {
                $braceCount--;
            }
            $currentPos++;
        }

        // Se braceCount è 0, abbiamo trovato la chiusura corrispondente
        if ($braceCount === 0) {
            $jsonString = substr($inputString, $startPos, $currentPos - $startPos);
            // Verifica se il JSON è valido
            if (json_decode($jsonString) !== null) {
                return $jsonString;
            }
        }

        return "";
    }
    /**
     * v5.x — Backend safety net for false-positive FLUSSU_IMG markers.
     * Small chat models (e.g. qwen-turbo) sometimes emit the FLUSSU_IMG JSON
     * pattern when the user message is long and contains visual-sounding nouns
     * (e.g. "chatbot", "interface"), even though the user is not asking for
     * an image. This method scans the *tail* of the user prompt for explicit
     * image-generation triggers — we look at the tail because workflow
     * templates typically prepend long static context before the actual
     * user input.
     *
     * Returns true when the user prompt plausibly asked for an image.
     */
    public static function hasImageIntent(string $userText): bool {
        if ($userText === '') return false;
        // Tail-of-prompt scan: workflow templates typically prepend long static
        // context, so the actual user input is at the end. 1500 bytes is enough
        // for any reasonable user message; UTF-8 truncation is harmless because
        // the /iu regex flag handles unicode word boundaries.
        $len = strlen($userText);
        $tail = $len > 1500 ? substr($userText, $len - 1500) : $userText;
        // Italian + English imperative verbs and image-related nouns.
        $patterns = [
            // IT verbs: genera/generami, crea/creami, disegna/disegnami, dipingi,
            // raffigura, mostrami, fammi, illustra, rappresenta
            '/\b(?:gener[ai](?:mi|re)?|cre[ai](?:mi|re)?|disegn[a-z]*|dipin[gt][a-z]*|raffigur[a-z]+|mostr[aei](?:mi)?|fammi|fammene|illustr[a-z]+|rappresent[a-z]+|produc[a-z]+|render[a-z]*)\b/iu',
            // IT nouns
            '/\b(?:foto|fotografia|fotografie|immagin[ei]|ritratt[oi]|illustrazion[ei]|disegn[oi]|dipint[oi]|raffigurazion[ei]|icona|loghi|logo|banner|poster|mockup|grafica|diagramm[ai])\b/iu',
            // EN verbs
            '/\b(?:generate|create|make|produce|draw|sketch|paint|depict|render|design|visuali[sz]e|illustrate|portray)\b/iu',
            // EN nouns
            '/\b(?:image|picture|photo|photograph|illustration|drawing|painting|portrait|landscape|graphic|sketch|render(?:ing)?|icon|logo|banner|poster|mockup)\b/iu',
            // file-extension hints
            '/\.(?:png|jpg|jpeg|webp|svg|gif)\b/iu',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $tail)) return true;
        }
        return false;
    }

    /**
     * v5.0 — Extract the prompt from a {"FLUSSU_IMG":"..."} marker emitted by the chat model.
     * Returns '' if the marker is absent or malformed.
     */
    function extractFlussuImgPrompt(string $text): string {
        $pos = strpos($text, '"FLUSSU_IMG"');
        if ($pos === false) return '';
        $start = strrpos($text, '{', $pos - strlen($text));
        if ($start === false) return '';
        $brace = 1;
        $i = $start + 1;
        $len = strlen($text);
        while ($i < $len && $brace > 0) {
            if ($text[$i] === '{')      $brace++;
            elseif ($text[$i] === '}')  $brace--;
            $i++;
        }
        if ($brace !== 0) return '';
        $json = substr($text, $start, $i - $start);
        $decoded = json_decode($json, true);
        if (is_array($decoded) && isset($decoded['FLUSSU_IMG']) && is_string($decoded['FLUSSU_IMG']))
            return trim($decoded['FLUSSU_IMG']);
        return '';
    }

    /**
     * v5.x — Extract the FLUSSU_WEB payload object emitted by the chat model.
     * Mirrors extractFlussuImgPrompt() but the marker value is a JSON object instead of a string.
     *
     * Recognised payload shape:
     *   {"FLUSSU_WEB": {"action":"search|fetch|research", "query":"...", "url":"...",
     *                   "lang":"it", "geo":"it", "top":5, "deep":1, "seeds":["url",…]}}
     *
     * Returns the decoded payload as an associative array, or [] if the marker is
     * absent / malformed / not an object.
     *
     * Example operator system prompt snippet to teach a model to emit it:
     *   Quando l'utente chiede di cercare su internet, di consultare un sito o di
     *   aggiornarsi con dati dal web, rispondi SOLO con il marker JSON:
     *     {"FLUSSU_WEB":{"action":"search","query":"<query>","lang":"it"}}
     *   Per fetchare un URL specifico:
     *     {"FLUSSU_WEB":{"action":"fetch","url":"https://..."}}
     */
    function extractFlussuWebPayload(string $text): array {
        $pos = strpos($text, '"FLUSSU_WEB"');
        if ($pos === false) return [];
        $start = strrpos($text, '{', $pos - strlen($text));
        if ($start === false) return [];
        $brace = 1;
        $i = $start + 1;
        $len = strlen($text);
        while ($i < $len && $brace > 0) {
            if ($text[$i] === '{')      $brace++;
            elseif ($text[$i] === '}')  $brace--;
            $i++;
        }
        if ($brace !== 0) return [];
        $json = substr($text, $start, $i - $start);
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['FLUSSU_WEB'])) return [];
        $payload = $decoded['FLUSSU_WEB'];
        if (is_string($payload)) {
            // Lenient mode: accept {"FLUSSU_WEB":"query string or URL"} too.
            $val = trim($payload);
            if ($val === '') return [];
            if (preg_match('#^https?://#i', $val))
                return ['action' => 'fetch', 'url' => $val];
            return ['action' => 'search', 'query' => $val];
        }
        if (!is_array($payload)) return [];
        return $payload;
    }

    /**
     * v5.0 — Dispatch the extracted image prompt to AiMediaController and return a tuple
     * [string $markdownReply, ?array $tokenUsage, ?string $url]. The caller (chat()) wraps
     * the reply in {MD}...{/MD} and merges the image-gen tokenUsage with the chat one,
     * so this method must NOT add wrap tags. The third element is the bare URL (or null
     * on error) so the caller can derive a meaningful filename for the client envelope.
     */
    private function _dispatchImageGeneration(string $sessId, string $imgPrompt): array {
        try {
            $imgPlatform = $this->_platform->resolveImageProvider();
            if ($imgPlatform !== $this->_platform)
                General::addRowLog("Image dispatch redirected: " . $this->_platform->name . " → " . $imgPlatform->name);
            $media = new AiMediaController($imgPlatform);
            if (!$media->canGenerate())
                return ["_(Image generation is not supported by the current provider.)_", null, null];
            $img = $media->generateImage($sessId, $imgPrompt);
            if (($img[0] ?? '') !== 'Ok' || empty($img[1])) {
                $rawErr = (string)($img[1] ?? 'unknown');
                General::addRowLog("AI chat-image error (raw): " . $rawErr);
                $friendly = AiMediaController::humanizeImageError($rawErr);
                return ["_(" . $friendly . ")_", null, null];
            }

            $url   = $img[1];
            $usage = $img[2] ?? null;
            General::addRowLog("AI chat-image dispatched: " . $url . " for session " . $sessId);
            return ["![generated](" . $url . ")", is_array($usage) ? $usage : null, $url];
        } catch (\Throwable $e) {
            General::addRowLog("AI chat-image exception: " . $e->getMessage());
            return ["_(" . AiMediaController::humanizeImageError($e->getMessage()) . ")_", null, null];
        }
    }

    /**
     * v5.x — Dispatch the FLUSSU_WEB payload to AiWebController and return a tuple
     * [string $markdownReply, ?array $tokenUsage]. Symmetric to _dispatchImageGeneration().
     */
    private function _dispatchWebResearch(string $sessId, array $payload, string $userText): array {
        try {
            $webPlatform = $this->_platform->resolveWebProvider();
            if ($webPlatform !== $this->_platform)
                General::addRowLog("Web research dispatch redirected: " . $this->_platform->name . " → " . $webPlatform->name);
            $ctrl = new AiWebController($webPlatform);
            if (!$ctrl->canBrowse())
                return ["_(Web research is not supported by the current provider.)_", null];
            $res = $ctrl->dispatch($sessId, $payload, $userText);
            if (($res[0] ?? '') !== 'Ok' || empty($res[1])) {
                $rawErr = (string)($res[1] ?? 'unknown');
                General::addRowLog("AI chat-web error (raw): " . $rawErr);
                // dispatch() already returns a humanised "_(…)_" string for errors;
                // pass it through unchanged.
                return [$rawErr !== '' ? $rawErr : "_(Web research failed.)_", null];
            }
            $reply = (string)$res[1];
            $usage = $res[2] ?? null;
            General::addRowLog("AI chat-web dispatched (" . strlen($reply) . " chars) for session " . $sessId);
            return [$reply, is_array($usage) ? $usage : null];
        } catch (\Throwable $e) {
            General::addRowLog("AI chat-web exception: " . $e->getMessage());
            return ["_(" . AiWebController::humanizeWebError($e->getMessage()) . ")_", null];
        }
    }

    private function _checkLimitReached($text) {
        $limitError=false;
        // Implement your rate limit checking logic here
        if (is_array($text)) {
            $text = json_encode($text);
        }
        if ((stripos($text,"error")<3 && stripos($text,"Error")!==false) && (stripos($text,"maximum context length")!==false || stripos($text,"rate_limit_error")!==false || stripos($text,"request too large")!==false || stripos($text,"would exceed the rate limit")!==false)){
            $limitError=true;
        }
        return $limitError;
    }

    /**
     * Funzione che trova uri nel testo e lo sostituisce con l'HTML della pagina
     */
    function sostituisciURLconHTML($testo) {
        $pattern = '/\b(?:https?:\/\/)(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(?::[0-9]+)?(?:\/[^\s"<>]*)?/i';
        
        // Trova tutti gli URL nel testo
        preg_match_all($pattern, $testo, $matches);
        
        $urls = $matches[0];
        
        if (empty($urls)) {
            return $testo;
        }
        
        // Rimuovi duplicati
        $urlsUnici = array_unique($urls);
        
        $scraper = new \Flussu\Controllers\WebScraperController();
        
        // Processa ogni URL dall'più lungo al più corto
        // per evitare sostituzioni parziali
        usort($urlsUnici, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        foreach ($urlsUnici as $url) {
            try {
                $json=$scraper->getPageContentJSON($url);
                if (json_decode($json)!==false){
                    if ($json !== false && $json !== null) {
                        // Sostituisci tutte le occorrenze di questo URL
                        $testo = str_replace($url, "\n---\npage content at ".$url.":\n```html\n".$json."```\n---\n", $testo);
                    }
                }
            } catch (\Throwable $e) {
                error_log("Errore nel recupero HTML per $url: " . $e->getMessage());
            }
        }
        
        return $testo;
    }

    /**
     * Funzione helper per estrarre solo gli URL dal testo
     * 
     * @param string $testo Il testo da analizzare
     * @return array Array di URL unici trovati
     */
    function estraiURLUnici($testo) {
        $pattern = '/\b(?:https?:\/\/)(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(?::[0-9]+)?(?:\/[^\s]*)?/i';
        
        preg_match_all($pattern, $testo, $matches);
        
        return array_unique($matches[0]);
    }
}
 /*-------------
 |   ==(O)==   |
 |     | |     |
 | AL  |D|  VS |
 |  \__| |__/  |
 |     \|/     |
 |  @INXIMKR   |
 |------------*/ 

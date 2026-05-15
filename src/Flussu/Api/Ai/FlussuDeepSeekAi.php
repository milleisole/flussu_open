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
 * CLASS-NAME:       Flussu DeepSeek interface - v1.0
 * CREATED DATE:     31.05.2025 - Aldus - Flussu v4.3
 * VERSION REL.:     5.0 -def- 20260426
 * UPDATE DATE:      26.04:2026 - Aldus
 * -------------------------------------------------------*/
namespace Flussu\Api\Ai;
use Flussu\Config;
use Flussu\Contracts\IAiProvider;
use Flussu\General;
use DeepSeek\DeepSeekClient;
use DeepSeek\Enums\Models;
use Log;
use Exception;

class FlussuDeepSeekAi implements IAiProvider
{
    private $_aiErrorState=false;
    private $_deepseek;
    private $_deepseek_key="";
    private $_deepseek_model="";
    private $_deepseek_chat_model="";
    public function canBrowseWeb(){
        return true;
    }

    public function __construct($model="",$chat_model=""){
        if (!isset($this->_deepseek)){
            $this->_deepseek_key = config("services.ai_provider.deepseek.auth_key");
            if (empty($this->_deepseek_key))
                throw new Exception("DeepSeek API key not configured. Set 'auth_key' in config services.ai_provider.deepseek");
            if ($model)
                $this->_deepseek_model = $model;
            else {
                if (!empty(config("services.ai_provider.deepseek.model")))
                    $this->_deepseek_model=config("services.ai_provider.deepseek.model");
            }
            if ($chat_model)
                $this->_deepseek_chat_model = $chat_model;
            else {
                if (!empty(config("services.ai_provider.deepseek.chat-model")))
                    $this->_deepseek_chat_model=config("services.ai_provider.deepseek.chat-model");
            }
            $this->_deepseek=DeepSeekClient::build($this->_deepseek_key, timeout:60);
        }
    }

    function chat($preChat,$sendText,$role="user"){
        foreach ($preChat as &$message) {
            if (isset($message["message"]) && !isset($message["content"])) {
                $message["content"] = $message["message"];
                unset($message["message"]);
            }
        }
        unset($message);
        $preChat[]= [
            'role' => $role,
            'content' => $sendText,
        ];
        return $this->_chatContinue($preChat);
    }

    private function _chatContinue($arrayText){
        try{
            $temperature = (float) Config::init()->aiTemperature('deepseek');
            $result = $this->_deepseek->query(json_encode(["messages"=>$arrayText]))->withModel($this->_deepseek_chat_model)->setTemperature($temperature)->run();
        } catch (\Throwable $e) {
            return [$arrayText, "Error: no response. Details: " . $e->getMessage(), null];
        }
        $res=json_decode($result, true);
        // Extract token usage if available
        $tokenUsage = null;
        if (isset($res["usage"])) {
            $tokenUsage = [
                'model' => $this->_deepseek_chat_model,
                'input' => $res["usage"]["prompt_tokens"] ?? 0,
                'output' => $res["usage"]["completion_tokens"] ?? 0
            ];
        }
        return [$arrayText, ($res["choices"][0]["message"]["content"] ?? "Error: no DeepSeek response. Details: " . $result), $tokenUsage];
    }

    // v4.5.2 - AI Media Exchange: not supported by DeepSeek
    public function canAnalyzeMedia(): bool { return false; }
    public function analyzeMedia($preChat, $mediaPath, $prompt, $role="user"): array {
        return [[], "Error: media analysis not supported by DeepSeek", null];
    }
    public function canGenerateImages(): bool { return false; }
    public function generateImage($prompt, $size="1024x1024", $quality="standard"): array {
        return ["error" => "Image generation not supported by DeepSeek"];
    }

    function chat_WebPreview($sendText,$session="123-231-321",$max_output_tokens=150,$temperature=null){
        if ($temperature === null) {
            $temperature = (float) Config::init()->aiTemperature('deepseek');
        }
        $result = $this->_deepseek->query($sendText)->setTemperature($temperature)->run();
        return $result;
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
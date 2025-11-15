<?php
/* --------------------------------------------------------------------*
 * Flussu v.5.0 - Mille Isole SRL - Released under Apache License 2.0
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

 La classe Session gestisce tutto lo stato di un processo 
 una volta eseguito.
 Contiene tutto lo stato del processo e tutte le variabili
 generate emodificate ad ogni step del processo

 * -------------------------------------------------------*
 * CLASS PATH:       App\Flussu\Flussuserver
 * CLASS NAME:       Session
 * CLASS-INTENT:     Statistics producer
 * USE ALDUS BEAN:   Session Handler
 * -------------------------------------------------------*
 * CREATED DATE:     10.01.2021 - Aldus
 * VERSION REL.:     5.0.0.20251103
 * UPDATES DATE:     11.04.2025
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/

 /*
    NOTA
    -----------
    Per migliorare la velocità di elaborazione è necessario mettere le mani in questa classe.
    Sono state fatte delle sperimentazioni e migliorie ma le modifiche non sono finite.
    Quando tutti sarà completato, le parti di accesso al database dovranno andare nella relativa
    classe handler per permettere la centralizzazione dell'accesso al database e l'implementazione 
    dialettale per il supporto di più database.
*/

/**
 * The Session class is responsible for managing user sessions within the Flussu server.
 * 
 * This class handles various aspects of session management, including creating, updating, and terminating
 * sessions. It ensures that session data is securely stored and retrieved, and it manages session-related
 * operations such as authentication and state tracking.
 * 
 * Key responsibilities of the Session class include:
 * - Creating new sessions for users and storing session data.
 * - Updating session information, such as user activity, state, and error tracking.
 * - Terminating sessions and cleaning up session data when a user logs out or a session expires.
 * - Managing session-related errors and user-specific error states.
 * - Tracking subscription information and other session-specific metadata.
 * - Ensuring the integrity and security of session data throughout its lifecycle.
 * 
 * The class interacts with the database to store and retrieve session information, ensuring that all session
 * operations are performed efficiently and securely.
 * 
 */

namespace Flussu\Flussuserver;
use Flussu\General;
use Flussu\Flussuserver\Handler;
use Flussu\Flussuserver\NC\HandlerSessNC;
use Flussu\Beans;
use Flussu\Beans\Databroker;

use Exception;
use stdClass;

class Session {
    // Core objects
    private $_WofoD;
    private $_WofoDNC;
    
    // Session state
    private $_sessId = null;
    private $_wfId = "";
    private $_wfError = false;
    private $_hduration = 2;
    
    // Flags
    private $_varRenewed = true;
    private $_is_starting = true;
    private $_is_expired = false;
    private $_doNotSaveHistory = false;
    private $_alreadyLoaded = false;

    // Collections
    private $_history = [];
    private $_arVars = [];
    public  $arVarKeys = [];
    private $_lastVarCmd = [];
    private $_deletes = [];
    private $_wrklogs = [];
    private $_stat = [];
    
    // UUID handling
    private $_thisUuidBin = 0;
    private $_thisUuidCal = "";
    
    // Memory state
    private $_MemSeStat;
    private $_execBid_id = 0;
    private $_origWid = "";
    private $_wasBid = "";
    private $_subWid = [];
    
    // Status flags
    private $_isRunning = false;
    private $_isExecuting = false;
    private $_isEnd = false;
    private $_isRender = false;
    private $_isCallExit = false;
    private $_isError = false;
    private $_errType = 0;
    private $_sstate;
    
    // Workflow vars
    private $_wofoVars = "";
    private $_actualBlock = [];
    
    // Performance tracking
    private $_timestart = 0;
    
    /* ================================================================
     * PERFORMANCE OPTIMIZATIONS - NEW PROPERTIES
     * ================================================================ */
    
    // UUID caching (Optimization #1)
    private $_uuidCache = [];
    
    private $_dirtyVars = [];        // Track modified variables
    private $_loadedVars = [];       // Track loaded variables  
    private $_availableVars = [];    // Metadata di variabili disponibili
    private $enableIncrementalSave = true;  // Feature flag

    // Lazy serialization (Optimization #2)
    private $_serializedCache = [];
    
    // Variable generation cache (Optimization #3)
    private $_genVarsCache = null;
    private $_genVarsCacheKey = '';
    
    // Batch updates (Optimization #4)
    private $_pendingUpdates = [];
    private $_pendingStateUpdates = [];
    
    // Lazy loading (Optimization #5)
    private $_varsLoaded = false;
    
    /* ================================================================
     * CONSTRUCTOR
     * ================================================================ */
    
    public function __construct($SessionId){
        $this->_timestart = microtime(true);
        
        $this->_WofoD = new Handler();
        $this->_WofoDNC = new HandlerSessNC();

        $isNew = false;
        if (!isset($SessionId)){
            $isNew = true;
            $SessionId = General::getUuidv4();
        }
        
        $this->_sessId = $SessionId;
        
        // Load history
        if (!$isNew){
            $sessExists = $this->_chkExists($SessionId);
            if (!$sessExists){
                $this->_is_expired = true;
            } else {
                $hasHistory = $this->_loadHistory();
                
                // OPTIMIZATION: Load vars from session cache first
                if (isset($_SESSION["vars0"])){
                    $this->_arVars = $_SESSION["vars0"];
                    $this->arVarKeys = array_keys($this->_arVars);
                    $this->_varsLoaded = true; // Mark as loaded
                }
                
                $this->_ensureVarsLoaded(); // Lazy load if needed
                
                $initWid = $this->getVarValueFast("$"."WID");
                
                if (!$hasHistory && (!isset($initWid) || empty($initWid)))
                    $this->_is_expired = true;
                else
                    $this->_initMemSeStat($initWid);
                    
                $this->_checkIsStarting();
            }
        }
        
        $_SESSION["FlussuSid"] = $this->_sessId;
    }

    public function __clone(){
        $this->_WofoDNC = clone $this->_WofoDNC;
    }

    /* ================================================================
     * OPTIMIZATION #1: UUID CACHING
     * ================================================================ */
    
    /**
     * Cached UUID to binary conversion
     * Avoids repeated string operations
     */
    private function _uuid2binCached($uuidValue){
        if (empty($uuidValue)) return "";
        
        if (!isset($this->_uuidCache[$uuidValue])){
            $this->_uuidCache[$uuidValue] = str_replace("-", "", $uuidValue);
        }
        
        return $this->_uuidCache[$uuidValue];
    }
    
    /**
     * Legacy method - redirects to cached version
     */
    public function _uuid2bin($uuidValue){
        return $this->_uuid2binCached($uuidValue);
    }

    /* ================================================================
     * OPTIMIZATION #5: LAZY LOADING
     * ================================================================ */
    
    /**
     * Ensure variables are loaded only when needed
     */
    private function _ensureVarsLoaded() {
        if ($this->_varsLoaded) return;
        
        $SQL = "SELECT c205_elm_val FROM t205_work_var 
                WHERE c205_sess_id=? AND c205_elm_id='allValues' LIMIT 1";
        
        $res = $this->_WofoDNC->execSql($SQL, [$this->_uuid2binCached($this->_sessId)]);
        $rows = $this->_WofoDNC->getData();
        
        if (!empty($rows[0]['c205_elm_val'])){
            $d = json_decode($rows[0]['c205_elm_val'], true);
            if ($d !== null) {
                $this->_arVars = $d;
                $this->arVarKeys = array_keys($this->_arVars);
            }
        }
        
        $this->_varsLoaded = true;
    }
    
    /**
     * Optimized loadWflowVars - uses lazy loading
     */
    public function loadWflowVars(){
        $this->_ensureVarsLoaded();
    }

    /* ================================================================
     * OPTIMIZATION #7: SESSION STATE CACHING
     * ================================================================ */
    
    /**
     * Initialize memory session state with caching
     */
    private function _initMemSeStat($initWid){
        if (!isset($this->_sessId)) return;
        
        // OPTIMIZATION: Check session cache first
        if (isset($_SESSION[$this->_sessId]) && 
            isset($_SESSION[$this->_sessId]->sessid)) {
            $this->_MemSeStat = $_SESSION[$this->_sessId];
            
            // Restore from cache variable if more recent
            $vvm = $this->getVarValueFast("$"."_MemSeStat");
            if (!is_null($vvm) && isset($vvm->sessid)) {
                $this->_MemSeStat = $vvm;
            }
            return; // Skip DB query!
        }
        
        // Load from variable first
        $vvm = $this->getVarValueFast("$"."_MemSeStat");
        if (!is_null($vvm) && isset($vvm->sessid)) {
            $this->_MemSeStat = $vvm;
            $_SESSION[$this->_sessId] = $this->_MemSeStat;
            return;
        }
        
        // Initialize new state object
        $this->_MemSeStat = new stdClass();
        
        // Check for multi-workflow data
        $multArvars = [];
        if (!empty($initWid) && strtoupper(substr($initWid, 0, 3)) == "[M."){
            $multArvars = $this->_arVars;
        }
        
        // Load from database
        $rSet = $this->_WofoDNC->getActiveSess($this->_uuid2binCached($this->_sessId));
        
        if ($rSet != null && is_array($rSet) && count($rSet) == 1){
            // OPTIMIZATION: Efficient hydration with single assignment
            $row = $rSet[0];
            
            $this->_wfId = $row["c200_wid"];
            $this->_MemSeStat->workflowId = $this->_wfId;
            
            // Get workflow metadata
            $HndNc = new \Flussu\Flussuserver\NC\HandlerNC();
            $rec = $HndNc->getFlussuNameDefLangs($this->_wfId);
            
            if (isset($rec) && is_array($rec)){
                $this->_MemSeStat->title = $rec[0]["name"];
                $this->_MemSeStat->supplangs = $rec[0]["supp_langs"];
                $this->_MemSeStat->deflang = $rec[0]["def_lang"];
            } else {
                $this->_MemSeStat->title = "unknown";
                $this->_MemSeStat->supplangs = "unknown";
                $this->_MemSeStat->deflang = "N/A";
            }
            
            // Bulk property assignment
            $this->_MemSeStat->sessid = $row["c200_sess_id"];
            $this->_MemSeStat->wid = $row["c200_wid"];
            $this->_MemSeStat->wfauid = $row["wfauid"];
            $this->_MemSeStat->lang = $row["c200_lang"];
            $this->_MemSeStat->blockid = $row["c200_thisblock"];
            $this->_MemSeStat->endblock = $row["c200_blk_end"];
            $this->_MemSeStat->enddate = $row["c200_time_end"];
            $this->_MemSeStat->userid = $row["c200_user"];
            $this->_MemSeStat->err = $row["c200_state_error"];
            $this->_MemSeStat->usrerr = $row["c200_state_usererr"];
            $this->_MemSeStat->exterr = $row["c200_state_exterr"];
            $this->_MemSeStat->workflowActive = $row["wactive"];
            $this->_MemSeStat->Wwid = $initWid;
            
            if (isset($row["c200_subs"]) && !is_null($row["c200_subs"])){
                $this->_MemSeStat->subWID = json_decode($row["c200_subs"]);
            }
        } else {
            // Init time - merge multi-workflow data if present
            if (count($multArvars) > 0){
                $elms = json_decode(
                    str_replace('\"', '"', substr($multArvars["$"."_mult_data"]->dbValue, 1, -1)),
                    true
                );
                
                foreach ($elms as $key => $data){
                    $key = "$"."_mult_".$key;
                    $this->assignVarsFast($key, $data);
                }
                
                unset($multArvars["$"."_mult_data"]);
                $this->_arVars = array_merge($multArvars, $this->_arVars);
            }
        }
        
        $this->_updateStatFast();
    }

    /* ================================================================
     * OPTIMIZATION #2: LAZY SERIALIZATION
     * ================================================================ */
    
    /**
     * Fast variable assignment without immediate serialization
     */
    public function assignVars($varName, $orig_varValue){
        return $this->assignVarsFast($varName, $orig_varValue);
    }
    
    /**
     * Optimized assignVars with lazy serialization
     */
    private function assignVarsFast($varName, $orig_varValue){
        // Validation
        if ($varName == "$"."wofoEnv"){
            throw new Exception("Error, you cannot assign values to the $"."wofoEnv protected variable name");
        }
        
        if (trim(strtolower($varName)) == "$"."this"){
            throw new Exception("Cannot store a var named as $"."this !!");
        }
        
        if (substr($varName, 0, 1) != "$") {
            $varName = "$" . $varName;
        }
        
        if (strlen($varName) < 2) {
            return false;
        }
        
        // Sanitize variable name
        $invalidChars = ["+","-",">","<","*","/",".","=","£","%","!","?","^","'","\"","#","@","§","°",":",",","|","\\"];
        if (str_replace($invalidChars, "", $varName) != $varName){
            $varName2 = str_replace('"', "", str_replace('"$"."', "$", $varName));
            if ($varName2 == $varName) {
                $varName2 = str_replace("'", "", str_replace("'$'.'", "$", $varName));
            }
            
            if (str_replace($invalidChars, "", $varName2) != $varName2){
                $bDesc = $this->_WofoD->getFlussuBlock(true, 0, ($this->_history[count($this->_history)-1][1]))[0]["description"];
                throw new Exception("Unacceptable var name:[$varName2] on block[".$bDesc."]");
            }
            
            $varName = $varName2;
        }
        
        // Ensure vars are loaded
        $this->_ensureVarsLoaded();
        
        // Get or create variable
        $var = $this->getVarFast($varName);
        
        if ($var === false){
            // Create new variable object
            $var = new stdClass();
            $var->title = $varName;
            $var->value = null;
            $var->jValue = "[]";
            $var->isNull = true;
        } else {
            // CRITICAL FIX: Ensure $var is always an object
            if (!is_object($var)) {
                // If it's not an object, wrap it
                $oldValue = $var;
                $var = new stdClass();
                $var->title = $varName;
                $var->value = $oldValue;
                $var->jValue = is_array($oldValue) || is_object($oldValue) 
                    ? json_encode($oldValue) 
                    : $oldValue;
                $var->isNull = is_null($oldValue);
                $var->isObject = is_object($oldValue);
            }
            
            // Quick equality check
            if (isset($var->value) && $var->value === $orig_varValue) {
                return true;
            }
        }
        
        // Detect types
        $isObject = is_object($orig_varValue);
        $var->isObject = $isObject;
        $var->value = $orig_varValue;
        $var->isNull = is_null($var->value);
        
        // OPTIMIZATION: Don't serialize immediately - just mark as dirty
        $this->_dirtyVars[$varName] = true;
        unset($this->_serializedCache[$varName]); // Invalidate cache
        
        // Handle DateTime objects
        if (is_a($var->value, "DateTime") || is_a($var->value, "DateInterval")){
            $var->dValue = '"' . $var->value->format('Y-m-d H:i:s') . '"';
        }
        
        // Store variable
        $this->_arVars[$var->title] = $var;
        
        // OPTIMIZATION: Use [] instead of array_push
        if (!in_array($var->title, $this->arVarKeys)){
            $this->arVarKeys[] = $var->title;
        }
        
        return true;
    }
    
    /**
     * Serialize variable on demand (lazy)
     */
    private function _serializeVar($varName) {
        if (isset($this->_serializedCache[$varName])) {
            return $this->_serializedCache[$varName];
        }
        
        $var = $this->_arVars[$varName];
        
        if (is_object($var->value) || is_array($var->value)) {
            $this->_serializedCache[$varName] = json_encode($var->value, JSON_UNESCAPED_UNICODE);
        } else {
            $this->_serializedCache[$varName] = $var->value;
        }
        
        return $this->_serializedCache[$varName];
    }

    /* ================================================================
     * OPTIMIZATION #3: STRINGBUILDER FOR VARIABLE GENERATION
     * ================================================================ */
    
    /**
     * Generate workflow variables with StringBuilder pattern
     */
    private function _genWflowVars($alsoSysVars, $forExecution = false){
        // OPTIMIZATION: Cache result if vars haven't changed
        $cacheKey = ($alsoSysVars ? '1' : '0') . '_' . ($forExecution ? '1' : '0');
        
        if ($this->_genVarsCacheKey === $cacheKey && empty($this->_dirtyVars)) {
            return count($this->arVarKeys);
        }
        
        $this->_ensureVarsLoaded();
        
        // OPTIMIZATION: Use array for string building (StringBuilder pattern)
        $lines = [];
        $lines[] = "\r\n";
        
        foreach ($this->arVarKeys as $vKey) {
            // Skip conditions
            if (!$alsoSysVars && $vKey == "$"."dummy") continue;
            if (empty($vKey) || substr(trim($vKey), 0, 1) != "$") continue;
            if ($forExecution && stripos($vKey, "$"."___") !== false) continue;
            
            $var = $this->_arVars[$vKey];
            $vValue = $var->value;
            
            if (is_array($vValue) || is_object($vValue)){
                // Get cached JSON
                $je = $this->_serializeVar($vKey);
                $taf = !$var->isObject ? ",true" : "";
                
                // OPTIMIZATION V5.0: Use base64 for JSON to avoid apostrophe issues
                // Apostrophes in JSON (like "dall'ultimo") break single-quoted strings
                if (strlen($je) > 200 || strpos($je, "'") !== false) {
                    // Use base64 for long JSON or JSON containing apostrophes
                    $encoded = base64_encode($je);
                    $lines[] = "$vKey=json_decode(base64_decode('{$encoded}'){$taf});";
                } else {
                    // Use direct JSON for short strings without apostrophes
                    $je = str_replace("'", "\\'", $je); // Escape single quotes
                    $lines[] = "$vKey=json_decode('$je'$taf);";
                }
            } else {
                $lines[] = $this->_formatScalarVar($vKey, $vValue);
            }
        }
        
        // OPTIMIZATION: Single join operation
        $this->_wofoVars = implode("\r\n", $lines);
        
        // Cache result
        $this->_genVarsCacheKey = $cacheKey;
        
        return count($this->arVarKeys);
    }
    
    /**
     * Format scalar variable efficiently
     */
    private function _formatScalarVar($vKey, $vValue) {
        // Boolean
        if (is_bool($vValue)) {
            return "$vKey=" . ($vValue ? "true" : "false") . ";";
        }
        
        // Empty/null
        if ((empty($vValue) || $vValue == "") && !is_numeric($vValue)) {
            return "$vKey=\"\";";
        }
        
        // Special values
        $trimmed = trim($vValue);
        if ($trimmed == "true" || $trimmed == "false" || $trimmed == "null") {
            return "$vKey=" . str_replace('"', '', $trimmed) . ";";
        }
        
        // Numeric with special handling
        if (is_numeric($vValue)) {
            $len = strlen(trim(strval($vValue)));
            $is_numero = true;
            
            // CAP, phone numbers, etc.
            if ($len > 0 && (substr(trim($vValue), 0, 1) === "0" || substr(trim($vValue), 0, 1) == "+")){
                $is_numero = ($vValue == "0" || (substr(trim($vValue), 0, 2) === "0."));
            }
            
            if ($is_numero) {
                return "$vKey=$vValue;";
            }
        }
        
        // Variable reference or expression
        if (strpos($vValue, "->setDataJson") !== false || substr(trim($vValue), 0, 1) == "$") {
            return "$vKey=$vValue;";
        }
        
        // String - check for multiline or very long
        $hasNewLine = (strpos($vValue, "\n") !== false || strpos($vValue, "\r") !== false);
        $isVeryLong = (strlen($vValue) > 300);
        
        if ($hasNewLine || $isVeryLong) {
            // OPTIMIZATION V5.0: Use base64 for long/multiline strings
            // This is 100% safe and avoids all HEREDOC parsing issues
            $encoded = base64_encode($vValue);
            return "$vKey=base64_decode('{$encoded}');";
        }
        
        // Normal string
        return "$vKey=\"" . addslashes(stripslashes($vValue)) . "\";";
    }
    
    public function getWorkflowVars($forExecution = false) { 
        $this->_genWflowVars(true, $forExecution); 
        return $this->_wofoVars; 
    }
    
    public function getLogWorkflowVars() { 
        $this->_genWflowVars(false); 
        return $this->_wofoVars;
    }

    /* ================================================================
     * OPTIMIZATION #4: BATCH DATABASE UPDATES
     * ================================================================ */
    
    /**
     * Buffer block ID update
     */
    public function setBlockId($thisBlockId){
        $this->_MemSeStat->blockid = $thisBlockId;
        
        // OPTIMIZATION: Buffer update instead of executing immediately
        $this->_pendingUpdates['thisblock'] = $thisBlockId;
        $this->_pendingUpdates['time_end'] = date('Y-m-d H:i:s');
        
        // Check if starting
        $SQL = "SELECT c20_start FROM t20_block WHERE c20_uuid=? LIMIT 1";
        $res = $this->_WofoDNC->execSql($SQL, [$thisBlockId]);
        $this->_checkIsStarting($res);
        
        $this->recLog("BID: " . $thisBlockId);
        $this->_updateStatFast();
    }
    
    /**
     * Buffer state updates
     */
    private function _setState($stateId, $stateValue = null){
        if (is_null($this->_sstate)) {
            $this->_sstate = new stdClass();
        }
        
        $this->_sstate->err = 0;
        $this->_sstate->exterr = 0;
        $this->_sstate->usrerr = 0;
        $this->_sstate->tend = date("Y/m/d H:i:s");
        
        $theBlkId = $this->_MemSeStat->blockid;
        
        if (!empty($theBlkId)){
            // OPTIMIZATION: Buffer updates
            switch ($stateId){
                case 0:
                    $this->_sstate->exterr = 1;
                    $this->_pendingStateUpdates['state_exterr'] = 1;
                    break;
                case 1:
                    $this->_sstate->err = 1;
                    $this->_pendingStateUpdates['state_error'] = 1;
                    break;
                case 2:
                    $this->_sstate->err = 1;
                    $this->_pendingStateUpdates['state_usererr'] = 1;
                    break;
                case 3:
                    $this->_pendingStateUpdates['time_end'] = date('Y-m-d H:i:s');
                    break;
            }
        }
    }
    
    /**
     * Flush all pending database updates in a single query
     */
    private function _flushPendingUpdates() {
        // Merge all pending updates
        $allUpdates = array_merge($this->_pendingUpdates, $this->_pendingStateUpdates);
        
        if (empty($allUpdates)) return;
        
        $setClauses = [];
        $params = [];
        
        foreach ($allUpdates as $col => $val) {
            $setClauses[] = "c200_$col = ?";
            $params[] = $val;
        }
        
        $params[] = $this->_uuid2binCached($this->_sessId);
        
        $SQL = "UPDATE t200_worker SET " . implode(', ', $setClauses) . " WHERE c200_sess_id = ?";
        $this->_WofoDNC->execSql($SQL, $params);
        
        // Clear buffers
        $this->_pendingUpdates = [];
        $this->_pendingStateUpdates = [];
    }
    
    /**
     * Set end block with buffered update
     */
    private function _setEndBlock($blockId){
        $endBid = 9999;
        $SQL = "SELECT c20_id as id FROM t20_block WHERE c20_uuid=? LIMIT 1";
        $res = $this->_WofoDNC->execSql($SQL, [$blockId]);
        
        if ($res) {
            $data = $this->_WofoDNC->getData();
            if (!empty($data[0]["id"])) {
                $endBid = $data[0]["id"];
            }
        }
        
        // OPTIMIZATION: Buffer update
        $this->_pendingUpdates['blk_end'] = $endBid;
        $this->_pendingUpdates['time_end'] = date('Y-m-d H:i:s');
        
        $this->_MemSeStat->endBlockId = $endBid;
        $this->_updateStatFast();
        $this->recLog("set EndBlock: " . $endBid);
    }
    
    /**
     * Fast stat update - only in memory
     */
    private function _updateStatFast(){
        $this->assignVarsFast("$"."_MemSeStat", $this->_MemSeStat);
        $_SESSION[$this->_sessId] = $this->_MemSeStat;
    }
    
    /**
     * Legacy method
     */
    private function _updateStat(){
        $this->_updateStatFast();
    }

    /* ================================================================
     * OPTIMIZATION #6: NATIVE ARRAY OPERATIONS
     * ================================================================ */
    
    /**
     * Optimized history assignment
     */
    public function assignHistory($dataIdOrBlockId, $shownData){
        if ($this->_history == null) {
            $this->_loadHistory();
        }
        
        // OPTIMIZATION: Use [] instead of array_push
        $this->_history[] = [date("Y/m/d H:i:s"), $dataIdOrBlockId, $shownData];
    }
    
    /**
     * Optimized log recording
     */
    public function recLog($logtext, string $sessId = null, int $tpInfo = 0){
        if (is_null($sessId)) {
            $sessId = $this->_sessId;
        }
        
        if (is_array($logtext)){
            $logtext = json_encode($logtext);
        }
        
        // OPTIMIZATION: Use [] instead of array_push
        $this->_wrklogs[] = [
            'sid' => $this->_uuid2binCached($sessId),
            'tpi' => $tpInfo,
            'txt' => $logtext
        ];
        
        return true;
    }
    
    /**
     * Optimized usage stats recording
     */
    public function recUseStat(int $bid, $data = null, string $sid = null, bool $isStart = false, $channel = 0){
        if (is_null($data)) {
            $data = "";
        } else if (is_array($data)) {
            $data = json_encode($data);
        }
        
        $stv = $isStart ? 1 : 0;
        
        if ($isStart || (!empty($data) && $data != "[]")) {
            // OPTIMIZATION: Use []
            $this->_stat[] = [
                'wid' => $this->_MemSeStat->workflowId,
                'sid' => $this->_uuid2binCached($this->_MemSeStat->sessid),
                'bid' => $bid,
                'stv' => $stv,
                'chn' => $channel,
                'sdt' => $data
            ];
        }
    }
    
    /**
     * Get history rows with optimized iteration
     */
    public function getHistoryRows($rowsQty, $doNotUseThisBid = "", $addDate = false){
        $res = [];
        
        if ($rowsQty == 0) {
            $rowsQty = 9999;
        }
        
        $bid = "";
        $_buildRowParts = "";
        $c = count($this->_history);
        $cr = $c;
        
        // OPTIMIZATION: Iterate backwards without array_reverse
        for ($i = $c - 1; $i >= 0; $i--) {
            $hRow = $this->_history[$i];
            
            if ($hRow[1] == "<SESS_START>" || $hRow[1] == "<START>"){
                if ($c-- < $cr) {
                    break;
                } else {
                    $hRow[1] = "<EXT_CALL>";
                    $hRow[2] = ["E>C", "[{b}Restarted OR Called from external link{/b}]"];
                }
            }
            
            if (is_array($hRow[2])){
                if ($bid != $hRow[1]){
                    $rowsQty--;
                    if ($rowsQty < 0) break;
                    $bid = $hRow[1];
                }
                
                $arr = $hRow[2];
                if ($addDate) {
                    $arr[] = $hRow[0];
                }
                
                $dontadd = (count($res) > 1 && $res[count($res)-1] == $arr) ||
                          (count($res) > 2 && $res[count($res)-2] == $arr);
                
                if (!$dontadd) {
                    $res[] = $arr;
                }
            } else {
                if ($hRow[1] == "wname"){
                    $_buildRowParts = $hRow[2];
                }
                if ($hRow[1] == "wid"){
                    $rowsQty--;
                    $res[] = ["W", $hRow[2] . " " . $_buildRowParts, $hRow[0]];
                }
            }
        }
        
        // Reverse back to chronological order
        return array_reverse($res);
    }

    /* ================================================================
     * FAST ACCESSORS
     * ================================================================ */
    
    /**
     * Fast variable getter without loading overhead
     */
    private function getVarFast($varName){
        if (!isset($this->_arVars[$varName])) {
            return false;
        }
        
        $foundVar = $this->_arVars[$varName];
        
        // IMPORTANTE: Gestione caso speciale array
        if (is_array($foundVar)){
            if (count($foundVar) > 0) {
                $foundVar = $foundVar[array_keys($foundVar)[0]];
            } else {
                return false;
            }
        }
        
        if (is_null($foundVar) || empty($foundVar)) {
            return false;
        }
        
        return $foundVar;
    }
    
    /**
     * Fast value getter
     */
    private function getVarValueFast($varName){
        $var = $this->getVarFast($varName);
        
        if ($var === false) {
            return null;
        }
        
        // Assicurati che sia un oggetto
        if (!is_object($var)) {
            return null;
        }
        
        $ret = $var->value;
        
        if (is_string($ret)) {
            try {
                $ret = htmlspecialchars_decode($var->value);
            } catch(\Throwable $e){
                // Keep original value
            }
        }
        
        return $ret;
    }

    
    /**
     * Public variable getter
     */
    public function getVar($varName){
        $this->_ensureVarsLoaded();
        return $this->getVarFast($varName);
    }
    
    /**
     * Public value getter
     */
    public function getVarValue($varName){
        $this->_ensureVarsLoaded();
        return $this->getVarValueFast($varName);
    }

    /* ================================================================
     * PUBLIC GETTERS (unchanged)
     * ================================================================ */
    
    public function isStarting() { return $this->_is_starting; }
    public function getId() { return $this->_sessId; }
    public function getLang() { return isset($this->_MemSeStat->lang) ? strToUpper($this->_MemSeStat->lang) : "IT"; }
    public function getWid() { return isset($this->_MemSeStat->wid) ? $this->_MemSeStat->wid : ""; }
    public function getWfAuid() { return isset($this->_MemSeStat->wfauid) ? $this->_MemSeStat->wfauid : ""; }
    public function getBlockId() { return isset($this->_MemSeStat->blockid) ? $this->_MemSeStat->blockid : ""; }
    public function getWfId() { return $this->_wfId; }
    
    public function getWholeWID(){
        $thisWid = $this->getVarValueFast("$"."WID");
        return is_numeric($thisWid) ? HandlerSessNC::Wofoid2WID($thisWid) : $thisWid;
    }
    
    public function getWfTitle() { return isset($this->_MemSeStat->title) ? $this->_MemSeStat->title : ""; }
    public function getWfLangs() { return isset($this->_MemSeStat->supplangs) ? $this->_MemSeStat->supplangs : ""; }
    public function getWfDefLng() { return isset($this->_MemSeStat->deflang) ? $this->_MemSeStat->deflang : ""; }
    public function getStarterWID() { return $this->getVarValueFast("$"."_StWID"); }
    public function getStarterW_id() { return $this->getVarValueFast("$"."_St_WID"); }
    public function getEndBlockId() { return isset($this->_MemSeStat->endblock) ? $this->_MemSeStat->endblock : null; }
    public function isWorkflowActive() { return $this->_MemSeStat->workflowActive; }
    public function isWorkflowInError() { return $this->_wfError; }
    
    public function getSuppLangs(){ 
        if (!empty($this->getWid())){
            $res = $this->_WofoD->getSuppLang($this->getWid());
            return explode(",", $res[0]["supp_langs"]);
        }
        return [];
    }
    
    public function getName(){
        if (!empty($this->getWid())){
            return $this->_WofoD->getFlussuName($this->getWid());
        }
        return "";
    }

    /* ================================================================
     * SESSION STATE MANAGEMENT
     * ================================================================ */
    
    public function isExpired(){
        if ($this->_is_expired) return true;
        
        if (!isset($this->_MemSeStat->workflowId)){
            unset($_SESSION[$this->_sessId]);
            return true;
        }
        
        $ebid=$this->getEndBlockId();
        if (is_null($ebid) || $ebid == "" || $ebid == 0){
            return false;
        } else {
            return true;
        }
    }
    
    public function setSessionEnd($EndBlock = null){
        if (is_null($EndBlock)) {
            $EndBlock = $this->_MemSeStat->blockid;
        }
        if (empty($EndBlock)) {
            $EndBlock = "N/A";
        }
        $this->_setEndBlock($EndBlock);
    }
    
    private function _checkIsStarting($res = null){
        $this->_is_starting = false;
        if ($this->_is_expired) return false;
        
        if (!isset($res)){
            if (isset($this->_MemSeStat->blockid) && !empty($this->_MemSeStat->blockid)){
                $SQL = "SELECT c20_start FROM t20_block WHERE c20_uuid=? LIMIT 1";
                $res = $this->_WofoDNC->execSql($SQL, [$this->_MemSeStat->blockid]);
            } else {
                $this->_is_starting = true;
                return;
            }
        }
        
        if ($res){
            $res = $this->_WofoDNC->getData();
            if (isset($res) && is_array($res) && count($res) > 0 && $res[0]["c20_start"] == "1"){
                $this->_is_starting = true;
            }
        }
    }

    /* ================================================================
     * WORKFLOW OPERATIONS
     * ================================================================ */
    
    public function setExecBid($bid){
        if (is_null($bid) || empty($bid) || $this->_wasBid === $bid) return;
        
        if (!is_numeric($bid) && !empty($bid)){
            $this->_wasBid = $bid;
            $bid = $this->_WofoD->getBlockIdFromUUID($bid);
        }
        
        $this->_execBid_id = $bid;
    }
    
    public function setFunctions($functions){
        return $this->assignVarsFast("$"."___PRV_wf_functions", $functions);
    }
    
    public function getFunctions(){
        return $this->getVarValueFast("$"."___PRV_wf_functions");
    }
    
    public function setLang($newLangId){
        $this->assignVarsFast("$"."lang", strtoupper($newLangId));
        $this->_MemSeStat->lang = $newLangId;
        $this->recLog("set lang: " . $this->_MemSeStat->lang);
    }
    
    public function setDurationHours($hours = 1){
        if (!empty($hours) && intval($hours) > 0){
            if ($this->_WofoDNC->setDurationHours($hours, $this->_uuid2binCached($this->_sessId))){
                $this->_hduration = intval($hours);
                return true;
            }
        }
        return false;
    }
    
    public function setDurationZero(){
        return $this->_WofoDNC->setDurationHours(0, $this->_uuid2binCached($this->_sessId));
    }
    
    public function removeVars($varName){
        if (is_null($varName) || empty($varName)) return false;
        
        $this->_ensureVarsLoaded();
        
        unset($this->arVarKeys[$varName]);
        unset($this->_arVars[$varName]);
        
        $this->_deletes[] = [
            'sid' => $this->_uuid2binCached($this->_sessId),
            'eid' => $varName
        ];
        
        $this->recLog("var $varName removed");
        $this->_varRenewed = true;
        $this->_dirtyVars[$varName] = true;
        
        return true;
    }

    /* ================================================================
     * SUBROUTINE ENGINE
     * ================================================================ */
    
    public function moveTo(string $WID, string $backToBlockId, string $gotoBlockId = null){
        $this->_MemSeStat->subWID[] = (object)[
            "wid" => $this->_MemSeStat->wid,
            "wwid" => $this->_MemSeStat->Wwid,
            "bid" => $backToBlockId,
            "title" => $this->_MemSeStat->title
        ];
        
        $this->_MemSeStat->returnToWid = $this->_MemSeStat->wid;
        $this->_MemSeStat->returnToWwid = $this->_MemSeStat->Wwid;
        $this->_MemSeStat->returnToBid = $backToBlockId;
        
        $stblk = 0;
        $w_id = HandlerSessNC::WID2Wofoid($WID, $this);
        $res = $this->_WofoD->getFlussuNameFirstBlock($w_id);
        
        if ($res != null && is_array($res) && count($res) == 1 && $res[0]["active"] == true){
            $this->_MemSeStat->wid = $w_id;
            $this->_MemSeStat->Wwid = $WID;
            $this->_MemSeStat->bid = $res[0]["start_blk"];
            $this->_MemSeStat->title = $res[0]["name"];
            
            $swh = count($this->_MemSeStat->subWID) - 1;
            $this->_MemSeStat->subWID[$swh]->title = $res[0]["name"];
            $this->_MemSeStat->subWID[$swh]->st_bid = $res[0]["start_blk"];
            
            $stblk = $this->_MemSeStat->bid;
            $this->assignVarsFast("$"."WID", $this->_MemSeStat->wid);
            
            $this->recLog("Moving to workflow [$WID/".$this->_MemSeStat->title."]", $this->_sessId);
            $this->assignHistory("wid", $this->_MemSeStat->Wwid);
            $this->assignHistory("wname", $this->_MemSeStat->title);
        } else {
            $this->_wfError = true;
            $stblk = "0000-0000-0000-0000-0000";
            
            $mean = "E00-unspecified error";
            if (is_null($res)) {
                $mean = "E001-cannot get workflow or first block (res is null)";
            }
            if (!is_null($res) && count($res) != 1) {
                $mean = "E002-count of res=".count($res)." getting workflow / first block";
            }
            
            $data = "Cannot start workflow [$WID]:".$mean;
            $this->recLog($data, null, 4);
        }
        
        $this->_MemSeStat->movedToBid = $stblk;
        $this->_updateStatFast();
        
        return $stblk;
    }
    
    public function moveBack($vars = null){
        $theblk = "";
        $lastSW = array_pop($this->_MemSeStat->subWID);
        
        $this->_MemSeStat->title = $lastSW->title;
        $theblk = $lastSW->bid;
        
        $this->assignVarsFast("$"."WID", $lastSW->wid);
        $this->recLog("Return back to workflow [".$lastSW->wid."/".$lastSW->title."]", $this->_uuid2binCached($this->_sessId));
        
        $this->assignHistory("wid", $lastSW->wwid);
        $this->_MemSeStat->wid = $lastSW->wid;
        $this->assignHistory("wname", $lastSW->title);
        
        $this->_MemSeStat->returnToWid = $lastSW->wid;
        $this->_MemSeStat->returnToWwid = $lastSW->wwid;
        $this->_MemSeStat->returnToBid = $lastSW->bid;
        
        $this->_updateStatFast();
        
        return $theblk;
    }

    /* ================================================================
     * NOTIFICATIONS ENGINE
     * ================================================================ */
    
    public function setNotify($notifType, $dataName, $dataValue){
        $dataType = "N";
        $channel = 20;
        
        switch($notifType){
            case 1: $dataType = "A"; $channel = 21; break;
            case 2: $dataType = "CI"; $channel = 22; break;
            case 3: $dataType = "CV"; $channel = 23; break;
            case 4: $dataType = "AR"; $channel = 24; break;
            case 5: $dataType = "CB"; $channel = 25; break;
        }
        
        $this->_addNotify($dataType, $dataName, $dataValue, $channel);
        $this->_ensureVarsLoaded();
    }
    
    private function _addNotify($dataType, $dataName, $dataValue, $channel){
        return $this->_WofoDNC->addNotify(
            $dataType, 
            $dataName, 
            $dataValue, 
            $channel, 
            $this->_MemSeStat->wid, 
            $this->_MemSeStat->sessid, 
            $this->_execBid_id
        );
    }
    
    public function getNotify($sessId = ""){
        if (empty($sessId) && !is_null($this->_MemSeStat)) {
            $sessId = $this->_MemSeStat->sessid;
        }
        
        $notyf = [];
        if (!empty($sessId)) {
            $notyf = $this->_WofoDNC->getNotify($sessId);
        }
        
        return $notyf;
    }

    /* ================================================================
     * TIMED CALLS
     * ================================================================ */
    
    public function setTimedCalled($value = true){
        $_SESSION["isTimedCalled"] = $value;
        $dt = new \DateTime();
        $this->assignVarsFast("$"."_dtc_lastCall", $dt);
        
        $qty = $this->getVarValueFast("$"."_dtc_callsCount");
        $qty = empty($qty) ? 0 : $qty + 1;
        
        $this->assignVarsFast("$"."_dtc_callsCount", $qty);
    }
    
    public function isTimedCalled(){
        return isset($_SESSION["isTimedCalled"]) ? $_SESSION["isTimedCalled"] : false;
    }
    
    public function timedCalledCount(){
        return $this->getVarValueFast("$"."_dtc_callsCount");
    }
    
    public function timedCalledLast(){
        return $this->getVarValueFast("$"."_dtc_lastCall");
    }

    /* ================================================================
     * STATUS MANAGEMENT
     * ================================================================ */
    
    public function statusStart(){}
    
    public function statusRunning($booVal){
        $this->_isRunning = $booVal;
    }
    
    public function statusExec($booVal){
        $this->_isExecuting = $booVal;
    }
    
    public function statusEnd($booVal){
        $this->_isEnd = $booVal;
        $this->_setState(3);
    }
    
    public function statusRender($booVal){
        $this->_isRender = $booVal;
    }
    
    public function statusCallExt($booVal){
        $this->_isCallExit = $booVal;
    }
    
    public function statusError($booVal){
        $this->_isError = $booVal;
        
        if ($this->_isCallExit){
            $this->_errType = 1;
            $this->_setState(0);
            $this->recLog("EXTERNAL ERROR STATE");
        } elseif ($this->_isExecuting || $this->_isRender){
            $this->_setState(1);
            $this->_errType = 2;
            $this->recLog("INTERNAL ERROR STATE");
        } else {
            $this->_setState(2);
            $this->_errType = 3;
            $this->recLog("USER ERROR STATE");
        }
    }
    
    public function getStateIntError() { return ($this->_isError && $this->_errType == 2); }
    public function getStateUsrError() { return ($this->_isError && $this->_errType == 3); }
    public function getStateExtError() { return ($this->_isError && $this->_errType == 1); }

    /* ================================================================
     * HISTORY MANAGEMENT
     * ================================================================ */
    
    public function cleanLastHistoryBid($bid){
        $cnt = count($this->_history) - 1;
        
        for ($i = $cnt; $i >= 0; $i--){
            if (isset($this->_history[$i])){
                $hRow = $this->_history[$i];
                if ($hRow[1] == $bid) {
                    unset($this->_history[$i]);
                } else {
                    break;
                }
            }
        }
    }
    
    private function _chkExists($sessId){
        $SQL = "SELECT c200_sess_id as sid FROM t200_worker WHERE c200_sess_id=? LIMIT 1";
        $this->_WofoDNC->execSql($SQL, [$this->_uuid2binCached($sessId)]);
        
        $data = $this->_WofoDNC->getData();
        return isset($data[0]["sid"]);
    }
    
    private function _loadHistory(){
        $SQL = "SELECT c207_history FROM t207_history WHERE c207_sess_id=? LIMIT 1";
        $this->_WofoDNC->execSql($SQL, [$this->_uuid2binCached($this->_sessId)]);
        
        $data = $this->_WofoDNC->getData();
        
        if (isset($data[0]["c207_history"])){
            $this->_history = json_decode($data[0]["c207_history"], true);
            return true;
        }
        
        return false;
    }
    
    private function _initHistory($sid){
        $SQL = "INSERT INTO t207_history (c207_sess_id) VALUES (?)";
        return $this->_WofoDNC->execSql($SQL, [$this->_uuid2binCached($sid)]);
    }
    
    public function getLog(string $sessId = null){
        if (is_null($sessId)) {
            $sessId = $this->_sessId;
        }
        
        $this->_updateWorklog(null);
        
        $SQL = "SELECT c209_timestamp as e_date, c209_tpinfo as e_type, c209_row as e_desc 
                FROM t209_work_log 
                WHERE c209_sess_id=?";
        
        $this->_WofoDNC->execSql($SQL, [$this->_uuid2binCached($sessId)]);
        return $this->_WofoDNC->getData();
    }
    
    private function _updateWorklog($transExec){
        if (count($this->_wrklogs) > 0){
            $TX = $this->_WofoDNC->updateWorklog($this->_wrklogs, $transExec);
            $this->_wrklogs = $TX[0];
            $transExec = $TX[1];
        }
        return $transExec;
    }

    /* ================================================================
     * SESSION MANAGEMENT
     * ================================================================ */
    
    public function getSessionsList($whereClause){
        return $this->_WofoDNC->getSessionsList($whereClause);
    }

    /* ================================================================
     * CREATE NEW SESSION
     * ================================================================ */
    
    public function createNew(string $WID, string $IP, string $Lang, $userAgentSign, int $userId, string $app = "", string $origWid = "", $newSessId = null){
        $newSessId = null;
        $bid = 0;
        $data = "unknown";
        $isWeb = false;
        $isZap = false;
        $isForm = false;
        $isMobile = false;
        $isTelegram = false;
        $isMessenger = false;
        $isWhatsapp = false;
        $isAndroidApp = false;
        $isIosApp = false;
        $appVersion = "";
        $appDeviceId = "";
        $isStarting = false;
        $this->_wfError = false;
        
        $app = trim($app);
        $channel = 0;
        
        if (is_null($userAgentSign)) {
            $userAgentSign = "(no useragent data!)";
        }
        
        $res = $this->_WofoD->getFlussuNameFirstBlock($WID);
        
        if (empty($this->_origWid)) {
            $this->_origWid = $WID;
        }
        
        if ($res != null && is_array($res) && count($res) == 1 && $res[0]["active"] == true){
            if (!isset($newSessId)) {
                $newSessId = General::getUuidv4();
            }
            
            $this->_wfError = false;
            $this->_sessId = $newSessId;
            $this->_initMemSeStat($origWid);
            $this->_MemSeStat->workflowActive = true;
            
            $wname = $res[0]["name"];
            $stblk = $res[0]["start_blk"];
            $bid = intval($res[0]["bid"]);
            
            $this->recLog("Starting workflow [$WID/$wname] for user [$userId] from [$IP] using $userAgentSign", $newSessId);
            $this->recLog($userId, $newSessId, 1);
            $this->recLog($IP, $newSessId, 2);
            
            $this->assignHistory("WID", $WID);
            $this->assignHistory("wname", $wname);
            $this->assignHistory("IP", $IP);
            $this->assignHistory("userAgentSign", $userAgentSign);
            $this->assignHistory("userId", $userId);
            
            // Detect device type
            $channel = 0;
            
            // Mobile detection regex
            $mobileRegex1 = '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i';
            $mobileRegex2 = '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i';
            
            if (preg_match($mobileRegex1, $userAgentSign) || 
                preg_match($mobileRegex2, substr($userAgentSign, 0, 4))) {
                $isMobile = true;
            }
            
            // Parse app type
            if (!empty($app)){
                $this->recLog(strtoupper($app) . " " . $userAgentSign, $newSessId, 3);
                
                if (substr(strtolower($app), 0, 7) == "appand!" || substr(strtolower($app), 0, 7) == "appios!"){
                    if (substr(strtolower($app), 0, 7) == "appand!"){
                        $this->recLog("aan", $newSessId, 104);
                        $isAndroidApp = true;
                        $channel = 4;
                    } else {
                        $this->recLog("aio", $newSessId, 105);
                        $isIosApp = true;
                        $channel = 5;
                    }
                    
                    $apppart = explode("!", $app);
                    $appVersion = $apppart[1];
                    $appDeviceId = $apppart[2];
                    
                    $this->recLog("AppVersion", $appVersion);
                    $this->recLog("AppDeviceId", $appDeviceId);
                } else {
                    switch(strtolower($app)){
                        case "tg":
                        case "tgm":
                        case "telegram":
                            $this->recLog("tgm", $newSessId, 101);
                            $isTelegram = true;
                            $channel = 1;
                            break;
                        case "wa":
                        case "wzp":
                        case "whatsapp":
                            $isWhatsapp = true;
                            $this->recLog("wzp", $newSessId, 102);
                            $channel = 2;
                            break;
                        case "fb":
                        case "msg":
                        case "messenger":
                            $isMessenger = true;
                            $this->recLog("msg", $newSessId, 103);
                            $channel = 3;
                            break;
                        case "zap":
                        case "ZAP":
                        case "zapier":
                        case "ZAPIER":
                            $isZap = true;
                            $this->recLog("zap", $newSessId, 110);
                            $channel = 10;
                            break;
                        default:
                            $this->recLog("UNK:" . $app, $newSessId, 100);
                    }
                }
            } else {
                $this->recLog($userAgentSign, $newSessId, 3);
            }
            
            if ($channel > 0) $isMobile = true;
            
            if (stripos($userAgentSign, "mozilla") !== false || stripos($userAgentSign, "gecko") !== false) {
                $isWeb = true;
            }
            
            $this->recLog("Type - isWeb?:$isWeb, isForm?:$isForm, isMobile?:$isMobile, isTelegramChat?:$isTelegram, isMessengerChat?:$isMessenger, isWhatsapp?:$isWhatsapp, isZapier?:$isZap", $newSessId, 4);
            
            $log = "Starting " . $newSessId . " workflow [$WID/$wname]\n";
            $log .= " - -  Agent :" . $userAgentSign . "\n";
            $log .= " - -  Client:" . ($isWeb ? "web" : "mobile") . "\n";
            $log .= " - -  Type  :isWeb?:$isWeb, isForm?:$isForm, isMobile?:$isMobile, isTgm?:$isTelegram, isMessenger?:$isMessenger, isWtsapp?:$isWhatsapp, isZapier?:$isZap";
            
            General::Log($log);
            
            $this->assignHistory("client", $isWeb ? "web" : "mobile");
            $this->assignHistory("<START>", $newSessId);
            $this->_initHistory($newSessId);
            
            $p_res = $this->_WofoDNC->startSession($WID, $Lang, $stblk, $userId, $this->_hduration, $this->_uuid2binCached($newSessId));
            
            $isStarting = true;
            $data = "{'uid':'$userId','CIP':'$IP','UA':'$userAgentSign'}";
            
        } else if ($res != null && is_array($res) && count($res) > 0 && $res[0]["active"] == false){
            $this->_MemSeStat->workflowActive = false;
            $bid = -1;
            $newSessId = "0000-0000-0000-0000-0000";
            $data = "Cannot start workflow [$WID] because it is inactive";
            $this->recLog($data, $newSessId, 4);
        } else {
            $this->_wfError = true;
            $bid = -1;
            $newSessId = "0000-0000-0000-0000-0000";
            
            $mean = "E00-unspecified error";
            if (is_null($res)) {
                $mean = "E01-cannot get workflow or first block (res is null)";
            }
            if (!is_null($res) && count($res) != 1) {
                $mean = "E02-count of res=" . count($res) . " getting workflow / first block";
            }
            
            $data = "Cannot start workflow [$WID]:" . $mean;
            $this->recLog($data, $newSessId, 4);
        }
        
        $this->_sessId = $newSessId;
        $this->_initMemSeStat($origWid);
        $this->recUseStat($bid, $data, $newSessId, true, $channel);
        
        // Set platform flags
        if ($isStarting){
            if ($isAndroidApp || $isIosApp) {
                $this->assignVarsFast("$"."isApp", true);
            } else {
                $this->assignVarsFast("$"."isApp", false);
            }
            
            $this->assignVarsFast("$"."isAndroidApp", $isAndroidApp);
            $this->assignVarsFast("$"."isIosApp", $isIosApp);
            $this->assignVarsFast("$"."appVersion", $appVersion);
            $this->assignVarsFast("$"."appDeviceId", $appDeviceId);
            
            if ($isZap){
                $this->assignVarsFast("$"."isZapier", true);
                $isWeb = false;
            } else {
                $this->assignVarsFast("$"."isZapier", false);
            }
            
            $this->assignVarsFast("$"."isForm", $isForm);
            $this->assignVarsFast("$"."isMobile", $isMobile);
            
            if ($isTelegram){
                $this->assignVarsFast("$"."isTelegram", true);
                $isWeb = false;
            } else {
                $this->assignVarsFast("$"."isTelegram", false);
            }
            
            if ($isWhatsapp){
                $this->assignVarsFast("$"."isWhatsapp", true);
                $isWeb = false;
            } else {
                $this->assignVarsFast("$"."isWhatsapp", false);
            }
            
            if ($isMessenger){
                $this->assignVarsFast("$"."isMessenger", true);
                $isWeb = false;
            } else {
                $this->assignVarsFast("$"."isMessenger", false);
            }
            
            $this->assignVarsFast("$"."isWeb", $isWeb);
            $this->assignVarsFast("$"."WID", $origWid);
            $this->assignVarsFast("$"."_StWID", $origWid);
            $this->assignVarsFast("$"."_StW_ID", $WID);
            
            if (!isset($this->_MemSeStat->StarterWid)){
                $this->_MemSeStat->StarterWid = $origWid;
                $this->_MemSeStat->Wwid = $origWid;
            }
        }
        
        $this->_updateStatFast();
        
        return $this->_sessId;
    }

    /* ================================================================
     * DESTRUCTOR - OPTIMIZATION #4: FLUSH ALL PENDING UPDATES
     * ================================================================ */
    
    function __destruct(){
        $durmsec = intval((microtime(true) - $this->_timestart) * 1000);
        $start_time = microtime(true);
        
        if (!$this->_doNotSaveHistory){
            // Return values
            if (isset($this->_MemSeStat->returnToWwid) && !empty($this->_MemSeStat->returnToWwid)){
                $this->assignVarsFast("$"."_callerWID", $this->_MemSeStat->returnToWwid);
                $this->assignVarsFast("$"."_callerBLOCK", $this->_MemSeStat->returnToBid);
            }
            
            // OPTIMIZATION: Flush all pending database updates
            $this->_flushPendingUpdates();
            
            // Close session
            $usessid = $this->_uuid2binCached($this->_sessId);
            $dirtyVarsData = $this->getDirtyVars();  // ✅ NUOVO
            $this->_WofoDNC->closeSession(
                $this->_MemSeStat, 
                $this->_arVars,
                $dirtyVarsData,      // ✅ NUOVO parametro
                $this->_stat, 
                $this->_history, 
                $this->_wrklogs, 
                $this->_subWid, 
                $usessid
            );
            $this->clearDirtyVars();  // ✅ NUOVO
        }
        
        $sessClose = intval((microtime(true) - $start_time) * 1000);
        
        General::log("SID:" . $this->_sessId . ":" . ($durmsec + $sessClose) . "ms (Calc:" . $durmsec . "ms + Close:" . $sessClose . "ms)");
        
        // OPTIMIZATION: Save to session only if dirty
        //if ($this->hasDirtyVars()) {
            $_SESSION["vars0"] = $this->_arVars;
        //}
    }
    public function getDirtyVars() {
        if (!$this->enableIncrementalSave) {
            return $this->_arVars;
        }
        
        if (empty($this->_dirtyVars)) {
            return [];
        }
        
        $dirtyData = [];
        foreach ($this->_dirtyVars as $varName => $_) {
            if (isset($this->_arVars[$varName])) {
                $dirtyData[$varName] = $this->_arVars[$varName];
            }
        }
        
        return $dirtyData;
    }
    
    public function getDirtyVarsCount() {
        return count($this->_dirtyVars);
    }
    
    public function clearDirtyVars() {
        $this->_dirtyVars = [];
    }
    
    public function hasDirtyVars() {
        return !empty($this->_dirtyVars);
    }
    
    public function getOptimizationStats() {
        return [
            'total_vars' => count($this->_arVars),
            'dirty_vars' => count($this->_dirtyVars),
            'dirty_percentage' => count($this->_arVars) > 0 
                ? round(count($this->_dirtyVars) / count($this->_arVars) * 100, 2) 
                : 0,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'incremental_save_enabled' => $this->enableIncrementalSave
        ];
    }
}
 //---------------
 //    _{()}_    |
 //    --[]--    |
 //      ||      |
 //  AL  ||  DVS |
 //  \\__||__//  |
 //   \__||__/   |
 //      \/      |
 //   @INXIMKR   |
 //---------------
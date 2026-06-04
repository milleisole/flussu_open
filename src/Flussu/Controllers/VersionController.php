<?php
/* --------------------------------------------------------------------*
 * Flussu v4.5 - Mille Isole SRL - Released under Apache License 2.0
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
 
 La classe VersionController serve a verificare e fare upgrade
 sul database delle versioni precedenti.

 Quando su un server si installa FlussuServer nuova versione,
 fare /update per eseguire questa classe, che verifica il 
 database e fa gli upgrade necessari.

 * -------------------------------------------------------*
 * CLASS PATH:       \Flussu\Api
 * CLASS-NAME:       VersionController.class
 * CLASS-INTENT:     Database version/upgdrade utility
 * -------------------------------------------------------*
 * CREATED DATE:     (09.03.2023) - Aldus
 * FOR ALDUS BEAN:   Databroker.bean
 * VERSION REL.:     5.0.
 * UPDATES DATE:     04.06:2026 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * Added version 11 - Error field in t20_block
 * Added version 12 - allineamento "v11 con c20_error" (DB v11 drifted senza la colonna)
 * (13,14 NON usate - si salta da 12 a 15)
 * Added version 15 - schema corrente di sviluppo: t82_api_key, user-mgmt/auth
 *                    (t81,t88,t92,t94,t96), pass indici performance,
 *                    t205_work_var tracking (cols+idx)
 * Added version 16 - normalizzazione drift: c01_email->varchar(65),
 *                    pulizia default t05_app_lang (c05_langstart default 'it')
 * -------------------------------------------------------*/

 /*
 * Note: This class is responsible for managing the version control of the FlussuServer database.
 * It provides methods to check the current database version and perform necessary updates.
 */

namespace Flussu\Controllers;
use Flussu\General;
use Flussu\Beans\Databroker;
use Flussu\Flussuserver\Request;

 class VersionController {
    private $_UBean;
    private $_thisVers=0;
    public function getDbVersion(){
        $this->_UBean = new Databroker(General::$DEBUG);
        $this->execSql("select * from t00_version");
        try{
            $this->_thisVers=$this->getData()[0]["c00_version"];
            if (is_null($this->_thisVers))
                $this->_thisVers=0;
        } catch (\Throwable $e){
            // non versioned database.
            $this->_thisVers=0;
        }
        return $this->_thisVers;
    }

    public function execCheck(){
        $retTxt="<html><head><title='flussuserver database check/update'><link rel='shortcut icon' href='/favicon.png' type='image/x-icon'> </head><body><h3>FlussuServer database version updater</h3><h4>Start</h4>";
        $this->_UBean = new Databroker(General::$DEBUG);
        $this->execSql("select * from t00_version");
        $createVTable=false;
        $res=true;
        try{
            $this->_thisVers=$this->getData()[0]["c00_version"];
            $createVTable=is_null($this->_thisVers);
        } catch (\Throwable $e){
            // non versioned database.
            // put first script
            $createVTable=true;
        }
        if ($createVTable){
            // V0 - creazione tabella delle versioni
            $res=$this->_execVersion(0,null,[["
            CREATE TABLE t00_version (
                c00_version VARCHAR(5) NOT NULL,
                c00_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (c00_version)
            );",null]]);
            $retTxt.="create version table:".($res?"OK":"Error")."<br>";
            $res=$this->execSql("insert into t00_version (c00_version,c00_date) values (?,?)",[0,date('Y/m/d h:i:s', time())]);
            $this->_thisVers=0;
        }
        if ($res)
            $retTxt.=$this->_checkVersion1();
        return $retTxt."<h4>End</h4></body></html>";
    }

    private function _checkVersion1(){
        // V1 - v2.8 - creazione tabella per i multi-workflow
        $res="Update V1:";
        $ret=true;
        if ($this->_thisVers<1){
            // Versione DB=0. Passo a versione 1
            $SQL="CREATE TABLE t60_multi_flow (
                c60_id varchar(15) NOT NULL,
                c60_workflow_id int(10) unsigned DEFAULT NULL,
                c60_user_id int(10) unsigned DEFAULT 0,
                c60_email varchar(45) NOT NULL,
                c60_json_data text NOT NULL,
                c60_assigned_server varchar(25) DEFAULT 'srv02.flu.lu',
                c60_date_from datetime NOT NULL DEFAULT current_timestamp(),
                c60_date_to datetime NOT NULL DEFAULT '2099-12-31 23:59:59',
                c60_deleted int(1) unsigned DEFAULT 0,
                c60_open_count int(10) unsigned DEFAULT 0,
                c60_used_count int(10) unsigned DEFAULT 0,
                c60_mail_count int(10) unsigned DEFAULT 0,
                c60_count_summary text DEFAULT NULL,
                PRIMARY KEY (c60_id),
                KEY ix_wfid (c60_workflow_id),
                KEY ix_cusid (c60_user_id)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            $ret=$this->_execVersion(1,null,[["drop table t60_multi_flow",null],[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
            $res.="<hr>".$this->_checkVersion2();
        return $res;
    }
    private function _checkVersion2(){
        // V2 - v2.8.1 - modifiche per la nuova gestione dei campi: modifica tipo blocco (sub, return, note, ecc.)
        $res="Update V2:";
        $ret=true;
        if ($this->_thisVers<2){
            // Versione DB=1. Passo a versione 2
            $SQL1="UPDATE t20_block SET c20_type=SUBSTRING(c20_type, 1, 3)";
            $SQL2="ALTER TABLE t20_block CHANGE COLUMN c20_type c20_type VARCHAR(3) NULL DEFAULT NULL";
            $ret=$this->_execVersion(2,null,[[$SQL1,null],[$SQL2,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
            $res.="<hr>".$this->_checkVersion3();
        return $res;
    }
    private function _checkVersion3(){
        // V3 - v2.8.1 - modifiche per la nuova gestione dei campi: modifica caratteristiche blocco (valore json)
        $res="Update V3:";
        $ret=true;
        if ($this->_thisVers<3){
            // Versione DB=2. Passo a versione 3
            $SQL="ALTER TABLE t30_blk_elm CHANGE COLUMN c30_css c30_css TEXT NULL DEFAULT NULL";
            $ret.=$this->_execVersion(3,null,[[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion4();
        return $res;
    }
    private function _checkVersion4(){
        // V4 - v2.9 - modifiche per la gestione di CHAT con OpenAi. Campi: Sessione, dati (json), data update
        $res="Update V4:";
        $ret=true;
        if ($this->_thisVers<4){
            // Versione DB=3. Passo a versione 4
            $SQL="
              CREATE TABLE t01_app (
                c01_wf_id INT(10) UNSIGNED NOT NULL,
                c01_logo MEDIUMTEXT NOT NULL,
                c01_name VARCHAR(45) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c01_email VARCHAR(65) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c01_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                c01_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                c01_validfrom DATETIME NOT NULL DEFAULT '1899-12-31 23:59:59',
                c01_validuntil DATETIME NOT NULL DEFAULT '1899-12-31 23:59:59',
                PRIMARY KEY (c01_wf_id));
              
              CREATE TABLE t05_app_lang (
                c05_wf_id INT(10) UNSIGNED NOT NULL,
                c05_lang VARCHAR(5) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_title VARCHAR(64) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_website MEDIUMTEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_whoweare TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_privacy MEDIUMTEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_startprivacy MEDIUMTEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_langstart  MEDIUMTEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_menu TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_errors TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_operative TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c05_openai TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
                c05_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                c05_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (c05_wf_id, c05_lang));

              ALTER TABLE t10_workflow 
                ADD COLUMN c10_app_code VARCHAR(64) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL 
                AFTER c10_name;
              
              CREATE TABLE t210_openai_chat (
                c210_sess_id VARCHAR(36) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c210_data TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL,
                PRIMARY KEY (c210_sess_id));
            ";
            $ret=$this->_execVersion(4,null,[[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion5();
        return $res;
    }

    private function _checkVersion5(){
        // V5 - v2.9.5 - modifiche per la gestione dei sub-processi
        $res="Update V5:";
        $ret=true;
        if ($this->_thisVers<5){
            // Versione DB=4. Passo a versione 5
            $SQL="ALTER TABLE t200_worker ADD COLUMN `c200_subs` LONGTEXT NULL AFTER `c200_hduration`;";
            $ret.=$this->_execVersion(5,null,[[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion6();
        return $res;
    }

    private function _checkVersion6(){
        // V6 - v3.0.0 - modifiche per gestione sessione
        $res="Update V6:";
        $ret=true;
        if ($this->_thisVers<6){
            // Versione DB=5. Passo a versione 6
            $SQL="ALTER TABLE t205_work_var MODIFY c205_elm_val LONGTEXT;";
            $ret.=$this->_execVersion(6,null,[[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion7();
        return $res;
    }

    private function _checkVersion7(){
        // V7 - v3.0 - modifiche per la gestione di TIMED calls
        $res="Update V7:";
        $ret=true;
        if ($this->_thisVers<7){
            // Versione DB=6. Passo a versione 7
            $SQL="
            CREATE TABLE t100_timed_call (
                c100_seq BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                c100_sess_id VARCHAR(36) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c100_wid INT(10) NOT NULL,
                c100_block_id VARCHAR(36) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '',
                c100_send_data MEDIUMTEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci',
                c100_start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                c100_minutes INT(10) UNSIGNED NOT NULL DEFAULT 60,
                c100_enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
                c100_call_date DATETIME NOT NULL DEFAULT '1899-12-31 23:59:59',
                c100_call_result LONGTEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci',
                INDEX ix100_session (c100_sess_id),
                INDEX ix100_enabled (c100_enabled),
                INDEX ix100_timed (c100_start_date,c100_minutes)
            );";
            $ret=$this->_execVersion(7,null,[[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion8();
        return $res;
    }

    private function _checkVersion8(){
        // V8 - v3.0 - tabella dei dati di notifica
        $newVer=8;
        $res="Update V8:";
        $ret=true;
        if ($this->_thisVers<$newVer){
            // Versione DB=7. Passo a versione 8
            $SQL="
              CREATE TABLE t203_notifications (
                c203_notify_id bigint NOT NULL AUTO_INCREMENT,
                c203_recdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                c203_sess_id VARCHAR(36) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c203_n_type VARCHAR(3) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c203_n_name VARCHAR(45) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                c203_n_value TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
                PRIMARY KEY (c203_notify_id),
                INDEX ix203_session (c203_sess_id,c203_recdate)
                );
            ";
            $ret=$this->_execVersion($newVer,null,[[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion9();
        return $res;
    }

    private function _checkVersion9(){
        /* V9 - v3.0.5 - campo Workflow Absolute Unique ID nel workflow
        E' un itentificativo univoco che accompagna il workflow anche quando viene clonato.
        Essendo un ID univoco all'aggiornamento del DB viene assegnato un UUID di default.
        - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        Quando un WF viene clonato nel DB, si porta dietro il sui wf_AUID che non dovrebbe più cambiare.
        E' subito rappresentato come UUID() ma siccome può contenere 50chr potrebbe contenere  
            anche indo sul producer, sulla versione, ecc.
        E' un campo univoco in modo trasversale (assoluto) ma sarà possibile modificarlo per assegnare
        dati del producer, versione, release, ecc.
        Es.: 
              1. semplice UUID   -> 9e8b3b7e-4b7e-11ec-9f3b-0242ac120002
              2. Producer WFAUID -> MilleIsole_AP_WF123456789ABC_SUB01a_v120_rel_241110
        */
        $newVer=9;
        $res="Update V".$newVer.":";
        $ret=true;
        if ($this->_thisVers<$newVer){
            // Passo a nuova versione
            $SQL="
              ALTER TABLE t10_workflow 
                ADD COLUMN c10_wf_auid varchar(50) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT '' AFTER c10_id;
            ";
            $this->execSql($SQL);
            $SQL="select c10_id from t10_workflow";
            $this->execSql($SQL);
            $rows=$this->getData();
            foreach($rows as $row){
                $SQL="update t10_workflow set c10_wf_auid=? where c10_id=?";
                $this->execSql($SQL,[General::getUuidv4(),$row["c10_id"]]);
            }
            $SQL="ALTER TABLE t10_workflow ADD UNIQUE INDEX (c10_wf_auid);";
            $ret=$this->_execVersion($newVer,null,[[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion10();
        return $res;
    }

    //select * from t20_block where c20_exec like 'wofoEnv->goToFlussu("%';

    private function _checkVersion10(){
        /* V10 - v3.0.5 - 
        l'aggiunta del campo wauid è stata fatta in modo da identificare univocamente tutti 
        i sub-workflow così da non perdere i link in caso di clonazione.
        - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        la v9 ha inserito il wauid, la v10 sostuituisce l'id nel "go to flussu" con il wauid
        */
        $newVer=10;
        $res="Update V".$newVer.":";
        $ret=true;
        if ($this->_thisVers<$newVer){
            // Passo a nuova versione
            $SQL="select c20_id as id,c20_exec as exec from t20_block where c20_type='SW' or c20_exec like 'wofoEnv->goToFlussu(%'";
            $this->execSql($SQL);
            $rows=$this->getData();
            foreach($rows as $row){
                $exec=$row["exec"];
                $wid=str_ireplace(['wofoenv->gotoflussu("[',']");'],['',''],$exec);
                $sub_id=General::demouf("_".substr($wid,1)."_");
                if (is_numeric($sub_id)){
                    $SQL2="select c10_wf_auid from t10_workflow where c10_id=?";
                    $this->execSql($SQL2,[$sub_id]);
                    $wrws=$this->getData();
                    if (count($wrws)>0){
                        $exec2=str_ireplace("[".$wid."]","{".$wrws[0]["c10_wf_auid"]."}",$exec);
                        $SQL3="update t20_block set c20_exec=? where c20_id=?";
                        $this->execSql($SQL3,[$exec2,$row["id"]]);
                    }
                }
            }
            $ret=$this->_execVersion($newVer,null);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion11();
        return $res;
    }

    private function _checkVersion11(){
        /* V11 - v4.4.5 - 
        aggiunto campo errore alla tabella t30_block
        */
        $newVer=11;
        $res="Update V".$newVer.":";
        $ret=true;
        if ($this->_thisVers<$newVer){
            $SQL="
              ALTER TABLE t20_block 
                ADD COLUMN c20_error TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT '' AFTER c20_note;
            ";
            $this->execSql($SQL);
            $ret=$this->_execVersion($newVer,null);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion12();
        return $res;
    }

    private function _checkVersion12(){
        /* V12 - allineamento "v11 con colonna error".
           Alcuni DB di produzione risultano registrati v11 ma SENZA la colonna
           c20_error (la migrazione v11 era segnata ma non applicata). Questo step
           rappresenta esattamente la differenza tra "v11 senza error" e "v11 con
           error" e la applica in modo idempotente.
           NB: si salta direttamente a V15 (13 e 14 non utilizzate). */
        $newVer=12;
        $res="Update V".$newVer.":";
        $ret=true;
        if ($this->_thisVers<$newVer){
            $SQL="ALTER TABLE t20_block ADD COLUMN IF NOT EXISTS c20_error TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT '' AFTER c20_note";
            $ret=$this->_execVersion($newVer,null,[[$SQL,null]]);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion15(); // salto V13 e V14
        return $res;
    }

    private function _checkVersion15(){
        /* V15 - allineamento allo schema corrente di sviluppo (etichettato v15).
           Porta un DB v12 a contenere tutto il delta v11->v15:
             - t82_api_key (API key temporanee)
                Rif: Docs/Install/unneeded-old-bak/migration_t82_api_key.sql
             - sottosistema user-management/auth: t81_pwd_recovery, t88_wf_permissions,
                t92_user_audit, t94_user_sessions, t96_user_invitations
                Rif: Docs/Install/unneeded-old-bak/user_management_schema.sql
             - pass di indici di performance su tabelle esistenti
             - t205_work_var: colonne+indici di tracking ("hotness" variabili)
           NOTA: seeding ruoli (t90_role) e admin di default NON inclusi: dati
           ambiente-specifici, da gestire separatamente. Qui solo schema.
           NOTA: alcune tabelle (t60,t100,t200) nel DB di sviluppo hanno DUE indici
           equivalenti (residuo di due pass): replicati per fedeltà, ridondanti. */
        $newVer=15;
        $res="Update V".$newVer.":";
        $ret=true;
        if ($this->_thisVers<$newVer){
            $scripts=[];
            // --- API key ---
            $scripts[]=["CREATE TABLE IF NOT EXISTS t82_api_key (
                c82_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                c82_user_id int(10) unsigned NOT NULL,
                c82_key varchar(128) NOT NULL,
                c82_created timestamp NOT NULL DEFAULT current_timestamp(),
                c82_expires datetime NOT NULL,
                c82_used datetime DEFAULT NULL,
                PRIMARY KEY (c82_id),
                UNIQUE KEY UNQ_ApiKey (c82_key),
                KEY ix82_userid (c82_user_id),
                KEY ix82_expires (c82_expires),
                CONSTRAINT fk82_user FOREIGN KEY (c82_user_id) REFERENCES t80_user (c80_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",null];
            // --- user-management / auth ---
            $scripts[]=["CREATE TABLE IF NOT EXISTS t81_pwd_recovery (
                c81_id int(10) unsigned NOT NULL AUTO_INCREMENT,
                c81_user_id int(10) unsigned NOT NULL,
                c81_token varchar(64) NOT NULL COMMENT 'Hashed recovery token',
                c81_created timestamp NOT NULL DEFAULT current_timestamp(),
                c81_expires timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                c81_used tinyint(1) unsigned NOT NULL DEFAULT 0,
                c81_used_at timestamp NULL DEFAULT NULL,
                c81_ip_address varchar(45) DEFAULT NULL,
                c81_user_agent varchar(255) DEFAULT NULL,
                PRIMARY KEY (c81_id),
                KEY idx_user_id (c81_user_id),
                KEY idx_token (c81_token),
                KEY idx_expires (c81_expires),
                CONSTRAINT fk_pwd_recovery_user FOREIGN KEY (c81_user_id) REFERENCES t80_user (c80_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Password recovery tokens with 1-hour expiration'",null];
            $scripts[]=["CREATE TABLE IF NOT EXISTS t88_wf_permissions (
                c88_wf_id int(10) unsigned NOT NULL COMMENT 'ID workflow',
                c88_usr_id int(10) unsigned NOT NULL COMMENT 'ID utente',
                c88_permission varchar(10) NOT NULL DEFAULT 'R' COMMENT 'R=Read, W=Write, X=Execute, D=Delete, O=Owner',
                c88_granted_by int(10) unsigned NOT NULL COMMENT 'ID utente che ha concesso il permesso',
                c88_granted_at timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (c88_wf_id,c88_usr_id),
                KEY ix_usr (c88_usr_id),
                KEY ix_permission (c88_permission),
                CONSTRAINT fk_wf_perm_user FOREIGN KEY (c88_usr_id) REFERENCES t80_user (c80_id) ON DELETE CASCADE,
                CONSTRAINT fk_wf_perm_workflow FOREIGN KEY (c88_wf_id) REFERENCES t10_workflow (c10_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",null];
            $scripts[]=["CREATE TABLE IF NOT EXISTS t92_user_audit (
                c92_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                c92_usr_id int(10) unsigned NOT NULL,
                c92_action varchar(50) NOT NULL COMMENT 'login, logout, create_wf, edit_wf, delete_wf, ...',
                c92_target_type varchar(20) DEFAULT NULL COMMENT 'workflow, user, project',
                c92_target_id int(10) unsigned DEFAULT NULL COMMENT 'ID oggetto target',
                c92_ip_address varchar(45) DEFAULT NULL,
                c92_user_agent varchar(255) DEFAULT NULL,
                c92_details text DEFAULT NULL COMMENT 'Dettagli aggiuntivi in JSON',
                c92_timestamp timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (c92_id),
                KEY ix_user (c92_usr_id,c92_timestamp),
                KEY ix_action (c92_action),
                KEY ix_timestamp (c92_timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",null];
            $scripts[]=["CREATE TABLE IF NOT EXISTS t94_user_sessions (
                c94_session_id varchar(64) NOT NULL,
                c94_usr_id int(10) unsigned NOT NULL,
                c94_api_key varchar(128) DEFAULT NULL COMMENT 'API key temporaneo',
                c94_ip_address varchar(45) DEFAULT NULL,
                c94_user_agent varchar(255) DEFAULT NULL,
                c94_created_at timestamp NOT NULL DEFAULT current_timestamp(),
                c94_expires_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                c94_last_activity timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (c94_session_id),
                KEY ix_user (c94_usr_id),
                KEY ix_expires (c94_expires_at),
                KEY ix_api_key (c94_api_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",null];
            $scripts[]=["CREATE TABLE IF NOT EXISTS t96_user_invitations (
                c96_id int(10) unsigned NOT NULL AUTO_INCREMENT,
                c96_email varchar(65) NOT NULL,
                c96_role int(4) unsigned NOT NULL DEFAULT 0,
                c96_invited_by int(10) unsigned NOT NULL,
                c96_invitation_code varchar(64) NOT NULL,
                c96_status tinyint(2) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=accepted, 2=expired, 3=rejected',
                c96_created_at timestamp NOT NULL DEFAULT current_timestamp(),
                c96_expires_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                c96_accepted_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (c96_id),
                UNIQUE KEY idx_code (c96_invitation_code),
                KEY idx_email (c96_email),
                KEY idx_status (c96_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",null];
            // --- pass indici di performance ---
            $idx=[
                ["t10_workflow","idx_workflow_active_deleted","(c10_active,c10_deleted,c10_id)"],
                ["t20_block","idx_block_uuid_active","(c20_uuid,c20_flofoid,c20_deleted)"],
                ["t25_blockexit","idx_blockexit_block_number","(c25_blockid,c25_nexit,c25_direction)"],
                ["t30_blk_elm","idx_blkelm_block_order","(c30_blockid,c30_order,c30_deleted)"],
                ["t40_element","idx_element_id_lang","(c40_id,c40_lang,c40_deleted)"],
                ["t60_multi_flow","idx_multi_workflow_client","(c60_workflow_id,c60_user_id,c60_deleted)"],
                ["t60_multi_flow","idx_multiflow_wid_user","(c60_workflow_id,c60_user_id,c60_deleted)"],
                ["t100_timed_call","idx_timed_enabled_date","(c100_enabled,c100_call_date,c100_start_date)"],
                ["t100_timed_call","idx_timedcall_enabled_date","(c100_enabled,c100_call_date,c100_start_date)"],
                ["t200_worker","idx_session_uuid_active","(c200_sess_id,c200_wid,c200_start)"],
                ["t200_worker","idx_worker_sess_wid","(c200_sess_id,c200_wid,c200_start)"],
                ["t207_history","idx_history_session","(c207_sess_id)"],
            ];
            foreach($idx as $i){
                $scripts[]=["ALTER TABLE ".$i[0]." ADD INDEX IF NOT EXISTS ".$i[1]." ".$i[2],null];
            }
            // --- t205_work_var tracking ---
            $scripts[]=["ALTER TABLE t205_work_var ADD COLUMN IF NOT EXISTS c205_modified timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER c205_elm_val",null];
            $scripts[]=["ALTER TABLE t205_work_var ADD COLUMN IF NOT EXISTS c205_access_count int(10) unsigned DEFAULT 0 AFTER c205_modified",null];
            $scripts[]=["ALTER TABLE t205_work_var ADD COLUMN IF NOT EXISTS c205_last_access timestamp NULL DEFAULT NULL AFTER c205_access_count",null];
            $scripts[]=["ALTER TABLE t205_work_var ADD COLUMN IF NOT EXISTS c205_data_size int(10) unsigned DEFAULT 0 AFTER c205_last_access",null];
            $scripts[]=["ALTER TABLE t205_work_var ADD INDEX IF NOT EXISTS idx_vars_session (c205_sess_id,c205_elm_id)",null];
            $scripts[]=["ALTER TABLE t205_work_var ADD INDEX IF NOT EXISTS idx_workvar_session (c205_sess_id)",null];
            $scripts[]=["ALTER TABLE t205_work_var ADD INDEX IF NOT EXISTS idx_workvar_modified (c205_sess_id,c205_modified)",null];
            $scripts[]=["ALTER TABLE t205_work_var ADD INDEX IF NOT EXISTS idx_workvar_hotness (c205_sess_id,c205_access_count DESC)",null];
            $scripts[]=["ALTER TABLE t205_work_var ADD INDEX IF NOT EXISTS idx_workvar_lastaccess (c205_sess_id,c205_last_access)",null];

            $ret=$this->_execVersion($newVer,null,$scripts);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        if ($ret)
           $res.="<hr>".$this->_checkVersion16();
        return $res;
    }

    private function _checkVersion16(){
        /* V16 - normalizzazione coerenza schema (correzione drift), la "prossima" versione.
           - t01_app.c01_email: ripristina varchar(65) (era stato erroneamente ridotto
             a 45 in sviluppo; ampliare non tronca dati esistenti).
           - t05_app_lang: rimuove i default "spazzatura" (valori di esempio, uno non era
             nemmeno JSON valido). c05_startprivacy/c05_errors -> nullable senza default;
             c05_langstart -> default 'it'. Rilassare NOT NULL->NULL non fallisce mai. */
        $newVer=16;
        $res="Update V".$newVer.":";
        $ret=true;
        if ($this->_thisVers<$newVer){
            $scripts=[
                ["ALTER TABLE t01_app MODIFY COLUMN c01_email varchar(65) NOT NULL DEFAULT ''",null],
                ["ALTER TABLE t05_app_lang MODIFY COLUMN c05_startprivacy mediumtext DEFAULT NULL",null],
                ["ALTER TABLE t05_app_lang MODIFY COLUMN c05_errors mediumtext DEFAULT NULL",null],
                ["ALTER TABLE t05_app_lang MODIFY COLUMN c05_langstart mediumtext DEFAULT 'it'",null],
            ];
            $ret=$this->_execVersion($newVer,null,$scripts);
            $res.=($ret?"OK":"Error");
        } else {
            $res.="not needed";
        }
        return $res;
    }

    private function _checkQuery($checkQuery){
        // checkquery MUST return 1 for true or 0 for false
        // EXAMPLE:
        // select c10_id=1 from t10_workflow where c10_id=2; (always return false)
        // select c10_id=1 from t10_workflow where c10_id=1; (if c10_id=1 exist, return true. Else false)
        if (is_null($checkQuery))
            return true;
        else{
            if ($this->execSql($checkQuery)){
                $res=$this->getData();
                if (isset($res) && is_array($res)){
                    $ret=$res[0]==1;
                    return ($ret);
                }
            }
        }
    }
    private function _execVersion($versionId,$checkQuery,$scriptsArray=null){
        $res=$this->_checkQuery($checkQuery);
        if (!is_null($scriptsArray)){
            foreach($scriptsArray as $sqlscript){
                $sql=$sqlscript[0];
                $vars=$sqlscript[1];
                if (!$this->execSql($sql,$vars))
                    $res=false;
            }
        }
        if ($res){
            $this->execSql("update t00_version set c00_version=?, c00_date=? where c00_version=? ",[$versionId,date('Y/m/d h:i:s', time()),$this->_thisVers]);
            $this->_thisVers=$versionId;
        }
        return $res;
    }

    public function refreshViews(){
        $res="D10_T:".$this->execSql("DROP TABLE IF EXISTS `v10_wf_prj`;");
        $res.="<br>D15_T:".$this->execSql("DROP TABLE IF EXISTS `v15_prj_wf_usr`;");
        $res.="<br>D20_T:".$this->execSql("DROP TABLE IF EXISTS `v20_prj_wf_all`;");
        $res.="<br>D10_V:".$this->execSql("DROP VIEW IF EXISTS `v10_wf_prj`;");
        $res.="<br>D15_V:".$this->execSql("DROP VIEW IF EXISTS `v15_prj_wf_usr`;");
        $res.="<br>D20_V:".$this->execSql("DROP VIEW IF EXISTS `v20_prj_wf_all`;");
        $res.="<br>C10_V:".$this->execSql("CREATE VIEW `v10_wf_prj` AS select `wf`.`c10_id` AS `wf_id`,ifnull(`pw`.`c85_prj_id`,0) AS `prj_id` from ((`t10_workflow` `wf` left join `t85_prj_wflow` `pw` on(`pw`.`c85_flofoid` = `wf`.`c10_id`)) left join `t83_project` `pr` on(`pr`.`c83_id` = `pw`.`c85_prj_id`));");
        $res.="<br>C15_V:".$this->execSql("CREATE VIEW `v15_prj_wf_usr` AS select `v2`.`wf_id` AS `wf_id`,`v2`.`prj_id` AS `prj_id`,ifnull(`p`.`c83_desc`,'@GENERIC') AS `c83_desc`,`w2`.`c10_name` AS `c10_name`,ifnull(`u`.`c87_usr_id`,`w2`.`c10_userid`) AS `c87_usr_id` from (((`t10_workflow` `w2` left join `v10_wf_prj` `v2` on(`w2`.`c10_id` = `v2`.`wf_id`)) left join `t83_project` `p` on(`p`.`c83_id` = `v2`.`prj_id`)) left join `t87_prj_user` `u` on(`u`.`c87_prj_id` = `v2`.`prj_id`)) order by ifnull(`p`.`c83_desc`,'@GENERIC'),`w2`.`c10_name` ;");
        $res.="<br>C12_V:".$this->execSql("CREATE VIEW `v20_prj_wf_all` AS select `v15_prj_wf_usr`.`wf_id` AS `wf_id`,`v15_prj_wf_usr`.`prj_id` AS `prj_id`,`v15_prj_wf_usr`.`c83_desc` AS `prj_name`,`v15_prj_wf_usr`.`c10_name` AS `wf_name`,`v15_prj_wf_usr`.`c87_usr_id` AS `wf_user`,`t80_user`.`c80_email` AS `usr_email`,`t80_user`.`c80_name` AS `usr_name`,`t10_workflow`.`c10_active` AS `wf_active`,`t10_workflow`.`c10_deleted` AS `dt_deleted`,`t10_workflow`.`c10_validfrom` AS `dt_validfrom`,`t10_workflow`.`c10_validuntil` AS `dt_validuntil` from ((`v15_prj_wf_usr` join `t10_workflow` on(`v15_prj_wf_usr`.`wf_id` = `t10_workflow`.`c10_id`)) join `t80_user` on(`t80_user`.`c80_id` = `v15_prj_wf_usr`.`c87_usr_id`)) order by `v15_prj_wf_usr`.`c83_desc`,`v15_prj_wf_usr`.`c10_name`;");
        return $res;
    }

    function execSql($SqlCommand,$SqlARRParams=null, $Transactional=false) {
        //if (!is_null($SqlARRParams))
            $this->_UBean->setsearchData($SqlARRParams);
        return $this->_UBean->loadData($SqlCommand, $Transactional);
    }
    // Data GET
    //----------------------
    function getData()	{
        return $this->_UBean->getfoundRows();
    } 
    
    // CLASS DESTRUCTION
    //----------------------
    public function __destruct(){
        //if (General::$Debug) echo "[Distr Databroker ]<br>";
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
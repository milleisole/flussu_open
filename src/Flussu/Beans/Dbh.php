<?php
/* --------------------------------------------------------------------*
 * Flussu v4.3 - Mille Isole SRL - Released under Apache License 2.0
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
 * Flussu v2.7.0  -  03 Jan 2023
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Beans;

use PDO;
use Flussu\General;

class Dbh {
  private $_db_host="";
  private $_db_user="";
  private $_db_pwd="";
  private $_db_dbName="";

  private $_db_dbConn=null;
  private $_Tdb_dbConn=null;
  private $_isTransact=false;

  private $_inited=false;


  private function _init () {
    if (isset($_ENV["db_host"]))
        $this->_db_host=$_ENV["db_host"];
    else
        $this->_db_host=$_ENV["FLX_HOST"];
    if (isset($_ENV["db_user"]))
        $this->_db_user=$_ENV["db_user"];
    else
        $this->_db_user=$_ENV["FLX_USER"];
    if (isset($_ENV["db_pass"]))
        $this->_db_pwd=$_ENV["db_pass"];
    else
        $this->_db_pwd=$_ENV["FLX_PASSWORD"];
    if (General::isCurtatoned($this->_db_pwd))
      $this->_db_pwd=General::montanara($this->_db_pwd,999);
    if (isset($_ENV["db_name"]))
      $this->_db_dbName=$_ENV["db_name"];
    else
      $this->_db_dbName=$_ENV["FLX_DATABASE"];
  $this->_inited=true;
  }

  protected function connect($transact=false){
    if (!$this->_inited) $this->_init();
    if (is_null($this->_db_dbConn) || !isset($this->_db_dbConn)){
        $dsn='mysql:host='.$this->_db_host.';dbname='.$this->_db_dbName.';charset=utf8mb4';
        $this->_db_dbConn= new PDO($dsn,$this->_db_user,$this->_db_pwd);
        $this->_db_dbConn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        if ($transact){
          $this->_db_dbConn->beginTransaction();
          $this->_isTransact=true;
        }
      }
    return $this->_db_dbConn;
  }

  protected function beginTransaction($Db_Conn){
    try{
      $Db_Conn->beginTransaction();
      $this->_isTransact=true;
      return $Db_Conn;
    } catch (\Throwable $Exc){}
  }

/*
  protected function commit(){
    try{
      $this->_db_dbConn->commit();
      $this->_isTransact=false;
    } catch (\Throwable $Exc){}
  }
*/

  protected function commit($Tdb_dbConn=null, $closeTransact=false){
    try{
      if(is_null($Tdb_dbConn)){
        $this->_db_dbConn->commit();
      } else {
        $Tdb_dbConn->commit();
      }
      $this->_isTransact=!$closeTransact;
    } catch (\Throwable $Exc){}
  }

  protected function rollBack($Tdb_dbConn=null){
    if(is_null($Tdb_dbConn)){
      $this->_db_dbConn->rollBack();
    } else {
      $Tdb_dbConn->rollBack();
    }
    $this->_isTransact=false;
  }
  
  protected function getLastInsertId(){
    return $this->_db_dbConn->lastInsertId();
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
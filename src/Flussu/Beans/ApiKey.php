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
 *
 * BEAN-NAME:        ApiKey
 * GENERATION DATE:  16.02.2025
 * CLASS FILE:       /Beans/ApiKey.bean.php
 * FOR MYSQL TABLE:  t82_api_key
 * VERSION REL.:     4.5.20250216
 * UPDATE DATE:      16.02:2025
 * -------------------------------------------------------*/
namespace Flussu\Beans;
use PDO;

class ApiKey extends Dbh
{
    private $_opLog=""; // FOR DEBUG PURPOSES

    // ATTRIBUTES
    //----------------------
    private $c82_id;            // KEY ATTR. WITH AUTOINCREMENT
    private $c82_user_id;       // Foreign key to t80_user
    private $c82_key;           // API Key (128 chars)
    private $c82_created;       // DateTime - creation timestamp
    private $c82_expires;       // DateTime - expiration datetime
    private $c82_used;          // DateTime - used datetime (NULL if not used)

    private $isDebug = false;

    // CONSTRUCTOR
    //----------------------
    function __construct (bool $debug = false) {
        $this->_opLog = date("D M d, Y H:i:s u")." Created new ApiKey.Bean;\r\n";
        if ($debug == true) $this->isDebug = true;
        $this->clear();
    }

    // GETTERS
    //----------------------
    function getc82_id()        {if (is_null($this->c82_id)) return 0; else return $this->c82_id;}
    function getc82_user_id()   {if (is_null($this->c82_user_id)) return 0; else return $this->c82_user_id;}
    function getc82_key()       {if (is_null($this->c82_key)) return ""; else return $this->c82_key;}
    function getc82_created()   {if (is_null($this->c82_created)) return date('1899/12/31'); else return $this->c82_created;}
    function getc82_expires()   {if (is_null($this->c82_expires)) return date('1899/12/31'); else return $this->c82_expires;}
    function getc82_used()      {return $this->c82_used;} // Can be NULL

    // SETTERS
    //----------------------
    function setc82_id($val)        {$this->c82_id = $val;}
    function setc82_user_id($val)   {$this->c82_user_id = $val;}
    function setc82_key($val)       {$this->c82_key = $val;}
    function setc82_created($val)   {$this->c82_created = $val;}
    function setc82_expires($val)   {$this->c82_expires = $val;}
    function setc82_used($val)      {$this->c82_used = $val;}

    function clear(){
        $this->setc82_id(0);
        $this->setc82_user_id(0);
        $this->setc82_key("");
        $this->setc82_created(date('Y-m-d H:i:s'));
        $this->setc82_expires(date('Y-m-d H:i:s'));
        $this->setc82_used(null);
    }

    // SELECT BY ID METHOD
    //----------------------
    function select($id) {
        $this->_opLog .= date("H:i:s u")." SELECT;\r\n";
        $sql = "SELECT * FROM t82_api_key WHERE c82_id=?";
        $stmt = $this->connect()->prepare($sql);

        if(!$stmt->execute(array($id))){
            if ($this->isDebug) echo "[SELECT ApiKey.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog .= date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        $row = $stmt->fetch(PDO::FETCH_BOTH);
        $this->setFromRow($row);
        return true;
    }

    // SELECT BY KEY METHOD
    //----------------------
    function selectByKey($key) {
        $this->_opLog .= date("H:i:s u")." SELECT BY KEY;\r\n";
        $sql = "SELECT * FROM t82_api_key WHERE c82_key=?";
        $stmt = $this->connect()->prepare($sql);

        if(!$stmt->execute(array($key))){
            if ($this->isDebug) echo "[SELECT ApiKey.bean BY KEY EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog .= date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        $row = $stmt->fetch(PDO::FETCH_BOTH);
        $this->setFromRow($row);
        return true;
    }

    private function setFromRow($row){
        if (is_array($row)){
            $this->setc82_id($row["c82_id"]);
            $this->setc82_user_id($row["c82_user_id"]);
            $this->setc82_key($row["c82_key"]);
            $this->setc82_created($row["c82_created"]);
            $this->setc82_expires($row["c82_expires"]);
            $this->setc82_used($row["c82_used"]);
        } else {
            $this->clear();
        }
    }

    // SELECT ROWS METHOD
    //----------------------
    function selectRows($selectFields, $whereClause) {
        $this->_opLog.=date("H:i:s v")." SELECT ROWS;\r\n";
        $where="";
        if (!is_null($whereClause) && $whereClause!="")
            $where.=" WHERE ".$whereClause;
        $sql="SELECT $selectFields FROM t82_api_key".$where;
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute()){
            if ($this->isDebug) echo "[SELECTRows ApiKey.bean ($sql) EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s v")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        return $stmt->fetchall();
    }

    // DELETE METHOD
    //----------------------
    function delete($id) {
        $this->_opLog .= date("H:i:s u")." DELETE (".$id.");\r\n";
        $sql = "DELETE FROM t82_api_key WHERE c82_id = ?;";
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($id))){
            if ($this->isDebug) echo "[DELETE ApiKey.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog .= date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        return true;
    }

    // INSERT METHOD
    //----------------------
    function insert()
    {
        $this->_opLog.=date("H:i:s u")." INSERT;\r\n";
        $this->c82_id = 0; // clear key for autoincrement
        $sql = "INSERT INTO t82_api_key (c82_user_id, c82_key, c82_expires) VALUES (?, ?, ?)";
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($this->c82_user_id, $this->c82_key, $this->c82_expires))){
            if ($this->isDebug) echo "[INSERT ApiKey.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        $this->c82_id = $this->connect()->lastInsertId();
        return true;
    }

    // UPDATE METHOD (Mark as used)
    //----------------------
    function markAsUsed()
    {
        $this->_opLog.=date("H:i:s u")." MARK AS USED;\r\n";
        $sql = "UPDATE t82_api_key SET c82_used = ? WHERE c82_id = ?";
        $this->c82_used = date('Y-m-d H:i:s');
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($this->c82_used, $this->c82_id))){
            if ($this->isDebug) echo "[UPDATE ApiKey.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        return true;
    }

    // DELETE EXPIRED KEYS
    //----------------------
    function deleteExpired()
    {
        $this->_opLog.=date("H:i:s u")." DELETE EXPIRED;\r\n";
        $sql = "DELETE FROM t82_api_key WHERE c82_expires < ?";
        $stmt = $this->connect()->prepare($sql);
        $now = date('Y-m-d H:i:s');
        if(!$stmt->execute(array($now))){
            if ($this->isDebug) echo "[DELETE EXPIRED ApiKey.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return 0;
        }
        return $stmt->rowCount();
    }

    // LOG-DEBUG PURPOSES
    //----------------------
    public function getLog(){
        return $this->_opLog;
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

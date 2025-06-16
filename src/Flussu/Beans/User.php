<?php
/* --------------------------------------------------------------------*
 * Flussu v4.1 - Mille Isole SRL - Released under Apache License 2.0
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
 * TBD - INCOMPLETE
 * 
 * BEAN-NAME:        User
 * GENERATION DATE:  11.01.2021
 * CLASS FILE:       /Beans/User.bean.php
 * FOR MYSQL TABLE:  t80_user
 * FOR MYSQL DB:     wofobo
 * VERSION REL.:     4.1.20250205
 * UPDATE DATE:      12.01:2025 
 * -------------------------------------------------------*/
namespace Flussu\Beans;
use PDO;
class User extends Dbh
{

    private $_opLog=""; // FOR DEBUG PURPOSES

    // ATTRIBUTES
    //----------------------

    private $c80_id;       // KEY ATTR. WITH AUTOINCREMENT

    private  $c80_email;        //
    private  $c80_username;     //
    private  $c80_password;     //
    private  $c80_pwd_chng ;    //<--DateTime)  (default='1899-12-31 00:00:00')
    private  $c80_role;         //
    private  $c80_name;         //
    private  $c80_surname;      // 
    private  $c80_created;      //<--DateTime)  (default='1899-12-31 00:00:00')
    private  $c80_modified;     //<--DateTime)  (default='1899-12-31 00:00:00')
    private  $c80_deleted;      //<--DateTime)  (default='1899-12-31 00:00:00')
    private  $c80_deleted_by;   //<--DateTime)  (default='1899-12-31 00:00:00')

    private $isDebug = false;

    // CONSTRUCTOR
    //----------------------
    function __construct (bool $debug) {
        $this->_opLog = date("D M d, Y H:i:s u")." Created new User.Bean;\r\n";
        if ($debug == true) $isDebug = true;
        $this->clear();
    }

    // GETTERS
    //----------------------
    function getc80_id()		{if (is_null($this->c80_id)) 		return 0; else return $this->c80_id;}
    function getc80_username()	{if (is_null($this->c80_username)) 	return ""; else return $this->c80_username;}
    function getc80_email()		{if (is_null($this->c80_email)) 	return ""; else return $this->c80_email;}
    function getc80_password()	{if (is_null($this->c80_password))	return ""; else return $this->c80_password;}
    function getc80_name()		{if (is_null($this->c80_name)) 	    return ""; else return $this->c80_name;}
    function getc80_surname()	{if (is_null($this->c80_surname)) 	return ""; else return $this->c80_surname;}
    function getc80_role()	    {if (is_null($this->c80_role)) 	    return 0; else return $this->c80_role;}
    function getc80_pwd_chng()	{if (is_null($this->c80_pwd_chng))  return date('1899/12/31'); else return $this->c80_pwd_chng;}
    function getc80_created()	{if (is_null($this->c80_created))   return date('1899/12/31'); else return $this->c80_created;}
    function getc80_modified()	{if (is_null($this->c80_modified))  return date('1899/12/31'); else return $this->c80_modified;}
    function getc80_deleted()   {if (is_null($this->c80_deleted))   return date('1899/12/31'); else return $this->c80_deleted;}
    function getc80_deleted_by(){if (is_null($this->c80_deleted_by)) return 0; else return $this->c80_deleted_by;}

    // SETTERS
    //----------------------
    function setc80_id($val)			{$this->c80_id = $val;}
    function setc80_username($val)		{$this->c80_username = $val;}
    function setc80_email($val)			{$this->c80_email = $val;}
    function setc80_password($val)		{if (is_null($val)) $this->c80_password=""; else $this->c80_password=$val;}
    function setc80_name($val)			{if (is_null($val)) $this->c80_name=""; else $this->c80_name=$val;}
    function setc80_surname($val)		{if (is_null($val)) $this->c80_surname=""; else $this->c80_surname=$val;}
    function setc80_role($val)		    {$this->c80_role = $val;}
    function setc80_pwd_chng($val)		{$this->c80_pwd_chng = $val;}
    function setc80_created($val)		{$this->c80_deleted = $val;}
    function setc80_modified($val)		{$this->c80_created = $val;}
    function setc80_deleted($val)		{$this->c80_modified = $val;}
    function setc80_deleted_by($val)	{$this->c80_deleted_by = $val;}

    function load($login) {
        $this->_opLog.=date("H:i:s u")." LOAD('".$login."');\r\n";
        $sql="SELECT * FROM t80_user WHERE c80_username=?";
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($login))){
            if ($this->isDebug) echo "[SELECT User.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        } else {
            $row = $stmt->fetch(PDO::FETCH_BOTH);
            $this->setFromRow($row);
        }
        return true;
    }

    private function setFromRow($row){
        if (is_array($row)){
            $this->setc80_id($row["c80_id"]);
            $this->setc80_username($row["c80_username"]);
            $this->setc80_email($row["c80_email"]);
            $this->setc80_password($row["c80_password"]);
            $this->setc80_name($row["c80_name"]);
            $this->setc80_surname($row["c80_surname"]);
            $this->setc80_role($row["c80_role"]);
            $this->setc80_pwd_chng($row["c80_pwd_chng"]);
            $this->setc80_created($row["c80_created"]);
            $this->setc80_modified($row["c80_modified"]);
            $this->setc80_deleted($row["c80_deleted"]);
            $this->setc80_deleted_by($row["c80_deleted_by"]);
        } else
            $this->clear();
    }

    function clear(){
        $this->setc80_id(0);
        $this->setc80_username("");
        $this->setc80_email("");
        $this->setc80_password("");
        $this->setc80_role(0);
        $this->setc80_name("");
        $this->setc80_surname("");
        $this->setc80_created(date('1899/12/31 00:00:00'));
        $this->setc80_modified(date('1899/12/31 00:00:00'));
        $this->setc80_deleted(date('1899/12/31 00:00:00'));
        $this->setc80_deleted_by(0);
    }

    // SELECT METHOD
    //----------------------
    function select($id) {
        $this->_opLog .= date("H:i:s u")." SELECT;\r\n";
        $sql = "SELECT * FROM t80_user WHERE c80_id=?";
        $stmt = $this->connect()->prepare($sql);
        
        //echo "connect error:".$stmt->errorInfo()." - ";
        
        if(!$stmt->execute(array($id))){
            //echo "execute error:".$stmt->errorInfo()." - ";
            if ($this->isDebug) echo "[SELECT User.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog .= date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        $row = $stmt->fetch(PDO::FETCH_BOTH);
        $this->setFromRow($row);
    }

    function selectDataUsingEmail($emailAddr){
        $this->_opLog .= date("H:i:s u")." SELECT;\r\n";
        $sql = "SELECT c80_id as UID, c80_username as LOGIN_ID, c80_pwd_chng as PWDCHNG_DATE FROM t80_user WHERE c80_email=?";
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($emailAddr))){
            if ($this->isDebug) echo "[SELECT User.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog .= date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        $row = $stmt->fetch(PDO::FETCH_BOTH);
        return $row;
    }

    function selectRows($selectFields,$whereClause) {
        $this->_opLog.=date("H:i:s v")." SELECT;\r\n";
        $where="";
        if (!is_null($whereClause) && $whereClause!="")
            $where.=" WHERE ".$whereClause;
        $sql="SELECT $selectFields FROM t80_user".$where;
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute()){
            if ($this->isDebug) echo "[SELECTRows User.bean ($sql) EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s v")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        //return $stmt->fetchall(PDO::FETCH_BOTH);
        return $stmt->fetchall();
    }

    /*
    function selectRowsIncludeRules($selectFields, $whereClause) {
        $this->_opLog.=date("H:i:s v")." SELECT;\r\n";
        $where="";
        if (!is_null($whereClause) && $whereClause!="")
            $where.=" WHERE ".$whereClause;
        if ($selectFields=="*")
            $selectFields="t80_user.*";
        $sql="SELECT $selectFields, (SELECT GROUP_CONCAT(c20_rulename) FROM t20_rule, t15_user_rule WHERE c20_ruleid=c15_ruleid AND c15_userid=c80_id) as c00_rulenames_csv FROM t80_user".$where;
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute()){
            if ($this->isDebug) echo "[SELECTRows User.bean ($sql) EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s v")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        //return $stmt->fetchall(PDO::FETCH_BOTH);
        return $stmt->fetchall();
    }
    */

    // DELETE METHOD
    //----------------------
    function delete($id) {
        $this->_opLog .= date("H:i:s u")." DELETE (".$id.");\r\n";
        $sql = "DELETE FROM t80_user WHERE c80_id = ?;";
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($id))){
            if ($this->isDebug) echo "[DELETE User.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
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
        $this->c80_id = 0; // clear key for autoincrement
        $sql = "INSERT INTO t80_user ( c80_username,c80_email,c80_password,c80_role,c80_name,c80_surname,c80_pwd_chng) VALUES ( ?,?,?,?,?,?,? )";
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($this->c80_username,$this->c80_email,$this->c80_password,$this->c80_role,$this->c80_name,$this->c80_surname,$this->c80_pwd_chng))){
            if ($this->isDebug) echo "[INSERT User.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        return true;
    }

    // UPDATE METHOD
    //----------------------
    function update()
    {
        $this->_opLog.=date("H:i:s u")." UPDATE;\r\n";
        $sql = " UPDATE t80_user SET  c80_username = ?,c80_email = ?,c80_role = ?,c80_name = ?,c80_surname = ?,c80_pwd_chng = ? WHERE c80_id = ? ";
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($this->c80_username,$this->c80_email,$this->c80_role,$this->c80_name,$this->c80_surname,$this->c80_pwd_chng,$this->c80_id))){
            if ($this->isDebug) echo "[UPDATE User.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        return true;
    }

    // UPDATE METHOD
    //----------------------
    function updatePassword()
    {
        $this->_opLog.=date("H:i:s u")." UPDATE PASSWORD;\r\n";
        $sql = " UPDATE t80_user SET  c80_password = ?,c80_pwd_chng = ? WHERE c80_id = ? ";
        $stmt = $this->connect()->prepare($sql);
        if(!$stmt->execute(array($this->c80_password,$this->c80_pwd_chng,$this->c80_id))){
            if ($this->isDebug) echo "[UPDATE PASSWORD User.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog.=date("H:i:s u")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        return true;
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
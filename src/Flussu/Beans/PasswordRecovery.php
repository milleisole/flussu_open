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
 * BEAN-NAME:        PasswordRecovery
 * GENERATION DATE:  2025-11-18
 * CLASS FILE:       /Beans/PasswordRecovery.php
 * FOR MYSQL TABLE:  t81_pwd_recovery
 * VERSION REL.:     4.5.20251118
 * --------------------------------------------------------------------*/
namespace Flussu\Beans;
use PDO;

class PasswordRecovery extends Dbh
{
    private $_opLog = "";

    // ATTRIBUTES
    private $c81_id;
    private $c81_user_id;
    private $c81_token;
    private $c81_created;
    private $c81_expires;
    private $c81_used;
    private $c81_used_at;
    private $c81_ip_address;
    private $c81_user_agent;

    private $isDebug = false;

    // CONSTRUCTOR
    function __construct(bool $debug = false) {
        $this->_opLog = date("D M d, Y H:i:s")." Created new PasswordRecovery.Bean;\r\n";
        $this->isDebug = $debug;
        $this->clear();
    }

    // GETTERS
    function getc81_id()         { return $this->c81_id ?? 0; }
    function getc81_user_id()    { return $this->c81_user_id ?? 0; }
    function getc81_token()      { return $this->c81_token ?? ""; }
    function getc81_created()    { return $this->c81_created ?? null; }
    function getc81_expires()    { return $this->c81_expires ?? null; }
    function getc81_used()       { return $this->c81_used ?? 0; }
    function getc81_used_at()    { return $this->c81_used_at ?? null; }
    function getc81_ip_address() { return $this->c81_ip_address ?? null; }
    function getc81_user_agent() { return $this->c81_user_agent ?? null; }

    // SETTERS
    function setc81_id($val)         { $this->c81_id = $val; }
    function setc81_user_id($val)    { $this->c81_user_id = $val; }
    function setc81_token($val)      { $this->c81_token = $val; }
    function setc81_created($val)    { $this->c81_created = $val; }
    function setc81_expires($val)    { $this->c81_expires = $val; }
    function setc81_used($val)       { $this->c81_used = $val; }
    function setc81_used_at($val)    { $this->c81_used_at = $val; }
    function setc81_ip_address($val) { $this->c81_ip_address = $val; }
    function setc81_user_agent($val) { $this->c81_user_agent = $val; }

    function clear() {
        $this->setc81_id(0);
        $this->setc81_user_id(0);
        $this->setc81_token("");
        $this->setc81_created(null);
        $this->setc81_expires(null);
        $this->setc81_used(0);
        $this->setc81_used_at(null);
        $this->setc81_ip_address(null);
        $this->setc81_user_agent(null);
    }

    /**
     * Create a new password recovery token
     * @return bool Success status
     */
    function insert() {
        $this->_opLog .= date("H:i:s")." INSERT;\r\n";
        $this->c81_id = 0; // clear key for autoincrement

        $sql = "INSERT INTO t81_pwd_recovery (c81_user_id, c81_token, c81_expires, c81_ip_address, c81_user_agent)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->connect()->prepare($sql);
        if (!$stmt->execute(array(
            $this->c81_user_id,
            $this->c81_token,
            $this->c81_expires,
            $this->c81_ip_address,
            $this->c81_user_agent
        ))) {
            if ($this->isDebug) echo "[INSERT PasswordRecovery.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog .= date("H:i:s")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        return true;
    }

    /**
     * Mark token as used
     * @return bool Success status
     */
    function markAsUsed() {
        $this->_opLog .= date("H:i:s")." MARK AS USED;\r\n";

        $sql = "UPDATE t81_pwd_recovery SET c81_used=1, c81_used_at=NOW() WHERE c81_id=?";
        $stmt = $this->connect()->prepare($sql);

        if (!$stmt->execute(array($this->c81_id))) {
            if ($this->isDebug) echo "[UPDATE PasswordRecovery.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog .= date("H:i:s")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }
        return true;
    }

    /**
     * Find valid token by hashed value
     * @param string $hashedToken
     * @return bool Success status
     */
    function selectByToken($hashedToken) {
        $this->_opLog .= date("H:i:s")." SELECT BY TOKEN;\r\n";

        $sql = "SELECT * FROM t81_pwd_recovery
                WHERE c81_token=?
                AND c81_used=0
                AND c81_expires > NOW()
                ORDER BY c81_created DESC
                LIMIT 1";

        $stmt = $this->connect()->prepare($sql);

        if (!$stmt->execute(array($hashedToken))) {
            if ($this->isDebug) echo "[SELECT PasswordRecovery.bean EXECUTE ERROR:".implode($stmt->errorInfo())."]<br>";
            $this->_opLog .= date("H:i:s")." EXECUTE ERROR:".implode($stmt->errorInfo()).";\r\n";
            return false;
        }

        $row = $stmt->fetch(PDO::FETCH_BOTH);
        if (is_array($row)) {
            $this->setFromRow($row);
            return true;
        }

        $this->clear();
        return false;
    }

    /**
     * Clean up expired tokens for a user
     * @param int $userId
     * @return bool Success status
     */
    function cleanupExpiredTokens($userId) {
        $this->_opLog .= date("H:i:s")." CLEANUP EXPIRED;\r\n";

        $sql = "DELETE FROM t81_pwd_recovery
                WHERE c81_user_id=?
                AND (c81_expires < NOW() OR c81_used=1)";

        $stmt = $this->connect()->prepare($sql);
        return $stmt->execute(array($userId));
    }

    private function setFromRow($row) {
        if (is_array($row)) {
            $this->setc81_id($row["c81_id"]);
            $this->setc81_user_id($row["c81_user_id"]);
            $this->setc81_token($row["c81_token"]);
            $this->setc81_created($row["c81_created"]);
            $this->setc81_expires($row["c81_expires"]);
            $this->setc81_used($row["c81_used"]);
            $this->setc81_used_at($row["c81_used_at"]);
            $this->setc81_ip_address($row["c81_ip_address"]);
            $this->setc81_user_agent($row["c81_user_agent"]);
        } else {
            $this->clear();
        }
    }

    // LOG-DEBUG PURPOSES
    public function getLog() {
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

<?php
/* --------------------------------------------------------------------*
 * Flussu v4.2 - Mille Isole SRL - Released under Apache License 2.0
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
 * BEAN-NAME:        Databroker.bean
 * WRITTEN DATE:     09.04.2020 - Aldus
 * CLASS DIR:        D:\xampp\htdocs\aldus/classes/beans
 * FOR MYSQL TABLE:  Undefined
 * FOR MYSQL DB:     aldus
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/

declare(strict_types=1);

namespace Flussu\Beans;

use PDO;
use Throwable;
use Flussu\General;

class Databroker extends Dbh
{
    /** @var string Log interno di debug */
    private string $_opLog = "";

    /** @var array|null Parametri di ricerca */
    private ?array $searchData = null;

    /** @var array|null Risultato di fetch dal DB */
    private ?array $sendData = null;

    /** @var mixed Ultimo errore catturato */
    private $_lastError = null;

    /** @var bool Flag di debug */
    private bool $_debug = false;

    /**
     * Costruttore
     *
     * @param bool $debug
     */
    public function __construct(bool $debug = false)
    {
        $this->_opLog = date("D M d, Y H:i:s v") . " Created new Databroker;\r\n";
        $this->_debug = $debug;
    }

    // -----------------------------------------------------------------
    // GETTER
    // -----------------------------------------------------------------

    /**
     * Ritorna l'array di dati di ricerca (se presente).
     *
     * @return array
     */
    public function getSearchData(): array
    {
        return $this->searchData ?? [];
    }

    /**
     * Ritorna i record trovati dopo una query di SELECT.
     *
     * @return array
     */
    public function getFoundRows(): array
    {
        return $this->sendData ?? [];
    }

    /**
     * Indica se il bean è attivo. (placeholder)
     *
     * @return bool
     */
    public function getIsActive(): bool
    {
        return true;
    }

    // -----------------------------------------------------------------
    // SETTER
    // -----------------------------------------------------------------

    /**
     * Imposta i parametri di ricerca. Li converte in array se non lo fossero già.
     *
     * @param mixed $val
     * @return bool
     */
    public function setSearchData($val): bool
    {
        try {
            if (!isset($val)) {
                $this->searchData = null;
            } elseif (!is_array($val)) {
                $this->searchData = [$val];
            } else {
                $this->searchData = $val;
            }
            return true;
        } catch (Throwable $e) {
            $this->_lastError = $e;
            return false;
        }
    }

    // -----------------------------------------------------------------
    // METODI PUBBLICI PER ESEGUIRE QUERY
    // -----------------------------------------------------------------

    /**
     * Carica dati dal DB in base a una query SQL.
     *
     * @param string $sqlString
     * @param bool   $transactional
     * @return bool
     */
    public function loadData(string $sqlString, bool $transactional = false): bool
    {
        try {
            return $this->exec($sqlString, $transactional);
        } catch (Throwable $e) {
            $this->_lastError = $e;
            return false;
        }
    }

    /**
     * Ottiene l'ultimo ID inserito (autoincrement).
     *
     * @return string|false
     */
    public function getLastId()
    {
        return $this->getLastInsertId();
    }

    // -----------------------------------------------------------------
    // METODI TRANSAZIONALI "MULTI-ESECUZIONE"
    // -----------------------------------------------------------------

    /** @var PDO|null */
    private ?PDO $_mStmt = null;

    /** @var Dbh|null */
    private ?Dbh $tBbh = null;

    /**
     * Inizia una transazione per inserimenti multipli.
     */
    public function prepareMultExecs(): void
    {
        $this->tBbh = new Dbh();
        $this->_opLog .= date("H:i:s v") . " DataBroker START MULTIPLE TRANSACTIONAL INSERT\r\n\t";
        $this->_mStmt = $this->tBbh->connect(true);
    }

    /**
     * Esegue le insert multiple in un batch.
     *
     * @param string $sqlString
     * @param array  $paramsArr
     * @return bool
     */
    public function execMultExecs(string $sqlString, array $paramsArr): bool
    {
        $res = true;
        if (!$this->_mStmt) {
            $this->_lastError = "Transazione multipla non inizializzata.";
            return false;
        }

        try {
            $this->_opLog .= date("H:i:s v") . " DataBroker EXEC SQL MULT INSERT\r\n\t";
            $stmt = $this->_mStmt->prepare($sqlString);

            foreach ($paramsArr as $params) {
                try {
                    $ok = $stmt->execute($params);
                    if (!$ok) {
                        $this->_lastError = $stmt->errorInfo();
                        General::addRowLog("[DataBroker MULT_EXECUTE ERROR: " . implode($stmt->errorInfo()) . "]");
                        $res = false;
                    }
                } catch (Throwable $e) {
                    $this->_lastError = $e;
                    General::addRowLog("[DataBroker MULT_EXECUTE EXCEPTION: {$e->getMessage()}]");
                    $res = false;
                }
            }
        } catch (Throwable $e) {
            $this->_lastError = $e;
            General::addRowLog("[DataBroker MULT_EXECUTE EXCEPTION: {$e->getMessage()}]");
            $res = false;
        }
        return $res;
    }

    /**
     * Chiude la transazione multipla, facendo il commit.
     */
    public function closeMultExecs(): void
    {
        if (!$this->_mStmt || !$this->tBbh) {
            return;
        }
        $this->_opLog .= date("H:i:s v") . " DataBroker END MULTIPLE TRANSACTIONAL INSERT\r\n\t";
        $this->commit($this->_mStmt, true);
        $this->tBbh = null;
        $this->_mStmt = null;
    }

    /**
     * Esegue un'inserzione multipla (tutto in uno, con beginTransaction e commit).
     *
     * @param string $sqlString
     * @param array  $paramsArr
     */
    public function multDataInsert(string $sqlString, array $paramsArr): void
    {
        $this->_opLog .= date("H:i:s v") . " DataBroker START MULT INSERT\r\n\t";

        // Avvio transazione
        $pdo = $this->connect(true);
        $stmt = $pdo->prepare($sqlString);

        foreach ($paramsArr as $params) {
            try {
                $stmt->execute($params);
            } catch (Throwable $e) {
                $this->_lastError = $stmt->errorInfo();
                General::addRowLog("[DataBroker EXECUTE MULT INSERT ERROR: " . implode($stmt->errorInfo()) . "]");
            }
        }

        // Commit conclusivo
        $this->commit();
    }

    /**
     * Esegue una serie di comandi (SQL e parametri) in un'unica transazione.
     *
     * @param array $sqlArr Array di array associativi: [ ["SQL" => "...", "PRM" => [...]], ... ]
     * @return bool|string  Restituisce il concatenato di booleani, oppure false in caso di errori.
     */
    public function transExecs(array $sqlArr)
    {
        $res = "";
        try {
            $this->_mStmt = $this->connect(true);
            $this->_opLog .= date("H:i:s v") . " DataBroker EXEC SQL TRANS-EXEC\r\n\t";
            $this->_mStmt->beginTransaction();

            foreach ($sqlArr as $sqlCmd) {
                $SQL = $sqlCmd["SQL"];
                $PRM = $sqlCmd["PRM"];

                $eStmt = $this->_mStmt->prepare($SQL);
                try {
                    $ok = $eStmt->execute($PRM);
                    $res .= "|" . ($ok ? "true" : "false");
                } catch (Throwable $e) {
                    $this->_lastError = $this->_mStmt->errorInfo();
                    General::addRowLog("[DataBroker TRANS-EXEC ERROR: " . implode($this->_mStmt->errorInfo()) . "]");
                    $res .= "|false";
                }
            }

            $this->_mStmt->commit();
        } catch (Throwable $e) {
            $this->_lastError = $this->_mStmt ? $this->_mStmt->errorInfo() : $e->getMessage();
            General::addRowLog("[DataBroker TRANS-EXEC EXCEPTION: " . (is_array($this->_lastError) ? implode($this->_lastError) : $this->_lastError) . "]");
            return false;
        }

        return $res;
    }

    // -----------------------------------------------------------------
    // METODI PER LOG E ERRORI
    // -----------------------------------------------------------------

    /**
     * Restituisce il log delle operazioni svolte.
     *
     * @return string
     */
    public function getLog(): string
    {
        return $this->_opLog;
    }

    /**
     * Restituisce l'ultimo errore catturato.
     *
     * @return mixed
     */
    public function getError()
    {
        return $this->_lastError;
    }

    // -----------------------------------------------------------------
    // METODI PRIVATI DI SUPPORTO
    // -----------------------------------------------------------------

    /**
     * Esegue una query SELECT/UPDATE/DELETE con o senza parametri (searchData), opzionalmente in transazione.
     *
     * @param string $sqlString
     * @param bool   $transactional
     * @return bool
     */
    private function exec(string $sqlString, bool $transactional = false): bool
    {
        $this->_opLog .= date("H:i:s v") . " DataBroker EXEC SQL:\r\n\t$sqlString\r\n\t";

        // Apre la connessione con opzione transazionale se richiesto
        $stmt = $this->connect($transactional)->prepare($sqlString);

        $executeOk = false;

        try {
            // se searchData non è null, la usiamo come parametri
            $executeOk = $stmt->execute($this->searchData ?? []);
        } catch (Throwable $e) {
            $this->_lastError = $e;
            $this->_opLog .= "EXECUTE ERROR: " . $e->getMessage() . ";\r\n";
            if ($transactional) {
                $this->rollBack();
            }
            return false;
        }

        if (!$executeOk) {
            $this->_lastError = $stmt->errorInfo();
            $this->_opLog .= "EXECUTE ERROR: " . implode(" | ", $stmt->errorInfo()) . ";\r\n";
            if ($transactional) {
                $this->rollBack();
            }
            return false;
        }

        // Se arriva qui, l'esecuzione è andata a buon fine
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); 
        $this->sendData = is_array($rows) ? $rows : [];

        // Se era transazionale, facciamo il commit
        if ($transactional) {
            $this->commit();
        }

        return true;
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
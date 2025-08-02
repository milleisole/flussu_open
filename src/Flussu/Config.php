<?php
/* --------------------------------------------------------------------*
 * Flussu v4.4 - Mille Isole SRL - Released under Apache License 2.0
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
 * CLASS PATH:       App\Flussu
 * CLASS-NAME:       General.class
 * -------------------------------------------------------*
 * RELEASED DATE:    07.01:2022 - Aldus - Flussu v2.0
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     29.07:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * Implemented a "config file" search.
 * -------------------------------------------------------*/

/**
 * Class Config
 *
 * This class is responsible for managing the configuration settings of the Flussu application.
 * It follows the Singleton pattern to ensure that only one instance of the configuration is loaded
 * and used throughout the application. The configuration data can be accessed using dot notation
 * for nested values. It provides methods to retrieve all configuration data, specific sections,
 * or individual values.
 *
 * SAMPLE usage:
 *
 * Get the Singleton instance (does not reload if already initialized)
 *    $cfg = Config::init();
 *
 * Get all configuration data
 *   $allData = $cfg->all();
 *
 * Get a specific section
 *   $services = $cfg->getSection('services');
 *   print_r($services);
 *
 * Get a specific value using dot notation
 *    $googlePrivateKey = $cfg->get('services.google.private_key');
 *    echo $googlePrivateKey;
 *
 * Or get "Stripe" info
 *   $stripeTestKey = $cfg->get('services.stripe.test_key');
 *   echo $stripeTestKey;
 */
namespace Flussu;
use RuntimeException;

final class Config
{
    /**
    * @var self|null Istanza singleton
    */
    private static ?self $instance = null;

    /** @var array|null Contiene i dati di configurazione letti dal JSON */
    private static ?array $configData = null;

    /**
     * Costruttore privato (Singleton).
     * Carica i dati dal file JSON.
     */
    private function __construct()
    {
        // Proviamo diversi metodi per trovare il file di configurazione
        $filePath = $this->findConfigFile();
        
        if (!file_exists($filePath)) {
            throw new RuntimeException("Can't find the configuration file: $filePath");
        }

        $jsonStr = file_get_contents($filePath);
        if ($jsonStr === false) {
            throw new RuntimeException("The configuration file is unreadable: $filePath");
        }

        $data = json_decode($jsonStr, true);
        if (!is_array($data)) {
            throw new RuntimeException("Can't decode the JSON file: $filePath");
        }

        // Salviamo i dati in un array interno IMMUTABILE
        self::$configData = $data;
    }
    
    /**
     * Trova il file di configurazione provando diversi percorsi
     */
    private function findConfigFile(): string
    {
        $possiblePaths = [];
        
        // 1. Prova con DOCUMENT_ROOT se disponibile
        if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
            $possiblePaths[] = $_SERVER['DOCUMENT_ROOT'] . "/../config/.services.json";
        }
        
        // 2. Prova relativo alla directory corrente del file Config.php
        $possiblePaths[] = __DIR__ . "/../../config/.services.json";
        
        // 3. Prova relativo alla root del progetto (assumendo struttura standard)
        $possiblePaths[] = dirname(__DIR__, 2) . "/config/.services.json";
        
        // 4. Se esiste una costante o env variable per il percorso
        if (defined('FLUSSU_CONFIG_PATH')) {
            $possiblePaths[] = FLUSSU_CONFIG_PATH . "/.services.json";
        }
        
        if (isset($_ENV['FLUSSU_CONFIG_PATH'])) {
            $possiblePaths[] = $_ENV['FLUSSU_CONFIG_PATH'] . "/.services.json";
        }
        
        // Prova tutti i percorsi
        foreach ($possiblePaths as $path) {
            $normalizedPath = realpath(dirname($path)) . '/' . basename($path);
            if (file_exists($normalizedPath)) {
                return $normalizedPath;
            }
        }
        
        // Se nessun percorso funziona, ritorna il primo tentativo per il messaggio di errore
        return $possiblePaths[0];
    }
    
    /**
     * Metodo statico per inizializzare il Config (se non già fatto) e restituirne l'istanza.
     *
     * @return self
     */
    public static function init(): self
    {
        // Se non esiste un'istanza, la creiamo
        if (self::$instance === null) {
            self::$instance = new self();
        }

        // Se esiste già, non ricarichiamo nulla: è immutabile!
        return self::$instance;
    }

    /**
     * Restituisce TUTTO il contenuto del file di configurazione in forma di array.
     *
     * @return array
     */
    public function all(): array
    {
        return self::$configData;
    }

    /**
     * Ritorna un sotto-array di configurazioni, ad esempio "services".
     * 
     * @param string $key Chiave di primo livello (es. "services")
     * @return array|null
     */
    public function getSection(string $key): ?array
    {
        return self::$configData[$key] ?? null;
    }

    /**
     * Ritorna una voce di configurazione usando la "dot notation".
     * Esempio: "services.google.client_email"
     *
     * @param string $key
     * @param mixed $defaultValue Valore di default se la chiave non esiste
     * @return mixed|null
     */
    public function get(string $key, $defaultValue = null)
    {
        $keys = explode('.', $key);

        $value = self::$configData;
        foreach ($keys as $part) {
            if (!isset($value[$part])) {
                return $defaultValue; // Chiave non trovata
            }
            $value = $value[$part];
        }

        return $value;
    }
    
    /**
     * Impediamo la clonazione e la serializzazione (immutabilità).
     */
    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize Config"); }
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
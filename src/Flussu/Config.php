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
 * CLASS PATH:       App\Flussu
 * CLASS-NAME:       General.class
 * -------------------------------------------------------*
 * RELEASED DATE:    07.01:2022 - Aldus - Flussu v2.0
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
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
        $filePath = $_SERVER['DOCUMENT_ROOT']."/../config/.services.json";
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
        return $this->configData;
    }

    /**
     * Ritorna un sotto-array di configurazioni, ad esempio "services".
     * 
     * @param string $key Chiave di primo livello (es. "services")
     * @return array|null
     */
    public function getSection(string $key): ?array
    {
        return $this->configData[$key] ?? null;
    }

    /**
     * Ritorna una voce di configurazione usando la "dot notation".
     * Esempio: "services.google.client_email"
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key,$defaultValue=null)
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
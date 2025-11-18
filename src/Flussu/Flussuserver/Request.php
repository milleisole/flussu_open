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
 Classe che "mima" il resolver $Request di Laravel
 Questa modifica non è più necessaria, in futuro potrà essere eliminata
 perchè crea incompatibilità tra PHP 7 e PHP 8
 * -------------------------------------------------------*
 * CLASS PATH:       App\Flussu\Illuminate\Http
 * CLASS-NAME:       Request
 * -------------------------------------------------------*
 * Request.php v1.1
 * CREATED DATE:     22.02:2022 - Aldo Prinzi
 * VERSION REL.:     5.0.0.20251103
 * UPDATES DATE:     11.03:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/
namespace Flussu\Flussuserver;
use Flussu\General;

class Request implements \ArrayAccess {
    private $container = array();

    public function __construct() {  $this->container = array(); }
    public function offsetExists($offset):bool {  return isset($this->container[$offset]); }
    public function offsetUnset($offset):void {   unset($this->container[$offset]); }
    public function offsetSet($offset, $value):void {
        if (is_null($offset)) $this->container[] = $value;
        else $this->container[$offset] = $value;
    }
    public function offsetGet($offset):mixed   {
        if (isset($this->container[$offset])) 
            return $this->container[$offset];
        return General::getGetOrPost($offset);
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
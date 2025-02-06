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
 * CLASS-NAME:     Abstract class for SMS providers
 * CREATE DATE:    14.01:2025 
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use Flussu\Contracts\IPayProvider;
abstract class AbsPayProviders implements IPayProvider{
    protected $_compName="";
    protected $_keyType="";
    protected $_apiKey="";
    function __construct() {}

    protected function _setKey($providerName)
    {
        $this->_apiKey = config("services.pay_provider.".$providerName.".".$this->_compName.".".$this->_keyType);
    }

}
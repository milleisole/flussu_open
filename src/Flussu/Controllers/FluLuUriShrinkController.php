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
 * CLASS-NAME:      Flussu Flu-lu Uri Shrink Controller
 * CREATE DATE:    24.09.2023 - Aldus - Flussu v2.9
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use Flussu\Contracts\IUriShrinkProvider;
use Flussu\HttpCaller;
/**
 * Provides functionality to copmpress (SHRINK) a long URI to a short URI.
 * Class specialized on done so using the Flu.lu service.
 */
class FluLuUriShrinkController implements IUriShrinkProvider
{
    private $_svcuri="";
    private $_svckey="";
    function __construct() {
        $this->_svcuri=config("services.shorturl.flu_lu.uri");
        $this->_svckey=config("services.shorturl.flu_lu.api_key");
    }

    public function shrink($longUri){
        $completeUri="https://".$this->_svcuri."/api?key=".$this->_svckey."&uri=".urlencode($longUri);
        $hp=new HttpCaller();
        $res= $hp->exec($completeUri,"GET");
        $ret=json_decode($res);
        return $ret->short_url;
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
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
use Flussu\Contracts\ISmsProvider;
use Flussu\HttpCaller;
abstract class AbsSmsSender implements ISmsProvider{
    protected $_apiKey="";
    function __construct() {
        $classPath = explode('\\', static::class);
        $this->_apiKey=config("services.sms_provider.".end($classPath).".api_key");
    }

    /**
     * @param  string  $url
     * @param  string  $method
     * @param  array   $opts
     * @return bool|string
     */

     protected function sendRequest(string $url, string $method, array $opts = [])
     {
        $ht=new HttpCaller();
        return $ht->exec($url,$method,$opts);

        /*
         $Url = $url . '?' . http_build_query($opts);
         $curl_info = [
             CURLOPT_URL            => $Url,
             CURLOPT_RETURNTRANSFER => true,
             CURLOPT_ENCODING       => '',
             CURLOPT_MAXREDIRS      => 10,
             CURLOPT_FOLLOWLOCATION => true,
             CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
             CURLOPT_CUSTOMREQUEST  => $method,
         ];
 
         if ($opts == []) {
            unset($curl_info[CURLOPT_POSTFIELDS]);
        }
         $curl = curl_init();
 
         curl_setopt_array($curl, $curl_info);
         $response = curl_exec($curl);
 
         $info= curl_getinfo($curl);
         if ($info['http_code']!=200){
             return false;
         }
         curl_close($curl);
 
         if (stripos($response,"XML")!==false){
             $xml = simplexml_load_string($response);
             $response = json_encode($xml);
         }
         return $response;
         */
     }
}
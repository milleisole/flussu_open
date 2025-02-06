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
 * CLASS PATH:       App\Flussu
 * CLASS-NAME:       HttpCaller.class
 * -------------------------------------------------------*
 * RELEASED DATE:    07.01:2022 - Aldus - Flussu v2.0
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/

/*
    The HttpCaller class is a utility class designed to be the reference http/call/api class for entire Flussu project.
    It is designed to facilitate making HTTP requests. 
    The class includes properties for managing headers, proxy settings, cURL information, 
    content types, and timeout settings. The constructor initializes these properties, 
    although some initialization code is currently commented out. 
    The class also includes a method to set the timeout for HTTP requests.
*/


namespace Flussu;

class HttpCaller {
// Properties
private array $headers; // Stores HTTP headers for the request
private string $proxy = ""; // Stores proxy settings if any
private array $curlInfo = []; // Stores cURL information
private array $contentTypes; // Stores different content types for the request
private int $timeout = 0; // Stores the timeout setting for the request

// Constructor    
public function __construct(string $BEARER_KEY=null)
    {
        if (!is_null($BEARER_KEY)){
            $this->headers = [
                $this->contentTypes["application/json"],
                "Authorization: Bearer $BEARER_KEY",
            ];
        }
        $this->headers = [];
        $this->contentTypes = [
            "application/json"                => "Content-Type: application/json",
            "multipart/form-data"             => "Content-Type: multipart/form-data",
            "application/x-www-form-urlencoded" => "Content-Type: application/x-www-form-urlencoded",
            "text/plain"                      => "Content-Type: text/plain",
            "text/html"                       => "Content-Type: text/html",
            "application/xml"                 => "Content-Type: application/xml",
            "application/pdf"                 => "Content-Type: application/pdf",
            "image/jpeg"                      => "Content-Type: image/jpeg",
            "image/png"                       => "Content-Type: image/png",
        ];
    }

        /**
     * @param  int  $timeout
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @param  string  $proxy
     */
    public function setProxy(string $proxy)
    {
        if ($proxy && strpos($proxy, '://') === false) {
            $proxy = 'https://'.$proxy;
        }
        $this->proxy = $proxy;
    }

    /**
     * @param  array  $header
     * @return void
     */
    public function setHeader(array $header)
    {
        if ($header) {
            foreach ($header as $key => $value) {
                $this->headers[$key] = $value;
            }
        }
    }

     /**
     * @param  string  $url
     * @param  string  $method GET / POST
     * @param  array   $opts
     * @return bool|string
     */
    public function exec(string $url, string $method, array $opts = [])
    {
        $method=strtoupper($method);
        switch($method){
            case "GET":
            case "POST":
                break;
            default:
                throw new \Exception("HttpCaller: Invalid GET/POST parameter");
        }
        $resp="";

        $post_fields      = $opts;
        if (array_key_exists('file', $opts) || array_key_exists('image', $opts)) {
            $this->headers[0] = $this->contentTypes["multipart/form-data"];
        } else {
            if ($method=="GET"){
                $url = $url . '?' . http_build_query($opts);
            } else {
                $post_fields = json_encode($opts);
                $this->headers[0] = $this->contentTypes["application/json"];
            }
        }
        $curl_info = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_HTTPHEADER     => $this->headers,
        ];

        if ($opts == [] || $method=="GET"){
            unset($curl_info[CURLOPT_POSTFIELDS]);
        }

        if (!empty($this->proxy)) {
            $curl_info[CURLOPT_PROXY] = $this->proxy;
        }
        
        $curl = curl_init();

        curl_setopt_array($curl, $curl_info);
        $response = curl_exec($curl);

        $info           = curl_getinfo($curl);
        $this->curlInfo = $info;

        curl_close($curl);

        return $response;
    }
}
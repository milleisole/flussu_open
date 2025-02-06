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
 * CLASS-NAME:       Flussu API Controller
 * CREATED DATE:     04.08.2022 - Aldus - Flussu v2.2
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;
use Auth;
use Session;

class PartitaIvaController 
{

  /**
   * It takes a country code and a VAT number and returns an array with the VAT number validity, the
   * company name, the company address and the VAT number itself
   * 
   * Args:
   *   country: The country code of the VAT number to check.
   *   vatId: The VAT number to check.
   */
   public function PICheck($vatId,$country=null){
        if (empty($country)){
            if (strlen($vatId)==11)
                $country="IT";
        }
        $countries = array(
            "IT","BE","BG","CY","CZ","DK","EE","FI","FR","DE","GR","HU","IE","AT","LV","LT","LU","MT","NL","PL","PT","RO","SK","SI","ES","SE","GB"
        );
        $res=null;
        if ($country="IT"){
            $vatId=$this->_checkFormat($vatId);
            if(!empty($vatId)) {
                $vatId=$this->_checkLast($vatId);
                if(!empty($vatId)) 
                    $ret=["error"=>false,"valid"=>true,"name"=>"","address"=>"","vat"=>"IT".$vatId];
                else
                    $ret=["error"=>true,"valid"=>false,"name"=>"","address"=>"","vat"=>""];
            }
        } else {
            if(!empty($country) && !empty($vatId)) {
                $client = new \SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");
                $res=$client->checkVat(array(
                    'countryCode' => $country,
                    'vatNumber' => $vatId,
                ));
            }
            if (isset($res))
                $ret=["error"=>false,"valid"=>$res->valid,"name"=>$res->name,"address"=>$res->address,"vat"=>$res->countryCode.$res->vatNumber];
            else
                $ret=["error"=>true,"valid"=>false,"name"=>"","address"=>"","vat"=>""];
        }
        return $ret;
    } 

    private function _checkFormat($pi)
    {
        if ($pi === '') 
            return '';
        elseif (strlen($pi) != 11) 
            return ""; //'La Partita IVA deve essere composta da 11 caratteri';
        elseif (preg_match("/^[0-9]+\$/D", $pi) != 1) 
            return ""; //'La Partita IVA deve contenere solo numeri';
        else return $pi;
    }

    private function _checkLast($pi){
        $s = $c = 0;
        for($i=0; $i<=9; $i+=2) {
            $s += ord($pi[$i]) - ord('0');
        }
        for ($i=1; $i<=9; $i+=2) {
            $c = 2*(ord($pi[$i]) - ord('0'));
            if ($c > 9) $c = $c - 9;
                $s += $c;
        }
        $controllo = (10 - $s%10)%10;
        if ($controllo != (ord($pi[10]) - ord('0'))) {
            return ""; //'La Partita IVA non sembra valida';
        }else{
            return $pi;
        }
    }
}
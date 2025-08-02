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
 * CLASS-NAME:       Flussu Stripe API Controller
 * CREATED DATE:     22.04.2024 - Aldus - Flussu v3.0.0
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * -------------------------------------------------------*/
namespace Flussu\Controllers;

/**
 * Flussu Stripe API Controller
 * This class serves as an interface to the Stripe connector.
 * It provides methods to retrieve charge information from Stripe.
 */
class StripeController extends AbsPayProviders
{
    private $_stripe;

    public function init($companyName,$keyType){
        $this->_compName = $companyName;
        $this->_keyType = $keyType;
        $this->_apiKey="";
        $this->_setKey("stripe");
        return $this->_apiKey!="";
    }

    public function getChargeInfo($ch_Id, $keyName){
        $res=null;
        $stripe_payId="";
        if (isset($keyName) && !empty($keyName))
            return "you must INIT: no key found!";
        $this->_stripe = new \Stripe\StripeClient($this->_apiKey);
        if (isset($this->_stripe) && !is_null($this->_stripe)){
            $item=null;
            $ref="";
            $cstmFlds=[];
            if (substr(strtolower($ch_Id), 0, 3)=="pi_"){
                $payint=$this->_stripe->paymentIntents->retrieve($ch_Id,[]);
                $arr_payint=json_decode(json_encode($payint),true);
                foreach ($arr_payint["metadata"] as $mtdKey=>$mtdVal){
                    array_push($cstmFlds,["key"=>$mtdKey,"value"=>$mtdVal]);
                }
                $tot=$payint->amount;
                if (strlen($tot)>2 && substr($tot, -2, 1)!=",")
                $dec=substr($tot, 0, -2).",".substr($tot,-2);
                $resch=$this->_stripe->charges->retrieve($arr_payint["latest_charge"], []);
                $custPhone="";
                $custName="";
                $custEmail="";
                $custCountry="";
                if (isset($arr_payint["customer"])){
                    $cust=json_decode(json_encode($this->_stripe->customers->retrieve($arr_payint["customer"], [])),true);
                    if (isset($cust["address"]["phone"]))
                        $custPhone=$cust["address"]["phone"];
                    $custName=$cust["name"];
                    $custEmail=$cust["email"];
                    $custCountry=$cust["address"]["country"];
                } else {
                    $custName=$resch->billing_details->name;
                    $custEmail=$resch->billing_details->email;
                    $custCountry=$resch->billing_details->address->country;
                }
                if (empty($custPhone) && isset($resch->billing_details) && isset($resch->billing_details->phone))
                    $custPhone=$resch->billing_details->phone;
                $custData=["name"=>$custName,"email"=>$custEmail,"phone"=>$custPhone,"country"=>$custCountry];
                array_push($cstmFlds,["key"=>"invoice","value"=>$cust["invoice_prefix"]]);
                $mdt="";
                if (isset($resch->metadata))
                    $mdt=$resch->metadata;
                $itmId="";
                try{
                    $itmId=array_values(array_values($cstmFlds)[0])[1];
                } catch(\Throwable $e){
                    $itmId="NOT FOUND!";
                    //Item id/Order id - Metadata not found!!
                }
                $item=[
                    "id"=>$itmId,
                    "description"=>$resch->calculated_statement_descriptor,
                    "metadata"=>$mdt,
                    "price"=>["amount"=>$dec,"id"=>implode(":",array_values($cstmFlds)[0]),"metadata"=>$mdt]
                ];
                $res=[
                    "session"=>$arr_payint["latest_charge"],
                    "intent"=>$arr_payint["id"],
                    "charge"=>$dec,
                    "event"=>["date"=>date('Y/m/d', $arr_payint["created"]),"time"=>date('H:i:s', $arr_payint["created"])],
                    "customer"=>$custData,
                    "email"=>$resch->billing_details->email,
                    "total"=>$dec,
                    "currency"=>$arr_payint["currency"],
                    "paid"=>($arr_payint["status"]=="succeeded"),
                    "receipt"=>$resch->receipt_url,
                    "metadata"=>$mdt,
                    "reference"=>$resch->description,
                    "product"=>$item,
                    "custom_fields"=>$cstmFlds
                ];
            }
            if (substr(strtolower($ch_Id), 0, 3)=="ch_"){
                $resch=$this->_stripe->charges->retrieve($ch_Id, []);
                $respi=$this->_stripe->paymentIntents->retrieve($resch->payment_intent, []);
                $stripe_payId=$respi->id;
                $stripe_payId=$ch_Id;
                $resses=$this->_stripe->checkout->sessions->all(['limit' => 1,'payment_intent' => $stripe_payId,'expand' => ['data.line_items']]);
                if (isset($resses->data[0]->custom_fields)){
                    if (count($resses->data[0]->custom_fields)){
                        foreach ($resses->data[0]->custom_fields as $customField)
                            array_push($cstmFlds,["key"=>$customField->key,"type"=>$customField->type,"label"=>$customField->label->custom,"value"=>$customField->text->value]);
                    }
                    $payInfo=$resses->data[0];
                }
                $tot=$payInfo->amount_total;
                if (strlen($tot)>2 && substr($tot, -2, 1)!=",")
                $dec=substr($tot, 0, -2).",".substr($tot,-2);
                $mdt="";
                if (isset($payInfo->metadata))
                    $mdt=$payInfo->metadata;
                if (isset($payInfo->line_items) && is_array($payInfo->line_items->data)){
                    $des=$payInfo->line_items->data[0]->description;
                    if (isset($payInfo->line_items->data[0]->price->nickname))
                        $des.=" (".$payInfo->line_items->data[0]->price->nickname.")";
                    $pmdt=null;
                    $pmmdt=null;
                    if (is_array($payInfo->line_items->data[0]->metadata))
                        $pmdt=$payInfo->line_items->data[0]->metadata;
                    if (is_array($payInfo->line_items->data[0]->price->metadata))
                        $pmmdt=$payInfo->line_items->data[0]->price->metadata;
                    $ptot=$payInfo->line_items->data[0]->price->unit_amount_decimal;
                    if (strlen($ptot)>2 && substr($ptot, -2, 1)!=",")
                    $pdec=substr($ptot, 0, -2).",".substr($ptot,-2);
                    $item=[
                        "id"=>$payInfo->line_items->data[0]->price->id,
                        "description"=>$des,
                        "metadata"=>$pmdt,
                        "price"=>["amount"=>$pdec,"id"=>$payInfo->line_items->data[0]->price->id,"metadata"=>$pmmdt]
                    ];
                }
                if (isset($payInfo->client_reference_id))
                    $ref=$payInfo->client_reference_id;
                $res=[
                    "session"=>$payInfo->id,
                    "intent"=>$respi->id,
                    "charge"=>$ch_Id,
                    "event"=>["date"=>date('Y/m/d', $resch->created),"time"=>date('H:i:s', $resch->created)],
                    "customer"=>["name"=>$payInfo->customer_details->name,"email"=>$payInfo->customer_details->email,"phone"=>$payInfo->customer_details->phone],
                    "email"=>$resch->receipt_email,
                    "total"=>$dec,
                    "currency"=>$payInfo->currency,
                    "paid"=>($resch->paid=="true"),
                    "receipt"=>$resch->receipt_url,
                    "metadata"=>$mdt,
                    "reference"=>$ref,
                    "product"=>$item,
                    "custom_fields"=>$cstmFlds
                ];
            }
        }
        return $res;
    }

    function createPayLink($paymentId,$prodName,$prodPrice,$prodImg,$successUri,$cancelUri){
        $res=["link"=> "", "id"=>"","stripeSession"=>null];
        try {
            $parts=explode(".",$prodPrice);
            if (count($parts)<2)
                $parts[1]="00";
            $amount = $parts[0].substr($parts[1]."00",0,2);
            $params=[
                'mode' => 'payment',
                /* 'payment_method_types' => ['card'],*/
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $prodName,
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                /*'customer_email'=>$email,*/
                'client_reference_id'=> $paymentId,
                'phone_number_collection' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'identificativo' => $paymentId,
                ],
                // Anche se non utilizzi il redirect, Stripe richiede questi URL
                'success_url' => $successUri,
                'cancel_url' => $cancelUri,
            ];
            if (isset($prodImg) && !empty($prodImg))
                $params['line_items'][0]['price_data']['product_data']['images'][] = $prodImg;

            $this->_stripe = new \Stripe\StripeClient($this->_apiKey);
            $chkOut=$this->_stripe->checkout->sessions->create($params);
            // Ottieni il link di pagamento
            $payment_link = $chkOut->url;
            // Stampa o restituisci il link di pagamento
            $res["link"]= $payment_link;
            $res["id"]=$chkOut->id;
            $res["stripeSession"]=$chkOut;
        } catch (\Throwable $e) {
            // Gestisci l'errore
            $res["link"]="ERROR : ".$e->getMessage();
        }
        return $res;
    }
    function getStripePaymentResult( $stripeSessionId){
        $ret="ERROR";
        try {
            // Usa l'API di ricerca per trovare la Checkout Session con il metadata specifico
            if (isset($keyName) && !empty($keyName))
                return "ERROR: you must INIT: no key found!";
            $this->_stripe = new \Stripe\StripeClient($this->_apiKey);
            $events=$this->_stripe->events->all(['limit'=>20,'type'=>'charge.*']);
            foreach ($events->data as $event){
                $ret=$this->_eventGet ($event,$stripeSessionId);
                if (count($ret)>1)
                    break;
            }
        } catch (\Throwable $e) {
            $ret="ERROR : ".$e->getMessage();
        }
        return $ret;
    }

    public function getWebHookData($payload){
        $ret = [];

        try {
            $event=json_decode($payload);
            $ret["charge_date"]=date('Y/m/d H:i:s', $event->created);
            $ret["charge_id"]=$event->data->object->id;
            $ret["amount"]=$event->data->object->amount_captured;
            $ret["currency"]=$event->data->object->currency;
            $ret["billing_country"]=$event->data->object->billing_details->address->country;
            $ret["billing_city"]=$event->data->object->billing_details->address->city;
            $ret["billing_address"]=$event->data->object->billing_details->address->line1;
            $ret["billing_address_l2"]=$event->data->object->billing_details->address->line2;
            $ret["billing_postal_code"]=$event->data->object->billing_details->address->postal_code;
            $ret["billing_state"]=$event->data->object->billing_details->address->state;
            $ret["billing_email"]=$event->data->object->billing_details->name;
            $ret["billing_name"]=$event->data->object->billing_details->email;
            $ret["billing_phone"]=$event->data->object->billing_details->phone;
            $ret["description"]=$event->data->object->description;
            $ret["metadata"]=json_encode($event->data->object->metadata);
            $ret["shipping"]=json_encode($event->data->object->shipping);
            $ret["payment_intent_id"]=$event->data->object->payment_intent;
            $ret["payment_method_id"]=$event->data->object->payment_method;
            $ret["receipt_email"]=$event->data->object->receipt_email;
            $ret["receipt_url"]=$event->data->object->receipt_url;
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
           //http_response_code(400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            //http_response_code(400);
        }
        // Handle the event
        //echo 'Received unknown event type ' . $event->type;
        //http_response_code(200);

        return $ret;
    }
    function getWebHookEvent($stripeKeyId,$endpointSecret){
        $ret=[];
        try {
            // $endpointSecret is your Stripe CLI webhook secret key.
            if (isset($keyName) && !empty($keyName))
                return "ERROR: you must INIT: no key found!";
            $this->_stripe = new \Stripe\StripeClient($this->_apiKey);
            if (isset($endpointSecret) && !empty($endpointSecret)){

                $payload = @file_get_contents('php://input');
                $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
                $event = null;

                try {
                    $event = \Stripe\Webhook::constructEvent(
                      $payload, $sig_header, $endpointSecret
                    );
                    $ret=$this->getWebHookData($payload);
                    $ret=array_merge($ret,$this->_eventGet ($event));
                } catch(\UnexpectedValueException $e) {
                    // Invalid payload
                   //http_response_code(400);
                } catch(\Stripe\Exception\SignatureVerificationException $e) {
                    // Invalid signature
                    //http_response_code(400);
                }
                // Handle the event
                return 'ERROR: Received unknown event type ' . $event->type;
                //http_response_code(200);
            }
        } catch (\Throwable $e) {
            $ret="ERROR : ".$e->getMessage();
        }
        return $ret;
    }

    private function _eventGet ($event,$stripeSessionId=""){
        $ret=[];
        if ($event->type=="charge.succeeded"){

            $getEvent=true;
            if ($stripeSessionId!=""){
                $myPaySess=$this->_stripe->checkout->sessions->all(['limit' => 1,'payment_intent'=>$event->data->object->payment_intent]);
                $getEvent=($myPaySess->data[0]->id==$stripeSessionId);
            }

            if ($getEvent){
                $dec=substr($event->data->object->amount_captured, 0, -2).",".substr($event->data->object->amount_captured,-2);
                $litems=$this->_stripe->checkout->sessions->allLineItems($stripeSessionId,[])->data[0];
                $pdec=substr($litems->amount_total, 0, -2).",".substr($litems->amount_total,-2);
                $item=[
                    "id"=>"", $litems->price->id,
                    "description"=>$litems->description, 
                    "metadata"=>"", /*$pmdt,*/
                    "price"=>[
                        "amount"=>$pdec,
                        "id"=>$litems->price->id,
                        "metadata"=>""/*$pmmdt*/
                    ]
                ];
                $ret=[
                    "session"=>$myPaySess->data[0]->id,
                    "intent"=>$event->data->object->payment_intent,
                    "charge"=>$event->data->object->id,
                    "event"=>["date"=>date('Y/m/d', $event->created),"time"=>date('H:i:s', $event->created)],
                    "customer"=>["name"=>$myPaySess->data[0]->customer_details->name,"email"=>$myPaySess->data[0]->customer_details->email,"phone"=>$myPaySess->data[0]->customer_details->phone],
                    "email"=>$event->data->object->receipt_email,
                    "total"=>$dec,
                    "currency"=>$event->data->object->currency,
                    "paid"=>true,
                    "receipt"=>$event->data->object->receipt_url,
                    "metadata"=>"",
                    "reference"=>$myPaySess->data[0]->client_reference_id,
                    "product"=>$item,
                    "custom_fields"=>""
                ];
            }
        }
        return $ret;
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
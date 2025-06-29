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

 La classe COMMAND è l'esecutore materiale dei comandi
 interpretati ed eseguiti attraverso COMMAND e WORKER.
 In pratica QUI sono scritte le funzionalità per mandare
 e-mail, interpretare il testo, ecc.

 * -------------------------------------------------------*
 * CLASS-NAME:       FlussuCommand.class
 * CLASS PATH:       /Flussu/Flussuserver
 * FOR ALDUS BEAN:   Databroker.bean
 * -------------------------------------------------------*
 * CREATED DATE:     1.0 28.12:2020 - Aldus
 * VERSION REL.:     4.4.1.20250629
 * UPDATES DATE:     29.06:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * Possibilità di fare ATTACH all'email con una stringa
 * Arr$Attach["filename"] Arr$Attach["filetype"] Arr$Attach["filecontent"];
 * Handles MARKDOWN messsage format
 * Corrected MD handling for email body
 * -------------------------------------------------------*/

  /**
 * The Command class is responsible for executing various commands within the Flussu server.
 * 
 * This class interprets and executes commands through the COMMAND and WORKER components. It provides
 * functionalities for tasks such as sending emails, interpreting text, and other command-based operations.
 * 
 * Key responsibilities of the Command class include:
 * - Sending emails using the PHPMailer library.
 * - Interpreting and processing text commands.
 * - Integrating with external APIs such as JomobileSms and SmsFactor for SMS functionalities.
 * - Handling file uploads through the Fileuploader component.
 * - Managing general command execution tasks within the Flussu server environment.
 * 
 * The class is designed to be flexible and extendable, allowing for the addition of new command types and
 * functionalities as needed.
 * 
 */

namespace Flussu\Flussuserver;
use Flussu\General;
use Flussu\Documents\Fileuploader;
use PHPMailer\PHPMailer\PHPMailer;
use Flussu\Flussuserver\NC\HandlerNC;

class Command {
    private $_path;
    private $_config;
    public function __construct (){
        $this->_path=General::getDocRoot();
    }
    private function _sendEMail(Session $sess, $fromEmail, $fromName, $email, $subject, $tMessage, $hMessage, $replyTo, $attaches,$providerCode="")
    {
        if ($providerCode=="")
            $provider=config("services.email.default");
        else
            $provider=$providerCode;
        
        $provider="services.email.".$provider;
        $email_server   = config($provider.".smtp_host");
        $email_port     = config($provider.".smtp_port");
        $email_auth     = config($provider.".smtp_auth",0)!=0;
        $email_user     = config($provider.".smtp_user");
        $email_passwd   = config($provider.".smtp_pass");
        if (General::isCurtatoned($email_passwd))
            $email_passwd=General::montanara($email_passwd,999);

        $email_encrypt  = config($provider.".smtp_encrypt");
        $mail = new PHPMailer(true);
        General::log("Sending e-mail to:".$email. " - subj:".$subject);

        try {
            $mail->SMTPDebug = 0;
            $mail->isSMTP();     
            $mail->Host       = $email_server;                          //Set the SMTP server to send through
            $mail->SMTPAuth   = $email_auth;                            //Enable SMTP authentication
            $mail->Username   = $email_user;                            //SMTP username
            $mail->Password   = $email_passwd;                          //SMTP password
            if ($email_encrypt=="STARTTLS")
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            if ($email_encrypt=="SMTPS")
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

            $mail->Port       = $email_port;                            //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
            if (empty($fromEmail))
                $fromEmail=$mail->Username;
                                                    
            $mail->setFrom($fromEmail, $fromName);

            $mail->addAddress($email);
            if ($replyTo!="")
                $mail->addReplyTo($replyTo);

            $mail->CharSet = "UTF-8";
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $hMessage;
            if ($tMessage!="")
                $mail->AltBody = $tMessage;

            $chksum = crc32($mail->Host." ".$mail->From." ".$mail->FromName." ".$mail->Sender." ".$mail->Subject." ".$mail->Body." ".$mail->AltBody." ".json_encode($mail->getToAddresses())." ".json_encode($mail->getCCAddresses())." ".json_encode($mail->getBccAddresses())." ".json_encode($mail->getReplyToAddresses()));
            $canSendEmail=true;
            $arrSentmail=$sess->getVarValue("_inner_sentmail_doNotUse_");
            if (is_array($arrSentmail)){
                foreach ($arrSentmail as $key => $value){
                    if ($key==$chksum){
                        $now=new \DateTime("now");
                        $sent=new \DateTime($value);
                        $diff = $sent->diff($now);
                        if (($diff->days * 86400 + $diff->h * 3600 + $diff->i * 60 + $diff->s)<180)
                            $canSendEmail=false;
                    }
                }
            }

            if (!$sess->isWorkflowActive() || $sess->isExpired() || !$canSendEmail){
                $sess->recLog("Workflow inactive, expired or this exact e-mail message was already sent. If this is the last reason, please wait at least 3 minutes before resend it.");
                $result['success'] = true;
                $result['message'] = "Mail already sent.";
            } else {
                if (is_array($attaches) && count($attaches)>0){
                    $i=0;
                    $title="attach".($i++);
                    foreach ($attaches as $akey => $attach){
                        $checkFile=true;
                        if ($this->_isJson($attach)){
                            $jAttach=json_decode($attach,true);
                            if (isset($jAttach["filename"]) && isset($jAttach["filetype"]) && isset($jAttach["filecontent"])){
                                $result["attach"]="ATTACH TO: [".$jAttach["filename"]."] - DONE";
                                $attach=$jAttach;
                                $checkFile=false;
                                $mail->addStringAttachment(
                                    $jAttach["filecontent"],
                                    $jAttach["filename"],
                                    PHPMailer::ENCODING_BASE64,
                                    $jAttach["filetype"]);
                            }
                        }  

                        if ($checkFile){
                            $canAttach=true;
                            if (!static::fileIsAccessible($attach)) {
                                $canAttach=false;
                                if (!static::fileIsAccessible($_SERVER["DOCUMENT_ROOT"]."/".$attach)) {
                                    // inaccessibile, non fare niente
                                    $result["attach"]="CANNOT ACCESS TO: [".$attach."]";
                                } else {
                                    $result["attach"]="ATTACH TO: [".$attach."] - DONE";
                                    $attach=$_SERVER["DOCUMENT_ROOT"]."/".$attach;
                                    $canAttach=true;
                                }
                            }
                            if ($canAttach){
                                if (!is_numeric($akey)){
                                    $title=$akey;
                                }
                                $mail->AddAttachment($attach, $title);
                            }
                        }
                    }
                }
                $result = [];
                if ($mail->send()){
                    $result['success'] = true;
                    $result['message'] = "Mail sent.";
                    if (!is_array($arrSentmail))
                        $arrSentmail=[$chksum=>Date("Y-m-d H:i:s")];
                    else
                        $arrSentmail[$chksum] = Date("Y-m-d H:i:s");
                    //$arrSentmail=array_push($arrSentmail,[$chksum=>Date("Y-m-d H:i:s")]);
                    $sess->assignVars("_inner_sentmail_doNotUse_",$arrSentmail);
                } else {
                    $result['success'] = false;
                    $result['message'] = "Mailer error: {$mail->ErrorInfo}";
                }
            }
            General::log("Email send result:".json_encode($result,JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['message'] = "Failed exception ".$e->getMessage()."\r\nMailer error: {$mail->ErrorInfo}";
            General::log("Email send ERROR:".$e->getMessage()."\r\nMailer error: ".$mail->ErrorInfo);
        }
        return $result;
    }

    private function _isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
     }
    protected static function fileIsAccessible($path)
    {
        $readable = is_file($path);
        //If not a UNC path (expected to start with \\), check read permission, see #2069
        if (strpos($path, '\\\\') !== 0) {
            $readable = $readable && is_readable($path);
        }
        return  $readable;
    }

    public function localSendMail(Session $sess, $fromEmail, $fromName, $toEmail, $subject, $message, $replyTo, $blk_id, $attaches=null,$providerCode=null){
        $res="";
        try{
            $tmessage=Command::textSanitize($message);
            $A=quoted_printable_encode ($tmessage);
            $hmessage=Command::htmlSanitize($message);

            $start_header="<head><style>a:link{text-decoration:none;} a:visited{text-decoration:none;} a:hover{text-decoration:underline;} a:active{text-decoration:underline;}</style></head>";
            $end_footer="<div style='background:#e0e0e0;border-top: solid 1px black'><div style='padding:15px'><center><a href='https://www.flussu.com'>flussu service</a></center></div></div>";

            $htmlEmail="<html><body style='font-size:1.2em;padding=0;margin=0;'>".$start_header."<div style='padding:30px'>".$hmessage."</div><br>&nbsp;".$end_footer."</body></html>";
            $htmlEmail=str_replace("\r"," ",$htmlEmail);
            $htmlEmail=str_replace("\n"," ",$htmlEmail);
            $htmlEmail=str_replace("  "," ",$htmlEmail);
            $htmlEmail=str_replace("  "," ",$htmlEmail);

            return $this->_sendEMail($sess, $fromEmail, $fromName, $toEmail, $subject, $A, $htmlEmail, $replyTo, $attaches,$providerCode);
        }catch(\Exception $e){
            $res.="\r\nE02: "+$e->getMessage();
        }
        return $res;
    }
  
    private function textBy75Char($text){
        $text2="";
        do{
            try{
                if (strlen($text)>75){
                    $text2.=substr($text,0,75)."=\n";
                    $text=substr($text,75);
                }
                if (strlen($text)<76){
                    $text2.=$text;
                    $text="";
                }
            } catch (\Exception $e){
                error_log($e->getMessage());
                $text2.=$text;
                break;
            }
        } while (strlen($text)>75);
        return $text2;
    }

    public function execRemoteCommand($address,$jsonData=null){
        return $this->execRemoteCommandProtocol($address,"POST",$jsonData);
    }
    public function execRemoteCommandProtocol($address, $protocol="GET", $jsonData=null){
        $exit=true;
        $done=false;
        $fluV=$_ENV["major"].".". $_ENV["minor"];
        do {
            $ch = curl_init($address);
            curl_setopt( $ch, CURLOPT_USERAGENT, "Flussu/".$fluV." (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
            if (trim(strtoupper($protocol))=="POST" && (!is_null($jsonData) && !empty($jsonData)))
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_COOKIE, "flussu='server'");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Caller:flussu'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $respinfo = curl_getinfo($ch);
            curl_close($ch);
            if (!$done){
                $done=true;
                if ($respinfo['http_code'] == 301 || $respinfo['http_code'] == 302){
                    $address=$this->get_final_url($address,5,0);
                    $exit=false;
                } else 
                    return $result;
            } else
                return $result;
        } while(!$exit);
    }

    public function callURI($address,$postData=null){
        return $this->execRemoteCommand($address,$postData);
    }

    public function doZAP($uri,$jsonData){
        return $this->execRemoteCommand($uri,$jsonData);
    }

    function get_final_url( $url, $timeout,$times)
    {
        if ($times>10)
            return $url;
        $url = str_replace( "&amp;", "&", urldecode(trim($url)) );
        $cookie = tempnam ("/tmp", "CURLCOOKIE");
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $ch, CURLOPT_ENCODING, "" );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
        curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
        curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
        $content = curl_exec( $ch );
        $response = curl_getinfo( $ch );
        curl_close ( $ch );
        if ($response['http_code'] == 301 || $response['http_code'] == 302)
        {
            ini_set("user_agent", "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
            $headers = get_headers($response['url']);
            $location = "";
            foreach( $headers as $value )
            {
                if ( substr( strtolower($value), 0, 9 ) == "location:" )
                {
                    $location=trim(substr($value,9,strlen($value)));
                    return $this->get_final_url($location,$timeout,++$times);
                }
            }
        }
        if (preg_match("/window\.location\.replace\('(.*)'\)/i", $content, $value) ||
            preg_match("/window\.location\=\"(.*)\"/i", $content, $value)) {
                return $this->get_final_url ($value[1],$timeout,++$times);
        }
        else
            return $response['url'];
    }

    public static function htmlSanitize($message,$suppressSpecial=false) {
        if (!empty($message)){

            // MARKDOWN format
            $message=str_replace("&lbrace;MD&rcub;","{MD}",$message);
            $message=str_replace("&lbrace;&solMD&rcub;","{/MD}",$message);
            $message=str_replace("&lbrace;/MD&rcub;","{/MD}",$message);
            
            if (strpos($message,"{MD}")===0 && strpos($message,"{/MD}")===strlen($message)-5){
                $message=substr($message,4,strlen($message)-9);
                $Parsedown = new \Parsedown();
                $Parsedown->setSafeMode(true);
                return $Parsedown->text($message);
            }

            $message=str_replace("\r\n","<br>",htmlentities($message, ENT_HTML5|ENT_SUBSTITUTE|ENT_NOQUOTES , 'UTF-8'));
            $message=str_replace("&#039;","'",$message);
            $message=str_replace("&newline;","<br>",$message);
            $message=str_replace("&bsol;n","<br>",$message);
            $message=str_replace("\r","",$message);
            $message=str_replace("&bsol;r","",$message);
            $message=str_replace("&NewLine;","<br>",$message);
            $message=str_replace("&lbrace;\/","&lbrace;&sol;",$message);
            
            //$message=str_replace("&lbrace;br&bsol;","<br>",$message);

            $message=str_replace("&lbrace;pre&rcub;","<pre flussu-data-code class='flussu_code_snippet'>",str_replace("&lbrace;&sol;pre&rcub;","</pre>",$message));
            $message=str_replace("&lbrace;code&rcub;","<pre flussu-text-code class='flussu_code_txt'>",str_replace("&lbrace;&sol;code&rcub;","</pre>",$message));

            if (!$suppressSpecial){
                $message=str_replace("&lbrace;pl1&rcub;","<div style='padding-left:10px'>",str_replace("&lbrace;&sol;pl1&rcub;","</div>",$message));
                $message=str_replace("&lbrace;pl2&rcub;","<div style='padding-left:20px'>",str_replace("&lbrace;&sol;pl2&rcub;","</div>",$message));
                $message=str_replace("&lbrace;pl3&rcub;","<div style='padding-left:30px'>",str_replace("&lbrace;&sol;pl3&rcub;","</div>",$message));

                $message=str_replace("&lbrace;pr1&rcub;","<div style='padding-right:10px'>",str_replace("&lbrace;&sol;pr1&rcub;","</div>",$message));
                $message=str_replace("&lbrace;pr2&rcub;","<div style='padding-right:20px'>",str_replace("&lbrace;&sol;pr2&rcub;","</div>",$message));
                $message=str_replace("&lbrace;pr3&rcub;","<div style='padding-right:30px'>",str_replace("&lbrace;&sol;pr3&rcub;","</div>",$message));

                $message=str_replace("&lbrace;pbr&rcub;","<inpage>",str_replace("&lbrace;&sol;pbr&rcub;","</inpage>",$message));
            } else {
                $message=str_replace("&lbrace;pl1&rcub;","       ",str_replace("&lbrace;&sol;pl1&rcub;","<br>",$message));
                $message=str_replace("&lbrace;pl2&rcub;","              ",str_replace("&lbrace;&sol;pl2&rcub;","<br>",$message));
                $message=str_replace("&lbrace;pl3&rcub;","                     ",str_replace("&lbrace;&sol;pl3&rcub;","<br>",$message));

                $message=str_replace("&lbrace;pr1&rcub;","",str_replace("&lbrace;&sol;pr1&rcub;","       <br>",$message));
                $message=str_replace("&lbrace;pr2&rcub;","",str_replace("&lbrace;&sol;pr2&rcub;","              <br>",$message));
                $message=str_replace("&lbrace;pr3&rcub;","",str_replace("&lbrace;&sol;pr3&rcub;","                     <br>",$message));

                $message=str_replace("&lbrace;pbr&rcub;","",str_replace("&lbrace;&sol;pbr&rcub;","<br>.<br>---page-end---<br>.<br>",$message));
            }

            $message=str_replace("&lbrace;w&rcub;","<strong style='color:red'>",str_replace("&lbrace;&sol;w&rcub;","</strong>",$message));
            $message=str_replace("&lbrace;b&rcub;","<strong>",str_replace("&lbrace;&sol;b&rcub;","</strong>",$message));
            $message=str_replace("&lbrace;ar&rcub;","<div style='float:right;'>",str_replace("&lbrace;&sol;ar&rcub;","</div>",$message));
            $message=str_replace("&lbrace;d&rcub;","<table width='100%'><tr><td width='1%' style='align:center;width:1%;border:solid 1px silver;padding:4px;margin:4px'>",str_replace("&lbrace;&sol;d&rcub;","</td><td width='99%'>&nbsp;</td></tr></table>",$message));

            $message=str_replace("&lbrace;img&rcub;","<div style='padding:5px;margin:5px;'><img ",str_replace("&lbrace;&sol;img&rcub;"," ></div>",$message));

            if (!$suppressSpecial){
                $message=str_replace("&lbrace;t&rcub;","<div style='font-size:1.2em;font-weight:800' class=\"flussu-lbl-title flussu_title2\">",str_replace("&lbrace;&sol;t&rcub;","</div>",$message));
                $message=str_replace("&lbrace;h1&rcub;","<h1 class=\"flussu-lbl-title flussu_title1\">",str_replace("&lbrace;&sol;h1&rcub;","</h1>",$message));
                $message=str_replace("&lbrace;h&rcub;","<h2 class=\"flussu-lbl-title flussu_title2\">",str_replace("&lbrace;&sol;h&rcub;","</h2>",$message));
                $message=str_replace("&lbrace;h2&rcub;","<h2 class=\"flussu-lbl-title flussu_title2\">",str_replace("&lbrace;&sol;h2&rcub;","</h2>",$message));
                $message=str_replace("&lbrace;h3&rcub;","<h3 class=\"flussu-lbl-title flussu_title3\">",str_replace("&lbrace;&sol;h3&rcub;","</h3>",$message));
                $message=str_replace("&lbrace;h4&rcub;","<h4 class=\"flussu-lbl-title flussu_title4\">",str_replace("&lbrace;&sol;h4&rcub;","</h4>",$message));
            } else {
                $message=str_replace("&lbrace;t&rcub;","<strong>",str_replace("&lbrace;&sol;t&rcub;","</strong><br>",$message));
                $message=str_replace("&lbrace;h&rcub;","<strong>",str_replace("&lbrace;&sol;h&rcub;","</strong><br>",$message));
            }
            
            $message=str_replace("&lbrace;ul&rcub;","<ul>",str_replace("&lbrace;&sol;ul&rcub;","</ul>",$message));
            $message=str_replace("&lbrace;ol&rcub;","<ol>",str_replace("&lbrace;&sol;ol&rcub;","</ol>",$message));
            $message=str_replace("&lbrace;li&rcub;","<li>",str_replace("&lbrace;&sol;li&rcub;","</li>",$message));


            $message=str_replace("&lbrace;p&rcub;","<p>",str_replace("&lbrace;&sol;p&rcub;","</p>",$message));
            $message=str_replace("&lbrace;i&rcub;","<i>",str_replace("&lbrace;&sol;i&rcub;","</i>",$message));
            $message=str_replace("&lbrace;s&rcub;","<s>",str_replace("&lbrace;&sol;s&rcub;","</s>",$message));
            $message=str_replace("&lbrace;u&rcub;","<u>",str_replace("&lbrace;&sol;u&rcub;","</u>",$message));

            if (!$suppressSpecial){
                $message=str_replace("&lbrace;hr&rcub;","<hr style='border-size:1px;color:#909090'>",$message);
            }
            else {
                $message=str_replace(["&lbrace;hr&rcub;","<hr>","&lt;hr&gt;"],"<br>---------------------------------------------<br>",$message);
            }
            $message=str_replace("\'","&apos;",$message);
            $message=str_replace("'","&apos;",$message);
            $message=str_replace("\"","&OpenCurlyDoubleQuote;",$message);
            $message= Command::sanitizeBase($message);


            // LINK TEXT ----------------------------------------------------
            // is an encoded string?
            $asrc="&lbrace;a&rcub;";
            $pmsg=explode($asrc,$message);
            if (count($pmsg)>1){
                for ($i=1;$i<count($pmsg);$i++){
                    $asrc2="&lbrace;/a&rcub;";
                    $pmsg2=explode($asrc2,$pmsg[$i]);
                    $theLink=$pmsg2[0];
                    $theText=$theLink;
                    $theMessagePart=$asrc.$theLink.$asrc2;
                    // is there a text on the link?
                    $tsrc2="&lbrace;/text&rcub;";
                    $ttlnk=explode($tsrc2,$theLink);
                    if (count($ttlnk)>1){
                        // c'è un text
                        $theText=explode("&lbrace;text&rcub;",$ttlnk[0])[1];
                        $theLink=str_replace("&lbrace;text&rcub;".$theText."&lbrace;/text&rcub;","",$theLink);
                    } else {
                        // c'è un button?
                        $bsrc2="&lbrace;/button&rcub;";
                        $bblnk=explode($bsrc2,$theLink);
                        if (count($bblnk)>1){
                            $theText=explode("&lbrace;button&rcub;",$bblnk[0])[1];
                            $theLink=str_replace("&lbrace;button&rcub;".$theText."&lbrace;/button&rcub;","",$theLink);
                            $theText="<button class=\"btn btn-primary\" style=\"min-width:100px\"> ".$theText." </button>";
                        }
                    }
                    $message=str_replace($theMessagePart,"<a href=\"".$theLink."\" target=\"_blank\">".$theText."</a>",$message);
                }
            }

            // LINK BUTTON ----------------------------------------------------
            $lb_s=strpos($message,"&lbrace;LB&rcub;");
            $lb_e=strpos($message,"&lbrace;&sol;LB&rcub;");
            if (!$lb_e)
                $lb_e=strpos($message,"&lbrace;/LB&rcub;");
            $lnk="";
            $lnk2="";
            $but="";
            if ($lb_s!==false && $lb_e!==false){
                $lnk=substr($message,$lb_s+16,($lb_e-$lb_s)-16);
                $lnk2=str_replace("&sol;","/",$lnk);
                $lnk2=str_replace("&colon;",":",$lnk2);
                $lnk2=str_replace("&equals;","=",$lnk2);
                $lnk2=str_replace("&amp;","&",$lnk2);
                $lnk2=str_replace("&pound;","£",$lnk2);
                $lnk2=str_replace("&period;",".",$lnk2);
                $lnk2=str_replace("&quest;","?",$lnk2);
                $lnk2=str_replace("&lbrack;","[",$lnk2);
                $lnk2=str_replace("&rsqb;","]",$lnk2);
                $but="<a href=\"".$lnk2."\" target=\"_blank\"><button class=\"btn btn-primary\" style=\"min-width:100px\"> OK </button></a>";
            }
            // LINK BUTTON ----------------------------------------------------
            
            //$message=self::clickableUrls($message);
            /*
            $pattern  = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#';
            $message2= preg_replace_callback($pattern, function($matches){
                if (isset($matches[3]))
                    return "<a href='{$matches[0]}'>{$matches[1]}</a>";
                else 
                    return "<a href='{$matches[2]}'>{$matches[1]}</a>"; 
            }, $message);

            $message=$message2;
            */
            // LINK BUTTON ----------------------------------------------------
            $lb_s=strpos($message,"&lbrace;LB&rcub;");
            $lb_e=strpos($message,"&lbrace;/LB&rcub;");
            if (!empty($but) && $lb_s!==false && $lb_e!==false){
                $lnk=substr($message,$lb_s+16,($lb_e-$lb_s)+1);
                $message=str_replace("&lbrace;LB&rcub;".$lnk,$but,$message);
            }
            // LINK BUTTON ----------------------------------------------------
        }
        return $message; // str_replace("'","&apos;",$message);
    }




    public static function clickableUrls($html){
        return preg_replace(
            '%\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))%s',
            '<a href="$1">$1</a>',
            $html
        );
    }
    public static function sanitizeBase($message) {
        $message=str_replace("&lbrack;","[",$message);
        $message=str_replace("&semi;",";",$message);
        $message=str_replace("&plus;","+",$message);
        $message=str_replace("&ast;","*",$message);
        $message=str_replace("&num;","#",$message);
        $message=str_replace("&lowbar;","_",$message);
        $message=str_replace("&lsqb;","[",$message);
        $message=str_replace("&rsqb;","]",$message);
        $message=str_replace("&equals;","=",$message);
        $message=str_replace("&colon;",":",$message);
        $message=str_replace("&sol;","/",$message);
        $message=str_replace("&period;",".",$message);
        $message=str_replace("&commat;","@",$message);
        $message=str_replace("&comma;",",",$message);
        $message=str_replace("&bsol;","\\",$message);
        $message=str_replace("&excl;","!",$message);
        $message=str_replace("&apos;","'",$message);
        $message=str_replace("&lpar;","(",$message);
        $message=str_replace("&rpar;",")",$message);
        $message=str_replace("&quest;","?",$message);
        $message=str_replace("&quot;","\"",$message);
        $message=str_replace("&percnt;","%",$message);
        $message=str_replace("&OpenCurlyDoubleQuote;","\"",$message);
        $message=str_replace("&opencurlydoublequote;","\"",$message);
        $message=str_replace("&doublequote;","\"",$message);
        $message=str_replace("&DoubleQuote;","\"",$message);
        return preg_replace('/u([\da-fA-F]{4})/', '&#x\1;', $message);
    }

    public static function textSanitize($message) {
        $message=str_replace("&NewLine;","\r\n\r\n",$message);
        $message=str_replace("{w}"," [ ",str_replace("{/w}"," ] ",$message));
        $message=str_replace("{b}"," [ ",str_replace("{/b}"," ] ",$message));
        $message=str_replace("{d}"," [ ",str_replace("{/d}"," ] ",$message));
        $message=str_replace("{t}"," [ ",str_replace("{/t}"," ] ",$message));
        $message=str_replace("{h}"," [ ",str_replace("{/h}"," ] ",$message));
        $message=str_replace("{i}"," ",str_replace("{/i}"," ",$message));
        $message=str_replace("{s}"," ",str_replace("{/s}"," ",$message));
        $message=str_replace("{u}"," _ ",str_replace("{/u}"," _ ",$message));
        $message=str_replace("{LB}"," [ ",str_replace("{/LB}"," ] ",$message));

        return Command::sanitizeBase(html_entity_decode($message));
    }

    public static function strposArray($haystack, $needle, $offset=0) {
        if(!is_array($needle)) $needle = array($needle);
        foreach($needle as $query) {
            if(strrpos($haystack, $query, $offset) !== false) 
                return strrpos($haystack, $query, $offset);
        }
        return -1;
    }

    public static function canBeVariableName($varName){
        if (preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/',$varName) ){
            if (trim(\substr($varName,-1)!="[")){
                return true;
            }
        }
        return false;
    }

    function fileCheckExtract($wSess,$wWork,$wBid,$terms,$upFiles=null){
        //$terms=json_decode(\General::getGetOrPost("TRM"),true);
        $wid=$wSess->getWid();
        //c'è un file?
        $fobj=new \stdClass();
        $fobj->Error=null;
        $fobj->ErrMsg="";
        $bPath=$_SERVER['DOCUMENT_ROOT']."/../Uploads/";
        if ($upFiles!=null && is_array($upFiles) && count($upFiles)>0){
            $trmName=array_keys($upFiles)[0];
            $terms[$trmName]=$upFiles[$trmName]["name"];
        }
        if (isset($terms) && count($terms)>1){
            foreach ($terms as $key => $value){
                if (strpos($key,"_data")!==false || ($upFiles!=null && array_key_exists($key,$upFiles))){ 
                    // SI, c'è un file
                    // estraggo il nome del file
                    $hasFilename=false;
                    if ($upFiles!=null && $upFiles[$key]!=null){
                        $filename=$upFiles[$key]["name"];
                        $varname=$key;
                        $fobj->fileSize=$upFiles[$key]["size"];
                        if ($filename!="" && $fobj->fileSize>0){
                            $hasFilename=true;
                            $fobj->tmpName=$upFiles[$key]["tmp_name"];
                            $fobj->mimeType=$upFiles[$key]["type"];
                            $fobj->fileName=Fileuploader::sanitize_filename($filename);
                        }
                    } else {
                        $filename="noname.jpg";
                        $varname=substr($key,0,strpos($key,"_data"))."_name";
                        foreach ($terms as $sk => $sv){
                            if ($sk==$varname){
                                $filename=$sv;
                                $hasFilename=true;
                                break;
                            }
                        }
                        if ($hasFilename){
                            $fobj->fileName=Fileuploader::sanitize_filename($filename);
                            $etp=strpos($value,",");
                            $ptp=explode(";",substr($value,0,$etp));
                            $fobj->tmpName=$bPath."temp/".$fobj->fileName;
                            if (!substr($ptp[0],5))
                                $fobj->mimeType="image/jpeg";
                            else
                                $fobj->mimeType=substr($ptp[0],5);
                            $encode="";
                            if (!is_array($ptp) || count($ptp)<2){
                                if (!$fobj->mimeType=="text/plain")
                                    $encode="base64";
                            }
                            else
                                $encode=$ptp[1];
                            if ($etp==0) $etp=-1;
                            if ($fobj->mimeType=="text/plain"){
                                $ext = pathinfo($fobj->tmpName, PATHINFO_EXTENSION);
                                switch ($ext){
                                    case "jpg":
                                    case "jpeg":
                                    case "png":
                                    case "tiff":
                                    case "gif":
                                        $fobj->tmpName.=".decoded.txt";
                                        $fobj->fileName.=".decoded.txt";
                                    break;
                                }
                            }
                            switch ($encode){
                                case "base64":
                                    file_put_contents($fobj->tmpName,base64_decode(substr($value,$etp+1)));
                                    break;
                                default:    
                                    file_put_contents($fobj->tmpName,substr($value,$etp+1));
                            }
                            $fobj->fileSize=filesize ($fobj->tmpName);
                        }
                    }
                    if ($hasFilename){
                        // è tutto OK
                        $i=0;
                        do {
                            $whichServer=rand(1,3);
                            $wPath=$bPath."flussus_0".$whichServer;
                            if(is_dir($wPath))
                                break;
                        } while ($i++<30);
                        $wPath.="/".$wid;
                        if(!is_dir($wPath))
                            mkdir ($wPath, 0775);
                        $wPath.="/";

                        $wSess->recLog("Get file ".$fobj->fileName." (". $fobj->mimeType.")");

                        if ($fobj->mimeType=="text/plain"){
                            rename($fobj->tmpName, $wPath.$fobj->fileName);
                        } else {
                            $fup=new Fileuploader();
                            $ret=$fup->imageUpload($fobj,$wPath);
                        }
                        if (file_exists($fobj->tmpName))
                            unlink($fobj->tmpName);
                        
                        $w_id=HandlerNC::Wofoid2WID($wid);
                        $trmkey=explode("_data",$key)[0];
                        $FFD=$_ENV["filehost"];
                        if (!is_null($ret) && !is_null($ret->fileNameNew)){
                            $file_Uri=$FFD."/".str_replace("[w","",str_replace("]","",$w_id))."-".$ret->fileNameNew;
                            $thumb_Uri=$FFD."/".str_replace("[w","",str_replace("]","",$w_id))."-".$ret->fileNameNew2;
                            unset($terms[$trmkey."_data"]);
                            unset($terms[$trmkey."_name"]);
                            $wSess->removeVars($trmkey."_data");
                            $wSess->removeVars($trmkey."_name");

                            if (!is_null($fobj->fileName))
                                $wWork->pushValue($trmkey,$fobj->fileName ,$wBid);
                            $wWork->pushValue($trmkey."_uri", "https://".$file_Uri);
                            $wWork->pushValue($trmkey."_urithumb", "https://".$thumb_Uri);
                            $wWork->pushValue($trmkey."_filepath", $ret->fileDest);
                            $wWork->pushValue($trmkey."_imgthumb", $ret->fileDest2);
                            $wWork->pushValue($trmkey."_filename", $ret->fileNameNew);

                            $wSess->recLog("Added new file ".$ret->fileNameNew." to ".$w_id." uri=".$file_Uri);
                            $fobj->fileUri=$file_Uri;
                        }
                    } else {
                        // Malformed request
                        $fobj->Error="E05";
                        $fobj->ErrMsg="malformed request";
                    }
                }
            }
        } 
        $fobj->Terms=$terms;
        return $fobj;
    }

    public function sendSMS($senderName,$phoneNum,$message,$provider=null){
        if (is_null($provider))
            $provider = config("services.sms_provider.default");
        return $this->sendProviderSMS($provider,$senderName,$phoneNum,$message);
    }
    public function sendProviderSMS($provider,$senderName,$phoneNum,$message){
            $providerClass = 'Flussu\\Controllers\\' . $provider;
            if (!class_exists($providerClass)) {
                throw new \Exception("NoProvider", "Provider [$provider] not found or not defined");
            }
            $prov = new $providerClass();
            if (!($this->startsWith($phoneNum,"00")) && !$this->startsWith($phoneNum,"+")){
                $phoneNum="+39".$phoneNum;
            }
            $result=$prov->sendSms($senderName,$phoneNum,$message);
            return $result;
    }
    function startsWith($haystack, $needle) {
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
    }
    public function chkCodFisc($innerParams){
        $CF=trim(strtoupper($innerParams[0]));
        $res = new \stdClass();
        $tpFnc=false;
        if (count($innerParams)>1){
            $res->isGood=[$innerParams[1],false];
            $res->sex=[$innerParams[2],"U"];
            $res->bDate=[$innerParams[3],"1899-12-31"];
        } else {
            $tpFnc=true;
            $res->codFisc=$CF;
            $res->isGood=false;
            $res->sex="";
            $res->bDate="";
        }
        if (strlen($CF)==16){
            $alfabetoMesi = array('A','B','C','D','E','H','L','M','P','R','S','T');
            $alfabeto     = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
            // Caratteri posizione dispari
            $PD['0'] = 1;  $PD['B'] = 0;  $PD['M'] = 18; $PD['X'] = 25;
            $PD['1'] = 0;  $PD['C'] = 5;  $PD['N'] = 20; $PD['Y'] = 24;
            $PD['2'] = 5;  $PD['D'] = 7;  $PD['O'] = 11; $PD['Z'] = 23;
            $PD['3'] = 7;  $PD['E'] = 9;  $PD['P'] = 3;  $PD['4'] = 9; 
            $PD['F'] = 13; $PD['Q'] = 6;  $PD['5'] = 13; $PD['G'] = 15; 
            $PD['R'] = 8;  $PD['6'] = 15; $PD['H'] = 17; $PD['S'] = 12;
            $PD['7'] = 17; $PD['I'] = 19; $PD['T'] = 14; $PD['8'] = 19; 
            $PD['J'] = 21; $PD['U'] = 16; $PD['9'] = 21; $PD['K'] = 2; 
            $PD['V'] = 10; $PD['A'] = 1;  $PD['L'] = 4;  $PD['W'] = 22;
            try {
                // CODICE DI CONTROLLO ((caratteri posiz pari + posiz dispari) / 26 -> Carattere di controllo)
                $arrCOD = str_split(substr($CF,0,strlen($CF)-1));
                //$index = count($arrCOD);
                /* posizione pari */
                $somma1 = 0;
                $somma2 = 0;
                for($i = 0; $i < 15; $i++){
                    if(($i+1)%2==0)
                    {
                        if(!in_array($arrCOD[$i], $alfabeto)) $somma1 += $arrCOD[$i];
                        else
                        {
                            $n = array_search($arrCOD[$i], $alfabeto);
                            $somma1 += $n;
                        }
                    } else {
                        $somma2 += $PD["$arrCOD[$i]"];
                    }
                }
                /* posizione dispari */
                $somma = $somma1+$somma2;
                $codControllo = ($somma % 26);
                $codControllo = $alfabeto[$codControllo];
                
                if (substr($CF,-1)==$codControllo){
                    if(!$tpFnc)
                        $res->isGood[1]=true;
                    else
                        $res->isGood=true;
                }

                $SEX="M";
                $AA=substr($CF,6,2);
                
                $aa=date('Y');
                $aa=substr($aa,2,2);
                if (intval($AA)<intval($aa)+3)
                    $AA="20".$AA;
                else
                    $AA="19".$AA;
                $MM=substr($CF,8,1);
                $GG=substr($CF,9,2);
                if (intval($GG)>40){
                    $GG=intval($GG)-40;
                    $SEX="F";
                }
                $MM=array_search($MM,$alfabetoMesi,true)+1;
                if(!$tpFnc)
                    $res->sex[1]=$SEX;
                else
                    $res->sex=$SEX;
                $time = strtotime("$MM/$GG/$AA");
                if(!$tpFnc)
                    $res->bDate[1]= date('Y-m-d',$time);
                else
                    $res->bDate= date('Y-m-d',$time);
            } catch (\Exception $e) {
                // do nothing
                //return false;
            }
        }
        return $res;
    }
    public function chkPIva($innerParams){
        $PIva=trim(strtoupper($innerParams[0]));
        $res = new \stdClass();
        $tpFnc=false;
        if (count($innerParams)>1){
            $res->isGood=[$innerParams[1],false];
        } else {
            $tpFnc=true;
            $res->PIva=$PIva;
            $res->isGood=false;
        }

        $res->reason="";

		if( strlen($PIva) == 0 )
            $res->reason="Empty.";
		else if( strlen($PIva) != 11 )
            $res->reason="Invalid length.";
		if( preg_match("/^[0-9]{11}\$/sD", $PIva) !== 1 )
            $res->reason="Invalid characters.";

        if (!empty($res->reason))
            return $res;
        
        $res->isGood=true;
        $s = 0;
		for( $i = 0; $i < 11; $i++ ){
			$n = ord($PIva[$i]) - ord('0');
			if( ($i & 1) == 1 ){
				$n *= 2;
				if( $n > 9 )
					$n -= 9;
			}
			$s += $n;
		}
		if( $s % 10 != 0 ){
            $res->reason="Invalid checksum.";
            $res->isGood=false;
        }
		
        return $res;

/*
        $pic=new \Api\PartitaIvaController;
        $res=$pic->PICheck(substr($PIva,0,2),substr($PIva,2));
        if(!$tpFnc)
            $res->isGood[1]=true;
        else
            $res->isGood=true;
        return $res;
*/
    }


    public function php_error_test($code){

        return "";

        /**
         * Check the syntax of some PHP code.
         * @param string $code PHP code to check.
         * @return boolean|array If false, then check was successful, otherwise an array(message,line) of errors is returned.
         */
        if(!defined("CR"))
            define("CR","\r");
        if(!defined("LF"))
            define("LF","\n") ;
        if(!defined("CRLF"))
            define("CRLF","\r\n") ;
        $braces=0;
        $inString=0;
        foreach (token_get_all('<?php ' . $code) as $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                    case T_START_HEREDOC: ++$inString; break;
                    case T_END_HEREDOC:   --$inString; break;
                }
            } else if ($inString & 1) {
                switch ($token) {
                    case '`': case '\'':
                    case '"': --$inString; break;
                }
            } else {
                switch ($token) {
                    case '`': case '\'':
                    case '"': ++$inString; break;
                    case '{': ++$braces; break;
                    case '}':
                        if ($inString) {
                            --$inString;
                        } else {
                            --$braces;
                            if ($braces < 0) break 2;
                        }
                        break;
                }
            }
        }
        $inString = @ini_set('log_errors', false);
        $token = @ini_set('display_errors', true);
        ob_start();
        $code = substr($code, strlen('<?php '));
        $braces || $code = "if(0){{$code}\n}";
        if (eval($code) === false) {
            if ($braces) {
                $braces = PHP_INT_MAX;
            } else {
                false !== strpos($code,CR) && $code = strtr(str_replace(CRLF,LF,$code),CR,LF);
                $braces = substr_count($code,LF);
            }
            $code = ob_get_clean();
            $code = strip_tags($code);
            if (preg_match("'syntax error, (.+) in .+ on line (\d+)$'s", $code, $code)) {
                $code[2] = (int) $code[2];
                $code = $code[2] <= $braces
                    ? array($code[1], $code[2])
                    : array('unexpected $end' . substr($code[1], 14), $braces);
            } else $code = array('syntax error', 0);
        } else {
            ob_end_clean();
            $code = false;
        }
        @ini_set('display_errors', $token);
        @ini_set('log_errors', $inString);
        return $code;
    }


    public function php_nikic_parse($theCode){
        $parser = (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::PREFER_PHP7);
        try {
            $ast = $parser->parse($theCode);
        } catch (\Exception $e) {
            echo "Parse error: {$e->getMessage()}\n";
            return "ERROR:".$e->getMessage();
        }

        $dumper = new \PhpParser\NodeDumper;
        return $dumper->dump($ast) . "\n";
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
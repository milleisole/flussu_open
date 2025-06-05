<?php
 
 
// init external code
use Flussu\Flussuserver\Environment;
//$wofoEnv=new Environment($this->_WofoS);
$Flussu = new \stdClass; 
$Flussu->Wid="[w222afeaf278f11aa]";
$Flussu->wid="143";
$Flussu->WfAuid="1d8b6736-1ec6-60e4-f398-525562e3bf94";
$Flussu->Sid="de036842-fc98-89e8-8544-999809cd5dce";
$Flussu->Bid="d10e6841-f928-8ab6-6920-625601d5979b";
$Flussu->BlockTitle="initChatText";
$Flussu->Referer=urldecode("https://srvdev4.flu.lt/flucli/client.html");


$_MemSeStat=json_decode('{"workflowActive":1,"workflowId":143,"title":"Chatbot Test","supplangs":"IT,EN,FR","deflang":"IT","sessid":"de036842fc9889e88544999809cd5dce","wid":143,"wfauid":"1d8b6736-1ec6-60e4-f398-525562e3bf94","lang":"IT","blockid":"d10e6841-f928-8ab6-6920-625601d5979b","endblock":null,"enddate":"2025-06-05 23:44:41","userid":0,"err":0,"usrerr":0,"exterr":0,"Wwid":"[w222afeaf278f11aa]","StarterWid":"[w222afeaf278f11aa]"}');
$isApp=false;
$isAndroidApp=false;
$isIosApp=false;
$appVersion="";
$appDeviceId="";
$isZapier=false;
$isForm=true;
$isMobile=false;
$isTelegram=false;
$isWhatsapp=false;
$isMessenger=false;
$isWeb=1;
$WID="[w222afeaf278f11aa]";
$_StWID="[w222afeaf278f11aa]";
$_StW_ID=143;
$reminder_to="";
$_dateNow="Thu 05 Jun, 2025 - 23:46:41";
$procTitle="This is the Neil Title";
$testo="ciao";
$risposta="";
$platname="";
//$dummy=$wofoEnv->setDataJson('[]');
$_FD0508="https://srvdev4.flu.lt/flucli/client.html";
$_AL2905="";
$lastLabel="";
$AR_platform=json_decode('["ChatGPT","Grok","Gemini","DeepSeek","Claude(3)"]',true);
$platform=0;
$plat=0;


if (isset($_outerCallerUri)){
    if ((is_null($_outerCallerUri) || empty($_outerCallerUri)) && $_scriptCallerUri!="")
        $Flussu->Referer=urldecode($_scriptCallerUri);
    elseif (!is_null($_outerCallerUri) && !empty($_outerCallerUri))
        $Flussu->Referer=urldecode($_outerCallerUri);
}
try {
    $initChatText=<<<TXT
You are a standard AI assistant are designed to assist users answering in the same language the users write the questions. 
Your responses should be clear, concise, and helpful. If you do not know the answer to a question, you should politely inform the user that you do not have that information.     
If anyone ask something about 'Flussu' or 'Mille Isole' or 'Aldo Prinzi', here are some info you can use to reply:
Flussu is a platform for managing and automating workflows, tasks, and communications, written by Aldo Prinzi and produced by Mille Isole SRL, a software house company based in italy.
TXT;
$wofoEnv->initAiAgent($initChatText);

    return $wofoEnv->endScript();
} catch (\Throwable $e){
    $wofoEnv->log("INTERNAL ERROR! Wid:".$Flussu->Wid." - Bid:".$Flussu->Bid." (".$Flussu->BlockTitle.") - Sid:".$Flussu->Sid."\n - - ".json_encode($e->getMessage()));
    return "Internal exec exception: [1] - ".var_export($e,true);
} catch (\ParseError $p){
    $wofoEnv->log("INTERNAL PARSER ERROR! Wid:".$Flussu->Wid." - Bid:".$Flussu->Bid." (".$Flussu->BlockTitle.") - Sid:".$Flussu->Sid."\n - - ".json_encode($p->getMessage()));
    return "Internal exec exception: [2] - ".var_export($p,true);
};
 
 if (!function_exists('pizza')) {
function pizza($qty){
  $ret="";
  for ($i=0;$i<$qty;$i++)
    $ret.="Pizza!,";
  return $ret;
}
}
 
if (!function_exists('panella')) {
function panella($qty){
  $ret="";
  for ($i=0;$i<$qty;$i++)
    $ret.="Panella!,";
  return $ret;
}
}
 
 
 
             
 
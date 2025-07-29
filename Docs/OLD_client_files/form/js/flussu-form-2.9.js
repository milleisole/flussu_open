/*************************************************************************
Flussu 2.9.9
Script to handle Flussu's typeform-display (v2.9-last - rel 1104 - 2024)
Copyright (C) 2021-2024 Mille Isole SRL - Palermo (Italy)
*************************************************************************/

// determine display platform checking if mobile device
window.mobileCheck = function() {
    let check = false;
    (function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4))) check = true;})(navigator.userAgent||navigator.vendor||window.opera);
    return check;
};
var isMobile = window.mobileCheck();
// get url parameters
$.urlParam = function(name) {
    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
    if (results == null)
        return null;
    return decodeURI(results[1]) || 0;
}
$.sureUrlParam = function(name) {
    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
    if (results == null)
        return "";
    return decodeURI(results[1]) || 0;
}

$(document).ready(function() {
    if (!isMobile) {
        $("#flussu-form").html(`
        <script>
            var link = document.createElement("link");
            link.type = "text/css"; link.rel = "stylesheet";
            link.href = flussuSrv+"v2.9/flussu-form-"+fCssVer+".css";
            document.body.appendChild(link);
        </script>
            <script src="`+flussuSrv+`client/node_modules/html5-qrcode/html5-qrcode.min.js"></script> 
            <script src="`+flussuSrv+`client/assets/script/jquery.blockUI.js"></script>
        <div class="row">
            <div id="flussuHeader" class="col-10 offset-1" style="padding-top:5px;margin-top:5px">
                <h3 id="flussuTitle" class="d-flex justify-content-center flussu-title">(loading ...)</h1>
                <div id="flussuHeaderBtm" style="display:none" class="flussu-head"></div>
                <div id="flussuChat" class="row chat_window">
                    <div id="flussu-qr-code" style="display:none" class="col flussu-wrap">
                        <p><iframe borders="none" width="100%" height="390px" src="`+flussuSrv+`client/qrcode/index.htm"></iframe></p>
                    </div>
                    <div id="flussu-environment" style="display:none" class="col flussu-wrap"></div>
                    <div id="flussu-startarea" style="display:none;padding-top:100px" class="col d-flex justify-content-center"></div>
                </div>
            </div>
        </div>
        <div id="throbber" style="display:none;"><img width="70px" src="`+flussuSrv+`client/assets/img/busy.gif" /></div>
        
        `);
    } else {
        $("#flussu-form").html(`
        <script>
            var link = document.createElement("link");
            link.type = "text/css"; link.rel = "stylesheet";
            link.href = flussuSrv+"v2.9/flussu-form-mobile-"+fCssVer+".css";
            document.body.appendChild(link);
        </script>
        <script src="`+flussuSrv+`client/assets/script/jquery.blockUI.js"></script>
        <div class="row">
            <div class="col-12" style="padding-top:15px;margin-top:5px">
                <h3 id="flussuTitle" class="d-flex justify-content-center flussu-title">(loading ...)</h1>
                <div class="chat_window">
                    <div id="flussuHeaderBtm" style="display:none" class="flussu-head"></div>
                    <div id="flussu-environment" style="display:none" class="flussu-wrap"></div>
                    <div id="flussu-startarea" style="display:none;padding-top:200px" class="container d-flex h-100 justify-content-center"></div>
                </div>
            </div>
        </div>
        <div id="throbber" style="display:none;"><img width="100px" src="`+flussuSrv+`client/assets/img/busy.gif" /></div>
        `);
    }
    $(document.body).append(`
    <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
        <symbol id="check-circle-fill" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></symbol>
        <symbol id="info-fill" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></symbol>
        <symbol id="exclamation-triangle-fill" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></symbol>
    </svg>
    <div class="modal modal-dialog-scrollable modal-xl"><div class="modal-dialog"><div class="modal-content"><div class="modal-header">
        <h5 id="flussu-modal-title" class="modal-title">Modal title</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div id="flussu-modal-body" class="modal-body">
        <p><iframe borders="none" width="100%" height="390px" src="`+flussuSrv+`client/qrcode/index.htm"></iframe></p>
    </div></div></div></div>`);
    getWorkflowInfo();
    checkCounters();
    if (flussuSid) {
	    FlussuIn('');
    }
});

$("body").keypress(function(event) {
    if (event.target.tagName!="TEXTAREA"){
        if (event.which == 13) {
            event.preventDefault();
            if ($("#flubut0"))
                $("#flubut0").click();
        }
    }
});
$('body').on('click','img',function(){
    $('#flussu-overlay')
      .css('background-image', 'url("' + this.src + '")')
      .addClass('open')
      .one('click', function() { $(this).removeClass('open'); });
});


var _getFileName = "";
var _startData=null;
var chkScroll;
var btnNum = 0;
var butElemId = "flussu-startarea";
var displayTit=false;
var divOpen = "";
var elmId = 0;
var elmNum = 0;
var eventSource;
var fCssVer="2.8";
let flussuLang = "IT";
let flussuSrv = location.protocol + "//" + window.location.host + "/"
let flussuApi = flussuSrv + "api/";
var flussuId = ""; 
var forcedBid = $.urlParam('BID');
var flussuBlock = "";
var flussuSid = $.urlParam('SID');
var formElemId = "flussu-environment";
var htmlTxt = "";
var isInit = true;
var lang = $.urlParam('lang');
var flussuSetLang= $.urlParam('lang');
var terms = "";
var TMR;
var txtInputFocusDone = false;
var titElemId = "flussuTitle";
var xFlussuSid = "";
var theCallerUri=window.location.href;
var outerFrameUri=decodeURIComponent($.urlParam('OFU'));
var regOfu=false;

if (lang)
    flussuLang = lang;

function FlussuIn(LNG) {
    if (LNG){
        lng = LNG.toLowerCase();
        if (lng!=""){
            flussuLang=lng;
            initWorkflow();
        }
    }
}

var doSound=false;
function setFlussuSound(sndValue) {
    doSound=sndValue=="Y" || sndValue=="yes";
}

function setFlussuEndpoint(flussuEndpoint) {
    flussuSrv = flussuEndpoint;
    flussuApi = flussuSrv + "api/v2.0/";
}

function setFlussuCssVersion(cssver) {
    fCssVer=cssver;
}

function setFlussuId(theWID,theTIT,Arbitrary) {
    displayTit=!(theTIT==="0" || theTIT==="false" || theTIT==="none");
    flussuId = theWID;
    if (!Arbitrary)
        Arbitrary = new Object();
    Arbitrary["$isForm"]=true;
    Arbitrary["$_FD0508"]=theCallerUri;
    Arbitrary["$_AL2905"]=outerFrameUri;

    _addUrlvarsToArbitrary(Arbitrary);

    _startData=JSON.stringify(Arbitrary);
}

function _addUrlvarsToArbitrary(Arbitrary){
    // check for return arbitrary values
    var refr=outerFrameUri;
    if (refr==null || refr=="" || refr=="null")
        refr=theCallerUri;

    var vars=refr.split("&");
    for (var i=1;i<vars.length;i++){
        tvar=decodeURI(vars[i]).split("=");
        if (tvar[0].substr(0,1)=="£"){
            Arbitrary[tvar[0].replace("£","$")]=tvar[1];
        }
        if (tvar[0]=="SID"){
            setFlussuSid(tvar[1]);
        }
        if (tvar[0]=="BID"){
            forcedBid=tvar[1];
        }
    }
}

function setFlussuSid(theSID) {
    // Notifier
    if (xFlussuSid!=theSID){
        xFlussuSid=theSID;
    }
}

function handleNotify(notifyData){
    doBlock=false;
    $.unblockUI();
    TMR=setTimeout(function(){handleNotifyTimed(notifyData);}, 500);
}

function handleNotifyTimed(notifyData){
    clearTimeout(TMR);
    arr=JSON.parse(notifyData);
    for (var item in arr) {
        if (item=="SID"){
            // Do Nothing
        } else if(item!=null) {
            var tp=arr[item][0]
            var nm=arr[item][1]
            var vl=arr[item][2]
            if (typeof vl === 'object')
                vl=JSON.stringify(vl);
            switch (tp){
                case 1:
                    alert(item+"\r\n"+vl);
                    break;
                case 2:
                    addCounter(nm,vl)
                    break;
                case 3:
                    updateCounter(nm,vl)
                    break;
                case 4:
                    addChatRow(vl);
                    break;
                case 5:
                    callBack(nm,vl);
                    break;
                default:
                    tp="[0]Notify "+nm+"=";
            }
        }
    }
}

function callBack(wid,bid){
    alert ("callback:\r\nWID="+wid+"\r\nBID="+bid);
    flussuId=wid;
    flussuBlock=bid;
    postParam();
}

function addChatRow(message){
    addPart='<div class="flussu-par" id="fd_NAR"><span class="flussu-lbl" id="flulblnull">'+message+'</span></div>';
    document.getElementById("flussu-environment").innerHTML=document.getElementById("flussu-environment").innerHTML+addPart;
}

function checkCounters(){
    val=sessionStorage.getItem("counter");
    if (val){
        val=val.split("|");
        addCounter(val[0], val[1],true)
    }
    val=sessionStorage.getItem("counter_val");
    if (val){
        val=val.split("|");
        updateCounter(val[0], val[1],true)
    }
}

function addCounter(name, val, donotrec){
    if (donotrec!==true)
        sessionStorage.setItem("counter",name+"|"+val);
    val=JSON.parse(val);
    elm=document.getElementById("flussuHeaderBtm");
    addPart="<div class='flussu-counter' id='flussu_cnt_"+name+"' vMin='"+val.min+"' vMax='"+val.max+"'><table width='100%' border=0 class='flussu-counter-table'><tr><td width='5%'><span class='flussu-counter-title'>"+val.desc.replace(/ /g, '&nbsp;')+"</span></td>";
    var i=val.min;
    prc=(100/(((val.max)-(val.min))+1)).toFixed(1);
    for (;i<=val.max;i++)
        addPart+="<td class='flussu-counter-value' id='flussu_cv"+i+"' align='center' width='"+prc+"%'>&nbsp;</td>";    
    addPart+="</tr></table></div>";
    elm.innerHTML=addPart+elm.innerHTML;
    elm.style.display="block";
}

function updateCounter(name, val, donotrec){
    if (donotrec!==true)
        sessionStorage.setItem("counter_val",name+"|"+val);
    tbl=document.getElementById("flussu_cnt_"+name);
    elm=document.getElementById("flussu_cv"+val);
    if (elm){
        elm.className = "flussu-counter-value-full";
        if (tbl){
            vMin=parseInt(tbl.getAttribute("vMin"));
            if (!vMin)
                vMin=0;
            vMax=parseInt(tbl.getAttribute("vMax"));
            if (val-1>=vMin && (document.getElementById("flussu_cv"+(val-1))))
                document.getElementById("flussu_cv"+(val-1)).innerText="";
            for (i=vMin;i<=val;i++){
                if (document.getElementById("flussu_cv"+i))
                    document.getElementById("flussu_cv"+i).className="flussu-counter-value-full";
            }
            if (val<vMax){
                prc=100/(((vMax)-(vMin))+1);
                perc=(prc*val).toFixed(1);
            }
            else{
                perc="100";
                sessionStorage.removeItem("counter");
            }
            document.getElementById("flussu_cv"+(val)).innerText=perc+"%";
        }
    }
}

function initWorkflow(theWID, titleElementId, buttonsElementId) {
    if (buttonsElementId)
        butElemId = buttonsElementId;
    if (titleElementId)
        titElemId = titleElementId;
    var CK = getCookie("flussuSid");
    var dontstart = false;
    if (CK) {
        var elms = CK.split(",");
        if (flussuId == null && theWID != null)
            flussuId =theWID;
        if (flussuId != null && flussuId.length > 0 && flussuId != elms[0]) {
            eraseCookie("flussuSid");
            getWorkflowInfo();
        } else {
            pre_post(elms);
            dontstart = true;
        }
    } else {
        $("#" + butElemId).removeClass("d-flex").addClass("d-none").hide();
        $("#" + formElemId).show();
        if (_startData != null) {
            terms = '{"arbitrary":' + _startData + '}';
            _startData = null;
            regOfu=true;
        }
        postParam();
        dontstart = true;
    }
    if (!dontstart) {
        $("#" + butElemId).addClass("d-flex").removeClass("d-none").show();
        $("#" + formElemId).hide();
        getWorkflowInfo();
    }
}

function addLangButton(item, index) {
    var text = "DUNNO";
    var item2 = item;
    switch (item) {
        case "IT":
            text = "inizia";
            break;
        case "EN":
            text = "start";
            item2 = "gb";
            break;
        case "FR":
            text = "partir";
            break;
        case "DE":
            text = "beginnt";
            break;
        case "ES":
            text = "iniciar";
            break;
    }
    btn = "<button class='btn flussu-lang-btn' onclick=\"FlussuIn('" + item + "')\" id=\"flussu-btn-lang-" + item.toLowerCase() + "\" style=\"background:rgb(210, 210, 210)\">" + text + "<br><img style=\"padding-top:8px;\" src=\"https://flagcdn.com/28x21/" + item2.toLowerCase() + ".png\"></button>";
    $("#" + butElemId).append(btn);
}

function getWorkflowInfo() {
    if (!displayTit){
        $("#" + titElemId).removeClass("flussu-title");
        $("#" + titElemId).removeClass("d-flex").addClass("d-none").hide();
    }

    //flussuId = flussuId;
    //setFlussuId(flussuId,null,'none');

    var CK = getCookie("flussuSid");
    var dontstart = false;
    if (CK) {
        var elms = CK.split(",");
        if (flussuId != null && flussuId.length > 0 && flussuId != elms[0])
            eraseCookie("flussuSid");
        else {
            pre_post(elms);
            return;
        }
    }

    pdta = { WID: flussuId, CMD: 'info' };
    $.post(flussuApi + 'flussueng.php', pdta)
        .done(function(data) {
            $("#" + titElemId).text(data.tit);
            var wst = $("#WebSiteTitle");
            if (wst && isMobile)
                $("#WebSiteTitle").text("Flussu mobile webform");
            else if (wst)
                $("#WebSiteTitle").text("Flussu webform");

            if (!flussuSetLang || flussuSetLang==""){
                Btns = data.langs.split(',');
                $("#" + butElemId).html("");
                $("#" + butElemId).append("<div style='text-align-center'>");
                Btns.forEach(addLangButton);
                $("#" + butElemId).removeClass("d-none").addClass("d-flex").show();
            } else {
                setTimeout(() => {
                    FlussuIn(flussuSetLang);
                }, 1500)
            }

        })
        .fail(function(xhr, status, error) {
              eraseCookie("flussuSid");
              location.reload();
              //alert("Internet error:"+status+"\r\n"+error);
    }),'json';
}

function focusInp(Elm) {
    $(this).find("input").focus();
}

function pre_post(elms) {
    $("#" + butElemId).removeClass("d-flex").addClass("d-none").hide();
    $("#" + formElemId).show();
    flussuId = elms[0];
    flussuSid = elms[1];
    setFlussuSid(flussuSid);
    if (forcedBid!=""){
        flussuBlock = forcedBid;
        forcedBid="";
    }
    else
        flussuBlock = elms[2];
    flussuLang = elms[3];
    terms = "R:2";
    Arbitrary = new Object();
    _addUrlvarsToArbitrary(Arbitrary);
    if (!regOfu){
        if(outerFrameUri=="" || outerFrameUri=="null")
            outerFrameUri=decodeURIComponent($.urlParam('OFU'));
        if(outerFrameUri!="" && outerFrameUri!="null"){
            Arbitrary["$_AL2905"]=outerFrameUri;
            regOfu=true;
        }
    }
    if (JSON.stringify(Arbitrary).length>5)
        terms+=(",{\"arbitrary\":"+JSON.stringify(Arbitrary)+"}");

    $("#mychat").show();
    postParam();
}

const delay = ms => new Promise(res => setTimeout(res, ms));
var doBlock=false;
const showDelayedBlock = async () => {
    // delayed screen (waiting GIF) wait 750ms before shows up
    await delay(750);
    if (flussuSid!="" && $.blockUI && doBlock)
        $.blockUI({ message: $('#throbber'), css: { backgroundColor:'#ffffff00', border:'0px solid #ffffff00'} });
};

function postParam() {
    // BLOCCA DISPLAY
    if (flussuSid!="" && $.blockUI){
        doBlock=true;
        showDelayedBlock();
    }

    if (chkScroll)
        clearInterval(chkScroll);
    pdta = { WID: flussuId, SID: flussuSid, BID: flussuBlock, LNG: flussuLang, TRM: terms };

    $.post(flussuApi + 'flussueng.php', pdta)
      .done(function(data){ 
        doBlock=false;
        //if ($.unblockUI) $.unblockUI();
        elab(data)
        })
      .fail(function(xhr, status, error) {
        doBlock=false;
        eraseCookie("flussuSid");
        if ($.unblockUI) $.unblockUI();
        //alert("Internet error:"+status+"\r\n"+error);
    }),'json';

    elmNum = 0;
    btnNum = 0;
}

function elab(obj) {
    // SBLOCCA DISPLAY
    doBlock=false;
    if ($.unblockUI)
        $.unblockUI();

    if (obj.hasOwnProperty("error")){
        eraseCookie("flussuSid");
        if (obj.error.indexOf("E89")>0){
            flussuSid="";
            flussuBid="";
            initWorkflow();
            // The session is expired
        } else {
            //alert ("ERROR:\r\n"+obj.error);
        }
    } else {
        if (obj.sid == null) {
            eraseCookie("flussuSid");
            initWorkflow(flussuId, titElemId, butElemId)
            return;
        }

        var CK = getCookie("flussuSid");
        if (CK != null)
            eraseCookie("flussuSid");
        setCookie("flussuSid", flussuId + "," + obj.sid + "," + obj.bid + "," + flussuLang, 30);

        if ($("#" + formElemId).children().html()) {
            $("#" + formElemId).children().fadeOut(350).promise().done(function() {
                $("#" + formElemId).children().remove();
                elab2(obj);
            });
        } else
            elab2(obj);
    }
}
/*
$('.hotel_photo_select').fadeOut(500)
    .promise().done(function() {
        alert('Got this far!');
    });
*/

const regexForStripHTML = /<.*>.*?/ig
function elab2(obj) {
    elmId = 0;
    divOpen = "";
    startHtmlTxt = "";
    endHtmlTxt = "";
    htmlTxt = "<div id='fluPart0' class='flussu-wrap align-middle flussuOPTflussu' style='display:none'><div id='b5LiveAlertPlaceholder' class='d-flex p-2 justify-content-center'></div>";

    if (!eventSource && obj.sid!=null){
        eventSource = new EventSource(flussuSrv+"notifier?SID="+obj.sid);
        eventSource.onmessage = function(event) {
            handleNotify(event.data);
        };
    }

    $("#" + butElemId).removeClass("d-flex").addClass("d-none").hide();

    terms = "";
    flussuSid = obj.sid;
    setFlussuSid(flussuSid);
    flussuBlock = obj.bid;
    elmNum = 0;
    isGUIreq=false;
    strmValGui="";
    for (var Key in obj.elms) {
        mVal = obj.elms[Key];
        fem = "";
        Css = "";
        fe = Key.split("$");
        if (Array.isArray(fe) && fe.length > 1) {
            key = fe[0];
            fem = fe[1];
        } else
            key = Key;
        if (Array.isArray(mVal)) {
            try{strmVal = mVal[0].replace(/["]+/g, '');} catch ($e){}
            Css = mVal[1];
        } else
            strmVal = mVal.replace(/["]+/g, '');

        //strmVal=strmVal.length<2?"":strmVal[1]==='"'?JSON.parse(strmVal):JSON.parse('"'+strmVal+'"');
        switch (key) {
            case "SESS":
                eraseCookie("flussuSid");
                text = "We are sorry, this session has expired.";
                switch (flussuLang) {
                    case "IT":
                        text = "Spiacenti, questa sessione � scaduta.";
                        break;
                    case "FR":
                        text = "Nous sommes d�sol�s, cette session a expir�.";
                        break;
                    case "DE":
                        text = "Es tut uns leid, diese Sitzung ist abgelaufen.";
                        break;
                    case "ES":
                        text = "Lo sentimos, esta sesi�n ha expirado.";
                        break;
                }
                alert(text);
                location.reload(); 
                break;
            case "END":
                // This process has ended, you can delete the cookie
                eraseCookie("flussuSid");
                break;
            case "TITLE":
                // This is the workflow title
                $("#" + titElemId).text(strmVal);
                break;
                // in case of restart/refresh, the engine sends you back the text that was entered by the client
            case "B":
            case "R":
                add_shw_Redo(key, null, strmVal, Css);
                break;
            case "S":
                add_shw_LblRedo(key, null, strmVal, Css);
                break;
            case "L":
                strmVal2=strmVal.replaceAll("&amp;","&");
                add_shw_Label(key, null, strmVal2, Css);
                break;
            case "M":
                add_shw_Media(key, fem, strmVal, Css);
                break;
            case "A":
                add_shw_Anchor(key, null, strmVal, Css);
                break;
            case "ITB":
                add_inp_Button(key, fem, strmVal, Css, isGUIreq);
                break;
            case "ITM":
                add_inp_Media(key, fem, strmVal, Css);
                break;
            case "ITS":
                arrmVal = JSON.parse(mVal[0]);
                if (mVal.length>2 && mVal[2].substr(0,6)=="[val]:")
                    defVal=JSON.parse(mVal[2].substring(6));
                add_inp_Select(key, fem, arrmVal, Css, defVal);
                break;
            case "ITT":
                strmVal2 = strmVal.replaceAll(regexForStripHTML, '');
                defVal="";
                if (mVal.length>2 && mVal[2].substr(0,6)=="[val]:")
                    defVal=mVal[2].substr(6);
                add_inp_Text(key, fem, strmVal2, Css, defVal);
                break;
            case "GUI":
                add_shw_Label(key, null, "<h3>The app needs your personal data.<br>If you agree, press OK or Consent.</h3>", Css);
                add_gui_Req(flussuBlock,fem);
                isGUIreq=true;
                GuiStrmVal=strmVal;
                GuiFlussuBlock=flussuBlock;
                break;
            }
        elmNum++;
    }
    if (isGUIreq){
        setTimeout(function () {
            flussu_execGetUserInfo(GuiFlussuBlock,GuiStrmVal,'{\"name\":\"theUser\",\"email\":\"theuser@email.com\",\"tel\":\"+396543434231\",\"consent\":\"'+new Date().toISOString()+'\"}');
        }, 500)
    }
    if (htmlTxt != "") {
        close_div(null);
        htmlTxt += "</div>";
        hasPart = false;
        if (endHtmlTxt) {
            startHtmlTxt = "<div class='row align-items-center w-100'>" + htmlTxt.replace("flussuOPTflussu", "col-xl-8");
            endHtmlTxt = endHtmlTxt.replace("flussuOPTflussu", "col-xl-4 mx-auto flussu-back");
            htmlTxt = startHtmlTxt + endHtmlTxt + "</div>";
            hasPart = true;
        } else if (startHtmlTxt) {
            startHtmlTxt = "<div class='row align-items-center w-100'>" + startHtmlTxt.replace("flussuOPTflussu", "col-xl-4 flussu-back");
            endHtmlTxt = htmlTxt.replace("flussuOPTflussu", "col-xl-8 mx-auto");
            htmlTxt = startHtmlTxt + endHtmlTxt + "</div>";
            hasPart = true;
        }
        htmlTxt.replace("flussuOPTflussu", "");
        document.getElementById(formElemId).insertAdjacentHTML('beforeend', htmlTxt+"<div id='flussu-overlay'></div>");
        if (hasPart)
            $("#fluPart1").fadeIn(490);
        $("#fluPart0").fadeIn(500);
    }
    if (replVals.length>0){
        for (i=0;i<replVals.length;i++){
            find="rplv_"+replVals[i].id;
            $(".rplv_"+replVals[i].id).val(replVals[i].value)
        }
        replVals=[];
    }
}

function flussu_execGetUserInfo(GuiFlussuBlock,GuiStrmVal,userData){
    if (GuiStrmVal=="")
    GuiStrmVal="Posso mandare i suoi dati personali all'applicazione?";
    if (confirm(GuiStrmVal))
        document.getElementById("flussu_requestedUserData").value=userData;
    execFlussuForm(GuiFlussuBlock,"$ex!0","Ok");
}


function close_div(which) {
    el = document.getElementById(formElemId);
    if (divOpen != which) {
        if (divOpen != "") {
            htmlTxt += "</div>";
            //el.insertAdjacentHTML('beforeend', htmlTxt);
        }
        if (which) {
            htmlTxt += "<div class='flussu-par' id='fd_" + elmId + "'>";
            divOpen = which;
        }
    }
    elmId++;
}

function add_shw_Redo(eType, inputId, eText, eCss) {
    close_div("r");
    wCss=typeof(eCss)=="string"?eCss:eCss.class;
    htmlTxt += "<button class='btn btn-secondary flussu-btn-redo " + wCss + "' id='flubut" + inputId + "'>" + eText + "</button>";
}

function add_shw_LblRedo(eType, inputId, eText, eCss) {
    close_div("r");
    wCss=typeof(eCss)=="string"?eCss:eCss.class;
    htmlTxt += "<button class='btn btn-primary flussu-btn " + wCss + "' id='flubut" + inputId + "'>" + eText + "</button>";
}

function add_shw_Label(eType, inputId, eText, eCss) {
    close_div("l");
    wCss=typeof(eCss)=="string"?eCss:eCss.class;
    htmlTxt += "<span class='flussu-lbl " + wCss + "' id='flulbl" + inputId + "'>" + eText + "</span>";
    textToSpeech(". ! . ! . ! "+eText);
}

function add_shw_Media(eType, inputId, eText, eCss) {
    wCss=eCss.class;
    if (eText.trim()!=""){
        close_div("m");
        if (eCss.display_info.subtype=="background"){
            $("#flussuChat").css("background",eText);
        } else {
            var img ="";
            var css="flussu-img";
            switch (eCss.display_info.type){
                case "image":
                    if (!isMobile)
                        img = "<img alt='click=Zoom' title='click=Zoom' class='flussu-pic mx-auto d-block "+wCss+"' src='" + eText + "'/>";
                    else
                        img = "<img alt='click=Zoom' title='click=Zoom' class='flussu-med "+wCss+"' src='" + eText + "'/>";
                break;
                case "image":
                    if (!isMobile)
                        img = "<img alt='click=Zoom' title='click=Zoom' class='flussu-pic mx-auto d-block "+wCss+"' src='" + eText + "'/>";
                    else
                        img = "<img alt='click=Zoom' title='click=Zoom' class='flussu-med "+wCss+"' src='" + eText + "'/>";
                break;
                default:
                    // è youtube?
                    var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=|\?v=)([^#\&\?]*).*/;
                    var match = eText.match(regExp);
                    if (match && match[2].length == 11) {
                        // Do anything for being valid
                        // if need to change the url to embed url then use below line
                        img="<div class='flussu-vid' id='player'></div>";
                        //"<iframe class='flussu-vid' id='ytplayer' type='text/html' width='640' height='360' src='"+eText+"?autoplay=0' frameborder='0'></iframe>";
                        AddYoutube(eText);
                    }
                    else {
                        // Do anything for not being valid
                    }
            }


            if (!isMobile) {
                if (eCss.display_info.subtype=="align-Lx" || eCss.display_info.subtype=="align-Rx"){
                    mHtmlTxt = "<div id='fluPart1' class='jumbotron col-xs-12 col-sm-12 col-md-5 " + wCss + " flussuOPTflussu' id='flumed" + inputId + "' style='display:none'><div class='flussu-med-h m-3'>" + img + "</div></div>";
                    htmlTxt = htmlTxt.replace("'fluPart0' class='", "'fluPart0' class='col-xs-12 col-sm-12 col-md-7 ");
                    if (eCss.display_info.subtype=="align-Lx"){
                        startHtmlTxt = mHtmlTxt.replace("'fluPart1'", "'fluPart0'");
                        htmlTxt = htmlTxt.replace("'fluPart0'", "'fluPart1'");
                    }
                    else 
                        endHtmlTxt = mHtmlTxt;
                } else {
                    htmlTxt += "<div class='flussu-img-wrap'>"+img.replace('flussu-pic ',css+' ')+"</div>";    
                }
            } else
            htmlTxt += "<!-- IS MOBILE --> "+img;
        }
    }
}

function add_shw_Anchor(eType, inputId, eText, eCss) {
    close_div("a");

    if (typeof(eCss)=="string"){
        wCss=eCss;
        eCss=new Object();
        eCss.display_info=new Object();
        eCss.display_info.subtype="";
    } else {
        wCss=eCss.class;
    }
    
    desc=eText;
    parts=eText.split("!|!");
    if (Array.isArray(parts) && parts.length>1){
        eText=parts[1];
        desc=parts[0];
    }

    if (eText.trim().substring(0, 4) != "http")
        eText = "http://" + eText.trim();

    res = "<a target='_blank' href='" + eText.replace(/'/g, "\\'") + "' class='flussu-link ";
    if (eCss.display_info.subtype == "button") {
        res += "btn btn-primary'>" + desc;
    } else
        res += wCss + "'>" + desc;
    textToSpeech(". ! . ! . ! "+desc);
    htmlTxt += res + "</a>";
}
var btnSpeechAdded=false;
function add_inp_Button(eType, inputId, eText, eCss, isGUIreq) {
    if (!btnSpeechAdded){
        btnSpeechAdded=true;
        textToSpeech(". ! (i pulsanti di scelta disponibili sono:)");
    }
    close_div("B");
    wCss=typeof(eCss)=="string"?eCss:eCss.class;

    if (isMobile && wCss!="img") {
        var sp = 0;
        while (true) {
            if (eText.length > sp + 50) {
                sp = eText.indexOf(" ", sp + 40);
                eText = eText.substr(0, sp) + "\n" + eText.substr(sp);
            } else
                break;
        }
    }
    ischk=false;
    if (wCss.substr(0,3)=="chk")
        ischk=true;
    if (wCss=="img"){
	// DO SOMETHING?

    } else {
        bText = eText.replace("\n", "</div>");
        if (bText != eText) {
            eText = eText.replace("\n", " ");
            eText = eText.replace("\r", "");
            bText = bText.replace("\r", "");
            if (!ischk)
                bText = "<div class='flussu-btn-row1'>" + bText;
        }
    }
    textToSpeech(". ! "+eText);
    if (wCss=="chk"){
        htmlTxt += "<label class='container'><input type='checkbox' id='flubut" + btnNum + "'>&nbsp;"+bText+"<span class='checkmark'></span></label>";    
    } else {
	 if(wCss=="img"){
		bText=eText;
	       ss=eText.split("/");
		eText=ss[ss.length-1];
	 }
        onClk = "onclick='execFlussuForm(\"" + flussuBlock + "\",\"$ex!" + inputId + "\",\"" + eText.replace(/'/g, "-") + "\")'";
	 if(wCss=="img"){
            if (bText.trim()!="")
        	    htmlTxt += "<div class='btn btn-primary flussu-btn-img'><img src='"+bText+"' "+ onClk + " id='flubut" + btnNum + "'></div>";    
	 } else {
		if (wCss=="btn-chk")
            		htmlTxt += "<div><label><button class='btn btn-primary flussu-btn-chk " + wCss + "' " + onClk + " id='flubut" + btnNum + "'></button><span class='flussu-lbl-chk'>" + bText + "</span></label></div>";
	       else
       		htmlTxt += "<button class='btn btn-primary flussu-btn " + wCss + "' " + onClk + " id='flubut" + btnNum + "'>" + bText + "</button>";
	 }
        btnNum++;
    }
}

function add_inp_Media(eType, inputId, eText, eCss) {
    close_div("M");
    wCss=typeof(eCss)=="string"?eCss:eCss.class;

    if (wCss.toLowerCase().trim()=="#qr-code" || wCss.toLowerCase().trim()=="#qrcode"){
        htmlTxt += "<button onclick=\"$('#flussu-qr-code').show()\">GET QR-CODE</button>";
    } else {
        accept="";
        switch(eCss.display_info.subtype){
            case "file-document":
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,application/vnd.openxmlformats-officedocument.wordprocessingml.document";
                break;
            case "file-image":
            case "read-code":
            case "take-photo":
                accept="image/*";
                break;
        }
        htmlTxt += "<input id=\"img_" + inputId + "\" name=\"$" + inputId + "\" type='file' accept=\""+accept+"\" class=\"flussu-inpFile " + wCss + " I" + flussuBlock + "\" onchange=\"getPhoto(this,'img" + flussuBlock + "',true);\" ></input>";
        htmlTxt += "<img class='M" + flussuBlock +" flussu-med flussu-med-get "+wCss+"' id='img" + flussuBlock + "' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAEElEQVQIHQEFAPr/AAAAAAAABQABuokQigAAAABJRU5ErkJggg'></div>";
    }
}

function colselcheck(item){
    if (item.getElementsByTagName('input')[0].type=="radio")
        item.getElementsByTagName('input')[0].checked=true;
    else
        item.getElementsByTagName('input')[0].checked=!item.getElementsByTagName('input')[0].checked;
}

function add_inp_Select(eType, inputId, arrElems, eCss, defVal) {
    close_div("S");

    subtype="";
    if (typeof(eCss)=="string"){
        wCss=eCss;
    } else {
        wCss=eCss.class;
        subtype=eCss.display_info.subtype;
    }
    if (arrElems){
        arrElems=_chooseSel(arrElems,defVal);
        switch(subtype){
            case "exclusive":
            case "multiple":
                if((eCss && typeof(eCss)!="string")?eCss.display_info.mandatory:false)
                    htmlTxt += "<div class='flxMandSel "+ flussuBlock+"-group'>";
                i=0;d=false;
                htmlTxt += "<div class='row flussu-selrcrow'>";
                textToSpeech(". ! (Le possibili scelte sono:)");
                arrElems.forEach(function(elem) {
                    eText=elem[3].replaceAll("$apos;","'").replaceAll("$quot;","'").replaceAll("\"","'");
                    if (subtype=="multiple"){
                        htmlTxt += "<div class='col flussu-selrccol_c' onclick='colselcheck(this)'>";
                        htmlTxt += "<input class='flussu-selcheck " + flussuBlock + "' "+(elem[2]?"checked":"")+" onclick='event.stopPropagation(); colselcheck(this)' type='checkbox' name='$" + inputId +"' value='@OPT[\"" + elem[1] + "\",\"" +eText + "\"]'/><label class='flussu-selrclab'>" + eText + "</label>";
                    } else {
                        htmlTxt += "<div class='col flussu-selrccol_r' onclick='colselcheck(this)'>";
                        htmlTxt += "<input class='flussu-selradio " + flussuBlock + "' type='radio' "+(elem[2]?"checked":"")+" name='$" + inputId +"' value='@OPT[\"" + elem[1] + "\",\"" + eText + "\"]'/><label class='flussu-selrclab'>" + eText + "</label>";
                    }
                    htmlTxt += "</div>";
                    if ((i++)%3==2){
                        htmlTxt += "</div><div class='row flussu-selrcrow'>";
                    }
                    textToSpeech(". ! "+eText);
                });
                htmlTxt += "</div>";
                if((eCss && typeof(eCss)!="string")?eCss.display_info.mandatory:false)
                    htmlTxt += "</div>";
                break;
            default:
                mand=" ";
                if((eCss && typeof(eCss)!="string")?eCss.display_info.mandatory:false)
                    mand=" flxMand ";
                htmlTxt += "<select class='" + wCss + mand + flussuBlock + " flussu-select' id='select-input' name='$" + inputId + "'>";
                arrElems.forEach(function(elem) {
                    eText=elem[3].replaceAll("$apos;","'").replaceAll("$quot;","'").replaceAll("\"","'");
                    htmlTxt += "<option "+(elem[2]?"selected":"")+" value='@OPT[\"" + elem[1] + "\",\"" + eText + "\"]'>" + eText + "</option>";
                });
                htmlTxt += "</select>";
        }
    } else
        htmlTxt += "<div style='background:red;color:white;padding:30px;font-size:2em' class='text-center'>NO ARRAY!</div>";
    
}

function _chooseSel(selElems,defVal){
    // routine that receive input datas, defaults and data already
    // choosen (if any) and prepare the right array for optional checks
    arrElems=[];
    Object.keys(selElems).forEach(function(opK) {
        desc=selElems[opK];
        aKey=opK.split(",");
        sel=false;
        if (defVal.length<1)
            sel=(aKey[1]==1);
        else {
            for(i=0;i<defVal.length;i++){
                if (aKey[0]==defVal[i]) {
                    sel=true;
                    break;
                }
            }

        }
        arrElems.push([opK,aKey[0],sel,desc]);
    });
    return arrElems;
}

replVals=[];
function add_inp_Text(eType, inputId, eText, eCss, defVal) {
    close_div("T");
    wCss=typeof(eCss)=="string"?eCss:eCss.class;
    isArea=(wCss.trim() == "textarea");
    if (eCss && typeof(eCss)!="string")
        isArea=(eCss.display_info.subtype=="textarea");
    mand="";
    onmand="";
    if(
        (eCss && typeof(eCss)!="string")?eCss.display_info.mandatory:false
    )
    {
        mand=" flxMand ";
        onmand=" onfocus='checkUnmand(this);' "
    }

    if (isArea)
        htmlTxt += "<textarea placeholder='" + eText + "' id='txtInput' rows=4 cols=80 class='inpArea flussu-inpArea " + flussuBlock + mand + "' "+onmand+" name='$" + inputId + "'>"+defVal+"</textarea>";
    else{
        const elm={"id":replVals.length+1};
        elm.value=defVal
        replVals.push(elm);
        htmlTxt += "<input placeholder='" + eText + "' id=\"txtInput\" class='flussu-inpText rplv_"+elm.id+" " + wCss + " " + flussuBlock + mand + "' "+onmand+" type='text' name='$" + inputId + "' value=''>";
    }
}
function add_gui_Req(flBlk,inputId) {
    close_div(null);
    htmlTxt += "\r\n<input id='flussu_requestedUserData' type='hidden' name='$" + inputId + "' class='" + flBlk + "' value='{\"consent\":\"none\"}'>\r\n";
}

/*-----------------------------------
   MANDATORY FIELDS 
      -start-
  ----------------------------------- */
function checkUnmand(elm){
    elm.classList.remove("flussu-emptyctrl-flash");
    elm.classList.remove("flussu-emptyctrl");
}
function unflashMand(relms){
    for (i=0;i<relms.length;i++){
        relms[i].classList.remove("flussu-emptyctrl-flash");
        relms[i].classList.add("flussu-emptyctrl");
    }
}
function checkMand(){
    ret=true;
    elms=document.getElementsByClassName("flxMand");
    relms=[];
    for (i=0;i<elms.length;i++){
        if (elms[i].value.trim()==""){
            ret=false;
            elms[i].classList.add("flussu-emptyctrl-flash");
            relms.push(elms[i]);
        }
    }
    return checkSelMand(ret,relms);
}
function checkSelMand(ret,relms){
    elms=document.getElementsByClassName("flxMandSel");
    for (i=0;i<elms.length;i++){
        // extract the uuid
        cls="";
        clss=elms[0].className.replace("flxMandSel","").replace("  "," ").split(" ");
        clss.forEach(function(elm) {
            if(elm.indexOf("-group")>34){
                cls=elm.replace("-group","");
                return;
            }
        }); 
        tot=0;
        opts=document.getElementsByClassName(cls);
        for (j=0;j<opts.length;j++){
            if(opts[j].checked) tot++;
        }
        if (tot==0){
            ret=false;
            elms[i].classList.add("flussu-emptyctrl-flash");
            relms.push(elms[i]);
        }
    }
    if (!ret)
        mandAlert(relms);
    return ret;
}
function mandAlert(relms){
    setTimeout(function () {
        msg="Please fill all mandatory fields.\r\nThank you.";
        switch(flussuLang){
            case "IT":
                msg="Per piacere fornire i dati obbligatori.\r\nGrazie."
            break;
            case "FR":
                msg="Veuillez saisir une valeur dans les espaces obligatoires s'il vous pla t.\r\nMerci."
            break;
            case "DE":
                msg="Bitte f llen Sie alle obligatorischen Felder aus.\r\nVielen Dank."
            break;
            case "SP":
                msg="Por favor, complete todos los campos obligatorios.\r\nGracias."
            break;
        }
        textToSpeech(". ! "+msg);
        b5Alert(msg,"warning");
    }, 300)
    setTimeout(function () {
        unflashMand(relms);
    }, 5000)
}

const b5Alert = (message, type) => {
    const wrapper = document.createElement('div')
    wrapper.innerHTML = [
        `<div class="alert alert-${type} alert-dismissible" style="box-shadow: 2px 2px 4px #000000" role="alert">
            <div><svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Alert:"><use xlink:href="#exclamation-triangle-fill"/></svg>&nbsp;${message}</div>
            <button type="button" onclick="this.parentNode.parentNode.remove();" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`
    ].join('');
    b5a=document.getElementById('b5LiveAlertPlaceholder');
    b5a.append(wrapper);
}

/*-----------------------------------
   MANDATORY FIELDS 
      -end-
  ----------------------------------- */

function execFlussuForm(blockId, btnName, btnVal) {
    textToSpeech("{CANC}");
    if (checkMand()){
        btnVal = unescape(btnVal);
        aLink = "";
        if (btnVal.startsWith("http://") || btnVal.startsWith("https://")) {
            aLink = btnVal;
            btnVal = "Link";
            window.open(aLink, '_blank');
        }
        textToSpeech(btnVal);

        if (chkScroll)
            clearInterval(chkScroll);

        appendTxt = "";
        appendBtn = "";
        var trmsv = {};
        trmsv[btnName] = btnVal;

        // FILE ------------------------------------------------------------
        if ($(".M" + blockId)[0]) {
            // - ctrls aspect - - - - - - - - - - - - - - - - 
            $("#P" + blockId).hide();
            $(".I" + blockId).hide();
            $(".M" + blockId).width("100");
            $(".M" + blockId).parent().parent().css("border", "none")
                // - img aspect - - - - - - - - - - - - - - - - - 
            if ($(".M" + blockId).height() > 100)
                $(".M" + blockId).height("100");
            IRW = ( $(".M" + blockId).parent().width() /2 ) + ( $(".M" + blockId).parent().position().left *2 );
            $(".M" + blockId).css({ position: 'relative', left:IRW+"px" });
            // - data - - - - - - - - - - - - - - - - - - - - 
            appendTxt = _getFileName;
            nam = $(".I" + blockId)[0].name;
            trmsv[nam + "_name"] = _getFileName;
            trmsv[nam + "_data"] = $(".M" + blockId)[0].src;
        }
        // -----------------------------------------------------------------

        blockId = "." + blockId;
        checkend=false;
        $(blockId).each(
            function() {
                switch (this.nodeName) {
                    case "SELECT":
                        trmsv[this.name] = this.selectedOptions[0].value;
                        appendTxt += this.selectedOptions[0].text;
                        break;
                    case "INPUT":
                    case "TEXTAREA":
                        if (this.value != "") {
                            if (appendTxt != "")
                                appendTxt += ", ";
                            appendTxt += this.value;
                        }
                        if (this.type=="checkbox" || this.type=="radio"){
                            //checkend=true;
                            if(this.checked){
                                vl=this.value.replaceAll("$apos;","'");
                                if (!trmsv[this.name]) {
                                    trmsv[this.name]="";
                                } else {
                                    vl=","+vl.replace("@OPT[","");
                                    trmsv[this.name]=trmsv[this.name].substr(0,trmsv[this.name].length-1);
                                }
                                trmsv[this.name] += vl;
                            }
                        }
                        else
                            trmsv[this.name] = this.value;
                        break;
                    case "BUTTON":
                        this.style["display"] = "none";
                        appendBtn = btnVal;
                        break;
                }
            }
        );
        if (checkend){
            $(Object.entries(trmsv)).each(
                function() {
                    if (this[1].length>6 && this[1].substring(0,4)=="@OPT" && this[1].slice(-2)=="},"){
                        trmsv[this[0]]="@OPT"+this[1].substring(4,this[1].length-1);
                    }
                }
            );
        }
        dt = "";
        $(".flussuDiv").append("<div>" + appendTxt + " <u>[" + appendBtn + "]</u></div>");
        terms = JSON.stringify(trmsv);
        postParam();
    }
}

function getPhoto(inp, prew, adj) {
    if (inp.files && inp.files[0]) {
        _getFileName = inp.files[0].name;
        var reader = new FileReader();
        reader.onload = function(e) {
            var pe = $("#" + prew);
            pe.css("display", "flex");
            pe.css("width", "30%");
            pe.css("max-height", "40%");
            pe.css("object-fit", "contain");
            pe.attr("src", e.target.result);
            //pe[0].parentElement.style.display = "flex";
        };
        reader.readAsDataURL(inp.files[0]);
    }
}

function newUuid() {
    var dt = new Date().getTime();
    var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        var r = (dt + Math.random() * 16) % 16 | 0;
        dt = Math.floor(dt / 16);
        return (c == 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
    return uuid;
}

function AddYoutube(eText){
    // 2. This code loads the IFrame Player API code asynchronously.
    videoId="M7lc1UVf-VE";
    var regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
    var match = eText.match(regExp);
    videoId=(match&&match[7].length==11)? match[7] : false;

    var tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    var firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
}

  // 3. This function creates an <iframe> (and YouTube player)
  //    after the API code downloads.
var player;
var videoId="";
function onYouTubeIframeAPIReady() {
    player = new YT.Player('player', {
        height: '360',
        width: '620',
        videoId: videoId,
        events: {
        'onReady': onPlayerReady,
        'onStateChange': onPlayerStateChange
        }
    });
}

  // 4. The API will call this function when the video player is ready.
function onPlayerReady(event) {
    event.target.playVideo();
}

  // 5. The API calls this function when the player's state changes.
  //    The function indicates that when playing a video (state=1),
  //    the player should play for six seconds and then stop.
var done = false;
function onPlayerStateChange(event) {
    if (event.data == YT.PlayerState.PLAYING && !done) {
      setTimeout(stopVideo, 6000);
      done = true;
    }
}
function stopVideo() {
    player.stopVideo();
}

/*
 Client cookie setting: let the client restart
 without lost the data of the previous session.
*/
function setCookie(name, value, minutes) {
    var expires = "";
    if (minutes) {
        var date = new Date();
        date.setTime(date.getTime() + (minutes * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "2=" + (value || "") + expires + "; path=" + window.location.href;
}

function getCookie(name) {
    var nameEQ = name + "2=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

function eraseCookie(name) {
    document.cookie = name + '2=; Path="+flussuSrv+"; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}

const synth = window.speechSynthesis;

const textToSpeech = (string) => {
    if (!doSound)
        return;

    if (string=="{CANC}"){
        synth.cancel();
        btnSpeechAdded=false;
    } else {
        var div = document.createElement("div");
        string = string.replace(/<br>/g, '. ! . ! . ! ');
        div.innerHTML = string;
        string=div.innerText;
        div=null;
        let voice = new SpeechSynthesisUtterance(string);
        voice.text = string;
        voice.lang = "us-EN";
        switch (lng){
            case "it":
            case "IT":
                voice.lang = "it-IT";
        }
        voice.volume = 1;
        voice.rate = 1;
        voice.pitch = 1; // Can be 0, 1, or 2
        synth.speak(voice);
    }
}
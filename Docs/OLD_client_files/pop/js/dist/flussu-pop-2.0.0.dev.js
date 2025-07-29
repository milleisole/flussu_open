"use strict";

/************************************************************************
Flussu v2.0 - pop-up show v2.0.1
Copyright (C) 2021-2022 Mille Isole SRL - Palermo (Italy)
- scripting sample v2.0.1 - 06 April 2022 - REL
- scripting sample v2.0.0R - 17 January 2022 - REL
- scripting sample v2.0.0B - 11 December 2021 - Beta - For Flussu v2.0
************************************************************************/
// get url parameters
$.urlParam = function (name) {
  var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
  if (results == null) return null;
  return decodeURI(results[1]) || 0;
};

var callLang = $.urlParam('lang');
var is_chat = false;
var flussuLang = "IT";
if (callLang) flussuLang = callLang;
var flussuServer = "https://www.flussu.com/";
var flussuApi = flussuServer + "api/v2.0/";
var lang = flussuLang;
var flussuId = $.urlParam('WID');
var flussuBlock = $.urlParam('BID');
var flussuSid = "";
var isInit = true;
var terms = "";
var scrolled = false;
var lastScrollTop = 0;
var staScrivendo = "sta scrivendo";
var txtInputFocusDone = false;
var chkScroll;
var chatEngine;
var mustRestartAll = false;
var chatTitle = "Chat Title HERE";
var startMsg = "&#128578;";
var popup;
var chatBtn;
var submitTxt;
var submitSel;
var submitFil;
var chatArea;
var inputElm;
var badge;
var nullArea;
var msecSleep = 0;
var isThere = false; // emoji picker and adder to the chat space

var picker;
var trigger; //var langOptions = ["IT"];

function setFlussuPopUpStart(FlussuId, ChatTitle, StartMsg) {
  setFlussuId(FlussuId);
  chatTitle = ChatTitle;
  startMsg = StartMsg;
}

function setFlussuId(theWID) {
  flussuId = theWID;
}

function setFlussuSid(theSID) {
  if (theSID) {
    delCookie();
    setCookie("flussuSid", flussuId + "," + theSID + ",{123-123-123}," + flussuLang, 200);
    xFlussuSid = theSID;
  }
}

function setFlussuEndpoint(flussuEndpoint) {
  flussuServer = flussuEndpoint;
  flussuApi = flussuServer + "api/v2.0/";
}

function setScrolled() {
  scrolled = true;
}

function setAsChat(paramTrueFalse) {
  is_chat = false;
  if (paramTrueFalse === true) is_chat = true;
}

function startFlussu(theLang, startData) {
  flussuLang = theLang;
  $("#flussu-startarea").hide();
  var CK = getCookie("flussuSid");
  var dontstart = false;

  if (CK != null) {
    var elms = CK.split(",");
    if (flussuId != null && flussuId.length > 0 && flussuId != elms[0]) eraseCookie("flussuSid");else {
      flussuId = elms[0];
      flussuSid = elms[1];
      flussuBlock = elms[2];
      flussuLang = elms[3];
      terms = "R:10";
      $("#mychat").show();
      dontstart = true;
      postParam();
    }
  }

  if (!dontstart) {
    if (startData != null) {
      terms = '{"arbitrary":' + startData + '}';
    }

    flussuSid = "";
    flussuBlock = "";
    postParam();
  }
}

function focusInp(Elm) {
  document.getElementById("flussu-text-input").focus();
}

function postParam() {
  if (chkScroll) clearInterval(chkScroll);
  scrolled = false;

  if (!isInit) {
    $("#theTyping").remove();
    $("#chatbot").prop("class", "old_flussuDiv");
    $("#chatbot").prop("id", "old_chatbot");
    $("#mychat").append("<div id='chatbot' class='flussuDiv'></div>");
  }

  pdta = {
    WID: flussuId,
    SID: flussuSid,
    BID: flussuBlock,
    LNG: flussuLang,
    TRM: terms
  };
  $.post(flussuApi + 'flussueng.php', pdta, function (data) {
    sleep(msecSleep);
    elab(data);
    msecSleep = 1000;
  }), 'json';
}

function showUserRow(text) {
  if (text.trim() != "") {
    var temp = "<div class='flussu-out-msg'>";
    temp += "<span class='flussu-my-msg'>" + text.trim() + "</span>";
    temp += "</div>";
    chatArea.insertAdjacentHTML("beforeend", temp);
  }
}

var _fadeDelay = 0;

function showFlussuRow(text) {
  sty = "";

  if (Array.isArray(text)) {
    sty = text[1];
    text = text[0].trim();
  }

  if (text != "") {
    var temp = "<div class='flussu-income-msg' style='animation-duration: " + ++_fadeDelay + "s'>";
    temp += "<img class='flussu-avatar' src='" + flussuServer + "client/assets/img/person.png' alt=''>";
    temp += "<span class='flussu-msg'>" + text + "</span></div>";
    var audio = new Audio(flussuServer + "client/assets/sound/pop.mp3");
    audio.play();
    return temp;
  }
}

function addFlussuButton(btnData) {
  btnTxt = btnData.mVal;
  btnVal = btnData.status = Status;
  area = document.querySelector("#flussu-btnarea");

  if (area.style.display == "none" || area.style.display == "") {
    area.innerHTML = ""; //area.style.display = "flex";

    area.style.display = "block";
    $(".flussu-null-area").hide();
  }

  sty = "";

  if (Array.isArray(btnTxt)) {
    sty = btnTxt[1] + " ";
    btnTxt = btnTxt[0];
  }

  btnTxt = btnTxt.trim();

  if (btnTxt != "") {
    res = "<button " + btnVal + " class='" + sty + "flussu-btn-choose' onclick='execFlussuForm(\"" + flussuBlock + "\",\"$ex!" + fem + "\",\"" + escape(btnTxt) + "\",this)'>";
    if (btnTxt.startsWith("http://") || btnTxt.startsWith("https://")) res += link + "</button><br>";else res += btnTxt + "</button><br>";
    varea = $("#flussu-btnarea");
    h = varea.height();
    varea.height(h + 40);
    area.insertAdjacentHTML("beforeend", res);
  }
}

var btnData = {
  mval: "",
  status: "",
  uuid: ""
};
var txtData = {
  fem: "",
  lbl: "",
  css: ""
};

function elab(obj) {
  _fadeDelay = 0;
  terms = "";
  btnCnt = 0;
  btnData.mVal = "";
  btnData.status = "";
  btnData.uuid = "";
  txtCnt = 0;
  txtData.fem = "";
  txtData.lbl = "";
  txtData.css = "";
  show = true;
  flussuSid = obj.sid;
  flussuBlock = obj.bid;

  if (flussuSid == null || flussuBlock == null) {
    eraseCookie("flussuSid");
    initWorkflow(flussuId, titElemId, butElemId);
    return;
  }

  var CK = getCookie("flussuSid");

  if (CK != null) {
    if (CK != obj.sid) {
      CK = null;
      eraseCookie("flussuSid");
    }
  }

  if (CK == null) setCookie("flussuSid", flussuId + "," + obj.sid + "," + obj.bid + "," + flussuLang, 30);
  var Result = "";
  Status = "";

  if (isInit) {
    isInit = false;
  }

  var d = new Date();
  var btnFirst = true;
  var btnUuid = newUuid();

  for (var Key in obj.elms) {
    mVal = obj.elms[Key];
    D = d.toISOString().replace("T", " ").split(".")[0];
    show = false;
    fe = Key.split("$");
    fem = "";

    if (Array.isArray(fe) && fe.length > 1) {
      key = fe[0];
      fem = fe[1];
    } else key = Key;

    Css = "";

    switch (key) {
      case "END":
        // This process has ended, you can delete the cookie
        eraseCookie("flussuSid");
        mustRestartAll = true;
        show = false;
        break;

      case "B":
        // in case of restart/refresh, the engine sends you back the buttons that was pressed by the client
        if (Array.isArray(mVal)) strmVal = mVal[0].replace(/['"]+/g, '');else strmVal = mVal.replace(/['"]+/g, '');
        if (strmVal.toLowerCase() == "ok") break;
        mVal = strmVal;

      case "R":
        if (Array.isArray(mVal)) {
          if (Array.isArray(mVal[0])) {
            mVal = mVal[0][1];
          } else mVal = mVal[0];
        }

        if (mVal && mVal != '""') {
          showUserRow(mVal);
        }

        break;

      case "S":
        // in case of restart/refresh, the engine sends you back the text that was entered by the client
        if (Array.isArray(mVal)) strmVal = mVal[0].replace(/['"]+/g, '');else strmVal = mVal.replace(/['"]+/g, '');

        if (strmVal && strmVal != '""') {
          Result += showFlussuRow(strmVal);
          show = true;
        } //$(".flussuDiv").append("<div class='elmdiv'></div><div class='d-flex justify-content-end'><div class='elm2' style=''><u>" + strmVal + "</u></div></div><div class='d-flex justify-content-end'></div>");


        break;

      case "TITLE":
        // in case of restart/refresh, the engine sends you back the workflow title if you need to show it on your html client/local interface
        $("#chatTitle").text(mVal);
        break;

      case "L":
        Result += showFlussuRow(mVal);
        show = true;
        break;

      case "M":
        if (Array.isArray(mVal)) {
          Css = " " + mVal[1];
          opts = mVal[0];
        } else opts = mVal;

        Result += addRowChat(opts, 1, Css);
        show = true;
        break;

      case "A":
        if (Array.isArray(mVal)) {
          Css = " " + mVal[1];
          opts = mVal[0];
        } else opts = mVal;

        Result += addRowChat(opts, 2, Css);
        show = true;
        break;

      case "ITS":
        Css = "";

        if (Array.isArray(mVal)) {
          Css = " " + mVal[1];
          opts = mVal[0];
        } else opts = mVal;

        opts = JSON.parse(opts);
        showSelection(opts, fem);
        show = true;
        break;

      case "ITT":
        if (Array.isArray(mVal)) {
          Css = " " + mVal[1];
          lbl = mVal[0];
        } else lbl = mVal;

        txtData.fem = fem;
        txtData.css = Css.trim();
        txtData.lbl = lbl; //txtData.flussuBlock = flussuBlock;

        show = true;
        txtInputFocusDone = false;
        txtCnt++;
        break;

      case "ITB":
        btnData.mVal = mVal;
        btnData.status = Status;
        btnData.uuid = btnUuid;
        addFlussuButton(btnData);
        btnCnt++;
        break;

      case "ITM":
        if (Status == "itb" || Status == "itt") {
          Result += "</div>";
          Status = "";
        }

        Result += addRowFile(mVal, fem, Css);
        show = true;
        break;
    }

    if (show && Status == "") {
      // tipicamente label, link o media
      showRow(Result, is_chat, staScrivendo); //chkScroll = setInterval(updateScroll, 1000);

      Result = "";
    }
  }

  if (Result != "") {
    showRow(Result, is_chat, staScrivendo);
    Result = "";
  }

  if (txtCnt == 1 && btnCnt == 1) {
    showTextArea(btnData, txtData, flussuBlock);
  } else if (txtCnt > 0) {
    showTextArea(null, txtData, flussuBlock);
  }

  scrolled = true;
  updateScroll(); //    scrolled = false;
}

function showSelection(opts, fem) {
  area = document.querySelector("#flussu-selarea");
  area.innerHTML = "";
  Result = "<select class='" + flussuBlock + " flussu-select' id='select-input' name='$" + fem + "'>";
  Object.keys(opts).forEach(function (opK) {
    Result += "<option value='@OPT[\"" + opK + "\",\"" + opts[opK] + "\"]'>" + opts[opK] + "</option>";
  });
  Result += "</select>";
  area.insertAdjacentHTML("beforeend", Result);
  area.style.display = "flex";
  $(".flussu-null-area").hide();
}

function showTextArea(btn, txt, flussuBlock) {
  area = document.querySelector("#flussu-inparea");
  area.innerHTML = "";

  if (btn) {
    Result = " id='flussu-text-input' class='flussu-input " + flussuBlock + "' name='$" + txt.fem + "'";
    if (txt.css == "textarea") Result = "<textarea style='height:100px' rows=3 " + Result + "></textarea><br>";else Result = "<input type='text' onkeyup='checkEnter(event,\"" + btn.uuid + "\")' " + Result + " value=''>";
    $("#flussu-btnarea").hide();
  } else {
    if (txt.css == "textarea") Result = "<textarea style='height:100px' rows=3 id='flussu-text-input' class='flussu-input " + flussuBlock + "' name='$" + txt.fem + "'></textarea><br>";else Result = "<input id='flussu-text-input' class='flussu-input " + flussuBlock + "' type='text' name='$" + txt.fem + "' value=''>";
  }

  area.insertAdjacentHTML("beforeend", Result);
  if (btn) area.insertAdjacentHTML("beforeend", "<button id='flussu-emoji-btn'>&#127773;</button>");else area.insertAdjacentHTML("beforeend", "<button id='flussu-emoji-btn2'>&#127773;</button>");

  if (btn) {
    Result = "<button class='flussu-submit' id='" + btn.uuid + "' onclick='execFlussuForm(\"" + flussuBlock + "\",\"$ex!" + btn.fem + "\",\"" + escape(txt.lbl) + "\")'><span class='material-icons'>send</span></button>";
    area.insertAdjacentHTML("beforeend", Result);
  }

  area.style.display = "flex";
  $(".flussu-null-area").hide();
  focusInp(area);
}

function showRow(theRow) {
  chatArea.insertAdjacentHTML("beforeend", theRow);
  updateScroll();
}

function addRowFile(element, fem, Css) {
  if (Array.isArray(element)) {
    Css = " " + element[1];
    element = element[0];
  }

  id_elm = "img_" + fem + flussuBlock;
  res = "<div class='elmdiv'></div>";
  res += "<div style='border:solid 1px silver;padding:5px;margin:5px;'><!--input img--><p id='P" + flussuBlock + "' class='" + Css + "'>" + element + "</p><div>";
  res += "<input id=\"img_" + fem + "\" name=\"$" + fem + "\" type='file' accept=\"image/*\" class=\"flussu-input I" + flussuBlock + " " + Css + "\" onchange=\"getPhoto(this,'" + id_elm + "',true);\" ></input>";
  res += "</div><figure id='F" + flussuBlock + "' class='about-picture' style='display:none;width:75%;height:60%;align-items:center;'>";
  res += "<img class='M" + flussuBlock + "' id='" + id_elm + "' src='' style='max-width:100%;max-height:100%'></figure></div>";
  return res;
}

function addRowChat(element, elType, Css) {
  switch (elType) {
    case 1:
      res = "<div>" + element + "</div>";
      break;

    case 2:
      preElm = element;
      if (element.trim().substring(0, 4) != "http") element = "http://" + element.trim();
      res = "<div><a target='_blank' href='" + element + "' class=";

      if (Css.trim().substring(0, 6) == "button") {
        CssElm = Css.split(",");
        if (CssElm.length == 1) CssElm[1] = preElm;
        res += "'btn btn-primary'>" + CssElm[1] + "</a></div>";
      } else res += "'" + Css + "'>" + element + "</a></div>";

      break;

    default:
      res = "<div>WHAT???</div>";
  }

  var temp = "<div class='flussu-income-msg' style='animation-duration: " + ++_fadeDelay + "s'>";
  temp += "<img class='flussu-avatar' src='" + flussuServer + "client/assets/img/person.png' alt=''>";
  temp += "<span class='flussu-msg'>" + res + "</span></div>";
  return temp;
}

function _hideAll() {
  $("#flussu-btnarea").hide();
  $("#flussu-filarea").hide();
  $("#flussu-selarea").hide();
  $("#flussu-inparea").hide();
  $(".flussu-null-area").show();
}

function execFlussuForm(blockId, btnName, btnVal, elem) {
  _hideAll();

  btnVal = unescape(btnVal);
  aLink = "";

  if (btnVal.startsWith("http://") || btnVal.startsWith("https://")) {
    aLink = btnVal;
    btnVal = "Link";
    window.open(aLink, '_blank');
  }

  if (chkScroll) clearInterval(chkScroll);
  appendTxt = "";
  appendBtn = "";
  var d = new Date();
  D = d.toISOString().replace("T", " ").split(".")[0];
  var trmsv = {};
  trmsv[btnName] = btnVal; // FILE ------------------------------------------------------------

  if ($(".M" + blockId)[0]) {
    // - ctrls aspect - - - - - - - - - - - - - - - - 
    $("#P" + blockId).hide();
    $(".I" + blockId).hide();
    $(".M" + blockId).width("100");
    $(".M" + blockId).parent().parent().css("border", "none"); // - img aspect - - - - - - - - - - - - - - - - - 

    if ($(".M" + blockId).height() > 100) $(".M" + blockId).height("100");
    IRW = $(".M" + blockId).parent().width();
    $(".M" + blockId).css({
      position: 'relative',
      left: IRW + "px"
    }); // - data - - - - - - - - - - - - - - - - - - - - 

    appendTxt = _getFileName;
    nam = $(".I" + blockId)[0].name;
    trmsv[nam + "_name"] = _getFileName;
    trmsv[nam + "_data"] = $(".M" + blockId)[0].src;
  } // -----------------------------------------------------------------


  blkElm = $("." + blockId);
  $(blkElm).each(function () {
    switch (this.nodeName) {
      case "SELECT":
        trmsv[this.name] = this.selectedOptions[0].value;
        appendTxt += this.selectedOptions[0].text;
        break;

      case "INPUT":
      case "TEXTAREA":
        if (this.value != "") {
          if (appendTxt != "") appendTxt += ", ";
          appendTxt += this.value;
        }

        trmsv[this.name] = this.value;
        break;

      case "BUTTON":
        this.style["display"] = "none";
        appendBtn = btnVal;
        break;
    }

    ;
  });

  if (appendTxt == "") {
    appendBtn = btnVal;
  }

  dt = "";
  if (is_chat) dt = "<div class='msgdate2'>" + D + "</div>";
  if (appendBtn != "") showUserRow(appendTxt + " <span class='flussu-usr-reply'>" + appendBtn + "</span>");else showUserRow(appendTxt);
  terms = JSON.stringify(trmsv);
  postParam();
}

var _getFileName = "";

function getPhoto(inp, prew, adj) {
  if (inp.files && inp.files[0]) {
    _getFileName = inp.files[0].name;
    var reader = new FileReader();

    reader.onload = function (e) {
      var pe = $("#" + prew);
      pe.css("display", "flex");
      pe.attr("src", e.target.result);
      pe[0].parentElement.style.display = "flex";
    };

    reader.readAsDataURL(inp.files[0]);
  }
}

function checkEnter(e, btnUUID) {
  if (e.keyCode == 13) $('#' + btnUUID).click();
}

function getWorkflowInfo() {
  pdta = {
    WID: flussuId,
    CMD: 'info'
  };
  $.post(flussuApi + 'flussueng.php', pdta, function (data) {
    $("#chatTitle").text(data.tit);
    Btns = data.langs.split(',');
    $("#flussu-startarea").html("<div>");
    Btns.forEach(addLangButton);
    $("#flussu-startarea").append("</div>");
    popup.classList.toggle('flussu-show');
  }), 'json';
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

  btn = "<button onclick=\"FlussuIn('" + item + "')\" class=\"flussu-btn-lang\" id=\"flussu-btn-lang-" + item.toLowerCase() + "\" style=\"background:rgb(80, 80, 88)\">" + text + "<br><img style=\"padding-top:8px;\" src=\"https://flagcdn.com/28x21/" + item2.toLowerCase() + ".png\"></button>";
  $("#flussu-startarea").append(btn);
}

function updateScroll() {
  var element = document.getElementById("flussu-chat-area");
  element.scrollTop = element.scrollHeight;

  if (chkScroll > 0) {
    clearInterval(chkScroll);
    chkScroll = 0;
  } else {
    chkScroll = setInterval(updateScroll, 1000);
  }
}

function newUuid() {
  var dt = new Date().getTime();
  var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    var r = (dt + Math.random() * 16) % 16 | 0;
    dt = Math.floor(dt / 16);
    return (c == 'x' ? r : r & 0x3 | 0x8).toString(16);
  });
  return uuid;
}

$(document).ready(function () {
  if (document.getElementById("flussu-section")) return;
  fluz = "<div class=\"flussu-section\" id=\"flussu-section\"><button class=\"flussu-chat-btn\"><span class=\"material-icons\">insert_comment</span></button>\n    <div class=\"flussu-chat-popup\">\n       <div class=\"flussu-chat-header\">\n           <div class=\"flussu-badge\">BADGE</div>\n           <div class=\"flussu-chat-title\" id=\"chatTitle\">Titolo della Chat</div>\n    </div>   \n    <div class=\"flussu-chat-area\" id=\"flussu-chat-area\"><div class=\"flussu-income-msg\" id=\"flussu-startMsgElm\">\n    <img class=\"flussu-avatar\" src=\"";
  fluz += flussuServer + "client/assets/img/person.png\" alt=\"\"><span class=\"flussu-msg\" id=\"flussu-startMsg\">&nbsp;</span></div></div>\n    <div class=\"flussu-start-area\" id=\"flussu-startarea\">[start buttons]</div>\n    <div class=\"flussu-null-area\" id=\"flussu-nullarea\">&nbsp;</div><div class=\"flussu-input-area\" id=\"flussu-inparea\"><input id=\"flussu-text-input\" type=\"text\" class=\"flussu-input\"><button id=\"flussu-emoji-btn\">&#127773;</button><button class=\"flussu-submit\" id=\"submitxt\"><span class=\"material-icons\">send</span></button></div>\n    <div class=\"flussu-select-area\" id=\"flussu-selarea\"><select id=\"select-input\"></select><button class=\"flussu-submit\" id=\"submitsl\"><span class=\"material-icons\">send</span></button></div>\n    <div class=\"flussu-file-area\" id=\"flussu-filarea\"><input id=\"file-input\" type=\"file\" accept=\"image/png, image/jpeg\" class=\"flussu-input\"><button class=\"flussu-submit\" id=\"submitfl\"><span class=\"material-icons\">send</span></button></div>\n    <div class=\"flussu-buttons-area\" id=\"flussu-btnarea\"></div><div class=\"flussu-powered-by\">powered by <a href=\"https://www.flussu.com\" target=\"_blank\">flussu.com</a></div></div></div>";
  document.body.insertAdjacentHTML("beforeend", fluz);
  picker = new EmojiButton();
  trigger = document.querySelector('#flussu-emoji-btn');
  popup = document.querySelector('.flussu-chat-popup');
  chatBtn = document.querySelector('.flussu-chat-btn');
  submitSel = document.querySelector('#submitsl');
  submitFil = document.querySelector('#submitfl');
  chatArea = document.querySelector('.flussu-chat-area');
  inputElm = document.querySelector('#flussu-text-input');
  badge = document.querySelector('.flussu-badge');
  nullArea = document.querySelector(".flussu-nullarea");
  submitTxt = document.querySelector('#submitxt');
  $("#chatTitle").text(chatTitle);
  if (startMsg != "") $("#flussu-startMsg").html(startMsg);else $("#flussu-startMsgElm").hide(); // chat button toggler

  chatBtn.addEventListener('click', function () {
    _hideAll();

    $('#flussu-chat-area').html("");
    chatBtn.style.display = "none";

    if (mustRestartAll) {
      eraseCookie("flussuSid");
      mustRestartAll = false;
    }

    var CK = getCookie("flussuSid");

    if (CK != null) {
      var elms = CK.split(",");
      if (flussuId != null && flussuId.length > 0 && flussuId != elms[0]) eraseCookie("flussuSid");else {
        startFlussu(null, null);
      }
    } else {
      //$("#flussu-btnarea").show();
      $("#flussu-startarea").show();
    }

    var audio = new Audio(flussuServer + "client/assets/sound/pop2.wav");
    audio.play();
    getWorkflowInfo();
  }); // close badge toggler

  badge.addEventListener('click', function () {
    popup.classList.toggle('flussu-show');
    chatBtn.style.display = ""; //chatBtn.style.toggle('flussu-chat-btn');
  });
  picker.on('emoji', function (emoji) {
    document.querySelector('#flussu-text-input').value += emoji;
  });
  trigger.addEventListener('click', function () {
    return picker.togglePicker(trigger);
  }); //send msg

  submitTxt.addEventListener('click', function () {
    alert("PING");
    getInput();
  }); // send selection

  submitSel.addEventListener('click', function () {
    alert("SEL-PING");
  }); // send selection

  submitFil.addEventListener('click', function () {
    alert("FIL-PING");
  });
  inputElm.addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
      getInput();
    }
  }); // get scrolling event 

  $("#chatContainer").scroll(function (event) {
    var st = $(this).scrollTop();

    if (st > lastScrollTop) {// downscroll code
    } else setScrolled();

    lastScrollTop = st;
  });
});

function sleep(milliseconds) {
  var date = Date.now();
  var currentDate = null;

  do {
    currentDate = Date.now();
  } while (currentDate - date < milliseconds);
}
/*
Client cookie setting to let the script restart when browser is reloaded or updated.
*/


function setCookie(name, value, minutes) {
  var expires = "";

  if (minutes) {
    var date = new Date();
    date.setTime(date.getTime() + minutes * 60 * 1000);
    expires = "; expires=" + date.toUTCString();
  }

  document.cookie = name + "=" + (value || "") + expires + "; path=" + window.location.href;
}

function getCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(';');

  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];

    while (c.charAt(0) == ' ') {
      c = c.substring(1, c.length);
    }

    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
  }

  return null;
}

function eraseCookie(name) {
  document.cookie = name + '=; Path="+flussuServer+"; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}
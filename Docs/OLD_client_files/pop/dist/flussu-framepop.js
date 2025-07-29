"use strict";

/**************************************************************************

  Flussu 3.0.0
  Script to frame the "whole video chat window" - Apr 2023 
    - v1.2.0 - 27 Apr 2023 
    - Aldo Prinzi
  Updated Jun 2024

* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
Copyright (C) 2021-2024 Mille Isole SRL - Palermo (Italy)
============================ INSTRUCTIONS ==============================
   Add the flussu script at the end of the header of your HTML page.
   Example:
    <head>
        <script id="flussu" src='https://server.flussu.com/client/pop/dist/flussu-framepop.js' WID='[123456ABCDE]' TIT='hello world!' LNG='IT' CSS='2' SRV='server.flussu.com'></script>
    </head>
   Sintax: 
        <script 
                src='/client/pop/dist/flussu-framepop.js' 
           mand WID='[yourWIDnumber]'
           mand TIT='your title here'
           opt  [SRV='server.flussu.com' as the Flussu's executing server address]
           opt  [LNG='IT' or 'EN' or whatever]
           opt  [CSS='yourStyleAttributeName' or 'app' to use your own version]
        >
        </script>
================================================================================*/
function urlParam(name) {
  var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
  if (results == null) return null;
  return decodeURI(results[1]) || 0;
};

function getParam(pName, pDef) {
  var PAR = document.scripts.namedItem("flussu").getAttribute(pName);
  if (!PAR || PAR == "") PAR = urlParam(pName);
  if (!PAR || PAR == "") PAR = pDef;
  return PAR;
}

var _flussu_isApp = false;
var _flussu_cssUri = "";
document.addEventListener("readystatechange", function (event) {
  if (document.readyState === "complete") {
    var Server = getParam("SRV", new URL(document.scripts.namedItem("flussu").src).origin) + "/";
    if (Server.substring(0, 4) != "http") Server = "https://" + Server;
    var WID = getParam("WID", "");
    var TIT = getParam("TIT", "no title...");
    var LNG = getParam("LNG", "IT");
    var CSS = getParam("CSS", "1");
    var TRM = decodeURIComponent(getParam("TRM", ""));
    var link = document.createElement("link");
    link.type = "text/css";
    link.rel = "stylesheet";

    if (CSS == "app") {
      _flussu_isApp = true;
      CSS = "";
      _flussu_cssUri = Server+"client/pop/css/flussu_chat.css";
      link.href = Server+"client/pop/css/flussu-frame.css";
    } else link.href = Server+"client/pop/css/flussu-frame" + CSS + ".css";

    document.body.appendChild(link);
    if (document.getElementById("flussu-chatbot")) return;
    var fluz = "    <div class=\"flussu-chatbot\">\n        <div class=\"flussu-sticky-call\">\n            <div class=\"flussu-sticky-chat\">\n                <div class=\"flussu-chat-header\">\n                    <div class=\"flussu-head-icon\">\n                        <img class=\"flussu-header-pic\">\n                    </div>\n                    <div class=\"flussu-badge\" onClick=\"toggleChat()\"></div>\n                    <div class=\"flussu-chat-title\" id=\"chatTitle\">" + TIT + "</div>\n                </div> \n                <div class=\"flussu-chat-container\">";
    fluz += "<iframe src=\""+Server+"client/pop/chat.php?WID=" + WID + (_flussu_cssUri == "" ? "" : "&CSSURI=" + encodeURIComponent(_flussu_cssUri)) + (CSS == "" ? "" : "&CSS=" + CSS) + "&LNG=" + LNG + "&TRM=" + encodeURIComponent(TRM) + "&OFU=" + encodeURIComponent(window.location.href) + "\" ";
    fluz += " class=\"flussu-chat-frame\" frameborder=\"0\"></iframe>\n                </div>\n                <div class=\"flussu-chat-footer\">\n                    <div class=\"flussu-chat-footer-content\" id=\"chatFooter\">powered by <a href='https://www.flussu.com' target='_blank'>flussu.com</a></div>\n                </div>\n            </div>\n        </div>\n    </div>\n    <div class=\"flussu-section\" id=\"flussu-section\"><button id=\"flussu-start-chat\" class=\"flussu-chat-btn\"><svg class=\"flussu-btn-svg\" width=\"35px\" fill=\"none\" height=\"31px\" version=\"1.0\" viewBox=\"0 0 8.47 7.42\" xmlns=\"http://www.w3.org/2000/svg\">\n        <path fill=\"none\" stroke=\"#ffffff\" stroke-width=\"1.3\" stroke-miterlimit=\"22.9256\" d=\"M2.38 0.65l3.97 0c0.81,0 1.47,0.66 1.47,1.47l0 1.95c0,0.81 -0.66,1.46 -1.47,1.47l-3.36 0.05 -1.72 1.17c-0.25,0.17 0.62,-1.2 0.19,-1.37 -0.5,-0.2 -0.81,-0.74 -0.81,-1.32l0 -1.69c0,-0.95 0.78,-1.73 1.73,-1.73z\"/>\n        </svg></button>\n    </div>\n";
    document.body.insertAdjacentHTML("beforeend", fluz);
    var chatBtn = document.querySelector('#flussu-start-chat');
    chatBtn.addEventListener('click', function () {
      setCookie("flussuSid", Server, WID + "," + LNG, 30);
      chatBtn.style.display = "none";
      var el;
      try {
        //if there is jQuery
        $(".flussu-chatbot").show("slow");
        $(".flussu-chatbot").animate({
          bottom: '50px',
          right: '50px'
        });
      } catch (e) {
        //if Not
        el = document.querySelector(".flussu-chatbot");
        el.style.display = "block";
      }

      var audio = new Audio(Server+"client/assets/sound/pop2.wav");
      audio.play();
    });
    var badge = document.querySelector('.flussu-badge');
    badge.addEventListener('click', function () {
      var el = document.querySelector(".flussu-chatbot");
      el.style.display = "none";
      chatBtn.style.display = "";
  });
  }
});

function toggleChat() {
  $(".flussu-chatbot").hide();
  chatBtn.style.display = "flex";
}

;

function setCookie(name, server, value, minutes) {
  var expires = "";

  if (minutes) {
    var date = new Date();
    date.setTime(date.getTime() + minutes * 60 * 1000);
    expires = "; expires=" + date.toUTCString();
  }

  document.cookie = name + "=" + (value || "") + expires + "; Path=" + server + "; SameSite=Strict";
}
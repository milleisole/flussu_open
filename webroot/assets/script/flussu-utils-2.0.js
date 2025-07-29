
/*************************************************************************
Flussu 2.2.1
Script to handle Flussu's iframe inner display (v2.2.1)
- v2.0.0 - 30 Aug 2022 
- Aldo Prinzi
Copyright (C) 2021-2022 Mille Isole SRL - Palermo (Italy)
*************************************************************************/

function FlussuPassVarToIframe(){
    // find flussu iFrames
    var selection = document.getElementsByTagName('iframe');
    var iframes = Array.prototype.slice.call(selection);
    var loc = window.location.toString();
    var parms = loc.split('?')[1];

    iframes.forEach(function(iframe) {
        if ((iframe.src.match(/flu.lu/g))||(iframe.src.match(/flu.lt/g))) {
            var src=iframe.src
            if (parms){
                if (src.replace(parms,"")==src)	       
                    src+="&"+parms;
            }
            src+="&OFU="+encodeURIComponent(window.location.href)
            iframe.setAttribute("src", src);
        }
    });
}

document.addEventListener("DOMContentLoaded", function(event) { 
    FlussuPassVarToIframe();
});

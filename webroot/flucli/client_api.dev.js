// Nuovo client API per Flussu v4.4 - Client UNICO (chat+form)
// SCRIPT FUNZIONALE

// se una label ha una classe "onchat" on viene visualizzata sulla form ma sulo stream di chat
// se una label o un pulsante ha valore "noshow" non viene visualizzato nell'area di chat

// TODO:
// Inputbox: se il pulsante è uno al ENTER premere il pulsante
// Language: all'init abilitare i pulsanti della lingua previsti
// Language: quando si preme un tatso lingua, si deve cambiare anche la lingua del workflow e ricaricare il form
/* ------------------------------------

Nel footer, considerare il pulsante di send a destra, sicchè a SX potrebbe andarci un controllo
per esempio un select o un altro pulsante.

----------------------------------------*/

const SERVER_URL = "/srvdev4.flu.lt/api/v2.0/flussueng.php"; 
let WID = "";  
let SID = null; 
let BID = null;  
LNG = "it";  

let defaultChatBarHTML = null;
let lastPressedTerm = null;
let lastFormElms = null;
let lastPressedSkipValidation = false;
let theCallerUri=window.location.href;
let calendarHtml = '';
let lastOptionMaps = {};
const params = new URLSearchParams(window.location.search);
let outerFrameUri = decodeURIComponent(params.get('OFU') || "");

let hideSingleButton=false;

function encodeFormBody(obj) {
  return Object.entries(obj)
    .map(([k, v]) => encodeURIComponent(k) + "=" + encodeURIComponent(v))
    .join("&");
}

function saveDefaultChatBarHTML() {
  const chatBar = document.querySelector("#chat-form > div.rounded-2xl");
  if (chatBar) defaultChatBarHTML = chatBar.outerHTML;
}

function restoreDefaultChatBarHTML() {
  const chatForm = document.getElementById("chat-form");
  if (!defaultChatBarHTML || !chatForm) return;
  const oldBar = chatForm.querySelector("div.rounded-2xl");
  if (oldBar) oldBar.replaceWith(createElementFromHTML(defaultChatBarHTML));
  if (window.initChatLogic) window.initChatLogic();
}
function createElementFromHTML(htmlString) {
  const div = document.createElement('div');
  div.innerHTML = htmlString.trim();
  return div.firstChild;
}

function disabilitaChatBar() {
  const chatForm = document.getElementById("chat-form");
  if (chatForm) {
    Array.from(chatForm.elements).forEach(el => el.disabled = true);
  }
}
function abilitaChatBar() {
  const chatForm = document.getElementById("chat-form");
  if (chatForm) {
    Array.from(chatForm.elements).forEach(el => el.disabled = false);
  }
}

async function sendStepData(termObj = {}, callback = null) {

  if (!SID){
    termObj["$isForm"] = "true"; 
    termObj["$_FD0508"]=theCallerUri;
    termObj["$_AL2905"]=outerFrameUri;
  }

  const payload = {
    WID: WID,
    SID: SID || "",
    BID: BID || "",
    LNG: LNG || "",
    TRM: JSON.stringify(termObj)
  };
  const body = encodeFormBody(payload);

  const res = await fetch(SERVER_URL, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body
  });

  if (!res.ok) throw new Error("Errore comunicazione server: " + res.status);
  const json = await res.json();

    if (json.error) {
        console.error("Errore server:", json.error);
        if (json.error === "This session has expired - E89") {
            showAlert(LNG['confirm_exipred'] || 'La sessione è terminata. Vuoi ricominciare?', () => {
                resetFlussuSession();
            });
            return null;
        } else {
            alert("Errore: " + json.error);
            return null;
        }
    } else {
        if (json.sid) { 
            SID = json.sid;
            document.getElementById("sessioncode-footer").innerHTML  = "<span style='font-size:0.6em'>"+SID+"</span>";
        }
        if (json.bid) BID = json.bid;
        if (json.lng) LNG = json.lng;

        if (callback) callback(json);
        return json;
    }
}


async function startWorkflow(initWid,initLang = "it") {
  
  await loadWorkflowInfo({ titElemId: 'chat-title', butElemId: 'lang-dropdown-list', flussuId: initWid });
  
  LNG = initLang || "it";
  SID = getCookie(SID_COOKIE) || "";
  BID = null;
  WID= initWid || "";
  saveDefaultChatBarHTML();
  await sendStepData({}, renderFormFlussu);
}

// Chiamare dopo ogni step form
async function submitFormStep(termObj) {
  appendUserFormCard(lastFormElms, termObj);   

  const chatForm = document.getElementById('chat-form')
                     .querySelector('div.rounded-2xl'); 
  disabilitaChatBar();       
  showFormSpinner(chatForm); 

  await sendStepData(termObj, (json) => {
    if (json.sid) SID = json.sid;
    if (SID) setCookie(SID_COOKIE, SID); 
    renderFormFlussu(json); 
    
    hideFormSpinner();          
    abilitaChatBar();           
  });
}

function setLanguage(lang) {
  LNG = lang;
  sendStepData({}, renderFormFlussu);
}

  var submitClicked="";


function renderFormFlussu(json) {

  if (json.bid) BID = json.bid;
  if (json.sid) SID = json.sid;
  if (json.lng) LNG = json.lng;

  calendarHtml = '';  // reset ogni volta!
  lastFormElms = extractCalendar(json.elms); // qui si fa la “magia”

  if (!json.elms || !Object.keys(json.elms).length) {
    restoreDefaultChatBarHTML();
    abilitaChatBar();
    return;
  }

  const chatForm = document.getElementById("chat-form");
  const oldBar = chatForm.querySelector("div.rounded-2xl");
  if (!oldBar) return;
  const newBar = document.createElement("div");
    newBar.className = [
    oldBar.className,
    + 'max-h-[60vh]',     // limite verticale
    + 'overflow-y-auto'   // scrollbar interna
    ].join(' ');


  newBar.innerHTML = calendarHtml;

  let foundFileRequest = false;

  let itbKeys = Object.keys(json.elms).filter(k => /^ITB\$/.test(k));
  let onlyOkButton = (itbKeys.length === 1) && 
    (/^ok$/i.test(json.elms[itbKeys[0]][0] || ''));

    hideSingleButton=!onlyOkButton ;
  
  let btnGroup = null;
    if (itbKeys.length > 1) {
        btnGroup = document.createElement('div');
        btnGroup.className = "flex flex-wrap gap-2 mt-2 items-end";
  }

  Object.keys(json.elms).forEach((k, idx) => {
    const arr = json.elms[k];
    if (!Array.isArray(arr) || arr.length < 2) return;

    // Label
    if (/^L\$/.test(k)) {
        let label = arr[0] || "";
        if (label) {
            if (!hasOnChat(arr)){
                const labelEl = document.createElement('label');
                labelEl.className = "font-semibold mb-2 block font-semibold mb-2 block text-gray-700 dark:text-gray-200";
                labelEl.innerHTML = label.replace(/[:：]$/, ""); // USA innerHTML!
                newBar.appendChild(labelEl);
            } else {
                elementi = { elms: {} };
                elementi.elms[k] = arr;
                appendUserFormCard(elementi.elms, null);
            }
        }
        return;
    }

    // Link/anchor: A$
    if (/^A\$/.test(k)) {
        const link = document.createElement("a");
        link.href = arr[0] || "#";
        link.className = "underline text-blue-700 dark:text-blue-400 my-1 block";
        link.target = "_blank";
        link.textContent = arr[0];
        newBar.appendChild(link);
        return;
    }

    // Textarea (ITT$)
    if (/^ITT\$/.test(k)) {
      if (arr[1] && arr[1].display_info && arr[1].display_info.subtype=="textarea"){
            const textarea = document.createElement('textarea');
            textarea.name = k;
            textarea.className = "w-full px-2 py-2 rounded border mt-1 mb-1 dark:bg-gray-700 dark:text-gray-100";
            if (arr[1] && arr[1].display_info && arr[1].display_info.mandatory)
                textarea.required = true;
            textarea.placeholder = arr[0] || "";
            newBar.appendChild(textarea);
        } else {
            const input = document.createElement('input');
            input.name = k;
            input.type = "text";
            input.className = "w-full px-2 py-2 rounded border mt-1 mb-1 dark:bg-gray-700 dark:text-gray-100";
            if (arr[1] && arr[1].display_info && arr[1].display_info.mandatory)
                input.required = true;
            input.placeholder = arr[0] || "";
            newBar.appendChild(input);
        }
        return;
    }

    // Select (ITS$) anche multiple/esclusive
    if (/^ITS\$/.test(k)) {
        let indice=0;
        if(arr[2]) 
            indice=estraiValore(arr[2]);
        let optString = arr[0] || "";
        let opts;
        try {
            opts = typeof optString === "string"
            ? JSON.parse(optString.replace(/([{\[])\s*([0-9]+,[0-9]+)\s*:/g, '$1"$2":'))
            : optString;
        } catch { opts = optString; }
        let subtype = arr[1] && arr[1].display_info && arr[1].display_info.subtype;
        let mandatory = arr[1] && arr[1].display_info && arr[1].display_info.mandatory;
        if (subtype === "exclusive" || subtype === "radio") {
            Object.entries(opts).forEach(([v, txt]) => {
                const label = document.createElement('label');
                label.className = "flex items-center gap-2 mb-1 text-gray-800 dark:text-gray-200";
                const radio = document.createElement('input');
                radio.type = "radio";
                radio.name = k;
                radio.value = v.split(',')[0];
                if (mandatory) radio.required = true;
                label.appendChild(radio);
                label.append(" " + txt);
                newBar.appendChild(label);
            });
        } else if (subtype === "multiple") {
            Object.entries(opts).forEach(([v, txt]) => {
            const label = document.createElement('label');
            label.className = "flex items-center gap-2 mb-1 text-gray-800 dark:text-gray-200";
            const check = document.createElement('input');
            check.type = "checkbox";
            check.name = k;
            check.value = v.split(',')[0];
            label.appendChild(check);
            label.append(" " + txt);
            newBar.appendChild(label);
            });
        } else {
            // Select normale
            const select = document.createElement('select');
            select.name = k;
            select.className = "w-full px-3 py-2 rounded border mt-1 mb-2 dark:bg-gray-700 dark:text-gray-100";
            if (mandatory) select.required = true;
            Object.entries(opts).forEach(([v, txt]) => {
                const o = document.createElement('option');
                o.value = v.split(',')[0];
                o.textContent = txt;
                if (o.value === indice)  o.selected = true;      
                select.appendChild(o);
            });
            newBar.appendChild(select);
        }
        lastOptionMaps[k] = opts; 
        return;
    }

    // File richiesto dal server (ITM$): gestito solo via upload esterno!
    if (/^ITM\$/.test(k)) {
      foundFileRequest = true;
      attivaUploadEsterno(k, arr);
      disabilitaChatBar();
      const info = document.createElement('div');
      info.className = "text-sm text-blue-500 mb-2";
      info.textContent = "Carica il file richiesto con il pulsante di upload in basso.";
      newBar.appendChild(info);
      return;
    }

    // Video/Youtube (M$ con display_info.type == "youtube")
    if (/^M\$/.test(k) && arr[1]?.display_info?.type === "youtube") {
      const iframe = document.createElement("iframe");
      iframe.width = "320";
      iframe.height = "180";
      iframe.src = "https://www.youtube.com/embed/" + arr[0].split("v=")[1];
      iframe.allow = "accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture";
      iframe.allowFullscreen = true;
      iframe.className = "my-2 rounded-lg";
      newBar.appendChild(iframe);
      return;
    }

    // Hidden field (GUI)
    if (/^GUI\$/.test(k)) {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = k;
      input.value = arr[0] || "";
      newBar.appendChild(input);
      return;
    }
    
    // Bottone submit (ITB$)
    if (/^ITB\$/.test(k)) {
        const btn = document.createElement('button');
        btn.type = "submit";
        //var genBtn=null;
        //genBtn=k.split(";");
        //var btnCont=null;
        //if (genBtn.length > 1) {
        //    k=genBtn[0];
        //    btnCont = genBtn[1];
        //
        btn.name = k;
        btn.addEventListener('click', () => { 
            lastPressedTerm = btn.name; 
            lastPressedSkipValidation = (arr[1].display_info.subtype || "").split(/\s+/).includes("skip-validation");
        });
        if ((arr[1].display_info.subtype || "").split(/\s+/).includes("skip-validation")) {
            btn.setAttribute('formnovalidate', 'formnovalidate');
            btn.formNoValidate = true;
        }
        if (onlyOkButton) {
            /* ------------------- NOVITÀ ------------------- */
            // ① Creo una riga flex
            const actionRow = document.createElement('div');
            actionRow.className = "flex items-center gap-2 mt-2";

            // ② Recupero l’ultimo elemento già inserito (es. <select>),
            //    purché non sia un label né il textarea iniziale
            let lastCtrl = newBar.lastElementChild;
            if (lastCtrl &&
                !/^(LABEL|BR)$/i.test(lastCtrl.tagName) &&   // escludi etichette & break
                !lastCtrl.classList.contains('flex')) {      // evita di ricatturare altre righe action
            newBar.removeChild(lastCtrl);
            // facciamo sì che occupi lo spazio libero
            lastCtrl.classList.add("flex-1", "min-w-0");
            actionRow.appendChild(lastCtrl);
            }

            // ③ Stili del bottone piccolo
            btn.className =
            "px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-800 " +
            "flex items-center gap-2 ml-auto flex-shrink-0";
            btn.innerHTML = `
            <svg viewBox="0 0 20 20" fill="none" class="w-5 h-5" stroke="currentColor" stroke-width="2">
                <path d="M2.5 17.5l15-7.5-15-7.5v6l10 1.5-10 1.5z" fill="currentColor"/>
            </svg>
            <span>${arr[0] || "OK"}</span>
            `;
            actionRow.appendChild(btn);

            // ④ Inserisco la riga nel form
            newBar.appendChild(actionRow);
            /* ---------------------------------------------- */
        } else if (btnGroup) {
            btn.className = "px-5 py-1 rounded bg-blue-600 text-white hover:bg-blue-800";
            //if (btnCont) {
            //    btn.textContent = btnCont;
            //} else {
                btn.textContent = arr[0] || "OK";
            //}
            btnGroup.appendChild(btn);
        } else {
            btn.className = "px-5 py-1 mt-4 rounded bg-blue-600 text-white hover:bg-blue-800";
            btn.textContent = arr[0] || "OK";
            newBar.appendChild(btn);
        }
        return;
    }
  }
);

    if (btnGroup && btnGroup.childElementCount) {
        newBar.appendChild(btnGroup);   
    }
  oldBar.replaceWith(newBar);
  fitFormHeight(newBar);
  window.addEventListener('resize', () => fitFormHeight(newBar));

  scrollChatBottom(); 
  if (foundFileRequest) return;

  chatForm.onsubmit = function(e) {
    e.preventDefault();
    const trmObj = {};
    let pressedTerm =lastPressedTerm;
    let skipValidation = lastPressedSkipValidation;
    lastPressedTerm = null;
    lastPressedSkipValidation = false;

    if (skipValidation) {
        newBar.querySelectorAll('input[required], textarea[required], select[required]').forEach(el => {
            el.removeAttribute('required');
        });
    }

    if (!pressedTerm) {
        const submitBtn = newBar.querySelector('button[type="submit"]');
        if (submitBtn && submitBtn.name && /^ITB\$/.test(submitBtn.name)) {
            pressedTerm = submitBtn.name;
        }
    }

    new FormData(chatForm).forEach((value, key) => {
        let cleanKey = key.replace(/^(IT[T|S|B]?\$)/, "$");
        if (lastOptionMaps[key]) {
            // Esiste una mappa opzioni per questo campo (radio/select)
            let label = null;
            for (let optKey in lastOptionMaps[key]) {
                if (optKey.startsWith(value)) {
                    label = lastOptionMaps[key][optKey];
                    break;
                }
            }
            trmObj[cleanKey] = `@OPT["${value}","${label}"]`;
        } else {
            if (trmObj[cleanKey]) {
                if (!Array.isArray(trmObj[cleanKey])) trmObj[cleanKey] = [trmObj[cleanKey]];
                trmObj[cleanKey].push(value);
            } else {
                trmObj[cleanKey] = value;
            }
        }
    });


    if (e.submitter) {
        const idx = e.submitter.name.match(/ITB\$(\d+)/);
        let key = "$ex!0";
        if (idx && idx[1]) key = `$ex!${idx[1]}`;

        var genBtn=e.submitter.name.split(";");
        submitClicked = e.submitter.textContent;
        if (genBtn.length > 1) 
            key=key+";"+genBtn[1];
        //} else {
            trmObj[key] = e.submitter ? e.submitter.textContent : "OK";
        //}
    }
    submitFormStep(trmObj);
  };
    
  fixPreBlocksAndHighlight();

}

function extractCalendar(elms) {
    // 1. Trova tutti gli ITB$ con [clndr]
    let dateToSlots = {};
    let toRemove = [];
    Object.keys(elms).forEach(k => {
        if (/^ITB\$/.test(k)) {
            const arr = elms[k];
            const cls = arr[1]?.class || "";
            const m = cls.match(/^\[clndr\](\d{4}\/\d{2}\/\d{2}) (\d{2}:\d{2}:\d{2})/);
            if (m) {
                const date = m[1];
                const hour = m[2].slice(0,5); // HH:MM
                if (!dateToSlots[date]) dateToSlots[date] = [];
                dateToSlots[date].push({ k, arr, hour, label: arr[0] });
                toRemove.push(k);
            }
        }
    });
    // 2. Rimuovi dal json.elms tutti quelli usati
    toRemove.forEach(k => delete elms[k]);
    // 3. Costruisci il markup solo se ci sono slot calendario
    if (Object.keys(dateToSlots).length) {
        let html = `<div class="w-full my-4 flex flex-wrap gap-4">`;
        Object.keys(dateToSlots).sort().forEach(date => {
            html += `<div class="bg-gray-100 dark:bg-gray-800 rounded-2xl p-4 min-w-[180px] shadow flex flex-col items-center">`;
            // Formatta data in modo leggibile
            let dateObj = new Date(date.replace(/\//g, "-"));
            html += `<div class="font-bold mb-2 text-blue-900 dark:text-blue-200">${dateObj.toLocaleDateString("it-IT", { weekday: "short", year: "short", month: "short", day: "numeric" })}</div>`;
            // Bottoni slot
            dateToSlots[date].forEach(slot => {
                // Costruisci bottone, ma senza event, perché il bottone verrà reinserito dalla routine principale!
                pippo=slot.label.split(/\s+/)[4];
                html += `<button type="submit" name="${slot.k}" class="my-1 px-3 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-800 w-full">${pippo}</button>`;
            });
            html += `</div>`;
        });
        html += `</div>`;
        calendarHtml = html;
    }
    return elms;
}




function attivaUploadEsterno(term, arr) {
  const fileBtn = document.getElementById("file-btn");
  if (fileBtn) {
    fileBtn.disabled = false;
    fileBtn.classList.add("ring-2", "ring-blue-400");
    fileBtn.title = "Carica ora il file richiesto!";
  }
  disabilitaChatBar();
  if (fileBtn) fileBtn.disabled = false;
}

function inviaTermUpload(fileNamesArr) {
  const termObj = { uploads: fileNamesArr };
  submitFormStep(termObj);
}

function hasOnChat(arr) {return hasValue(arr,'onchat');}
function hasNoDisp(arr) {return hasValue(arr,'nodisp');}

function hasValue(arr,elem) {
  if (!Array.isArray(arr) || arr.length < 2) return false;
  const cls = arr[1]?.class;
  if (typeof cls !== 'string') return false;
  return cls.split(/\s+/).includes(elem);
}
function estraiValore(elem) {
  const m = elem.match(/^\[val\]:(.+)$/);
  return m ? m[1] : null;         
}

function appendUserFormCard(elms, trmObj) {
    const chatArea = document.getElementById('chat-area'); // Per lo scroll
    const msgArea = document.getElementById('msg-area'); // Contenitore per i messaggi
    if (!msgArea) {
        console.error('Contenitore msg-area non trovato!');
        return; // Esci se il contenitore non esiste
    }
    done=false;
    let html="";
    let starthtml = '<div class="w-full flex flex-col justify-start mb-2">';
    Object.keys(elms).forEach((k) => {
    const arr = elms[k];
    // Label domanda
    if (/^L\$/.test(k)) {
        if (!trmObj || !hasOnChat(arr)) {
            if (!hasNoDisp(arr)) {
            const raw = (arr[0] || "").trim();
            const hasMedia = /<(img|iframe|video|audio|svg)\b/i.test(raw);
            const plain = raw
                .replace(/<\/?[^>]+(>|$)/g, "") // via tutti i tag
                .replace(/[:：]\s*$/, "") // via ":" finale
                .trim();
                if (hasMedia || plain) {
                    const content = hasMedia ? raw : `${plain}:`;
                    html += starthtml+`<div class="text-sm text-left text-gray-700 dark:text-gray-200 mb-1">${markdownLinksToHtml(raw)}</div>`;
                    done=true;
                    starthtml="";   
                }
            }
        }
    }
  });
  if (done)
    html += '</div>';

  if (trmObj) {
    starthtml = `<div class="w-full flex flex-col items-end mb-2">`;
    done=false;
    Object.keys(elms).forEach((k) => {
        const arr = elms[k];
        if (/^IT[T]?\$/.test(k)) {
            const label = arr[0] || "";
            let cleanKey = k.replace(/^(IT[T|S|B]?\$)/, "$");
            const val = trmObj[cleanKey] || "";
            html += starthtml+`<div class="font-semibold">${label ? label + ": " : ""}<span class="inline-block bg-white/70 dark:bg-gray-700/80 rounded px-2 py-1">${val}</span></div>`;
            done=true;
            starthtml="";
        }
        if (/^ITS\$/.test(k)) {
            let cleanKey = k.replace(/^(ITS\$)/, "$");
            let val = trmObj[cleanKey] || "";
            // Gestione formato speciale @OPT["valore","etichetta"]
            let v = val;
            if (/^@OPT\[".*?",".*?"\]$/.test(val)) {
                try {
                    v = JSON.parse(val.replace(/^@OPT/, ''))[0];
                } catch(e) { v = val; }
            }
            const obj = JSON.parse(arr[0]);
            const label = Object.entries(obj).find(([k]) => k.split(',')[0] === v)?.[1];
            html += starthtml+`<div class="font-semibold">${label}</div>`;
            done=true;
            starthtml="";
        }
    });
    if (done)
        html += "</div>";
    Object.keys(elms).forEach((k) => {
        starthtml = `<div class="w-full flex flex-col items-end mb-2">`;
        done=false;
        if (/^ITB\$/.test(k)) {
            if (hideSingleButton) {
                const idx = k.match(/ITB\$(\d+)/);
                let key = "$ex!0";
                if (idx && idx[1]) key = `$ex!${idx[1]}`;

                var parts=k.split(";");
                if (parts.length > 1) 
                    key=key+";"+parts[1];

                if (trmObj[key]) {
                    if (Object.keys(elms).filter(k => k.startsWith('ITB$')).length > 1) {
                        if (!hasNoDisp(elms[k]) && elms[k][0]==submitClicked){
                            html += starthtml+`<div><button disabled class="px-3 py-1 rounded-xl bg-blue-600 text-white text-sm cursor-not-allowed opacity-70">${elms[k][0]}</button></div>`;
                            done=true;
                            starthtml="";
                        }
                    }
                }
            }
        }
    });
    if (done)
        html += "</div>";
  } 
  msgArea.innerHTML += html;
  chatArea.scrollTop = chatArea.scrollHeight; // Scorri in fondo
}

function scrollChatBottom() {
  const chatArea = document.getElementById('chat-area');
  if (!chatArea) return;
  requestAnimationFrame(() => {
    chatArea.scrollTop = chatArea.scrollHeight;
  });
}

function showFormSpinner(formEl) {
    if (document.getElementById('form-spinner')) return;   // già presente

    formEl.style.position = 'relative';

    const overlay = document.createElement('div');
    overlay.id = 'form-spinner';
    overlay.className = `
        absolute inset-0 z-20
        bg-white/70 dark:bg-black/60
        flex items-center justify-center
        rounded-2xl
        cursor-wait select-none
    `;
    overlay.innerHTML = `
        <svg class="w-12 h-12 animate-spin text-blue-600 dark:text-blue-300"
            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10"
                    stroke="currentColor" stroke-width="6"></circle>
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 000 8v4a8 8 0 01-8-8z"></path>
        </svg>
    `;
  formEl.appendChild(overlay);
}

function hideFormSpinner() {
  document.getElementById('form-spinner')?.remove();
}

function fitFormHeight(bar) {
  const vp = window.innerHeight;
  const headerH = document.querySelector('header')?.offsetHeight || 0;
  const chatAreaH = document.getElementById('chat-area')?.offsetHeight || 0;

  const max = vp - headerH - 10;      
  bar.style.maxHeight = max + 'px';
  bar.style.overflowY = 'auto';
}

const SID_COOKIE = 'flussu_sid';

function setCookie(name, value, days = 7) {
  const expires = days
    ? "; expires=" + new Date(Date.now() + days * 864e5).toUTCString()
    : "";
  document.cookie = `${name}=${encodeURIComponent(value || "")}${expires}; path=/`;
}

function getCookie(name) {
  const re = new RegExp('(?:^|; )' + name.replace(/[$()*+?.\\^|{}[\]]/g, '\\$&') + '=([^;]*)');
  const m = document.cookie.match(re);
  return m ? decodeURIComponent(m[1]) : null;
}

function delCookie(name) {
  document.cookie = `${name}=; Max-Age=0; path=/`;
}

function resetFlussuSession() {
  delCookie(SID_COOKIE);  
  SID = null;
  BID = null;
  startWorkflow(WID);     
};

async function loadWorkflowInfo({ titElemId, butElemId, flussuId }) {
  try {
    const res = await fetch(SERVER_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ WID: flussuId, CMD: 'info' })
    });
    const data = await res.json();

    /* ─ Titolo workflow ───────────────────────────── */
    document.getElementById(titElemId).textContent = data.tit || '';

    /* ─ Lingue permesse ───────────────────────────── */
    const allowed = (data.langs || '')
                      .split(',')
                      .map(l => l.trim())
                      .filter(Boolean);          // ["it","en",…]

    const wrap = document.getElementById(butElemId);
    if (wrap)
        wrap.innerHTML = '';                         // svuota
    allowed.forEach(lg => {
      const btn = document.createElement('button');
      btn.className =
        'lang-choice px-4 py-2 rounded bg-gray-100 dark:bg-gray-700 '+
        'text-gray-800 dark:text-gray-100 hover:bg-blue-600 hover:text-white';
      btn.dataset.lang = lg;
      btn.textContent  = BTN_LABELS[lg] || lg.toUpperCase();
      btn.addEventListener('click', () => {
        loadLanguage(lg);
        wrap.classList.add('hidden');            // chiudi dropdown
      });
      wrap.appendChild(btn);
    });
    wrap.classList.toggle('hidden', allowed.length === 0);

  } catch (e) {
    console.error('info fetch error', e);
    //eraseCookie('flussuSid');
  }
}

function markdownLinksToHtml(str) {
  return str.replace(
    /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,                     
    '<a class="text-[#000080] dark:text-blue-300 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400/70" target="_blank" rel="noopener noreferrer" href="$2">$1</a>'
  );
}

function fixPreBlocksAndHighlight() {

document.querySelectorAll('pre code').forEach((el) => {
  hljs.highlightElement(el);
});

}


// EXPORT
window.startWorkflow = startWorkflow;
window.submitFormStep = submitFormStep;
window.setLanguage = setLanguage;
window.inviaTermUpload = inviaTermUpload;
window.resetFlussuSession = resetFlussuSession;

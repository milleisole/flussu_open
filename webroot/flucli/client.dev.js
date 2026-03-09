// Nuovo client API per Flussu v4.4 - Client UNICO (chat+form)
// SCRIPT DELL?INTERFACCIA

const chatArea = document.getElementById('chat-area');
const chatForm = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');
const fileBtn = document.getElementById('file-btn');
const fileInput = document.getElementById('file-input');
const uploadStatusList = document.getElementById('upload-status-list');
const sendBtn = document.getElementById('send-btn');
const waitSpinner = document.getElementById('wait-spinner');
const formDrawer = document.getElementById('form-drawer');
const formClose = document.getElementById('form-close');
const dynamicForm = document.getElementById('dynamic-form');

let attachedFiles = []; 
let isUploading = false;

fileBtn.addEventListener('click', () => fileInput.click());
fileInput.addEventListener('change', handleFileInput);

function handleFileInput() {
  const files = Array.from(fileInput.files);
  files.forEach(f => {
    attachedFiles.push({ file: f, status: 'pending', percent: 0 });
  });
  renderUploadList();
}

function renderUploadList() {
  uploadStatusList.innerHTML = '';
  attachedFiles.forEach((fobj, idx) => {
    const f = fobj.file;
    const div = document.createElement('div');
    div.className = "flex items-center gap-2 text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 border dark:border-gray-600";
    let stato = '';
    if (fobj.status === 'uploading') {
      stato = `
        <progress max="100" value="${fobj.percent}" class="w-28 h-2"></progress>
        <span class="upload-percent w-8 text-right">${fobj.percent}%</span>
      `;
    } else if (fobj.status === 'done') {
      stato = `<span class="text-green-500 ml-2">✅</span>`;
    } else if (fobj.status === 'error') {
      stato = `<span class="text-red-500 ml-2">❌</span>`;
    }
    div.innerHTML = `
      <span class="truncate flex-1">${f.name}</span>
      <span class="text-gray-400">${(f.size/1024).toFixed(1)} KB</span>
      ${stato}
      ${isUploading ? '' : `<button type="button" class="remove-file text-red-500 px-2" data-i18n-title="remove" data-idx="${idx}">✕</button>`}
    `;
    uploadStatusList.appendChild(div);
  });
  if (!isUploading) {
    Array.from(uploadStatusList.querySelectorAll('.remove-file')).forEach(btn => {
      btn.onclick = function() {
        attachedFiles.splice(+btn.dataset.idx,1);
        renderUploadList();
        checkSendBtn();
      }
    });
  }
  checkSendBtn();
}

function checkSendBtn() {
  sendBtn.disabled = isUploading || (!chatInput.value.trim() && attachedFiles.length === 0);
}

chatForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  if (isUploading) return;
  const text = chatInput.value.trim();
  if (attachedFiles.some(f=>f.status==='pending')) {
    isUploading = true;
    checkSendBtn();
    await uploadFiles(attachedFiles.filter(f=>f.status==='pending'));
    isUploading = false;
  }
  if (text || attachedFiles.length > 0) {
    appendMessage({ role: 'user', content: text, files: [] });
    chatInput.value = "";
  }
  renderUploadList();
  checkSendBtn();
});

async function uploadFiles(filesArr) {
  for (let i = 0; i < filesArr.length; i++) {
    const fobj = filesArr[i];
    fobj.status = 'uploading';
    fobj.percent = 0;
    renderUploadList();
    await uploadSingleFileObj(fobj);
  }
}

function uploadSingleFileObj(fobj) {
  return new Promise((resolve) => {
    const formData = new FormData();
    formData.append('file', fobj.file);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload.SRV.php', true);
    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable) {
        const percent = Math.round((e.loaded / e.total) * 100);
        fobj.percent = percent;
        renderUploadList();
      }
    };
    xhr.onload = function () {
      fobj.percent = 100;
      if (xhr.status === 200) {
        let res;
        try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
        fobj.status = (res && res.status === 'ok') ? 'done' : 'error';
      } else {
        fobj.status = 'error';
      }
      renderUploadList();
      setTimeout(resolve, 600);
    };
    xhr.onerror = function () {
      fobj.status = 'error';
      renderUploadList();
      setTimeout(resolve, 600);
    };
    xhr.send(formData);
  });
}

chatInput.addEventListener('input', checkSendBtn);

document.addEventListener('dragover', function (e) { e.preventDefault(); });
document.addEventListener('drop', function (e) { e.preventDefault(); });
chatForm.addEventListener('drop', function(e) {
  e.preventDefault();
  let files = Array.from(e.dataTransfer.files);
  if (files.length) {
    files.forEach(f => {
      attachedFiles.push({ file: f, status: 'pending', percent: 0 });
    });
    renderUploadList();
  }
});

renderUploadList();
checkSendBtn();

function appendMessage({ role, content, files=[] }) {
  const tpl = document.getElementById('message-template');
  const node = tpl.content.cloneNode(true);

  node.querySelector('[data-role="role"]').textContent = (role === 'user') ? 'Tu' : 'FLUSSU';
  node.querySelector('[data-content="content"]').textContent = content;

  const filesArea = node.querySelector('[data-files="files"]');
  files.forEach(f => {
    const tplFile = document.getElementById('file-template');
    const nodeFile = tplFile.content.cloneNode(true);
    let a = nodeFile.querySelector('a');
    a.textContent = f.name;
    a.href = f.url || "#";
    filesArea.appendChild(a);
  });

  chatArea.appendChild(node);
  chatArea.scrollTop = chatArea.scrollHeight;
}

chatInput.addEventListener('input', function () {
  this.style.height = "auto";
  const maxHeight = 72; // circa 3 righe
  this.style.height = Math.min(this.scrollHeight, maxHeight) + "px";
});

document.getElementById('refresh-btn').addEventListener('click', function() {
  showAlert(LNG['confirm_restart'] || 'Vuoi davvero ricominciare la sessione?', () => {
    resetFlussuSession();
    location.reload();
  });
});

const langBtn = document.getElementById('lang-btn');
const langDropdown = document.getElementById('lang-dropdown');
const langCancel = document.getElementById('lang-cancel');
langBtn.addEventListener('click', function(e) {
  e.stopPropagation();
  langDropdown.classList.toggle('hidden');
  window.addEventListener('scroll', hideLangDropdown);
});
function hideLangDropdown() {
  langDropdown.classList.add('hidden');
  window.removeEventListener('scroll', hideLangDropdown);
}
langCancel.addEventListener('click', () => {
  langDropdown.classList.add('hidden');
});
document.addEventListener('click', function(e) {
  if (!langDropdown.classList.contains('hidden') && !langDropdown.contains(e.target) && e.target !== langBtn) {
    langDropdown.classList.add('hidden');
  }
});

document.getElementById('theme-toggle-switch').addEventListener('click', function () {
  toggleTheme();
  updateThemeSwitch();
  setTimeout(() => {
    setHighlightTheme(document.documentElement.classList.contains('dark'));
  }, 300);
});
function toggleTheme() {
  document.documentElement.classList.toggle('dark');
  localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
}
function updateThemeSwitch() {
  const knob = document.getElementById('theme-switch-knob');
  if (document.documentElement.classList.contains('dark')) {
    knob.classList.add('translate-x-3');
    knob.classList.remove('translate-x-0');
  } else {
    knob.classList.remove('translate-x-3');
    knob.classList.add('translate-x-0');
  }
}
document.addEventListener('DOMContentLoaded', () => {
    if (headParam=="none"){
        document.getElementsByTagName("header")[0].style.display="none";
    }
    updateThemeSwitch();
    document.querySelectorAll('.lang-choice').forEach(btn => {
        btn.addEventListener('click', () => {
            const lang = btn.getAttribute('data-lang');
            loadLanguage(lang);
            langDropdown.classList.add('hidden');
        });
    });
    loadLanguage("it");
});

function showAlert(msg, callback,noButtons=false) {
    const modal = document.getElementById('custom-alert');
    const content = document.getElementById('custom-alert-content');
    const okBtn = document.getElementById('custom-alert-ok');
    const abortBtn = document.getElementById('custom-alert-abort');
    content.textContent = msg;
    modal.classList.remove('hidden');
    if (noButtons==true) {
        okBtn.style.display="none";
        abortBtn.style.display="none";
    } else {
        okBtn.style.display="";
        abortBtn.style.display="";
    }
    okBtn.onclick = () => {
        modal.classList.add('hidden');
        if (callback) callback();
    };
    abortBtn.onclick = () => {
        modal.classList.add('hidden');
    };
}

window.addEventListener('resize', ()=> {
  if(window.innerWidth < 640) formDrawer.classList.add('w-full');
  else formDrawer.classList.remove('w-full');
});


let LNG = {};
let BTN_LABELS = {};
let currentLang = "it";

async function loadButtonLabels() {
  if (Object.keys(BTN_LABELS).length === 0) {
    const res = await fetch(`/flucli/langs/buttons.lng?rnd=${Date.now()}`);
    BTN_LABELS = await res.json();
  }
}

async function loadLanguage(lang) {
  await loadButtonLabels();
  const res = await fetch(`/flucli/langs/${lang}.lng?rnd=${Date.now()}`);
  LNG = (await res.json()).values || {};
  currentLang = lang;
  applyLanguage();
  translateTitleIfNeeded(lang);
}

// Applica le etichette a tutta la UI
function applyLanguage() {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (LNG[key]) el.textContent = LNG[key];
  });
  document.querySelectorAll('[data-i18n-html]').forEach(el => {
    const key = el.getAttribute('data-i18n-html');
    if (LNG[key]) el.innerHTML = LNG[key];
  });
  document.querySelectorAll('[data-i18n-ph]').forEach(el => {
    const key = el.getAttribute('data-i18n-ph');
    if (LNG[key]) el.placeholder = LNG[key];
  });
  document.querySelectorAll('[data-i18n-title]').forEach(el => {
    const key = el.getAttribute('data-i18n-title');
    if (LNG[key]) {
      el.title = LNG[key];
      el.setAttribute('aria-label', LNG[key]);
    }
  });
  document.querySelectorAll('.lang-choice').forEach(el => {
    const lang = el.getAttribute('data-lang');
    if (BTN_LABELS[lang]) el.textContent = BTN_LABELS[lang];
  });
}

async function renderLanguageButtons() {
  await loadButtonLabels();
  const langList = Object.keys(BTN_LABELS);
  const langDropdownList = document.querySelector('#lang-dropdown .flex.flex-col'); // trova il container dei bottoni lingue
  langDropdownList.innerHTML = '';
  langList.forEach(lang => {
    const btn = document.createElement('button');
    btn.className = "lang-choice px-4 py-2 rounded bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-blue-600 hover:text-white";
    btn.setAttribute('data-lang', lang);
    btn.textContent = BTN_LABELS[lang];
    langDropdownList.appendChild(btn);
  });
  langDropdownList.querySelectorAll('.lang-choice').forEach(btn => {
    btn.addEventListener('click', () => {
      const lang = btn.getAttribute('data-lang');
      loadLanguage(lang);
      langDropdown.classList.add('hidden');
    });
  });
}

// All’avvio
document.addEventListener('DOMContentLoaded', async () => {
  await renderLanguageButtons();
  await loadLanguage(localStorage.getItem("lang") || "it");
  updateThemeSwitch();

  initWorkflow(); 
});

/* --- TITLE TRANSLATE --- */
const titleTranslationCache = {};

async function translateTitleIfNeeded(targetLang) {
  const chatTitleEl = document.getElementById('chat-title');
  if (!chatTitleEl) return;

  const originalTitle = chatTitleEl.dataset.original || chatTitleEl.textContent.trim();
  chatTitleEl.dataset.original = originalTitle; 

  const cacheKey = `${originalTitle}|${targetLang}`;
  if (titleTranslationCache[cacheKey]) {
    chatTitleEl.textContent = titleTranslationCache[cacheKey];
    return;
  }

  if (targetLang === 'it') {
    chatTitleEl.textContent = originalTitle;
    return;
  }

  try {
    chatTitleEl.textContent = '...'; 
    const res = await fetch("https://translate.argosopentech.com/translate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        q: originalTitle,
        source: "auto",
        target: targetLang,
        format: "text"
      })
    });
    const data = await res.json();
    const translated = data.translatedText || originalTitle;
    chatTitleEl.textContent = translated;
    titleTranslationCache[cacheKey] = translated;
  } catch (e) {
    chatTitleEl.textContent = originalTitle;
  }
}

const PRIVACY_VERSION = "2.0";
const TOS_VERSION = "2.0";

const privacyTexts = {
  it: {
    message: 'Per usare questo sistema<br><strong>deve accettare</strong> la privacy policy e i termini di servizio.',
    checkbox_label: 'Ho letto e accetto la <a href="/flucli/privacy/priv.php?lang=it&tit=Privacy%20Policy" target="_blank" class="text-blue-600 underline">Privacy Policy</a> e i <a href="/flucli/tos/tos.php?lang=it&tit=Termini%20di%20Servizio" target="_blank" class="text-blue-600 underline">Termini di Servizio</a>',
    accept: 'Accetto',
    decline: 'Non accetto'
  },
  en: {
    message: 'To use this system you<br><strong>must accept</strong> the privacy policy and the terms of service.',
    checkbox_label: 'I have read and accept the <a href="/flucli/privacy/priv.php?lang=en&tit=Privacy%20Policy" target="_blank" class="text-blue-600 underline">Privacy Policy</a> and the <a href="/flucli/tos/tos.php?lang=en&tit=Terms%20of%20Service" target="_blank" class="text-blue-600 underline">Terms of Service</a>',
    accept: 'Accept',
    decline: 'Do not accept'
  },
  fr: {
    message: 'Pour utiliser ce système,<br><strong>vous devez accepter</strong> la politique de confidentialité et les conditions d\'utilisation.',
    checkbox_label: 'J\'ai lu et j\'accepte la <a href="/flucli/privacy/priv.php?lang=fr&tit=Confidentialit%C3%A9" target="_blank" class="text-blue-600 underline">Politique de Confidentialité</a> et les <a href="/flucli/tos/tos.php?lang=fr&tit=Conditions%20d%27utilisation" target="_blank" class="text-blue-600 underline">Conditions d\'utilisation</a>',
    accept: "J'accepte",
    decline: "Je n'accepte pas"
  },
  es: {
    message: 'Para usar este sistema,<br><strong>debes aceptar</strong> la política de privacidad y los términos de servicio.',
    checkbox_label: 'He leído y acepto la <a href="/flucli/privacy/priv.php?lang=es&tit=Privacidad" target="_blank" class="text-blue-600 underline">Política de Privacidad</a> y los <a href="/flucli/tos/tos.php?lang=es&tit=T%C3%A9rminos%20de%20Servicio" target="_blank" class="text-blue-600 underline">Términos de Servicio</a>',
    accept: 'Aceptar',
    decline: 'No aceptar'
  },
  de: {
    message: 'Um dieses System zu nutzen,<br><strong>müssen Sie</strong> die Datenschutzerklärung und die Nutzungsbedingungen akzeptieren.',
    checkbox_label: 'Ich habe die <a href="/flucli/privacy/priv.php?lang=de&tit=Datenschutzinformationen" target="_blank" class="text-blue-600 underline">Datenschutzerklärung</a> und die <a href="/flucli/tos/tos.php?lang=de&tit=Nutzungsbedingungen" target="_blank" class="text-blue-600 underline">Nutzungsbedingungen</a> gelesen und akzeptiere sie',
    accept: 'Akzeptieren',
    decline: 'Nicht akzeptieren'
  },
  zh: {
    message: '要使用此系统，<br>您必须接受隐私政策和服务条款。',
    checkbox_label: '我已阅读并接受<a href="/flucli/privacy/priv.php?lang=zh&tit=%E9%9A%90%E7%A7%81%E6%94%BF%E7%AD%96" target="_blank" class="text-blue-600 underline">隐私政策</a>和<a href="/flucli/tos/tos.php?lang=zh&tit=%E6%9C%8D%E5%8A%A1%E6%9D%A1%E6%AC%BE" target="_blank" class="text-blue-600 underline">服务条款</a>',
    accept: '接受',
    decline: '不接受'
  }
};

function getCookie(name) {

  if ((name=="privacy_accepted" || name=="tos_accepted") && iframecookie) return true;

  const cookieArr = document.cookie.split(';');
  for (let i = 0; i < cookieArr.length; i++) {
    let c = cookieArr[i].trim();
    if (c.startsWith(name + '=')) {
      return c.substring(name.length + 1);
    }
  }
  return null;
}

function setCookie(name, value, maxAgeSeconds) {
  document.cookie = `${name}=${value};max-age=${maxAgeSeconds};path=/;SameSite=Lax`;
}

var iframecookie=false;
window.addEventListener('message', function(event) {
    if (event.data === 'cookieAlreadyAccepted') {
        iframecookie=true;
        document.getElementById('privacy-modal').classList.add('hidden');
    }
});

function checkPrivacyCookie() {
    const urlParams = new URLSearchParams(window.location.search);
    const ifraValue = urlParams.get('ifra');

    // Se ifra è presente e vale "1", non mostrare il modale della privacy
    if (ifraValue === '1') {
        console.log('Parametro ifra=1 rilevato, richiesta di accettazione privacy bypassata.');
        return; // Esce dalla funzione senza mostrare il modale
    }

    const privacyAccepted = getCookie('privacy_accepted');
    const tosAccepted = getCookie('tos_accepted');
    if (!privacyAccepted || !tosAccepted) {
        document.getElementById('privacy-modal').classList.remove('hidden');
    }
}

async function sendDocumentAcceptance(acceptedDate) {
    const apiBase = window.location.origin;
    const payload = {
        acceptances: [
            {
                document_type: "privacy_policy",
                document_version: PRIVACY_VERSION,
                acceptance_method: "checkbox_click"
            },
            {
                document_type: "terms_of_service",
                document_version: TOS_VERSION,
                acceptance_method: "checkbox_click"
            }
        ]
    };

    try {
        const response = await fetch(apiBase + '/api/document-acceptances/bulk', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        if (!response.ok) {
            console.warn('Document acceptance API returned status:', response.status);
        }
    } catch (e) {
        console.warn('Could not send document acceptance to backend:', e);
    }
}

function updatePrivacyTexts(lang) {
  const txt = privacyTexts[lang] || privacyTexts['en'];
  const container = document.getElementById('privacy-message');
  container.innerHTML = `
    <div class="mb-4">${txt.message}</div>
    <label class="flex items-start gap-2 text-left cursor-pointer mb-4">
      <input type="checkbox" id="privacy-tos-checkbox" class="mt-1 w-4 h-4 accent-blue-600 flex-shrink-0">
      <span class="text-sm">${txt.checkbox_label}</span>
    </label>
  `;
  const acceptBtn = document.getElementById('privacy-accept');
  acceptBtn.textContent = txt.accept;
  acceptBtn.disabled = true;
  acceptBtn.classList.add('opacity-50', 'cursor-not-allowed');
  document.getElementById('privacy-decline').textContent = txt.decline;

  // Enable/disable accept button based on checkbox
  const checkbox = document.getElementById('privacy-tos-checkbox');
  checkbox.addEventListener('change', () => {
    if (checkbox.checked) {
      acceptBtn.disabled = false;
      acceptBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
      acceptBtn.disabled = true;
      acceptBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
  });
}

// --- Al caricamento della pagina ---
document.addEventListener('DOMContentLoaded', () => {
    const langSelect = document.getElementById('privacy-lang-select');
    updatePrivacyTexts('en');
    checkPrivacyCookie();
    langSelect.addEventListener('change', (e) => {
        const chosen = e.target.value;
        updatePrivacyTexts(chosen);
    });
    // Bottone “Accetto”
    document.getElementById('privacy-accept').addEventListener('click', () => {
        const checkbox = document.getElementById('privacy-tos-checkbox');
        if (!checkbox || !checkbox.checked) return;

        const threeMonthsInSec = 90 * 24 * 60 * 60;
        const acceptedDate = new Date().toISOString();
        setCookie('privacy_accepted', acceptedDate, threeMonthsInSec);
        setCookie('tos_accepted', acceptedDate, threeMonthsInSec);
        document.getElementById('privacy-modal').classList.add('hidden');

        // Send acceptance data to backend API
        sendDocumentAcceptance(acceptedDate);

        try{
            window.parent.postMessage('cookieAccepted', '*');
        } catch (e) {
            console.warn('Impossibile inviare il messaggio al parent:', e);
        }
    });
    // Bottone “Non accetto”
    document.getElementById('privacy-decline').addEventListener('click', () => {
        const lang = document.getElementById('privacy-lang-select').value;
        window.location.href = `/flucli/privacy/priv.php?lang=${lang}&tit=nc&pn=c`;
    });
});

function setHighlightTheme(dark) {
  const oldTheme = document.getElementById('hljs-theme');
  if (oldTheme) oldTheme.remove();

  const link = document.createElement('link');
  link.id = 'hljs-theme';
  link.rel = 'stylesheet';
  link.href = dark
    ? 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css'
    : 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css';

  document.head.appendChild(link);
}
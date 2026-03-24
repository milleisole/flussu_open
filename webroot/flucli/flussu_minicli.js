/**
 * Flussu MiniCli — widget inline minimale per workflow Flussu.
 *
 * USO:
 *   <script src="https://SERVER/flucli/flussu_minicli.js"
 *           server="SERVER" wid="WID" lang="it"></script>
 *   ...
 *   <div class="flussu-minicli"></div>
 *
 *   Attributi opzionali sul div:
 *     data-wid="..."            Override WID dello script tag
 *     data-lang="..."           Override lingua dello script tag
 *     data-input-rows="1-3"     Righe iniziali textarea (default 1)
 *     data-response-rows="1-10" Righe iniziali area risposta (default 1)
 *
 * Nessuna richiesta parte verso il server fino a quando l'utente non invia un messaggio.
 */
(function () {
  /* ── Lingue supportate ───────────────────────────────────── */
  var SUPPORTED_LANGS = ['it', 'en', 'fr', 'de', 'es'];

  var I18N = {
    it: { placeholder: 'Scrivi qui\u2026',   ended: 'Conversazione terminata', error: 'Errore di comunicazione',   expired: 'Sessione scaduta' },
    en: { placeholder: 'Type here\u2026',    ended: 'Conversation ended',      error: 'Communication error',      expired: 'Session expired' },
    fr: { placeholder: '\u00C9crivez ici\u2026', ended: 'Conversation termin\u00E9e', error: 'Erreur de communication', expired: 'Session expir\u00E9e' },
    de: { placeholder: 'Hier schreiben\u2026', ended: 'Gespr\u00E4ch beendet',  error: 'Kommunikationsfehler',     expired: 'Sitzung abgelaufen' },
    es: { placeholder: 'Escribe aqu\u00ED\u2026', ended: 'Conversaci\u00F3n terminada', error: 'Error de comunicaci\u00F3n', expired: 'Sesi\u00F3n expirada' }
  };

  function t(lang, key) {
    return (I18N[lang] || I18N.it)[key] || (I18N.it)[key] || key;
  }

  /* ── Rileva lingua dalla pagina ──────────────────────────── */
  function detectPageLang() {
    var raw = (document.documentElement.lang || navigator.language || 'it').toLowerCase().slice(0, 2);
    return SUPPORTED_LANGS.indexOf(raw) !== -1 ? raw : null;
  }

  /* ── Configurazione dallo script tag ─────────────────────── */
  var scriptTag =
    document.currentScript ||
    document.querySelector('script[src*="flussu_minicli"]');

  var CFG = {
    server:  scriptTag ? scriptTag.getAttribute('server') : null,
    wid:     scriptTag ? scriptTag.getAttribute('wid')    : '',
    lang:    scriptTag ? scriptTag.getAttribute('lang')   : null,
    apipath: scriptTag ? scriptTag.getAttribute('apipath') : null,
  };

  // Lingua: attributo script > lingua pagina > 'it'
  if (!CFG.lang) CFG.lang = detectPageLang() || 'it';
  if (!CFG.server) CFG.server = location.hostname;
  if (!CFG.apipath) CFG.apipath = '/api/v2.0/flussueng.php';

  var API_URL = 'https://' + CFG.server + CFG.apipath;

  /* ── Utilità cookie (per-WID) ────────────────────────────── */
  function sidCookieName(wid) { return 'flussu_sid_' + (wid || '').replace(/[^a-zA-Z0-9]/g, ''); }

  function getCookie(n) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + n.replace(/[$()*+?.\\^|{}\[\]]/g, '\\$&') + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }
  function setCookie(n, v, days) {
    days = days || 7;
    var exp = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = n + '=' + encodeURIComponent(v) + '; expires=' + exp + '; path=/';
  }
  function delCookie(n) {
    document.cookie = n + '=; Max-Age=0; path=/';
  }

  /* ── Encoding ────────────────────────────────────────────── */
  function encodeBody(obj) {
    return Object.keys(obj).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]);
    }).join('&');
  }

  /* ── Clamp helper ────────────────────────────────────────── */
  function clamp(val, min, max) {
    var n = parseInt(val, 10);
    if (isNaN(n)) return min;
    return Math.max(min, Math.min(max, n));
  }

  /* ── Stili (iniettati una sola volta) ────────────────────── */
  var stylesInjected = false;
  function injectStyles() {
    if (stylesInjected) return;
    stylesInjected = true;
    var s = document.createElement('style');
    s.textContent =
'/* Flussu MiniCli */\n' +
'.flussu-mc{font-family:system-ui,-apple-system,sans-serif;font-size:14px;line-height:1.45;box-sizing:border-box;width:100%}\n' +
'.flussu-mc *{box-sizing:border-box}\n' +
'.flussu-mc-response{\n' +
'  min-height:1.45em;padding:6px 10px;\n' +
'  border:1px solid #d0d0d0;border-radius:6px 6px 0 0;\n' +
'  background:#fafafa;color:#222;\n' +
'  user-select:text;-webkit-user-select:text;\n' +
'  overflow-y:auto;white-space:pre-wrap;word-wrap:break-word;\n' +
'  transition:min-height .15s ease;\n' +
'}\n' +
'.flussu-mc-response:empty::before{content:"\\00a0"}\n' +
'.flussu-mc-response a{color:#2563eb;text-decoration:underline}\n' +
'.flussu-mc-row{display:flex;align-items:flex-end;border:1px solid #d0d0d0;border-top:none;border-radius:0 0 6px 6px;background:#fff;overflow:hidden}\n' +
'.flussu-mc-input{\n' +
'  flex:1;resize:none;border:none;outline:none;\n' +
'  padding:6px 10px;font:inherit;line-height:1.45;\n' +
'  min-height:1.45em;\n' +
'  overflow-y:auto;background:transparent;color:#222;\n' +
'  transition:min-height .15s ease;\n' +
'}\n' +
'.flussu-mc-input::placeholder{color:#999}\n' +
'.flussu-mc-input:disabled{background:#f5f5f5;color:#999}\n' +
'.flussu-mc-send{\n' +
'  flex-shrink:0;border:none;background:#2563eb;color:#fff;\n' +
'  cursor:pointer;padding:6px 14px;font:inherit;font-size:13px;\n' +
'  display:flex;align-items:center;justify-content:center;\n' +
'  transition:background .15s;align-self:stretch;\n' +
'}\n' +
'.flussu-mc-send:hover{background:#1d4ed8}\n' +
'.flussu-mc-send:disabled{background:#93a3b8;cursor:default}\n' +
'.flussu-mc-send svg{width:16px;height:16px;fill:currentColor}\n' +
'.flussu-mc-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:flussu-mc-spin .6s linear infinite}\n' +
'@keyframes flussu-mc-spin{to{transform:rotate(360deg)}}\n';
    document.head.appendChild(s);
  }

  /* ── Classe widget ───────────────────────────────────────── */
  function MiniCli(container) {
    this.el = container;
    this.wid = container.getAttribute('data-wid') || CFG.wid;
    this.lang = container.getAttribute('data-lang') || CFG.lang;

    // Righe iniziali configurabili
    this.initInputRows = clamp(container.getAttribute('data-input-rows'), 1, 3);
    this.initResponseRows = clamp(container.getAttribute('data-response-rows'), 1, 10);
    this.maxResponseRows = 10;

    // Sessione per-WID
    this.cookieName = sidCookieName(this.wid);
    this.sid = getCookie(this.cookieName) || '';
    this.bid = '';
    this.currentFields = null;
    this.initialized = false;
    this.busy = false;
    this.ended = false;
    this.responseMinH = 0;
    this.inputMinH = 0;

    this._build();
  }

  /* ── Costruzione DOM ─────────────────────────────────────── */
  MiniCli.prototype._build = function () {
    this.el.innerHTML = '';
    this.el.classList.add('flussu-mc');

    // Area risposta
    this.respArea = document.createElement('div');
    this.respArea.className = 'flussu-mc-response';
    this.el.appendChild(this.respArea);

    // Riga input
    var row = document.createElement('div');
    row.className = 'flussu-mc-row';

    this.input = document.createElement('textarea');
    this.input.className = 'flussu-mc-input';
    this.input.rows = 1;
    this.input.placeholder = t(this.lang, 'placeholder');
    row.appendChild(this.input);

    this.sendBtn = document.createElement('button');
    this.sendBtn.className = 'flussu-mc-send';
    this.sendBtn.type = 'button';
    this.sendBtn.innerHTML = '<svg viewBox="0 0 20 20"><path d="M2.5 17.5l15-7.5-15-7.5v6l10 1.5-10 1.5z"/></svg>';
    row.appendChild(this.sendBtn);

    this.el.appendChild(row);

    // Applica righe iniziali
    this._applyInitialRows();

    // Eventi
    var self = this;
    this.input.addEventListener('input', function () { self._autoGrowInput(); });
    this.input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); self._onSend(); }
    });
    this.sendBtn.addEventListener('click', function () { self._onSend(); });
  };

  /* ── Applica righe iniziali configurate ──────────────────── */
  MiniCli.prototype._applyInitialRows = function () {
    var lineH = 20.3; // 14px * 1.45 line-height
    var pad = 12;      // padding top+bottom

    if (this.initResponseRows > 1) {
      var respH = lineH * this.initResponseRows + pad;
      this.respArea.style.minHeight = respH + 'px';
      this.responseMinH = respH;
    }

    if (this.initInputRows > 1) {
      var inputH = lineH * this.initInputRows + pad;
      this.input.style.minHeight = inputH + 'px';
      this.inputMinH = inputH;
    }
  };

  /* ── Auto-grow textarea (max 3 righe, mai rimpicciolisce) ── */
  MiniCli.prototype._autoGrowInput = function () {
    var ta = this.input;
    ta.style.height = 'auto';
    var scrollH = ta.scrollHeight;
    var lineH = parseFloat(getComputedStyle(ta).lineHeight) || 20;
    var maxH = lineH * 3 + 12;
    var newH = Math.min(scrollH, maxH);
    if (newH > this.inputMinH) this.inputMinH = newH;
    ta.style.height = Math.max(newH, this.inputMinH) + 'px';
    ta.style.minHeight = this.inputMinH + 'px';
  };

  /* ── Grow area risposta (max 10 righe, mai rimpicciolisce) ─ */
  MiniCli.prototype._fitResponse = function () {
    var el = this.respArea;
    el.style.height = 'auto';
    var scrollH = el.scrollHeight;
    var lineH = parseFloat(getComputedStyle(el).lineHeight) || 20;
    var maxH = lineH * this.maxResponseRows + 12;
    var newH = Math.min(scrollH, maxH);
    if (newH > this.responseMinH) this.responseMinH = newH;
    el.style.minHeight = this.responseMinH + 'px';
    if (scrollH > maxH) {
      el.style.height = maxH + 'px';
    }
  };

  /* ── Cambio lingua runtime ───────────────────────────────── */
  MiniCli.prototype.setLang = function (lang) {
    lang = (lang || '').toLowerCase().slice(0, 2);
    if (SUPPORTED_LANGS.indexOf(lang) === -1) return;
    this.lang = lang;
    this.input.placeholder = t(lang, 'placeholder');
  };

  /* ── Invio messaggio ─────────────────────────────────────── */
  MiniCli.prototype._onSend = function () {
    if (this.ended) return;
    var text = this.input.value.trim();
    if (!text || this.busy) return;
    this.busy = true;
    this._showSpinner();

    var self = this;
    if (!this.initialized) {
      this._apiCall({}, function (json) {
        self.initialized = true;
        self._processResponse(json);
        if (self.ended) {
          self.busy = false;
          self._hideSpinner();
          return;
        }
        self._sendUserText(text);
      });
    } else {
      this._sendUserText(text);
    }
  };

  /* ── Invia testo utente mappato sui campi correnti ──────── */
  MiniCli.prototype._sendUserText = function (text) {
    var trmObj = {};
    var fields = this.currentFields || {};

    var ittKey = null;
    var itbKey = null;
    var keys = Object.keys(fields);
    for (var i = 0; i < keys.length; i++) {
      if (/^ITT\$/.test(keys[i]) && !ittKey) ittKey = keys[i];
      if (/^ITB\$/.test(keys[i]) && !itbKey) itbKey = keys[i];
    }

    if (ittKey) {
      trmObj[ittKey.replace(/^ITT\$/, '$')] = text;
    } else {
      trmObj['$1'] = text;
    }

    if (itbKey) {
      var idx = itbKey.match(/ITB\$(\d+)/);
      var exKey = '$ex!' + (idx ? idx[1] : '0');
      trmObj[exKey] = fields[itbKey][0] || 'OK';
    } else {
      trmObj['$ex!0'] = 'OK';
    }

    var self = this;
    this._apiCall(trmObj, function (json) {
      self._processResponse(json);
      self.busy = false;
      self._hideSpinner();
      if (!self.ended) {
        self.input.value = '';
        self.input.style.height = 'auto';
        self.input.focus();
      }
    });
  };

  /* ── Rileva fine workflow ─────────────────────────────────── */
  MiniCli.prototype._checkEnd = function (elms) {
    if (!elms) return true;
    var keys = Object.keys(elms);
    if (!keys.length) return true;
    // END$ esplicito
    if (elms['END$']) return true;
    // Label con testo "finiu"
    for (var i = 0; i < keys.length; i++) {
      var k = keys[i];
      if (/^L\$/.test(k) && Array.isArray(elms[k]) && elms[k][0]) {
        if (elms[k][0].trim().toLowerCase() === 'finiu') return true;
      }
    }
    return false;
  };

  /* ── Disabilita widget a fine workflow ────────────────────── */
  MiniCli.prototype._disableInput = function () {
    this.ended = true;
    this.input.disabled = true;
    this.input.placeholder = t(this.lang, 'ended');
    this.sendBtn.disabled = true;
  };

  /* ── Processa risposta server ────────────────────────────── */
  MiniCli.prototype._processResponse = function (json) {
    if (!json) return;
    if (json.sid) { this.sid = json.sid; setCookie(this.cookieName, json.sid); }
    if (json.bid) this.bid = json.bid;
    if (json.lng) this.lang = json.lng;

    var elms = json.elms;

    // Fine workflow?
    if (this._checkEnd(elms)) {
      // Mostra eventuale ultimo testo prima di disabilitare
      this._showLabels(elms);
      this._disableInput();
      return;
    }

    this.currentFields = elms;
    this._showLabels(elms);
  };

  /* ── Mostra testo dalle label L$ ─────────────────────────── */
  MiniCli.prototype._showLabels = function (elms) {
    if (!elms) return;
    var texts = [];
    var keys = Object.keys(elms);
    for (var i = 0; i < keys.length; i++) {
      var k = keys[i];
      if (!/^L\$/.test(k)) continue;
      var arr = elms[k];
      if (!Array.isArray(arr) || arr.length < 2) continue;
      var cls = (arr[1] && arr[1].class) || '';
      if (cls === 'noshow') continue;
      var label = (arr[0] || '').trim();
      if (label && label.toLowerCase() !== 'finiu') texts.push(label);
    }
    if (texts.length) {
      this.respArea.innerHTML = texts.join('<br>');
      this._fitResponse();
    }
  };

  /* ── Chiamata API ────────────────────────────────────────── */
  MiniCli.prototype._apiCall = function (termObj, callback) {
    if (!this.sid) {
      termObj['$isForm'] = 'true';
      termObj['$_FD0508'] = window.location.href;
    }

    var payload = {
      WID: this.wid,
      SID: this.sid || '',
      BID: this.bid || '',
      LNG: this.lang || '',
      APP: 'CHAT',
      TRM: JSON.stringify(termObj)
    };

    var self = this;
    fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: encodeBody(payload)
    })
    .then(function (res) {
      if (!res.ok) throw new Error(res.status);
      return res.json();
    })
    .then(function (json) {
      if (json.error) {
        if (json.error.indexOf('E89') !== -1) {
          // Sessione scaduta: reset
          delCookie(self.cookieName);
          self.sid = '';
          self.bid = '';
          self.initialized = false;
          self.currentFields = null;
          self.respArea.textContent = t(self.lang, 'expired');
          self._fitResponse();
        } else {
          self.respArea.textContent = json.error;
          self._fitResponse();
        }
        self.busy = false;
        self._hideSpinner();
        return;
      }
      if (callback) callback(json);
    })
    .catch(function (err) {
      self.respArea.textContent = t(self.lang, 'error') + ' (' + err.message + ')';
      self._fitResponse();
      self.busy = false;
      self._hideSpinner();
    });
  };

  /* ── Spinner ─────────────────────────────────────────────── */
  MiniCli.prototype._showSpinner = function () {
    this.sendBtn.disabled = true;
    this.sendBtn.innerHTML = '<span class="flussu-mc-spinner"></span>';
  };
  MiniCli.prototype._hideSpinner = function () {
    if (this.ended) return;
    this.sendBtn.disabled = false;
    this.sendBtn.innerHTML = '<svg viewBox="0 0 20 20"><path d="M2.5 17.5l15-7.5-15-7.5v6l10 1.5-10 1.5z"/></svg>';
  };

  /* ── Init ────────────────────────────────────────────────── */
  function initAll() {
    injectStyles();
    var divs = document.querySelectorAll('.flussu-minicli');
    for (var i = 0; i < divs.length; i++) {
      if (!divs[i]._flussuMC) {
        divs[i]._flussuMC = new MiniCli(divs[i]);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();

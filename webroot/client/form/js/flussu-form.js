/*************************************************************************
Flussu Client Script v4.0 - Complete Implementation
Compatible with Flussu Server v4.2
Modern, responsive, Typeform-inspired interface
Copyright (C) 2021-2025 Mille Isole SRL - Palermo (Italy)
*************************************************************************/

console.log('[Flussu] Loading v4.0...');

// Platform detection
window.mobileCheck = function() {
    let check = false;
    (function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4))) check = true;})(navigator.userAgent||navigator.vendor||window.opera);
    return check;
};

// Global variables
const FlussuClient = {
    version: "4.0",
    serverVersion: "4.2",
    isMobile: window.mobileCheck(),
    config: {
        srv: location.protocol + "//" + window.location.host + "/",
        api: null,
        notifServer: null,
        notifTime: 5000,
        isIframe: false,
        animations: true,
        sound: false,
        displayTitle: true
    },
    state: {
        wid: null,
        sid: null,
        bid: null,
        lang: "IT",
        isInit: false,
        eventSource: null,
        currentStep: 0,
        totalSteps: 0,
        startData: null
    },
    ui: {
        container: null,
        environment: null,
        startArea: null,
        title: null,
        progress: null,
        notify: null
    }
};

// Initialize API endpoints
FlussuClient.config.api = FlussuClient.config.srv + "api/v2.0/";
FlussuClient.config.notifServer = FlussuClient.config.srv + "notify";

// URL parameter helper
const getUrlParam = (name) => {
    const results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
    return results ? decodeURI(results[1]) : null;
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Flussu] DOM Ready');
    
    // Check for URL parameters
    FlussuClient.state.sid = getUrlParam('SID');
    FlussuClient.state.bid = getUrlParam('BID');
    FlussuClient.state.lang = getUrlParam('lang') || FlussuClient.state.lang;
    
    // Build interface
    buildInterface();
    
    // Auto-init if WID is already set
    if (FlussuClient.state.wid && !FlussuClient.state.isInit) {
        setTimeout(() => {
            initWorkflow();
        }, 100);
    }
});

// Build the main interface
function buildInterface() {
    const container = document.getElementById('flussu-form');
    if (!container) {
        console.error('[Flussu] Container element #flussu-form not found');
        return;
    }
    
    const html = `
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="${FlussuClient.config.srv}client/form/css/flussu-form.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" href="${FlussuClient.config.srv}client/assets/img/favicon.png">
    <script>
        // Patch per CSS.escape se non supportato
        if (!CSS.escape) {
            CSS.escape = function(value) {
                return value.replace(/^(\d)/, '\\3$1 ');
            };
        }
        // Applica dark mode immediatamente per evitare flash
        (function() {
            const savedPref = localStorage.getItem('flussuDarkMode');
            const isDark = savedPref ? savedPref === 'true' : 
                window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (isDark) {
                document.body.classList.add('dark-mode');
            }
        })();
    </script>
    <div id="flussu-app" class="flussu-app">
        <!-- Header -->
        <header class="flussu-header" ${!FlussuClient.config.displayTitle ? 'style="display:none;"' : ''}>
            <div class="flussu-header-content">
                <button id="flussu-menu" class="flussu-menu-btn" aria-label="Menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <h1 id="flussuTitle" class="flussu-title">Loading...</h1>
            </div>
            <div id="flussuProgress" class="flussu-progress">
                <div class="flussu-progress-bar"></div>
            </div>
        </header>

        <!-- Menu Sidebar -->
        <aside id="flussu-sidebar" class="flussu-sidebar">
            <div class="flussu-sidebar-content">
                <button class="flussu-sidebar-close" onclick="FlussuClient.ui.sidebar.classList.remove('open')">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <nav class="flussu-nav">
                    <div class="flussu-nav-section">
                        <!--<h3 class="flussu-nav-title">Language</h3>-->
                        <div id="flussu-language-buttons" class="flussu-language-buttons">
                            <!-- Language buttons will be added dynamically -->
                        </div>
                    </div>
                    
                    <div class="flussu-nav-section">
                        <!--<h3 class="flussu-nav-title">Options</h3>-->
                        <button class="flussu-nav-item" onclick="window.resetWorkflow()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                                <path d="M3 3v5h5"></path>
                            </svg>
                            <span>Reset</span>
                        </button>
                    </div>
                    
                    <div class="flussu-nav-section">
                        <!--<h3 class="flussu-nav-title">Display</h3>-->
                        <div class="flussu-nav-item">
                            <span>Dark Mode</span>
                            <label class="flussu-switch">
                                <input type="checkbox" id="darkModeToggle" onchange="window.toggleDarkMode()">
                                <span class="flussu-switch-slider"></span>
                            </label>
                        </div>
                    </div>
                </nav>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flussu-main">
            <div class="flussu-content">
                <!-- Start Area -->
                <div id="flussu-startarea" class="flussu-start-area"></div>
                
                <!-- Form Environment -->
                <div id="flussu-environment" class="flussu-environment" style="display:none;"></div>
            </div>
        </main>
        
        <!-- Notifications -->
        <div id="flussuNotifyAlert" class="flussu-notify"></div>
        
        <!-- Loading Overlay -->
        <div id="flussu-loading" class="flussu-loading">
            <div class="flussu-spinner"></div>
        </div>
        
    </div>
    
    <!-- Image Lightbox -->
    <div id="flussu-lightbox" class="flussu-lightbox" onclick="closeLightbox()">
        <img id="flussu-lightbox-img" src="" alt="">
    </div>
    `;
    
    container.innerHTML = html;
    
    // Cache UI elements
    FlussuClient.ui = {
        container: document.getElementById('flussu-app'),
        environment: document.getElementById('flussu-environment'),
        startArea: document.getElementById('flussu-startarea'),
        title: document.getElementById('flussuTitle'),
        progress: document.getElementById('flussuProgress'),
        notify: document.getElementById('flussuNotifyAlert'),
        loading: document.getElementById('flussu-loading'),
        sidebar: document.getElementById('flussu-sidebar')
    };
    
    // Initialize event listeners
    initializeEventListeners();
    
    console.log('[Flussu] Interface built');
}

// Initialize event listeners
function initializeEventListeners() {
    // Menu button
    const menuBtn = document.getElementById('flussu-menu');
    if (menuBtn) {
        menuBtn.addEventListener('click', () => {
            FlussuClient.ui.sidebar.classList.toggle('open');
        });
    }
    
    loadDarkModePreference();

    // Keyboard navigation
    document.addEventListener('keydown', handleKeyPress);
    
    // Image clicks for lightbox
    document.addEventListener('click', function(e) {
        if (e.target.matches('.flussu-image')) {
            openLightbox(e.target.src);
        }
    });

    if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        // Solo se l'utente non ha mai scelto manualmente
        if (localStorage.getItem('flussuDarkMode') === null) {
            const isDark = e.matches;
            document.getElementById('darkModeToggle').checked = isDark;
            document.body.classList.toggle('dark-mode', isDark);
        }
    });
}
}

// Handle keyboard events
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.target.matches('textarea')) {
        event.preventDefault();
        // Find the primary button in the current form
        const primaryBtn = document.querySelector('.flussu-btn-primary');
        if (primaryBtn) {
            primaryBtn.click();
        }
    }
}

// Initialize workflow
async function initWorkflow() {
    console.log('[Flussu] Initializing workflow...');
    FlussuClient.state.isInit = true;
    
    showLoading();
    
    // Check for existing session
    const savedSession = getSession();
    if (savedSession && savedSession.wid === FlussuClient.state.wid) {
        FlussuClient.state = { ...FlussuClient.state, ...savedSession };
        await executeWorkflow();
    } else {
        await getWorkflowInfo();
    }
}

// Get workflow information
async function getWorkflowInfo() {
    console.log('[Flussu] Getting workflow info for WID:', FlussuClient.state.wid);
    
    try {
        const response = await fetch(FlussuClient.config.api + 'flussueng.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                WID: FlussuClient.state.wid,
                CMD: 'info'
            })
        });
        
        const data = await response.json();
        console.log('[Flussu] Workflow info:', data);
        
        if (FlussuClient.config.displayTitle && data.tit) {
            FlussuClient.ui.title.textContent = data.tit;
        }
        
        // Save and update languages
        if (data.langs) {
            const languages = data.langs.split(',');
            sessionStorage.setItem('flussuLangs', data.langs);
            updateLanguageButtons(languages);
        }
        
        if (FlussuClient.state.sid) {
            await executeWorkflow();
        } else {
            showLanguageSelection(data.langs.split(','));
        }
        
    } catch (error) {
        console.error('[Flussu] Error getting workflow info:', error);
        showError('Connection error. Please try again.');
    } finally {
        hideLoading();
    }
}

// Update language buttons in sidebar
function updateLanguageButtons(languages) {
    const container = document.getElementById('flussu-language-buttons');
    if (!container) return;
    
    let html = '';
    const langNames = {
        'IT': 'Italiano',
        'EN': 'English',
        'FR': 'Français',
        'DE': 'Deutsch',
        'ES': 'Español'
    };
    
    // Mapping corretti per le bandiere
    const flagCodes = {
        'IT': 'it',
        'EN': 'gb',  // United Kingdom flag for English
        'FR': 'fr',
        'DE': 'de',
        'ES': 'es'
    };
    
    languages.forEach(lang => {
        const isActive = lang === FlussuClient.state.lang;
        const flagCode = flagCodes[lang] || lang.toLowerCase();
        html += `
            <button class="flussu-lang-nav-btn ${isActive ? 'active' : ''}" 
                    onclick="window.changeLanguage('${lang}')"
                    ${isActive ? 'disabled' : ''}>
                <img src="https://flagcdn.com/24x18/${flagCode}.png" 
                     alt="${lang}" class="flussu-flag-small">
                <span>${langNames[lang] || lang}</span>
            </button>
        `;
    });
    
    container.innerHTML = html;
}

// Change language
window.changeLanguage = async function(lang) {
    if (lang === FlussuClient.state.lang) return;
    
    console.log('[Flussu] Changing language to:', lang);
    FlussuClient.state.lang = lang;
    
    // Close sidebar
    FlussuClient.ui.sidebar.classList.remove('open');
    
    // Re-execute with same SID and BID but new language
    await executeWorkflow();
};

// Reset workflow
window.resetWorkflow = async function() {
    console.log('[Flussu] Resetting workflow...');
    
    // Clear session
    FlussuClient.state.sid = '';
    FlussuClient.state.bid = '';
    clearSession();
    
    // Close sidebar
    FlussuClient.ui.sidebar.classList.remove('open');
    
    // Re-initialize
    await initWorkflow();
};

// Toggle dark mode
window.toggleDarkMode = function() {
    const isDark = document.getElementById('darkModeToggle').checked;
    document.body.classList.toggle('dark-mode', isDark);
    // Salva la preferenza esplicita dell'utente
    localStorage.setItem('flussuDarkMode', isDark ? 'true' : 'false');
    localStorage.setItem('flussuDarkModeExplicit', 'true'); // L'utente ha scelto
    
    setTimeout(() => {
        FlussuClient.ui.sidebar.classList.remove('open');
    }, 300);
};

// Load dark mode preference on init
function loadDarkModePreference() {
    // Prima controlla se c'è una preferenza salvata
    const savedPreference = localStorage.getItem('flussuDarkMode');
    
    let isDark;
    if (savedPreference !== null) {
        // Se c'è una preferenza salvata, usala
        isDark = savedPreference === 'true';
    } else {
        // Altrimenti usa la preferenza del sistema
        isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    
    document.getElementById('darkModeToggle').checked = isDark;
    document.body.classList.toggle('dark-mode', isDark);
}

// Show language selection
function showLanguageSelection(languages) {
    let html = '<div class="flussu-language-select">';
    html += '<h2 class="text-2xl font-bold mb-6 text-center">Select Language</h2>';
    html += '<div class="flussu-lang-grid">';
    
    const langNames = {
        'IT': 'Italiano',
        'EN': 'English',
        'FR': 'Français',
        'DE': 'Deutsch',
        'ES': 'Español'
    };
    
    // Mapping corretti per le bandiere
    const flagCodes = {
        'IT': 'it',
        'EN': 'gb',  // United Kingdom flag for English
        'FR': 'fr',
        'DE': 'de',
        'ES': 'es'
    };
    
    languages.forEach(lang => {
        const flagCode = flagCodes[lang] || lang.toLowerCase();
        html += `
            <button class="flussu-lang-btn" onclick="window.startWorkflowWithLang('${lang}')">
                <img src="https://flagcdn.com/48x36/${flagCode}.png" 
                     alt="${lang}" class="flussu-flag">
                <span>${langNames[lang] || lang}</span>
            </button>
        `;
    });
    
    html += '</div></div>';
    
    FlussuClient.ui.startArea.innerHTML = html;
    FlussuClient.ui.startArea.style.display = 'block';
    FlussuClient.ui.environment.style.display = 'none';
}

// Start workflow with language
window.startWorkflowWithLang = async function(lang) {
    console.log('[Flussu] Starting workflow with language:', lang);
    FlussuClient.state.lang = lang;
    await executeWorkflow();
};

// Execute workflow step
async function executeWorkflow() {
    console.log('[Flussu] Executing workflow step...');
    showLoading();
    
    const params = new URLSearchParams();
    params.append('WID', FlussuClient.state.wid);
    params.append('SID', FlussuClient.state.sid || '');
    params.append('BID', FlussuClient.state.bid || '');
    params.append('LNG', FlussuClient.state.lang);
    params.append('APP', 'WEB');

    // Gestisci TRM in modo diverso
    if (FlussuClient.state.terms) {
        params.append('TRM', FlussuClient.state.terms);
    } else if (FlussuClient.state.startData && !FlussuClient.state.sid) {
        params.append('TRM', FlussuClient.state.startData);
        FlussuClient.state.startData = null;
    } else {
        params.append('TRM', '');
    }
    
    // Debug: mostra esattamente cosa stiamo inviando
    console.log('[Flussu] Request URL:', FlussuClient.config.api + 'flussueng.php');
    console.log('[Flussu] Request params:', params.toString());
    console.log('[Flussu] Request params decoded:');
    for (let [key, value] of params) {
        console.log(`  ${key}:`, value);
        if (key === 'TRM' && value) {
            try {
                console.log('  TRM parsed:', JSON.parse(value));
            } catch (e) {
                console.log('  TRM is not valid JSON');
            }
        }
    }
    
    try {
        const response = await fetch(FlussuClient.config.api + 'flussueng.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: params
        });
        
        const responseText = await response.text();
        console.log('[Flussu] Response status:', response.status);
        console.log('[Flussu] Response headers:', response.headers);
        console.log('[Flussu] Raw response:', responseText);
        
        if (!response.ok) {
            // Prova a vedere se c'è un messaggio di errore nel response
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = JSON.parse(responseText);
                if (errorData.error) {
                    errorMessage = errorData.error;
                }
            } catch (e) {
                // Se non è JSON, usa il testo raw
                if (responseText) {
                    errorMessage += ' - ' + responseText;
                }
            }
            throw new Error(errorMessage);
        }
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('[Flussu] Invalid JSON response:', responseText);
            throw new Error('Invalid response from server');
        }
        
        console.log('[Flussu] Response data:', data);
        processWorkflowResponse(data);
        
    } catch (error) {
        console.error('[Flussu] Execute workflow error:', error);
        showError('Error: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Process workflow response
function processWorkflowResponse(data) {
    // Controlla se è un messaggio di fine workflow
    if (data[""] && Array.isArray(data[""]) && 
        data[""].includes("finiu") && data[""].includes("stop")) {
        console.log('[Flussu] Workflow completed - showing thank you screen');
        showThankYouScreen();
        return;
    }
    
    if (data.error) {
        console.error('[Flussu] Server error:', data.error);
        if (data.error.includes('E89')) {
            clearSession();
            showError('Session expired. Please start again.');
            setTimeout(() => location.reload(), 3000);
        } else {
            showError(data.error);
        }
        return;
    }
    
    if (!data.sid) {
        console.log('[Flussu] No session ID, restarting...');
        clearSession();
        initWorkflow();
        return;
    }
    
    // Update state
    FlussuClient.state.sid = data.sid;
    FlussuClient.state.bid = data.bid;
    
    // Save session
    saveSession();
    
    // Update language buttons to reflect current language
    if (FlussuClient.state.wid) {
        // Get workflow info to get available languages
        const savedLangs = sessionStorage.getItem('flussuLangs');
        if (savedLangs) {
            updateLanguageButtons(savedLangs.split(','));
        }
    }
    
    // Clear terms for next request
    FlussuClient.state.terms = '';
    
    // Render elements
    renderElements(data.elms);
}

// Show thank you screen
function showThankYouScreen() {
    // Nascondi area di start
    FlussuClient.ui.startArea.style.display = 'none';
    
    // Mostra l'environment con il messaggio di ringraziamento
    FlussuClient.ui.environment.style.display = 'flex';
    FlussuClient.ui.environment.innerHTML = `
        <div class="flussu-thank-you">
            <h1 class="flussu-thank-you-text">GRAZIE</h1>
        </div>
    `;
    
    // Pulisci la sessione
    clearSession();
}

// Initialize Server-Sent Events
function initializeSSE(sessionId) {
    console.log('[Flussu] Initializing SSE for session:', sessionId);
    
    FlussuClient.state.eventSource = new EventSource(
        FlussuClient.config.notifServer + '?SID=' + sessionId
    );
    
    FlussuClient.state.eventSource.onmessage = function(event) {
        handleNotification(event.data);
    };
    
    FlussuClient.state.eventSource.onerror = function(error) {
        console.error('[Flussu] SSE Error:', error);
    };
}

// Render form elements
function renderElements(elements) {
    console.log('[Flussu] Rendering elements:', elements);
    
    FlussuClient.ui.startArea.style.display = 'none';
    FlussuClient.ui.environment.style.display = 'block';
    
    let html = '<div class="flussu-form-wrapper">';
    
    // Non creare blocchi separati, ma un unico blocco contenitore
    html += '<div class="flussu-block">';
    
    // Variabile per raggruppare i pulsanti
    let buttonGroup = [];
    let hasOpenButtonGroup = false;
    
    for (const [key, value] of Object.entries(elements)) {
        const [type, id] = key.split('$');
        const element = {
            type,
            id,
            value: Array.isArray(value) ? value : [value],
            key
        };
        
        // Se è un pulsante, aggiungilo al gruppo
        if (type === 'ITB') {
            buttonGroup.push(element);
            hasOpenButtonGroup = true;
        } else {
            // Se abbiamo pulsanti in coda, renderizzali prima
            if (buttonGroup.length > 0) {
                html += '<div class="flussu-button-group">';
                buttonGroup.forEach(btn => {
                    html += renderElement(btn);
                });
                html += '</div>';
                buttonGroup = [];
                hasOpenButtonGroup = false;
            }
            
            // Renderizza l'elemento normale
            html += renderElement(element);
        }
    }
    
    // Renderizza eventuali pulsanti rimasti
    if (buttonGroup.length > 0) {
        html += '<div class="flussu-button-group">';
        buttonGroup.forEach(btn => {
            html += renderElement(btn);
        });
        html += '</div>';
    }
    
    html += '</div>'; // Chiudi il blocco unico
    html += '</div>'; // Chiudi il wrapper
    
    FlussuClient.ui.environment.innerHTML = html;
    
    // Focus first input and autogrow textareas
    setTimeout(() => {
        const textareas = document.querySelectorAll('.flussu-textarea-auto');
        textareas.forEach(textarea => {
            // Funzione per auto-resize
            const autoResize = () => {
                textarea.style.height = 'auto';
                const scrollHeight = textarea.scrollHeight;
                const minHeight = parseInt(window.getComputedStyle(textarea).minHeight);
                const maxHeight = parseInt(window.getComputedStyle(textarea).maxHeight);
                
                if (scrollHeight > maxHeight) {
                    textarea.style.height = maxHeight + 'px';
                    textarea.style.overflowY = 'auto';
                } else {
                    textarea.style.height = Math.max(scrollHeight, minHeight) + 'px';
                    textarea.style.overflowY = 'hidden';
                }
            };
            
            // Applica auto-resize all'input
            textarea.addEventListener('input', autoResize);
            
            // Resize iniziale se c'è già del contenuto
            if (textarea.value) {
                autoResize();
            }
        });
        
        // Focus first input
        const firstInput = document.querySelector('.flussu-input:not([readonly])');
        if (firstInput) firstInput.focus();
    }, 100);
}

// Render individual element
function renderElement(element) {
    const { type, id, value, key } = element;
    const [content, cssData, defaultValue] = value;
    const css = typeof cssData === 'string' ? cssData : (cssData?.class || '');
    
    console.log('[Flussu] Rendering element:', type, id, content);
    
    switch (type) {
        case 'L': // Label
            return renderLabel(content, css);
            
        case 'ITT': // Text Input
            return renderTextInput(id, content, cssData, defaultValue);
            
        case 'ITB': // Button
            console.log('[Flussu] Rendering button:', id, content, cssData);
            return renderButton(id, content, cssData);
            
        case 'ITS': // Selection
            return renderSelection(id, content, cssData, defaultValue);
            
        case 'ITM': // Media Upload
            return renderMediaUpload(id, content, css);
            
        case 'M': // Media Display
            return renderMedia(content, cssData);
            
        case 'A': // Anchor/Link
            return renderAnchor(content, cssData);
            
        case 'GUI': // Get User Info
            return renderUserInfoRequest(id, content);
            
        default:
            return `<div class="flussu-unknown">[${type}: ${content}]</div>`;
    }
}

// Parse scale class like [1>5] to get min and max
function parseScaleClass(classStr) {
    if (!classStr || typeof classStr !== 'string') return null;
    
    const match = classStr.match(/^\[(\d+)>(\d+)\]$/);
    if (match) {
        return {
            min: parseInt(match[1]),
            max: parseInt(match[2])
        };
    }
    return null;
}

// Render Label
function renderLabel(content, css) {
    // Clean HTML tags but preserve content
    const cleanContent = content.replace(/<h1[^>]*>/gi, '<h2 class="flussu-block-title">').replace(/<\/h1>/gi, '</h2>');
    
    return `<div class="flussu-label ${css}">${cleanContent}</div>`;
}

// Render Text Input
// Render Text Input
function renderTextInput(id, placeholder, cssData, defaultValue) {
    // Controlla se cssData indica che deve essere una textarea
    let isTextarea = false;
    let isMandatory = false;
    
    if (cssData) {
        if (typeof cssData === 'string') {
            // Retrocompatibilità con il vecchio sistema
            isTextarea = cssData.includes('textarea');
            isMandatory = cssData.includes('flxMand');
        } else if (cssData.display_info) {
            // Nuovo sistema con display_info
            isTextarea = cssData.display_info.subtype === 'textarea';
            isMandatory = cssData.display_info.mandatory === true;
        }
    }
    
    const value = defaultValue && defaultValue.startsWith('[val]:') ? defaultValue.substring(6) : '';
    const css = typeof cssData === 'string' ? cssData : (cssData?.class || '');
    
    if (isTextarea) {
        return `
            <div class="flussu-input-group">
                <textarea 
                    name="$${id}" 
                    placeholder="${placeholder}"
                    class="flussu-input flussu-textarea flussu-textarea-auto ${css} ${FlussuClient.state.bid}"
                    ${isMandatory ? 'required' : ''}
                    rows="3">${value}</textarea>
                ${isMandatory ? '<span class="flussu-required">* Campo obbligatorio</span>' : ''}
            </div>
        `;
    }
    
    return `
        <div class="flussu-input-group">
            <input 
                type="text" 
                name="$${id}" 
                placeholder="${placeholder}"
                value="${value}"
                class="flussu-input ${css} ${FlussuClient.state.bid}"
                ${isMandatory ? 'required' : ''}
            />
            ${isMandatory ? '<span class="flussu-required">* Campo obbligatorio</span>' : ''}
        </div>
    `;
}

// Render Button
function renderButton(id, text, cssData) {
    const css = typeof cssData === 'string' ? cssData : (cssData?.class || '');
    const isPrimary = id === '0';
    
    // Controlla se cssData ha display_info come stringa o oggetto
    let isSkipValidation = false;
    if (cssData && cssData.display_info) {
        // Se display_info è una stringa, prova a parsarla
        if (typeof cssData.display_info === 'string') {
            try {
                const displayInfo = JSON.parse(cssData.display_info);
                isSkipValidation = displayInfo.subtype === 'skip-validation';
            } catch (e) {
                // Se non è JSON valido, lascia false
            }
        } else {
            // Se è già un oggetto
            isSkipValidation = cssData.display_info.subtype === 'skip-validation';
        }
    }
    
    console.log('[Flussu] Button render:', id, text, 'Skip validation:', isSkipValidation, cssData);
    
    return `
        <button 
            class="flussu-btn ${isPrimary ? 'flussu-btn-primary' : 'flussu-btn-secondary'} ${css}"
            onclick="window.submitForm('$ex!${id}', '${text.replace(/'/g, "\\'")}', ${isSkipValidation})"
        >
            ${text}
        </button>
    `;
}

// Render Selection
function renderSelection(id, optionsJson, cssData, defaultValue) {
    const options = JSON.parse(optionsJson);
    const displayInfo = cssData?.display_info || {};
    const subtype = displayInfo.subtype || 'selection';
    const isMandatory = displayInfo.mandatory || false;
    const selectedValues = defaultValue && defaultValue.startsWith('[val]:') ? 
        JSON.parse(defaultValue.substring(6)) : [];
    
    // Check if this is a numeric scale
    const scaleInfo = parseScaleClass(cssData?.class);
    
    let html = '<div class="flussu-selection-group">';
    
    // If it's a scale and multiple subtype, render as a scale matrix
    if (scaleInfo && subtype === 'multiple') {
        html += '<div class="flussu-scale-matrix">';
        
        // Scale header - SOLO NUMERI
        html += '<div class="flussu-scale-row flussu-scale-header-row">';
        html += '<div class="flussu-scale-item-label"></div>'; // Spazio vuoto per allineamento
        html += '<div class="flussu-scale-item-options">';
        for (let i = scaleInfo.min; i <= scaleInfo.max; i++) {
            html += `<div class="flussu-scale-header-number">${i}</div>`;
        }
        html += '</div>';
        html += '</div>';
        
        // Scale items
        for (const [key, label] of Object.entries(options)) {
            const [value] = key.split(',');
            html += '<div class="flussu-scale-row">';
            html += `<div class="flussu-scale-item-label">${label}</div>`;
            html += '<div class="flussu-scale-item-options">';
            
            for (let i = scaleInfo.min; i <= scaleInfo.max; i++) {
                const inputId = `${id}_${value}_${i}`;
                const isChecked = selectedValues.includes(`${value}_${i}`) ? 'checked' : '';
                
                html += `
                    <label class="flussu-scale-option" for="${inputId}">
                        <input 
                            type="radio" 
                            id="${inputId}"
                            name="$${id}_${value}" 
                            value='@SCALE["${value}","${i}"]'
                            class="${FlussuClient.state.bid} flussu-scale-input"
                            ${isChecked}
                            ${isMandatory ? 'data-required="true"' : ''}
                        />
                        <span class="flussu-scale-dot"></span>
                    </label>
                `;
            }
            
            html += '</div>';
            html += '</div>';
        }
        
        html += '</div>';
    } else if (subtype === 'selection' || subtype === 'default') {
        // Dropdown
        html += `
            <select 
                name="$${id}" 
                class="flussu-input flussu-select ${FlussuClient.state.bid}"
                ${isMandatory ? 'required' : ''}
            >
        `;
        
        // Add empty option if not mandatory
        if (!isMandatory) {
            html += '<option value="">Scegli un\'opzione...</option>';
        }
        
        for (const [key, label] of Object.entries(options)) {
            const [value, isDefault] = key.split(',');
            const selected = selectedValues.includes(value) || (selectedValues.length === 0 && isDefault === '1') ? 'selected' : '';
            html += `<option value='@OPT["${value}","${label}"]' ${selected}>${label}</option>`;
        }
        
        html += '</select>';
    } else if (subtype === 'exclusive') {
        // Radio buttons
        const inputType = 'radio';
        html += '<div class="flussu-options-grid">';
        
        for (const [key, label] of Object.entries(options)) {
            const [value, isDefault] = key.split(',');
            const checked = selectedValues.includes(value) || (selectedValues.length === 0 && isDefault === '1') ? 'checked' : '';
            const inputId = `${id}_${value}`;
            
            html += `
                <label class="flussu-option-card" for="${inputId}">
                    <input 
                        type="${inputType}" 
                        id="${inputId}"
                        name="$${id}" 
                        value='@OPT["${value}","${label}"]'
                        class="${FlussuClient.state.bid}"
                        ${checked}
                        ${isMandatory ? 'required' : ''}
                    />
                    <span class="flussu-option-content">
                        <span class="flussu-option-check"></span>
                        <span class="flussu-option-label">${label}</span>
                    </span>
                </label>
            `;
        }
        
        html += '</div>';
    } else if (subtype === 'multiple' && !scaleInfo) {
        // Checkboxes
        const inputType = 'checkbox';
        html += '<div class="flussu-options-grid">';
        
        for (const [key, label] of Object.entries(options)) {
            const [value, isDefault] = key.split(',');
            const checked = selectedValues.includes(value) || (selectedValues.length === 0 && isDefault === '1') ? 'checked' : '';
            const inputId = `${id}_${value}`;
            
            html += `
                <label class="flussu-option-card" for="${inputId}">
                    <input 
                        type="${inputType}" 
                        id="${inputId}"
                        name="$${id}" 
                        value='@OPT["${value}","${label}"]'
                        class="${FlussuClient.state.bid}"
                        ${checked}
                    />
                    <span class="flussu-option-content">
                        <span class="flussu-option-check"></span>
                        <span class="flussu-option-label">${label}</span>
                    </span>
                </label>
            `;
        }
        
        html += '</div>';
    }
    
    if (isMandatory) {
        html += '<span class="flussu-required">* Campo obbligatorio</span>';
    }
    
    html += '</div>';
    return html;
}
// Render Media Upload
function renderMediaUpload(id, text, css) {
    return `
        <div class="flussu-upload-group">
            <label class="flussu-upload-label" for="upload_${id}">
                <input 
                    type="file" 
                    id="upload_${id}"
                    name="$${id}"
                    class="flussu-upload-input ${FlussuClient.state.bid}"
                    onchange="window.handleFileUpload(this, '${id}')"
                    accept="image/*,application/pdf,.doc,.docx"
                />
                <div class="flussu-upload-area" 
                     id="drop_${id}"
                     ondrop="window.handleDrop(event, '${id}')"
                     ondragover="window.handleDragOver(event)"
                     ondragleave="window.handleDragLeave(event)">
                    <svg class="flussu-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <span class="flussu-upload-text">${text || 'Click to upload or drag and drop'}</span>
                    <span class="flussu-upload-hint">PNG, JPG, PDF up to 10MB</span>
                </div>
            </label>
            <div id="preview_${id}" class="flussu-upload-preview"></div>
        </div>
    `;
}

// Handle drag over
window.handleDragOver = function(event) {
    event.preventDefault();
    event.currentTarget.classList.add('drag-over');
};

// Handle drag leave
window.handleDragLeave = function(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
};

// Handle drop
window.handleDrop = function(event, id) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        const input = document.getElementById(`upload_${id}`);
        input.files = files;
        window.handleFileUpload(input, id);
    }
};
// Render Media Display
function renderMedia(url, cssData) {
    if (!url || url.trim() === '') return '';
    
    const displayType = cssData?.display_info?.type || 'image';
    
    if (displayType === 'image') {
        return `
            <div class="flussu-media-container">
                <img 
                    src="${url}" 
                    alt="Media" 
                    class="flussu-image"
                    loading="lazy"
                />
            </div>
        `;
    } else if (url.includes('youtube.com') || url.includes('youtu.be')) {
        const videoId = extractYouTubeId(url);
        return `
            <div class="flussu-video-container">
                <iframe 
                    src="https://www.youtube.com/embed/${videoId}"
                    frameborder="0"
                    allowfullscreen
                    class="flussu-video"
                ></iframe>
            </div>
        `;
    }
    
    return '';
}

// Render Anchor
function renderAnchor(content, cssData) {
    let [text, url] = content.includes('!|!') ? content.split('!|!') : [content, content];
    
    if (!url.startsWith('http')) {
        url = 'https://' + url;
    }
    
    const isButton = cssData?.display_info?.subtype === 'button';
    
    if (isButton) {
        return `
            <a href="${url}" target="_blank" class="flussu-btn flussu-btn-link">
                ${text}
            </a>
        `;
    }
    
    return `<a href="${url}" target="_blank" class="flussu-link">${text}</a>`;
}

// Render User Info Request
function renderUserInfoRequest(id, content) {
    return `
        <div class="flussu-user-info">
            <p>${content || 'The app needs your personal data.'}</p>
            <input type="hidden" name="$${id}" id="userInfoData" value='{"consent":"none"}' />
        </div>
    `;
}

// Submit form
window.submitForm = async function(buttonName, buttonValue, skipValidation = false) {
    console.log('[Flussu] Submitting form:', buttonName, buttonValue, 'Skip validation:', skipValidation);
    
    if (!skipValidation && !validateForm()) {
        return;
    }
    
    const formData = collectFormData();
    formData[buttonName] = buttonValue;
    
    // Debug: mostra esattamente cosa stiamo inviando
    console.log('[Flussu] Form data to send:', formData);
    console.log('[Flussu] Form data JSON:', JSON.stringify(formData));
    
    FlussuClient.state.terms = JSON.stringify(formData);
    
    // Mark current block as completed
    const currentBlock = document.querySelector('.flussu-block:has(.flussu-btn-primary)');
    if (currentBlock) {
        currentBlock.classList.add('completed');
    }
    
    await executeWorkflow();
};

// Collect form data
function collectFormData() {
    const formData = {};
    // Escape the bid to make it a valid CSS selector
    const escapedBid = CSS.escape ? CSS.escape(FlussuClient.state.bid) : FlussuClient.state.bid.replace(/([^\w-])/g, '\\$1');
    const elements = document.querySelectorAll(`.${escapedBid}`);
    
    // Set per tenere traccia dei nomi già processati per le scale
    const processedScaleNames = new Set();
    
    elements.forEach(element => {
        const name = element.name;
        if (!name) return;
        
        // Controlla se è un input di scala
        if (element.classList.contains('flussu-scale-input')) {
            // Estrai il nome base (rimuovi il suffisso _value)
            const baseName = name.split('_').slice(0, -1).join('_');
            
            if (!processedScaleNames.has(baseName) && element.checked) {
                // Aggiungi al set dei processati
                processedScaleNames.add(baseName);
                
                // Raccogli tutti i valori per questa scala
                const scaleValues = {};
                const scaleInputs = document.querySelectorAll(`input[name^="${baseName}_"]:checked`);
                
                scaleInputs.forEach(scaleInput => {
                    // Estrai il valore dell'elemento dalla parte del nome
                    const nameParts = scaleInput.name.split('_');
                    const itemValue = nameParts[nameParts.length - 1];
                    
                    // Estrai il valore numerico dalla stringa @SCALE["value","number"]
                    const match = scaleInput.value.match(/@SCALE\["([^"]+)","(\d+)"\]/);
                    if (match) {
                        scaleValues[match[1]] = match[2];
                    }
                });
                
                // Salva come oggetto JSON
                if (Object.keys(scaleValues).length > 0) {
                    formData[baseName] = JSON.stringify(scaleValues);
                }
            }
        } else if (element.type === 'file' && element.files.length > 0) {
            // Handle file upload secondo la documentazione Flussu
            const file = element.files[0];
            // Il nome deve mantenere il $ per il campo
            formData[name + '_name'] = file.name;
            formData[name + '_data'] = element.dataset.fileData || '';
        } else if (element.type === 'checkbox') {
            // Checkbox normali (non scale)
            if (element.checked && !element.classList.contains('flussu-scale-input')) {
                if (formData[name]) {
                    // Multiple selections - correggi il formato
                    const currentValue = formData[name];
                    if (currentValue.startsWith('@OPT[')) {
                        // Rimuovi l'ultimo "]" e aggiungi il nuovo valore
                        formData[name] = currentValue.slice(0, -1) + ',' + 
                                       element.value.replace('@OPT[', '').replace(']', '') + ']';
                    } else {
                        formData[name] = element.value;
                    }
                } else {
                    formData[name] = element.value;
                }
            }
        } else if (element.type === 'radio') {
            // Radio normali (non scale)
            if (element.checked && !element.classList.contains('flussu-scale-input')) {
                formData[name] = element.value;
            }
        } else if (element.value) {
            // Tutti gli altri tipi di input (text, select, textarea, etc.)
            formData[name] = element.value;
        }
    });
    
    console.log('[Flussu] Form data collected:', formData);
    return formData;
}

// Validate form
function validateForm() {
    // Escape the bid to make it a valid CSS selector
    const escapedBid = CSS.escape(FlussuClient.state.bid);
    const requiredFields = document.querySelectorAll(`.${escapedBid}[required]`);
    let isValid = true;
    
    // Prima, gestisci le scale con data-required
    const scaleContainers = document.querySelectorAll('.flussu-scale-matrix');
    scaleContainers.forEach(container => {
        // Controlla se questa scala è mandatory
        const hasRequiredInputs = container.querySelector('.flussu-scale-input[data-required="true"]');
        if (hasRequiredInputs) {
            // Trova tutte le righe (escludendo l'header)
            const scaleRows = container.querySelectorAll('.flussu-scale-row:not(.flussu-scale-header-row)');
            
            scaleRows.forEach(row => {
                const radioInputs = row.querySelectorAll('input[type="radio"]');
                const isRowChecked = Array.from(radioInputs).some(input => input.checked);
                
                if (!isRowChecked) {
                    row.classList.add('error');
                    isValid = false;
                } else {
                    row.classList.remove('error');
                }
            });
            
            // Aggiungi error class al container se ci sono errori
            const parent = container.closest('.flussu-selection-group');
            if (!isValid && parent) {
                parent.classList.add('error');
            } else if (parent) {
                parent.classList.remove('error');
            }
        }
    });
    
    // Poi gestisci tutti gli altri campi required
    requiredFields.forEach(field => {
        // Salta gli input delle scale, li abbiamo già gestiti sopra
        if (field.classList.contains('flussu-scale-input')) {
            return;
        }
        
        const parent = field.closest('.flussu-input-group, .flussu-selection-group');
        
        if (field.type === 'checkbox' || field.type === 'radio') {
            const name = field.name;
            const checked = document.querySelector(`input[name="${name}"]:checked`);
            if (!checked) {
                if (parent) parent.classList.add('error');
                isValid = false;
            } else {
                if (parent) parent.classList.remove('error');
            }
        } else if (!field.value.trim()) {
            if (parent) parent.classList.add('error');
            isValid = false;
        } else {
            if (parent) parent.classList.remove('error');
        }
    });
    
    if (!isValid) {
        showNotification('Completa tutti i campi obbligatori', 'error');
    }
    
    return isValid;
}

// Handle file upload - versione corretta per Flussu
window.handleFileUpload = async function(input, id) {
    const file = input.files[0];
    if (!file) return;
    
    const preview = document.getElementById(`preview_${id}`);
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    if (file.size > maxSize) {
        showNotification('File size must be less than 10MB', 'error');
        input.value = '';
        return;
    }
    
    // Mostra loading
    preview.innerHTML = '<div class="flussu-file-loading">Loading...</div>';
    
    // Leggi il file come base64
    const reader = new FileReader();
    
    reader.onload = function(e) {
        // Il risultato è già in formato data:image/jpeg;base64,... 
        // che è quello che Flussu si aspetta
        const base64Data = e.target.result;
        
        // Salva i dati base64 completi
        input.dataset.fileData = base64Data;
        
        console.log('[Flussu] File loaded:', {
            name: file.name,
            type: file.type,
            size: file.size,
            dataLength: base64Data.length
        });
        
        // Show preview
        if (file.type.startsWith('image/')) {
            preview.innerHTML = `
                <div class="flussu-file-preview">
                    <img src="${base64Data}" alt="${file.name}" style="max-width: 200px; max-height: 200px;" />
                    <div class="flussu-file-info">
                        <span class="flussu-file-name">${file.name}</span>
                        <span class="flussu-file-size">${(file.size / 1024).toFixed(2)} KB</span>
                    </div>
                    <button type="button" class="flussu-file-remove" onclick="window.removeFile('${id}')">×</button>
                </div>
            `;
        } else {
            preview.innerHTML = `
                <div class="flussu-file-preview">
                    <svg class="flussu-file-icon" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    <div class="flussu-file-info">
                        <span class="flussu-file-name">${file.name}</span>
                        <span class="flussu-file-size">${(file.size / 1024).toFixed(2)} KB</span>
                    </div>
                    <button type="button" class="flussu-file-remove" onclick="window.removeFile('${id}')">×</button>
                </div>
            `;
        }
    };
    
    reader.onerror = function() {
        preview.innerHTML = '<div class="flussu-file-error">Error loading file</div>';
        console.error('[Flussu] File read error');
    };
    
    // Leggi il file come data URL (base64)
    reader.readAsDataURL(file);
};

// Remove file
window.removeFile = function(id) {
    const input = document.getElementById(`upload_${id}`);
    const preview = document.getElementById(`preview_${id}`);
    
    input.value = '';
    delete input.dataset.fileData;
    preview.innerHTML = '';
};
// Update progress bar
function updateProgress() {
    const completed = document.querySelectorAll('.flussu-block.completed').length;
    const progress = (completed / FlussuClient.state.totalSteps) * 100;
    
    const progressBar = document.querySelector('.flussu-progress-bar');
    if (progressBar) {
        progressBar.style.width = `${progress}%`;
    }
}

// Handle notifications
function handleNotification(data) {
    if (!data || data === '{}') return;
    
    try {
        const notifications = JSON.parse(data);
        console.log('[Flussu] Notification received:', notifications);
        
        for (const [key, notification] of Object.entries(notifications)) {
            if (key === 'SID') continue;
            
            const { type, name, value } = notification;
            
            switch (type) {
                case 1: // Alert
                    showNotification(value, 'info');
                    break;
                case 2: // Add counter
                    addCounter(name, value);
                    break;
                case 3: // Update counter
                    updateCounter(name, value);
                    break;
                case 4: // Add chat row
                    addChatMessage(value);
                    break;
                case 5: // Callback
                    handleCallback(name, value);
                    break;
            }
        }
    } catch (error) {
        console.error('[Flussu] Notification error:', error);
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const notify = FlussuClient.ui.notify;
    
    notify.className = `flussu-notify flussu-notify-${type}`;
    notify.innerHTML = message;
    notify.classList.add('show');
    
    setTimeout(() => {
        notify.classList.remove('show');
    }, FlussuClient.config.notifTime);
}

// Loading states
function showLoading() {
    if (FlussuClient.ui.loading) {
        FlussuClient.ui.loading.classList.add('show');
    }
}

function hideLoading() {
    if (FlussuClient.ui.loading) {
        FlussuClient.ui.loading.classList.remove('show');
    }
}

// Error handling
function showError(message) {
    showNotification(message, 'error');
}

// Session management
function saveSession() {
    const session = {
        wid: FlussuClient.state.wid,
        sid: FlussuClient.state.sid,
        bid: FlussuClient.state.bid,
        lang: FlussuClient.state.lang
    };
    
    sessionStorage.setItem('flussuSession', JSON.stringify(session));
}

function getSession() {
    const session = sessionStorage.getItem('flussuSession');
    return session ? JSON.parse(session) : null;
}

function clearSession() {
    sessionStorage.removeItem('flussuSession');
    if (FlussuClient.state.eventSource) {
        FlussuClient.state.eventSource.close();
        FlussuClient.state.eventSource = null;
    }
}

// Lightbox functionality
function openLightbox(src) {
    const lightbox = document.getElementById('flussu-lightbox');
    const img = document.getElementById('flussu-lightbox-img');
    
    if (lightbox && img) {
        img.src = src;
        lightbox.classList.add('show');
    }
}

window.closeLightbox = function() {
    const lightbox = document.getElementById('flussu-lightbox');
    if (lightbox) {
        lightbox.classList.remove('show');
    }
};

// Utility functions
function extractYouTubeId(url) {
    const match = url.match(/[?&]v=([^&]+)/) || url.match(/youtu\.be\/([^?]+)/);
    return match ? match[1] : '';
}

// Text-to-speech functionality
function textToSpeech(text) {
    if (!FlussuClient.config.sound || !window.speechSynthesis) return;
    
    if (text === '{CANC}') {
        window.speechSynthesis.cancel();
        return;
    }
    
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = FlussuClient.state.lang === 'IT' ? 'it-IT' : 'en-US';
    utterance.rate = 1;
    utterance.pitch = 1;
    utterance.volume = 1;
    
    window.speechSynthesis.speak(utterance);
}

// Public API functions
window.FlussuIn = function(lang) {
    if (lang) FlussuClient.state.lang = lang;
    
    if (lang !== "" || FlussuClient.state.sid !== null) {
        initWorkflow();
    }
};

window.setFlussuId = function(wid, title, arbitrary = {}) {
    console.log('[Flussu] Setting WID:', wid, 'Title:', title);
    
    FlussuClient.state.wid = wid;
    FlussuClient.config.displayTitle = !(title === "0" || title === "false" || title === "none");
    
    if (FlussuClient.config.displayTitle && FlussuClient.ui.title) {
        FlussuClient.ui.title.textContent = title;
    }
    
    arbitrary["$isForm"] = true;
    arbitrary["$clientVersion"] = FlussuClient.version;
    arbitrary["$_FD0508"] = window.location.href;
    
    // Add URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    for (let [key, value] of urlParams) {
        if (key.startsWith('£')) {
            arbitrary[key.replace('£', '$')] = value;
        }
    }
    
    FlussuClient.state.startData = JSON.stringify({ arbitrary });
    
    // Auto-initialize if DOM is ready
    if (document.readyState !== 'loading' && !FlussuClient.state.isInit) {
        setTimeout(() => {
            initWorkflow();
        }, 100);
    }
};

window.setFlussuSound = function(enabled) { 
    FlussuClient.config.sound = enabled === 'Y' || enabled === 'yes'; 
};

window.setFlussuEndpoint = function(endpoint) {
    console.log('[Flussu] Setting endpoint:', endpoint);
    FlussuClient.config.srv = endpoint;
    FlussuClient.config.api = endpoint + "api/v2.0/";
    FlussuClient.config.notifServer = endpoint + "notify";
};

window.setFlussuInIframe = function() { 
    FlussuClient.config.isIframe = true; 
};

window.setFlussuCssVersion = function(version) {
    // Compatibility function - CSS is now embedded
};

// jQuery compatibility
if (typeof jQuery !== 'undefined') {
    jQuery.urlParam = function(name) {
        return getUrlParam(name);
    };
}

console.log('[Flussu] v4.0 loaded successfully');
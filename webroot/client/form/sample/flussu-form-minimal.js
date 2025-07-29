console.log('[Flussu] Script loading...');

// Minimal Flussu Client
window.FlussuClient = {
    version: "4.0",
    config: {
        srv: location.protocol + "//" + window.location.host + "/",
        api: null
    },
    state: {
        wid: null,
        sid: null,
        bid: null,
        lang: "IT",
        title: ""
    }
};

// Set API endpoint
FlussuClient.config.api = FlussuClient.config.srv + "api/v2.0/";

// Test functions
window.setFlussuEndpoint = function(endpoint) {
    console.log('[Flussu] Setting endpoint:', endpoint);
    FlussuClient.config.srv = endpoint;
    FlussuClient.config.api = endpoint + "api/v2.0/";
};

window.setFlussuId = function(wid, title, arbitrary) {
    console.log('[Flussu] Setting WID:', wid, 'Title:', title);
    FlussuClient.state.wid = wid;
    FlussuClient.state.title = title;
    
    // Try to initialize
    setTimeout(function() {
        console.log('[Flussu] Attempting to initialize...');
        initWorkflow();
    }, 100);
};

function initWorkflow() {
    console.log('[Flussu] InitWorkflow called');
    
    const container = document.getElementById('flussu-form');
    if (!container) {
        console.error('[Flussu] Container not found!');
        return;
    }
    
    console.log('[Flussu] Container found, fetching workflow info...');
    
    // First, get workflow info
    fetchWorkflowInfo();
}

async function fetchWorkflowInfo() {
    console.log('[Flussu] Fetching workflow info for WID:', FlussuClient.state.wid);
    
    try {
        const response = await fetch(FlussuClient.config.api + 'flussueng.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                WID: FlussuClient.state.wid,
                CMD: 'info'
            })
        });
        
        console.log('[Flussu] Info response status:', response.status);
        const data = await response.json();
        console.log('[Flussu] Workflow info:', data);
        
        // Update title if provided by server
        if (data.tit) {
            FlussuClient.state.title = data.tit;
        }
        
        // Get available languages
        const languages = data.langs ? data.langs.split(',') : ['IT', 'EN'];
        
        // Build language selection interface
        buildLanguageSelection(languages);
        
    } catch (error) {
        console.error('[Flussu] Fetch error:', error);
        document.getElementById('flussu-form').innerHTML = `
            <div style="padding: 20px; color: red;">
                Error loading workflow: ${error.message}
            </div>
        `;
    }
}

function buildLanguageSelection(languages) {
    const container = document.getElementById('flussu-form');
    
    let buttonsHtml = '';
    const langNames = {
        'IT': 'Inizia',
        'EN': 'Start',
        'FR': 'Commencer',
        'DE': 'Beginnen',
        'ES': 'Empezar'
    };
    
    languages.forEach(lang => {
        const buttonText = langNames[lang] || lang;
        buttonsHtml += `
            <button onclick="startWorkflow('${lang}')" 
                    style="padding: 10px 20px; margin: 10px; font-size: 16px; cursor: pointer;">
                ${buttonText} (${lang})
            </button>
        `;
    });
    
    container.innerHTML = `
        <div style="padding: 20px; text-align: center; font-family: Arial, sans-serif;">
            <h1>${FlussuClient.state.title || 'Flussu Workflow'}</h1>
            <p style="color: #666;">WID: ${FlussuClient.state.wid}</p>
            <div id="flussu-buttons" style="margin-top: 30px;">
                ${buttonsHtml}
            </div>
            <div id="flussu-loading" style="display: none; margin-top: 20px; color: #666;">
                Loading...
            </div>
        </div>
    `;
}

// Make this function global
window.startWorkflow = async function(lang) {
    console.log('[Flussu] Starting workflow with language:', lang);
    FlussuClient.state.lang = lang;
    
    // Show loading
    document.getElementById('flussu-loading').style.display = 'block';
    document.getElementById('flussu-buttons').style.display = 'none';
    
    try {
        const params = {
            WID: FlussuClient.state.wid,
            LNG: lang,
            BID: '',
            SID: '',
            TRM: ''
        };
        
        console.log('[Flussu] Sending start request with params:', params);
        
        const response = await fetch(FlussuClient.config.api + 'flussueng.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params)
        });
        
        console.log('[Flussu] Start response status:', response.status);
        const data = await response.json();
        console.log('[Flussu] Start response data:', data);
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        // Store session info
        if (data.sid) {
            FlussuClient.state.sid = data.sid;
            FlussuClient.state.bid = data.bid;
        }
        
        // Display the workflow elements
        displayWorkflowElements(data);
        
    } catch (error) {
        console.error('[Flussu] Start workflow error:', error);
        document.getElementById('flussu-form').innerHTML = `
            <div style="padding: 20px; color: red;">
                Error starting workflow: ${error.message}
                <br><br>
                <button onclick="location.reload()">Retry</button>
            </div>
        `;
    }
};

function displayWorkflowElements(data) {
    console.log('[Flussu] Displaying elements:', data.elms);
    
    const container = document.getElementById('flussu-form');
    let html = '<div style="padding: 20px; font-family: Arial, sans-serif;">';
    
    // Process elements
    if (data.elms) {
        for (const [key, value] of Object.entries(data.elms)) {
            const [type, id] = key.split('$');
            const content = Array.isArray(value) ? value[0] : value;
            
            console.log('[Flussu] Processing element:', type, id, content);
            
            switch (type) {
                case 'L': // Label
                    html += `<div style="margin: 10px 0;">${content}</div>`;
                    break;
                    
                case 'ITB': // Button
                    html += `
                        <button onclick="submitWorkflow('${id}', '${content}')" 
                                style="padding: 10px 20px; margin: 10px; cursor: pointer;">
                            ${content}
                        </button>
                    `;
                    break;
                    
                case 'ITT': // Text input
                    html += `
                        <div style="margin: 10px 0;">
                            <input type="text" 
                                   id="input_${id}" 
                                   placeholder="${content}"
                                   style="padding: 8px; width: 300px;">
                        </div>
                    `;
                    break;
                    
                default:
                    html += `<div style="margin: 10px 0; color: #999;">[${type} - ${content}]</div>`;
            }
        }
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// Make submit function global
window.submitWorkflow = async function(buttonId, buttonText) {
    console.log('[Flussu] Submit clicked:', buttonId, buttonText);
    
    // Collect form data
    const formData = {};
    formData[`$ex!${buttonId}`] = buttonText;
    
    // Collect input values
    const inputs = document.querySelectorAll('input[type="text"]');
    inputs.forEach(input => {
        if (input.value) {
            const inputId = input.id.replace('input_', '$');
            formData[inputId] = input.value;
        }
    });
    
    console.log('[Flussu] Form data:', formData);
    
    // Continue workflow
    try {
        const params = {
            WID: FlussuClient.state.wid,
            SID: FlussuClient.state.sid,
            BID: FlussuClient.state.bid,
            LNG: FlussuClient.state.lang,
            TRM: JSON.stringify(formData)
        };
        
        console.log('[Flussu] Sending continue request:', params);
        
        const response = await fetch(FlussuClient.config.api + 'flussueng.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params)
        });
        
        const data = await response.json();
        console.log('[Flussu] Continue response:', data);
        
        // Update state
        if (data.bid) {
            FlussuClient.state.bid = data.bid;
        }
        
        // Display new elements
        displayWorkflowElements(data);
        
    } catch (error) {
        console.error('[Flussu] Submit error:', error);
    }
};

console.log('[Flussu] Script loaded successfully');
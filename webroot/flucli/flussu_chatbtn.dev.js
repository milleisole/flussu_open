(function () {
    // Funzione principale che verrà eseguita quando il DOM è pronto
    function initFlussuChatbot() {
        // Leggi gli attributi 'server' e 'wid' dal tag <script> con id="flussu_chatbot"
        const scriptTag = document.getElementById('flussu_chatbot');
        if (!scriptTag) {
            console.error('Flussu Chatbot: tag script con id="flussu_chatbot" non trovato.');
            return;
        }
        
        const server = scriptTag.getAttribute('server') || 'default-server.com';
        const wid = scriptTag.getAttribute('wid') || 'default-wid';
        
        // Crea il link del chatbot usando server e wid
        const chatbotUrl = `https://${server}/flucli/client_dev.html?wid=${wid}&ifra=1`;

        // Verifica che document.head esista
        if (!document.head) {
            console.error('Flussu Chatbot: document.head non trovato.');
            return;
        }

        // Inietta il CSS
        const style = document.createElement('style');
        style.textContent = `
            .flussu_chatbot_button {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 60px;
                height: 60px;
                background-color: #28a745;
                border-radius: 50%;
                display: flex;
                justify-content: center;
                align-items: center;
                cursor: pointer;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
                z-index: 1000;
                transition: transform 0.2s;
            }
            .flussu_chatbot_button:hover {
                transform: scale(1.1);
            }
            .flussu_chatbot_iframe_container {
                display: none;
                position: fixed;
                bottom: 15px;
                right: 20px;
                width: 400px;
                height: 0; /* Inizialmente chiuso con altezza 0 */
                background: #FFF;
                box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
                z-index: 1001;
                border-radius: 0 10px 0 10px; /* Solo in alto a destra e in basso a sinistra */
                overflow: hidden;
                transition: transform 0.3s ease-out, height 0.3s ease-out; /* Animazione fluida */
                transform: translateY(100%); /* Inizia dal basso */
            }
            .flussu_chatbot_iframe_container.open {
                height: 680px; /* Altezza completa quando aperto */
                transform: translateY(0); /* Torna in posizione normale */
            }
            .flussu_chatbot_iframe {
                width: 100%;
                height: 100%;
                border: none;
                padding-top: 8px;
                box-sizing: border-box;
            }
             .flussu_close_button {
                position: absolute;
                top: -1px; 
                right: -1px; 
                background: #28a745;
                color: white;
                border: none;
                border-radius: 0 6px 0 4px;
                width: 24px; 
                height: 24px; 
                cursor: pointer;
                font-size: 20px; 
                line-height: 22px; 
                text-align: center;
                z-index: 1002;
                font-weight: bold;
            }
            @media (max-width: 500px) {
                .flussu_chatbot_iframe_container {
                    width: 100%;
                    height: 0;
                    bottom: 15px;
                    right: 0;
                    border-radius: 0;
                    transform: translateY(100%);
                }
                .flussu_chatbot_iframe_container.open {
                    height: calc(100% - 15px);
                }
            }
        `;
        document.head.appendChild(style);

        // Verifica che document.body esista
        if (!document.body) {
            console.error('Flussu Chatbot: document.body non trovato.');
            return;
        }

        // Inietta l'HTML per il pulsante
        const buttonDiv = document.createElement('div');
        buttonDiv.className = 'flussu_chatbot_button';
        buttonDiv.innerHTML = `
        <svg class="flussu-btn-svg" width="35px" fill="none" height="31px" version="1.0" viewBox="0 0 8.47 7.42" xmlns="http://www.w3.org/2000/svg">
            <path fill="none" stroke="#ffffff" stroke-width="1.3" stroke-miterlimit="22.9256" d="M2.38 0.65l3.97 0c0.81,0 1.47,0.66 1.47,1.47l0 1.95c0,0.81 -0.66,1.46 -1.47,1.47l-3.36 0.05 -1.72 1.17c-0.25,0.17 0.62,-1.2 0.19,-1.37 -0.5,-0.2 -0.81,-0.74 -0.81,-1.32l0 -1.69c0,-0.95 0.78,-1.73 1.73,-1.73z"></path>
        </svg>`;
        document.body.appendChild(buttonDiv);

        // Inietta l'HTML per l'iframe
        const iframeContainer = document.createElement('div');
        iframeContainer.className = 'flussu_chatbot_iframe_container';
        iframeContainer.id = 'flussu_chatbot_iframe_container';
        iframeContainer.innerHTML = `
            <button class="flussu_close_button">×</button>
            <iframe class="flussu_chatbot_iframe" src="${chatbotUrl}"></iframe>
        `;
        document.body.appendChild(iframeContainer);

        // Funzione per alternare la visibilità dell'iframe e del pulsante con animazione
        function toggleFlussuChatbot() {
            const container = document.getElementById('flussu_chatbot_iframe_container');
            if (container) {
                const isVisible = container.classList.contains('open');
                if (isVisible) {
                    container.classList.remove('open');
                    // Aspetta la fine dell'animazione prima di nascondere il pulsante
                    setTimeout(() => {
                        container.style.display = 'none';
                        buttonDiv.style.display = 'flex';
                    }, 300); // Tempo pari alla durata della transition (0.3s)
                } else {
                    container.style.display = 'block';
                    setTimeout(() => {
                        container.classList.add('open');
                        buttonDiv.style.display = 'none';
                    }, 10); // Piccolo ritardo per avviare l'animazione
                }
            } else {
                console.error('Flussu Chatbot: contenitore iframe non trovato.');
            }
        }

        // Aggiungi event listeners
        buttonDiv.addEventListener('click', toggleFlussuChatbot);
        const closeButton = iframeContainer.querySelector('.flussu_close_button');
        if (closeButton) {
            closeButton.addEventListener('click', toggleFlussuChatbot);
        } else {
            console.error('Flussu Chatbot: pulsante di chiusura non trovato.');
        }
    }

    // Esegui quando il DOM è completamente caricato
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFlussuChatbot);
    } else {
        initFlussuChatbot();
    }
})();
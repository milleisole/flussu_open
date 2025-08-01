(function () {
    let overlayDiv = null;
    function initFlussuChatbot() {
        const scriptTag = document.getElementById('flussu_chatbot');
        if (!scriptTag) {
            console.error('Aldo Bot: tag script con id="flussu_chatbot" non trovato.');
            return;
        }
        const server = scriptTag.getAttribute('server') || 'default-server.com';
        const wid = scriptTag.getAttribute('wid') || 'default-wid';
        const chatbotUrl = `https://${server}/flucli/aldclient.html?wid=${wid}&ifra=1`;
        const reduceIconPath = `https://${server}/flucli/images/reduce.png`;
        const enlargeIconPath = `https://${server}/flucli/images/enlarge.png`;
        const style = document.createElement('style');
        style.textContent = `
            .flussu_chatbot_button {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 60px;
                height: 60px;
                background-color:rgb(57, 122, 197);
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
                height: 0;
                background: #FFF;
                box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
                z-index: 1001;
                border-radius: 0 10px 0 10px;
                overflow: hidden;
                transition: height 0.32s cubic-bezier(.32,1.3,.41,1), width 0.32s cubic-bezier(.32,1.3,.41,1);
            }
            .flussu_chatbot_iframe_container.open {
                height: 680px;
            }
            .flussu_chatbot_iframe {
                width: 100%;
                height: 100%;
                border: none;
                padding-top: 8px;
                box-sizing: border-box;
            }
            .flussu_header_btns {
                position: absolute;
                top: 6px;
                right: 10px;
                display: flex;
                gap: 3px;
                z-index: 1003;
            }
            .flussu_enlarge_button, .flussu_close_button {
                background: rgb(57, 122, 197);
                color: white;
                border: none;
                border-radius: 4px;
                width: 24px;
                height: 24px;
                cursor: pointer;
                font-size: 17px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
                font-weight: bold;
                padding: 0;
            }
            .flussu_enlarge_button:hover, .flussu_close_button:hover {
                background: rgb(57, 122, 197);
            }
            .flussu_chatbot_iframe_container.fullscreen {
                left: 5vw !important;
                top: 5vh !important;
                bottom: auto !important;
                right: auto !important;
                width: 90vw !important;
                height: 90vh !important;
                border-radius: 18px !important;
                box-shadow: 0 0 0 9999px rgba(0,0,0,0.12), 0 8px 24px rgba(0,0,0,0.18);
                z-index: 10010;
                transition: width 0.32s cubic-bezier(.32,1.3,.41,1), height 0.32s cubic-bezier(.32,1.3,.41,1);
            }
            .flussu_chatbot_overlay {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0,0,0,0.17);
                z-index: 10005;
                transition: background 0.3s;
            }
            .flussu_chatbot_overlay.active {
                display: block;
            }
            @media (max-width: 500px) {
                .flussu_chatbot_iframe_container {
                    width: 100%;
                    height: 0;
                    bottom: 15px;
                    right: 0;
                    border-radius: 0;
                }
                .flussu_enlarge_button {display:none;}
                .flussu_chatbot_iframe_container.open {
                    height: calc(100% - 15px);
                }
                .flussu_chatbot_iframe_container.fullscreen {
                    left: 1vw !important;
                    top: 1vh !important;
                    width: 98vw !important;
                    height: 98vh !important;
                    border-radius: 8px !important;
                }
            }
        `;
        document.head.appendChild(style);
        const buttonDiv = document.createElement('div');
        buttonDiv.className = 'flussu_chatbot_button';
        buttonDiv.innerHTML = `
        <svg class="flussu-btn-svg" width="35px" fill="none" height="31px" version="1.0" viewBox="0 0 8.47 7.42" xmlns="http://www.w3.org/2000/svg">
            <path fill="none" stroke="#ffffff" stroke-width="1.3" stroke-miterlimit="22.9256" d="M2.38 0.65l3.97 0c0.81,0 1.47,0.66 1.47,1.47l0 1.95c0,0.81 -0.66,1.46 -1.47,1.47l-3.36 0.05 -1.72 1.17c-0.25,0.17 0.62,-1.2 0.19,-1.37 -0.5,-0.2 -0.81,-0.74 -0.81,-1.32l0 -1.69c0,-0.95 0.78,-1.73 1.73,-1.73z"></path>
        </svg>`;
        document.body.appendChild(buttonDiv);
        overlayDiv = document.createElement('div');
        overlayDiv.className = 'flussu_chatbot_overlay';
        document.body.appendChild(overlayDiv);
        const iframeContainer = document.createElement('div');
        iframeContainer.className = 'flussu_chatbot_iframe_container';
        iframeContainer.id = 'flussu_chatbot_iframe_container';
        iframeContainer.innerHTML = `
            <div class="flussu_header_btns">
                <button class="flussu_enlarge_button" title="Ingrandisci chatbot">⤢</button>
                <button class="flussu_close_button" title="Chiudi chatbot">×</button>
            </div>
            <iframe class="flussu_chatbot_iframe" src="${chatbotUrl}"></iframe>
        `;
        document.body.appendChild(iframeContainer);

        const enlargeButton = iframeContainer.querySelector('.flussu_enlarge_button');
        const closeButton = iframeContainer.querySelector('.flussu_close_button');

        let isFullScreen = false;
        
        enlargeButton.innerHTML = `<img src="${enlargeIconPath}" alt="Riduci" style="width:18px;height:18px;display:block;margin:auto;" />`;
        function toggleFullScreenChatbot(forceTo) {
            const container = document.getElementById('flussu_chatbot_iframe_container');
            if (!container) return;
            if (typeof forceTo === "boolean") isFullScreen = forceTo;
            else isFullScreen = !isFullScreen;
            if (isFullScreen) {
                container.classList.add('fullscreen');
                enlargeButton.innerHTML = `<img src="${reduceIconPath}" alt="Riduci" style="width:18px;height:18px;display:block;margin:auto;" />`;
                enlargeButton.title = "Riduci chatbot";
                overlayDiv.classList.add('active');
            } else {
                container.classList.remove('fullscreen');
                enlargeButton.innerHTML = `<img src="${enlargeIconPath}" alt="Riduci" style="width:18px;height:18px;display:block;margin:auto;" />`;
                enlargeButton.title = "Ingrandisci chatbot";
                overlayDiv.classList.remove('active');
            }
        }
        enlargeButton.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleFullScreenChatbot();
        });
        overlayDiv.addEventListener('click', function() {
            if (isFullScreen) toggleFullScreenChatbot(false);
        });
        function toggleFlussuChatbot() {
            const container = document.getElementById('flussu_chatbot_iframe_container');
            if (container) {
                const isVisible = container.classList.contains('open');
                if (isVisible) {
                    container.classList.remove('open');
                    container.classList.remove('fullscreen');
                    isFullScreen = false;
                    enlargeButton.textContent = '⤢';
                    enlargeButton.title = "Ingrandisci chatbot";
                    overlayDiv.classList.remove('active');
                    setTimeout(() => {
                        container.style.display = 'none';
                        buttonDiv.style.display = 'flex';
                        overlayDiv.classList.remove('active');
                    }, 320);
                } else {
                    container.style.display = 'block';
                    setTimeout(() => {
                        container.classList.add('open');
                        buttonDiv.style.display = 'none';
                    }, 10);
                }
            } else {
                console.error('Flussu Chatbot: contenitore iframe non trovato.');
            }
        }
        buttonDiv.addEventListener('click', toggleFlussuChatbot);
        closeButton.addEventListener('click', toggleFlussuChatbot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFlussuChatbot);
    } else {
        initFlussuChatbot();
    }
})();
 //---------------
 //    _{()}_    |
 //    --[]--    |
 //      ||      |
 //  AL  ||  DVS |
 //  \\__||__//  |
 //   \__||__/   |
 //      \/      |
 //   @INXIMKR   |
 //--------------- 
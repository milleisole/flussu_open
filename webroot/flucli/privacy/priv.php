<?php 
    $lng=$_GET["lang"];
    $title=$_GET["tit"];
    $diniego=$_GET["pn"]??"";
    $file_path = __DIR__ . "/." .$lng . $diniego.".html";
?>
<!DOCTYPE html>
<html lang="it" class="h-full bg-gray-50 dark:bg-gray-900">
    <head>
        <meta charset="UTF-8" />
        <link rel="icon" href="/client/assets/img/favicon.png"/>
        <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
        <title data-i18n="app_title">FLUSSU Chat Prvacy Policy</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { darkMode: 'class' }
            if (localStorage.getItem("theme") === "dark" || (!localStorage.getItem("theme") && window.matchMedia("(prefers-color-scheme: dark)").matches)) 
                document.documentElement.classList.add("dark");
            else 
                document.documentElement.classList.remove("dark");
        </script>
    </head>
    <body class="h-full flex flex-col">
        <div class="flex h-screen">
            <div class="flex-1 flex flex-col relative">
                <header class="shadow-[0_3px_16px_-4px_rgba(0,0,0,0.1)] flex items-start sm:items-center justify-between px-4 py-3 border-b bg-white dark:bg-gray-800 sticky top-0 z-10 border-gray-200 dark:border-gray-700 flex-wrap">
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <img src="/flucli/images/flussu.svg" alt="logo" class="w-10 h-10 rounded-lg bg-white" />
                        <span class="text-xl font-bold text-gray-800 dark:text-gray-200 hidden sm:inline" data-i18n="app_title">FLUSSU</span>
                    </div>
                    <div class="flex-1 flex justify-center items-start sm:items-center min-w-0">
                        <span id="chat-title" class="block text-lg sm:text-2xl font-semibold text-gray-700 dark:text-gray-200 text-center break-words leading-snug sm:leading-tight" style="word-break:break-word;" data-i18n="page_title">
                        <?php echo $title; ?>
                        </span>
                    </div>
                    <div id="header-toolbar" class="flex gap-2 sm:gap-4 ml-auto items-center flex-shrink-0 h-10">
                        <button id="theme-toggle-switch"
                        class="w-7 h-4 sm:w-8 sm:h-5 rounded-full bg-gray-300 dark:bg-gray-500 border border-gray-400 dark:border-gray-500 flex items-center transition-colors duration-300 focus:outline-none p-0.5"
                        data-i18n-title="theme" aria-label="">
                        <span id="theme-switch-knob"
                            class="h-3 w-3 sm:h-3.5 sm:w-3.5 rounded-full bg-white dark:bg-white shadow-md transform transition-transform duration-300"></span>
                        </button>
                    </div>
                </header>
                <main id="chat-area" 
                    class="flex-1 overflow-y-auto   /* area scrollabile */
                            py-4                     /* padding solo verticale */
                            flex flex-col gap-2
                            bg-gray-50 dark:bg-black transition-colors duration-300
                            /* 👇 padding orizzontale responsive */
                            px-0          /* mobile: 0  */
                            sm:px-[8%]    /* ≥ 640 px   :  8 % */
                            lg:px-[15%]   /* ≥ 1024 px  : 15 % */
                    ">
                    <div>
                        <button onclick="history.back()">&lt;- Back</button>
                    </div>
                    <?php echo file_get_contents($file_path); ?>
                </main>
            </div>
        </div>
    </body>
</html>



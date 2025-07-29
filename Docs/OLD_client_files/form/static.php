<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Flussu - Chat Testing Page" />
        <meta name="author" content="" />
        <title>Flussu - Chat Testing Page</title>
        <!-- Favicon-->
        <link rel="icon" type="image/x-icon" href="/client/assets/sample_page_assets/favicon.ico" />
        <!-- Font Awesome icons (free version)-->
        <script src="https://use.fontawesome.com/releases/v5.15.3/assets/js/all.js" crossorigin="anonymous"></script>

    <!-- flussu pop-up needed files -->
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@joeattardi/emoji-button@3.1.1/dist/index.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<link rel="stylesheet" href="/client/assets/style/flussu-popup.css">

    </head>
    <body id="page-top">
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/assets/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <!--<script src="cps_assets/assets/js/cps_scripts.js"></script>-->
        <script src="/client/assets/script/flussu-pop-2.0.0.js"></script>
        <style>
            .flussu-chat-popup{
                /*display:block;*/
                right:0px;
                bottom:0px;
                left:0px;
                right:0px;
                height:100vh;
                width:100%;
                max-height:100vh;
            }
        </style>
        <script>
            //flussuEP = "https://v20.flussu.com/";
            flussuEP = "https://"+location.hostname+"/";
            setFlussuEndpoint(flussuEP);
            var flussuId = $.urlParam('WID');
            function FlussuIn(LNG) {
                lng = LNG.toLowerCase();
                var cssId = lng + 'Css';
                if (!document.getElementById(cssId)) {
                    var head = document.getElementsByTagName('head')[0];
                    var link = document.createElement('link');
                    link.id = cssId;
                    link.rel = 'stylesheet';
                    link.type = 'text/css';
                    link.href = flussuEP + 'client/assets/style/flussu_chat_' + lng + '.css';
                    link.media = 'all';
                    head.appendChild(link);
                }
                startFlussu(LNG, "{'$ChatTesting':'true', '$user':'Testing'}");
            }
            $(document).ready(function(){
                $("#flussu-chat-btn").hide();
                //$("#flussu-chat-popup").show();
                $("#flussu-badge").hide();
                $("#flussu-startarea").show();
                getWorkflowInfo();
            });
        </script>
    </body>
</html>

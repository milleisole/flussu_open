<!doctype html>
<html class="the_page">
    <head>
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> 


        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>


        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">
        <meta name="author" content="">

        <link rel="icon" href="/client/assets/img/favicon.png">

        <title>Chat - flussu.com</title>
        <style>
          .fakechatbot-typing, .fakechatbot-message { display: block; clear:both; }
        </style>

        <script src="/client/pop/dist/flussu-pop.js"></script> 
  </head>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-__________"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-__________');
  </script>
  <body class="the_body">
      <!-- Google tag (gtag.js) -->
       
    <div id="flussu-form" class="flussu-form" style="width:98%"></div>
    <script>
      flussuServer="https://"+window.location.host+"/";
      setFlussuApi(flussuServer);
      $(document).ready(
        function() {
          css=$.urlParam('CSS');
          if (!css)
            css="";
          cssuri=$.urlParam('CSSURI');
          if (!cssuri)
            cssuri="";
          lng=$.urlParam('LNG');
          if (!lng || lng.trim()=="")
            lng="it";
          lng=lng.toLowerCase();
          var cssId = lng + 'Css';
          if (!document.getElementById(cssId)) {
            var head = document.getElementsByTagName('head')[0];
            var link = document.createElement('link');
            link.id = cssId;
            link.rel = 'stylesheet';
            link.type = 'text/css';
            link.href = '/client/pop/css/flussu_chat_' + lng + '.css';
            link.media = 'all';
            head.appendChild(link);

            var link2 = document.createElement('link');
            link2.rel = 'stylesheet';
            link2.type = 'text/css';
            if (cssuri=="")
              link2.href = '/client/pop/css/flussu_chat' + css + '.css';
            else
              link2.href = decodeURIComponent(cssuri);
            link2.media = 'all';
            head.appendChild(link2);
          }
          $("#flussu-startMsgElm").hide();
          trm=decodeURIComponent($.urlParam('TRM'));
          if (trm){
            trms=trm.split(",");
            trm="{";
            for (i=0;i<trms.length;i++){
              ttsnd=trms[i];
              elms=trms[i].split("=");
              if (elms.length>1)          
                ttsnd=elms[0].replace(/"([^"]+(?="))"/g, '$1')+'":"'+elms[1].replace(/"([^"]+(?="))"/g, '$1')+'"';
              trm+='"$'+ttsnd+',';
            }
            trm=trm.substring(0, trm.length - 1)+"}"; 
          } else {
            trm='{"$ChatTesting":"true", "$user":"Testing"}';
          }
          startFlussu(lng.toUpperCase(), trm);
        }
      );
    </script>
  </body>
</html>

# Comandi di Programmazione wofoEnv

Documentazione completa dei comandi disponibili nell'oggetto `wofoEnv` utilizzabili negli step di Flussu.

Gli step possono contenere codice PHP che viene eseguito tramite l'oggetto `wofoEnv` disponibile automaticamente nel contesto di esecuzione.

---

## Indice

- [Gestione Dati](#gestione-dati)
- [Gestione Exit e Flussi](#gestione-exit-e-flussi)
- [Gestione Sessione](#gestione-sessione)
- [Informazioni Sistema](#informazioni-sistema)
- [Comunicazioni](#comunicazioni)
- [HTTP e API](#http-e-api)
- [Validazione e Check](#validazione-e-check)
- [Web Scraping](#web-scraping)
- [AI (Intelligenza Artificiale)](#ai-intelligenza-artificiale)
- [Pagamenti](#pagamenti)
- [PDF](#pdf)
- [Excel e Google Sheets](#excel-e-google-sheets)
- [Elementi UI Dinamici](#elementi-ui-dinamici)
- [Notifiche](#notifiche)
- [Utility](#utility)
- [Comandi Esterni](#comandi-esterni)
- [Debug](#debug)
- [Reminder](#reminder)
- [MultiRec Workflow](#multirec-workflow)

---

## Gestione Dati

### recData($varName, $varValue)
Registra una variabile e il suo valore nel contesto del workflow.

```php
wofoEnv->recData('nome_utente', 'Mario Rossi');
wofoEnv->recData('eta', 35);
wofoEnv->recData('email', 'mario@example.com');
```

### recDataFile($fileName, $filePath)
Acquisisce un file e lo memorizza come variabile (codificato in base64 con prefisso `§FILE§:`).

```php
wofoEnv->recDataFile('documento', '/path/to/documento.pdf');
// Il file sarà disponibile come $§FILE§:documento
```

### getData($varName)
Recupera il valore di una variabile precedentemente registrata.

```php
$nome = wofoEnv->getData('nome_utente');
$eta = wofoEnv->getData('eta');
```

### clearData()
Cancella tutti i dati registrati nel workflow.

```php
wofoEnv->clearData();
```

### getDataJson()
Restituisce tutti i dati registrati in formato JSON.

```php
$jsonData = wofoEnv->getDataJson();
// Esempio output: {"nome_utente":"Mario Rossi","eta":35}
```

### setDataJson($theJsonData)
Carica dati da una stringa JSON.

```php
$jsonData = '{"nome":"Mario","cognome":"Rossi"}';
wofoEnv->setDataJson($jsonData);
```

### makeJson($varArray)
Converte un array PHP in stringa JSON.

```php
$array = ['nome' => 'Mario', 'cognome' => 'Rossi'];
$json = wofoEnv->makeJson($array);
```

### readJson($varArray)
Decodifica una stringa JSON in oggetto PHP.

```php
$json = '{"nome":"Mario","cognome":"Rossi"}';
$obj = wofoEnv->readJson($json);
echo $obj->nome; // Output: Mario
```

---

## Gestione Exit e Flussi

### setExit($exitNum)
Imposta l'uscita da utilizzare per il prossimo blocco.

```php
// Esce dall'uscita 0
wofoEnv->setExit(0);

// Esce dall'uscita 1 in caso di errore
if ($errore) {
    wofoEnv->setExit(1);
}
```

### getExit()
Restituisce il numero dell'uscita impostata.

```php
$uscita = wofoEnv->getExit();
```

### goToFlussu($subprocWid)
Naviga verso un altro workflow (flussu).

```php
wofoEnv->goToFlussu('[W123456789ABC]');
```

### return($varArr = null)
Ritorna al workflow chiamante, opzionalmente passando dati.

```php
// Ritorno semplice
wofoEnv->return();

// Ritorno con dati
$datiRitorno = ['risultato' => 'OK', 'valore' => 100];
wofoEnv->return($datiRitorno);
```

### callSubwf($subWID, $varArray)
Chiama un sub-workflow passando parametri.

```php
$parametri = ['cliente_id' => 123, 'importo' => 500];
wofoEnv->callSubwf('[W987654321XYZ]', $parametri);
```

---

## Gestione Sessione

### getWID() / getSelfWid()
Restituisce il Workflow ID corrente.

```php
$wid = wofoEnv->getWID();
// Esempio output: [W123456789ABC]
```

### getBlockId() / getSelfBid()
Restituisce il Block ID corrente.

```php
$bid = wofoEnv->getBlockId();
// Esempio output: 1234-5678-90AB-CDEF
```

### getSessionId() / getSelfSid()
Restituisce il Session ID corrente.

```php
$sid = wofoEnv->getSessionId();
```

### setLang($langId)
Imposta la lingua della sessione.

```php
wofoEnv->setLang('IT'); // Italiano
wofoEnv->setLang('EN'); // Inglese
wofoEnv->setLang('FR'); // Francese
wofoEnv->setLang('DE'); // Tedesco
wofoEnv->setLang('SP'); // Spagnolo
```

### getLang()
Restituisce la lingua corrente della sessione.

```php
$lingua = wofoEnv->getLang();
// Esempio output: IT
```

### setSessDurationHours($intHours)
Imposta la durata della sessione in ore.

```php
wofoEnv->setSessDurationHours(24); // La sessione durerà 24 ore
wofoEnv->setSessDurationHours(0.5); // La sessione durerà 30 minuti
```

### backHereUri()
Restituisce l'URI per tornare al punto corrente del workflow.

```php
$uriRitorno = wofoEnv->backHereUri();
// Può essere usato in link o email per riprendere il workflow
```

### isTimedcalled()
Verifica se il workflow è stato chiamato da un timer.

```php
if (wofoEnv->isTimedcalled()) {
    wofoEnv->recData('tipo_chiamata', 'automatica');
} else {
    wofoEnv->recData('tipo_chiamata', 'manuale');
}
```

### setRecallPoint()
Imposta il punto di richiamo per il timer.

```php
wofoEnv->setRecallPoint();
// Il workflow potrà essere richiamato da questo punto
```

### timedRecallIn($minutes)
Richiama il workflow dopo un numero di minuti.

```php
wofoEnv->setRecallPoint();
wofoEnv->timedRecallIn(60); // Richiama dopo 60 minuti
```

### timedRecallAt($dateTime)
Richiama il workflow a una data/ora specifica.

```php
wofoEnv->setRecallPoint();
wofoEnv->timedRecallAt('2025-12-31 23:59:59');
```

---

## Informazioni Sistema

### thisServer()
Restituisce l'indirizzo del server corrente.

```php
$server = wofoEnv->thisServer();
```

### getNow($langId = null) / getDateNow($langId = null)
Restituisce la data/ora corrente formattata secondo la lingua.

```php
$dataOra = wofoEnv->getNow(); // Usa la lingua della sessione
// Esempio output: Lun 16 Gen, 2025 - 14:30:45

$dataOraIT = wofoEnv->getNow('IT');
$dataOraEN = wofoEnv->getNow('EN');
```

### get_EnvVersion()
Restituisce la versione di Environment.

```php
$versione = wofoEnv->get_EnvVersion();
// Esempio output: 4.5.20250930
```

### get_EnvMedia()
Restituisce il tipo di media (pc, mobile, etc.).

```php
$media = wofoEnv->get_EnvMedia();
```

### get_EnvChannel()
Restituisce il canale di comunicazione (web, Telegram, WhatsApp, etc.).

```php
$canale = wofoEnv->get_EnvChannel();
```

### getUUID()
Genera un UUID versione 4.

```php
$uuid = wofoEnv->getUUID();
// Esempio output: 550e8400-e29b-41d4-a716-446655440000
```

---

## Comunicazioni

### sendEmail($toAddress, $subject, $message, $replyTo = "")
Invia una email semplice.

```php
wofoEnv->sendEmail(
    'destinatario@example.com',
    'Oggetto della mail',
    'Corpo del messaggio',
    'reply@example.com'
);
```

### sendPremiumEmail($toAddress, $subject, $message, $replyTo = "", $senderName = "")
Invia una email premium con nome mittente personalizzato.

```php
wofoEnv->sendPremiumEmail(
    'destinatario@example.com',
    'Benvenuto',
    'Grazie per esserti registrato!',
    'supporto@azienda.com',
    'Servizio Clienti'
);
```

### sendEmailwAttaches($toAddress, $subject, $message, $replyTo = "", $attachFiles = [])
Invia una email con allegati.

```php
$allegati = ['/path/to/documento.pdf', '/path/to/immagine.jpg'];
wofoEnv->sendEmailwAttaches(
    'destinatario@example.com',
    'Documenti richiesti',
    'In allegato trovi i documenti.',
    'info@azienda.com',
    $allegati
);
```

### sendPremiumEmailwAttaches($toAddress, $subject, $message, $replyTo = "", $senderName = "", $attachFiles = [])
Invia una email premium con allegati.

```php
$allegati = ['/path/to/fattura.pdf'];
wofoEnv->sendPremiumEmailwAttaches(
    'cliente@example.com',
    'Fattura del mese',
    'In allegato la fattura.',
    'amministrazione@azienda.com',
    'Ufficio Amministrazione',
    $allegati
);
```

### sendSms($senderName, $toPhoneNum, $message, $retVarName)
Invia un SMS.

```php
wofoEnv->sendSms(
    'AziendaXYZ',
    '+393331234567',
    'Il tuo codice di verifica è: 12345',
    'sms_result'
);
```

### sendTimedSms($senderName, $toPhoneNum, $message, $datetime, $retVarName)
Invia un SMS programmato.

```php
wofoEnv->sendTimedSms(
    'AziendaXYZ',
    '+393331234567',
    'Promemoria appuntamento domani ore 10:00',
    '2025-11-17 09:00:00',
    'sms_result'
);
```

---

## HTTP e API

### httpSend($URI, $dataArray = null, $retResVarName = null)
Invia una richiesta HTTP.

```php
// GET semplice
wofoEnv->httpSend('https://api.example.com/data', null, 'risultato');

// POST con dati
$dati = ['nome' => 'Mario', 'email' => 'mario@example.com'];
wofoEnv->httpSend('https://api.example.com/utenti', $dati, 'risposta');
```

### getResultFromHttpApi($URI, $method = "GET")
Effettua una chiamata API e restituisce il risultato direttamente.

```php
// GET
$risultato = wofoEnv->getResultFromHttpApi('https://api.example.com/users');

// POST
$risultato = wofoEnv->getResultFromHttpApi('https://api.example.com/create', 'POST');
```

### doZAP($URI, $retResVarName, $dataArray = null)
Invia dati a Zapier o IFTTT.

```php
$dati = ['evento' => 'nuovo_ordine', 'importo' => 100];
wofoEnv->doZAP(
    'https://hooks.zapier.com/hooks/catch/12345/abcde/',
    'zapier_result',
    $dati
);
```

---

## Validazione e Check

### checkCodFiscale($codFisc, $retIfGood, $retSexVarName, $retBirthdateVarName)
Verifica la validità di un codice fiscale italiano.

```php
wofoEnv->checkCodFiscale(
    'RSSMRA80A01H501Z',
    'cf_valido',
    'sesso',
    'data_nascita'
);
// Imposterà le variabili: $cf_valido (true/false), $sesso (M/F), $data_nascita
```

### getCodFiscaleInfo($codFisc)
Ottiene informazioni dettagliate da un codice fiscale.

```php
$info = wofoEnv->getCodFiscaleInfo('RSSMRA80A01H501Z');
// $info->isGood (true/false)
// $info->sex (M/F)
// $info->bDate (data di nascita)
// $info->yOld (età in anni)
```

### checkPIva($pIva, $retIfGood)
Verifica la validità di una partita IVA italiana.

```php
wofoEnv->checkPIva('12345678901', 'piva_valida');
// Imposterà $piva_valida a true o false
```

### checkEmailAddress($emailAddr)
Verifica se un indirizzo email esiste ed è valido.

```php
$valida = wofoEnv->checkEmailAddress('utente@example.com');
// Imposta anche la variabile $isGoodEmailAddress
```

### isDisposableEmailAddress($emailAddr)
Verifica se un indirizzo email è temporaneo/usa-e-getta.

```php
$temporanea = wofoEnv->isDisposableEmailAddress('test@guerrillamail.com');
// Restituisce 1 se è temporanea, 0 se non lo è, null in caso di errore
```

### isGoodText($theText)
Verifica se un testo ha senso (non è gibberish).

```php
$testoBuono = wofoEnv->isGoodText('Questo è un testo valido');
// Restituisce true

$testoNonSenso = wofoEnv->isGoodText('asdfghjkl zxcvbnm');
// Restituisce false
```

### normalizePhoneNum($baseIntlCode, $thePhonenumInput)
Normalizza un numero di telefono.

```php
$numeroNormalizzato = wofoEnv->normalizePhoneNum('+39', '333 123.45-67');
// Output: +393331234567
```

---

## Web Scraping

### getHtml($url)
Ottiene il codice HTML di una pagina web.

```php
$html = wofoEnv->getHtml('https://www.example.com');
```

### getText($url)
Estrae il testo leggibile da una pagina web.

```php
$testo = wofoEnv->getText('https://www.example.com/articolo');
// Restituisce solo il testo senza tag HTML
```

### getMarkdown($url)
Converte una pagina web in formato Markdown.

```php
$markdown = wofoEnv->getMarkdown('https://www.example.com/blog/post');
```

### getPageJson($url)
Ottiene il contenuto di una pagina in formato JSON.

```php
$json = wofoEnv->getPageJson('https://api.example.com/data');
```

### getWebSearch($query, $language = 'it', $location = 'it')
Effettua una ricerca web usando DuckDuckGo.

```php
$risultati = wofoEnv->getWebSearch('flussu workflow', 'it', 'it');
// Restituisce un JSON con i risultati della ricerca

$risultatiEN = wofoEnv->getWebSearch('workflow automation', 'en', 'us');
```

---

## AI (Intelligenza Artificiale)

### initAiAgent($initChatText)
Inizializza un agente AI con un testo di contesto.

```php
wofoEnv->initAiAgent('Sei un assistente esperto di cucina italiana.');
```

### sendToAi($sendText, $varResponseName, $provider = 0)
Invia un messaggio all'AI e riceve la risposta.

**Provider disponibili:**
- `0` = ChatGPT (default)
- `1` = Grok
- `2` = Gemini
- `3` = DeepSeek
- `4` = Claude

```php
// Usa ChatGPT (default)
wofoEnv->sendToAi(
    'Dammi una ricetta per la carbonara',
    'ricetta_carbonara'
);

// Usa Claude
wofoEnv->initAiAgent('Sei un esperto di programmazione PHP.');
wofoEnv->sendToAi(
    'Come si crea un array associativo in PHP?',
    'risposta_php',
    4
);

// Conversazione continua
wofoEnv->initAiAgent('Sei un tutor di matematica.');
wofoEnv->sendToAi('Cos\'è il teorema di Pitagora?', 'risposta1');
wofoEnv->sendToAi('Puoi farmi un esempio pratico?', 'risposta2');
```

---

## Pagamenti

### getPaymentLink($provider, $configId, $keyType, $paymentId, $description, $amount, $imageUri, $successUri, $cancelUri, $varStripeRetUriName)
Genera un link di pagamento.

**Provider:** `stripe`, `paypal`, etc.
**keyType:** `test` o `prod`
**amount:** Importo in centesimi (es. 4999 = 49,99€)

```php
wofoEnv->getPaymentLink(
    'stripe',
    'milleisole',
    'test',
    'ORD-12345',
    'Abbonamento Premium',
    4999,
    'https://www.example.com/img/prodotto.jpg',
    'https://www.example.com/pagamento-ok',
    'https://www.example.com/pagamento-annullato',
    'stripe_link'
);
```

### getStripeChargeInfo($stripeChargeId, $keyName = "")
Ottiene informazioni su un pagamento Stripe.

```php
$info = wofoEnv->getStripeChargeInfo('ch_1234567890abcdef');
// Restituisce: session id, intent id, charge id, customer info, amount, paid, receipt URL, metadata, etc.
```

### getStripeSessInfo($configId, $keyType, $stripeSessId)
Ottiene informazioni su una sessione di pagamento Stripe.

```php
$info = wofoEnv->getStripeSessInfo('milleisole', 'prod', 'cs_test_1234567890');
```

---

## PDF

### printToPdf($title, $txt2Prn, $var4Filename)
Crea un PDF semplice senza intestazione/piè di pagina.

```php
$contenuto = '{t}Titolo Documento{/t}{p}Questo è il contenuto.{/p}';
wofoEnv->printToPdf('Documento', $contenuto, 'percorso_pdf');
// La variabile $percorso_pdf conterrà il path del file generato
```

### print2PdfwHF($title, $txt2Prn, $flxTxtHead, $flxTxtFoot, $var4Fname)
Crea un PDF con intestazione e piè di pagina personalizzati.

```php
$intestazione = '{pl}Logo Azienda - Tel: 123456789{/pl}';
$piede = '{center}Pagina {PAGENO} di {nb}{/center}';
$contenuto = '{t}Report Mensile{/t}{p}Dati del mese...{/p}';

wofoEnv->print2PdfwHF(
    'Report',
    $contenuto,
    $intestazione,
    $piede,
    'percorso_report'
);
```

### printRawHtml2Pdf($theHtml, $var4Fname)
Converte HTML grezzo in PDF.

```php
$html = '<html><body><h1>Titolo</h1><p>Contenuto HTML</p></body></html>';
wofoEnv->printRawHtml2Pdf($html, 'percorso_pdf_html');
```

---

## Excel e Google Sheets

### excelAddRow($fileName, $arrData)
Aggiunge una riga a un file Excel.

```php
$dati = ['Mario Rossi', 'mario@example.com', '333-1234567'];
wofoEnv->excelAddRow('clienti.xlsx', $dati);
```

### addToGoogleSheet($fileId, $rowArray, $sheetName = "", $formulaArray = [], $TitleArray = [])
Aggiunge dati a un Google Sheet.

```php
// Aggiunta semplice
$riga = ['Mario Rossi', 35, 'mario@example.com'];
wofoEnv->addToGoogleSheet('1ABC...XYZ', $riga, 'Clienti');

// Con titoli e formule
$titoli = ['Nome', 'Età', 'Email', 'Totale'];
$riga = ['Mario Rossi', 35, 'mario@example.com', ''];
$formule = [3 => '=B2*10']; // Colonna D (indice 3)

wofoEnv->addToGoogleSheet(
    '1ABC...XYZ',
    $riga,
    'Clienti',
    $formule,
    $titoli
);
```

---

## Elementi UI Dinamici

### createLabel($labelText)
Crea una etichetta nel blocco corrente.

```php
wofoEnv->createLabel('{b}Attenzione:{/b} Compilare tutti i campi obbligatori.');
```

### createButton($buttonVarName, $clickValue, $buttonText, $buttonExit = 0, $buttonCss = "", $skipValidation = false)
Crea un pulsante dinamico.

```php
// Pulsante semplice
wofoEnv->createButton('scelta', 'conferma', 'Conferma', 0);

// Pulsante con uscita personalizzata e CSS
wofoEnv->createButton(
    'azione',
    'annulla',
    'Annulla',
    1,
    'btn-danger',
    true
);
```

### createInputStandard($inputVarName, $inputValue, $suggestText, $isMandatory = false, $inputCss = "")
Crea un campo di input standard.

```php
wofoEnv->createInputStandard(
    'nome',
    '',
    'Inserisci il tuo nome',
    true,
    'form-control'
);
```

### createInputEmail($inputVarName, $inputValue, $suggestText, $isMandatory = false, $inputCss = "")
Crea un campo di input per email.

```php
wofoEnv->createInputEmail(
    'email',
    '',
    'Inserisci la tua email',
    true
);
```

### createInputMultirow($inputVarName, $inputValue, $suggestText, $isMandatory = false, $inputCss = "")
Crea un campo di testo multiriga (textarea).

```php
wofoEnv->createInputMultirow(
    'messaggio',
    '',
    'Scrivi il tuo messaggio',
    false,
    'form-control'
);
```

### createSelect($inputVarName, $selectType, $inputValues, $isMandatory = false, $inputCss = "")
Crea un campo select.

**Tipi di select:**
- `SS` = Standard (dropdown)
- `SE` = Esclusivo (radio buttons)
- `SM` = Multiplo (checkboxes)

```php
// Select standard
$opzioni = ['rosso' => 'Rosso', 'verde' => 'Verde', 'blu' => 'Blu'];
wofoEnv->createSelect('colore', 'SS', $opzioni, true);

// Radio buttons
wofoEnv->createSelect('dimensione', 'SE', ['S' => 'Small', 'M' => 'Medium', 'L' => 'Large']);

// Checkboxes multiple
wofoEnv->createSelect('interessi', 'SM', ['sport' => 'Sport', 'musica' => 'Musica', 'cinema' => 'Cinema']);
```

---

## Notifiche

### alert($message)
Mostra un messaggio di alert all'utente.

```php
wofoEnv->alert('Operazione completata con successo!');
```

### addRowToChat($message)
Aggiunge un messaggio alla chat.

```php
wofoEnv->addRowToChat('Sistema: Elaborazione in corso...');
```

### counterInit($cntId, $description, $min, $max)
Inizializza un contatore visibile all'utente.

```php
wofoEnv->counterInit('progresso', 'Progresso elaborazione', 0, 100);
```

### counterValue($cntId, $value)
Aggiorna il valore di un contatore.

```php
wofoEnv->counterInit('progresso', 'Caricamento file', 0, 100);

for ($i = 0; $i <= 100; $i += 10) {
    wofoEnv->counterValue('progresso', $i);
    sleep(1);
}
```

### notifyClient($dataName, $dataValue)
Invia una notifica personalizzata al client.

```php
wofoEnv->notifyClient('aggiornamento', 'Nuovi dati disponibili');
```

### notify($dataName, $dataValue)
Notifica generica al sistema.

```php
wofoEnv->notify('stato_processo', 'completato');
```

### notifyCallback($callingBidIdentifier)
Imposta un callback verso un blocco specifico.

```php
// Callback verso un blocco specifico
wofoEnv->notifyCallback('1234-5678-90AB-CDEF');

// Callback verso un'uscita specifica
wofoEnv->notifyCallback('exit(0)');

// Callback verso un workflow specifico
wofoEnv->notifyCallback('[W123456789ABC]');

// Callback verso un blocco di un workflow specifico
wofoEnv->notifyCallback('[W123456789ABC]:1234-5678-90AB-CDEF');
```

---

## Utility

### generateNewPassword($len, $chars = "...")
Genera una password casuale.

```php
// Password di 12 caratteri con set predefinito
$password = wofoEnv->generateNewPassword(12);

// Password personalizzata
$password = wofoEnv->generateNewPassword(16, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
```

### generateNewCheckCode($len, $chars = "...")
Genera un codice di verifica casuale (senza caratteri ambigui).

```php
$codice = wofoEnv->generateNewCheckCode(6);
// Esempio: A4D9K2
```

### getRnd($from, $to)
Genera un numero casuale in un intervallo.

```php
$numero = wofoEnv->getRnd(1, 100);
// Numero casuale tra 1 e 100
```

### generateOTP($charQty = 5)
Genera un codice OTP (One-Time Password) numerico.

```php
$otp5 = wofoEnv->generateOTP(); // 5 cifre
$otp6 = wofoEnv->generateOTP(6); // 6 cifre
$otp8 = wofoEnv->generateOTP(8); // 8 cifre
```

### generateQrCode($data)
Genera un QR code.

```php
wofoEnv->generateQrCode('https://www.example.com');
wofoEnv->generateQrCode('Mario Rossi|mario@example.com|+393331234567');
```

### getShortUri($longUri)
Accorcia un URL lungo.

```php
$urlCorto = wofoEnv->getShortUri('https://www.example.com/percorso/molto/lungo/con/parametri?a=1&b=2');
```

### execBatch($batchName)
Esegue un file batch/script.

```php
$risultato = wofoEnv->execBatch('elaborazione.sh');
```

### readFile($fileName)
Legge un file dalla cartella Uploads/files.

```php
$righe = wofoEnv->readFile('dati.txt');
foreach ($righe as $riga) {
    // Elabora ogni riga
}
```

### execOcr($filepath, $retVarName)
Esegue OCR (riconoscimento testo) su un'immagine.

```php
wofoEnv->execOcr('/path/to/documento.jpg', 'testo_estratto');
// Imposta $testo_estratto con il testo riconosciuto
// Imposta $testo_estratto_error con eventuali errori
```

### requestUserInfo($retVarName)
Richiede informazioni all'utente.

```php
wofoEnv->requestUserInfo('info_utente');
```

### normalizeSurvey($arraySurvey)
Normalizza i dati di un questionario.

```php
$questionario = [
    'Q1' => (object)['tit' => 'Domanda 1', 'data' => [...], 'res' => 'risposta'],
    // ...
];
$normalizzato = wofoEnv->normalizeSurvey($questionario);
// Restituisce array con 'data' e 'flussu' (formato HTML)
```

### getHtmlFromFlussuText($theFlussuText)
Converte testo in formato Flussu in HTML.

```php
$testoFlussu = '{t}Titolo{/t}{p}Paragrafo{/p}{b}Grassetto{/b}';
$html = wofoEnv->getHtmlFromFlussuText($testoFlussu);
```

---

## Comandi Esterni

### getXCmdKey($srvAddr, $srvCmd, $user, $pass)
Ottiene una chiave per eseguire comandi esterni.

```php
wofoEnv->getXCmdKey(
    'https://api.example.com/command',
    'EXECUTE',
    'username',
    'password'
);
// Imposta la variabile $XCmdKey
```

### sendXCmdData($srvAddr, $cmdKey, $cmdJData, $retVarName)
Invia dati a un comando esterno.

```php
$dati = wofoEnv->makeJson(['azione' => 'importa', 'file' => 'dati.csv']);
wofoEnv->sendXCmdData(
    'https://api.example.com/command',
    wofoEnv->getData('XCmdKey'),
    $dati,
    'risultato_comando'
);
```

---

## Debug

### debugWrite($text)
Scrive un messaggio di debug.

```php
wofoEnv->debugWrite('Valore variabile X: ' . $x);
wofoEnv->debugWrite('Inizio elaborazione dati');
```

### log($logString)
Scrive un log generale (visibile).

```php
wofoEnv->log('Processo completato alle ' . date('H:i:s'));
```

### logDebug($logString)
Scrive un log di debug (solo in modalità debug).

```php
wofoEnv->logDebug('Debug: array dati = ' . print_r($dati, true));
```

---

## Reminder

### setReminderTo($reminderAddr)
Imposta un indirizzo per i promemoria.

```php
wofoEnv->setReminderTo('admin@example.com');
```

### getReminderTo()
Ottiene l'indirizzo dei promemoria impostato.

```php
$indirizzo = wofoEnv->getReminderTo();
```

---

## MultiRec Workflow

### createNewMultirecWf($wid, $uid, $uemail, $arrData, $varName)
Crea un nuovo workflow multi-record.

```php
$datiRecord = ['nome' => 'Mario', 'cognome' => 'Rossi'];
wofoEnv->createNewMultirecWf(
    '[W123456789ABC]',
    'user_123',
    'utente@example.com',
    $datiRecord,
    'nuovo_wf_id'
);
```

### addProcessVariable($varName, $varValue)
Aggiunge una variabile al processo.

```php
wofoEnv->addProcessVariable('stato', 'approvato');
wofoEnv->addProcessVariable('data_approvazione', date('Y-m-d H:i:s'));
```

---

## Note Importanti

### Formato Testo Flussu
Nei comandi che accettano testo formattato, si possono usare i seguenti tag:

- `{t}...{/t}` - Titolo
- `{p}...{/p}` - Paragrafo
- `{b}...{/b}` - Grassetto
- `{i}...{/i}` - Corsivo
- `{u}...{/u}` - Sottolineato
- `{center}...{/center}` - Centrato
- `{pl}` - Nuova riga
- `{pl1}` - Nuova riga con indentazione
- `{hr}` - Linea orizzontale
- `{pbr}` - Page break
- `{img}...{/img}` - Immagine

### Variabili di Sistema

Flussu imposta automaticamente alcune variabili di sistema:

- `$_scriptCallerUri` - URI del chiamante
- `$_dtc_recallPoint` - Punto di richiamo per timer
- `$isTelegram` - true se chiamato da Telegram
- `$isApp` - true se chiamato da App
- `$isMessenger` - true se chiamato da Messenger
- `$isWhatsapp` - true se chiamato da WhatsApp
- `$XCmdKey` - Chiave per comandi esterni

### Best Practices

1. **Gestione Errori**: Utilizzare sempre i parametri di ritorno per verificare il successo delle operazioni
2. **Debug**: Usare `debugWrite()` durante lo sviluppo e `log()` in produzione
3. **Sessioni**: Impostare sempre una durata appropriata per le sessioni con `setSessDurationHours()`
4. **Dati Sensibili**: Non includere dati sensibili nei log
5. **Validazione**: Validare sempre gli input utente prima di processarli
6. **Email Temporanee**: Controllare con `isDisposableEmailAddress()` per evitare registrazioni fake

---

## Esempi Completi

### Esempio 1: Registrazione Utente con Validazione

```php
// Validazione email
if (!wofoEnv->checkEmailAddress($email)) {
    wofoEnv->alert('Email non valida');
    wofoEnv->setExit(1);
    return;
}

// Controllo email temporanea
if (wofoEnv->isDisposableEmailAddress($email) == 1) {
    wofoEnv->alert('Non è consentito usare email temporanee');
    wofoEnv->setExit(1);
    return;
}

// Genera password temporanea
$password = wofoEnv->generateNewPassword(12);

// Salva dati
wofoEnv->recData('email', $email);
wofoEnv->recData('password', $password);

// Invia email di benvenuto
wofoEnv->sendPremiumEmail(
    $email,
    'Benvenuto!',
    'La tua password temporanea è: ' . $password,
    'noreply@example.com',
    'Sistema di Registrazione'
);

wofoEnv->setExit(0);
```

### Esempio 2: Processo con Timer e Promemoria

```php
// Imposta punto di richiamo
wofoEnv->setRecallPoint();

// Salva dati ordine
wofoEnv->recData('ordine_id', $ordineId);
wofoEnv->recData('data_ordine', date('Y-m-d H:i:s'));

// Invia conferma
wofoEnv->sendEmail(
    $emailCliente,
    'Conferma ordine #' . $ordineId,
    'Il tuo ordine è stato ricevuto.'
);

// Programma verifica dopo 24 ore
wofoEnv->timedRecallIn(1440); // 24 ore = 1440 minuti
wofoEnv->setSessDurationHours(26); // Mantieni sessione per 26 ore
```

### Esempio 3: Integrazione con AI

```php
// Inizializza assistente AI
wofoEnv->initAiAgent('Sei un esperto di prodotti per la casa. Rispondi in modo conciso e professionale.');

// Prima domanda
wofoEnv->sendToAi(
    'Quali sono i vantaggi di un aspirapolvere robot?',
    'risposta_aspirapolvere'
);

$risposta1 = wofoEnv->getData('risposta_aspirapolvere');
wofoEnv->createLabel($risposta1);

// Domanda di follow-up (mantiene il contesto)
wofoEnv->sendToAi(
    'Quale modello consigli per un appartamento di 80mq?',
    'risposta_modello'
);

$risposta2 = wofoEnv->getData('risposta_modello');
wofoEnv->createLabel($risposta2);
```

### Esempio 4: Creazione Report PDF con Dati da Google Sheet

```php
// Raccogli dati
$datiRiga = [
    date('Y-m-d'),
    $nomeCliente,
    $importo,
    $prodotto
];

// Aggiungi a Google Sheet
$titoli = ['Data', 'Cliente', 'Importo', 'Prodotto', 'Totale Anno'];
$formule = [4 => '=SUM(C:C)']; // Totale nella colonna E

wofoEnv->addToGoogleSheet(
    '1ABC...XYZ',
    $datiRiga,
    'Vendite2025',
    $formule,
    $titoli
);

// Genera PDF
$intestazione = '{center}{b}Report Vendite{/b}{/center}';
$piede = '{center}Pagina {PAGENO}{/center}';
$contenuto = '{t}Vendita Registrata{/t}' .
             '{p}Cliente: ' . $nomeCliente . '{/p}' .
             '{p}Prodotto: ' . $prodotto . '{/p}' .
             '{p}Importo: € ' . number_format($importo, 2) . '{/p}';

wofoEnv->print2PdfwHF(
    'Report Vendita',
    $contenuto,
    $intestazione,
    $piede,
    'percorso_pdf'
);

// Invia PDF via email
$pdfPath = wofoEnv->getData('percorso_pdf');
wofoEnv->sendEmailwAttaches(
    $emailCliente,
    'Conferma vendita',
    'In allegato il report della vendita.',
    'vendite@example.com',
    [$pdfPath]
);
```

### Esempio 5: UI Dinamica con Form Personalizzato

```php
// Crea etichetta introduttiva
wofoEnv->createLabel('{t}Registrazione Cliente{/t}{p}Compila il form sottostante:{/p}');

// Campo nome (obbligatorio)
wofoEnv->createInputStandard(
    'nome_completo',
    '',
    'Nome e Cognome',
    true,
    'form-control'
);

// Campo email (obbligatorio)
wofoEnv->createInputEmail(
    'email_cliente',
    '',
    'Indirizzo Email',
    true,
    'form-control'
);

// Select categoria cliente
$categorie = [
    'privato' => 'Privato',
    'azienda' => 'Azienda',
    'ente' => 'Ente Pubblico'
];
wofoEnv->createSelect('categoria', 'SE', $categorie, true);

// Checkbox interessi multipli
$interessi = [
    'newsletter' => 'Ricevi newsletter',
    'offerte' => 'Ricevi offerte speciali',
    'eventi' => 'Informazioni su eventi'
];
wofoEnv->createSelect('interessi', 'SM', $interessi, false);

// Note aggiuntive (non obbligatorie)
wofoEnv->createInputMultirow(
    'note',
    '',
    'Note aggiuntive (opzionale)',
    false,
    'form-control'
);

// Pulsanti
wofoEnv->createButton('azione', 'invia', 'Invia Registrazione', 0, 'btn-primary');
wofoEnv->createButton('azione', 'annulla', 'Annulla', 1, 'btn-secondary', true);
```

---

## File Sorgente

- **Environment.php**: `/src/Flussu/Flussuserver/Environment.php`
- **Executor.php**: `/src/Flussu/Flussuserver/Executor.php`

---

*Documentazione generata per Flussu v4.5.1 - Ultimo aggiornamento: 2025-11-16*

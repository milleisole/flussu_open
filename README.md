# flussuserver 4.2.20250625 Open Source

What you need to put it on work:

  a. Server operating system (linux/windows)
  
  b. Php 8 from >=8.1 (avoid 8.0.*)
  
  c. Composer (or windows version)
  
  d. Apache2  (or IIS)
  
  e. Mariadb (v.>=11)
  
  non mandatory:

     *. DomPdf (if you need to PDF/PRINT something)

     *. Tesseract OCR (if you want to use the OCR functions)
     Python 3 (if you want to use OCR features)

How to install:

1. Copy the code "as is" in a dir with apache read permissions
2. create an Upload dir with "WebServer User" write permissions
3. inside that folder create flussus_01, flussus_02, temp folders 
    with "WebServer User" write permissions
4. if you need to use OCR function also create inside that folder
    the OCR and OCR-ri folders with "WebServer User" write permissions

then: 

5. create database (see /Docs/install folder)
6. add apache2 config (see /Docs/install folder)
   at this stage we do not have IIS config needs

then use the following command at prompt:

7. apt-get install chromium-chromedriver firefox-geckodriver 
   (non mandatory, it is needed if you want to use the scraping functions)
8. Then add security for various dir, add cron call and install vendor software (composer)
   **sh batchinstall.sh**

configure your installation:

9. use the file ".env.sample" rename to ".env" then open with a text editor
   and configure as you need/wish
10. in the /config dir you can find the services.json (*.sample*, **rename it**).
    We start to use this config file to handle multiple configs (i.e. the zapier
    accoun/api token for company ONE and company TWO, Stripe config, Email configs,
    and so on) 

Open a browser and call flussu server to check database version and check for
any database updates:

11. http(s)://yourwebsite.com/views

# Docs & Database
take a look at the /Docs folder

# 4.1 - New functionalities
### CACHE 
Now Flussu server can cache their objects, and deploy it in the /Cache dir. Every time you delete it, it will be rebuilt when requested.
Each time you update a workflow, all the cached content will be deleted.

### TEXT LOGS
In the /Logs folder you can find one month of text logs full of info about execution, caching, errors, and so on.

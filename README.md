# 🔄 FLUSSU - Workflow Automation Server

<div align="center">

![FLUSSU Logo](docs/images/flussu_logo.png)

**Un potente BPM (Business Process Management) server basato su architettura SOA**

[![Version](https://img.shields.io/badge/version-5.0-blue.svg)](https://github.com/yourusername/flussu)
[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4.svg?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-10.8%2B-4479A1.svg?logo=mysql)](https://www.mysql.com)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)](LICENSE)

[English](#english) | [Italiano](#italiano)

</div>

---

<a name="italiano"></a>

## 🇮🇹 Italiano

### 📖 Cos'è FLUSSU?

**FLUSSU** (WoFoBot - WOrkFlOw-roBot) è un server di automazione dei processi aziendali che permette di **progettare, eseguire e gestire workflow complessi** attraverso un'interfaccia grafica intuitiva.

Basato su un'architettura SOA (Service Oriented Architecture) e sul paradigma degli **automi a stati finiti**, FLUSSU trasforma processi complessi in flussi visuali facilmente gestibili.

### ✨ Caratteristiche Principali

#### 🎨 **Editor Grafico Visuale**
- Progetta processi con drag & drop
- Rappresentazione visuale chiara del flusso
- Debug interattivo dei processi
- Versioning e backup automatico

#### 🌍 **Multilingua Nativo**
- Supporto completo per contenuti multi-lingua
- Gestione elementi UI localizzati
- Switch automatico lingua per utente

#### 🔌 **Multi-Channel**
Esegui i tuoi workflow su qualsiasi canale:
- 🌐 **Web**: Browser moderni (Chrome, Firefox, Safari, Edge)
- 💬 **Chat Apps**: Telegram, WhatsApp, Webchat
- 🔗 **API/REST**: Integrazione con sistemi esterni
- 📱 **Mobile**: App native via API
- 🖥️ **Backend**: PHP, C#, Node.js, Python

#### 🏗️ **Architettura Robusta**
- **SOA compliant**: Servizi indipendenti e scalabili
- **Automi a stati finiti**: Processi deterministici e prevedibili
- **Sub-processes**: Supporto per workflow annidati
- **Multi-flow v3.0**: Stesso processo, dati diversi per più clienti

#### ⚡ **Performance Ottimizzate (v5.0)**
- Sistema cache a 3 livelli (APCu + File + Database)
- Query builder ottimizzato con prepared statements pool
- Serializzazione session incrementale
- Response time < 150ms (target)

### 🎯 Casi d'Uso

#### 📋 **Gestione Processi Aziendali**
```
Richiesta Ferie → Approvazione Manager → Notifica HR → Aggiornamento Sistema
```

#### 🛒 **E-commerce & Order Management**
```
Ordine Cliente → Verifica Stock → Pagamento → Spedizione → Tracking → Feedback
```

#### 📞 **Customer Service Automation**
```
Ticket → Categorizzazione → Assegnazione → Risoluzione → Chiusura → Survey
```

#### 🤖 **Chatbot Conversazionali**
```
Messaggio User → Interpretazione → Azione → Risposta Personalizzata
```

#### 📊 **Data Processing Pipeline**
```
Input Dati → Validazione → Trasformazione → Arricchimento → Output
```

### 🚀 Quick Start

#### Prerequisiti
```bash
- PHP 8.0+
- MySQL 10.8+ / MariaDB
- Apache 2.4+ / Nginx
- Composer
```

#### Installazione

```bash
# 1. Clone repository
git clone https://github.com/yourusername/flussu.git
cd flussu

# 2. Install dependencies
composer install

# 3. Configura database
mysql -u root -p < database.sql

# 4. Configura environment
cp .env.example .env
nano .env

# 5. Configura web server
cp flussu_web_apache2.conf /etc/apache2/sites-available/flussu.conf
sudo a2ensite flussu
sudo systemctl reload apache2

# 6. Warm cache (opzionale ma raccomandato)
php scripts/cache_warm.php
```

#### Primo Workflow

```php
// 1. Accedi all'editor BPM
http://your-domain.com/bpm-editor

// 2. Crea un nuovo workflow
// 3. Aggiungi blocchi e connessioni
// 4. Salva e pubblica
// 5. Testa via API

// API Call Example
POST http://your-domain.com/flx/api/execute
{
  "workflow_id": 123,
  "action": "start",
  "data": {
    "user_input": "Hello FLUSSU!"
  }
}
```

### 📚 Documentazione

La documentazione completa è disponibile nella cartella `/docs`:

- **[Architettura Completa](docs/FLUSSU_Analisi_Architettura_Completa_v5.md)** - Analisi tecnica dettagliata
- **[Guida Implementazione](docs/FLUSSU_Guida_Implementazione_v5_0.md)** - Guida step-by-step per upgrade v5.0
- **[Manuale Utente](docs/manuale_Flussu_2_0.docx)** - Manuale per utilizzatori
- **[Mobile Development](docs/mobile_application_development_v1_2.docx)** - Sviluppo app mobile

### 🏗️ Architettura

```
┌──────────────────────┐  ┌──────────────────────┐
│      BPM EDITOR      │  │  html txt BPM EDITOR │
│   (Visual Designer)  │  │  -or- workflow file  │
└─────────────┬────────┘  └───────┬──────────────┘
              │                   │
              ▼                   ▼
┌──────────────────────────────────────────────────┐
│           REPOSITORY (Database)                  │
│   • Workflow Definitions                         │
│   • Blocks & Elements                            │
│   • Multi-language Content                       │
└────────────────┬─────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────┐
│       FLUSSU PROCESS ENGINE (WoFoBot)            │
│                                                  │
│   Engine.php → Worker.php → Handler.php          │
│                    ↓                             │
│              Session.php                         │
└────────────────┬─────────────────────────────────┘
                 │
    ┌────────────┴────────────┐
    │                         │
    ▼                         ▼
┌─────────────┐         ┌──────────────┐
│  FRONTEND   │         │   BACKEND    │
│  • Web      │         │   • APIs     │
│  • Mobile   │         │   • Systems  │
│  • Chat     │         │   • Services │
└─────────────┘         └──────────────┘
```

### 🛠️ Tecnologie

- **Backend**: PHP 8.x
- **Database**: MySQL 10.8+ / MariaDB
- **Cache**: APCu + File cache
- **Web Server**: Apache 2.4+ / Nginx
- **Frontend**: JavaScript, HTML5, CSS3
- **API**: REST/JSON

### 📊 Performance (v5.0)

| Metrica | v4.5 | v5.0 | Miglioramento |
|---------|------|------|---------------|
| Response Time (p50) | ~300ms | ~120ms | **-60%** ✅ |
| Response Time (p99) | ~800ms | ~350ms | **-56%** ✅ |
| DB Queries/Request | 15-25 | 5-10 | **-60%** ✅ |
| Cache Hit Rate | ~40% | ~80% | **+100%** ✅ |
| Throughput | ~50 req/s | 150 req/s | **+200%** ✅ |

### 🤝 Contribuire

Siamo aperti a contributi! Per favore leggi [CONTRIBUTING.md](CONTRIBUTING.md) per dettagli sul nostro processo di sviluppo.

### 📝 Licenza

Questo progetto è proprietario. Vedi [LICENSE](LICENSE) per dettagli.

### 📧 Contatti

- **Website**: [www.flussu.com](https://www.flussu.com)
- **Email**: info@flussu.com
- **Supporto**: support@flussu.com

### 👥 Team

Sviluppato da [Mille Isole SRL](https://www.milleisole.com) - Startup innovativa di Palermo

---

<a name="english"></a>

## 🇬🇧 English

### 📖 What is FLUSSU?

**FLUSSU** (WoFoBot - WOrkFlOw-roBot) is a business process automation server that allows you to **design, execute, and manage complex workflows** through an intuitive graphical interface.

Based on SOA (Service Oriented Architecture) and the **finite state machine** paradigm, FLUSSU transforms complex processes into easily manageable visual flows.

### ✨ Key Features

#### 🎨 **Visual Graphic Editor**
- Design processes with drag & drop
- Clear visual representation of flows
- Interactive process debugging
- Automatic versioning and backup

#### 🌍 **Native Multi-language**
- Full support for multi-language content
- Localized UI elements management
- Automatic language switching per user

#### 🔌 **Multi-Channel**
Execute your workflows on any channel:
- 🌐 **Web**: Modern browsers (Chrome, Firefox, Safari, Edge)
- 💬 **Chat Apps**: Telegram, WhatsApp, Webchat
- 🔗 **API/REST**: Integration with external systems
- 📱 **Mobile**: Native apps via API
- 🖥️ **Backend**: PHP, C#, Node.js, Python

#### 🏗️ **Robust Architecture**
- **SOA compliant**: Independent and scalable services
- **Finite state machines**: Deterministic and predictable processes
- **Sub-processes**: Support for nested workflows
- **Multi-flow v3.0**: Same process, different data for multiple clients

#### ⚡ **Optimized Performance (v5.0)**
- 3-level cache system (APCu + File + Database)
- Optimized query builder with prepared statements pool
- Incremental session serialization
- Response time < 150ms (target)

### 🎯 Use Cases

#### 📋 **Business Process Management**
```
Leave Request → Manager Approval → HR Notification → System Update
```

#### 🛒 **E-commerce & Order Management**
```
Customer Order → Stock Check → Payment → Shipping → Tracking → Feedback
```

#### 📞 **Customer Service Automation**
```
Ticket → Categorization → Assignment → Resolution → Closure → Survey
```

#### 🤖 **Conversational Chatbots**
```
User Message → Interpretation → Action → Personalized Response
```

#### 📊 **Data Processing Pipeline**
```
Data Input → Validation → Transformation → Enrichment → Output
```

### 🚀 Quick Start

#### Prerequisites
```bash
- PHP 8.0+
- MySQL 10.8+ / MariaDB
- Apache 2.4+ / Nginx
- Composer
```

#### Installation

```bash
# 1. Clone repository
git clone https://github.com/yourusername/flussu.git
cd flussu

# 2. Install dependencies
composer install

# 3. Setup database
mysql -u root -p < database.sql

# 4. Configure environment
cp .env.example .env
nano .env

# 5. Configure web server
cp flussu_web_apache2.conf /etc/apache2/sites-available/flussu.conf
sudo a2ensite flussu
sudo systemctl reload apache2

# 6. Warm cache (optional but recommended)
php scripts/cache_warm.php
```

#### First Workflow

```php
// 1. Access BPM editor
http://your-domain.com/bpm-editor

// 2. Create a new workflow
// 3. Add blocks and connections
// 4. Save and publish
// 5. Test via API

// API Call Example
POST http://your-domain.com/flx/api/execute
{
  "workflow_id": 123,
  "action": "start",
  "data": {
    "user_input": "Hello FLUSSU!"
  }
}
```

### 📚 Documentation

Complete documentation is available in the `/docs` folder:

- **[Complete Architecture](docs/FLUSSU_Analisi_Architettura_Completa_v5.md)** - Detailed technical analysis
- **[Implementation Guide](docs/FLUSSU_Guida_Implementazione_v5_0.md)** - Step-by-step guide for v5.0 upgrade
- **[User Manual](docs/manuale_Flussu_2_0.docx)** - User manual
- **[Mobile Development](docs/mobile_application_development_v1_2.docx)** - Mobile app development

### 🏗️ Architecture

```
┌──────────────────────┐  ┌──────────────────────┐
│      BPM EDITOR      │  │  html txt BPM EDITOR │
│   (Visual Designer)  │  │  -or- workflow file  │
└─────────────┬────────┘  └───────┬──────────────┘
              │                   │
              ▼                   ▼
┌──────────────────────────────────────────────────┐
│           REPOSITORY (Database)                  │
│   • Workflow Definitions                         │
│   • Blocks & Elements                            │
│   • Multi-language Content                       │
└────────────────┬─────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────┐
│       FLUSSU PROCESS ENGINE (WoFoBot)            │
│                                                  │
│   Engine.php → Worker.php → Handler.php         │
│                    ↓                             │
│              Session.php                         │
└────────────────┬─────────────────────────────────┘
                 │
    ┌────────────┴────────────┐
    │                         │
    ▼                         ▼
┌─────────────┐         ┌──────────────┐
│  FRONTEND   │         │   BACKEND    │
│  • Web      │         │   • APIs     │
│  • Mobile   │         │   • Systems  │
│  • Chat     │         │   • Services │
└─────────────┘         └──────────────┘
```

### 🛠️ Technologies

- **Backend**: PHP 8.x
- **Database**: MySQL 10.8+ / MariaDB
- **Cache**: APCu + File cache
- **Web Server**: Apache 2.4+ / Nginx
- **Frontend**: JavaScript, HTML5, CSS3
- **API**: REST/JSON

### 📊 Performance (v5.0)

| Metric | v4.5 | v5.0 | Improvement |
|--------|------|------|-------------|
| Response Time (p50) | ~300ms | ~120ms | **-60%** ✅ |
| Response Time (p99) | ~800ms | ~350ms | **-56%** ✅ |
| DB Queries/Request | 15-25 | 5-10 | **-60%** ✅ |
| Cache Hit Rate | ~40% | ~80% | **+100%** ✅ |
| Throughput | ~50 req/s | 150 req/s | **+200%** ✅ |

### 🤝 Contributing

We welcome contributions! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our development process.

### 📝 License

This project is proprietary. See [LICENSE](LICENSE) for details.

### 📧 Contact

- **Website**: [www.flussu.com](https://www.flussu.com)
- **Email**: info@flussu.com
- **Support**: support@flussu.com

### 👥 Team

Developed by [Mille Isole SRL](https://www.milleisole.com) - Innovative startup from Palermo, Italy

---

<div align="center">

**Made with ❤️ in Palermo and Parma, Italy 🇮🇹**

[⬆ Back to top](#-flussu---workflow-automation-server)

</div>
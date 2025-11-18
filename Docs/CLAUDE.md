# CLAUDE.md - AI Assistant Guide for Flussu Server

**Version**: 4.5.1 (Updated: 2025-11-15)
**Purpose**: Comprehensive guide for AI assistants working on the Flussu Server codebase

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Quick Start for AI Assistants](#quick-start-for-ai-assistants)
3. [Codebase Structure](#codebase-structure)
4. [Development Conventions](#development-conventions)
5. [Common Development Tasks](#common-development-tasks)
6. [Testing & Debugging](#testing--debugging)
7. [External Integrations](#external-integrations)
8. [Database Guidelines](#database-guidelines)
9. [Critical Files Reference](#critical-files-reference)
10. [Things to Avoid](#things-to-avoid)

---

## Project Overview

### What is Flussu Server?

Flussu Server is a **PHP-based workflow automation platform** that enables:
- Visual workflow creation with block-based execution
- Multi-step, stateful process automation
- External service integrations (AI, payments, webhooks, cloud storage, SMS, etc.)
- Multi-language support
- Session-based workflow state management
- Webhook routing and processing

### Technology Stack

**Backend**:
- **PHP**: 8.1+ (PSR-4 autoloading via Composer)
- **Database**: MariaDB 11+ (MySQL-compatible, InnoDB engine)
- **Web Server**: Apache2 with mod_rewrite (or Nginx)

**Key Dependencies**:
- AI: Claude, OpenAI, Gemini, DeepSeek, Grok, Hugging Face, Mooonshot, Kimi
- Payments: Stripe, Revolut
- Cloud: Google Drive, AWS
- Documents: DomPDF, PhpSpreadsheet
- Web Automation: Symfony Panther (Selenium)
- HTTP: Guzzle
- Email: PHPMailer

**Frontend**:
- JavaScript client libraries (`/webroot/flucli/`)
- HTML5 QR code, Puppeteer

### Architecture Pattern

```
HTTP Request → /webroot/api.php (entry point)
    ├─→ Webhook Detection (User-Agent based)
    │   └─→ ZapierController / IftttController / StripeController
    ├─→ Version Management
    │   └─→ VersionController
    └─→ Workflow Execution
        └─→ FlussuController
            └─→ Api/V40/Engine
                └─→ Flussuserver/Session
                    └─→ Flussuserver/Worker
                        └─→ Flussuserver/Executor
                            └─→ Flussuserver/Command
                                └─→ Controllers (external services)
```

---

## Quick Start for AI Assistants

### First Steps When Engaging

1. **Check if task involves**:
   - [ ] Adding new integration → See [Adding External Integrations](#adding-a-new-external-integration)
   - [ ] Modifying workflow engine → Review `/src/Flussu/Flussuserver/`
   - [ ] Database changes → See [Database Guidelines](#database-guidelines)
   - [ ] API changes → Review `/webroot/api.php` and `/src/Flussu/Api/V40/`
   - [ ] Webhook modifications → Review `WEBHOOK_INTEGRATION.md`

2. **Always read** these files for context:
   - `README.md` - Installation and setup
   - `WEBHOOK_INTEGRATION.md` - Webhook architecture (if webhook-related)
   - `/config/.services.json.sample` - Service configuration structure
   - `.env.sample` - Environment variables

3. **Key file locations**:
   - Configuration: `/config/.services.json`, `.env`
   - Entry point: `/webroot/api.php`
   - Controllers: `/src/Flussu/Controllers/`
   - Core engine: `/src/Flussu/Flussuserver/`
   - Database: `/src/Flussu/Beans/Dbh.php`

### Environment Setup

```bash
# Install dependencies
composer install
npm install  # For puppeteer, html5-qrcode

# Setup directories
chmod -R 775 Uploads Logs Log_sys webroot

# Configure environment
cp .env.sample .env
cp config/.services.json.sample config/.services.json
# Edit both files with appropriate values

# Setup database
# Import Docs/Install/database.sql into MariaDB

# Setup cron (for timed calls)
cd bin && chmod +x add2cron.sh && ./add2cron.sh
```

---

## Codebase Structure

### Directory Layout

```
/home/user/flussu_open/
├── src/Flussu/                    # Main PHP codebase (PSR-4 autoloaded)
│   ├── General.php                # Utilities, logging, encryption
│   ├── Config.php                 # Singleton config manager
│   ├── Api/V40/                   # HTTP API layer
│   ├── Controllers/               # External service integrations
│   ├── Flussuserver/              # Core workflow engine
│   ├── Beans/                     # Data access layer
│   ├── Contracts/                 # Interfaces (I* pattern)
│   ├── Documents/                 # File handling
│   └── Persons/                   # User management
├── webroot/                       # Public web directory
│   ├── api.php                    # MAIN ENTRY POINT
│   ├── notify.php                 # Async notifications
│   ├── assets/                    # Static files
│   ├── client/                    # Client libraries & samples
│   └── flucli/                    # JavaScript client SDKs
├── config/                        # Configuration
│   └── .services.json             # Service configs (gitignored)
├── bin/                           # Executables, cron scripts
├── Docs/                          # Documentation
│   ├── Install/                   # Database, Apache config
│   └── Man/                       # Manuals, diagrams
├── Uploads/                       # File storage (gitignored)
│   ├── flussus_01/, flussus_02/  # Upload areas
│   ├── temp/                      # Temporary files
│   └── OCR/, OCR-ri/              # OCR processing
├── Cache/                         # Runtime cache (gitignored)
├── Logs/                          # Application logs (gitignored)
└── vendor/                        # Composer packages (gitignored)
```

### Core Namespaces

```php
Flussu\                           # Root namespace
├── General                       # Static utility class
├── Config                        # Configuration singleton
├── Api\V40\                      # API version 4.0
│   ├── Engine                    # Request processor
│   ├── Flow                      # Workflow control
│   ├── Sess                      # Session management
│   └── Conn                      # Connection management
├── Controllers\                  # Service controllers
│   ├── FlussuController          # Main workflow orchestrator
│   ├── Abs*                      # Abstract base classes
│   └── *Controller               # Specific integrations
├── Flussuserver\                 # Workflow engine
│   ├── Session                   # Session management
│   ├── Worker                    # Block execution
│   ├── Executor                  # Command execution
│   ├── Command                   # External calls
│   └── Handler                   # Request handling
├── Beans\                        # Data layer
│   ├── Dbh                       # Database handler
│   └── User                      # User bean
└── Contracts\                    # Interfaces
    ├── IPayProvider
    ├── ISmsProvider
    ├── IWebhookProvider
    ├── IAiProvider
    └── ICloudStorageProvider
```

---

## Development Conventions

### Naming Conventions

**Classes**:
- Controllers: `*Controller.php` (e.g., `StripeController`)
- Abstract classes: `Abs*` prefix (e.g., `AbsPayProviders`)
- Interfaces: `I*` prefix (e.g., `IPayProvider`)
- Beans: Plain names (e.g., `Dbh`, `User`)

**Database**:
- Tables: `t{number}_{name}` (e.g., `t10_workflow`, `t200_worker`)
- Columns: `c{table_number}_{name}` (e.g., `c10_id`, `c200_sess_id`)

**Files**:
- PHP classes: PascalCase matching class name
- Config files: `.services.json`, `.env`
- Documentation: `@readme.txt` in directories

### Design Patterns

#### 1. Strategy Pattern (Providers)

Used for pluggable external services. **Always follow this pattern** when adding new integrations.

```php
// 1. Define interface in /src/Flussu/Contracts/
interface IPayProvider {
    public function processPayment($amount, $currency);
}

// 2. Create abstract base in /src/Flussu/Controllers/
abstract class AbsPayProviders implements IPayProvider {
    protected function validateAmount($amount) { /* ... */ }
}

// 3. Implement concrete controller
class StripeController extends AbsPayProviders {
    public function processPayment($amount, $currency) { /* ... */ }
}
```

**Current provider interfaces**:
- `IPayProvider` → Payment gateways (Stripe, Revolut)
- `ISmsProvider` → SMS services (Jomobile, SMSFactor)
- `IWebhookProvider` → Webhook services (Zapier, IFTTT)
- `IAiProvider` → AI services (OpenAI, Claude, Gemini, etc.)
- `ICloudStorageProvider` → Cloud storage (Google Drive, etc.)
- `IUriShrinkProvider` → URL shorteners

#### 2. Singleton Pattern (Configuration)

```php
// Config is a singleton - loaded once per request
use Flussu\Config;

$cfg = Config::init();  // First call loads config
$cfg = Config::init();  // Subsequent calls return same instance

// Access via dot notation
$apiKey = config('services.ai_provider.open_ai.auth_key');
```

**Important**: Config is immutable after initialization. Don't try to modify it at runtime.

#### 3. Single Entry Point

**All HTTP requests** go through `/webroot/api.php`. This file:
1. Detects webhook sources (User-Agent matching)
2. Routes version management requests
3. Handles direct workflow webhooks
4. Delegates to `FlussuController::apiCall()` for workflow execution

**Never bypass api.php** - always route through it.

#### 4. Session-Based State

Workflows maintain state across requests using sessions:
- Each execution has a unique session UUID (SID)
- Session data stored in database (`t200_worker`)
- Variables persisted per session
- Access via `$_SESSION["Log"]`, `$_SESSION["_WorkFlow"]`, etc.

### Code Style

**PSR-4 Autoloading**:
```php
// composer.json
"autoload": {
    "psr-4": {
        "Flussu\\": "src/Flussu/"
    }
}
```

**Namespace Usage**:
```php
namespace Flussu\Controllers;

use Flussu\General;
use Flussu\Config;
use Flussu\Beans\Dbh;

class MyController {
    // ...
}
```

**Logging**:
```php
use Flussu\General;

// Session log (stored in $_SESSION["Log"])
General::addLog("User action completed", "INFO");

// File log (stored in /Logs/YYYY-MM-DD.log)
General::log("Critical error occurred", "ERROR");
```

**Configuration Access**:
```php
// Using helper function
$apiKey = config('services.stripe.secret_key');

// Using Config class
use Flussu\Config;
$cfg = Config::init();
$apiKey = $cfg->get('services.stripe.secret_key');
```

**Database Access**:
```php
use Flussu\Beans\Dbh;

$dbh = new Dbh();
$dbh->BeginTrans();

$sql = "SELECT c10_id, c10_name FROM t10_workflow WHERE c10_wf_auid = ?";
$stmt = $dbh->db->prepare($sql);
$stmt->execute([$workflowId]);
$workflow = $stmt->fetch(PDO::FETCH_ASSOC);

$dbh->CommitTrans();
```

---

## Common Development Tasks

### Adding a New External Integration

**Example: Adding a new webhook provider**

1. **Create the interface** (if not exists):
```php
// /src/Flussu/Contracts/IWebhookProvider.php
namespace Flussu\Contracts;

interface IWebhookProvider {
    public function apiCall($request);
    public function validateSignature($request);
}
```

2. **Create abstract base** (if not exists):
```php
// /src/Flussu/Controllers/AbsWebhookProvider.php
namespace Flussu\Controllers;

use Flussu\Contracts\IWebhookProvider;

abstract class AbsWebhookProvider implements IWebhookProvider {
    protected function getUserCredentials($userId) {
        $cfg = \Flussu\Config::init();
        // Load from config...
    }
}
```

3. **Implement concrete controller**:
```php
// /src/Flussu/Controllers/MakeController.php
namespace Flussu\Controllers;

class MakeController extends AbsWebhookProvider {
    public function apiCall($request) {
        // Set CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

        // Validate authentication
        if (!$this->validateSignature($request)) {
            http_response_code(401);
            return json_encode(['error' => 'Unauthorized']);
        }

        // Process webhook
        // ...
    }

    public function validateSignature($request) {
        // Implement validation logic
    }
}
```

4. **Register in `/config/.services.json.sample`**:
```json
{
  "webhooks": {
    "make": {
      "sign": ["Make/", "integromat"],
      "call": "MakeController@apiCall",
      "credentials": [
        {
          "userid": "user123",
          "servicekey": "$2y$10$hashed_password_here"
        }
      ]
    }
  }
}
```

5. **Update webhook detection** in `/webroot/api.php`:
```php
function checkUnattendedWebHookCall($req, $apiPage) {
    $cfg = Config::init();
    $webhooks = $cfg->get("webhooks") ?? [];

    foreach ($webhooks as $name => $webhook) {
        $signatures = $webhook['sign'] ?? [];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        foreach ($signatures as $signature) {
            if (stripos($userAgent, $signature) !== false) {
                // Call configured controller
                $callSpec = $webhook['call'];
                list($className, $method) = explode('@', $callSpec);

                $fullClassName = "Flussu\\Controllers\\{$className}";
                $controller = new $fullClassName();
                return $controller->$method($req);
            }
        }
    }
    return null;
}
```

6. **Document in `WEBHOOK_INTEGRATION.md`** (if webhook-related)

### Adding a New AI Provider

1. **Create provider class**:
```php
// /src/Flussu/Api/Ai/FlussuMyAi.php
namespace Flussu\Api\Ai;

use Flussu\Contracts\IAiProvider;

class FlussuMyAi implements IAiProvider {
    private $apiKey;
    private $model;

    public function __construct() {
        $this->apiKey = config('services.ai_provider.my_ai.auth_key');
        $this->model = config('services.ai_provider.my_ai.model');
    }

    public function chat($messages, $options = []) {
        // Implement chat API call
    }
}
```

2. **Add to `/config/.services.json.sample`**:
```json
{
  "services": {
    "ai_provider": {
      "my_ai": {
        "auth_key": "YOUR_API_KEY",
        "model": "my-model-name"
      }
    }
  }
}
```

3. **Integrate into `AiChatController`**:
```php
// /src/Flussu/Controllers/AiChatController.php
public function getProvider($providerName) {
    switch($providerName) {
        case 'my_ai':
            return new \Flussu\Api\Ai\FlussuMyAi();
        // ... other cases
    }
}
```

### Modifying the Workflow Engine

**Key files** in `/src/Flussu/Flussuserver/`:
- `Session.php` - Session lifecycle management
- `Worker.php` - Block-level execution logic
- `Executor.php` - Command parsing and execution
- `Command.php` - External service command implementations

**Example: Adding a new workflow command**

1. **Add command method** in `/src/Flussu/Flussuserver/Command.php`:
```php
public function doMyCommand($params) {
    General::addLog("Executing MyCommand with params: " . json_encode($params));

    try {
        // Implement command logic
        $result = $this->performAction($params);

        // Store result in session variable
        $this->setVariable('mycommand_result', $result);

        return ['success' => true, 'data' => $result];
    } catch (\Exception $e) {
        General::log("MyCommand failed: " . $e->getMessage(), "ERROR");
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

2. **Register command** in `Executor.php` command parsing logic

3. **Update workflow builder** (if UI-based) to expose new command

### Database Schema Changes

**IMPORTANT**: Always increment database version and provide migration path.

1. **Create migration SQL**:
```sql
-- /Docs/Install/updates/update_v12.sql

-- Update version table
UPDATE t00_version SET c00_db_ver = 12 WHERE c00_id = 1;

-- Add new table or columns
ALTER TABLE t200_worker
ADD COLUMN c200_new_field VARCHAR(255) DEFAULT NULL
AFTER c200_existing_field;

-- Or create new table
CREATE TABLE IF NOT EXISTS t250_new_feature (
    c250_id INT AUTO_INCREMENT PRIMARY KEY,
    c250_name VARCHAR(255) NOT NULL,
    c250_created DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (c250_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

2. **Update version check** in `/src/Flussu/Controllers/VersionController.php`:
```php
const DB_VERSION = 12;  // Increment this

public function checkVersion() {
    $dbh = new Dbh();
    $stmt = $dbh->db->query("SELECT c00_db_ver FROM t00_version WHERE c00_id = 1");
    $currentVersion = $stmt->fetchColumn();

    if ($currentVersion < self::DB_VERSION) {
        return ['update_required' => true, 'current' => $currentVersion, 'target' => self::DB_VERSION];
    }
    // ...
}
```

3. **Add migration logic** (if automated migrations supported)

4. **Update main `/Docs/Install/database.sql`** with new schema

5. **Document changes** in commit message and relevant documentation

---

## Testing & Debugging

### Current Testing Status

**Limited formal testing infrastructure**:
- No PHPUnit configuration
- No comprehensive test suite
- Manual testing via debug tools

### Manual Testing Tools

**Debug Interface**: `/webroot/debug/`
- `wfdebug.php` - Workflow debugger
- `_notif_list.php` - Notification viewer

**Sample Clients**: `/webroot/client/`
- `api/php_sample.php` - PHP API usage examples
- `form/sample/` - Form samples

**Test Endpoints**:
```bash
# Check server status
curl http://localhost/

# Check database version
curl http://localhost/checkversion

# Test workflow execution
curl -X POST http://localhost/flussueng \
  -H "Content-Type: application/json" \
  -d '{"WID": "test-workflow", "CMD": "info"}'
```

### Logging & Debugging

**Session Logs**:
```php
use Flussu\General;

// Add to session log (visible in response, stored in $_SESSION["Log"])
General::addLog("Debug message: " . print_r($data, true), "DEBUG");
```

**File Logs** (`/Logs/YYYY-MM-DD.log`):
```php
// Critical errors, system events
General::log("Error occurred: " . $error, "ERROR");
General::log("System event: database backup completed", "INFO");
```

**Log Levels**: DEBUG, INFO, WARN, ERROR

**Viewing Logs**:
```bash
# Today's log
tail -f Logs/$(date +%Y-%m-%d).log

# Search logs
grep -r "ERROR" Logs/

# Session-specific logs
# Check $_SESSION["Log"] in API response
```

### Common Issues & Solutions

**Issue: "Class not found" error**
- Run `composer dump-autoload`
- Check namespace matches directory structure (PSR-4)

**Issue: "Database connection failed"**
- Verify `.env` has correct `db_host`, `db_name`, `db_user`, `db_pass`
- Check MariaDB is running: `systemctl status mariadb`
- Test connection: `mysql -u flussu_user -p -h localhost flussu_db`

**Issue: "Permission denied" on Uploads/**
- Run: `chmod -R 775 Uploads Logs Log_sys Cache`
- Ensure web server user (e.g., `www-data`) has write access

**Issue: "Config not found"**
- Ensure `/config/.services.json` exists (copy from `.services.json.sample`)
- Check file permissions: `chmod 644 config/.services.json`
- Verify JSON syntax: `php -r "json_decode(file_get_contents('config/.services.json'));"`

**Issue: Cron tasks not running**
- Check crontab: `crontab -l`
- Verify `/bin/flussu.sh` is executable: `chmod +x bin/flussu.sh`
- Check cron logs: `tail /var/log/syslog | grep CRON`

---

## External Integrations

### Webhook System Architecture

**User-Agent Based Routing** (in `/webroot/api.php`):
```php
checkUnattendedWebHookCall($req, $apiPage)
  ↓
  Checks $_SERVER['HTTP_USER_AGENT'] against configured signatures
  ↓
  Matches: Loads controller class dynamically
  ↓
  Calls configured method (e.g., ZapierController@apiCall)
```

**Configured Webhooks** (in `/config/.services.json`):
- **Zapier**: User-Agent contains "Zapier"
- **IFTTT**: User-Agent contains "IFTTT"
- **Stripe**: User-Agent contains "Stripe/" or "//stripe.com/"
- **Revolut**: User-Agent contains "Revolut "

**Direct Webhooks**: `/wh/{workflow-id}/{optional-block-id}`

### Authentication Patterns

**HTTP Basic Auth** (Zapier, IFTTT):
```php
$auth = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

// Verify against bcrypt hash in config
if (!password_verify($pass, $storedHash)) {
    http_response_code(401);
    return json_encode(['error' => 'Unauthorized']);
}
```

**API Key Auth** (most other services):
```php
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$configKey = config('services.my_service.api_key');

if ($apiKey !== $configKey) {
    http_response_code(403);
    return json_encode(['error' => 'Forbidden']);
}
```

**Service Key Auth** (custom webhooks):
```php
// Configured in .services.json per user
$userId = $request['userid'] ?? '';
$serviceKey = $request['servicekey'] ?? '';

$credentials = config("webhooks.my_webhook.credentials") ?? [];
foreach ($credentials as $cred) {
    if ($cred['userid'] === $userId &&
        password_verify($serviceKey, $cred['servicekey'])) {
        // Authenticated
    }
}
```

### AI Provider Integration

**All AI providers** implement `IAiProvider` interface:

```php
use Flussu\Api\Ai\FlussuOpenAi;
use Flussu\Api\Ai\FlussuClaudeAi;

$provider = new FlussuClaudeAi();
$response = $provider->chat([
    ['role' => 'user', 'content' => 'Hello, Claude!']
], [
    'model' => 'claude-3-5-sonnet-20241022',
    'temperature' => 0.7
]);
```

**Available providers**:
- OpenAI (GPT-4, O1)
- Claude (Anthropic)
- Gemini (Google)
- DeepSeek
- Grok (xAI)
- Hugging Face

**Configuration** in `/config/.services.json`:
```json
{
  "services": {
    "ai_provider": {
      "open_ai": {
        "auth_key": "sk-...",
        "model": "o1-mini"
      },
      "ant_claude": {
        "auth_key": "sk-ant-...",
        "model": "claude-3-5-sonnet-20241022"
      }
    }
  }
}
```

### Payment Provider Integration

**Strategy pattern implementation**:

```php
use Flussu\Controllers\StripeController;

$stripe = new StripeController();
$stripe->createPaymentIntent([
    'amount' => 1000,  // In cents
    'currency' => 'usd'
]);
```

**Webhook handling**:
```php
// Stripe webhook verification
$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$webhook_secret = config('services.pay_provider.stripe.webhook_secret');

$event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
```

---

## Database Guidelines

### Connection Management

**Always use transactions** for data modifications:

```php
use Flussu\Beans\Dbh;

$dbh = new Dbh();
try {
    $dbh->BeginTrans();

    // Perform queries
    $stmt = $dbh->db->prepare("INSERT INTO t200_worker (c200_sess_id, c200_wf_id) VALUES (?, ?)");
    $stmt->execute([$sessionId, $workflowId]);

    $dbh->CommitTrans();
} catch (\Exception $e) {
    $dbh->RollbackTrans();
    General::log("Database error: " . $e->getMessage(), "ERROR");
    throw $e;
}
```

### Prepared Statements

**Always use prepared statements** to prevent SQL injection:

```php
// GOOD
$stmt = $dbh->db->prepare("SELECT * FROM t10_workflow WHERE c10_wf_auid = ?");
$stmt->execute([$workflowId]);

// BAD - NEVER DO THIS
$sql = "SELECT * FROM t10_workflow WHERE c10_wf_auid = '$workflowId'";
$result = $dbh->db->query($sql);
```

### Table Reference

**Key tables**:
- `t00_version` - Database version (current: 11)
- `t01_app` - Applications
- `t05_app_lang` - Multi-language app content
- `t10_workflow` - Workflow definitions
- `t15_workflow_backup` - Workflow backups (JSON)
- `t100_timed_call` - Scheduled tasks (cron)
- `t200_worker` - Active workflow sessions
- `t203_notifications` - Notification queue

### Column Naming

**Pattern**: `c{table_number}_{descriptive_name}`

Examples:
- `t10_workflow` columns: `c10_id`, `c10_wf_auid`, `c10_name`, `c10_app_code`
- `t200_worker` columns: `c200_id`, `c200_sess_id`, `c200_wf_id`, `c200_block_id`

### Charset & Collation

**Always use**:
- Charset: `utf8mb4` (full Unicode support including emojis)
- Collation: `utf8mb4_general_ci`
- Engine: `InnoDB` (transactions, foreign keys)

```sql
CREATE TABLE t_example (
    c_id INT AUTO_INCREMENT PRIMARY KEY,
    c_name VARCHAR(255) NOT NULL,
    INDEX idx_name (c_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

---

## Critical Files Reference

### Entry Points

**`/webroot/api.php`** - Main HTTP entry point
- Routes all requests
- Webhook detection
- Version management
- Workflow execution delegation

**`/webroot/notify.php`** - Async notification handler
- Session-agnostic notifications
- Used for background tasks

**`/bin/flussu.sh`** - Cron entry point
- Runs every minute (via cron)
- Executes timed calls

### Core Engine

**`/src/Flussu/Flussuserver/Session.php`** (53.4 KB)
- Session lifecycle management
- Session creation, loading, saving
- Variable management

**`/src/Flussu/Flussuserver/Worker.php`** (69.9 KB)
- Block-level execution
- Workflow navigation
- Block state management

**`/src/Flussu/Flussuserver/Executor.php`** (44.1 KB)
- Command parsing
- Command execution orchestration
- Variable substitution

**`/src/Flussu/Flussuserver/Command.php`** (47.6 KB)
- External service command implementations
- doEmail(), doSMS(), doPDF(), doAI(), etc.

### Configuration

**`/src/Flussu/Config.php`** - Singleton configuration manager
- Loads `/config/.services.json`
- Provides dot notation access
- Immutable after initialization

**`/src/Flussu/General.php`** - Utility functions
- Logging (session + file)
- Encryption (curtatone/montanara)
- String manipulation
- Date/time utilities

### Controllers

**`/src/Flussu/Controllers/FlussuController.php`** - Main workflow orchestrator
- Entry point for workflow execution
- Delegates to Api/V40/Engine

**`/src/Flussu/Controllers/VersionController.php`** - Version management
- Database version checking
- Update mechanisms

**`/src/Flussu/Controllers/AbsWebhookProvider.php`** - Webhook base class
- Common webhook functionality
- Credential management
- CORS headers

### Database

**`/src/Flussu/Beans/Dbh.php`** - Database handler
- PDO wrapper
- Transaction management
- Connection pooling

### Documentation

**`README.md`** - Installation guide
**`WEBHOOK_INTEGRATION.md`** - Webhook architecture
**`INTEGRATION_ANALYSIS.md`** - Zapier/IFTTT analysis
**`/Docs/Install/database.sql`** - Complete DB schema
**`/Docs/Install/flussu.web.apache2.conf`** - Apache config

---

## Things to Avoid

### Critical Don'ts

1. **NEVER modify `/webroot/api.php` routing logic** without understanding full impact
   - All requests flow through this file
   - Changes affect entire system

2. **NEVER bypass PSR-4 autoloading**
   - Don't use `require` or `include` for Flussu classes
   - Use `namespace` and `use` statements

3. **NEVER store sensitive data in code**
   - Use `.env` for credentials
   - Use `/config/.services.json` for API keys
   - Both files are gitignored

4. **NEVER commit**:
   - `.env` (use `.env.sample`)
   - `/config/.services.json` (use `.services.json.sample`)
   - `/vendor/`
   - `/Uploads/`, `/Cache/`, `/Logs/`, `/Log_sys/`
   - Credentials or API keys

5. **NEVER modify database without version increment**
   - Always update `t00_version`
   - Provide migration path
   - Document changes

6. **NEVER use raw SQL with user input**
   - Always use prepared statements
   - Prevents SQL injection

7. **NEVER skip transaction management**
   - Use `BeginTrans()` / `CommitTrans()` / `RollbackTrans()`
   - Ensures data consistency

8. **NEVER hardcode configuration values**
   - Use `config()` helper or `Config::init()->get()`
   - Makes code environment-agnostic

9. **NEVER ignore error handling**
   - Wrap risky operations in try-catch
   - Log errors with `General::log()`
   - Return meaningful error messages

10. **NEVER break backward compatibility** without major version bump
    - API changes affect existing workflows
    - Database changes affect existing data

### Performance Considerations

1. **Avoid N+1 queries**
   - Use JOINs or bulk queries
   - Cache frequently accessed data

2. **Use caching** (`/Cache/` directory)
   - Workflow definitions cached automatically
   - Consider caching external API responses

3. **Clean up old data**
   - Logs auto-delete after 1 month
   - Implement cleanup for session data, temp files

4. **Optimize large file handling**
   - Use streaming for large uploads
   - Process in chunks when possible

---

## Development Workflow

### Making Changes

1. **Create feature branch**:
```bash
git checkout -b feature/my-new-feature
```

2. **Make changes** following conventions above

3. **Test locally**:
```bash
# Run composer autoload
composer dump-autoload

# Test endpoint
curl -X POST http://localhost/flussueng -d '{"WID":"test"}'

# Check logs
tail -f Logs/$(date +%Y-%m-%d).log
```

4. **Commit with clear message**:
```bash
git add .
git commit -m "Add MyFeature integration with webhook support"
```

5. **Push and create PR**:
```bash
git push origin feature/my-new-feature
```

### Code Review Checklist

- [ ] Follows PSR-4 autoloading
- [ ] Uses appropriate design pattern (Strategy for providers)
- [ ] Prepared statements for all database queries
- [ ] Error handling with try-catch
- [ ] Logging for debugging
- [ ] Configuration in `.services.json.sample`
- [ ] Documentation updated
- [ ] No sensitive data committed
- [ ] Backward compatible (or versioned appropriately)

---

## Quick Reference Commands

```bash
# Install dependencies
composer install && npm install

# Setup permissions
chmod -R 775 Uploads Logs Log_sys Cache webroot

# Setup cron
cd bin && chmod +x add2cron.sh && ./add2cron.sh

# View logs
tail -f Logs/$(date +%Y-%m-%d).log

# Test database connection
mysql -u flussu_user -p -h localhost flussu_db

# Refresh autoloader
composer dump-autoload

# Check PHP syntax
php -l src/Flussu/Controllers/MyController.php

# Validate JSON config
php -r "json_decode(file_get_contents('config/.services.json'));"

# Test endpoint
curl http://localhost/checkversion
curl -X POST http://localhost/flussueng -d '{"WID":"test","CMD":"info"}'
```

---

## Getting Help

1. **Check logs** first:
   - `/Logs/YYYY-MM-DD.log` - File logs
   - Session logs in API response

2. **Review documentation**:
   - `README.md` - Installation
   - `WEBHOOK_INTEGRATION.md` - Webhooks
   - `/Docs/Man/` - Architecture diagrams, manuals

3. **Examine similar implementations**:
   - Look at existing controllers for patterns
   - Check `/webroot/client/` for API usage examples

4. **Debug with tools**:
   - `/webroot/debug/wfdebug.php` - Workflow debugger
   - Browser developer tools for client-side issues

---

## Changelog

- **2025-11-15**: Created comprehensive CLAUDE.md
  - Added based on v4.5.1 codebase analysis
  - Documented recent webhook integration improvements
  - Included Zapier/IFTTT integration patterns

---

**End of CLAUDE.md**

For questions or updates, please refer to the project maintainers or create an issue in the repository.

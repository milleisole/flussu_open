# Flussu Webhook Integration Guide

## Overview

Flussu 4.5.1 introduces a unified, secure, and extensible architecture for webhook integrations with automation platforms like Zapier, IFTTT, Make.com, n8n, and others.

## Architecture

### Design Pattern

The webhook system follows the **Strategy Pattern** with a three-tier architecture:

```
IWebhookProvider (Interface)
    ↓
AbsWebhookProvider (Abstract Base Class)
    ↓                           ↓
ZapierController          IftttController
```

This mirrors the payment provider architecture (`IPayProvider` → `AbsPayProviders` → `StripeController`), ensuring consistency across the codebase.

### Components

1. **`IWebhookProvider`** (`src/Flussu/Contracts/IWebhookProvider.php`)
   - Interface defining required methods for all webhook providers
   - Methods: `apiCall()`, `authenticate()`, `extractData()`, `executeWorkflow()`

2. **`AbsWebhookProvider`** (`src/Flussu/Controllers/AbsWebhookProvider.php`)
   - Abstract base class with common functionality
   - Handles CORS, authentication, error reporting, workflow execution
   - Protected methods for extension by specific providers

3. **Provider Controllers**
   - `ZapierController` - Zapier integration
   - `IftttController` - IFTTT integration
   - Easy to add more: Make.com, n8n, Integromat, etc.

## Security Improvements

### Before (v4.5.0 and earlier)

```php
// HARDCODED CREDENTIALS - SECURITY RISK!
if ($usrName=="pippuzzo" && ($usrPass=="giannuzzo123" || $usrPass=="giannuzzo"))
    $theFlussuUser->load(16);
if ($usrName=="aldus" && ($usrPass=="pattavina"))
    $theFlussuUser->load(16);
```

### After (v4.5.1+)

```php
// Secure authentication from config/database
$uid = $this->authenticate($username, $password);
```

Credentials are now:
- ✅ Stored in configuration files (outside webroot)
- ✅ Support bcrypt password hashing
- ✅ Can be moved to database easily
- ✅ No hardcoded secrets in code
- ✅ Multiple users per provider supported

## Configuration

### Step 1: Copy Configuration Template

```bash
cp config/.services.json.sample config/.services.json
```

### Step 2: Configure Webhook Credentials

Edit `config/.services.json`:

```json
{
  "webhooks": {
    "zapier": {
      "sign": ["Zapier"],
      "call": "ZapierController@apiCall",
      "credentials": [
        {
          "username": "zapier_user",
          "password": "your_secure_password",
          "user_id": 16
        }
      ]
    },
    "ifttt": {
      "sign": ["IFTTT"],
      "call": "IftttController@apiCall",
      "credentials": [
        {
          "username": "ifttt",
          "password": "your_ifttt_service_key",
          "user_id": 16
        }
      ]
    }
  }
}
```

### Step 3: Hash Passwords (Recommended)

For enhanced security, use bcrypt hashed passwords:

```php
<?php
// Generate hash (run once, then copy to config)
echo password_hash('your_actual_password', PASSWORD_BCRYPT);
// Output: $2y$10$abcdefghijklmnopqrstuv...
```

Then use the hash in config:

```json
{
  "username": "zapier_user",
  "password": "$2y$10$abcdefghijklmnopqrstuv...",
  "user_id": 16
}
```

### Step 4: Set Permissions

```bash
chmod 600 config/.services.json
chown www-data:www-data config/.services.json
```

## Usage Examples

### Zapier Integration

#### 1. Configure Zapier Webhook

In your Zap:
- **Action**: Webhooks by Zapier → POST Request
- **URL**: `https://your-flussu-server.com/zap`
- **Auth**: HTTP Basic Auth
  - Username: `zapier_user`
  - Password: `your_secure_password`
- **Headers**:
  - `WID`: Your workflow ID (e.g., `WF001`)
- **Body** (JSON):
  ```json
  {
    "WID": "WF001",
    "data": {
      "name": "John Doe",
      "email": "john@example.com",
      "amount": "100.00"
    }
  }
  ```

#### 2. Create Flussu Workflow

Variables in your workflow starting with `$zap_` will be automatically populated:

```
$zap_name      // Will receive "John Doe"
$zap_email     // Will receive "john@example.com"
$zap_amount    // Will receive "100.00"
```

#### 3. Available Endpoints

- **Execute workflow**: `POST /zap`
- **Test connection**: `GET /zap?auth`
- **List workflows**: `GET /zap?list`

### IFTTT Integration

#### 1. Configure IFTTT Applet

In your IFTTT Applet:
- **Action**: Webhooks → Make a web request
- **URL**: `https://your-flussu-server.com/ifttt`
- **Method**: POST
- **Content Type**: application/json
- **Headers**:
  - `IFTTT-Service-Key`: `your_ifttt_service_key`
- **Body**:
  ```json
  {
    "WID": "WF002",
    "actionFields": {
      "title": "New task",
      "description": "Task from IFTTT",
      "priority": "high"
    }
  }
  ```

#### 2. Create Flussu Workflow

Variables in your workflow starting with `$ifttt_` will be automatically populated:

```
$ifttt_title         // Will receive "New task"
$ifttt_description   // Will receive "Task from IFTTT"
$ifttt_priority      // Will receive "high"
```

#### 3. Available Endpoints

- **Execute workflow**: `POST /ifttt` or `POST /ifttt/v1/actions/execute_workflow`
- **Status check**: `GET /ifttt/v1/status`
- **Test setup**: `GET /ifttt/v1/test/setup`
- **List workflows**: `GET /ifttt?list` or `GET /ifttt/v1/triggers`

## Adding New Webhook Providers

### Example: Adding Make.com Integration

1. **Create Controller** (`src/Flussu/Controllers/MakeController.php`):

```php
<?php
namespace Flussu\Controllers;

use Flussu\Flussuserver\Request;

class MakeController extends AbsWebhookProvider
{
    public function __construct()
    {
        parent::__construct();
        $this->providerName = 'MAKE';
        $this->varPrefix = 'make_';
        $this->expectedUserAgent = 'Integromat';
    }

    public function apiCall(Request $request, $apiPage): void
    {
        $this->setCorsHeaders();

        list($usrName, $usrPass) = $this->extractCredentials();
        list($wid, $SentWID) = $this->extractWorkflowId($request);

        $rawdata = file_get_contents('php://input');
        $theData = json_decode($rawdata, true);

        $uid = $this->authenticate($usrName, $usrPass);

        if ($uid < 1) {
            $this->reportErrorAndDie("401", "Unauthorized");
        }

        if ($wid < 1) {
            $this->reportErrorAndDie("400", "Invalid workflow");
        }

        $res = $this->executeWorkflow($wid, $SentWID, $uid, $theData);
        die(json_encode(["success" => true, "session_id" => $res[0]]));
    }
}
```

2. **Add to Configuration** (`config/.services.json`):

```json
{
  "webhooks": {
    "make": {
      "sign": ["Integromat", "Make.com"],
      "call": "MakeController@apiCall",
      "credentials": [
        {
          "username": "make_user",
          "password": "secure_key",
          "user_id": 16
        }
      ]
    }
  }
}
```

3. **Done!** The webhook router in `webroot/api.php` will automatically detect and route Make.com requests.

## Workflow Variable Naming

Each provider uses a unique prefix for workflow variables:

| Provider | Prefix     | Example           |
|----------|------------|-------------------|
| Zapier   | `$zap_`    | `$zap_email`      |
| IFTTT    | `$ifttt_`  | `$ifttt_title`    |
| Make     | `$make_`   | `$make_status`    |
| Custom   | `$wh_`     | `$wh_data`        |

In your workflow's first block, declare variables with the appropriate prefix:

```
$zap_customer_name = ""
$zap_order_id = ""
$zap_total_amount = ""
```

These will be automatically populated from the webhook payload.

## Testing

### Test Zapier Connection

```bash
curl -X POST https://your-flussu-server.com/zap \
  -u "zapier_user:your_password" \
  -H "Content-Type: application/json" \
  -H "WID: [__wzaptest__]" \
  -d '{"test": "data"}'

# Expected response:
# {"error":"200","message":"Hi Zapier, I'm Alive :), how are u?"}
```

### Test IFTTT Connection

```bash
curl -X GET https://your-flussu-server.com/ifttt/v1/status \
  -H "IFTTT-Service-Key: your_service_key"

# Expected response:
# {"status":"ok","service":"Flussu","version":"4.5"}
```

### Execute Real Workflow

```bash
curl -X POST https://your-flussu-server.com/zap \
  -u "zapier_user:your_password" \
  -H "Content-Type: application/json" \
  -d '{
    "WID": "WF001",
    "data": {
      "name": "Test User",
      "email": "test@example.com"
    }
  }'

# Expected response:
# {"result":"started","res":{...},"WID":"WF001","SID":"session_id_here"}
```

## Migration Guide

### From v4.5.0 to v4.5.1

**Old code** (ZapierController with hardcoded credentials):
```php
if ($usrName=="pippuzzo" && $usrPass=="giannuzzo123")
    $theFlussuUser->load(16);
```

**New code** (configuration-based):
```json
{
  "webhooks": {
    "zapier": {
      "credentials": [
        {
          "username": "pippuzzo",
          "password": "$2y$10$hashed_password_here",
          "user_id": 16
        }
      ]
    }
  }
}
```

**Steps:**
1. Copy existing usernames/passwords to config
2. Hash passwords using `password_hash()`
3. Test connections
4. Remove old code (already done in v4.5.1)

## Troubleshooting

### "Unauthenticated" Error

- ✓ Check credentials in `config/.services.json`
- ✓ Verify username/password match exactly
- ✓ If using hashed password, ensure hash is valid bcrypt (starts with `$2y$`)
- ✓ Check file permissions: `chmod 600 config/.services.json`

### "Wrong Flussu WID" Error

- ✓ Verify workflow ID exists in your Flussu instance
- ✓ Check WID format (e.g., "WF001", not "1")
- ✓ Ensure WID is sent in header or JSON body

### "No Data Received" Error

- ✓ Check Content-Type header is `application/json`
- ✓ Verify JSON payload is valid
- ✓ Ensure data is in expected format (see examples above)

### Webhook Not Detected

- ✓ Check User-Agent string matches configuration
- ✓ Verify `"sign"` patterns in config match actual User-Agent
- ✓ Check logs: `General::log()` entries in api.php

## Security Best Practices

1. **Always use HTTPS** for webhook endpoints
2. **Hash passwords** with bcrypt, never store plain text
3. **Restrict file permissions**: `chmod 600 config/.services.json`
4. **Use different credentials** for each provider
5. **Rotate credentials** periodically
6. **Monitor logs** for unauthorized access attempts
7. **Validate input** - the framework does this automatically
8. **Use webhook signatures** when available (Stripe example in codebase)

## Advanced: Database-Backed Credentials

To move credentials from config files to database:

1. Create `webhook_credentials` table
2. Modify `AbsWebhookProvider::authenticate()`:

```php
protected function authenticate(string $username, string $password): int
{
    $db = new \Database();
    $cred = $db->query(
        "SELECT user_id, password_hash FROM webhook_credentials
         WHERE provider = ? AND username = ?",
        [$this->getProviderConfigKey(), $username]
    );

    if ($cred && password_verify($password, $cred['password_hash'])) {
        return $cred['user_id'];
    }

    return 0;
}
```

## API Reference

### IWebhookProvider Interface

```php
interface IWebhookProvider
{
    public function apiCall(Request $request, string $apiPage): void;
    public function authenticate(string $username, string $password): int;
    public function extractData($data): array;
    public function executeWorkflow(int $wid, string $origWid, int $userId, array $data): array;
}
```

### AbsWebhookProvider Methods

| Method | Visibility | Description |
|--------|------------|-------------|
| `authenticate()` | public | Authenticate user from config |
| `extractData()` | public | Normalize webhook payload |
| `executeWorkflow()` | public | Execute Flussu workflow |
| `setCorsHeaders()` | protected | Set CORS response headers |
| `reportErrorAndDie()` | protected | Return JSON error and exit |
| `extractWorkflowId()` | protected | Get WID from request |
| `extractCredentials()` | protected | Get HTTP Basic Auth |
| `verifyPassword()` | protected | Check plain or hashed password |

## License

This webhook integration system is part of Flussu v4.5.1, released under Apache License 2.0.

---

**Version**: 4.5.20251115
**Last Updated**: November 15, 2025
**Author**: Mille Isole SRL

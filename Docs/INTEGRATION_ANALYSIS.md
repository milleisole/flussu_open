# Flussu Zapier and IFTTT Integration Analysis

## Executive Summary

This report provides a comprehensive analysis of how Zapier and IFTTT are currently integrated into the Flussu server v4.5 workflow automation platform. The codebase implements Zapier integration but does not currently include IFTTT integration.

---

## 1. Current Implementation Status

### 1.1 Zapier Integration - IMPLEMENTED
- **Status**: Fully implemented and functional
- **Location**: Dedicated controller with API endpoints
- **Integration Type**: Bidirectional (Flussu can call Zapier, and Zapier can call Flussu)

### 1.2 IFTTT Integration - NOT IMPLEMENTED
- **Status**: No IFTTT-specific code found
- **Potential**: Can be added using the webhook pattern already established
- **Configuration**: Space reserved in config but not yet utilized

---

## 2. File Structure and Key Components

### 2.1 Core Files

#### A. Controllers
```
/src/Flussu/Controllers/
├── ZapierController.php (8.3 KB)        ← MAIN ZAPIER IMPLEMENTATION
├── FlussuController.php (9.9 KB)        ← Routes webhook calls
├── StripeController.php (17.7 KB)       ← Payment webhook (reference pattern)
└── RevolutController.php (4.6 KB)       ← Payment webhook (reference pattern)
```

#### B. Server-Side Execution
```
/src/Flussu/Flussuserver/
├── Command.php (47.6 KB)                ← HTTP calls handler
├── Executor.php (44.1 KB)               ← Workflow execution engine
├── Session.php (53.4 KB)                ← Session management
└── Worker.php (69.9 KB)                 ← Command executor
```

#### C. Configuration
```
/config/
└── .services.json.sample                ← Webhook routing config
```

#### D. Entry Point
```
/webroot/
└── api.php (4.5 KB)                    ← Main HTTP entry point
```

---

## 3. How Zapier Integration Works

### 3.1 Two-Way Integration Pattern

#### **Direction 1: Zapier → Flussu (Incoming Webhooks)**

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Zapier Service calls Flussu                              │
│                                                             │
│ HTTP Request:                                               │
│ ├─ User-Agent: "Zapier"                                     │
│ ├─ Auth: HTTP Basic (username/password)                     │
│ ├─ Body: JSON with "data" array                             │
│                                                             │
│ 2. Router detects Zapier (webroot/api.php)                  │
│ ├─ Checks HTTP_USER_AGENT header                            │
│ ├─ Routes to: ZapierController@apicall                      │
│                                                             │
│ 3. ZapierController processes request                       │
│ ├─ Validates authentication                                │
│ ├─ Extracts WID (Workflow ID)                              │
│ ├─ Parses request payload                                   │
│ ├─ Creates new Session                                      │
│ ├─ Executes first workflow block                            │
│ └─ Returns JSON response with SID (Session ID)              │
└─────────────────────────────────────────────────────────────┘
```

**Endpoint**: POST /api.php (or any endpoint with ZapierController routing)

**Expected Headers**:
```php
User-Agent: Zapier
Authorization: Basic base64(username:password)
```

**Request Body**:
```json
{
  "WID": "[workflow_id]",
  "data": [
    {
      "$zap_fieldname1": "value1",
      "$zap_fieldname2": "value2"
    }
  ]
}
```

**Response**:
```json
{
  "result": "started",
  "WID": "[workflow_id]",
  "SID": "session_uuid",
  "res": {
    "$zap_fieldname1": "value1",
    "$zap_fieldname2": "value2"
  }
}
```

#### **Direction 2: Flussu → Zapier (Outgoing Calls)**

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Workflow executes "doZAP" command                         │
│ ├─ Command: doZAP(uri, result_var, optional_data)          │
│ ├─ Example: doZAP("https://hooks.zapier.com/...", "res")   │
│                                                             │
│ 2. Executor (Executor.php:255-272) handles command         │
│ ├─ Extracts URI from parameters                             │
│ ├─ Calls _doZAP() method                                    │
│                                                             │
│ 3. _doZAP() prepares payload                                │
│ ├─ Constructs JSON with workflow data                       │
│ ├─ Adds metadata: server, WID, SID, BID                    │
│ ├─ Calls Command.doZAP()                                    │
│                                                             │
│ 4. Command.doZAP() (Command.php:296-298)                    │
│ ├─ Makes HTTP POST call via curl                            │
│ ├─ Sends JSON request                                        │
│ ├─ Returns response                                          │
│                                                             │
│ 5. Result stored in workflow variable                       │
│ └─ Variable: $resultVarName                                 │
└─────────────────────────────────────────────────────────────┘
```

**Workflow Syntax Example**:
```
doZAP("https://hooks.zapier.com/hooks/catch/ABC123/xyz", "zapier_response", 
  "{\"field1\": \"value1\", \"field2\": \"value2\"}")
```

### 3.2 Key Code Flow

**File**: `/src/Flussu/Controllers/ZapierController.php`

```php
class ZapierController {
    // Line 42: Entry point for Zapier calls
    public function apiCall(Request $request, $apiPage)
    
    // Line 180: Extract variables from Zapier request
    private function _getWidZapierVars($wid, $origWid, $userId, $theData)
    
    // Line 142: Parse data payload
    private function _extractData($dr)
    
    // Line 175: Error response handler
    private function _reportErrorAndDie($httpErr, $errMsg)
}
```

**Authentication Logic** (Line 73-82):
```php
if ($usrName=="pippuzzo" && ($usrPass=="giannuzzo123" || $usrPass=="giannuzzo"))
    $theFlussuUser->load(16);
if ($usrName=="aldus" && ($usrPass=="pattavina"))
    $theFlussuUser->load(16);
```

⚠️ **SECURITY ISSUE**: Hard-coded credentials in production code!

**Routing Logic** (Lines 90-106):
- `/zap?auth` - Authentication check
- `/zap?list` - List all workflows for user
- `/zap` - Execute workflow with data

### 3.3 Session Integration

**File**: `/src/Flussu/Flussuserver/Session.php` (Lines 438-512)

```php
// Line 440-441: Identify Zapier source
case "zapier":
case "ZAPIER":
    $isZap = true;
    $channel = 10;

// Line 507-512: Set Zapier flag for workflow
if ($isZap) {
    $this->assignVars("$isZapier", true);
    $isWeb = false;
} else {
    $this->assignVars("$isZapier", false);
}
```

**Available Workflow Variables**:
- `$isZapier` - Boolean flag indicating Zapier source
- `$isWeb`, `$isMobile`, `$isTelegram` - Source detection

### 3.4 Execution Engine Integration

**File**: `/src/Flussu/Flussuserver/Executor.php` (Lines 255-272)

```php
case "doZAP":
    $Sess->statusCallExt(true);
    try {
        $Sess->recLog("call Zapier Uri" . $this->arr_print($innerParams));
        $data = null;
        if (count($innerParams) > 2)
            $data = $innerParams[2];
        $reslt = $this->_doZAP($Sess, $innerParams[0], $data);
        if (!empty($innerParams[1]))
            $Sess->assignVars("\$" . $innerParams[1], $reslt);
    } catch (\Throwable $e) {
        $Sess->recLog(" call Zapier - execution EXCEPTION:" . json_encode($e));
        if (!empty($innerParams[1]))
            $Sess->assignVars("\$" . $innerParams[1], "ERROR");
        $Sess->statusError(true);
    }
    $Sess->statusCallExt(false);
    break;
```

**The _doZAP Method** (Executor.php:880-890):
```php
private function _doZAP($Sess, $uri, $params) {
    $data = [];
    if (!empty($params))
        $data["data"] = json_decode($params, true);
    $data["info"] = [
        "server" => "flussu",
        "recall" => General::getHttpHost(),
        "WID" => $Sess->getStarterWID(),
        "SID" => $Sess->getId(),
        "BID" => $Sess->getBlockId()
    ];
    $jsonReq = json_encode($data);
    $Sess->statusCallExt(true);
    $cmd = new Command();
    $result = $cmd->doZAP($uri, $jsonReq);
    return $result;
}
```

---

## 4. Configuration Structure

### 4.1 Webhook Configuration

**File**: `/config/.services.json.sample` (Lines 72-85)

```json
"webhooks": {
  "zapier": {
    "sign": ["Zapier"],
    "call": "ZapierController@apicall"
  },
  "stripe": {
    "sign": ["Stripe/", "//stripe.com/"],
    "call": "StripeController@webhook"
  },
  "revolut": {
    "sign": ["Revolut ", "/revolut "],
    "call": "RevolutController@webhook"
  }
}
```

### 4.2 Webhook Detection Logic

**File**: `/webroot/api.php` (Lines 184-211)

```php
function checkUnattendedWebHookCall($req, $apiPage) {
    $callSign = $_SERVER['HTTP_USER_AGENT'] . " " . $_SERVER['HTTP_ORIGIN'];
    $wh = config("webhooks");
    $iwh = "";
    $idf = "";
    
    foreach ($wh as $service => $sign) {
        $idf = $service;
        foreach ($sign['sign'] as $p_sign) {
            if (strpos($callSign, $p_sign) !== false) {
                $iwh = $sign['call'];
                break;
            }
        }
        if ($iwh) break;
    }
    
    if ($iwh) {
        $iwh = explode("@", $iwh);
        $providerClass = 'Flussu\\Controllers\\' . $iwh[0];
        if (class_exists($providerClass)) {
            $handlerCall = new $providerClass();
            $handlerCall->{$iwh[1]}($req, $apiPage);
            return true;
        }
    }
    return false;
}
```

**Detection Mechanism**:
1. Concatenates `USER_AGENT` and `HTTP_ORIGIN` headers
2. Searches for configured signatures
3. Routes to matching controller and method

---

## 5. Common Patterns Across Integrations

### 5.1 Payment Provider Pattern (Stripe, Revolut)

All payment providers follow this pattern:

**Structure**:
```php
class StripeController extends AbsPayProviders {
    public function init($companyName, $keyType) { ... }
    public function webhook($request, $apiPage) { ... }
}
```

**Inheritance**: `extends AbsPayProviders` (Abstract class)

**Configuration Access**:
```php
$this->_apiKey = config("services.pay_provider.stripe.{$companyName}.{$keyType}");
```

### 5.2 Zapier Pattern (Dedicated Implementation)

**Different from payment providers**:
```php
class ZapierController {
    // No inheritance
    public function apiCall(Request $request, $apiPage) { ... }
    // Custom implementation
}
```

### 5.3 Webhook Pattern - Generic

All webhooks follow this routing pattern:

```
User-Agent Detection → Configuration Match → Controller Route → Method Call
```

**Common Signature Patterns**:
- `"Zapier"` - Simple string match
- `"Stripe/"` - Partial match
- `"Revolut "` - Case sensitive with space
- URL origin-based matching

---

## 6. Where Integrations Are Used

### 6.1 Incoming Requests (Zapier Calling Flussu)

**Entry Point**: `webroot/api.php`

```
Request → checkUnattendedWebHookCall()
        → Signature match (User-Agent: Zapier)
        → ZapierController::apiCall()
        → Workflow execution
        → JSON response
```

### 6.2 Outgoing Requests (Flussu Calling Zapier)

**Workflow Command**: `doZAP()`

```
Workflow Block
  ↓
Executor reads "doZAP" command
  ↓
_doZAP() prepares data with metadata
  ↓
Command::doZAP() makes HTTP POST
  ↓
Zapier webhook receives request
  ↓
Result stored in workflow variable
```

### 6.3 Webhook Calls (Generic)

**Entry Point**: `webroot/api.php` (Line 120-135)

```
Direct webhook: /wh/[workflow-id]/[optional-block-id]
  ↓
FlussuController::webhook()
  ↓
Passes data as variables to workflow execution
  ↓
Engine::execWorker()
```

---

## 7. Current Implementation Issues & Gaps

### 7.1 Security Issues

| Issue | Location | Severity | Details |
|-------|----------|----------|---------|
| Hard-coded credentials | ZapierController.php:77-82 | CRITICAL | User credentials hard-coded in source |
| No credential encryption | Throughout | HIGH | API keys stored in plain text in config |
| Basic Auth over HTTP | ZapierController.php:46-47 | HIGH | Authentication vulnerable if not HTTPS |
| No signature verification | Command.php:296 | MEDIUM | Zapier calls not verified with signature |

### 7.2 Missing Features

| Feature | Impact | Recommendation |
|---------|--------|-----------------|
| IFTTT integration | No IFTTT support | Implement using webhook pattern |
| Rate limiting | Possible abuse | Add rate limiting to ZapierController |
| Request logging | Poor audit trail | Enhance logging in Session.php |
| Error handling | Vague errors | Add detailed error messages |
| API versioning | Maintenance issues | Version the ZapierController API |
| Webhook signature validation | Security risk | Validate incoming Zapier requests |

### 7.3 Architecture Issues

| Issue | Impact | Details |
|-------|--------|---------|
| Inconsistent patterns | Maintenance burden | Zapier != Stripe pattern |
| Mixed concerns | Testing difficulty | Auth + execution + routing mixed |
| Poor abstraction | Code duplication | No common webhook handler |
| Configuration structure | Limited flexibility | Hardcoded routing logic |
| No dependency injection | Tight coupling | All classes instantiated directly |

---

## 8. Proposed Improvements

### 8.1 Add IFTTT Integration

**Implementation Strategy**:

```
1. Create IFTTTController following Zapier pattern
2. Add to config/services.json:
   {
     "ifttt": {
       "sign": ["IFTTT"],
       "call": "IFTTTController@webhook"
     }
   }
3. Implement source detection in Session.php
4. Add doIFTTT command to Executor.php (optional)
```

**Similar to Zapier but with IFTTT-specific requirements**:
- Different authentication method
- Different payload structure
- Different variable naming conventions

### 8.2 Refactor Webhook Handling

**Current Problem**: Each controller has different webhook signature
**Solution**: Create abstract webhook interface

```php
// New file: AbstractWebhookController.php
abstract class AbstractWebhookController {
    abstract public function getSignatures(): array;
    abstract public function validateSignature(): bool;
    abstract public function handleWebhook(Request $request, $apiPage): void;
}

// Refactor ZapierController to extend this
class ZapierController extends AbstractWebhookController {
    public function getSignatures(): array { return ["Zapier"]; }
    // ... implement other methods
}
```

### 8.3 Secure Credential Management

**Current Problem**: Hard-coded credentials and plain-text API keys
**Solution**: Implement credential manager

```php
class CredentialManager {
    public static function getUserCredentials($username): array {
        // Query from secure database
        // Never hard-code credentials
    }
    
    public static function encryptApiKey($key): string {
        // Encrypt API keys
    }
}
```

### 8.4 Improve Configuration

**Move from signature strings to regex/callback**:

```json
"webhooks": {
  "zapier": {
    "matcher": "class:ZapierMatcher",
    "handler": "ZapierController@webhook",
    "version": "1.0"
  }
}
```

### 8.5 Add Request Validation

**Validate all incoming requests**:

```php
class WebhookValidator {
    public static function validateZapier(Request $req): bool {
        // Check User-Agent
        // Validate signature if present
        // Verify timestamp
        // Check required fields
    }
}
```

---

## 9. Folder and File Organization

### Current Structure
```
/src/Flussu/
├── Controllers/
│   ├── ZapierController.php          [Standalone]
│   ├── StripeController.php          [Extends AbsPayProviders]
│   ├── RevolutController.php         [Extends AbsPayProviders]
│   └── FlussuController.php          [Mixed concerns]
├── Flussuserver/
│   ├── Session.php                   [Session + Source detection]
│   ├── Executor.php                  [Command execution + doZAP]
│   ├── Command.php                   [HTTP calls + Email + SMS]
│   └── Worker.php                    [Block execution]
├── Contracts/
│   └── IPayProvider.php             [Payment interface only]
└── Api/
    └── V40/
        └── Engine.php               [Workflow execution]
```

### Recommended Improved Structure
```
/src/Flussu/
├── Controllers/
│   ├── Webhooks/
│   │   ├── AbstractWebhookController.php    [Base class]
│   │   ├── ZapierController.php
│   │   ├── IFTTTController.php              [New]
│   │   ├── StripeController.php
│   │   └── RevolutController.php
│   └── FlussuController.php
├── Flussuserver/
│   ├── Integration/
│   │   ├── WebhookRegistry.php             [New]
│   │   ├── SourceDetector.php              [New]
│   │   └── WebhookValidator.php            [New]
│   ├── Session.php
│   ├── Executor.php
│   ├── Command.php
│   └── Worker.php
├── Contracts/
│   ├── IPayProvider.php
│   └── IWebhook.php                        [New]
└── Security/
    ├── CredentialManager.php               [New]
    └── SignatureValidator.php              [New]
```

---

## 10. Integration Comparison Matrix

| Aspect | Zapier | IFTTT | Stripe | Revolut |
|--------|--------|-------|--------|---------|
| **Implemented** | ✓ Yes | ✗ No | ✓ Yes | ✓ Yes |
| **Pattern** | Custom | TBD | Abstract | Abstract |
| **Auth Type** | HTTP Basic | (TBD) | Signature | OAuth |
| **Webhook In** | ✓ Yes | ✗ | ✓ Yes | ✓ Yes |
| **Webhook Out** | ✓ Yes (doZAP) | ✗ | Callback | Callback |
| **Source Detection** | User-Agent | TBD | User-Agent | User-Agent |
| **Config Location** | /config/.services.json | TBD | /config/.services.json | /config/.services.json |
| **Controller File** | ZapierController.php | TBD | StripeController.php | RevolutController.php |
| **Session Integration** | ✓ Yes | ✗ | ✓ Limited | ✓ Limited |
| **Workflow Command** | doZAP | TBD | (None) | (None) |

---

## 11. Getting Started with IFTTT Integration

### Quick Implementation Guide

#### Step 1: Create IFTTT Controller

Create `/src/Flussu/Controllers/IFTTTController.php`:

```php
<?php
namespace Flussu\Controllers;

use Flussu\Flussuserver\Request;
use Flussu\Flussuserver\NC\HandlerNC;
use Flussu\General;

class IFTTTController {
    public function webhook(Request $request, $apiPage) {
        // Similar to ZapierController but with IFTTT-specific logic
        // Extract values from IFTTT payload
        // Execute workflow with extracted values
    }
}
```

#### Step 2: Update Configuration

Update `/config/.services.json`:

```json
"webhooks": {
  "zapier": {
    "sign": ["Zapier"],
    "call": "ZapierController@apicall"
  },
  "ifttt": {
    "sign": ["IFTTT"],
    "call": "IFTTTController@webhook"
  }
}
```

#### Step 3: Add Source Detection

Update `/src/Flussu/Flussuserver/Session.php` (around line 440):

```php
case "ifttt":
case "IFTTT":
    $isIFTTT = true;
    $channel = 11;
    $done = $this->recLog("ifttt", $newSessId, 111);
    break;
```

#### Step 4: Add Outgoing Command (Optional)

Update `/src/Flussu/Flussuserver/Executor.php` (add to switch):

```php
case "doIFTTT":
    $Sess->statusCallExt(true);
    try {
        $Sess->recLog("call IFTTT Uri" . $this->arr_print($innerParams));
        $result = $this->_doIFTTT($Sess, $innerParams[0], $innerParams[1] ?? null);
        if (!empty($innerParams[2]))
            $Sess->assignVars("\$" . $innerParams[2], $result);
    } catch (\Throwable $e) {
        $Sess->statusError(true);
    }
    $Sess->statusCallExt(false);
    break;
```

---

## 12. Testing Zapier Integration

### Test Endpoint

```bash
# Test authentication
curl -X POST https://your-server.com/api.php \
  -H "User-Agent: Zapier" \
  -u "pippuzzo:giannuzzo123" \
  'https://your-server.com/api.php?page=zap?auth'

# List workflows
curl -X POST https://your-server.com/api.php \
  -H "User-Agent: Zapier" \
  -u "pippuzzo:giannuzzo123" \
  'https://your-server.com/api.php?page=zap?list'

# Execute workflow
curl -X POST https://your-server.com/api.php \
  -H "User-Agent: Zapier" \
  -H "Content-Type: application/json" \
  -u "pippuzzo:giannuzzo123" \
  -H "WID: [workflow_id]" \
  -d '{
    "WID": "[workflow_id]",
    "data": [{
      "$zap_field1": "value1",
      "$zap_field2": "value2"
    }]
  }'
```

---

## Summary & Recommendations

### Key Findings

1. **Zapier Integration**: Fully functional, bidirectional, but with security issues
2. **IFTTT**: Not implemented but can be added following Zapier pattern
3. **Webhook Pattern**: Inconsistent across payment and API providers
4. **Security**: Critical issues with hard-coded credentials and plain-text storage
5. **Architecture**: Mixed concerns, poor abstraction, tight coupling

### Priority Improvements

1. **IMMEDIATE** (Security):
   - Move hard-coded credentials to encrypted config
   - Implement request signature validation
   - Add HTTPS enforcement
   - Require authentication token refresh

2. **SHORT-TERM** (Completeness):
   - Implement IFTTT integration
   - Add comprehensive error handling
   - Improve logging and audit trails
   - Add rate limiting

3. **LONG-TERM** (Architecture):
   - Refactor webhook handling into abstract pattern
   - Implement dependency injection
   - Create webhook registry system
   - Add webhook versioning and backward compatibility

---

## Appendix A: File Cross-Reference

| File | Lines | Purpose | Related |
|------|-------|---------|---------|
| api.php | 184-211 | Webhook routing | ZapierController |
| ZapierController.php | 42-106 | Zapier request handling | api.php |
| ZapierController.php | 180-214 | Variable extraction | Executor.php |
| Session.php | 438-512 | Source detection | ZapierController |
| Executor.php | 255-272 | doZAP execution | Command.php |
| Executor.php | 880-890 | Zapier call preparation | Command.php |
| Command.php | 296-298 | HTTP POST execution | Executor.php |
| .services.json | 72-85 | Webhook configuration | api.php |

---

## Appendix B: Variable Naming Convention

### Zapier Variable Prefix
- Incoming: `$zap_` prefix in workflow blocks
- Outgoing: Set as `$"zap_"` prefix

### Workflow Source Variables
- `$isZapier` - Boolean, true if source is Zapier
- `$isForm` - Boolean, true if source is form
- `$isMobile` - Boolean, true if source is mobile
- `$isTelegram` - Boolean, true if source is Telegram
- `$isWeb` - Boolean, true if source is web

### Internal Metadata
- `$web_caller` - HTTP User-Agent
- `$webhook` - Boolean, true if webhook call


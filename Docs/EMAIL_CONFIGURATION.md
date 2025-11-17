# Email Configuration for Flussu

## Overview

This document explains how to configure email sending functionality in Flussu, particularly for the password reset feature.

## Configuration

### 1. Email Service Configuration

Email settings are configured in the `/config/.services.json` file. If this file doesn't exist, copy from the sample:

```bash
cp config/.services.json.sample config/.services.json
```

### 2. Add Email Configuration

Add the following section to your `.services.json` file under the `services` section:

```json
{
  "services": {
    "email": {
      "default": "smtp_provider",
      "smtp_provider": {
        "smtp_host": "smtp.example.com",
        "smtp_port": 587,
        "smtp_auth": 1,
        "smtp_user": "noreply@example.com",
        "smtp_pass": "your_smtp_password_here",
        "smtp_encrypt": "STARTTLS",
        "from_email": "noreply@example.com",
        "from_name": "Flussu Password Reset"
      }
    }
  }
}
```

### 3. Configuration Parameters

| Parameter | Description | Values |
|-----------|-------------|--------|
| `default` | Default email provider to use | Name of the provider (e.g., "smtp_provider") |
| `smtp_host` | SMTP server hostname | e.g., "smtp.gmail.com", "smtp.office365.com" |
| `smtp_port` | SMTP server port | 587 (STARTTLS), 465 (SSL/TLS), 25 (plain) |
| `smtp_auth` | Enable SMTP authentication | 1 (enabled), 0 (disabled) |
| `smtp_user` | SMTP username | Usually your email address |
| `smtp_pass` | SMTP password | Your SMTP password or app password |
| `smtp_encrypt` | Encryption method | "STARTTLS", "SMTPS", "SSL", or empty for none |
| `from_email` | From email address | Email address to use as sender |
| `from_name` | From display name | Display name for the sender |

### 4. Password Encryption (Optional)

Flussu supports encrypted passwords in the configuration. To encrypt a password:

1. Use the Flussu encryption utility
2. Replace the plain text password with the encrypted version in the configuration

The system will automatically decrypt it using `General::montanara()` when needed.

## Common SMTP Providers

### Gmail

```json
{
  "smtp_host": "smtp.gmail.com",
  "smtp_port": 587,
  "smtp_auth": 1,
  "smtp_user": "your-email@gmail.com",
  "smtp_pass": "your-app-password",
  "smtp_encrypt": "STARTTLS",
  "from_email": "your-email@gmail.com",
  "from_name": "Your App Name"
}
```

**Note**: For Gmail, you need to create an [App Password](https://support.google.com/accounts/answer/185833).

### Microsoft 365 / Outlook

```json
{
  "smtp_host": "smtp.office365.com",
  "smtp_port": 587,
  "smtp_auth": 1,
  "smtp_user": "your-email@outlook.com",
  "smtp_pass": "your-password",
  "smtp_encrypt": "STARTTLS",
  "from_email": "your-email@outlook.com",
  "from_name": "Your App Name"
}
```

### SendGrid

```json
{
  "smtp_host": "smtp.sendgrid.net",
  "smtp_port": 587,
  "smtp_auth": 1,
  "smtp_user": "apikey",
  "smtp_pass": "your-sendgrid-api-key",
  "smtp_encrypt": "STARTTLS",
  "from_email": "noreply@yourdomain.com",
  "from_name": "Your App Name"
}
```

### Mailgun

```json
{
  "smtp_host": "smtp.mailgun.org",
  "smtp_port": 587,
  "smtp_auth": 1,
  "smtp_user": "postmaster@mg.yourdomain.com",
  "smtp_pass": "your-mailgun-smtp-password",
  "smtp_encrypt": "STARTTLS",
  "from_email": "noreply@yourdomain.com",
  "from_name": "Your App Name"
}
```

## Usage in Code

### Password Reset Email

The password reset functionality in `webroot/flussu/forgot-password.php` automatically uses the configured email settings.

Example usage:

```php
// The system automatically:
// 1. Loads email configuration from .services.json
// 2. Creates a PHPMailer instance
// 3. Sends an HTML email with the reset link

$emailResult = sendResetEmail($userEmail, $token);
```

### Using Email in Custom Code

To send emails in your custom Flussu code, you can use the `Command` class:

```php
use Flussu\Flussuserver\Command;
use Flussu\Flussuserver\Session;

$cmd = new Command();
$sess = new Session(); // Your session object

$result = $cmd->localSendMail(
    $sess,
    'from@example.com',      // From email
    'From Name',             // From name
    'to@example.com',        // To email
    'Email Subject',         // Subject
    'Email message',         // Message (supports HTML/Markdown)
    'reply@example.com',     // Reply-to (optional)
    0,                       // Block ID
    null,                    // Attachments (optional)
    'smtp_provider'          // Provider code (optional, uses default if null)
);
```

## Email Templates

### Password Reset Email

The password reset email includes:

- **HTML version**: Professional template with styling and a call-to-action button
- **Plain text version**: Fallback for email clients that don't support HTML
- **Reset link**: Valid for 24 hours
- **Security notice**: Informs user if they didn't request the reset

The template can be customized in the `sendResetEmail()` function in `webroot/flussu/forgot-password.php`.

## Troubleshooting

### Email Not Sending

1. **Check SMTP credentials**: Verify username and password are correct
2. **Check SMTP server**: Ensure the host and port are correct
3. **Check firewall**: Ensure outbound SMTP ports are not blocked
4. **Check logs**: Review Flussu logs for error messages
5. **Test SMTP connection**: Use tools like `telnet` or `openssl` to test connectivity

### Common Errors

| Error | Solution |
|-------|----------|
| "SMTP connect() failed" | Check host, port, and firewall settings |
| "SMTP Error: Could not authenticate" | Verify username and password |
| "Connection refused" | Check if SMTP port is correct and not blocked |
| "Email configuration not found" | Ensure `.services.json` has email configuration |

### Testing Email Configuration

Create a test script to verify your email setup:

```php
<?php
require_once 'webroot/flussu/inc/includebase.php';

use PHPMailer\PHPMailer\PHPMailer;

// Load config
$provider = 'services.email.' . config('services.email.default');
$smtpHost = config($provider . '.smtp_host');
$smtpPort = config($provider . '.smtp_port');
$smtpUser = config($provider . '.smtp_user');
$smtpPass = config($provider . '.smtp_pass');

echo "SMTP Host: $smtpHost\n";
echo "SMTP Port: $smtpPort\n";
echo "SMTP User: $smtpUser\n";
echo "Testing connection...\n";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->Port = $smtpPort;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    
    // Test sending
    $mail->setFrom($smtpUser, 'Test');
    $mail->addAddress('test@example.com');
    $mail->Subject = 'Test Email';
    $mail->Body = 'This is a test email';
    
    if ($mail->send()) {
        echo "✅ Email sent successfully!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: {$mail->ErrorInfo}\n";
}
```

## Security Considerations

### Best Practices

1. **Never commit credentials**: Keep `.services.json` out of version control
2. **Use app passwords**: For Gmail and similar, use app-specific passwords
3. **Encrypt passwords**: Use Flussu's encryption for sensitive credentials
4. **Use HTTPS**: Always use HTTPS for password reset links
5. **Validate domains**: Ensure reset links point to your domain only
6. **Rate limiting**: Implement rate limiting for password reset requests
7. **Token expiration**: Tokens expire after 24 hours by default

### Email Security Headers

The implementation includes:

- UTF-8 character encoding
- HTML and plain text versions
- Professional templates to avoid spam filters

## Multiple Email Providers

You can configure multiple email providers:

```json
{
  "services": {
    "email": {
      "default": "primary_smtp",
      "primary_smtp": {
        "smtp_host": "smtp.primary.com",
        ...
      },
      "backup_smtp": {
        "smtp_host": "smtp.backup.com",
        ...
      }
    }
  }
}
```

Then specify the provider when sending:

```php
$result = $cmd->localSendMail(
    $sess, $from, $fromName, $to, $subject, 
    $message, $reply, $blkId, $attaches, 
    'backup_smtp'  // Use backup provider
);
```

## Support

For issues or questions:
- Check the [Flussu documentation](https://www.flussu.com/docs)
- Review logs in the configured log directory
- Contact Flussu support: support@flussu.com

---

**Version**: 5.0  
**Last Updated**: 17.11.2025  
**Author**: Flussu Development Team  
**License**: Apache License 2.0

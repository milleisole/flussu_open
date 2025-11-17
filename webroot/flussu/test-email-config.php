<?php
/* --------------------------------------------------------------------*
 * Flussu v5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * EMAIL CONFIGURATION TEST SCRIPT
 * VERSION REL.:     5.0.20251117
 * UPDATES DATE:     17.11.2025
 * --------------------------------------------------------------------*
 * This script tests the email configuration for password reset
 * functionality. Run it from the command line or browser to verify
 * your SMTP settings are correct.
 * --------------------------------------------------------------------*/

require_once 'inc/includebase.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Set output type based on context
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<html><head><title>Email Configuration Test</title><style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .info { color: #2196F3; font-weight: bold; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        h1 { color: #333; }
    </style></head><body>';
    echo '<h1>Flussu Email Configuration Test</h1>';
}

/**
 * Print formatted message
 */
function printMessage($type, $message) {
    global $isCli;
    $prefix = match($type) {
        'success' => '✅',
        'error' => '❌',
        'info' => 'ℹ️',
        default => '•'
    };
    
    if ($isCli) {
        echo "$prefix $message\n";
    } else {
        $class = $type;
        echo "<p class='$class'>$prefix $message</p>";
    }
}

/**
 * Test email configuration
 */
function testEmailConfiguration() {
    try {
        printMessage('info', 'Loading email configuration...');
        
        // Load configuration
        $emailConfig = config('services.email.default');
        if (empty($emailConfig)) {
            printMessage('error', 'Email configuration not found in .services.json');
            return false;
        }
        
        printMessage('success', "Email provider: $emailConfig");
        
        $provider = 'services.email.' . $emailConfig;
        $smtpHost = config($provider . '.smtp_host');
        $smtpPort = config($provider . '.smtp_port');
        $smtpAuth = config($provider . '.smtp_auth', 0) != 0;
        $smtpUser = config($provider . '.smtp_user');
        $smtpPass = config($provider . '.smtp_pass');
        $smtpEncrypt = config($provider . '.smtp_encrypt');
        $fromEmail = config($provider . '.from_email', $smtpUser);
        $fromName = config($provider . '.from_name', 'Flussu');
        
        // Display configuration (without password)
        printMessage('info', "SMTP Host: $smtpHost");
        printMessage('info', "SMTP Port: $smtpPort");
        printMessage('info', "SMTP Auth: " . ($smtpAuth ? 'Enabled' : 'Disabled'));
        printMessage('info', "SMTP User: $smtpUser");
        printMessage('info', "SMTP Encryption: $smtpEncrypt");
        printMessage('info', "From Email: $fromEmail");
        printMessage('info', "From Name: $fromName");
        
        // Decrypt password if encrypted
        if (Flussu\General::isCurtatoned($smtpPass)) {
            $smtpPass = Flussu\General::montanara($smtpPass, 999);
            printMessage('info', 'Password is encrypted (decrypted for testing)');
        }
        
        // Create PHPMailer instance
        printMessage('info', 'Creating PHPMailer instance...');
        $mail = new PHPMailer(true);
        
        // Configure SMTP
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = $smtpAuth;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->Port = $smtpPort;
        
        if ($smtpEncrypt == "STARTTLS") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtpEncrypt == "SMTPS" || $smtpEncrypt == "SSL") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Enable verbose debug output (comment out in production)
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            printMessage('info', "Debug: " . trim($str));
        };
        
        printMessage('success', 'Configuration loaded successfully!');
        printMessage('info', 'Ready to send test emails. To actually send a test email, uncomment the sending code in this script.');
        
        return true;
        
    } catch (Exception $e) {
        printMessage('error', 'Configuration test failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send a test email (uncomment to actually send)
 */
function sendTestEmail($toEmail) {
    try {
        printMessage('info', "Attempting to send test email to: $toEmail");
        
        // Load configuration
        $emailConfig = config('services.email.default');
        $provider = 'services.email.' . $emailConfig;
        
        $smtpHost = config($provider . '.smtp_host');
        $smtpPort = config($provider . '.smtp_port');
        $smtpAuth = config($provider . '.smtp_auth', 0) != 0;
        $smtpUser = config($provider . '.smtp_user');
        $smtpPass = config($provider . '.smtp_pass');
        $smtpEncrypt = config($provider . '.smtp_encrypt');
        $fromEmail = config($provider . '.from_email', $smtpUser);
        $fromName = config($provider . '.from_name', 'Flussu');
        
        // Decrypt password if encrypted
        if (Flussu\General::isCurtatoned($smtpPass)) {
            $smtpPass = Flussu\General::montanara($smtpPass, 999);
        }
        
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Configure SMTP
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = $smtpAuth;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->Port = $smtpPort;
        
        if ($smtpEncrypt == "STARTTLS") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtpEncrypt == "SMTPS" || $smtpEncrypt == "SSL") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Email content
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = 'Flussu Email Configuration Test';
        $mail->Body = '
        <html>
        <body style="font-family: Arial, sans-serif; padding: 20px;">
            <h2>Email Configuration Test</h2>
            <p>This is a test email from your Flussu installation.</p>
            <p>If you received this email, your SMTP configuration is working correctly!</p>
            <p><strong>Configuration Details:</strong></p>
            <ul>
                <li>SMTP Host: ' . $smtpHost . '</li>
                <li>SMTP Port: ' . $smtpPort . '</li>
                <li>From: ' . $fromEmail . '</li>
            </ul>
            <p>Timestamp: ' . date('Y-m-d H:i:s') . '</p>
        </body>
        </html>';
        
        $mail->AltBody = "Email Configuration Test\n\n"
            . "This is a test email from your Flussu installation.\n"
            . "If you received this email, your SMTP configuration is working correctly!\n\n"
            . "Configuration Details:\n"
            . "SMTP Host: $smtpHost\n"
            . "SMTP Port: $smtpPort\n"
            . "From: $fromEmail\n\n"
            . "Timestamp: " . date('Y-m-d H:i:s');
        
        // Send
        $mail->send();
        
        printMessage('success', "Test email sent successfully to $toEmail");
        return true;
        
    } catch (Exception $e) {
        printMessage('error', "Failed to send test email: " . $e->getMessage());
        return false;
    }
}

// Main execution
printMessage('info', 'Starting email configuration test...');
echo $isCli ? "\n" : "<hr>";

$configOk = testEmailConfiguration();

echo $isCli ? "\n" : "<hr>";

if ($configOk) {
    printMessage('success', 'Email configuration test completed successfully!');
    printMessage('info', 'To send an actual test email, uncomment the sendTestEmail() call in this script and provide a test email address.');
    
    // UNCOMMENT THE FOLLOWING LINE AND SET YOUR EMAIL TO TEST SENDING
    // sendTestEmail('your-test-email@example.com');
} else {
    printMessage('error', 'Email configuration test failed. Please check your .services.json configuration.');
}

echo $isCli ? "\n" : "<hr>";

printMessage('info', 'Test complete.');

if (!$isCli) {
    echo '</body></html>';
}

// Exit
exit($configOk ? 0 : 1);

 //---------------
 //    _{()}_    |
 //    --[]--    |
 //      ||      |
 //  AL  ||  DVS |
 //  \\__||__//  |
 //   \__||__/   |
 //      \/      |
 //   @INXIMKR   |
 //---------------

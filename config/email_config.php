<?php
// config/email_config.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Correct the path - use absolute path
require_once __DIR__ . '/../vendor/autoload.php';

class EmailSender {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Server settings for Gmail
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com'; // Gmail SMTP server
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = 'antonettemorway01@gmail.com'; // Your Gmail
        $this->mail->Password   = 'gtor qxro wmye agty'; // CHANGE THIS - Generate new app password!
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;
        
        // Sender settings - must match the Gmail account
        $this->mail->setFrom('antonettemorway01@gmail.com', 'Learnexus');
        $this->mail->isHTML(true);
        
        // Enable debugging for troubleshooting
        // $this->mail->SMTPDebug = SMTP::DEBUG_SERVER; // Uncomment for debugging
    }
    
    public function sendOTP($toEmail, $toName, $otpCode) {
        try {
            // Clear any previous recipients
            $this->mail->clearAddresses();
            
            // Recipient
            $this->mail->addAddress($toEmail, $toName);
            
            // Content
            $this->mail->Subject = 'Learnexus - Email Verification OTP';
            
            $htmlContent = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                    .content { padding: 30px; background: #f9f9f9; }
                    .otp-code { 
                        font-size: 32px; 
                        font-weight: bold; 
                        color: #007bff; 
                        text-align: center;
                        letter-spacing: 10px;
                        margin: 20px 0;
                        padding: 15px;
                        background: white;
                        border-radius: 5px;
                        border: 2px dashed #007bff;
                    }
                    .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Learnexus</h1>
                    </div>
                    <div class="content">
                        <h2>Verify Your Email Address</h2>
                        <p>Hello ' . htmlspecialchars($toName) . ',</p>
                        <p>Thank you for registering with Learnexus. To complete your registration, please use the following One-Time Password (OTP) to verify your email address:</p>
                        
                        <div class="otp-code">' . $otpCode . '</div>
                        
                        <p>This OTP is valid for 10 minutes. If you didn\'t request this, please ignore this email.</p>
                        
                        <p><strong>Important:</strong> Never share this OTP with anyone.</p>
                        
                        <p>Best regards,<br>The Learnexus Team</p>
                    </div>
                    <div class="footer">
                        <p>© 2026 Learnexus. All rights reserved.</p>
                        <p>This is an automated message, please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>';
            
            $this->mail->Body = $htmlContent;
            
            // Alternative plain text version
            $this->mail->AltBody = "Learnexus Email Verification\n\nHello $toName,\n\nYour OTP code is: $otpCode\n\nThis OTP is valid for 10 minutes.\n\nBest regards,\nThe Learnexus Team";
            
            $this->mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            $error = "Email sending failed: " . $this->mail->ErrorInfo;
            error_log($error);
            return ['success' => false, 'message' => $error];
        }
    }
}

// Simple wrapper function
function sendEmailOTP($toEmail, $toName, $otpCode) {
    $emailSender = new EmailSender();
    return $emailSender->sendOTP($toEmail, $toName, $otpCode);
}
?>
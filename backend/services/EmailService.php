<?php

declare(strict_types=1);

class EmailService
{
    /**
     * Sends an email using SMTP sockets.
     * This fulfills the requirement to use the SMTP server in config.php.
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        $config = require __DIR__ . '/../config.php';
        $smtp = $config['smtp'];

        $host = $smtp['host'];
        $port = (int) $smtp['port'];
        $user = $smtp['user'];
        $pass = $smtp['pass'];
        $fromEmail = $smtp['from_email'];
        $fromName = $smtp['from_name'];

        try {
            // Use SSL if port is 465
            $socketHost = ($port === 465) ? "ssl://$host" : $host;
            $socket = @fsockopen($socketHost, $port, $errno, $errstr, 10);

            if (!$socket) {
                throw new Exception("Could not connect to SMTP host: $errstr ($errno)");
            }

            $getResponse = function($socket) {
                $res = "";
                while ($str = @fgets($socket, 515)) {
                    $res .= $str;
                    if (substr($str, 3, 1) == " ") break;
                }
                return $res;
            };

            $sendCmd = function($socket, $cmd) use ($getResponse) {
                fputs($socket, $cmd . "\r\n");
                return $getResponse($socket);
            };

            $getResponse($socket); // Catch greeting
            $sendCmd($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            
            // Start TLS if port is 587
            if ($port === 587) {
                $sendCmd($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $sendCmd($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            }

            // Auth
            $sendCmd($socket, "AUTH LOGIN");
            $sendCmd($socket, base64_encode($user));
            $sendCmd($socket, base64_encode($pass));

            // Mail flow
            $sendCmd($socket, "MAIL FROM: <$user>");
            $sendCmd($socket, "RCPT TO: <$to>");
            $sendCmd($socket, "DATA");

            // Headers & Body
            $headers = [
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "From: $fromName <$fromEmail>",
                "To: <$to>",
                "Subject: $subject",
                "Date: " . date("r"),
                "X-Mailer: PHP/" . phpversion()
            ];

            fputs($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
            $getResponse($socket);

            $sendCmd($socket, "QUIT");
            fclose($socket);
            return true;
        } catch (Exception $e) {
            // Log error but don't break JSON output
            error_log("Email Error: " . $e->getMessage());
            return false;
        }
    }

    public static function sendVerificationCode(string $to, string $code): bool
    {
        $subject = "Verify your MTH GLOBAL RESOURCES Account";
        $body = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px;'>
                <h2 style='color: #1e3a8a; text-align: center;'>Account Verification</h2>
                <p>Hello,</p>
                <p>Thank you for joining MTH GLOBAL RESOURCES. Please use the following 6-digit code to verify your account:</p>
                <div style='background: #f0f9ff; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #1e3a8a;'>$code</span>
                </div>
                <p style='color: #6b7280; font-size: 14px;'>This code will expire shortly. If you did not request this, please ignore this email.</p>
                <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
                <p style='text-align: center; color: #1e3a8a; font-weight: bold;'>MTH GLOBAL RESOURCES</p>
            </div>
        ";
        return self::send($to, $subject, $body);
    }
    public static function sendOrderSummary(string $to, int $orderId, float $amount, array $items = []): bool
    {
        $subject = "Order Confirmation - MTH GLOBAL RESOURCES (#$orderId)";
        $amountFormatted = "₦" . number_format($amount, 2);
        
        $baseUrl = getenv('APP_URL') ?: (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/' : "http://localhost/");
        $itemsHtml = "";
        if (!empty($items)) {
            $itemsHtml = "<table style='width:100%; border-collapse:collapse; margin: 20px 0;'>
                <thead>
                    <tr style='background:#f9fafb; border-bottom:1px solid #e5e7eb;'>
                        <th style='text-align:left; padding:12px; width:60px;'>Image</th>
                        <th style='text-align:left; padding:12px;'>Item</th>
                        <th style='text-align:right; padding:12px;'>Qty</th>
                        <th style='text-align:right; padding:12px;'>Price</th>
                    </tr>
                </thead>
                <tbody>";
            foreach ($items as $item) {
                $imgUrl = $item['image_url'] ? (str_starts_with($item['image_url'], 'http') ? $item['image_url'] : $baseUrl . $item['image_url']) : $baseUrl . "assets/images/placeholder.png";
                $itemPrice = "₦" . number_format((float)$item['price'], 2);
                $itemsHtml .= "<tr style='border-bottom:1px solid #f3f4f6;'>
                    <td style='padding:12px;'><img src='$imgUrl' width='50' height='50' style='border-radius:4px; object-fit:cover;'></td>
                    <td style='padding:12px; font-weight:500;'>{$item['name']}</td>
                    <td style='padding:12px; text-align:right; color:#6b7280;'>{$item['quantity']}</td>
                    <td style='padding:12px; text-align:right; font-weight:600;'>$itemPrice</td>
                </tr>";
            }
            $itemsHtml .= "</tbody></table>";
        }

        $body = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h1 style='color: #1e3a8a; margin: 0;'>Order Confirmed!</h1>
                    <p style='color: #6b7280;'>Thank you for your purchase from MTH GLOBAL RESOURCES.</p>
                </div>
                
                <div style='background: #f9fafb; border-radius: 8px; padding: 15px; margin-bottom: 20px;'>
                    <p style='margin: 0; font-size: 14px; color: #6b7280;'>Order Number</p>
                    <p style='margin: 0; font-size: 18px; font-weight: 700; color: #111827;'>#$orderId</p>
                </div>

                $itemsHtml

                <div style='border-top: 2px solid #1e3a8a; padding-top: 15px; margin-top: 20px; text-align: right;'>
                    <p style='margin: 0; font-size: 14px; color: #6b7280;'>Total Amount Paid</p>
                    <p style='margin: 0; font-size: 24px; font-weight: 800; color: #1e3a8a;'>$amountFormatted</p>
                </div>

                <div style='margin-top: 30px; padding: 15px; background: #f0f9ff; border-radius: 8px; text-align: center;'>
                    <p style='margin: 0; color: #1e3a8a; font-size: 14px;'>Your beverages are being prepared for shipment. You will receive another update once they are on the way!</p>
                </div>

                <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                <p style='text-align: center; color: #9ca3af; font-size: 12px;'>MTH GLOBAL RESOURCES &bull; Premium Beverages &bull; Lagos, Nigeria</p>
            </div>
        ";
        return self::send($to, $subject, $body);
    }

    public static function sendPasswordResetLink(string $to, string $token, string $domain): bool
    {
        $resetUrl = rtrim($domain, '/') . '/pages/reset-password.html?token=' . urlencode($token);
        $subject = "Reset your MTH GLOBAL RESOURCES Password";
        $body = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px;'>
                <h2 style='color: #1e3a8a; text-align: center;'>Password Reset Request</h2>
                <p>Hello,</p>
                <p>We received a request to reset your password. Please click the button below to set a new password:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetUrl' style='background: #1e3a8a; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; display: inline-block;'>Reset Password</a>
                </div>
                <p style='color: #6b7280; font-size: 14px;'>If you did not request this, please ignore this email. Your password will remain unchanged.</p>
                <p style='color: #6b7280; font-size: 12px;'>Or copy this link: <br>$resetUrl</p>
                <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
                <p style='text-align: center; color: #1e3a8a; font-weight: bold;'>MTH GLOBAL RESOURCES</p>
            </div>
        ";
        return self::send($to, $subject, $body);
    }

    public static function sendNewProductNotification(string $to, string $productName, string $description, float $price, ?string $imagePath): bool
    {
        $subject = "💧 New Arrival at MTH GLOBAL RESOURCES: $productName!";
        $amountFormatted = "₦" . number_format($price, 2);
        
        $baseUrl = getenv('APP_URL') ?: (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/' : "http://localhost/");
        $imgUrl = $imagePath ? (str_starts_with($imagePath, 'http') ? $imagePath : $baseUrl . ltrim($imagePath, '/')) : $baseUrl . "assets/images/placeholder.png";
        $shopUrl = $baseUrl . "pages/shop.html";

        $body = "
            <div style='font-family: \"Outfit\", \"Inter\", \"Helvetica Neue\", sans-serif; max-width: 600px; margin: 0 auto; padding: 0; border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background-color: #ffffff;'>
                <!-- Header Banner -->
                <div style='background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%); padding: 35px 20px; text-align: center;'>
                    <span style='font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #38bdf8; display: block; margin-bottom: 8px;'>Fresh Announcement</span>
                    <h1 style='color: #ffffff; font-size: 26px; font-weight: 800; margin: 0; letter-spacing: -0.5px;'>New Product Alert!</h1>
                </div>

                <!-- Main Content -->
                <div style='padding: 30px 24px;'>
                    <div style='text-align: center; margin-bottom: 24px;'>
                        <img src='$imgUrl' alt='$productName' style='width: 100%; max-width: 480px; height: auto; border-radius: 12px; object-fit: cover; box-shadow: 0 8px 24px rgba(0,0,0,0.08); margin: 0 auto 20px auto; display: block;'>
                    </div>

                    <h2 style='color: #111827; font-size: 22px; font-weight: 700; margin: 0 0 10px 0; text-align: center;'>$productName</h2>
                    
                    <!-- Category & Price Block -->
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <span style='display: inline-block; background-color: #f0f9ff; color: #1d4ed8; font-weight: 600; font-size: 14px; padding: 6px 16px; border-radius: 9999px; margin-bottom: 8px;'>Available Now</span>
                        <div style='font-size: 28px; font-weight: 800; color: #1e3a8a; margin-top: 5px;'>$amountFormatted</div>
                    </div>

                    <p style='color: #4b5563; font-size: 16px; line-height: 1.6; margin: 0 0 24px 0; text-align: center;'>
                        " . nl2br(htmlspecialchars($description)) . "
                    </p>

                    <!-- CTA Button -->
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$shopUrl' style='background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 700; font-size: 16px; display: inline-block; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4); transition: transform 0.2s;'>Shop Now at MTH GLOBAL RESOURCES</a>
                    </div>

                    <div style='margin-top: 30px; padding: 15px; background: #f0f9ff; border-radius: 10px; text-align: center; border-left: 4px solid #3b82f6;'>
                        <p style='margin: 0; color: #1e3a8a; font-size: 13px; font-weight: 500;'>Every product is quality-tested under strict standards. Pure hydration and premium beverages, delivered to your door!</p>
                    </div>
                </div>

                <!-- Footer -->
                <div style='background-color: #f9fafb; padding: 24px; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0 0 8px 0; color: #111827; font-weight: 700; font-size: 14px;'>MTH GLOBAL RESOURCES</p>
                    <p style='margin: 0 0 16px 0; color: #6b7280; font-size: 12px;'>Premium Water & Beverage Distribution &bull; Lagos, Nigeria</p>
                    <p style='margin: 0; color: #9ca3af; font-size: 11px; line-height: 1.4;'>
                        You received this because you are a registered user of MTH GLOBAL RESOURCES.<br>
                        To stop receiving these, please update your notifications in your account settings.
                    </p>
                </div>
            </div>
        ";
        return self::send($to, $subject, $body);
    }
}

<?php
// ---------------------------------------------------------------------------------
// WordPress DB fatal error page
// by Erik Bertrand (http://bertpc.com, @BertPC)
//
// Feel free to use and modify as you see fit, though I would appreciate keeping
// the attribution in place.  Rock on!
//
// Edit the constants below, plus the HTML further down as you like, then place
// this file within /wp-content and you'll be notified via email when someone
// accesses your site and WordPress is unable to reach the database.  The user
// will see a helpful error page, which will automatically retry every 30 seconds
// for a better user experience!
//
// Note that this script will send emails even when index crawlers, bots, etc.
// access your site.  To help keep the number of notifications down, add code to
// avoid the email step based on the value of either or both of HTTP_USER_AGENT and
// REMOTE_ADDR headers.
// ---------------------------------------------------------------------------------

const SITE_NAME = 'Website';
const SITE_DOMAIN = 'website.com';
const FROM_ADDRESS = 'website@website.com';
const SMTP_USERNAME = '';
const SMTP_PASSWORD = '';
const SMTP_SERVER = 'localhost';
const LOGO_PATH = '/wp-content/uploads/logo.png'; // Upload your own logo and put the path here!

$contact_email = 'info@website.com';
$contact_phone = '603-555-1212';
$contact_address = 'Address Line 1 &middot; City, ST zip';

$recipients = array(
    "Name" => "email@address.com"
);

ob_start();
?>
</p><!-- WP wraps the error page content with a "p" tag set. Close it here to maintain well-formed HTML. -->

<h1 style="text-align:center"><img src="<?= LOGO_PATH ?>" alt="<?= SITE_NAME ?>"/></h1>
<p>The <?= SITE_NAME ?> website is currently unavailable due to a system problem.
    We are working to bring the site back up as soon as possible.</p>
<p>Keep this page open and the site will automatically appear once it's available again. Thank you for your
    patience!</p>
<hr/>
<p style="text-align:center; font-size:0.85em"><?= SITE_NAME ?> &middot; <?= $contact_address ?> &middot;
    <a href="mailto:<?= $contact_email ?>"><?= $contact_email ?></a> &middot; <a
        href="tel:<?= $contact_phone ?>"><?= $contact_phone ?></a></p>

<script type="text/javascript">
    (function () {
        setTimeout('RetrySite()', 30000);
        var RetrySite = function () {
            document.location = '/?r=1';
        }
    })();
</script>

<p><!-- WP wraps the error page content with a "p" tag set. Continue efforts to maintain well-formed HTML. -->

<?php

$content = ob_get_contents();
ob_end_clean();

// Before we die, email the powers-that-be
$text_body = "Client Info:\n\n"
    . "IP:  " . $_SERVER['REMOTE_ADDR'] . "\n"
    . "User Agent:  " . $_SERVER['HTTP_USER_AGENT'] . "\n"
    . "Referer:  " . $_SERVER['HTTP_REFERER'] . "\n"
    . "Forwarded by:  " . $_SERVER['HTTP_X_FORWARDED_FOR'];

if (!isset($_GET['r'])) {
    SendEmail($recipients, '[' . SITE_DOMAIN . '] Website DOWN', $text_body);
}

wp_die($content);


/**
 * SMTP Email Sender
 *
 * @param array $recipients An key/value array of recipient names and email addresses (i.e. "name" => "email")
 * @param string $subject The subject of the email
 * @param string $text_body The ASCII text contents of the email (optional)
 * @param string $html_body HTML body content (optional)
 * @param string $from The email address from which to send (defaults to FROM_ADDRESS)
 * @param array $extra_headers An associative array of SMTP header names and values to add to the email (optional)
 * @param string $return_path The email address failure emails should go to (defaults to FROM_ADDRESS)
 * @param string $username The username of the SMTP connection (defaults to SMTP_USERNAME)
 * @param string $password The password of the SMTP connection (defaults to SMTP_PASSWORD)
 * @param string $host The server to make the SMTP connection to. Defaults to SMTP_SERVER
 *
 * @return bool True if the email was sent successfully.
 */
function SendEmail($recipients, $subject, $text_body = '', $html_body = '', $from = FROM_ADDRESS, $extra_headers = array(), $return_path = FROM_ADDRESS, $username = SMTP_USERNAME, $password = SMTP_PASSWORD, $host = SMTP_SERVER)
{
    // Open connection
    $SMTPIN = fsockopen($host, '25', $errnum, $errstr, 30);
    if ($SMTPIN) {
        // Handshake
        fputs($SMTPIN, "EHLO " . $from . "\r\n");
        $talk["EHLO"] = GetStreamResponse($SMTPIN);

        // Pass auth credentials, if needed
        if (SMTP_USERNAME != '') {
            fputs($SMTPIN, "AUTH LOGIN\r\n");
            $talk["AUTH LOGIN"] = GetStreamResponse($SMTPIN);
            fputs($SMTPIN, base64_encode($username) . "\r\n");
            $talk["user"] = GetStreamResponse($SMTPIN);
            fputs($SMTPIN, base64_encode($password) . "\r\n");
            $talk["pass"] = GetStreamResponse($SMTPIN);
        }

        // Email is from...
        fputs($SMTPIN, "MAIL FROM: <" . $return_path . ">\r\n");
        $talk["MAIL FROM"] = GetStreamResponse($SMTPIN);
        // ... and going to...
        foreach ($recipients as $name => $email) {
            fputs($SMTPIN, "RCPT TO: <" . $email . ">\r\n");
            $talk["RCPT TO"] .= GetStreamResponse($SMTPIN);
        }
        // Provide and send the message
        fputs($SMTPIN, "DATA\r\n");
        $talk["DATA"] = GetStreamResponse($SMTPIN);
        $to = '';
        foreach ($recipients as $name => $email) {
            $to .= '"' . $name . '" <' . $email . '>, ';
        }
        fputs($SMTPIN, "To: " . rtrim($to, ", ") . "\r\n");
        fputs($SMTPIN, "From: <$from>\r\n");
        fputs($SMTPIN, "Subject: $subject\r\n");
        if ($html_body != '') {
            if ($text_body != '') //they wanted multi part
            {
                $boundary = 'Content-Boundary';
                fputs($SMTPIN, "Mime-Version: 1.0\r\n");
                fputs($SMTPIN, "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n");
                foreach ($extra_headers as $header => $value)
                    fputs($SMTPIN, "$header: $value\r\n");
                fputs($SMTPIN, "\r\n");
                fputs($SMTPIN, "--$boundary\r\n");
                fputs($SMTPIN, "Content-Type: text/html;\r\n");
                fputs($SMTPIN, "\r\n$html_body\r\n\r\n");
                fputs($SMTPIN, "--$boundary\r\n");
                fputs($SMTPIN, "Content-Type: text/plain;\r\n\r\n");
                fputs($SMTPIN, "\r\n$text_body\r\n\r\n");
                fputs($SMTPIN, "--$boundary--\r\n");
            } else {
                fputs($SMTPIN, "Content-Type: text/html;\r\n");
                foreach ($extra_headers as $header => $value)
                    fputs($SMTPIN, "$header: $value\r\n");
                fputs($SMTPIN, "\r\n$html_body\r\n");
            }
        } else {
            foreach ($extra_headers as $header => $value)
                fputs($SMTPIN, "$header: $value\r\n");
            fputs($SMTPIN, "\r\n$text_body\r\n");
        }

        $talk["body"] = GetStreamResponse($SMTPIN);
        fputs($SMTPIN, ".\r\nQUIT\r\n");
        $talk["QUIT"] = GetStreamResponse($SMTPIN);
        // Close connection
        fclose($SMTPIN);
        return true;
    } else
        return false;
}

/**
 * Processes stream responses
 *
 * @param mixed $connection
 *
 * @return mixed
 */
function GetStreamResponse($connection = null)
{
    $data = "";
    while ($str = fgets($connection, 515)) {
        $data .= $str;
        # if the 4th character is a space then we are done reading
        # so just break the loop
        if (substr($str, 3, 1) == " " || $str == "") {
            break;
        }
    }
    return trim($data);
}

?>


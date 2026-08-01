<?php

namespace SummerCraft\Service\Email\Sender;

use RuntimeException;
use SummerCraft\Service\Mime\DefaultMimeTypeConverter;

/**
 * CodeIgniter-based Email Class
 *
 * Permits email to be sent using Mail, Sendmail, or SMTP.
 */
class EmailSender
{
    /**
     * @var array
     */
    public static $defaultConfig = [
        // Refactored
        'userAgent' => 'CodeHuiter', // Used as the User-Agent and X-Mailer headers' value
        'newline' => "\n", // Newline character sequence. Use "\r\n" to comply with RFC 822. "\r\n" or "\n"
        'charset' => 'utf-8', // Character set (default: utf-8)
        'encoding' => '8bit', // Mail encoding. '8bit' or '7bit'
        'protocol' => 'mail', // Which method to use for sending e-mails. string 'mail', 'sendmail' or 'smtp'
        'wordWrap' => true, // Whether to apply word-wrapping to the message body
        'wrapChars' => 76, // Number of characters to wrap at
        'mailPath' => '/usr/sbin/sendmail', // Path to the Sendmail binary.
        'mailType' => 'text', // Message format. 'text' or 'html'
        'validate' => true, // Whether to validate e-mail addresses.
        'priority' => 3, // X-Priority header value. 1-5
        
        /**
         * 	 * RFC 2045 specifies that for 'quoted-printable' encoding,
         * "\r\n" must be used. However, it appears that some servers
         * (even on the receiving end) don't handle it properly and
         * switching to "\n", while improper, is the only solution
         * that seems to work for all environments.
         */
        'CRLF' => "\n", // CRLF character sequence http://www.ietf.org/rfc/rfc822.txt

        'SMTPUser' => '', // SMTP Username
        'SMTPPass' => '', // SMTP Password
        'SMTPHost' => '', // STMP Server host
        'SMTPPort' => 25, // SMTP Server port
        'SMTPTimeout' => 5, // SMTP connection timeout in seconds
        'SMTPKeepAlive' => false, // SMTP persistent connection
        'SMTPCrypto' => '', // SMTP Encryption. Empty, 'tls' or 'ssl'

        // UnRefactored

        'DSN' => false, // Whether to use Delivery Status Notification.
        'sendMultipart' => true, // Whether to send multipart alternatives. Yahoo! doesn't seem to like these.
    ];

    /** @var array  */
    protected $config;

    /**
     * Subject header
     * @var string
     */
    protected $subject = '';

    /**
     * Message body
     * @var string
     */
    protected $body = '';

    /**
     * Final message body to be sent.
     * @var string
     */
    protected $finalBody = '';

    /**
     * Final headers to send
     * @var string
     */
    protected $headerStr = '';

    /**
     * SMTP Connection socket. '' (falsy, is_resource() === false) until connected.
     * @var resource|string
     */
    protected $SMTPConnect = '';

    /**
     * Whether to perform SMTP authentication
     * @var bool
     */
    protected $isSMTPAuth = false;

    /**
     * Debug messages
     * @var array
     */
    protected $debugMessage = [];

    /**
     * Message headers
     * @var array
     */
    protected $headers = [];

    /**
     * Alternative message (for HTML messages only)
     * @var string
     */
    public $altMessage = '';

    /**
     * Recipients
     * @var array
     */
    protected $recipients = [];

    /**
     * CC Recipients
     * @var array
     */
    protected $CCArray = [];

    /**
     * BCC Recipients
     * @var array
     */
    protected $BCCArray = [];

    /**
     * Attachment data
     * @var array
     */
    protected $attachments = [];

    // -- INNER --

    /**
     * Whether to send messages to BCC recipients in batches.
     * @var bool
     */
    public $BCCBatchMode = false;

    /**
     * BCC Batch max number size.
     * @var int
     */
    public $BCCBatchSize = 200;

    /**
     * Whether to send a Reply-To header
     * @var bool
     */
    protected $replyToFlag = false;

    /**
     * Valid $protocol values
     * @var array
     */
    protected $allowProtocols = ['mail', 'sendmail', 'smtp'];

    /**
     * Character sets valid for 7-bit encoding, excluding language suffix.
     * @var array
     */
    protected $baseCharsets = ['us-ascii', 'iso-2022-'];

    /**
     * Bit depths. Valid mail encodings
     * @var array
     */
    protected $bitDepths = ['7bit', '8bit'];

    /**
     * $priority translations
     * Actual values to send with the X-Priority header
     * @var array
     */
    protected $priorities = [
        1 => '1 (Highest)',
        2 => '2 (High)',
        3 => '3 (Normal)',
        4 => '4 (Low)',
        5 => '5 (Lowest)',
    ];

    /**
     * Constructor - Sets Email Preferences
     */
    public function __construct(array $config = [])
    {
        $this->clear();
        $this->config = array_merge(self::$defaultConfig, $config);
        $this->config['charset']  = strtoupper($this->config['charset']);
        $this->config['newline'] = in_array($this->config['newline'], ["\n", "\r\n", "\r"]) ? $this->config['newline'] : "\n";
        $this->isSMTPAuth = $this->config['SMTPUser'] !== '' && $this->config['SMTPPass'] !== '';
        $this->setProtocol($this->config['protocol']);
        $this->setCRLF($this->config['CRLF']);
        $this->setPriority($this->config['priority']);
    }

    /**
     * Get Language Message
     * @param string $alias
     * @param array $replacePairs
     * @return string
     */
    protected function lang($alias, $replacePairs = [])
    {
        $result = $alias;
        $unReplacedPairs = $replacePairs;
        foreach ($replacePairs as $key => $value) {
            if (strpos($result,$key) !== false) {
                $result = str_replace($key, $value, $result);
                unset($unReplacedPairs[$key]);
            }
        }
        foreach ($unReplacedPairs as $key => $value) {
            $result .= $this->config['newline'] . $key . ' => ' . $value;
        }
        return $result;
    }

    /**
     * Initialize the Email Data
     * @param bool $clearAttachments
     * @return EmailSender
     */
    public function clear($clearAttachments = false)
    {
        $this->subject      = '';
        $this->body         = '';
        $this->finalBody    = '';
        $this->headerStr    = '';
        $this->replyToFlag  = false;
        $this->recipients   = [];
        $this->CCArray      = [];
        $this->BCCArray     = [];
        $this->headers      = [];
        $this->debugMessage = [];
        $this->setHeader('Date', $this->setDate());
        if ($clearAttachments !== false) {
            $this->attachments = [];
        }
        return $this;
    }

    /**
     * Set RFC 822 Date
     * @return string
     */
    protected function setDate()
    {
        $timezone = date('Z');
        $operator = ($timezone[0] === '-') ? '-' : '+';
        $timezone = abs((int)$timezone);
        $timezone = floor($timezone/3600)*100+($timezone%3600)/60;
        return sprintf('%s %s%04d', date('D, j M Y H:i:s'), $operator, $timezone);
    }

    /**
     * Add a Header Item
     * @param string $header
     * @param string $value
     * @return EmailSender
     */
    public function setHeader($header, $value)
    {
        $this->headers[$header] = str_replace(["\n", "\r"], '', $value);
        return $this;
    }

    /**
     * Filter The Name
     * @param string $name
     * @return string
     */
    protected function filterName($name)
    {
        if ($name !== '') {
            // only use Q encoding if there are characters that would require it
            if (! preg_match('/[\200-\377]/', $name)) {
                // add slashes for non-printing characters, slashes, and double quotes, and surround it in double quotes
                $name = '"'.addcslashes($name, "\0..\37\177'\"\\").'"';
            } else {
                $name = $this->prepQEncoding($name);
            }
        }
        return $name;
    }

    /**
     * Set FROM
     * @param string $from
     * @param string $name
     * @param string|null $returnPath Return-Path
     * @return EmailSender
     */
    public function setFrom($from, $name = '', $returnPath = null)
    {
        if (preg_match('/\<(.*)\>/', $from, $match)) {
            $from = $match[1];
        }
        if ($this->config['validate']) {
            $this->validateEmails($this->stringToArray($from));
            if ($returnPath) {
                $this->validateEmails($this->stringToArray($returnPath));
            }
        }

        $name = $this->filterName($name);
        $this->setHeader('From', $name.' <'.$from.'>');

        if ($returnPath === null) $returnPath = $from;
        $this->setHeader('Return-Path', '<'.$returnPath.'>');

        return $this;
    }

    /**
     * Set Reply-to
     * @param string $replyTo
     * @param string $name
     * @return EmailSender
     */
    public function setReplyTo($replyTo, $name = '')
    {
        if (preg_match('/\<(.*)\>/', $replyTo, $match)) {
            $replyTo = $match[1];
        }
        if ($this->config['validate']) {
            $this->validateEmails($this->stringToArray($replyTo));
        }
        $name = $this->filterName($name);
        $this->setHeader('Reply-To', $name.' <'.$replyTo.'>');
        $this->replyToFlag = true;
        return $this;
    }

    /**
     * Set Recipients
     */
    public function setTo(array|string $to): static
    {
        $to = $this->stringToArray($to);
        $to = $this->cleanEmails($to);
        if ($this->config['validate']) {
            $this->validateEmails($to);
        }
        if ($this->config['protocol'] !== 'mail') {
            $this->setHeader('To', implode(', ', $to));
        }
        $this->recipients = $to;
        return $this;
    }

    /**
     * Set CC
     */
    public function setCC(array|string $cc): static
    {
        $cc = $this->cleanEmails($this->stringToArray($cc));
        if ($this->config['validate']) {
            $this->validateEmails($cc);
        }
        $this->setHeader('Cc', implode(', ', $cc));
        if ($this->config['protocol'] === 'smtp') {
            $this->CCArray = $cc;
        }
        return $this;
    }

    /**
     * Set BCC
     */
    public function setBCC(array|string $bcc, string $limit = ''): static
    {
        if ($limit !== '' && is_numeric($limit)) {
            $this->BCCBatchMode = true;
            $this->BCCBatchSize = (int)$limit;
        }
        $bcc = $this->cleanEmails($this->stringToArray($bcc));
        if ($this->config['validate']) {
            $this->validateEmails($bcc);
        }
        if ($this->config['protocol'] === 'smtp' || ($this->BCCBatchMode && count($bcc) > $this->BCCBatchSize)) {
            $this->BCCArray = $bcc;
        } else {
            $this->setHeader('Bcc', implode(', ', $bcc));
        }
        return $this;
    }

    /**
     * Set Email Subject
     * @param string $subject
     * @return EmailSender
     */
    public function setSubject($subject)
    {
        $subject = $this->prepQEncoding($subject);
        $this->setHeader('Subject', $subject);
        return $this;
    }

    /**
     * Set Body
     * @param string $body
     * @return EmailSender
     */
    public function setMessage($body)
    {
        $this->body = rtrim(str_replace("\r", '', $body));
        return $this;
    }

    /**
     * Assign file attachments
     * @param string $file Can be local path, URL or buffered content
     * @param string $disposition 'attachment'
     * @param string|null $newName
     * @param string $mime
     */
    public function attach($file, $disposition = '', $newName = null, $mime = '')
    {
        if ($mime === '') {
            if (strpos($file, '://') === false && !file_exists($file)) {
                $this->setErrorMessage($this->lang('email.attachmentMissing', ['{#file}' => $file]), true);
            }
            if (!$fp = @fopen($file, 'rb')) {
                $this->setErrorMessage($this->lang('email.attachmentUnreadable', ['{#file}' => $file]), true);
            }
            $fileContent  = stream_get_contents($fp);
            $mime         = $this->mimeTypes(pathinfo($file, PATHINFO_EXTENSION));
            fclose($fp);
        } else {
            $fileContent =& $file; // buffered file
        }
        $this->attachments[] = [
            'name'        => [$file, $newName],
            'disposition' => empty($disposition) ? 'attachment' : $disposition,
            // Can also be 'inline'  Not sure if it matters
            'type'        => $mime,
            'content'     => chunk_split(base64_encode($fileContent)),
            'multipart'   => 'mixed',
        ];
    }

    /**
     * Set and return attachment Content-ID
     * Useful for attached inline pictures
     * false if no attachment with this filename was found
     */
    public function setAttachmentCID(string $filename): string|false
    {
        for ($i = 0, $c = count($this->attachments); $i < $c; $i++) {
            if ($this->attachments[$i]['name'][0] === $filename) {
                $this->attachments[$i]['multipart'] = 'related';
                $this->attachments[$i]['cid']       = uniqid(basename($this->attachments[$i]['name'][0]).'@', true);
                return $this->attachments[$i]['cid'];
            }
        }
        return false;
    }

    /**
     * Convert a String to an Array
     */
    protected function stringToArray(array|string $email): array
    {
        if (!is_array($email)) {
            return (strpos($email, ',') !== false)
                ? preg_split('/[\s,]/', $email, -1, PREG_SPLIT_NO_EMPTY)
                : (array)trim($email);
        }
        return $email;
    }

    /**
     * Set Multipart Value
     * @param string $str
     * @return EmailSender
     */
    public function setAltMessage($str)
    {
        $this->altMessage = (string)$str;
        return $this;
    }

    /**
     * Set Mailtype
     * @param string $type
     * @return EmailSender
     */
    public function setMailType($type = 'text')
    {
        $this->config['mailType'] = ($type === 'html') ? 'html' : 'text';
        return $this;
    }

    /**
     * Set Wordwrap
     * @param bool $wordWrap
     * @return EmailSender
     */
    public function setWordWrap($wordWrap = true)
    {
        $this->config['wordWrap'] = (bool)$wordWrap;
        return $this;
    }

    /**
     * Set Protocol
     * @param string $protocol
     * @return EmailSender
     */
    public function setProtocol($protocol = 'mail')
    {
        $this->config['protocol'] = in_array($protocol, $this->allowProtocols, true) ? strtolower($protocol) : 'mail';
        return $this;
    }

    /**
     * Set Priority
     * @param int $n
     * @return EmailSender
     */
    public function setPriority($n = 3)
    {
        $this->config['priority'] = preg_match('/^[1-5]$/', (string)$n) ? (int)$n : 3;
        return $this;
    }

    /**
     * Set CRLF
     * @param string $CRLF
     * @return EmailSender
     */
    public function setCRLF($CRLF = "\n")
    {
        $this->config['CRLF'] = ($CRLF !== "\n" && $CRLF !== "\r\n" && $CRLF !== "\r") ? "\n" : $CRLF;
        return $this;
    }

    /**
     * Get the Message ID
     * @return string
     */
    protected function getMessageID()
    {
        $from = str_replace(['>', '<'], '', $this->headers['Return-Path']);
        return '<'.uniqid('', true).strstr($from, '@').'>';
    }

    /**
     * Get Mail Encoding
     * @return string
     */
    protected function getEncoding()
    {
        $encoding = $this->config['encoding'];
        if (!in_array($encoding, $this->bitDepths)) {
            $encoding = '8bit';
        }
        foreach ($this->baseCharsets as $charset) {
            if (strpos($this->config['charset'], $charset) === 0) {
                $encoding = '7bit';
            }
        }
        return $encoding;
    }

    /**
     * Get content type (text/html/attachment)
     * @return string
     */
    protected function getContentType()
    {
        if ($this->config['mailType'] === 'html') {
            return empty($this->attachments) ? 'html' : 'html-attach';
        } elseif ($this->config['mailType'] === 'text' && ! empty($this->attachments)) {
            return 'plain-attach';
        } else {
            return 'plain';
        }
    }

    /**
     * Mime message
     * @return string
     */
    protected function getMimeMessage()
    {
        return 'This is a multi-part message in MIME format.'.$this->config['newline'].'Your email application may not support this format.';
    }

    /**
     * Validate Email Address
     * @param array $emails
     * @param bool $throw
     * @return bool
     */
    public function validateEmails($emails, $throw = true)
    {
        foreach ($emails as $email) {
            if (! $this->isValidEmail($email)) {
                $this->setErrorMessage($this->lang('email.invalidAddress', ['{#email}' => $email]), $throw);
            }
        }
        return true;
    }

    /**
     * Email Validation
     * @param string $email
     * @return bool
     */
    public function isValidEmail($email)
    {
        if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46') && $atpos = strpos($email, '@')) {
            $email = substr($email, 0, ++$atpos).idn_to_ascii(substr($email, $atpos), 0,
                    INTL_IDNA_VARIANT_UTS46);
        }
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Clean Extended Email Address: Joe Smith <joe@smith.com>
     * @param string $email
     * @return string
     */
    public function cleanEmail($email)
    {
        return preg_match('/\<(.*)\>/', $email, $match) ? $match[1] : $email;
    }

    /**
     * Clean Extended Email Address: Joe Smith <joe@smith.com>
     * @param array $emails
     * @return array
     */
    public function cleanEmails($emails)
    {
        $cleanEmails = [];
        foreach ($emails as $email) {
            $cleanEmails[] = $this->cleanEmail($email);
        }
        return $cleanEmails;
    }

    /**
     * Build alternative plain text message
     * Provides the raw message for use in plain-text headers of
     * HTML-formatted emails.
     * If the user hasn't specified his own alternative message
     * it creates one by stripping the HTML
     * @return string
     */
    protected function getAltMessage()
    {
        if (! empty($this->altMessage)) {
            return ($this->config['wordWrap'])
                ? $this->wordWrap($this->altMessage, 76)
                : $this->altMessage;
        }

        $body = preg_match('/\<body.*?\>(.*)\<\/body\>/si', $this->body, $match) ? $match[1] : $this->body;
        $body = str_replace("\t", '', preg_replace('#<!--(.*)--\>#', '', trim(strip_tags($body))));

        for ($i = 20; $i >= 3; $i--) {
            $body = str_replace(str_repeat("\n", $i), "\n\n", $body);
        }

        // Reduce multiple spaces
        $body = preg_replace('| +|', ' ', $body);

        return ($this->config['wordWrap'])
            ? $this->wordWrap($body, 76)
            : $body;
    }

    /**
     * Word Wrap
     * @param string   $str
     * @param int|null $charLimit Line-length limit
     * @return string
     */
    public function wordWrap($str, $charLimit = null)
    {
        // Set the character limit, if not already present
        if (empty($charLimit))  {
            $charLimit = empty($this->config['wrapChars']) ? 76 : $this->config['wrapChars'];
        }
        // Standardize newlines
        if (strpos($str, "\r") !== false) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }
        // Reduce multiple spaces at end of line
        $str = preg_replace('| +\n|', "\n", $str);
        // If the current word is surrounded by {unwrap} tags we'll
        // strip the entire chunk and replace it with a marker.
        $unwrap = [];
        if (preg_match_all('|\{unwrap\}(.+?)\{/unwrap\}|s', $str, $matches)) {
            for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
                $unwrap[] = $matches[1][$i];
                $str      = str_replace($matches[0][$i], '{{unwrapped'.$i.'}}', $str);
            }
        }
        // Use PHP's native function to do the initial wordwrap.
        // We set the cut flag to FALSE so that any individual words that are
        // too long get left alone. In the next step we'll deal with them.
        $str = wordwrap($str, $charLimit, "\n", false);
        // Split the string into individual lines of text and cycle through them
        $output = '';
        foreach (explode("\n", $str) as $line) {
            // Is the line within the allowed character count?
            // If so we'll join it to the output and continue
            if (strlen($line) <= $charLimit) {
                $output .= $line.$this->config['newline'];
                continue;
            }
            $temp = '';
            do {
                // If the over-length word is a URL we won't wrap it
                if (preg_match('!\[url.+\]|://|www\.!', $line)) {
                    break;
                }
                // Trim the word down
                $temp .= substr($line, 0, $charLimit-1);
                $line = substr($line, $charLimit-1);
            } while (strlen($line) > $charLimit);
            // If $temp contains data it means we had to split up an over-length
            // word into smaller chunks so we'll add it back to our current line
            if ($temp !== '') {
                $output .= $temp.$this->config['newline'];
            }
            $output .= $line.$this->config['newline'];
        }
        // Put our markers back
        if ($unwrap) {
            foreach ($unwrap as $key => $val) {
                $output = str_replace('{{unwrapped'.$key.'}}', $val, $output);
            }
        }
        return $output;
    }

    /**
     * Build final headers
     */
    protected function buildHeaders()
    {
        $this->setHeader('User-Agent', $this->config['userAgent']);
        $this->setHeader('X-Sender', $this->cleanEmail($this->headers['From']));
        $this->setHeader('X-Mailer', $this->config['userAgent']);
        $this->setHeader('X-Priority', $this->priorities[$this->config['priority']]);
        $this->setHeader('Message-ID', $this->getMessageID());
        $this->setHeader('Mime-Version', '1.0');
    }

    /**
     * Write Headers as a string
     */
    protected function writeHeaders()
    {
        if ($this->config['protocol'] === 'mail') {
            if (isset($this->headers['Subject'])) {
                $this->subject = $this->headers['Subject'];
                unset($this->headers['Subject']);
            }
        }
        reset($this->headers);
        $this->headerStr = '';
        foreach ($this->headers as $key => $val) {
            $val = trim($val);
            if ($val !== '') {
                $this->headerStr .= $key.': '.$val.$this->config['newline'];
            }
        }
        if ($this->config['protocol'] === 'mail') {
            $this->headerStr = rtrim($this->headerStr);
        }
    }

    /**
     * Build Final Body and attachments
     */
    protected function buildMessage()
    {
        $newLine = $this->config['newline'];
        if ($this->config['wordWrap'] === true && $this->config['mailType'] !== 'html') {
            $this->body = $this->wordWrap($this->body);
        }
        $boundary = null;

        $this->writeHeaders();

        $hdr  = ($this->config['protocol'] === 'mail') ? $newLine : '';
        $body = '';

        switch ($this->getContentType()) {
            case 'plain':
                $hdr .= 'Content-Type: text/plain; charset='.$this->config['charset'].$newLine
                        .'Content-Transfer-Encoding: '.$this->getEncoding();
                if ($this->config['protocol'] === 'mail') {
                    $this->headerStr .= $hdr;
                    $this->finalBody = $this->body;
                } else {
                    $this->finalBody = $hdr.$newLine.$newLine.$this->body;
                }
                return;
            case 'html':
                if ($this->config['sendMultipart'] === false) {
                    $hdr .= 'Content-Type: text/html; charset='.$this->config['charset'].$newLine
                            .'Content-Transfer-Encoding: quoted-printable';
                } else {
                    $boundary = uniqid('B_ALT_', true);
                    $hdr      .= 'Content-Type: multipart/alternative; boundary="'.$boundary.'"';
                    $body .= $this->getMimeMessage().$newLine.$newLine
                             .'--'.$boundary.$newLine

                             .'Content-Type: text/plain; charset='.$this->config['charset'].$newLine
                             .'Content-Transfer-Encoding: '.$this->getEncoding().$newLine.$newLine
                             .$this->getAltMessage().$newLine.$newLine
                             .'--'.$boundary.$newLine

                             .'Content-Type: text/html; charset='.$this->config['charset'].$newLine
                             .'Content-Transfer-Encoding: quoted-printable'.$newLine.$newLine;
                }
                $this->finalBody = $body.$this->prepQuotedPrintable($this->body).$newLine.$newLine;
                if ($this->config['protocol'] === 'mail') {
                    $this->headerStr .= $hdr;
                } else {
                    $this->finalBody = $hdr.$newLine.$newLine.$this->finalBody;
                }
                if ($this->config['sendMultipart'] !== false) {
                    $this->finalBody .= '--'.$boundary.'--';
                }
                return;
            case 'plain-attach':
                $boundary = uniqid('B_ATC_', true);
                $hdr      .= 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
                if ($this->config['protocol'] === 'mail') {
                    $this->headerStr .= $hdr;
                }
                $body .= $this->getMimeMessage().$newLine
                         .$newLine
                         .'--'.$boundary.$newLine
                         .'Content-Type: text/plain; charset='.$this->config['charset'].$newLine
                         .'Content-Transfer-Encoding: '.$this->getEncoding().$newLine
                         .$newLine
                         .$this->body.$newLine.$newLine;
                $this->appendAttachments($body, $boundary);
                break;
            case 'html-attach':
                $alt_boundary  = uniqid('B_ALT_', true);
                $last_boundary = null;
                if ($this->attachmentsHaveMultipart('mixed')) {
                    $atc_boundary  = uniqid('B_ATC_', true);
                    $hdr           .= 'Content-Type: multipart/mixed; boundary="'.$atc_boundary.'"';
                    $last_boundary = $atc_boundary;
                }
                if ($this->attachmentsHaveMultipart('related')) {
                    $rel_boundary        = uniqid('B_REL_', true);
                    $rel_boundary_header = 'Content-Type: multipart/related; boundary="'.$rel_boundary.'"';
                    if (isset($last_boundary)) {
                        $body .= '--'.$last_boundary.$newLine.$rel_boundary_header;
                    } else {
                        $hdr .= $rel_boundary_header;
                    }
                    $last_boundary = $rel_boundary;
                }
                if ($this->config['protocol'] === 'mail') {
                    $this->headerStr .= $hdr;
                }
                strlen($body) && $body .= $newLine.$newLine;
                $body .= $this->getMimeMessage().$newLine.$newLine
                         .'--'.$last_boundary.$newLine

                         .'Content-Type: multipart/alternative; boundary="'.$alt_boundary.'"'.$newLine.$newLine
                         .'--'.$alt_boundary.$newLine

                         .'Content-Type: text/plain; charset='.$this->config['charset'].$newLine
                         .'Content-Transfer-Encoding: '.$this->getEncoding().$newLine.$newLine
                         .$this->getAltMessage().$newLine.$newLine
                         .'--'.$alt_boundary.$newLine

                         .'Content-Type: text/html; charset='.$this->config['charset'].$newLine
                         .'Content-Transfer-Encoding: quoted-printable'.$newLine.$newLine

                         .$this->prepQuotedPrintable($this->body).$newLine.$newLine
                         .'--'.$alt_boundary.'--'.$newLine.$newLine;
                if (! empty($rel_boundary)) {
                    $body .= $newLine.$newLine;
                    $this->appendAttachments($body, $rel_boundary, 'related');
                }
                // multipart/mixed attachments
                if (! empty($atc_boundary)) {
                    $body .= $newLine.$newLine;
                    $this->appendAttachments($body, $atc_boundary, 'mixed');
                }
                break;
        }
        $this->finalBody = ($this->config['protocol'] === 'mail')
            ? $body
            : $hdr.$newLine.$newLine.$body;
    }

    /**
     * @param string $type
     * @return bool
     */
    protected function attachmentsHaveMultipart($type)
    {
        foreach ($this->attachments as &$attachment) {
            if ($attachment['multipart'] === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prepares attachment string
     * @param string      &$body     Message body to append to
     * @param string      $boundary  Multipart boundary
     * @param string|null $multipart When provided, only attachments of this type will be processed
     * @return void
     */
    protected function appendAttachments(&$body, $boundary, $multipart = null)
    {
        $newLine = $this->config['newline'];
        for ($i = 0, $c = count($this->attachments); $i < $c; $i++) {
            if (isset($multipart) && $this->attachments[$i]['multipart'] !== $multipart) {
                continue;
            }
            $name = isset($this->attachments[$i]['name'][1])
                ? $this->attachments[$i]['name'][1]
                : basename($this->attachments[$i]['name'][0]);
            $body .= '--'.$boundary.$newLine
                     .'Content-Type: '.$this->attachments[$i]['type'].'; name="'.$name.'"'.$newLine
                     .'Content-Disposition: '.$this->attachments[$i]['disposition'].';'.$newLine
                     .'Content-Transfer-Encoding: base64'.$newLine
                     .(empty($this->attachments[$i]['cid']) ? ''
                    : 'Content-ID: <'.$this->attachments[$i]['cid'].'>'.$newLine)
                     .$newLine
                     .$this->attachments[$i]['content'].$newLine;
        }
        // $name won't be set if no attachments were appended,
        // and therefore a boundary wouldn't be necessary
        empty($name) || $body .= '--'.$boundary.'--';
    }

    /**
     * Prep Quoted Printable
     * Prepares string for Quoted-Printable Content-Transfer-Encoding
     * Refer to RFC 2045 http://www.ietf.org/rfc/rfc2045.txt
     * @param string $str
     * @return string
     */
    protected function prepQuotedPrintable($str)
    {
        // ASCII code numbers for "safe" characters that can always be
        // used literally, without encoding, as described in RFC 2049.
        // http://www.ietf.org/rfc/rfc2049.txt
        static $ascii_safe_chars = [
            // ' (  )   +   ,   -   .   /   :   =   ?
            39, 40, 41, 43, 44, 45, 46, 47, 58, 61, 63,
            // numbers
            48, 49, 50, 51, 52, 53, 54, 55, 56, 57,
            // upper-case letters
            65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90,
            // lower-case letters
            97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109,
            110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122,
        ];

        // We are intentionally wrapping so mail servers will encode characters
        // properly and MUAs will behave, so {unwrap} must go!
        $str = str_replace(['{unwrap}', '{/unwrap}'], '', $str);

        // RFC 2045 specifies CRLF as "\r\n".
        // However, many developers choose to override that and violate
        // the RFC rules due to (apparently) a bug in MS Exchange,
        // which only works with "\n".
        if ($this->config['CRLF'] === "\r\n") {
            return quoted_printable_encode($str);
        }

        // Reduce multiple spaces & remove nulls
        $str = preg_replace(['| +|', '/\x00+/'], [' ', ''], $str);

        // Standardize newlines
        if (strpos($str, "\r") !== false) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }

        $escape = '=';
        $output = '';

        foreach (explode("\n", $str) as $line) {
            $length = strlen($line);
            $temp   = '';

            // Loop through each character in the line to add soft-wrap
            // characters at the end of a line " =\r\n" and add the newly
            // processed line(s) to the output (see comment on $crlf class property)
            for ($i = 0; $i < $length; $i++) {
                // Grab the next character
                $char  = $line[$i];
                $ascii = ord($char);

                // Convert spaces and tabs but only if it's the end of the line
                if ($ascii === 32 || $ascii === 9) {
                    if ($i === ($length-1))
                    {
                        $char = $escape.sprintf('%02s', dechex($ascii));
                    }
                }
                // DO NOT move this below the $ascii_safe_chars line!
                //
                // = (equals) signs are allowed by RFC2049, but must be encoded
                // as they are the encoding delimiter!
                elseif ($ascii === 61) {
                    $char = $escape.strtoupper(sprintf('%02s', dechex($ascii)));  // =3D
                } elseif (! in_array($ascii, $ascii_safe_chars, true)) {
                    $char = $escape.strtoupper(sprintf('%02s', dechex($ascii)));
                }

                // If we're at the character limit, add the line to the output,
                // reset our temp variable, and keep on chuggin'
                if ((strlen($temp)+strlen($char)) >= 76) {
                    $output .= $temp.$escape.$this->config['CRLF'];
                    $temp   = '';
                }

                // Add the character to our temporary line
                $temp .= $char;
            }

            // Add our completed line to the output
            $output .= $temp.$this->config['CRLF'];
        }

        // get rid of extra CRLF tacked onto the end
        return substr($output, 0, strlen($this->config['CRLF'])*-1);
    }

    /**
     * Prep Q Encoding
     * Performs "Q Encoding" on a string for use in email headers.
     * It's related but not identical to quoted-printable, so it has its
     * own method.
     * @param string $str
     * @return string
     */
    protected function prepQEncoding($str)
    {
        $str = str_replace(["\r", "\n"], '', $str);

        if ($this->config['charset'] === 'UTF-8') {
            // Note: We used to have mb_encode_mimeheader() as the first choice
            //       here, but it turned out to be buggy and unreliable. DO NOT
            //       re-add it! -- Narf
            if (extension_loaded('iconv')) {
                $output = @iconv_mime_encode('', $str,
                    [
                        'scheme'           => 'Q',
                        'line-length'      => 76,
                        'input-charset'    => $this->config['charset'],
                        'output-charset'   => $this->config['charset'],
                        'line-break-chars' => $this->config['CRLF'],
                    ]
                );

                // There are reports that iconv_mime_encode() might fail and return FALSE
                if ($output !== false) {
                    // iconv_mime_encode() will always put a header field name.
                    // We've passed it an empty one, but it still prepends our
                    // encoded string with ': ', so we need to strip it.
                    return substr($output, 2);
                }

                $chars = iconv_strlen($str, 'UTF-8');
            } elseif (extension_loaded('mbstring')) {
                $chars = mb_strlen($str, 'UTF-8');
            }
        }

        // We might already have this set for UTF-8
        if (!isset($chars)) {
            $chars = strlen($str);
        }

        $output = '=?'.$this->config['charset'].'?Q?';
        for ($i = 0, $length = strlen($output); $i < $chars; $i++) {
            $chr = ($this->config['charset'] === 'UTF-8' && extension_loaded('iconv'))
                ? '='.implode('=', str_split(strtoupper(bin2hex(iconv_substr($str, $i, 1, $this->config['charset']))), 2))
                : '='.strtoupper(bin2hex($str[$i]));

            // RFC 2045 sets a limit of 76 characters per line.
            // We'll append ?= to the end of each line though.
            if ($length+($l = strlen($chr)) > 74) {
                $output .= '?='.$this->config['CRLF'] // EOL
                           .' =?'.$this->config['charset'].'?Q?'.$chr; // New line
                $length = 6+strlen($this->config['charset'])+$l; // Reset the length for the new line
            } else {
                $output .= $chr;
                $length += $l;
            }
        }

        // End the header
        return $output.'?=';
    }

    /**
     * Send Email
     * @param bool $autoClear
     * @return bool
     */
    public function send($autoClear = true)
    {
        if (! isset($this->headers['From'])) {
            $this->setErrorMessage($this->lang('email.noFrom'));
            return false;
        }
        if ($this->replyToFlag === false) {
            $this->setReplyTo($this->headers['From']);
        }
        if (empty($this->recipients)
            && !isset($this->headers['To'])
            && empty($this->BCCArray)
            && !isset($this->headers['Bcc'])
            && !isset($this->headers['Cc'])
        ) {
            $this->setErrorMessage($this->lang('email.noRecipients'));
            return false;
        }
        $this->buildHeaders();
        if ($this->BCCBatchMode && count($this->BCCArray) > $this->BCCBatchSize) {
            $this->batchBCCSend();
            if ($autoClear) {
                $this->clear();
            }
            return true;
        }
        $this->buildMessage();
        $result = $this->spoolEmail();
        if ($result && $autoClear) {
            $this->clear();
        }
        return $result;
    }

    /**
     * Batch Bcc Send. Sends groups of BCCs in batches
     */
    public function batchBCCSend()
    {
        $float = $this->BCCBatchSize-1;
        $set   = '';
        $chunk = [];
        for ($i = 0, $c = count($this->BCCArray); $i < $c; $i++) {
            if (isset($this->BCCArray[$i])) {
                $set .= ', '.$this->BCCArray[$i];
            }
            if ($i === $float) {
                $chunk[] = substr($set, 1);
                $float   += $this->BCCBatchSize;
                $set     = '';
            }
            if ($i === $c-1) {
                $chunk[] = substr($set, 1);
            }
        }
        for ($i = 0, $c = count($chunk); $i < $c; $i++) {
            unset($this->headers['Bcc']);
            $bcc = $this->cleanEmails($this->stringToArray($chunk[$i]));
            if ($this->config['protocol'] !== 'smtp') {
                $this->setHeader('Bcc', implode(', ', $bcc));
            } else {
                $this->BCCArray = $bcc;
            }
            $this->buildMessage();
            $this->spoolEmail();
        }
    }

    /**
     * Unwrap special elements
     */
    protected function unwrapSpecials()
    {
        $this->finalBody = preg_replace_callback(
            '/\{unwrap\}(.*?)\{\/unwrap\}/si',
            [$this, 'removeNLCallback'],
            $this->finalBody
        );
    }

    /**
     * Strip line-breaks via callback
     * @param string $matches
     * @return string
     */
    protected function removeNLCallback($matches)
    {
        if (strpos($matches[1], "\r") !== false || strpos($matches[1], "\n") !== false) {
            $matches[1] = str_replace(["\r\n", "\r", "\n"], '', $matches[1]);
        }
        return $matches[1];
    }

    /**
     * Spool mail to the mail server
     * @return bool
     */
    protected function spoolEmail()
    {
        $this->unwrapSpecials();
        $protocol = $this->config['protocol'];
        $method   = 'sendWith'.ucfirst($protocol);
        if (!$this->$method()) {
            $this->setErrorMessage($this->lang('email.sendFailure'.($protocol === 'mail' ? 'PHPMail' : ucfirst($protocol))));
            return false;
        }
        $this->setErrorMessage($this->lang('email.sent', [$protocol]));
        return true;
    }

    /**
     * Validate email for shell
     * Applies stricter, shell-safe validation to email addresses.
     * Introduced to prevent RCE via sendmail's -f option.
     * @see        https://github.com/bcit-ci/CodeIgniter/issues/4963
     * @see        https://gist.github.com/Zenexer/40d02da5e07f151adeaeeaa11af9ab36
     * @license    https://creativecommons.org/publicdomain/zero/1.0/	CC0 1.0, Public Domain
     * Credits for the base concept go to Paul Buonopane <paul@namepros.com>
     * @param string &$email
     * @return bool
     */
    protected function validateEmailForShell(&$email)
    {
        if (function_exists('idn_to_ascii') && $atpos = strpos($email, '@')) {
            $email = substr($email, 0, ++$atpos).idn_to_ascii(substr($email, $atpos), 0,
                    INTL_IDNA_VARIANT_UTS46);
        }
        return (filter_var($email, FILTER_VALIDATE_EMAIL) === $email
                && preg_match('#\A[a-z0-9._+-]+@[a-z0-9.-]{1,253}\z#i', $email));
    }

    /**
     * Send using mail()
     * @return bool
     */
    protected function sendWithMail()
    {
        // $this->recipients is always populated by setTo() as an array — kept
        // local so the property itself never holds anything but an array
        $recipients = implode(', ', $this->recipients);
        // _validate_email_for_shell() below accepts by reference,
        // so this needs to be assigned to a variable
        $from = $this->cleanEmail($this->headers['Return-Path']);
        if (!$this->validateEmailForShell($from)) {
            return mail($recipients, $this->subject, $this->finalBody, $this->headerStr);
        }
        // most documentation of sendmail using the "-f" flag lacks a space after it, however
        // we've encountered servers that seem to require it to be in place.
        return mail($recipients, $this->subject, $this->finalBody, $this->headerStr, '-f '.$from);
    }

    /**
     * Send using Sendmail
     * @return bool
     */
    protected function sendWithSendmail()
    {
        // _validate_email_for_shell() below accepts by reference,
        // so this needs to be assigned to a variable
        $from = $this->cleanEmail($this->headers['From']);
        if ($this->validateEmailForShell($from)) {
            $from = '-f '.$from;
        } else {
            $from = '';
        }
        // is popen() enabled?
        if (!$this->functionUsable('popen')
            || false === ($fp = @popen($this->config['mailPath'].' -oi '.$from.' -t', 'w'))
        ) {
            // server probably has popen disabled, so nothing we can do to get a verbose error.
            return false;
        }
        fputs($fp, $this->headerStr);
        fputs($fp, $this->finalBody);
        $status = pclose($fp);
        if ($status !== 0) {
            $this->setErrorMessage($this->lang('email.exitStatus', [$status]));
            $this->setErrorMessage($this->lang('email.noSocket'));
            return false;
        }
        return true;
    }

    private function functionUsable($functionName)
    {
        static $_suhosin_func_blacklist;

        if (function_exists($functionName))
        {
            if ( ! isset($_suhosin_func_blacklist))
            {
                $_suhosin_func_blacklist = extension_loaded('suhosin')
                    ? explode(',', trim(ini_get('suhosin.executor.func.blacklist')))
                    : array();
            }

            return ! in_array($functionName, $_suhosin_func_blacklist, TRUE);
        }

        return FALSE;
    }

    /**
     * Send using SMTP
     * @return bool
     */
    protected function sendWithSmtp()
    {
        if ($this->config['SMTPHost'] === '') {
            $this->setErrorMessage($this->lang('email.noHostname'));
            return false;
        }
        if (!$this->SMTPConnect() || !$this->SMTPAuthenticate()) {
            return false;
        }
        if (! $this->sendCommand('from', $this->cleanEmail($this->headers['From']))) {
            $this->SMTPEnd();
            return false;
        }
        foreach ($this->recipients as $val) {
            if (!$this->sendCommand('to', $val)) {
                $this->SMTPEnd();
                return false;
            }
        }
        foreach ($this->CCArray as $val) {
            if ($val !== '' && ! $this->sendCommand('to', $val)) {
                $this->SMTPEnd();
                return false;
            }
        }
        foreach ($this->BCCArray as $val) {
            if ($val !== '' && ! $this->sendCommand('to', $val)) {
                $this->SMTPEnd();
                return false;
            }
        }
        if (! $this->sendCommand('data')) {
            $this->SMTPEnd();
            return false;
        }

        // perform dot transformation on any lines that begin with a dot
        $this->sendData($this->headerStr.preg_replace('/^\./m', '..$1', $this->finalBody));
        $this->sendData('.');
        $reply = $this->getSMTPData();
        $this->setErrorMessage($reply);
        $this->SMTPEnd();
        if (strpos($reply, '250') !== 0) {
            $this->setErrorMessage($this->lang('email.SMTPError', [$reply]));
            return false;
        }
        return true;
    }

    /**
     * SMTP End
     *
     * Shortcut to send RSET or QUIT depending on keep-alive
     */
    protected function SMTPEnd()
    {
        $this->sendCommand($this->config['SMTPKeepAlive'] ? 'reset' : 'quit');
    }

    /**
     * SMTP Connect
     * @return bool
     */
    protected function SMTPConnect()
    {
        if (is_resource($this->SMTPConnect)) {
            return true;
        }
        $ssl = ($this->config['SMTPCrypto'] === 'ssl') ? 'ssl://' : '';
        $this->SMTPConnect = fsockopen(
            $ssl.$this->config['SMTPHost'],
            $this->config['SMTPPort'],
            $errno,
            $errstr,
            $this->config['SMTPTimeout']
        );
        if (! is_resource($this->SMTPConnect)) {
            $this->setErrorMessage($this->lang('email.SMTPError', ['{#error}' => $errno.' '.$errstr]));
            return false;
        }
        stream_set_timeout($this->SMTPConnect, $this->config['SMTPTimeout']);
        $this->setErrorMessage($this->getSMTPData());
        if ($this->config['SMTPCrypto'] === 'tls') {
            $this->sendCommand('hello');
            $this->sendCommand('starttls');
            $crypto = stream_socket_enable_crypto($this->SMTPConnect, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                $this->setErrorMessage($this->lang('email.SMTPError', ['{#error}' => $this->getSMTPData()]));
                return false;
            }
        }
        return $this->sendCommand('hello');
    }

    /**
     * Send SMTP command
     * @param string $cmd
     * @param string $data
     * @return bool
     */
    protected function sendCommand($cmd, $data = '')
    {
        $resp = 0;
        switch ($cmd) {
            case 'hello':
                if ($this->isSMTPAuth || $this->getEncoding() === '8bit') {
                    $this->sendData('EHLO '.$this->getHostname());
                } else {
                    $this->sendData('HELO '.$this->getHostname());
                }
                $resp = 250;
                break;
            case 'starttls':
                $this->sendData('STARTTLS');
                $resp = 220;
                break;
            case 'from':
                $this->sendData('MAIL FROM:<'.$data.'>');
                $resp = 250;
                break;
            case 'to':
                if ($this->config['DSN']) {
                    $this->sendData('RCPT TO:<'.$data.'> NOTIFY=SUCCESS,DELAY,FAILURE ORCPT=rfc822;'.$data);
                } else {
                    $this->sendData('RCPT TO:<'.$data.'>');
                }
                $resp = 250;
                break;
            case 'data':
                $this->sendData('DATA');
                $resp = 354;
                break;
            case 'reset':
                $this->sendData('RSET');
                $resp = 250;
                break;
            case 'quit':
                $this->sendData('QUIT');
                $resp = 221;
                break;
        }
        $reply = $this->getSMTPData();
        $this->debugMessage[] = '<pre>'.$cmd.': '.$reply.'</pre>';
        if ((int)substr($reply, 0, 3) !== $resp) {
            $this->setErrorMessage($this->lang('email.SMTPError', ['{#error}' => $reply]));
            return false;
        }
        if ($cmd === 'quit') {
            fclose($this->SMTPConnect);
        }
        return true;
    }

    /**
     * SMTP Authenticate
     * @return bool
     */
    protected function SMTPAuthenticate()
    {
        if (! $this->isSMTPAuth) {
            return true;
        }
        if ($this->config['SMTPUser'] === '' && $this->config['SMTPPass'] === '') {
            $this->setErrorMessage($this->lang('lang:email.noSMTPAuth'));
            return false;
        }
        $this->sendData('AUTH LOGIN');
        $reply = $this->getSMTPData();
        if (strpos($reply, '503') === 0) { // Already authenticated
            return true;
        } elseif (strpos($reply, '334') !== 0) {
            $this->setErrorMessage($this->lang('email.failedSMTPLogin', ['{#reply}' => $reply]));
            return false;
        }
        $this->sendData(base64_encode($this->config['SMTPUser']));
        $reply = $this->getSMTPData();
        if (strpos($reply, '334') !== 0) {
            $this->setErrorMessage($this->lang('email.SMTPAuthUsername', ['{#reply}' => $reply]));
            return false;
        }
        $this->sendData(base64_encode($this->config['SMTPPass']));
        $reply = $this->getSMTPData();
        if (strpos($reply, '235') !== 0) {
            $this->setErrorMessage($this->lang('email.SMTPAuthPassword', ['{#reply}' => $reply]));
            return false;
        }
        if ($this->config['SMTPKeepAlive']) {
            $this->isSMTPAuth = false;
        }
        return true;
    }

    /**
     * Send SMTP data
     * @param string $data
     * @return bool
     */
    protected function sendData($data)
    {
        $result = null;
        $data .= $this->config['newline'];
        for ($written = $timestamp = 0, $length = strlen($data); $written < $length; $written += $result) {
            if (($result = fwrite($this->SMTPConnect, substr($data, $written))) === false) {
                break;
            } elseif ($result === 0) {
                // See https://bugs.php.net/bug.php?id=39598 and http://php.net/manual/en/function.fwrite.php#96951
                if ($timestamp === 0) {
                    $timestamp = time();
                } elseif ($timestamp < (time()-$this->config['SMTPTimeout'])) {
                    $result = false;
                    break;
                }
                usleep(250000);
                continue;
            } else {
                $timestamp = 0;
            }
        }
        if ($result === false) {
            $this->setErrorMessage($this->lang('email.SMTPDataFailure', ['{#data}' => $data]));
            return false;
        }
        return true;
    }

    /**
     * Get SMTP data
     * @return string
     */
    protected function getSMTPData()
    {
        $data = '';
        while ($str = fgets($this->SMTPConnect, 512)) {
            $data .= $str;
            if ($str[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    /**
     * Get Hostname
     * There are only two legal types of hostname - either a fully
     * qualified domain name (eg: "mail.example.com") or an IP literal
     * (eg: "[1.2.3.4]").
     * @link https://tools.ietf.org/html/rfc5321#section-2.3.5
     * @link http://cbl.abuseat.org/namingproblems.html
     * @return string
     */
    protected function getHostname()
    {
        if (isset($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }
        return isset($_SERVER['SERVER_ADDR']) ? '['.$_SERVER['SERVER_ADDR'].']' : '[127.0.0.1]';
    }

    /**
     * Get Debug Message
     * @param array|string $include List of raw data chunks to include in the output
     *                       Valid options are: 'headers', 'subject', 'body'
     */
    public function printDebugger(array|string $include = ['headers', 'subject', 'body']): string
    {
        $msg = implode('<br/>', $this->debugMessage);

        // Determine which parts of our raw data needs to be printed
        $raw_data = '';
        is_array($include) || $include = [$include];

        in_array('headers', $include, true) && $raw_data = htmlspecialchars($this->headerStr)."\n";
        in_array('subject', $include, true) && $raw_data .= htmlspecialchars($this->subject)."\n";
        in_array('body', $include, true) && $raw_data .= htmlspecialchars($this->finalBody);

        return $msg.($raw_data === '' ? '' : '<pre>'.$raw_data.'</pre>');
    }

    /**
     * Set Error Message
     * @param string $msg
     * @param bool $throw
     */
    protected function setErrorMessage($msg, $throw = false)
    {
        $this->debugMessage[] = $msg;
        if ($throw) {
            throw new RuntimeException($msg);
        }
    }

    /**
     * Mime Types
     * @param string $ext
     * @return string
     */
    protected function mimeTypes($ext = '')
    {
        $mime = (new DefaultMimeTypeConverter())->getType(strtolower($ext));
        return ! empty($mime)
            ? $mime
            : 'application/x-unknown-content-type';
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        is_resource($this->SMTPConnect) && $this->sendCommand('quit');
    }
}

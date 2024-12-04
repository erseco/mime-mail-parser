<?php

/**
 * MimeMailParser.php
 *
 * PHP Version 8.1
 *
 * @category Library
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Erseco;

/**
 * MimeMailParser class for parsing email messages
 *
 * This class provides functionality to parse email messages and extract
 * their content including headers, body parts and attachments.
 *
 * @category Library
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */
/**
 * Message class for handling email message parsing
 *
 * This class provides functionality to parse and extract information from email messages,
 * including headers, body parts, and attachments.
 *
 * @category Library
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */
class Message implements \JsonSerializable
{
    protected string $message;

    protected string $boundary;

    protected array $headers = [];

    /**
     * @var MessagePart[]
     */
    protected array $parts = [];

    /**
     * Create a new Message instance
     *
     * @param string $message         The raw email message
     * @param bool   $ignoreSignature Whether to ignore message signatures
     */
    public function __construct(string $message, bool $ignoreSignature = false)
    {
        $this->message = $message;

        $this->parse($ignoreSignature);
    }

    /**
     * Create a Message instance from a string
     *
     * @param string $message         The raw email message string
     * @param bool   $ignoreSignature Whether to ignore message signatures
     *
     * @return self
     */
    public static function fromString(string $message, bool $ignoreSignature = false): self
    {
        return new self($message);
    }

    /**
     * Create a Message instance from a file
     *
     * @param string $path            Path to the email message file
     * @param bool   $ignoreSignature Whether to ignore message signatures
     *
     * @return self
     */
    public static function fromFile($path, bool $ignoreSignature = false): self
    {
        return new self(file_get_contents($path));
    }

    /**
     * Get the message boundary
     *
     * @return string The message boundary
     */
    public function getBoundary(): string
    {
        return $this->boundary;
    }

    /**
     * Get all headers from the email message
     *
     * @return array<string, string> Array of headers with key-value pairs
     */
    /**
     * Get all headers for this message part
     *
     * @return array Array of headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value from the email message
     *
     * @param string $header  The name of the header to retrieve
     * @param mixed  $default Default value if header is not found
     *
     * @return string|null The header value if found, default value otherwise
     */
    public function getHeader(string $header, $default = null): ?string
    {
        $header = strtolower($header);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $header) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get the Content-Type header of the email message
     *
     * @return string The content type or empty string if not found
     */
    /**
     * Get the content type of this message part
     *
     * @return string The content type or empty string if not set
     */
    public function getContentType(): string
    {
        return $this->getHeader('Content-Type', '');
    }

    /**
     * Get the Message-ID of the email
     *
     * @return string The message ID without angle brackets
     */
    public function getId(): string
    {
        $header = $this->getHeader('Message-ID', '');

        return trim($header, '<>');
    }

    /**
     * Get the email subject
     *
     * @return string The subject line or empty string if not found
     */
    public function getSubject(): string
    {
        return $this->getHeader('Subject', '');
    }

    /**
     * Get the sender's email address
     *
     * @return string The From header value or empty string if not found
     */
    public function getFrom(): string
    {
        return $this->getHeader('From', '');
    }

    /**
     * Get the recipient's email address
     *
     * @return string The To header value or empty string if not found
     */
    public function getTo(): string
    {
        return $this->getHeader('To', '');
    }

    /**
     * Get the reply-to email address
     *
     * @return string The Reply-To header value or empty string if not found
     */
    public function getReplyTo(): string
    {
        return $this->getHeader('Reply-To', '');
    }

    /**
     * Get the date when the email was sent
     *
     * @return \DateTime|null DateTime object of the email date or null if invalid/not found
     */
    public function getDate(): ?\DateTime
    {
        return \DateTime::createFromFormat(
            'D, d M Y H:i:s O',
            $this->getHeader('Date')
        ) ?: null;
    }

    /**
     * Get all message parts
     *
     * @return MessagePart[] Array of all message parts
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * Get the HTML part of the message if available
     *
     * @return MessagePart|null The HTML message part or null if not found
     */
    public function getHtmlPart(): ?MessagePart
    {
        foreach ($this->parts as $part) {
            if ($part->isHtml()) {
                return $part;
            }
        }

        return null;
    }

    /**
     * Get the plain text part of the message if available
     *
     * @return MessagePart|null The text message part or null if not found
     */
    public function getTextPart(): ?MessagePart
    {
        foreach ($this->parts as $part) {
            if ($part->isText()) {
                return $part;
            }
        }

        return null;
    }

    /**
     * @return MessagePart[]
     */
    public function getAttachments(): array
    {
        return array_values(array_filter($this->parts, fn ($part) => $part->isAttachment()));
    }

    /**
     * Get the total size of the email message in bytes
     *
     * @return int Size of the message in bytes
     */
    /**
     * Get the size of the content in bytes
     *
     * @return int Size in bytes
     */
    public function getSize(): int
    {
        return strlen($this->message);
    }

    /**
     * Convert the message to an array representation
     *
     * @return array<string, mixed> Array containing message data
     */
    /**
     * Convert the message part to an array
     *
     * @return array Array representation of the message part
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'subject' => $this->getSubject(),
            'from' => $this->getFrom(),
            'to' => $this->getTo(),
            'reply_to' => $this->getReplyTo(),
            'date' => $this->getDate() ? $this->getDate()->format('c') : null,
            'headers' => $this->getHeaders(),
            'parts' => array_map(fn ($part) => $part->toArray(), $this->getParts()),
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array<string, mixed> Data to be JSON serialized
     */
    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed Data to be JSON serialized
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Parse the email message into headers and body parts
     *
     * This method processes the raw email message, extracting headers and
     * separating the message into its constituent parts (text, HTML, attachments).
     *
     * @param bool $ignoreSignature Whether to ignore email signatures in parsing
     *
     * @return void
     */
    protected function parse(bool $ignoreSignature): void
    {
        $lines = explode("\n", $this->message);
        $headerInProgress = null;

        $collectingBody = false;
        $currentBody = '';
        $currentBodyHeaders = [];
        $currentBodyHeaderInProgress = null;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n ");

            if ($headerInProgress) {

                // Initialize the header if it's not set
                if (!isset($this->headers[$headerInProgress])) {
                    $this->headers[$headerInProgress] = '';
                }

                $this->headers[$headerInProgress] .= PHP_EOL . $line;
           
                $headerInProgress = str_ends_with($this->headers[$headerInProgress], ';');
                continue;
            }

            if ($currentBodyHeaderInProgress) {
                $currentBodyHeaders[$currentBodyHeaderInProgress] .= PHP_EOL . $line;
                $currentBodyHeaderInProgress = str_ends_with($currentBodyHeaders[$currentBodyHeaderInProgress], ';');
                continue;
            }

            if (isset($this->boundary) && str_ends_with($line, '--'.$this->boundary.'--')) {
                $line = str_replace('--'.$this->boundary.'--', '', $line);
                $currentBody .= $line;
                // We've reached the end of the message
                break;
            }

            if (isset($this->boundary) && str_ends_with($line, '--'.$this->boundary)) {
                $line = str_replace('--'.$this->boundary, '', $line);

                if ($collectingBody) {
                    // We've reached the end of a part, add it and reset the variables
                    $this->addPart($currentBody . $line, $currentBodyHeaders);
                }

                $collectingBody = true;
                $currentBody = '';
                $currentBodyHeaders = [];
                continue;
            }

            if ($collectingBody && preg_match('/^(?<key>[A-Za-z\-0-9]+): (?<value>.*)$/', $line, $matches)) {
                $currentBodyHeaders[$matches['key']] = $matches['value'];

                // if the last character is a semicolon, then the header is continued on the next line
                if (str_ends_with($currentBodyHeaders[$matches['key']], ';')) {
                    $currentBodyHeaderInProgress = $matches['key'];
                }

                continue;
            }

            if ($collectingBody) {
                $currentBody .= $line . PHP_EOL;
                continue;
            }

            if (preg_match("/^Content-Type: (?<contenttype>multipart\/.*); boundary=(?<boundary>.*)$/", $line, $matches)) {
                $this->headers['Content-Type'] = $matches['contenttype']."; boundary=".$matches['boundary'];
                $this->boundary = trim($matches['boundary'], '"');
                continue;
            }

            if (preg_match('/^(?<key>[A-Za-z\-0-9]+): (?<value>.*)$/', $line, $matches)) {
                if (strtolower($matches['key']) === 'content-type' && !isset($this->boundary) && !str_contains($matches['value'], 'multipart/mixed')) {
                    // this might be a single-part message. Let's start collecting the body.
                    $collectingBody = true;
                    $currentBody = '';
                    $currentBodyHeaders = [
                        $matches['key'] => $matches['value'],
                    ];

                    if (str_ends_with($currentBodyHeaders[$matches['key']], ';')) {
                        $currentBodyHeaderInProgress = $matches['key'];
                    }

                    continue;
                }

                $this->headers[$matches['key']] = $matches['value'];

                // if the last character is a semicolon, then the header is continued on the next line
                if (str_ends_with($this->headers[$matches['key']], ';')) {
                    $headerInProgress = $matches['key'];
                }

                continue;
            }

            if (preg_match("~^--(?<boundary>[0-9A-Za-z'()+_,-./:=?]{0,68}[0-9A-Za-z'()+_,-./=?])~", $line, $matches)) {
                $this->boundary = trim($matches['boundary']);
                $collectingBody = true;
                $currentBody = '';
                $currentBodyHeaders = [];
                continue;
            }

            // The line is not part of the email message. Let's remove it altogether.
            $this->message = ltrim(substr($this->message, strlen($line)));
        }

        if (!empty($currentBody) || !empty($currentBodyHeaders)) {
            $this->addPart($currentBody, $currentBodyHeaders);
        }

        if (! $this->getContentType() && ($part = $this->getParts()[0] ?? null)) {
            foreach ($part->getHeaders() as $key => $value) {
                if (strtolower($key) === 'content-type') {
                    $this->headers[$key] = $value;
                    break;
                }
            }
        }
    }

    /**
     * Add a new message part to the parts array
     *
     * @param string $currentBody        The content of the message part
     * @param array  $currentBodyHeaders The headers for this message part
     *
     * @return void
     */
    protected function addPart(string $currentBody, array $currentBodyHeaders): void
    {
        $this->parts[] = new MessagePart(trim($currentBody), $currentBodyHeaders);
    }
}

/**
 * MessagePart class for handling individual parts of an email message
 *
 * This class represents a single part of an email message, which could be
 * the body text, HTML content, or an attachment.
 *
 * @category Library
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */
class MessagePart implements \JsonSerializable
{
    protected string $content;

    protected array $headers;

    /**
     * Create a new MessagePart instance
     *
     * @param string $content The content of the message part
     * @param array  $headers The headers associated with this part
     */
    public function __construct(string $content, array $headers = [])
    {
        $this->content = $content;
        $this->headers = $headers;
    }

    public function getContentType(): string
    {
        return $this->headers['Content-Type'] ?? '';
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value
     *
     * @param string $name    The name of the header to retrieve
     * @param mixed  $default Default value if header not found
     *
     * @return mixed The header value or default if not found
     */
    public function getHeader(string $name, $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Get the decoded content of this message part
     *
     * @return string The decoded content
     */
    public function getContent(): string
    {
        if (strtolower($this->getHeader('Content-Transfer-Encoding', '')) === 'base64') {
            return base64_decode($this->content);
        }

        return $this->content;
    }

    /**
     * Check if this part is HTML content
     *
     * @return bool True if content type is text/html
     */
    public function isHtml(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'text/html');
    }

    /**
     * Check if this part is plain text content
     *
     * @return bool True if content type is text/plain
     */
    public function isText(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'text/plain');
    }

    /**
     * Check if this part is an image
     *
     * @return bool True if content type starts with image/
     */
    public function isImage(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'image/');
    }

    /**
     * Check if this part is an attachment
     *
     * @return bool True if content disposition is attachment
     */
    public function isAttachment(): bool
    {
        return str_starts_with($this->getHeader('Content-Disposition', ''), 'attachment');
    }

    /**
     * Get the filename of this part if it's an attachment
     *
     * @return string The filename or empty string if not found
     */
    public function getFilename(): string
    {
        if (preg_match('/filename=([^;]+)/', $this->getHeader('Content-Disposition'), $matches)) {
            return trim($matches[1], '"');
        }

        if (preg_match('/name=([^;]+)/', $this->getContentType(), $matches)) {
            return trim($matches[1], '"');
        }

        return '';
    }

    public function getSize(): int
    {
        return strlen($this->getContent());
    }

    public function toArray(): array
    {
        return [
            'headers' => $this->getHeaders(),
            'content' => $this->getContent(),
            'filename' => $this->getFilename(),
            'size' => $this->getSize(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}

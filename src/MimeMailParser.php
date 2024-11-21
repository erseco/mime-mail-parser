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
class MimeMailParser
{

    private $_rawEmail;
    private $_parsed = [
        'headers' => [],
        'html' => '',
        'text' => '',
        'attachments' => []
    ];

    /**
     * Initializes the class with raw email content.
     *
     * @param string $rawEmail The raw email content as a string.
     */
    public static function fromString(string $rawEmail): MimeMailParser 
    {
        return new self($rawEmail);
    }

    public static function fromFile(string $filename): MimeMailParser
    {
        return new self(file_get_contents($filename));
    }

    public function __construct(string $rawEmail)
    {
        $this->_rawEmail = $rawEmail;
        $this->_parseEmail();
    }

    /**
     * Parses the raw email content.
     */
    /**
     * Parses the raw email content.
     *
     * @return void
     */
    private function _parseEmail(): void
    {
        // Split headers and body
        list($headerSection, $bodySection) = $this->_splitHeadersAndBody($this->_rawEmail);

        // Parse headers
        $this->_parsed['headers'] = $this->_parseHeaders($headerSection);
        // Remove content-transfer-encoding from public headers
        unset($this->_parsed['headers']['Content-Transfer-Encoding']);

        // Determine content type
        $contentType = $this->_parsed['headers']['content-type'] ?? 'text/plain';

        // Check for multipart content
        if (strpos($contentType, 'multipart/') !== false) {
            // Extract boundary
            $boundary = $this->getBoundary();
            if ($boundary) {
                // Parse multipart content
                $this->_parseMultipart($bodySection, $boundary, $contentType);
            }
        } else {
            // Single part email
            $encoding = $this->_parsed['headers']['content-transfer-encoding'] ?? '7bit';
            $decodedContent = $this->_decodeContent($bodySection, $encoding);

            if (strpos($contentType, 'text/html') !== false) {
                $this->_parsed['html'] = $decodedContent;
            } else {
                $this->_parsed['text'] = $decodedContent;
            }
        }
    }

    /**
     * Parses multipart content recursively.
     *
     * @param string $body              The body content.
     * @param string $boundary          The boundary string.
     * @param string $parentContentType The content type of the parent part.
     */
    /**
     * Parses multipart content recursively.
     *
     * @param string $body              The body content.
     * @param string $boundary          The boundary string.
     * @param string $parentContentType The content type of the parent part.
     *
     * @return void
     */
    private function _parseMultipart($body, $boundary, $parentContentType): void
    {
        // Split body into parts
        $parts = $this->_splitBodyByBoundary($body, $boundary);
        foreach ($parts as $part) {
            // Split headers and content
            list($headerSection, $bodyContent) = $this->_splitHeadersAndBody($part);
            $headers = $this->_parseHeaders($headerSection);

            // Get content type and encoding
            $contentType = $headers['content-type'] ?? 'text/plain';
            $encoding = $headers['content-transfer-encoding'] ?? '7bit';

            if (strpos($contentType, 'multipart/') !== false) {
                // Nested multipart
                $subBoundary = $this->getBoundary();
                if ($subBoundary) {
                    $this->_parseMultipart($bodyContent, $subBoundary, $contentType);
                }
            } else {

                // Decode content
                $decodedContent = $this->_decodeContent($bodyContent, $encoding);
                // Clean up boundary markers and whitespace
                $decodedContent = preg_replace('/\r?\n--.*?--\r?\n?$/s', '', $decodedContent);
                $decodedContent = trim($decodedContent);


                // Handle content based on type
                if (strpos($contentType, 'text/html') !== false) {
                    $this->_parsed['html'] = $decodedContent;
                } elseif (strpos($contentType, 'text/plain') !== false) {
                    $this->_parsed['text'] = $decodedContent;
                } elseif (isset($headers['content-disposition']) && strpos($headers['content-disposition'], 'attachment') !== false) {
                    // Handle attachment

                    $filename = $this->_getFilename($headers);
                    if ($filename) {
                        $this->_parsed['attachments'][] = [
                            'filename' => $filename,
                            'content' => $decodedContent,
                            'mimetype' => $contentType,
                            'content-type' => $contentType,
                            'headers' => $headers,
                        ];
                    }
                } elseif (strpos($contentType, 'image/') !== false || strpos($contentType, 'application/') !== false) {
                    // Embedded content
                    $filename = $this->_getFilename($headers) ?? $this->_generateFilename($contentType);
                    $this->_parsed['attachments'][] = [
                        'filename' => $filename,
                        'content' => $decodedContent,
                        'mimetype' => $contentType,
                        'content-type' => $contentType,
                        'headers' => $headers,
                    ];
                }
            }
        }
    }

    /**
     * Splits raw email into headers and body.
     *
     * @param string $rawEmail The raw email content.
     *
     * @return array  An array containing headers and body.
     */
    private function _splitHeadersAndBody(string $rawEmail): array
    {
        $parts = preg_split("/\r?\n\r?\n/", $rawEmail, 2);
        return [
            $parts[0] ?? '',
            $parts[1] ?? ''
        ];
    }

    /**
     * Parses email headers into an associative array.
     *
     * @param string $headerText The header section of the email.
     *
     * @return array  Associative array of headers.
     */
    private function _parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = preg_split("/\r?\n/", $headerText);
        $currentHeader = '';

        foreach ($lines as $line) {
            if (preg_match('/^\s+/', $line)) {
                // Continuation of previous header
                if ($currentHeader) {
                    $headers[strtolower($currentHeader)] .= ' ' . trim($line);
                }
            } else {
                $parts = explode(':', $line, 2);
                if (count($parts) == 2) {
                    $currentHeader = $parts[0]; // Preserve original case
                    $headers[strtolower($currentHeader)] = trim($parts[1]);
                }
            }
        }

        return $headers;
    }

    /**
     * Extracts the boundary string from the Content-Type header stored in the headers array.
     *
     * @return string|null The boundary string or null if not found.
     */
    public function getBoundary(): ?string
    {

        if (isset( $this->_parsed['headers']['content-type'])) {

            $contentType =  $this->_parsed['headers']['content-type'];
            if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Splits the body into parts using the boundary.
     *
     * @param string $body     The body of the email.
     * @param string $boundary The boundary string.
     *
     * @return array  An array of body parts.
     */
    private function _splitBodyByBoundary(string $body, string $boundary): array
    {
        $boundary = preg_quote($boundary, '/');
        $pattern = "/--$boundary(?:--)?[\r\n]+/";
        $parts = preg_split($pattern, $body);
        
        // Remove first empty part and last part after final boundary
        array_shift($parts);
        array_pop($parts);
        
        return array_map('trim', array_filter($parts));
    }

    /**
     * Decodes content based on the encoding specified.
     *
     * @param string $content  The content to decode.
     * @param string $encoding The encoding type.
     *
     * @return string  The decoded content.
     */
    private function _decodeContent(string $content, string $encoding): string
    {
        $encoding = strtolower($encoding);
        switch ($encoding) {
        case 'base64':
            return base64_decode($content);
        case 'quoted-printable':
            return quoted_printable_decode($content);
        case '7bit':
        case '8bit':
        default:
            return $content;
        }
    }

    /**
     * Extracts the filename from the headers.
     *
     * @param array $headers The headers array.
     *
     * @return string|null  The filename or null if not found.
     */
    private function _getFilename(array $headers): ?string
    {        
        if (isset($headers['content-disposition'])) {
            if (preg_match('/filename="?([^"]+)"?/i', $headers['content-disposition'], $matches)) {
                return $matches[1];
            }
        }
        if (isset($headers['content-type'])) {
            if (preg_match('/name="?([^"]+)"?/i', $headers['content-type'], $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Generates a filename based on the content type.
     *
     * @param string $contentType The content type.
     *
     * @return string  A generated filename.
     */
    private function _generateFilename(string $contentType): string
    {
        $extension = explode('/', $contentType)[1] ?? 'dat';
        return 'attachment_' . uniqid() . '.' . $extension;
    }

    /**
     * Gets the headers of the email.
     *
     * @return array The email headers.
     */
    public function getHeaders(): array
    {
        return $this->_parsed['headers'];
    }

    /**
     * Gets a specific header value by name
     * 
     * @param string $name The header name (case-insensitive)
     * 
     * @return string|null The header value or null if not found
     */
    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        return $this->_parsed['headers'][$name] ?? null;
    }

    /**
     * Gets the HTML part of the email.
     *
     * @return string|null The HTML content or null if not available.
     */
    public function getHtmlPart(): ?object
    {
        if (empty($this->_parsed['html'])) {
            return null;
        }

        return new class($this->_parsed['html']) {
            private $content;
            
            public function __construct($content) {
                $this->content = $content;
            }
            
            public function getContent() {
                return $this->content;
            }
            
            public function getHeaders() {
                return [
                    'Content-Type' => 'text/html; charset=utf-8',
                    'Content-Transfer-Encoding' => 'quoted-printable',
                ];
            }
        };
    }

    /**
     * Gets the text part of the email.
     *
     * @return string|null The text content or null if not available.
     */
    public function getTextPart(): ?object
    {
        if (empty($this->_parsed['text'])) {
            return null;
        }

        return new class($this->_parsed['text']) {
            private $content;
            
            public function __construct($content) {
                $this->content = $content;
            }
            
            public function getContent() {
                return $this->content;
            }
            
            public function getHeaders() {
                return [
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'Content-Transfer-Encoding' => 'quoted-printable',
                ];
            }
        };
    }

    /**
     * Gets the email body, preferring HTML content over plain text
     *
     * @return string The email body content
     */
    public function getBody(): string
    {
        // Try to get HTML content first
        $html_content = $this->getHtmlPart();
        if (!empty($html_content)) {
            return $html_content;
        }

        // Fall back to text content
        $text_content = $this->getTextPart();
        if (!empty($text_content)) {
            return nl2br($text_content);
        }

        // If both are empty, return a default message
        return '<p>No content provided in the email.</p>';
    }


    /**
     * Gets the attachments from the email.
     *
     * @return array An array of attachments.
     */
    public function getAttachments(): array
    {
        return $this->_parsed['attachments'];
    }


    /**
     * Retrieves the 'From' header from the parsed headers.
     *
     * @return string|null The 'From' header value or null if not found.
     */
    public function getFrom(): ?string 
    {
        $from = $this->_parsed['headers']['From'] ?? $this->_parsed['headers']['from'] ?? null;
        return $from;
    }

    /**
     * Retrieves the 'To' header from the parsed headers.
     *
     * @return string|null The 'To' header value or null if not found.
     */
    public function getTo(): ?string
    {
        return $this->_parsed['headers']['to'] ?? null;
    }

    /**
     * Retrieves the 'To' header from the parsed headers.
     *
     * @return string|null The 'To' header value or null if not found.
     */
    public function getReplyTo(): ?string
    {
        return $this->_parsed['headers']['reply-to'] ?? null;
    }

    /**
     * Retrieves the 'Subject' header from the parsed headers.
     *
     * @return string|null The 'Subject' header value or null if not found.
     */
    public function getSubject(): ?string
    {
        return $this->_parsed['headers']['Subject'] ?? $this->_parsed['headers']['subject'] ?? null;
    }

    /**
     * Retrieves the 'Message-ID' header from the parsed headers.
     *
     * @return string|null The 'Message-ID' header value or null if not found.
     */
    public function getId(): ?string
    {
        $id = $this->_parsed['headers']['message-id'] ?? null;
        if ($id) {
            return trim($id, '<>');
        }
        return null;
    }

    /**
     * Retrieves the date as a DateTime object, if available.
     *
     * @return \DateTime|null The date object or null if not found.
     */
    public function getDate(): ?\DateTime
    {
        $dateString = $this->_parsed['headers']['date'] ?? null;
        if ($dateString) {
            return new \DateTime($dateString);
        }
        return null;
    }

    /**
     * Retrieves the 'Content-Type' header from the parsed headers.
     *
     * @return string|null The 'Content-Type' header value or null if not found.
     */
    public function getContentType(): ?string
    {
        return $this->_parsed['headers']['content-type'] ?? null;
    }

    /**
     * Retrieves all parts (HTML, text, attachments) from the email.
     *
     * @return array An array of parts.
     */
    public function getParts(): array
    {
        $parts = [];
        if (!empty($this->_parsed['text'])) {
            $contentType = $this->_parsed['headers']['content-type'] ?? '';
            if (preg_match('/charset=["\']?([^"\'\s;]+)["\']?/i', $contentType, $matches)) {
                $charset = $matches[1];
                $textContentType = "text/plain; charset={$charset}";
            } else {
                $textContentType = 'text/plain; charset=utf-8';
            }
            
            $textPart = new class($this->_parsed['text'], $textContentType) {
                private $content;
                private $contentType;
                
                public function __construct($content, $contentType) {
                    $this->content = $content;
                    $this->contentType = $contentType;
                }
                
                public function getContent() {
                    return $this->content;
                }
                
                public function getContentType() {
                    return $this->contentType;
                }
                
                public function getHeaders() {
                    return [
                        'Content-Type' => $this->contentType,
                        'Content-Transfer-Encoding' => 'quoted-printable',
                    ];
                }
                
                public function isHtml() {
                    return false;
                }
                
                public function isAttachment() {
                    return false;
                }
            };
            $parts[] = $textPart;
        }
        if (!empty($this->_parsed['html'])) {
            $contentType = $this->_parsed['headers']['content-type'] ?? '';
            if (preg_match('/charset=["\']?([^"\'\s;]+)["\']?/i', $contentType, $matches)) {
                $charset = $matches[1];
                $htmlContentType = "text/html; charset={$charset}";
            } else {
                $htmlContentType = 'text/html; charset=utf-8';
            }
            
            $htmlPart = new class($this->_parsed['html'], $htmlContentType) {
                private $content;
                private $contentType;
                
                public function __construct($content, $contentType) {
                    $this->content = $content;
                    $this->contentType = $contentType;
                }
                
                public function getContent() {
                    return $this->content;
                }
                
                public function getContentType() {
                    return $this->contentType;
                }
                
                public function getHeaders() {
                    return [
                        'Content-Type' => $this->contentType,
                        'Content-Transfer-Encoding' => 'quoted-printable',
                    ];
                }
                
                public function isHtml() {
                    return true;
                }
                
                public function isAttachment() {
                    return false;
                }
            };
            $parts[] = $htmlPart;
        }
        foreach ($this->_parsed['attachments'] as $attachment) {
            $attachmentPart = new class($attachment) {
                private $attachment;
                
                public function __construct($attachment) {
                    $this->attachment = $attachment;
                }
                
                public function getContent() {
                    return $this->attachment['content'];
                }
                
                public function getContentType() {
                    return $this->attachment['mimetype'];
                }
                
                public function getFilename() {
                    return $this->attachment['filename'];
                }
                
                public function getHeaders() {
                    return $this->attachment['headers'];
                }
                
                public function isHtml() {
                    return false;
                }
                
                public function isAttachment() {
                    return true;
                }
            };
            $parts[] = $attachmentPart;
        }
        return $parts;
    }


}

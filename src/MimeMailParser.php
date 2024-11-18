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
        $this->_parsed['headers'] = array_change_key_case($this->_parseHeaders($headerSection), CASE_LOWER);

        // Determine content type
        $contentType = $this->_parsed['headers']['content-type'] ?? 'text/plain';

        // Check for multipart content
        if (strpos($contentType, 'multipart/') !== false) {
            // Extract boundary
            $boundary = $this->_getBoundary($contentType);
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
    private function _parseMultipart($body, $boundary, $parentContentType)
    {
        // Split body into parts
        $parts = $this->_splitBodyByBoundary($body, $boundary);
        foreach ($parts as $part) {
            // Split headers and content
            list($headerSection, $bodyContent) = $this->_splitHeadersAndBody($part);
            $headers = array_change_key_case($this->_parseHeaders($headerSection), CASE_LOWER);

            // Get content type and encoding
            $contentType = $headers['content-type'] ?? 'text/plain';
            $encoding = $headers['content-transfer-encoding'] ?? '7bit';

            if (strpos($contentType, 'multipart/') !== false) {
                // Nested multipart
                $subBoundary = $this->_getBoundary($contentType);
                if ($subBoundary) {
                    $this->_parseMultipart($bodyContent, $subBoundary, $contentType);
                }
            } else {
                // Decode content
                $decodedContent = $this->_decodeContent($bodyContent, $encoding);

                // Handle content based on type
                if (strpos($contentType, 'text/html') !== false) {
                    $this->_parsed['html'] = $decodedContent;
                } elseif (strpos($contentType, 'text/plain') !== false) {
                    $this->_parsed['text'] = $decodedContent;
                } elseif (isset($headers['content-disposition']) && strpos($headers['content-disposition'], 'attachment') !== false) {
                    // Handle attachment
                    $filename = $this->_getFilename($headers);
                    if ($filename) {
                        $this->parsed['attachments'][] = [
                            'filename' => $filename,
                            'content' => $decodedContent,
                            'mimetype' => $contentType
                        ];
                    }
                } elseif (strpos($contentType, 'image/') !== false || strpos($contentType, 'application/') !== false) {
                    // Embedded content
                    $filename = $this->_getFilename($headers) ?? $this->_generateFilename($contentType);
                    $this->parsed['attachments'][] = [
                        'filename' => $filename,
                        'content' => $decodedContent,
                        'mimetype' => $contentType
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
                $headers[$currentHeader] .= ' ' . trim($line);
            } else {
                $parts = explode(':', $line, 2);
                if (count($parts) == 2) {
                    $currentHeader = trim($parts[0]);
                    $headers[$currentHeader] = trim($parts[1]);
                }
            }
        }

        return $headers;
    }

    /**
     * Extracts the boundary string from the Content-Type header.
     *
     * @param string $contentType The Content-Type header value.
     *
     * @return string|null  The boundary string or null if not found.
     */
    private function _getBoundary(string $contentType): ?string
    {
        if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $matches)) {
            return $matches[1];
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
        $pattern = "/--$boundary(?:--)?\r?\n/";
        $parts = preg_split($pattern, $body);
        return array_filter(
            $parts, function ($part) {
                return trim($part) !== '';
            }
        );
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
        if (isset($headers['Content-Disposition'])) {
            if (preg_match('/filename="([^"]+)"/i', $headers['Content-Disposition'], $matches)) {
                return $matches[1];
            }
        }
        if (isset($headers['Content-Type'])) {
            if (preg_match('/name="([^"]+)"/i', $headers['Content-Type'], $matches)) {
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
     * Gets the HTML part of the email.
     *
     * @return string|null The HTML content or null if not available.
     */
    public function getHtmlPart(): ?string
    {
        return !empty($this->_parsed['html']) ? $this->_parsed['html'] : null;
    }

    /**
     * Gets the text part of the email.
     *
     * @return string|null The text content or null if not available.
     */
    public function getTextPart(): ?string
    {
        return !empty($this->_parsed['text']) ? $this->_parsed['text'] : null;
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
        return $this->_parsed['headers']['From'] ?? null;
    }

    /**
     * Retrieves the 'To' header from the parsed headers.
     *
     * @return string|null The 'To' header value or null if not found.
     */
    public function getTo(): ?string
    {
        return $this->_parsed['headers']['To'] ?? null;
    }

    /**
     * Retrieves the 'Subject' header from the parsed headers.
     *
     * @return string|null The 'Subject' header value or null if not found.
     */
    public function getSubject(): ?string
    {
        return $this->_parsed['headers']['Subject'] ?? null;
    }

    /**
     * Retrieves the 'Message-ID' header from the parsed headers.
     *
     * @return string|null The 'Message-ID' header value or null if not found.
     */
    public function getId(): ?string
    {
        return $this->_parsed['headers']['Message-ID'] ?? null;
    }

    /**
     * Retrieves the date as a DateTime object, if available.
     *
     * @return \DateTime|null The date object or null if not found.
     */
    public function getDate(): ?\DateTime
    {
        $dateString = $this->_parsed['headers']['Date'] ?? null;
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
        return $this->_parsed['headers']['Content-Type'] ?? null;
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
            $parts[] = (object) [
                'contentType' => 'text/plain',
                'content' => $this->_parsed['text'],
                'headers' => $this->_parsed['headers'],
                'isHtml' => false,
                'isAttachment' => false
            ];
        }
        if (!empty($this->_parsed['html'])) {
            $parts[] = (object) [
                'contentType' => 'text/html',
                'content' => $this->_parsed['html'],
                'headers' => $this->_parsed['headers'],
                'isHtml' => true,
                'isAttachment' => false
            ];
        }
        foreach ($this->_parsed['attachments'] as $attachment) {
            $parts[] = (object) [
                'contentType' => $attachment['mimetype'],
                'content' => $attachment['content'],
                'filename' => $attachment['filename'],
                'headers' => $this->parsed['headers'],
                'isAttachment' => true
            ];
        }
        return $parts;
    }


}

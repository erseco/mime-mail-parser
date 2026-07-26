<?php

/**
 * MessagePart.php
 *
 * @category Library
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Erseco;

/**
 * MessagePart class for handling individual parts of an email message.
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
     * Create a new MessagePart instance.
     *
     * @param string                $content The content of the message part.
     * @param array<string, string> $headers The headers associated with this part.
     */
    public function __construct(string $content, array $headers = [])
    {
        $this->content = $content;
        $this->headers = $headers;
    }

    /**
     * Get the content type of this message part.
     *
     * @return string The content type or an empty string if not set.
     */
    public function getContentType(): string
    {
        return $this->getHeader('Content-Type', '');
    }

    /**
     * Get all headers for this message part.
     *
     * @return array<string, string> Array of headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value using a case-insensitive name.
     *
     * @param string $name    The name of the header to retrieve.
     * @param mixed  $default Default value if the header is not found.
     *
     * @return mixed The header value or default if not found.
     */
    public function getHeader(string $name, $default = null): mixed
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return preg_replace('/\n[\t ]+/', ' ', $value) ?? $value;
            }
        }

        return $default;
    }

    /**
     * Get the decoded content of this message part.
     *
     * @return string The decoded content.
     */
    public function getContent(): string
    {
        $encoding = strtolower(trim($this->getHeader('Content-Transfer-Encoding', '')));

        if ($encoding === 'base64') {
            $decoded = base64_decode($this->content, true);

            return $decoded === false ? $this->content : $decoded;
        }

        if ($encoding === 'quoted-printable') {
            return quoted_printable_decode($this->content);
        }

        return $this->content;
    }

    /**
     * Check if this part is HTML content.
     *
     * @return bool True if the content type is text/html.
     */
    public function isHtml(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'text/html');
    }

    /**
     * Check if this part is plain text content.
     *
     * @return bool True if the content type is text/plain.
     */
    public function isText(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'text/plain');
    }

    /**
     * Check if this part is an image.
     *
     * @return bool True if the content type starts with image/.
     */
    public function isImage(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'image/');
    }

    /**
     * Check if this part is an inline resource.
     *
     * @return bool True if the content disposition is inline.
     */
    public function isInline(): bool
    {
        return str_starts_with(
            strtolower(ltrim($this->getHeader('Content-Disposition', ''))),
            'inline'
        );
    }

    /**
     * Check if this part is an attachment.
     *
     * @return bool True if the part is an attachment.
     */
    public function isAttachment(): bool
    {
        $disposition = strtolower(ltrim($this->getHeader('Content-Disposition', '')));

        if (str_starts_with($disposition, 'attachment')) {
            return true;
        }

        return !$this->isInline() && $this->getFilename() !== '';
    }

    /**
     * Get the Content-ID without angle brackets.
     *
     * @return string Content-ID or an empty string if not found.
     */
    public function getContentId(): string
    {
        return trim($this->getHeader('Content-ID', ''), '<>');
    }

    /**
     * Get the filename of this part if available.
     *
     * Supports regular MIME parameters and RFC 2231 extended parameters.
     *
     * @return string The filename or an empty string if not found.
     */
    public function getFilename(): string
    {
        foreach (
            [
                ['Content-Disposition', 'filename'],
                ['Content-Type', 'name'],
            ] as [$header, $parameter]
        ) {
            $filename = $this->getHeaderParameter($header, $parameter);

            if ($filename !== null) {
                return $filename;
            }
        }

        return '';
    }

    /**
     * Get the size of the decoded content in bytes.
     *
     * @return int Size in bytes.
     */
    public function getSize(): int
    {
        return strlen($this->getContent());
    }

    /**
     * Convert the message part to an array representation.
     *
     * @return array<string, mixed> Array containing message part data.
     */
    public function toArray(): array
    {
        return [
            'headers' => $this->getHeaders(),
            'content' => $this->getContent(),
            'filename' => $this->getFilename(),
            'content_id' => $this->getContentId(),
            'inline' => $this->isInline(),
            'size' => $this->getSize(),
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed> Array containing message part data.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Extract and decode a MIME header parameter.
     *
     * @param string $headerName Header name.
     * @param string $parameter  Parameter name.
     *
     * @return string|null Parameter value.
     */
    protected function getHeaderParameter(string $headerName, string $parameter): ?string
    {
        $header = $this->getHeader($headerName, '');

        if (!is_string($header) || $header === '') {
            return null;
        }

        $extendedPattern = '/(?:^|;)\s*'
            . preg_quote($parameter, '/')
            . '\*\s*=\s*(?:"(?<quoted>(?:\\\\.|[^"])*)"|(?<plain>[^;]*))/i';

        if (preg_match($extendedPattern, $header, $matches)) {
            $value = $matches['quoted'] !== '' ? $matches['quoted'] : trim($matches['plain']);

            return $this->decodeExtendedParameter(stripcslashes($value));
        }

        $regularPattern = '/(?:^|;)\s*'
            . preg_quote($parameter, '/')
            . '\s*=\s*(?:"(?<quoted>(?:\\\\.|[^"])*)"|(?<plain>[^;]*))/i';

        if (!preg_match($regularPattern, $header, $matches)) {
            return null;
        }

        $value = $matches['quoted'] !== '' ? $matches['quoted'] : trim($matches['plain']);

        return stripcslashes($value);
    }

    /**
     * Decode an RFC 2231 extended parameter value.
     *
     * @param string $value Encoded parameter value.
     *
     * @return string Decoded value.
     */
    protected function decodeExtendedParameter(string $value): string
    {
        if (!preg_match("/^([^']*)'[^']*'(.*)$/", $value, $matches)) {
            return rawurldecode($value);
        }

        $charset = $matches[1];
        $decoded = rawurldecode($matches[2]);

        if (
            $charset !== ''
            && strcasecmp($charset, 'UTF-8') !== 0
            && function_exists('iconv')
        ) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $decoded);

            if ($converted !== false) {
                return $converted;
            }
        }

        return $decoded;
    }
}

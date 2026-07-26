<?php

/**
 * Message.php
 *
 * @category Library
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Erseco;

/**
 * Message class for parsing email messages.
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

    protected ?string $boundary = null;

    protected array $headers = [];

    protected array $parts = [];

    protected bool $ignoreSignature = false;

    /**
     * Create a new Message instance.
     *
     * @param string $message         The raw email message.
     * @param bool   $ignoreSignature Whether to ignore message signatures.
     */
    public function __construct(string $message, bool $ignoreSignature = false)
    {
        $this->message = $message;
        $this->ignoreSignature = $ignoreSignature;

        $this->parse();
    }

    /**
     * Create a Message instance from a string.
     *
     * @param string $message         The raw email message string.
     * @param bool   $ignoreSignature Whether to ignore message signatures.
     *
     * @return self
     */
    public static function fromString(string $message, bool $ignoreSignature = false): self
    {
        return new self($message, $ignoreSignature);
    }

    /**
     * Create a Message instance from a file.
     *
     * @param string $path            Path to the email message file.
     * @param bool   $ignoreSignature Whether to ignore message signatures.
     *
     * @throws \RuntimeException When the file cannot be read.
     *
     * @return self
     */
    public static function fromFile(string $path, bool $ignoreSignature = false): self
    {
        $message = @file_get_contents($path);

        if ($message === false) {
            throw new \RuntimeException(sprintf('Unable to read email message from "%s".', $path));
        }

        return new self($message, $ignoreSignature);
    }

    /**
     * Get the message boundary.
     *
     * @return string|null The message boundary, or null for non-multipart messages.
     */
    public function getBoundary(): ?string
    {
        return $this->boundary;
    }

    /**
     * Get all headers from the email message.
     *
     * @return array<string, string> Array of headers with key-value pairs.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value from the email message.
     *
     * Header names are matched case-insensitively. Repeated header values are
     * joined with a newline for backwards compatibility; use getHeaderValues()
     * to retrieve them separately.
     *
     * @param string $header  The name of the header to retrieve.
     * @param mixed  $default Default value if the header is not found.
     *
     * @return mixed The header value or the supplied default.
     */
    public function getHeader(string $header, $default = null): mixed
    {
        $key = $this->findHeaderKey($this->headers, $header);

        if ($key === null) {
            return $default;
        }

        return $this->unfoldHeaderValue($this->headers[$key]);
    }

    /**
     * Get every value for a repeated header.
     *
     * @param string $header The name of the header to retrieve.
     *
     * @return array<int, string> The header values.
     */
    public function getHeaderValues(string $header): array
    {
        $value = $this->getHeader($header);

        if (!is_string($value)) {
            return [];
        }

        return preg_split('/\n(?=\S)/', $value) ?: [$value];
    }

    /**
     * Get the Content-Type header of the email message.
     *
     * @return string The content type or an empty string if not found.
     */
    public function getContentType(): string
    {
        return $this->getHeader('Content-Type', '');
    }

    /**
     * Get the Message-ID of the email.
     *
     * @return string The message ID without angle brackets.
     */
    public function getId(): string
    {
        return trim($this->getHeader('Message-ID', ''), '<>');
    }

    /**
     * Get the email subject.
     *
     * @return string The subject line or an empty string if not found.
     */
    public function getSubject(): string
    {
        return $this->getHeader('Subject', '');
    }

    /**
     * Get the sender header.
     *
     * @return string The From header value or an empty string if not found.
     */
    public function getFrom(): string
    {
        return $this->getHeader('From', '');
    }

    /**
     * Get the recipient header.
     *
     * @return string The To header value or an empty string if not found.
     */
    public function getTo(): string
    {
        return $this->getHeader('To', '');
    }

    /**
     * Get the reply-to header.
     *
     * @return string The Reply-To header value or an empty string if not found.
     */
    public function getReplyTo(): string
    {
        return $this->getHeader('Reply-To', '');
    }

    /**
     * Get the date when the email was sent.
     *
     * @return \DateTime|null DateTime object of the email date or null if invalid.
     */
    public function getDate(): ?\DateTime
    {
        $date = $this->getHeader('Date');

        if (!is_string($date) || $date === '') {
            return null;
        }

        try {
            return new \DateTime($date);
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Get all message parts.
     *
     * @return MessagePart[] Array of all message parts.
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * Get the HTML part of the message if available.
     *
     * @return MessagePart|null The HTML message part or null if not found.
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
     * Get the plain text part of the message if available.
     *
     * @return MessagePart|null The text message part or null if not found.
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
     * Get the attachments of a message.
     *
     * @return MessagePart[]
     */
    public function getAttachments(): array
    {
        return array_values(
            array_filter(
                $this->parts,
                static fn (MessagePart $part): bool => $part->isAttachment()
            )
        );
    }

    /**
     * Get the total size of the email message in bytes.
     *
     * @return int Size of the message in bytes.
     */
    public function getSize(): int
    {
        return strlen($this->message);
    }

    /**
     * Convert the message to an array representation.
     *
     * @return array<string, mixed> Array containing message data.
     */
    public function toArray(): array
    {
        $date = $this->getDate();

        return [
            'id' => $this->getId(),
            'subject' => $this->getSubject(),
            'from' => $this->getFrom(),
            'to' => $this->getTo(),
            'reply_to' => $this->getReplyTo(),
            'date' => $date ? $date->format('c') : null,
            'headers' => $this->getHeaders(),
            'parts' => array_map(
                static fn (MessagePart $part): array => $part->toArray(),
                $this->getParts()
            ),
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed> Array containing message data.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Parse the email message into headers and body parts.
     *
     * @return void
     */
    protected function parse(): void
    {
        [$headerBlock, $body] = $this->splitHeaderAndBody(
            $this->removeLeadingNoise($this->message)
        );

        $this->headers = $this->parseHeaders($headerBlock);

        $contentType = $this->getContentType();
        $isMultipart = str_starts_with(strtolower($contentType), 'multipart/');
        $this->boundary = $this->extractParameter($contentType, 'boundary');

        if ($isMultipart && $this->boundary === null) {
            $this->boundary = $this->inferBoundary($body);
        }

        if ($isMultipart && $this->boundary !== null) {
            foreach ($this->splitMultipartBody($body, $this->boundary) as $rawPart) {
                [$partHeaderBlock, $partBody] = $this->splitHeaderAndBody($rawPart);
                $partHeaders = $this->parseHeaders($partHeaderBlock);

                if ($partHeaders === [] && trim($partBody) === '') {
                    continue;
                }

                $this->addPart($this->trimPartLineEndings($partBody), $partHeaders);
            }

            return;
        }

        $partHeaders = $this->extractContentHeaders($this->headers);
        $contentTypeKey = $this->findHeaderKey($partHeaders, 'Content-Type');

        foreach (array_keys($partHeaders) as $contentHeaderKey) {
            unset($this->headers[$contentHeaderKey]);
        }

        if ($contentTypeKey !== null) {
            $this->headers[$contentTypeKey] = $partHeaders[$contentTypeKey];
        }

        if ($contentTypeKey === null) {
            $partHeaders['Content-Type'] = 'text/plain; charset=us-ascii';
        }

        if ($body !== '' || $partHeaders !== []) {
            $this->addPart($this->trimPartLineEndings($body), $partHeaders);
        }
    }

    /**
     * Add a new message part to the parts array.
     *
     * @param string                $currentBody        The content of the message part.
     * @param array<string, string> $currentBodyHeaders The headers for this message part.
     *
     * @return void
     */
    protected function addPart(string $currentBody, array $currentBodyHeaders): void
    {
        $contentTypeKey = $this->findHeaderKey($currentBodyHeaders, 'Content-Type');
        $contentType = $contentTypeKey === null ? '' : $currentBodyHeaders[$contentTypeKey];

        if (str_starts_with(strtolower($contentType), 'multipart/')) {
            $innerMessage = $this->buildHeaderBlock($currentBodyHeaders)
                . "\r\n\r\n"
                . $currentBody;
            $innerParser = new self($innerMessage, $this->ignoreSignature);

            foreach ($innerParser->getParts() as $innerPart) {
                $this->parts[] = $innerPart;
            }

            return;
        }

        if ($this->ignoreSignature && str_starts_with(strtolower($contentType), 'text/plain')) {
            $currentBody = $this->stripSignature($currentBody);
        }

        $this->parts[] = new MessagePart($currentBody, $currentBodyHeaders);
    }

    /**
     * Parse an RFC-style header block.
     *
     * @param string $headerBlock Raw header block.
     *
     * @return array<string, string> Parsed headers.
     */
    protected function parseHeaders(string $headerBlock): array
    {
        $headers = [];
        $currentKey = null;
        $lines = preg_split('/\r\n|\n|\r/', $headerBlock) ?: [];

        foreach ($lines as $line) {
            if ($currentKey !== null && preg_match('/^[\t ]/', $line)) {
                $headers[$currentKey] .= "\n" . $line;
                continue;
            }

            if (!preg_match(
                "/^(?<key>[!#$%&'*+\\-.^_`|~0-9A-Za-z]+):[\\t ]*(?<value>.*)$/",
                $line,
                $matches
            )) {
                $currentKey = null;
                continue;
            }

            $existingKey = $this->findHeaderKey($headers, $matches['key']);

            if ($existingKey !== null) {
                $headers[$existingKey] .= "\n" . $matches['value'];
                $currentKey = $existingKey;
                continue;
            }

            $headers[$matches['key']] = $matches['value'];
            $currentKey = $matches['key'];
        }

        return $headers;
    }

    /**
     * Split a raw message or MIME part into headers and body.
     *
     * @param string $message Raw message content.
     *
     * @return array{0: string, 1: string} Header block and body.
     */
    protected function splitHeaderAndBody(string $message): array
    {
        if (!preg_match('/\r\n\r\n|\n\n|\r\r/', $message, $matches, PREG_OFFSET_CAPTURE)) {
            return [$message, ''];
        }

        $separator = $matches[0][0];
        $offset = $matches[0][1];

        return [
            substr($message, 0, $offset),
            substr($message, $offset + strlen($separator)),
        ];
    }

    /**
     * Remove mbox lines or other content preceding the first valid header.
     *
     * @param string $message Raw message content.
     *
     * @return string Message starting at the first header.
     */
    protected function removeLeadingNoise(string $message): string
    {
        $headerPattern = "[!#$%&'*+\\-.^_`|~0-9A-Za-z]+:[\\t ]*";

        if (preg_match('/^' . $headerPattern . '/', $message)) {
            return $message;
        }

        if (preg_match(
            '/(?:\r\n|\n|\r)(?<header>' . $headerPattern . ')/',
            $message,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return substr($message, $matches['header'][1]);
        }

        return $message;
    }

    /**
     * Split a multipart body into raw child parts.
     *
     * @param string $body     Multipart body.
     * @param string $boundary Multipart boundary.
     *
     * @return array<int, string> Raw MIME parts.
     */
    protected function splitMultipartBody(string $body, string $boundary): array
    {
        $delimiter = '--' . $boundary;
        $lines = preg_split('/\r\n|\n|\r/', $body) ?: [];
        $parts = [];
        $currentLines = [];
        $collecting = false;

        foreach ($lines as $line) {
            $trimmedLine = rtrim($line, "\t ");

            if ($trimmedLine === $delimiter || $trimmedLine === $delimiter . '--') {
                if ($collecting) {
                    $parts[] = implode("\n", $currentLines);
                    $currentLines = [];
                }

                if ($trimmedLine === $delimiter . '--') {
                    break;
                }

                $collecting = true;
                continue;
            }

            if ($collecting) {
                $currentLines[] = $line;
            }
        }

        if ($collecting && $currentLines !== []) {
            $parts[] = implode("\n", $currentLines);
        }

        if ($parts !== []) {
            return $parts;
        }

        // Compatibility fallback for malformed messages with inline boundaries.
        $chunks = explode($delimiter, $body);

        foreach (array_slice($chunks, 1) as $chunk) {
            if (str_starts_with($chunk, '--')) {
                break;
            }

            $chunk = ltrim($chunk, "\r\n");
            $chunk = rtrim($chunk, "\r\n");

            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return $parts;
    }

    /**
     * Extract a parameter from a structured MIME header.
     *
     * @param string $header    Header value.
     * @param string $parameter Parameter name.
     *
     * @return string|null Parameter value.
     */
    protected function extractParameter(string $header, string $parameter): ?string
    {
        $pattern = '/(?:^|;)\s*'
            . preg_quote($parameter, '/')
            . '\s*=\s*(?:"(?<quoted>(?:\\\\.|[^"])*)"|(?<plain>[^;\s]+))/i';

        if (!preg_match($pattern, $header, $matches)) {
            return null;
        }

        $value = $matches['quoted'] !== '' ? $matches['quoted'] : $matches['plain'];

        return stripcslashes($value);
    }

    /**
     * Infer a missing or malformed boundary from the first delimiter line.
     *
     * @param string $body Multipart body.
     *
     * @return string|null Inferred boundary.
     */
    protected function inferBoundary(string $body): ?string
    {
        if (preg_match('/^--(?<boundary>[^\r\n]+?)(?:--)?[\t ]*\r?$/m', $body, $matches)) {
            return $matches['boundary'];
        }

        return null;
    }

    /**
     * Extract MIME content headers for a single-part message.
     *
     * @param array<string, string> $headers Message headers.
     *
     * @return array<string, string> Content headers.
     */
    protected function extractContentHeaders(array $headers): array
    {
        return array_filter(
            $headers,
            static fn (string $key): bool => str_starts_with(strtolower($key), 'content-'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Find the original array key for a case-insensitive header name.
     *
     * @param array<string, string> $headers Headers to search.
     * @param string                $header  Header name.
     *
     * @return string|null Original header key.
     */
    protected function findHeaderKey(array $headers, string $header): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $header) === 0) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Unfold a header value according to RFC 5322.
     *
     * @param string $value Raw header value.
     *
     * @return string Unfolded header value.
     */
    protected function unfoldHeaderValue(string $value): string
    {
        return preg_replace('/\n[\t ]+/', ' ', $value) ?? $value;
    }

    /**
     * Build a raw header block from parsed headers.
     *
     * @param array<string, string> $headers Parsed headers.
     *
     * @return string Raw header block.
     */
    protected function buildHeaderBlock(array $headers): string
    {
        $lines = [];

        foreach ($headers as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }

        return implode("\r\n", $lines);
    }

    /**
     * Remove only MIME framing line endings from a part body.
     *
     * @param string $body Part body.
     *
     * @return string Part body without framing line endings.
     */
    protected function trimPartLineEndings(string $body): string
    {
        return rtrim($body, "\r\n");
    }

    /**
     * Strip the email signature from a body string.
     *
     * @param string $body The body content to strip the signature from.
     *
     * @return string The body without the signature.
     */
    protected function stripSignature(string $body): string
    {
        if (preg_match('/^-- ?\r?$/m', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return rtrim(substr($body, 0, $matches[0][1]));
        }

        return $body;
    }
}

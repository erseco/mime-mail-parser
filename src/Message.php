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
     * @return array<int, string> Header values.
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
     * Get the Content-Type header.
     *
     * @return string Content type or an empty string.
     */
    public function getContentType(): string
    {
        return $this->getHeader('Content-Type', '');
    }

    /**
     * Get the Message-ID without angle brackets.
     *
     * @return string Message ID.
     */
    public function getId(): string
    {
        return trim($this->getHeader('Message-ID', ''), '<>');
    }

    /**
     * Get the message subject.
     *
     * @return string Subject.
     */
    public function getSubject(): string
    {
        return $this->getHeader('Subject', '');
    }

    /**
     * Get the From header.
     *
     * @return string Sender header.
     */
    public function getFrom(): string
    {
        return $this->getHeader('From', '');
    }

    /**
     * Get the To header.
     *
     * @return string Recipient header.
     */
    public function getTo(): string
    {
        return $this->getHeader('To', '');
    }

    /**
     * Get the Reply-To header.
     *
     * @return string Reply-To header.
     */
    public function getReplyTo(): string
    {
        return $this->getHeader('Reply-To', '');
    }

    /**
     * Get the message date.
     *
     * @return \DateTime|null Parsed date or null.
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
     * @return MessagePart[] Message parts.
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * Get the first HTML part.
     *
     * @return MessagePart|null HTML part.
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
     * Get the first plain-text part.
     *
     * @return MessagePart|null Text part.
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
     * Get attachment parts.
     *
     * @return MessagePart[] Attachments.
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
     * Get the raw message size.
     *
     * @return int Size in bytes.
     */
    public function getSize(): int
    {
        return strlen($this->message);
    }

    /**
     * Convert the message to an array.
     *
     * @return array<string, mixed> Message data.
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
     * Specify JSON data.
     *
     * @return array<string, mixed> Message data.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Parse the raw message.
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
        } else {
            $partHeaders['Content-Type'] = 'text/plain; charset=us-ascii';
        }

        $this->addPart($this->trimPartLineEndings($body), $partHeaders);
    }

    /**
     * Add a parsed message part.
     *
     * @param string                $body    Part body.
     * @param array<string, string> $headers Part headers.
     *
     * @return void
     */
    protected function addPart(string $body, array $headers): void
    {
        $contentTypeKey = $this->findHeaderKey($headers, 'Content-Type');
        $contentType = $contentTypeKey === null ? '' : $this->unfoldHeaderValue($headers[$contentTypeKey]);

        if (str_starts_with(strtolower($contentType), 'multipart/')) {
            $innerMessage = $this->buildHeaderBlock($headers) . "\r\n\r\n" . $body;
            $innerParser = new self($innerMessage, $this->ignoreSignature);

            foreach ($innerParser->getParts() as $innerPart) {
                $this->parts[] = $innerPart;
            }

            return;
        }

        if ($this->ignoreSignature && str_starts_with(strtolower($contentType), 'text/plain')) {
            $body = $this->stripSignature($body);
        }

        $this->parts[] = new MessagePart($body, $headers);
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
     * Split headers and body at the first blank line.
     *
     * @param string $message Raw message or MIME part.
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
     * Remove content preceding the first valid header.
     *
     * @param string $message Raw message.
     *
     * @return string Message beginning with a header.
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
     * Split a multipart body into child parts.
     *
     * Correct messages use delimiter-only lines. A fallback keeps support for
     * malformed messages where boundaries are concatenated with the content.
     *
     * @param string $body     Multipart body.
     * @param string $boundary Multipart boundary.
     *
     * @return array<int, string> Raw child parts.
     */
    protected function splitMultipartBody(string $body, string $boundary): array
    {
        $delimiter = '--' . $boundary;
        $lines = preg_split('/\r\n|\n|\r/', $body) ?: [];
        $parts = [];
        $currentLines = [];
        $collecting = false;
        $foundClosingDelimiter = false;

        foreach ($lines as $line) {
            $trimmedLine = rtrim($line, "\t ");

            if ($trimmedLine === $delimiter || $trimmedLine === $delimiter . '--') {
                if ($collecting) {
                    $parts[] = implode("\n", $currentLines);
                    $currentLines = [];
                }

                if ($trimmedLine === $delimiter . '--') {
                    $foundClosingDelimiter = true;
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

        if ($parts !== [] && ($foundClosingDelimiter || substr_count($body, $delimiter) === 1)) {
            return $parts;
        }

        $parts = [];
        $chunks = explode($delimiter, $body);

        foreach (array_slice($chunks, 1) as $chunk) {
            if (str_starts_with($chunk, '--')) {
                break;
            }

            $chunk = trim($chunk, "\r\n");

            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return $parts;
    }

    /**
     * Extract a parameter from a MIME header.
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
     * Infer a boundary from a delimiter line.
     *
     * @param string $body Multipart body.
     *
     * @return string|null Boundary.
     */
    protected function inferBoundary(string $body): ?string
    {
        if (preg_match('/^--(?<boundary>[^\r\n]+?)(?:--)?[\t ]*\r?$/m', $body, $matches)) {
            return $matches['boundary'];
        }

        return null;
    }

    /**
     * Extract Content-* headers from message headers.
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
     * Find a case-insensitive header key.
     *
     * @param array<string, string> $headers Headers.
     * @param string                $header  Header name.
     *
     * @return string|null Original key.
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
     * @param string $value Raw value.
     *
     * @return string Unfolded value.
     */
    protected function unfoldHeaderValue(string $value): string
    {
        return preg_replace('/\n[\t ]+/', ' ', $value) ?? $value;
    }

    /**
     * Build a raw header block.
     *
     * @param array<string, string> $headers Parsed headers.
     *
     * @return string Header block.
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
     * Remove MIME framing line endings from a body.
     *
     * @param string $body Part body.
     *
     * @return string Body without framing line endings.
     */
    protected function trimPartLineEndings(string $body): string
    {
        return rtrim($body, "\r\n");
    }

    /**
     * Strip the plain-text signature delimiter and following content.
     *
     * @param string $body Plain-text body.
     *
     * @return string Body without signature.
     */
    protected function stripSignature(string $body): string
    {
        if (preg_match('/^-- ?\r?$/m', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return rtrim(substr($body, 0, $matches[0][1]));
        }

        return $body;
    }
}

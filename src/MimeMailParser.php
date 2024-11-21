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
class Message implements \JsonSerializable
{
    protected string $message;

    protected string $boundary;

    protected array $headers = [];

    /**
     * @var MessagePart[]
     */
    protected array $parts = [];

    public function __construct(string $message)
    {
        $this->message = $message;

        $this->parse();
    }

    public static function fromString($message): self
    {
        return new self($message);
    }

    public static function fromFile($path): self
    {
        return new self(file_get_contents($path));
    }

    public function getBoundary(): string
    {
        return $this->boundary;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

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

    public function getContentType(): string
    {
        return $this->getHeader('Content-Type', '');
    }

    public function getId(): string
    {
        $header = $this->getHeader('Message-ID', '');

        return trim($header, '<>');
    }

    public function getSubject(): string
    {
        return $this->getHeader('Subject', '');
    }

    public function getFrom(): string
    {
        return $this->getHeader('From', '');
    }

    public function getTo(): string
    {
        return $this->getHeader('To', '');
    }

    public function getReplyTo(): string
    {
        return $this->getHeader('Reply-To', '');
    }

    public function getDate(): ?\DateTime
    {
        return \DateTime::createFromFormat(
            'D, d M Y H:i:s O',
            $this->getHeader('Date')
        ) ?: null;
    }

    public function getParts(): array
    {
        return $this->parts;
    }

    public function getHtmlPart(): ?MessagePart
    {
        foreach ($this->parts as $part) {
            if ($part->isHtml()) {
                return $part;
            }
        }

        return null;
    }

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

    public function getSize(): int
    {
        return strlen($this->message);
    }

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

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Parse the email message into headers and body parts.
     */
    protected function parse(): void
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

    protected function addPart(string $currentBody, array $currentBodyHeaders): void
    {
        $this->parts[] = new MessagePart(trim($currentBody), $currentBodyHeaders);
    }
}

class MessagePart implements \JsonSerializable
{
    protected string $content;

    protected array $headers;

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

    public function getHeader(string $name, $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    public function getContent(): string
    {
        if (strtolower($this->getHeader('Content-Transfer-Encoding', '')) === 'base64') {
            return base64_decode($this->content);
        }

        return $this->content;
    }

    public function isHtml(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'text/html');
    }

    public function isText(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'text/plain');
    }

    public function isImage(): bool
    {
        return str_starts_with(strtolower($this->getContentType()), 'image/');
    }

    public function isAttachment(): bool
    {
        return str_starts_with($this->getHeader('Content-Disposition', ''), 'attachment');
    }

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

<?php

namespace App\Support;

use Stringable;

/**
 * Assembles an SMS body under a strict total-character budget (TASK-352).
 *
 * Carriers deliver a text message as a single SMS only when the whole payload
 * fits in 160 GSM-7 characters; anything longer is split, and iOS in particular
 * turns the overflow into a *.txt attachment the recipient cannot read inline.
 * Our notifications reach phones through an email-to-SMS gateway, so the body we
 * hand the gateway must already be <= 160 characters.
 *
 * The builder distinguishes three kinds of content:
 *
 *  - fixed()    essential literal text (labels, punctuation) that is always kept
 *               in full and never truncated.
 *  - flexible() variable text (customer names, addresses, reasons) that may be
 *               shortened with an ellipsis so the whole message fits.
 *  - url()      an action link, appended at the very end and NEVER truncated —
 *               a half a URL is worse than a short message.
 *
 * Budget priority is: reserve the fixed text and the URL first, then hand the
 * remaining budget to the flexible pieces (fairly, in order). The result is
 * guaranteed to be <= the limit as long as the fixed text plus the URL already
 * fit — which every caller keeps comfortably true.
 *
 * The ellipsis is the ASCII "..." on purpose: the single-character "…" is not in
 * the GSM-7 alphabet and would force the entire message into UCS-2 (a 70-char
 * limit), defeating the point.
 */
class SmsMessage implements Stringable
{
    /** Hard per-message character budget for a single SMS segment. */
    public const LIMIT = 160;

    private const ELLIPSIS = '...';

    /** @var array<int, array{text: string, flexible: bool}> */
    private array $segments = [];

    private ?string $url = null;

    public function __construct(private int $limit = self::LIMIT)
    {
    }

    public static function make(int $limit = self::LIMIT): self
    {
        return new self($limit);
    }

    /**
     * Literal text that is always kept in full (labels, punctuation, short
     * essentials). Callers include any separators here.
     */
    public function fixed(string $text): self
    {
        $this->segments[] = ['text' => $text, 'flexible' => false];

        return $this;
    }

    /**
     * Variable text that may be truncated with an ellipsis to fit the budget.
     * A null/empty value contributes nothing.
     */
    public function flexible(?string $text, string $fallback = ''): self
    {
        $value = trim((string) ($text ?? ''));

        if ($value === '') {
            $value = $fallback;
        }

        $this->segments[] = ['text' => $value, 'flexible' => true];

        return $this;
    }

    /**
     * The action URL. Reserved before flexible content and appended at the end
     * of the message; it is never truncated.
     */
    public function url(?string $url): self
    {
        $url = $url !== null ? trim($url) : null;
        $this->url = ($url === null || $url === '') ? null : $url;

        return $this;
    }

    public function build(): string
    {
        $urlLength = $this->url !== null ? mb_strlen($this->url) : 0;

        $fixedLength = 0;
        foreach ($this->segments as $segment) {
            if (! $segment['flexible']) {
                $fixedLength += mb_strlen($segment['text']);
            }
        }

        // Whatever the fixed text and the URL do not consume is available to the
        // flexible pieces.
        $flexibleBudget = max(0, $this->limit - $fixedLength - $urlLength);

        // Positions of the flexible segments, in order, so we can hand each a
        // fair share of the remaining budget. Segments that need less than their
        // share release the rest to the ones that follow.
        $flexiblePositions = [];
        foreach ($this->segments as $index => $segment) {
            if ($segment['flexible']) {
                $flexiblePositions[] = $index;
            }
        }

        $rendered = [];
        foreach ($this->segments as $index => $segment) {
            $rendered[$index] = $segment['flexible'] ? '' : $segment['text'];
        }

        $remaining = $flexibleBudget;
        $total = count($flexiblePositions);
        foreach ($flexiblePositions as $ordinal => $index) {
            $segmentsLeft = $total - $ordinal;
            $share = $segmentsLeft > 0 ? intdiv($remaining, $segmentsLeft) : 0;
            $piece = $this->truncate($this->segments[$index]['text'], $share);
            $rendered[$index] = $piece;
            $remaining -= mb_strlen($piece);
        }

        $body = implode('', $rendered);

        if ($this->url !== null) {
            $body .= $this->url;
        }

        return $body;
    }

    public function __toString(): string
    {
        return $this->build();
    }

    /**
     * Shorten $text to at most $max characters, using an ellipsis when there is
     * room for one. The return value is always <= $max characters.
     */
    private function truncate(string $text, int $max): string
    {
        if ($max <= 0) {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $ellipsisLength = mb_strlen(self::ELLIPSIS);

        if ($max <= $ellipsisLength) {
            return mb_substr($text, 0, $max);
        }

        return mb_substr($text, 0, $max - $ellipsisLength).self::ELLIPSIS;
    }
}

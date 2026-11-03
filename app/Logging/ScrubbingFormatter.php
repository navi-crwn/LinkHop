<?php

namespace App\Logging;

use App\Support\EncryptedNoteLink;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/** Runs the formatter a channel already had, then takes the note keys out of the text it produced. */
class ScrubbingFormatter implements FormatterInterface
{
    public function __construct(private readonly FormatterInterface $inner) {}

    public function format(LogRecord $record): mixed
    {
        return $this->scrub($this->inner->format($record));
    }

    public function formatBatch(array $records): mixed
    {
        return array_map(fn (LogRecord $record) => $this->format($record), $records);
    }

    private function scrub(mixed $line): mixed
    {
        if (! is_string($line) || ! str_contains($line, '#')) {
            return $line;
        }
        try {
            return EncryptedNoteLink::scrubText($line);
        } catch (\Throwable) {
            // the log must keep working when the host list cannot be built (no container yet, no database)
            return $line;
        }
    }
}

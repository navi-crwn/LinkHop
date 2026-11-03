<?php

namespace App\Logging;

use Monolog\Handler\FormattableHandlerInterface;

/**
 * Log channel tap: the secret parameters of an encrypted NoteHop address never reach a log line. A failed query
 * puts its SQL values into the exception message, so a key that sits in a text field would otherwise be written to
 * laravel.log in clear. The line is cut after it has been formatted, so the message, the context and the stack
 * trace are all covered.
 */
class ScrubNoteKeys
{
    public function __invoke($logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new ScrubbingFormatter($handler->getFormatter()));
            }
        }
    }
}

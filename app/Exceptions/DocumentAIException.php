<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised when a document cannot be analyzed by the AI provider.
 *
 * Never means "the document is invalid": it only means the AI gave no verdict.
 * Retryable errors (timeouts, 429, 5xx) are retried by the job; the rest
 * send the document to manual review.
 */
final class DocumentAIException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function transient(string $message, ?Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    public static function unprocessable(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }
}

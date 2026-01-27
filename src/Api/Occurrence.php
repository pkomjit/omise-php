<?php

declare(strict_types=1);

namespace Omise\Api;

use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Occurrence API for retrieving schedule occurrences.
 *
 * Occurrences represent individual executions of a schedule,
 * such as a single charge in a recurring charge schedule.
 *
 * @see https://docs.omise.co/occurrences-api
 */
class Occurrence extends ApiResource
{
    protected string $endpoint = 'occurrences';

    /**
     * Occurrence status constants.
     */
    public const string STATUS_SCHEDULED = 'scheduled';
    public const string STATUS_SUCCESSFUL = 'successful';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_SKIPPED = 'skipped';

    /**
     * Retrieve an occurrence by ID.
     *
     * @param  string $id  Occurrence ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * Check if the occurrence is scheduled (not yet executed).
     */
    public function isScheduled(Response $occurrence): bool
    {
        return $occurrence->get('status') === self::STATUS_SCHEDULED;
    }

    /**
     * Check if the occurrence was successful.
     */
    public function isSuccessful(Response $occurrence): bool
    {
        return $occurrence->get('status') === self::STATUS_SUCCESSFUL;
    }

    /**
     * Check if the occurrence failed.
     */
    public function isFailed(Response $occurrence): bool
    {
        return $occurrence->get('status') === self::STATUS_FAILED;
    }

    /**
     * Check if the occurrence was skipped.
     */
    public function isSkipped(Response $occurrence): bool
    {
        return $occurrence->get('status') === self::STATUS_SKIPPED;
    }

    /**
     * Get the schedule ID.
     */
    public function getScheduleId(Response $occurrence): ?string
    {
        return $occurrence->get('schedule');
    }

    /**
     * Get the scheduled date.
     */
    public function getScheduledOn(Response $occurrence): ?string
    {
        return $occurrence->get('scheduled_on');
    }

    /**
     * Get the processed date.
     */
    public function getProcessedAt(Response $occurrence): ?string
    {
        return $occurrence->get('processed_at');
    }

    /**
     * Get the result (charge or transfer ID).
     */
    public function getResult(Response $occurrence): ?string
    {
        return $occurrence->get('result');
    }

    /**
     * Get the retry date (if the occurrence is scheduled for retry).
     */
    public function getRetryOn(Response $occurrence): ?string
    {
        return $occurrence->get('retry_on');
    }

    /**
     * Get the message (usually contains error details for failed occurrences).
     */
    public function getMessage(Response $occurrence): ?string
    {
        return $occurrence->get('message');
    }

    /**
     * Get the creation date.
     */
    public function getCreatedAt(Response $occurrence): ?string
    {
        return $occurrence->get('created_at');
    }
}

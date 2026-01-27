<?php

declare(strict_types=1);

namespace Omise\Api;

use InvalidArgumentException;
use Omise\Exceptions\ApiException;
use Omise\Http\Response;

/**
 * Schedule API for managing recurring charges and transfers.
 *
 * Schedules allow you to create recurring billing cycles for charges or transfers.
 *
 * @see https://docs.omise.co/schedules-api
 */
class Schedule extends ApiResource
{
    protected string $endpoint = 'schedules';

    /**
     * Schedule status constants.
     */
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_EXPIRING = 'expiring';
    public const string STATUS_EXPIRED = 'expired';
    public const string STATUS_DELETED = 'deleted';
    public const string STATUS_SUSPENDED = 'suspended';

    /**
     * Period constants.
     */
    public const string PERIOD_DAY = 'day';
    public const string PERIOD_WEEK = 'week';
    public const string PERIOD_MONTH = 'month';

    /**
     * Day of week constants (for weekly schedules).
     */
    public const string DAY_MONDAY = 'monday';
    public const string DAY_TUESDAY = 'tuesday';
    public const string DAY_WEDNESDAY = 'wednesday';
    public const string DAY_THURSDAY = 'thursday';
    public const string DAY_FRIDAY = 'friday';
    public const string DAY_SATURDAY = 'saturday';
    public const string DAY_SUNDAY = 'sunday';

    /**
     * Retrieve a schedule by ID.
     *
     * @param  string $id  Schedule ID
     * @throws ApiException
     */
    public function retrieve(string $id): Response
    {
        return parent::retrieve($id);
    }

    /**
     * List all schedules with optional filters.
     *
     * @param  array $params  List parameters (offset, limit, from, to, order)
     * @throws ApiException
     */
    public function all(array $params = []): Response
    {
        return parent::all($this->normalizePaginationParams($params));
    }

    /**
     * Delete/destroy a schedule.
     *
     * @param  string $scheduleId  Schedule ID
     * @throws ApiException
     */
    public function destroy(string $scheduleId): Response
    {
        $data = $this->client->delete(
            $this->buildEndpoint($scheduleId),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Create a charge schedule.
     *
     * @param  array $params  Schedule parameters:
     *   - every: Interval value (e.g., 1, 2, 3)
     *   - period: 'day', 'week', or 'month'
     *   - on: When to execute:
     *     - For weekly: ['weekdays' => ['monday', 'friday']]
     *     - For monthly: ['days_of_month' => [1, 15]] or ['weekday_of_month' => 'last_friday']
     *   - start_date: Schedule start date (YYYY-MM-DD)
     *   - end_date: Schedule end date (YYYY-MM-DD)
     *   - charge: Charge parameters:
     *     - customer: Customer ID
     *     - card: Card ID (optional)
     *     - amount: Amount in the smallest unit
     *     - currency: Currency code
     *     - description: Charge description
     *     - metadata: Additional metadata
     * @throws ApiException
     */
    public function createChargeSchedule(array $params): Response
    {
        $this->validateScheduleParams($params);

        if (empty($params['charge'])) {
            throw new InvalidArgumentException('charge is required');
        }

        $data = $this->client->post(
            '/charges/schedules',
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Create a transfer schedule.
     *
     * @param  array $params  Schedule parameters:
     *   - every: Interval value (e.g., 1, 2, 3)
     *   - period: 'day', 'week', or 'month'
     *   - on: When to execute
     *   - start_date: Schedule start date (YYYY-MM-DD)
     *   - end_date: Schedule end date (YYYY-MM-DD)
     *   - transfer: Transfer parameters:
     *     - recipient: Recipient ID (optional, uses default if not set)
     *     - amount: Amount in the smallest unit (optional, uses percentage_of_balance if not set)
     *     - percentage_of_balance: Percentage to transfer (optional)
     * @throws ApiException
     */
    public function createTransferSchedule(array $params): Response
    {
        $this->validateScheduleParams($params);

        if (empty($params['transfer'])) {
            throw new InvalidArgumentException('transfer is required');
        }

        $data = $this->client->post(
            '/transfers/schedules',
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List charge schedules.
     *
     * @param  array $params  List parameters
     * @throws ApiException
     */
    public function chargeSchedules(array $params = []): Response
    {
        $data = $this->client->get(
            '/charges/schedules',
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List transfer schedules.
     *
     * @param  array $params  List parameters
     * @throws ApiException
     */
    public function transferSchedules(array $params = []): Response
    {
        $data = $this->client->get(
            '/transfers/schedules',
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Search charge schedules.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function searchChargeSchedules(array $params = []): Response
    {
        $data = $this->client->get(
            '/charges/schedules/search',
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Search transfer schedules.
     *
     * @param  array $params  Search parameters
     * @throws ApiException
     */
    public function searchTransferSchedules(array $params = []): Response
    {
        $data = $this->client->get(
            '/transfers/schedules/search',
            $params,
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * List occurrences for a schedule.
     *
     * @param  string $scheduleId  Schedule ID
     * @param  array $params  List parameters
     * @throws ApiException
     */
    public function occurrences(string $scheduleId, array $params = []): Response
    {
        $data = $this->client->get(
            $this->buildEndpoint("{$scheduleId}/occurrences"),
            $this->normalizePaginationParams($params),
            $this->usePublicKey
        );

        return $this->createResponse($data);
    }

    /**
     * Check if the schedule is active.
     */
    public function isActive(Response $schedule): bool
    {
        return $schedule->get('status') === self::STATUS_ACTIVE;
    }

    /**
     * Check if the schedule is expiring.
     */
    public function isExpiring(Response $schedule): bool
    {
        return $schedule->get('status') === self::STATUS_EXPIRING;
    }

    /**
     * Check if the schedule is expired.
     */
    public function isExpired(Response $schedule): bool
    {
        return $schedule->get('status') === self::STATUS_EXPIRED;
    }

    /**
     * Check if the schedule is deleted.
     */
    public function isDeleted(Response $schedule): bool
    {
        return $schedule->get('status') === self::STATUS_DELETED;
    }

    /**
     * Check if the schedule is suspended.
     */
    public function isSuspended(Response $schedule): bool
    {
        return $schedule->get('status') === self::STATUS_SUSPENDED;
    }

    /**
     * Get the schedule period.
     */
    public function getPeriod(Response $schedule): ?string
    {
        return $schedule->get('period');
    }

    /**
     * Get the schedule interval.
     */
    public function getEvery(Response $schedule): ?int
    {
        $every = $schedule->get('every');
        return $every !== null ? (int) $every : null;
    }

    /**
     * Get the start date.
     */
    public function getStartDate(Response $schedule): ?string
    {
        return $schedule->get('start_on') ?? $schedule->get('start_date');
    }

    /**
     * Get the end date.
     */
    public function getEndDate(Response $schedule): ?string
    {
        return $schedule->get('end_on') ?? $schedule->get('end_date');
    }

    /**
     * Get the next occurrence dates.
     */
    public function getNextOccurrences(Response $schedule): array
    {
        return $schedule->get('next_occurrences_on', []);
    }

    /**
     * Get the charge parameters (for charge schedules).
     */
    public function getChargeParams(Response $schedule): array
    {
        return $schedule->get('charge', []);
    }

    /**
     * Get the transfer parameters (for transfer schedules).
     */
    public function getTransferParams(Response $schedule): array
    {
        return $schedule->get('transfer', []);
    }

    /**
     * Validate required schedule parameters.
     *
     * @throws InvalidArgumentException
     */
    private function validateScheduleParams(array $params): void
    {
        if (empty($params['every'])) {
            throw new InvalidArgumentException('every is required');
        }

        if (empty($params['period'])) {
            throw new InvalidArgumentException('period is required');
        }

        $validPeriods = [self::PERIOD_DAY, self::PERIOD_WEEK, self::PERIOD_MONTH];
        if (!in_array($params['period'], $validPeriods, true)) {
            throw new InvalidArgumentException('period must be "day", "week", or "month"');
        }
    }
}

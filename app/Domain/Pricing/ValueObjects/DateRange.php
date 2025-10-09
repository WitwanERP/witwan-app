<?php

namespace App\Domain\Pricing\ValueObjects;

use DateTime;
use DateInterval;
use InvalidArgumentException;

class DateRange
{
    private DateTime $startDate;
    private DateTime $endDate;

    public function __construct(DateTime $startDate, DateTime $endDate)
    {
        if ($startDate > $endDate) {
            throw new InvalidArgumentException('Start date must be before or equal to end date');
        }

        $this->startDate = clone $startDate;
        $this->endDate = clone $endDate;
    }

    public static function fromStrings(string $startDate, string $endDate): self
    {
        return new self(
            new DateTime($startDate),
            new DateTime($endDate)
        );
    }

    public function getStartDate(): DateTime
    {
        return clone $this->startDate;
    }

    public function getEndDate(): DateTime
    {
        return clone $this->endDate;
    }

    public function getDurationInDays(): int
    {
        return $this->startDate->diff($this->endDate)->days + 1;
    }

    public function contains(DateTime $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    public function overlaps(DateRange $other): bool
    {
        return $this->startDate <= $other->endDate && $other->startDate <= $this->endDate;
    }

    public function equals(DateRange $other): bool
    {
        return $this->startDate == $other->startDate && $this->endDate == $other->endDate;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s',
            $this->startDate->format('Y-m-d'),
            $this->endDate->format('Y-m-d')
        );
    }
}
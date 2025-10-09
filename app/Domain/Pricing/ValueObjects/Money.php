<?php

declare(strict_types=1);

namespace App\Domain\Pricing\ValueObjects;

use InvalidArgumentException;

final class Money
{
    private float $amount;
    private string $currency;

    public function __construct(float $amount, string $currency)
    {
        $this->validateAmount($amount);
        $this->validateCurrency($currency);

        $this->amount = round($amount, 2);
        $this->currency = strtoupper($currency);
    }

    public static function fromCents(int $cents, string $currency): self
    {
        return new self($cents / 100, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0.0, $currency);
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    public function percentage(float $percentage): self
    {
        return new self($this->amount * ($percentage / 100), $this->currency);
    }

    public function format(): string
    {
        return sprintf('%s %.2f', $this->currency, $this->amount);
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch');
        }
    }

    private function validateAmount(float $amount): void
    {
        if (!is_finite($amount)) {
            throw new InvalidArgumentException('Amount must be finite');
        }
    }

    private function validateCurrency(string $currency): void
    {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be 3 letters');
        }
    }
}
/

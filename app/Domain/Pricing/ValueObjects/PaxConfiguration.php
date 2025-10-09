<?php

namespace App\Domain\Pricing\ValueObjects;

use InvalidArgumentException;

class PaxConfiguration
{
    private int $adults;
    private int $children;
    private int $infants;

    public function __construct(int $adults, int $children = 0, int $infants = 0)
    {
        if ($adults < 0 || $children < 0 || $infants < 0) {
            throw new InvalidArgumentException('Pax numbers must be non-negative');
        }

        if ($adults === 0 && ($children > 0 || $infants > 0)) {
            throw new InvalidArgumentException('Children and infants require at least one adult');
        }

        $this->adults = $adults;
        $this->children = $children;
        $this->infants = $infants;
    }

    public function getAdults(): int
    {
        return $this->adults;
    }

    public function getChildren(): int
    {
        return $this->children;
    }

    public function getInfants(): int
    {
        return $this->infants;
    }

    public function getTotalPax(): int
    {
        return $this->adults + $this->children + $this->infants;
    }

    public function getPaxByType(string $type): int
    {
        return match (strtolower($type)) {
            'adults', 'adultos' => $this->adults,
            'children', 'niños' => $this->children,
            'infants', 'bebes' => $this->infants,
            default => throw new InvalidArgumentException("Invalid pax type: {$type}")
        };
    }

    public function equals(PaxConfiguration $other): bool
    {
        return $this->adults === $other->adults
            && $this->children === $other->children
            && $this->infants === $other->infants;
    }

    public function __toString(): string
    {
        $parts = [];

        if ($this->adults > 0) {
            $parts[] = "{$this->adults} adultos";
        }

        if ($this->children > 0) {
            $parts[] = "{$this->children} niños";
        }

        if ($this->infants > 0) {
            $parts[] = "{$this->infants} bebés";
        }

        return implode(', ', $parts) ?: '0 pasajeros';
    }
}
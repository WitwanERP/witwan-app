<?php

namespace App\Domain\Pricing\Enums;

enum ClientType: string
{
    case INDIVIDUAL = 'individual';
    case CORPORATE = 'corporate';
    case AGENCY = 'agency';
    case WHOLESALE = 'wholesale';
    case VIP = 'vip';
    case GROUP = 'group';

    public function getLabel(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::CORPORATE => 'Corporativo',
            self::AGENCY => 'Agencia',
            self::WHOLESALE => 'Mayorista',
            self::VIP => 'VIP',
            self::GROUP => 'Grupo',
        };
    }

    public function getDiscountPercentage(): float
    {
        return match ($this) {
            self::INDIVIDUAL => 0.0,
            self::CORPORATE => 5.0,
            self::AGENCY => 10.0,
            self::WHOLESALE => 15.0,
            self::VIP => 20.0,
            self::GROUP => 8.0,
        };
    }

    public function getMinimumOrderAmount(): float
    {
        return match ($this) {
            self::INDIVIDUAL => 0.0,
            self::CORPORATE => 1000.0,
            self::AGENCY => 2000.0,
            self::WHOLESALE => 5000.0,
            self::VIP => 0.0,
            self::GROUP => 3000.0,
        };
    }

    public function hasSpecialRates(): bool
    {
        return match ($this) {
            self::INDIVIDUAL => false,
            self::CORPORATE, self::AGENCY, self::WHOLESALE, self::VIP, self::GROUP => true,
        };
    }

    public function requiresApproval(): bool
    {
        return match ($this) {
            self::WHOLESALE, self::VIP => true,
            default => false,
        };
    }

    public function getPaymentTerms(): int
    {
        return match ($this) {
            self::INDIVIDUAL => 0,
            self::CORPORATE => 30,
            self::AGENCY => 15,
            self::WHOLESALE => 45,
            self::VIP => 60,
            self::GROUP => 15,
        };
    }

    public static function getCommercialTypes(): array
    {
        return [
            self::CORPORATE,
            self::AGENCY,
            self::WHOLESALE,
            self::GROUP,
        ];
    }
}
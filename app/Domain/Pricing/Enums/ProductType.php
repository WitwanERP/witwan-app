<?php

namespace App\Domain\Pricing\Enums;

enum ProductType: string
{
    case HOTEL = 'HOT';
    case TRANSFER = 'TRN';
    case EXCURSION = 'EXC';
    case PACKAGE = 'PAQ';
    case OTHER = 'OTR';
    case TICKET = 'CTK';
    case TRAVEL_ASSISTANCE = 'ASV';
    case AIR_QUOTA = 'CAE';

    public function getLabel(): string
    {
        return match ($this) {
            self::HOTEL => 'Hotel',
            self::TRANSFER => 'Traslado',
            self::EXCURSION => 'Excursión',
            self::PACKAGE => 'Paquete',
            self::OTHER => 'Otro',
            self::TICKET => 'Ticket/Entrada',
            self::TRAVEL_ASSISTANCE => 'Asistencia al Viajero',
            self::AIR_QUOTA => 'Cupo Aéreo',
        };
    }

    public function getCategory(): string
    {
        return match ($this) {
            self::HOTEL => 'accommodation',
            self::TRANSFER => 'transportation',
            self::EXCURSION => 'activities',
            self::PACKAGE => 'packages',
            self::TICKET => 'entertainment',
            self::TRAVEL_ASSISTANCE => 'services',
            self::AIR_QUOTA => 'flights',
            self::OTHER => 'miscellaneous',
        };
    }

    public function requiresRoomConfiguration(): bool
    {
        return match ($this) {
            self::HOTEL => true,
            default => false,
        };
    }

    public function hasDynamicPricing(): bool
    {
        return match ($this) {
            self::HOTEL, self::AIR_QUOTA => true,
            default => false,
        };
    }

    public function hasFixedDates(): bool
    {
        return match ($this) {
            self::AIR_QUOTA => true,
            default => false,
        };
    }

    public function requiresQuotaCheck(): bool
    {
        return match ($this) {
            self::HOTEL, self::EXCURSION, self::PACKAGE, self::TICKET, self::AIR_QUOTA => true,
            default => false,
        };
    }

    public static function getByCategory(string $category): array
    {
        return array_filter(
            self::cases(),
            fn(ProductType $type) => $type->getCategory() === $category
        );
    }
}
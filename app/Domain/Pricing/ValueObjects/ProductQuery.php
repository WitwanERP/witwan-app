<?php

namespace App\Domain\Pricing\ValueObjects;

use App\Domain\Pricing\Enums\ProductType;
use DateTime;
use InvalidArgumentException;

class ProductQuery
{
    private int $clientId;
    private ProductType $productType;
    private int $originCityId;
    private int $destinationCityId;
    private DateRange $travelDates;
    private PaxConfiguration $paxConfiguration;
    private bool $isResident;
    private ?int $specificProductId;
    private array $additionalFilters;

    public function __construct(
        int $clientId,
        ProductType $productType,
        int $originCityId,
        int $destinationCityId,
        DateRange $travelDates,
        PaxConfiguration $paxConfiguration,
        bool $isResident = false,
        ?int $specificProductId = null,
        array $additionalFilters = []
    ) {
        if ($clientId <= 0) {
            throw new InvalidArgumentException('Client ID must be positive');
        }

        if ($originCityId <= 0 || $destinationCityId <= 0) {
            throw new InvalidArgumentException('City IDs must be positive');
        }

        $this->clientId = $clientId;
        $this->productType = $productType;
        $this->originCityId = $originCityId;
        $this->destinationCityId = $destinationCityId;
        $this->travelDates = $travelDates;
        $this->paxConfiguration = $paxConfiguration;
        $this->isResident = $isResident;
        $this->specificProductId = $specificProductId;
        $this->additionalFilters = $additionalFilters;
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getProductType(): ProductType
    {
        return $this->productType;
    }

    public function getOriginCityId(): int
    {
        return $this->originCityId;
    }

    public function getDestinationCityId(): int
    {
        return $this->destinationCityId;
    }

    public function getTravelDates(): DateRange
    {
        return $this->travelDates;
    }

    public function getPaxConfiguration(): PaxConfiguration
    {
        return $this->paxConfiguration;
    }

    public function isResident(): bool
    {
        return $this->isResident;
    }

    public function getSpecificProductId(): ?int
    {
        return $this->specificProductId;
    }

    public function getAdditionalFilters(): array
    {
        return $this->additionalFilters;
    }

    public function getResidentCode(): string
    {
        return $this->isResident ? 'R' : 'N';
    }

    public function hasSpecificProduct(): bool
    {
        return $this->specificProductId !== null;
    }

    public function addFilter(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->additionalFilters[$key] = $value;
        return $clone;
    }

    public function removeFilter(string $key): self
    {
        $clone = clone $this;
        unset($clone->additionalFilters[$key]);
        return $clone;
    }

    public function getFilter(string $key, mixed $default = null): mixed
    {
        return $this->additionalFilters[$key] ?? $default;
    }

    public function withTravelDates(DateRange $newDates): self
    {
        $clone = clone $this;
        $clone->travelDates = $newDates;
        return $clone;
    }

    public function withPaxConfiguration(PaxConfiguration $newPax): self
    {
        $clone = clone $this;
        $clone->paxConfiguration = $newPax;
        return $clone;
    }

    public function withSpecificProduct(?int $productId): self
    {
        $clone = clone $this;
        $clone->specificProductId = $productId;
        return $clone;
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'product_type' => $this->productType->value,
            'origin_city_id' => $this->originCityId,
            'destination_city_id' => $this->destinationCityId,
            'travel_dates' => [
                'start' => $this->travelDates->getStartDate()->format('Y-m-d'),
                'end' => $this->travelDates->getEndDate()->format('Y-m-d'),
                'duration' => $this->travelDates->getDurationInDays(),
            ],
            'pax_configuration' => [
                'adults' => $this->paxConfiguration->getAdults(),
                'children' => $this->paxConfiguration->getChildren(),
                'infants' => $this->paxConfiguration->getInfants(),
                'total' => $this->paxConfiguration->getTotalPax(),
            ],
            'is_resident' => $this->isResident,
            'specific_product_id' => $this->specificProductId,
            'additional_filters' => $this->additionalFilters,
        ];
    }
}
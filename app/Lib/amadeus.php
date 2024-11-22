<?php

namespace Vendor\AmadeusAirParser;

class AirParser
{
    private array $parsed;
    private array $currentPassenger;
    private array $currentSegment;
    private string $currentTicket;

    private const SEGMENT_IDENTIFIERS = [
        'HEADER' => 'AIR-BLK',
        'AMENDMENT' => 'AMD',
        'OFFICE_ID' => 'MUC',
        'AIRLINE' => 'A-',
        'BOOKING' => 'B-',
        'DATE' => 'D-',
        'FLIGHT' => ['H-', 'U-'],  // U- para segmentos no confirmados
        'FARE' => 'K-',
        'FARE_TAX' => ['KFTF', 'KFTR', 'KFT'],
        'TAX' => 'TAX-',
        'PASSENGER' => 'I-',
        'SSR' => 'SSR',
        'TICKET' => 'T-',
        'FORM_OF_PAYMENT' => 'FP',
        'FARE_BASIS' => 'M-',
        'REMARKS' => 'RM',
    ];

    private const PASSENGER_TYPES = [
        'ADT' => 'ADULT',
        'CHD' => 'CHILD',
        'INF' => 'INFANT'
    ];

    public function __construct()
    {
        $this->initializeParseStructure();
    }

    /**
     * Parse el contenido completo del archivo AIR
     */
    public function parse(string $content): array
    {
        $this->initializeParseStructure();
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $this->parseLine(trim($line));
        }

        $this->processEndOfParsing();
        return $this->parsed;
    }

    /**
     * Inicializa la estructura del parser
     */
    private function initializeParseStructure(): void
    {
        $this->parsed = [
            'header' => [],
            'flights' => [],
            'passengers' => [],
            'fares' => [
                'base_fares' => [],
                'taxes' => [],
                'totals' => []
            ],
            'tickets' => [],
            'ssrs' => [],
            'remarks' => [],
            'meta' => [
                'created_at' => date('Y-m-d H:i:s'),
                'version' => '2.0'
            ]
        ];

        $this->currentPassenger = [];
        $this->currentSegment = [];
        $this->currentTicket = '';
    }

    /**
     * Parse una línea individual
     */
    private function parseLine(string $line): void
    {
        if (empty($line)) return;

        switch (true) {
            case strpos($line, self::SEGMENT_IDENTIFIERS['HEADER']) === 0:
                $this->parseHeader($line);
                break;

            case in_array(substr($line, 0, 2), self::SEGMENT_IDENTIFIERS['FLIGHT']):
                $this->parseFlightSegment($line);
                break;

            case strpos($line, self::SEGMENT_IDENTIFIERS['PASSENGER']) === 0:
                $this->parsePassenger($line);
                break;

            case strpos($line, self::SEGMENT_IDENTIFIERS['FARE']) === 0:
                $this->parseFare($line);
                break;

            case strpos($line, self::SEGMENT_IDENTIFIERS['TAX']) === 0:
                $this->parseTax($line);
                break;

            case strpos($line, self::SEGMENT_IDENTIFIERS['SSR']) === 0:
                $this->parseSSR($line);
                break;

            case strpos($line, self::SEGMENT_IDENTIFIERS['TICKET']) === 0:
                $this->parseTicket($line);
                break;

            case strpos($line, self::SEGMENT_IDENTIFIERS['FORM_OF_PAYMENT']) === 0:
                $this->parseFormOfPayment($line);
                break;

            case strpos($line, self::SEGMENT_IDENTIFIERS['FARE_BASIS']) === 0:
                $this->parseFareBasis($line);
                break;

            case strpos($line, self::SEGMENT_IDENTIFIERS['REMARKS']) === 0:
                $this->parseRemark($line);
                break;
        }
    }

    /**
     * Parse el encabezado del archivo
     */
    private function parseHeader(string $line): void
    {
        $parts = explode(';', $line);
        $this->parsed['header'] = [
            'record_type' => $parts[0],
            'office_id' => $parts[1] ?? '',
            'document_number' => $parts[4] ?? '',
            'control_number' => $parts[5] ?? '',
            'sequence' => $parts[6] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Parse un segmento de vuelo
     */
    private function parseFlightSegment(string $line): void
    {
        $parts = explode(';', $line);
        $segmentNumber = substr($parts[0] ?? '', 2);

        // Parse la información del vuelo
        $flightInfo = explode(' ', trim($parts[5] ?? ''));

        $segment = [
            'segment_number' => $segmentNumber,
            'sequence' => $parts[1] ?? '',
            'from_city' => substr($parts[1] ?? '', -3),
            'from_airport' => trim($parts[2] ?? ''),
            'to_city' => $parts[3] ?? '',
            'to_airport' => trim($parts[4] ?? ''),
            'airline' => $flightInfo[0] ?? '',
            'flight_number' => $flightInfo[1] ?? '',
            'class' => $flightInfo[2] ?? '',
            'status_code' => $flightInfo[3] ?? '',
            'departure_date' => $flightInfo[4] ?? '',
            'departure_time' => $flightInfo[5] ?? '',
            'arrival_date' => $flightInfo[7] ?? '',
            'arrival_time' => $flightInfo[6] ?? '',
            'status' => $parts[6] ?? '',
            'booking_status' => $parts[7] ?? '',
            'cabin_class' => $parts[8] ?? '',
            'equipment' => $parts[10] ?? '',
            'operated_by' => trim($parts[12] ?? ''),
            'baggage' => $parts[13] ?? '',
            'duration' => $parts[16] ?? '',
            'co2' => $this->parseCO2Data($parts),
            'distance' => $parts[18] ?? '',
            'origin_country' => $parts[19] ?? '',
            'destination_country' => $parts[20] ?? '',
            'connection_type' => $parts[21] ?? ''
        ];

        // Procesamiento adicional del segmento
        $segment['duration_formatted'] = $this->formatDuration($segment['duration']);
        $segment['is_connection'] = !empty($segment['connection_type']);

        $this->currentSegment = $segment;
        $this->parsed['flights'][] = $segment;
    }

    /**
     * Parse la información de CO2 del segmento
     */
    private function parseCO2Data(array $parts): ?array
    {
        foreach ($parts as $part) {
            if (preg_match('/CO2[\/\s]?(\d+\.?\d*)\s*(KG|G)?/i', trim($part), $matches)) {
                return [
                    'amount' => (float)$matches[1],
                    'unit' => !empty($matches[2]) ? strtoupper($matches[2]) : 'KG',
                    'raw' => trim($part)
                ];
            }
        }
        return null;
    }

    /**
     * Parse información de pasajero
     */
    private function parsePassenger(string $line): void
    {
        $parts = explode(';', $line);
        $sequenceInfo = explode(',', $parts[0]);

        $passengerInfo = $this->parsePassengerName($parts[1] ?? '');
        $passenger = [
            'sequence' => substr($sequenceInfo[0], 2),
            'number' => $parts[1] ?? '',
            'surname' => $passengerInfo['surname'],
            'given_name' => $passengerInfo['given_name'],
            'type' => $passengerInfo['type'],
            'contact' => $parts[2] ?? '',
            'documents' => [],
            'tickets' => [],
            'ssrs' => [],
            'frequent_flyer' => null
        ];

        $this->currentPassenger = $passenger;
        $this->parsed['passengers'][] = $passenger;
    }

    /**
     * Parse el nombre del pasajero
     */
    private function parsePassengerName(string $nameString): array
    {
        $result = [
            'surname' => '',
            'given_name' => '',
            'type' => 'ADT'
        ];

        // Detectar tipo de pasajero
        foreach (self::PASSENGER_TYPES as $code => $type) {
            if (strpos($nameString, "($code)") !== false) {
                $result['type'] = $code;
                $nameString = str_replace("($code)", '', $nameString);
                break;
            }
        }

        $nameParts = explode('/', trim($nameString));
        $result['surname'] = trim($nameParts[0] ?? '');
        $result['given_name'] = trim($nameParts[1] ?? '');

        return $result;
    }

    /**
     * Parse información de tarifa
     */
    private function parseFare(string $line): void
    {
        if (strpos($line, 'KFTF') === 0 || strpos($line, 'KFTR') === 0) {
            $this->parseFareTax($line);
            return;
        }

        $parts = explode(';', $line);
        $fareString = substr($parts[0] ?? '', 2);

        preg_match('/([A-Z]{3})(\d+\.?\d*)/', $fareString, $matches);

        $fare = [
            'currency' => $matches[1] ?? '',
            'amount' => $matches[2] ?? '',
            'total_amount' => $parts[13] ?? '',
            'rate_of_exchange' => $this->extractRateOfExchange($parts),
        ];

        $this->parsed['fares']['base_fares'][] = $fare;
    }

    /**
     * Parse impuestos de la tarifa
     */
    private function parseFareTax(string $line): void
    {
        $parts = explode(';', $line);
        array_shift($parts); // Remove KFTF/KFTR

        foreach ($parts as $part) {
            if (empty(trim($part))) continue;

            $taxParts = preg_split('/\s+/', trim($part));
            if (count($taxParts) >= 3) {
                $this->parsed['fares']['taxes'][] = [
                    'amount' => $taxParts[1] ?? '',
                    'currency' => $taxParts[0] ?? '',
                    'code' => $taxParts[2] ?? '',
                    'type' => $taxParts[3] ?? ''
                ];
            }
        }
    }

    /**
     * Parse SSR (Special Service Request)
     */
    private function parseSSR(string $line): void
    {
        $parts = explode(' ', $line, 5);

        $ssr = [
            'type' => $parts[1] ?? '',
            'airline' => $parts[2] ?? '',
            'status' => substr($parts[3] ?? '', 0, 2),
            'number' => substr($parts[3] ?? '', 2),
            'text' => $parts[4] ?? '',
            'passenger_reference' => $this->extractPassengerReference($line)
        ];

        if ($ssr['type'] === 'DOCS') {
            $this->parseDocsSSR($ssr);
        }

        $this->parsed['ssrs'][] = $ssr;

        // Asociar SSR con el pasajero actual si existe
        if (!empty($this->currentPassenger)) {
            $passengerIndex = count($this->parsed['passengers']) - 1;
            if ($passengerIndex >= 0) {
                $this->parsed['passengers'][$passengerIndex]['ssrs'][] = $ssr;
            }
        }
    }

    /**
     * Parse SSR DOCS específicamente
     */
    private function parseDocsSSR(array $ssr): void
    {
        $docsParts = explode('/', trim($ssr['text']));
        if (count($docsParts) >= 7) {
            $passengerIndex = count($this->parsed['passengers']) - 1;
            if ($passengerIndex >= 0) {
                $this->parsed['passengers'][$passengerIndex]['documents'] = [
                    'type' => $docsParts[0] ?? '',
                    'country' => $docsParts[1] ?? '',
                    'number' => $docsParts[2] ?? '',
                    'nationality' => $docsParts[3] ?? '',
                    'dob' => $docsParts[4] ?? '',
                    'gender' => $docsParts[5] ?? '',
                    'expiry' => $docsParts[6] ?? ''
                ];
            }
        }
    }

    /**
     * Parse información del ticket
     */
    private function parseTicket(string $line): void
    {
        if (strpos($line, 'TKOK') === 0 || strpos($line, 'TKTL') === 0) {
            $this->parseTicketTimeLimit($line);
            return;
        }

        $parts = explode('-', $line);
        $ticket = [
            'type' => substr($parts[0] ?? '', 2),
            'airline' => $parts[1] ?? '',
            'number' => $parts[2] ?? '',
            'status' => 'OK',
            'issuing_date' => null,
            'passenger_reference' => $this->currentPassenger['sequence'] ?? null
        ];

        $this->currentTicket = $ticket['number'];
        $this->parsed['tickets'][] = $ticket;

        // Asociar ticket con el pasajero actual
        if (!empty($this->currentPassenger)) {
            $passengerIndex = count($this->parsed['passengers']) - 1;
            if ($passengerIndex >= 0) {
                $this->parsed['passengers'][$passengerIndex]['tickets'][] = $ticket;
            }
        }
    }

    // Métodos de utilidad

    private function formatDuration(string $duration): string
    {
        if (strlen($duration) === 4) {
            $hours = substr($duration, 0, 2);
            $minutes = substr($duration, 2, 2);
            return sprintf('%02d:%02d', $hours, $minutes);
        }
        return $duration;
    }

    private function extractPassengerReference(string $line): ?string
    {
        if (preg_match('/;P(\d+)/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractRateOfExchange(array $parts): ?string
    {
        foreach ($parts as $part) {
            if (preg_match('/ROE(\d*\.?\d*)/', $part, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    private function processEndOfParsing(): void
    {
        $this->calculateTotals();
        $this->linkPassengersToSegments();
        $this->validateRequiredFields();
    }

    private function calculateTotals(): void
    {
        // Calcular totales de tarifas
        $totalBase = 0;
        $totalTaxes = 0;
        $currency = '';

        foreach ($this->parsed['fares']['base_fares'] as $fare) {
            $totalBase += (float)$fare['amount'];
            $currency = $fare['currency'];
        }

        foreach ($this->parsed['fares']['taxes'] as $tax) {
            $totalTaxes += (float)$tax['amount'];
        }

        $this->parsed['fares']['totals'] = [
            'base_amount' => $totalBase,
            'tax_amount' => $totalTaxes,
            'total_amount' => $totalBase + $totalTaxes,
            'currency' => $currency
        ];
    }

    private function linkPassengersToSegments(): void
    {
        foreach ($this->parsed['passengers'] as &$passenger) {
            $passenger['segments'] = [];
            foreach ($this->parsed['flights'] as $segment) {
                if ($this->isPassengerInSegment($passenger, $segment)) {
                    $passenger['segments'][] = $segment['segment_number'];
                }
            }
        }
    }

    private function isPassengerInSegment(array $passenger, array $segment): bool
    {
        // Lógica para determinar si el pasajero está en el segmento
        // Esto puede variar según la aerolínea y el formato específico
        return true; // Por defecto asumimos que sí
    }

    private function validateRequiredFields(): void
    {
        // Validar campos requeridos
        $required = [
            'header' => ['record_type', 'control_number'],
            'flights' => ['segment_number', 'from_city', 'to_city'],
            'passengers' => ['sequence', 'surname']
        ];

        foreach ($required as $section => $fields) {
            if ($section === 'flights' || $section === 'passengers') {
                foreach ($this->parsed[$section] as $item) {
                    foreach ($fields as $field) {
                        if (empty($item[$field])) {
                            $this->parsed['warnings'][] = "Campo requerido faltante: $section.$field";
                        }
                    }
                }
            } else {
                foreach ($fields as $field) {
                    if (empty($this->parsed[$section][$field])) {
                        $this->parsed['warnings'][] = "Campo requerido faltante: $section.$field";
                    }
                }
            }
        }
    }

    // Métodos públicos de acceso

    /**
     * Obtener todos los segmentos de vuelo
     */
    public function getFlightSegments(): array
    {
        return $this->parsed['flights'];
    }

    /**
     * Obtener información de todos los pasajeros
     */
    public function getPassengers(): array
    {
        return $this->parsed['passengers'];
    }

    /**
     * Obtener todas las tarifas e impuestos
     */
    public function getFares(): array
    {
        return $this->parsed['fares'];
    }

    /**
     * Obtener todos los tickets
     */
    public function getTickets(): array
    {
        return $this->parsed['tickets'];
    }

    /**
     * Obtener todos los SSRs
     */
    public function getSSRs(): array
    {
        return $this->parsed['ssrs'];
    }

    /**
     * Obtener la información de CO2 de todos los segmentos
     */
    public function getCO2Information(): array
    {
        $co2Info = [];
        foreach ($this->parsed['flights'] as $flight) {
            if (!empty($flight['co2'])) {
                $co2Info[] = [
                    'segment' => $flight['segment_number'],
                    'route' => $flight['from_city'] . '-' . $flight['to_city'],
                    'flight' => $flight['airline'] . $flight['flight_number'],
                    'co2' => $flight['co2']
                ];
            }
        }
        return $co2Info;
    }

    /**
     * Obtener el total de CO2 del itinerario
     */
    public function getTotalCO2(): ?array
    {
        $total = 0;
        $unit = null;
        $segments = 0;

        foreach ($this->parsed['flights'] as $flight) {
            if (!empty($flight['co2'])) {
                $total += $flight['co2']['amount'];
                $unit = $flight['co2']['unit'];
                $segments++;
            }
        }

        if ($segments > 0) {
            return [
                'total' => $total,
                'unit' => $unit,
                'segments_counted' => $segments
            ];
        }

        return null;
    }

    /**
     * Obtener todos los datos raw parseados
     */
    public function getRawData(): array
    {
        return $this->parsed;
    }

    /**
     * Obtener información de un pasajero específico
     */
    public function getPassengerByNumber(string $passengerNumber): ?array
    {
        foreach ($this->parsed['passengers'] as $passenger) {
            if ($passenger['sequence'] === $passengerNumber) {
                return $passenger;
            }
        }
        return null;
    }

    /**
     * Obtener todas las advertencias generadas durante el parsing
     */
    public function getWarnings(): array
    {
        return $this->parsed['warnings'] ?? [];
    }
}
/**$parser = new AirParser();
$result = $parser->parse($airContent);

// Obtener información específica
$flights = $parser->getFlightSegments();
$co2Info = $parser->getCO2Information();
$totalCO2 = $parser->getTotalCO2();
$passengers = $parser->getPassengers();
$fares = $parser->getFares();
$warnings = $parser->getWarnings(); */
?>

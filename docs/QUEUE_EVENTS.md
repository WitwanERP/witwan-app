# Queue Event System

## Overview

The Queue Event System provides asynchronous processing of business operations through the `colaevento` table. This system was migrated from CodeIgniter's `Procesarcola` controller to a modern Laravel implementation.

## Architecture

### Components

1. **Colaevento Model** ([app/Models/Colaevento.php](../app/Models/Colaevento.php))
   - Represents events in the queue
   - States: `pendiente`, `procesado`, `error`

2. **QueueEventService** ([app/Services/QueueEventService.php](../app/Services/QueueEventService.php))
   - Main business logic for processing events
   - Handles event routing based on type and model

3. **LicenseSyncService** ([app/Services/LicenseSyncService.php](../app/Services/LicenseSyncService.php))
   - Syncs reservations with external license systems
   - Uses HTTP client for API communication

4. **ProcessQueueEventsCommand** ([app/Console/Commands/ProcessQueueEventsCommand.php](../app/Console/Commands/ProcessQueueEventsCommand.php))
   - Artisan command for manual/scheduled processing

## Event Types

### 1. Reserva Creation Event

**Trigger**: `tipo_evento = 'create'` AND `modelo = 'reserva'`

**Purpose**: Synchronizes reservation data with external license systems

**Process**:
1. Retrieves reservation and associated services from database
2. Constructs payload with reservation, services, PNR data, and passengers
3. Calculates financial data (venta_total, venta_iva, venta_neta)
4. Queries license database for target system URL
5. Sends data via HTTP POST to license system's `/migrator/witwan` endpoint
6. Marks event as `procesado` on success or `error` on failure

**Data Structure**:
```php
[
    'proveedor' => LICENCIA_ID,
    'reserva' => [...], // Reserva model data
    'servicios' => [
        [
            'venta_total' => float,
            'venta_iva' => float,
            'venta_neta' => float,
            'pnraereo' => [...],
            'pasajeros' => [...]
        ]
    ]
]
```

### 2. Automatic Invoice Event

**Trigger**: `tipo_evento = 'create'` AND `modelo = 'factura_automatica'`

**Purpose**: Generates invoices automatically for reservation services

**Process**:
1. Retrieves all services for the reservation
2. Processes accounting for each service
3. Marks event as `procesado`

**Note**: Full implementation requires integration with accounting/invoicing library

## Usage

### Manual Processing

Process one event:
```bash
php artisan queue:process-events
```

Process multiple events:
```bash
php artisan queue:process-events --limit=10
```

### Scheduled Processing

Add to [app/Console/Kernel.php](../app/Console/Kernel.php):

```php
protected function schedule(Schedule $schedule): void
{
    // Process up to 50 events every minute
    $schedule->command('queue:process-events --limit=50')->everyMinute();
}
```

### Programmatic Usage

```php
use App\Services\QueueEventService;

$queueEventService = app(QueueEventService::class);

// Process pending events
$stats = $queueEventService->processPendingEvents(10);

// Returns: ['processed' => 8, 'failed' => 2, 'skipped' => 0]
```

## Configuration

### Environment Variables

Add to `.env`:

```env
# License Database Connection
LICENSE_DB_DRIVER=mysql
LICENSE_DB_HOST=127.0.0.1
LICENSE_DB_PORT=3306
LICENSE_DB_DATABASE=licenses
LICENSE_DB_USERNAME=root
LICENSE_DB_PASSWORD=secret

# License ID for sync operations
LICENCIA=your_license_id
```

### Database Connection

The license database connection is configured in [config/database.php](../config/database.php):

```php
'license' => [
    'driver' => env('LICENSE_DB_DRIVER', 'mysql'),
    'host' => env('LICENSE_DB_HOST', '127.0.0.1'),
    // ... other settings
]
```

## Error Handling

### Logging

All processing events are logged:
- **Info**: Successful operations
- **Warning**: Unsupported event types
- **Error**: Processing failures with full stack trace

View logs:
```bash
php artisan pail --timeout=0
```

### Event States

- **pendiente**: Event awaiting processing
- **procesado**: Successfully completed
- **error**: Failed processing (requires manual review)

### Retry Logic

Events marked as `error` remain in the queue for manual intervention. To retry:

1. Review logs to identify the issue
2. Fix the underlying problem
3. Update event state back to `pendiente`:

```sql
UPDATE colaevento SET estado = 'pendiente' WHERE colaevento_id = ?;
```

## Creating New Event Types

1. Add event to queue:
```php
use App\Models\Colaevento;

Colaevento::create([
    'tipo_evento' => 'create',
    'modelo' => 'your_model',
    'estado' => 'pendiente',
    'id_relacionado' => $modelId,
    'datos' => json_encode(['key' => 'value'])
]);
```

2. Add handler in [QueueEventService.php](../app/Services/QueueEventService.php):
```php
protected function processCreateEvent(Colaevento $evento): void
{
    switch ($evento->modelo) {
        case 'your_model':
            $this->processYourModelEvent($evento);
            break;
        // ... existing cases
    }
}

protected function processYourModelEvent(Colaevento $evento): void
{
    // Your processing logic
    $this->markEventAsProcessed($evento);
}
```

## Migration from CodeIgniter

This system replaces the CodeIgniter `Procesarcola` controller with the following improvements:

- **Dependency Injection**: Services are injected via constructor
- **Type Safety**: Full PHP type hints and return types
- **HTTP Client**: Laravel's HTTP facade instead of raw cURL
- **Error Handling**: Structured exception handling with detailed logging
- **Testability**: Service layer allows for easy unit testing
- **Modern PHP**: Uses PHP 8+ features and Laravel 12 conventions

## Testing

Create feature tests for event processing:

```php
use Tests\TestCase;
use App\Models\Colaevento;
use App\Services\QueueEventService;

class QueueEventTest extends TestCase
{
    public function test_processes_reserva_event()
    {
        $evento = Colaevento::factory()->create([
            'tipo_evento' => 'create',
            'modelo' => 'reserva',
            'estado' => 'pendiente'
        ]);

        $service = app(QueueEventService::class);
        $stats = $service->processPendingEvents(1);

        $this->assertEquals(1, $stats['processed']);
        $this->assertEquals('procesado', $evento->fresh()->estado);
    }
}
```

## Monitoring

Monitor queue health:

```sql
-- Pending events count
SELECT COUNT(*) FROM colaevento WHERE estado = 'pendiente';

-- Failed events
SELECT * FROM colaevento WHERE estado = 'error' ORDER BY regdate DESC;

-- Processing statistics
SELECT
    estado,
    COUNT(*) as total,
    MIN(regdate) as oldest,
    MAX(regdate) as newest
FROM colaevento
GROUP BY estado;
```

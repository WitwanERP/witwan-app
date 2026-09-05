# Guía de Migración: Nuevo Sistema de Pricing

## Descripción

Este documento describe la migración del método obsoleto `tarifar()` al nuevo sistema de pricing basado en Domain Driven Design, utilizando la arquitectura `Domain/Pricing` existente.

## ✅ Componentes Implementados

### 1. **Nuevas Estrategias de Pricing**

#### Estrategias Específicas por Tipo de Producto:
- **AsistenciaViajeroStrategy** (`ASV`) - Cálculos específicos para asistencia al viajero
- **CupoTicketsStrategy** (`CTK`) - Manejo de cupos de tickets con categorías
- **CupoAereoStrategy** (`CAE`) - Cálculos para cupos aéreos con impuestos
- **HotelPricingStrategy** (`HOT`, `MOT`, `PAQ`, `AEL`, `MSC`) - Hoteles y alojamientos
- **TransferPricingStrategy** (`TRL`, `TRN`) - Traslados y transfers
- **ExcursionPricingStrategy** (`EXC`) - Excursiones

### 2. **Servicio Principal de Reemplazo**

**LegacyTariffReplacementService** - Servicio que reemplaza completamente al método `tarifar()`:
- Mantiene la misma interfaz de entrada
- Utiliza las nuevas estrategias por tipo de producto
- Manejo de errores mejorado
- Cálculos masivos para múltiples productos

### 3. **Nuevos Endpoints API**

```
POST /api/tariff/calculate      - Cálculo individual
POST /api/tariff/calculate-bulk - Cálculo masivo
POST /api/tariff/legacy         - Compatibilidad con método obsoleto
```

### 4. **Repositorios de Infraestructura**

- **EloquentProductRepository** - Acceso a productos
- **EloquentTariffRepository** - Gestión de tarifas
- **EloquentQuotaRepository** - Control de cupos y disponibilidad
- **EloquentCommissionRepository** - Manejo de comisiones y markups

## 🚀 Cómo Usar el Nuevo Sistema

### Uso Básico (Reemplazo Directo)

```php
// ANTES (método obsoleto)
$resultado = $this->tarifar();

// AHORA (nuevo sistema)
use App\Domain\Pricing\Services\LegacyTariffReplacementService;

$tariffService = app(LegacyTariffReplacementService::class);
$resultado = $tariffService->calculateTariff([
    'product_id' => 123,
    'check_in' => '2024-03-15',
    'check_out' => '2024-03-18',
    'adults' => 2,
    'children' => 1,
    'children_ages' => [8],
    'client_id' => 456
]);
```

### Uso de API

```bash
# Cálculo individual
curl -X POST /api/tariff/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 123,
    "check_in": "2024-03-15",
    "check_out": "2024-03-18",
    "adults": 2,
    "children": 1,
    "children_ages": [8],
    "client_id": 456
  }'

# Cálculo masivo
curl -X POST /api/tariff/calculate-bulk \
  -H "Content-Type: application/json" \
  -d '{
    "product_ids": [123, 456, 789],
    "check_in": "2024-03-15",
    "check_out": "2024-03-18",
    "adults": 2,
    "client_id": 456
  }'
```

### Respuesta del Nuevo Sistema

```json
{
  "ok": 1,
  "producto": {
    "id": 123,
    "nombre": "Hotel Plaza",
    "tipo": "HOT"
  },
  "pricing": {
    "base_price": 150.00,
    "total_price": 180.50,
    "currency": "USD",
    "adjustments": [...],
    "discounts": [...],
    "applied_rules": [...]
  },
  "pax_breakdown": {
    "adults": 2,
    "children": 1,
    "infants": 0
  },
  "date_range": {
    "check_in": "2024-03-15",
    "check_out": "2024-03-18",
    "nights": 3
  }
}
```

## 🔄 Proceso de Migración

### Fase 1: Implementación Paralela
1. ✅ **Completado**: Nuevo sistema implementado y funcional
2. ✅ **Completado**: Endpoints API disponibles
3. ✅ **Completado**: Compatibilidad con método obsoleto mantenida

### Fase 2: Migración Gradual
1. **Identificar** todas las llamadas al método `tarifar()` obsoleto
2. **Reemplazar** gradualmente con `LegacyTariffReplacementService`
3. **Probar** cada reemplazo en entorno de desarrollo
4. **Validar** que los resultados sean consistentes

### Fase 3: Deprecación del Método Obsoleto
1. Marcar método `tarifar()` como `@deprecated`
2. Agregar warnings en logs cuando se use
3. Programar eliminación completa

## 📋 Validación y Testing

### Comandos de Verificación

```bash
# Verificar que las dependencias están registradas
php artisan tinker
>>> resolve(App\Domain\Pricing\Services\LegacyTariffReplacementService::class)

# Probar endpoint básico
curl -X POST localhost:8000/api/tariff/calculate \
  -H "Content-Type: application/json" \
  -d '{"product_id": 1, "check_in": "2024-03-15", "check_out": "2024-03-16", "adults": 1}'
```

### Tests de Comparación

```php
// Ejemplo de test para validar consistencia
public function testPricingConsistency()
{
    $legacyResult = $this->legacyTarifar();
    $newResult = $this->newTariffService->calculateTariff($params);

    $this->assertEquals($legacyResult['total'], $newResult['pricing']['total_price']);
}
```

## 🔧 Configuración Avanzada

### Agregar Nueva Estrategia

```php
// 1. Crear nueva estrategia
class CustomProductStrategy implements PricingStrategyInterface
{
    public function calculate(Product $product, PaxConfiguration $pax, DateRange $dates): PriceCalculation
    {
        // Lógica personalizada
    }
}

// 2. Registrar en PricingServiceProvider
$this->pricingService->registerStrategy('CUSTOM', new CustomProductStrategy());
```

### Configurar Repositorios Personalizados

```php
// En PricingServiceProvider
$this->app->bind(TariffRepositoryInterface::class, CustomTariffRepository::class);
```

## 🚨 Puntos Importantes

### Diferencias Clave del Método Obsoleto

1. **Mejor separación de responsabilidades** - Cada tipo de producto tiene su estrategia
2. **Manejo de errores mejorado** - Excepciones específicas y logging
3. **Cálculos más precisos** - Lógica simplificada y testeada
4. **Escalabilidad** - Fácil agregar nuevos tipos de productos
5. **Mantenibilidad** - Código más limpio y documentado

### Consideraciones Especiales

- **ASV**: Maneja cálculos por días con tarifas por edad
- **CTK**: Gestiona múltiples tipos de tickets con disponibilidad
- **CAE**: Incluye impuestos aéreos y coberturas
- **Productos estándar**: Utiliza el sistema de vigencias y tarifas por fecha

## 📞 Soporte

Para dudas o problemas durante la migración:
1. Revisar logs en `storage/logs/laravel.log`
2. Verificar que todos los Service Providers están registrados
3. Validar que las tablas de la base de datos tienen los datos necesarios

## 🎯 Roadmap

- [ ] **Fase 1**: Testing exhaustivo del nuevo sistema
- [ ] **Fase 2**: Migración gradual de controladores existentes
- [ ] **Fase 3**: Eliminación del método obsoleto
- [ ] **Fase 4**: Optimizaciones de performance
- [ ] **Fase 5**: Nuevas funcionalidades exclusivas del nuevo sistema
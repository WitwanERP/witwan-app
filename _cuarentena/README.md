# Cuarentena

Código retirado de `app/` porque no compila o describe algo que no existe, pero
que se conserva para consulta hasta que el reemplazo esté completo.

## pricing (2026-09-04)

Fase 0 de `docs/PRODUCTOS_VIGENCIAS_TARIFAS.md`:

- `app/Domain/Pricing` y `app/Infrastructure/Pricing` (commit `6c5c231`): `Money.php`
  con parse error, repos que consultan columnas inexistentes en el esquema legacy.
- `app/Providers/PricingServiceProvider.php` (ya estaba comentado en `bootstrap/providers.php`).
- `app/Http/Controllers/Api/{Tariff,Quote,TestPricing}Controller.php` y sus rutas
  `tariff/*`, `quote/*`, `pricing/*` de `routes/api.php`.
- `tests/Feature/TariffCalculationTest.php` (en rojo: 401 en todos los casos).
- `PRICING_MIGRATION_GUIDE.md`, `test_mock.php`, `test_pricing.php`, `test_simple.php`.

Nada de esta carpeta se autocarga (composer sólo mapea `app/`). El reemplazo vive
en `app/Services/Productos`, `app/Services/Vigencias`, `app/Services/Tarifarios`,
`app/Services/Cupos` y `app/Services/Pricing`.

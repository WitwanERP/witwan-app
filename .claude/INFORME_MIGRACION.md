# Informe acumulado — migración de vistas CI → /app (Laravel + Inertia)

Origen: proyecto CodeIgniter `witwan-ci-svn/witwan/produccion/application` (copia de producción). Destino: `witwan-app` (Laravel 12, montado en `/app`), misma base del tenant. Cada bloque replica la lógica del controlador/vista del CI; los desvíos están anotados. Este archivo se actualiza al cierre de cada bloque. Detalle técnico completo en `docs/MIGRACION_VISTAS.md`.

Cómo verificar: `php vendor/bin/phpunit` (no `php artisan test`). Build de assets commiteado (`npm run build`). Rama `main`, publicada en `origin/main` al cierre de cada bloque desde el 16.

---

## Bloques cerrados (un commit por bloque)

| # | Bloque | Commit | Qué se portó |
|---|---|---|---|
| 1 | Configuración: ABMs restantes + motor ABM generalizado | 990cbfc | tipos alojamiento, tarjetas, grupos país, regímenes, cadenas cliente/hoteleras, centros costo, tipos habitación, facilidades, tags, formas pago, guías, interfases, puntos interés, adjuntos |
| 2 | Administración: monedas y contabilidad | 6c6f483 | monedas, tipo de cambio, cotizaciones, tabla IVA, plan de cuentas (réplica a hijas), parámetros contables AR/otros, perfiles/modelos de comisión, modelos de fee |
| 3 | Configuración: usuarios y proveedores | 23c4036 | tipos de usuario con matriz de permisos (brain), usuarios con API key, proveedores/prestadores con réplica a colectora |
| 4 | Motor de reportes + primeros reportes | 602c959 | ventas netas, reporte de deuda, productos por origen, cierre de grupo; export CSV |
| 5 | Reportes de administración | bae0d33 | canjes, gastos administrativos/bancarios, honorarios, provisión de deuda, pagos a proveedores, gastos por área, facturas impagas |
| 6 | Operaciones por área | 42695da | autorizar reservas (con extras de servicio), planilla de guardia, tráfico |
| 7 | Documentos en modo lectura | aeb4e8f | facturas, NC, ND, recibos, OP, OS; cotizaciones por área |
| 8 | Cuentas corrientes | 0d70e89, cc7c0a4 | cliente y proveedor (port de `getCuenta`), modo diferencias, CSV |
| 9 | Inicio | f2b5eea | dashboard real con widgets por permiso `inicio_*` |
| 10 | Reportes varios + buscar pax + escritorios | 714976d | servicios sin factura, diferencia de cambio, analítico de ventas, gastos de reserva, OP nacionales; `rel_usuariousuario` |
| 11 | Proveedores y caja | 139bed2 | canjes, pre-compras, crédito de proveedores (recalculo de utilizado); movimientos de fondos |
| 12 | Clientes y pasajeros | 9489066 | sobre las pantallas ya existentes en /app: tags, tarifarios por sistema, extras JSON del pasajero (documentos, domicilios, visas, teléfonos, emails, frecuentes), relaciones recíprocas; menú ruc/rup → /app |
| 13 | Reportes de facturación | ec80352 | control de crédito (crédito extra diario), pendientes de factura, facturados (códigos SII, estado de cobro, renta); acciones POST en listados |
| 14 | Documentación | (junto con 15) | `docs/MIGRACION_VISTAS.md`: motores, mapeo CI→/app, desvíos, lo que queda en el legacy, cómo agregar pantallas |
| 15 | Generador de reservas v1 | ver `git log` | `/app/reservas/{área}/nueva`: file + N servicios con nómina, código atómico, transacción única, totales en servidor, validaciones de dominio, límite de crédito con forzado auditado, validación previa. Análisis en `docs/GENERADOR_RESERVAS.md`. 7 tests |
| 16 | Reservas a facturar / facturación acumulada | ver `git log` | port de `administracion/reportes/afacturar` y `afacturarpp` con flags `facturapracial`/`factura_vertodos`, facturas/NC por servicio y botón FACTURAR al legacy. 3 tests |
| 17 | Tarifador en el generador de reservas | ver `git log` | "Buscar tarifa" por servicio: cotiza productos propios con `App\Services\Pricing\Tarifador` (endpoint propio sin permiso de productos), aplica precio/costo/IVA/impuestos/vencimiento/categoría/régimen, RQ si no hay cupo. 1 test más |
| 18 | Contabilidad: libro diario y libro mayor | ver `git log` | port de `libros::diario` y `libros::mayor` (clasificación debe/haber por documento, conversión de moneda, cuentas de recibos de sysconfig, saldo anterior, exclusión de anulados). 2 tests |
| 19 | Contabilidad: balance de sumas y saldos, libro de ventas y compras (SII) | ver `git log` | port de `libros::balance/ventascl/comprascl`; totalización a cuentas padre, acumulado anterior, códigos SII 30/33/34/55/56/60, tipos de transacción. 3 tests |
| 20 | Contabilidad: IVA venta | ver `git log` | port de `administracion/ivacredito` (UNION facturas/NC/ND con alícuotas 21/10,5, RG 3819/5272, IVA TUR, tipo de cambio USD), agrupado por tipo. 1 test |
| 21 | Balance 8 columnas y estado de cuenta | ver `git log` | port de `administracion/Balance` (árbol de cuentas, deudor/acreedor y columnas patrimoniales, SUMAS/RESULTADO) y de `cuentas/micuenta` (files del cliente con documentos, recibos e hijos). 2 tests |
| 22 | Caja: cartera y arqueo del día; solicitudes de alta | ver `git log` | port de `cartera/lista` (con quitar de cartera), `caja/arqueos` (saldo inicial, movimientos del día, saldo final) y `configuracion/solicitud` (listado; aprobar/rechazar al legacy). 3 tests |
| 23 | Cierre contable | ver `git log` | ABM de `cierrecaja` (port de `libros/cierrecontable`). 1 test |
| 24 | Generador de reservas v2 (asistente de venta) | 683e927 … ver `git log` | Rehecho el front como wizard cliente → buscar → carrito → confirmar sobre el mismo backend de alta. Contexto de venta (tarifario, crédito, historial del cliente), búsqueda por tipo de producto con buscadores por familia (alojamiento, circuitos, tramos, asistencia por días de cobertura), grilla con detalle por habitación, ofertas (fechas cercanas, promos del destino, historial, alternativas), cross-selling por co-ocurrencia histórica, nómina única, recotización en la validación previa. `config/reservas_busqueda.php` declarativo. Detalle en `docs/GENERADOR_RESERVAS.md` §5. 20 tests nuevos |

Suite completa al cierre del bloque 24: **432 tests OK** (`php vendor/bin/phpunit`). Todo publicado en `origin/main`.

---

## En curso

Nada en curso: bloque 24 cerrado. Para el generador quedan PKD/CAE/CTK e interfases XML (nuevos `BuscadorTipo` en `config/reservas_busqueda.php`), cupos, fees/gastos, mails y `colaevento`; recién ahí dar de alta `reserva/nueva/{área}` en `rutas_migradas`. Del resto quedan: analítico de cuentas (con conciliación), rentabilidad (depende de `reserva_model::listar`), reportes `porboletear` / ventas por cliente / por proveedor / gasto e ingreso, cobranzas y pagos (transaccionales), cierre de arqueo, consolidador/BSP, destacados.

---

## Pendiente después

- El deploy es `git pull` en el servidor: al hacerlo, los mapeos nuevos de `config/menu.php` pasan a abrir /app para los usuarios (se puede revertir sección por sección quitando la clave).
- Lo que queda en el legacy (detalle en `docs/MIGRACION_VISTAS.md` §4): cobranzas/pagos/cartera/arqueo, libros contables, rentabilidad, reportes grandes (afacturar, porboletear, ventas por cliente/proveedor, gasto e ingreso), consolidador/BSP, productos por tipo.

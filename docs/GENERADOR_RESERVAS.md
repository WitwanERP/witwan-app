# Generador de reservas — análisis del legacy y v1 en /app

Fecha: 2026-09-06. Legacy: `reserva.php::nueva()` (4467-4995), `resultado()` (3270-4327), `carrito()` (1148-2389), `reservar()` (2389-3260), `reserva_model::set_reserva()`, `servicio_model::set_servicio()`, `crearvacio()`.

## 1. Cómo funciona hoy en el CI

`reserva/nueva/{área}` es un flujo de 4 pasos en sesión de PHP:

1. **nueva()**: elige cliente (filtrado por área: `rel_clientesistema`, o todos en minorista/administración, `clienteminorista=1` en área 3, `consolidador` en área 4), titular (nombre/apellido/país o email), escritorio (en licencias con `rel_usuariousuario`) y markup interno. Calcula la fecha mínima: hoy para internos, +4 días hábiles sin feriados para externos.
2. **resultado()**: busca productos con `tarifar()` (tarifas propias) y con las interfases XML (Fase, HotelBeds, Travel Compositor, UA…), arma disponibilidad y precios en sesión.
3. **carrito()**: acumula servicios elegidos (con nómina de pasajeros por servicio).
4. **reservar()**: crea el file con `set_reserva()` y cada servicio con `set_servicio()`, nómina en `servicio_nomina`, reserva de cupos, extras de reserva (`usuariofinal`, `markupinterno`), llama a los WS para confirmar, `generarfee()` y `actualizartotal()`, manda mails y redirige.

Persistencia relevante:
- `set_reserva()`: `INSERT INTO reserva SET …, codigo = (SELECT MAX(CAST(codigo AS UNSIGNED))+1 FROM reserva WHERE escotizacion=0)`. Además encola `colaevento` cuando el file pasa a CL/auditado y dispara la factura automática.
- `set_servicio()`: completa `cotcosto`/`cotventa` con `cotizarmoneda()` si vienen en 0; calcula `comisionproveedor_valor`.
- `crearvacio()`: file "vacío" con un servicio ANT; status `RQ` y moneda `CLP` en CL, `CO`/`USD` en el resto.
- `actualizartotal()`: recalcula `reserva.total` desde `listar()` (que suma servicios, fees y gastos con conversiones).

## 2. Problemas de integridad detectados en el legacy

| # | Problema | Efecto |
|---|---|---|
| 1 | Código de file por `MAX+1` en la misma sentencia, sin lock ni unique | Dos altas simultáneas generan el mismo código (hay duplicados en producción) |
| 2 | Reserva y servicios se insertan en sentencias sueltas, sin transacción | Si falla un servicio (WS caído, dato inválido) queda un file vacío o incompleto |
| 3 | Totales del file (`total_apagar`, `total_total`, `total_iva`…) vienen del POST del navegador | Cualquier alteración del formulario cambia el total facturable |
| 4 | Casi sin validación de dominio en `reservar()`: cliente, proveedor, fechas y pax se asumen válidos | Files con clientes deshabilitados, fechas pasadas o pax en cero |
| 5 | Límite de crédito sólo se muestra como aviso en el combo (y sólo en tower) | Se reserva por encima del crédito sin traza |
| 6 | `procesandoreserva` en sesión para evitar doble envío, pero nunca se chequea | Doble click = dos reservas |
| 7 | Sin auditoría de quién creó qué (sólo `historialfile` en algunos casos) | Trazabilidad pobre |

## 3. Lo que hace la v1 en /app

Ruta: `/app/reservas/{área}/nueva` (GET form, POST crear, POST `validar` previa). Botón "Nueva reserva" en el listado. **No reemplaza** la entrada de menú `reserva/nueva/{área}`: el flujo con tarifador y WS sigue en el CI.

Alcance: alta manual de file + N servicios con nómina. Se cargan tipo de producto, proveedor, ciudad, fechas, pax, monedas de venta/costo, importes, status, confirmación, comentarios y vencimiento de pago al proveedor.

Persistencia (misma forma que el CI): `reserva` (tipocodigo = `sistema.sistema_codigo`, status `RQ` en CL / `CO` resto, `facturar_a` = cliente, `agente` = escritorio/usuario interno/vendedor del cliente, `promotor` = promotor del cliente, `moneda_factura` = básica o `sysconfig.monedafacturadefault`, `fecha_alta` hoy, `inicio` = menor vigencia), `servicio` (con `cotcosto`/`cotventa`, `renta`, `origen='APP'`), `servicio_nomina`, `historialfile` (campo `alta`) y `auditoria` (`ALTA_GENERADOR`).

Mejoras implementadas (`App\Services\Reservas\GeneradorReservaService`):

1. **Código atómico**: `GET_LOCK('reserva_codigo_<base>')` + `MAX+1` dentro de la transacción + recuento posterior; si quedara duplicado se deshace todo.
2. **Transacción única** para file, servicios, nómina, historial y auditoría.
3. **Totales en el servidor**: `total`, `iva`, `costo`, `ivacosto`, `impuestos` y `renta` del file se calculan desde los servicios (conversión a la moneda del file sólo cuando difiere; renta = venta·cotventa − iva·cotventa − (costo+iva_costo)·cotcosto − impuestos·cotcosto salvo AER, igual que el reporte de facturados).
4. **Validaciones**: cliente habilitado y del área (mismas reglas de combo del CI), proveedor habilitado y no eliminado, tipo de producto activo, monedas existentes, fecha de inicio ≥ fecha mínima (misma regla de +4 hábiles para externos), fin ≥ inicio, pax > 0, importes ≥ 0, status CO/RQ; avisos por nómina mayor que pax y totales en cero.
5. **Límite de crédito**: con `credito_habilitado=1` y límite > 0 se calcula lo utilizado (`CreditoClienteService`, mismo cálculo que control de crédito) y se bloquea si utilizado + reserva > límite. POW o quien tenga `cambiar_limite_credito` puede tildar "crear igual": queda en `auditoria` con el detalle.
6. **Cotizaciones siempre persistidas** (fallback 1 si no hay cotización, como `set_servicio`).
7. **Cotización desde el Tarifador** (`POST /app/reservas/{área}/nueva/cotizar`): mismos parámetros que `/app/productos/{id}/cotizar`, sin exigir permiso de productos; los servicios cotizados quedan con `origen='TAR'` y los manuales con `'APP'`.
8. **Validación previa** sin crear (botón "Validar") y confirmación antes del POST; el botón se bloquea mientras hay un envío en curso (`useEnvio`).

## 4. Lo que no hace la v1 (próximos pasos)

- Interfases XML (Fase, HotelBeds, Travel Compositor, UA…): no se consultan. Los productos propios sí se cotizan con el `Tarifador` de `App\Services\Pricing` (botón "Buscar tarifa" en cada servicio: usa fechas, adultos, edades de menores, residente y el tarifario del cliente; al aplicar precarga nombre, producto, categoría, régimen, monedas, venta, IVA, impuestos, costo, IVA costo y vencimiento de pago; si no hay cupo o está sold out el servicio queda RQ). No descuenta cupo.
- `generarfee()` (fees automáticos por modelo de fee) y gastos de reserva del cliente.
- Cotizaciones (`ctz`/`servicioctz`): la v1 sólo crea reservas.
- Reserva de cupos (`cupo_reservado`), reserva hija (`fk_filepadre_id`), extras `usuariofinal`/`markupinterno`, mails de confirmación, `colaevento`.
- Edición de servicios ya creados (sigue en el CI: `reserva/servicio`).

## 5. v2 (2026-09-07): asistente de venta producto-primero

La v1 quedó como un formulario plano (file + N cards de 25 campos con el tarifador escondido) y se rehízo el front sobre el mismo backend de alta. Filosofía: primero el cliente y el tipo de pasajero (fijan tarifario, markup y vigencias), después el tipo de producto con su búsqueda propia, y sobre eso ofertas, cross-selling y carrito con nómina única.

Pasos (`resources/js/Pages/Reservas/Nueva.vue` + `Generador/*`, estado en `useGenerador.js` persistido en `sessionStorage` por área):

1. **Cliente**: autocomplete remoto filtrado por área (`GET nueva/clientes`, reglas del combo del CI vía `GeneradorReservaService::queryClientes`), residente/extranjero, moneda del file, escritorio/vendedor, titular. `GET nueva/cliente/{id}` (`ContextoVentaService`) trae tarifario (rel_clientesistema con fallback `cliente.fk_tarifario{sistema}_id`), crédito (límite, utilizado, disponible) e historial de 12 meses (últimas reservas con destinos, destinos y tipos más comprados, gasto promedio).
2. **Buscar**: pestañas = tipos de `config/reservas_busqueda.php` con productos habilitados en el sistema (réplica de reserva.php:4634-4736). Cada tipo declara `campos`/`requiere`; `POST nueva/buscar` (`BusquedaRequest` + `BusquedaProductosService`) resuelve el buscador por config:
   - `BuscadorAlojamiento` (HOT/MSC/AEL/MOT): candidatos `producto ⋈ rel_productociudad ⋈ vigencia` (reserva.php:3891-3910, `CandidatosQuery`), una cotización del `Tarifador` por habitación, la fila entra sólo si todas cotizan.
   - `BuscadorPaquete` (PAQ/TRL): una noche, fin = salida + `vigencia.noches`; PAQ sólo circuitos (`solo_circuito`).
   - `BuscadorTramos` (EXC/GUI/TRE/TRN/AUT/CRU): una cotización por producto; TRN por origen/destino; EXC/TRN/GUI con pick-up/drop-off.
   - `BuscadorAsistencia` (ASV): port de la rama propia de tarifar() (tarifa_model:1333-1510): `max_pax` = días de cobertura, `<70`/`>70`, venta = costo ÷ markup sin IVA ni impuestos.
   Todas devuelven la misma fila (`NormalizadorFila`): producto, proveedor, estrellas, disponibilidad CI/RQ/SO, promo, mejor total y `habitaciones[]` con opciones (categoría/régimen, costo, venta, IVA, total, cupo, vencimiento). Tope de candidatos, presupuesto de tiempo (`truncado`), cache 120 s, `ms`/`candidatos` en la respuesta.
   - **Ofertas** (`POST nueva/ofertas`, `OfertasService`): alternativas más baratas de la misma categoría (en el front, sobre la grilla), misma estadía ±3 días (respeta la fecha mínima), promociones del destino (vigencias promocionales y `destacado`) y lo que el cliente pagó por el mismo producto / mismo tipo en la ciudad.
   - **Cross-selling** (`POST nueva/cross-selling`, `CrossSellingService`): al agregar, "Completar el viaje" con los tipos complementarios del config cotizados para las fechas y pax, ordenados por co-ocurrencia histórica en `servicio` (24 meses) y completados con productos del destino.
3. **Carrito**: una línea por habitación (`agregarDesdeResultado`: costo sin IVA neto de promo en moneda de costo, IVA costo, total en moneda de venta, CI→CO / RQ,SO→RQ, vencimiento del tarifador), margen por línea y total en la moneda del file, `total` editable sólo para internos, servicio manual (card reducido con autocompletes), nómina única asignable a servicios por tilde. Aviso si una línea se cotizó hace más de 2 h.
4. **Confirmar**: `validar` corre al entrar; errores bloquean, avisos y crédito ahí mismo (`forzar_credito` si corresponde). La validación **recotiza las líneas tarifadas** (envían `edades` y `tarifario_id`) y avisa si el total cambió o la opción ya no existe. Un solo botón de crear; redirect al listado.

Backend que cambió: `NuevaReservaRequest` (antes reglas inline), `fk_base_id` y `servicio_extra` (pickup/dropoff/hora_pickup) en `filaServicio()`, `Tarifador::cotizar()` acepta `producto` precargado y `tipopax`. Tests: `GeneradorReservaTest`, `BusquedaAlojamientoTest`, `BusquedaTiposTest`, `OfertasTest`, `CrossSellingTest`.

Sigue en el CI hasta cubrirlo: PKD/CAE/CTK (paquetes dinámicos, cupos aéreos, entradas), interfases XML (HotelBeds, RIU, Roombeast…), cupos (`cupo_reservado`), fees automáticos y gastos de reserva, cotizaciones (ctz), mails y `colaevento`. Cuando el tenant no dependa de eso, dar de alta `reserva/nueva/{área}` en `config/menu.php` → `rutas_migradas`.

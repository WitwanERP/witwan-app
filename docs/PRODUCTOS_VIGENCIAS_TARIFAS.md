# Carga de productos, vigencias y tarifas — análisis del legacy y propuesta en Laravel

Fecha: 2026-09-04. Legacy analizado: `witwan-ci-svn/witwan/produccion/application` (CI 2.2). Destino: este repo (`witwan-app`, Laravel 12, montado en `/app`).

Las dos apps conviven en producción sobre la **misma base de tenant**. Todo lo que Laravel escriba en `producto`/`vigencia`/`tarifa` lo sigue leyendo el `tarifar()` del CI para reservar. Por eso la propuesta cambia *cómo se carga* sin cambiar *qué se persiste*.

---

## 1. Cómo funciona hoy en el CI

### 1.1 Modelo de datos (tablas legacy, sin FKs reales)

```
producto ──< vigencia ──< tarifa
   │            ├──< rel_vigenciadia        (días de semana; redundante con vigencia.weekdays)
   │            └──< vigenciaalojamiento    (PAQ: hotel + categoría + régimen + noches incluidos)
   ├──< alojamientohabitacion   (categoría instanciada en el producto, con capacidades)
   ├──< producto_extra          (EAV: nombre/valor, todo lo que no cabe en producto)
   ├──< rel_productociudad / rel_productobase / rel_productoalojamientofacilidad / productogaleria
   ├──< cupo                    (allotment por fecha/categoría/bases/cliente/tarifario, release, freesale)
   └──< soldout                 (bloqueo: UNA FILA POR DÍA por categoría)

tarifario ──< tarifariocomision   (divisor_markup + porcentaje_comision por scope submódulo/país/ciudad/producto)
          ──< tarifarioarchivo    (adjuntos)
tarifacategoria                   (catálogo global de "habitaciones/categorías" por tipo de producto)
iva                               (cascada de scope igual que tarifariocomision)
```

Semántica clave de `tarifa`:

| Columna | Significado |
|---|---|
| `fk_tarifario_id = 0` | **costo** (lo que carga el operador) |
| `fk_tarifario_id != 0` | precio de **venta cargado a mano** para ese tarifario (solo si `vigencia.cargamanual = 1`) |
| `fk_base_id` | alojamiento: `1,2,3…` = cantidad de adultos (single/doble/triple); pseudo-bases `INF`, `MN`, `MN2`, `M2`, `M22`, `JNR` para menores |
| `min_pax`/`max_pax` + `fk_tipopax_id` | no alojamiento: tramos por cantidad de pax y tipo (`ADU`,`CHD`,`INF`,`SNR`,`JNR`,`<70`,`>70`) |
| `moneda_costo`, `impuestos`, `redondear` | conceptualmente de la vigencia, pero **replicados en cada fila** |
| UNIQUE `vigenciaunica` | `(fk_vigencia_id, fk_tarifario_id, fk_base_id, min_pax, max_pax, fk_tipopax_id, fk_tarifacategoria_id)` → por eso todo el legacy usa `REPLACE INTO` |

Producto tiene además `modotarifa` (`P` por persona, `S` servicio, `H` habitación, `R` prorrateado), `disponibilidad` (`CI`/`RQ`), `fk_sistema_id` (1 receptivo, 2 mayorista, 3 minorista, 4 consolidador, 7 nacional) y `fk_tipoproducto_id` (`HOT EXC PAQ TRN TRE TRL AEL AUT MOT CRU GUI ASV MSC CAE CTK`). Moneda e IVA **no** viven en producto: moneda va por tarifa/tarifario, IVA se resuelve por cascada en `iva`.

Las edades de corte (`edad_infoa`, `edad_menor1`, `edad_menor2`, `edad_junior`, `edad_senior`) viven en `producto_extra` y son las que **generan las columnas de la grilla de tarifas** (ver 1.3).

### 1.2 Alta / edición de producto

- 18 controladores en `controllers/productos/*.php` que son copias literales (cambian `tpro`, título y URLs). Sin clase base. Sin `_check_perm()`.
- El formulario se define en `Producto_model::get_estructura($tipo)` (`producto_model.php:22-599`): array PHP de campos con `type`, `field`, `label`, `sistema` (visibilidad por sistema) y `options.savetable` (`producto` vs `producto_extra`).
- `save()` (`productos/hotel.php:129-306`): arma el `INSERT/UPDATE producto SET` concatenando el nombre de columna desde la metadata y el valor con `addslashes`; luego borra y reinserta `producto_extra`, `rel_productobase`, `rel_productoalojamientofacilidad`, `rel_productociudad` (en hotel: **una sola ciudad**). Sin transacción.
- Habitaciones (`alojamientohabitacion`) se editan aparte por AJAX en `producto.php:935` (`guardarhabitacion`), inputs con nombre `campo_ID`.
- `copy()` solo precarga el formulario con `id=0`; **no copia vigencias/tarifas**. `duplicarproducto.php` es un script one-shot receptivo→nacional, no una función de usuario.
- `delete()` deja huérfanos en `alojamientohabitacion`, `cupo`, `soldout`, `rel_*`, `vigenciaalojamiento`, `rel_vigenciadia`, y hace un `DELETE FROM tarifa WHERE fk_vigencia_id NOT IN (SELECT …)` global.

### 1.3 Vigencias y tarifas (`controllers/vigencia.php`)

Una vigencia = período tarifario: fechas de estadía, fechas de venta, días de semana, noches mínimas, residente (`R`/`O`/todos), régimen, promoción (`promo_noches` × `promo_pornoches`, `acumulable`), vencimientos, `cargamanual`.

`save()` (`vigencia.php:159-517`) hace `REPLACE INTO vigencia`, luego `rel_vigenciadia`, `vigenciaalojamiento`, y las tarifas en dos ramas:

- **Alojamiento** (`HOT AEL MOT MSC PAQ TRL`): habitaciones × bases. Las bases se derivan en runtime (`vigencia.php:276-320`): numéricas desde `producto.bases`; `INF` si `edad_infoa>0`; `MN`,`MN2`… según `max_child` y `edad_menor1>0`; `M2`,`M22`… si `edad_menor2>0`; `JNR` si `edad_junior>0`. Para PAQ se fuerza `max_child=1`. Input `costo_{categoria}_{base}` → `REPLACE INTO tarifa … fk_tarifario_id=0`. Si `manual=1`, además `valor_{categoria}_{base}_{tarifario}` → filas con `fk_tarifario_id={tarifario}` y moneda del tarifario.
- **Resto** (`EXC GUI TRN ASV CRU TRE …`): tramos `keytar[]` con `min_/max_` y un costo por tipo de pax (`ADU CHD INF`; TRE suma `JNR SNR`; ASV usa `<70`/`>70`).

El precio de venta se calcula en runtime en `Tarifa_model::tarifar()` (`tarifa_model.php:1101-3340`, ~2.240 líneas en un método). Fórmula documentada en la cabecera del archivo:

```
costo (+ iva_costo, − promo) × cotización            → costo en moneda de venta
costo_en_moneda / divisor_markup (redondeo)          → venta   (salvo carga manual: se toma el valor cargado, sin markup)
venta × porcentaje_comision                          → comisión
IVA venta sobre la renta (modo 2/3) o sobre venta    → iva
+ percepciones sistema.extra1/extra2 + gastos        → total ; total − comisión = a pagar
```

La misma fórmula de markup está **duplicada en JS** (`assets/admin/js/vigencia.js:37-91`, botón "calcular venta"), sin comisión ni gastos.

### 1.4 Tarifarios y comisiones (`controllers/tarifario.php`)

`_insert_record()` (`:335-449`) guarda el tarifario y hace `DELETE FROM tarifariocomision WHERE fk_tarifario_id=X` + reinserción completa (se pierden los ids en cada guardado). La clave de fila del form es `"{submodulo}_{pais}_{ciudad}_{producto}_{origen}"` parseada con `explode`. `vista()` (`:534-1314`) es la **exportación** a Excel/HTML del tarifario; **no existe importación** de tarifas desde Excel, solo adjuntos.

### 1.5 Cupos y soldout (`controllers/producto.php`)

`guardarcupo()` inserta una fila; `guardarsoldout()` inserta **una fila por día**; `bloqueo()` togglea un día desde el calendario; `vercupo()` arma el calendario con 3 queries por día (~90 por mes). En `tarifar()` el cupo se consulta por día con `FIND_IN_SET(base, bases)` y `release`; freesale suma 10 unidades ficticias; las reservas tomadas restan 1 por servicio sin ponderar pax ni filtrar por cliente; `disponibilidad='CI'` fuerza `cupo=1`.

---

## 2. Problemas concretos encontrados

### 2.1 Bugs que afectan datos hoy (verificados en código)

| # | Dónde | Qué pasa |
|---|---|---|
| B1 | `vigencia.php:324` | `intval($hab['fk_tarifacategoria_id']." AND fk_base_id='".$base."'")` — el `AND` queda **dentro** del `intval()`. Al vaciar un costo se ejecuta `DELETE … fk_tarifacategoria_id=N` sin filtro de base: **borra todas las bases de la categoría**. |
| B2 | `vigencia.php:271` | `DELETE FROM tarifa WHERE fk_vigencia_id=$post['vigencia_id'] AND fk_tarifario_id!=0` usa el id del POST, no el nuevo. |
| B3 | `vigencia.php:163` | `REPLACE INTO vigencia` sobre la PK: en cada edición se borra y reinserta la fila, perdiendo silenciosamente columnas que el form no manda (`clase`, `infoadicional`, `promocional`, `cotizacionespecial`, `fk_tarifario_id`, `comentarios_en/pt`). |
| B4 | `vigencia.php:392` | `for($a=0;$a<15;$a++)` con `$a` sin usar: el bloque de tarifas no-alojamiento se ejecuta 15 veces. |
| B5 | `producto_model.php:648` vs `tarifa_model.php:2181` | El form lee días desde `rel_vigenciadia` (pisa `weekdays`); el tarifador filtra por `weekdays`. Dos fuentes de verdad. |
| B6 | `producto_model.php:867` vs `tarifa_model.php:2703` | Las dos cascadas de IVA ordenan distinto → pueden resolver IVAs distintos para el mismo producto. |
| B7 | `tarifa_model.php:1262-1267` | `ORDER BY … fk_pais_id DESC, fk_ciudad_id DESC, fk_pais_id DESC`: país pesa más que ciudad y está duplicado. |
| B8 | `tarifariocomision.vigencia_ini/fin` | Se graban pero **ningún query de pricing las filtra**. |
| B9 | `tarifa_model.php:2722-2731` | El SELECT de `gastoproveedor` está comentado; las variables siguen en la fórmula (siempre 0). |
| B10 | `producto.php:510,518` | `DELETE … IN (join(",", $post['ids']))` sin `intval` — inyección SQL directa. Además interpolación sin escape en `vigencia.php:205-232, 335-428` y `tarifario.php:363-447`. |

### 2.2 Deuda estructural

- Sin validación de entrada: fechas inválidas → `1970-01-01`; nada impide `fin < ini`, costos negativos ni solapes entre vigencias de igual prioridad.
- Sin transacciones: decenas de `DELETE`/`REPLACE` secuenciales; un fallo deja la vigencia sin tarifas.
- Redundancias: `weekdays` vs `rel_vigenciadia`; `producto_extra.bases` vs `rel_productobase`; moneda/impuestos/redondeo por fila de tarifa.
- `producto_extra` sin PK/UNIQUE pero usada con `REPLACE INTO` (degenera en INSERT).
- N+1 masivo en `tarifar()` (~10 queries × habitación × día) y en el calendario de cupos.
- Lógica por licencia esparcida (`witwan_expert`, `tower`, `grh`, `greatchile`, `hb`, `med`, `mundotour_sdg`) dentro del cálculo.
- Metadata de formularios hardcodeada en PHP; HTML generado desde PHP (`addbase`, `addline`).

---

## 3. Estado en witwan-app

Lo que hay hoy, verificado:

- **`app/Services/CotizacionService.php` es cotización de monedas** (tipo de cambio), no de productos. Funciona y es útil para el tarifador.
- **`app/Domain/Pricing` + `app/Infrastructure/Pricing` está muerto y no compila**: `php -l Money.php` → parse error en línea 85; `PricingServiceProvider` comentado en `bootstrap/providers.php:5`; ~14 incoherencias (métodos inexistentes, enums sin casos, repos que consultan columnas `activo`, `fk_ciudad_id`, `markup_porcentaje` que no existen en el esquema legacy). Un solo commit (`6c5c231`), nunca retocado. `tests/Feature/TariffCalculationTest.php` está en rojo. `PRICING_MIGRATION_GUIDE.md` describe un sistema que no existe.
- Lo único que responde es `SimpleTariffService` vía `POST /api/pricing/test`, con un `echo` de debug adentro y sin cupos, residente, bases ni noches mínimas.
- CRUD API de `productos`, `vigencias`, `tarifas`, `tarifarios`, `cupos`, `sold-out` = scaffolds Reliese idénticos, sin reglas de validación (aceptan cualquier payload sobre el `$fillable`).
- **Cero pantallas Inertia** de productos. El patrón vivo del repo es: `app/Services/**` planos + `Web/**` Inertia + Form Requests + `config/*.php` declarativo + tests unitarios con fixtures JSON citando el CI (`FacturaproveedorCalculo` es el modelo). ABMs simples salen de `Web/Abm/AbmController`.
- Modelos Eloquent con relaciones: `Producto`, `Vigencium` (tabla `vigencia`), `Tarifa`, `Tarifario`, `Tarifariocomision`, `Cupo`, `Soldout`, `Tarifacategorium`, `Base`. Sin relaciones: `Vigenciaalojamiento`, `RelVigenciadium`, `RelProductobase` (sin `$fillable`), `RelProductociudad` (`$fillable` solo `tipo`).

**Conclusión:** no construir sobre `Domain/Pricing`. Seguir el patrón `Services + Web/Inertia` que ya funciona en Clientes, Facturas de proveedor y Asientos.

---

## 4. Propuesta

### 4.1 Principios

1. **Compatibilidad binaria con el CI.** Mismas tablas, mismas semánticas (`fk_tarifario_id=0` = costo, bases derivadas de edades, soldout por día, `weekdays` **y** `rel_vigenciadia` escritos juntos). Nada de migraciones destructivas mientras el CI reserve.
2. **Una sola fuente de verdad por regla.** Derivación de bases, fórmula de venta y validaciones viven en Services PHP puros con tests; el front las consume por Inertia (preview de venta por request, no fórmula en JS).
3. **Config declarativa en vez de 18 controladores.** Un `config/productos.php` con la definición por tipo (puerto de `get_estructura()`), un solo `ProductoController`.
4. **Escrituras transaccionales y por diferencia.** Upsert sobre la unique `vigenciaunica`, borrar solo lo que dejó de estar en el payload. Nunca `REPLACE` sobre PK.
5. **Permisos por sección** con `PermisoHelper` + `config/secciones.php`, replicando las secciones del CI (`productos/hotel`, `vigencia`, `tarifario`, `producto/vercupo`).

### 4.2 Estructura propuesta

```
config/productos.php                       tipos, campos por tipo, savetable, visibilidad por sistema, tipos de pax
config/secciones.php                       overrides de sección para productos/vigencias/tarifarios/cupos

app/Services/Productos/
  ProductoService.php                      listar (filtros fieles al CI), cargar, guardar (transacción), clonar, eliminar
  ProductoFormulario.php                   lee config/productos.php → campos + opciones para el tipo/sistema
  HabitacionService.php                    alojamientohabitacion (alta/orden/habilitar, bloqueo si tiene tarifas)
  ProductoExtraService.php                 EAV: set/unset por clave, sin REPLACE
app/Services/Vigencias/
  VigenciaService.php                      cargar, guardar (transacción), clonar (con tarifas, días, alojamientos), eliminar
  GrillaTarifas.php                        arma la grilla: (categorías × bases) o (tramos × tipos de pax)
  BasesResolver.php                        PURO: bases a partir de producto.bases + edades + max_child (puerto de vigencia.php:276-320)
  TarifaUpsert.php                         diff + upsert por vigenciaunica; escribe moneda/impuestos/redondeo por fila
  VigenciaReglas.php                       validaciones de negocio (fechas, venta, solapes por prioridad, noches, días)
app/Services/Tarifarios/
  TarifarioService.php                     tarifario + adjuntos
  TarifarioComisionService.php             filas de markup/comisión por scope, actualización por diferencia (conserva ids)
app/Services/Cupos/
  CupoService.php                          cupos y soldout (alta por rango → filas/día), calendario mensual en 3 queries
app/Services/Pricing/
  Tarifador.php                            puerto de tarifa_model::tarifar() (fase 4)
  MarkupCalculadora.php                    PURO: costo → venta (iva costo, cotización, divisor, redondeo) — reemplaza vigencia.js
  ScopeResolver.php                        cascada producto > ciudad > país > submódulo > general (tarifariocomision e iva, un solo ORDER BY)

app/Http/Requests/Productos/
  ProductoRequest.php                      reglas base + reglas por tipo desde config
  VigenciaRequest.php                      cabecera + grilla (costos numéricos ≥ 0, manual/tarifario)
  TarifarioRequest.php, CupoRequest.php, SoldoutRequest.php
app/Http/Controllers/Web/Productos/
  ProductoController.php                   /app/productos/{sistema}/{tipo}[/create|/{id}/edit|/{id}/clonar]
  VigenciaController.php                   /app/productos/{id}/vigencias[/create|/{vid}/edit|/{vid}/clonar] + POST preview-venta
  HabitacionController.php                 /app/productos/{id}/habitaciones (JSON para el form)
  TarifarioController.php                  /app/tarifarios/{sistema}
  CupoController.php                       /app/productos/{id}/cupos (calendario) + soldout
resources/js/Pages/Productos/
  Index.vue, Form.vue (secciones: Datos, Habitaciones, Galería, Vigencias)
  Vigencia/Form.vue (cabecera + GrillaAlojamiento.vue | GrillaTramos.vue + columnas de venta manual por tarifario)
  Cupos/Calendario.vue
tests/Unit/Vigencias/  BasesResolverTest, VigenciaReglasTest, TarifaUpsertTest (SQL con CompilaSqlDeMysql)
tests/Unit/Pricing/    MarkupCalculadoraTest, TarifadorTest (fixtures JSON generados desde el CI)
```

### 4.3 Producto: qué mejora

- **Un controller para todos los tipos.** `config/productos.php` declara por tipo: campos (`producto` vs `producto_extra`), si tiene habitaciones, si usa bases, tipos de pax de la grilla, ciudades (única o múltiples, con `tipo` D/O), visibilidad por sistema. La UI se genera desde esa config (mismo enfoque que `Abm/Form.vue`, extendido).
- **Guardado transaccional** en `ProductoService::guardar()`: producto → extras por diferencia (delete por clave + insert, sin REPLACE) → ciudades/bases/facilidades por diferencia → galería.
- **Habitaciones en el mismo form** (no AJAX suelto), con la regla "no se puede deshabilitar/borrar si tiene tarifas" evaluada en el service.
- **Clonar de verdad** (`clonar` copia producto + extras + relaciones + habitaciones + galería, y opcionalmente vigencias futuras con sus tarifas), reemplazando el `copy()` que solo precarga el form.
- **Baja lógica** usando `producto.eliminar=1` (el CI ya filtra `eliminar=0` en el tarifario) en vez del `DELETE` en cascada incompleto.
- Validación: nombre, proveedor, ciudad(es), bases coherentes con el tipo, edades crecientes (`infoa < menor1 < menor2 < junior`), `modotarifa` dentro de los permitidos por tipo.

### 4.4 Vigencias y tarifas: qué mejora

- **Una pantalla, una grilla, una regla.** `GrillaTarifas` arma las filas/columnas en el server (categorías × bases o tramos × tipos de pax) usando `BasesResolver`; el Vue solo pinta y edita celdas. Se elimina la derivación duplicada en la vista PHP y en JS.
- **Guardado por diferencia con upsert** sobre `vigenciaunica`: celda con valor → upsert; celda vaciada → delete de **esa** celda (corrige B1); filas de venta manual se borran solo si `cargamanual` pasó de 1 a 0. `UPDATE` sobre la vigencia existente (corrige B3); id nuevo siempre desde el insert (corrige B2).
- **Escribe `weekdays` y `rel_vigenciadia` juntos**, desde el mismo array (corrige B5 hacia adelante; los datos históricos se reconcilian con un comando `vigencias:reconciliar-dias` que reporta diferencias antes de tocar nada).
- **Validaciones** (`VigenciaReglas`): `vigencia_fin >= vigencia_ini`; `ventafin >= ventaini`; al menos un día de semana; `noches_minimas` coherente con `modo`; costos numéricos ≥ 0; moneda obligatoria; **aviso** (no bloqueo) de solape con otra vigencia del mismo producto, categoría y prioridad, mostrando cuál gana.
- **Preview de venta** con `MarkupCalculadora` (costo + iva costo + cotización de `CotizacionService` + divisor del `ScopeResolver`), mismo código que usará el tarifador. Reemplaza el botón "calcular venta" de `vigencia.js`.
- **Clonar vigencia** en el server: copia cabecera, días, alojamientos y tarifas, con opciones "desplazar fechas N días/meses" y "ajustar costos ±%". Es la operación más repetida al cargar temporadas y hoy es manual celda por celda.
- **Carga masiva** (fase 4): plantilla Excel por producto (categorías × bases × vigencias), importación con previsualización y validación por celda. El legacy solo exporta.

### 4.5 Tarifarios, comisiones, cupos

- `TarifarioComisionService` actualiza por diferencia usando `tarifariocomision_id` (conserva ids e historia), valida unicidad del scope y que `divisor_markup > 0`.
- `ScopeResolver` unifica la cascada (producto > ciudad > país > submódulo > general) para `tarifariocomision` e `iva`, con un único ORDER BY (corrige B6/B7). Decisión pendiente: si `tarifariocomision.vigencia_ini/fin` debe filtrar (B8).
- `CupoService`: alta de soldout por rango sigue generando una fila por día (el CI lo lee así), pero en una transacción; calendario mensual con tres queries por mes; borrado masivo con ids validados (corrige B10).

### 4.6 Contrato de compatibilidad con el CI (no romper)

| Regla | Motivo |
|---|---|
| `tarifa.fk_tarifario_id = 0` es costo; `!= 0` es venta manual y solo existe si `vigencia.cargamanual = 1` | `tarifar()` `:2951-3010` y `tarifario.php:726` |
| Bases exactamente como las deriva `vigencia.php:276-320` (`MN`, `MN2`, `M2`, `M22`, `INF`, `JNR`; PAQ con `max_child=1`) | el tarifador busca la base por edad `:2503-2534` |
| `moneda_costo`, `impuestos`, `redondear` en **cada** fila de tarifa; `impuestos_menor` en bases no numéricas | `vigenciabyid()` `:691-703` |
| `weekdays` como bit(7) lunes→domingo **y** `rel_vigenciadia` | B5 |
| `soldout` una fila por día | `tarifar()` `:2766` |
| `producto_extra` con las mismas claves (`edad_*`, `zona`, `politica_*`, `aparece_excel`…) | `byid()` las mapea al item |
| `producto.eliminar` para baja lógica, `habilitar` para visibilidad | `tarifario.php:692-720` |
| Rutas Laravel bajo `/app/...`; links desde el menú del CI vía `config/menu.php` | ver memoria del proxy |

### 4.7 Fases

| Fase | Entrega | Depende de |
|---|---|---|
| 0 | Cuarentena de `app/Domain/Pricing`, `app/Infrastructure/Pricing`, `Api/{Tariff,Quote,TestPricing}Controller`, `test_*.php`, `PRICING_MIGRATION_GUIDE.md`; sacar de `routes/api.php`; dejar los tests en verde | — |
| 1 | `config/productos.php` + `ProductoService` + `ProductoController` + `Productos/Index.vue`/`Form.vue` (datos, habitaciones, galería) + permisos. Los tipos entran de a uno: HOT primero (más campos), después EXC/TRN/PAQ, resto | 0 |
| 2 | `BasesResolver` (con tests contra casos reales del CI), `GrillaTarifas`, `VigenciaService` + `TarifaUpsert` + `VigenciaReglas`, `Vigencia/Form.vue`, clonar vigencia. Comando de reconciliación de días | 1 |
| 3 | Tarifarios + comisiones (ABM con `ScopeResolver`), cupos/soldout con calendario | 1 |
| 4 | `MarkupCalculadora` + preview de venta; `Tarifador` (puerto de `tarifar()`) validado con fixtures generados desde el CI para los mismos inputs; importación Excel | 2, 3 |

Cada fase deja al CI funcionando: mientras Laravel no tenga una pantalla, el menú sigue apuntando al legacy.

### 4.8 Decisiones que necesita el negocio

1. `tarifariocomision.vigencia_ini/fin`: ¿deben filtrar el markup por fecha (hoy no lo hacen)?
2. `weekdays` vs `rel_vigenciadia`: ¿cuál es la verdad para los datos históricos que difieren? (el comando de reconciliación va a listar los casos).
3. El bug B1 puede haber borrado bases de tarifas en producción: ¿se audita `tarifa` contra las categorías/bases esperadas antes de migrar?
4. Diferencias por licencia dentro de `tarifar()` (expert, tower, grh, greatchile, hb, med, mundotour_sdg): ¿se mantienen todas o se consolidan en `config/pricing.php` por tenant?
5. Cupo consumido por reservas: ¿se sigue restando 1 por servicio o se pondera por habitaciones/pax?

---

## 5. Estado de implementación (2026-09-05)

Backend, config y tests de las fases 0 a 3 y el preview de venta de la fase 4 están hechos; las páginas Vue, el `Tarifador` completo y la importación Excel quedan pendientes. Informe detallado con endpoints, payloads, respuestas y tests: artifact "Productos y Tarifas en Laravel" (ver también `tests/Feature/Productos` y `tests/Unit/{Productos,Vigencias,Pricing}`).

| Pieza | Archivo |
|---|---|
| Config declarativa | `config/productos.php` |
| Services | `app/Services/Productos/*`, `app/Services/Vigencias/*`, `app/Services/Tarifarios/*`, `app/Services/Cupos/CupoService.php`, `app/Services/Pricing/{MarkupCalculadora,ScopeResolver,VentaPreview}.php` |
| Días de semana | `app/Support/Productos/DiasSemana.php` |
| Requests | `app/Http/Requests/Productos/*` |
| Controllers | `app/Http/Controllers/Web/Productos/*` (rutas en `routes/web.php`) |
| Comando | `php artisan vigencias:reconciliar-dias [--aplicar --fuente=weekdays|rel] [--producto=N]` |
| Cuarentena | `_cuarentena/pricing/` (fase 0) |
| Tests con base | MariaDB local `witwan_test` (ver `phpunit.xml`); esquema en `tests/Concerns/CreaEsquemaProductos.php` |

---

## 5. Estado de implementación (2026-09-05)

Commits en `witwan-app`: `aceec7b` (fase 0), `cea09e2` (backend + tests), `86de36d` (pantallas Inertia), y el del Tarifador. Suite: 300 tests en verde contra MariaDB `witwan_test` (`phpunit.xml`; el esquema legacy lo crea `tests/Concerns/CreaEsquemaProductos`).

| Fase | Estado | Dónde |
|---|---|---|
| 0 Cuarentena | hecha | `_cuarentena/pricing/` |
| 1 Productos | hecha (backend + `Productos/Index.vue`, `Form.vue`) | `Services/Productos`, `Web/Productos/ProductoController`, `config/productos.php` |
| 2 Vigencias | hecha (backend + `Vigencia/Form.vue` con grillas, clonar, `vigencias:reconciliar-dias --host=`) | `Services/Vigencias` |
| 3 Tarifarios y cupos | hecha (backend + `Tarifarios/*.vue`, `Cupos/Calendario.vue`) | `Services/Tarifarios`, `Services/Cupos` |
| 4 Pricing | `MarkupCalculadora`, `ScopeResolver`, `VentaPreview` y `Tarifador` (port parcial, endpoint `POST /app/productos/{id}/cotizar`). Falta validar contra fixtures del CI e importación Excel | `Services/Pricing` |

Decisiones §4.8 resueltas o con datos:
- 4.8.2: `vigencias:reconciliar-dias --host=rays.witwan.com` no encontró diferencias entre `weekdays` y `rel_vigenciadia` en `witwan_rays`. Correr en los demás tenants antes de activar el menú.
- Aviso de solape a igual prioridad: el CI se queda con la última fila del `ORDER BY prioridad DESC, costo ASC` (la de mayor costo), no con la más barata. `VigenciaReglas` lo dice así y el `Tarifador` lo replica.

Pendiente: activar `rutas_migradas` en `config/menu.php` (están comentadas), fixtures del CI para el `Tarifador`, ramas por licencia (4.8.4), upload de adjuntos de tarifario y galería (hoy siguen en el CI), importación Excel.

# Migración de vistas del CI a /app — estado, mapeo y desvíos

Fecha: 2026-09-06. Legacy: `witwan-ci-svn/witwan/produccion/application` (CI 2.2). Destino: este repo (Laravel 12 + Inertia/Vue, montado en `/app`).

Las dos apps conviven sobre la **misma base del tenant**. Cada pantalla portada lee y escribe las mismas tablas que el CI, con la misma lógica salvo lo que se lista en "Desvíos". La botonera del CI sigue siendo la de `brain.seccion`: una sección pasa a abrir `/app` cuando su `seccion_uri` aparece en `config/menu.php` → `rutas_migradas` (`MenuService::url()`). Quitar una clave de ahí devuelve a los usuarios al legacy sin tocar nada más.

---

## 1. Motores reutilizables

| Motor | Dónde | Para qué |
|---|---|---|
| ABM config-driven | `App\Http\Controllers\Web\Abm\AbmController` + `Pages/Abm/{Index,Form}.vue` | Tablas simples de configuración/administración: columnas, filtros, campos (text/number/decimal/select/radio/checkbox/textarea/date), selects dependientes, hooks `antesDeGuardar`/`despuesDeGuardar`/`despuesDeEliminar`, acciones extra (links o POST con confirmación). Rutas con el helper `$abm($slug, $controller, $pkNumerica)` en `routes/web.php`. |
| Reportes | `App\Http\Controllers\Web\Reportes\ReporteController` + `Pages/Reportes/Listado.vue` | Listados con filtros (text/select/multi/rango/date/bool), agrupación por moneda, totales, columna de acciones (links o POST con prompt/confirmación), acciones globales, corte por `$limite` en pantalla y export CSV (`;`, BOM) completo. Rutas con `$reporte($slug, $controller)` (GET slug y slug/export). |
| Listados de documentos | `Web\Documentos\Listados\DocumentoListadoController` | Reporte especializado: coeficiente de IVA general, decimales por país, rango por defecto de 3 meses, límite 500. |
| Operaciones | `Web\Operaciones\OperacionesController` | Base de autorizar/guardia/tráfico por área (mapa de áreas → `fk_sistema_id`, catálogos, comentarios base64). |
| Filas repetibles / campos | `Components/FilasRepetibles.vue` | Tablas editables (contactos, documentos, teléfonos…) que se guardan como JSON en `*_extra`. |
| Servicios de apoyo | `Services/CatalogosService`, `Services/TablaLegacyService`, `Services/CotizacionService`, `Services/Admin/ReplicadorColectora`, `Services/Empresas/{ExtrasService,RelacionesService}` | Combos, CRUD sobre tablas legacy sin modelo, cotizaciones al costo/venta, réplica a bases hijas de una colectora, extras JSON y relaciones recíprocas. |

Tests: cada lote tiene su Feature test sobre el esquema real en MySQL (`tests/Concerns/CreaEsquemaConfiguracion.php` crea ~80 tablas legacy con sus columnas). Correr con `php vendor/bin/phpunit` (no `php artisan test`, que hereda el `SESSION_DRIVER` del `.env`).

---

## 2. Pantallas portadas (sección del CI → /app)

### Configuración
| CI | /app | Notas |
|---|---|---|
| configuracion/{region,pais,ciudad} | /app/geo/… | previas |
| configuracion/{negocios,proyecto,programafidelidad,tipoclavefiscal,banco,feriados,aerolinea} | /app/config/… | previas |
| configuracion/{puntos,grupopais,tarjetacredito,tag,formasdepago,Centrocosto,cadenacliente,interfases,filearchivo,alojamientotipo,habitaciontipo,facilidad,cadenahotelera,regimen,guia} | /app/config/… | ABM config-driven |
| configuracion/tipousuario | /app/config/tipos-usuario | matriz de permisos por sección (brain) + copiar de otro tipo |
| configuracion/usuario | /app/config/usuarios | alta/edición, API key |
| configuracion/proveedor, configuracion/Prestador | /app/config/proveedores, /app/config/prestadores | réplica a colectora, CUIT válido en AR |
| configuracion/ruc, configuracion/rup | /app/clientes, /app/pasajeros | ya existían; se les agregó tags, tarifarios por sistema (`rel_clientesistema`), extras JSON del pasajero y relaciones recíprocas |
| administracion/escritorios | /app/config/escritorios | `rel_usuariousuario`, sólo POW, escritura gobernada por `sysconfig.escritorios_abm` |
| dashboard/buscarpax | /app/reservas-buscar | |

### Administración
| CI | /app | Notas |
|---|---|---|
| administracion/moneda, cambio/ultimas, tablaiva, plancuenta, Parametros, usuariocomision, Modelocomision, Modelofee | /app/admin/… | plan de cuentas con réplica a hijas; parámetros contables AR/otros |
| administracion/{factura,notacredito,notadebito,recibo,ordenpago,ordenservicio,mfondos} | /app/documentos/… | modo lectura; imprimir/anular/editar linkean al legacy |
| administracion/factura3ero (+ subdiario) | /app/facturas-proveedor | previa |
| administracion/{asientocontable,asientocta,fondos} | /app/contabilidad/asientos/… | previas |
| administracion/libros/{diario,mayor} | /app/contabilidad/libro-{diario,mayor} | sólo la base del tenant (el CI unía las de secontur); el saldo del mayor es del período, con fila de saldo anterior |
| administracion/ivacredito | /app/contabilidad/iva-venta | subdiario AR (21/10,5, RG); agrupa por tipo de documento; sin la rama multi-base de secontur |
| administracion/libros/{balance,ventascl,comprascl} | /app/contabilidad/{balance,libro-ventas,libro-compras} | balance convierte siempre a moneda básica (el CI comparaba contra 'ARS' fijo); libros SII con tasa de `sysconfig.tasageneral` (el CI: 19 fijo) |
| administracion/cuentas/{cliente,proveedor} | /app/cuentas/… | port de `getCuenta` con modo diferencias y CSV |
| administracion/{canje,precompra,creditoproveedor} | /app/proveedores/… | recalculo de "utilizado" como el legacy |
| administracion/{ventasnetas,reportedeuda,productopororigen} y administracion/reportes/{canjes,r14,r12,honorarios,provisiondeuda,pagos,gastos,facturasaldo,reportedifcambio,analiticovta,reportegastosreserva,opnacionales} | /app/admin/reportes/… | motor de reportes |
| reportes/controlcredito | /app/admin/reportes/control-credito | crédito extra diario (`creditoextra`) |
| dashboard/afacturarmt, dashboard/facturadosmt | /app/admin/reportes/{pendientes-factura,facturados} | el legacy sólo bajaba CSV; ahora listado + CSV |
| administracion/reportes/afacturar, afacturarpp | /app/admin/reportes/{reservas-a-facturar,facturacion-acumulada} | flags `sysconfig.facturapracial` / `factura_vertodos`; FACTURAR linkea al legacy |
| reserva/nueva (v1 manual, sin mapear en menú) | /app/reservas/{área}/nueva | ver `docs/GENERADOR_RESERVAS.md` |
| reserva/lista/{área,all} | /app/reservas/{área} | previa |
| reserva/cotizaciones/{área} | /app/cotizaciones/{área} | |
| operaciones/{autorizar,guardia,trafico}/lista/{área} | /app/operaciones/… | |
| operaciones cierre de grupo | /app/operaciones/cierre-grupo | |
| dashboard (inicio) | /app | widgets por permiso `inicio_*` |

---

## 3. Desvíos deliberados respecto del legacy

- **Contraseñas de usuario**: sólo hash bcrypt (`usuario_password`); no se copia el texto plano en `usuario_clave`. Editar un usuario no borra sus permisos individuales (`permiso`), cosa que el CI hacía por accidente.
- **Clientes**: no se sube el formulario 8001 ni se crea un pasajero nuevo inline desde el cliente (se relacionan pasajeros existentes). Las relaciones recíprocas se mantienen decodificando el JSON (el CI hacía `str_replace` sobre el texto).
- **Pasajeros**: sin fotos (pasajero/documentos). El CI escribía `rel_clientesistema.fk_pasajero_id`, columna que no existe: se omite.
- **Proveedor**: sin logo/imagen. Crédito de proveedor en OP no se convierte de moneda (igual que el legacy).
- **Reportes**: canjes aplica los filtros que el legacy ignoraba; gastos por área filtra por área (el legacy no); diferencia de cambio sólo corre con fecha hasta; OP nacionales mantiene el `<=` del filtro de proveedor. Control de crédito usa la tasa general del tenant en vez de 1.19 fijo (mismo resultado en CL) y omite el ajuste de cartera exclusivo de mundotour_sdg. Facturados usa moneda básica en vez de "CLP" fijo y en pantalla corta en 500 filas (últimos 3 meses sin filtros).
- **Documentos**: las acciones que emiten/anulan/imprimen siguen en el legacy.
- **Movimientos de fondos**: anular sigue en el legacy (además de `status='AN'` marca `movimiento.statusdocumento=0`).
- **Dashboard**: pagos/cobros/a facturar del inicio no se portan (widgets muertos en el legacy).
- **Escritorios**: sin importar/restaurar planilla.
- **Tráfico**: sin "reasignar" (necesita el tarifador).

---

## 4. Secciones del tenant que siguen en el legacy

Listado tomado de `brain.seccion` para la licencia (script en el historial de esta migración). Se agrupan por tipo de trabajo pendiente:

- **Flujos transaccionales pesados** (emiten comprobantes o mueven dinero): `administracion/cobranzas`, `administracion/pagos` (pagos a procesar), `administracion/cartera/{lista,pagodirecto}`, `administracion/ordenservicio/acuenta`, `administracion/caja/arqueo`, `administracion/conciliacion/conciliar`, `administracion/banco/` (conciliación automática), `administracion/autorizar` (e-voucher), `administracion/factura/prebcn`.
- **Contabilidad**: `administracion/libros/cierrecontable`, `administracion/Balance` (8 columnas), `administracion/ctacliente/analitico` (conciliación con `ctaaplicada`/`nubeanalitico`), `administracion/cuentas/micuenta`, `administracion/Balance` (8 columnas), `administracion/ivacredito`, `administracion/ctacliente/analitico`, `administracion/cuentas/micuenta`.
- **Rentabilidad**: `administracion/renta/{mirenta,rentabilidad}`, `administracion/rentamt/{payroll,mayorista,desestimados,crearasiento}`.
- **Reportes grandes** (dependen de `reserva_model` del CI, 500–1500 líneas cada uno): `administracion/reportes/{porboletear,cliente,proveedor,reportegastosingreso}`.
- **Consolidador / BSP**: `consolidador/*`, `administracion/bsp/*`, `administracion/Bspmt/link`.
- **Productos por tipo**: las pantallas Inertia de productos/tarifarios existen pero sus `seccion_uri` quedan sin mapear a propósito hasta probarlas con datos reales (ver comentario en `config/menu.php`). Los tipos auto/asistencia/crucero/ctk/cae/guia/motorhome/misc/dinamicos no tienen pantalla nueva.
- **Varios**: `configuracion/solicitud`, `configuracion/destacado/lista`, `configuracion/reporte/vista` (marketing), `dashboard/backup`, `productos/vistarapidahotel/`, `tarifario/mayorista/{lista,vista}`, `reserva/reservamayorista/minorista`.
- **Nueva reserva / cotización** (`reserva/nueva/{área}`): ver `docs/GENERADOR_RESERVAS.md`.
- El resto de las entradas de la sección 6 "Reservas" y "Usuarios" son flags de permiso sin URI (no son pantallas).

---

## 5. Cómo agregar una pantalla

1. Leer el controlador y la vista del CI (`grep -v '^\s*$'` ayuda) y anotar SQL, filtros, acciones y efectos colaterales.
2. Elegir motor: ABM (tabla simple), Reporte (listado/export) o controlador + página propios.
3. Registrar la ruta (`$abm`/`$reporte` o `Route::…`) y mapear el `seccion_uri` en `config/menu.php`.
4. Extender `CreaEsquemaConfiguracion` con las tablas/columnas que falten y escribir el Feature test (inserts multi-fila sólo con claves idénticas por fila).
5. `php vendor/bin/pint --dirty`, `npm run build` (el build en `public/app/build` se commitea), commit por tema.

# Mapa contable del file

`/app/reservas/mapa-contable/{id}` (con `?embed=1`, sin layout). Sólo lectura.

Muestra, para un file:
- su circuito de venta: facturas, NC y ND, recibos y asientos en cuenta corriente del cliente;
- su circuito de costo: órdenes de servicio y de pago, facturas de proveedor y asientos en cuenta corriente del proveedor;
- los asientos de todos esos comprobantes;
- cuánto se gana en realidad;
- los desvíos entre todas esas fuentes.

| | |
|---|---|
| Código | `app/Services/Reservas/MapaContable/` (`MapaContableFileService` carga, `Tasas` tipos de cambio, `EconomiaFile` cálculo, `DesviosFile` reglas), `Web/Reservas/MapaContableController`, `Pages/Reservas/MapaContable.vue` |
| Flag | `sysconfig.mapa_contable_file` (sembrado en 0 por `db/updates/0013_mapa_contable_file.sql` del CI). En 0 responde 404. Se cachea 1 h (`SysconfigHelper`). |
| Acceso | Usuarios internos: botón "Mapa contable" en la ficha del file del CI y link en el resumen del listado de reservas. Los usuarios CLI/CLM reciben 403. |
| Tests | `tests/Unit/Reservas/EconomiaFileTest.php` (cálculo a mano), `tests/Feature/Reservas/MapaContableTest.php` (carga sobre el esquema legacy) |

## Cómo se llega a cada comprobante

| Comprobante | Vínculo con el file | Vínculo con el servicio |
|---|---|---|
| Factura | `factura.fk_file_id`, `rel_filefactura` (puede ser de varios files) | `rel_serviciofactura` / `serviciofactura`, tipodocumento 1 |
| NC | `notacredito.fk_factura_id` → factura, o `notacredito.fk_file_id` (vacío si se emitió desde el módulo de NC) | `serviciofactura` tipodocumento 2 (en esa fila, `fk_factura_id` es el id de la NC) |
| ND | sólo `fk_notacredito_id` → NC | — |
| Recibo | `rel_filerecibo`, con importe propio por file | — |
| OS / OP | por el servicio | `rel_ordenadminocupacion` (`status` C = comisión del proveedor) |
| Factura de proveedor | por el servicio | `rel_facturaproveedorocupacion` |
| Asientos C / A | `movimiento.fk_file_id` | — |

Para las facturas se toma la unión de los tres caminos y se informa por cuál apareció cada una. La librería `Facturar` no escribe `rel_filefactura`, y la ficha del CI sólo lee esa tabla.

No hay importe facturado por servicio: `serviciofactura.monto` y `rel_serviciofactura.monto` existen en el esquema, pero nadie los escribe. Por eso la venta se compara por comprobante.

Los totales de FC, NC y ND se calculan por conceptos, con las mismas fórmulas que los listados de Documentos. Las NC y ND emitidas desde sus módulos no graban `*_total`.

Una factura compartida con otros files se prorratea según el peso de los servicios de este file. Si no tiene servicios vinculados, se reparte en partes iguales entre los files que la referencian.

## Cuánto se gana

Todo se expresa en moneda básica. El signo positivo es a favor de la agencia.

| Concepto | Fórmula |
|---|---|
| Renta presupuestada (R0) | (total − iva)·cv − costo_operado·cc. Con `costo_operado` = costo + iva_costo + impuestos (estos últimos, sólo si el servicio no tiene PNR). Es la fórmula de `Analitica_model::sql_renta_operacion` (rentamt), sin reemplazarla por `serviciofactura.renta`. |
| Precio de costo | (costo_operado − Q)·cc. Q es lo que facturó el proveedor; sin factura de proveedor, max(costo_operado, pagado). |
| Precio de venta | Comprobantes de venta (FC − NC + ND, en la parte de este file y a su TC) − servicios facturados a la tasa de su factura, llevado a neto de IVA. No se restan percepciones: en factura.php salen de servicios del propio file. |
| Cambio servicio → factura | (total − iva)·(TC de la factura − cv) |
| Cambio factura → cobranza | min(facturado, cobrado)·(TC del recibo − TC de la factura), en la moneda extranjera de facturación. No se calcula si se facturó en más de una moneda extranjera. |
| Cambio servicio → factura del proveedor | facturado por el proveedor·(cc − TC de su factura) |
| Cambio factura del proveedor → pago | min(facturado, pagado)·(TC de la factura del proveedor − TC de la OP) |
| Cambio servicio → pago | pagado·(cc − TC de la OP), sólo si no hay factura de proveedor |
| **Resultado real** | R0 + precio + cambios. Cierra exacto con la venta a la tasa de la factura, más el cambio de cobranza, menos el costo al TC de pago (ver `EconomiaFileTest`). |

Para comparar se muestran también:
- la renta congelada al facturar: `serviciofactura.renta`·cv;
- la renta contable: cuentas de renta de sysconfig y de submódulo, con la misma regla de cuentas ambiguas que el conciliador, convertida a moneda básica;
- la diferencia de cambio asentada, en cuentas cuyo nombre contiene DIF y CAMBIO;
- la caja: cobrado − pagado.

Cada tipo de cambio se muestra junto con su origen:

| Origen | Significado |
|---|---|
| documento | El que grabó el comprobante. |
| servicio | `cotventa` / `cotcosto`. |
| implícita | Deducido del total de la factura. |
| tabla | Tabla `cotizacion` a la fecha. Se marca como inferido. |
| falta | No hay ninguno: desvío de severidad alta. |

La OP se valúa con su propia `cotizacion`, la que se asentó. `reserva_model::pagado()` usa en cambio la de la OS que la originó, y cuando difieren se avisa.

## Desvíos

| Área | Códigos |
|---|---|
| Venta | `SRV_CANCELADO_FACTURADO`, `SRV_SIN_FACTURA`, `SRV_MARCADO_FACTURADO`, `FACTURA_FUERA_DE_FICHA`, `FACTURA_SIN_SERVICIOS`, `FACTURA_TOTAL_GRABADO`, `NC_SIN_FILE`, `DIF_PRECIO_VENTA`, `SALDO_CLIENTE`, `COBRADO_SIN_FACTURAR`, `RECIBO_IMPUTADO_DOS_VECES`, `RECIBO_SIN_APLICAR`, `COMPROBANTES_COMPARTIDOS` |
| Costo | `COSTO_EN_CANCELADO`, `FC3_DIF_COSTO`, `PAGADO_SIN_FC3`, `PAGADO_DE_MAS`, `SALDO_PROVEEDOR`, `OS_PENDIENTE`, `OP_COTIZACION_OS` |
| Cambio | `SIN_COTIZACION`, `SIN_COTIZACION_DOC`, `COTIZACION_INFERIDA`, `COBRANZA_VARIAS_MONEDAS`, `COBRANZA_MIXTA`, `DIF_CAMBIO_NO_ASENTADA`, `DIF_CAMBIO_DISTINTA` |
| Renta | `RENTA_NEGATIVA`, `RENTA_CONGELADA_DISTINTA`, `RENTA_CONTABLE_DISTINTA`, `CUENTA_AMBIGUA`, `SIN_CUENTAS_RENTA` |
| Contabilidad | `COMPROBANTE_SIN_ASIENTO`, `FACTURA_ANULADA_CON_ASIENTO`, `ASIENTO_DESBALANCEADO`, `ASIENTOS_MANUALES` |

Severidades:

| Severidad | Cuándo |
|---|---|
| alta | El número está mal o falta un registro obligatorio. |
| media | Hay una diferencia de plata que alguien tiene que revisar. |
| info | Contexto para leer los números. |

La tolerancia es de 1 en moneda básica.

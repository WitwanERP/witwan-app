<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="Facturaproveedor",
 *     @OA\Property(property="facturaproveedor_id", type="integer", example=1),
 *     @OA\Property(property="facturaproveedor_nro", type="string", example="0001-00012345"),
 *     @OA\Property(property="facturaproveedor_tipodocumento", type="string", enum={"Factura", "Nota de Credito"}, example="Factura"),
 *     @OA\Property(property="facturaproveedor_tipofactura", type="string", example="A"),
 *     @OA\Property(property="fk_proveedor_id", type="integer", example=123),
 *     @OA\Property(property="fk_proyecto_id", type="integer", example=0),
 *     @OA\Property(property="fk_moneda_id", type="string", example="ARS"),
 *     @OA\Property(property="fecha", type="string", format="date", example="2026-05-28"),
 *     @OA\Property(property="fechacontable", type="string", format="date", example="2026-05-28"),
 *     @OA\Property(property="vencimiento", type="string", format="date", example="2026-06-28"),
 *     @OA\Property(property="cotizacion", type="number", format="float", example=1),
 *     @OA\Property(property="tipomovimiento", type="string", enum={"Servicio", "Gasto", "Boleta", "BSP"}, example="Servicio"),
 *     @OA\Property(property="montototal", type="number", format="float", example=121000.00),
 *     @OA\Property(property="descripcion", type="string", example="Texto libre")
 * )
 *
 * @OA\Schema(
 *     schema="FacturaproveedorInput",
 *     required={"facturaproveedor_nro", "facturaproveedor_tipodocumento", "fk_proveedor_id", "fk_moneda_id", "fecha", "tipomovimiento"},
 *     @OA\Property(property="facturaproveedor_nro", type="string", example="0001-00012345"),
 *     @OA\Property(property="facturaproveedor_tipodocumento", type="string", enum={"Factura", "Nota de Credito"}, example="Factura"),
 *     @OA\Property(property="facturaproveedor_tipofactura", type="string", example="A"),
 *     @OA\Property(property="fk_proveedor_id", type="integer", example=123),
 *     @OA\Property(property="fk_moneda_id", type="string", example="ARS"),
 *     @OA\Property(property="fecha", type="string", description="dd/mm/YYYY o ISO", example="28/05/2026"),
 *     @OA\Property(property="fechacontable", type="string", description="Opcional; default = fecha", example="28/05/2026"),
 *     @OA\Property(property="vencimiento", type="string", example="28/06/2026"),
 *     @OA\Property(property="tipomovimiento", type="string", enum={"Servicio", "Gasto", "Boleta", "BSP"}, example="Servicio"),
 *     @OA\Property(property="cotizacion", type="number", format="float", example=1),
 *     @OA\Property(property="nocomputable", type="number", format="float", example=0),
 *     @OA\Property(property="exento", type="number", format="float", example=0),
 *     @OA\Property(property="especial", type="number", format="float", description="Neto gravado al 10.5%", example=0),
 *     @OA\Property(property="general", type="number", format="float", description="Neto gravado a la alícuota general (21%)", example=100000),
 *     @OA\Property(property="monto27", type="number", format="float", description="Neto gravado al 27%", example=0),
 *     @OA\Property(property="monto25", type="number", format="float", description="Neto gravado al 2.5%", example=0),
 *     @OA\Property(property="ivatur", type="number", format="float", example=0),
 *     @OA\Property(property="ivatotal", type="number", format="float", description="IVA total (usado en Chile)", example=0),
 *     @OA\Property(property="otrosimpuestos", type="number", format="float", example=0),
 *     @OA\Property(property="retencioniva", type="number", format="float", example=0),
 *     @OA\Property(property="retencioniibb", type="number", format="float", example=0),
 *     @OA\Property(property="percepcioniva", type="number", format="float", example=0),
 *     @OA\Property(property="percepcioniibb", type="number", format="float", example=0),
 *     @OA\Property(property="retencionganancias", type="number", format="float", example=0),
 *     @OA\Property(property="percepcionganancias", type="number", format="float", example=0),
 *     @OA\Property(property="fk_plancuenta_id", type="integer", example=0),
 *     @OA\Property(property="fk_proyecto_id", type="integer", example=0),
 *     @OA\Property(property="fk_itemgasto_id", type="integer", example=0),
 *     @OA\Property(property="observaciones", type="string", example="Texto libre"),
 *     @OA\Property(
 *         property="ocupacion",
 *         type="array",
 *         description="Servicios existentes a vincular. No permitido para tipomovimiento Gasto o Boleta.",
 *         @OA\Items(
 *             type="object",
 *             required={"id"},
 *             @OA\Property(property="id", type="integer", example=501),
 *             @OA\Property(property="monto", type="number", format="float", example=50000)
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/admin/documentos/facturas-proveedor",
 *     operationId="facturaproveedorIndex",
 *     tags={"Facturas de Proveedor"},
 *     summary="Listar facturas de proveedor",
 *     description="Listado con montos calculados (neto gravado, IVA por alícuota, total). Soporta filtros y paginación.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=100)),
 *     @OA\Parameter(name="proveedor", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="numero", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="codigo", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="proyecto", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="fecha_desde", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="fecha_hasta", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="fechacontable_desde", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="fechacontable_hasta", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Listado paginado")
 * )
 *
 * @OA\Post(
 *     path="/admin/documentos/facturas-proveedor",
 *     operationId="facturaproveedorStore",
 *     tags={"Facturas de Proveedor"},
 *     summary="Crear factura de proveedor",
 *     description="Crea la factura, su servicio contable asociado, relaciones con ocupaciones y el asiento contable con sus movimientos débito/haber. Los totales se calculan en el servidor.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/FacturaproveedorInput")),
 *     @OA\Response(response=201, description="Factura creada", @OA\JsonContent(ref="#/components/schemas/Facturaproveedor")),
 *     @OA\Response(response=403, description="Sin permiso de alta"),
 *     @OA\Response(response=409, description="Ya existe una factura con ese número, proveedor y tipo de documento"),
 *     @OA\Response(
 *         response=422,
 *         description="Validación fallida: faltan campos, total en 0, o Gasto/Boleta con ocupaciones"
 *     )
 * )
 *
 * @OA\Get(
 *     path="/admin/documentos/facturas-proveedor/create",
 *     operationId="facturaproveedorCreate",
 *     tags={"Facturas de Proveedor"},
 *     summary="Datos auxiliares para el alta",
 *     description="Devuelve proveedores con CUIT, cuentas de gasto, proyectos y conceptos adicionales.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Datos auxiliares")
 * )
 *
 * @OA\Get(
 *     path="/admin/documentos/facturas-proveedor/control",
 *     operationId="facturaproveedorControl",
 *     tags={"Facturas de Proveedor"},
 *     summary="Verificar duplicado",
 *     description="Verifica que no exista una factura con la misma combinación de número, proveedor y tipo de documento.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="proveedor", in="query", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="nro", in="query", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="tipo", in="query", required=true, @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Resultado del control",
 *         @OA\JsonContent(@OA\Property(property="ok", type="boolean", example=true))
 *     )
 * )
 *
 * @OA\Get(
 *     path="/admin/documentos/facturas-proveedor/{id}",
 *     operationId="facturaproveedorShow",
 *     tags={"Facturas de Proveedor"},
 *     summary="Detalle de una factura",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Detalle", @OA\JsonContent(ref="#/components/schemas/Facturaproveedor")),
 *     @OA\Response(response=404, description="Registro no encontrado")
 * )
 *
 * @OA\Put(
 *     path="/admin/documentos/facturas-proveedor/{id}",
 *     operationId="facturaproveedorUpdate",
 *     tags={"Facturas de Proveedor"},
 *     summary="Actualización limitada",
 *     description="Permite editar cuenta contable, proyecto, item de gasto, imputación y número. No editable si la fecha contable cae en un período cerrado.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="fk_plancuenta_id", type="integer", example=1101),
 *             @OA\Property(property="fk_proyecto_id", type="integer", example=5),
 *             @OA\Property(property="fk_itemgasto_id", type="integer", example=3),
 *             @OA\Property(property="areaimputacion", type="object"),
 *             @OA\Property(property="factura_numero", type="string", example="0001-00012346")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Factura actualizada", @OA\JsonContent(ref="#/components/schemas/Facturaproveedor")),
 *     @OA\Response(response=403, description="Sin permiso de edición"),
 *     @OA\Response(response=404, description="Registro no encontrado"),
 *     @OA\Response(response=422, description="Fecha fuera del periodo contable abierto")
 * )
 *
 * @OA\Delete(
 *     path="/admin/documentos/facturas-proveedor/{id}",
 *     operationId="facturaproveedorDestroy",
 *     tags={"Facturas de Proveedor"},
 *     summary="Eliminar factura",
 *     description="Elimina validando período contable y que no esté pagada. Limpia relaciones y movimientos huérfanos.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=204, description="Eliminada"),
 *     @OA\Response(response=403, description="Sin permiso de baja"),
 *     @OA\Response(response=404, description="Registro no encontrado"),
 *     @OA\Response(response=422, description="Período cerrado o factura con pago realizado")
 * )
 *
 * @OA\Get(
 *     path="/admin/documentos/facturas-proveedor/{id}/imprimir",
 *     operationId="facturaproveedorImprimir",
 *     tags={"Facturas de Proveedor"},
 *     summary="Datos para impresión",
 *     description="Devuelve la factura con su proveedor/plan de cuenta y los servicios asociados con su reserva.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Datos de impresión",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/Facturaproveedor"),
 *             @OA\Property(property="datasvc", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=404, description="Registro no encontrado")
 * )
 */
class FacturaproveedorDocumentation
{
}

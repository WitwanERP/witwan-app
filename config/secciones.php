<?php

/**
 * Secciones del CI legacy (tabla `seccion` de brain) usadas para permisos.
 *
 * `App\Support\Secciones` resuelve el id por la URI del CI consultando brain, y
 * cachea el resultado por licencia. Este archivo permite fijar el id a mano
 * cuando la resolución automática no alcanza.
 *
 * Verificado contra brain (2026-08):
 *   72  => /administracion/factura3ero                  "Facturas de terceros"
 *   270 => /administracion/factura3erom                 "Facturas de terceros" (carga múltiple)
 *   284 => /administracion/factura3ero/subdiariocompra  "Subdiario compra (impresion)"
 *
 * OJO: 270 y 284 no tienen NINGUNA fila en `permisogrupo`. Como CI arma el
 * sidebar filtrando por `can('acceso', $id)` (main_model.php:46-52), esas dos
 * secciones no aparecen en el menú de los usuarios no-POW; y como el id de
 * sección se resuelve por coincidencia de substring contra el sidebar
 * (Admin_Controller.php:658-676), en la práctica ambas URLs terminan
 * gobernadas por la sección 72 —cuya URI es prefijo de las otras dos—. Por eso
 * el módulo entero se gatea con 72, que es lo que hace el legacy hoy.
 */
return [

    'overrides' => [
        'administracion/factura3ero' => 72,

        // Se gatean con la sección madre a propósito: ver la nota de arriba.
        'administracion/factura3erom' => 72,
        'administracion/factura3ero/subdiariocompra' => 72,
    ],

    // Cache de la resolución por licencia, en segundos.
    'cache_ttl' => (int) env('SECCIONES_CACHE_TTL', 3600),
];

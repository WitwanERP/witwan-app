<?php

/**
 * Webservice de DTE de Chile (getdte.cl), usado por el listado de documentos
 * recibidos de las licencias chilenas.
 *
 * Las credenciales estaban hardcodeadas en el controller del CI
 * (factura3ero.php:1698-1702 y :1728-1732, repetidas dos veces). Acá salen del
 * entorno: sin ellas la pantalla avisa que no está configurada en vez de fallar
 * contra el webservice.
 */
return [
    'wsdl' => env('DTE_CL_WSDL', 'https://mundotour.getdte.cl/wsdl/WSGetDTE.wsdl'),
    'ambiente' => env('DTE_CL_AMBIENTE'),
    'empresa' => env('DTE_CL_EMPRESA'),
    'usuario' => env('DTE_CL_USUARIO'),
    'password' => env('DTE_CL_PASSWORD'),

    // Licencias donde la pantalla tiene sentido.
    'licencias' => ['mundotour_sdg'],
];

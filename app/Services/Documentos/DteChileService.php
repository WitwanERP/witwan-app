<?php

namespace App\Services\Documentos;

use App\Support\Licencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SoapClient;

/**
 * Documentos tributarios electrónicos recibidos (getdte.cl), sólo Chile.
 *
 * Port de `factura3ero::listadobcn()` (factura3ero.php:1688-1766): pide el
 * listado de recibidos del período, baja el detalle de cada documento y lo cruza
 * contra `facturaproveedor` para marcar cuáles ya están cargados.
 *
 * Dos cosas que el legacy hacía mal y acá no:
 *  - Creaba un SoapClient y hacía login POR CADA documento del listado. Con 200
 *    documentos son 200 logins. Acá se conecta y autentica una sola vez.
 *  - Las credenciales estaban escritas en el código; ahora salen de config/dte_chile.php.
 */
class DteChileService
{
    private ?SoapClient $cliente = null;

    private ?string $token = null;

    /** ¿Está disponible? Requiere ext-soap y credenciales cargadas. */
    public function disponible(): bool
    {
        return $this->soapDisponible() && $this->configurado();
    }

    public function soapDisponible(): bool
    {
        return class_exists(SoapClient::class);
    }

    public function configurado(): bool
    {
        foreach (['ambiente', 'empresa', 'usuario', 'password'] as $clave) {
            if (blank(config("dte_chile.{$clave}"))) {
                return false;
            }
        }

        return true;
    }

    /** ¿La licencia actual usa esta pantalla? */
    public function aplicaALaLicencia(): bool
    {
        return in_array(Licencia::base(), (array) config('dte_chile.licencias', []), true)
            || Licencia::pais() === 'CL';
    }

    /**
     * Documentos recibidos entre dos fechas, con la marca de si ya están
     * cargados como factura de proveedor.
     *
     * @return list<array>
     */
    public function recibidos(string $desde, string $hasta): array
    {
        if (! $this->disponible()) {
            throw new RuntimeException(
                $this->soapDisponible()
                    ? 'El webservice de DTE no está configurado para esta instalación.'
                    : 'La extensión SOAP de PHP no está instalada en este servidor.'
            );
        }

        $listado = $this->listadoRecibidos($this->fecha($desde), $this->fecha($hasta));

        if ($listado === []) {
            return [];
        }

        // Un solo lote de consultas contra la BD en vez de una por documento.
        $yaCargados = $this->buscarCargados($listado);

        $documentos = [];
        foreach ($listado as $item) {
            $clave = $item['emisor'].'_'.$item['tipo'].'_'.$item['folio'];

            $documentos[] = [
                'clave' => $clave,
                'emisor' => $item['emisor'],
                'tipo' => $item['tipo'],
                'folio' => $item['folio'],
                'detalle' => $this->detalle($item),
                'facturaId' => $yaCargados[$this->claveCruce($item)] ?? null,
            ];
        }

        return $documentos;
    }

    /** @return list<array{emisor:string,tipo:string,folio:string}> */
    private function listadoRecibidos(string $desde, string $hasta): array
    {
        $respuesta = $this->llamar('listado_recibidos_diario', [
            'token' => $this->token(),
            'fecha_inicio' => $desde,
            'fecha_final' => $hasta,
            'tipo_archivo' => 'JSON',
        ]);

        if (! isset($respuesta['listado'])) {
            return [];
        }

        $xml = @simplexml_load_string($respuesta['listado']);

        if ($xml === false) {
            Log::warning('DTE Chile: el listado no se pudo parsear');

            return [];
        }

        $items = [];
        foreach ($xml as $item) {
            $items[] = [
                'emisor' => (string) $item->emisor,
                'tipo' => (string) $item->tipo,
                'folio' => (string) $item->folio,
            ];
        }

        return $items;
    }

    /** Detalle del documento (XML en base64). Null si el webservice falla. */
    private function detalle(array $item): ?array
    {
        try {
            $respuesta = $this->llamar('archivo_recibido', [
                'token' => $this->token(),
                'dte_emisor' => $item['emisor'],
                'dte_tipo' => $item['tipo'],
                'dte_folio' => $item['folio'],
                'tipo_archivo' => 'XML',
            ]);

            $xml = @simplexml_load_string(base64_decode($respuesta['archivo_base64'] ?? ''));

            if ($xml === false || ! isset($xml->Documento)) {
                return null;
            }

            return [
                'encabezado' => json_decode(json_encode($xml->Documento->Encabezado), true),
                'detalle' => json_decode(json_encode($xml->Documento->Detalle), true),
                'referencia' => json_decode(json_encode($xml->Documento->Referencia), true),
            ];
        } catch (\Throwable $e) {
            // El legacy hacía `die($ex->getMessage())`: un documento roto tiraba
            // abajo la pantalla entera. Acá se pierde ese detalle y sigue.
            Log::warning('DTE Chile: no se pudo bajar el detalle', [
                'folio' => $item['folio'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Facturas ya cargadas que coinciden con los documentos del listado.
     *
     * @return array<string,int> "cuit_folio" => facturaproveedor_id
     */
    private function buscarCargados(array $listado): array
    {
        $folios = array_values(array_unique(array_column($listado, 'folio')));

        if ($folios === []) {
            return [];
        }

        return DB::table('facturaproveedor')
            ->join('proveedor', 'proveedor.proveedor_id', '=', 'facturaproveedor.fk_proveedor_id')
            ->whereIn('facturaproveedor.facturaproveedor_nro', $folios)
            ->get(['facturaproveedor.facturaproveedor_id', 'facturaproveedor.facturaproveedor_nro', 'proveedor.cuit'])
            ->mapWithKeys(fn ($f) => [
                $this->normalizarRut($f->cuit).'_'.$f->facturaproveedor_nro => (int) $f->facturaproveedor_id,
            ])
            ->all();
    }

    private function claveCruce(array $item): string
    {
        return $this->normalizarRut($item['emisor']).'_'.$item['folio'];
    }

    /** El listado trae el RUT con guion y la tabla lo guarda sin él. */
    private function normalizarRut(?string $rut): string
    {
        return str_replace('-', '', (string) $rut);
    }

    private function llamar(string $metodo, array $params): array
    {
        $respuesta = $this->cliente()->__soapCall($metodo, $params);

        return is_array($respuesta) ? $respuesta : (array) $respuesta;
    }

    private function cliente(): SoapClient
    {
        return $this->cliente ??= new SoapClient(config('dte_chile.wsdl'), [
            'trace' => true,
            'exceptions' => true,
        ]);
    }

    /** Login una sola vez por instancia (el legacy lo repetía por documento). */
    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $data = $this->llamar('login', [
            'dte_ambiente' => (string) config('dte_chile.ambiente'),
            'dte_empresa' => (string) config('dte_chile.empresa'),
            'dte_usuario' => (string) config('dte_chile.usuario'),
            'dte_password' => (string) config('dte_chile.password'),
        ]);

        if (empty($data['token'])) {
            throw new RuntimeException('El webservice de DTE rechazó las credenciales.');
        }

        return $this->token = (string) $data['token'];
    }

    private function fecha(string $valor): string
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return Carbon::parse($valor)->format('Y-m-d');
    }
}

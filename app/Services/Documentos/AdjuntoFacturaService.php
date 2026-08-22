<?php

namespace App\Services\Documentos;

use App\Support\Licencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Adjunto de una factura de tercero (el PDF o imagen del comprobante).
 *
 * DIFERENCIA CON EL LEGACY, deliberada. El CI hacía `move_uploaded_file()` a
 * `DOCUMENT_ROOT/upfiles/{licencia}/` y linkeaba a `/upfiles/{licencia}/...`,
 * pero las dos puntas están rotas: en el alta la variable `$licdata` no existe en
 * ese scope (factura3ero.php:968), así que el archivo cae en `/upfiles//`, y en
 * la vista de detalle el link se arma con `<?php LICENCIA; ?>` sin `echo`
 * (factura3rover.php:26), o sea sin la licencia. Hoy la función no sirve.
 *
 * Además, con el front nuevo `/upfiles/...` lo sirve el CI (nginx manda todo lo
 * que no es /app ni /api al Apache legacy) y Laravel corre en otro contenedor con
 * otro volumen: escribir ahí desde acá no lo haría descargable.
 *
 * Por eso el archivo se guarda en el storage de Laravel, particionado por
 * licencia, y se descarga por una ruta propia bajo /app.
 */
class AdjuntoFacturaService
{
    private const DISCO = 'local';

    /** Guarda el archivo y devuelve el nombre a persistir en `facturaproveedor.archivo`. */
    public function guardar(UploadedFile $archivo, int $facturaId): string
    {
        $nombre = $facturaId.'-'.Str::slug(
            pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME)
        ).'.'.$archivo->getClientOriginalExtension();

        $archivo->storeAs($this->carpeta(), $nombre, self::DISCO);

        return $nombre;
    }

    /** Ruta absoluta del adjunto, o null si no existe. */
    public function ruta(?string $nombre): ?string
    {
        if (blank($nombre)) {
            return null;
        }

        $ruta = $this->carpeta().'/'.$nombre;

        return Storage::disk(self::DISCO)->exists($ruta)
            ? Storage::disk(self::DISCO)->path($ruta)
            : null;
    }

    public function eliminar(?string $nombre): void
    {
        if (blank($nombre)) {
            return;
        }

        Storage::disk(self::DISCO)->delete($this->carpeta().'/'.$nombre);
    }

    /** Una carpeta por licencia: las bases son distintas pero el storage es uno. */
    private function carpeta(): string
    {
        return 'facturas-proveedor/'.(Licencia::base() ?: '_');
    }
}

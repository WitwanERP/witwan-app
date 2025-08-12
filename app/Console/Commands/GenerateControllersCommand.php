<?php
// app/Console/Commands/GenerateControllersCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateControllersCommand extends Command
{
    protected $signature = 'generate:controllers {--tables=}';
    protected $description = 'Generate controllers for database tables';

    public function handle()
    {
        $tables = $this->option('tables')
            ? explode(',', $this->option('tables'))
            : $this->getAllTables();

        foreach ($tables as $table) {
            $this->generateController($table);
        }

        $this->info('Controllers generated successfully!');
    }

    private function getAllTables()
    {
        $tables = DB::select('SHOW TABLES');
        $tableNames = [];

        foreach ($tables as $table) {
            // Obtener el nombre de la tabla del objeto stdClass
            $tableName = array_values((array) $table)[0];
            $tableNames[] = $tableName;
        }

        return $tableNames;
    }

private function generateController($tableName)
    {
        // Limpiar el nombre de la tabla
        $tableName = trim($tableName);

        // Verificar que el nombre no esté vacío
        if (empty($tableName)) {
            $this->error("Nombre de tabla vacío, saltando...");
            return;
        }

        try {
            $modelName = Str::studly(Str::singular($tableName));
            $controllerName = $modelName . 'Controller';

            // Determinar el namespace según la tabla
            $namespace = $this->getNamespaceForTable($tableName);

            $template = $this->getControllerTemplate($modelName, $namespace);

            $path = app_path("Http/Controllers/{$namespace}/{$controllerName}.php");

            // Crear directorio si no existe
            $directory = dirname($path);
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put($path, $template);

            $this->info("Generated: {$controllerName} in {$namespace}");
        } catch (\Exception $e) {
            $this->error("Error generando controlador para tabla '{$tableName}': " . $e->getMessage());
        }
    }

    private function getNamespaceForTable($tableName)
    {
        // Mapear tablas a namespaces según tu estructura
        $namespaceMap = [
            'banner' => 'Banner',
            'base' => 'Base',
            'busqueda' => 'Reservas/Busquedas',
            'ciudadtourico' => 'Geo',
            'ciudadxml' => 'Geo',
            'cadenacliente' => 'Empresas/Clientes',
            'cliente' => 'Empresas/Clientes',
            'cliente_extra' => 'Empresas/Clientes',
            'pasajero' => 'Empresas/Clientes',
            'asientocontable' => 'Admin/Contabilidad',
            'auxiliarcontable' => 'Admin/Contabilidad',
            'canje' => 'Admin/Contabilidad',
            'centrocosto' => 'Admin/Contabilidad',
            'cierrearqueo' => 'Admin/Contabilidad',
            'cierrecaja' => 'Admin/Contabilidad',
            'cobrocliente' => 'Admin/Caja',
            'comisionespecial' => 'Admin/Contabilidad',
            'conciliabanco' => 'Admin/Caja',
            'conciliacion' => 'Admin/Caja',
            'conciliamvt' => 'Admin/Caja',
            'condicioniva' => 'Admin/Contabilidad',
            'convenio' => 'Empresas/Proveedores',
            'cpayroll' => 'Contabilidad',
            'creditoextra' => 'Admin/Clientes',
            'ctaaplicada' => 'Admin/Contabilidad',
            'cuposborrados' => 'Productos',
            'factura' => 'Admin/Documentos',
            'factura_envio' => 'Admin/Documentos',
            'facturaaerolinea' => 'Admin/Documentos',
            'facturaboleta' => 'Admin/Documentos',
            'facturacliente' => 'Admin/Documentos',
            'facturaproveedor' => 'Admin/Documentos',
            'formapago' => 'Admin/General',
            'grupocomsion' => 'Admin/General',
            'imputacion' => 'Admin/General',
            'itemgasto' => 'Admin/General',
            'iva' => 'Admin/General',
            'ivatipo' => 'Admin/General',
            'modelocomision' => 'Admin/General',
            'modelofee' => 'Admin/General',
            'modoivaventa' => 'Admin/General',
            'moneda' => 'Admin/Monedas',
            'movimiento' => 'Admin/Contabilidad',
            'notacredito' => 'Admin/Documentos',
            'notadebito' => 'Admin/Documentos',
            'nubeanalitico' => 'Admin/Contabilidad',
            'ordenadmin' => 'Admin/Caja',
            'pagoproveedor' => 'Admin/Caja',
            'payroll' => 'Admin/General',
            'plancuenta' => 'Admin/Contabilidad',
            'precompra' => 'Admin/General',
            'preventa' => 'Admin/General',
            'recibo' => 'Admin/Caja',
            'cotizacion' => 'Admin/Monedas',
            'colaevento' => 'Eventos',
            'crmbanco' => 'Admin/General',
            'diccionario' => 'General',
            'feriado' => 'General',
            'idioma' => 'General',
            'loginterfase' => 'General',
            'lotedocumento' => 'Admin/General',
            'pasajero_extra' => 'General',
            'permiso' => 'Users',
            'permisogrupo' => 'Users',
            'personal_access_tokens' => 'Users',
            'plugins' => 'General',
            'programafidelidad' => 'General',
            'proyecto' => 'General',
            'rel_clientesistema' => 'General',
            'rel_clientetag' => 'General',
            'rel_eerr' => 'General',
            'rel_facturaproveedorocupacion' => 'General',
            'rel_facturarecibo' => 'General',
            'rel_facturareportebsp' => 'General',
            'rel_filefactura' => 'General',
            'rel_filerecibo' => 'General',
            'rel_grupopaispais' => 'General',
            'rel_guiaidioma' => 'General',
            'rel_ocupacionprecompra' => 'General',
            'rel_ocupacionvigencia' => 'General',
            'rel_ordenadminocupacion' => 'General',
            'rel_pasajerotag' => 'General',
            'rel_pkdinamicotarifario' => 'General',
            'rel_productoalojamientofacilidad' => 'General',
            'rel_productobase' => 'General',
            'rel_productociudad' => 'General',
            'rel_proveedorsistema' => 'General',
            'rel_servicio' => 'General',
            'rel_serviciofactura' => 'General',
            'rel_usuariomodelocomision' => 'General',
            'rel_usuariotipousuario' => 'General',
            'rel_usuariousuario' => 'General',
            'rel_vigenciadia' => 'General',
            'relacionsigav' => 'General',
            'reportebsp' => 'Admin/BSP',
            'reportebsptkt' => 'Admin/BSP',
            'sessions' => 'General',
            'sistema' => 'General',
            'solicitud' => 'General',
            'syscategory' => 'General',
            'sysconfig' => 'General',
            'syslogin' => 'General',
            'sysmenu' => 'General',
            'sysmodule' => 'General',
            'sysnotification' => 'General',
            'sysperm' => 'General',
            'sysrole' => 'General',
            'sysuser' => 'General',
            'sysuserperm' => 'General',
            'tag' => 'Empresas/Clientes',
            'tarjetacredito' => 'Admin/General',
            'tipoboleto' => 'General',
            'tipocambio' => 'General',
            'tipoclavefiscal' => 'General',
            'tipofactura' => 'General',
            'tiposervicio' => 'General',
            'tipousuario' => 'General',
            'torpedo' => 'General',
            'traslado' => 'General',
            'usuario' => 'General',
            'usuariocomision' => 'General',
            'vigencia' => 'General',
            'vigenciaalojamiento' => 'General',
            'xmlin' => 'General',
            'ciudad' => 'Geo',
            'grupopais' => 'Geo',
            'pais' => 'Geo',
            'region' => 'Geo',
            'alojamiento' => 'Productos',
            'alojamientofacilidad' => 'Productos',
            'alojamientohabitacion' => 'Productos',
            'alojamientotipo' => 'Productos',
            'asv' => 'Productos',
            'cupo' => 'Productos',
            'cupoaereo' => 'Productos',
            'cupohistorial' => 'Productos',
            'cupotkt' => 'Productos',
            'dataoff' => 'Productos',
            'destacado' => 'Productos',
            'dia' => 'Productos',
            'excursion' => 'Productos',
            'gds' => 'Productos',
            'guia' => 'Productos',
            'hotelcategoria' => 'Productos',
            'interfasedata' => 'Productos',
            'interfases' => 'Productos',
            'pkdgaleria' => 'Productos',
            'pkdinamico' => 'Productos',
            'pkditem' => 'Productos',
            'pkdproducto' => 'Productos',
            'pnraereo' => 'Productos',
            'pnrremark' => 'Productos',
            'pnrremarksconfig' => 'Productos',
            'pnrsegment' => 'Productos',
            'producto' => 'Productos',
            'producto_extra' => 'Productos',
            'productogaleria' => 'Productos',
            'productogrupo' => 'Productos',
            'regimen' => 'Productos',
            'soldout' => 'Productos',
            'submodulo' => 'Productos',
            'tarifa' => 'Productos',
            'tarifacategoria' => 'Productos',
            'tarifario' => 'Productos',
            'tarifarioarchivo' => 'Productos',
            'tarifariocomision' => 'Productos',
            'pkd' => 'Productos',
            'cadenahotelera' => 'Empresas/Proveedores',
            'proveedor' => 'Empresas/Proveedores',
            'ctz' => 'Reservas',
            'filearchivo' => 'Reservas',
            'filecomentario' => 'Reservas',
            'filemail' => 'Reservas',
            'filenotificacion' => 'Reservas',
            'filenotificacionctz' => 'Reservas',
            'filestatus' => 'Reservas',
            'identidadfiscal' => 'Reservas',
            'mails' => 'Reservas',
            'mailsctz' => 'Reservas',
            'negocio' => 'Reservas',
            'reserva' => 'Reservas',
            'reserva_extra' => 'Reservas',
            'reservain' => 'Reservas',
            'servicio' => 'Reservas',
            'servicio_extra' => 'Reservas',
            'servicio_extractz' => 'Reservas',
            'servicio_nomina' => 'Reservas',
            'servicio_nominactz' => 'Reservas',
            'servicioasociado' => 'Reservas',
            'servicioboleto' => 'Reservas',
            'serviciocontable' => 'Reservas',
            'servicioctz' => 'Reservas',
            'serviciofactura' => 'Reservas',
            'cache' => 'Sistema',
            'cache_locks' => 'Sistema',
            'ci_sessions' => 'Sistema',
            'historial' => 'Sistema',
            'historialfile' => 'Sistema',
            'historialsql' => 'Sistema',
        ];
        return $namespaceMap[$tableName] ?? 'General';
    }

    private function getControllerTemplate($modelName, $namespace)
    {
        // Los modelos están todos en App\Models sin subcarpetas
        $modelPath = "App\\Models\\{$modelName}";
        // Convertir / a \ para namespaces correctos en controladores
        $namespace = str_replace('/', '\\', $namespace);

        return "<?php

namespace App\\Http\\Controllers\\{$namespace};

use {$modelPath};
use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Validator;
use Illuminate\\Support\\Facades\\Schema;
use Illuminate\\Database\\Eloquent\\ModelNotFoundException;

class {$modelName}Controller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request \$request)
    {
        \$perPage = \$request->get('per_page', 100);
        \$query = {$modelName}::query();

        // Agregar filtros básicos aquí
        if (\$request->has('search') && !empty(\$request->search)) {
            // Implementar búsqueda según los campos de la tabla
        }

        return response()->json(\$query->paginate(\$perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request \$request)
    {
        \$validator = Validator::make(\$request->all(), [
            // Agregar reglas de validación aquí
        ]);

        if (\$validator->fails()) {
            return response()->json(['errors' => \$validator->errors()], 422);
        }

        \$data = \$request->all();
        \$model = new {$modelName}();
        \$tableColumns = collect(Schema::getColumnListing(\$model->getTable()));

        // Agregar campos automáticos si existen
        if (\$tableColumns->contains('fechacarga')) {
            \$data['fechacarga'] = now();
        }
        if (\$tableColumns->contains('um')) {
            \$data['um'] = now();
        }
        if (\$tableColumns->contains('fk_usuario_id')) {
            \$data['fk_usuario_id'] = auth()->id();
        }

        \$item = {$modelName}::create(\$data);
        return response()->json(\$item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(\$id)
    {
        try {
            \$item = {$modelName}::findOrFail(\$id);
            return response()->json(\$item);
        } catch (ModelNotFoundException \$e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request \$request, \$id)
    {
        try {
            \$item = {$modelName}::findOrFail(\$id);

            \$validator = Validator::make(\$request->all(), [
                // Agregar reglas de validación aquí
            ]);

            if (\$validator->fails()) {
                return response()->json(['errors' => \$validator->errors()], 422);
            }

            \$data = \$request->all();
            \$tableColumns = collect(Schema::getColumnListing(\$item->getTable()));

            // Actualizar campos automáticos
            if (\$tableColumns->contains('um')) {
                \$data['um'] = now();
            }
            if (\$tableColumns->contains('fk_usuario_id')) {
                \$data['fk_usuario_id'] = auth()->id();
            }

            \$item->update(\$data);
            return response()->json(\$item);
        } catch (ModelNotFoundException \$e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(\$id)
    {
        try {
            \$item = {$modelName}::findOrFail(\$id);
            \$item->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException \$e) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
    }

    /**
     * Search resources.
     */
    public function search(Request \$request)
    {
        \$query = {$modelName}::query();
        \$perPage = \$request->get('per_page', 100);

        if (\$request->has('q') && !empty(\$request->q)) {
            \$searchTerm = \$request->q;
            // Implementar búsqueda en campos principales
        }

        return response()->json(\$query->paginate(\$perPage));
    }
}";
    }
}

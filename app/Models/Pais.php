<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pai
 *
 * @property int $pais_id
 * @property string $pais_nombre
 * @property string $pais_codigo
 * @property int $fk_region_id
 * @property string $nombre_en
 * @property string $nombre_pg
 * @property bool $validado
 * @property string $pais_cuit
 * @property int $codigoafip
 *
 * @property Region $region
 * @property Collection|Ciudad[] $ciudads
 * @property Collection|Cliente[] $clientes
 * @property Collection|Iva[] $ivas
 * @property Collection|Modelofee[] $modelofees
 * @property Collection|Proveedor[] $proveedors
 * @property Collection|Tarifariocomision[] $tarifariocomisions
 *
 * @package App\Models
 */
class Pais extends Model
{
    protected $table = 'pais';
    protected $primaryKey = 'pais_id';
    public $timestamps = false;

    protected $casts = [
        'fk_region_id' => 'int',
        'validado' => 'bool',
        'codigoafip' => 'int'
    ];

    protected $fillable = [
        'pais_nombre',
        'pais_codigo',
        'fk_region_id',
        'nombre_en',
        'nombre_pg',
        'validado',
        'pais_cuit',
        'codigoafip'
    ];

    public function region()
    {
        return $this->belongsTo(Region::class, 'fk_region_id');
    }

    public function ciudads()
    {
        return $this->hasMany(Ciudad::class, 'fk_pais_id');
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'fk_pais_id');
    }

    public function ivas()
    {
        return $this->hasMany(Iva::class, 'fk_pais_id');
    }

    public function modelofees()
    {
        return $this->hasMany(Modelofee::class, 'fk_pais_id');
    }

    public function proveedors()
    {
        return $this->hasMany(Proveedor::class, 'fk_pais_id');
    }

    public function tarifariocomisions()
    {
        return $this->hasMany(Tarifariocomision::class, 'fk_pais_id');
    }
}

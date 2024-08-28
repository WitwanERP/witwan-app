<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'usuario';
    protected $primaryKey = 'usuario_id';

    protected $fillable = [
        'usuario_mail', // campo personalizado
        'usuario_clave',
        // otros campos
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }


    public function getAuthPassword()
    {
        return $this->usuario_clave; // Campo de contraseña personalizado
    }

    public function getAuthIdentifierName()
    {
        return $this->usuario_mail; // Campo de contraseña personalizado
    }

}

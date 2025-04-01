<?php
namespace App\Providers;
use App\Models\Cliente;
use App\Policies\ClientePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Invoice::class => ClientePolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}

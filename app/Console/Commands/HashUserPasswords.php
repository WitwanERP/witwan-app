<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HashUserPasswords extends Command
{
    protected $signature = 'users:hash-passwords';
    protected $description = 'Hash usuario_clave field and store in password field';

    public function handle()
    {
        $this->info('Starting password hashing process...');

        $users = DB::table('usuario')->whereNotNull('usuario_clave')->get();
        $count = 0;

        foreach ($users as $user) {
            if (isset($user->usuario_clave)) {
                DB::table('usuario')
                    ->where('usuario_id', $user->usuario_id)
                    ->update([
                        'password' => Hash::make($user->usuario_clave)
                    ]);
                $count++;
            }
        }

        $this->info("Successfully hashed passwords for {$count} users!");
        return Command::SUCCESS;
    }
}

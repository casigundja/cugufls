<?php

use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('cugufls:admin {email} {--generate}', function () {
    $email = strtolower($this->argument('email'));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Email inválido.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Conta existente; não foi alterada.');

        return 1;
    }
    $password = $this->option('generate') ? Str::password(24) : $this->secret('Palavra-passe (mínimo 12 caracteres)');
    if (strlen($password ?? '') < 12) {
        $this->error('Palavra-passe demasiado curta.');

        return 1;
    }
    $user = new User;
    $user->name = 'Administrador';
    $user->email = $email;
    $user->password = $password;
    $user->active = true;
    $user->email_verified_at = now();
    $user->save();
    $user->assignRole('super-admin');
    if ($this->option('generate')) {
        Storage::disk('local')->put('initial-admin.txt', 'Email: '.$email."\nPassword: ".$password."\nAltere a palavra-passe após o primeiro acesso e elimine este ficheiro.\n");
        $this->info('Conta criada. Credenciais guardadas em storage/app/private/initial-admin.txt.');
    } else {
        $this->info('Conta criada.');
    }
})->purpose('Criar o primeiro administrador, sem palavra-passe padrão');

Artisan::command('cugufls:backup', function () {
    if (config('database.default') !== 'sqlite') {
        $this->error('Para MySQL, utilize o backup cifrado configurado na infraestrutura.');

        return 1;
    }
    $path = config('database.connections.sqlite.database');
    $target = storage_path('app/private/backups/'.now()->format('Ymd-His').'.sqlite');
    if (! is_dir(dirname($target))) {
        mkdir(dirname($target), 0700, true);
    }
    $pdo = DB::connection()->getPdo();
    $pdo->exec('VACUUM INTO '.$pdo->quote($target));
    $this->info('Backup local criado em disco privado.');
});
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::call(function () {
    foreach (ReportExport::where('expires_at', '<', now())->cursor() as $export) {
        if ($export->path) {
            Storage::disk('local')->delete($export->path);
        }
        $export->delete();
    }
})->daily();

Artisan::command('cugufls:status', function () {
    $this->table(['Indicador', 'Estado'], [
        ['Ambiente', app()->environment()], ['Base de dados', config('database.default')],
        ['Administradores ativos', User::role('super-admin')->where('active', true)->count()],
        ['Utilizadores', User::count()], ['Fila', config('queue.default')], ['Email', config('mail.default')],
        ['Build', file_exists(public_path('build/manifest.json')) ? 'Disponível' : 'Em falta'],
    ]);
    foreach (User::role('super-admin')->where('active', true)->get(['email']) as $user) {
        $this->line('Administrador: '.$user->email);
    }
})->purpose('Verificar a instalação sem exibir segredos');

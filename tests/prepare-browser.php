<?php

use App\Models\Category;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with(str_replace('\\', '/', $database), str_replace('\\', '/', storage_path('framework/testing/browser-')))) {
    throw new RuntimeException('O teste exige uma base temporária isolada.');
}
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
$user = new User;
$user->forceFill(['name' => 'Verificação de navegador', 'email' => 'browser@example.test', 'password' => getenv('BROWSER_PASSWORD'), 'email_verified_at' => now(), 'active' => true])->save();
$user->assignRole('super-admin');
$segment = Segment::where('slug', 'farmacia')->firstOrFail();
(new Category)->forceFill(['name' => 'Cuidados', 'slug' => 'cuidados', 'segment_id' => $segment->id, 'active' => true, 'sort_order' => 0])->save();
echo "Base de navegador preparada.\n";

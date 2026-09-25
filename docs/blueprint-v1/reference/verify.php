<?php

// Verificação descartável: não carrega bootstrap/app.php nem .env.
require dirname(__DIR__, 3).'/vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

$container = new Container;
$container->instance('config', new Repository([
    'database' => [
        'default' => 'blueprint',
        'connections' => [
            'blueprint' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ],
    ],
]));
$database = new DatabaseManager($container, new ConnectionFactory($container));
$container->instance('db', $database);
$container->bind('db.schema', fn () => $database->connection()->getSchemaBuilder());
Facade::setFacadeApplication($container);
Model::setConnectionResolver($database);

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function rejected(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (QueryException) {
        return;
    }

    throw new RuntimeException($message);
}

Schema::create('users', function (Blueprint $table) {
    $table->id();
});
Schema::create('roles', function (Blueprint $table) {
    $table->id();
});

$files = glob(__DIR__.'/migrations/*.php');
sort($files);
$migrations = [];
foreach ($files as $file) {
    $migration = require $file;
    $migration->up();
    $migrations[] = $migration;
}

$schema = json_decode(file_get_contents(dirname(__DIR__).'/schema.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($schema as $table) {
    check(Schema::hasTable($table['table']), 'Tabela ausente: '.$table['table']);
    foreach ($table['columns'] as $column) {
        check(Schema::hasColumn($table['table'], $column['name']), 'Coluna ausente: '.$column['name']);
    }
}
check(Schema::hasColumn('users', 'active'), 'Extensão de User ausente.');

$database->table('segments')->insert(['id' => 1, 'name' => 'Teste', 'slug' => 'teste', 'sort_order' => 0]);
$database->table('branches')->insert(['id' => 1, 'name' => 'Teste', 'slug' => 'teste', 'city' => 'Teste', 'province' => 'Teste']);
$database->table('categories')->insert(['id' => 1, 'segment_id' => 1, 'name' => 'Teste', 'slug' => 'teste', 'sort_order' => 0]);
$database->table('products')->insert(['id' => 1, 'segment_id' => 1, 'category_id' => 1, 'name' => 'Teste', 'slug' => 'teste', 'unit' => 'unit', 'purchase_mode' => 'catalog_only']);
$inventory = ['product_id' => 1, 'branch_id' => 1, 'quantity' => '0.000', 'minimum_quantity' => '0.000'];
$database->table('inventories')->insert($inventory);
rejected(fn () => $database->table('inventories')->insert($inventory), 'Inventário duplicado não foi rejeitado.');
rejected(fn () => $database->table('inventories')->insert(array_merge($inventory, ['product_id' => 999])), 'FK inválida não foi rejeitada.');
rejected(fn () => $database->table('products')->where('id', 1)->delete(), 'Eliminação de produto referenciado não foi rejeitada.');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Models\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__.'/models/'.substr($class, strlen($prefix)).'.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
}, true, true);

$relationCount = 0;
foreach ($schema as $table) {
    if ($table['model'] === null) {
        continue;
    }
    $class = 'App\\Models\\'.$table['model'];
    $model = new $class;
    check($model->getTable() === $table['table'], 'Tabela incorreta para '.$class);
    check($model->getGuarded() === ['*'], 'Mass assignment não protegido: '.$class);
    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $class || $method->getName() === 'role') {
            continue;
        }
        check($method->invoke($model) instanceof Relation, 'Relação inválida: '.$class.'::'.$method->getName());
        $relationCount++;
    }
}

// Eliminar apenas os dados descartáveis em memória antes de testar down().
$database->table('inventories')->delete();
$database->table('products')->delete();
$database->table('categories')->delete();
$database->table('branches')->delete();
$database->table('segments')->delete();
foreach (array_reverse($migrations) as $migration) {
    $migration->down();
}
foreach ($schema as $table) {
    check(! Schema::hasTable($table['table']), 'Rollback incompleto: '.$table['table']);
}
check(! Schema::hasColumn('users', 'active'), 'Rollback da extensão de User incompleto.');

echo count($migrations).' migrations up/down; '.count($schema).' tabelas; '.$relationCount." relações locais; unicidade e FKs: OK\n";
echo "Spatie e concorrência MySQL: validação reservada à integração.\n";

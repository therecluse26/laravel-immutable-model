<?php

declare(strict_types=1);

/*
 * Read benchmark: Eloquent vs ImmutableModel on a real database.
 *
 * It boots Eloquent directly (no Laravel app, no Testbench), so it measures
 * only the query, hydration and serialization paths.
 *
 * Usage (MySQL in Docker):
 *   docker run -d --name bench-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=bench -p 33062:3306 mysql:8.4
 *   DB_PORT=33062 php benchmarks/read-benchmark.php seed
 *   DB_PORT=33062 php -d opcache.enable_cli=1 -d memory_limit=-1 benchmarks/read-benchmark.php
 *
 * Environment: DB_CONNECTION (mysql|pgsql, default mysql), DB_HOST, DB_PORT,
 * DB_DATABASE (default bench), DB_USERNAME, DB_PASSWORD.
 */

namespace Brighten\ImmutableModel\Benchmarks;

require __DIR__ . '/../vendor/autoload.php';

use Brighten\ImmutableModel\ImmutableModel;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use PDO;

$driver = getenv('DB_CONNECTION') ?: 'mysql';
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => $driver,
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306)),
    'database' => getenv('DB_DATABASE') ?: 'bench',
    'username' => getenv('DB_USERNAME') ?: ($driver === 'pgsql' ? 'postgres' : 'root'),
    'password' => getenv('DB_PASSWORD') ?: 'secret',
    'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
    'collation' => $driver === 'pgsql' ? null : 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Both user models: a JSON cast, a datetime cast, and Eloquent's two timestamps.
class EloquentUser extends Model
{
    protected $table = 'users';
    protected $casts = ['settings' => 'array', 'email_verified_at' => 'datetime'];

    public function posts(): HasMany
    {
        return $this->hasMany(EloquentPost::class, 'user_id');
    }
}

class EloquentPost extends Model
{
    protected $table = 'posts';
    protected $casts = ['published' => 'bool'];
}

class ImmutableUser extends ImmutableModel
{
    protected $table = 'users';
    protected $casts = ['settings' => 'array', 'email_verified_at' => 'datetime'];

    public function posts(): HasMany
    {
        return $this->hasMany(ImmutablePost::class, 'user_id');
    }
}

class ImmutablePost extends ImmutableModel
{
    protected $table = 'posts';
    protected $casts = ['published' => 'bool'];
}

if (($argv[1] ?? 'run') === 'seed') {
    $schema = Capsule::schema();
    $schema->dropIfExists('posts');
    $schema->dropIfExists('users');
    $schema->create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->json('settings')->nullable();
        $t->timestamp('email_verified_at')->nullable();
        $t->timestamps();
    });
    $schema->create('posts', function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->index();
        $t->string('title');
        $t->text('body');
        $t->boolean('published')->default(false);
        $t->timestamps();
    });

    $rows = [];
    for ($i = 1; $i <= 100000; $i++) {
        $rows[] = [
            'id' => $i, 'name' => "User {$i}", 'email' => "user{$i}@example.com",
            'settings' => '{"theme":"dark","notifications":true}', 'email_verified_at' => '2024-01-01 00:00:00',
            'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00',
        ];
        if (count($rows) === 2000) {
            Capsule::table('users')->insert($rows);
            $rows = [];
        }
    }

    $postId = 1;
    for ($userId = 1; $userId <= 1000; $userId++) {
        for ($p = 1; $p <= 10; $p++) {
            $rows[] = [
                'id' => $postId++, 'user_id' => $userId, 'title' => "Post {$postId}", 'body' => 'Post body content',
                'published' => true, 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00',
            ];
            if (count($rows) === 2000) {
                Capsule::table('posts')->insert($rows);
                $rows = [];
            }
        }
    }

    printf("Seeded %d users and %d posts.\n", Capsule::table('users')->count(), Capsule::table('posts')->count());
    exit(0);
}

/**
 * Median wall time in milliseconds, after one warm-up call.
 */
function time_ms(callable $fn, int $reps): float
{
    $fn();
    $samples = [];
    for ($i = 0; $i < $reps; $i++) {
        gc_collect_cycles();
        $start = hrtime(true);
        $result = $fn();
        $samples[] = (hrtime(true) - $start) / 1e6;
        unset($result);
    }
    sort($samples);

    return $samples[intdiv(count($samples), 2)];
}

function saved(float $eloquent, float $immutable): string
{
    return sprintf('%+.0f%%', 100 * ($immutable / $eloquent - 1));
}

$pdo = Capsule::connection()->getPdo();

printf(
    "Read benchmark | %s | PHP %s | OPcache %s | JIT %s\n\n",
    $driver,
    PHP_VERSION,
    ini_get('opcache.enable_cli') ? 'on' : 'off',
    ini_get('opcache.jit') && ini_get('opcache.jit') !== 'disable' ? ini_get('opcache.jit') : 'off'
);

echo "Times in ms (median). Hydration = model get() minus query builder get().\n";
printf("%8s | %8s %9s | %9s %9s %6s | %9s %9s %6s | %10s %10s %6s\n",
    'rows', 'pdo', 'qbuilder', 'hydr E', 'hydr I', 'Δ', 'toArr E', 'toArr I', 'Δ', 'get+arr E', 'get+arr I', 'Δ');
foreach ([100 => 200, 1000 => 50, 10000 => 10] as $n => $reps) {
    $pdoMs = time_ms(fn () => $pdo->query("select * from users limit {$n}")->fetchAll(PDO::FETCH_OBJ), $reps);
    $qb = time_ms(fn () => Capsule::table('users')->limit($n)->get(), $reps);
    $e = time_ms(fn () => EloquentUser::query()->limit($n)->get(), $reps);
    $i = time_ms(fn () => ImmutableUser::query()->limit($n)->get(), $reps);

    $eModels = EloquentUser::query()->limit($n)->get();
    $iModels = ImmutableUser::query()->limit($n)->get();
    $ea = time_ms(fn () => $eModels->toArray(), $reps);
    $ia = time_ms(fn () => $iModels->toArray(), $reps);
    unset($eModels, $iModels);

    printf("%8s | %8.2f %9.2f | %9.2f %9.2f %6s | %9.2f %9.2f %6s | %10.2f %10.2f %6s\n",
        number_format($n), $pdoMs, $qb, $e - $qb, $i - $qb, saved($e - $qb, $i - $qb),
        $ea, $ia, saved($ea, $ia), $e + $ea, $i + $ia, saved($e + $ea, $i + $ia));
}

echo "\nEager loading: users with 10 posts each (ms, median)\n";
foreach ([10 => 100, 100 => 40, 1000 => 10] as $n => $reps) {
    $e = time_ms(fn () => EloquentUser::with('posts')->limit($n)->get(), $reps);
    $i = time_ms(fn () => ImmutableUser::with('posts')->limit($n)->get(), $reps);
    printf("%6s users | Eloquent %8.2f | Immutable %8.2f | %s\n", number_format($n), $e, $i, saved($e, $i));
}

echo "\nMemory held by 10,000 hydrated models\n";
foreach (['Eloquent' => EloquentUser::class, 'Immutable' => ImmutableUser::class] as $label => $class) {
    gc_collect_cycles();
    $before = memory_get_usage();
    $models = $class::query()->limit(10000)->get();
    $bytes = memory_get_usage() - $before;
    unset($models);
    printf("%-9s %6.2f MB (%4d bytes per model)\n", $label, $bytes / 1048576, intdiv($bytes, 10000));
}

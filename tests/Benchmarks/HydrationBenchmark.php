<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Benchmarks;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Performance benchmarks comparing ImmutableModel against Eloquent.
 *
 * Run with: php vendor/bin/phpunit tests/Benchmarks/HydrationBenchmark.php
 */
class HydrationBenchmark extends TestCase
{
    private array $benchmarkResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBenchmarkData();
    }

    protected function seedBenchmarkData(): void
    {
        // Seed 10000 users for benchmarking
        $users = [];
        for ($i = 1; $i <= 10000; $i++) {
            $users[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'settings' => json_encode(['theme' => 'dark']),
                'email_verified_at' => '2024-01-01 00:00:00',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ];

            // Insert in batches of 1000
            if (count($users) === 1000) {
                $this->app['db']->table('users')->insert($users);
                $users = [];
            }
        }
    }

    public function test_benchmark_hydration_100(): void
    {
        $this->runHydrationBenchmark(100);
        $this->assertTrue(true); // Placeholder assertion
    }

    public function test_benchmark_hydration_1000(): void
    {
        $this->runHydrationBenchmark(1000);
        $this->assertTrue(true);
    }

    public function test_benchmark_hydration_10000(): void
    {
        $this->runHydrationBenchmark(10000);
        $this->assertTrue(true);
    }

    public function test_benchmark_hydration_100000(): void
    {
        $this->runHydrationBenchmark(100000);
        $this->assertTrue(true);
    }


    public function test_benchmark_memory_usage(): void
    {
        $count = 100000;

        // Benchmark Eloquent memory
        gc_collect_cycles();
        $startMemory = memory_get_usage(false);
        $eloquentModels = BenchmarkEloquentUser::query()->limit($count)->get();
        $eloquentMemory = memory_get_usage(false) - $startMemory;
        unset($eloquentModels);

        // Benchmark Immutable memory
        gc_collect_cycles();
        $startMemory = memory_get_usage(false);
        $immutableModels = BenchmarkImmutableUser::query()->limit($count)->get();
        $immutableMemory = memory_get_usage(false) - $startMemory;
        unset($immutableModels);

        $this->outputResult('Memory Usage', [
            'count' => $count,
            'eloquent_memory' => $this->formatBytes($eloquentMemory),
            'immutable_memory' => $this->formatBytes($immutableMemory),
            'eloquent_per_model' => $this->formatBytes((int) round($eloquentMemory / $count)),
            'immutable_per_model' => $this->formatBytes((int) round($immutableMemory / $count)),
            'savings_percent' => $eloquentMemory > 0
                ? round((1 - ($immutableMemory / $eloquentMemory)) * 100, 2)
                : 0,
        ]);

        $this->assertTrue(true);
    }

    public function test_benchmark_eager_loading(): void
    {
        // Seed posts for eager loading test
        $posts = [];
        for ($i = 1; $i <= 1000; $i++) {
            $posts[] = [
                'id' => $i,
                'user_id' => (($i - 1) % 100) + 1, // Distribute among first 100 users
                'title' => "Post {$i}",
                'body' => 'Post body content',
                'published' => true,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ];

            if (count($posts) === 100) {
                $this->app['db']->table('posts')->insert($posts);
                $posts = [];
            }
        }

        $count = 100;

        // Benchmark Eloquent eager loading
        $start = hrtime(true);
        $eloquentUsers = BenchmarkEloquentUser::with('posts')->limit($count)->get();
        $eloquentTime = (hrtime(true) - $start) / 1e6;

        // Benchmark Immutable eager loading
        $start = hrtime(true);
        $immutableUsers = BenchmarkImmutableUser::with('posts')->limit($count)->get();
        $immutableTime = (hrtime(true) - $start) / 1e6;

        $this->outputResult('Eager Loading', [
            'count' => $count,
            'eloquent_ms' => round($eloquentTime, 2),
            'immutable_ms' => round($immutableTime, 2),
            'difference_percent' => $eloquentTime > 0
                ? round((($immutableTime - $eloquentTime) / $eloquentTime) * 100, 2)
                : 0,
        ]);

        $this->assertTrue(true);
    }

    private function runHydrationBenchmark(int $count): void
    {
        $iterations = 5;

        // Warm up
        BenchmarkEloquentUser::query()->limit(10)->get();
        BenchmarkImmutableUser::query()->limit(10)->get();

        // Benchmark Eloquent
        $eloquentTimes = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            BenchmarkEloquentUser::query()->limit($count)->get();
            $eloquentTimes[] = (hrtime(true) - $start) / 1e6; // Convert to ms
        }
        $eloquentAvg = array_sum($eloquentTimes) / count($eloquentTimes);

        // Benchmark Immutable
        $immutableTimes = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            BenchmarkImmutableUser::query()->limit($count)->get();
            $immutableTimes[] = (hrtime(true) - $start) / 1e6;
        }
        $immutableAvg = array_sum($immutableTimes) / count($immutableTimes);

        $this->outputResult("Hydration ({$count} rows)", [
            'count' => $count,
            'eloquent_ms' => round($eloquentAvg, 2),
            'immutable_ms' => round($immutableAvg, 2),
            'difference_percent' => $eloquentAvg > 0
                ? round((($immutableAvg - $eloquentAvg) / $eloquentAvg) * 100, 2)
                : 0,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return round($value, 2) . ' ' . $units[$unitIndex];
    }

    private function outputResult(string $name, array $data): void
    {
        $this->benchmarkResults[$name] = $data;

        // Output to console
        fwrite(STDERR, "\n┌─ {$name} ─────────────────────────────────\n");
        foreach ($data as $key => $value) {
            $formatted = is_numeric($value)
                ? number_format($value, is_float($value) ? 2 : 0)
                : $value;
            fwrite(STDERR, "│ {$key}: {$formatted}\n");
        }
        fwrite(STDERR, "└──────────────────────────────────────────\n");
    }

    protected function tearDown(): void
    {
        if (! empty($this->benchmarkResults)) {
            fwrite(STDERR, "\n\n╔══════════════════════════════════════════════════════════╗\n");
            fwrite(STDERR, "║                  BENCHMARK SUMMARY                       ║\n");
            fwrite(STDERR, "╠══════════════════════════════════════════════════════════╣\n");
            fwrite(STDERR, "║ Test                    │ Eloquent   │ Immutable │ Diff  ║\n");
            fwrite(STDERR, "╟─────────────────────────┼────────────┼───────────┼───────╢\n");

            foreach ($this->benchmarkResults as $name => $data) {
                $name = substr($name, 0, 22);
                $diff = $data['difference_percent'] ?? $data['savings_percent'] ?? 0;
                $diffStr = ($diff >= 0 ? '+' : '') . $diff . '%';

                if (isset($data['eloquent_ms'])) {
                    fwrite(STDERR, sprintf(
                        "║ %-23s │ %8.2fms │ %7.2fms │ %5s ║\n",
                        $name,
                        $data['eloquent_ms'],
                        $data['immutable_ms'],
                        $diffStr
                    ));
                } elseif (isset($data['eloquent_memory'])) {
                    fwrite(STDERR, sprintf(
                        "║ %-23s │ %10s │ %9s │ %5s ║\n",
                        $name,
                        $data['eloquent_memory'],
                        $data['immutable_memory'],
                        $diffStr
                    ));
                }
            }

            fwrite(STDERR, "╚══════════════════════════════════════════════════════════╝\n\n");
        }

        parent::tearDown();
    }
}

/**
 * Eloquent model for benchmarking.
 */
class BenchmarkEloquentUser extends EloquentModel
{
    protected $table = 'users';

    protected $casts = [
        'settings' => 'array',
        'email_verified_at' => 'datetime',
    ];

    public function posts()
    {
        return $this->hasMany(BenchmarkEloquentPost::class, 'user_id');
    }
}

class BenchmarkEloquentPost extends EloquentModel
{
    protected $table = 'posts';

    protected $casts = [
        'published' => 'bool',
    ];

    public function user()
    {
        return $this->belongsTo(BenchmarkEloquentUser::class, 'user_id');
    }
}

/**
 * Immutable model for benchmarking.
 */
class BenchmarkImmutableUser extends ImmutableModel
{
    protected string $table = 'users';

    protected ?string $primaryKey = 'id';

    protected array $casts = [
        'settings' => 'array',
        'email_verified_at' => 'datetime',
    ];

    public function posts()
    {
        return $this->hasMany(BenchmarkImmutablePost::class);
    }
}

class BenchmarkImmutablePost extends ImmutableModel
{
    protected string $table = 'posts';

    protected ?string $primaryKey = 'id';

    protected array $casts = [
        'user_id' => 'int',
        'published' => 'bool',
    ];

    public function user()
    {
        return $this->belongsTo(BenchmarkImmutableUser::class);
    }
}

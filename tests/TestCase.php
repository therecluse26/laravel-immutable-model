<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests;

use Brighten\ImmutableModel\ImmutableModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Drop views as well as tables when RefreshDatabase runs migrate:fresh.
     * Without it, a view left by an earlier run on MySQL/Postgres makes
     * every migration fail.
     *
     * @var bool
     */
    protected $dropViews = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up the connection resolver for ImmutableModel
        ImmutableModel::setConnectionResolver($this->app['db']);
    }

    /**
     * Register the test migrations with the migrator.
     *
     * Only the path is registered, so RefreshDatabase migrates once per run
     * and wraps each test in a transaction. loadMigrationsFrom() would roll
     * the migrations back after tests, which rebuilds every table on MySQL
     * and Postgres and makes the suite take over an hour.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->app['migrator']->path(__DIR__ . '/database/migrations');
    }

    /**
     * Use SQLite in memory by default.
     *
     * Set DB_CONNECTION=mysql or DB_CONNECTION=pgsql (plus DB_HOST, DB_PORT,
     * DB_DATABASE, DB_USERNAME, DB_PASSWORD) to run the suite on another
     * database. Testbench's default connection configs read those variables.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $connection = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', $connection);

        if ($connection === 'sqlite') {
            $app['config']->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }
    }

    /**
     * Run the database migrations for testing.
     */
    protected function setUpDatabase(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        // Users table
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->json('settings')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        // Posts table (user hasMany posts)
        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
            $table->text('body');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        // Comments table (post hasMany comments, user hasMany comments)
        $schema->create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });

        // Profiles table (user hasOne profile)
        $schema->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('bio')->nullable();
            $table->date('birthday')->nullable();
        });
    }
}

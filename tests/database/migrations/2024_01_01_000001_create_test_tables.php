<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->json('settings')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        // Categories table (must be created before posts for FK constraint)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title');
            $table->text('body');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('bio')->nullable();
            $table->date('birthday')->nullable();
        });

        // Tags table (mutable model for testing)
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Post meta table (mutable hasMany target from ImmutablePost)
        Schema::create('post_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->string('key');
            $table->text('value')->nullable();
        });

        // User settings table (mutable hasOne target from ImmutableUser)
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('theme')->nullable();
            $table->boolean('notifications_enabled')->default(true);
        });

        // =========================================================================
        // Tables for testing new relationship types
        // =========================================================================

        // Pivot table for BelongsToMany (posts <-> tags)
        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->integer('order')->nullable();
            $table->timestamps();
            $table->primary(['post_id', 'tag_id']);
        });

        // Polymorphic pivot table for MorphToMany (taggables)
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->morphs('taggable');
            $table->timestamps();
        });

        // Images table for MorphOne/MorphMany testing
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('imageable');
            $table->string('path');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        // Countries table for HasManyThrough testing
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Suppliers table (intermediate for Country -> Supplier -> User)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Add supplier_id to users for HasManyThrough testing
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('email_verified_at')->constrained()->onDelete('set null');
        });

        // =========================================================================
        // Tables for edge case testing (self-referential, UUID, deep nesting, etc.)
        // =========================================================================

        // Self-referential categories table
        Schema::create('immutable_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('depth')->default(0);
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('immutable_categories')->onDelete('set null');
        });

        // UUID products table (for non-incrementing primary key testing)
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('sku')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Orders table (for deep nesting: Country -> Supplier -> User -> Order)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });

        // Order items table (for 5-level deep nesting)
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->uuid('product_uuid')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->foreign('product_uuid')->references('uuid')->on('products')->onDelete('set null');
        });

        // Videos table (additional polymorphic target)
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('url');
            $table->timestamps();
        });

        // Articles table (with soft deletes for comprehensive testing)
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Database view for view-backed model testing
        // SQLite supports basic CREATE VIEW
        \Illuminate\Support\Facades\DB::statement('CREATE VIEW user_post_counts AS
            SELECT users.id as user_id, users.name, COUNT(posts.id) as post_count
            FROM users
            LEFT JOIN posts ON users.id = posts.user_id
            GROUP BY users.id, users.name');
    }

    public function down(): void
    {
        // Drop view first
        \Illuminate\Support\Facades\DB::statement('DROP VIEW IF EXISTS user_post_counts');

        // Drop edge case tables
        Schema::dropIfExists('articles');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('immutable_categories');

        // Drop supplier_id from users first
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        // Drop new tables in reverse order
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('images');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('post_tag');

        // Original tables
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('post_meta');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
    }
};

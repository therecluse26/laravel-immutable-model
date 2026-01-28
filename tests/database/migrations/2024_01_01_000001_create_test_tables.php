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
    }

    public function down(): void
    {
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

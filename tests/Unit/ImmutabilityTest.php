<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;

class ImmutabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        $this->app['db']->table('users')->insert([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'settings' => null,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $this->app['db']->table('posts')->insert([
            'id' => 1,
            'user_id' => 1,
            'title' => 'Test Post',
            'body' => 'Post body',
            'published' => true,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);
    }

    // =========================================================================
    // ATTRIBUTE MUTATION TESTS
    // =========================================================================

    public function test_setting_attribute_via_property_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot set attribute [name]');

        $user->name = 'New Name';
    }

    public function test_setting_attribute_via_array_access_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot set attribute [name]');

        $user['name'] = 'New Name';
    }

    public function test_unsetting_attribute_via_unset_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot set attribute [name]');

        unset($user->name);
    }

    public function test_unsetting_attribute_via_array_access_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot set attribute [name]');

        unset($user['name']);
    }

    // =========================================================================
    // RELATION MUTATION TESTS
    // =========================================================================

    public function test_setting_relation_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot set relation [posts]');

        $user->setRelation('posts', new ImmutableCollection([]));
    }

    // =========================================================================
    // PERSISTENCE METHOD TESTS
    // =========================================================================

    public function test_save_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [save]');

        $user->save();
    }

    public function test_update_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [update]');

        $user->update(['name' => 'New Name']);
    }

    public function test_delete_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [delete]');

        $user->delete();
    }

    public function test_create_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [create]');

        ImmutableUser::create(['name' => 'Test']);
    }

    public function test_fill_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [fill]');

        $user->fill(['name' => 'New Name']);
    }

    public function test_push_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [push]');

        $user->push();
    }

    public function test_touch_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [touch]');

        $user->touch();
    }

    public function test_increment_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [increment]');

        $user->increment('id');
    }

    public function test_decrement_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [decrement]');

        $user->decrement('id');
    }

    public function test_force_delete_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [forceDelete]');

        $user->forceDelete();
    }

    public function test_restore_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [restore]');

        $user->restore();
    }

    // =========================================================================
    // QUERY BUILDER MUTATION TESTS
    // =========================================================================

    public function test_query_insert_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [insert]');

        ImmutableUser::query()->insert(['name' => 'Test']);
    }

    public function test_query_update_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [update]');

        ImmutableUser::query()->update(['name' => 'Test']);
    }

    public function test_query_delete_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [delete]');

        ImmutableUser::query()->delete();
    }

    public function test_query_upsert_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [upsert]');

        ImmutableUser::query()->upsert([['name' => 'Test']], ['id']);
    }

    public function test_query_truncate_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [truncate]');

        ImmutableUser::query()->truncate();
    }

    public function test_query_increment_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [increment]');

        ImmutableUser::query()->where('id', 1)->increment('id');
    }

    public function test_query_decrement_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [decrement]');

        ImmutableUser::query()->where('id', 1)->decrement('id');
    }

    public function test_query_force_delete_throws(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [forceDelete]');

        ImmutableUser::query()->forceDelete();
    }

    // =========================================================================
    // COLLECTION MUTATION TESTS
    // =========================================================================

    public function test_collection_push_throws(): void
    {
        $users = ImmutableUser::all();

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [push]');

        $users->push(ImmutableUser::find(1));
    }

    public function test_collection_put_throws(): void
    {
        $users = ImmutableUser::all();

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [put]');

        $users->put(0, ImmutableUser::find(1));
    }

    public function test_collection_forget_throws(): void
    {
        $users = ImmutableUser::all();

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [forget]');

        $users->forget(0);
    }

    public function test_collection_pop_throws(): void
    {
        $users = ImmutableUser::all();

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [pop]');

        $users->pop();
    }

    public function test_collection_shift_throws(): void
    {
        $users = ImmutableUser::all();

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [shift]');

        $users->shift();
    }

    public function test_collection_offset_set_throws(): void
    {
        $users = ImmutableUser::all();

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [offsetSet]');

        $users[0] = ImmutableUser::find(1);
    }

    public function test_collection_offset_unset_throws(): void
    {
        $users = ImmutableUser::all();

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [offsetUnset]');

        unset($users[0]);
    }

    public function test_collection_transform_throws(): void
    {
        $users = ImmutableUser::all();

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [transform]');

        $users->transform(fn($user) => $user);
    }

    // =========================================================================
    // RELATION BUILDER MUTATION TESTS
    // =========================================================================

    public function test_relation_create_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [create]');

        $user->posts()->create(['title' => 'Test']);
    }

    public function test_relation_save_throws(): void
    {
        $user = ImmutableUser::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $this->expectExceptionMessage('Cannot call [save]');

        $user->posts()->save(ImmutablePost::find(1));
    }
}

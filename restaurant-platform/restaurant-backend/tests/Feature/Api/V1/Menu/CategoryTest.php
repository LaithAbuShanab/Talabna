<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Menu;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_active_categories(): void
    {
        Category::factory()->create(['name' => 'Active One', 'sort_order' => 1]);
        Category::factory()->inactive()->create(['name' => 'Hidden One']);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name.en', 'Active One');
    }

    public function test_categories_are_bilingual_with_fallback_when_arabic_is_missing(): void
    {
        Category::factory()->create([
            'name' => 'Burgers',
            'name_ar' => 'برجر',
            'description' => 'Tasty burgers',
            'description_ar' => null,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonPath('data.0.name.en', 'Burgers')
            ->assertJsonPath('data.0.name.ar', 'برجر')
            ->assertJsonPath('data.0.description.en', 'Tasty burgers')
            ->assertJsonPath('data.0.description.ar', 'Tasty burgers');
    }

    public function test_categories_are_ordered_by_sort_order(): void
    {
        Category::factory()->create(['name' => 'Second', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'First', 'sort_order' => 1]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertJsonPath('data.0.name.en', 'First')
            ->assertJsonPath('data.1.name.en', 'Second');
    }

    public function test_category_list_is_served_from_cache_on_subsequent_requests(): void
    {
        Category::factory()->count(3)->create();

        DB::enableQueryLog();
        $this->getJson('/api/v1/categories')->assertOk();
        DB::flushQueryLog();

        $this->getJson('/api/v1/categories')->assertOk();

        $this->assertSame([], DB::getQueryLog());
    }
}

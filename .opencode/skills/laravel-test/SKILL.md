---
name: laravel-test
description: Use when running tests, writing new tests, debugging test failures, or checking test coverage in this project. Front-load keywords: run tests, phpunit, write test, test failure, assertion, factory, test case, php artisan test.
---

# Laravel Test Skill

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test File
```bash
php artisan test --filter=SalesOrderTest
```

### Run Specific Test Method
```bash
php artisan test --filter=SalesOrderTest::test_can_create_sales_order
```

### Run with Verbose Output
```bash
php artisan test -v
```

### Run with Coverage (if configured)
```bash
php artisan test --coverage
```

## Project Test Structure

- Tests are in `tests/` directory
- Feature tests in `tests/Feature/`
- Unit tests in `tests/Unit/`
- Uses PHPUnit (configured in `phpunit.xml`)
- Factories in `database/factories/`

## Writing Tests

### Standard Feature Test Template

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\YourModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

class YourModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with permissions
        $this->user = User::factory()->create();
        $this->user->assignRole('admin'); // or specific role
    }

    public function test_can_list_models(): void
    {
        // Arrange
        YourModel::factory()->count(3)->create();

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/your-models');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_model(): void
    {
        // Arrange
        $data = [
            'name' => 'Test Model',
            'description' => 'Test Description',
        ];

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/your-models', $data);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('your_models', ['name' => 'Test Model']);
    }

    public function test_can_show_model(): void
    {
        // Arrange
        $model = YourModel::factory()->create();

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/your-models/{$model->id}");

        // Assert
        $response->assertOk();
        $response->assertJsonFragment(['id' => $model->id]);
    }

    public function test_can_update_model(): void
    {
        // Arrange
        $model = YourModel::factory()->create();
        $data = ['name' => 'Updated Name'];

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/your-models/{$model->id}", $data);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('your_models', ['name' => 'Updated Name']);
    }

    public function test_can_delete_model(): void
    {
        // Arrange
        $model = YourModel::factory()->create();

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/your-models/{$model->id}");

        // Assert
        $response->assertOk();
        $this->assertDatabaseMissing('your_models', ['id' => $model->id]);
    }

    public function test_cannot_create_model_without_required_fields(): void
    {
        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/your-models', []);

        // Assert
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }
}
```

### Authentication in Tests

```php
// Sanctum token authentication
$this->actingAs($this->user, 'sanctum');

// With specific ability
$token = $this->user->createToken('test', ['warehouse'])->plainTextToken;
$this->withHeader('Authorization', "Bearer {$token}");
```

### Factory Pattern

```php
// Using factory
$model = YourModel::factory()->create();
$model = YourModel::factory()->count(5)->create();
$model = YourModel::factory()->create(['name' => 'Custom Name']);

// Related models
$model = YourModel::factory()
    ->has(OrderDetail::factory()->count(3), 'details')
    ->create();
```

### Database Assertions

```php
// Check record exists
$this->assertDatabaseHas('table', ['column' => 'value']);

// Check record does not exist
$this->assertDatabaseMissing('table', ['column' => 'value']);

// Check record count
$this->assertDatabaseCount('table', 5);

// Check soft delete
$this->assertSoftDeleted('table', ['id' => $model->id]);
```

## Common Test Patterns

### Testing API Endpoints
```php
// GET (list)
$response = $this->actingAs($user, 'sanctum')
    ->getJson('/api/endpoint');
$response->assertOk();

// POST (create)
$response = $this->actingAs($user, 'sanctum')
    ->postJson('/api/endpoint', $data);
$response->assertCreated();

// PUT (update)
$response = $this->actingAs($user, 'sanctum')
    ->putJson('/api/endpoint/' . $id, $data);
$response->assertOk();

// DELETE
$response = $this->actingAs($user, 'sanctum')
    ->deleteJson('/api/endpoint/' . $id);
$response->assertOk();
```

### Testing Tenanted Models
```php
// Create user with warehouse assignment
$user = User::factory()->create();
$warehouse = Warehouse::factory()->create();
$user->warehouses()->attach($warehouse->id);

// Create model in user's warehouse
$model = YourModel::factory()->create(['warehouse_id' => $warehouse->id]);

// Test tenanted query
$response = $this->actingAs($user, 'sanctum')
    ->getJson('/api/your-models');
$response->assertOk();
```

### Testing Events & Listeners
```php
use Illuminate\Support\Facades\Event;

Event::fake();

// Perform action that should fire event
YourModel::create($data);

Event::assertDispatched(YourEvent::class);
```

## After Writing Tests

1. Run `php artisan test` to verify all tests pass
2. Run `./vendor/bin/pint` to format code
3. Check test coverage if needed

# Room Booking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a room-booking app — Laravel 12 REST API (Sanctum auth) with a Vue 3 SPA — where users register, browse rooms, and create/cancel bookings with time-overlap and capacity validation.

**Architecture:** Backend layered as Controller → FormRequest → Action → Repository, with readonly DTOs for write operations and a string-backed `BookingStatus` enum. API versioned by directory (`Api\V1`); URLs unversioned. Frontend is a token-based SPA in `resources/js` (Vue 3 Composition API + Pinia + Tailwind v4), served by Laravel Vite, talking to `/api` with a Bearer token in `localStorage`.

**Tech Stack:** PHP 8.4, Laravel 12, Sanctum, MySQL (SQLite in-memory for tests), Vue 3, vue-router, Pinia, Tailwind v4, axios, PHPUnit.

**Conventions confirmed in this codebase:**
- `User` model casts `password => 'hashed'` — assign plain passwords, never `Hash::make()` before assignment.
- Tests run on SQLite `:memory:` (see `phpunit.xml`); use `RefreshDatabase`.
- `routes/api.php` is registered (`bootstrap/app.php`) under the `/api` prefix.
- Cast definitions go in a `casts()` method, not a `$casts` property.
- Run `vendor/bin/pint --dirty --format agent` after touching PHP files.

---

## Task 0: Initialize git

The project is not yet a git repository, but this plan relies on frequent commits.

- [ ] **Step 1: Initialize and make the baseline commit**

```bash
cd /Users/marcin/code/booking
git init
git add -A
git commit -m "chore: baseline before room booking feature"
```

- [ ] **Step 2: Verify**

Run: `git log --oneline`
Expected: one commit listed.

---

## Task 1: Domain foundation (enum, models, migrations, factories)

**Files:**
- Create: `app/Enums/BookingStatus.php`
- Create: `app/Models/Room.php`
- Create: `app/Models/Booking.php`
- Create: `database/migrations/XXXX_create_rooms_table.php`
- Create: `database/migrations/XXXX_create_bookings_table.php`
- Create: `database/factories/RoomFactory.php`
- Create: `database/factories/BookingFactory.php`
- Test: `tests/Feature/DomainModelTest.php`

- [ ] **Step 1: Generate scaffolding files**

```bash
php artisan make:enum Enums/BookingStatus --no-interaction
php artisan make:model Room -mf --no-interaction
php artisan make:model Booking -mf --no-interaction
```

(If `make:enum` is unavailable, create `app/Enums/BookingStatus.php` by hand with the content from Step 2.)

- [ ] **Step 2: Write the enum**

`app/Enums/BookingStatus.php`:

```php
<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * Statuses that occupy a room's time slot.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::active());
    }
}
```

- [ ] **Step 3: Write the migrations**

`database/migrations/XXXX_create_rooms_table.php` — `up()`:

```php
Schema::create('rooms', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedInteger('capacity');
    $table->timestamps();
});
```

`database/migrations/XXXX_create_bookings_table.php` — `up()`:

```php
Schema::create('bookings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('room_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->dateTime('starts_at');
    $table->dateTime('ends_at');
    $table->unsignedInteger('participants_count');
    $table->string('status')->default('confirmed');
    $table->timestamps();

    $table->index(['room_id', 'starts_at', 'ends_at']);
});
```

(Ensure the bookings migration filename sorts after the rooms one so `room_id`'s FK target exists.)

- [ ] **Step 4: Write the models**

`app/Models/Room.php`:

```php
<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'capacity'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
```

`app/Models/Booking.php`:

```php
<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'room_id',
        'user_id',
        'starts_at',
        'ends_at',
        'participants_count',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'participants_count' => 'integer',
            'status' => BookingStatus::class,
        ];
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Write the factories**

`database/factories/RoomFactory.php` — `definition()`:

```php
public function definition(): array
{
    return [
        'name' => fake()->unique()->words(2, true),
        'capacity' => fake()->numberBetween(2, 20),
    ];
}
```

`database/factories/BookingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+2 weeks');
        $endsAt = (clone $startsAt)->modify('+1 hour');

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participants_count' => 2,
            'status' => BookingStatus::Confirmed,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Cancelled,
        ]);
    }
}
```

- [ ] **Step 6: Write the failing test**

`tests/Feature/DomainModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_casts_status_to_enum_and_belongs_to_room(): void
    {
        $room = Room::factory()->create(['capacity' => 5]);
        $booking = Booking::factory()->for($room)->create();

        $this->assertInstanceOf(BookingStatus::class, $booking->status);
        $this->assertSame($room->id, $booking->room->id);
        $this->assertSame(5, $room->capacity);
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/DomainModelTest.php`
Expected: PASS (migrations apply on the in-memory DB, casts and relations resolve).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add room and booking domain models, enum, migrations, factories"
```

---

## Task 2: Language files

**Files:**
- Run: `php artisan lang:publish`
- Modify: `lang/en/auth.php`
- Create: `lang/en/bookings.php`

- [ ] **Step 1: Publish base language files**

```bash
php artisan lang:publish
```

Expected: creates `lang/en/{auth,pagination,passwords,validation}.php`.

- [ ] **Step 2: Add auth messages**

In `lang/en/auth.php`, the returned array already has `'failed'`, `'password'`, `'throttle'`. Add two keys to the same array:

```php
'registered' => 'Registration successful.',
'logged_out' => 'Logged out successfully.',
```

- [ ] **Step 3: Create domain messages**

`lang/en/bookings.php`:

```php
<?php

return [
    'room_unavailable' => 'The room is already booked for the selected time range.',
    'capacity_exceeded' => 'The number of participants exceeds the room capacity.',
];
```

- [ ] **Step 4: Verify keys resolve**

Run: `php artisan tinker --execute 'echo __("bookings.room_unavailable").PHP_EOL; echo __("auth.logged_out");'`
Expected: prints both English strings (not the key names).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add language files for auth and booking messages"
```

---

## Task 3: Base controller authorization + DTOs + repositories

These are pure scaffolding used by later tasks. They are exercised by the endpoint feature tests in Tasks 4–9.

**Files:**
- Modify: `app/Http/Controllers/Controller.php`
- Create: `app/DTO/RegisterUserData.php`
- Create: `app/DTO/CreateBookingData.php`
- Create: `app/Repositories/RoomRepository.php`
- Create: `app/Repositories/BookingRepository.php`
- Test: `tests/Feature/BookingRepositoryTest.php`

- [ ] **Step 1: Add the AuthorizesRequests trait to the base controller**

`app/Http/Controllers/Controller.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
```

- [ ] **Step 2: Create the DTOs**

```bash
php artisan make:class DTO/RegisterUserData --no-interaction
php artisan make:class DTO/CreateBookingData --no-interaction
```

`app/DTO/RegisterUserData.php`:

```php
<?php

namespace App\DTO;

class RegisterUserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {}
}
```

`app/DTO/CreateBookingData.php`:

```php
<?php

namespace App\DTO;

class CreateBookingData
{
    public function __construct(
        public readonly int $roomId,
        public readonly int $userId,
        public readonly string $startsAt,
        public readonly string $endsAt,
        public readonly int $participantsCount,
    ) {}
}
```

- [ ] **Step 3: Create the repositories**

```bash
php artisan make:class Repositories/RoomRepository --no-interaction
php artisan make:class Repositories/BookingRepository --no-interaction
```

`app/Repositories/RoomRepository.php`:

```php
<?php

namespace App\Repositories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

class RoomRepository
{
    /**
     * @return Collection<int, Room>
     */
    public function all(): Collection
    {
        return Room::query()->orderBy('name')->get();
    }

    public function find(int $id): ?Room
    {
        return Room::find($id);
    }
}
```

`app/Repositories/BookingRepository.php`:

```php
<?php

namespace App\Repositories;

use App\DTO\CreateBookingData;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

class BookingRepository
{
    /**
     * @return Collection<int, Booking>
     */
    public function forUser(int $userId): Collection
    {
        return Booking::query()
            ->with('room')
            ->where('user_id', $userId)
            ->orderByDesc('starts_at')
            ->get();
    }

    /**
     * Half-open overlap [start, end): adjacent bookings do not conflict.
     */
    public function hasActiveOverlap(int $roomId, string $startsAt, string $endsAt): bool
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->whereIn('status', BookingStatus::activeValues())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    public function create(CreateBookingData $data): Booking
    {
        return Booking::create([
            'room_id' => $data->roomId,
            'user_id' => $data->userId,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'participants_count' => $data->participantsCount,
            'status' => BookingStatus::Confirmed,
        ]);
    }

    public function markCancelled(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::Cancelled]);

        return $booking;
    }
}
```

- [ ] **Step 4: Write the failing test**

`tests/Feature/BookingRepositoryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Repositories\BookingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlap_detection_ignores_adjacent_and_cancelled_bookings(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->for($room)->create([
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
        ]);
        Booking::factory()->cancelled()->for($room)->create([
            'starts_at' => '2026-07-01 13:00:00',
            'ends_at' => '2026-07-01 14:00:00',
        ]);

        $repository = app(BookingRepository::class);

        // Overlapping active booking.
        $this->assertTrue($repository->hasActiveOverlap($room->id, '2026-07-01 10:30:00', '2026-07-01 11:30:00'));
        // Adjacent (touching) is allowed.
        $this->assertFalse($repository->hasActiveOverlap($room->id, '2026-07-01 11:00:00', '2026-07-01 12:00:00'));
        // Cancelled booking does not block.
        $this->assertFalse($repository->hasActiveOverlap($room->id, '2026-07-01 13:00:00', '2026-07-01 14:00:00'));
    }
}
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact tests/Feature/BookingRepositoryTest.php`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add DTOs, repositories, and authorizable base controller"
```

---

## Task 4: Register endpoint (TDD)

**Files:**
- Create: `app/Actions/Auth/RegisterUserAction.php`
- Create: `app/Http/Requests/Api/V1/Auth/RegisterRequest.php`
- Create: `app/Http/Resources/Api/V1/UserResource.php`
- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Auth/RegisterTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/Auth/RegisterTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Auth/RegisterTest.php`
Expected: FAIL (route `/api/register` not defined → 404).

- [ ] **Step 3: Create the action, request, resource, controller**

```bash
php artisan make:request Api/V1/Auth/RegisterRequest --no-interaction
php artisan make:resource Api/V1/UserResource --no-interaction
php artisan make:controller Api/V1/AuthController --no-interaction
php artisan make:class Actions/Auth/RegisterUserAction --no-interaction
```

`app/Actions/Auth/RegisterUserAction.php`:

```php
<?php

namespace App\Actions\Auth;

use App\DTO\RegisterUserData;
use App\Models\User;

class RegisterUserAction
{
    /**
     * @return array{user: User, token: string}
     */
    public function execute(RegisterUserData $data): array
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password, // hashed by the model's 'hashed' cast
        ]);

        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }
}
```

`app/Http/Requests/Api/V1/Auth/RegisterRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
```

`app/Http/Resources/Api/V1/UserResource.php` — `toArray()`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
    ];
}
```

`app/Http/Controllers/Api/V1/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RegisterUserAction;
use App\DTO\RegisterUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->execute(new RegisterUserData(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        ));

        return response()->json([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ], 201);
    }
}
```

- [ ] **Step 4: Register the route**

Replace the body of `routes/api.php` with:

```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
```

(Later tasks extend this file; full final version is shown in Task 8.)

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Auth/RegisterTest.php`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add register endpoint"
```

---

## Task 5: Login endpoint (TDD)

**Files:**
- Create: `app/Actions/Auth/LoginUserAction.php`
- Create: `app/Http/Requests/Api/V1/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Auth/LoginTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/Auth/LoginTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Auth/LoginTest.php`
Expected: FAIL (route `/api/login` not defined → 404).

- [ ] **Step 3: Create the action and request, extend the controller**

```bash
php artisan make:request Api/V1/Auth/LoginRequest --no-interaction
php artisan make:class Actions/Auth/LoginUserAction --no-interaction
```

`app/Actions/Auth/LoginUserAction.php`:

```php
<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    /**
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function execute(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }
}
```

`app/Http/Requests/Api/V1/Auth/LoginRequest.php` — `authorize()` returns `true`, `rules()`:

```php
public function rules(): array
{
    return [
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ];
}
```

Add to `app/Http/Controllers/Api/V1/AuthController.php` (new imports + method):

```php
use App\Actions\Auth\LoginUserAction;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
```

```php
public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
{
    $result = $action->execute(
        $request->string('email')->toString(),
        $request->string('password')->toString(),
    );

    return response()->json([
        'user' => UserResource::make($result['user']),
        'token' => $result['token'],
    ]);
}
```

- [ ] **Step 4: Add the route**

In `routes/api.php`, add the import and route:

```php
Route::post('/login', [AuthController::class, 'login']);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Auth/LoginTest.php`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add login endpoint"
```

---

## Task 6: Logout endpoint (TDD)

**Files:**
- Create: `app/Actions/Auth/LogoutUserAction.php`
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Auth/LogoutTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/Auth/LogoutTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJsonStructure(['message']);
    }

    public function test_guest_cannot_logout(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Auth/LogoutTest.php`
Expected: FAIL (route not defined).

- [ ] **Step 3: Create the action and extend the controller**

```bash
php artisan make:class Actions/Auth/LogoutUserAction --no-interaction
```

`app/Actions/Auth/LogoutUserAction.php`:

```php
<?php

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUserAction
{
    public function execute(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
```

Add to `AuthController` (imports + method):

```php
use App\Actions\Auth\LogoutUserAction;
use Illuminate\Http\Request;
```

```php
public function logout(Request $request, LogoutUserAction $action): JsonResponse
{
    $action->execute($request->user());

    return response()->json(['message' => __('auth.logged_out')]);
}
```

- [ ] **Step 4: Add the protected route**

In `routes/api.php`, add a Sanctum group (this group is extended by later tasks):

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Auth/LogoutTest.php`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add logout endpoint"
```

---

## Task 7: Rooms listing endpoint (TDD)

**Files:**
- Create: `app/Http/Resources/Api/V1/RoomResource.php`
- Create: `app/Http/Controllers/Api/V1/RoomController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/RoomTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/RoomTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_rooms(): void
    {
        Room::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'capacity']]]);
    }

    public function test_guest_cannot_list_rooms(): void
    {
        $this->getJson('/api/rooms')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/RoomTest.php`
Expected: FAIL (route not defined).

- [ ] **Step 3: Create the resource and controller**

```bash
php artisan make:resource Api/V1/RoomResource --no-interaction
php artisan make:controller Api/V1/RoomController --no-interaction
```

`app/Http/Resources/Api/V1/RoomResource.php` — `toArray()`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'capacity' => $this->capacity,
    ];
}
```

`app/Http/Controllers/Api/V1/RoomController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoomResource;
use App\Repositories\RoomRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoomController extends Controller
{
    public function index(RoomRepository $rooms): AnonymousResourceCollection
    {
        return RoomResource::collection($rooms->all());
    }
}
```

- [ ] **Step 4: Add the route**

Inside the existing `auth:sanctum` group in `routes/api.php`, add (with the import):

```php
Route::get('/rooms', [RoomController::class, 'index']);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/RoomTest.php`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add rooms listing endpoint"
```

---

## Task 8: Create + list bookings endpoints (TDD)

**Files:**
- Create: `app/Actions/Booking/CreateBookingAction.php`
- Create: `app/Actions/Booking/ListUserBookingsAction.php`
- Create: `app/Http/Requests/Api/V1/Booking/StoreBookingRequest.php`
- Create: `app/Http/Resources/Api/V1/BookingResource.php`
- Create: `app/Http/Controllers/Api/V1/BookingController.php`
- Modify: `routes/api.php` (final form below)
- Test: `tests/Feature/BookingTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/BookingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_booking_for_available_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
            'participants_count' => 4,
        ])->assertCreated()->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_booking_fails_when_time_overlaps_active_booking(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->for($room)->create([
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:30:00',
            'ends_at' => '2026-07-01 11:30:00',
            'participants_count' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('starts_at');
    }

    public function test_adjacent_booking_is_allowed(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->for($room)->create([
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 11:00:00',
            'ends_at' => '2026-07-01 12:00:00',
            'participants_count' => 2,
        ])->assertCreated();
    }

    public function test_cancelled_booking_does_not_block_slot(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->cancelled()->for($room)->create([
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
            'participants_count' => 2,
        ])->assertCreated();
    }

    public function test_booking_fails_when_participants_exceed_capacity(): void
    {
        $room = Room::factory()->create(['capacity' => 3]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 10:00:00',
            'ends_at' => '2026-07-01 11:00:00',
            'participants_count' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('participants_count');
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-07-01 11:00:00',
            'ends_at' => '2026-07-01 10:00:00',
            'participants_count' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_user_only_sees_their_own_bookings(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);
        Booking::factory()->for($user)->for($room)->create();
        Booking::factory()->for($other)->for($room)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/BookingTest.php`
Expected: FAIL (route not defined).

- [ ] **Step 3: Create the actions**

```bash
php artisan make:class Actions/Booking/CreateBookingAction --no-interaction
php artisan make:class Actions/Booking/ListUserBookingsAction --no-interaction
```

`app/Actions/Booking/CreateBookingAction.php`:

```php
<?php

namespace App\Actions\Booking;

use App\DTO\CreateBookingData;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use App\Repositories\RoomRepository;
use Illuminate\Validation\ValidationException;

class CreateBookingAction
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly RoomRepository $rooms,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(CreateBookingData $data): Booking
    {
        $room = $this->rooms->find($data->roomId);

        if ($room === null) {
            throw ValidationException::withMessages([
                'room_id' => [__('validation.exists', ['attribute' => 'room id'])],
            ]);
        }

        if ($data->participantsCount > $room->capacity) {
            throw ValidationException::withMessages([
                'participants_count' => [__('bookings.capacity_exceeded')],
            ]);
        }

        if ($this->bookings->hasActiveOverlap($data->roomId, $data->startsAt, $data->endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => [__('bookings.room_unavailable')],
            ]);
        }

        return $this->bookings->create($data);
    }
}
```

`app/Actions/Booking/ListUserBookingsAction.php`:

```php
<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Repositories\BookingRepository;
use Illuminate\Database\Eloquent\Collection;

class ListUserBookingsAction
{
    public function __construct(private readonly BookingRepository $bookings) {}

    /**
     * @return Collection<int, Booking>
     */
    public function execute(int $userId): Collection
    {
        return $this->bookings->forUser($userId);
    }
}
```

- [ ] **Step 4: Create the request and resource**

```bash
php artisan make:request Api/V1/Booking/StoreBookingRequest --no-interaction
php artisan make:resource Api/V1/BookingResource --no-interaction
```

`app/Http/Requests/Api/V1/Booking/StoreBookingRequest.php` — `authorize()` returns `true`, `rules()`:

```php
public function rules(): array
{
    return [
        'room_id' => ['required', 'integer', 'exists:rooms,id'],
        'starts_at' => ['required', 'date'],
        'ends_at' => ['required', 'date', 'after:starts_at'],
        'participants_count' => ['required', 'integer', 'min:1'],
    ];
}
```

`app/Http/Resources/Api/V1/BookingResource.php` — `toArray()`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'room_id' => $this->room_id,
        'room' => RoomResource::make($this->whenLoaded('room')),
        'starts_at' => $this->starts_at?->toIso8601String(),
        'ends_at' => $this->ends_at?->toIso8601String(),
        'participants_count' => $this->participants_count,
        'status' => $this->status->value,
    ];
}
```

Add the import at the top of `BookingResource.php`:

```php
use App\Http\Resources\Api\V1\RoomResource;
```

- [ ] **Step 5: Create the controller**

```bash
php artisan make:controller Api/V1/BookingController --no-interaction
```

`app/Http/Controllers/Api/V1/BookingController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\ListUserBookingsAction;
use App\DTO\CreateBookingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Booking\StoreBookingRequest;
use App\Http\Resources\Api\V1\BookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function index(Request $request, ListUserBookingsAction $action): AnonymousResourceCollection
    {
        return BookingResource::collection($action->execute($request->user()->id));
    }

    public function store(StoreBookingRequest $request, CreateBookingAction $action): JsonResponse
    {
        $booking = $action->execute(new CreateBookingData(
            roomId: $request->integer('room_id'),
            userId: $request->user()->id,
            startsAt: $request->string('starts_at')->toString(),
            endsAt: $request->string('ends_at')->toString(),
            participantsCount: $request->integer('participants_count'),
        ));

        return BookingResource::make($booking->load('room'))
            ->response()
            ->setStatusCode(201);
    }
}
```

- [ ] **Step 6: Add the routes (final routes/api.php)**

Replace `routes/api.php` with its final form:

```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\RoomController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/rooms', [RoomController::class, 'index']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
});
```

(The `cancel` route is wired now; its controller method is added in Task 9. Do not run the full suite until Task 9 — `BookingController::cancel` does not exist yet. The `BookingTest` from this task does not hit the cancel route, so it passes.)

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/BookingTest.php`
Expected: PASS (all 7 cases).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add create and list bookings endpoints with validation rules"
```

---

## Task 9: Cancel booking endpoint + policy (TDD)

**Files:**
- Create: `app/Policies/BookingPolicy.php`
- Create: `app/Actions/Booking/CancelBookingAction.php`
- Modify: `app/Http/Controllers/Api/V1/BookingController.php`
- Test: `tests/Feature/BookingCancellationTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/BookingCancellationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cancel_own_booking(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);
        $booking = Booking::factory()->for($user)->for($room)->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);
        $booking = Booking::factory()->for(User::factory())->for($room)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/bookings/{$booking->id}/cancel")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/BookingCancellationTest.php`
Expected: FAIL (`BookingController::cancel` does not exist → error/500).

- [ ] **Step 3: Create the policy and action**

```bash
php artisan make:policy BookingPolicy --model=Booking --no-interaction
php artisan make:class Actions/Booking/CancelBookingAction --no-interaction
```

`app/Policies/BookingPolicy.php` — keep only the `cancel` ability (remove the generated CRUD stubs):

```php
<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }
}
```

(Policy auto-discovery in Laravel 12 maps `Booking` → `BookingPolicy` by name; no manual registration needed.)

`app/Actions/Booking/CancelBookingAction.php`:

```php
<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Repositories\BookingRepository;

class CancelBookingAction
{
    public function __construct(private readonly BookingRepository $bookings) {}

    public function execute(Booking $booking): Booking
    {
        return $this->bookings->markCancelled($booking);
    }
}
```

- [ ] **Step 4: Add the controller method**

Add to `app/Http/Controllers/Api/V1/BookingController.php` (new imports + method):

```php
use App\Actions\Booking\CancelBookingAction;
use App\Models\Booking;
```

```php
public function cancel(Request $request, Booking $booking, CancelBookingAction $action): BookingResource
{
    $this->authorize('cancel', $booking);

    return BookingResource::make($action->execute($booking)->load('room'));
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/BookingCancellationTest.php`
Expected: PASS (owner gets 200 + `cancelled`; non-owner gets 403).

- [ ] **Step 6: Run the full suite**

Run: `php artisan test --compact`
Expected: all tests PASS.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add cancel booking endpoint with ownership policy"
```

---

## Task 10: CORS configuration

**Files:**
- Modify: `config/cors.php`

- [ ] **Step 1: Restrict origins to the local frontend**

In `config/cors.php`, change `'allowed_origins'` from `['*']` to:

```php
'allowed_origins' => [
    env('FRONTEND_URL', 'http://localhost:5173'),
    env('APP_URL', 'http://localhost'),
],
```

Leave `'paths' => ['api/*', 'sanctum/csrf-cookie']` and `'supports_credentials' => false` as-is (token auth uses the `Authorization` header, not cookies).

- [ ] **Step 2: Verify config loads**

Run: `php artisan config:show cors.allowed_origins`
Expected: lists the two origins (not `*`).

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: scope CORS to local frontend origins"
```

---

## Task 11: Seeders

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write the seeder**

`database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $users = User::factory(8)->create();
        $users->first()->update(['email' => 'test@example.com']); // known login, password "password"

        $rooms = Room::factory(5)->create();

        $baseDay = CarbonImmutable::parse('next monday')->setTime(9, 0);

        foreach ($users->values() as $userIndex => $user) {
            foreach (range(0, 2) as $slot) {
                $room = $rooms->random();

                // Each user occupies its own 3-day window, each booking its own hour:
                // no two seeded bookings ever overlap.
                $start = $baseDay->addDays(($userIndex * 3) + $slot)->addHours($slot);

                Booking::factory()->create([
                    'user_id' => $user->id,
                    'room_id' => $room->id,
                    'starts_at' => $start,
                    'ends_at' => $start->addHour(),
                    'participants_count' => min(2, $room->capacity),
                    'status' => BookingStatus::Confirmed,
                ]);
            }
        }
    }
}
```

- [ ] **Step 2: Run the seeder against a fresh database**

Run: `php artisan migrate:fresh --seed`
Expected: completes without errors.

- [ ] **Step 3: Verify the seeded data**

Run: `php artisan tinker --execute 'echo User::count()." users, ".Room::count()." rooms, ".Booking::count()." bookings".PHP_EOL;'`
Expected: `8 users, 5 rooms, 24 bookings`.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: seed users, rooms, and non-overlapping bookings"
```

---

## Task 12: Postman collection

**Files:**
- Create: `booking.postman_collection.json`

- [ ] **Step 1: Write the collection**

`booking.postman_collection.json` (repository root):

```json
{
  "info": {
    "name": "Room Booking API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    { "key": "base_url", "value": "https://booking.test" },
    { "key": "token", "value": "" }
  ],
  "item": [
    {
      "name": "Register",
      "request": {
        "method": "POST",
        "header": [{ "key": "Accept", "value": "application/json" }],
        "url": { "raw": "{{base_url}}/api/register", "host": ["{{base_url}}"], "path": ["api", "register"] },
        "body": {
          "mode": "raw",
          "options": { "raw": { "language": "json" } },
          "raw": "{\n  \"name\": \"Jane Doe\",\n  \"email\": \"jane@example.com\",\n  \"password\": \"password123\",\n  \"password_confirmation\": \"password123\"\n}"
        }
      }
    },
    {
      "name": "Login",
      "event": [
        {
          "listen": "test",
          "script": {
            "type": "text/javascript",
            "exec": [
              "const data = pm.response.json();",
              "if (data.token) { pm.collectionVariables.set('token', data.token); }"
            ]
          }
        }
      ],
      "request": {
        "method": "POST",
        "header": [{ "key": "Accept", "value": "application/json" }],
        "url": { "raw": "{{base_url}}/api/login", "host": ["{{base_url}}"], "path": ["api", "login"] },
        "body": {
          "mode": "raw",
          "options": { "raw": { "language": "json" } },
          "raw": "{\n  \"email\": \"test@example.com\",\n  \"password\": \"password\"\n}"
        }
      }
    },
    {
      "name": "Logout",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept", "value": "application/json" },
          { "key": "Authorization", "value": "Bearer {{token}}" }
        ],
        "url": { "raw": "{{base_url}}/api/logout", "host": ["{{base_url}}"], "path": ["api", "logout"] }
      }
    },
    {
      "name": "List Rooms",
      "request": {
        "method": "GET",
        "header": [
          { "key": "Accept", "value": "application/json" },
          { "key": "Authorization", "value": "Bearer {{token}}" }
        ],
        "url": { "raw": "{{base_url}}/api/rooms", "host": ["{{base_url}}"], "path": ["api", "rooms"] }
      }
    },
    {
      "name": "Create Booking",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept", "value": "application/json" },
          { "key": "Authorization", "value": "Bearer {{token}}" }
        ],
        "url": { "raw": "{{base_url}}/api/bookings", "host": ["{{base_url}}"], "path": ["api", "bookings"] },
        "body": {
          "mode": "raw",
          "options": { "raw": { "language": "json" } },
          "raw": "{\n  \"room_id\": 1,\n  \"starts_at\": \"2026-07-01 10:00:00\",\n  \"ends_at\": \"2026-07-01 11:00:00\",\n  \"participants_count\": 2\n}"
        }
      }
    },
    {
      "name": "List My Bookings",
      "request": {
        "method": "GET",
        "header": [
          { "key": "Accept", "value": "application/json" },
          { "key": "Authorization", "value": "Bearer {{token}}" }
        ],
        "url": { "raw": "{{base_url}}/api/bookings", "host": ["{{base_url}}"], "path": ["api", "bookings"] }
      }
    },
    {
      "name": "Cancel Booking",
      "request": {
        "method": "PATCH",
        "header": [
          { "key": "Accept", "value": "application/json" },
          { "key": "Authorization", "value": "Bearer {{token}}" }
        ],
        "url": {
          "raw": "{{base_url}}/api/bookings/1/cancel",
          "host": ["{{base_url}}"],
          "path": ["api", "bookings", "1", "cancel"]
        }
      }
    }
  ]
}
```

- [ ] **Step 2: Validate the JSON**

Run: `php -r 'json_decode(file_get_contents("booking.postman_collection.json"), false, 512, JSON_THROW_ON_ERROR); echo "valid JSON".PHP_EOL;'`
Expected: `valid JSON`.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "docs: add Postman collection for the booking API"
```

---

## Task 13: Frontend scaffold (deps, Vite, entry, stores, router)

**Files:**
- Modify: `package.json` (via npm install)
- Modify: `vite.config.js`
- Modify: `resources/css/app.css`
- Create: `resources/views/app.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/js/app.js`
- Create: `resources/js/lib/axios.js`
- Create: `resources/js/lang/messages.js`
- Create: `resources/js/stores/auth.js`
- Create: `resources/js/stores/bookings.js`
- Create: `resources/js/router/index.js`
- Create: `resources/js/App.vue`

- [ ] **Step 1: Install frontend dependencies**

```bash
npm install vue vue-router pinia
npm install -D @vitejs/plugin-vue
```

- [ ] **Step 2: Register the Vue plugin in Vite**

`vite.config.js` — add the import and plugin:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

- [ ] **Step 3: Make Tailwind scan .vue files**

In `resources/css/app.css`, add one `@source` line after the existing ones:

```css
@source '../**/*.vue';
```

- [ ] **Step 4: Create the SPA entry blade view**

`resources/views/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Room Booking</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
```

- [ ] **Step 5: Serve the SPA from web routes**

`routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'app')->where('any', '.*');
```

(API routes live under `/api` in `routes/api.php` and are matched first, so this catch-all only handles SPA paths.)

- [ ] **Step 6: Write the JS entry**

`resources/js/app.js`:

```js
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

createApp(App).use(createPinia()).use(router).mount('#app');
```

- [ ] **Step 7: Create the axios instance**

`resources/js/lib/axios.js`:

```js
import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            if (window.location.pathname !== '/login') {
                window.location.assign('/login');
            }
        }
        return Promise.reject(error);
    },
);

export default api;
```

- [ ] **Step 8: Create the UI strings module**

`resources/js/lang/messages.js`:

```js
export default {
    app: { title: 'Room Booking' },
    auth: {
        login: 'Log in',
        register: 'Register',
        name: 'Name',
        email: 'Email',
        password: 'Password',
        passwordConfirmation: 'Confirm password',
        logout: 'Log out',
        haveAccount: 'Already have an account? Log in',
        noAccount: "Don't have an account? Register",
        genericError: 'Something went wrong.',
    },
    rooms: { title: 'Rooms', capacity: 'Capacity', book: 'Book' },
    bookings: {
        title: 'My bookings',
        create: 'New booking',
        room: 'Room',
        startsAt: 'Start',
        endsAt: 'End',
        participants: 'Participants',
        submit: 'Create booking',
        cancel: 'Cancel',
        empty: 'You have no bookings yet.',
        status: 'Status',
    },
};
```

- [ ] **Step 9: Create the Pinia stores**

`resources/js/stores/auth.js`:

```js
import { defineStore } from 'pinia';
import api from '../lib/axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token'),
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {
        async login(email, password) {
            const { data } = await api.post('/login', { email, password });
            this.setSession(data);
        },
        async register(payload) {
            const { data } = await api.post('/register', payload);
            this.setSession(data);
        },
        async logout() {
            try {
                await api.post('/logout');
            } finally {
                this.clearSession();
            }
        },
        setSession(data) {
            this.user = data.user;
            this.token = data.token;
            localStorage.setItem('token', data.token);
        },
        clearSession() {
            this.user = null;
            this.token = null;
            localStorage.removeItem('token');
        },
    },
});
```

`resources/js/stores/bookings.js`:

```js
import { defineStore } from 'pinia';
import api from '../lib/axios';

export const useBookingsStore = defineStore('bookings', {
    state: () => ({
        rooms: [],
        bookings: [],
    }),
    actions: {
        async fetchRooms() {
            const { data } = await api.get('/rooms');
            this.rooms = data.data;
        },
        async fetchBookings() {
            const { data } = await api.get('/bookings');
            this.bookings = data.data;
        },
        async createBooking(payload) {
            await api.post('/bookings', payload);
            await this.fetchBookings();
        },
        async cancelBooking(id) {
            await api.patch(`/bookings/${id}/cancel`);
            await this.fetchBookings();
        },
    },
});
```

- [ ] **Step 10: Create the router**

`resources/js/router/index.js`:

```js
import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import LoginPage from '../pages/LoginPage.vue';
import RoomsPage from '../pages/RoomsPage.vue';
import BookingFormPage from '../pages/BookingFormPage.vue';
import MyBookingsPage from '../pages/MyBookingsPage.vue';

const routes = [
    { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
    { path: '/', redirect: '/rooms' },
    { path: '/rooms', name: 'rooms', component: RoomsPage, meta: { auth: true } },
    { path: '/bookings/new', name: 'bookings.new', component: BookingFormPage, meta: { auth: true } },
    { path: '/bookings', name: 'bookings', component: MyBookingsPage, meta: { auth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login' };
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'rooms' };
    }
    return true;
});

export default router;
```

- [ ] **Step 11: Create the root component**

`resources/js/App.vue`:

```vue
<script setup>
import { RouterView, RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import messages from './lang/messages';

const auth = useAuthStore();
const router = useRouter();

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900">
        <nav v-if="auth.isAuthenticated" class="bg-white shadow">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
                <span class="font-semibold">{{ messages.app.title }}</span>
                <div class="flex items-center gap-4">
                    <RouterLink to="/rooms" class="hover:underline">{{ messages.rooms.title }}</RouterLink>
                    <RouterLink to="/bookings" class="hover:underline">{{ messages.bookings.title }}</RouterLink>
                    <button class="text-red-600 hover:underline" @click="logout">{{ messages.auth.logout }}</button>
                </div>
            </div>
        </nav>
        <main class="mx-auto max-w-4xl px-4 py-8">
            <RouterView />
        </main>
    </div>
</template>
```

- [ ] **Step 12: Build to verify the scaffold compiles**

The page components are created in Task 14; this build will fail on missing imports until then. Skip the build here and run it once at the end of Task 14. Commit the scaffold now:

```bash
git add -A
git commit -m "feat: scaffold Vue SPA (vite, router, pinia stores, axios, entry)"
```

---

## Task 14: Frontend pages

**Files:**
- Create: `resources/js/pages/LoginPage.vue`
- Create: `resources/js/pages/RoomsPage.vue`
- Create: `resources/js/pages/BookingFormPage.vue`
- Create: `resources/js/pages/MyBookingsPage.vue`

- [ ] **Step 1: Login / register page**

`resources/js/pages/LoginPage.vue`:

```vue
<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import messages from '../lang/messages';

const auth = useAuthStore();
const router = useRouter();

const mode = ref('login');
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref({});
const generalError = ref('');

async function submit() {
    errors.value = {};
    generalError.value = '';
    try {
        if (mode.value === 'login') {
            await auth.login(form.email, form.password);
        } else {
            await auth.register({ ...form });
        }
        router.push({ name: 'rooms' });
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            generalError.value = error.response.data.message ?? '';
        } else {
            generalError.value = messages.auth.genericError;
        }
    }
}
</script>

<template>
    <div class="mx-auto max-w-md rounded-lg bg-white p-6 shadow">
        <h1 class="mb-4 text-xl font-semibold">
            {{ mode === 'login' ? messages.auth.login : messages.auth.register }}
        </h1>
        <p v-if="generalError" class="mb-4 rounded bg-red-50 p-2 text-sm text-red-700">{{ generalError }}</p>
        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="mode === 'register'">
                <label class="block text-sm font-medium">{{ messages.auth.name }}</label>
                <input v-model="form.name" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                <p v-if="errors.name" class="text-sm text-red-600">{{ errors.name[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ messages.auth.email }}</label>
                <input v-model="form.email" type="email" class="mt-1 w-full rounded border px-3 py-2" />
                <p v-if="errors.email" class="text-sm text-red-600">{{ errors.email[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ messages.auth.password }}</label>
                <input v-model="form.password" type="password" class="mt-1 w-full rounded border px-3 py-2" />
                <p v-if="errors.password" class="text-sm text-red-600">{{ errors.password[0] }}</p>
            </div>
            <div v-if="mode === 'register'">
                <label class="block text-sm font-medium">{{ messages.auth.passwordConfirmation }}</label>
                <input v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded border px-3 py-2" />
            </div>
            <button type="submit" class="w-full rounded bg-blue-600 py-2 text-white hover:bg-blue-700">
                {{ mode === 'login' ? messages.auth.login : messages.auth.register }}
            </button>
        </form>
        <button
            class="mt-4 text-sm text-blue-600 hover:underline"
            @click="mode = mode === 'login' ? 'register' : 'login'"
        >
            {{ mode === 'login' ? messages.auth.noAccount : messages.auth.haveAccount }}
        </button>
    </div>
</template>
```

- [ ] **Step 2: Rooms page**

`resources/js/pages/RoomsPage.vue`:

```vue
<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBookingsStore } from '../stores/bookings';
import messages from '../lang/messages';

const store = useBookingsStore();
const router = useRouter();

onMounted(() => store.fetchRooms());

function book(roomId) {
    router.push({ name: 'bookings.new', query: { room_id: roomId } });
}
</script>

<template>
    <h1 class="mb-4 text-xl font-semibold">{{ messages.rooms.title }}</h1>
    <div class="grid gap-4 sm:grid-cols-2">
        <div v-for="room in store.rooms" :key="room.id" class="rounded-lg bg-white p-4 shadow">
            <h2 class="font-medium">{{ room.name }}</h2>
            <p class="text-sm text-gray-600">{{ messages.rooms.capacity }}: {{ room.capacity }}</p>
            <button
                class="mt-3 rounded bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700"
                @click="book(room.id)"
            >
                {{ messages.rooms.book }}
            </button>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Booking form page**

`resources/js/pages/BookingFormPage.vue`:

```vue
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBookingsStore } from '../stores/bookings';
import messages from '../lang/messages';

const store = useBookingsStore();
const route = useRoute();
const router = useRouter();

const form = reactive({
    room_id: route.query.room_id ? Number(route.query.room_id) : '',
    starts_at: '',
    ends_at: '',
    participants_count: 1,
});
const errors = ref({});
const generalError = ref('');

onMounted(() => store.fetchRooms());

async function submit() {
    errors.value = {};
    generalError.value = '';
    try {
        await store.createBooking({
            room_id: form.room_id,
            starts_at: form.starts_at,
            ends_at: form.ends_at,
            participants_count: form.participants_count,
        });
        router.push({ name: 'bookings' });
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            generalError.value = error.response.data.message ?? '';
        } else {
            generalError.value = messages.auth.genericError;
        }
    }
}
</script>

<template>
    <h1 class="mb-4 text-xl font-semibold">{{ messages.bookings.create }}</h1>
    <p v-if="generalError" class="mb-4 rounded bg-red-50 p-2 text-sm text-red-700">{{ generalError }}</p>
    <form class="max-w-md space-y-4 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
        <div>
            <label class="block text-sm font-medium">{{ messages.bookings.room }}</label>
            <select v-model="form.room_id" class="mt-1 w-full rounded border px-3 py-2">
                <option value="" disabled>—</option>
                <option v-for="room in store.rooms" :key="room.id" :value="room.id">
                    {{ room.name }} ({{ room.capacity }})
                </option>
            </select>
            <p v-if="errors.room_id" class="text-sm text-red-600">{{ errors.room_id[0] }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ messages.bookings.startsAt }}</label>
            <input v-model="form.starts_at" type="datetime-local" class="mt-1 w-full rounded border px-3 py-2" />
            <p v-if="errors.starts_at" class="text-sm text-red-600">{{ errors.starts_at[0] }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ messages.bookings.endsAt }}</label>
            <input v-model="form.ends_at" type="datetime-local" class="mt-1 w-full rounded border px-3 py-2" />
            <p v-if="errors.ends_at" class="text-sm text-red-600">{{ errors.ends_at[0] }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ messages.bookings.participants }}</label>
            <input
                v-model.number="form.participants_count"
                type="number"
                min="1"
                class="mt-1 w-full rounded border px-3 py-2"
            />
            <p v-if="errors.participants_count" class="text-sm text-red-600">{{ errors.participants_count[0] }}</p>
        </div>
        <button type="submit" class="w-full rounded bg-blue-600 py-2 text-white hover:bg-blue-700">
            {{ messages.bookings.submit }}
        </button>
    </form>
</template>
```

- [ ] **Step 4: My bookings page**

`resources/js/pages/MyBookingsPage.vue`:

```vue
<script setup>
import { onMounted } from 'vue';
import { useBookingsStore } from '../stores/bookings';
import messages from '../lang/messages';

const store = useBookingsStore();

onMounted(() => store.fetchBookings());

function formatDate(value) {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ messages.bookings.title }}</h1>
        <RouterLink to="/bookings/new" class="rounded bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700">
            {{ messages.bookings.create }}
        </RouterLink>
    </div>
    <p v-if="store.bookings.length === 0" class="text-gray-600">{{ messages.bookings.empty }}</p>
    <div v-else class="space-y-3">
        <div
            v-for="booking in store.bookings"
            :key="booking.id"
            class="flex items-center justify-between rounded-lg bg-white p-4 shadow"
        >
            <div>
                <p class="font-medium">{{ booking.room?.name }}</p>
                <p class="text-sm text-gray-600">{{ formatDate(booking.starts_at) }} – {{ formatDate(booking.ends_at) }}</p>
                <p class="text-sm text-gray-600">{{ messages.bookings.participants }}: {{ booking.participants_count }}</p>
                <p class="text-sm text-gray-600">{{ messages.bookings.status }}: {{ booking.status }}</p>
            </div>
            <button
                v-if="booking.status !== 'cancelled'"
                class="rounded bg-red-600 px-3 py-1 text-sm text-white hover:bg-red-700"
                @click="store.cancelBooking(booking.id)"
            >
                {{ messages.bookings.cancel }}
            </button>
        </div>
    </div>
</template>
```

(`RouterLink` is auto-registered globally by vue-router, so no import is needed in this component.)

- [ ] **Step 5: Build the frontend**

Run: `npm run build`
Expected: Vite build completes with no errors and writes `public/build/manifest.json`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add Vue pages for auth, rooms, booking form, and my bookings"
```

---

## Task 15: Final verification

- [ ] **Step 1: Run the full backend test suite**

Run: `php artisan test --compact`
Expected: all tests PASS (DomainModel, BookingRepository, Register, Login, Logout, Room, Booking, BookingCancellation).

- [ ] **Step 2: Confirm formatting is clean**

Run: `vendor/bin/pint --test --format agent`
Expected: no style issues. (If any, run `vendor/bin/pint --format agent` and commit.)

- [ ] **Step 3: Confirm the frontend builds**

Run: `npm run build`
Expected: success.

- [ ] **Step 4: Final commit (if anything changed)**

```bash
git add -A
git commit -m "chore: final verification for room booking feature" || echo "nothing to commit"
```

---

## Self-Review

**Spec coverage:**
- Entities User/Room/Booking + enum status → Task 1. ✓
- 7 endpoints under `Api\V1` + versioned directories → Tasks 4–9, routes in Task 8. ✓
- Repository / Action / DTO layering → Tasks 3, 4–9. ✓
- Postman collection at root → Task 12. ✓
- Vue 3 SPA (Composition API, Pinia, Tailwind, axios, localStorage token) → Tasks 13–14. ✓
- All four views → Task 14. ✓
- Business rules (overlap [start,end), ends_at after starts_at, capacity, own-only, cancelled-ignored) → Tasks 3, 8; verified by Task 8 tests. ✓
- Texts in lang files (backend `lang/`, frontend `messages.js`) → Tasks 2, 13. ✓
- Sanctum auth → Tasks 4–9. ✓
- Seeders (8 users, rooms, several bookings each) → Task 11. ✓
- API-level request validation → Tasks 4, 5, 8 (FormRequests). ✓
- ≥3 Feature tests → Tasks 1, 3, 4–9 (well above 3). ✓
- CORS for local frontend → Task 10. ✓

**Type/naming consistency:** `hasActiveOverlap` (defined Task 3, used Task 8), `forUser`/`markCancelled`/`create`/`find` consistent across repo + actions. DTO property names (`roomId`, `startsAt`, …) match controller construction in Task 8. Resource keys (`data.status`) match test assertions. `BookingStatus::activeValues()` defined Task 1, used Task 3.

**Placeholder scan:** none — every code step contains complete code.

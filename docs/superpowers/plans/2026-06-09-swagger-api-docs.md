# Swagger API Docs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Udostępnić interaktywną dokumentację OpenAPI 3.0 dla API V1 pod `/api/documentation`, generowaną z adnotacji PHPDoc w kontrolerach i klasach Resource.

**Architecture:** Pakiet `darkaonline/l5-swagger` skanuje adnotacje `@OA\...` (swagger-php) w `app/`. Blok bazowy (`@OA\Info`, `@OA\Server`, `@OA\SecurityScheme`, `@OA\Tag`, schematy współdzielone) ląduje w bazowym kontrolerze. Schematy odpowiedzi opisują klasy Resource; endpointy odwołują się do nich przez `$ref`. Testy weryfikują kompletność dokumentu, skanując `app/` przez `OpenApi\Generator::scan()`.

**Tech Stack:** Laravel 12, PHP 8.4, darkaonline/l5-swagger, swagger-php, PHPUnit 11.

---

## File Structure

- `composer.json` — dodanie zależności `darkaonline/l5-swagger`.
- `config/l5-swagger.php` — publikowany config pakietu.
- `.env` / `.env.example` — `L5_SWAGGER_GENERATE_ALWAYS=true`.
- `app/Http/Controllers/Controller.php` — blok bazowy OpenAPI + schematy współdzielone.
- `app/Http/Resources/Api/V1/UserResource.php` — schemat `User`.
- `app/Http/Resources/Api/V1/RoomResource.php` — schemat `Room`.
- `app/Http/Resources/Api/V1/BookingResource.php` — schemat `Booking`.
- `app/Http/Controllers/Api/V1/AuthController.php` — adnotacje register/login/logout.
- `app/Http/Controllers/Api/V1/RoomController.php` — adnotacja rooms.index.
- `app/Http/Controllers/Api/V1/TranslationController.php` — adnotacja translations.index.
- `app/Http/Controllers/Api/V1/BookingController.php` — adnotacje bookings.index/store/cancel.
- `tests/Feature/OpenApiDocumentationTest.php` — testy kompletności dokumentu OpenAPI.

---

## Task 1: Instalacja i konfiguracja l5-swagger

**Files:**
- Modify: `composer.json`
- Create: `config/l5-swagger.php` (publikacja)
- Modify: `.env`, `.env.example`

- [ ] **Step 1: Zainstaluj pakiet**

Run:
```bash
composer require darkaonline/l5-swagger
```
Expected: pakiet dodany do `require` w `composer.json`, instalacja bez błędów.

- [ ] **Step 2: Opublikuj config**

Run:
```bash
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider" --no-interaction
```
Expected: powstaje `config/l5-swagger.php`.

- [ ] **Step 3: Włącz auto-regenerację lokalnie**

Dopisz do `.env` oraz `.env.example`:
```
L5_SWAGGER_GENERATE_ALWAYS=true
```

- [ ] **Step 4: Zweryfikuj rejestrację trasy dokumentacji**

Run:
```bash
php artisan route:list --path=documentation
```
Expected: widoczna trasa `GET api/documentation` (l5-swagger.page).

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config/l5-swagger.php .env.example
git commit -m "chore: instalacja i konfiguracja l5-swagger"
```

---

## Task 2: Blok bazowy OpenAPI + schematy współdzielone

**Files:**
- Modify: `app/Http/Controllers/Controller.php`
- Test: `tests/Feature/OpenApiDocumentationTest.php`

- [ ] **Step 1: Napisz failujący test**

Utwórz `tests/Feature/OpenApiDocumentationTest.php`:
```php
<?php

namespace Tests\Feature;

use OpenApi\Generator;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        return json_decode(Generator::scan([app_path()])->toJson(), true);
    }

    public function test_document_has_info_server_and_security_scheme(): void
    {
        $doc = $this->document();

        $this->assertSame('Booking API', $doc['info']['title']);
        $this->assertSame('1.0.0', $doc['info']['version']);
        $this->assertSame('https://booking.test', $doc['servers'][0]['url']);
        $this->assertArrayHasKey('sanctum', $doc['components']['securitySchemes']);
        $this->assertSame('http', $doc['components']['securitySchemes']['sanctum']['type']);
        $this->assertSame('bearer', $doc['components']['securitySchemes']['sanctum']['scheme']);
    }

    public function test_document_has_shared_schemas(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertArrayHasKey('ValidationError', $schemas);
        $this->assertArrayHasKey('ErrorMessage', $schemas);
        $this->assertArrayHasKey('AuthResponse', $schemas);
    }
}
```

- [ ] **Step 2: Uruchom test — ma failować**

Run:
```bash
php artisan test --compact --filter=OpenApiDocumentationTest
```
Expected: FAIL — brak `info`/schematów (klucze nie istnieją).

- [ ] **Step 3: Dodaj blok bazowy w bazowym kontrolerze**

Zastąp zawartość `app/Http/Controllers/Controller.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @OA\Info(
 *     title="Booking API",
 *     version="1.0.0",
 *     description="API rezerwacji pokoi."
 * )
 *
 * @OA\Server(
 *     url="https://booking.test",
 *     description="Local (Herd)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     description="Token Sanctum: Authorization: Bearer <token>"
 * )
 *
 * @OA\Tag(name="Auth", description="Rejestracja, logowanie, wylogowanie")
 * @OA\Tag(name="Rooms", description="Pokoje")
 * @OA\Tag(name="Bookings", description="Rezerwacje")
 * @OA\Tag(name="Translations", description="Tłumaczenia interfejsu")
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(property="errors", type="object", description="Mapa: pole => lista komunikatów błędów")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorMessage",
 *     @OA\Property(property="message", type="string", example="Unauthenticated.")
 * )
 *
 * @OA\Schema(
 *     schema="AuthResponse",
 *     @OA\Property(property="user", ref="#/components/schemas/User"),
 *     @OA\Property(property="token", type="string", example="1|abcDEF123...")
 * )
 */
abstract class Controller
{
    use AuthorizesRequests;
}
```

- [ ] **Step 4: Uruchom test — ma przejść**

Run:
```bash
php artisan test --compact --filter=OpenApiDocumentationTest
```
Expected: PASS (oba testy).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Controller.php tests/Feature/OpenApiDocumentationTest.php
git commit -m "docs: blok bazowy OpenAPI i schematy współdzielone"
```

---

## Task 3: Schematy odpowiedzi na klasach Resource

**Files:**
- Modify: `app/Http/Resources/Api/V1/UserResource.php`
- Modify: `app/Http/Resources/Api/V1/RoomResource.php`
- Modify: `app/Http/Resources/Api/V1/BookingResource.php`
- Test: `tests/Feature/OpenApiDocumentationTest.php`

- [ ] **Step 1: Dopisz failujący test**

Dodaj metodę do `OpenApiDocumentationTest`:
```php
    public function test_document_has_resource_schemas(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertArrayHasKey('User', $schemas);
        $this->assertArrayHasKey('Room', $schemas);
        $this->assertArrayHasKey('Booking', $schemas);

        $bookingProps = $schemas['Booking']['properties'];
        $this->assertArrayHasKey('room', $bookingProps);
        $this->assertArrayHasKey('status', $bookingProps);
        $this->assertSame(
            ['pending', 'confirmed', 'cancelled'],
            $bookingProps['status']['properties']['value']['enum']
        );
    }
```

- [ ] **Step 2: Uruchom test — ma failować**

Run:
```bash
php artisan test --compact --filter=test_document_has_resource_schemas
```
Expected: FAIL — brak schematów `User`/`Room`/`Booking`.

- [ ] **Step 3a: Schemat `User`**

W `app/Http/Resources/Api/V1/UserResource.php` dodaj blok PHPDoc nad `class UserResource`:
```php
/**
 * @OA\Schema(
 *     schema="User",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Jan Kowalski"),
 *     @OA\Property(property="email", type="string", format="email", example="jan@example.com")
 * )
 */
```

- [ ] **Step 3b: Schemat `Room`**

W `app/Http/Resources/Api/V1/RoomResource.php` dodaj blok PHPDoc nad `class RoomResource`:
```php
/**
 * @OA\Schema(
 *     schema="Room",
 *     @OA\Property(property="id", type="integer", example=4),
 *     @OA\Property(property="name", type="string", example="Sala konferencyjna A"),
 *     @OA\Property(property="capacity", type="integer", example=10)
 * )
 */
```

- [ ] **Step 3c: Schemat `Booking`**

W `app/Http/Resources/Api/V1/BookingResource.php` dodaj blok PHPDoc nad `class BookingResource`:
```php
/**
 * @OA\Schema(
 *     schema="Booking",
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="room_id", type="integer", example=4),
 *     @OA\Property(property="room", ref="#/components/schemas/Room", nullable=true),
 *     @OA\Property(property="starts_at", type="string", format="date-time", example="2026-06-19T12:00:00+00:00"),
 *     @OA\Property(property="ends_at", type="string", format="date-time", example="2026-06-19T14:00:00+00:00"),
 *     @OA\Property(property="participants_count", type="integer", example=5),
 *     @OA\Property(
 *         property="status",
 *         type="object",
 *         @OA\Property(property="label", type="string", example="Potwierdzona"),
 *         @OA\Property(property="color", type="string", example="bg-green-100 text-green-800"),
 *         @OA\Property(property="value", type="string", enum={"pending", "confirmed", "cancelled"}, example="confirmed")
 *     )
 * )
 */
```

- [ ] **Step 4: Uruchom test — ma przejść**

Run:
```bash
php artisan test --compact --filter=test_document_has_resource_schemas
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Resources/Api/V1/
git commit -m "docs: schematy OpenAPI User/Room/Booking na klasach Resource"
```

---

## Task 4: Adnotacje endpointów Auth

**Files:**
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`
- Test: `tests/Feature/OpenApiDocumentationTest.php`

- [ ] **Step 1: Dopisz failujący test**

Dodaj metodę do `OpenApiDocumentationTest`:
```php
    public function test_document_has_auth_paths(): void
    {
        $paths = $this->document()['paths'];

        $this->assertArrayHasKey('post', $paths['/api/register']);
        $this->assertArrayHasKey('post', $paths['/api/login']);
        $this->assertArrayHasKey('post', $paths['/api/logout']);
        $this->assertSame(
            [['sanctum' => []]],
            $paths['/api/logout']['post']['security']
        );
    }
```

- [ ] **Step 2: Uruchom test — ma failować**

Run:
```bash
php artisan test --compact --filter=test_document_has_auth_paths
```
Expected: FAIL — brak klucza `/api/register` w `paths`.

- [ ] **Step 3a: Adnotacja `register`**

Nad metodą `register` w `AuthController`:
```php
    /**
     * @OA\Post(
     *     path="/api/register",
     *     tags={"Auth"},
     *     summary="Rejestracja nowego użytkownika",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Jan Kowalski"),
     *             @OA\Property(property="email", type="string", format="email", example="jan@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="haslo1234"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="haslo1234")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Utworzono", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *     @OA\Response(response=422, description="Błąd walidacji", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
```

- [ ] **Step 3b: Adnotacja `login`**

Nad metodą `login`:
```php
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Auth"},
     *     summary="Logowanie",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="jan@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="haslo1234")
     *         )
     *     ),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *     @OA\Response(response=422, description="Błąd walidacji", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
```

- [ ] **Step 3c: Adnotacja `logout`**

Nad metodą `logout`:
```php
    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Auth"},
     *     summary="Wylogowanie (unieważnia bieżący token)",
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
     *     @OA\Response(response=401, description="Brak autoryzacji", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
     * )
     */
```

- [ ] **Step 4: Uruchom test — ma przejść**

Run:
```bash
php artisan test --compact --filter=test_document_has_auth_paths
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/AuthController.php tests/Feature/OpenApiDocumentationTest.php
git commit -m "docs: adnotacje OpenAPI endpointów Auth"
```

---

## Task 5: Adnotacje endpointów Rooms i Translations

**Files:**
- Modify: `app/Http/Controllers/Api/V1/RoomController.php`
- Modify: `app/Http/Controllers/Api/V1/TranslationController.php`
- Test: `tests/Feature/OpenApiDocumentationTest.php`

- [ ] **Step 1: Dopisz failujący test**

Dodaj metodę do `OpenApiDocumentationTest`:
```php
    public function test_document_has_rooms_and_translations_paths(): void
    {
        $paths = $this->document()['paths'];

        $this->assertArrayHasKey('get', $paths['/api/rooms']);
        $this->assertSame(
            [['sanctum' => []]],
            $paths['/api/rooms']['get']['security']
        );
        $this->assertArrayHasKey('get', $paths['/api/translations']);
        $this->assertSame(
            'locale',
            $paths['/api/translations']['get']['parameters'][0]['name']
        );
    }
```

- [ ] **Step 2: Uruchom test — ma failować**

Run:
```bash
php artisan test --compact --filter=test_document_has_rooms_and_translations_paths
```
Expected: FAIL — brak klucza `/api/rooms`.

- [ ] **Step 3a: Adnotacja `rooms.index`**

Nad metodą `index` w `RoomController`:
```php
    /**
     * @OA\Get(
     *     path="/api/rooms",
     *     tags={"Rooms"},
     *     summary="Lista pokoi",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Room"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Brak autoryzacji", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
     * )
     */
```

- [ ] **Step 3b: Adnotacja `translations.index`**

Nad metodą `index` w `TranslationController`:
```php
    /**
     * @OA\Get(
     *     path="/api/translations",
     *     tags={"Translations"},
     *     summary="Tłumaczenia interfejsu dla danego locale",
     *     @OA\Parameter(
     *         name="locale",
     *         in="query",
     *         required=false,
     *         description="Kod języka, np. pl/en. Domyślnie locale aplikacji.",
     *         @OA\Schema(type="string", example="pl")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="locale", type="string", example="pl"),
     *             @OA\Property(property="version", type="string", example="1"),
     *             @OA\Property(property="messages", type="object", description="Mapa: klucz tłumaczenia => tekst")
     *         )
     *     )
     * )
     */
```

- [ ] **Step 4: Uruchom test — ma przejść**

Run:
```bash
php artisan test --compact --filter=test_document_has_rooms_and_translations_paths
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/RoomController.php app/Http/Controllers/Api/V1/TranslationController.php tests/Feature/OpenApiDocumentationTest.php
git commit -m "docs: adnotacje OpenAPI endpointów Rooms i Translations"
```

---

## Task 6: Adnotacje endpointów Bookings

**Files:**
- Modify: `app/Http/Controllers/Api/V1/BookingController.php`
- Test: `tests/Feature/OpenApiDocumentationTest.php`

- [ ] **Step 1: Dopisz failujący test**

Dodaj metodę do `OpenApiDocumentationTest`:
```php
    public function test_document_has_bookings_paths(): void
    {
        $paths = $this->document()['paths'];

        $this->assertArrayHasKey('get', $paths['/api/bookings']);
        $this->assertArrayHasKey('post', $paths['/api/bookings']);
        $this->assertArrayHasKey('patch', $paths['/api/bookings/{booking}/cancel']);

        $cancel = $paths['/api/bookings/{booking}/cancel']['patch'];
        $this->assertSame('booking', $cancel['parameters'][0]['name']);
        $this->assertArrayHasKey('403', $cancel['responses']);
    }
```

- [ ] **Step 2: Uruchom test — ma failować**

Run:
```bash
php artisan test --compact --filter=test_document_has_bookings_paths
```
Expected: FAIL — brak klucza `/api/bookings`.

- [ ] **Step 3a: Adnotacja `bookings.index`**

Nad metodą `index` w `BookingController`:
```php
    /**
     * @OA\Get(
     *     path="/api/bookings",
     *     tags={"Bookings"},
     *     summary="Lista rezerwacji zalogowanego użytkownika",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Booking"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Brak autoryzacji", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
     * )
     */
```

- [ ] **Step 3b: Adnotacja `bookings.store`**

Nad metodą `store`:
```php
    /**
     * @OA\Post(
     *     path="/api/bookings",
     *     tags={"Bookings"},
     *     summary="Utworzenie rezerwacji",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"room_id", "starts_at", "ends_at", "participants_count"},
     *             @OA\Property(property="room_id", type="integer", example=4),
     *             @OA\Property(property="starts_at", type="string", format="date-time", example="2026-06-19T12:00:00+00:00"),
     *             @OA\Property(property="ends_at", type="string", format="date-time", example="2026-06-19T14:00:00+00:00"),
     *             @OA\Property(property="participants_count", type="integer", minimum=1, example=5)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Utworzono", @OA\JsonContent(ref="#/components/schemas/Booking")),
     *     @OA\Response(response=401, description="Brak autoryzacji", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
     *     @OA\Response(response=422, description="Błąd walidacji", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
```

- [ ] **Step 3c: Adnotacja `bookings.cancel`**

Nad metodą `cancel`:
```php
    /**
     * @OA\Patch(
     *     path="/api/bookings/{booking}/cancel",
     *     tags={"Bookings"},
     *     summary="Anulowanie rezerwacji",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="booking",
     *         in="path",
     *         required=true,
     *         description="ID rezerwacji",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/Booking")),
     *     @OA\Response(response=401, description="Brak autoryzacji", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
     *     @OA\Response(response=403, description="Brak uprawnień do anulowania", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
     * )
     */
```

- [ ] **Step 4: Uruchom test — ma przejść**

Run:
```bash
php artisan test --compact --filter=test_document_has_bookings_paths
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/BookingController.php tests/Feature/OpenApiDocumentationTest.php
git commit -m "docs: adnotacje OpenAPI endpointów Bookings"
```

---

## Task 7: Pełna generacja, weryfikacja i formatowanie

**Files:**
- (weryfikacja, bez nowego kodu produkcyjnego)

- [ ] **Step 1: Wygeneruj spec przez l5-swagger**

Run:
```bash
php artisan l5-swagger:generate
```
Expected: brak błędów; powstaje plik JSON (ścieżka wg `config/l5-swagger.php`, domyślnie `storage/api-docs/api-docs.json`).

- [ ] **Step 2: Sprawdź, że spec zawiera wszystkie 8 ścieżek**

Run:
```bash
php -r '$d=json_decode(file_get_contents("storage/api-docs/api-docs.json"),true); echo count($d["paths"])." paths\n"; echo implode("\n",array_keys($d["paths"]))."\n";'
```
Expected: wypisane 7 kluczy ścieżek (`/api/bookings` łączy GET+POST), w tym `/api/register`, `/api/login`, `/api/logout`, `/api/rooms`, `/api/bookings`, `/api/bookings/{booking}/cancel`, `/api/translations`.

- [ ] **Step 3: Uruchom cały plik testowy**

Run:
```bash
php artisan test --compact --filter=OpenApiDocumentationTest
```
Expected: PASS (wszystkie metody).

- [ ] **Step 4: Formatowanie Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```
Expected: brak błędów; ewentualne poprawki stylu zaaplikowane.

- [ ] **Step 5: Weryfikacja UI (manualna)**

Otwórz `https://booking.test/api/documentation`.
Expected: renderuje się Swagger UI z czterema tagami (Auth, Rooms, Bookings, Translations), przyciskiem „Authorize" (Bearer/sanctum) i wszystkimi endpointami.

- [ ] **Step 6: Commit ewentualnych zmian formatowania**

```bash
git add -A
git commit -m "docs: regeneracja spec OpenAPI i formatowanie"
```

---

## Notes

- Jeśli `Generator::scan([app_path()])` w testach napotka adnotacje także poza V1 w przyszłości — testy weryfikują tylko obecność kluczy V1, więc pozostają stabilne.
- Gdyby `storage/api-docs/` nie istniał przy generacji, l5-swagger tworzy go sam; w razie błędu uprawnień utworzyć katalog ręcznie.
- Domyślny URL serwera (`https://booking.test`) jest twardo wpisany w bloku bazowym zgodnie ze specyfikacją; przy wdrożeniu na inne środowisko warto przenieść go na zmienną z configu (`L5_SWAGGER_CONST_HOST`).

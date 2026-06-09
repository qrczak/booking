# Dokumentacja API przez Swagger (l5-swagger)

Data: 2026-06-09
Status: zaakceptowany

> **Aktualizacja w trakcie wdrożenia (2026-06-09):** pierwotnie wybrano adnotacje
> PHPDoc. W trakcie implementacji okazało się, że zainstalowana swagger-php to v6, a
> l5-swagger v11 domyślnie skanuje **wyłącznie atrybuty PHP 8** (jego analyser jest
> zahardkodowany na `AttributeAnnotationFactory`). Utrzymanie PHPDoc wymagałoby pakietu
> `doctrine/annotations` (porzucony) oraz nadpisania analysera w runtime (config-owy
> obiekt psuje `php artisan config:cache`). Po konsultacji **przełączono na atrybuty
> PHP 8 `#[OA\...]`** — działają z l5-swagger v11 bez dodatkowych zależności i obejść.
> Sekcje „Styl opisu" i „Architektura adnotacji" poniżej należy czytać z tą poprawką.

## Cel

Udostępnić interaktywną dokumentację OpenAPI 3.0 dla API V1, generowaną z adnotacji
w kodzie, tak aby spec nie rozjeżdżał się z implementacją i był dostępny pod
`/api/documentation`.

## Zakres

Wszystkie istniejące endpointy `routes/api.php` (V1):

| Metoda | Ścieżka                       | Akcja                          | Auth   | Tag          |
|--------|-------------------------------|--------------------------------|--------|--------------|
| POST   | `/api/register`               | `AuthController@register`      | nie    | Auth         |
| POST   | `/api/login`                  | `AuthController@login`         | nie    | Auth         |
| POST   | `/api/logout`                 | `AuthController@logout`        | tak    | Auth         |
| GET    | `/api/rooms`                  | `RoomController@index`         | tak    | Rooms        |
| GET    | `/api/bookings`               | `BookingController@index`      | tak    | Bookings     |
| POST   | `/api/bookings`               | `BookingController@store`      | tak    | Bookings     |
| PATCH  | `/api/bookings/{booking}/cancel` | `BookingController@cancel`  | tak    | Bookings     |
| GET    | `/api/translations`           | `TranslationController@index`  | nie    | Translations |

## Decyzje projektowe

- **Pakiet:** `darkaonline/l5-swagger` (zwykła zależność `require`, aby UI działało we
  wszystkich środowiskach).
- **Styl opisu:** adnotacje PHPDoc (`@OA\...` swagger-php).
- **Lokalizacja adnotacji endpointów:** bezpośrednio w kontrolerach, nad metodami.
- **Schematy odpowiedzi:** jako `@OA\Schema` nad klasami Resource (tam zdefiniowany jest
  kształt danych); endpointy odwołują się przez `$ref`.
- **Tytuł API:** „Booking API", wersja `1.0.0`.

## Architektura adnotacji

### Blok bazowy — `app/Http/Controllers/Controller.php`

- `@OA\Info` — tytuł „Booking API", wersja `1.0.0`, krótki opis.
- `@OA\Server` — URL bazowy (rozwiązany przez `get-absolute-url`).
- `@OA\SecurityScheme` — `securityScheme="sanctum"`, `type="http"`, `scheme="bearer"`
  (token z Sanctum przekazywany w nagłówku `Authorization: Bearer <token>`).
- `@OA\Tag` dla: Auth, Rooms, Bookings, Translations.

### Schematy (`@OA\Schema`) na klasach Resource

- `UserResource` → schemat `User`: `id` (int), `name` (string), `email` (string).
- `RoomResource` → schemat `Room`: `id` (int), `name` (string), `capacity` (int).
- `BookingResource` → schemat `Booking`: `id` (int), `room_id` (int),
  `room` (`$ref` Room, opcjonalny), `starts_at` (ISO8601 string),
  `ends_at` (ISO8601 string), `participants_count` (int),
  `status` (obiekt: `label` string, `color` string, `value` string).

### Schematy współdzielone

Zdefiniowane raz (np. w bloku bazowym lub dedykowanym pliku schematów referencyjnych):

- `ValidationError` (422): `message` (string) + `errors` (mapa pole → lista komunikatów).
- `ErrorMessage` (401/403): `message` (string).
- `AuthResponse` (register/login): `user` (`$ref` User) + `token` (string).

### Adnotacje endpointów (w kontrolerach)

Każda metoda otrzymuje `@OA\Get|Post|Patch` z:

- `path`, `tags`, `summary`/`description`,
- `security={{"sanctum":{}}}` dla tras chronionych (`logout`, `rooms`, `bookings.*`),
- `@OA\Parameter` dla parametrów ścieżki/zapytania
  (`{booking}` w cancel; `locale` query w translations),
- `@OA\RequestBody` odwzorowujący reguły Form Requestów:
  - register: `name`, `email`, `password`, `password_confirmation`,
  - login: `email`, `password`,
  - store booking: `room_id`, `starts_at`, `ends_at`, `participants_count`,
- `@OA\Response`:
  - 200/201 z `$ref` odpowiedniego schematu,
  - 401 (`ErrorMessage`) dla tras chronionych,
  - 403 (`ErrorMessage`) dla `cancel` (policy),
  - 422 (`ValidationError`) dla endpointów z walidacją.

#### Mapowanie odpowiedzi sukcesu

- register → 201 `AuthResponse`
- login → 200 `AuthResponse`
- logout → 200 `ErrorMessage` (pole `message`)
- rooms.index → 200 tablica `Room`
- bookings.index → 200 tablica `Booking`
- bookings.store → 201 `Booking`
- bookings.cancel → 200 `Booking`
- translations.index → 200 obiekt: `locale` (string), `version` (string),
  `messages` (obiekt klucz→string)

## Konfiguracja

- Publikacja `config/l5-swagger.php` przez `php artisan vendor:publish`.
- `.env` lokalnie: `L5_SWAGGER_GENERATE_ALWAYS=true` (auto-regeneracja przy odświeżeniu).
- UI: `/api/documentation`, spec JSON pod ścieżką z configu (`/docs`).

## Weryfikacja

1. `php artisan l5-swagger:generate` — spec buduje się bez błędów.
2. Wygenerowany JSON zawiera wszystkie 8 ścieżek i zdefiniowane schematy.
3. UI `/api/documentation` renderuje się i pozwala autoryzować się tokenem (przycisk
   „Authorize" → Bearer).
4. `vendor/bin/pint --dirty` — formatowanie PHP.

## Poza zakresem

- Generowanie kolekcji Postman.
- Automatyczne testowanie kontraktu API względem spec.
- Dokumentacja endpointów innych niż V1.

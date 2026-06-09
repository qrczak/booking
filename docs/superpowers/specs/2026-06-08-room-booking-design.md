# System rezerwacji sal — projekt (design spec)

Data: 2026-06-08
Stack: Laravel 12 (PHP 8.4), Sanctum, MySQL, Vue 3 (Composition API) + Pinia + Tailwind v4, axios.

## Cel

Prosta aplikacja do rezerwacji sal: rejestracja/logowanie użytkowników, przegląd
sal, tworzenie i anulowanie rezerwacji z kontrolą kolizji terminów i pojemności.
Backend jako REST API (token Sanctum), frontend jako token-based SPA serwowane
przez Laravel Vite z `resources/js`.

## Kluczowe decyzje architektoniczne

- **Frontend** mieszka w `resources/js`, budowany istniejącym `laravel-vite-plugin`.
  Autoryzacja tokenem w `localStorage` (nie cookie/sesja).
- **Stan frontu**: Pinia.
- **Repository**: konkretne klasy bez interfejsów (mała aplikacja, mniej ceremonii).
- **Kolizje terminów**: przedział półotwarty `[start, end)` — rezerwacje stykające
  się (koniec jednej = początek drugiej) NIE kolidują.
- **Wersjonowanie API**: na poziomie katalogów (`Api\V1`), nie w URL. URL-e endpointów
  pozostają takie jak w wymaganiach (`/api/register`, `/api/bookings`, ...).
- **Status nowej rezerwacji**: `confirmed` (brak endpointu „confirm", więc `pending`
  nie miałby jak się zmienić). Rezerwacja „aktywna" przy sprawdzaniu kolizji =
  `pending` lub `confirmed`; `cancelled` nie blokuje terminu.

## 1. Model danych i migracje

`rooms`:
- `id`
- `name` (string)
- `capacity` (unsigned int)
- timestamps

`bookings`:
- `id`
- `room_id` (FK → rooms, cascade)
- `user_id` (FK → users, cascade)
- `starts_at` (datetime)
- `ends_at` (datetime)
- `participants_count` (unsigned int)
- `status` (string, default `confirmed`)
- timestamps
- indeks złożony `(room_id, starts_at, ends_at)` pod zapytania o kolizje

Enum `app/Enums/BookingStatus.php` — string-backed: `Pending`, `Confirmed`,
`Cancelled`. Castowany na modelu `Booking` w metodzie `casts()`.

## 2. Warstwy backendu (Repository → Action → DTO)

```
app/
├── Enums/BookingStatus.php
├── Models/{Room,Booking,User}.php
├── Repositories/
│   ├── RoomRepository.php          # all()
│   └── BookingRepository.php       # forUser(), overlappingActive(), create(), markCancelled()
├── DTO/
│   ├── RegisterUserData.php
│   └── CreateBookingData.php
├── Actions/
│   ├── Auth/{RegisterUserAction,LoginUserAction,LogoutUserAction}.php
│   └── Booking/{CreateBookingAction,CancelBookingAction,ListUserBookingsAction}.php
├── Policies/BookingPolicy.php      # tylko właściciel może anulować
└── Http/
    ├── Controllers/Api/V1/{AuthController,RoomController,BookingController}.php
    ├── Requests/Api/V1/Auth/{RegisterRequest,LoginRequest}.php
    ├── Requests/Api/V1/Booking/StoreBookingRequest.php
    └── Resources/Api/V1/{UserResource,RoomResource,BookingResource}.php
```

Podział odpowiedzialności:
- **Controller**: cienki — autoryzacja (`$this->authorize`), zbudowanie DTO
  z walidowanego requestu, wywołanie Action, zwrot Resource.
- **FormRequest**: walidacja strukturalna (`required`, `date`, `ends_at` →
  `after:starts_at`, `participants_count` → `integer|min:1`, `room_id` →
  `exists:rooms`).
- **Action**: logika biznesowa. Reguły wymagające bazy (kolizja terminu,
  `participants_count > room.capacity`) sprawdzane tutaj — rzucają
  `ValidationException` (HTTP 422) z komunikatami z plików lang.
- **Repository**: cały dostęp do bazy (Eloquent). Zapytanie o kolizję:
  `WHERE room_id = ? AND status IN (pending, confirmed)
   AND starts_at < :new_ends AND ends_at > :new_starts`.
- **DTO**: `RegisterUserData`, `CreateBookingData` — niezmienne (`readonly`)
  obiekty przekazywane do Action zapisujących/edytujących dane.

## 3. Kontrakt API

Wersjonowanie na poziomie katalogów (`Api\V1`); URL-e bez segmentu wersji.

| Metoda | Ścieżka | Auth | Action / wynik |
|---|---|---|---|
| POST | `/api/register` | — | RegisterUserAction → token + user |
| POST | `/api/login` | — | LoginUserAction → token + user |
| POST | `/api/logout` | sanctum | LogoutUserAction (usuwa bieżący token) |
| GET | `/api/rooms` | sanctum | RoomRepository::all → RoomResource |
| POST | `/api/bookings` | sanctum | CreateBookingAction → BookingResource |
| GET | `/api/bookings` | sanctum | ListUserBookingsAction (tylko własne) |
| PATCH | `/api/bookings/{id}/cancel` | sanctum | CancelBookingAction (+ Policy) |

Listy bez paginacji (mały zbiór danych). „User widzi tylko swoje" egzekwowane
w `BookingRepository::forUser($userId)`. `{id}/cancel` chroniony `BookingPolicy`
(403 dla obcego usera).

## 4. Reguły biznesowe (gdzie egzekwowane)

| Reguła | Miejsce |
|---|---|
| `ends_at` po `starts_at` | StoreBookingRequest (`after:starts_at`) |
| Brak kolizji z aktywną rezerwacją | CreateBookingAction → BookingRepository::overlappingActive |
| `participants_count ≤ room.capacity` | CreateBookingAction |
| User widzi tylko swoje rezerwacje | BookingRepository::forUser |
| Anulowane nie blokują terminu | warunek `status IN (pending, confirmed)` w zapytaniu kolizji |

## 5. Frontend (Vue 3 Composition API, w `resources/js`)

```
resources/
├── views/app.blade.php           # @vite + <div id="app">; web.php: catch-all → SPA
├── css/app.css                   # Tailwind v4
└── js/
    ├── app.js                    # mount Vue + router + pinia
    ├── App.vue
    ├── router/index.js           # /login /rooms /bookings/new /bookings + guard auth
    ├── stores/{auth,bookings}.js # Pinia
    ├── lib/axios.js              # instancja, Bearer z localStorage, 401 → logout
    ├── lang/messages.js          # teksty UI w jednym pliku
    └── pages/{LoginPage,RoomsPage,BookingFormPage,MyBookingsPage}.vue
```

Widoki:
- **LoginPage** — formularz logowania i rejestracji (przełączane).
- **RoomsPage** — lista sal z pojemnością.
- **BookingFormPage** — wybór sali, `starts_at`, `ends_at`, liczby uczestników.
- **MyBookingsPage** — rezerwacje zalogowanego usera + przycisk anulowania.

Nowe zależności: `vue`, `vue-router`, `pinia`, `@vitejs/plugin-vue`. Token w
`localStorage`; interceptor axios dodaje `Authorization: Bearer`, a `401` czyści
sesję i przekierowuje na `/login`. `web.php` zawiera catch-all zwracający
`app.blade.php`, aby routing po stronie klienta działał.

## 6. Teksty, CORS, Postman, seedery, testy

**Teksty (pliki lang)**
- `php artisan lang:publish`, następnie `lang/en/validation.php` (komunikaty/atrybuty)
  + domenowe `lang/en/bookings.php` (np. `room_unavailable`, `capacity_exceeded`)
  i `lang/en/auth.php`.
- Action/Request odwołują się przez `__('bookings.room_unavailable')` itp.
- Frontend trzyma teksty UI w `resources/js/lang/messages.js`.

**CORS** (`config/cors.php`)
- `paths: ['api/*']`
- `allowed_origins`: origin dev-serwera Vite + `APP_URL`
- `supports_credentials: false` (auth tokenem w nagłówku)

**Postman**
- `booking.postman_collection.json` w katalogu głównym — wszystkie 7 endpointów,
  zmienne `{{base_url}}` i `{{token}}`, request login z test-skryptem zapisującym token.

**Seedery**
- `RoomFactory` + `BookingFactory`; `DatabaseSeeder` → 8 userów, ~5 sal o różnej
  pojemności, dla każdego usera po kilka rezerwacji (mieszane statusy, sloty bez
  nakładania).

**Testy Feature** (min. 3 — tu więcej):
1. Rejestracja zwraca token.
2. Utworzenie rezerwacji dla wolnej sali → 201.
3. Kolizja terminu → 422.
4. Przekroczenie pojemności → 422.
5. `ends_at ≤ starts_at` → 422.
6. User widzi tylko swoje rezerwacje.
7. Anulowana rezerwacja nie blokuje terminu.
8. Właściciel anuluje (200) / obcy user dostaje 403.

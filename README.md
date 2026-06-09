# Booking API

Aplikacja Laravel do rezerwacji pokoi, z REST API (V1) udokumentowanym przez Swagger/OpenAPI.

## Wymagania

| Składnik | Wersja | Uwagi |
|----------|--------|-------|
| PHP | ≥ 8.2 (rekomendowane 8.4) |  |
| Composer | 2.x | |
| Node.js | ≥ 18 (rekomendowane 22) | do budowania frontendu (Vite) |
| Baza danych | MySql lub SQLite |  |

Rozszerzenia PHP: standardowe dla Laravela 12 + `ext-json` (wbudowane w PHP).

## Instalacja

```bash
# 1. Zależności PHP
composer install

# 2. Plik środowiskowy
cp .env.example .env

# 3. Klucz aplikacji
php artisan key:generate

# 4. Baza SQLite — utwórz pusty plik bazy
touch database/database.sqlite

# lub jeśli chcemy używać MySql to w .env.example jest przykład konfiguracji bazy + trzeba utworzyć bazę na lokalnym serwerze DB

# 5. Migracje + dane startowe (tworzy też testowego użytkownika)
php artisan migrate --seed

# 6. Frontend
npm install
npm run build
```

> **Skrót `composer setup`:** wykonuje `composer install`, kopię `.env`, `key:generate`,
> `migrate --force`, `npm install` i `npm run build`. Uwagi:
> 1. **nie tworzy** pliku `database/database.sqlite` — utwórz go (krok 4) **przed**
>    `composer setup`, inaczej migracja się wywali;
> 2. uruchamia migracje **bez seedów** — po nim odpal `php artisan db:seed`, żeby mieć
>    testowego użytkownika i dane.

## Dokumentacja API (Swagger)

Wygenerowany spec OpenAPI nie jest trzymany w repozytorium (`storage/api-docs` jest w
`.gitignore`), więc po instalacji trzeba go zbudować:

```bash
php artisan l5-swagger:generate
```

Lokalnie regeneruje się też automatycznie przy każdym żądaniu, bo `.env` ma
`L5_SWAGGER_GENERATE_ALWAYS=true`.

- **Swagger UI:** `/api/documentation`
- **Spec JSON:** `/docs?api-docs.json`

> **Ważne przy uruchamianiu na innej maszynie:** adres serwera w Swaggerze jest na sztywno
> ustawiony na `https://booking.test` (Laravel Herd) w atrybucie `#[OA\Server(...)]`
> w `app/Http/Controllers/Controller.php`. Jeśli uruchamiasz przez `php artisan serve`
> (czyli `http://localhost:8000`), zmień tam `url`, inaczej przycisk **Try it out** będzie
> wysyłał żądania na zły host.

## Testowanie API
W katalogu głównym jest plik booking.postman_collection.json . Można go zaimportować do Postmana lub Insomnia.

## Uruchomienie (jeśli nie używamy Laravel Herd lub czegoś podobnego)

```bash
php artisan serve            # http://localhost:8000
npm run dev                  # w drugim terminalu (hot reload frontendu)
```

## Dostęp

- **Aplikacja:** `http://localhost:8000` (lub `https://booking.test` przy Laravel Herd)
- **Swagger UI:** `http://localhost:8000/api/documentation`
- **Testowy użytkownik** (z seedera): `test@example.com` / `password`

### Autoryzacja w Swaggerze

Endpointy chronione wymagają tokenu Sanctum:

1. W Swagger UI wywołaj **`POST /api/login`** z danymi testowego użytkownika.
2. Skopiuj `token` z odpowiedzi.
3. Kliknij **Authorize** (kłódka) i wklej **sam token** (bez prefiksu `Bearer` — Swagger
   dokleja go sam).

## Seedery

```bash
php artisan db:seed
```
Seedery tworzą użytkowników, pokoje i rezerwacje.

## Testy

```bash
php artisan test            # cały pakiet
composer test               # to samo + wcześniejsze config:clear
php artisan test --compact  # zwięzły output
```

## Uwagi

Zmiany tłumaczeń w plikach lang na poziomie backendu Laravel są widoczne w aplikacji Vue po pzeładowaniu strony bez konieczności ponownego builda aplikacji.

## Co bym poprawił mając więcej czasu

- Jeśli doszedłby panel admina to separaracja na frontend i backend do osobnych katalogów tak logiki jak i widoków
- Cache - na poziomie repozytoriów aplikacji
- Użyłbym innego kalendarza do wyboru dnia i godziny - tak aby już zarezerwowane dni i godziny dla danego pokoju podczas rezerwacji były nieaktywne i nie dałoby sie ich zaznaczyć/wybrać.

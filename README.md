# Container API

Backend Laravel pentru aplicația de producție containere (UI: `../ui`). În această etapă: autentificare cu Bearer token + 2FA prin SMS, roluri/permisiuni și administrarea utilizatorilor. API-ul pentru proiecte, materiale și comentarii nu e implementat încă (există doar permisiunile pentru ele).

## Setup

PHP rulează în Docker (`compose.yaml`: `app` = php-fpm 8.4, `nginx` pe portul **8093**).

```sh
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed   # în local seed-uiește și conturile demo
```

API-ul e la `http://localhost:8093/api`.

Teste și formatare:

```sh
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint
```

### Seed

| Seeder | Ce face | Când rulează |
|---|---|---|
| `RolesAndPermissionsSeeder` | permisiunile + rolurile admin/designer/executant | mereu (idempotent) |
| `SectionsSeeder` | Sudură (1), Vopsitorie (2), Asamblare (3), Control calitate (4) | mereu (idempotent) |
| `DemoUsersSeeder` | cei 6 utilizatori din `ui/src/data/mockData.js` (aceleași emailuri, parole, telefoane, secții) | doar în `APP_ENV=local`/`development` |

Conturi demo: `andrei.popescu@…` / `admin123` (admin), `ioana.marin@…` / `design123` (designer), `vasile.ionescu@…`, `mihai.radu@…`, `elena.dumitru@…`, `cristian.neagu@…` / `exec123` (executanți). Toate au domeniul `@befu-containere.ro`.

## Variabile `.env` noi

| Variabilă | Implicit | Descriere |
|---|---|---|
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173` | Originile permise, separate prin virgulă (ex. `http://localhost:5173,https://container.spa360.ro`). |
| `SANCTUM_TOKEN_EXPIRATION` | `720` | Durata de viață a token-ului, în minute (720 = 12h). |
| `SANCTUM_STATEFUL_DOMAINS` | gol | Lăsați gol: API-ul folosește doar Bearer token, fără cookie SPA. |
| `SMS_DRIVER` | `log` | Driverul de SMS. `log` scrie mesajul (inclusiv codul) în `storage/logs/laravel.log`. Pentru producție trebuie adăugat un driver real care implementează `App\Contracts\SmsSender`. |
| `AUTH_SMS_CODE_TTL` | `300` | Valabilitatea codului SMS, în secunde. |
| `AUTH_SMS_MAX_ATTEMPTS` | `5` | Încercări greșite permise înainte ca challenge-ul să fie invalidat. |
| `AUTH_SMS_RESEND_COOLDOWN` | `60` | Pauza minimă între retrimiteri, în secunde. |
| `AUTH_DEMO_SMS_CODE` | — | Cod fix acceptat **doar în `APP_ENV=local`** (ex. `123456`), ca UI-ul demo să meargă. Ignorat în orice alt mediu. |

## Fluxul de login (2 pași)

```
UI                                   API
 │ POST /api/auth/login {email, password, device_name?}
 │──────────────────────────────────▶│ verifică parola + is_active
 │                                   │ creează login_challenge (cod 6 cifre, stocat doar ca hash, expiră în 5 min)
 │                                   │ trimite codul prin SMS
 │◀──────────────────────────────────│ {challenge_id, phone_masked, expires_at, resend_available_at}   (fără token)
 │
 │ POST /api/auth/verify {challenge_id, code}
 │──────────────────────────────────▶│ cod greșit → 422, attempts+1; la a 5-a greșeală challenge-ul e invalidat
 │◀──────────────────────────────────│ {token, token_type, expires_at, user}
 │
 │ (opțional) POST /api/auth/resend {challenge_id}   → cod nou; 429 + Retry-After în primele 60s
 │
 │ toate celelalte cereri: Authorization: Bearer <token>
```

- Eroarea de la login e mereu aceeași (`Datele de autentificare sunt incorecte.`), fie că emailul nu există, parola e greșită sau contul e inactiv.
- `device_name` (`ui` implicit, sau `inventar`) devine numele token-ului Sanctum.
- Rate limiting: login = 5/min pe email+IP și 20/min pe IP; verify/resend = 10/min pe challenge+IP și 30/min pe IP → `429`.
- Un utilizator dezactivat nu se mai poate loga; token-urile lui sunt revocate, iar orice token rămas primește `401`.
- Schimbarea parolei (proprie sau de către admin) revocă celelalte token-uri.

## Roluri și permisiuni

| Permisiune | Descriere | admin | designer | executant |
|---|---|:-:|:-:|:-:|
| `projects.view` | Vizualizare proiecte | ✓ | ✓ | ✓ |
| `projects.create` | Creare proiecte | ✓ | ✓ | |
| `projects.close` | Închidere proiecte | ✓ | ✓ | |
| `project_items.manage` | Adăugare/ștergere materiale, alocare secții | ✓ | ✓ | |
| `project_items.work` | Status/cantitate reală — **doar pe secțiile proprii** | ✓ | | ✓ |
| `comments.create` | Comentarii | ✓ | ✓ | ✓ |
| `sections.view` | Vizualizare secții | ✓ | ✓ | ✓ |
| `users.view` | Listare utilizatori | ✓ | | |
| `users.manage` | Administrare utilizatori | ✓ | | |
| `roles.manage` | Administrare roluri/permisiuni | ✓ | | |

Implementare proprie, fără pachete externe:

- `User::hasPermission('users.manage')` — permisiunile vin din rolurile utilizatorului (pivot `role_user`; acum un singur rol per utilizator).
- `Gate::before` (în `AppServiceProvider`) rezolvă orice slug de permisiune, deci merg `->middleware('can:users.manage')`, `$this->authorize('users.manage')`, `Gate::allows(...)`.
- Verificările fine se fac în Policies. `SectionPolicy::work($user, $section)` cere `project_items.work` **și** ca secția să fie alocată utilizatorului (adminul trece). Viitorul `ProjectItemPolicy` trebuie să delege aici cu `$item->section`.
- Rolul `admin` are mereu toate permisiunile (nu poate fi modificat prin API), iar un utilizator nu își poate schimba propriul rol și nu se poate dezactiva pe sine.

## Endpoint-uri

Toate răspund JSON. Trimiteți `Accept: application/json`. Rutele de după login cer `Authorization: Bearer <token>`. Fără token → `401`, fără permisiune → `403`, validare → `422`.

### Autentificare

| Metodă | Rută | Acces |
|---|---|---|
| POST | `/api/auth/login` | public |
| POST | `/api/auth/verify` | public |
| POST | `/api/auth/resend` | public |
| POST | `/api/auth/logout` | autentificat |
| GET | `/api/auth/me` | autentificat |
| PATCH | `/api/auth/me/password` | autentificat |

```http
POST /api/auth/login
{"email": "vasile.ionescu@befu-containere.ro", "password": "exec123", "device_name": "ui"}

200 {"challenge_id": "01a125c6-…", "phone_masked": "+40 7•• ••• 213",
     "expires_at": "2026-10-10T12:30:45+00:00", "resend_available_at": "2026-10-10T12:26:45+00:00"}
422 {"message": "Datele de autentificare sunt incorecte.", "errors": {"email": ["Datele de autentificare sunt incorecte."]}}
```

```http
POST /api/auth/verify
{"challenge_id": "01a125c6-…", "code": "123456"}

200 {"token": "1|I0cQ…", "token_type": "Bearer", "expires_at": "2026-10-11T00:25:45+00:00",
     "user": {"id": 3, "name": "Vasile Ionescu", "email": "vasile.ionescu@befu-containere.ro",
              "phone_masked": "+40 7•• ••• 213", "role": "executant", "sectionIds": [1],
              "permissions": ["comments.create", "project_items.work", "projects.view", "sections.view"],
              "is_active": true}}
422 {"errors": {"code": ["Codul este incorect. Mai aveți 4 încercări."]}}
422 {"errors": {"challenge_id": ["Codul a expirat sau nu mai este valid. Autentificați-vă din nou."]}}
```

```http
POST /api/auth/resend
{"challenge_id": "01a125c6-…"}

200 {"challenge_id": "…", "phone_masked": "…", "expires_at": "…", "resend_available_at": "…"}
429 {"message": "Puteți retrimite codul peste 40 secunde."}   (header Retry-After: 40)
```

```http
GET /api/auth/me                    → 200 {"data": { …user… }}
POST /api/auth/logout               → 204 (revocă doar token-ul curent)
PATCH /api/auth/me/password
{"current_password": "exec123", "password": "parola-noua-1", "password_confirmation": "parola-noua-1"}
                                    → 204 (celelalte token-uri sunt revocate)
```

### Formatul utilizatorului (`UserResource`)

```json
{
  "id": 3,
  "name": "Vasile Ionescu",
  "email": "vasile.ionescu@befu-containere.ro",
  "phone_masked": "+40 7•• ••• 213",
  "role": "executant",
  "sectionIds": [1],
  "permissions": ["comments.create", "project_items.work", "projects.view", "sections.view"],
  "is_active": true
}
```

Câmpul `phone` (numărul complet) apare în plus doar dacă cel care face cererea are `users.manage`. Răspunsurile cu un singur utilizator sunt învelite în `data` (convenția API Resources). Excepție face `verify`, unde utilizatorul e sub `user`.

### Administrare

| Metodă | Rută | Permisiune |
|---|---|---|
| GET | `/api/users?role=&section_id=&search=&is_active=&per_page=&page=` | `users.view` |
| POST | `/api/users` | `users.manage` |
| GET | `/api/users/{id}` | `users.manage` |
| PATCH | `/api/users/{id}` | `users.manage` |
| DELETE | `/api/users/{id}` | `users.manage` — dezactivare (soft) |
| PUT | `/api/users/{id}/sections` | `users.manage` |
| PUT | `/api/users/{id}/role` | `users.manage` |
| GET | `/api/roles` | `roles.manage` |
| PUT | `/api/roles/{slug}/permissions` | `roles.manage` |
| GET | `/api/sections` | `sections.view` |

```http
GET /api/users?role=executant&section_id=1&search=vasile&is_active=1&per_page=15
200 {"data": [ …users… ], "links": {…}, "meta": {"current_page": 1, "per_page": 15, "total": 1, …}}
```

```http
POST /api/users
{"name": "Ana Pop", "email": "ana.pop@befu-containere.ro", "phone": "+40 711 222 333",
 "password": "parola-sigura-1", "role": "executant", "section_ids": [1, 3], "is_active": true}
201 {"data": { …user…, "phone": "+40 711 222 333" }}
```

```http
PATCH /api/users/7      {"name": "…", "email": "…", "phone": "…", "password": "…", "is_active": false}   (toate opționale)
200 {"data": { …user… }}         — parolă nouă sau dezactivare ⇒ token-urile utilizatorului sunt revocate

DELETE /api/users/7     → 200 {"data": { …, "is_active": false }}
DELETE /api/users/{id-ul propriu} → 403 {"message": "Nu vă puteți dezactiva propriul cont."}
```

```http
PUT /api/users/7/sections   {"section_ids": [1, 2]}     → 200 {"data": { …, "sectionIds": [1, 2] }}
PUT /api/users/7/role       {"role": "designer"}        → 200 {"data": { …, "role": "designer", "permissions": […] }}
```

```http
GET /api/roles
200 {"data": [{"id": 1, "slug": "admin", "name": "Administrator", "permissions": ["comments.create", …]}, …],
     "meta": {"permissions": [{"slug": "projects.view", "description": "Vizualizare proiecte"}, …]}}

PUT /api/roles/executant/permissions   {"permissions": ["projects.view", "sections.view"]}
200 {"data": {"id": 3, "slug": "executant", "name": "Executant", "permissions": ["projects.view", "sections.view"]}}
PUT /api/roles/admin/permissions → 422 (rolul admin are mereu toate permisiunile)
```

```http
GET /api/sections
200 {"data": [{"id": 1, "name": "Sudură"}, {"id": 2, "name": "Vopsitorie"},
              {"id": 3, "name": "Asamblare"}, {"id": 4, "name": "Control calitate"}]}
```

## Notă despre deploy: HTTP Basic Auth vs Bearer

UI-ul de pe `container.spa360.ro` e protejat cu HTTP Basic Auth (`.htaccess`). Basic Auth și token-ul API folosesc **același** header, `Authorization`:

- Dacă API-ul stă pe același host sau sub aceeași protecție, browserul trimite `Authorization: Basic …`, iar UI-ul trebuie să trimită `Authorization: Bearer …`. O cerere nu poate avea ambele, deci fie trece de Basic Auth și Sanctum nu vede token-ul (`401`), fie invers.
- **Recomandare:** API-ul pe un subdomeniu separat, ex. `api.container.spa360.ro`, **fără** Basic Auth, cu `CORS_ALLOWED_ORIGINS=https://container.spa360.ro`. API-ul are propria autentificare (parolă + SMS + token), deci Basic Auth nu adaugă nimic acolo.
- Pe subdomeniul API, asigurați-vă că serverul transmite header-ul `Authorization` către PHP (la Apache cu PHP-FPM/CGI poate fi nevoie de `CGIPassAuth On` sau de regula `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`, care există deja în `public/.htaccess` din Laravel).

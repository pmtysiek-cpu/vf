# VeloForum PHP 8.3+ — Silnik Forum & Migrator Invision Community 5

Lekki, modularny i ściśle relacyjny skrypt forum dyskusyjnego napisany w czystym **PHP 8.3+** (bez Node.js) na wzór **Invision Community 5**, wyposażony w pełny migrator bazy danych z IC5 bez utraty struktury relacyjnej oraz haseł użytkowników.

## Wymagania serwerowe

- **PHP 8.3+** z rozszerzeniami: `pdo_mysql`, `mbstring`, `json`, `session`
- **MySQL 8.0+** lub **MariaDB 10.11+** (silnik `InnoDB`, kodowanie `utf8mb4_unicode_ci`)
- **Apache** (`mod_rewrite`) lub **Nginx** wskazujący na katalog `public/`

## Szybka instalacja

1. Skopiuj `.env.example` jako `.env` i uzupełnij dane połączenia do bazy docelowej (`DB_*`) oraz bazy źródłowej Invision Community 5 (`IC5_DB_*`).
2. Zaimportuj strukturę tabel do docelowej bazy danych:
   ```bash
   mysql -u veloforum_user -p veloforum < database/schema.sql
   ```
3. Ustaw katalog `public/` jako *DocumentRoot* serwera WWW.
4. (Opcjonalnie) uruchom `composer dump-autoload -o` — projekt zawiera także wbudowany autoloader PSR-4 w `src/Core/Autoloader.php`, więc działa od razu po wrzuceniu przez FTP.

## Migracja bazy danych z Invision Community 5

Migrator odczytuje tabele źródłowe IC5 w sposób bezpieczny (tylko do odczytu po stronie IC5) i przenosi dane w transakcjach z zachowaniem **oryginalnych kluczy głównych i obcych**:

| Tabela IC5 | Tabela VeloForum | Co jest zachowane |
|---|---|---|
| `core_groups` | `vf_groups` | `g_id`, nazwy grup, kolory formatowania, flagi admin/moderator |
| `core_members` | `vf_members` | `member_id`, login, e-mail, `members_pass_hash` (`$2y$10$` / `$2y$13$`), `members_pass_salt`, grupa główna i dodatkowe, data rejestracji, licznik postów |
| `core_sys_lang_words` | (mapowanie nazw) | Nazwy i opisy działów zapisane w IC5 pod kluczami `forums_forum_{id}` oraz `forums_forum_{id}_desc` |
| `forums_forums` | `vf_forums` | `id`, pełne zagnieżdżone drzewo `parent_id` (`-1` → `NULL`), kolejność `position`, `name_seo`, liczniki tematów i postów |
| `forums_topics` | `vf_topics` | `tid`, `forum_id`, `starter_id`, przypięcie (`pinned`), zamknięcie (`state = closed`), akceptacja, wyświetlenia, slug SEO |
| `forums_posts` | `vf_posts` | `pid`, `topic_id`, `author_id`, `author_name`, treść HTML, data publikacji, historia edycji, flaga pierwszego posta |

### Uruchomienie migracji z wiersza poleceń (zalecane dla dużych baz > 50 tys. postów)

```bash
# Test połączenia i audyt rekordów bez zapisu (Dry-Run)
php bin/migrate-ic5.php --dry-run

# Pełna migracja z zachowaniem ID i haseł
php bin/migrate-ic5.php --execute
```

Dostępny jest również kreator WWW pod adresem `/migrate` (chroniony: dostępny wyłącznie przy pustej bazie przed pierwszym uruchomieniem lub po zalogowaniu na konto administratora).

## Bezpieczeństwo wdrożenia produkcyjnego

- **DocumentRoot = `public/`**: Na serwerze Nginx lub Apache ustaw katalog `public/` jako główny katalog domeny. Dodatkowo główny plik `.htaccess` blokuje dostęp HTTP do `.env`, `config/`, `src/`, `database/` i `views/`.
- **Przykładowa konfiguracja Nginx**:
  ```nginx
  root /var/www/veloforum/public;
  index index.php;
  location / {
      try_files $uri $uri/ /index.php?$query_string;
  }
  location ~ \.php$ {
      include fastcgi_params;
      fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
      fastcgi_pass unix:/run/php/php8.3-fpm.sock;
  }
  ```
- **Ochrona przed atakami**:
  - Zapytania SQL: wyłącznie natywne `PDO` z `ATTR_EMULATE_PREPARES => false` (odporność na SQL Injection).
  - Formularze: tokeny `Csrf` (`hash_equals`) na logowaniu, wylogowaniu, rejestracji, tworzeniu/edycji postów, moderacji i migratorze.
  - XSS: biała lista tagów i atrybutów przez `DOMDocument` w `HtmlSanitizer`.
  - Brute-force: limit 8 nieudanych logowań na sesję (blokada 5 min) + `session_regenerate_id(true)`.
  - Po zakończeniu migracji usuń dane `IC5_DB_*` z pliku `.env`.

## Jak działają hasła użytkowników po migracji?

Invision Community 4.x i 5.x zapisuje hasła użytkowników w `core_members.members_pass_hash` za pomocą standardowego algorytmu PHP `PASSWORD_BCRYPT` (`$2y$10$...` lub `$2y$13$...`). Klasa `VeloForum\Service\AuthService`:

1. Weryfikuje hasło natychmiast przy pomocy `password_verify($haslo, $hash)` bez konieczności resetowania kont.
2. Obsługuje również starsze konta odziedziczone jeszcze z IPS 3.x (`md5(md5($salt) . md5($haslo))`).
3. Po pierwszym udanym logowaniu automatycznie aktualizuje hash w tle do nowoczesnego `PASSWORD_ARGON2ID` (`password_needs_rehash`).

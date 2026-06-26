#  Shrnk

A tiny, self-hosted **URL shortener** with click analytics — built in **PHP + SQLite**. Paste a long link, get a short code, and watch the click counts add up.

![lang](https://img.shields.io/badge/PHP-8.2-777bb4) ![db](https://img.shields.io/badge/SQLite-zero%20config-003b57) ![deps](https://img.shields.io/badge/dependencies-none-54e6ff) ![license](https://img.shields.io/badge/license-MIT-yellow)

##  Features
- **Zero database setup** — uses file-based **SQLite**, created automatically on first run (no MySQL / phpMyAdmin needed)
- **Click analytics** — per-link click counts, created time, and last-visit tracking
- **Safe by default** — server-side URL validation rejects `javascript:`, `data:`, and other non-http(s) schemes; all output is escaped (XSS-safe)
- **Unambiguous short codes** — random base-56 codes with no `0/O/1/l` confusion, uniqueness-checked
- **Post/Redirect/Get** pattern so refreshes never re-submit
- **Copy-to-clipboard** buttons and a clean, responsive dark UI
- Works whether installed at `/` or in a subfolder like `/shrnk/`

##  Run it
1. Drop this folder in your XAMPP `htdocs`.
2. Start **Apache**.
3. Visit **`http://localhost/shrnk/`**.

That's it — the `data/links.sqlite` database is created on the first page load.

##  How it works
| Route | Behavior |
|-------|----------|
| `GET /` | Renders the form + a table of recent links with stats |
| `POST /` (`url=`) | Validates the URL, generates a unique code, stores it, redirects back |
| `GET /?c=CODE` | Looks up the code, increments its click count, and `302`-redirects to the destination |

All persistence is three small files: [db.php](db.php) (PDO/SQLite + helpers), [index.php](index.php) (controller + view), and the auto-created `data/` database.

##  Security notes
- URLs are validated with `filter_var(..., FILTER_VALIDATE_URL)` **and** an explicit scheme allow-list (`http`/`https` only).
- Every dynamic value is rendered through `htmlspecialchars(..., ENT_QUOTES)`.
- All DB access uses prepared statements.

##  License
MIT.

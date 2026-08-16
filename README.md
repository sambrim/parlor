# Parlor

A simple, no-login message board. Pick a handle and start talking. Messages
appear for everyone within a couple of seconds via AJAX polling. Every post is
stored with the sender's IP address for moderation purposes.

Built with PHP 8.5, MySQL, and vanilla JavaScript. Styled for clarity and
warmth — it reads like a real room where people gather to chat.

## Requirements

- **PHP 8.5** (what this is built and tested against; nothing here needs 8.5
  specifically, so 8.0+ will run it unchanged)
- PHP extensions: `pdo_mysql`, `mbstring`
- MySQL 5.7+ or MariaDB 10.2+
- Any web server that runs PHP (Apache, nginx + PHP-FPM, or `php -S` for local work)

## Installing the requirements

Pick the section for your platform, then skip to [Setup](#setup). If you'd
rather not install anything system-wide, jump to
[Docker](#docker-any-platform) — it's the fastest route to a working board.

### Ubuntu 26.04

PHP 8.5 is in the standard archive, so no extra repository is needed:

```bash
sudo apt update
sudo apt install php8.5-cli php8.5-mysql php8.5-mbstring
```

### Ubuntu 24.04 / 22.04

These releases don't ship `php8.5`, so add Ondřej Surý's repository first:

```bash
sudo apt update
sudo apt install -y software-properties-common
sudo LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.5-cli php8.5-mysql php8.5-mbstring
```

`add-apt-repository` misbehaves under non-UTF-8 locales, which is what the
`LC_ALL` prefix is guarding against.

### Debian

Same packages, different source — Sury publishes a Debian repository:

```bash
sudo apt install -y ca-certificates curl lsb-release
sudo curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg \
  https://packages.sury.org/php/apt.gpg
echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] \
https://packages.sury.org/php/ $(lsb_release -sc) main" \
  | sudo tee /etc/apt/sources.list.d/php.list
sudo apt update
sudo apt install php8.5-cli php8.5-mysql php8.5-mbstring
```

### Web server (Debian/Ubuntu)

`php8.5-cli` is enough for `php -S`. For a real server, add one of:

```bash
# Apache
sudo apt install apache2 libapache2-mod-php8.5

# nginx
sudo apt install nginx php8.5-fpm
```

### MySQL (Debian/Ubuntu)

```bash
sudo apt install mysql-server      # or: mariadb-server
sudo mysql_secure_installation
```

### macOS (Homebrew)

```bash
brew install php
php -v                             # confirm it reports 8.5
brew install mysql
brew services start mysql
```

If Homebrew's `php` formula has already moved past 8.5, pin the branch instead:

```bash
brew tap shivammathur/php
brew install shivammathur/php/php@8.5
brew link --overwrite --force php@8.5
```

`pdo_mysql` and `mbstring` are compiled into Homebrew's PHP — nothing extra to
install.

### Windows

1. Download the **VS17 x64 Thread Safe** zip for PHP 8.5 from
   <https://windows.php.net/download/> and extract it to `C:\php`.
2. Copy `php.ini-development` to `php.ini` in that folder, then uncomment these
   lines (delete the leading `;`):

   ```ini
   extension_dir = "ext"
   extension=pdo_mysql
   extension=mbstring
   ```

3. Add `C:\php` to your `PATH` so `php` works from any terminal.
4. Install MySQL with the official
   [MySQL Installer for Windows](https://dev.mysql.com/downloads/installer/).

### Docker (any platform)

Nothing to install but Docker. Drop these two files next to `index.php`:

**`Dockerfile`**

```dockerfile
FROM php:8.5-apache
RUN docker-php-ext-install pdo_mysql
```

**`compose.yaml`**

```yaml
services:
  web:
    build: .
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
  db:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: boardpw
      MYSQL_DATABASE: messageboard
    volumes:
      - ./schema.sql:/docker-entrypoint-initdb.d/schema.sql:ro
      - dbdata:/var/lib/mysql

volumes:
  dbdata:
```

Then:

```bash
docker compose up --build
```

`schema.sql` runs automatically the first time the database container starts, so
you can skip step 1 of Setup. Set `DB_HOST` to `db`, `DB_USER` to `root`, and
`DB_PASS` to `boardpw` in `config.php`. The board is at
<http://localhost:8000>.

`mbstring` ships in the official PHP images; only `pdo_mysql` needs adding,
which is what the `Dockerfile` line does.

### Verify before continuing

```bash
php -v                                    # should say 8.5.x
php -m | grep -E 'pdo_mysql|mbstring'     # should print both
mysql --version
```

If `php -m` prints neither name, the extensions aren't loaded and the board will
fail on its first query with a driver error.

## Files

| File | Purpose |
|---|---|
| `index.php` | The whole board: JSON API endpoints, HTML page, and polling JS |
| `config.php` | Database credentials, board limits, connection helper, IP resolution |
| `schema.sql` | Creates the database and the `messages` table |
| `style.css` | Warm, minimal styling for the board |
| `nginx-parlor.conf` | nginx server block configuration for production |

## Getting started

**For local development:**

```bash
php -S localhost:8000
```

Then import `schema.sql` into MySQL and set your credentials in `config.php`.

## GitHub setup

This is meant to be a private repository. If you're hosting it:

1. Create a private repo on GitHub
2. Clone this project into it:

   ```bash
   git init
   git add .
   git commit -m "Initial commit: Parlor message board"
   git branch -M main
   git remote add origin https://github.com/your-username/parlor.git
   git push -u origin main
   ```

3. **Important:** Before pushing, ensure `config.php` has no real credentials.
   Replace them with placeholders:

   ```php
   const DB_HOST = 'localhost';
   const DB_NAME = 'messageboard';
   const DB_USER = 'parlor';
   const DB_PASS = '';  // Set this on your server, not in the repo
   ```

   Or add `config.php` to `.gitignore` and manage it separately on each server.

4. Add a `.gitignore` file:

   ```
   config.php
   *.log
   .DS_Store
   ```

   This keeps sensitive files out of version control.

## Running Parlor

## Setup

Assumes PHP, MySQL, and the two extensions are already in place — see
[Installing the requirements](#installing-the-requirements) if not.

1. **Create the database and table.**

   ```bash
   mysql -u root -p < schema.sql
   ```

   This creates a database called `messageboard` and a single `messages` table.
   If you want a different database name, change it in `schema.sql` *and* in
   `config.php`.

2. **Add your credentials.** Open `config.php` and set `DB_HOST`, `DB_NAME`,
   `DB_USER`, `DB_PASS`.

3. **Serve the directory.** For a quick local test:

   ```bash
   php -S localhost:8000
   ```

   Then open <http://localhost:8000>. For a real deployment, point a vhost at
   the directory — no rewrite rules or extra routing needed.

## Configuration

All of these live at the top of `config.php`.

| Constant | Default | What it does |
|---|---|---|
| `MAX_HANDLE_LENGTH` | `32` | Longest handle accepted. Must match the `handle` column width in `schema.sql` if you raise it. |
| `MAX_MESSAGE_LENGTH` | `1000` | Longest message accepted |
| `HISTORY_LIMIT` | `100` | Messages loaded on first paint, and the cap on any single fetch |
| `FLOOD_SECONDS` | `2` | Minimum seconds between posts from one IP |
| `POLL_MS` | `2000` | How often the browser checks for new messages |
| `BEHIND_PROXY` | `false` | Whether to trust `X-Forwarded-For` — see below |

### BEHIND_PROXY

Leave this `false` unless PHP genuinely sits behind a proxy or load balancer you
control (nginx, Cloudflare, a cloud LB). `X-Forwarded-For` is a client-supplied
header: on a directly-exposed server, trusting it lets anyone write any IP they
like into your table, which defeats the point of logging it.

## Database schema

```
messages
  id          INT UNSIGNED  auto-increment, primary key
  handle      VARCHAR(32)
  message     TEXT
  ip          VARCHAR(45)   45 chars fits a full IPv6 address
  created_at  DATETIME      defaults to NOW()
```

Two indexes: one on `created_at` for time-based queries and cleanup, one on
`(ip, created_at)` which the flood check uses.

Useful moderation queries:

```sql
-- Everything from one address
SELECT * FROM messages WHERE ip = '203.0.113.5' ORDER BY id DESC;

-- Loudest posters in the last hour
SELECT ip, COUNT(*) AS n FROM messages
 WHERE created_at > NOW() - INTERVAL 1 HOUR
 GROUP BY ip ORDER BY n DESC;

-- Delete a thread of spam
DELETE FROM messages WHERE ip = '203.0.113.5';
```

## API

Both endpoints are on `index.php` and return JSON. Errors come back as
`{"error": "..."}` with a matching HTTP status.

### `GET index.php?action=fetch&since=<id>`

Returns messages with an `id` greater than `since`. Pass `since=0` (or omit it)
to get the tail of the history instead.

```json
{
  "messages": [
    { "id": 42, "handle": "ada", "message": "hello", "ts": 1755300000 }
  ]
}
```

`ts` is a Unix timestamp; the browser formats it in the reader's local time.
Note that `ip` is never sent to the client.

### `POST index.php?action=post`

Form-encoded body with `handle` and `message`. Returns `{"ok": true, "id": 43}`.

Status codes: `405` wrong method, `422` validation failed (empty or too long),
`429` flood limit hit, `500` server-side error.

## How it works

`index.php` checks for an `action` query parameter before emitting any HTML. If
one is present it prints JSON and exits, so the page markup can never leak into
an AJAX response. Without it, the file serves the board.

The browser tracks the highest message `id` it has seen and sends it as `since`
on every poll, so after the first load each request typically returns an empty
array. Polling uses chained `setTimeout` rather than `setInterval`, which stops
requests from stacking up if the server is slow to answer.

Handles are kept in `localStorage` only. Nothing prevents two people from using
the same one — that's inherent to a board with no accounts. The IP column is
what gives you a trail.

## Security notes

- **SQL injection**: all queries use PDO prepared statements with
  `ATTR_EMULATE_PREPARES` off, so the `LIMIT` bindings are real integer
  parameters rather than string interpolation.
- **XSS**: messages are inserted into the DOM with `textContent`, never
  `innerHTML`, so the database stores the raw text and nothing is interpreted as
  markup. If you later build HTML strings instead — to add links or mentions —
  you must escape server-side with `htmlspecialchars()`.
- **Control characters** are stripped and newlines normalised on the way in.
- **Errors** are written to the PHP error log, not shown to the user. Don't
  enable `display_errors` in production; a stack trace would expose your
  credentials.
- There is **no CSRF protection**, because there's nothing to protect: no
  accounts, no privileged actions, and anyone can post anyway.

## Known limits

These are fine for a small board and worth revisiting if it grows.

- **Polling doesn't scale.** At 2-second intervals, every open tab is 30
  requests a minute. Past a few dozen concurrent readers, move to Server-Sent
  Events (`EventSource`) or WebSockets.
- **The table grows forever.** Add a cleanup job:

  ```sql
  DELETE FROM messages WHERE created_at < NOW() - INTERVAL 30 DAY;
  ```

- **Flood control is per-IP and coarse.** It won't stop anyone with a proxy
  pool, and it will throttle everyone behind a shared NAT.
- **No moderation UI.** Deleting a message means running SQL.
- **No edit, delete, or reply.** Messages are append-only.

## Ideas for later

- CSS (the markup is waiting: `#messages`, `#composer`, `#status`, and `li` with
  `time` / `strong` / `span` children)
- Server-Sent Events instead of polling
- An admin page behind HTTP basic auth for deleting messages and banning IPs
- Multiple rooms via a `room` column and `?room=` parameter
- A "N people typing" indicator

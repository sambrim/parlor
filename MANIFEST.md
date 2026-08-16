# Parlor – Project manifest

## What is this?

A single-file message board. No accounts, no login. Pick a handle, type a
message, it appears for everyone. Built for small communities, gatherings, or
testing. Source and all conversation are logged with IP addresses for
moderation.

## Stack

- **Backend:** PHP 8.5 with PDO (MySQL/MariaDB)
- **Frontend:** Vanilla JavaScript, AJAX polling, no dependencies
- **Styling:** Minimal CSS, warm and readable
- **Server:** nginx + PHP-FPM (or Apache, or `php -S` locally)

## Project structure

```
parlor/
├── index.php              # The whole board (HTML + API + JS)
├── config.example.php     # Template for config.php (copy and fill in credentials)
├── config.php             # [NOT IN REPO] Database config (created per-server)
├── style.css              # Warm minimal stylesheet
├── schema.sql             # Database schema (run once to set up)
├── nginx-parlor.conf      # Production nginx config
├── README.md              # User guide and install instructions
├── DEPLOY.md              # Step-by-step deployment guide
├── MANIFEST.md            # This file
└── .gitignore             # Keeps config.php and logs out of version control
```

## Quick start

```bash
# Local development
php -S localhost:8000

# Production
# See DEPLOY.md for full nginx + PHP-FPM setup
```

## Key decisions

- **No framework:** PHP files are committed as-is. No build step, no dependencies
  to install. This makes it portable and easy to understand.
- **Single index.php:** Routes on query params (`?action=fetch | post`). Returns
  JSON for AJAX, serves HTML otherwise. Keeps deployment simple.
- **AJAX polling:** Clients poll every 2 seconds for new messages. Simple to
  understand, good enough for small boards. Migrate to Server-Sent Events or
  WebSockets if traffic grows.
- **IP logging:** Every message stores the sender's IP for moderation. No
  accounts means no persistent identity, so IP is the trail.
- **No third-party CSS:** The stylesheet is hand-written and minimal. Add your
  own design on top.

## Security notes

- All SQL queries use prepared statements (`PDO::ATTR_EMULATE_PREPARES = false`).
- Messages are inserted via `textContent` (not `innerHTML`), so XSS is not a
  concern in the current code.
- Errors are logged, never shown to the user. In production, disable
  `display_errors` in `php.ini`.
- `config.php` holds database credentials and is git-ignored.
- There is no CSRF protection because there are no accounts or privileged
  actions — anyone can post anyway.

## Known limits

- **Polling doesn't scale.** Past ~50 concurrent readers, move to Server-Sent
  Events or WebSockets.
- **No message retention policy.** The table grows forever. Add a cleanup job to
  delete messages older than N days.
- **Flood control is per-IP.** Won't stop proxies; will throttle shared NATs.
- **No moderation UI.** Deleting spam means running SQL queries.
- **No threaded replies or edits.** Messages are append-only.

## Roadmap (if you want to extend it)

- [ ] CSS framework / custom theme
- [ ] Server-Sent Events instead of polling
- [ ] Admin panel (basic auth) for deleting messages and banning IPs
- [ ] Multiple rooms / channels via `?room=` parameter
- [ ] "Typing" indicator (via client JS, no server state)
- [ ] Message timestamps in local timezone
- [ ] Rate limiting per IP + optional Cloudflare integration
- [ ] Markdown or emoji support
- [ ] Optional user mentions via `@handle`
- [ ] Dark mode

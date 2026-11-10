# Offline-Primary Architecture (Future Work)

> Status: **Design only** — not implemented. Document captured 2026-06-04
> from a planning conversation. Implement when bandwidth allows.

## Goal

Run posinnova so that:

- **All data lives at the shop** (mini-PC on the shop LAN).
- **Cashiers always work locally** over LAN — never depend on internet.
- **Remote users (owner, HQ) reach the same data through the internet**, but
  only when the shop's internet is up. If the shop is offline from the
  internet, remote access stops; in-shop operations continue.
- **Zero data duplication, zero sync logic, zero schema changes.**

## Architecture

```
Browser anywhere (owner, accountant, remote staff)
        │  HTTPS  →  pmi.posinnova.com
        ▼
┌────────────────────────────────────┐
│  Cloudflare edge  (or tiny VPS)    │   No business data lives here.
│  - TLS termination                 │
│  - Auth gate (optional portal)     │
│  - Reverse proxy ──────────────────┼──┐
└────────────────────────────────────┘  │
                                        │ Outbound tunnel held open
                                        │ by `cloudflared` on the shop PC
                                        ▼
                       ┌──────────────────────────┐
                       │  Shop mini-PC            │
                       │  XAMPP / Apache / PHP    │
                       │  MySQL  ← only copy      │
                       │  posinnova full codebase │
                       └──────────────────────────┘
                                 ▲
                                 │ LAN (always works,
                                 │      independent of internet)
                          Cashier PCs
                          (browser → http://192.168.x.x)
```

### Three invariants

1. **The only database is at the shop.** The cloud holds *no* business data.
2. **The shop opens an outbound tunnel** (no public IP, no firewall holes).
3. **All cloud requests are proxied down the tunnel** to the shop's Apache.

## Trade-offs (acknowledged)

| ✅ Wins | ⚠️ Costs |
|---|---|
| No sync logic, no conflicts, no schema changes | Remote access dies if shop internet dies |
| Same code on cloud and shop (in fact, *no* code on cloud) | Remote access dies if shop PC is off |
| Owner can work from anywhere when shop is online | Remote performance limited by shop upload speed |
| Cashiers unaffected by internet outages | Multi-shop reporting must federate across tunnels |
| Cloud VPS cheap or unnecessary (Cloudflare Tunnel free) | Backups must be pushed out *by* the shop (mysqldump cron) |
| Single critical attack surface = the tunnel auth | Must enforce MFA + tunnel auth carefully |

## What lives where

| Component | Shop mini-PC | Cloud VPS / Cloudflare |
|---|---|---|
| Full Laravel app (posinnova) | ✅ | ❌ |
| MySQL database | ✅ (the only copy) | ❌ |
| Apache / PHP / XAMPP | ✅ | ❌ |
| `cloudflared` outbound daemon | ✅ | n/a |
| TLS cert / public hostname | ❌ | ✅ (Cloudflare provides free) |
| Optional "shop picker" portal | ❌ | ✅ (only for multi-shop owners) |
| `mysqldump` hourly backup → S3/B2 | ✅ | n/a (S3 is independent) |

**Key point:** we do NOT deploy the posinnova codebase to the VPS.
The VPS / Cloudflare edge is a traffic cop, not a copy of the app.

## Setup steps (single-shop MVP — cheapest path)

> Estimated effort: 2–3 hours per shop, no code changes to posinnova.

### Prerequisites

- A mini-PC (Intel NUC, refurbished SFF, or even a Raspberry Pi 5) at the
  shop running Windows or Linux with XAMPP + the posinnova codebase
  (basically what we develop on today).
- A domain name we control (e.g., `posinnova.com`) added to a free
  Cloudflare account.
- A stable power source at the shop (UPS recommended; the box must be
  always-on for remote access to work).

### Step 1 — Set up the shop mini-PC

1. Install XAMPP (or Docker + LAMP image) on the mini-PC.
2. Deploy the posinnova codebase exactly as we do locally:
   - `git clone` (or copy from a release ZIP) into `htdocs/`.
   - `composer install --no-dev --optimize-autoloader`.
   - Configure `.env` with `APP_URL=http://localhost/posinnova/public`
     for LAN, plus `APP_URL_REMOTE=https://pmi.posinnova.com` if we add
     a tunnel-aware fallback later.
   - `php artisan migrate --force` to create schema.
   - Optional: seed initial business data or import a SQL dump from
     existing data.
3. Verify cashier PCs on the LAN can reach
   `http://<mini-pc-ip>/posinnova/public` and log in.
4. Set Apache + MySQL to auto-start on boot.

### Step 2 — Install Cloudflare Tunnel on the shop mini-PC

1. Download `cloudflared` from
   <https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/>.
2. Authenticate the daemon to the Cloudflare account that owns
   `posinnova.com`:

   ```bash
   cloudflared tunnel login
   ```

3. Create a named tunnel for this shop:

   ```bash
   cloudflared tunnel create pmi-dispensary
   ```

4. Configure routing — create `C:\cloudflared\config.yml`:

   ```yaml
   tunnel: pmi-dispensary
   credentials-file: C:\cloudflared\<tunnel-uuid>.json

   ingress:
     - hostname: pmi.posinnova.com
       service: http://localhost/posinnova/public
     - service: http_status:404
   ```

5. Bind the public hostname to the tunnel:

   ```bash
   cloudflared tunnel route dns pmi-dispensary pmi.posinnova.com
   ```

6. Install as a Windows service so it auto-starts:

   ```bash
   cloudflared service install
   ```

### Step 3 — Lock down the public hostname

In the Cloudflare dashboard, under **Zero Trust → Access → Applications**:

1. Add a new self-hosted application for `pmi.posinnova.com`.
2. Require **email + OTP** (or Google SSO) to reach the app.
3. Restrict the policy to the owner/manager email addresses.

This means *before* a request even reaches the shop Apache, Cloudflare
already authenticated the user. Cashier traffic on the LAN bypasses
this entirely (it never goes through Cloudflare).

### Step 4 — Off-site backups (mandatory)

Because the only DB lives at the shop, a hard-drive failure = total
data loss. So we add **one-way** backup push (this is the only
"sync" in the design):

1. Create a Backblaze B2 bucket (or S3, or even a free
   `restic` backend) — `posinnova-backups-pmi`.
2. Add a Windows Task Scheduler job, every hour:

   ```powershell
   $stamp = Get-Date -Format "yyyyMMdd-HHmm"
   & "C:\xampp\mysql\bin\mysqldump.exe" `
       -u root innovabiss `
       --single-transaction --routines --triggers `
       | & "C:\Program Files\7-Zip\7z.exe" a -si -tgzip `
         "C:\backups\innovabiss-$stamp.sql.gz"
   & "C:\rclone\rclone.exe" copy `
       "C:\backups\innovabiss-$stamp.sql.gz" b2:posinnova-backups-pmi/
   ```

3. Retention: keep 7 daily + 4 weekly snapshots in the cloud bucket.

### Step 5 — Health monitoring (optional but recommended)

Add a simple cron on the cloud side (or a free UptimeRobot monitor)
that hits `https://pmi.posinnova.com/api/heartbeat` every 5 minutes
and alerts via email if down. If the shop loses internet *or* power,
we want to know.

We'd add a tiny route to posinnova for this:

```php
// routes/web.php
Route::get('/api/heartbeat', fn () => response()->json([
    'ok' => true,
    'time' => now()->toIso8601String(),
    'db' => DB::connection()->getPdo() ? 'up' : 'down',
]));
```

## Multi-shop variant (when we have more than one location)

Same as above but each shop gets its own subdomain:
`pmi.posinnova.com`, `bonamoussadi.posinnova.com`, etc.

If the owner needs a **single login** that lets them pick a shop, we
build a tiny "portal" Laravel app on a small VPS:

1. Manages users + their allowed shops (a small `users` and
   `user_shop` table — this *is* data, but it's auth metadata, not
   business data).
2. After login, presents "Which shop?" → redirects to that shop's
   subdomain.
3. Cloudflare Access still gates each subdomain.

Effort: ~1 week.

## What to do for consolidated multi-shop reporting

The proxy model alone cannot give "total sales across 5 shops in one
SQL query" because each shop's data is isolated. Two options:

1. **Push approach** — each shop runs an additional cron that POSTs
   yesterday's summarized totals (not raw rows) to a small cloud DB
   used only for dashboards. ~1 day of work per metric.
2. **Federated query** — the portal app fans out an HTTP request to
   each shop tunnel, merges results in PHP. Slower but always live.

Either way, this is *additive* and can wait until we actually have
multiple shops asking for it.

## Why we explicitly rejected the alternatives (for reference)

| Alternative | Why not |
|---|---|
| **Full two-way sync** (cloud + shop both write, sync via UUIDs) | Months of work, complex conflict resolution, schema rewrite. |
| **One-way mirror** (shop writes, cloud read-only mirror) | Forces duplicating data + writing a push pipeline; user prefers no duplication. |
| **PWA + IndexedDB** | Only handles short outages, doesn't solve "owner wants remote access". |
| **Cloud-primary with offline cache** | Conflict-prone, breaks under long outages. |

## Open questions to settle before implementation

1. Do owners need to make **writes** remotely (create products, edit
   prices)? Under this design, yes — those writes go through the
   tunnel to the shop DB, no special handling. Confirm acceptable.
2. What happens to **POS receipts** that are in-flight when the shop
   internet drops mid-checkout for a remote user? Acceptable to show
   "connection lost, try again from the shop"?
3. **Fiscal/DGI requirements** — some Cameroon tax flows require the
   invoice to be signed by a government API. That signing must happen
   only when the shop has internet. We need a queue table for unsigned
   receipts that flushes on reconnect. Same logic regardless of
   architecture, but worth listing.
4. Hardware spec for the shop mini-PC. Draft: 8 GB RAM, 256 GB SSD,
   wired Ethernet + Wi-Fi fallback, UPS for 30 min runtime.
5. Decide: Cloudflare Tunnel (free, ties us to Cloudflare) vs. self-
   hosted FRP / WireGuard on our own VPS (more control, more ops work).

## TL;DR for the future implementer

1. Shop mini-PC runs the full posinnova stack + a `cloudflared` daemon.
2. Cloudflare gives us a public hostname that tunnels back to it.
3. The VPS / cloud has **no posinnova code and no business DB** — only
   (optionally) a tiny multi-shop portal app.
4. Cashiers use the LAN; remote users use the hostname; same data.
5. Mandatory: hourly `mysqldump` to off-site cloud storage.
6. No schema changes, no sync code, no conflict logic. The whole
   "magic" is the tunnel.

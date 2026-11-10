# Cloudflare Tunnel Setup Runbook (per shop)

> **Audience:** the person installing posinnova at a new client site.
> **Goal:** make the shop's on-premise XAMPP server reachable from the
> internet at `https://<shop>.posinnova.com`, while cashiers keep using
> the LAN URL `http://localhost/posinnova/public` (or
> `http://<shop-pc-ip>/posinnova/public` from other LAN PCs).
> **Result of this guide:** a permanent, auto-starting tunnel that
> survives reboots, has its own subdomain, and is locked down behind
> Cloudflare Access (email OTP).
>
> **Time per shop:** 30–45 minutes (first shop), 15–20 minutes once
> you've done it once.
>
> Companion doc: [`offline-primary-architecture.md`](./offline-primary-architecture.md)
> (explains *why* this design).

---

## 0. Prerequisites (do these once, before visiting the first shop)

These are owned by you (the operator), not per shop:

| Item | Where | Why |
|---|---|---|
| A domain name (e.g. `posinnova.com`) | A registrar (Namecheap, Cloudflare Registrar, GoDaddy…) | Each shop gets a subdomain. |
| A free Cloudflare account | <https://dash.cloudflare.com> | Hosts the tunnel control plane. |
| Domain's nameservers pointing to Cloudflare | Registrar dashboard | Required for Cloudflare to manage DNS. After adding the domain, Cloudflare shows the two `*.ns.cloudflare.com` nameservers — paste them at the registrar. Propagation: 5 min – 24 h. |

**Verification:** on the Cloudflare dashboard the domain shows the
green **"Active"** badge. If it doesn't, you cannot proceed.

---

## 1. Per-shop preparation (before you arrive)

Pick a **subdomain slug** for the shop and write it down. Keep it short
and lowercase, no spaces:

| Shop | Slug | Public URL |
|---|---|---|
| PMI Dispensary, Bamenda | `pmi` | `https://pmi.posinnova.com` |
| Bonamoussadi pharmacy | `bonamoussadi` | `https://bonamoussadi.posinnova.com` |
| Marché Central kiosk | `marche` | `https://marche.posinnova.com` |

Each shop's tunnel will be **named** the same way for clarity, e.g.
`pmi-dispensary`, `bonamoussadi-pharmacy`.

---

## 2. On-site: prepare the shop mini-PC

### 2.1 Verify posinnova works on the LAN

1. Open `http://localhost/posinnova/public/` on the shop PC itself —
   the login screen must load with proper styling.
2. From another LAN device (cashier laptop, manager's PC), open
   `http://<shop-pc-lan-ip>/posinnova/public/` — must also load
   correctly. Find the IP with `ipconfig` → `IPv4 Address` of the
   wired/Wi-Fi adapter (e.g. `192.168.1.42`).

If either of those fails, fix it before continuing. The tunnel
cannot fix a broken local install.

### 2.2 Confirm Laravel proxy/HTTPS handling

The repo already contains the code that lets the *same* install serve
both LAN (HTTP) and tunnel (HTTPS) traffic correctly:

- [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) — overrides the URL
  root using the request's host & forwarded-proto headers.
- [`app/Http/Middleware/TrustProxies.php`](../app/Http/Middleware/TrustProxies.php) — trusts the Cloudflare
  forwarded headers (`$proxies = '*'`).
- `.env` — `APP_URL=http://localhost/posinnova/public` is the LAN
  default; the runtime override above takes care of the tunnel.

After deploying a new client install, **clear caches once**:

```powershell
cd C:\xampp\htdocs\posinnova
php artisan config:clear
php artisan view:clear
php artisan cache:clear
php artisan route:clear
```

---

## 3. Install `cloudflared` on the shop PC

### 3.1 Download

Open <https://github.com/cloudflare/cloudflared/releases/latest> and
download **`cloudflared-windows-amd64.msi`** (≈18 MB).

### 3.2 Install

Double-click the MSI. **It installs silently** — a window flashes up
and disappears. That is normal, not an error.

### 3.3 Verify

Open a **new** PowerShell window (existing terminals won't see the
updated PATH) and run:

```powershell
cloudflared --version
```

Expect output like `cloudflared version 2026.5.2 (built …)`. If the
command is not found, close all PowerShell windows and try again, or
use the full path `& 'C:\Program Files (x86)\cloudflared\cloudflared.exe' --version`.

---

## 4. Phase 1 — Quick-tunnel smoke test (5 min, optional)

Use this only to confirm the box can reach Cloudflare. Skip if you're
confident.

```powershell
cloudflared tunnel --url http://localhost
```

Watch the output for a line like:

```
Your quick Tunnel has been created!
https://random-words.trycloudflare.com
```

Open `https://random-words.trycloudflare.com/posinnova/public/login`
from a phone on **mobile data** (not the shop Wi-Fi — otherwise you're
not actually testing internet access). If the login page renders,
phase 1 works.

`Ctrl+C` in the terminal to stop the quick tunnel before you start
phase 2. Quick-tunnel URLs change on every restart and have no
uptime guarantee — they're throw-away.

---

## 5. Phase 2 — Permanent named tunnel

This is the real setup.

### 5.1 Authenticate cloudflared to your Cloudflare account

```powershell
cloudflared tunnel login
```

A browser window opens at <https://dash.cloudflare.com/argotunnel> →
log in → select the domain (e.g. `posinnova.com`) → click
**Authorize**.

This writes a certificate to `%USERPROFILE%\.cloudflared\cert.pem`.
Keep this file private — it lets the shop PC create tunnels in your
account.

### 5.2 Create the tunnel

```powershell
cloudflared tunnel create pmi-dispensary
```

Output:

```
Tunnel credentials written to C:\Users\<you>\.cloudflared\<tunnel-uuid>.json
Created tunnel pmi-dispensary with id <tunnel-uuid>
```

**Copy the UUID** — you need it in step 5.3.

The `<uuid>.json` file is the tunnel's secret. Lose it = recreate the
tunnel. Back it up to a password manager once.

### 5.3 Write the config file

Create `C:\Users\<you>\.cloudflared\config.yml` (use the *same* user
that ran `tunnel login`):

```yaml
tunnel: pmi-dispensary
credentials-file: C:\Users\<you>\.cloudflared\<tunnel-uuid>.json

# Speed: open 4 parallel connections instead of the default 1.
ha-connections: 4

# Map the public hostname to the local Apache.
# We deliberately keep the /posinnova/public prefix so we don't have
# to rewrite Apache vhosts on the shop box.
ingress:
  - hostname: pmi.posinnova.com
    service: http://localhost:80
    originRequest:
      # Apache is on the same box; long-lived requests (reports, exports)
      # can take a while. Be generous.
      connectTimeout: 30s
      tlsTimeout: 10s
      noTLSVerify: true
      # Pass the original host so Laravel's Host header logic works.
      httpHostHeader: pmi.posinnova.com

  # Required catch-all.
  - service: http_status:404
```

Replace `pmi-dispensary`, `pmi.posinnova.com`, and the UUID per shop.

### 5.4 Bind the public DNS name to the tunnel

```powershell
cloudflared tunnel route dns pmi-dispensary pmi.posinnova.com
```

This creates a CNAME `pmi.posinnova.com → <uuid>.cfargotunnel.com` in
Cloudflare DNS automatically.

### 5.5 Test it manually first

```powershell
cloudflared tunnel run pmi-dispensary
```

Open `https://pmi.posinnova.com/posinnova/public/login` from a phone
on mobile data. Should load with full styling and HTTPS.

`Ctrl+C` to stop. We're about to make it run forever as a service.

### 5.6 Install as a Windows service

```powershell
# Run PowerShell *as Administrator* for this step.
cloudflared service install
```

This creates a Windows service named **"Cloudflared agent"** that
auto-starts on boot. From now on the tunnel runs in the background
even if no one is logged in.

**Important:** when installed as a service, cloudflared reads its
config from `C:\Windows\System32\config\systemprofile\.cloudflared\`
(the LocalSystem profile), **not** from your user profile. Copy the
files over:

```powershell
$src = "$env:USERPROFILE\.cloudflared"
$dst = "C:\Windows\System32\config\systemprofile\.cloudflared"
New-Item -ItemType Directory -Force $dst | Out-Null
Copy-Item "$src\cert.pem"     $dst\ -Force
Copy-Item "$src\config.yml"   $dst\ -Force
Copy-Item "$src\<uuid>.json"  $dst\ -Force
Restart-Service Cloudflared
```

Verify with:

```powershell
Get-Service Cloudflared
Get-Content "C:\Windows\System32\config\systemprofile\.cloudflared\config.yml"
```

Service should show **Running**.

### 5.7 Reboot test

Reboot the mini-PC. After login, open `https://pmi.posinnova.com/...`
from your phone — must still work without anyone starting anything.
If it does, the install is permanent.

---

## 6. Lock it down — Cloudflare Access (mandatory)

Without this step **anyone on the internet** who knows the URL can hit
your shop's login page and brute-force it. Five minutes of work
prevents that.

1. Go to Cloudflare dashboard → **Zero Trust** (left sidebar) →
   **Access → Applications**.
2. Click **Add an application → Self-hosted**.
3. Fill in:
   - **Application name:** `Posinnova - PMI` (or whichever shop)
   - **Session duration:** `24 hours`
   - **Application domain:** `pmi.posinnova.com` (path empty)
4. **Next** → add an Access policy:
   - **Policy name:** `Owners and managers`
   - **Action:** `Allow`
   - **Include:** `Emails` → list the addresses allowed in (owner,
     accountant, you).
   - **Require:** `Email OTP` (or Google SSO if all users have Google
     accounts).
5. Save.

Now opening `https://pmi.posinnova.com/...` from outside first shows
a Cloudflare email-OTP screen. Only after the user enters the
6-digit code emailed to them does the request even reach the shop's
Apache. Cashiers on the LAN bypass this entirely — Cloudflare
Access never sees them.

> **Path bypass (cashiers webhook, payment provider callbacks, etc.)**
> If a payment gateway needs to POST to `https://pmi.posinnova.com/api/webhook`
> without OTP, add a **second policy** scoped to that path with
> `Action: Bypass` and `Include: Everyone`. Be precise — only whitelist
> the exact path.

---

## 7. Off-site backups (mandatory — the *only* sync in this design)

Because the only DB lives at the shop, a disk crash = total loss.
Set up automated mysqldump → cloud storage.

### 7.1 Pick a cloud bucket

Either:
- **Backblaze B2** — cheapest (~$0.005/GB/month). Sign up at
  <https://www.backblaze.com/b2/>. Create a bucket
  `posinnova-backups-<slug>` (private). Generate an application key.
- **AWS S3** — pricier but more common.
- **Cloudflare R2** — free 10 GB/month, S3-compatible.

### 7.2 Install `rclone` on the shop PC

```powershell
winget install Rclone.Rclone -e
# new PowerShell window after install
rclone config
```

Interactive wizard: pick `n` (new remote), name `b2`, type `b2`,
paste the application key id + key, accept defaults. Test:

```powershell
rclone lsd b2:
```

Should list your bucket.

### 7.3 Create the backup script

`C:\posinnova-tools\backup.ps1`:

```powershell
$stamp  = Get-Date -Format "yyyyMMdd-HHmm"
$dir    = "C:\backups"
$file   = "$dir\innovabiss-$stamp.sql.gz"
$bucket = "b2:posinnova-backups-pmi"   # change per shop

New-Item -ItemType Directory -Force $dir | Out-Null

# Dump and gzip in one pipe (needs 7-Zip on PATH).
& "C:\xampp\mysql\bin\mysqldump.exe" -u root innovabiss `
    --single-transaction --routines --triggers `
  | & "C:\Program Files\7-Zip\7z.exe" a -si -tgzip $file

# Push to cloud, then keep only last 7 local files.
rclone copy $file $bucket --quiet
Get-ChildItem $dir -Filter "innovabiss-*.sql.gz" `
  | Sort-Object LastWriteTime -Descending `
  | Select-Object -Skip 7 `
  | Remove-Item -Force
```

### 7.4 Schedule it hourly

Open **Task Scheduler** → Create Task:

- **Name:** `Posinnova hourly backup`
- **Triggers:** Daily, repeat every 1 hour, indefinitely
- **Action:** `powershell.exe -ExecutionPolicy Bypass -File C:\posinnova-tools\backup.ps1`
- **General tab:** "Run whether user is logged on or not", "Run with
  highest privileges".

### 7.5 Retention in the bucket

Configure lifecycle rules in the bucket UI (B2 or S3): keep newest
**24 hourlies + 7 dailies + 4 weeklies**. About 50–200 MB/snapshot
× 35 snapshots ≈ a few GB per shop.

### 7.6 Restore test (do this once per shop, before going live)

```powershell
# Pull yesterday's snapshot to a temp file.
rclone copy b2:posinnova-backups-pmi/innovabiss-<stamp>.sql.gz C:\restore-test\

# Restore into a throw-away DB.
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE restore_test;"
gunzip -c C:\restore-test\innovabiss-<stamp>.sql.gz `
  | & "C:\xampp\mysql\bin\mysql.exe" -u root restore_test
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "SHOW TABLES;" restore_test
# Cleanup
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE restore_test;"
```

A backup you've never restored is not a backup.

---

## 8. Health monitoring (recommended, free)

Sign up for [UptimeRobot](https://uptimerobot.com) (free tier: 50
monitors, 5-min interval). Add a monitor:

- **Type:** HTTPS
- **URL:** `https://pmi.posinnova.com/health` *(see optional route below)*
- **Alert contacts:** your email + the owner's email
- **Interval:** 5 minutes

> **Optional `/health` route (already easy to add to posinnova):**
> ```php
> // routes/web.php
> Route::get('/health', function () {
>     try { \DB::connection()->getPdo(); $db = 'up'; }
>     catch (\Throwable $e) { $db = 'down'; }
>     return response()->json([
>         'ok'   => $db === 'up',
>         'db'   => $db,
>         'time' => now()->toIso8601String(),
>     ]);
> });
> ```
> If you don't add it, monitor the login page instead — but that
> generates an alert for any styling change too.

---

## 9. Multi-shop checklist (cheat sheet)

Per shop, on the shop's mini-PC, in this order:

```
[ ] LAN install verified (http://localhost works on shop PC + 1 cashier)
[ ] Slug chosen: ______________   →  https://_______.posinnova.com
[ ] cloudflared.msi installed; `cloudflared --version` works
[ ] cloudflared tunnel login                     # browser auth
[ ] cloudflared tunnel create <slug>             # note the UUID
[ ] config.yml written with hostname + UUID
[ ] cloudflared tunnel route dns <slug> <slug>.posinnova.com
[ ] cloudflared tunnel run <slug>                # smoke test from phone
[ ] cloudflared service install (PowerShell admin)
[ ] copy cert.pem + config.yml + <uuid>.json into systemprofile dir
[ ] Restart-Service Cloudflared
[ ] Reboot PC → URL still works
[ ] Cloudflare Access app + email-OTP policy created
[ ] Backup script + scheduled task + restore test passed
[ ] UptimeRobot monitor added
[ ] Shop info recorded in shared password manager:
        - slug, public URL
        - tunnel UUID
        - location of <uuid>.json (back it up!)
        - cloudflared cert.pem fingerprint
        - DB root password
        - backup bucket name + rclone remote name
```

---

## 10. Operations & troubleshooting

### "The remote URL is slow" (5–15 s per page)

Expected with any tunnel + Cameroon ↔ Cloudflare distance. Mitigations
applied in `config.yml` (`ha-connections: 4`). Further improvements:

1. Enable Cloudflare caching for static assets (`/css/*`, `/js/*`,
   `/fonts/*`, `/img/*`) via **Page Rules** or **Cache Rules** with
   "Edge Cache TTL: a month". First load slow, subsequent loads fast.
2. Make sure the shop's internet upload speed is ≥ 5 Mbps. The tunnel
   uploads every asset, every AJAX response. Cheap DSL kills you.
3. Tell the owner: **the LAN is always fast**. The tunnel is a
   convenience for off-site work, not a replacement for being on-site.

### "Tunnel works but I get 502 / Bad Gateway"

`cloudflared` is up but can't reach Apache.

```powershell
# Is Apache listening?
Test-NetConnection -ComputerName localhost -Port 80
# Look at cloudflared logs:
Get-EventLog -LogName Application -Source Cloudflared -Newest 20
```

Fix: start XAMPP's Apache; verify `http://localhost/posinnova/public/`
works on the shop PC.

### "Service is running but `<slug>.posinnova.com` returns Error 1033"

Tunnel exists but isn't connected. Usually the credentials file isn't
where the service looks.

```powershell
# Service reads from here, not from your user profile:
Get-ChildItem C:\Windows\System32\config\systemprofile\.cloudflared\
```

You should see `cert.pem`, `config.yml`, and `<uuid>.json`. If any
are missing, re-copy them (step 5.6) and `Restart-Service Cloudflared`.

### "I changed config.yml but nothing changed"

```powershell
Restart-Service Cloudflared
```

The service only reads config at startup.

### "I want to see what's happening live"

```powershell
# Tail the service log:
Get-WinEvent -LogName Application `
  | Where-Object { $_.ProviderName -eq 'Cloudflared' } `
  | Select-Object -First 30 TimeCreated, Message
```

Or stop the service and run `cloudflared tunnel run <slug>` in the
foreground for live output.

### "We need to move the shop to a new PC"

1. On the old PC: copy `C:\Windows\System32\config\systemprofile\.cloudflared\`
   to a USB stick (the `cert.pem` + `<uuid>.json` is the tunnel's
   identity).
2. On the new PC: install cloudflared, drop the same files into the
   systemprofile path, `cloudflared service install`, start.
3. **Do not run `cloudflared tunnel create`** — that would make a
   *new* tunnel. Re-using the credentials file makes the new PC
   inherit the old tunnel's DNS entry seamlessly.

### "We need to delete a shop"

```powershell
cloudflared tunnel delete <slug>
```

Then remove the CNAME record from Cloudflare DNS, and the Access
Application from Zero Trust. Stop & uninstall the service:

```powershell
cloudflared service uninstall
```

### "I want to change the subdomain"

```powershell
cloudflared tunnel route dns <slug> <new-hostname>
```

Update `httpHostHeader` in `config.yml` and `Restart-Service Cloudflared`.
Update the Cloudflare Access Application's domain too.

---

## 11. What this guide does NOT cover (yet)

- **Multi-shop owner portal** — a tiny VPS-hosted "pick a shop" login
  page. Not needed for single-shop owners. See section *Multi-shop
  variant* in [`offline-primary-architecture.md`](./offline-primary-architecture.md).
- **Consolidated cross-shop reporting** — see same doc, "Push" vs
  "Federated query" options.
- **Cameroon DGI fiscal API integration** — out of scope; same logic
  applies regardless of tunnel architecture.
- **High-availability shop hardware** (RAID, dual UPS, hot spare PC) —
  recommend for ≥ 50 transactions/day shops; documentation TBD.

---

## TL;DR for the experienced operator

```powershell
# At the shop, on the shop PC, in an admin PowerShell:
cloudflared tunnel login
cloudflared tunnel create <slug>
# Write config.yml with the UUID, hostname, ha-connections: 4
cloudflared tunnel route dns <slug> <slug>.posinnova.com
cloudflared tunnel run <slug>   # smoke test
cloudflared service install     # then copy cert+config+json into systemprofile
Restart-Service Cloudflared
# Cloudflare dashboard: Zero Trust → Access → Add app → require email OTP
# Task Scheduler: hourly backup.ps1 → rclone to b2
# UptimeRobot: monitor https://<slug>.posinnova.com/health
```

Done.

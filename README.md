# DiSSENT — Cross-Border Journalism Platform

**Final Year Project 2026–27 · University of Limerick**

DiSSENT is a shared workspace for journalists, including refugee and displaced reporters, to submit stories, communicate with media teams and follow editorial review across borders.

The platform combines a PHP website with a Java (Spring Boot) backend. Messages travel between systems through **Apache Kafka**, and records are verified on a **Hyperledger Fabric** blockchain.

---

## Table of Contents

1. [Features](#features)
2. [Tech Stack](#tech-stack)
3. [System Architecture](#system-architecture)
4. [Project Structure](#project-structure)
5. [Prerequisites](#prerequisites)
6. [How to Run the Project](#how-to-run-the-project)
7. [Addresses and Ports](#addresses-and-ports)
8. [Sharing a Demo Link](#sharing-a-demo-link)
9. [Custom Domain dissent.ie (planned)](#custom-domain-dissentie-planned)
10. [Troubleshooting](#troubleshooting)
11. [Page Progress](#page-progress)
12. [Progress Log](#progress-log)
13. [Future Work](#future-work)
14. [Third-party content and permissions](#third-party-content-and-permissions)
15. [Team](#team)

---

## Features

- User registration, login, logout and password reset (strong password rules checked in PHP and shown live; limits on wrong passwords; changing a password logs out other devices)
- Journalist dashboard ("Across DiSSENT", "Your activity", "Getting started") and profile (name, category, picture)
- Secure messaging between journalists and media teams, with one image or PDF per message, unread counts and a phone layout with separate list and chat screens
- Private contacts: nobody can browse the members. You add someone with "New message" and their exact email address; they accept, decline or block the request
- Messages with files or personal details are held until an administrator approves them
- Report submission and editorial review
- Media Centre for published content
- Admin panel
- FAQ page with search, User guide, Terms of use and Privacy notice, and a Settings page (password, language, notifications, delete account)
- Accessible: "Skip to main content" link on every page, diagrams with text versions, styled "Page not found" page
- Blockchain-backed verification of reports (Hyperledger Fabric)
- Event messaging between the two backend systems (Apache Kafka)
- Responsive design for phones, tablets (including iPad) and desktops, with a hamburger menu

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | PHP 8.2 (Apache, in Docker), HTML, CSS, plain JavaScript (no framework) |
| Backend | Java, Spring Boot (Maven) — two services: `securecomm` and `securecomm2` |
| Messaging | Apache Kafka |
| Blockchain | Hyperledger Fabric (smart contract written in Go) |
| Database | MySQL 8 (website), MongoDB 7 (Spring Boot services) |
| Email | PHPMailer (installed with Composer); Mailpit as the local test inbox |
| Containers | Docker, Docker Compose |
| Monitoring | OpenTelemetry (turned off by default; set `OTEL_SDK_DISABLED=false` to turn it on) |

---

## System Architecture

```
  Browser
     │
     ▼
  PHP Website (docs/, Docker)  ──►  MySQL (Docker)
     │
     ▼
  SecureComm 1 (Spring Boot)  ──► Kafka ──►  SecureComm 2 (Spring Boot)
     │                                            │
     └──────────────►  Hyperledger Fabric  ◄──────┘
                       (smartcontract.go)
```

---

## Project Structure

```
DiSSENT-clean/
├── docs/                          # PHP website
│   ├── Pages/                     # All website pages (home, login, register, ...)
│   ├── includes/                  # Shared header.php and footer.php
│   ├── backend/                   # PHP backend (db.php, bootstrap.php security headers, blockchain/messaging clients)
│   ├── assets/                    # CSS, JavaScript and images
│   └── index.php
├── Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main/
│   ├── Chaincode/                 # Hyperledger Fabric smart contract (smartcontract.go)
│   ├── docker and Shell files/    # Docker and setup scripts
│   ├── securecomm/                # Spring Boot service 1 (+ docker-compose.yml)
│   └── securecomm2/               # Spring Boot service 2
├── ai/                            # AI compliance check: RAG + ReAct service (FastAPI), knowledge base, evaluation (ai/README.md)
├── docs-research/                 # Research documents (requirements.md), not served by the website
├── database/migrations/           # SQL changes to run on the database (outside the website folder)
├── docker/                        # Web container (Dockerfile, Apache security settings, composer.json/.lock for PHPMailer) + MySQL init SQL
├── private/                       # Report documents and message files (outside the web root, not pushed to GitHub)
├── site/                          # Static site assets
├── docker-compose.yml             # Website, MySQL, phpMyAdmin, Mailpit and the AI service
├── start-demo.ps1                 # One-click start script
├── .env.example                   # Format of the local .env (DISSENT_API_KEY, API_CRYPTO_AES_KEY_BASE64, SMTP settings, ADMIN_EMAIL, APP_DEBUG)
└── README.md
```

---

## Prerequisites

Install these before running the project:

- **Docker Desktop**: runs the website (PHP + Apache), MySQL, phpMyAdmin, Kafka, MongoDB and the Fabric network
- **Java 17 or newer**: check with `java -version`
- **Hyperledger Fabric test-network** (in WSL2): needed for report verification. See the Fabric section of the [backend README](Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main/README.md)
- **Git** and **VS Code** (recommended)

> Maven does not need to be installed. Both services use the Maven Wrapper (`mvnw`).
> XAMPP is no longer needed. PHP now runs inside the `dissent-web` Docker container.

### Shared API key (`.env`)

PHP and both Spring Boot services authenticate to each other with one shared key, `DISSENT_API_KEY`, stored in a `.env` file in the `DiSSENT-clean` folder:

```
DISSENT_API_KEY=<a long random value>
API_CRYPTO_AES_KEY_BASE64=<32 random bytes, base64>
```

`API_CRYPTO_AES_KEY_BASE64` is the AES-256 key that `securecomm` and `securecomm2` use to encrypt messages between them. Both must use the same value. There is no built-in default any more: both services **refuse to start** without it, or if it is not 32 bytes. `start-demo.ps1` creates it if it is missing; `.env.example` shows how to make one yourself.

- `.env` is in `.gitignore`. **Never commit it.** `.env.example` shows the format.
- `start-demo.ps1` creates `.env` with a random key if it is missing.
- Docker Compose reads `.env` automatically. The Java services read the key from the environment and **refuse to start without it**.
- If you change `DISSENT_API_KEY`, restart all three: the `web` container, `securecomm` and `securecomm2`. If you change the AES key, restart both Java services.

### Local test settings (`.env`)

- `APP_DEBUG` is `0` by default. Add `APP_DEBUG=1` to `.env` to show the password-reset link on the Forgot Password page, so you can test without opening Mailpit. Even then, the link is only shown to a browser on this computer, never through the tunnel. `start-demo.ps1 -Share` always uses `0`.
- `APP_URL` (default `http://localhost:8888`) is the address used inside emailed links. `start-demo.ps1 -Share` sets it to the public address. On a public server, set it in `.env` (for example `https://your-site.example`).
- After changing `.env`, run `docker compose up -d` so the `web` container picks it up.

### Email (`.env`)

The website sends these emails: welcome, password reset (link valid 60 minutes), password changed, account deleted, "We received your message" (Contact form), and a notification to the admin when someone uses the Contact form.

- By default every email goes to **Mailpit**, a local test inbox: open **http://127.0.0.1:8025** to read them. Nothing leaves your computer.
- To send real email, fill in `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` and `SMTP_SECURE` (`tls` for port 587, `ssl` for 465) in `.env`, then run `docker compose up -d`.
- **Example, Gmail:** turn on 2-Step Verification, create an app password at https://myaccount.google.com/apppasswords, then set `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`, `SMTP_SECURE=tls`, `SMTP_USER` and `SMTP_FROM` to your Gmail address and `SMTP_PASS` to the app password (never your normal password). A free SMTP service works the same way with the values it gives you.
- `ADMIN_EMAIL` is who gets the Contact form notification. If it is empty, it goes to `admin@dissent.local` while using Mailpit, and is not sent with real SMTP.
- If an email cannot be sent, the visitor still sees the normal message; the reason is written to `docker logs dissent-web`.

---

## How to Run the Project

### Option A: One-click start (recommended)

1. Open **Docker Desktop** and wait until it is running.
2. Start the Fabric test-network if it is not already running (check with `docker ps` for `peer0.org1.example.com`).
3. Open a terminal in the `DiSSENT-clean` folder and run:
   ```powershell
   powershell -ExecutionPolicy Bypass -File .\start-demo.ps1
   ```
   The script starts the Docker containers and opens `securecomm` and `securecomm2` in their own windows with `DISSENT_API_KEY` and `API_CRYPTO_AES_KEY_BASE64` set. It then waits until everything answers and opens the homepage.
   Add `-Share` to also get a public demo link (see [Sharing a Demo Link](#sharing-a-demo-link)).

### Option B: Step by step

**Step 1: Start the website, MySQL, phpMyAdmin and Mailpit** (from `DiSSENT-clean`)
```powershell
docker compose up -d --build
docker compose ps
```

**Database updates (once per new file in `database/migrations/`)**
If your database was created before a migration was added, run it once. For the Settings page:
```powershell
Get-Content .\database\migrations\2026-11-10_user_settings.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
```
For messages with attachments and review (**needed before Messaging works**), and one account per email:
```powershell
Get-Content .\database\migrations\2026-11-25_message_review.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
Get-Content .\database\migrations\2026-11-25_unique_email.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
```
For the security update (login limits, safer reset links, logging out other devices; **needed before Login works**):
```powershell
Get-Content .\database\migrations\2026-11-25_security.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
```
It logs everyone out once and makes old reset links stop working.

For unread message counts and the consent tick box on Register (**needed before Register works**):
```powershell
Get-Content .\database\migrations\2026-11-26_message_read.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
Get-Content .\database\migrations\2026-11-26_consent.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
Get-Content .\database\migrations\2026-11-26_contacts.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
Get-Content .\database\migrations\2026-11-26_ai_checks.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
```
Existing messages count as read, and people who already have a conversation become contacts. All eight migrations are safe to run again.

**Back up the database first** (do not use `>` in PowerShell, it damages the file). The `database/backups/` folder is not pushed to GitHub.
```powershell
New-Item -ItemType Directory -Force .\database\backups | Out-Null
docker exec dissent-mysql mysqldump -udissent -pdissent_dev --no-tablespaces --single-transaction --result-file=/tmp/dissent_backup.sql dissent_local
docker cp dissent-mysql:/tmp/dissent_backup.sql .\database\backups\
docker exec dissent-mysql rm /tmp/dissent_backup.sql
```

**Make a user an admin:** the person registers first, then run this with their email. It works straight away.
```powershell
docker exec dissent-mysql mysql -udissent -pdissent_dev dissent_local -e "UPDATE users SET role='admin' WHERE email='person@example.com' AND role='user'; SELECT ROW_COUNT() AS changed;"
```
`changed` must be `1`. To undo it, swap `admin` and `user`. Admins can read messages that are held for review, so choose them carefully. An admin cannot decide about their own messages, so **two admins are needed**.

**Step 2: Start Kafka, Zookeeper and MongoDB**
```powershell
cd ".\Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main\securecomm"
docker compose up -d
```
If it says a container name is already in use, see [Troubleshooting](#troubleshooting).

**Step 3: Start SecureComm 1 (port 8081)**
In VS Code, right-click the `securecomm` folder → **Open in Integrated Terminal**, then:
```powershell
$env:DISSENT_API_KEY = (Get-Content ..\..\.env | Where-Object { $_ -like 'DISSENT_API_KEY=*' }) -replace '^DISSENT_API_KEY=',''
$env:API_CRYPTO_AES_KEY_BASE64 = (Get-Content ..\..\.env | Where-Object { $_ -like 'API_CRYPTO_AES_KEY_BASE64=*' }) -replace '^API_CRYPTO_AES_KEY_BASE64=',''
.\mvnw spring-boot:run
```
Wait for: `Started ...Application in X seconds`

**Step 4: Start SecureComm 2 (port 8082)**
Right-click `securecomm2` → **Open in Integrated Terminal**, then run the same three lines.

The first two lines load the keys into that terminal only, without printing them. Run them in every new terminal before `mvnw`.

**Step 5: Open the website**
```
http://localhost:8888/Pages/home.php
```

After editing a PHP or CSS file: save (**Ctrl + S**) and refresh the browser (**F5**, or **Ctrl + F5** if the page still looks old). `docs/` is mounted into the container, so no restart is needed. After changing `docker/web/Dockerfile`, run `docker compose up -d --build`.

**Stopping**
```powershell
docker compose down        # website, MySQL, phpMyAdmin, Mailpit (database is kept; test emails are not)
```
Close the two Spring Boot windows (or press **Ctrl + C** in them). Do **not** add `-v` to `docker compose down` unless you want to wipe the database.

---

## Addresses and Ports

The website, MySQL, phpMyAdmin, Mailpit, the AI service, Kafka, ZooKeeper and DiSSENT's MongoDB are bound to `127.0.0.1`, so they can only be reached from this computer.

| Address | What it is |
|---|---|
| http://localhost:8888/Pages/home.php | **DiSSENT website** (Docker: `dissent-web`) |
| http://localhost:8888/Pages/login.php | Login page |
| http://127.0.0.1:8089 | phpMyAdmin |
| http://127.0.0.1:8025 | Mailpit test inbox (all emails the website sends locally) |
| 127.0.0.1:1025 | Mailpit SMTP (used by the website) |
| 127.0.0.1:3307 | MySQL (Docker: `dissent-mysql`, database `dissent_local`) |
| http://127.0.0.1:8090/health | AI compliance service (Docker: `dissent-ai`); the website calls it inside Docker at `http://ai:8000`. Used only when `AI_ENABLED=1` (see `ai/README.md`) |
| http://localhost:8081 | SecureComm 1 API (a 401 "Missing or invalid X-API-Key header" means it is running) |
| http://localhost:8082 | SecureComm 2 API (same 401 check) |
| 127.0.0.1:9092 | Kafka (no password, so it is kept to this computer) |
| 127.0.0.1:2181 | ZooKeeper (used by Kafka) |
| 127.0.0.1:27018 | MongoDB (Docker: `mongo`, DiSSENT's own; databases `securecomm` and `securecomm2`) |
| localhost:7050 / 7051 / 9051 | Fabric orderer / peer0.org1 / peer0.org2 |

Inside Docker the website lives at `/var/www/html` (= `docs/`). Report documents and message files live at `/var/www/private` (= `private/`), outside the web root. They are only sent by PHP pages that check who is asking.

---

## Sharing a Demo Link

A Cloudflare quick tunnel gives a temporary public `https://....trycloudflare.com` link to the website on your laptop.

1. Install it once: `winget install --id Cloudflare.cloudflared`
2. Start everything with the tunnel:
   ```powershell
   powershell -ExecutionPolicy Bypass -File .\start-demo.ps1 -Share
   ```
   It starts the tunnel, restarts the website with `APP_DEBUG=0` and `APP_URL` set to the public address (so links in emails work), checks that `APP_DEBUG` really is `0`, and prints the link to share.
3. When you finish, stop the tunnel with the `Stop-Process -Id ...` line the script printed, then run `.\start-demo.ps1` again without `-Share` to go back to local settings.

- The link only works while the laptop is on, awake and online. Each new tunnel gets a new link.
- Reset links are never shown on screen through the tunnel, even if `APP_DEBUG=1` is in `.env`.
- Visitors reach the website only, not phpMyAdmin, MySQL or Mailpit. Emails still go to Mailpit unless real SMTP is set, so visitors do not receive them.
- Visitors only see the people they connect with in Messaging (by exact email address). Ask visitors to use test details only.

---

## Custom Domain dissent.ie (planned)

The domain **dissent.ie** has been bought. The Cloudflare setup is waiting for the nameserver change, so the domain does not open the website yet. Until then, use the demo link above.

Still to do: finish the nameserver change, connect the domain to a permanent tunnel or host, set `APP_URL=https://dissent.ie` in `.env` (keep `APP_DEBUG=0`), and add real SMTP settings.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `DISSENT_API_KEY is not set` when starting Java | Run the `$env:DISSENT_API_KEY = ...` line (Option B, Step 3) in that terminal first, or use `start-demo.ps1` |
| `Could not resolve placeholder 'API_CRYPTO_AES_KEY_BASE64'` or `must be 32 bytes` when starting Java | Load the key first (Option B, Step 3) or use `start-demo.ps1`. To make a new key, see `.env.example`. Both services need the same value |
| "Login is temporarily unavailable" after updating | Run `database/migrations/2026-11-25_security.sql` (Option B) |
| "Registration could not be completed" after updating | Run `database/migrations/2026-11-26_consent.sql` (Option B) |
| No unread numbers in Messaging | Run `database/migrations/2026-11-26_message_read.sql` (Option B) |
| Messaging shows an error or no contacts after updating | Run `database/migrations/2026-11-26_contacts.sql` (Option B). Contacts are only people you are connected with; use "New message" to add someone |
| "Too many login attempts. Please wait 15 minutes" | 5 wrong passwords for one email from one place in 15 minutes, or 100 failed logins from one IP address for any emails (so a class on one Wi-Fi is not blocked). Wait 15 minutes. Locally you can unlock at once with `docker exec dissent-mysql mysql -udissent -pdissent_dev dissent_local -e "DELETE FROM auth_attempts"` |
| Logged out with "your password was changed or your account was removed" | Expected after the password was changed or reset on another device. Log in again |
| `Set DISSENT_API_KEY in .env` from `docker compose` | `.env` is missing or empty. Copy `.env.example` to `.env` and set a long random value, or run `start-demo.ps1` |
| Messages show status "unknown" / reports stay "Unconfirmed" | The key differs between PHP and Java, or a Java service is not running. Restart all three after changing `.env` |
| `404 Not Found` on `/docs/...` | The website root is now `docs/` itself. Remove `/docs` from the URL: `http://localhost:8888/Pages/home.php` |
| Website not reachable from phone / another PC | Expected. Ports are bound to `127.0.0.1` for security. To show it to others, see [Sharing a Demo Link](#sharing-a-demo-link) |
| `Cannot start maven from wrapper` | `.mvn\wrapper\maven-wrapper.properties` is missing in that service folder |
| `container name "/kafka"` (or `"/zookeeper"`) `is already in use` | The container already exists but is stopped. Run `docker start zookeeper`, then `docker start kafka`. `start-demo.ps1` now does this by itself |
| `port is already allocated` for 27018 | Something else is using 27018. Find it with `docker ps`. DiSSENT uses only its own `mongo` container on 27018, never the `mongodb` container on 27017 |
| Report approval fails / Fabric errors in the Java window | The Fabric test-network is not running. Start it, then restart both Java services |
| Settings page says language/notification settings are "not available yet" | The `user_settings` table is missing. Run the database update in Option B (`database/migrations/2026-11-10_user_settings.sql`) |
| Messages cannot be loaded or sent, or Admin says to run a migration | Run `database/migrations/2026-11-25_message_review.sql` (Option B) |
| Images cannot be attached to messages | The web container is old (it needs `gd` and `exif`). Run `docker compose up -d --build` |
| A message stays "Waiting for review" | An admin who is not part of the conversation must approve it on the Admin page |
| PHP errors not shown on the page | Expected (`display_errors = Off`). See them with `docker logs dissent-web` |
| `403 Forbidden` for a file in `backend/` or `includes/`, or a `.sql`, `.bak`, `.json` or `.env` file | Expected. These files are never served by the web server |
| Upload rejected | Report documents must be one PDF of up to 5 MB. PHP itself refuses any file over 6 MB (`upload_max_filesize`) |
| Welcome or password reset email never arrives (local) | Open the Mailpit inbox at http://127.0.0.1:8025. If it is empty, check `docker compose ps` shows `dissent-mailpit` running and look for "email could not be sent" in `docker logs dissent-web`. With `APP_DEBUG=1` in `.env`, the Forgot Password page also shows the link (only in a browser on this computer) |
| "PHPMailer is not installed" in the logs | The web container is old. Rebuild it with `docker compose up -d --build` |
| "Your password needs …" when registering or changing a password | Passwords need at least 8 characters, an uppercase letter, a lowercase letter, a number and a special character. The checklist under the box ticks off each rule |
| Page or menu looks old after an update | Hard refresh with **Ctrl + F5** so the browser loads the new CSS |
| `Failed to connect to localhost:4317` | Should no longer appear: OpenTelemetry is disabled at startup. If it does, you are running an old build. Restart with `.\mvnw spring-boot:run` |
| Path errors after unzipping | Windows path limit (260 characters). Keep the project in a short folder path |

---

## Page Progress

✅ Done · 🔄 In progress · ⏳ To do

Every page uses the shared header, so all pages are **responsive** and have the **hamburger menu** on screens up to 1024px wide. Checked on 25/11/2026 in Chrome at 360, 390, 768, 820, 1024, 1280, 1440 and 1600px as visitor, member and admin, and again on 26/11/2026 after the security update and the workshop changes, at 320, 390, 820 and 1440px (no scripts blocked by the Content-Security-Policy and no JavaScript errors). The notes below list what is special about each page.

| Page | Status | Notes |
|---|---|---|
| Header and menu (`includes/header.php`) | ✅ | Hamburger sidebar up to 1024px, two-row menu 1025–1599px, one row from 1600px. Visitors only see Home, About, Media Centre, Verification, FAQ and Contact; members also see Messaging (with a red unread number), Reports and Dashboard; admins also see Admin. "Create account" when logged out. Language list says "Translations coming soon"; English is current and the other languages are shown by name only, greyed out (only English works). It closes when tapping anywhere, also on iPhone. Logged in: a round 36px profile picture (or the first letter) with the name underneath (12px, one line, "…" if long) as one button to My Profile; the header keeps its height. In the phone sidebar the picture and name are side by side. "Skip to main content" link for keyboard users. Footer: About, Contact, Media Centre, FAQ, User guide, Terms of use, Privacy notice |
| Home (`home.php`) | ✅ | Title "Cross-border Exile Journalist Collaboration Platform". What DiSSENT is, who it is for, and "Get started in 3 steps" with "Open →" link cards. Two simple SVG illustrations drawn for DiSSENT (no photos). "Create an account" when logged out, "Go to Dashboard" when logged in |
| About (`about.php`) | ✅ | Purpose, what you can do, "How DiSSENT works" (3 diagrams: message data flow, report review, message review; each an accessible SVG with the same steps as a list), the review criteria administrators use, and "Why blockchain?". The labels sit in their own boxes halfway down and beside the arrows, at least 12px from the boxes above and below: "Approve" green, "Reject" red, "Yes"/"No" navy |
| Contact (`contact_us.php`) | ✅ | Responsive. The form saves messages to the `contact_messages` table; admins read them on the Admin page ("Contact messages"). The sender gets an automatic reply (at most one per 10 minutes) and `ADMIN_EMAIL` gets a notification. A full sentence explains how to reach the team |
| Register (`register.php`) | ✅ | Password rules (checked in PHP + live checklist), "Passwords match" message, eye icon. Professional category list; "Other" shows a box to type your own (max 100 characters). Required tick box for the Terms of use and Privacy notice; the time is saved in `users.consent_at`. Sends a "Welcome to DiSSENT" email. After sending the form it always goes to Login with "Thank you. If this email address can be used, your account is ready. You can log in now.", so nobody can find out who is registered. If the email already has an account, nothing changes and the owner gets a "someone tried to register" email (at most one per hour) |
| Login (`login.php`) | ✅ | Same design as Register, eye icon, "You have been logged out" message. Opening a members' page while logged out shows "Please log in to view that page." and goes back to that page after logging in (only DiSSENT pages from a fixed list). After 5 wrong passwords for one email from one place in 15 minutes it is blocked for 15 minutes (same message for every email); 100 per IP for all emails. Logs you out if your password changed on another device or the account was deleted |
| Forgot Password (`forgot_password.php`) | ✅ | Emails a reset link straight away (locally to Mailpit); it works for 60 minutes. Shows the same message for every email, and asks people to check their spam folder. Only the newest link works. At most 5 requests per email and place in 15 minutes. The link is also shown on screen when `APP_DEBUG=1`, only in a browser on this computer. Real email needs SMTP settings in `.env` |
| Reset Password (`reset_password.php`) | ✅ | Same password rules, checklist and eye icon as Register. Links expire after 60 minutes. Sends a "password was changed" email. Links are stored only as SHA-256 hashes. A reset logs the account out on every device |
| Verification (`verification.php`) | ✅ | Responsive. Search by report ID or title (not the journalist's name). "Reports recorded on the blockchain" (number and list) and "Published, not yet on the blockchain". "Total reports" and "Pending review" moved to the Dashboard. The live service record and Chain Integrity need `securecomm` (8081) and Fabric, otherwise it shows "Unavailable" |
| Dashboard (`dashboard.php`) | ✅ | "Across DiSSENT" (reports submitted, published reports, pending review, members, members from media organisations) and "Your activity" (your reports with what each status means, messages and unread messages, your account in plain words). A "Getting started" box while you have no reports or messages. Quick Actions include My Profile, Settings and User guide |
| Profile (`profile.php`) | ✅ | Edit name, professional category (same list and "Other" box as Register; older values show as Other with the text filled in) and picture. Pictures (JPG, PNG or GIF, max 3 MB, as before) are saved again without hidden data such as the GPS location, camera and date of a phone photo, and turned upright (`backend/image_clean.php`, shared with message images). The email address is read-only. Passwords are changed in Settings only (link) |
| Messaging (`messaging.php`) | ✅ | Responsive. Only your contacts are listed (accepted message requests and earlier conversations); there is no list of all members. "New message" sends a request by exact email address with an optional first message (always the same reply; 20 a day, 5 a week to one address); anything that is not a full email address, such as a name, is refused with a message next to the box, in the browser and on the server. "Search your contacts" filters the list by name. "Message requests" shows name, category and first message with Accept, Decline and Block; the sender is never told about a decline or block. Contacts with the newest conversation first, their professional category, and a red number for unread messages and requests (also on "Messaging" in the menu, updated live). On phones the list and the chat are separate screens with an "All contacts" button and a smaller banner. The chat header shows the person's initial. One JPG, PNG, WebP or PDF per message (max 5 MB; location data removed from images, not from PDFs). Messages with files or personal details wait for an admin. Badges: "Secure transfer: pending", "Secure transfer: verified ✓", "Secure transfer: failed", "Secure transfer: not used (service offline)". Checked on 26/11/2026 that a message reaches "Secure transfer: verified ✓" with both Java services, Kafka, MongoDB and Fabric running and the new AES key. Message text is stored unencrypted in MySQL, and files unencrypted on disk |
| Media Centre (`media_center.php`) | ✅ | Search (wide enough for its hint) → publications (preview of about 200 characters, "Read more" opens the whole report) → 3 recent updates → "How it works" → organisations as white cards (MiCT: the whole card opens https://www.mict-international.org/ in a new tab, with "Open →" and a hover effect; ECHO: no link, no arrow, no hover until its website is confirmed). The repeated "Platform features" cards were removed |
| Reports (`reports.php`) | ✅ | Responsive. Category list (Human rights; Politics and government; Conflict and security; Migration and refugees; Press freedom; Corruption; Environment; Health; Economy and labour; Other with a box). Optional PDF (max 5 MB, stored outside the web root) with exact errors: not a PDF, empty, over 5 MB, name over 150 characters, upload interrupted, over the server limit. "My reports" shows each status with what it means. Editing or withdrawing a report is in Future Work |
| FAQ (`faq.php`) | ✅ | Public. 35 questions in 6 groups, accordion, search box that filters as you type, shared "Back to top" button. Answers come from `faq_items()` in `backend/content.php` |
| Settings (`settings.php`) | ✅ | Logged-in only. Change password (logs out your other devices), language and notification preferences (saved in `user_settings`), delete my account (keeps the text of other people's conversations, deletes your message files). Sends "password was changed" and "account deleted" emails. The preferences are saved but not used yet (see Future Work) |
| Admin (`admin.php`) | 🔄 | Responsive. Report review (each status with what it means), Messages waiting for review (approve or reject, with an audit log), activity and Contact messages. Members see a styled "You don't have permission" page with Dashboard and Home buttons. Missing: a fresh check of approving a report with Java and Fabric running, and paging for long report lists |
| User guide (`user_guide.php`) | ✅ | Public. Numbered steps for 9 topics. Linked from the footer, the FAQ and the Dashboard (not the top menu) |
| Terms of use (`terms.php`) and Privacy notice (`privacy.php`) | ✅ | Public. Only statements that are true today, taken from the code; questions go to the DiSSENT team through the Contact page. Facts still missing are left out and listed in Future Work ("Facts to add to the Privacy notice and Terms before launch") |
| Page not found (`not_found.php`) | ✅ | Styled 404 for every missing address (Apache `ErrorDocument`), with Home and Dashboard buttons |

> Update this table as pages are completed.

---

## Progress Log

Newest first.

| Date | Update |
|---|---|
| 26/11/2026 | Research plan steps 0–2: requirements from the workshops (`docs-research/requirements.md`); baseline evaluation of the current rules on 62 fictional test messages (`ai/eval/`: accuracy 67.7 %, recall 38.7 %, 19 missed risks); new **AI compliance service** `dissent-ai` (FastAPI, RAG with Chroma and local embeddings, ReAct loop, MOCK model, SAMPLE knowledge for IE, DE, UA, IR, AF) on 127.0.0.1:8090; the website uses it when `AI_ENABLED=1` (default 0) and holds the message if it is down, slow or answers wrongly; the Admin page shows "AI suggestion – the admin decides" with reasons, cited passages and steps, and records whether the admin agreed (migration `2026-11-26_ai_checks.sql`, database backed up first). Mock results: accuracy 98.4 %, recall 96.8 %, 1 missed risk (optimistic: the mock and the test set were written together; see `ai/eval/compare.md`). |
| 26/11/2026 | Profile pictures: hidden data (EXIF such as GPS location, camera and date; PNG text chunks) is removed on upload, the same way as message images, and the photo is turned upright. The 4 existing pictures were cleaned once in place (none had a GPS location; two JPEGs had other EXIF data); the originals were backed up to `database/backups/profile_pictures-2026-11-26-before-clean/` (not in git). Privacy notice, FAQ and Profile help text updated. |
| 26/11/2026 | Password reset wording: the Forgot password page, the reset email, the FAQ, the User guide and the Privacy notice now say the link is sent straight away and works for 60 minutes (the expiry is unchanged). About diagram labels moved halfway down their arrows, with space above and below. |
| 26/11/2026 | Header profile button with a round picture (or first letter) and the name underneath; the same small pictures in the Messaging contact list and chat header. Language list shows only the language names. Media Centre partner cards are white and readable (the ECHO card turned navy with grey text on hover); only MiCT is a link. "Back to top" has a white outline and turns white with navy text over the dark footer. FAQ "User guide" link readable on the dark help box. |
| 26/11/2026 | Messaging privacy: members no longer see everyone in Messaging. Contacts are made by message request (exact email address, same reply whether or not the account exists, no suggestions), with Accept, Decline and Block; limits of 20 requests a day and 5 a week to one address; the first message goes through the compliance check. New tables `contacts` and `contact_request_log` (migration `2026-11-26_contacts.sql`, database backed up first); the 21 pairs with an earlier conversation became contacts. FAQ, User guide and Privacy notice updated. |
| 26/11/2026 | Ready for a public launch on dissent.ie: Terms of use and Privacy notice rewritten with only true statements from the code (no placeholders); "Draft", "prototype" and "student project" wording removed from every page; review criteria on About kept without the Draft tag. |
| 26/11/2026 | Smooth movement: links within a page scroll smoothly and pages fade into each other (CSS View Transitions; browsers without support load normally); both are off for "reduce motion". One shared "Back to top" button (`includes/footer.php`, `app.js`) on the long pages (FAQ, User guide, Terms, Privacy, About, Media Centre, Verification, Reports, Dashboard, Admin) after 1.5 screens of scrolling. About diagram labels now sit in boxes beside the arrows (Approve green, Reject red, Yes/No navy). |
| 26/11/2026 | Workshop feedback and usability: the menu shows only public pages to visitors; members' pages send visitors to Login ("Please log in to view that page.") and back afterwards. "Skip to main content" on every page, meta descriptions on all pages, "Media Centre" spelled the same everywhere, "Create an account" on every register link. Language list says "Translations coming soon" and closes on iPhone too. Styled 404 page. Deleted the unused `image_123.jpg`. |
| 26/11/2026 | Messaging: newest conversation first, unread numbers per contact and in the menu (new `messages.read_at`, migration `2026-11-26_message_read.sql`), separate list and chat screens on phones, initial and category in the chat, one wording for the secure-transfer badge. |
| 26/11/2026 | Register and Profile: one category list with "Other" and a text box; Profile no longer changes passwords (Settings only). Register has a required Terms/Privacy tick box; the time is saved (`users.consent_at`, migration `2026-11-26_consent.sql`). Reports: category list and exact upload errors. Status tags explain themselves. |
| 26/11/2026 | Content: new Home (what it is, who it is for, 3 steps, two SVG illustrations), About diagrams with review criteria and "Why blockchain?", Media Centre without repeated cards, Verification searches by ID or title only and lists "Published, not yet on the blockchain", Dashboard got the moved numbers and a "Getting started" box. New User guide, Terms of use and Privacy notice (with `[TO CONFIRM WITH SUPERVISOR]` places). GitHub Pages preview: the note is at the top of pages with forms, and sample items marked "Sample data – preview only" appear only there. |
| 26/11/2026 | Login limit per IP for all emails raised from 30 to 100 in 15 minutes, so a class sharing one Wi-Fi is not blocked. |
| 26/11/2026 | Security fixes from the workshop: after 5 wrong passwords (or 5 reset requests) for one email from one place in 15 minutes, that is blocked for 15 minutes. Reset links are stored as SHA-256 hashes, only the newest one works, and old links stopped working. Changing or resetting a password logs out the other devices (everyone was logged out once). Register no longer shows whether an email is already registered; the owner gets an email instead. |
| 26/11/2026 | Web server: security headers (Content-Security-Policy, HSTS on https, nosniff, Referrer-Policy, no framing by other sites), Secure login cookie on https, PHP and Apache versions hidden. `backend/`, `includes/`, `.sql`, backup and config files are never served, and PHP never runs in `uploads/`. Moved `schema_updates.sql` to `database/migrations/` and the Messaging script to `assets/JavaScript/messaging.js`. `db_count()` only counts listed tables and conditions. |
| 26/11/2026 | `APP_DEBUG` is now `0` by default and reset links are only shown on screen to a browser on this computer. New `start-demo.ps1 -Share` starts the public tunnel with `APP_DEBUG=0` and the public `APP_URL` (tested with a fake cloudflared, nothing went public). |
| 26/11/2026 | Java services: removed the built-in AES key. They now need `API_CRYPTO_AES_KEY_BASE64` (32 bytes) from `.env` and stop with a clear message without it. A new key was made (the old one is still in the git history, so it must never be used again). Kafka and ZooKeeper now only listen on 127.0.0.1. Removed `connection-org1.json.backup-*` from git. Checked that a message reaches "Secure transfer: verified ✓" with the new key. |
| 25/11/2026 | Messages are checked before delivery. If they have a file, phone number, email address, ID-like number or IBAN, they wait for an admin, who approves or rejects them. The receiver sees nothing and nothing goes to Kafka until then. Every decision is logged. Rules are in `backend/compliance.php`. |
| 25/11/2026 | Messages can have one image or PDF (max 5 MB). Files are stored outside the website, checked by content, and only the right people can open them. Location data is removed from images. Deleting an account deletes the files it sent. |
| 25/11/2026 | New emails: password changed, account deleted, Contact form reply and admin notification (`ADMIN_EMAIL`). Password reset now only says "updated" if the change was really saved. |
| 25/11/2026 | Members who open the Admin page see a styled "You don't have permission to view this page". Register shows "This email is already registered" next to the email box. |
| 25/11/2026 | Checked that `users.email` has no duplicates and is already unique, and added a migration so other copies of the database match. Chat times are now right in every time zone (they were read as local time). |
| 25/11/2026 | Responsive check of all 16 pages at 8 widths as visitor, member and admin. Fixed: the footer floating above the bottom on short pages, its links wrapping on small phones, cut-off search hints, form hints and long account names, and small links on Verification, Reports and Admin. |
| 25/11/2026 | `start-demo.ps1` now starts existing stopped `kafka` and `zookeeper` containers instead of failing with "container name already in use". |
| 25/11/2026 | New site icon (navy with a white "D"), linked from the shared header and copied to the GitHub Pages preview. |
| 25/11/2026 | Used a Cloudflare quick-tunnel link to share the website for the workshop, and added the steps to this README. |
| 25/11/2026 | Noted that the domain dissent.ie has been bought; the Cloudflare setup is waiting for the nameserver change. |
| 10/11/2026 | Added email with PHPMailer: a "Welcome to DiSSENT" email after registration and a "Reset your DiSSENT password" email (link valid 60 minutes), in DiSSENT colours with a plain-text version. Added Mailpit as a local test inbox (http://127.0.0.1:8025); real SMTP can be set in `.env`. Sending problems are logged, never shown to visitors. |
| 10/11/2026 | GitHub Pages preview: removed the fixed blue bar at the bottom. Submitting a form now shows a small friendly message below it ("This is a preview of DiSSENT…") instead of a browser alert, and nothing is sent, so there are no "405" errors. |
| 10/11/2026 | Dashboard: added My Profile and Settings to Quick Actions (Settings was already in the header menu and phone sidebar). |
| 10/11/2026 | GitHub Actions: deleted `gh-pages.yml`, which deployed at the same time as `deploy-dissent.yml` and made the live site change at random. Updated every action in `deploy-dissent.yml` to its newest version that runs on Node.js 24 (no more "Node.js 20 is deprecated" warnings). The build report now marks pages that need a login (dashboard, profile, settings, etc.) as "Needs login" instead of "Built". |
| 10/11/2026 | Deleting an account no longer deletes the other person's conversation: messages are kept and shown from "Deleted user", the conversation can still be read but not replied to, and the server refuses new messages to a deleted account. |
| 10/11/2026 | Reviewed the remaining pages so they all match: one shared page banner, button colour, message box and form-field style in `base.css`; removed duplicate and unused CSS; no inline styles left; every page checked at 390, 820, 1024, 1440 and 1600px as visitor, member and admin. Added a site icon (`favicon.ico`). |
| 10/11/2026 | Dashboard now shows real numbers from the database and your own activity instead of fixed example values. |
| 10/11/2026 | Admin page: added the standard page banner, quick section links and a "Contact messages" section so admins can read messages from the Contact page (newest first). |
| 10/11/2026 | Fixed the messaging helpers: `send_message.php` checks the recipient and message length, `get_new_messages.php` answers with JSON instead of a login redirect when logged out, and `message_status.php` no longer sends a stray blank line before its JSON. |
| 10/11/2026 | Added a Settings page: change password, choose a language, choose email notifications (saved in a new `user_settings` table, see `database/migrations/`), and delete your account. Deleting removes your login, settings and unpublished reports; approved reports stay published as "Deleted user". |
| 10/11/2026 | Added an FAQ page with 26 short questions and answers in 6 groups and a search box. Added FAQ to the menu and footer, and Settings next to your name. The two-row menu now covers screens up to 1599px so all links fit. Footer links are easier to tap. |
| 10/11/2026 | Improved the register, login, forgot password, reset password, logout and profile pages: one shared design, an eye icon inside password boxes, strong password rules checked in PHP and shown as a live checklist, a live "Passwords match" message, and correct browser autofill. Fixed the Full name box on Register showing "My profile". Logout now shows "You have been logged out". Reset links use the right http/https and are shown on screen locally only. |
| 10/11/2026 | Fixed the missing Admin link on the Dashboard page for admin users. |
| 10/11/2026 | Made every page responsive for phones, iPad and desktop. Added a hamburger sidebar menu (up to 1024px) and a two-row menu for laptops. Wide tables scroll inside their own box, and the Messaging page now works on phones. |
| 10/11/2026 | Gave DiSSENT its own MongoDB on 127.0.0.1:27018 (it was using another project's `mongodb` container) and moved the existing DiSSENT data across. |
| 10/11/2026 | Added `start-demo.ps1`, a one-click script that starts the containers and both Java services and opens the website. |
| 10/11/2026 | Turned off OpenTelemetry in both Java services, so the "Failed to connect to localhost:4317" errors no longer appear. |
| 10/11/2026 | PHP and both Java services now share one API key (`DISSENT_API_KEY`) from the `.env` file. The Java services will not start without it. |
| 10/11/2026 | Fixed CSS files that did not load (404 errors) on the Register, Login and Media Centre pages. |
| 10/11/2026 | Made the Docker setup safer: only `docs/` is served, uploaded reports in `private/` are outside the website, ports only work on this computer, and PHP settings were added (6 MB uploads, errors hidden from visitors, safer login cookies). |
| 10/11/2026 | Moved the website, MySQL (port 3307) and phpMyAdmin (port 8089) into Docker. XAMPP is no longer needed. |
| 09/11/2026 | Updated login, register and other pages with new CSS and JavaScript. Cleaned up project folder structure. Fixed Maven Wrapper so Spring Boot runs with `.\mvnw spring-boot:run`. Set up local PHP server for testing. Removed private keys and uploaded files from the repository. |

> Add a new row (at the top) each time you push important changes.

---

## Future Work

**Done**
- [x] Run the website, MySQL and phpMyAdmin in Docker
- [x] One shared API key between PHP and the Java services
- [x] Responsive pages and hamburger menu
- [x] Strong password rules, eye icons and new design on the account pages
- [x] Show the forgot-password link on screen when testing locally
- [x] Site works offline for the demo (no outside fonts, CSS or JavaScript are loaded)
- [x] FAQ page (questions also ready to use as an AI assistant's knowledge base)
- [x] Settings page with delete my account
- [x] Show real numbers on the Dashboard
- [x] Let admins read Contact form messages (Admin page)
- [x] Same design on every page (shared banner, buttons, message boxes and form fields)
- [x] Deleted the old `gh-pages.yml` workflow; `deploy-dissent.yml` is the only one that deploys
- [x] Send welcome and password reset emails (tested locally with Mailpit)
- [x] Public demo link using a Cloudflare quick tunnel
- [x] Message attachments and admin review of held messages
- [x] Password changed, account deleted and Contact form emails
- [x] Proper "no permission" page when a member opens Admin
- [x] Security fixes from the workshop (login limits, hashed reset links, logging out other devices, security headers, no built-in AES key)
- [x] Log out other devices when a password changes
- [x] Re-test that a message reaches "Secure transfer: verified ✓" with Java, Kafka and Fabric running
- [x] Workshop feedback and usability changes (menu by login state, unread counts, phone messaging, categories, User guide, Terms, Privacy, 404 page)
- [x] Write a user guide (`user_guide.php`)

**Still to do**
- [ ] Translate the site and use the saved language preference. Plan: about 900–1,100 pieces of text (about 10,000 words: ~313 on public pages, the rest on members' pages, error messages and 6 emails). Put them behind a `t('key')` function with `lang/en.php` and `lang/fa.php`, choose the language from Settings (or a cookie for visitors), set `<html lang dir>`, and change 47 CSS left/right rules to start/end for right-to-left languages. About 3–5 days of coding and testing, plus a **human** Farsi translation (not machine)
- [ ] Facts to add to the Privacy notice and Terms before launch (they are left out of the pages until they are known): the organisation's legal name, a contact email address, the hosting country, the email provider, the exact retention period, and the legal basis for using personal data
- [ ] Let members see and undo the people they declined or blocked, and remove a contact
- [ ] Media centre roles (super admin and media centre admins); the `contacts` table already has `source` and `centre_id` so members of the same centre can be connected later: plan agreed in part; invite link vs. join request, journalists with no media centre, messaging across centres and the MiCT/ECHO agreements still to be confirmed
- [ ] Choose a licence with the supervisor (UL rules); for now "all rights reserved"
- [ ] Send email notifications using the saved notification preferences
- [ ] Let users edit or withdraw their own reports
- [ ] Put real SMTP settings in `.env` on the public server (emails currently go to the local Mailpit inbox)
- [ ] Let admins mark contact messages as handled or delete them
- [ ] Database: add the missing foreign keys and add a clean `schema.sql` (only `database/migrations/schema_updates.sql`, migrations and a database export exist now)
- [ ] Connect dissent.ie (waiting for the nameserver change) to a permanent tunnel or host
- [x] RAG + ReAct compliance pipeline with a mock model, sample knowledge, fail-safe, admin view and evaluation (`ai/README.md`)
- [ ] Choose the real model with the supervisor (local Ollama or an approved cloud model) and finish `OllamaModel` or `CloudModel` in `ai/app/models.py`
- [ ] Replace the SAMPLE knowledge with the agreed legal texts for each country (`ai/knowledge/README.md`)
- [ ] Decide whether to store a country for each member, so the AI searches the right laws (sensitive for exiled journalists)
- [ ] Write a held-out test set with people other than the developer (the mock's results are optimistic) and fill in the "Real model" column of `ai/eval/compare.md`
- [ ] Switch `AI_ENABLED=1` on the public server once the real model is evaluated
- [ ] LLM / AI integration (help assistant using the FAQ)
- [ ] Encrypt stored messages and files, and remove hidden details from PDFs
- [ ] Second admin account (an admin cannot review their own messages)
- [ ] Write up the evaluation from the workshop on 25/11/2026
- [ ] Re-test approving a report → blockchain with Java, Kafka and Fabric running
- [ ] Email confirmation before a new account can be used (needs real SMTP first)
- [ ] Passwords for Kafka and ZooKeeper (SASL) if they ever run on a shared server (now they only listen on 127.0.0.1)
- [ ] Confirm the organisations shown on the Media Centre page, and ECHO's website (shown without a link for now)
- [ ] Add automated tests
- [ ] Prepare demo day script and backup recording
- [ ] Test on a real iPhone and iPad (so far tested in Chrome at phone and tablet sizes)

---

## Third-party content and permissions

There is **no licence file yet**: the project is "all rights reserved" until the supervisor confirms the University of Limerick rules. Any licence chosen later covers only our own code, not the items below.

**Logos and images**

| Item | Where | Status |
|---|---|---|
| University of Limerick logo | `site/assets/university-of-limerick.svg` (GitHub Pages landing page) | Permission to be confirmed with the supervisor |
| Dublin City University logo | `site/assets/dublin-city-university.svg` | Permission to be confirmed with the supervisor |
| Research Ireland logo | `site/assets/research-ireland.svg` | Permission to be confirmed with the supervisor |
| Banner photos | `docs/assets/images/image_111.jpg`, `image_333.jpg` | Source unknown (git does not record it). Confirm permission or replace |
| Home and About illustrations | Inline SVG in `home.php` and `about.php` | Drawn for DiSSENT, no permission needed |

`image_123.jpg` was not used and was deleted on 26/11/2026.

**Downloaded code:** the `Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main` folder (securecomm, securecomm2, chaincode). Origin and licence: **to be confirmed with the supervisor** (its `pom.xml` has no licence).

**Libraries and tools**

| Library or tool | Used for | Licence |
|---|---|---|
| PHPMailer | Sending emails | LGPL-2.1 |
| PHP, Apache HTTP Server (`php:8.2-apache` image) | Website | PHP License 3.01, Apache-2.0 |
| Spring Boot, Spring Kafka | securecomm and securecomm2 | Apache-2.0 |
| Hyperledger Fabric, Fabric Gateway for Java | Blockchain records | Apache-2.0 |
| Bouncy Castle (`bcprov-jdk15on`) | Cryptography in Java | MIT-style Bouncy Castle licence |
| Lombok | Java code generation (build only) | MIT |
| Apache Kafka, ZooKeeper (`confluentinc/cp-*` images) | Message queue | Apache-2.0 (check Confluent's terms for the images) |
| MongoDB (`mongo:7` image) | securecomm databases | SSPL (server) |
| MySQL 8.0 | Website database | GPL-2.0 |
| phpMyAdmin | Database admin page (local only) | GPL-2.0 |
| Mailpit | Local test inbox | MIT |
| Composer | Installing PHPMailer | MIT |
| cloudflared | Demo link | Apache-2.0 |

---

## Team

To be completed.

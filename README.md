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
8. [Troubleshooting](#troubleshooting)
9. [Page Progress](#page-progress)
10. [Progress Log](#progress-log)
11. [Future Work](#future-work)
12. [Team](#team)

---

## Features

- User registration, login, logout and password reset (strong password rules checked in PHP and shown live)
- Journalist dashboard and profile (edit name, category, picture and password)
- Secure messaging between journalists and media teams
- Report submission and editorial review
- Media Centre for published content
- Admin panel
- FAQ page with search, and a Settings page (password, language, notifications, delete account)
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
│   ├── backend/                   # PHP backend (db.php, blockchain/messaging clients, SQL)
│   ├── assets/                    # CSS, JavaScript and images
│   └── index.php
├── Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main/
│   ├── Chaincode/                 # Hyperledger Fabric smart contract (smartcontract.go)
│   ├── docker and Shell files/    # Docker and setup scripts
│   ├── securecomm/                # Spring Boot service 1 (+ docker-compose.yml)
│   └── securecomm2/               # Spring Boot service 2
├── database/migrations/           # SQL changes to run on the database (outside the website folder)
├── docker/                        # Dockerfile for the PHP web container + MySQL init SQL
├── private/                       # Uploaded report documents (outside the web root, not pushed to GitHub)
├── site/                          # Static site assets
├── docker-compose.yml             # Website, MySQL and phpMyAdmin
├── start-demo.ps1                 # One-click start script
├── .env.example                   # Format of the local .env (DISSENT_API_KEY)
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
```

- `.env` is in `.gitignore`. **Never commit it.** `.env.example` shows the format.
- `start-demo.ps1` creates `.env` with a random key if it is missing.
- Docker Compose reads `.env` automatically. The Java services read the key from the environment and **refuse to start without it**.
- If you change the key, restart all three: the `web` container, `securecomm` and `securecomm2`.

### Local-only settings in `docker-compose.yml`

- `APP_DEBUG: "1"` shows the password-reset link on the Forgot Password page, because email is not set up locally. **Never set this on a public server.**
- `APP_URL: http://localhost:8888` is the address used inside reset links. On a public server, set it to the real address (for example `https://your-site.example`).
- After changing anything in `docker-compose.yml`, run `docker compose up -d` so the `web` container picks it up.

---

## How to Run the Project

### Option A: One-click start (recommended)

1. Open **Docker Desktop** and wait until it is running.
2. Start the Fabric test-network if it is not already running (check with `docker ps` for `peer0.org1.example.com`).
3. Open a terminal in the `DiSSENT-clean` folder and run:
   ```powershell
   powershell -ExecutionPolicy Bypass -File .\start-demo.ps1
   ```
   The script starts the Docker containers and opens `securecomm` and `securecomm2` in their own windows with `DISSENT_API_KEY` set. It then waits until everything answers and opens the homepage.

### Option B: Step by step

**Step 1: Start the website, MySQL and phpMyAdmin** (from `DiSSENT-clean`)
```powershell
docker compose up -d --build
docker compose ps
```

**Database updates (once per new file in `database/migrations/`)**
If your database was created before a migration was added, run it once. For the Settings page:
```powershell
Get-Content .\database\migrations\2026-11-10_user_settings.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local
```
It is safe to run again (it only creates the table if it is missing).

**Step 2: Start Kafka, Zookeeper and MongoDB**
```powershell
cd ".\Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main\securecomm"
docker compose up -d
```

**Step 3: Start SecureComm 1 (port 8081)**
In VS Code, right-click the `securecomm` folder → **Open in Integrated Terminal**, then:
```powershell
$env:DISSENT_API_KEY = (Get-Content ..\..\.env | Where-Object { $_ -like 'DISSENT_API_KEY=*' }) -replace '^DISSENT_API_KEY=',''
.\mvnw spring-boot:run
```
Wait for: `Started ...Application in X seconds`

**Step 4: Start SecureComm 2 (port 8082)**
Right-click `securecomm2` → **Open in Integrated Terminal**, then run the same two lines.

The first line loads the key into that terminal only, without printing it. Run it in every new terminal before `mvnw`.

**Step 5: Open the website**
```
http://localhost:8888/Pages/home.php
```

After editing a PHP or CSS file: save (**Ctrl + S**) and refresh the browser (**F5**, or **Ctrl + F5** if the page still looks old). `docs/` is mounted into the container, so no restart is needed. After changing `docker/web/Dockerfile`, run `docker compose up -d --build`.

**Stopping**
```powershell
docker compose down        # website, MySQL, phpMyAdmin (data is kept)
```
Close the two Spring Boot windows (or press **Ctrl + C** in them). Do **not** add `-v` to `docker compose down` unless you want to wipe the database.

---

## Addresses and Ports

The website, MySQL and phpMyAdmin are bound to `127.0.0.1`, so they can only be reached from this computer.

| Address | What it is |
|---|---|
| http://localhost:8888/Pages/home.php | **DiSSENT website** (Docker: `dissent-web`) |
| http://localhost:8888/Pages/login.php | Login page |
| http://127.0.0.1:8089 | phpMyAdmin |
| 127.0.0.1:3307 | MySQL (Docker: `dissent-mysql`, database `dissent_local`) |
| http://localhost:8081 | SecureComm 1 API (a 401 "Missing or invalid X-API-Key header" means it is running) |
| http://localhost:8082 | SecureComm 2 API (same 401 check) |
| localhost:9092 | Kafka |
| 127.0.0.1:27018 | MongoDB (Docker: `mongo`, DiSSENT's own; databases `securecomm` and `securecomm2`) |
| localhost:7050 / 7051 / 9051 | Fabric orderer / peer0.org1 / peer0.org2 |

Inside Docker the website lives at `/var/www/html` (= `docs/`). Uploaded report files live at `/var/www/private` (= `private/`), outside the web root, so they cannot be downloaded by URL.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `DISSENT_API_KEY is not set` when starting Java | Run the `$env:DISSENT_API_KEY = ...` line (Option B, Step 3) in that terminal first, or use `start-demo.ps1` |
| `Set DISSENT_API_KEY in .env` from `docker compose` | `.env` is missing or empty. Copy `.env.example` to `.env` and set a long random value, or run `start-demo.ps1` |
| Messages show status "unknown" / reports stay "Unconfirmed" | The key differs between PHP and Java, or a Java service is not running. Restart all three after changing `.env` |
| `404 Not Found` on `/docs/...` | The website root is now `docs/` itself. Remove `/docs` from the URL: `http://localhost:8888/Pages/home.php` |
| Website not reachable from phone / another PC | Expected. Ports are bound to `127.0.0.1` for security |
| `Cannot start maven from wrapper` | `.mvn\wrapper\maven-wrapper.properties` is missing in that service folder |
| `port is already allocated` for 27018 | Something else is using 27018. Find it with `docker ps`. DiSSENT uses only its own `mongo` container on 27018, never the `mongodb` container on 27017 |
| Report approval fails / Fabric errors in the Java window | The Fabric test-network is not running. Start it, then restart both Java services |
| Settings page says language/notification settings are "not available yet" | The `user_settings` table is missing. Run the database update in Option B (`database/migrations/2026-11-10_user_settings.sql`) |
| PHP errors not shown on the page | Expected (`display_errors = Off`). See them with `docker logs dissent-web` |
| Upload rejected | Files are limited to 6 MB (`upload_max_filesize`) |
| Password reset email never arrives (local) | Expected: email is not set up locally. The Forgot Password page shows the reset link on screen while `APP_DEBUG: "1"` is set in `docker-compose.yml` |
| "Your password needs …" when registering or changing a password | Passwords need at least 8 characters, an uppercase letter, a lowercase letter, a number and a special character. The checklist under the box ticks off each rule |
| Page or menu looks old after an update | Hard refresh with **Ctrl + F5** so the browser loads the new CSS |
| `Failed to connect to localhost:4317` | Should no longer appear: OpenTelemetry is disabled at startup. If it does, you are running an old build. Restart with `.\mvnw spring-boot:run` |
| Path errors after unzipping | Windows path limit (260 characters). Keep the project in a short folder path |

---

## Page Progress

✅ Done · 🔄 In progress · ⏳ To do

Every page uses the shared header, so all pages are **responsive** (phones, iPad, desktop) and have the **hamburger menu** on screens up to 1024px wide. The notes below list what is special about each page.

| Page | Status | Notes |
|---|---|---|
| Header and menu (`includes/header.php`) | ✅ | Hamburger sidebar up to 1024px, two-row menu 1025–1599px, one row from 1600px. FAQ in the menu and footer; Settings next to your name when logged in. "Get Started" only shows when logged out |
| Home (`home.php`) | ✅ | Responsive. "Get Started" when logged out, "Go to Dashboard" when logged in |
| About (`about.php`) | 🔄 | Responsive. Not sure if the text is final; please review the content |
| Contact (`contact_us.php`) | ✅ | Responsive. The form saves messages to the `contact_messages` table; admins read them on the Admin page ("Contact messages") |
| Register (`register.php`) | ✅ | Password rules (checked in PHP + live checklist), "Passwords match" message, eye icon, updated professional categories |
| Login (`login.php`) | ✅ | Same design as Register, eye icon, "You have been logged out" message |
| Forgot Password (`forgot_password.php`) | 🔄 | Works and shows the same message for every email. Real email sending is not set up: locally the reset link is shown on screen (`APP_DEBUG`) |
| Reset Password (`reset_password.php`) | ✅ | Same password rules, checklist and eye icon as Register. Links expire after 60 minutes |
| Verification (`verification.php`) | 🔄 | Responsive; the history table scrolls inside its own box. Chain-integrity check needs `securecomm` (8081) running |
| Dashboard (`dashboard.php`) | ✅ | Real numbers from the database (reports, published reports, active members, media organisations) and "Your Activity" (your reports and their status, messages in the last 7 days, your role) |
| Profile (`profile.php`) | ✅ | New layout. Edit name, category and picture; change password with the same rules and eye icons |
| Messaging (`messaging.php`) | 🔄 | Works on phones. A deleted member's conversation stays readable as "Deleted user" but cannot be replied to. Sending works without Java; the "Secure Transfer" status needs both Java services, Kafka and MongoDB |
| Media Centre (`media_center.php`) | 🔄 | Responsive. Publications and search come from the database; not sure whether the partners/updates text is final |
| Reports (`reports.php`) | 🔄 | Responsive. Submit a report with an optional PDF (max 5 MB, stored outside the web root); "My reports" shows each report's review status |
| FAQ (`faq.php`) | ✅ | New. Public. 26 questions in 6 groups, open/close accordion, search box that filters as you type. Answers come from `faq_items()` in `backend/content.php` |
| Settings (`settings.php`) | ✅ | Logged-in only. Change password (same rules), language and notification preferences (saved in `user_settings`), delete my account (keeps other people's conversations). Language is saved but the site is not translated yet; notification emails are not sent yet |
| Admin (`admin.php`) | 🔄 | Standard page banner and section links. Approve/reject reports (approval is recorded on the blockchain through `securecomm`), recent admin activity, and Contact messages (newest first, latest 50). Not sure if paging is needed later for many reports |

> Update this table as pages are completed.

---

## Progress Log

Newest first.

| Date | Update |
|---|---|
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

**Still to do**
- [ ] Translate the site and use the saved language preference
- [ ] Send email notifications using the saved notification preferences
- [ ] Let users edit or withdraw their own reports
- [ ] Set up real email sending for password reset links
- [ ] Let admins mark contact messages as handled or delete them
- [ ] Database: add foreign keys and a clean `schema.sql` (only `schema_updates.sql` and a database export exist now)
- [ ] Public demo link using cloudflared (Cloudflare Tunnel)
- [ ] LLM / AI integration
- [ ] Workshop evaluation with users
- [ ] Complete end-to-end flow: submit report → Kafka → blockchain verification
- [ ] Add automated tests
- [ ] Prepare demo day script and backup recording
- [ ] Write final user guide
- [ ] Test on a real iPhone and iPad (so far tested in Chrome at phone and tablet sizes)

---

## Team

To be completed.

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
14. [Team](#team)

---

## Features

- User registration, login, logout and password reset (strong password rules checked in PHP and shown live)
- Journalist dashboard and profile (edit name, category, picture and password)
- Secure messaging between journalists and media teams, with one image or PDF per message
- Messages with files or personal details are held until an administrator approves them
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
│   ├── backend/                   # PHP backend (db.php, blockchain/messaging clients, SQL)
│   ├── assets/                    # CSS, JavaScript and images
│   └── index.php
├── Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main/
│   ├── Chaincode/                 # Hyperledger Fabric smart contract (smartcontract.go)
│   ├── docker and Shell files/    # Docker and setup scripts
│   ├── securecomm/                # Spring Boot service 1 (+ docker-compose.yml)
│   └── securecomm2/               # Spring Boot service 2
├── database/migrations/           # SQL changes to run on the database (outside the website folder)
├── docker/                        # Web container (Dockerfile, composer.json/.lock for PHPMailer) + MySQL init SQL
├── private/                       # Report documents and message files (outside the web root, not pushed to GitHub)
├── site/                          # Static site assets
├── docker-compose.yml             # Website, MySQL, phpMyAdmin and Mailpit
├── start-demo.ps1                 # One-click start script
├── .env.example                   # Format of the local .env (DISSENT_API_KEY, SMTP settings, ADMIN_EMAIL)
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

- `APP_DEBUG: "1"` shows the password-reset link on the Forgot Password page, so you can test without opening the Mailpit inbox. **Set it to `"0"` whenever other people can open the site** (see [Sharing a Demo Link](#sharing-a-demo-link)).
- `APP_URL: http://localhost:8888` is the address used inside reset links. On a public server, set it to the real address (for example `https://your-site.example`).
- After changing anything in `docker-compose.yml`, run `docker compose up -d` so the `web` container picks it up.

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
   The script starts the Docker containers and opens `securecomm` and `securecomm2` in their own windows with `DISSENT_API_KEY` set. It then waits until everything answers and opens the homepage.

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
All three are safe to run again.

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
docker compose down        # website, MySQL, phpMyAdmin, Mailpit (database is kept; test emails are not)
```
Close the two Spring Boot windows (or press **Ctrl + C** in them). Do **not** add `-v` to `docker compose down` unless you want to wipe the database.

---

## Addresses and Ports

The website, MySQL, phpMyAdmin, Mailpit and DiSSENT's MongoDB are bound to `127.0.0.1`, so they can only be reached from this computer.

| Address | What it is |
|---|---|
| http://localhost:8888/Pages/home.php | **DiSSENT website** (Docker: `dissent-web`) |
| http://localhost:8888/Pages/login.php | Login page |
| http://127.0.0.1:8089 | phpMyAdmin |
| http://127.0.0.1:8025 | Mailpit test inbox (all emails the website sends locally) |
| 127.0.0.1:1025 | Mailpit SMTP (used by the website) |
| 127.0.0.1:3307 | MySQL (Docker: `dissent-mysql`, database `dissent_local`) |
| http://localhost:8081 | SecureComm 1 API (a 401 "Missing or invalid X-API-Key header" means it is running) |
| http://localhost:8082 | SecureComm 2 API (same 401 check) |
| localhost:9092 | Kafka |
| 127.0.0.1:27018 | MongoDB (Docker: `mongo`, DiSSENT's own; databases `securecomm` and `securecomm2`) |
| localhost:7050 / 7051 / 9051 | Fabric orderer / peer0.org1 / peer0.org2 |

Inside Docker the website lives at `/var/www/html` (= `docs/`). Report documents and message files live at `/var/www/private` (= `private/`), outside the web root. They are only sent by PHP pages that check who is asking.

---

## Sharing a Demo Link

A Cloudflare quick tunnel gives a temporary public `https://....trycloudflare.com` link to the website on your laptop.

1. Install it once: `winget install --id Cloudflare.cloudflared`
2. In `docker-compose.yml`, set `APP_DEBUG: "0"`, then run `docker compose up -d web`. **Do this first.** With `"1"`, anyone with the link could see password-reset links on screen and take over an account.
3. Check it: `docker exec dissent-web printenv APP_DEBUG` must print `0`.
4. Start the tunnel and share the link it prints:
   ```powershell
   cloudflared tunnel --url http://localhost:8888
   ```
5. Optional, so links inside emails use the public address: set `APP_URL` in `docker-compose.yml` to the link (no `/` at the end) and run `docker compose up -d web` again.
6. When you finish, press **Ctrl + C**, set `APP_DEBUG: "1"` and `APP_URL: http://localhost:8888` again, and run `docker compose up -d web`.

- The link only works while that terminal is open and the laptop is on, awake and online. Each new tunnel gets a new link.
- Visitors reach the website only, not phpMyAdmin, MySQL or Mailpit. Emails still go to Mailpit unless real SMTP is set, so visitors do not receive them.
- Everyone with an account can see other members' names in Messaging. Ask visitors to use test details only.

---

## Custom Domain dissent.ie (planned)

The domain **dissent.ie** has been bought. The Cloudflare setup is waiting for the nameserver change, so the domain does not open the website yet. Until then, use the demo link above.

Still to do: finish the nameserver change, connect the domain to a permanent tunnel or host, set `APP_DEBUG: "0"` and `APP_URL: https://dissent.ie`, and add real SMTP settings.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `DISSENT_API_KEY is not set` when starting Java | Run the `$env:DISSENT_API_KEY = ...` line (Option B, Step 3) in that terminal first, or use `start-demo.ps1` |
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
| Upload rejected | Report documents must be one PDF of up to 5 MB. PHP itself refuses any file over 6 MB (`upload_max_filesize`) |
| Welcome or password reset email never arrives (local) | Open the Mailpit inbox at http://127.0.0.1:8025. If it is empty, check `docker compose ps` shows `dissent-mailpit` running and look for "email could not be sent" in `docker logs dissent-web`. The Forgot Password page also shows the link on screen while `APP_DEBUG: "1"` is set |
| "PHPMailer is not installed" in the logs | The web container is old. Rebuild it with `docker compose up -d --build` |
| "Your password needs …" when registering or changing a password | Passwords need at least 8 characters, an uppercase letter, a lowercase letter, a number and a special character. The checklist under the box ticks off each rule |
| Page or menu looks old after an update | Hard refresh with **Ctrl + F5** so the browser loads the new CSS |
| `Failed to connect to localhost:4317` | Should no longer appear: OpenTelemetry is disabled at startup. If it does, you are running an old build. Restart with `.\mvnw spring-boot:run` |
| Path errors after unzipping | Windows path limit (260 characters). Keep the project in a short folder path |

---

## Page Progress

✅ Done · 🔄 In progress · ⏳ To do

Every page uses the shared header, so all pages are **responsive** and have the **hamburger menu** on screens up to 1024px wide. Checked on 25/11/2026 in Chrome at 360, 390, 768, 820, 1024, 1280, 1440 and 1600px as visitor, member and admin. The Java services were off during that check. The notes below list what is special about each page.

| Page | Status | Notes |
|---|---|---|
| Header and menu (`includes/header.php`) | ✅ | Hamburger sidebar up to 1024px, two-row menu 1025–1599px, one row from 1600px. FAQ in the menu and footer; Settings next to your name when logged in. "Get Started" only shows when logged out |
| Home (`home.php`) | ✅ | Responsive. "Get Started" when logged out, "Go to Dashboard" when logged in |
| About (`about.php`) | ✅ | Responsive. The organisations listed (MiCT, ECHO) still need confirming before launch |
| Contact (`contact_us.php`) | ✅ | Responsive. The form saves messages to the `contact_messages` table; admins read them on the Admin page ("Contact messages"). The sender gets an automatic reply (at most one per 10 minutes) and `ADMIN_EMAIL` gets a notification |
| Register (`register.php`) | ✅ | Password rules (checked in PHP + live checklist), "Passwords match" message, eye icon, updated professional categories. Sends a "Welcome to DiSSENT" email. A taken email shows "This email is already registered" with links to Log in and Forgot password |
| Login (`login.php`) | ✅ | Same design as Register, eye icon, "You have been logged out" message |
| Forgot Password (`forgot_password.php`) | ✅ | Emails a reset link valid for 60 minutes (locally to Mailpit) and shows the same message for every email. The link is also shown on screen when `APP_DEBUG=1`. Real email needs SMTP settings in `.env` |
| Reset Password (`reset_password.php`) | ✅ | Same password rules, checklist and eye icon as Register. Links expire after 60 minutes. Sends a "password was changed" email |
| Verification (`verification.php`) | ✅ | Responsive. Search, numbers and history work from the database; only the table scrolls sideways. The live service record and Chain Integrity need `securecomm` (8081) and Fabric, otherwise it shows "Unavailable" |
| Dashboard (`dashboard.php`) | ✅ | Real numbers from the database (reports, published reports, active members, media organisations) and "Your Activity" (your reports and their status, messages in the last 7 days, your role). Quick Actions include My Profile and Settings |
| Profile (`profile.php`) | ✅ | New layout. Edit name, category and picture; change password with the same rules and eye icons. Sends a "password was changed" email. The email address is read-only |
| Messaging (`messaging.php`) | 🔄 | Responsive. One JPG, PNG, WebP or PDF per message (max 5 MB; location data removed from images, not from PDFs). Messages with files or personal details wait for an admin. Missing: a fresh check that messages reach "Secure Transfer: Verified" with both Java services, Kafka and MongoDB running (tested with a stand-in for securecomm). Message text is stored unencrypted in MySQL, and files unencrypted on disk |
| Media Centre (`media_center.php`) | ✅ | Responsive. Publications, search and updates come from the database. The organisations listed still need confirming before launch |
| Reports (`reports.php`) | ✅ | Responsive. Submit a report with an optional PDF (max 5 MB, stored outside the web root); "My reports" shows each review status. Editing or withdrawing a report is in Future Work. The PDF upload was not re-tested on 25/11/2026 |
| FAQ (`faq.php`) | ✅ | Public. 29 questions in 6 groups, accordion, search box that filters as you type. Answers come from `faq_items()` in `backend/content.php` |
| Settings (`settings.php`) | ✅ | Logged-in only. Change password, language and notification preferences (saved in `user_settings`), delete my account (keeps the text of other people's conversations, deletes your message files). Sends "password was changed" and "account deleted" emails. The preferences are saved but not used yet (see Future Work) |
| Admin (`admin.php`) | 🔄 | Responsive. Report review, Messages waiting for review (approve or reject, with an audit log), activity and Contact messages. Members see a styled "You don't have permission" page. Missing: a fresh check of approving a report with Java and Fabric running, and paging for long report lists |

> Update this table as pages are completed.

---

## Progress Log

Newest first.

| Date | Update |
|---|---|
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

**Still to do**
- [ ] Translate the site and use the saved language preference
- [ ] Send email notifications using the saved notification preferences
- [ ] Let users edit or withdraw their own reports
- [ ] Put real SMTP settings in `.env` on the public server (emails currently go to the local Mailpit inbox)
- [ ] Let admins mark contact messages as handled or delete them
- [ ] Database: add the missing foreign keys and add a clean `schema.sql` (only `schema_updates.sql`, migrations and a database export exist now)
- [ ] Connect dissent.ie (waiting for the nameserver change) to a permanent tunnel or host
- [ ] Replace the rule-based `compliance_check()` with the RAG + LLM (ReAct) check that reads the laws of both countries
- [ ] LLM / AI integration (help assistant using the FAQ)
- [ ] Encrypt stored messages and files, and remove hidden details from PDFs
- [ ] Log out other devices when a password changes
- [ ] Second admin account (an admin cannot review their own messages)
- [ ] Write up the evaluation from the workshop on 25/11/2026
- [ ] Re-test the full flow with Java, Kafka and Fabric running: approve report → blockchain, and message → "Secure Transfer: Verified"
- [ ] Confirm the organisations shown on About, Home and Media Centre
- [ ] Add automated tests
- [ ] Prepare demo day script and backup recording
- [ ] Write final user guide
- [ ] Test on a real iPhone and iPad (so far tested in Chrome at phone and tablet sizes)

---

## Team

To be completed.

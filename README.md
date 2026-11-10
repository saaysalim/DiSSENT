# DiSSENT — Cross-Border Journalism Platform

**Final Year Project 2026–27 · University of Limerick**

DiSSENT is a shared workspace for journalists, including refugee and displaced reporters, to submit stories, communicate with media teams and follow editorial review across borders.

The platform combines a PHP + Bootstrap website with a Java (Spring Boot) backend. Messages travel between systems through **Apache Kafka**, and records are verified on a **Hyperledger Fabric** blockchain.

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

- User registration, login, password reset and account verification
- Journalist dashboard and profile
- Secure messaging between journalists and media teams
- Report submission and editorial review
- Media Centre for published content
- Admin panel
- Blockchain-backed verification of reports (Hyperledger Fabric)
- Event messaging between the two backend systems (Apache Kafka)
- Responsive design for desktop, tablet and mobile

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | PHP, HTML, CSS, JavaScript, Bootstrap |
| Backend | Java, Spring Boot (Maven) — two services: `securecomm` and `securecomm2` |
| Messaging | Apache Kafka |
| Blockchain | Hyperledger Fabric (smart contract written in Go) |
| Database | MySQL |
| Containers | Docker, Docker Compose |
| Monitoring | OpenTelemetry (optional) |

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

After editing a PHP or CSS file: save (**Ctrl + S**) and refresh the browser (**F5**). `docs/` is mounted into the container, so no restart is needed. After changing `docker/web/Dockerfile`, run `docker compose up -d --build`.

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
| PHP errors not shown on the page | Expected (`display_errors = Off`). See them with `docker logs dissent-web` |
| Upload rejected | Files are limited to 6 MB (`upload_max_filesize`) |
| `Failed to connect to localhost:4317` | Should no longer appear: OpenTelemetry is disabled at startup. If it does, you are running an old build. Restart with `.\mvnw spring-boot:run` |
| Path errors after unzipping | Windows path limit (260 characters). Keep the project in a short folder path |

---

## Page Progress

✅ Done · 🔄 In progress · ⏳ To do

| Page | Status | Notes |
|---|---|---|
| Home (`home.php`) | ✅ | Responsive |
| About (`about.php`) | 🔄 | |
| Contact (`contact_us.php`) | 🔄 | |
| Register (`register.php`) | 🔄 | |
| Login (`login.php`) | 🔄 | |
| Forgot Password (`forgot_password.php`) | ⏳ | |
| Reset Password (`reset_password.php`) | ⏳ | |
| Verification (`verification.php`) | 🔄 | |
| Dashboard (`dashboard.php`) | 🔄 | |
| Profile (`profile.php`) | 🔄 | |
| Messaging (`messaging.php`) | 🔄 | |
| Media Centre (`media_center.php`) | 🔄 | |
| Reports (`reports.php`) | 🔄 | |
| Admin (`admin.php`) | 🔄 | |

> Update this table as pages are completed.

---

## Progress Log

| Date | Update |
|---|---|
| 09/11/2026 | Updated login, register and other pages with new CSS and JavaScript. Cleaned up project folder structure. Fixed Maven Wrapper so Spring Boot runs with `.\mvnw spring-boot:run`. Set up local PHP server for testing. Removed private keys and uploaded files from the repository. |
| 10/11/2026 | Moved the website into Docker (`docs/` is the web root, `private/` is outside it, ports bound to 127.0.0.1). Hardened PHP settings. Fixed CSS 404s. PHP and both Spring Boot services now share one `DISSENT_API_KEY` from `.env`. Added `start-demo.ps1`. |
| | |

> Add a new row each time you push important changes.

---

## Future Work

- [ ] Finish and test all remaining pages
- [ ] Connect login and registration fully to the Spring Boot backend
- [ ] Complete end-to-end flow: submit report → Kafka → blockchain verification
- [ ] Add automated tests
- [ ] Make the site work fully offline for the demo (local Bootstrap and fonts)
- [ ] Prepare demo day script and backup recording
- [ ] Write final user guide
- [ ] Integrate with AI

---



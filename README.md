DiSEENT- Cross-Border Journalist Platform
Final Year Project 2026-27 UL

DiSSENT is a shared workspace for journalists, including refugee and displaced reporters, to submit stories, communicate with media teams and follow editorial review across borders.
The platform combines a PHP + Bootstrap website with a Java (Spring Boot) backend. Messages travel between systems through Apache Kafka, and records are verified on a Hyperledger Fabric blockchain.

Table of Contents
Features
Tech Stack
System Architecture
Project Structure
Prerequisites
How to Run the Project
Addresses and Ports
Troubleshooting
Page Progress
Progress Log
Future Work
Team

Features
User registration, login, password reset and account verification
Journalist dashboard and profile
Secure messaging between journalists and media teams
Report submission and editorial review
Media Centre for published content
Admin panel
Blockchain-backed verification of reports (Hyperledger Fabric)
Event messaging between the two backend systems (Apache Kafka)
Responsive design for desktop, tablet and mobile


Tech Stack
Layer	Technology
Frontend	PHP, HTML, CSS, JavaScript, Bootstrap
Backend	Java, Spring Boot (Maven) — two services: securecomm and securecomm2
Messaging	Apache Kafka
Blockchain	Hyperledger Fabric (smart contract written in Go)
Database	MySQL
Containers	Docker, Docker Compose
Monitoring	OpenTelemetry (optional)

System Architecture
  Browser
     │
     ▼
  PHP Website (docs/)  ──────►  MySQL (Docker)
     │
     ▼
  SecureComm 1 (Spring Boot)  ──► Kafka ──►  SecureComm 2 (Spring Boot)
     │                                            │
     └──────────────►  Hyperledger Fabric  ◄──────┘
                       (smartcontract.go)


                       Project Structure
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
├── private/                       # Uploaded report documents (not pushed to GitHub)
├── site/                          # Static site assets
└── README.md
Prerequisites

Install these before running the project:

Docker Desktop: runs MySQL, Kafka, nginx and the other containers
Java 17 or newer: check with java -version
XAMPP: only its PHP is used (C:\xampp\php\php.exe). You do not need to start XAMPP's Apache or MySQL.
Git and VS Code (recommended)

Maven does not need to be installed. The project uses the Maven Wrapper (mvnw).


Prerequisites

Install these before running the project:

Docker Desktop: runs MySQL, Kafka, nginx and the other containers
Java 17 or newer: check with java -version
XAMPP: only its PHP is used (C:\xampp\php\php.exe). You do not need to start XAMPP's Apache or MySQL.
Git and VS Code (recommended)

Maven does not need to be installed. The project uses the Maven Wrapper (mvnw).

How to Run the Project
Option A: One-click start (recommended)
Open Docker Desktop and wait until it is running.
Open a terminal in the DiSSENT-clean folder and run:
powershell
   powershell -ExecutionPolicy Bypass -File .\start-demo.ps1
Chrome opens the homepage automatically.
Option B: Step by step

Step 1: Start Docker containers

powershell
cd ".\Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric-main\securecomm"
docker compose up -d
docker ps

Step 2: Start Spring Boot (SecureComm 1) In VS Code, right-click the securecomm folder → Open in Integrated Terminal, then:

powershell
.\mvnw spring-boot:run

Wait for: Started ...Application in X seconds

Step 3: Start Spring Boot (SecureComm 2) Right-click securecomm2 → Open in Integrated Terminal, then:

powershell
.\mvnw spring-boot:run

Step 4: Start the PHP website Open a new terminal in DiSSENT-clean:

powershell
C:\xampp\php\php.exe -S 127.0.0.1:8888

Leave this terminal open. Closing it stops the website.

Step 5: Open the website

http://127.0.0.1:8888/docs/Pages/home.php

After editing a PHP file: save (Ctrl + S) and refresh the browser (F5). No restart needed.

Addresses and Ports
Address	What it is
http://127.0.0.1:8888/docs/Pages/home.php	DiSSENT website
http://localhost:8080	SecureComm Spring Boot API (a 401 "Authentication required" message means it is running)
localhost:3306	MySQL (inside Docker)
localhost:80	nginx (inside Docker)
localhost:4317	OpenTelemetry collector (optional monitoring)
Troubleshooting
Problem	Fix
.\gradlew is not recognized	The project uses Maven, not Gradle. Use .\mvnw spring-boot:run
Cannot start maven from wrapper	.mvn\wrapper\maven-wrapper.properties is missing. Recreate it (see the Maven Wrapper docs)
php is not recognized	Use the full path: C:\xampp\php\php.exe
Browser shows 502 Bad Gateway	You opened localhost without a port. Use 127.0.0.1:8888
ERR_INVALID_RESPONSE on port 8000	Port 8000 is used by another program. Use port 8888
XAMPP says port 80 / 3306 in use by Docker	Normal. MySQL runs in Docker. Do not start XAMPP's Apache or MySQL
Failed to connect to localhost:4317	Monitoring collector is not running. The app still works. Start it with docker compose up -d or add management.tracing.enabled=false to application.properties
Path errors after unzipping	Windows path limit (260 characters). Keep the project in a short folder path
Page Progress

Most of the pages are Resposive for all devices - Done ·  In progress for the update pages ·  To do integrate with AI and descusse with
my Superviser

Page	Status	Notes
Home (home.php)		Responsive
About (about.php)	
Contact (contact_us.php)	
Register (register.php)		
Login (login.php)	
Forgot Password (forgot_password.php)		
Reset Password (reset_password.php)	
Verification (verification.php)	
Dashboard (dashboard.php)	
Profile (profile.php)	
Messaging (messaging.php)	
Media Centre (media_center.php)	
Reports (reports.php)	
Admin (admin.php)	

Update this table as pages are completed.

Progress Log
Date	Update
09/11/2026	Updated login, register and other pages with new CSS and JavaScript. Cleaned up project folder structure. Fixed Maven Wrapper so Spring Boot runs with .\mvnw spring-boot:run. Set up local PHP server for testing. Removed private keys and uploaded files from the repository.
	

Add a new row each time you push important changes.

Future Work
 Itegrate with AI
 Finish and test all remaining pages
 Connect login and registration fully to the Spring Boot backend
 Complete end-to-end flow: submit report → Kafka → blockchain verification
 Add automated tests
 Make the site work fully offline for the demo (local Bootstrap and fonts)
 Prepare demo day script and backup recording
 Write final user guide
 

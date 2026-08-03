# Cross-Border-Systems-using-Kafka-Broker-Architecture-and-Hyper-ledger-Fabric
A secure cross-border messaging prototype using Spring Boot, Apache Kafka, and Hyperledger Fabric. Supports AES encryption, ECDSA signatures, and blockchain-based public key verification. Dockerized for easy deployment. Designed for exile journalism in hostile environments.

 # Secure Cross-Border Messaging System using Spring Boot, Apache Kafka, and Hyperledger Fabric

This project delivers a secure and decentralized messaging solution for cross-border communication scenarios such as exile journalism. It integrates:

- **Spring Boot** for RESTful message orchestration  
- **Apache Kafka** for event streaming and decoupling  
- **AES + ECDSA** for message-level confidentiality and integrity  
- **Hyperledger Fabric** as a decentralized public key registry  
- **Docker** for containerization and reproducibility  

---

## Repository Structure

├── springboot-app-system1/
│ └── docker-compose.yml (Kafka + App)
├── springboot-app-system2/
│ └── docker-compose.yml
├── hyperledger-fabric/
│ └── fabric-samples/test-network/
├── deploy_chaincode.sh # Chaincode redeployment script
├── docker-compose.grafana.yml (optional monitoring stack)
├── README.md
-----------------------------------------
Prerequisites (Windows-based Setup)

Enable WSL2 and Virtual Machine Platform:

```powershell
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
wsl --set-default-version 2
2. Install:
Ubuntu 20.04 LTS from Microsoft Store

Windows Terminal

Docker Desktop: Download

WSL Kernel Update: wsl_update_x64.msi

3. Enable Docker for Ubuntu
Open Docker Desktop > Settings > Resources > WSL Integration

Enable for Ubuntu-20.04

Apply & Restart

Ubuntu Environment Setup
bash
Copy
Edit
sudo apt update && sudo apt upgrade
sudo apt install curl git wget build-essential

# Install Go
wget https://golang.org/dl/go1.16.3.linux-amd64.tar.gz
sudo tar -C /usr/local -xzf go1.16.3.linux-amd64.tar.gz
echo 'export PATH=$PATH:/usr/local/go/bin' >> ~/.bashrc
source ~/.bashrc
go version

# Optional: install cURL and Git
sudo apt install curl git
curl --version
git --version

Hyperledger Fabric Setup
bash
Copy
Edit
mkdir -p $HOME/go/src/github.com/
cd $HOME/go/src/github.com/

# Download Fabric binaries and samples (v2.3.2)
curl -sSL https://bit.ly/2ysbOFE | bash -s -- 2.3.2

# Navigate to test network
cd fabric-samples/test-network

# Clean any previous artifacts
./network.sh down

# Start the Fabric test network
./network.sh up

# Confirm containers
docker ps -a

Chaincode Redeployment (Optional)
Check Installed Packages
peer lifecycle chaincode queryinstalled

Deploy Chaincode with Script
./deploy_chaincode.sh basic_2.0:<package_id>
Example:
./deploy_chaincode.sh basic_2.0:6a6f743366675481339c7ddda5f281a441c9ad8c13e32a9a5c07b892e44de105

Running Spring Boot Messaging Services
securecomm and securecomm2 share ONE Kafka/Zookeeper/Mongo stack (both apps'
application.properties already point at localhost:9092 and localhost:27017,
just with separate Mongo databases) — only securecomm/docker-compose.yml needs
to be started, not a second one per system.

cd securecomm
mvn clean install

cd ../securecomm2
mvn clean install

Start Kafka, Zookeeper and Mongo with Docker (once, from securecomm/)
cd securecomm
docker-compose up -d

Run Spring Boot Apps
cd securecomm
java -jar target/securecomm-0.0.1-SNAPSHOT.jar

cd ../securecomm2
java -jar target/securecomm2-0.0.1-SNAPSHOT.jar

securecomm (System1, Org1):
- REST API for the report verification hash chain (Fabric-backed); every write also
  publishes a verification-events Kafka message
- On send: encrypts with AES, signs with ECDSA, publishes to Kafka

securecomm2 (System2, Org2):
- Consumes verification-events and mirrors each one into its own store — the
  cross-border partner's independent copy of what System1 verified (GET /api/verification/mirror)
- Consumes secure-messages, validates the sender's public key from Hyperledger Fabric,
  decrypts the payload, and exposes a status endpoint for polling delivery/verification

Monitoring (Grafana + Prometheus)
docker-compose up -d
Ensure ports used in docker-compose.grafana.yml don't conflict with your other services.


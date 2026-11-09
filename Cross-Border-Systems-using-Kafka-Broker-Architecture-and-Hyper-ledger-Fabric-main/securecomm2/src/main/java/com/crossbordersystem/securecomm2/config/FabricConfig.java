package com.crossbordersystem.securecomm2.config;

import com.crossbordersystem.securecomm2.util.PemUtils;
import org.hyperledger.fabric.gateway.Contract;
import org.hyperledger.fabric.gateway.Gateway;
import org.hyperledger.fabric.gateway.Identities;
import org.hyperledger.fabric.gateway.Identity;
import org.hyperledger.fabric.gateway.Network;
import org.hyperledger.fabric.gateway.Wallet;
import org.hyperledger.fabric.gateway.Wallets;
import org.springframework.boot.context.properties.EnableConfigurationProperties;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.core.io.ClassPathResource;

import java.io.InputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.security.PrivateKey;
import java.security.cert.X509Certificate;

/**
 * Wires the Fabric Gateway Java SDK for this system's identity (Org2). The connection
 * profile's peer/CA addresses must match wherever the local test-network is actually
 * running — see the deployment runbook.
 */
@Configuration
@EnableConfigurationProperties(FabricProperties.class)
public class FabricConfig {

    @Bean(destroyMethod = "close")
    public Gateway fabricGateway(FabricProperties props) throws Exception {
        Wallet wallet = Wallets.newInMemoryWallet();

        try (InputStream certIn = new ClassPathResource(props.getCertPath()).getInputStream();
             InputStream keyIn = new ClassPathResource(props.getKeyPath()).getInputStream()) {

            X509Certificate certificate = PemUtils.readCertificate(certIn);
            PrivateKey privateKey = PemUtils.readPrivateKey(keyIn);
            Identity identity = Identities.newX509Identity(props.getMspId(), certificate, privateKey);
            wallet.put(props.getUserLabel(), identity);
        }

        Path connectionProfilePath = copyResourceToTempFile(props.getConnectionProfile());

        // Discovery mode requires a peer configured with the 'discover' role; this network
        // is a single peer per org, so the static connection profile is sufficient on its own.
        Gateway.Builder builder = Gateway.createBuilder()
                .identity(wallet, props.getUserLabel())
                .networkConfig(connectionProfilePath)
                .discovery(false);

        return builder.connect();
    }

    @Bean
    public Network fabricNetwork(Gateway gateway, FabricProperties props) {
        return gateway.getNetwork(props.getChannel());
    }

    @Bean
    public Contract fabricContract(Network network, FabricProperties props) {
        return network.getContract(props.getChaincode());
    }

    private Path copyResourceToTempFile(String classpathLocation) throws Exception {
        ClassPathResource resource = new ClassPathResource(classpathLocation);
        Path tempFile = Files.createTempFile("fabric-ccp-", ".json");
        tempFile.toFile().deleteOnExit();
        try (InputStream in = resource.getInputStream()) {
            Files.copy(in, tempFile, java.nio.file.StandardCopyOption.REPLACE_EXISTING);
        }
        return tempFile;
    }
}

package com.crossbordersystem.securecomm.service;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.stereotype.Component;

/** Registers this system's own signing public key on the Fabric ledger at startup. */
@Component
public class PublicKeyRegistration implements ApplicationRunner {

    private static final Logger log = LoggerFactory.getLogger(PublicKeyRegistration.class);

    private final CryptoService cryptoService;
    private final FabricLedgerService ledgerService;
    private final String userId;
    private final String orgId;

    public PublicKeyRegistration(CryptoService cryptoService, FabricLedgerService ledgerService,
                    @Value("${app.identity.user-id}") String userId,
                    @Value("${app.identity.org-id}") String orgId) {
        this.cryptoService = cryptoService;
        this.ledgerService = ledgerService;
        this.userId = userId;
        this.orgId = orgId;
    }

    @Override
    public void run(ApplicationArguments args) {
        try {
            ledgerService.registerPublicKey(userId, orgId, cryptoService.getPublicKeyPem());
            log.info("Registered public key for {} ({}) on the Fabric ledger", userId, orgId);
        } catch (Exception e) {
            log.error("Failed to register public key on startup — is the Fabric network reachable?", e);
        }
    }
}

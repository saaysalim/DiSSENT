package com.crossbordersystem.securecomm2.service;

import com.crossbordersystem.securecomm2.model.PublicKeyRecord;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.hyperledger.fabric.gateway.Contract;
import org.springframework.stereotype.Service;

/** securecomm2 only needs read access to the public-key registry, to verify incoming messages. */
@Service
public class FabricLedgerService {

    private final Contract contract;
    private final ObjectMapper objectMapper;

    public FabricLedgerService(Contract contract, ObjectMapper objectMapper) {
        this.contract = contract;
        this.objectMapper = objectMapper;
    }

    /** Returns the registered public key PEM for a user, e.g. "System1". */
    public String getPublicKeyPem(String userId) throws Exception {
        byte[] result = contract.evaluateTransaction("GetPublicKey", userId);
        PublicKeyRecord record = objectMapper.readValue(result, PublicKeyRecord.class);
        return record.getPublicKeyPem();
    }
}

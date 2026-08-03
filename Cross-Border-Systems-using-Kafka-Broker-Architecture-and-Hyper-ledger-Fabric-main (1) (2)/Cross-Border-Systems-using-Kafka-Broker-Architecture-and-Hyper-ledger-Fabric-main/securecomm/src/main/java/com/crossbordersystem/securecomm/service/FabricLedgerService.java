package com.crossbordersystem.securecomm.service;

import com.crossbordersystem.securecomm.model.VerificationRecord;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.hyperledger.fabric.gateway.Contract;
import org.springframework.stereotype.Service;

import java.nio.charset.StandardCharsets;
import java.util.List;

/** Thin wrapper translating REST calls into chaincode submit/evaluate transactions. */
@Service
public class FabricLedgerService {

    private final Contract contract;
    private final ObjectMapper objectMapper;

    public FabricLedgerService(Contract contract, ObjectMapper objectMapper) {
        this.contract = contract;
        this.objectMapper = objectMapper;
    }

    public String addVerification(String reportId, String title, String dataHash) throws Exception {
        byte[] result = contract.submitTransaction("AddVerification", reportId, title, dataHash);
        return new String(result, StandardCharsets.UTF_8);
    }

    public VerificationRecord getVerification(String reportId) throws Exception {
        byte[] result = contract.evaluateTransaction("GetVerification", reportId);
        return objectMapper.readValue(result, VerificationRecord.class);
    }

    public List<VerificationRecord> getHistory() throws Exception {
        byte[] result = contract.evaluateTransaction("GetAllVerifications");
        return objectMapper.readValue(result, objectMapper.getTypeFactory()
                .constructCollectionType(List.class, VerificationRecord.class));
    }

    public boolean verifyChainIntegrity() throws Exception {
        byte[] result = contract.evaluateTransaction("VerifyChainIntegrity");
        return Boolean.parseBoolean(new String(result, StandardCharsets.UTF_8).trim());
    }

    public void registerPublicKey(String userId, String orgId, String publicKeyPem) throws Exception {
        contract.submitTransaction("RegisterPublicKey", userId, orgId, publicKeyPem);
    }

    public boolean verificationExists(String reportId) {
        try {
            getVerification(reportId);
            return true;
        } catch (Exception e) {
            return false;
        }
    }
}

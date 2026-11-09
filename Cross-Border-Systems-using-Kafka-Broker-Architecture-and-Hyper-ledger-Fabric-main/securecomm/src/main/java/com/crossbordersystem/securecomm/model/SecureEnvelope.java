package com.crossbordersystem.securecomm.model;

/**
 * The payload published to the {@code secure-messages} Kafka topic.
 * securecomm2 must deserialize this into an identical shape to verify + decrypt it.
 */
public class SecureEnvelope {

    private String envelopeId;
    private String senderId;
    private String receiverId;
    // The ledger identity whose key must be used to verify the signature below — always
    // System1's own identity, distinct from senderId (an app-level user id/username that
    // has no key registered on the ledger and must never be used for verification).
    private String signerId;
    private String ciphertextBase64;
    private String ivBase64;
    private String signatureBase64;
    private String sentAt;

    public SecureEnvelope() {
    }

    public SecureEnvelope(String envelopeId, String senderId, String receiverId, String signerId,
        String ciphertextBase64, String ivBase64, String signatureBase64, String sentAt) {
        this.envelopeId = envelopeId;
        this.senderId = senderId;
        this.receiverId = receiverId;
        this.signerId = signerId;
        this.ciphertextBase64 = ciphertextBase64;
        this.ivBase64 = ivBase64;
        this.signatureBase64 = signatureBase64;
        this.sentAt = sentAt;
    }

    public String getSignerId() {
        return signerId;
    }

    public void setSignerId(String signerId) {
        this.signerId = signerId;
    }

    public String getEnvelopeId() {
        return envelopeId;
    }

    public void setEnvelopeId(String envelopeId) {
        this.envelopeId = envelopeId;
    }

    public String getSenderId() {
        return senderId;
    }

    public void setSenderId(String senderId) {
        this.senderId = senderId;
    }

    public String getReceiverId() {
        return receiverId;
    }

    public void setReceiverId(String receiverId) {
        this.receiverId = receiverId;
    }

    public String getCiphertextBase64() {
        return ciphertextBase64;
    }

    public void setCiphertextBase64(String ciphertextBase64) {
        this.ciphertextBase64 = ciphertextBase64;
    }

    public String getIvBase64() {
        return ivBase64;
    }

    public void setIvBase64(String ivBase64) {
        this.ivBase64 = ivBase64;
    }

    public String getSignatureBase64() {
        return signatureBase64;
    }

    public void setSignatureBase64(String signatureBase64) {
        this.signatureBase64 = signatureBase64;
    }

    public String getSentAt() {
        return sentAt;
    }

    public void setSentAt(String sentAt) {
        this.sentAt = sentAt;
    }
}

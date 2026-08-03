package com.crossbordersystem.securecomm2.model;

/**
 * Must stay field-for-field identical to securecomm's SecureEnvelope — this is the
 * shape published to the {@code secure-messages} Kafka topic and consumed here.
 */
public class SecureEnvelope {

    private String envelopeId;
    private String senderId;
    private String receiverId;
    // The ledger identity whose key must be used to verify the signature — always System1's
    // own identity, distinct from senderId (an app-level user id with no key on the ledger).
    private String signerId;
    private String ciphertextBase64;
    private String ivBase64;
    private String signatureBase64;
    private String sentAt;

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

    public String getSignerId() {
        return signerId;
    }

    public void setSignerId(String signerId) {
        this.signerId = signerId;
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

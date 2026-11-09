package com.crossbordersystem.securecomm2.model;

import com.fasterxml.jackson.annotation.JsonProperty;

/** Mirrors the chaincode's PublicKeyRecord JSON shape. */
public class PublicKeyRecord {

    @JsonProperty("UserID")
    private String userId;

    @JsonProperty("OrgID")
    private String orgId;

    @JsonProperty("PublicKeyPEM")
    private String publicKeyPem;

    @JsonProperty("RegisteredAt")
    private String registeredAt;

    public String getUserId() {
        return userId;
    }

    public void setUserId(String userId) {
        this.userId = userId;
    }

    public String getOrgId() {
        return orgId;
    }

    public void setOrgId(String orgId) {
        this.orgId = orgId;
    }

    public String getPublicKeyPem() {
        return publicKeyPem;
    }

    public void setPublicKeyPem(String publicKeyPem) {
        this.publicKeyPem = publicKeyPem;
    }

    public String getRegisteredAt() {
        return registeredAt;
    }

    public void setRegisteredAt(String registeredAt) {
        this.registeredAt = registeredAt;
    }
}

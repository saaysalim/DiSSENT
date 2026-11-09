package com.crossbordersystem.securecomm.config;

import org.springframework.boot.context.properties.ConfigurationProperties;

@ConfigurationProperties(prefix = "fabric")
public class FabricProperties {

    /** Classpath location of the Fabric connection profile (CCP) for this org. */
    private String connectionProfile = "connection-org1.json";

    /** Classpath location of this identity's signed certificate. */
    private String certPath = "cert.pem";

    /** Classpath location of this identity's private key. */
    private String keyPath = "key.pem";

    private String mspId = "Org1MSP";
    private String userLabel = "appUser1";
    private String channel = "mychannel";
    private String chaincode = "verification";

    public String getConnectionProfile() {
        return connectionProfile;
    }

    public void setConnectionProfile(String connectionProfile) {
        this.connectionProfile = connectionProfile;
    }

    public String getCertPath() {
        return certPath;
    }

    public void setCertPath(String certPath) {
        this.certPath = certPath;
    }

    public String getKeyPath() {
        return keyPath;
    }

    public void setKeyPath(String keyPath) {
        this.keyPath = keyPath;
    }

    public String getMspId() {
        return mspId;
    }

    public void setMspId(String mspId) {
        this.mspId = mspId;
    }

    public String getUserLabel() {
        return userLabel;
    }

    public void setUserLabel(String userLabel) {
        this.userLabel = userLabel;
    }

    public String getChannel() {
        return channel;
    }

    public void setChannel(String channel) {
        this.channel = channel;
    }

    public String getChaincode() {
        return chaincode;
    }

    public void setChaincode(String chaincode) {
        this.chaincode = chaincode;
    }
}

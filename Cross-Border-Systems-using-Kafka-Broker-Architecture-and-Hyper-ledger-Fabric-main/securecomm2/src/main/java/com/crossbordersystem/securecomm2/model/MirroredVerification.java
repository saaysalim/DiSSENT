package com.crossbordersystem.securecomm2.model;

import org.springframework.data.annotation.Id;
import org.springframework.data.mongodb.core.mapping.Document;

/**
 * System2's own copy of a report verification event, consumed from Kafka rather than
 * queried from Fabric directly — the cross-border partner's independent audit trail
 * of what System1 verified, decoupled from Fabric availability.
 */
@Document(collection = "mirrored_verifications")
public class MirroredVerification {

    @Id
    private String reportId;
    private String title;
    private String dataHash;
    private String prevHash;
    private String blockHash;
    private int blockIndex;
    private String verifiedTimestamp;
    private String mirroredAt;

    public String getReportId() {
        return reportId;
    }

    public void setReportId(String reportId) {
        this.reportId = reportId;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getDataHash() {
        return dataHash;
    }

    public void setDataHash(String dataHash) {
        this.dataHash = dataHash;
    }

    public String getPrevHash() {
        return prevHash;
    }

    public void setPrevHash(String prevHash) {
        this.prevHash = prevHash;
    }

    public String getBlockHash() {
        return blockHash;
    }

    public void setBlockHash(String blockHash) {
        this.blockHash = blockHash;
    }

    public int getBlockIndex() {
        return blockIndex;
    }

    public void setBlockIndex(int blockIndex) {
        this.blockIndex = blockIndex;
    }

    public String getVerifiedTimestamp() {
        return verifiedTimestamp;
    }

    public void setVerifiedTimestamp(String verifiedTimestamp) {
        this.verifiedTimestamp = verifiedTimestamp;
    }

    public String getMirroredAt() {
        return mirroredAt;
    }

    public void setMirroredAt(String mirroredAt) {
        this.mirroredAt = mirroredAt;
    }
}

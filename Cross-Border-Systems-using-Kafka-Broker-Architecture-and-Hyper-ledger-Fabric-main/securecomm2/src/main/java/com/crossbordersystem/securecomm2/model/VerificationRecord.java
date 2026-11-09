package com.crossbordersystem.securecomm2.model;

import com.fasterxml.jackson.annotation.JsonProperty;

/** Mirrors the chaincode's VerificationRecord JSON shape, and securecomm's own copy of it. */
public class VerificationRecord {

    @JsonProperty("ReportID")
    private String reportId;

    @JsonProperty("Title")
    private String title;

    @JsonProperty("DataHash")
    private String dataHash;

    @JsonProperty("PrevHash")
    private String prevHash;

    @JsonProperty("BlockHash")
    private String blockHash;

    @JsonProperty("BlockIndex")
    private int blockIndex;

    @JsonProperty("Timestamp")
    private String timestamp;

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

    public String getTimestamp() {
        return timestamp;
    }

    public void setTimestamp(String timestamp) {
        this.timestamp = timestamp;
    }
}

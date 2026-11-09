package com.crossbordersystem.securecomm.model;

import jakarta.validation.constraints.NotBlank;

public class VerificationRequest {

    @NotBlank
    private String reportId;

    @NotBlank
    private String title;

    @NotBlank
    private String description;

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

    public String getDescription() {
        return description;
    }

    public void setDescription(String description) {
        this.description = description;
    }
}

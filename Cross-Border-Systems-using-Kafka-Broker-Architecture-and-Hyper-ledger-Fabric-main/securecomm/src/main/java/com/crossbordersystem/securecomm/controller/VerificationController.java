package com.crossbordersystem.securecomm.controller;

import com.crossbordersystem.securecomm.model.VerificationRecord;
import com.crossbordersystem.securecomm.model.VerificationRequest;
import com.crossbordersystem.securecomm.service.FabricLedgerService;
import com.crossbordersystem.securecomm.service.VerificationEventPublisher;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.ArrayList;
import java.util.Collections;
import java.util.HexFormat;
import java.util.List;
import java.util.Map;
import java.nio.charset.StandardCharsets;

@RestController
@RequestMapping("/api/verification")
public class VerificationController {

    private final FabricLedgerService ledgerService;
    private final VerificationEventPublisher eventPublisher;

    public VerificationController(FabricLedgerService ledgerService, VerificationEventPublisher eventPublisher) {
        this.ledgerService = ledgerService;
        this.eventPublisher = eventPublisher;
    }

    @PostMapping
    public ResponseEntity<?> addVerification(@Valid @RequestBody VerificationRequest request) {
        try {
            String dataHash = sha256(request.getTitle() + "|" + request.getDescription() + "|" + request.getReportId());
            String blockHash = ledgerService.addVerification(request.getReportId(), request.getTitle(), dataHash);
            eventPublisher.publish(ledgerService.getVerification(request.getReportId()));
            return ResponseEntity.ok(Map.of("blockHash", blockHash));
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.BAD_GATEWAY).body(errorBody(e));
        }
    }

    @GetMapping("/{reportId}")
    public ResponseEntity<?> getVerification(@PathVariable String reportId) {
        try {
            VerificationRecord record = ledgerService.getVerification(reportId);
            return ResponseEntity.ok(record);
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.NOT_FOUND).body(Map.of("error", "No verification record for report " + reportId));
        }
    }

    @GetMapping("/history")
    public ResponseEntity<?> getHistory(@RequestParam(defaultValue = "10") int limit) {
        try {
            List<VerificationRecord> all = ledgerService.getHistory();
            int fromIndex = Math.max(0, all.size() - limit);
            List<VerificationRecord> mostRecentFirst = new ArrayList<>(all.subList(fromIndex, all.size()));
            Collections.reverse(mostRecentFirst);
            return ResponseEntity.ok(mostRecentFirst);
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.BAD_GATEWAY).body(errorBody(e));
        }
    }

    @GetMapping("/integrity")
    public ResponseEntity<?> checkIntegrity() {
        try {
            boolean intact = ledgerService.verifyChainIntegrity();
            return ResponseEntity.ok(Map.of("chainIntact", intact));
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.BAD_GATEWAY).body(errorBody(e));
        }
    }

    private String sha256(String input) throws NoSuchAlgorithmException {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        byte[] hash = digest.digest(input.getBytes(StandardCharsets.UTF_8));
        return HexFormat.of().formatHex(hash);
    }

    /** Map.of() throws NPE on a null value, and some exceptions have a null getMessage(). */
    private Map<String, String> errorBody(Exception e) {
        return Map.of("error", e.getMessage() != null ? e.getMessage() : e.getClass().getSimpleName());
    }
}

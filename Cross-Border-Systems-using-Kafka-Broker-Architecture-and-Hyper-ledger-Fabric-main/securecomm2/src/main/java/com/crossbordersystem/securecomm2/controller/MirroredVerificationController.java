package com.crossbordersystem.securecomm2.controller;

import com.crossbordersystem.securecomm2.model.MirroredVerification;
import com.crossbordersystem.securecomm2.repository.MirroredVerificationRepository;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/verification")
public class MirroredVerificationController {

    private final MirroredVerificationRepository repository;

    public MirroredVerificationController(MirroredVerificationRepository repository) {
        this.repository = repository;
    }

    /** System2's own mirrored copy of every verification event consumed from Kafka. */
    @GetMapping("/mirror")
    public List<MirroredVerification> getAllMirrored() {
        return repository.findAll();
    }

    @GetMapping("/mirror/{reportId}")
    public ResponseEntity<?> getMirrored(@PathVariable String reportId) {
        return repository.findById(reportId)
                .<ResponseEntity<?>>map(ResponseEntity::ok)
                .orElseGet(() -> ResponseEntity.status(404).body(Map.of("error", "No mirrored verification for report " + reportId)));
    }
}

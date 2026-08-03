package com.crossbordersystem.securecomm2.service;

import com.crossbordersystem.securecomm2.model.MirroredVerification;
import com.crossbordersystem.securecomm2.model.VerificationRecord;
import com.crossbordersystem.securecomm2.repository.MirroredVerificationRepository;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.kafka.annotation.KafkaListener;
import org.springframework.stereotype.Service;

import java.time.Instant;

/**
 * Mirrors every report verification System1 writes to the ledger into System2's own
 * store — the cross-border partner keeps an independent copy, not just a Fabric query.
 */
@Service
public class VerificationEventConsumer {

    private static final Logger log = LoggerFactory.getLogger(VerificationEventConsumer.class);

    private final ObjectMapper objectMapper;
    private final MirroredVerificationRepository repository;

    public VerificationEventConsumer(ObjectMapper objectMapper, MirroredVerificationRepository repository) {
        this.objectMapper = objectMapper;
        this.repository = repository;
    }

    @KafkaListener(topics = "${app.kafka.verification-topic}", groupId = "${spring.kafka.consumer.group-id}")
    public void onVerificationEvent(String payload) {
        try {
            VerificationRecord record = objectMapper.readValue(payload, VerificationRecord.class);

            MirroredVerification mirrored = new MirroredVerification();
            mirrored.setReportId(record.getReportId());
            mirrored.setTitle(record.getTitle());
            mirrored.setDataHash(record.getDataHash());
            mirrored.setPrevHash(record.getPrevHash());
            mirrored.setBlockHash(record.getBlockHash());
            mirrored.setBlockIndex(record.getBlockIndex());
            mirrored.setVerifiedTimestamp(record.getTimestamp());
            mirrored.setMirroredAt(Instant.now().toString());

            repository.save(mirrored);
            log.info("Mirrored verification for report {}", record.getReportId());
        } catch (Exception e) {
            log.error("Failed to mirror verification event: {}", payload, e);
        }
    }
}

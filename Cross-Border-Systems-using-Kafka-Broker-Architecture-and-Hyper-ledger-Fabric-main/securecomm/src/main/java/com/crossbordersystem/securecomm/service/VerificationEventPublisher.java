package com.crossbordersystem.securecomm.service;

import com.crossbordersystem.securecomm.model.VerificationRecord;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.kafka.core.KafkaTemplate;
import org.springframework.stereotype.Service;

/**
 * Publishes an event for every ledger write so downstream consumers (a future
 * read-cache, an audit log, securecomm2) can react without polling Fabric directly.
 */
@Service
public class VerificationEventPublisher {

    private final KafkaTemplate<String, String> kafkaTemplate;
    private final ObjectMapper objectMapper;
    private final String topic;

    public VerificationEventPublisher(KafkaTemplate<String, String> kafkaTemplate, ObjectMapper objectMapper,
                            @Value("${app.kafka.verification-topic}") String topic) {
        this.kafkaTemplate = kafkaTemplate;
        this.objectMapper = objectMapper;
        this.topic = topic;
    }

    public void publish(VerificationRecord record) throws Exception {
        String json = objectMapper.writeValueAsString(record);
        kafkaTemplate.send(topic, record.getReportId(), json);
    }
}

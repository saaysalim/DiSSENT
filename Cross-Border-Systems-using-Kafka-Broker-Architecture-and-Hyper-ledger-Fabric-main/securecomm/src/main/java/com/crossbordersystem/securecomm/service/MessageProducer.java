package com.crossbordersystem.securecomm.service;

import com.crossbordersystem.securecomm.model.SecureEnvelope;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.kafka.core.KafkaTemplate;
import org.springframework.stereotype.Service;

@Service
public class MessageProducer {

    private final KafkaTemplate<String, String> kafkaTemplate;
    private final ObjectMapper objectMapper;
    private final String topic;

    public MessageProducer(KafkaTemplate<String, String> kafkaTemplate, ObjectMapper objectMapper,
                            @Value("${app.kafka.messages-topic}") String topic) {
        this.kafkaTemplate = kafkaTemplate;
        this.objectMapper = objectMapper;
        this.topic = topic;
    }

    public void publish(SecureEnvelope envelope) throws Exception {
        String json = objectMapper.writeValueAsString(envelope);
        kafkaTemplate.send(topic, envelope.getEnvelopeId(), json);
    }
}

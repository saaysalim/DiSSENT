package com.crossbordersystem.securecomm2.service;

import com.crossbordersystem.securecomm2.model.DecryptedMessage;
import com.crossbordersystem.securecomm2.model.SecureEnvelope;
import com.crossbordersystem.securecomm2.repository.DecryptedMessageRepository;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.kafka.annotation.KafkaListener;
import org.springframework.stereotype.Service;

import java.time.Instant;

/**
 * Consumes envelopes published by securecomm, verifies the sender's signature against
 * the Fabric public-key registry, decrypts, and stores the result for PHP to poll.
 */
@Service
public class MessageConsumer {

    private static final Logger log = LoggerFactory.getLogger(MessageConsumer.class);

    private final ObjectMapper objectMapper;
    private final FabricLedgerService ledgerService;
    private final CryptoService cryptoService;
    private final DecryptedMessageRepository repository;

    public MessageConsumer(ObjectMapper objectMapper, FabricLedgerService ledgerService,
                            CryptoService cryptoService, DecryptedMessageRepository repository) {
        this.objectMapper = objectMapper;
        this.ledgerService = ledgerService;
        this.cryptoService = cryptoService;
        this.repository = repository;
    }

    @KafkaListener(topics = "${app.kafka.messages-topic}", groupId = "${spring.kafka.consumer.group-id}")
    public void onMessage(String payload) {
        SecureEnvelope envelope;
        try {
            envelope = objectMapper.readValue(payload, SecureEnvelope.class);
        } catch (Exception e) {
            log.error("Discarding unparseable Kafka message on secure-messages topic", e);
            return;
        }

        DecryptedMessage result = new DecryptedMessage();
        result.setEnvelopeId(envelope.getEnvelopeId());
        result.setSenderId(envelope.getSenderId());
        result.setReceiverId(envelope.getReceiverId());
        result.setReceivedAt(Instant.now().toString());

        try {
            String signerPublicKeyPem = ledgerService.getPublicKeyPem(envelope.getSignerId());
            String plaintext = cryptoService.verifyAndDecrypt(envelope, signerPublicKeyPem);

            result.setVerified(true);
            result.setDecryptedText(plaintext);
        } catch (Exception e) {
            log.error("Failed to verify/decrypt envelope {}", envelope.getEnvelopeId(), e);
            result.setVerified(false);
            result.setError(e.getMessage() != null ? e.getMessage() : e.getClass().getSimpleName());
        }

        repository.save(result);
    }
}

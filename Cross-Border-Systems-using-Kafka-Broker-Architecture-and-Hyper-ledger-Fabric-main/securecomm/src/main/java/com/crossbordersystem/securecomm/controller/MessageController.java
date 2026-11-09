package com.crossbordersystem.securecomm.controller;

import com.crossbordersystem.securecomm.model.SecureEnvelope;
import com.crossbordersystem.securecomm.model.SendMessageRequest;
import com.crossbordersystem.securecomm.service.CryptoService;
import com.crossbordersystem.securecomm.service.MessageProducer;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.Map;

@RestController
@RequestMapping("/api/messages")
public class MessageController {

    private final CryptoService cryptoService;
    private final MessageProducer messageProducer;

    public MessageController(CryptoService cryptoService, MessageProducer messageProducer) {
        this.cryptoService = cryptoService;
        this.messageProducer = messageProducer;
    }

    @PostMapping("/send")
    public ResponseEntity<?> send(@Valid @RequestBody SendMessageRequest request) {
        try {
            SecureEnvelope envelope = cryptoService.encryptAndSign(
                    request.getSenderId(), request.getReceiverId(), request.getPlaintext());
            messageProducer.publish(envelope);
            return ResponseEntity.ok(Map.of("envelopeId", envelope.getEnvelopeId()));
        } catch (Exception e) {
            String message = e.getMessage() != null ? e.getMessage() : e.getClass().getSimpleName();
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).body(Map.of("error", message));
        }
    }
}

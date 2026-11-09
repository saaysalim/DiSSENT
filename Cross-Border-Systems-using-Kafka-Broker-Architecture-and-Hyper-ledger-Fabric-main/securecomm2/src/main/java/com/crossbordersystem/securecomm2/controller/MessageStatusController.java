package com.crossbordersystem.securecomm2.controller;

import com.crossbordersystem.securecomm2.model.DecryptedMessage;
import com.crossbordersystem.securecomm2.repository.DecryptedMessageRepository;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.Map;
import java.util.Optional;

@RestController
@RequestMapping("/api/messages")
public class MessageStatusController {

    private final DecryptedMessageRepository repository;

    public MessageStatusController(DecryptedMessageRepository repository) {
        this.repository = repository;
    }

    @GetMapping("/{envelopeId}")
    public ResponseEntity<?> getStatus(@PathVariable String envelopeId) {
        Optional<DecryptedMessage> message = repository.findById(envelopeId);

        if (message.isEmpty()) {
            return ResponseEntity.ok(Map.of("status", "pending"));
        }

        DecryptedMessage found = message.get();
        if (found.isVerified()) {
            return ResponseEntity.ok(Map.of(
                    "status", "verified",
                    "decryptedText", found.getDecryptedText(),
                    "receivedAt", found.getReceivedAt()
            ));
        }

        return ResponseEntity.ok(Map.of(
                "status", "failed",
                "error", found.getError()
        ));
    }
}

package com.crossbordersystem.securecomm2.repository;

import com.crossbordersystem.securecomm2.model.DecryptedMessage;
import org.springframework.data.mongodb.repository.MongoRepository;

public interface DecryptedMessageRepository extends MongoRepository<DecryptedMessage, String> {
}

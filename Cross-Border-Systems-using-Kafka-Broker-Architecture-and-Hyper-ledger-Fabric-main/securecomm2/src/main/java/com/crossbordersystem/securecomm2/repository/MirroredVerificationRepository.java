package com.crossbordersystem.securecomm2.repository;

import com.crossbordersystem.securecomm2.model.MirroredVerification;
import org.springframework.data.mongodb.repository.MongoRepository;

public interface MirroredVerificationRepository extends MongoRepository<MirroredVerification, String> {
}

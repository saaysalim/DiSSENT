package com.crossbordersystem.securecomm.service;

import com.crossbordersystem.securecomm.model.SecureEnvelope;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

import javax.crypto.Cipher;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import java.security.KeyPair;
import java.security.KeyPairGenerator;
import java.security.PrivateKey;
import java.security.PublicKey;
import java.security.SecureRandom;
import java.security.Signature;
import java.security.spec.ECGenParameterSpec;
import java.nio.charset.StandardCharsets;
import java.time.Instant;
import java.util.Base64;
import java.util.UUID;

/**
 * AES-GCM for confidentiality + ECDSA for sender authenticity/integrity, matching
 * the project's original "AES + ECDSA, Fabric as public-key registry" design.
 *
 * The AES key is a single pre-shared secret (configured, not negotiated per-session) —
 * a deliberate simplification for this project's scope; a production system would
 * derive it via ECDH key agreement instead.
 */
@Service
public class CryptoService {

    private static final String AES_TRANSFORM = "AES/GCM/NoPadding";
    private static final int GCM_TAG_BITS = 128;
    private static final int GCM_IV_BYTES = 12;

    private final SecretKeySpec sharedAesKey;
    private final KeyPair signingKeyPair;
    private final String signerId;
    private final SecureRandom secureRandom = new SecureRandom();

    public CryptoService(@Value("${app.crypto.aes-key-base64}") String aesKeyBase64,
                          @Value("${app.identity.user-id}") String signerId) throws Exception {
        byte[] keyBytes = Base64.getDecoder().decode(aesKeyBase64);
        this.sharedAesKey = new SecretKeySpec(keyBytes, "AES");
        this.signerId = signerId;

        KeyPairGenerator generator = KeyPairGenerator.getInstance("EC");
        generator.initialize(new ECGenParameterSpec("secp256r1"));
        this.signingKeyPair = generator.generateKeyPair();
    }

    public PublicKey getPublicKey() {
        return signingKeyPair.getPublic();
    }

    /** X.509 SubjectPublicKeyInfo, PEM-wrapped, suitable for RegisterPublicKey on the ledger. */
    public String getPublicKeyPem() {
        String base64 = Base64.getEncoder().encodeToString(signingKeyPair.getPublic().getEncoded());
        StringBuilder pem = new StringBuilder("-----BEGIN PUBLIC KEY-----\n");
        for (int i = 0; i < base64.length(); i += 64) {
            pem.append(base64, i, Math.min(i + 64, base64.length())).append('\n');
        }
        pem.append("-----END PUBLIC KEY-----\n");
        return pem.toString();
    }

    public SecureEnvelope encryptAndSign(String senderId, String receiverId, String plaintext) throws Exception {
        byte[] iv = new byte[GCM_IV_BYTES];
        secureRandom.nextBytes(iv);

        Cipher cipher = Cipher.getInstance(AES_TRANSFORM);
        cipher.init(Cipher.ENCRYPT_MODE, sharedAesKey, new GCMParameterSpec(GCM_TAG_BITS, iv));
        byte[] ciphertext = cipher.doFinal(plaintext.getBytes(StandardCharsets.UTF_8));

        byte[] signature = sign(signingKeyPair.getPrivate(), ciphertext);

        return new SecureEnvelope(
                UUID.randomUUID().toString(),
                senderId,
                receiverId,
                signerId,
                Base64.getEncoder().encodeToString(ciphertext),
                Base64.getEncoder().encodeToString(iv),
                Base64.getEncoder().encodeToString(signature),
                Instant.now().toString()
        );
    }

    private byte[] sign(PrivateKey privateKey, byte[] data) throws Exception {
        Signature signature = Signature.getInstance("SHA256withECDSA");
        signature.initSign(privateKey);
        signature.update(data);
        return signature.sign();
    }
}

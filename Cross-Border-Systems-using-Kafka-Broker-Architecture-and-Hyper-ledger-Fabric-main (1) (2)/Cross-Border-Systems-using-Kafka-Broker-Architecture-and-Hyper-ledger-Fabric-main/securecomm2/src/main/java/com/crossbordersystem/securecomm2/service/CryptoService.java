package com.crossbordersystem.securecomm2.service;

import com.crossbordersystem.securecomm2.model.SecureEnvelope;
import com.crossbordersystem.securecomm2.util.PemUtils;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

import javax.crypto.Cipher;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.security.PublicKey;
import java.security.Signature;
import java.util.Base64;

/**
 * Mirror of securecomm's CryptoService, running the reverse direction: verify the
 * sender's ECDSA signature against their Fabric-registered public key, then AES-GCM
 * decrypt. The AES key is the same pre-shared secret configured on both sides — see
 * the note in securecomm's CryptoService for why this is a scoped-down simplification.
 */
@Service
public class CryptoService {

    private static final String AES_TRANSFORM = "AES/GCM/NoPadding";
    private static final int GCM_TAG_BITS = 128;

    private final SecretKeySpec sharedAesKey;

    public CryptoService(@Value("${app.crypto.aes-key-base64}") String aesKeyBase64) {
        byte[] keyBytes = Base64.getDecoder().decode(aesKeyBase64);
        this.sharedAesKey = new SecretKeySpec(keyBytes, "AES");
    }

    /** Throws if the signature doesn't verify; otherwise returns the decrypted plaintext. */
    public String verifyAndDecrypt(SecureEnvelope envelope, String senderPublicKeyPem) throws Exception {
        byte[] ciphertext = Base64.getDecoder().decode(envelope.getCiphertextBase64());
        byte[] signatureBytes = Base64.getDecoder().decode(envelope.getSignatureBase64());

        PublicKey senderPublicKey = PemUtils.readPublicKey(senderPublicKeyPem);
        Signature signature = Signature.getInstance("SHA256withECDSA");
        signature.initVerify(senderPublicKey);
        signature.update(ciphertext);

        if (!signature.verify(signatureBytes)) {
            throw new SecurityException("Signature verification failed for envelope " + envelope.getEnvelopeId());
        }

        byte[] iv = Base64.getDecoder().decode(envelope.getIvBase64());
        Cipher cipher = Cipher.getInstance(AES_TRANSFORM);
        cipher.init(Cipher.DECRYPT_MODE, sharedAesKey, new GCMParameterSpec(GCM_TAG_BITS, iv));
        byte[] plaintext = cipher.doFinal(ciphertext);

        return new String(plaintext, StandardCharsets.UTF_8);
    }
}

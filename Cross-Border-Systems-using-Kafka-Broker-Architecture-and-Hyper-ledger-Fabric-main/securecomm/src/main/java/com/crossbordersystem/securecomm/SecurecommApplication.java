package com.crossbordersystem.securecomm;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication
public class SecurecommApplication {

    public static void main(String[] args) {
        disableOpenTelemetryUnlessConfigured();
        SpringApplication.run(SecurecommApplication.class, args);
    }

    /**
     * The Fabric SDK auto-configures OpenTelemetry, which by default exports traces, metrics
     * and logs to localhost:4317 and logs "Failed to connect" when no collector is running.
     * Its autoconfigure only reads JVM system properties / OTEL_* env vars (not
     * application.properties), so it is switched off here before anything initialises it.
     * Set OTEL_SDK_DISABLED=false (or -Dotel.sdk.disabled=false) to turn monitoring back on.
     */
    private static void disableOpenTelemetryUnlessConfigured() {
        if (System.getProperty("otel.sdk.disabled") == null && System.getenv("OTEL_SDK_DISABLED") == null) {
            System.setProperty("otel.sdk.disabled", "true");
            System.setProperty("otel.traces.exporter", "none");
            System.setProperty("otel.metrics.exporter", "none");
            System.setProperty("otel.logs.exporter", "none");
        }
    }
}

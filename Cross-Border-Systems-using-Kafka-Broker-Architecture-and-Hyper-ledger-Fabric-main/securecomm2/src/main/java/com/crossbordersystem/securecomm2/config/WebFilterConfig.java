package com.crossbordersystem.securecomm2.config;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.web.servlet.FilterRegistrationBean;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

@Configuration
public class WebFilterConfig {

    @Bean
    public FilterRegistrationBean<ApiKeyFilter> apiKeyFilter(@Value("${api.key:}") String apiKey) {
        // Fail fast: with an empty key, a request sending an empty X-API-Key header would pass.
        if (apiKey == null || apiKey.isBlank()) {
            throw new IllegalStateException(
                    "DISSENT_API_KEY is not set. Set it in the environment before starting this service.");
        }
        FilterRegistrationBean<ApiKeyFilter> registration = new FilterRegistrationBean<>(new ApiKeyFilter(apiKey));
        registration.addUrlPatterns("/api/*");
        return registration;
    }
}

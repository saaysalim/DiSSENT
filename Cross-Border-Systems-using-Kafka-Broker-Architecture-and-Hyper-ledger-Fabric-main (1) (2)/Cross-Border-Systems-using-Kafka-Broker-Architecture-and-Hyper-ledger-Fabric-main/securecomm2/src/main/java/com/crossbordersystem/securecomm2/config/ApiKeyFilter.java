package com.crossbordersystem.securecomm2.config;

import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpFilter;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;

import java.io.IOException;

/**
 * Every /api/** endpoint here will eventually be reachable from the public internet
 * (InfinityFree calling out to this API), so it must not be left open. Registered
 * (scoped to /api/*) in {@link WebFilterConfig}.
 */
public class ApiKeyFilter extends HttpFilter {

    private final String expectedApiKey;

    public ApiKeyFilter(String expectedApiKey) {
        this.expectedApiKey = expectedApiKey;
    }

    @Override
    protected void doFilter(HttpServletRequest request, HttpServletResponse response, FilterChain chain)
            throws IOException, ServletException {

        String providedKey = request.getHeader("X-API-Key");

        if (providedKey == null || !providedKey.equals(expectedApiKey)) {
            response.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
            response.setContentType("application/json");
            response.getWriter().write("{\"error\":\"Missing or invalid X-API-Key header\"}");
            return;
        }

        chain.doFilter(request, response);
    }
}

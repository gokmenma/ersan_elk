package com.ersan.eimza;

import com.sun.net.httpserver.Headers;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;
import com.sun.net.httpserver.HttpServer;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.Base64;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class ErsanHttpServer {

    public static final int PORT = 9191;
    public static final String VERSION = "1.1.0 (Portable OpenSC)";

    private HttpServer server;

    public void start() throws IOException {
        server = HttpServer.create(new InetSocketAddress("127.0.0.1", PORT), 0);
        server.createContext("/", new MainHandler());
        server.setExecutor(null);
        server.start();
        System.out.println("Ersan E-İmza Java Servisi http://127.0.0.1:" + PORT + " üzerinde başlatıldı.");
    }

    public void stop() {
        if (server != null) {
            server.stop(0);
        }
    }

    private static class MainHandler implements HttpHandler {
        @Override
        public void handle(HttpExchange exchange) throws IOException {
            Headers respHeaders = exchange.getResponseHeaders();
            respHeaders.set("Access-Control-Allow-Origin", "*");
            respHeaders.set("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
            respHeaders.set("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Requested-With, Cache-Control");
            respHeaders.set("Access-Control-Allow-Private-Network", "true");
            respHeaders.set("Access-Control-Max-Age", "86400");
            respHeaders.set("Content-Type", "application/json; charset=UTF-8");
            respHeaders.set("Cache-Control", "no-cache, no-store, must-revalidate");

            String method = exchange.getRequestMethod().toUpperCase();
            String path = exchange.getRequestURI().getPath();

            if ("OPTIONS".equals(method)) {
                exchange.sendResponseHeaders(204, -1);
                return;
            }

            if ("GET".equals(method)) {
                if ("/status".equals(path) || "/detect".equals(path) || "/".equals(path)) {
                    TokenManager.TokenStatus ts = TokenManager.detectActiveToken();
                    StringBuilder sb = new StringBuilder("{");
                    if (ts.success) {
                        sb.append("\"status\":\"success\",");
                        sb.append("\"module\":").append(jsonEscape(ts.modulePath)).append(",");
                        sb.append("\"token_label\":").append(jsonEscape(ts.tokenLabel)).append(",");
                        sb.append("\"device_type\":").append(jsonEscape(ts.deviceType)).append(",");
                        sb.append("\"owner_name\":").append(jsonEscape(ts.ownerName)).append(",");
                        sb.append("\"tcno\":").append(jsonEscape(ts.tcno)).append(",");
                        sb.append("\"subject\":").append(jsonEscape(ts.subject)).append(",");
                    } else {
                        sb.append("\"status\":\"error\",");
                        sb.append("\"message\":").append(jsonEscape(ts.message)).append(",");
                    }
                    sb.append("\"agent_version\":").append(jsonEscape(VERSION)).append(",");
                    sb.append("\"system\":").append(jsonEscape(System.getProperty("os.name", "Unknown")));
                    sb.append("}");

                    sendResponse(exchange, 200, sb.toString());
                    return;
                } else {
                    sendResponse(exchange, 404, "{\"status\":\"error\",\"message\":\"Bulunamadı\"}");
                    return;
                }
            }

            if ("POST".equals(method) && "/sign".equals(path)) {
                String body = readStream(exchange.getRequestBody());
                String eHash = extractJsonValue(body, "hash");
                String pin = extractJsonValue(body, "pin");

                if (eHash == null || eHash.isEmpty() || pin == null || pin.isEmpty()) {
                    sendResponse(exchange, 400, "{\"status\":\"error\",\"message\":\"Özet (hash) ve PIN zorunludur.\"}");
                    return;
                }

                try {
                    byte[] rawDigest;
                    try {
                        rawDigest = Base64.getDecoder().decode(eHash);
                        if (rawDigest.length != 32) {
                            rawDigest = MessageDigest.getInstance("SHA-256").digest(eHash.getBytes(StandardCharsets.UTF_8));
                        }
                    } catch (Throwable t) {
                        rawDigest = MessageDigest.getInstance("SHA-256").digest(eHash.getBytes(StandardCharsets.UTF_8));
                    }

                    byte[] cadesDer = TokenManager.sign(pin, rawDigest);
                    String cadesB64 = Base64.getEncoder().encodeToString(cadesDer);

                    TokenManager.TokenStatus ts = TokenManager.detectActiveToken();
                    String signerName = ts.success ? ts.ownerName : "";
                    String signerTcno = ts.success ? ts.tcno : "";
                    String deviceType = ts.success ? ts.deviceType : "";

                    StringBuilder sb = new StringBuilder("{");
                    sb.append("\"status\":\"success\",");
                    sb.append("\"signature\":").append(jsonEscape(cadesB64)).append(",");
                    sb.append("\"signer_name\":").append(jsonEscape(signerName)).append(",");
                    sb.append("\"signer_tcno\":").append(jsonEscape(signerTcno)).append(",");
                    sb.append("\"device\":").append(jsonEscape(deviceType));
                    sb.append("}");

                    sendResponse(exchange, 200, sb.toString());
                } catch (Throwable e) {
                    String msg = e.getMessage() != null ? e.getMessage() : "İmza oluşturulamadı.";
                    sendResponse(exchange, 400, "{\"status\":\"error\",\"message\":" + jsonEscape(msg) + "}");
                }
                return;
            }

            sendResponse(exchange, 404, "{\"status\":\"error\",\"message\":\"Geçersiz servis\"}");
        }

        private void sendResponse(HttpExchange exchange, int statusCode, String responseText) throws IOException {
            byte[] bytes = responseText.getBytes(StandardCharsets.UTF_8);
            exchange.sendResponseHeaders(statusCode, bytes.length);
            try (OutputStream os = exchange.getResponseBody()) {
                os.write(bytes);
            }
        }

        private String readStream(InputStream is) throws IOException {
            ByteArrayOutputStream baos = new ByteArrayOutputStream();
            byte[] buf = new byte[4096];
            int n;
            while ((n = is.read(buf)) != -1) {
                baos.write(buf, 0, n);
            }
            return new String(baos.toByteArray(), StandardCharsets.UTF_8);
        }

        private String extractJsonValue(String json, String key) {
            Pattern p = Pattern.compile("\"" + Pattern.quote(key) + "\"\\s*:\\s*\"([^\"]*)\"");
            Matcher m = p.matcher(json);
            if (m.find()) {
                return m.group(1).trim();
            }
            return "";
        }

        private String jsonEscape(String str) {
            if (str == null) return "\"\"";
            StringBuilder sb = new StringBuilder("\"");
            for (char c : str.toCharArray()) {
                switch (c) {
                    case '"': sb.append("\\\""); break;
                    case '\\': sb.append("\\\\"); break;
                    case '\b': sb.append("\\b"); break;
                    case '\f': sb.append("\\f"); break;
                    case '\n': sb.append("\\n"); break;
                    case '\r': sb.append("\\r"); break;
                    case '\t': sb.append("\\t"); break;
                    default:
                        if (c < 0x20) {
                            sb.append(String.format("\\u%04x", (int) c));
                        } else {
                            sb.append(c);
                        }
                }
            }
            sb.append("\"");
            return sb.toString();
        }
    }
}

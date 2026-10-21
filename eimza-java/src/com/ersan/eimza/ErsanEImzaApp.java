package com.ersan.eimza;

import java.awt.*;
import java.awt.event.WindowAdapter;
import java.awt.event.WindowEvent;
import java.awt.image.BufferedImage;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import javax.swing.*;

public class ErsanEImzaApp {

    private static JFrame mainFrame;
    private static JLabel lblStatus;
    private static JLabel lblCard;
    private static ErsanHttpServer server;
    private static TrayIcon trayIcon;

    public static void main(String[] args) {
        System.out.println("=================================================");
        System.out.println("  Ersan E-İmza Yerel Köprü Servisi (Java v1.2.2) ");
        System.out.println("  Dinleme Adresi: http://127.0.0.1:9191          ");
        System.out.println("=================================================");

        // 1. HTTP Sunucusunu Başlat
        try {
            server = new ErsanHttpServer();
            server.start();
        } catch (Exception e) {
            if (requestExistingInstanceToShow()) {
                System.out.println("Çalışan Ersan E-İmza ajanı bulundu; penceresi açıldı.");
                return;
            }
            showStartupError(e);
            System.exit(1);
        }

        // 2. GUI Destekleniyorsa Pencere & Sistem Tepsisi (Tray) Göster
        if (!GraphicsEnvironment.isHeadless()) {
            SwingUtilities.invokeLater(() -> initGui());
        } else {
            System.out.println("Grafik arayüz bulunamadı, konsol modunda çalışıyor.");
            TokenManager.TokenStatus ts = TokenManager.detectActiveToken();
            if (ts.success) {
                System.out.println("[✓] Kart: " + ts.deviceType + " - " + ts.ownerName + " (TC: " + ts.tcno + ")");
            } else {
                System.out.println("[!] " + ts.message);
            }
        }
    }

    private static void initGui() {
        try {
            UIManager.setLookAndFeel(UIManager.getSystemLookAndFeelClassName());
        } catch (Throwable ignored) {}

        mainFrame = new JFrame("Ersan E-İmza Servisi");
        mainFrame.setSize(520, 280);
        mainFrame.setLocationRelativeTo(null);
        mainFrame.setDefaultCloseOperation(JFrame.HIDE_ON_CLOSE);
        mainFrame.setLayout(new BorderLayout(15, 15));

        JPanel pnlCenter = new JPanel(new GridLayout(3, 1, 8, 8));
        pnlCenter.setBorder(BorderFactory.createEmptyBorder(15, 20, 10, 20));

        JLabel lblTitle = new JLabel("Ersan E-İmza Yerel Ajanı (v1.2.2 PAdES)", JLabel.LEFT);
        lblTitle.setFont(new Font("Segoe UI", Font.BOLD, 15));
        lblTitle.setForeground(new Color(33, 37, 41));

        lblStatus = new JLabel("● Servis Aktif (http://127.0.0.1:9191)", JLabel.LEFT);
        lblStatus.setFont(new Font("Segoe UI", Font.PLAIN, 13));
        lblStatus.setForeground(new Color(25, 135, 84));

        lblCard = new JLabel("Kart aranıyor...", JLabel.LEFT);
        lblCard.setFont(new Font("Segoe UI", Font.PLAIN, 12));
        lblCard.setForeground(new Color(108, 117, 125));

        pnlCenter.add(lblTitle);
        pnlCenter.add(lblStatus);
        pnlCenter.add(lblCard);
        mainFrame.add(pnlCenter, BorderLayout.CENTER);

        JPanel pnlBottom = new JPanel(new FlowLayout(FlowLayout.RIGHT, 10, 10));
        JButton btnRefresh = new JButton("Kartı Yeniden Tara");
        btnRefresh.addActionListener(e -> refreshCardStatus());

        JButton btnMinimize = new JButton("Arka Plana Al");
        btnMinimize.addActionListener(e -> mainFrame.setVisible(false));

        JButton btnRestart = new JButton("Bağlantıyı Yenile");
        btnRestart.addActionListener(e -> restartAgent());

        JButton btnExit = new JButton("Tamamen Kapat");
        btnExit.addActionListener(e -> exitAgent());

        pnlBottom.add(btnRefresh);
        pnlBottom.add(btnRestart);
        pnlBottom.add(btnMinimize);
        pnlBottom.add(btnExit);
        mainFrame.add(pnlBottom, BorderLayout.SOUTH);

        // Sistem Tepsisi (System Tray) Kur
        if (SystemTray.isSupported()) {
            initSystemTray();
        }

        refreshCardStatus();
        mainFrame.setVisible(true);
    }

    private static void refreshCardStatus() {
        new Thread(() -> {
            TokenManager.TokenStatus ts = TokenManager.detectActiveToken();
            SwingUtilities.invokeLater(() -> updateCardStatus(ts));
        }).start();
    }

    private static void initSystemTray() {
        SystemTray tray = SystemTray.getSystemTray();
        Image image = createTrayIconImage();

        PopupMenu popup = new PopupMenu();
        MenuItem itemOpen = new MenuItem("Durum Penceresini Aç");
        itemOpen.addActionListener(e -> showMainWindow());

        MenuItem itemRefresh = new MenuItem("Kartı Yeniden Tara");
        itemRefresh.addActionListener(e -> refreshCardStatus());

        MenuItem itemRestart = new MenuItem("Bağlantıyı Yenile");
        itemRestart.addActionListener(e -> restartAgent());

        MenuItem itemExit = new MenuItem("Tamamen Kapat");
        itemExit.addActionListener(e -> exitAgent());

        popup.add(itemOpen);
        popup.add(itemRefresh);
        popup.addSeparator();
        popup.add(itemRestart);
        popup.add(itemExit);

        trayIcon = new TrayIcon(image, "Ersan E-İmza Servisi", popup);
        trayIcon.setImageAutoSize(true);
        trayIcon.addActionListener(e -> showMainWindow());

        try {
            tray.add(trayIcon);
        } catch (AWTException e) {
            System.err.println("TrayIcon eklenemedi: " + e.getMessage());
        }
    }

    public static void showMainWindow() {
        if (GraphicsEnvironment.isHeadless()) return;
        SwingUtilities.invokeLater(() -> {
            if (mainFrame == null) return;
            mainFrame.setVisible(true);
            mainFrame.setState(Frame.NORMAL);
            mainFrame.toFront();
            mainFrame.requestFocus();
        });
    }

    private static void restartAgent() {
        if (lblStatus != null) {
            lblStatus.setText("● Bağlantı ve kart bilgisi yenileniyor...");
            lblStatus.setForeground(new Color(255, 152, 0));
        }
        new Thread(() -> {
            try {
                // 9191 servisini kapatıp aynı porta yeniden bağlanmak bazı kurumsal
                // Windows güvenlik ürünlerinde askıda kalabiliyor. Servis çalışırken
                // yalnız kart önbelleğini yenilemek güvenli ve yeterlidir.
                TokenManager.clearCache();
                TokenManager.TokenStatus tokenStatus = TokenManager.detectActiveToken();
                SwingUtilities.invokeLater(() -> {
                    lblStatus.setText("● Servis Aktif (http://127.0.0.1:9191)");
                    lblStatus.setForeground(new Color(25, 135, 84));
                    updateCardStatus(tokenStatus);
                });
                if (trayIcon != null) {
                    trayIcon.displayMessage("Ersan E-İmza", "Bağlantı ve kart bilgisi yenilendi.", TrayIcon.MessageType.INFO);
                }
            } catch (Exception ex) {
                SwingUtilities.invokeLater(() -> {
                    lblStatus.setText("● Servis başlatılamadı: " + ex.getMessage());
                    lblStatus.setForeground(new Color(220, 53, 69));
                });
            }
        }, "ersan-agent-restart").start();
    }

    private static void updateCardStatus(TokenManager.TokenStatus ts) {
        if (lblCard == null) return;
        if (ts.success) {
            lblCard.setText("✓ " + ts.ownerName + " (" + ts.deviceType + ")");
            lblCard.setForeground(new Color(25, 135, 84));
        } else {
            lblCard.setText("! USB E-İmza Kartı Takılı Değil");
            lblCard.setForeground(new Color(220, 53, 69));
        }
    }

    private static void exitAgent() {
        if (server != null) server.stop();
        if (trayIcon != null && SystemTray.isSupported()) {
            SystemTray.getSystemTray().remove(trayIcon);
            trayIcon = null;
        }
        if (mainFrame != null) mainFrame.dispose();
        System.exit(0);
    }

    private static boolean requestExistingInstanceToShow() {
        try {
            HttpURLConnection connection = (HttpURLConnection) new URL("http://127.0.0.1:9191/show").openConnection();
            connection.setRequestMethod("POST");
            connection.setConnectTimeout(1000);
            connection.setReadTimeout(1000);
            connection.setDoOutput(true);
            try (OutputStream output = connection.getOutputStream()) {
                output.write(new byte[0]);
            }
            return connection.getResponseCode() == 200;
        } catch (Exception ignored) {
            return false;
        }
    }

    private static void showStartupError(Exception error) {
        String message = "Ersan E-İmza ajanı başlatılamadı.\n\n" + error.getMessage();
        System.err.println(message);
        if (!GraphicsEnvironment.isHeadless()) {
            JOptionPane.showMessageDialog(null, message, "Ersan E-İmza", JOptionPane.ERROR_MESSAGE);
        }
    }

    private static Image createTrayIconImage() {
        BufferedImage img = new BufferedImage(32, 32, BufferedImage.TYPE_INT_ARGB);
        Graphics2D g2 = img.createGraphics();
        g2.setRenderingHint(RenderingHints.KEY_ANTIALIASING, RenderingHints.VALUE_ANTIALIAS_ON);
        g2.setColor(new Color(13, 110, 253));
        g2.fillRoundRect(2, 2, 28, 28, 8, 8);
        g2.setColor(Color.WHITE);
        g2.setFont(new Font("Segoe UI", Font.BOLD, 16));
        g2.drawString("E", 10, 22);
        g2.dispose();
        return img;
    }
}

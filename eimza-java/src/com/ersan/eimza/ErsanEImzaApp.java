package com.ersan.eimza;

import java.awt.*;
import java.awt.event.WindowAdapter;
import java.awt.event.WindowEvent;
import java.awt.image.BufferedImage;
import javax.swing.*;

public class ErsanEImzaApp {

    private static JFrame mainFrame;
    private static JLabel lblStatus;
    private static JLabel lblCard;
    private static ErsanHttpServer server;

    public static void main(String[] args) {
        System.out.println("=================================================");
        System.out.println("  Ersan E-İmza Yerel Köprü Servisi (Java v1.0)   ");
        System.out.println("  Dinleme Adresi: http://127.0.0.1:9191          ");
        System.out.println("=================================================");

        // 1. HTTP Sunucusunu Başlat
        try {
            server = new ErsanHttpServer();
            server.start();
        } catch (Exception e) {
            System.err.println("HTTP Sunucusu başlatılamadı: " + e.getMessage());
            e.printStackTrace();
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
        mainFrame.setSize(440, 240);
        mainFrame.setLocationRelativeTo(null);
        mainFrame.setDefaultCloseOperation(JFrame.HIDE_ON_CLOSE);
        mainFrame.setLayout(new BorderLayout(15, 15));

        JPanel pnlCenter = new JPanel(new GridLayout(3, 1, 8, 8));
        pnlCenter.setBorder(BorderFactory.createEmptyBorder(15, 20, 10, 20));

        JLabel lblTitle = new JLabel("Ersan E-İmza Yerel Ajanı (v1.0)", JLabel.LEFT);
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

        pnlBottom.add(btnRefresh);
        pnlBottom.add(btnMinimize);
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
            SwingUtilities.invokeLater(() -> {
                if (ts.success) {
                    lblCard.setText("✓ " + ts.ownerName + " (" + ts.deviceType + ")");
                    lblCard.setForeground(new Color(25, 135, 84));
                } else {
                    lblCard.setText("! USB E-İmza Kartı Takılı Değil");
                    lblCard.setForeground(new Color(220, 53, 69));
                }
            });
        }).start();
    }

    private static void initSystemTray() {
        SystemTray tray = SystemTray.getSystemTray();
        Image image = createTrayIconImage();

        PopupMenu popup = new PopupMenu();
        MenuItem itemOpen = new MenuItem("Pencereyi Göster");
        itemOpen.addActionListener(e -> {
            mainFrame.setVisible(true);
            mainFrame.toFront();
        });

        MenuItem itemRefresh = new MenuItem("Kartı Yeniden Tara");
        itemRefresh.addActionListener(e -> refreshCardStatus());

        MenuItem itemExit = new MenuItem("Çıkış");
        itemExit.addActionListener(e -> {
            server.stop();
            System.exit(0);
        });

        popup.add(itemOpen);
        popup.add(itemRefresh);
        popup.addSeparator();
        popup.add(itemExit);

        TrayIcon trayIcon = new TrayIcon(image, "Ersan E-İmza Servisi", popup);
        trayIcon.setImageAutoSize(true);
        trayIcon.addActionListener(e -> {
            mainFrame.setVisible(true);
            mainFrame.toFront();
        });

        try {
            tray.add(trayIcon);
        } catch (AWTException e) {
            System.err.println("TrayIcon eklenemedi: " + e.getMessage());
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

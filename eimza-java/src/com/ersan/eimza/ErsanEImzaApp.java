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
        System.out.println("  Ersan E-İmza Yerel Köprü Servisi (Java v1.3.0) ");
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
        mainFrame.setSize(720, 470);
        mainFrame.setMinimumSize(new Dimension(640, 430));
        mainFrame.setLocationRelativeTo(null);
        mainFrame.setDefaultCloseOperation(JFrame.HIDE_ON_CLOSE);
        mainFrame.getContentPane().setBackground(new Color(244, 247, 250));
        mainFrame.setLayout(new BorderLayout());

        JPanel header = new JPanel(new BorderLayout(14, 0));
        header.setBackground(new Color(31, 51, 73));
        header.setBorder(BorderFactory.createEmptyBorder(18, 22, 18, 22));
        JLabel logo = new JLabel("E", JLabel.CENTER);
        logo.setOpaque(true);
        logo.setBackground(new Color(45, 156, 219));
        logo.setForeground(Color.WHITE);
        logo.setFont(new Font("Segoe UI", Font.BOLD, 22));
        logo.setPreferredSize(new Dimension(46, 46));
        header.add(logo, BorderLayout.WEST);

        JPanel titlePanel = new JPanel(new GridLayout(2, 1, 0, 2));
        titlePanel.setOpaque(false);
        JLabel lblTitle = new JLabel("Ersan E-İmza Yerel Ajanı");
        lblTitle.setFont(new Font("Segoe UI", Font.BOLD, 18));
        lblTitle.setForeground(Color.WHITE);
        JLabel lblSubtitle = new JLabel("Güvenli elektronik imza köprüsü  •  v1.3.0 PAdES");
        lblSubtitle.setFont(new Font("Segoe UI", Font.PLAIN, 12));
        lblSubtitle.setForeground(new Color(190, 205, 219));
        titlePanel.add(lblTitle);
        titlePanel.add(lblSubtitle);
        header.add(titlePanel, BorderLayout.CENTER);
        mainFrame.add(header, BorderLayout.NORTH);

        JTabbedPane tabs = new JTabbedPane();
        tabs.setFont(new Font("Segoe UI", Font.BOLD, 13));
        tabs.setBorder(BorderFactory.createEmptyBorder(10, 16, 0, 16));
        tabs.addTab("  Durum  ", createStatusTab());
        tabs.addTab("  Ayarlar ve Tanılama  ", createSettingsTab());
        mainFrame.add(tabs, BorderLayout.CENTER);

        JPanel pnlBottom = new JPanel(new FlowLayout(FlowLayout.RIGHT, 10, 10));
        pnlBottom.setBackground(new Color(244, 247, 250));
        pnlBottom.setBorder(BorderFactory.createMatteBorder(1, 0, 0, 0, new Color(222, 227, 232)));
        JButton btnMinimize = createButton("Arka Plana Al", false);
        btnMinimize.addActionListener(e -> mainFrame.setVisible(false));
        JButton btnExit = createButton("Tamamen Kapat", false);
        btnExit.addActionListener(e -> exitAgent());
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

    private static JPanel createStatusTab() {
        JPanel panel = new JPanel();
        panel.setBackground(new Color(244, 247, 250));
        panel.setLayout(new BoxLayout(panel, BoxLayout.Y_AXIS));
        panel.setBorder(BorderFactory.createEmptyBorder(16, 8, 16, 8));

        lblStatus = new JLabel("●  Servis Aktif", JLabel.LEFT);
        lblStatus.setFont(new Font("Segoe UI", Font.BOLD, 14));
        lblStatus.setForeground(new Color(25, 135, 84));
        JPanel serviceCard = createInfoCard("YEREL SERVİS", lblStatus,
                "http://127.0.0.1:9191 adresinde güvenli biçimde çalışıyor.");

        lblCard = new JLabel("Kart aranıyor...", JLabel.LEFT);
        lblCard.setFont(new Font("Segoe UI", Font.BOLD, 14));
        lblCard.setForeground(new Color(108, 117, 125));
        JPanel cardCard = createInfoCard("E-İMZA KARTI", lblCard,
                "Kartınızı taktıktan sonra yeniden tarayabilirsiniz.");

        JButton btnRefresh = new JButton("Kartı Yeniden Tara");
        styleButton(btnRefresh, true);
        btnRefresh.addActionListener(e -> refreshCardStatus());
        JPanel action = new JPanel(new FlowLayout(FlowLayout.LEFT, 0, 0));
        action.setOpaque(false);
        action.add(btnRefresh);

        panel.add(serviceCard);
        panel.add(Box.createVerticalStrut(12));
        panel.add(cardCard);
        panel.add(Box.createVerticalStrut(14));
        panel.add(action);
        return panel;
    }

    private static JPanel createSettingsTab() {
        JPanel panel = new JPanel(new GridLayout(3, 1, 0, 10));
        panel.setBackground(new Color(244, 247, 250));
        panel.setBorder(BorderFactory.createEmptyBorder(16, 8, 16, 8));

        panel.add(createSettingRow("Sürücü Ayarı",
                "Kartınız otomatik bulunmazsa üreticinin PKCS#11 DLL dosyasını seçin.",
                "Sürücü DLL Seç", e -> choosePkcs11Driver()));
        panel.add(createSettingRow("Bağlantıyı Yenile",
                "Servisi kapatmadan kart ve bağlantı önbelleğini güvenle yeniler.",
                "Bağlantıyı Yenile", e -> restartAgent()));
        panel.add(createSettingRow("Tanılama Bilgileri",
                "Java mimarisi, sürücü yolu ve kart okuyucu çıktısını görüntüler.",
                "Tanılamayı Aç", e -> showDiagnostic()));
        return panel;
    }

    private static JPanel createInfoCard(String heading, JLabel value, String detail) {
        JPanel card = new JPanel();
        card.setLayout(new BoxLayout(card, BoxLayout.Y_AXIS));
        card.setBackground(Color.WHITE);
        card.setBorder(BorderFactory.createCompoundBorder(
                BorderFactory.createLineBorder(new Color(222, 227, 232)),
                BorderFactory.createEmptyBorder(12, 16, 12, 16)));
        card.setAlignmentX(Component.LEFT_ALIGNMENT);
        card.setMaximumSize(new Dimension(Integer.MAX_VALUE, 86));
        JLabel title = new JLabel(heading);
        title.setFont(new Font("Segoe UI", Font.BOLD, 10));
        title.setForeground(new Color(108, 117, 125));
        JLabel description = new JLabel(detail);
        description.setFont(new Font("Segoe UI", Font.PLAIN, 11));
        description.setForeground(new Color(108, 117, 125));
        card.add(title);
        card.add(Box.createVerticalStrut(5));
        card.add(value);
        card.add(Box.createVerticalStrut(3));
        card.add(description);
        return card;
    }

    private static JPanel createSettingRow(String title, String detail, String buttonText,
                                           java.awt.event.ActionListener listener) {
        JPanel row = new JPanel(new BorderLayout(14, 0));
        row.setBackground(Color.WHITE);
        row.setBorder(BorderFactory.createCompoundBorder(
                BorderFactory.createLineBorder(new Color(222, 227, 232)),
                BorderFactory.createEmptyBorder(10, 14, 10, 14)));
        JPanel text = new JPanel(new GridLayout(2, 1, 0, 2));
        text.setOpaque(false);
        JLabel heading = new JLabel(title);
        heading.setFont(new Font("Segoe UI", Font.BOLD, 13));
        heading.setForeground(new Color(33, 37, 41));
        JLabel description = new JLabel(detail);
        description.setFont(new Font("Segoe UI", Font.PLAIN, 11));
        description.setForeground(new Color(108, 117, 125));
        text.add(heading);
        text.add(description);
        JButton button = createButton(buttonText, false);
        button.addActionListener(listener);
        row.add(text, BorderLayout.CENTER);
        row.add(button, BorderLayout.EAST);
        return row;
    }

    private static JButton createButton(String text, boolean primary) {
        JButton button = new JButton(text);
        styleButton(button, primary);
        return button;
    }

    private static void styleButton(JButton button, boolean primary) {
        button.setFont(new Font("Segoe UI", Font.BOLD, 12));
        button.setFocusPainted(false);
        button.setCursor(Cursor.getPredefinedCursor(Cursor.HAND_CURSOR));
        button.setBorder(BorderFactory.createCompoundBorder(
                BorderFactory.createLineBorder(primary ? new Color(13, 110, 253) : new Color(190, 198, 206)),
                BorderFactory.createEmptyBorder(7, 14, 7, 14)));
        if (primary) {
            button.setBackground(new Color(13, 110, 253));
            button.setForeground(Color.WHITE);
            button.setOpaque(true);
        } else {
            button.setBackground(Color.WHITE);
            button.setForeground(new Color(52, 58, 64));
        }
    }

    private static void refreshCardStatus() {
        new Thread(() -> {
            TokenManager.TokenStatus ts = TokenManager.detectActiveToken();
            SwingUtilities.invokeLater(() -> {
                updateCardStatus(ts);
                if (lblStatus != null) {
                    lblStatus.setText("● Servis Aktif (http://127.0.0.1:9191)");
                    lblStatus.setForeground(new Color(25, 135, 84));
                }
            });
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

        MenuItem itemDriver = new MenuItem("Sürücü DLL Seç");
        itemDriver.addActionListener(e -> choosePkcs11Driver());

        MenuItem itemDiagnostic = new MenuItem("Tanılama");
        itemDiagnostic.addActionListener(e -> showDiagnostic());

        MenuItem itemExit = new MenuItem("Tamamen Kapat");
        itemExit.addActionListener(e -> exitAgent());

        popup.add(itemOpen);
        popup.add(itemRefresh);
        popup.add(itemDriver);
        popup.add(itemDiagnostic);
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

    private static void choosePkcs11Driver() {
        if (GraphicsEnvironment.isHeadless()) return;
        JFileChooser chooser = new JFileChooser();
        chooser.setDialogTitle("E-İmza PKCS#11 sürücü DLL dosyasını seçin");
        chooser.setFileSelectionMode(JFileChooser.FILES_ONLY);
        String current = TokenManager.getCustomModulePath();
        if (!current.isEmpty()) chooser.setSelectedFile(new java.io.File(current));
        chooser.setFileFilter(new javax.swing.filechooser.FileNameExtensionFilter("PKCS#11 sürücüsü (*.dll)", "dll"));
        if (chooser.showOpenDialog(mainFrame) != JFileChooser.APPROVE_OPTION) return;

        java.io.File selected = chooser.getSelectedFile();
        if (selected == null || !selected.isFile()) {
            JOptionPane.showMessageDialog(mainFrame, "Geçerli bir DLL dosyası seçilmedi.", "Ersan E-İmza", JOptionPane.WARNING_MESSAGE);
            return;
        }
        TokenManager.setCustomModulePath(selected.getAbsolutePath());
        lblStatus.setText("● Seçilen sürücü kontrol ediliyor...");
        lblStatus.setForeground(new Color(255, 152, 0));
        refreshCardStatus();
    }

    private static void showDiagnostic() {
        JTextArea details = new JTextArea(TokenManager.getDiagnosticReport(), 14, 70);
        details.setEditable(false);
        details.setLineWrap(true);
        details.setWrapStyleWord(true);
        details.setCaretPosition(0);
        JScrollPane scroll = new JScrollPane(details);
        JOptionPane.showMessageDialog(mainFrame, scroll, "E-İmza Sürücü Tanılaması", JOptionPane.INFORMATION_MESSAGE);
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

package com.ersan.eimza;

import com.formdev.flatlaf.FlatClientProperties;
import com.formdev.flatlaf.FlatLightLaf;
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
    private static final Color NAVY = new Color(17, 42, 66);
    private static final Color NAVY_SOFT = new Color(25, 58, 84);
    private static final Color TEAL = new Color(17, 151, 161);
    private static final Color CANVAS = new Color(242, 245, 248);
    private static final Color TEXT = new Color(24, 39, 55);
    private static final Color MUTED = new Color(101, 116, 132);
    private static final Color BORDER = new Color(224, 230, 236);

    public static void main(String[] args) {
        System.out.println("=================================================");
        System.out.println("  Ersan E-İmza Yerel Köprü Servisi (Java v1.3.1) ");
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
            FlatLightLaf.setup();
            UIManager.put("Component.arc", 14);
            UIManager.put("Button.arc", 14);
            UIManager.put("TextComponent.arc", 12);
            UIManager.put("ScrollBar.width", 11);
            UIManager.put("Component.focusWidth", 1);
        } catch (Throwable error) {
            try {
                UIManager.setLookAndFeel(UIManager.getSystemLookAndFeelClassName());
            } catch (Throwable ignored) {}
        }

        mainFrame = new JFrame("Ersan E-İmza Yerel Ajanı");
        mainFrame.setSize(960, 620);
        mainFrame.setMinimumSize(new Dimension(820, 540));
        mainFrame.setLocationRelativeTo(null);
        mainFrame.setDefaultCloseOperation(JFrame.HIDE_ON_CLOSE);
        mainFrame.getContentPane().setBackground(CANVAS);
        mainFrame.setLayout(new BorderLayout());

        CardLayout pages = new CardLayout();
        JPanel content = new JPanel(pages);
        content.setBackground(CANVAS);
        content.add(createStatusTab(), "home");
        content.add(createSettingsTab(), "settings");
        mainFrame.add(createSidebar(pages, content), BorderLayout.WEST);
        mainFrame.add(content, BorderLayout.CENTER);

        // Sistem Tepsisi (System Tray) Kur
        if (SystemTray.isSupported()) {
            initSystemTray();
        }

        refreshCardStatus();
        mainFrame.setVisible(true);
    }

    private static JPanel createStatusTab() {
        JPanel panel = new JPanel(new BorderLayout(0, 18));
        panel.setBackground(CANVAS);
        panel.setBorder(BorderFactory.createEmptyBorder(28, 28, 22, 28));
        panel.add(createPageHeader("Genel Bakış", "Yerel servis ve e-imza kartınızın anlık durumu"), BorderLayout.NORTH);

        JPanel body = new JPanel();
        body.setOpaque(false);
        body.setLayout(new BoxLayout(body, BoxLayout.Y_AXIS));

        lblStatus = new JLabel("●  Servis Aktif", JLabel.LEFT);
        lblStatus.setFont(uiFont(Font.BOLD, 17));
        lblStatus.setForeground(new Color(25, 135, 84));
        JPanel serviceCard = createInfoCard("YEREL SERVİS", lblStatus,
                "http://127.0.0.1:9191 adresinde güvenli biçimde çalışıyor.");

        lblCard = new JLabel("Kart aranıyor...", JLabel.LEFT);
        lblCard.setFont(uiFont(Font.BOLD, 17));
        lblCard.setForeground(MUTED);
        JPanel cardCard = createInfoCard("E-İMZA KARTI", lblCard,
                "Kartınızı taktıktan sonra yeniden tarayabilirsiniz.");

        JPanel cardGrid = new JPanel(new GridLayout(1, 2, 16, 0));
        cardGrid.setOpaque(false);
        cardGrid.setMaximumSize(new Dimension(Integer.MAX_VALUE, 156));
        cardGrid.add(serviceCard);
        cardGrid.add(cardCard);

        JButton btnRefresh = new JButton("↻  Kartı Yeniden Tara");
        styleButton(btnRefresh, true);
        btnRefresh.addActionListener(e -> refreshCardStatus());
        JButton btnDriver = createButton("Sürücü Seç", false);
        btnDriver.addActionListener(e -> choosePkcs11Driver());
        JPanel action = new JPanel(new FlowLayout(FlowLayout.LEFT, 10, 0));
        action.setOpaque(false);
        action.add(btnRefresh);
        action.add(btnDriver);

        body.add(cardGrid);
        body.add(Box.createVerticalStrut(16));
        body.add(createRequestCard());
        body.add(Box.createVerticalStrut(18));
        body.add(action);
        panel.add(body, BorderLayout.CENTER);
        panel.add(createFooter(), BorderLayout.SOUTH);
        return panel;
    }

    private static JPanel createSettingsTab() {
        JPanel panel = new JPanel(new BorderLayout(0, 18));
        panel.setBackground(CANVAS);
        panel.setBorder(BorderFactory.createEmptyBorder(28, 28, 22, 28));
        panel.add(createPageHeader("Ayarlar ve Tanılama", "Kart sürücüsü, bağlantı ve teknik bilgiler"), BorderLayout.NORTH);

        JPanel settings = new JPanel(new GridLayout(3, 1, 0, 12));
        settings.setOpaque(false);
        settings.add(createSettingRow("Sürücü Ayarı",
                "Kartınız otomatik bulunmazsa üreticinin PKCS#11 DLL dosyasını seçin.",
                "Sürücü DLL Seç", e -> choosePkcs11Driver()));
        settings.add(createSettingRow("Bağlantıyı Yenile",
                "Servisi kapatmadan kart ve bağlantı önbelleğini güvenle yeniler.",
                "Bağlantıyı Yenile", e -> restartAgent()));
        settings.add(createSettingRow("Tanılama Bilgileri",
                "Java mimarisi, sürücü yolu ve kart okuyucu çıktısını görüntüler.",
                "Tanılamayı Aç", e -> showDiagnostic()));
        panel.add(settings, BorderLayout.CENTER);
        panel.add(createFooter(), BorderLayout.SOUTH);
        return panel;
    }

    private static JPanel createInfoCard(String heading, JLabel value, String detail) {
        JPanel card = new RoundedPanel(20, Color.WHITE);
        card.setLayout(new BoxLayout(card, BoxLayout.Y_AXIS));
        card.setBorder(BorderFactory.createEmptyBorder(20, 22, 20, 22));
        card.setAlignmentX(Component.LEFT_ALIGNMENT);
        card.setMaximumSize(new Dimension(Integer.MAX_VALUE, 156));
        JLabel title = new JLabel(heading);
        title.setFont(uiFont(Font.BOLD, 11));
        title.setForeground(MUTED);
        JLabel description = new JLabel(detail);
        description.setFont(uiFont(Font.PLAIN, 12));
        description.setForeground(MUTED);
        card.add(title);
        card.add(Box.createVerticalGlue());
        card.add(value);
        card.add(Box.createVerticalStrut(7));
        card.add(description);
        return card;
    }

    private static JPanel createSettingRow(String title, String detail, String buttonText,
                                           java.awt.event.ActionListener listener) {
        JPanel row = new RoundedPanel(18, Color.WHITE);
        row.setLayout(new BorderLayout(18, 0));
        row.setBorder(BorderFactory.createEmptyBorder(18, 20, 18, 20));
        JPanel text = new JPanel(new GridLayout(2, 1, 0, 2));
        text.setOpaque(false);
        JLabel heading = new JLabel(title);
        heading.setFont(uiFont(Font.BOLD, 14));
        heading.setForeground(TEXT);
        JLabel description = new JLabel(detail);
        description.setFont(uiFont(Font.PLAIN, 12));
        description.setForeground(MUTED);
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
        button.setFont(uiFont(Font.BOLD, 12));
        button.setFocusPainted(false);
        button.setCursor(Cursor.getPredefinedCursor(Cursor.HAND_CURSOR));
        button.putClientProperty(FlatClientProperties.STYLE,
                "arc: 14; borderWidth: 1; focusWidth: 1; innerFocusWidth: 0");
        button.setBorder(BorderFactory.createEmptyBorder(10, 17, 10, 17));
        if (primary) {
            button.setBackground(TEAL);
            button.setForeground(Color.WHITE);
            button.setOpaque(true);
        } else {
            button.setBackground(Color.WHITE);
            button.setForeground(TEXT);
        }
    }

    private static JPanel createSidebar(CardLayout pages, JPanel content) {
        JPanel sidebar = new JPanel(new BorderLayout());
        sidebar.setBackground(NAVY);
        sidebar.setPreferredSize(new Dimension(235, 0));
        sidebar.setBorder(BorderFactory.createEmptyBorder(24, 18, 20, 18));

        JPanel brand = new JPanel(new BorderLayout(12, 0));
        brand.setOpaque(false);
        JLabel logo = new JLabel("E", JLabel.CENTER);
        logo.setOpaque(true);
        logo.setBackground(TEAL);
        logo.setForeground(Color.WHITE);
        logo.setFont(uiFont(Font.BOLD, 25));
        logo.setPreferredSize(new Dimension(48, 48));
        logo.putClientProperty(FlatClientProperties.STYLE, "arc: 16");

        JPanel brandText = new JPanel(new GridLayout(2, 1, 0, 1));
        brandText.setOpaque(false);
        JLabel brandTitle = new JLabel("Ersan E-İmza");
        brandTitle.setFont(uiFont(Font.BOLD, 16));
        brandTitle.setForeground(Color.WHITE);
        JLabel brandSub = new JLabel("Yerel Ajan");
        brandSub.setFont(uiFont(Font.PLAIN, 11));
        brandSub.setForeground(new Color(164, 184, 201));
        brandText.add(brandTitle);
        brandText.add(brandSub);
        brand.add(logo, BorderLayout.WEST);
        brand.add(brandText, BorderLayout.CENTER);
        sidebar.add(brand, BorderLayout.NORTH);

        JPanel navigation = new JPanel();
        navigation.setOpaque(false);
        navigation.setLayout(new BoxLayout(navigation, BoxLayout.Y_AXIS));
        navigation.setBorder(BorderFactory.createEmptyBorder(34, 0, 0, 0));
        JButton home = createNavButton("⌂   Ana Sayfa", true);
        JButton settings = createNavButton("⚙   Ayarlar", false);
        home.addActionListener(e -> {
            pages.show(content, "home");
            setNavActive(home, settings);
        });
        settings.addActionListener(e -> {
            pages.show(content, "settings");
            setNavActive(settings, home);
        });
        navigation.add(home);
        navigation.add(Box.createVerticalStrut(9));
        navigation.add(settings);
        navigation.add(Box.createVerticalGlue());
        sidebar.add(navigation, BorderLayout.CENTER);

        JPanel version = new JPanel();
        version.setOpaque(false);
        version.setLayout(new BoxLayout(version, BoxLayout.Y_AXIS));
        JLabel secure = new JLabel("●  Güvenli bağlantı");
        secure.setFont(uiFont(Font.BOLD, 11));
        secure.setForeground(new Color(103, 211, 163));
        JLabel versionText = new JLabel("v1.3.1  •  PAdES");
        versionText.setFont(uiFont(Font.PLAIN, 11));
        versionText.setForeground(new Color(139, 162, 181));
        secure.setAlignmentX(Component.LEFT_ALIGNMENT);
        versionText.setAlignmentX(Component.LEFT_ALIGNMENT);
        version.add(secure);
        version.add(Box.createVerticalStrut(5));
        version.add(versionText);
        sidebar.add(version, BorderLayout.SOUTH);
        return sidebar;
    }

    private static JButton createNavButton(String text, boolean active) {
        JButton button = new JButton(text);
        button.setHorizontalAlignment(SwingConstants.LEFT);
        button.setMaximumSize(new Dimension(Integer.MAX_VALUE, 46));
        button.setPreferredSize(new Dimension(199, 46));
        button.setFont(uiFont(Font.BOLD, 13));
        button.setFocusPainted(false);
        button.setBorder(BorderFactory.createEmptyBorder(0, 15, 0, 15));
        button.setCursor(Cursor.getPredefinedCursor(Cursor.HAND_CURSOR));
        button.putClientProperty(FlatClientProperties.STYLE,
                "arc: 14; borderWidth: 0; focusWidth: 0; innerFocusWidth: 0");
        button.setBackground(active ? TEAL : NAVY);
        button.setForeground(active ? Color.WHITE : new Color(194, 209, 221));
        return button;
    }

    private static void setNavActive(JButton active, JButton inactive) {
        active.setBackground(TEAL);
        active.setForeground(Color.WHITE);
        inactive.setBackground(NAVY);
        inactive.setForeground(new Color(194, 209, 221));
    }

    private static JPanel createPageHeader(String title, String subtitle) {
        JPanel header = new JPanel(new GridLayout(2, 1, 0, 3));
        header.setOpaque(false);
        JLabel heading = new JLabel(title);
        heading.setFont(uiFont(Font.BOLD, 24));
        heading.setForeground(TEXT);
        JLabel detail = new JLabel(subtitle);
        detail.setFont(uiFont(Font.PLAIN, 13));
        detail.setForeground(MUTED);
        header.add(heading);
        header.add(detail);
        return header;
    }

    private static JPanel createRequestCard() {
        JPanel card = new RoundedPanel(20, Color.WHITE);
        card.setLayout(new BorderLayout(18, 0));
        card.setBorder(BorderFactory.createEmptyBorder(18, 22, 18, 22));
        card.setAlignmentX(Component.LEFT_ALIGNMENT);
        card.setMaximumSize(new Dimension(Integer.MAX_VALUE, 118));

        JPanel text = new JPanel(new GridLayout(2, 1, 0, 4));
        text.setOpaque(false);
        JLabel heading = new JLabel("İmza İstekleri");
        heading.setFont(uiFont(Font.BOLD, 15));
        heading.setForeground(TEXT);
        JLabel detail = new JLabel("Tarayıcıdan gelen güvenli imzalama talepleri burada işlenir.");
        detail.setFont(uiFont(Font.PLAIN, 12));
        detail.setForeground(MUTED);
        text.add(heading);
        text.add(detail);

        JPanel count = new JPanel(new GridLayout(2, 1, 0, 0));
        count.setOpaque(false);
        JLabel value = new JLabel("0", JLabel.CENTER);
        value.setFont(uiFont(Font.BOLD, 26));
        value.setForeground(TEXT);
        JLabel label = new JLabel("Bekleyen", JLabel.CENTER);
        label.setFont(uiFont(Font.PLAIN, 11));
        label.setForeground(MUTED);
        count.add(value);
        count.add(label);
        count.setPreferredSize(new Dimension(78, 64));

        card.add(text, BorderLayout.CENTER);
        card.add(count, BorderLayout.EAST);
        return card;
    }

    private static JPanel createFooter() {
        JPanel footer = new JPanel(new BorderLayout());
        footer.setOpaque(false);
        footer.setBorder(BorderFactory.createMatteBorder(1, 0, 0, 0, BORDER));
        JLabel state = new JLabel("  ●  Ajan arka planda çalışmaya devam eder");
        state.setFont(uiFont(Font.PLAIN, 11));
        state.setForeground(MUTED);

        JPanel actions = new JPanel(new FlowLayout(FlowLayout.RIGHT, 9, 10));
        actions.setOpaque(false);
        JButton btnMinimize = createButton("Arka Plana Al", false);
        btnMinimize.addActionListener(e -> mainFrame.setVisible(false));
        JButton btnExit = createButton("Sistemi Kapat", false);
        btnExit.setForeground(new Color(184, 54, 65));
        btnExit.addActionListener(e -> exitAgent());
        actions.add(btnMinimize);
        actions.add(btnExit);
        footer.add(state, BorderLayout.WEST);
        footer.add(actions, BorderLayout.EAST);
        return footer;
    }

    private static Font uiFont(int style, int size) {
        String family = System.getProperty("os.name", "").toLowerCase().contains("win")
                ? "Segoe UI" : "SansSerif";
        return new Font(family, style, size);
    }

    private static final class RoundedPanel extends JPanel {
        private final int radius;
        private final Color fill;

        private RoundedPanel(int radius, Color fill) {
            this.radius = radius;
            this.fill = fill;
            setOpaque(false);
        }

        @Override
        protected void paintComponent(Graphics graphics) {
            Graphics2D g2 = (Graphics2D) graphics.create();
            g2.setRenderingHint(RenderingHints.KEY_ANTIALIASING, RenderingHints.VALUE_ANTIALIAS_ON);
            g2.setColor(fill);
            g2.fillRoundRect(0, 0, getWidth() - 1, getHeight() - 1, radius, radius);
            g2.setColor(BORDER);
            g2.drawRoundRect(0, 0, getWidth() - 1, getHeight() - 1, radius, radius);
            g2.dispose();
            super.paintComponent(graphics);
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

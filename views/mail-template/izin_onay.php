<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni İzin Talebi</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8; padding:20px;">
        <tr>
            <td align="center">
                <!-- Card -->
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#2563eb; padding:20px; text-align:center;">
                            <span style="font-size:24px; color:#ffffff;">📄 Yeni İzin Talebi</span>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding:25px; color:#1f2937; font-size:14px; line-height:1.6;">
                            <p style="margin-top:0;">
                                Merhaba <strong>{{ONAYLAYAN_AD_SOYAD}}</strong>,
                            </p>

                            <p>
                                <strong>{{TALEP_EDEN_AD_SOYAD}}</strong> tarafından yeni bir izin talebi oluşturulmuştur.
                                Detaylar aşağıda yer almaktadır:
                            </p>

                            <!-- Info Box -->
                            <table width="100%" cellpadding="8" cellspacing="0" style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; margin:15px 0;">
                                <tr>
                                    <td width="30">🏷️</td>
                                    <td><strong>İzin Türü:</strong> {{IZIN_TURU}}</td>
                                </tr>
                                <tr>
                                    <td>📅</td>
                                    <td><strong>Başlangıç:</strong> {{BASLANGIC_TARIHI}}</td>
                                </tr>
                                <tr>
                                    <td>📅</td>
                                    <td><strong>Bitiş:</strong> {{BITIS_TARIHI}}</td>
                                </tr>
                                <tr>
                                    <td>📝</td>
                                    <td><strong>Açıklama:</strong><br>{{ACIKLAMA}}</td>
                                </tr>
                            </table>

                            <p>
                                Talebi inceleyerek sistem üzerinden işlem yapmanızı rica ederiz.
                            </p>

                            <!-- Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:20px 0;">
                                        <a href="{{ONAY_LINKI}}"
                                           style="background-color:#2563eb; color:#ffffff; text-decoration:none;
                                                  padding:12px 28px; border-radius:6px; font-size:14px; display:inline-block;">
                                            ✅ Talebi İncele & Onayla
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size:12px; color:#6b7280;">
                                Bu mail otomatik olarak oluşturulmuştur.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f9fafb; padding:15px; text-align:center; font-size:12px; color:#6b7280;">
                            © {{YIL}} İzin Yönetim Sistemi
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

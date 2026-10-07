<?php

namespace App\Service;

use App\Model\BildirimModel;
use App\Model\SystemLogModel;
use App\Model\UserNotificationPreferenceModel;

final class EInvoiceIncomingNotificationService
{
    public function notify(int $firmId, array $invoices): int
    {
        $notificationModel = new BildirimModel();
        $pushService = new PushNotificationService();
        $recipientIds = $notificationModel->userIdsByPermissionForFirm($firmId, 'efatura/gelen-list');
        $created = 0;
        $pushSent = 0;

        foreach ($invoices as $invoice) {
            $invoiceNo = trim((string)($invoice['fatura_no'] ?? '')) ?: 'Numarasız fatura';
            $sender = trim((string)($invoice['gonderici_unvan'] ?? '')) ?: 'Bilinmeyen gönderici';
            $amount = number_format((float)($invoice['odenecek_tutar'] ?? 0), 2, ',', '.');
            $currency = trim((string)($invoice['para_birimi'] ?? 'TRY')) ?: 'TRY';

            $title = 'Yeni gelen e-Fatura';
            $message = sprintf('%s tarafından düzenlenen %s numaralı %s %s tutarındaki fatura sisteme alındı.', $sender, $invoiceNo, $amount, $currency);
            $link = 'index.php?p=efatura/gelen-list&search=' . rawurlencode($invoiceNo);

            foreach ($recipientIds as $userId) {
                if ($notificationModel->createNotification($userId, $title, $message, $link, 'file-plus', 'success', UserNotificationPreferenceModel::TYPE_EINVOICE)) {
                    $created++;
                }
                if ($pushService->sendToUser($userId, ['title' => $title, 'body' => $message, 'url' => $link], true, UserNotificationPreferenceModel::TYPE_EINVOICE, $firmId)) {
                    $pushSent++;
                }
            }
        }

        (new SystemLogModel())->logActionForFirm(
            $firmId,
            0,
            'E-Fatura Bildirimi',
            sprintf('%d yeni gelen fatura için %d uygulama içi bildirim ve %d mobil push gönderildi; %d yetkili alıcı değerlendirildi.', count($invoices), $created, $pushSent, count($recipientIds)),
            SystemLogModel::LEVEL_IMPORTANT
        );

        return $created;
    }
}

<?php
namespace App\Services;

/**
 * FlashMessageService
 * 
 * Session tabanlı "flash" mesajları yönetir. Bir sonraki sayfa
 * isteğinde gösterilip sonra silinen geçici mesajlar oluşturur.
 * Kullanımı:
 * FlashMessageService::add('success', 'Başlık', 'Mesaj içeriği');
 * Sayfada gösterim için:
 */
class FlashMessageService
{
    /**
     * Session'da mesajları saklamak için kullanılacak anahtar.
     */
    private const FLASH_KEY = 'flash_messages';

    /**
     * Yeni bir flash mesajı session'a ekler.
     *
     * @param string $type    Mesaj türü (success, error, warning, info).
     * @param string $title   Mesaj başlığı.
     * @param string $message Mesaj içeriği.
     * @param string $icon    (İsteğe bağlı) Gösterilecek ikon dosya adı.
     */
    public static function add(string $type, string $title, string $message, string $icon = 'ikaz2.png'): void
    {
        // Session'ın aktif olduğundan emin ol
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::FLASH_KEY][] = [
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'icon'    => $icon
        ];
    }

    /**
     * Session'daki tüm flash mesajlarını alır ve sonra siler.
     *
     * @return array Alınan mesajlar dizisi veya boş dizi.
     */
    public static function getMessages(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION[self::FLASH_KEY])) {
            $messages = $_SESSION[self::FLASH_KEY];
            // Mesajları aldıktan sonra session'dan temizle
            unset($_SESSION[self::FLASH_KEY]);
            return $messages;
        }

        return [];
    }

    /**
     * Session'da okunmamış flash mesajı olup olmadığını kontrol eder.
     *
     * @return bool
     */
    public static function hasMessages(): bool
    {
        // if (session_status() === PHP_SESSION_NONE) {
        //     session_start();
        // }
        
        return isset($_SESSION[self::FLASH_KEY]) && !empty($_SESSION[self::FLASH_KEY]);
    }
}
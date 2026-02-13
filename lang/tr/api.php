<?php

return [

    // Genel
    'success'            => 'Başarılı',
    'error'              => 'Hata',
    'too_many_requests'  => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.',
    'forbidden'          => 'Erişim engellendi.',
    'not_found'          => 'Kaynak bulunamadı.',
    'method_not_allowed' => 'Metot izni yok.',
    'unauthenticated'    => 'Kimlik doğrulanmadı.',
    'validation_failed'  => 'Doğrulama başarısız.',
    'server_error'       => 'Sunucu hatası.',

    // Kimlik Doğrulama
    'auth' => [
        'registered'          => 'Kullanıcı başarıyla kaydedildi.',
        'login_success'       => 'Giriş başarılı.',
        'login_failed'        => 'Geçersiz kimlik bilgileri.',
        'account_deactivated' => 'Hesabınız devre dışı bırakılmıştır.',
        'logged_out'          => 'Başarıyla çıkış yapıldı.',
        'logged_out_all'      => 'Tüm cihazlardan başarıyla çıkış yapıldı.',
        'token_refreshed'     => 'Token başarıyla yenilendi.',
    ],

    // Şifre
    'password' => [
        'changed'       => 'Şifre başarıyla değiştirildi.',
        'reset_link'    => 'E-postanız kayıtlıysa şifre sıfırlama bağlantısı alacaksınız.',
        'reset_success' => 'Şifre başarıyla sıfırlandı.',
        'reset_failed'  => 'Geçersiz veya süresi dolmuş sıfırlama kodu.',
    ],

    // Doğrulama
    'verification' => [
        'verified'         => 'E-posta başarıyla doğrulandı.',
        'already_verified' => 'E-posta zaten doğrulanmış.',
        'invalid_link'     => 'Geçersiz doğrulama bağlantısı.',
        'link_sent'        => 'Doğrulama bağlantısı gönderildi.',
    ],

    // Profil
    'profile' => [
        'updated'          => 'Profil başarıyla güncellendi.',
        'password_changed' => 'Şifre başarıyla değiştirildi.',
    ],

    // Sağlık
    'health' => [
        'ok'       => 'Tüm sistemler çalışıyor.',
        'degraded' => 'Bazı hizmetlerde sorun yaşanıyor.',
    ],

];

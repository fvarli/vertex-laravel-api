<?php

return [

    // Genel
    'success' => 'Başarılı',
    'error' => 'Hata',
    'too_many_requests' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.',
    'forbidden' => 'Erişim engellendi.',
    'not_found' => 'Kaynak bulunamadı.',
    'method_not_allowed' => 'Metot izni yok.',
    'unauthenticated' => 'Kimlik doğrulanmadı.',
    'validation_failed' => 'Doğrulama başarısız.',
    'server_error' => 'Sunucu hatası.',

    // Kimlik Doğrulama
    'auth' => [
        'registered' => 'Kullanıcı başarıyla kaydedildi.',
        'login_success' => 'Giriş başarılı.',
        'login_failed' => 'Geçersiz kimlik bilgileri.',
        'account_deactivated' => 'Hesabınız devre dışı bırakılmıştır.',
        'logged_out' => 'Başarıyla çıkış yapıldı.',
        'logged_out_all' => 'Tüm cihazlardan başarıyla çıkış yapıldı.',
        'token_refreshed' => 'Token başarıyla yenilendi.',
    ],

    // Şifre
    'password' => [
        'changed' => 'Şifre başarıyla değiştirildi.',
        'reset_link' => 'E-postanız kayıtlıysa şifre sıfırlama bağlantısı alacaksınız.',
        'reset_success' => 'Şifre başarıyla sıfırlandı.',
        'reset_failed' => 'Geçersiz veya süresi dolmuş sıfırlama kodu.',
    ],

    // Doğrulama
    'verification' => [
        'verified' => 'E-posta başarıyla doğrulandı.',
        'already_verified' => 'E-posta zaten doğrulanmış.',
        'invalid_link' => 'Geçersiz doğrulama bağlantısı.',
        'link_sent' => 'Doğrulama bağlantısı gönderildi.',
    ],

    // Profil
    'profile' => [
        'updated' => 'Profil başarıyla güncellendi.',
        'password_changed' => 'Şifre başarıyla değiştirildi.',
        'account_deleted' => 'Hesap başarıyla silindi.',
        'avatar_uploaded' => 'Avatar başarıyla yüklendi.',
        'avatar_deleted' => 'Avatar başarıyla silindi.',
    ],

    // Sağlık
    'health' => [
        'ok' => 'Tüm sistemler çalışıyor.',
        'degraded' => 'Bazı hizmetlerde sorun yaşanıyor.',
    ],

    // Workspace
    'workspace' => [
        'created' => 'Workspace başarıyla oluşturuldu.',
        'switched' => 'Aktif workspace başarıyla değiştirildi.',
        'no_active_workspace' => 'Aktif workspace seçilmemiş.',
        'membership_required' => 'Bu workspace için erişim yetkiniz yok.',
    ],

    // Student
    'student' => [
        'created' => 'Öğrenci başarıyla oluşturuldu.',
        'updated' => 'Öğrenci başarıyla güncellendi.',
        'status_updated' => 'Öğrenci durumu başarıyla güncellendi.',
    ],

    // Program
    'program' => [
        'created' => 'Program başarıyla oluşturuldu.',
        'updated' => 'Program başarıyla güncellendi.',
        'status_updated' => 'Program durumu başarıyla güncellendi.',
        'active_exists_for_week' => 'Bu öğrenci ve hafta için zaten aktif bir program var.',
        'duplicate_day_order' => 'Program öğeleri aynı day_of_week ve order_no değerini paylaşamaz.',
        'template_created' => 'Program şablonu başarıyla oluşturuldu.',
        'template_updated' => 'Program şablonu başarıyla güncellendi.',
        'template_deleted' => 'Program şablonu başarıyla silindi.',
        'copied_week' => 'Program hedef haftaya başarıyla kopyalandı.',
        'copy_source_missing' => 'Seçilen kaynak hafta için program bulunamadı.',
    ],

    // Appointment
    'appointment' => [
        'created' => 'Randevu başarıyla oluşturuldu.',
        'updated' => 'Randevu başarıyla güncellendi.',
        'status_updated' => 'Randevu durumu başarıyla güncellendi.',
        'whatsapp_status_updated' => 'Randevu WhatsApp durumu başarıyla güncellendi.',
        'series_created' => 'Randevu serisi başarıyla oluşturuldu.',
        'series_updated' => 'Randevu serisi başarıyla güncellendi.',
        'series_status_updated' => 'Randevu serisi durumu başarıyla güncellendi.',
        'conflict' => 'Antrenör veya öğrenci için randevu çakışması tespit edildi.',
        'invalid_status_transition' => 'Durum geçişine izin verilmiyor.',
        'cannot_complete_future' => 'Gelecekteki randevular done veya no_show olarak işaretlenemez.',
    ],

    'reminder' => [
        'opened' => 'Hatırlatma açıldı olarak işaretlendi.',
        'marked_sent' => 'Hatırlatma gönderildi olarak işaretlendi.',
        'cancelled' => 'Hatırlatma iptal edildi.',
        'requeued' => 'Hatırlatma başarıyla yeniden kuyruğa alındı.',
        'bulk_applied' => 'Toplu hatırlatma işlemi başarıyla uygulandı.',
    ],

    // Web
    'web' => [
        'api_only' => 'Bu uygulama yalnızca API içindir. Web erişimine izin verilmez.',
        'api_client_only' => 'Yalnızca API erişimi. Lütfen bir API istemcisi kullanın.',
    ],

];

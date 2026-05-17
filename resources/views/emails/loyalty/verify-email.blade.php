<!DOCTYPE html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Halo {{ $name }},</p>

    <p>Terima kasih telah mendaftar di program loyalitas Platinum Adi Sentosa.</p>

    <p>Silakan klik tautan di bawah ini untuk memverifikasi alamat email Anda.
       Tautan ini berlaku selama 24 jam.</p>

    <p>
        <a href="{{ $verificationUrl }}"
           style="background:#1a73e8;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;">
            Verifikasi Email
        </a>
    </p>

    <p>Jika tombol tidak berfungsi, salin dan tempel URL berikut ke browser Anda:</p>
    <p style="word-break:break-all;">{{ $verificationUrl }}</p>

    <p>Jika Anda tidak merasa mendaftar, abaikan email ini.</p>

    <p>Salam,<br>Tim Platinum Adi Sentosa</p>
</body>
</html>

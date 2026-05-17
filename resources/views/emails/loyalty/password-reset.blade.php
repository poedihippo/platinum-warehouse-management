<!DOCTYPE html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Halo,</p>

    <p>Kami menerima permintaan untuk mereset kata sandi akun loyalitas
       Anda ({{ $email }}).</p>

    <p>Klik tautan di bawah ini untuk menyetel kata sandi baru. Tautan
       ini berlaku selama 60 menit:</p>

    <p>
        <a href="{{ $resetUrl }}"
           style="background:#1a73e8;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;">
            Reset Kata Sandi
        </a>
    </p>

    <p>Jika tombol tidak berfungsi, salin dan tempel URL berikut ke browser Anda:</p>
    <p style="word-break:break-all;">{{ $resetUrl }}</p>

    <p>Jika Anda tidak meminta reset kata sandi, abaikan email ini —
       kata sandi Anda tidak akan berubah.</p>

    <p>Salam,<br>Tim Platinum Adi Sentosa</p>
</body>
</html>

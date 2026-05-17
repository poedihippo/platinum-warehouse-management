<!DOCTYPE html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Halo,</p>

    <p>Kami menerima permintaan untuk mereset kata sandi akun loyalitas
       Anda ({{ $email }}).</p>

    <p>Gunakan kode token berikut untuk menyetel kata sandi baru. Token
       ini berlaku selama 60 menit:</p>

    <p style="font-family:monospace;font-size:16px;background:#f4f4f4;padding:10px;word-break:break-all;">
        {{ $token }}
    </p>

    <p>Jika Anda tidak meminta reset kata sandi, abaikan email ini —
       kata sandi Anda tidak akan berubah.</p>

    <p>Salam,<br>Tim Platinum Adi Sentosa</p>
</body>
</html>

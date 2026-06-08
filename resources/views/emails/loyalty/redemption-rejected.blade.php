<!DOCTYPE html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Halo {{ $name }},</p>

    <p>Mohon maaf, penukaran Anda untuk hadiah
       <strong>{{ $prizeName }}</strong> <strong>tidak disetujui</strong>.</p>

    <p>Alasan:</p>
    <blockquote style="border-left:3px solid #ccc;padding-left:12px;color:#555;">
        {{ $reason }}
    </blockquote>

    <p>Poin Anda telah dikembalikan sepenuhnya dan dapat digunakan kembali
       untuk menukar hadiah lain.</p>

    <p>Salam,<br>Tim Platinum Adi Sentosa</p>
</body>
</html>

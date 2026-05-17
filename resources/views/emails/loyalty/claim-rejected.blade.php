<!DOCTYPE html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Halo {{ $name }},</p>

    <p>Mohon maaf, pengajuan Anda untuk invoice
       <strong>{{ $invoiceNumber }}</strong> <strong>tidak disetujui</strong>.</p>

    <p>Alasan:</p>
    <blockquote style="border-left:3px solid #ccc;padding-left:12px;color:#555;">
        {{ $reason }}
    </blockquote>

    <p>Anda dapat mengajukan kembali dengan bukti yang valid jika diperlukan.</p>

    <p>Salam,<br>Tim Platinum Adi Sentosa</p>
</body>
</html>

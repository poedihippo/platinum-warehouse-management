# FEATURE Voucher
ini adalah fitur voucher yang digunakan untuk membuat invoice.

# EXPLANATION
secara flow tidak ada perubahan penggunaan vocucher di @app/Http/Controllers/Api/InvoiceController.php function store.
perubahannya ada di voucher itu sendiri, jadi sekarang voucher harus memiliki masa aktif, start_date dan end_date dengan type date di mysql.
lalu untuk pembuatan data voucher bisa melalui 2 cara, yang pertama melalui batch seperti sekarang dan input qty stock yang akan di generate, yang kedua langsung create voucher itu sendiri yang mana user harus boleh input `code` vouchers atau tidak, jika tidak di input maka akan di generate otomatis oleh system.

lalu pada saat create invoice di @app/Http/Controllers/Api/InvoiceController.php function store, harus di cek apakah voucher nya valid, valid disini artinya belum pernah digunakan, tidak di delete, dan hari ini masih berada di range `start_date` dan `end_date` voucher tersebut.
perhatikan juga dibagian preview create invoice, jadi di InvoiceController function store gw bikin kondisi apabila client kirim is_preview=true, maka itu adalah mekanisme untuk preview invoice sebelum dibuat, jadi nanti di frontend user bisa lihat preview orderan/invoice nya sebelum dibuat, pengencekan valid voucher harus ada juga di preview invoice.

lalu nanti implementasi fitur voucher ini di frontend, voucher ini akan terbagi menjadi 3 bagian yang akan ditampilkan di frontend(FE).
yang pertama tampilan data Voucher Category itu sudah ada di @app/Http/Controllers/Api/VoucherCategoryController.php api nya tinggal di cek aja.
yang kedua tampilan data Generate Voucher Batch, FE akan menampilkan data generate batch voucher, itu sudah ada api nya di @app/Http/Controllers/Api/VoucherGenerateBatchController.php tinggal di cek dan diperbaiki jika perlu.
yang ketika tampilan data voucher itu sendiri, api nya ada di @app/Http/Controllers/Api/VoucherController.php tinggal di cek dan diperbaiki jika perlu. jadi di data GET /vouchers harus bisa di filter berdasarkan start_date dan end_date nya, lalu bisa di filter berdasarkan batch nya, lalu juga terlihat apakah voucher tersebut sudah digunakan atau belum, dan jika sudah harus ditampilkan relasinya dengan salesOrder (sudah ada relasi salesOrder di @app/Models/Voucher.php)

untuk pengerjaan task ini lu tidak perlu menjalankan format menggunakan pint ya, nanti gw sendiri yang akan menjalankannya

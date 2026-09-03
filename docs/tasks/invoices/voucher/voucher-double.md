# FEATURE Voucher
ini adalah fitur voucher yang digunakan untuk membuat invoice.

# EXPLANATION
saat ini voucher sudah berjalan dengan benar di  @app/Http/Controllers/Api/InvoiceController.php function store dan update, bisa cek juga di @app/Services/SalesOrderService.php function createOrder lalu di @app/Pipes/Order/CalculateVoucher.php.

saat ini satu data invoice/sales_orders hanya bisa apply 1 voucher, dan itu disimpan di kolom `voucher_id` di table sales_orders. 

sekarang penggunakan voucher bisa lebih dari 1 dalam 1 sales_orders. plan gw sih bikin table sales_order_vouchers, dan hapus kolom voucher_id yang sekarang. lalu update code di @app/Pipes/Order/CalculateVoucher.php juga.

jangan lupa di file migration sekalian buatkan SQL sebelum hapus kolom voucher_id untuk migrasi data lama untuk sales_orders yang memiliki voucher_id ke table baru sales_order_vouchers. agar datanya tetap compatible.

lu bisa sambil cek database gw ya, credentials nya ada di file @.env

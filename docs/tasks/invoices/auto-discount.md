di @app/Http/Controllers/Api/InvoiceController.php function store dan update, kita menggunakan @app/Pipes/Order/CalculateAutoDiscount.php untuk calculate auto discount yang ada di @config/app.php min_trx_auto_discount.

list discount sekarang seperti ini
 'min_trx_auto_discount' => [
        [
            'min_value' => 75001,
            'max_value' => 250000,
            'discount' => [5],
        ],
        [
            'min_value' => 250001,
            'max_value' => 500000,
            'discount' => [5, 2],
        ],
        [
            'min_value' => 500001,
            'max_value' => 1000000,
            'discount' => [10],
        ],
        [
            'min_value' => 1000001,
            'max_value' => 2000000,
            'discount' => [15],
        ],
        [
            'min_value' => 2000001,
            'max_value' => null,
            'discount' => [20],
        ],
    ],

nah user ingin untuk discount yang double seperti yang ada di array index 1, disitu discount 5 percent lalu di discount lagi 2 percent. secara perhitungan di CalculateAutoDiscount.php sudah benar, tapi secara data tampilan user ingin nya ada detail discount nya.
misal user belanja 500000 lalu mendapatkan discount 5% + 2% . user ingin detail discount 5% nya dapet berapa, lalu discount 2% nya dapet berapa.
karena secara perhitungan sudah benar, sebenernya kita tinggal update dan simpan data nya di kolom `raw_source` table orders aja sih, jadi di detailkan lagi untuk auto discount ini.
tapi problem nya karena di table orders sudah ada kolom `auto_discount` untuk menyimpan value dari auto discount ini, jadi nanti frontend/client harus koordinasi lagi untuk ambil datanya dari `raw_source`, ini sebenernya jadi kurang rapih, tapi gimana ya best practice nya ? apa kita buat table baru `order_discounts` untuk menyimpan data auto discount ini ?

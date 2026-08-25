ini adalah project warehouse system distributor pakan, app ini diberi nama "BEJO"
techstack:

1. Laravel version 10
2. mysql as database
3. redis

library penting yang digunakan:

1. spatie/laravel-medialibrary
2. spatie/laravel-permission
3. spatie/laravel-query-builder
4. maatwebsite/excel
5. barryvdh/laravel-dompdf
6. bensampo/laravel-enum

lu bisa cek selengkapnya di @composer.json

fitur di project ini :

1. Pencatatan stock. stock disini disimpan di table stocks, dengan primary key id as ulid, dan id tersebut nantinya akan digenerate as QR code.
   pencatatan stock termasuk history penambahan atau pengurangan stock dari manual input atau dari sales order dan delivery order yang terbuat
2. SALES ORDER. adalah data penjualan yang dilakukan ketika ada customer membeli barang, which is relate ke stocks juga nantinya, tapi ini nanti ada di proses DELIVERY ORDER.
3. DELIVERY ORDER. proses pengiriman barang berdasarkan SALES ORDER yang telah dibuat. dalam satu SALES ORDER bisa dibuat beberapa DELIVERY ORDER, karena pengiriman qty SALES ORDER bisa tidak sekaligus dikirim semua. misal di suatu SALES ORDER beli product_unit dengan qty 100. bisa saja di DELIVERY ORDER A dikirim 50, DELIVERY ORDER B dikirim 50.
4. PRODUCT UNITS. barang/item yang di perjual belikan dan di record sebagai stocks adalah table product_units. lu bisa cek relasinya di @app/Models/ProductUnit.php .
   pencatatan stock tidak langsung ke product_units, tapi melalui table stock_product_units @app/Models/StockProductUnit.php karena pencatatan stocks harus berdasarkan warehouse nya @app/Models/Warehouse.php .

dari deskripsi diatas, buatkan file AGENTS.md dan PRD.md untuk project ini agar ai agent paham. disini gw pake ai agent OPENCODE, setup berdasarkan kebutuhan opencode ya dengan cara yang propper dan best practice



material masuk
HU=handle unit
BOM=bill of material
WIP=work in progress

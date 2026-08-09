# Keuangan
membuat Website Harian Keuangan

Nama Databases di Xmpp db_harian_keuangan di dalam databases itu ada 2 file yaitu :
1.Jurnal_harian = id INT(11) Primarykey.
                 tanggal datetime
                 isi_catatan text utf8mb4_general_ci	
                 created_at timestamp 
2.transaksi = id INT(11) primarykey .
             tanggal Date
             keterangan vachar utf8mb4_general_ci	
             jumlah decimal(12,2)
             tipe enum('masuk','keluar')
             created_at timestamp
            katerogi Vachar(50) utf8mb4_general_ci	
            

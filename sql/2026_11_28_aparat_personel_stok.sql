ALTER TABLE `aparat_stok`
    MODIFY `sahip_tipi` ENUM('ekip','depo','saha','hurda','kayip','personel') NOT NULL;

ALTER TABLE `aparat_hareket`
    MODIFY `sahip_tipi` ENUM('ekip','depo','saha','hurda','kayip','personel') NOT NULL;

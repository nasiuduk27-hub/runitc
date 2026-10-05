-- =====================================================================
-- delete_test_2026_mysql.sql  (DB itc_itconenew, koneksi mysql)
-- Backup: aaPanel full kedua DB. REVIEW manual sebelum run.
-- FASE 1: reversal 32 pembayaran test di pinjaman ASLI (id 2..34 kecuali 1,17,35)
-- FASE 2: hapus baris legacy test mysql (jalankan SETELAH delete_test_2026_run.sql)
-- Idempoten: guarded (payno+paidst / EXISTS trncd='20').
-- =====================================================================

-- ===================== FASE 1 =====================

-- pay#2 | member 757 | loan 1592 | dloan 10987 | A=441666 P=416666 T=PMT-26I-0121
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=10987 AND payno='PMT-26I-0121' AND paidst=1;
UPDATE icu_mloan SET paid=paid-441666, lupd=NOW() WHERE rec_id=1592 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0121' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1592 AND paidst=0) WHERE rec_id=1592;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=757 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0121' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0121' AND trncd='20';

-- pay#3 | member 755 | loan 1600 | dloan 11106 | A=441666 P=416666 T=PMT-26I-0122
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11106 AND payno='PMT-26I-0122' AND paidst=1;
UPDATE icu_mloan SET paid=paid-441666, lupd=NOW() WHERE rec_id=1600 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0122' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1600 AND paidst=0) WHERE rec_id=1600;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=755 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0122' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0122' AND trncd='20';

-- pay#4 | member 750 | loan 1601 | dloan 11119 | A=565333 P=533333 T=PMT-26I-0123
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11119 AND payno='PMT-26I-0123' AND paidst=1;
UPDATE icu_mloan SET paid=paid-565333, lupd=NOW() WHERE rec_id=1601 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0123' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1601 AND paidst=0) WHERE rec_id=1601;
UPDATE icu_member SET outstanding=outstanding+533333 WHERE rec_id=750 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0123' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0123' AND trncd='20';

-- pay#5 | member 748 | loan 1603 | dloan 11156 | A=466666 P=416666 T=PMT-26I-0124
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11156 AND payno='PMT-26I-0124' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1603 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0124' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1603 AND paidst=0) WHERE rec_id=1603;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=748 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0124' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0124' AND trncd='20';

-- pay#6 | member 748 | loan 1604 | dloan 11175 | A=700000 P=625000 T=PMT-26I-0125
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11175 AND payno='PMT-26I-0125' AND paidst=1;
UPDATE icu_mloan SET paid=paid-700000, lupd=NOW() WHERE rec_id=1604 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0125' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1604 AND paidst=0) WHERE rec_id=1604;
UPDATE icu_member SET outstanding=outstanding+625000 WHERE rec_id=748 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0125' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0125' AND trncd='20';

-- pay#7 | member 748 | loan 1605 | dloan 11197 | A=302777 P=277777 T=PMT-26I-0126
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11197 AND payno='PMT-26I-0126' AND paidst=1;
UPDATE icu_mloan SET paid=paid-302777, lupd=NOW() WHERE rec_id=1605 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0126' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1605 AND paidst=0) WHERE rec_id=1605;
UPDATE icu_member SET outstanding=outstanding+277777 WHERE rec_id=748 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0126' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0126' AND trncd='20';

-- pay#8 | member 748 | loan 1606 | dloan 11210 | A=302777 P=277777 T=PMT-26I-0127
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11210 AND payno='PMT-26I-0127' AND paidst=1;
UPDATE icu_mloan SET paid=paid-302777, lupd=NOW() WHERE rec_id=1606 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0127' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1606 AND paidst=0) WHERE rec_id=1606;
UPDATE icu_member SET outstanding=outstanding+277777 WHERE rec_id=748 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0127' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0127' AND trncd='20';

-- pay#9 | member 56 | loan 1633 | dloan 11601 | A=1400000 P=1250000 T=PMT-26I-0128
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11601 AND payno='PMT-26I-0128' AND paidst=1;
UPDATE icu_mloan SET paid=paid-1400000, lupd=NOW() WHERE rec_id=1633 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0128' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1633 AND paidst=0) WHERE rec_id=1633;
UPDATE icu_member SET outstanding=outstanding+1250000 WHERE rec_id=56 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0128' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0128' AND trncd='20';

-- pay#10 | member 56 | loan 1634 | dloan 11629 | A=471333 P=420833 T=PMT-26I-0129
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11629 AND payno='PMT-26I-0129' AND paidst=1;
UPDATE icu_mloan SET paid=paid-471333, lupd=NOW() WHERE rec_id=1634 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0129' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1634 AND paidst=0) WHERE rec_id=1634;
UPDATE icu_member SET outstanding=outstanding+420833 WHERE rec_id=56 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0129' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0129' AND trncd='20';

-- pay#11 | member 61 | loan 1640 | dloan 11730 | A=1400000 P=1250000 T=PMT-26I-0130
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11730 AND payno='PMT-26I-0130' AND paidst=1;
UPDATE icu_mloan SET paid=paid-1400000, lupd=NOW() WHERE rec_id=1640 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0130' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1640 AND paidst=0) WHERE rec_id=1640;
UPDATE icu_member SET outstanding=outstanding+1250000 WHERE rec_id=61 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0130' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0130' AND trncd='20';

-- pay#12 | member 67 | loan 1665 | dloan 12079 | A=466666 P=416666 T=PMT-26I-0131
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12079 AND payno='PMT-26I-0131' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1665 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0131' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1665 AND paidst=0) WHERE rec_id=1665;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=67 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0131' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0131' AND trncd='20';

-- pay#13 | member 79 | loan 1667 | dloan 12122 | A=466666 P=416666 T=PMT-26I-0132
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12122 AND payno='PMT-26I-0132' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1667 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0132' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1667 AND paidst=0) WHERE rec_id=1667;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=79 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0132' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0132' AND trncd='20';

-- pay#14 | member 62 | loan 1675 | dloan 12226 | A=466666 P=416666 T=PMT-26I-0133
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12226 AND payno='PMT-26I-0133' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1675 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0133' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1675 AND paidst=0) WHERE rec_id=1675;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=62 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0133' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0133' AND trncd='20';

-- pay#15 | member 62 | loan 1676 | dloan 12247 | A=883333 P=833333 T=PMT-26I-0134  (pinjaman jadi LUNAS)
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12247 AND payno='PMT-26I-0134' AND paidst=1;
UPDATE icu_mloan SET paid=paid-883333, lupd=NOW() WHERE rec_id=1676 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0134' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1676 AND paidst=0) WHERE rec_id=1676;
UPDATE icu_member SET outstanding=outstanding+833333 WHERE rec_id=62 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0134' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0134' AND trncd='20';

-- pay#16 | member 76 | loan 1678 | dloan 12271 | A=466666 P=416666 T=PMT-26I-0135
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12271 AND payno='PMT-26I-0135' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1678 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0135' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1678 AND paidst=0) WHERE rec_id=1678;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=76 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0135' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0135' AND trncd='20';

-- pay#18 | member 108 | loan 1584 | dloan 10916 | A=441666 P=416666 T=PMT-26I-0137  (pinjaman jadi LUNAS)
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=10916 AND payno='PMT-26I-0137' AND paidst=1;
UPDATE icu_mloan SET paid=paid-441666, lupd=NOW() WHERE rec_id=1584 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0137' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1584 AND paidst=0) WHERE rec_id=1584;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=108 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0137' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0137' AND trncd='20';

-- pay#19 | member 757 | loan 1592 | dloan 10986 | A=441666 P=416666 T=PMT-26I-0138
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=10986 AND payno='PMT-26I-0138' AND paidst=1;
UPDATE icu_mloan SET paid=paid-441666, lupd=NOW() WHERE rec_id=1592 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0138' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1592 AND paidst=0) WHERE rec_id=1592;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=757 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0138' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0138' AND trncd='20';

-- pay#20 | member 755 | loan 1600 | dloan 11105 | A=441666 P=416666 T=PMT-26I-0139
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11105 AND payno='PMT-26I-0139' AND paidst=1;
UPDATE icu_mloan SET paid=paid-441666, lupd=NOW() WHERE rec_id=1600 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0139' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1600 AND paidst=0) WHERE rec_id=1600;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=755 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0139' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0139' AND trncd='20';

-- pay#21 | member 750 | loan 1601 | dloan 11118 | A=565333 P=533333 T=PMT-26I-0140
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11118 AND payno='PMT-26I-0140' AND paidst=1;
UPDATE icu_mloan SET paid=paid-565333, lupd=NOW() WHERE rec_id=1601 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0140' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1601 AND paidst=0) WHERE rec_id=1601;
UPDATE icu_member SET outstanding=outstanding+533333 WHERE rec_id=750 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0140' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0140' AND trncd='20';

-- pay#22 | member 748 | loan 1603 | dloan 11155 | A=466666 P=416666 T=PMT-26I-0141
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11155 AND payno='PMT-26I-0141' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1603 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0141' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1603 AND paidst=0) WHERE rec_id=1603;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=748 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0141' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0141' AND trncd='20';

-- pay#23 | member 748 | loan 1604 | dloan 11174 | A=700000 P=625000 T=PMT-26I-0142
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11174 AND payno='PMT-26I-0142' AND paidst=1;
UPDATE icu_mloan SET paid=paid-700000, lupd=NOW() WHERE rec_id=1604 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0142' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1604 AND paidst=0) WHERE rec_id=1604;
UPDATE icu_member SET outstanding=outstanding+625000 WHERE rec_id=748 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0142' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0142' AND trncd='20';

-- pay#24 | member 748 | loan 1605 | dloan 11196 | A=302777 P=277777 T=PMT-26I-0143
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11196 AND payno='PMT-26I-0143' AND paidst=1;
UPDATE icu_mloan SET paid=paid-302777, lupd=NOW() WHERE rec_id=1605 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0143' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1605 AND paidst=0) WHERE rec_id=1605;
UPDATE icu_member SET outstanding=outstanding+277777 WHERE rec_id=748 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0143' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0143' AND trncd='20';

-- pay#25 | member 748 | loan 1606 | dloan 11209 | A=302777 P=277777 T=PMT-26I-0144
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11209 AND payno='PMT-26I-0144' AND paidst=1;
UPDATE icu_mloan SET paid=paid-302777, lupd=NOW() WHERE rec_id=1606 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0144' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1606 AND paidst=0) WHERE rec_id=1606;
UPDATE icu_member SET outstanding=outstanding+277777 WHERE rec_id=748 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0144' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0144' AND trncd='20';

-- pay#26 | member 63 | loan 1626 | dloan 11446 | A=1060000 P=1000000 T=PMT-26I-0145  (pinjaman jadi LUNAS)
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11446 AND payno='PMT-26I-0145' AND paidst=1;
UPDATE icu_mloan SET paid=paid-1060000, lupd=NOW() WHERE rec_id=1626 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0145' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1626 AND paidst=0) WHERE rec_id=1626;
UPDATE icu_member SET outstanding=outstanding+1000000 WHERE rec_id=63 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0145' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0145' AND trncd='20';

-- pay#27 | member 56 | loan 1633 | dloan 11600 | A=1400000 P=1250000 T=PMT-26I-0146
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11600 AND payno='PMT-26I-0146' AND paidst=1;
UPDATE icu_mloan SET paid=paid-1400000, lupd=NOW() WHERE rec_id=1633 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0146' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1633 AND paidst=0) WHERE rec_id=1633;
UPDATE icu_member SET outstanding=outstanding+1250000 WHERE rec_id=56 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0146' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0146' AND trncd='20';

-- pay#28 | member 56 | loan 1634 | dloan 11628 | A=471333 P=420833 T=PMT-26I-0147
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11628 AND payno='PMT-26I-0147' AND paidst=1;
UPDATE icu_mloan SET paid=paid-471333, lupd=NOW() WHERE rec_id=1634 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0147' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1634 AND paidst=0) WHERE rec_id=1634;
UPDATE icu_member SET outstanding=outstanding+420833 WHERE rec_id=56 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0147' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0147' AND trncd='20';

-- pay#29 | member 61 | loan 1640 | dloan 11729 | A=1400000 P=1250000 T=PMT-26I-0148
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=11729 AND payno='PMT-26I-0148' AND paidst=1;
UPDATE icu_mloan SET paid=paid-1400000, lupd=NOW() WHERE rec_id=1640 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0148' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1640 AND paidst=0) WHERE rec_id=1640;
UPDATE icu_member SET outstanding=outstanding+1250000 WHERE rec_id=61 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0148' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0148' AND trncd='20';

-- pay#30 | member 67 | loan 1665 | dloan 12078 | A=466666 P=416666 T=PMT-26I-0149
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12078 AND payno='PMT-26I-0149' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1665 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0149' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1665 AND paidst=0) WHERE rec_id=1665;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=67 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0149' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0149' AND trncd='20';

-- pay#31 | member 79 | loan 1667 | dloan 12121 | A=466666 P=416666 T=PMT-26I-0150
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12121 AND payno='PMT-26I-0150' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1667 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0150' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1667 AND paidst=0) WHERE rec_id=1667;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=79 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0150' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0150' AND trncd='20';

-- pay#32 | member 62 | loan 1675 | dloan 12225 | A=466666 P=416666 T=PMT-26I-0151
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12225 AND payno='PMT-26I-0151' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1675 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0151' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1675 AND paidst=0) WHERE rec_id=1675;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=62 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0151' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0151' AND trncd='20';

-- pay#33 | member 62 | loan 1676 | dloan 12246 | A=883333 P=833333 T=PMT-26I-0152
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12246 AND payno='PMT-26I-0152' AND paidst=1;
UPDATE icu_mloan SET paid=paid-883333, lupd=NOW() WHERE rec_id=1676 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0152' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1676 AND paidst=0) WHERE rec_id=1676;
UPDATE icu_member SET outstanding=outstanding+833333 WHERE rec_id=62 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0152' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0152' AND trncd='20';

-- pay#34 | member 76 | loan 1678 | dloan 12270 | A=466666 P=416666 T=PMT-26I-0153
UPDATE icu_dloan SET paidst=0, payno='' WHERE rec_id=12270 AND payno='PMT-26I-0153' AND paidst=1;
UPDATE icu_mloan SET paid=paid-466666, lupd=NOW() WHERE rec_id=1678 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0153' AND trncd='20');
UPDATE icu_mloan SET statrec=(SELECT CASE WHEN COUNT(*)>0 THEN 4 ELSE 5 END FROM icu_dloan WHERE mst_rec_id=1678 AND paidst=0) WHERE rec_id=1678;
UPDATE icu_member SET outstanding=outstanding+416666 WHERE rec_id=76 AND EXISTS (SELECT 1 FROM icu_transaction WHERE trnno='PMT-26I-0153' AND trncd='20');
DELETE FROM icu_transaction WHERE trnno='PMT-26I-0153' AND trncd='20';

-- ===== VERIFIKASI FASE 1 =====
SELECT 'fase1_dloan_masih_paid' AS cek, COUNT(*) AS n FROM icu_dloan
 WHERE rec_id IN (10987,11106,11119,11156,11175,11197,11210,11601,11629,11730,12079,12122,12226,12247,12271,10916,10986,11105,11118,11155,11174,11196,11209,11446,11600,11628,11729,12078,12121,12225,12246,12270)
   AND payno LIKE 'PMT-26%';   -- HARUS 0
SELECT 'fase1_tx_sisa' AS cek, COUNT(*) AS n FROM icu_transaction
 WHERE trnno IN ('PMT-26I-0121','PMT-26I-0122','PMT-26I-0123','PMT-26I-0124','PMT-26I-0125','PMT-26I-0126','PMT-26I-0127','PMT-26I-0128','PMT-26I-0129','PMT-26I-0130','PMT-26I-0131','PMT-26I-0132','PMT-26I-0133','PMT-26I-0134','PMT-26I-0135','PMT-26I-0137','PMT-26I-0138','PMT-26I-0139','PMT-26I-0140','PMT-26I-0141','PMT-26I-0142','PMT-26I-0143','PMT-26I-0144','PMT-26I-0145','PMT-26I-0146','PMT-26I-0147','PMT-26I-0148','PMT-26I-0149','PMT-26I-0150','PMT-26I-0151','PMT-26I-0152','PMT-26I-0153')
   AND trncd='20';             -- HARUS 0

-- ===================== FASE 2 (JALANKAN SETELAH delete_test_2026_run.sql) =====================

-- 10. Hapus sisa ledger 2026 (129 baris). REVIEW: termasuk simpanan 114 anggota NYATA.
DELETE FROM icu_transaction
 WHERE YEAR(entdt)=2026 AND trncd IN ('18','19','20')
   AND trnno NOT IN ('PMT-26I-0121','PMT-26I-0122','PMT-26I-0123','PMT-26I-0124','PMT-26I-0125','PMT-26I-0126','PMT-26I-0127','PMT-26I-0128','PMT-26I-0129','PMT-26I-0130','PMT-26I-0131','PMT-26I-0132','PMT-26I-0133','PMT-26I-0134','PMT-26I-0135','PMT-26I-0137','PMT-26I-0138','PMT-26I-0139','PMT-26I-0140','PMT-26I-0141','PMT-26I-0142','PMT-26I-0143','PMT-26I-0144','PMT-26I-0145','PMT-26I-0146','PMT-26I-0147','PMT-26I-0148','PMT-26I-0149','PMT-26I-0150','PMT-26I-0151','PMT-26I-0152','PMT-26I-0153');

-- 11. Bank trx: hanya 2 baris refinancing TEST.
DELETE FROM icu_bank_trx WHERE trnno IN ('RCV-26I-0754','RCV-26I-0755');
-- REVIEW: 3 baris collective (menunggu keputusan user) — JANGAN hapus dulu:
-- DELETE FROM icu_bank_trx WHERE trnno IN ('RCV-26I-0751','RCV-26I-0752','RCV-26I-0753');

-- 12. Jadwal pinjaman TEST (parent-driven via mst_rec_id)
DELETE FROM icu_dloan WHERE mst_rec_id IN (1694,1699);

-- 13. Tagihan PMT hasil Monthly Processing 2026 (8 baris). REVIEW.
DELETE FROM icu_mtrx2hrd WHERE YEAR(entdt)=2026;

-- 14. Header pinjaman TEST
DELETE FROM icu_mloan WHERE rec_id IN (1694,1699);

-- 15. Anggota TEST (paling terakhir)
DELETE FROM icu_member WHERE icuno IN ('CU-0077','CU-0078','CU-0079','CU-0080');

-- ===== VERIFIKASI AKHIR (mysql) =====
SELECT 'icu_transaction_2026_18_19_20' AS cek, COUNT(*) AS n FROM icu_transaction WHERE YEAR(entdt)=2026 AND trncd IN ('18','19','20');  -- 0
SELECT 'icu_dloan_paid_PMT26' AS cek, COUNT(*) AS n FROM icu_dloan WHERE paidst=1 AND payno LIKE 'PMT-26%';                              -- 0
SELECT 'icu_dloan_1694_1699' AS cek, COUNT(*) AS n FROM icu_dloan WHERE mst_rec_id IN (1694,1699);                                      -- 0
SELECT 'icu_mloan_1694_1699' AS cek, COUNT(*) AS n FROM icu_mloan WHERE rec_id IN (1694,1699);                                          -- 0
SELECT 'icu_member_test' AS cek, COUNT(*) AS n FROM icu_member WHERE icuno IN ('CU-0077','CU-0078','CU-0079','CU-0080');                -- 0
SELECT 'icu_mtrx2hrd_2026' AS cek, COUNT(*) AS n FROM icu_mtrx2hrd WHERE YEAR(entdt)=2026;                                              -- 0
SELECT 'icu_bank_trx_0754_0755' AS cek, COUNT(*) AS n FROM icu_bank_trx WHERE trnno IN ('RCV-26I-0754','RCV-26I-0755');                -- 0
SELECT 'icu_bank_trx_collective_sisa' AS cek, COUNT(*) AS n FROM icu_bank_trx WHERE trnno IN ('RCV-26I-0751','RCV-26I-0752','RCV-26I-0753'); -- 3 (dikomentar)

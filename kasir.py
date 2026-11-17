"""
Sistem Kasir Sederhana berbasis OOP (Python)
UTS Pemrograman Berorientasi Objek - 3B Ilmu Komputer

Konsep OOP yang dipakai:
- Class & Object      : Produk, ProdukDiskon, Transaksi, SistemKasir
- Constructor         : __init__() di setiap class
- Attribute & Method  : lihat tiap class
- Encapsulation       : atribut private (__nama, __harga, __stok, dst.)
- Property / Setter   : @property dan @nama.setter, dst. dengan validasi
- Class method        : Produk.dari_string(), Transaksi.buat_baru()
- Static method       : Produk.format_rupiah(), Transaksi.hitung_kembalian(),
                        SistemKasir.baca_input_item(), SistemKasir.baca_angka(),
                        SistemKasir.bantuan(), SistemKasir.menu_lanjut()
- Inheritance         : ProdukDiskon mewarisi Produk (bonus)
- Polymorphism        : harga_akhir() di-override oleh ProdukDiskon (bonus)
"""

import difflib
from datetime import datetime


class Produk:
    """Representasi satu produk yang dijual di toko."""

    jumlah_produk = 0  # class attribute

    def __init__(self, kode, nama, harga, stok):
        self.__kode = kode        # private
        self.__nama = ""
        self.__harga = 0
        self.__stok = 0
        # pakai setter supaya validasi ikut jalan
        self.nama = nama
        self.harga = harga
        self.stok = stok
        Produk.jumlah_produk += 1

    # ---------- property & setter (encapsulation) ----------
    @property
    def kode(self):
        return self.__kode

    @property
    def nama(self):
        return self.__nama

    @nama.setter
    def nama(self, nilai):
        if not nilai.strip():
            raise ValueError("Nama produk tidak boleh kosong.")
        self.__nama = nilai.strip()

    @property
    def harga(self):
        return self.__harga

    @harga.setter
    def harga(self, nilai):
        if nilai <= 0:
            raise ValueError("Harga harus lebih dari 0.")
        self.__harga = nilai

    @property
    def stok(self):
        return self.__stok

    @stok.setter
    def stok(self, nilai):
        if nilai < 0:
            raise ValueError("Stok tidak boleh negatif.")
        self.__stok = nilai

    # ---------- method ----------
    def harga_akhir(self):
        """Harga jual per unit. Bisa di-override oleh subclass."""
        return self.__harga

    def kurangi_stok(self, jumlah):
        if jumlah > self.__stok:
            raise ValueError(f"Stok {self.__nama} tidak cukup (sisa {self.__stok}).")
        self.__stok -= jumlah

    def info(self):
        return (f"{self.__kode:<6}{self.__nama:<20}"
                f"{Produk.format_rupiah(self.harga_akhir()):>12}{self.__stok:>8}")

    # ---------- class method ----------
    @classmethod
    def dari_string(cls, teks):
        """Buat produk dari string 'kode;nama;harga;stok'."""
        kode, nama, harga, stok = teks.split(";")
        return cls(kode, nama, int(harga), int(stok))

    @classmethod
    def total_produk(cls):
        return cls.jumlah_produk

    # ---------- static method ----------
    @staticmethod
    def format_rupiah(angka):
        return "Rp" + f"{angka:,.0f}".replace(",", ".")


class ProdukDiskon(Produk):
    """Produk dengan diskon persen (inheritance + polymorphism)."""

    def __init__(self, kode, nama, harga, stok, diskon_persen):
        super().__init__(kode, nama, harga, stok)
        self.__diskon = 0
        self.diskon = diskon_persen

    @property
    def diskon(self):
        return self.__diskon

    @diskon.setter
    def diskon(self, nilai):
        if not 0 <= nilai <= 100:
            raise ValueError("Diskon harus antara 0 - 100 persen.")
        self.__diskon = nilai

    def harga_akhir(self):  # override
        return int(self.harga * (100 - self.__diskon) / 100)

    def info(self):
        return super().info() + f"  (diskon {self.__diskon}%)"


class Transaksi:
    """Satu transaksi belanja: keranjang, total, pembayaran, struk."""

    __jumlah = 0  # private class attribute untuk nomor transaksi

    def __init__(self, nomor, nama_kasir):
        self.__nomor = nomor
        self.__kasir = nama_kasir
        self.__waktu = datetime.now()
        self.__item = {}  # kode -> [produk, jumlah]

    @classmethod
    def buat_baru(cls, nama_kasir):
        cls.__jumlah += 1
        return cls(f"TRX{cls.__jumlah:03d}", nama_kasir)

    @classmethod
    def total_transaksi(cls):
        return cls.__jumlah

    @staticmethod
    def hitung_kembalian(bayar, total):
        return bayar - total

    @property
    def nomor(self):
        return self.__nomor

    @property
    def total(self):
        return sum(p.harga_akhir() * q for p, q in self.__item.values())

    @property
    def jumlah_barang(self):
        return sum(q for _, q in self.__item.values())

    @property
    def kosong(self):
        return len(self.__item) == 0

    def tambah_item(self, produk, jumlah):
        if jumlah <= 0:
            raise ValueError("Jumlah harus lebih dari 0. Contoh: P001 2")
        sudah_di_keranjang = self.__item[produk.kode][1] if produk.kode in self.__item else 0
        sisa = produk.stok - sudah_di_keranjang
        if jumlah > sisa:
            raise ValueError(f"Stok {produk.nama} tidak cukup. Yang masih bisa dibeli: {sisa}. "
                             f"Kurangi jumlahnya.")
        if produk.kode in self.__item:
            self.__item[produk.kode][1] += jumlah
        else:
            self.__item[produk.kode] = [produk, jumlah]

    def hapus_item(self, kode):
        """Buang satu produk dari keranjang (untuk koreksi salah input)."""
        if kode not in self.__item:
            raise ValueError(f"{kode} tidak ada di keranjang. Ketik 'lihat' untuk cek isi keranjang.")
        return self.__item.pop(kode)[0].nama

    def lihat_keranjang(self):
        if self.kosong:
            print("  (keranjang kosong)")
            return
        for produk, qty in self.__item.values():
            subtotal = produk.harga_akhir() * qty
            print(f"  {produk.nama:<20}{qty:>3} x {Produk.format_rupiah(produk.harga_akhir()):>10}"
                  f" = {Produk.format_rupiah(subtotal):>12}")
        label = f"TOTAL ({self.jumlah_barang} barang)"
        print(f"  {label:<36}{Produk.format_rupiah(self.total):>12}")

    def bayar(self, uang):
        """Proses pembayaran, kurangi stok, return kembalian."""
        if self.kosong:
            raise ValueError("Keranjang masih kosong. Tambahkan produk dulu, contoh: P001 2")
        if uang < self.total:
            raise ValueError(f"Uang kurang {Produk.format_rupiah(self.total - uang)}. "
                             f"Total belanja {Produk.format_rupiah(self.total)}. "
                             f"Masukkan nominal yang cukup atau ketik 'batal'.")
        for produk, qty in self.__item.values():
            produk.kurangi_stok(qty)
        return Transaksi.hitung_kembalian(uang, self.total)

    def cetak_struk(self, uang, kembalian, metode="Tunai"):
        print("\n" + "=" * 46)
        print("            STRUK PEMBELIAN")
        print("=" * 46)
        print(f"No     : {self.__nomor}")
        print(f"Waktu  : {self.__waktu:%d-%m-%Y %H:%M:%S}")
        print(f"Kasir  : {self.__kasir}")
        print(f"Metode : {metode}")
        print("-" * 46)
        self.lihat_keranjang()
        print("-" * 46)
        print(f"  {'Bayar':<36}{Produk.format_rupiah(uang):>12}")
        print(f"  {'Kembali':<36}{Produk.format_rupiah(kembalian):>12}")
        print("=" * 46)
        print("        Terima kasih sudah berbelanja!")
        print("=" * 46 + "\n")

    def ringkas(self):
        return f"{self.__nomor} | {self.__waktu:%d-%m-%Y %H:%M} | {Produk.format_rupiah(self.total)}"


class SistemKasir:
    """Kelas utama yang menjalankan menu aplikasi."""

    def __init__(self, nama_kasir):
        self.nama_kasir = nama_kasir
        self.__produk = {}   # kode -> Produk
        self.__riwayat = []  # daftar Transaksi selesai
        self.__muat_data_awal()

    def __muat_data_awal(self):
        for teks in ("P001;Beras 5kg;65000;20",
                     "P002;Minyak Goreng 1L;18000;30",
                     "P003;Gula Pasir 1kg;16000;25"):
            p = Produk.dari_string(teks)
            self.__produk[p.kode] = p
        promo = ProdukDiskon("P004", "Mie Instan", 3500, 100, 10)
        self.__produk[promo.kode] = promo

    # ---------- fitur ----------
    def tampilkan_produk(self):
        print("\n" + "-" * 56)
        print(f"{'Kode':<6}{'Nama':<20}{'Harga':>12}{'Stok':>8}")
        print("-" * 56)
        for p in self.__produk.values():
            print(p.info())
        print("-" * 56)
        print(f"Total jenis produk: {Produk.total_produk()}")

    @staticmethod
    def baca_angka(label, contoh):
        """Minta input angka sampai valid. Return None jika user ketik 'batal'."""
        while True:
            mentah = input(f"{label}: ").strip().lower()
            if mentah == "batal":
                return None
            try:
                return int(mentah.replace("rp", "").replace(".", "").replace(",", ""))
            except ValueError:
                print(f"  ! Harus berupa angka tanpa huruf, contoh: {contoh} (ketik 'batal' untuk membatalkan)")

    @staticmethod
    def bantuan():
        print("""
=== PANDUAN ===
Menu utama : ketik angka 1-4 (0 = keluar), atau langsung kode produk, contoh: P001 2
Saat transaksi:
  1. Ketik kode produk + jumlah, contoh: P001 2 (jumlah dikosongkan = 1)
     Banyak barang sekaligus: P001 3 P002 4 P004 1
  2. Setelah barang masuk, pilih langkah berikutnya:
       [1] Tambah barang lain   [2] Bayar   [3] Hapus barang   [4] Batalkan
  3. Pilih metode bayar: [1] Tunai atau [2] QRIS
  Perintah cepat: lihat | hapus P001 | selesai | batal
Salah input? Tenang, program akan kasih petunjuk dan kamu bisa mengulang.""")

    @staticmethod
    def menu_lanjut():
        print("\nMau ngapain selanjutnya?")
        print("  [1] Tambah barang lain")
        print("  [2] Bayar")
        print("  [3] Hapus barang")
        print("  [4] Batalkan transaksi")

    @staticmethod
    def baca_input_item(teks):
        """
        Baca input satu atau banyak produk sekaligus.
        Contoh: 'P001 2'  |  'P001 3 P002 4 P005 1'  |  'P001 3, P002 4'
        Jumlah dikosongkan = 1. Return list of (kode, jumlah).
        """
        daftar = []  # [kode, jumlah, sudah_ada_jumlah]
        for token in teks.replace(",", " ").replace(";", " ").split():
            if token.isdigit():
                if not daftar or daftar[-1][2]:
                    raise ValueError(f"Angka {token} tidak punya kode produk. "
                                     f"Format: KODE JUMLAH, contoh: P001 3 P002 4")
                daftar[-1][1] = int(token)
                daftar[-1][2] = True
            else:
                daftar.append([token, 1, False])
        if not daftar:
            raise ValueError("Belum ada input. Contoh: P001 3 P002 4")
        return [(kode, jumlah) for kode, jumlah, _ in daftar]

    def __saran_kode(self, kode):
        mirip = difflib.get_close_matches(kode, list(self.__produk), n=1, cutoff=0.5)
        return f" Maksud kamu {mirip[0]}?" if mirip else ""

    def tambah_produk(self):
        print("\n--- Tambah Produk (ketik 'batal' untuk membatalkan) ---")
        kode = input("Kode produk (contoh: P005): ").strip().upper()
        if kode == "BATAL":
            print("Dibatalkan.")
            return
        if not kode or " " in kode:
            print("  ! Kode tidak boleh kosong atau mengandung spasi. Ulangi dari menu 2.")
            return
        if kode in self.__produk:
            print(f"  ! Kode {kode} sudah dipakai. Gunakan kode lain, misalnya P005.")
            return
        nama = input("Nama produk: ").strip()
        if nama.lower() == "batal":
            print("Dibatalkan.")
            return
        harga = self.baca_angka("Harga", "15000")
        if harga is None:
            print("Dibatalkan.")
            return
        stok = self.baca_angka("Stok", "50")
        if stok is None:
            print("Dibatalkan.")
            return
        diskon = self.baca_angka("Diskon % (0 = tanpa diskon)", "10")
        if diskon is None:
            print("Dibatalkan.")
            return
        if not 0 <= diskon <= 100:
            print("  ! Diskon harus antara 0 - 100. Ulangi dari menu 2.")
            return
        try:
            if diskon > 0:
                produk = ProdukDiskon(kode, nama, harga, stok, diskon)
            else:
                produk = Produk(kode, nama, harga, stok)
        except ValueError as e:
            print(f"  ! Gagal: {e} Ulangi dari menu 2 dengan data yang benar.")
            return
        self.__produk[kode] = produk
        print(f"Produk '{produk.nama}' berhasil ditambahkan.")

    def transaksi_baru(self, input_awal=None):
        trx = Transaksi.buat_baru(self.nama_kasir)
        print(f"\n=== Transaksi {trx.nomor} ===")
        self.tampilkan_produk()
        print("\nKetik kode produk + jumlah, contoh: P001 2 (jumlah dikosongkan = 1)")
        print("Banyak barang sekaligus juga bisa, contoh: P001 3 P002 4 P004 1")

        teks = input_awal
        while True:
            if teks is None:
                if trx.kosong:
                    teks = input("\nKode produk [jumlah] ('batal' = batalkan, 'bantuan' = panduan): ")
                else:
                    self.menu_lanjut()
                    teks = input("Pilih 1-4, atau langsung ketik kode produk: ")
            perintah = teks.strip().upper()
            teks = None

            if not trx.kosong and perintah in ("1", "2", "3", "4"):
                if perintah == "1":
                    teks = input("Kode produk [jumlah], boleh banyak sekaligus: ")
                    continue
                if perintah == "2":
                    break
                if perintah == "3":
                    perintah = "HAPUS " + input("Kode produk yang mau dihapus: ").strip().upper()
                if perintah == "4":
                    perintah = "BATAL"

            if not perintah:
                print("  ! Belum ada input. Ketik kode produk (contoh: P001 2) atau 'bantuan'.")
                continue
            if perintah == "BATAL":
                print("Transaksi dibatalkan.")
                return
            if perintah in ("BANTUAN", "?", "HELP"):
                self.bantuan()
                continue
            if perintah == "LIHAT":
                trx.lihat_keranjang()
                continue
            if perintah in ("SELESAI", "BAYAR"):
                if trx.kosong:
                    print("  ! Keranjang masih kosong. Tambahkan produk dulu, contoh: P001 2")
                    continue
                break
            bagian = perintah.split()
            if bagian[0] == "HAPUS":
                try:
                    if len(bagian) != 2:
                        raise ValueError("Format: hapus KODE, contoh: hapus P001")
                    nama = trx.hapus_item(bagian[1])
                    print(f"- {nama} dibuang dari keranjang")
                    if not trx.kosong:
                        trx.lihat_keranjang()
                except ValueError as e:
                    print(f"  ! {e}")
                continue
            try:
                daftar = SistemKasir.baca_input_item(perintah)
            except ValueError as e:
                print(f"  ! {e}")
                continue
            masuk = 0
            for kode, jumlah in daftar:
                produk = self.__produk.get(kode)
                if produk is None:
                    print(f"  ! {kode} dilewati: kode tidak ditemukan.{self.__saran_kode(kode)}")
                    continue
                try:
                    trx.tambah_item(produk, jumlah)
                    print(f"+ {jumlah} x {produk.nama} masuk keranjang")
                    masuk += 1
                except ValueError as e:
                    print(f"  ! {kode} dilewati: {e}")
            if masuk:
                trx.lihat_keranjang()

        # ---------- pembayaran ----------
        print(f"\nTotal bayar: {Produk.format_rupiah(trx.total)}")
        print("Metode pembayaran:")
        print("  [1] Tunai")
        print("  [2] QRIS / non-tunai (nominal pas)")
        while True:
            metode = input("Pilih metode (1/2, 'batal' = batalkan): ").strip().lower()
            if metode in ("1", "2", "batal"):
                break
            print("  ! Ketik 1 untuk Tunai, 2 untuk QRIS, atau 'batal'.")
        if metode == "batal":
            print("Transaksi dibatalkan.")
            return

        if metode == "2":
            nama_metode = "QRIS"
            uang = trx.total
            kembalian = trx.bayar(uang)
        else:
            nama_metode = "Tunai"
            while True:
                mentah = input("Uang dibayar ('pas' = uang pas, 'batal' = batalkan): ").strip().lower()
                if mentah == "batal":
                    print("Transaksi dibatalkan.")
                    return
                if mentah == "pas":
                    uang = trx.total
                else:
                    try:
                        uang = int(mentah.replace("rp", "").replace(".", "").replace(",", ""))
                    except ValueError:
                        print("  ! Masukkan angka saja, contoh: 150000 (atau ketik 'pas')")
                        continue
                try:
                    kembalian = trx.bayar(uang)
                    break
                except ValueError as e:
                    print(f"  ! {e}")

        trx.cetak_struk(uang, kembalian, nama_metode)
        self.__riwayat.append(trx)
        lagi = input("Transaksi berikutnya? (y = ya, Enter = kembali ke menu): ").strip().lower()
        if lagi == "y":
            self.transaksi_baru()

    def lihat_riwayat(self):
        print("\n--- Riwayat Transaksi ---")
        if not self.__riwayat:
            print("Belum ada transaksi.")
            return
        for trx in self.__riwayat:
            print(trx.ringkas())
        omzet = sum(t.total for t in self.__riwayat)
        print(f"Jumlah transaksi : {Transaksi.total_transaksi()}")
        print(f"Total pemasukan  : {Produk.format_rupiah(omzet)}")

    def jalankan(self):
        menu = {"1": self.tampilkan_produk,
                "2": self.tambah_produk,
                "3": self.transaksi_baru,
                "4": self.lihat_riwayat}
        while True:
            print("\n===== SISTEM KASIR =====")
            print("1. Lihat produk")
            print("2. Tambah produk")
            print("3. Transaksi baru")
            print("4. Riwayat transaksi")
            print("0. Keluar")
            print("Tips: ketik kode produk (contoh: P001 2) untuk belanja cepat, 'bantuan' untuk panduan")
            pilihan = input("Pilih menu: ").strip()
            kata = pilihan.upper().split()
            if pilihan == "0":
                print("Sampai jumpa!")
                break
            aksi = menu.get(pilihan)
            if aksi:
                aksi()
            elif not pilihan:
                print("  ! Belum ada input. Ketik angka menu 0-4 atau kode produk, contoh: P001")
            elif pilihan.upper() in ("BANTUAN", "?", "HELP"):
                self.bantuan()
            elif pilihan.upper() in ("SELESAI", "BAYAR", "BATAL", "LIHAT"):
                print("  ! Perintah itu hanya dipakai saat transaksi. "
                      "Mulai dengan mengetik kode produk (contoh: P001 2) atau pilih menu 3.")
            elif kata[0] in self.__produk:
                self.transaksi_baru(pilihan)  # belanja cepat
            else:
                print(f"  ! '{pilihan}' tidak dikenal.{self.__saran_kode(kata[0])} "
                      f"Ketik angka 0-4, kode produk (contoh: P001), atau 'bantuan'.")


if __name__ == "__main__":
    try:
        nama = input("Nama kasir: ").strip() or "Kasir"
        SistemKasir(nama).jalankan()
    except (KeyboardInterrupt, EOFError):
        print("\nProgram dihentikan. Sampai jumpa!")
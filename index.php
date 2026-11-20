<?php
// BPHOMES - Pencatatan Barang Masuk/Keluar & Laba (XAMPP lokal) - SEMUA DATA DI SQL
// Database "bphomes" & semua tabel dibuat otomatis saat pertama dibuka.
// Akun awal (otomatis dibuat di tabel `pengguna`): BPHOMES / BP2026  -> segera ganti di menu Pengaturan.
// Export Excel (.xlsx) memakai ekstensi zip PHP (aktif default di XAMPP).
session_start();
date_default_timezone_set('Asia/Jakarta');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n){ $n=(float)$n; $d=abs($n-round($n))>0.004?2:0; return 'Rp '.number_format($n,$d,',','.'); }
function tgl($d){ return date('d/m/Y', strtotime($d)); }
function redir($u){ header('Location: '.$u); exit; }
function flash($t,$m){ $_SESSION['m']=[$t,$m]; }

// ---------- Database ----------
try {
  $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $pdo->exec("CREATE DATABASE IF NOT EXISTS bphomes CHARACTER SET utf8mb4");
  $pdo->exec("USE bphomes");
  $pdo->exec("CREATE TABLE IF NOT EXISTS pengguna(
    id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(40) NOT NULL UNIQUE,
    nama VARCHAR(80) NOT NULL DEFAULT '', password_hash VARCHAR(255) NOT NULL,
    dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE IF NOT EXISTS satuan(
    id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(20) NOT NULL UNIQUE) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE IF NOT EXISTS pengaturan(
    k VARCHAR(40) PRIMARY KEY, v VARCHAR(200) NOT NULL DEFAULT '') ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE IF NOT EXISTS barang(
    id INT AUTO_INCREMENT PRIMARY KEY, kode VARCHAR(30) NOT NULL UNIQUE,
    nama VARCHAR(120) NOT NULL, satuan VARCHAR(20) NOT NULL DEFAULT 'pcs',
    stok INT NOT NULL DEFAULT 0, modal DECIMAL(15,4) NOT NULL DEFAULT 0) ENGINE=InnoDB");
  $pdo->exec("CREATE TABLE IF NOT EXISTS transaksi(
    id INT AUTO_INCREMENT PRIMARY KEY, barang_id INT NOT NULL,
    tipe ENUM('masuk','keluar') NOT NULL, tanggal DATE NOT NULL, qty INT NOT NULL,
    harga DECIMAL(15,2) NOT NULL, hpp DECIMAL(15,4) NOT NULL DEFAULT 0,
    catatan VARCHAR(200) NOT NULL DEFAULT '', dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(barang_id,tanggal), INDEX(tipe,tanggal),
    FOREIGN KEY(barang_id) REFERENCES barang(id) ON DELETE RESTRICT) ENGINE=InnoDB");

  // Data awal (hanya kalau tabel masih kosong)
  if ((int)$pdo->query("SELECT COUNT(*) FROM pengguna")->fetchColumn() === 0)
    $pdo->prepare("INSERT INTO pengguna(username,nama,password_hash) VALUES(?,?,?)")
        ->execute(['BPHOMES','Admin BPHOMES',password_hash('BP2026',PASSWORD_DEFAULT)]);
  if ((int)$pdo->query("SELECT COUNT(*) FROM satuan")->fetchColumn() === 0)
    foreach (['pcs','kg','gram','liter','dus','pak','lusin','botol','sachet','box'] as $s)
      $pdo->prepare("INSERT INTO satuan(nama) VALUES(?)")->execute([$s]);
  $pdo->exec("INSERT IGNORE INTO pengaturan(k,v) VALUES('nama_toko','BPHOMES')");
} catch (Exception $x) {
  die('<h3>Database tidak bisa diakses.</h3><p>Pastikan MySQL di XAMPP sudah di-Start (user root, tanpa password).</p><small>'.e($x->getMessage()).'</small>');
}
$toko = (string)$pdo->query("SELECT v FROM pengaturan WHERE k='nama_toko'")->fetchColumn() ?: 'BPHOMES';

// Hitung ulang stok, modal rata-rata (moving average) dan HPP tiap penjualan secara urut tanggal.
// Return false bila ada saat di mana stok akan minus.
function recalc(PDO $pdo, int $id): bool {
  $st = $pdo->prepare("SELECT id,tipe,qty,harga FROM transaksi WHERE barang_id=? ORDER BY tanggal, id");
  $st->execute([$id]);
  $up = $pdo->prepare("UPDATE transaksi SET hpp=? WHERE id=?");
  $stok = 0; $nilai = 0.0;
  foreach ($st->fetchAll() as $t) {
    if ($t['tipe'] === 'masuk') {
      $stok += $t['qty']; $nilai += $t['qty'] * (float)$t['harga'];
      $up->execute([$t['harga'], $t['id']]);
    } else {
      if ($t['qty'] > $stok) return false;
      $avg = $stok > 0 ? $nilai / $stok : 0;
      $up->execute([round($avg,4), $t['id']]);
      $nilai -= $t['qty'] * $avg; $stok -= $t['qty'];
      if ($stok === 0) $nilai = 0.0;
    }
  }
  $pdo->prepare("UPDATE barang SET stok=?, modal=? WHERE id=?")
      ->execute([$stok, $stok > 0 ? round($nilai / $stok, 4) : 0, $id]);
  return true;
}

// ---------- Export Excel (.xlsx sederhana, tanpa library) ----------
function xl_col(int $i): string { $s=''; $i++; while($i>0){ $m=($i-1)%26; $s=chr(65+$m).$s; $i=intdiv($i-1,26);} return $s; }
// Sel: skalar (teks/angka) atau [nilai, gaya]. Gaya: 0 biasa, 1 header, 2 angka bulat, 3 angka desimal, 4 tebal, 5 tebal angka
function xlsx_out(string $file, string $sheet, array $rows, array $widths): void {
  if (!class_exists('ZipArchive')) die('Ekstensi zip PHP belum aktif. Buka php.ini di XAMPP, aktifkan extension=zip, lalu restart Apache.');
  $x = '';
  foreach ($rows as $r => $row) {
    $x .= '<row r="'.($r+1).'">';
    foreach ($row as $c => $cell) {
      $v = $cell; $st = null;
      if (is_array($cell)) { $v = $cell[0]; $st = $cell[1]; }
      $ref = xl_col($c).($r+1);
      if ($v === null || $v === '') { if ($st) $x .= '<c r="'.$ref.'" s="'.$st.'"/>'; continue; }
      if (is_int($v) || is_float($v)) {
        if ($st === null) $st = (is_int($v) || abs($v-round($v))<0.0000001) ? 2 : 3;
        $x .= '<c r="'.$ref.'" s="'.$st.'"><v>'.$v.'</v></c>';
      } else {
        $x .= '<c r="'.$ref.'" t="inlineStr" s="'.(int)$st.'"><is><t xml:space="preserve">'.htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
      }
    }
    $x .= '</row>';
  }
  $cols = '<cols>'; foreach ($widths as $i=>$w) $cols .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.$w.'" customWidth="1"/>'; $cols .= '</cols>';
  $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
  $tmp = tempnam(sys_get_temp_dir(),'bph'); $z = new ZipArchive; $z->open($tmp, ZipArchive::OVERWRITE);
  $h = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
  $z->addFromString('[Content_Types].xml', $h.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
  $z->addFromString('_rels/.rels', $h.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml', $h.'<workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.e(mb_substr($sheet,0,30)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $z->addFromString('xl/_rels/workbook.xml.rels', $h.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
  $z->addFromString('xl/styles.xml', $h.'<styleSheet xmlns="'.$ns.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/></cellXfs></styleSheet>');
  $z->addFromString('xl/worksheets/sheet1.xml', $h.'<worksheet xmlns="'.$ns.'">'.$cols.'<sheetData>'.$x.'</sheetData></worksheet>');
  $z->close();
  while (ob_get_level()) ob_end_clean();
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$file.'"');
  header('Content-Length: '.filesize($tmp));
  readfile($tmp); unlink($tmp); exit;
}

// Query laporan (dipakai halaman laporan & export)
function lap_query(PDO $pdo): array {
  $dari = $_GET['dari'] ?? date('Y-m-01'); $smp = $_GET['sampai'] ?? date('Y-m-d');
  $jenis = $_GET['jenis'] ?? ''; $bid = (int)($_GET['b'] ?? 0);
  $w = "t.tanggal BETWEEN ? AND ?"; $par = [$dari,$smp];
  if ($jenis==='masuk'||$jenis==='keluar') { $w.=" AND t.tipe=?"; $par[]=$jenis; } else $jenis='';
  if ($bid) { $w.=" AND t.barang_id=?"; $par[]=$bid; }
  $q=$pdo->prepare("SELECT t.*,b.kode,b.nama,b.satuan FROM transaksi t JOIN barang b ON b.id=t.barang_id WHERE $w ORDER BY t.tanggal,t.id");
  $q->execute($par); $rows=$q->fetchAll();
  $beli=$jual=$hpp=0.0;
  foreach($rows as $t){ $tot=$t['qty']*$t['harga']; if($t['tipe']==='masuk') $beli+=$tot; else { $jual+=$tot; $hpp+=$t['qty']*$t['hpp']; } }
  return compact('dari','smp','jenis','bid','rows','beli','jual','hpp');
}

// ---------- Aksi (POST) ----------
$ok = !empty($_SESSION['u']);
if ($ok) { // pastikan akun masih ada di SQL
  $c = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE id=?"); $c->execute([(int)$_SESSION['u']]);
  if (!$c->fetchColumn()) { session_destroy(); redir('?'); }
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $a = $_POST['a'] ?? '';
  if ($a === 'login') {
    $q = $pdo->prepare("SELECT id,username,password_hash FROM pengguna WHERE username=?");
    $q->execute([trim((string)($_POST['u']??''))]); $u = $q->fetch();
    if ($u && password_verify((string)($_POST['p']??''), $u['password_hash'])) {
      if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT))
        $pdo->prepare("UPDATE pengguna SET password_hash=? WHERE id=?")->execute([password_hash((string)$_POST['p'],PASSWORD_DEFAULT),$u['id']]);
      session_regenerate_id(true); $_SESSION['u']=(int)$u['id']; $_SESSION['n']=$u['username'];
      $_SESSION['t']=bin2hex(random_bytes(16)); redir('?');
    } else $err = 'Username atau password salah.';
  } elseif ($ok) {
    if (!hash_equals($_SESSION['t'], (string)($_POST['t']??''))) die('Sesi tidak valid, muat ulang halaman.');
    try {
      if ($a === 'add_barang') {
        $kode=trim($_POST['kode']??''); $nama=trim($_POST['nama']??''); $sat=trim($_POST['satuan']??'');
        if ($kode===''||$nama==='') throw new Exception('Kode dan nama barang wajib diisi.');
        $c=$pdo->prepare("SELECT COUNT(*) FROM satuan WHERE nama=?"); $c->execute([$sat]);
        if (!$c->fetchColumn()) throw new Exception('Pilih satuan dari daftar (atur di menu Pengaturan).');
        try { $pdo->prepare("INSERT INTO barang(kode,nama,satuan) VALUES(?,?,?)")->execute([$kode,$nama,$sat]); }
        catch (PDOException $x) { throw new Exception('Kode barang "'.$kode.'" sudah dipakai.'); }
        flash('ok','Barang "'.$nama.'" ditambahkan.'); redir('?p=barang');
      }
      if ($a === 'del_barang') {
        $id=(int)$_POST['id'];
        $c=$pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE barang_id=?"); $c->execute([$id]);
        if ($c->fetchColumn() > 0) throw new Exception('Barang sudah punya transaksi, tidak bisa dihapus.');
        $pdo->prepare("DELETE FROM barang WHERE id=?")->execute([$id]);
        flash('ok','Barang dihapus.'); redir('?p=barang');
      }
      if ($a === 'add_trx') {
        $id=(int)($_POST['b']??0); $tipe=($_POST['tipe']??'')==='keluar'?'keluar':'masuk';
        $tg=(string)($_POST['tgl']??''); $qty=(int)($_POST['qty']??0);
        $h=(float)str_replace(',','.',(string)($_POST['harga']??'')); $cat=mb_substr(trim($_POST['cat']??''),0,200);
        $d=DateTime::createFromFormat('Y-m-d',$tg);
        if (!$d || $d->format('Y-m-d')!==$tg) throw new Exception('Tanggal tidak valid.');
        if ($qty < 1) throw new Exception('Jumlah minimal 1.');
        if ($h < 0) throw new Exception('Harga tidak boleh minus.');
        $pdo->beginTransaction();
        $b=$pdo->prepare("SELECT id FROM barang WHERE id=? FOR UPDATE"); $b->execute([$id]);
        if (!$b->fetch()) { $pdo->rollBack(); throw new Exception('Pilih barang terlebih dahulu.'); }
        $pdo->prepare("INSERT INTO transaksi(barang_id,tipe,tanggal,qty,harga,catatan) VALUES(?,?,?,?,?,?)")
            ->execute([$id,$tipe,$tg,$qty,$h,$cat]);
        if (!recalc($pdo,$id)) { $pdo->rollBack(); throw new Exception('Stok tidak cukup pada tanggal tersebut. Data tidak disimpan.'); }
        $pdo->commit();
        flash('ok', $tipe==='masuk' ? 'Barang masuk tersimpan.' : 'Penjualan tersimpan.'); redir('?p='.$tipe);
      }
      if ($a === 'del_trx') {
        $id=(int)$_POST['id'];
        $r=$pdo->prepare("SELECT barang_id,tipe FROM transaksi WHERE id=?"); $r->execute([$id]); $r=$r->fetch();
        if (!$r) throw new Exception('Data tidak ditemukan.');
        $pdo->beginTransaction();
        $pdo->prepare("SELECT id FROM barang WHERE id=? FOR UPDATE")->execute([$r['barang_id']]);
        $pdo->prepare("DELETE FROM transaksi WHERE id=?")->execute([$id]);
        if (!recalc($pdo,(int)$r['barang_id'])) { $pdo->rollBack(); throw new Exception('Tidak bisa dihapus: stok akan menjadi minus pada transaksi lain.'); }
        $pdo->commit();
        flash('ok','Transaksi dihapus, stok & laba dihitung ulang.'); redir('?p='.$r['tipe']);
      }
      // ----- Pengaturan -----
      if ($a === 'simpan_toko') {
        $n = mb_substr(trim($_POST['nama_toko']??''),0,100);
        if ($n==='') throw new Exception('Nama toko tidak boleh kosong.');
        $pdo->prepare("REPLACE INTO pengaturan(k,v) VALUES('nama_toko',?)")->execute([$n]);
        flash('ok','Nama toko disimpan.'); redir('?p=pengaturan');
      }
      if ($a === 'ganti_pass') {
        $q=$pdo->prepare("SELECT password_hash FROM pengguna WHERE id=?"); $q->execute([$_SESSION['u']]);
        if (!password_verify((string)($_POST['lama']??''), (string)$q->fetchColumn())) throw new Exception('Password lama salah.');
        $baru=(string)($_POST['baru']??'');
        if (strlen($baru)<6) throw new Exception('Password baru minimal 6 karakter.');
        if ($baru!==(string)($_POST['ulang']??'')) throw new Exception('Konfirmasi password tidak sama.');
        $pdo->prepare("UPDATE pengguna SET password_hash=? WHERE id=?")->execute([password_hash($baru,PASSWORD_DEFAULT),$_SESSION['u']]);
        flash('ok','Password berhasil diganti.'); redir('?p=pengaturan');
      }
      if ($a === 'add_user') {
        $un=trim($_POST['username']??''); $nm=mb_substr(trim($_POST['nama']??''),0,80); $pw=(string)($_POST['password']??'');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/',$un)) throw new Exception('Username 3-40 karakter (huruf, angka, _ . -).');
        if (strlen($pw)<6) throw new Exception('Password minimal 6 karakter.');
        try { $pdo->prepare("INSERT INTO pengguna(username,nama,password_hash) VALUES(?,?,?)")->execute([$un,$nm,password_hash($pw,PASSWORD_DEFAULT)]); }
        catch (PDOException $x) { throw new Exception('Username "'.$un.'" sudah dipakai.'); }
        flash('ok','Pengguna "'.$un.'" ditambahkan.'); redir('?p=pengaturan');
      }
      if ($a === 'del_user') {
        $id=(int)$_POST['id'];
        if ($id===(int)$_SESSION['u']) throw new Exception('Akun yang sedang dipakai tidak bisa dihapus.');
        $pdo->prepare("DELETE FROM pengguna WHERE id=?")->execute([$id]);
        flash('ok','Pengguna dihapus.'); redir('?p=pengaturan');
      }
      if ($a === 'add_satuan') {
        $s=mb_substr(trim($_POST['satuan']??''),0,20);
        if ($s==='') throw new Exception('Nama satuan wajib diisi.');
        try { $pdo->prepare("INSERT INTO satuan(nama) VALUES(?)")->execute([$s]); }
        catch (PDOException $x) { throw new Exception('Satuan "'.$s.'" sudah ada.'); }
        flash('ok','Satuan ditambahkan.'); redir('?p=pengaturan');
      }
      if ($a === 'del_satuan') {
        $q=$pdo->prepare("SELECT nama FROM satuan WHERE id=?"); $q->execute([(int)$_POST['id']]); $n=$q->fetchColumn();
        $c=$pdo->prepare("SELECT COUNT(*) FROM barang WHERE satuan=?"); $c->execute([$n]);
        if ($c->fetchColumn()>0) throw new Exception('Satuan masih dipakai barang, tidak bisa dihapus.');
        $pdo->prepare("DELETE FROM satuan WHERE id=?")->execute([(int)$_POST['id']]);
        flash('ok','Satuan dihapus.'); redir('?p=pengaturan');
      }
    } catch (Exception $x) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      flash('err', $x->getMessage()); redir($_SERVER['HTTP_REFERER'] ?? '?');
    }
  }
}
if (isset($_GET['logout'])) { session_destroy(); redir('?'); }

// ---------- Export Excel (GET) ----------
if ($ok && isset($_GET['export'])) {
  if (($_GET['p'] ?? '') === 'laporan') {
    $L = lap_query($pdo);
    $jn = ['masuk'=>'Barang Masuk','keluar'=>'Barang Keluar (Terjual)'][$L['jenis']] ?? 'Semua transaksi';
    $R = [
      [[$toko.' - Laporan Barang Masuk & Keluar',4]],
      ['Periode: '.tgl($L['dari']).' s/d '.tgl($L['smp']).'  |  Jenis: '.$jn],
      ['Dicetak: '.date('d/m/Y H:i')],
      [],
      [['Total barang masuk (pembelian)',4],'','','','','',['', 0], (float)$L['beli']],
      [['Total penjualan',4],'','','','','','', (float)$L['jual']],
      [['Modal barang terjual',4],'','','','','','', (float)$L['hpp']],
      [['Laba',4],'','','','','','', [(float)($L['jual']-$L['hpp']),5]],
      [],
    ];
    $R[4][6] = '';
    $hd = ['Tanggal','Jenis','Kode','Barang','Jumlah','Satuan','Harga/satuan','Total','Modal/satuan','Laba','Catatan'];
    $R[] = array_map(fn($h)=>[$h,1], $hd);
    foreach ($L['rows'] as $t) { $k=$t['tipe']==='keluar';
      $R[] = [tgl($t['tanggal']), $k?'Terjual':'Masuk', $t['kode'], $t['nama'], (int)$t['qty'], $t['satuan'],
        (float)$t['harga'], (float)($t['qty']*$t['harga']),
        $k?(float)$t['hpp']:'', $k?(float)($t['qty']*($t['harga']-$t['hpp'])):'', $t['catatan']]; }
    xlsx_out('Laporan_BPHOMES_'.date('Ymd_His').'.xlsx','Laporan',$R,[12,10,12,30,9,9,15,16,15,16,30]);
  }
  if (($_GET['p'] ?? '') === 'dash') {
    $R = [[[$toko.' - Stok Barang',4]], ['Dicetak: '.date('d/m/Y H:i')], [],
      array_map(fn($h)=>[$h,1], ['Kode','Nama barang','Satuan','Stok','Modal rata-rata','Nilai stok'])];
    $tot = 0.0;
    foreach ($pdo->query("SELECT * FROM barang ORDER BY nama") as $b) { $n=(float)($b['stok']*$b['modal']); $tot+=$n;
      $R[] = [$b['kode'],$b['nama'],$b['satuan'],(int)$b['stok'],(float)$b['modal'],$n]; }
    $R[] = []; $R[] = [['Total nilai stok',4],'','','','',[$tot,5]];
    xlsx_out('Stok_BPHOMES_'.date('Ymd_His').'.xlsx','Stok',$R,[14,32,10,10,18,18]);
  }
}

// ---------- Tampilan ----------
$p = $_GET['p'] ?? 'dash';
$msg = $_SESSION['m'] ?? null; unset($_SESSION['m']);
function csrf(){ return '<input type="hidden" name="t" value="'.e($_SESSION['t']).'">'; }
function head($title){ global $toko; ?>
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> - <?=e($toko)?></title>
<style>
*{box-sizing:border-box}body{margin:0;font:16px/1.5 system-ui,Segoe UI,Arial,sans-serif;background:#f3f5f8;color:#1c2430}
.top{background:#12355b;color:#fff;padding:12px 20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.top b{font-size:20px;margin-right:16px}.top a{color:#cfe0f5;text-decoration:none;padding:8px 14px;border-radius:8px}
.top a.on,.top a:hover{background:#2a5a91;color:#fff}.top .sp{flex:1}
.wrap{max-width:1100px;margin:20px auto;padding:0 16px}
.card{background:#fff;border-radius:12px;padding:18px;margin-bottom:18px;box-shadow:0 1px 4px #0001}
h1{font-size:22px;margin:0 0 14px}h2{font-size:18px;margin:0 0 12px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}
.stat{padding:14px;border-radius:10px;background:#eef3f9}.stat small{color:#556}.stat div{font-size:22px;font-weight:700}
.stat.g{background:#e6f6ea}.stat.o{background:#fff1e0}
label{display:block;font-weight:600;margin:10px 0 4px}
input,select{width:100%;padding:10px;border:1px solid #b9c3d0;border-radius:8px;font-size:16px;background:#fff}
.row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
button,.btn{background:#12355b;color:#fff;border:0;padding:11px 20px;border-radius:8px;font-size:16px;cursor:pointer;text-decoration:none;display:inline-block}
button.g,.btn.g{background:#1f8a3d}button.o{background:#d9730d}button.r{background:#c0392b;padding:6px 12px;font-size:14px}
.btn.gray{background:#667}
table{width:100%;border-collapse:collapse;font-size:15px}th,td{padding:9px 8px;border-bottom:1px solid #e3e8ef;text-align:left}
th{background:#f0f4f9}td.n,th.n{text-align:right;white-space:nowrap}.wide{overflow-x:auto}
.ok{background:#e2f5e7;color:#14602a;padding:12px;border-radius:8px;margin-bottom:14px}
.err{background:#fde8e6;color:#9b2318;padding:12px;border-radius:8px;margin-bottom:14px}
.tag{padding:2px 9px;border-radius:99px;font-size:13px;color:#fff}.tag.m{background:#1f8a3d}.tag.k{background:#d9730d}
.hint{color:#667;font-size:14px;margin-top:4px}.print-h{display:none}
@media print{body{background:#fff}.top,.noprint,button,.btn,form.f{display:none!important}.card{box-shadow:none;padding:0}.print-h{display:block;margin-bottom:10px}.wrap{margin:0;max-width:none}}
</style></head><body>
<?php }
function nav($p){ global $toko;
  $m=['dash'=>'Ringkasan','barang'=>'Daftar Barang','masuk'=>'Barang Masuk','keluar'=>'Barang Keluar (Terjual)','laporan'=>'Laporan / Cetak','pengaturan'=>'Pengaturan'];
  echo '<div class="top noprint"><b>'.e($toko).'</b>'; foreach($m as $k=>$v) echo '<a href="?p='.$k.'" class="'.($p===$k?'on':'').'">'.$v.'</a>';
  echo '<span class="sp"></span><span>'.e($_SESSION['n']??'').'</span><a href="?logout=1">Keluar</a></div>'; }

if (!$ok) { head('Login'); ?>
<div class="wrap" style="max-width:380px;margin-top:80px"><div class="card">
<h1><?=e($toko)?></h1><p class="hint">Silakan login untuk membuka aplikasi.</p>
<?php if($err) echo '<div class="err">'.e($err).'</div>'; ?>
<form method="post"><input type="hidden" name="a" value="login">
<label>Username</label><input name="u" autofocus autocomplete="username" required>
<label>Password</label><input type="password" name="p" autocomplete="current-password" required>
<p><button>Masuk</button></p></form></div></div></body></html>
<?php exit; }

$titles=['dash'=>'Ringkasan','barang'=>'Daftar Barang','masuk'=>'Barang Masuk','keluar'=>'Barang Keluar','laporan'=>'Laporan','pengaturan'=>'Pengaturan'];
if (!isset($titles[$p])) $p='dash';
head($titles[$p]); nav($p); echo '<div class="wrap">';
if ($msg) echo '<div class="'.($msg[0]==='ok'?'ok':'err').'">'.e($msg[1]).'</div>';

// ---- Ringkasan ----
if ($p === 'dash') {
  $bulan = date('Y-m');
  $s = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(stok*modal),0) nilai FROM barang")->fetch();
  $q = $pdo->prepare("SELECT COALESCE(SUM(qty*harga),0) jual, COALESCE(SUM(qty*(harga-hpp)),0) laba FROM transaksi WHERE tipe='keluar' AND DATE_FORMAT(tanggal,'%Y-%m')=?");
  $q->execute([$bulan]); $m = $q->fetch();
  $q = $pdo->prepare("SELECT COALESCE(SUM(qty*harga),0) FROM transaksi WHERE tipe='masuk' AND DATE_FORMAT(tanggal,'%Y-%m')=?");
  $q->execute([$bulan]); $beli = $q->fetchColumn();
  ?>
  <div class="card"><h1>Ringkasan Bulan Ini (<?=date('m/Y')?>)</h1><div class="grid">
    <div class="stat"><small>Jenis barang</small><div><?=e($s['n'])?></div></div>
    <div class="stat"><small>Nilai stok saat ini (modal)</small><div><?=rp($s['nilai'])?></div></div>
    <div class="stat"><small>Pembelian barang masuk</small><div><?=rp($beli)?></div></div>
    <div class="stat o"><small>Penjualan</small><div><?=rp($m['jual'])?></div></div>
    <div class="stat g"><small>Laba</small><div><?=rp($m['laba'])?></div></div></div></div>
  <div class="card"><div class="print-h"><h2><?=e($toko)?> - Laporan Stok</h2>Dicetak: <?=date('d/m/Y H:i')?></div>
  <h2>Stok Barang Saat Ini <span class="noprint" style="float:right"><a class="btn g" href="?p=dash&export=1">Download Excel</a> <button onclick="print()">Cetak</button></span></h2><div class="wide"><table>
  <tr><th>Kode</th><th>Nama</th><th class="n">Stok</th><th class="n">Modal rata-rata / satuan</th><th class="n">Nilai stok</th></tr>
  <?php foreach($pdo->query("SELECT * FROM barang ORDER BY nama") as $b): ?>
  <tr><td><?=e($b['kode'])?></td><td><?=e($b['nama'])?></td><td class="n"><?=e($b['stok'].' '.$b['satuan'])?></td>
  <td class="n"><?=rp($b['modal'])?></td><td class="n"><?=rp($b['stok']*$b['modal'])?></td></tr>
  <?php endforeach; ?></table></div></div>
<?php }

// ---- Daftar Barang ----
if ($p === 'barang') { $sats = $pdo->query("SELECT nama FROM satuan ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN); ?>
  <div class="card"><h1>Tambah Barang Baru</h1><form method="post" class="f"><input type="hidden" name="a" value="add_barang"><?=csrf()?>
  <div class="row"><div><label>Kode barang</label><input name="kode" required placeholder="mis. BRG001"></div>
  <div><label>Nama barang</label><input name="nama" required></div>
  <div><label>Satuan</label><select name="satuan" required>
  <?php foreach($sats as $s) echo '<option>'.e($s).'</option>'; ?></select>
  <div class="hint">Satuan baru ditambah di menu Pengaturan.</div></div></div>
  <p><button>Simpan Barang</button></p></form></div>
  <div class="card"><h2>Daftar Barang</h2><div class="wide"><table>
  <tr><th>Kode</th><th>Nama</th><th class="n">Stok</th><th class="n">Modal rata-rata</th><th></th></tr>
  <?php foreach($pdo->query("SELECT * FROM barang ORDER BY nama") as $b): ?>
  <tr><td><?=e($b['kode'])?></td><td><?=e($b['nama'])?></td><td class="n"><?=e($b['stok'].' '.$b['satuan'])?></td><td class="n"><?=rp($b['modal'])?></td>
  <td class="n"><form method="post" onsubmit="return confirm('Hapus barang ini?')" style="display:inline"><input type="hidden" name="a" value="del_barang"><input type="hidden" name="id" value="<?=$b['id']?>"><?=csrf()?><button class="r">Hapus</button></form></td></tr>
  <?php endforeach; ?></table></div>
  <p class="hint">Barang yang sudah punya transaksi tidak bisa dihapus.</p></div>
<?php }

// ---- Masuk / Keluar ----
if ($p === 'masuk' || $p === 'keluar') {
  $masuk = $p === 'masuk';
  $barang = $pdo->query("SELECT * FROM barang ORDER BY nama")->fetchAll(); ?>
  <div class="card"><h1><?=$masuk?'Catat Barang Masuk (Pembelian)':'Catat Barang Keluar (Terjual)'?></h1>
  <?php if(!$barang) echo '<p>Belum ada barang. <a href="?p=barang">Tambah barang dulu</a>.</p>'; else { ?>
  <form method="post" class="f"><input type="hidden" name="a" value="add_trx"><input type="hidden" name="tipe" value="<?=$p?>"><?=csrf()?>
  <div class="row"><div><label>Barang</label><select name="b" required><option value="">-- pilih barang --</option>
  <?php foreach($barang as $b) echo '<option value="'.$b['id'].'">'.e($b['nama'].' (stok '.$b['stok'].' '.$b['satuan'].')').'</option>'; ?></select></div>
  <div><label>Tanggal</label><input type="date" name="tgl" value="<?=date('Y-m-d')?>" required></div>
  <div><label>Jumlah</label><input type="number" name="qty" min="1" step="1" required></div>
  <div><label><?=$masuk?'Harga beli per satuan (Rp)':'Harga jual per satuan (Rp)'?></label><input type="number" name="harga" min="0" step="0.01" required></div></div>
  <label>Catatan (boleh kosong)</label><input name="cat" maxlength="200">
  <p><button class="<?=$masuk?'g':'o'?>"><?=$masuk?'Simpan Barang Masuk':'Simpan Penjualan'?></button></p>
  <?php if(!$masuk) echo '<p class="hint">Laba dihitung otomatis dari modal rata-rata barang saat dijual.</p>'; ?></form><?php } ?></div>
  <div class="card"><h2>100 Transaksi Terakhir</h2><div class="wide"><table>
  <tr><th>Tanggal</th><th>Barang</th><th class="n">Jumlah</th><th class="n">Harga/satuan</th><th class="n">Total</th>
  <?php if(!$masuk) echo '<th class="n">Laba</th>'; ?><th>Catatan</th><th></th></tr>
  <?php $q=$pdo->prepare("SELECT t.*,b.nama,b.satuan FROM transaksi t JOIN barang b ON b.id=t.barang_id WHERE t.tipe=? ORDER BY t.tanggal DESC,t.id DESC LIMIT 100");
  $q->execute([$p]); foreach($q as $t): ?>
  <tr><td><?=tgl($t['tanggal'])?></td><td><?=e($t['nama'])?></td><td class="n"><?=e($t['qty'].' '.$t['satuan'])?></td>
  <td class="n"><?=rp($t['harga'])?></td><td class="n"><?=rp($t['qty']*$t['harga'])?></td>
  <?php if(!$masuk) echo '<td class="n">'.rp($t['qty']*($t['harga']-$t['hpp'])).'</td>'; ?>
  <td><?=e($t['catatan'])?></td>
  <td class="n"><form method="post" onsubmit="return confirm('Hapus transaksi ini? Stok & laba dihitung ulang.')"><input type="hidden" name="a" value="del_trx"><input type="hidden" name="id" value="<?=$t['id']?>"><?=csrf()?><button class="r">Hapus</button></form></td></tr>
  <?php endforeach; ?></table></div></div>
<?php }

// ---- Laporan ----
if ($p === 'laporan') {
  extract(lap_query($pdo)); // $dari $smp $jenis $bid $rows $beli $jual $hpp
  $exp = '?'.http_build_query(['p'=>'laporan','dari'=>$dari,'sampai'=>$smp,'jenis'=>$jenis,'b'=>$bid,'export'=>1]);
  ?>
  <div class="card noprint"><h1>Laporan & Cetak</h1><form method="get"><input type="hidden" name="p" value="laporan">
  <div class="row"><div><label>Dari tanggal</label><input type="date" name="dari" value="<?=e($dari)?>"></div>
  <div><label>Sampai tanggal</label><input type="date" name="sampai" value="<?=e($smp)?>"></div>
  <div><label>Jenis</label><select name="jenis"><option value="">Semua</option><option value="masuk" <?=$jenis==='masuk'?'selected':''?>>Barang masuk</option><option value="keluar" <?=$jenis==='keluar'?'selected':''?>>Barang keluar (terjual)</option></select></div>
  <div><label>Barang</label><select name="b"><option value="0">Semua barang</option>
  <?php foreach($pdo->query("SELECT id,nama FROM barang ORDER BY nama") as $b) echo '<option value="'.$b['id'].'" '.($bid==$b['id']?'selected':'').'>'.e($b['nama']).'</option>'; ?></select></div></div>
  <p><button>Tampilkan</button> <a class="btn g" href="<?=e($exp)?>">Download Excel</a> <button type="button" class="gray" onclick="print()">Cetak</button></p></form></div>
  <div class="card"><div class="print-h"><h2><?=e($toko)?> - Laporan Barang Masuk &amp; Keluar</h2>
  Periode <?=tgl($dari)?> s/d <?=tgl($smp)?> | Dicetak: <?=date('d/m/Y H:i')?></div>
  <div class="grid" style="margin-bottom:16px">
    <div class="stat"><small>Total barang masuk (pembelian)</small><div><?=rp($beli)?></div></div>
    <div class="stat o"><small>Total penjualan</small><div><?=rp($jual)?></div></div>
    <div class="stat"><small>Modal barang terjual</small><div><?=rp($hpp)?></div></div>
    <div class="stat g"><small>Laba</small><div><?=rp($jual-$hpp)?></div></div></div>
  <div class="wide"><table><tr><th>Tanggal</th><th>Jenis</th><th>Barang</th><th class="n">Jumlah</th><th class="n">Harga/satuan</th><th class="n">Total</th><th class="n">Modal/satuan</th><th class="n">Laba</th></tr>
  <?php foreach($rows as $t): $k=$t['tipe']==='keluar'; ?>
  <tr><td><?=tgl($t['tanggal'])?></td><td><span class="tag <?=$k?'k':'m'?>"><?=$k?'Terjual':'Masuk'?></span></td><td><?=e($t['nama'])?></td>
  <td class="n"><?=e($t['qty'].' '.$t['satuan'])?></td><td class="n"><?=rp($t['harga'])?></td><td class="n"><?=rp($t['qty']*$t['harga'])?></td>
  <td class="n"><?=$k?rp($t['hpp']):'-'?></td><td class="n"><?=$k?rp($t['qty']*($t['harga']-$t['hpp'])):'-'?></td></tr>
  <?php endforeach; if(!$rows) echo '<tr><td colspan="8">Tidak ada data pada periode ini.</td></tr>'; ?></table></div></div>
<?php }

// ---- Pengaturan ----
if ($p === 'pengaturan') { ?>
  <div class="card"><h1>Nama Toko</h1><form method="post"><input type="hidden" name="a" value="simpan_toko"><?=csrf()?>
  <label>Nama toko (tampil di judul & laporan)</label><input name="nama_toko" value="<?=e($toko)?>" maxlength="100" required>
  <p><button>Simpan</button></p></form></div>

  <div class="card"><h1>Ganti Password Saya</h1><form method="post"><input type="hidden" name="a" value="ganti_pass"><?=csrf()?>
  <div class="row"><div><label>Password lama</label><input type="password" name="lama" required autocomplete="current-password"></div>
  <div><label>Password baru (min. 6)</label><input type="password" name="baru" required autocomplete="new-password"></div>
  <div><label>Ulangi password baru</label><input type="password" name="ulang" required autocomplete="new-password"></div></div>
  <p><button>Ganti Password</button></p></form></div>

  <div class="card"><h1>Pengguna (Login)</h1>
  <form method="post"><input type="hidden" name="a" value="add_user"><?=csrf()?>
  <div class="row"><div><label>Username</label><input name="username" required></div>
  <div><label>Nama lengkap</label><input name="nama"></div>
  <div><label>Password (min. 6)</label><input type="password" name="password" required autocomplete="new-password"></div></div>
  <p><button class="g">Tambah Pengguna</button></p></form>
  <div class="wide"><table><tr><th>Username</th><th>Nama</th><th>Dibuat</th><th></th></tr>
  <?php foreach($pdo->query("SELECT * FROM pengguna ORDER BY username") as $u): ?>
  <tr><td><?=e($u['username'])?></td><td><?=e($u['nama'])?></td><td><?=e(date('d/m/Y H:i',strtotime($u['dibuat'])))?></td>
  <td class="n"><?php if((int)$u['id']!==(int)$_SESSION['u']): ?><form method="post" onsubmit="return confirm('Hapus pengguna ini?')" style="display:inline"><input type="hidden" name="a" value="del_user"><input type="hidden" name="id" value="<?=$u['id']?>"><?=csrf()?><button class="r">Hapus</button></form><?php else: ?><small>(akun Anda)</small><?php endif; ?></td></tr>
  <?php endforeach; ?></table></div></div>

  <div class="card"><h1>Satuan Barang</h1>
  <form method="post"><input type="hidden" name="a" value="add_satuan"><?=csrf()?>
  <div class="row"><div><label>Satuan baru</label><input name="satuan" maxlength="20" required placeholder="mis. karung"></div></div>
  <p><button class="g">Tambah Satuan</button></p></form>
  <div class="wide"><table><tr><th>Satuan</th><th></th></tr>
  <?php foreach($pdo->query("SELECT * FROM satuan ORDER BY nama") as $s): ?>
  <tr><td><?=e($s['nama'])?></td><td class="n"><form method="post" onsubmit="return confirm('Hapus satuan ini?')" style="display:inline"><input type="hidden" name="a" value="del_satuan"><input type="hidden" name="id" value="<?=$s['id']?>"><?=csrf()?><button class="r">Hapus</button></form></td></tr>
  <?php endforeach; ?></table></div></div>
<?php }
echo '</div></body></html>';
<?php
declare(strict_types=1);

/* =====================================================
   KONFIGURASI  (ubah di sini saja)
   ===================================================== */
$cfg = [
    'nama'      => 'MA Syamsul Huda Kedungreja',
    'alamat'    => 'Jl. Pesantren, Ciklapa Kec. Kedungreja Kab. Cilacap',
    'bio'       => 'Hubungi media sosial kami:',
    'avatar'    => 'https://ugc.production.linktr.ee/d94d57a1-179e-47b0-acc2-202d994cc088_logo-yabakii-padding-trans.png?io=true&size=avatar-v3_0',
    'situs'     => 'https://masyamsulhuda.sch.id',
    'jumlah_berita' => 6,        // jumlah berita yang ditampilkan
    'cache_menit'   => 15,       // lama cache berita
    'cache_file'    => __DIR__ . '/cache_berita.json',

    // Ikon sosial. Kosongkan URL untuk menyembunyikan ikon.
    'sosial' => [
        ['label' => 'Website',   'ikon' => 'globe',    'url' => 'https://masyamsulhuda.sch.id'],
        ['label' => 'WhatsApp',  'ikon' => 'whatsapp', 'url' => 'https://wa.me/6281224095867'],
        ['label' => 'Telegram',  'ikon' => 'telegram', 'url' => ''],   // isi: https://t.me/username
        ['label' => 'Facebook',  'ikon' => 'facebook', 'url' => 'https://www.facebook.com/masyamsulhuda.kedungreja/'],
        ['label' => 'Email',     'ikon' => 'mail',     'url' => 'mailto:info@masyamsulhuda.sch.id'],
        ['label' => 'Instagram', 'ikon' => 'instagram','url' => 'https://www.instagram.com/masyamsulhuda.official/'],
        ['label' => 'Google Maps', 'ikon' => 'map', 'url' => 'https://maps.app.goo.gl/nc1GPokFxnREqSbt5'],
    ],

    // Tombol utama
    'tombol' => [
        ['teks' => 'Formulir Pendaftaran', 'url' => 'https://docs.google.com/forms/d/e/1FAIpQLSca-cO6ZUZLT_DMDIPdCiFPti0JQaNqruvZrgkGzjO_c78ptA/viewform'],
        ['teks' => 'Informasi Pendaftaran','url' => 'https://www.masyamsulhuda.sch.id/daftar/'],
    ],

    // Grup tombol kedua
    'grup' => [
        'judul' => 'Lingkungan Pondok Pesantren',
        'tombol' => [
            ['teks' => 'Pondok Pesantren Syamsul Huda',    'url' => 'https://maps.app.goo.gl/VCKdeLTNHkKoeZS8A'],
            ['teks' => "Pondok Pesantren Assa'idiyyah",    'url' => 'https://goo.gl/maps/Pn5z9wQ8atY1ABBb7'],
            ['teks' => 'Pondok Pesantren Salafiyah Mewah', 'url' => 'https://goo.gl/maps/NFywiRNPt4aw43W7A'],
        ],
    ],
];

/* =====================================================
   FUNGSI
   ===================================================== */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function http_get(string $url, int $timeout = 8): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; LinkPPDB/1.0)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json, application/xml, text/xml, */*'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
    }
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header'  => "User-Agent: Mozilla/5.0 (compatible; LinkPPDB/1.0)\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

function bersihkan(string $html, int $maks = 120): string
{
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');
    $t = preg_replace('/\s*(\[…\]|\[&hellip;\]|Read more.*|Baca selengkapnya.*)$/iu', '', $t) ?? $t;
    if (mb_strlen($t) > $maks) {
        $t = rtrim(mb_substr($t, 0, $maks), " ,.;:-") . '…';
    }
    return $t;
}

function tanggal_id(string $tgl): string
{
    $ts = strtotime($tgl);
    if (!$ts) {
        return '';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/** Sumber 1: WordPress REST API */
function berita_dari_rest(string $situs, int $n): array
{
    $url = rtrim($situs, '/') . '/wp-json/wp/v2/posts?per_page=' . $n . '&_embed=wp:featuredmedia';
    $raw = http_get($url);
    if ($raw === null) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    $hasil = [];
    foreach ($data as $p) {
        if (!isset($p['link'], $p['title']['rendered'])) {
            continue;
        }
        $gambar = '';
        $media = $p['_embedded']['wp:featuredmedia'][0] ?? null;
        if (is_array($media)) {
            $gambar = $media['media_details']['sizes']['medium']['source_url']
                ?? $media['source_url']
                ?? '';
        }
        $hasil[] = [
            'judul'   => bersihkan((string) $p['title']['rendered'], 140),
            'url'     => (string) $p['link'],
            'tanggal' => tanggal_id((string) ($p['date'] ?? '')),
            'ringkas' => bersihkan((string) ($p['excerpt']['rendered'] ?? ''), 110),
            'gambar'  => (string) $gambar,
        ];
    }
    return $hasil;
}

/** Sumber 2 (cadangan): RSS feed */
function berita_dari_rss(string $situs, int $n): array
{
    $raw = http_get(rtrim($situs, '/') . '/feed/');
    if ($raw === null || !function_exists('simplexml_load_string')) {
        return [];
    }
    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    libxml_use_internal_errors($prev);
    if (!$xml || !isset($xml->channel->item)) {
        return [];
    }
    $hasil = [];
    foreach ($xml->channel->item as $it) {
        $hasil[] = [
            'judul'   => bersihkan((string) $it->title, 140),
            'url'     => (string) $it->link,
            'tanggal' => tanggal_id((string) $it->pubDate),
            'ringkas' => bersihkan((string) $it->description, 110),
            'gambar'  => '',
        ];
        if (count($hasil) >= $n) {
            break;
        }
    }
    return $hasil;
}

function ambil_berita(array $cfg): array
{
    $file = $cfg['cache_file'];
    $ttl  = $cfg['cache_menit'] * 60;

    if (is_file($file) && (time() - filemtime($file)) < $ttl) {
        $c = json_decode((string) file_get_contents($file), true);
        if (is_array($c) && $c) {
            return $c;
        }
    }

    $berita = berita_dari_rest($cfg['situs'], $cfg['jumlah_berita']);
    if (!$berita) {
        $berita = berita_dari_rss($cfg['situs'], $cfg['jumlah_berita']);
    }

    if ($berita) {
        @file_put_contents($file, json_encode($berita, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $berita;
    }

    // Gagal ambil: pakai cache lama kalau ada
    if (is_file($file)) {
        $c = json_decode((string) file_get_contents($file), true);
        if (is_array($c)) {
            return $c;
        }
    }
    return [];
}

function ikon(string $nama): string
{
    $brands = [
        'whatsapp'  => 'fa-whatsapp',
        'telegram'  => 'fa-telegram',
        'facebook'  => 'fa-facebook-f',
        'instagram' => 'fa-instagram',
    ];
    if (isset($brands[$nama])) {
        return '<i class="fa-brands ' . $brands[$nama] . '" aria-hidden="true"></i>';
    }

    $icons = [
        'globe' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'mail'  => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'map'   => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($icons[$nama] ?? $icons['globe']) . '</svg>';
}

$berita = ambil_berita($cfg);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($cfg['nama']) ?> Official | PPDB</title>
<meta name="description" content="<?= e($cfg['bio']) ?>">
<meta property="og:title" content="<?= e($cfg['nama']) ?>">
<meta property="og:description" content="<?= e($cfg['bio']) ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<meta property="og:image" content="<?= e($cfg['avatar']) ?>">
<link rel="icon" href="<?= e($cfg['avatar']) ?>">
<style>
  :root{
    --green:#08783e; --green-dark:#064b2b; --yellow:#f4ce22;
    --bg1:var(--green); --bg2:var(--green-dark);
    --btn:#ffffff; --btn-text:#173b25;
    --text:#ffffff; --muted:rgba(255,255,255,.82);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html{-webkit-text-size-adjust:100%}
  body{
    min-height:100vh;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
    color:var(--text);
    background:linear-gradient(160deg,var(--bg1),var(--bg2)) fixed;
    padding:max(32px,env(safe-area-inset-top)) 16px max(32px,env(safe-area-inset-bottom));
  }
  .wrap{max-width:680px;margin:0 auto;text-align:center}
  .avatar{
    width:96px;height:96px;border-radius:50%;object-fit:cover;
    background:#fff;padding:4px;display:block;margin:0 auto 14px;
  }
  h1{font-size:1.35rem;font-weight:700;line-height:1.3}
  .bio{margin:8px auto 0;max-width:520px;font-size:.95rem;color:var(--muted);line-height:1.5}
  .sosial{display:flex;justify-content:center;flex-wrap:wrap;gap:10px;margin:20px 0 26px}
  .sosial a{
    width:44px;height:44px;border-radius:50%;
    display:grid;place-items:center;color:var(--yellow);
    background:rgba(255,255,255,.14);text-decoration:none;
    transition:background .15s,transform .15s;
  }
  .sosial a:hover,.sosial a:focus-visible{background:rgba(244,206,34,.2);transform:translateY(-2px)}
  @media (min-width:768px){
    .sosial a{width:54px;height:54px}
    .sosial a i{font-size:28px}
    .sosial a svg{width:28px;height:28px}
  }
  .link{
    display:block;width:100%;padding:16px 20px;margin-bottom:12px;
    background:var(--btn);color:var(--btn-text);
    border-radius:14px;text-decoration:none;font-weight:600;font-size:1rem;
    transition:transform .15s,box-shadow .15s;
  }
  .link .map-icon{margin-right:8px;color:var(--green)}
  .link:hover,.link:focus-visible{transform:scale(1.015);box-shadow:0 6px 18px rgba(0,0,0,.25)}
  h2{font-size:1rem;font-weight:700;margin:26px 0 12px;letter-spacing:.2px}

  /* Daftar berita */
  .berita{list-style:none;text-align:left}
  .berita li{margin-bottom:12px}
  .item{
    display:flex;gap:12px;align-items:stretch;
    background:var(--btn);color:var(--btn-text);
    border-radius:14px;overflow:hidden;text-decoration:none;
    transition:transform .15s,box-shadow .15s;
  }
  .item:hover,.item:focus-visible{transform:scale(1.015);box-shadow:0 6px 18px rgba(0,0,0,.25)}
  .item img{width:96px;min-height:96px;object-fit:cover;flex:none;background:#e2e8f0}
  .item .isi{padding:12px 14px;min-width:0}
  .item .tgl{font-size:.75rem;color:var(--green);font-weight:600}
  .item .jdl{font-size:.97rem;font-weight:700;line-height:1.35;margin-top:2px}
  .item .rgk{font-size:.82rem;color:#475569;line-height:1.4;margin-top:4px}
  .item.tanpa-gambar .isi{padding:14px 18px}
  .kosong{background:rgba(255,255,255,.12);border-radius:14px;padding:16px;color:var(--muted);font-size:.92rem;text-align:center}
  .semua{
    display:inline-block;margin-top:6px;color:var(--yellow);font-weight:600;
    font-size:.92rem;text-decoration:underline;text-underline-offset:3px
  }
  footer{margin-top:34px;font-size:.78rem;color:var(--muted)}
  footer a{color:var(--muted)}
  @media (max-width:420px){
    .item img{width:80px}
    .item .rgk{display:none}
  }
</style>
</head>
<body>
<main class="wrap">

  <img class="avatar" src="<?= e($cfg['avatar']) ?>" alt="Logo <?= e($cfg['nama']) ?>" width="96" height="96">
  <h1><?= e($cfg['nama']) ?></h1>
  <p class="bio alamat"><?= e($cfg['alamat']) ?></p>
  <p class="bio"><?= e($cfg['bio']) ?></p>

  <nav class="sosial" aria-label="Media sosial">
    <?php foreach ($cfg['sosial'] as $s): if (trim($s['url']) === '') continue; ?>
      <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label']) ?>" title="<?= e($s['label']) ?>">
        <?= ikon($s['ikon']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php foreach ($cfg['tombol'] as $t): ?>
    <a class="link" href="<?= e($t['url']) ?>" target="_blank" rel="noopener"><?= e($t['teks']) ?></a>
  <?php endforeach; ?>

  <h2>Berita Terbaru</h2>
  <?php if ($berita): ?>
    <ul class="berita">
      <?php foreach ($berita as $b): ?>
        <li>
          <a class="item<?= $b['gambar'] === '' ? ' tanpa-gambar' : '' ?>" href="<?= e($b['url']) ?>" target="_blank" rel="noopener">
            <?php if ($b['gambar'] !== ''): ?>
              <img src="<?= e($b['gambar']) ?>" alt="" loading="lazy">
            <?php endif; ?>
            <div class="isi">
              <?php if ($b['tanggal'] !== ''): ?><div class="tgl"><?= e($b['tanggal']) ?></div><?php endif; ?>
              <div class="jdl"><?= e($b['judul']) ?></div>
              <?php if ($b['ringkas'] !== ''): ?><div class="rgk"><?= e($b['ringkas']) ?></div><?php endif; ?>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <a class="semua" href="<?= e($cfg['situs']) ?>/blog" target="_blank" rel="noopener">Lihat semua berita &rarr;</a>
  <?php else: ?>
    <div class="kosong">Berita belum dapat dimuat saat ini.
      <a class="semua" href="<?= e($cfg['situs']) ?>/blog" target="_blank" rel="noopener">Buka situs sekolah &rarr;</a>
    </div>
  <?php endif; ?>

  <h2><?= e($cfg['grup']['judul']) ?></h2>
  <?php foreach ($cfg['grup']['tombol'] as $t): ?>
    <a class="link" href="<?= e($t['url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-map-location-dot map-icon" aria-hidden="true"></i><?= e($t['teks']) ?></a>
  <?php endforeach; ?>

  <footer>&copy; <?= date('Y') ?> <?= e($cfg['nama']) ?></footer>
</main>
</body>
</html>

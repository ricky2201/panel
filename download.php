<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/db_panel.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Download Game | Hiperion RAN</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main class="flex-grow px-4 py-10 max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold mb-8 text-center">Download Game</h1>

    <div class="bg-black bg-opacity-60 p-10 rounded-lg shadow-lg space-y-6">
      <?php
        // Daftar file download
        $downloads = [
          [
            'nama' => 'Full Client - Gdrive',
            'link' => 'https://drive.google.com/your-client-link',
            'keterangan' => 'Versi lengkap client game.',
            'ukuran' => '1.2 GB'
          ],
          [
            'nama' => 'Full Client - Mediafire',
            'link' => 'https://drive.google.com/your-client-link',
            'keterangan' => 'Versi lengkap client game.',
            'ukuran' => '1.2 GB'
          ],
          [
            'nama' => 'Patch Terbaru',
            'link' => 'https://drive.google.com/your-patch-link',
            'keterangan' => 'Gunakan ini jika sudah punya client.',
            'ukuran' => '150 MB'
          ],
          [
            'nama' => 'Launcher Only',
            'link' => 'https://drive.google.com/your-launcher-link',
            'keterangan' => 'Khusus untuk update launcher.',
            'ukuran' => '5 MB'
          ],
          [
            'nama' => 'Font Tambahan (opsional)',
            'link' => 'https://drive.google.com/your-font-link',
            'keterangan' => 'Gunakan jika font game rusak.',
            'ukuran' => '2 MB'
          ]
        ];
      ?>

      <?php foreach ($downloads as $file): ?>
        <div class="p-4 border border-gray-700 rounded-lg bg-gray-800 hover:bg-gray-700 transition">
          <div class="flex flex-col md:flex-row md:justify-between md:items-center">
            <div>
              <h2 class="text-lg font-bold text-yellow-400"><?= htmlspecialchars($file['nama']) ?></h2>
              <p class="text-sm text-gray-300"><?= htmlspecialchars($file['keterangan']) ?></p>
              <p class="text-xs text-gray-400 mt-1">Ukuran: <?= $file['ukuran'] ?></p>
            </div>
            <div class="mt-3 md:mt-0">
              <a href="<?= htmlspecialchars($file['link']) ?>" target="_blank"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-block ml-4">
                🔽 Download
                </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-8">
      <a href="index.php" class="text-blue-400 hover:underline">← Kembali ke Beranda</a>
    </div>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>
</body>
</html>
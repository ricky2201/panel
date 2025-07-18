<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../path.php';

// Ambil nama user jika login
$username = '';
if (isset($_SESSION['userid'])) {
  $username = htmlspecialchars($_SESSION['userid']);
}
?>

<!-- Font Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  body {
    font-family: 'Inter', sans-serif;
  }
</style>

<nav class="bg-black bg-opacity-70 text-white px-6 py-3 shadow-md">
  <div class="max-w-7xl mx-auto flex items-center justify-between">
    
    <!-- Kiri: Logo -->
    <a href="<?= BASE_URL ?>" class="flex items-center space-x-2">
      <img src="<?= ASSETS_PATH ?>logo.png" alt="Logo Game" class="h-[80px]" />
    </a>

    <!-- Tengah: Menu utama -->
    <div class="hidden md:flex space-x-6 text-xl font-semibold">
      <a href="<?= BASE_URL ?>" class="bg-blue-600 px-4 py-2 rounded text-white">Home</a>
      <a href="<?= BASE_URL ?>download" class="hover:text-blue-400 py-2">Download</a>

      <?php if (isset($_SESSION['userid'])): ?>
        <a href="<?= BASE_URL ?>topup/" class="hover:text-blue-400 py-2">Topup</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>auth/login" class="hover:text-blue-400 py-2">Topup</a>
      <?php endif; ?>

      <!-- Ranking Dropdown -->
      <a href="<?= BASE_URL ?>leaderboard" class="hover:text-blue-400 py-2">Ranking</a>

      <a href="#" class="hover:text-blue-400 py-2">Discord</a>
    </div>

    <!-- Kanan: Account -->
    <div class="relative ml-4" x-data="{ open: false }">
      <button @click="open = !open" class="hover:text-blue-400 py-2 flex items-center gap-1 text-xl font-semibold">
        Account <span>▾</span>
      </button>
      <div x-show="open" @click.away="open = false" class="absolute right-0 bg-black bg-opacity-80 mt-2 py-2 rounded shadow w-40 z-50 text-sm">
        <?php if (!isset($_SESSION['userid'])): ?>
          <a href="<?= BASE_URL ?>auth/login" class="block px-4 py-2 hover:bg-gray-800">Login</a>
          <a href="#" onclick="openRegisterModal(); return false;" class="block px-4 py-2 hover:bg-gray-800">Register</a>
        <?php else: ?>
          <div class="px-4 py-2 font-bold text-white border-b border-gray-700">
            halo, <span class="text-green-400"><?= $username ?></span>
          </div>
          <a href="<?= BASE_URL ?>userpanel/" class="block px-4 py-2 hover:bg-gray-800">User Panel</a>
          <a href="<?= BASE_URL ?>auth/logout" class="block px-4 py-2 hover:bg-gray-800 text-red-400">Logout</a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</nav>

<!-- Modal Register -->
<div id="registerModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
  <div class="bg-white text-black rounded-lg p-6 w-full max-w-sm text-center">
    <h2 class="text-xl font-bold mb-4">Informasi</h2>
    <p class="mb-4">Silakan Register di dalam game.</p>
    <button onclick="closeRegisterModal()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded w-full">Tutup</button>
  </div>
</div>

<script>
function openRegisterModal() {
  document.getElementById('registerModal').classList.remove('hidden');
}
function closeRegisterModal() {
  document.getElementById('registerModal').classList.add('hidden');
  location.reload();
}
</script>

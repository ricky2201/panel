<!-- Loading Overlay with Logo Spinner -->
<style>
  #loading-overlay {
    transition: opacity 0.5s ease;
  }

  #loading-logo {
    animation: spin 2s linear infinite;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>

<div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-[9999]">
  <img id="loading-logo" src="<?= BASE_URL ?>/assets/logo.png" alt="Loading..." class="h-20 w-20 opacity-90" />
</div>

<script>
  window.addEventListener('load', () => {
    const overlay = document.getElementById('loading-overlay');
    overlay.style.opacity = 0;
    setTimeout(() => overlay.style.display = 'none', 500);
  });

  document.querySelectorAll('a[href]').forEach(link => {
    const href = link.getAttribute('href');
    const target = link.getAttribute('target');

    if (!href.startsWith('#') && !target && !href.includes('javascript')) {
      link.addEventListener('click', () => {
        const overlay = document.getElementById('loading-overlay');
        overlay.style.display = 'flex';
        setTimeout(() => overlay.style.opacity = 1, 10);
      });
    }
  });
</script>

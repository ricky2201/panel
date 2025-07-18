<?php
// security_scanner.php - Script Pengujian Keamanan Website Dasar

// --- PENTING! BACA SEBELUM MENJALANKAN ---
// 1. Script ini dirancang untuk menguji website Anda sendiri.
//    JANGAN PERNAH MENGGUNAKANNYA PADA WEBSITE LAIN TANPA IZIN TERTULIS!
// 2. Skrip ini BUKAN pengganti alat pengujian penetrasi profesional.
//    Ini hanya memberikan indikasi awal kerentanan pasif dan dasar.
// 3. Simpan file ini di lokasi yang TIDAK DAPAT DIAKSES PUBLIK (misal, di luar public_html)
//    dan akseslah melalui browser Anda menggunakan path yang benar.
// 4. Setelah selesai pengujian, HAPUS file ini dari server Anda untuk keamanan.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tentukan URL dasar website Anda
// Ganti dengan URL domain Anda, misal: 'https://www.yourdomain.com'
$base_url = 'http://localhost/panel'; // Sesuaikan jika Anda punya subdomain atau folder lain
$test_url = 'http://localhost/panel/index.php'; // Contoh halaman untuk pengujian input

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Web Security Scanner - Glacier</title>
    <link href='https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Arial', sans-serif; }
        .result-box {
            background-color: #1e293b; /* gray-800 */
            border-left: 4px solid;
            padding: 1rem;
            margin-bottom: 1rem;
            color: white;
            border-radius: 0.25rem;
        }
        .result-pass { border-color: #10b981; /* green-500 */ }
        .result-warn { border-color: #f59e0b; /* yellow-500 */ }
        .result-fail { border-color: #ef4444; /* red-500 */ }
    </style>
</head>
<body class='bg-gray-900 text-white p-6'>
    <div class='max-w-4xl mx-auto'>
        <h1 class='text-4xl font-bold text-center mb-8'>Web Security Scanner</h1>
        <p class='text-center text-gray-400 mb-8'>Melakukan pengujian keamanan dasar pada website Anda. Ingat, ini bukan alat profesional.</p>
";

// --- Fungsi untuk mengambil header HTTP ---
function get_headers_from_url($url) {
    echo "<div class='result-box bg-gray-700'>";
    echo "<h2 class='text-xl font-bold mb-2'>Mengambil Header HTTP dari: <span class='text-yellow-300'>{$url}</span></h2>";
    try {
        $headers = @get_headers($url, 1);
        if ($headers === false) {
            echo "<p class='text-red-400'>[ERROR] Gagal mengambil header. Pastikan URL benar dan dapat diakses.</p>";
            echo "</div>";
            return false;
        }
        echo "<h3 class='text-lg font-semibold mb-2'>Headers Ditemukan:</h3>";
        echo "<pre class='text-sm bg-gray-800 p-3 rounded overflow-x-auto'>";
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $val) {
                    echo htmlspecialchars($name) . ": " . htmlspecialchars($val) . "\n";
                }
            } else {
                echo htmlspecialchars($name) . ": " . htmlspecialchars($value) . "\n";
            }
        }
        echo "</pre>";
        echo "</div>";
        return $headers;
    } catch (Exception $e) {
        echo "<p class='text-red-400'>[ERROR] Terjadi kesalahan: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        return false;
    }
}

// --- Fungsi untuk menganalisis Security Headers ---
function analyze_security_headers($headers) {
    echo "<div class='result-box'>";
    echo "<h2 class='text-xl font-bold mb-2'>Analisis HTTP Security Headers</h2>";
    if (!$headers) {
        echo "<p class='text-yellow-400'>Tidak ada header untuk dianalisis.</p>";
        echo "</div>";
        return;
    }

    $issues_found = false;

    // Strict-Transport-Security (HSTS)
    if (isset($headers['Strict-Transport-Security'])) {
        echo "<p class='text-green-400'><span class='font-bold'>[OK]</span> Strict-Transport-Security (HSTS) Ditemukan. Ini bagus untuk HTTPS.</p>";
    } else {
        echo "<p class='text-yellow-400'><span class='font-bold'>[PERINGATAN]</span> Strict-Transport-Security (HSTS) Tidak Ditemukan. Disarankan untuk memaksa HTTPS.</p>";
        $issues_found = true;
    }

    // Content-Security-Policy (CSP)
    if (isset($headers['Content-Security-Policy'])) {
        echo "<p class='text-green-400'><span class='font-bold'>[OK]</span> Content-Security-Policy (CSP) Ditemukan. Sangat direkomendasikan untuk mencegah XSS dan injeksi data.</p>";
    } else {
        echo "<p class='text-red-400'><span class='font-bold'>[FAIL]</span> Content-Security-Policy (CSP) Tidak Ditemukan. Rentan terhadap XSS.</p>";
        $issues_found = true;
    }

    // X-Content-Type-Options
    if (isset($headers['X-Content-Type-Options']) && strtolower($headers['X-Content-Type-Options']) === 'nosniff') {
        echo "<p class='text-green-400'><span class='font-bold'>[OK]</span> X-Content-Type-Options: nosniff Ditemukan. Mencegah MIME-sniffing.</p>";
    } else {
        echo "<p class='text-yellow-400'><span class='font-bold'>[PERINGATAN]</span> X-Content-Type-Options: nosniff Tidak Ditemukan. Berpotensi MIME-sniffing.</p>";
        $issues_found = true;
    }

    // X-Frame-Options
    if (isset($headers['X-Frame-Options'])) {
        $xfo = strtolower($headers['X-Frame-Options']);
        if ($xfo === 'deny' || $xfo === 'sameorigin') {
            echo "<p class='text-green-400'><span class='font-bold'>[OK]</span> X-Frame-Options: {$headers['X-Frame-Options']} Ditemukan. Mencegah Clickjacking.</p>";
        } else {
            echo "<p class='text-yellow-400'><span class='font-bold'>[PERINGATAN]</span> X-Frame-Options: Ditemukan tetapi nilainya ('" . htmlspecialchars($headers['X-Frame-Options']) . "') mungkin tidak optimal. Seharusnya 'DENY' atau 'SAMEORIGIN'.</p>";
            $issues_found = true;
        }
    } else {
        echo "<p class='text-red-400'><span class='font-bold'>[FAIL]</span> X-Frame-Options Tidak Ditemukan. Website rentan Clickjacking.</p>";
        $issues_found = true;
    }

    // Referrer-Policy
    if (isset($headers['Referrer-Policy'])) {
        echo "<p class='text-green-400'><span class='font-bold'>[OK]</span> Referrer-Policy Ditemukan. Mengatur informasi referrer yang dikirim.</p>";
    } else {
        echo "<p class='text-yellow-400'><span class='font-bold'>[PERINGATAN]</span> Referrer-Policy Tidak Ditemukan. Direkomendasikan untuk privasi.</p>";
        $issues_found = true;
    }

    // Permissions-Policy (Feature-Policy)
    if (isset($headers['Permissions-Policy']) || isset($headers['Feature-Policy'])) {
        echo "<p class='text-green-400'><span class='font-bold'>[OK]</span> Permissions-Policy/Feature-Policy Ditemukan. Kontrol fitur browser yang dapat digunakan.</p>";
    } else {
        echo "<p class='text-yellow-400'><span class='font-bold'>[PERINGATAN]</span> Permissions-Policy/Feature-Policy Tidak Ditemukan. Direkomendasikan untuk keamanan dan privasi.</p>";
        $issues_found = true;
    }

    if (!$issues_found) {
        echo "<p class='text-green-400 font-bold'>👍 Header keamanan Anda terlihat cukup baik!</p>";
    } else {
        echo "<p class='text-red-400 font-bold'>⚠️ Ada beberapa masalah atau peringatan dengan header keamanan Anda. Disarankan untuk memperbaikinya.</p>";
    }
    echo "</div>";
}

// --- Fungsi untuk memeriksa Directory Listing ---
function check_directory_listing($base_url) {
    echo "<div class='result-box'>";
    echo "<h2 class='text-xl font-bold mb-2'>Memeriksa Directory Listing</h2>";
    $test_dirs = [
        $base_url . '/wp-content/', // Contoh untuk WordPress
        $base_url . '/images/',
        $base_url . '/uploads/',
        $base_url . '/css/',
        $base_url . '/js/',
        $base_url . '/vendor/', // Direktori vendor composer
        $base_url . '/node_modules/' // Direktori node modules
    ];

    $found_listing = false;
    foreach ($test_dirs as $dir) {
        try {
            $response = @file_get_contents($dir, false, stream_context_create(['http' => ['timeout' => 5]]));
            if ($response !== false && (strpos($response, '<title>Index of') !== false || strpos($response, 'Directory Listing For') !== false)) {
                echo "<p class='text-red-400'><span class='font-bold'>[FAIL]</span> Directory Listing Ditemukan di: <a href='" . htmlspecialchars($dir) . "' target='_blank' class='underline text-red-300'>" . htmlspecialchars($dir) . "</a></p>";
                $found_listing = true;
            } elseif ($response !== false && (strpos($response, '403 Forbidden') === false && strpos($response, '404 Not Found') === false)) {
                 // Jika tidak 403/404 dan tidak ada index.html/php, perlu investigasi lebih lanjut
                 // Contoh: Jika hanya menampilkan halaman kosong tanpa "Index of" tapi bukan 403/404
                 // Ini bisa menjadi indikator, namun butuh validasi manual.
            }
        } catch (Exception $e) {
            // Abaikan error koneksi, dll.
        }
    }

    if (!$found_listing) {
        echo "<p class='text-green-400'><span class='font-bold'>[OK]</span> Tidak ada Directory Listing yang jelas ditemukan pada direktori umum.</p>";
    } else {
        echo "<p class='text-red-400 font-bold'>⚠️ Directory Listing adalah risiko keamanan! Nonaktifkan di konfigurasi web server Anda (Apache: Options -Indexes, Nginx: autoindex off;).</p>";
    }
    echo "</div>";
}

// --- Fungsi untuk pengujian Reflected XSS Dasar ---
function test_basic_xss($url_to_test, $param_name = 'q') {
    echo "<div class='result-box'>";
    echo "<h2 class='text-xl font-bold mb-2'>Pengujian Reflected XSS Dasar pada: <span class='text-yellow-300'>{$url_to_test}</span></h2>";
    echo "<p class='text-sm text-gray-400 mb-2'>Mencoba menyuntikkan payload XSS sederhana melalui parameter URL dan memeriksa pantulan.</p>";

    $payloads = [
        "<script>alert('XSS')</script>",
        "<img src=x onerror=alert('XSS')>",
        "\" onmouseover=alert(1) x=\"",
        "';alert(1)//"
    ];

    $xss_found = false;
    foreach ($payloads as $payload) {
        $encoded_payload = urlencode($payload);
        $full_test_url = "{$url_to_test}?{$param_name}={$encoded_payload}";

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $full_test_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Ikuti redirect
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                echo "<p class='text-yellow-400'>[INFO] Gagal mengakses URL: " . htmlspecialchars($full_test_url) . "</p>";
                continue;
            }

            // Periksa apakah payload mentah muncul kembali di respons HTML
            if (strpos($response, $payload) !== false) {
                echo "<p class='text-red-400'><span class='font-bold'>[FAIL]</span> Potensi Reflected XSS Ditemukan! Payload <span class='font-mono text-red-300'>" . htmlspecialchars($payload) . "</span> dipantulkan tanpa sanitasi di: <a href='" . htmlspecialchars($full_test_url) . "' target='_blank' class='underline text-red-300'>" . htmlspecialchars($full_test_url) . "</a></p>";
                $xss_found = true;
                break; // Hentikan setelah menemukan satu
            }
        } catch (Exception $e) {
            echo "<p class='text-yellow-400'>[INFO] Error saat menguji " . htmlspecialchars($payload) . ": " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    if (!$xss_found) {
        echo "<p class='text-green-400'><span class='font-bold'>[OK]</span> Tidak ada Reflected XSS dasar yang jelas ditemukan dengan payload ini.</p>";
    } else {
        echo "<p class='text-red-400 font-bold'>⚠️ Website Anda mungkin rentan terhadap Reflected XSS. Selalu lakukan sanitasi dan escape input pengguna.</p>";
    }
    echo "</div>";
}


// --- Jalankan Pengujian ---

echo "<h2 class='text-2xl font-bold mb-4 mt-8'>Hasil Pengujian</h2>";

// 1. Uji Security Headers pada base URL
$headers = get_headers_from_url($base_url);
analyze_security_headers($headers);

// 2. Cek Directory Listing
check_directory_listing($base_url);

// 3. Uji Reflected XSS pada halaman contoh (log_wars.php atau halaman lain yang menerima GET param)
// Pastikan halaman ini benar-benar ada dan menerima parameter GET
test_basic_xss($test_url, 'search_query'); // Ganti 'search_query' dengan nama parameter GET yang sering Anda gunakan, misal 'category'

echo "<div class='result-box bg-gray-700 result-warn'>
    <h2 class='text-xl font-bold'>Catatan Penting:</h2>
    <ul class='list-disc pl-5 mt-2 text-sm text-gray-300'>
        <li>Ini adalah pengujian keamanan yang sangat dasar. Kerentanan yang lebih kompleks (seperti SQL Injection, CSRF, File Upload, IDOR, dll.) memerlukan alat dan keahlian khusus.</li>
        <li>Selalu gunakan alat seperti OWASP ZAP atau Burp Suite untuk pemindaian yang lebih mendalam.</li>
        <li>HAPUS file ini dari server Anda setelah selesai pengujian untuk mencegah penyalahgunaan.</li>
        <li>Pastikan website Anda selalu diperbarui (CMS, plugin, framework).</li>
        <li>Gunakan HTTPS untuk seluruh website Anda.</li>
        <li>Lakukan validasi dan sanitasi ketat pada SEMUA input pengguna.</li>
    </ul>
</div>";

echo "</div></body></html>";
?>
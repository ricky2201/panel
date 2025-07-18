<?php
function approveAndAddToFirewall(int $id, $connPanel): void
{
    // Ambil IP dari database
    $sql = "SELECT ip_address FROM NRSPanel.dbo.whitelist_ip WHERE id = ?";
    $stmt = sqlsrv_query($connPanel, $sql, [$id]);
    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$data) return;

    $ip = trim($data['ip_address']);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return;

    // Update status whitelist di DB
    $update = "UPDATE NRSPanel.dbo.whitelist_ip SET status = 'approved', approved_at = GETDATE() WHERE id = ?";
    sqlsrv_query($connPanel, $update, [$id]);

    $maxIpsPerRule = 100;
    $selectedRule = null;

    // Cek semua rule "Whitelist IP *"
    for ($i = 1; $i <= 99; $i++) {
        $ruleName = "Whitelist IP $i";
        $output = shell_exec("netsh advfirewall firewall show rule name=\"$ruleName\"");
        if (strpos($output, "No rules match") !== false) {
            // Rule belum ada, kita buat baru dan tambahkan IP ini
            shell_exec("netsh advfirewall firewall add rule name=\"$ruleName\" dir=in action=allow remoteip=$ip enable=yes");
            $selectedRule = $ruleName;
            break;
        }

        // Ambil daftar IP dari rule
        preg_match('/RemoteIP:\s+(.*)/i', $output, $matches);
        $existingIps = isset($matches[1]) ? explode(',', str_replace('/32', '', trim($matches[1]))) : [];

        // Jika belum mencapai batas, tambahkan IP di sini
        if (count($existingIps) < $maxIpsPerRule) {
            if (!in_array($ip, $existingIps)) {
                $existingIps[] = $ip;
                $ipList = implode(',', $existingIps);

                // Hapus rule lama, lalu buat ulang dengan daftar IP baru
                shell_exec("netsh advfirewall firewall delete rule name=\"$ruleName\"");
                shell_exec("netsh advfirewall firewall add rule name=\"$ruleName\" dir=in action=allow remoteip=$ipList enable=yes");

                $selectedRule = $ruleName;
            }
            break;
        }
    }

    // Log hasilnya
    $log = date('[Y-m-d H:i:s] ') . "Approved IP: $ip to rule: $selectedRule\n";
    file_put_contents(__DIR__ . '/firewall_log.txt', $log, FILE_APPEND);
}

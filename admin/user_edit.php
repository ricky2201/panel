<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Jakarta');

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token tidak valid.");
    }

    $UserNum = (int)$_POST['UserNum'];
    $UserName = $_POST['UserName'];
    $UserPass = $_POST['UserPass'];
    $UserPass2 = $_POST['UserPass2'];
    $UserType = (int)$_POST['UserType'];
    $UserPoint = (int)$_POST['UserPoint'];
    $PlayTime = (int)$_POST['PlayTime'];
    $UserEmail = $_POST['UserEmail'];
    $LastLoginDate = $_POST['LastLoginDate'];
    $Referral = trim($_POST['Referral']);

    if (!filter_var($UserEmail, FILTER_VALIDATE_EMAIL) || strlen($UserEmail) > 50) {
        $error = "Email tidak valid atau terlalu panjang.";
    } else {
        $check = sqlsrv_query($conn, "SELECT * FROM dbo.UserInfo WHERE UserNum = ?", [$UserNum]);
        $old = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);

        $query = "UPDATE dbo.UserInfo SET 
                    UserName = ?, UserPass = ?, UserPass2 = ?, UserType = ?, 
                    UserPoint = ?, PlayTime = ?, UserEmail = ?, LastLoginDate = ?, Referral = ?
                  WHERE UserNum = ?";
        $params = [
            $UserName, $UserPass, $UserPass2, $UserType,
            $UserPoint, $PlayTime, $UserEmail, $LastLoginDate, $Referral, $UserNum
        ];

        $stmt = sqlsrv_query($conn, $query, $params);
        if ($stmt) {
            $fields = [
                'UserName' => $UserName,
                'UserPass' => $UserPass,
                'UserPass2' => $UserPass2,
                'UserType' => $UserType,
                'UserPoint' => $UserPoint,
                'PlayTime' => $PlayTime,
                'UserEmail' => $UserEmail,
                'LastLoginDate' => $LastLoginDate,
                'Referral' => $Referral
            ];

            $log = "=== EDIT USERNUM: $UserNum | TANGGAL: ".date("Y-m-d H:i:s")." (WIB) ===\n";
            $log .= "Admin: {$_SESSION['userid']}\n";
            foreach ($fields as $key => $newVal) {
                $oldVal = $old[$key];
                if ($oldVal instanceof DateTime) {
                    $oldVal = $oldVal->format('Y-m-d H:i:s');
                }
                if ($oldVal != $newVal) {
                    $log .= "$key: '$oldVal' -> '$newVal'\n";
                }
            }
            $log .= "===============================\n\n";
            file_put_contents(__DIR__ . "/log_editakun.txt", $log, FILE_APPEND);

            header("Location: user_edit?UserNum=$UserNum&status=success");
            exit;
        } else {
            header("Location: user_edit?UserNum=$UserNum&status=error");
            exit;
        }
    }
}

if (!isset($_GET['UserNum'])) {
    die("User ID tidak ditemukan.");
}
$UserNum = (int)$_GET['UserNum'];

$query = "SELECT * FROM dbo.UserInfo WHERE UserNum = ?";
$params = [$UserNum];
$stmt = sqlsrv_query($conn, $query, $params);
if (!$stmt || !($user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    die("Data tidak ditemukan.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Edit User</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-10">
<?php if (isset($_GET['status'])): ?>
<div id="popup" class="fixed inset-0 flex items-center justify-center z-50">
    <div class="bg-white text-gray-800 px-6 py-4 rounded-lg shadow-lg border border-gray-300">
        <p class="text-lg font-semibold">
            <?= $_GET['status'] === 'success' ? '✅ Data berhasil diperbarui.' : '❌ Gagal memperbarui data.' ?>
        </p>
    </div>
    <script>
    setTimeout(() => {
        const popup = document.getElementById('popup');
        if (popup) popup.remove();
    }, 2000);
    </script>
</div>
<?php endif; ?>

<div class="max-w-3xl mx-auto bg-gray-800 p-8 rounded shadow">
  <h2 class="text-2xl font-bold mb-6">✏️ Edit User</h2>

  <?php if (isset($error)): ?>
    <p class="bg-red-600 p-3 mb-4 rounded"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="UserNum" value="<?= $user['UserNum'] ?>">

    <div class="mb-4">
      <label class="block font-semibold">Username</label>
      <input type="text" name="UserName" class="w-full p-2 rounded text-black" value="<?= htmlspecialchars($user['UserName']) ?>" required>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block font-semibold">Password</label>
        <input type="text" name="UserPass" class="w-full p-2 rounded text-black" value="<?= htmlspecialchars($user['UserPass']) ?>">
      </div>
      <div>
        <label class="block font-semibold">Pin</label>
        <input type="text" name="UserPass2" class="w-full p-2 rounded text-black" value="<?= htmlspecialchars($user['UserPass2']) ?>">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block font-semibold">UserType</label>
        <input type="number" name="UserType" class="w-full p-2 rounded text-black" value="<?= $user['UserType'] ?>">
      </div>
      <div>
        <label class="block font-semibold">PlayTime</label>
        <input type="number" name="PlayTime" class="w-full p-2 rounded text-black" value="<?= $user['PlayTime'] ?>">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block font-semibold">UserPoint</label>
        <input type="number" name="UserPoint" class="w-full p-2 rounded text-black" value="<?= $user['UserPoint'] ?>">
      </div>
      <div class="mb-4">
      <label class="block font-semibold">Referral</label>
      <input type="text" name="Referral" class="w-full p-2 rounded text-black" value="<?= htmlspecialchars($user['Referral']) ?>">
      </div>
    </div>

    <div class="mb-4">
      <label class="block font-semibold">Email</label>
      <input type="email" name="UserEmail" class="w-full p-2 rounded text-black" value="<?= htmlspecialchars($user['UserEmail']) ?>">
    </div>

    <div class="mb-6">
      <label class="block font-semibold">Last Login Date</label>
      <input type="text" name="LastLoginDate" readonly class="w-full p-2 rounded bg-gray-700 text-white"
          value="<?= isset($user['LastLoginDate']) ? $user['LastLoginDate']->format('Y-m-d H:i:s') : 'Tidak tersedia' ?>">
    </div>

    <div class="flex justify-between">
      <a href="user_manage" class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded font-bold">🔙 Kembali</a>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded font-bold">💾 Simpan Perubahan</button>
    </div>
  </form>
</div>

</body>
</html>

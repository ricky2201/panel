<?php
include '../auth/cek_session.php';
include '../config/db.php';

if (!isset($_GET['userid']) || empty($_GET['userid'])) {
    echo "<h2 class='text-red-500'>User ID tidak ditemukan.</h2>";
    echo "<a href='users.php' class='text-blue-500 underline'>Kembali</a>";
    exit;
}

$userid = $_GET['userid'];

// Proses update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $params = [
        $_POST['UserID'],
        $_POST['UserPass'],
        $_POST['UserPass2'],
        $_POST['UserType'],
        $_POST['ChaName'],
        $_POST['UserEmail'],
        $_POST['UserPoint'],
        $_POST['VotePoint'],
        $_POST['UserPCIDHWID'],
        $_POST['UserPCIDMAC'],
        $_POST['LastPCIDHWID'],
        $_POST['LastPCIDMAC'],
        $_POST['UserNum'],
    ];

    $sql = "UPDATE dbo.UserInfo SET 
        UserID = ?, 
        UserPass = ?, 
        UserPass2 = ?, 
        UserType = ?, 
        ChaName = ?, 
        UserEmail = ?, 
        UserPoint = ?, 
        VotePoint = ?, 
        UserPCIDHWID = ?, 
        UserPCIDMAC = ?, 
        LastPCIDHWID = ?, 
        LastPCIDMAC = ?
        WHERE UserNum = ?";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        header("Location: users.php");
        exit();
    } else {
        $error = "Gagal menyimpan perubahan.";
    }
}

// Ambil data user
$sql = "SELECT * FROM dbo.UserInfo WHERE UserID = ?";
$stmt = sqlsrv_query($conn, $sql, [$userid]);
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$data) {
    die("User tidak ditemukan.");
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Edit User</title>
</head>
<body class="p-6 bg-gray-100">
  <h2 class="text-xl font-bold mb-4">Edit User: <?= htmlspecialchars($userid) ?></h2>
  <?php if (isset($error)) echo "<p class='text-red-500'>$error</p>"; ?>
  <form method="POST" class="bg-white p-6 rounded shadow w-full max-w-lg space-y-4">

    <input type="hidden" name="UserNum" value="<?= (int)$data['UserNum'] ?>">

    <div>
      <label class="block mb-1">UserNum</label>
      <input value="<?= (int)$data['UserNum'] ?>" class="w-full p-2 border rounded bg-gray-100" readonly>
    </div>

    <div>
      <label class="block mb-1">Username (UserID)</label>
      <input name="UserID" value="<?= htmlspecialchars($data['UserID']) ?>" class="w-full p-2 border rounded" required>
    </div>

    <div>
      <label class="block mb-1">UserPass</label>
      <input name="UserPass" value="<?= htmlspecialchars($data['UserPass']) ?>" class="w-full p-2 border rounded" required>
    </div>

    <div>
      <label class="block mb-1">UserPass2</label>
      <input name="UserPass2" value="<?= htmlspecialchars($data['UserPass2']) ?>" class="w-full p-2 border rounded" required>
    </div>

    <div>
      <label class="block mb-1">UserType</label>
      <input name="UserType" value="<?= $data['UserType'] ?>" class="w-full p-2 border rounded">
    </div>

    <div>
      <label class="block mb-1">ChaName</label>
      <input name="ChaName" value="<?= htmlspecialchars($data['ChaName']) ?>" class="w-full p-2 border rounded">
    </div>

    <div>
      <label class="block mb-1">UserEmail</label>
      <input name="UserEmail" value="<?= htmlspecialchars($data['UserEmail']) ?>" class="w-full p-2 border rounded">
    </div>

    <div>
      <label class="block mb-1">UserPoint</label>
      <input name="UserPoint" type="number" value="<?= (int)$data['UserPoint'] ?>" class="w-full p-2 border rounded">
    </div>

    <div>
      <label class="block mb-1">VotePoint</label>
      <input name="VotePoint" type="number" value="<?= (int)$data['VotePoint'] ?>" class="w-full p-2 border rounded">
    </div>

    <div>
      <label class="block mb-1">LastLoginDate</label>
      <input value="<?= isset($data['LastLoginDate']) ? $data['LastLoginDate']->format('Y-m-d H:i:s') : '' ?>" class="w-full p-2 border rounded bg-gray-100" readonly>
    </div>

    <div>
      <label class="block mb-1">UserPCIDHWID</label>
      <input name="UserPCIDHWID" value="<?= htmlspecialchars($data['UserPCIDHWID']) ?>" class="w-full p-2 border rounded">
    </div>

    <div>
      <label class="block mb-1">UserPCIDMAC</label>
      <input name="UserPCIDMAC" value="<?= htmlspecialchars($data['UserPCIDMAC']) ?>" class="w-full p-2 border rounded">
    </div>

    <div>
      <label class="block mb-1">LastPCIDHWID</label>
      <input name="LastPCIDHWID" value="<?= htmlspecialchars($data['LastPCIDHWID']) ?>" class="w-full p-2 border rounded">
    </div>

    <div>
      <label class="block mb-1">LastPCIDMAC</label>
      <input name="LastPCIDMAC" value="<?= htmlspecialchars($data['LastPCIDMAC']) ?>" class="w-full p-2 border rounded">
    </div>

    <button class="bg-blue-500 text-white px-4 py-2 rounded w-full">Simpan Perubahan</button>
    <a href="users.php" class="text-gray-600 underline block text-center mt-2">Kembali</a>
  </form>
</body>
</html>

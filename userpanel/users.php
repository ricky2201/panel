<?php
include '../auth/cek_session.php';
include '../config/db.php';

$sql = "SELECT 
    UserNum, 
    UserID, 
    UserPass, 
    UserPass2, 
    UserType, 
    ChaName, 
    UserEmail, 
    UserPoint, 
    VotePoint,
    UserPCIDHWID,
    UserPCIDMAC,
    LastPCIDHWID,
    LastPCIDMAC
FROM dbo.UserInfo";

$stmt = sqlsrv_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Manajemen User</title>
  <style>
    th, td { text-align: center; vertical-align: middle; }
    th.sortable:hover { cursor: pointer; background-color: #e2e8f0; }
  </style>
</head>
<body class="p-6 bg-gray-100 text-sm">
  <h2 class="text-2xl font-semibold mb-4">Manajemen User</h2>

  <!-- Pencarian -->
  <div class="mb-4 max-w-md">
    <input type="text" id="searchInput" placeholder="Cari Username, Email, atau ChaName..." class="w-full p-2 border rounded shadow" onkeyup="filterTable()">
  </div>

  <div class="overflow-x-auto">
    <table id="userTable" class="table-auto w-full bg-white rounded shadow border text-sm">
      <thead class="bg-gray-200">
		  <tr>
			<?php
			$headers = [
				'UserNum', 'UserID', 'UserPass', 'UserPass2', 'UserType', 'ChaName',
				'UserEmail', 'UserPoint', 'VotePoint',
				'UserPCIDHWID', 'UserPCIDMAC', 'LastPCIDHWID', 'LastPCIDMAC'
			];
			foreach ($headers as $i => $head) {
				echo "<th class='px-4 py-2 border text-center align-middle cursor-pointer bg-gray-100 hover:bg-gray-300 transition' onclick='sortTable($i)'>$head</th>";
			}
			echo "<th class='px-4 py-2 border text-center align-middle'>Aksi</th>"; // Aksi tidak bisa di-sort
			?>
		  </tr>
		</thead>
      <tbody>
        <?php
        if ($stmt) {
          while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td class='px-4 py-2 border'>" . (int)$row['UserNum'] . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['UserID']) . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['UserPass']) . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['UserPass2']) . "</td>";
            echo "<td class='px-4 py-2 border'>" . (int)$row['UserType'] . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['ChaName']) . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['UserEmail']) . "</td>";
            echo "<td class='px-4 py-2 border'>" . (int)$row['UserPoint'] . "</td>";
            echo "<td class='px-4 py-2 border'>" . (int)$row['VotePoint'] . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['UserPCIDHWID']) . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['UserPCIDMAC']) . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['LastPCIDHWID']) . "</td>";
            echo "<td class='px-4 py-2 border'>" . htmlspecialchars($row['LastPCIDMAC']) . "</td>";
            echo "<td class='px-4 py-2 border'>
                    <a href='edit_user?userid=" . urlencode($row['UserID']) . "' class='text-blue-600 underline'>Edit</a>
                  </td>";
            echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='14' class='text-red-600 px-4 py-2'>Gagal mengambil data user.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>

  <!-- Script Pencarian -->
  <script>
    function filterTable() {
      const input = document.getElementById("searchInput").value.toLowerCase();
      const rows = document.querySelectorAll("#userTable tbody tr");

      rows.forEach(row => {
        const userID = row.cells[1].textContent.toLowerCase();
        const chaName = row.cells[5].textContent.toLowerCase();
        const email = row.cells[6].textContent.toLowerCase();

        row.style.display = (userID.includes(input) || chaName.includes(input) || email.includes(input)) ? "" : "none";
      });
    }

    // Sorting kolom
    let sortDirections = {};

	function sortTable(columnIndex) {
	  const table = document.getElementById("userTable");
	  const rows = Array.from(table.tBodies[0].rows);
	  const isNumeric = rows.every(row => !isNaN(row.cells[columnIndex].innerText.trim()));
	  const dir = sortDirections[columnIndex] = !sortDirections[columnIndex];

	  rows.sort((a, b) => {
		let valA = a.cells[columnIndex].innerText.trim();
		let valB = b.cells[columnIndex].innerText.trim();

		if (isNumeric) {
		  valA = parseFloat(valA) || 0;
		  valB = parseFloat(valB) || 0;
		} else {
		  valA = valA.toLowerCase();
		  valB = valB.toLowerCase();
		}

		if (valA < valB) return dir ? -1 : 1;
		if (valA > valB) return dir ? 1 : -1;
		return 0;
	  });

	  rows.forEach(row => table.tBodies[0].appendChild(row));
	}
</script>
</body>
</html>

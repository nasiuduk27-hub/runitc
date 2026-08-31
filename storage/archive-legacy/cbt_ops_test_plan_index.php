<?php
// Mulai session dan panggil konfigurasi/header
require_once dirname(__DIR__, 3).'/includes/layout_header.php';
require_once BASE_PATH.'/includes/tad_access.php';

$canManageSupervisor = canManageTadSupervisor($pdo_run, (int) ($_SESSION['user_id'] ?? 0));

// Ambil parameter untuk Filter & Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '1';

$limit = 5; // Jumlah data per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Menyiapkan Query Data
$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(ts.spv_name LIKE ? OR ts.spv_alias LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type !== '') {
    $isCaptain = ($type === 'CAP') ? 1 : 0;
    $whereClauses[] = 'ts.captain = ?';
    $params[] = $isCaptain;
}

if ($status !== '') {
    $whereClauses[] = 'ts.status = ?';
    $params[] = $status;
}

$whereSql = '';
if (count($whereClauses) > 0) {
    $whereSql = 'WHERE '.implode(' AND ', $whereClauses);
}

// Gunakan Try Catch agar jika ada Error DB, script tidak mati mendadak
$db_error = null;
$tads = [];
$total_rows = 0;
$total_pages = 0;
$firstItem = 0;
$lastItem = 0;

try {
    // 1. Hitung Total Data (Untuk Pagination)
    $countSql = "SELECT COUNT(ts.rec_id) FROM tad_supervisor ts $whereSql";
    $stmtCount = $pdo_run->prepare($countSql);
    $stmtCount->execute($params);
    $total_rows = $stmtCount->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 2. Ambil Data
    $sql = "SELECT ts.*,
                   tr.bank_code as def_bank_code,
                   tr.bank_acc_no as def_bank_no,
                   tr.bank_acc_name as def_bank_name
            FROM tad_supervisor ts
            LEFT JOIN tad_rekening tr ON ts.rec_id = tr.tad_id AND tr.is_default = 1
            $whereSql
            ORDER BY ts.status DESC,ts.captain DESC, ts.spv_name ASC
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo_run->prepare($sql);
    $stmt->execute($params);
    $tads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cityIds = array_values(array_unique(array_filter(array_map('intval', array_column($tads, 'city_id')))));
    if (! empty($cityIds)) {
        $placeholders = implode(',', array_fill(0, count($cityIds), '?'));
        $stmtCities = $pdo_run->prepare("SELECT rec_id, nama FROM sys_kota WHERE rec_id IN ($placeholders)");
        $stmtCities->execute($cityIds);
        $cityMap = $stmtCities->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($tads as &$tad) {
            $tad['kota_nama'] = $cityMap[(int) ($tad['city_id'] ?? 0)] ?? null;
        }
        unset($tad);
    }

    $firstItem = $total_rows > 0 ? $offset + 1 : 0;
    $lastItem = min($offset + $limit, $total_rows);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<div class="py-4">
    <div class="max-w-[1400px] mx-auto sm:px-6 lg:px-8">

        <h2 class="font-semibold text-lg text-gray-800 mb-3">
            Data Master TAD (Supervisor & Captain)
        </h2>

        <div class="mb-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-brand-primary/10 text-brand-primary rounded-full text-xs font-bold">
                    Total: <?= $total_rows ?> Personel
                </span>
            </div>
            <?php if ($canManageSupervisor) { ?>
                <a href="create.php" class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition shadow-md">
                    + Tambah Data
                </a>
            <?php } ?>
        </div>

        <?php if ($db_error) { ?>
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm">
                <strong>Terjadi Kesalahan Database:</strong> <?= htmlspecialchars($db_error) ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['success'])) { ?>
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_SESSION['error'])) { ?>
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php } ?>

        <div class="mb-4 p-3 bg-white rounded-lg shadow-sm border border-gray-200">
            <form action="" method="GET" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Cari Nama / Alias</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Masukkan nama..."
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-primary focus:ring-brand-primary text-xs p-1.5 border">
                </div>

                <div class="w-48">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Jabatan</label>
                    <select name="type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-primary focus:ring-brand-primary text-xs p-1.5 border">
                        <option value="">Semua Jabatan</option>
                        <option value="SPV" <?= $type == 'SPV' ? 'selected' : '' ?>>Supervisor</option>
                        <option value="CAP" <?= $type == 'CAP' ? 'selected' : '' ?>>Captain</option>
                    </select>
                </div>

                <div class="w-40">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status</label>
                    <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-primary focus:ring-brand-primary text-xs p-1.5 border">
                        <!-- <option value="">Semua Status</option> -->
                        <option value="ALL" <?= $status === 'ALL' ? 'selected' : '' ?>>ALL Status</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Suspend</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-brand-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-primaryHover transition shadow-sm">
                        Filter
                    </button>
                    <?php if ($search || $type || $status !== '') { ?>
                        <a href="?" class="inline-flex items-center px-3 py-1.5 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 transition">
                            Reset
                        </a>
                    <?php } ?>
                </div>
            </form>
        </div>

        <div class="bg-white overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-brand-primary">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2.5">Nama Lengkap</th>
                            <th class="px-4 py-2.5 text-center">Jabatan</th>
                            <th class="px-4 py-2.5 text-center">Status</th>
                            <th class="px-4 py-2.5 text-center">Level</th>
                            <th class="px-4 py-2.5">Pengalaman/Kemampuan</th>
                            <?php if ($canManageSupervisor) { ?>
                                <th class="px-4 py-2.5 text-center w-28">Aksi</th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($tads) > 0) { ?>
                            <?php foreach ($tads as $tad) { ?>
                                <?php
                                    $detailData = [
                                        'id' => (int) $tad['rec_id'],
                                        'name' => $tad['spv_name'] ?? '',
                                        'alias' => $tad['spv_alias'] ?? '',
                                        'jabatan' => ((int) ($tad['captain'] ?? 0) === 1) ? 'CAPTAIN' : 'SUPERVISOR',
                                        'status' => ((int) ($tad['status'] ?? 0) === 1) ? 'Active' : 'Suspend',
                                        'gender' => in_array(strtoupper((string) ($tad['gender'] ?? '')), ['F', 'P'], true) ? 'Perempuan' : 'Laki-laki',
                                        'level' => $tad['lvl_spv'] ?? '-',
                                        'skills' => $tad['skills_notes'] ?? '',
                                        'email' => $tad['email'] ?? '',
                                        'phone' => $tad['phone'] ?? '',
                                        'address' => $tad['address'] ?? '',
                                        'city' => $tad['kota_nama'] ?? '',
                                        'bank_code' => $tad['def_bank_code'] ?? '',
                                        'bank_no' => $tad['def_bank_no'] ?? '',
                                        'bank_name' => $tad['def_bank_name'] ?? '',
                                        'photo' => ! empty($tad['photo_path']) ? BASE_URL.'/'.ltrim($tad['photo_path'], '/') : '',
                                        'can_edit' => $canManageSupervisor,
                                    ];
                                ?>
                                <tr class="hover:bg-gray-50 transition cursor-pointer" onclick='openTadDetail(<?= json_encode($detailData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <?php if (! empty($tad['photo_path'])) { ?>
                                                    <img class="h-8 w-8 rounded-full object-cover border border-gray-200 cursor-pointer hover:opacity-80 hover:scale-110 transition shadow-sm"
                                                        src="<?= BASE_URL.'/'.htmlspecialchars(ltrim($tad['photo_path'], '/')) ?>"
                                                        alt="<?= htmlspecialchars($tad['spv_name']) ?>"
                                                        onclick="event.stopPropagation(); showImageModal(this.src, this.alt)">
                                                <?php } else { ?>
                                                    <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-brand-primary font-bold border border-blue-200 select-none text-xs">
                                                        <?= strtoupper(substr($tad['spv_name'], 0, 1)) ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-xs font-bold text-gray-900"><?= htmlspecialchars($tad['spv_name']) ?></div>
                                                <?php if (! empty($tad['spv_alias'])) { ?>
                                                    <div class="text-xs text-gray-400">Alias: <?= htmlspecialchars($tad['spv_alias']) ?></div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2.5 text-center">
                                        <?php if ($tad['captain'] == 1) { ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800 border border-green-200">CAPTAIN</span>
                                        <?php } else { ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">SUPERVISOR</span>
                                        <?php } ?>
                                    </td>

                                    <td class="px-4 py-2.5 text-center">
                                        <?php if ($tad['status'] == 1) { ?>
                                            <span class="text-green-600 font-bold text-xs uppercase tracking-wider">Active</span>
                                        <?php } else { ?>
                                            <span class="text-red-500 font-bold text-xs uppercase tracking-wider">Suspend</span>
                                        <?php } ?>
                                    </td>

                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center justify-center min-w-7 px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-bold"><?= htmlspecialchars($tad['lvl_spv'] ?? '-') ?></span>
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <div class="text-xs text-gray-600 max-w-[360px] truncate" title="<?= htmlspecialchars($tad['skills_notes'] ?? '') ?>">
                                            <?= htmlspecialchars($tad['skills_notes'] ?: '-') ?>
                                        </div>
                                    </td>
                                    <?php if ($canManageSupervisor) { ?>
                                        <td class="px-4 py-2.5 text-center" onclick="event.stopPropagation()">
                                            <div class="inline-flex items-center gap-1">
                                                <a href="edit.php?id=<?= (int) $tad['rec_id'] ?>" class="px-2 py-1 rounded bg-yellow-100 text-yellow-700 hover:bg-yellow-200 font-bold text-[10px]">Edit</a>
                                                <form action="delete.php" method="POST" onsubmit="return confirm('Hapus data TAD <?= htmlspecialchars($tad['spv_name'], ENT_QUOTES) ?>?')" class="inline">
                                                    <input type="hidden" name="id" value="<?= (int) $tad['rec_id'] ?>">
                                                    <button type="submit" class="px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 font-bold text-[10px]">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="<?= $canManageSupervisor ? 6 : 5 ?>" class="px-4 py-6 text-center text-gray-400 italic">
                                    <?= $db_error ? 'Gagal memuat data.' : 'Belum ada data TAD. Silakan klik tombol Tambah Data.' ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="text-xs text-gray-700">
                        Showing <span class="font-semibold text-gray-900"><?= $firstItem ?></span>
                        to <span class="font-semibold text-gray-900"><?= $lastItem ?></span>
                        of <span class="font-semibold text-gray-900"><?= $total_rows ?></span> results
                    </div>

                    <?php if ($total_pages > 1) { ?>
                        <nav class="inline-flex rounded-md shadow-sm">
                            <?php
                            $queryParams = $_GET;
                        // Tombol Prev
                        if ($page > 1) {
                            $queryParams['page'] = $page - 1;
                            $prevUrl = '?'.http_build_query($queryParams);
                            echo '<a href="'.$prevUrl.'" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">Prev</a>';
                        } else {
                            echo '<span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">Prev</span>';
                        }

                        // Nomor Halaman
                        for ($i = 1; $i <= $total_pages; $i++) {
                            $queryParams['page'] = $i;
                            $pageUrl = '?'.http_build_query($queryParams);
                            if ($i == $page) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-brand-primary bg-blue-50 text-sm font-medium text-brand-primary">'.$i.'</span>';
                            } else {
                                echo '<a href="'.$pageUrl.'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">'.$i.'</a>';
                            }
                        }

                        // Tombol Next
                        if ($page < $total_pages) {
                            $queryParams['page'] = $page + 1;
                            $nextUrl = '?'.http_build_query($queryParams);
                            echo '<a href="'.$nextUrl.'" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">Next</a>';
                        } else {
                            echo '<span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">Next</span>';
                        }
                        ?>
                        </nav>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="tadDetailModal" class="fixed inset-0 z-[90] hidden overflow-y-auto bg-black bg-opacity-50 p-4" onclick="closeTadDetail()">
    <div class="min-h-full flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full overflow-hidden" onclick="event.stopPropagation()">
            <div class="px-5 py-3 border-b flex items-center justify-between bg-gray-50">
                <div>
                    <h3 id="detailName" class="text-base font-bold text-gray-900">-</h3>
                    <p id="detailAlias" class="text-xs text-gray-500 mt-0.5">-</p>
                </div>
                <button type="button" onclick="closeTadDetail()" class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
            </div>

            <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="md:col-span-1">
                    <div class="w-28 h-28 rounded-2xl overflow-hidden bg-gray-100 border mx-auto flex items-center justify-center">
                        <img id="detailPhoto" src="" class="hidden w-full h-full object-cover" alt="Foto TAD">
                        <i id="detailPhotoIcon" class="fas fa-user-circle text-6xl text-gray-300"></i>
                    </div>
                    <div class="mt-3 text-center space-y-1.5">
                        <span id="detailJabatan" class="inline-block px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 text-xs font-bold">-</span>
                        <span id="detailStatus" class="inline-block px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-bold">-</span>
                    </div>
                </div>

                <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                        <div class="text-xs uppercase font-bold text-gray-400">Level</div>
                        <div id="detailLevel" class="font-semibold text-gray-800">-</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-bold text-gray-400">Kota/Kabupaten</div>
                        <div id="detailCity" class="font-semibold text-gray-800">-</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-bold text-gray-400">Jenis Kelamin</div>
                        <div id="detailGender" class="font-semibold text-gray-800">-</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-bold text-gray-400">Email</div>
                        <div id="detailEmail" class="font-semibold text-gray-800 break-all">-</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-bold text-gray-400">Telepon/WA</div>
                        <div id="detailPhone" class="font-semibold text-gray-800">-</div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs uppercase font-bold text-gray-400">Alamat</div>
                        <div id="detailAddress" class="text-gray-800">-</div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs uppercase font-bold text-gray-400">Rekening Default</div>
                        <div id="detailBank" class="text-gray-800">-</div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs uppercase font-bold text-gray-400">Pengalaman/Kemampuan</div>
                        <div id="detailSkills" class="text-gray-800 whitespace-pre-line">-</div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 border-t bg-gray-50 flex justify-end gap-2">
                <button type="button" onclick="closeTadDetail()" class="px-3 py-1.5 rounded-lg bg-gray-200 text-gray-700 text-xs font-bold hover:bg-gray-300">Tutup</button>
                <a id="detailEditLink" href="#" class="hidden px-3 py-1.5 rounded-lg bg-yellow-500 text-white text-xs font-bold hover:bg-yellow-600">Edit</a>
            </div>
        </div>
    </div>
</div>

<div id="imageModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black bg-opacity-90 flex items-center justify-center" onclick="closeImageModal()">
    <div class="relative max-w-4xl w-full p-4 flex flex-col items-center">
        <button class="absolute top-0 right-4 text-gray-200 hover:text-white focus:outline-none mt-4 mr-4" onclick="closeImageModal()">
            <svg class="w-10 h-10 drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        <img id="modalImage" src="" alt="Preview" class="max-h-[85vh] w-auto rounded-lg shadow-2xl object-contain border-4 border-white" onclick="event.stopPropagation()">
        <p id="modalCaption" class="mt-4 text-white text-xl font-bold tracking-wide drop-shadow-md bg-black/50 px-4 py-1 rounded-full"></p>
    </div>
</div>

<script>
    function setDetailText(id, value) {
        document.getElementById(id).innerText = value && String(value).trim() !== '' ? value : '-';
    }

    function openTadDetail(data) {
        setDetailText('detailName', data.name);
        setDetailText('detailAlias', data.alias ? `Alias: ${data.alias}` : 'Alias: -');
        setDetailText('detailJabatan', data.jabatan);
        setDetailText('detailStatus', data.status);
        setDetailText('detailGender', data.gender);
        setDetailText('detailLevel', data.level);
        setDetailText('detailCity', data.city);
        setDetailText('detailEmail', data.email);
        setDetailText('detailPhone', data.phone);
        setDetailText('detailAddress', data.address);
        setDetailText('detailSkills', data.skills);

        const bankText = [data.bank_code, data.bank_no, data.bank_name ? `a.n ${data.bank_name}` : '']
            .filter(Boolean)
            .join(' - ');
        setDetailText('detailBank', bankText);

        const photo = document.getElementById('detailPhoto');
        const icon = document.getElementById('detailPhotoIcon');
        if (data.photo) {
            photo.src = data.photo;
            photo.classList.remove('hidden');
            icon.classList.add('hidden');
        } else {
            photo.src = '';
            photo.classList.add('hidden');
            icon.classList.remove('hidden');
        }

        const editLink = document.getElementById('detailEditLink');
        if (data.can_edit) {
            editLink.href = `edit.php?id=${data.id}`;
            editLink.classList.remove('hidden');
        } else {
            editLink.href = '#';
            editLink.classList.add('hidden');
        }

        document.getElementById('tadDetailModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeTadDetail() {
        document.getElementById('tadDetailModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function showImageModal(src, alt) {
        const modal = document.getElementById('imageModal');
        const img = document.getElementById('modalImage');
        const caption = document.getElementById('modalCaption');

        img.src = src;
        caption.innerText = alt;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeTadDetail();
            closeImageModal();
        }
    });


    // Script untuk mematikan layar loading setelah seluruh halaman selesai dimuat
    window.addEventListener('load', function() {
        const loader = document.getElementById('global-loader');
        if (loader) {
            // Beri efek transisi memudar (fade out)
            loader.style.opacity = '0';

            // Hapus elemen dari layar setelah 300ms (sesuai durasi CSS transition)
            setTimeout(function() {
                loader.style.display = 'none';
            }, 300);
        }
    });

</script>

<?php
require_once dirname(__DIR__, 3).'/includes/layout_footer.php';
?>


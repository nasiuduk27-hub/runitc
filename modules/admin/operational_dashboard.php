<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config.php';
require_once BASE_PATH . '/includes/menu_guard.php';
/** @var \PDO $pdo_run */
/** @var \PDO $pdo */
/** @var \PDO $pdo_war */
requireMenuAccess($pdo_run, 'modules/admin/operational_dashboard.php');
requireSuperadmin($pdo_run);

if (isset($_GET['api'])) {
    try {
        $ctrl = new OperationalDashboardController($pdo, $pdo_run, $pdo_war);
        $ctrl->handleApi();
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

try {
    $ctrl = new OperationalDashboardController($pdo, $pdo_run, $pdo_war);
    $model = $ctrl->getModel();
} catch (Throwable $e) {
    $model = null;
}

$today = date('Y-m-d');
$filterDate = $_GET['date'] ?? $today;
$filterClientId = !empty($_GET['client_id']) ? (int) $_GET['client_id'] : null;

$roomSummary = ['total_rooms' => 0,'active'=>0,'completed'=>0,'pending'=>0,'error'=>0,'waiting'=>0];
$partSummary = ['total'=>0,'assigned'=>0,'unassigned'=>0,'finished'=>0];
$spvStatus = ['active_spv'=>0,'total_assignments'=>0,'rooms_pending_assignment'=>0,'total_spv'=>0];
$crcStatus = ['total_rooms'=>0,'uploaded'=>0,'pending'=>0];
$baStatus = ['total_rooms'=>0,'complete'=>0,'pending'=>0];
$locDist = []; $incidents = []; $activities = []; $roomList = ['rooms'=>[],'total'=>0]; $clientOptions = [];

if ($model) {
    try {
        $roomSummary = $model->getRoomStatusSummary($filterDate, $filterClientId);
        $partSummary = $model->getParticipantSummary($filterDate, $filterClientId);
        $spvStatus = $model->getSPVDistributionStatus($filterDate, $filterClientId);
        $crcStatus = $model->getCRCUploadStatus($filterDate, $filterClientId);
        $baStatus = $model->getBeritaAcaraStatus($filterDate, $filterClientId);
        $locDist = $model->getParticipantsByLocation($filterDate, $filterClientId);
        $incidents = $model->getIncidents($filterDate, $filterClientId);
        $activities = $model->getRecentActivity($filterDate, 15);
        $roomList = $model->getRoomList(['date'=>$filterDate,'client_id'=>$filterClientId], 1, 50);
        $clientOptions = $model->getDistinctClients($filterDate);
    } catch (Throwable $e) {}
}

$pageTitle = 'Operational Control Center';
include BASE_PATH . '/includes/layout_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/compact-admin.css">

<div class="dashboard-wrapper min-h-screen bg-brand-bg p-6">
  <div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-extrabold text-gray-900 tracking-tight">Operational Control Center</h1>
        <p class="text-sm text-gray-500 mt-0.5">Pantau room, peserta, SPV, CRC &amp; Berita Acara</p>
      </div>
      <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2" id="filterForm">
          <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" class="w-36 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
          <select name="client_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
            <option value="">Semua</option>
            <?php foreach ($clientOptions as $cid => $cnm): ?>
            <option value="<?= $cid ?>" <?= $filterClientId === $cid ? 'selected' : '' ?>><?= htmlspecialchars($cnm) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="px-4 py-2 bg-brand-primary text-white text-sm font-semibold rounded-lg hover:bg-brand-primaryHover transition shadow-sm">Filter</button>
        </form>
        <button onclick="manualRefresh()" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-200 transition"><i class="fa-solid fa-rotate mr-1"></i>Refresh</button>
        <span class="text-xs text-gray-400"><span id="autoRefreshDot" class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>Auto</span>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-5 gap-4">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-green-500">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Active Rooms</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><span id="statActiveRooms"><?= $roomSummary['active'] ?? 0 ?></span> <span class="text-base text-gray-400">/ <span id="statTotalRooms"><?= $roomSummary['total_rooms'] ?? 0 ?></span></span></div>
        <div class="text-xs text-gray-400 mt-1"><span id="statCompletedRooms"><?= $roomSummary['completed'] ?? 0 ?></span> selesai &middot; <span id="statErrorRooms"><?= $roomSummary['error'] ?? 0 ?></span> error</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-indigo-400">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Peserta</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><span id="statPartTotal"><?= $partSummary['total'] ?? 0 ?></span></div>
        <div class="text-xs text-gray-400 mt-1"><span id="statPartAssigned"><?= $partSummary['assigned'] ?? 0 ?></span> assigned &middot; <span id="statPartUnassigned"><?= $partSummary['unassigned'] ?? 0 ?></span> unassigned</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-amber-400">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">SPV</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><span id="statActiveSpv"><?= $spvStatus['active_spv'] ?? 0 ?></span> <span class="text-base text-gray-400">/ <span id="statTotalSpv"><?= $spvStatus['total_spv'] ?? 0 ?></span></span></div>
        <div class="text-xs text-gray-400 mt-1"><span id="statPendingAssignment"><?= $spvStatus['rooms_pending_assignment'] ?? 0 ?></span> room pending</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-red-400">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">CRC</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><span id="statCrcUploaded"><?= $crcStatus['uploaded'] ?? 0 ?></span> <span class="text-base text-gray-400">/ <span id="statCrcTotal"><?= $crcStatus['total_rooms'] ?? 0 ?></span></span></div>
        <div class="text-xs text-gray-400 mt-1"><span id="statCrcPending"><?= $crcStatus['pending'] ?? 0 ?></span> pending</div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 border-l-4 border-l-purple-400">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Berita Acara</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><span id="statBaComplete"><?= $baStatus['complete'] ?? 0 ?></span> <span class="text-base text-gray-400">/ <span id="statBaTotal"><?= $baStatus['total_rooms'] ?? 0 ?></span></span></div>
        <div class="text-xs text-gray-400 mt-1"><span id="statBaPending"><?= $baStatus['pending'] ?? 0 ?></span> pending</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Left Column -->
      <div class="space-y-5">
        <!-- Room Grid -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900 text-sm">Room Monitoring</h2>
            <span class="text-xs text-gray-400">Legend: <span class="text-green-600">&#9632;</span> Active <span class="text-amber-500">&#9632;</span> Warning <span class="text-red-500">&#9632;</span> Error <span class="text-gray-400">&#9632;</span> Done</span>
          </div>
          <div class="p-4" id="roomGridContainer">
            <?php if (empty($roomList['rooms'])): ?>
            <div class="text-center py-8 text-gray-400"><i class="fa-solid fa-building text-3xl mb-2"></i><br>Tidak ada room untuk tanggal ini</div>
            <?php else: ?>
            <div class="grid grid-cols-6 gap-2" id="roomGrid">
              <?php foreach ($roomList['rooms'] as $room):
                $statusClass = match($room['status']) {
                  'active' => 'bg-green-100 text-green-700 border-green-300',
                  'error' => 'bg-red-100 text-red-700 border-red-300',
                  'completed' => 'bg-gray-100 text-gray-500 border-gray-200',
                  'pending' => 'bg-amber-100 text-amber-700 border-amber-300',
                  default => 'bg-amber-100 text-amber-700 border-amber-300'
                };
                $totalP = (int)($room['total_participants']??0);
                $finishedP = (int)($room['finished_participants']??0);
              ?>
              <div class="<?= $statusClass ?> rounded-lg border p-2 text-center cursor-pointer hover:shadow-sm transition text-[11px]" onclick="openRoomDetail('<?= htmlspecialchars($room['admin_no']) ?>')">
                <div class="font-bold text-xs"><?= htmlspecialchars($room['admin_no']) ?></div>
                <div class="text-[10px] opacity-75"><?= $room['status'] ?></div>
                <div class="text-[9px] opacity-60"><?= $finishedP ?>/<?= $totalP ?>
                  <?= $room['is_spv_assigned'] ? 'S' : '<span class="text-red-500">S</span>' ?>
                  <?= $room['is_crc_uploaded'] ? 'C' : '<span class="text-red-500">C</span>' ?>
                  <?= $room['is_ba_done'] ? 'B' : '<span class="text-red-500">B</span>' ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Peserta by Client + SPV Assignment -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
              <h2 class="font-bold text-gray-900 text-sm">Peserta by Client</h2>
            </div>
            <div class="p-4 space-y-2 text-sm">
              <?php $maxLoc = !empty($locDist) ? max(array_column($locDist, 'total')) : 1; ?>
              <?php foreach ($locDist as $loc): $pct = ($loc['total']/$maxLoc)*100; ?>
              <div>
                <div class="flex justify-between text-xs text-gray-500 mb-0.5">
                  <span><?= htmlspecialchars($loc['location']) ?></span><span><?= $loc['total'] ?></span>
                </div>
                <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full bg-brand-primary rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if (empty($locDist)): ?><div class="text-center text-gray-400 py-4 text-xs">Tidak ada data</div><?php endif; ?>
            </div>
          </div>
          <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
              <h2 class="font-bold text-gray-900 text-sm">SPV Assignment</h2>
            </div>
            <div class="p-4 text-center">
              <?php $assignedPct = $partSummary['total'] > 0 ? round(($partSummary['assigned']/$partSummary['total'])*100) : 0; ?>
              <div class="text-3xl font-bold text-brand-primary"><?= $assignedPct ?>%</div>
              <div class="text-xs text-gray-400 mt-1">Assigned</div>
              <div class="mt-2 text-xs text-gray-500">
                <span class="text-brand-primary">&#9632;</span> Assigned: <?= $partSummary['assigned'] ?? 0 ?> &nbsp;
                <span class="text-gray-200">&#9632;</span> Unassigned: <?= $partSummary['unassigned'] ?? 0 ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Room Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900 text-sm">Room List</h2>
            <input type="text" id="tableSearchInput" placeholder="Cari room..." oninput="filterTable()" class="w-40 px-3 py-1.5 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead><tr class="bg-gray-50 text-gray-500 font-semibold">
                <th class="px-3 py-2 text-left">Room</th>
                <th class="px-3 py-2 text-left">Status</th>
                <th class="px-3 py-2 text-left">Peserta</th>
                <th class="px-3 py-2 text-left">SPV</th>
                <th class="px-3 py-2 text-left">CRC</th>
                <th class="px-3 py-2 text-left">BA</th>
                <th class="px-3 py-2 text-left">Client</th>
                <th class="px-3 py-2 text-left">Update</th>
                <th class="px-3 py-2 text-left">Aksi</th>
              </tr></thead>
              <tbody id="roomTableBody" class="divide-y divide-gray-100">
                <?php foreach ($roomList['rooms'] as $room): ?>
                <tr data-name="<?= strtolower(htmlspecialchars($room['admin_no'])) ?>" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-semibold text-gray-800"><?= htmlspecialchars($room['admin_no']) ?></td>
                  <td class="px-3 py-2">
                    <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold
                      <?= match($room['status']){'active'=>'bg-green-100 text-green-700','error'=>'bg-red-100 text-red-700','completed'=>'bg-gray-100 text-gray-500','pending'=>'bg-amber-100 text-amber-700',default=>'bg-gray-100 text-gray-500'} ?>">
                      <?= $room['status'] ?>
                    </span>
                  </td>
                  <td class="px-3 py-2 text-gray-500"><?= $room['finished_participants'] ?? 0 ?>/<?= $room['total_participants'] ?? 0 ?></td>
                  <td class="px-3 py-2"><?= $room['is_spv_assigned'] ? '<span class="text-green-600">&#10003;</span>' : '<span class="text-red-500">&#10007;</span>' ?></td>
                  <td class="px-3 py-2"><?= $room['is_crc_uploaded'] ? '<span class="text-green-600">&#10003;</span>' : '<span class="text-red-500">&#10007;</span>' ?></td>
                  <td class="px-3 py-2"><?= $room['is_ba_done'] ? '<span class="text-green-600">&#10003;</span>' : '<span class="text-red-500">&#10007;</span>' ?></td>
                  <td class="px-3 py-2 text-gray-500"><?= htmlspecialchars($room['client_nm'] ?? '-') ?></td>
                  <td class="px-3 py-2 text-gray-400 font-mono text-[10px]"><?= !empty($room['lupdt']) && $room['lupdt'] !== '0000-00-00 00:00:00' ? date('H:i', strtotime($room['lupdt'])) : '-' ?></td>
                  <td class="px-3 py-2"><a href="javascript:void(0)" onclick="openRoomDetail('<?= htmlspecialchars($room['admin_no']) ?>')" class="text-brand-primary font-semibold text-[10px]">Detail</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="space-y-5">
        <!-- Incidents -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900 text-sm">Incidents</h2>
            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-semibold" id="incidentCount"><?= count($incidents) ?></span>
          </div>
          <div class="divide-y divide-gray-100 max-h-60 overflow-y-auto" id="incidentList">
            <?php if (empty($incidents)): ?>
            <div class="p-6 text-center text-gray-400"><i class="fa-solid fa-check-circle text-green-500 text-xl mb-2"></i><br>Tidak ada incident</div>
            <?php else: ?>
            <?php foreach ($incidents as $inc): ?>
            <div class="px-4 py-2.5 flex items-start gap-3">
              <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 <?= $inc['severity'] === 'critical' ? 'bg-red-500' : ($inc['severity'] === 'warning' ? 'bg-amber-400' : 'bg-green-500') ?>"></div>
              <div class="flex-1 min-w-0">
                <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold mr-1
                  <?= $inc['severity']==='critical'?'bg-red-100 text-red-700':($inc['severity']==='warning'?'bg-amber-100 text-amber-700':'bg-green-100 text-green-700') ?>">
                  <?= $inc['severity'] ?>
                </span>
                <span class="text-sm text-gray-700"><?= htmlspecialchars($inc['message']) ?></span>
                <span class="text-xs text-gray-400 ml-2"><?= date('H:i', strtotime($inc['timestamp'])) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="font-bold text-gray-900 text-sm">Recent Activity</h2>
          </div>
          <div class="divide-y divide-gray-100 max-h-60 overflow-y-auto" id="activityFeed">
            <?php if (empty($activities)): ?>
            <div class="p-6 text-center text-gray-400">Belum ada aktivitas</div>
            <?php else: ?>
            <?php foreach ($activities as $act): ?>
            <div class="px-4 py-2.5 flex items-start gap-3">
              <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 <?= $act['type']==='room_completed'||$act['type']==='crc_uploaded'?'bg-green-500':'bg-amber-400' ?>"></div>
              <div class="flex-1 min-w-0">
                <span class="text-sm text-gray-700"><?= htmlspecialchars($act['message']) ?></span>
                <span class="text-xs text-gray-400 ml-2"><?= date('H:i', strtotime($act['timestamp'])) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- CRC Upload Summary -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="font-bold text-gray-900 text-sm">CRC Upload</h2>
          </div>
          <div class="p-4 text-center">
            <div class="flex justify-center gap-8">
              <div><div class="text-2xl font-bold text-brand-primary"><?= $crcStatus['uploaded'] ?? 0 ?></div><div class="text-xs text-gray-400">Uploaded</div></div>
              <div><div class="text-2xl font-bold text-amber-500"><?= $crcStatus['pending'] ?? 0 ?></div><div class="text-xs text-gray-400">Pending</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Room Detail Modal -->
<div id="roomDetailModal" class="fixed inset-0 bg-black/40 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)closeDetail()">
  <div class="bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-3xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
      <h2 id="detailTitle" class="font-bold text-gray-900 text-lg">Room Detail</h2>
      <button onclick="closeDetail()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
    </div>
    <div id="detailContent" class="p-5 text-sm text-gray-700"></div>
  </div>
</div>

<script>
const API_BASE = 'operational_dashboard.php';
let refreshInterval = setInterval(pollSummary, 45000);

function manualRefresh() { pollSummary(); pollIncidents(); }

function pollSummary() {
  const params = new URLSearchParams(window.location.search);
  params.set('api','1'); params.set('action','summary');
  fetch(API_BASE+'?'+params.toString()).then(r=>r.json()).then(d=>{if(d.rooms)updateSummaryCards(d)}).catch(()=>{});
  pollIncidents();
}

function pollIncidents() {
  const params = new URLSearchParams(window.location.search);
  params.set('api','1'); params.set('action','incidents');
  fetch(API_BASE+'?'+params.toString()).then(r=>r.json()).then(updateIncidentPanel).catch(()=>{});
}

function updateSummaryCards(d) {
  const s=d.rooms; setText('statActiveRooms',s.active??0); setText('statTotalRooms',s.total_rooms??0);
  setText('statCompletedRooms',s.completed??0); setText('statErrorRooms',s.error??0);
  const p=d.participants; setText('statPartTotal',p.total??0); setText('statPartAssigned',p.assigned??0); setText('statPartUnassigned',p.unassigned??0);
  const spv=d.spv; setText('statActiveSpv',spv.active_spv??0); setText('statTotalSpv',spv.total_spv??0); setText('statPendingAssignment',spv.rooms_pending_assignment??0);
  const c=d.crc; setText('statCrcUploaded',c.uploaded??0); setText('statCrcTotal',c.total_rooms??0); setText('statCrcPending',c.pending??0);
  const b=d.ba; setText('statBaComplete',b.complete??0); setText('statBaTotal',b.total_rooms??0); setText('statBaPending',b.pending??0);
}

function updateIncidentPanel(incidents) {
  const list=document.getElementById('incidentList'), cnt=document.getElementById('incidentCount');
  if(!list)return; cnt.textContent=incidents.length;
  if(!incidents.length){list.innerHTML='<div class="p-6 text-center text-gray-400"><i class="fa-solid fa-check-circle text-green-500 text-xl mb-2"></i><br>Tidak ada incident</div>';return;}
  list.innerHTML=incidents.map(i=>{
    const sev=i.severity||'info';
    const dotCls=sev==='critical'?'bg-red-500':(sev==='warning'?'bg-amber-400':'bg-green-500');
    const badgeCls=sev==='critical'?'bg-red-100 text-red-700':(sev==='warning'?'bg-amber-100 text-amber-700':'bg-green-100 text-green-700');
    return `<div class="px-4 py-2.5 flex items-start gap-3"><div class="w-2 h-2 rounded-full mt-1.5 shrink-0 ${dotCls}"></div><div class="flex-1 min-w-0"><span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold mr-1 ${badgeCls}">${sev}</span><span class="text-sm text-gray-700">${escHtml(i.message)}</span><span class="text-xs text-gray-400 ml-2">${i.timestamp?new Date(i.timestamp).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}):'-'}</span></div></div>`;
  }).join('');
}

function setText(id,v){const e=document.getElementById(id);if(e)e.textContent=v;}

function openRoomDetail(adminNo) {
  document.getElementById('detailTitle').textContent='Room '+adminNo;
  document.getElementById('detailContent').innerHTML='<div class="text-center py-8 text-gray-400"><i class="fa-solid fa-spinner fa-spin text-xl"></i></div>';
  document.getElementById('roomDetailModal').classList.remove('hidden');
  document.getElementById('roomDetailModal').classList.add('flex');
  const p=new URLSearchParams();p.set('api','1');p.set('action','room_detail');p.set('admin_no',adminNo);
  fetch(API_BASE+'?'+p.toString()).then(r=>r.json()).then(renderRoomDetail).catch(()=>{
    document.getElementById('detailContent').innerHTML='<div class="text-center py-8 text-red-500">Gagal memuat data</div>';
  });
}

function renderRoomDetail(room) {
  const totalP=parseInt(room.total_participants)||0, finishedP=parseInt(room.finished_participants)||0, activeP=parseInt(room.active_participants)||0;
  const statusColor=totalP===0?'text-amber-500':(finishedP===totalP?'text-green-600':(activeP>0?'text-green-600':'text-amber-500'));
  const statusText=totalP===0?'Waiting':(finishedP===totalP?'Completed':(activeP>0?'Active':'Pending'));
  document.getElementById('detailContent').innerHTML=`
    <div class="grid grid-cols-4 gap-3 mb-4">
      <div class="bg-gray-50 rounded-xl p-3"><div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Status</div><div class="font-bold mt-1 ${statusColor}">${statusText}</div></div>
      <div class="bg-gray-50 rounded-xl p-3"><div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Peserta</div><div class="font-bold mt-1">${finishedP}/${totalP}</div></div>
      <div class="bg-gray-50 rounded-xl p-3"><div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Client</div><div class="font-bold mt-1">${escHtml(room.client_nm||'-')}</div></div>
      <div class="bg-gray-50 rounded-xl p-3"><div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Koneksi</div><div class="font-bold mt-1">${parseInt(room.conn_type)===2?'Hybrid':'Online'}</div></div>
    </div>
    <div class="mb-4"><h4 class="font-bold text-sm text-gray-800 mb-2">SPV Assignment</h4>
      ${room.batches&&room.batches.length?`<table class="w-full text-xs"><thead><tr class="bg-gray-50 font-semibold text-gray-500"><th class="px-2 py-1.5 text-left">Batch</th><th class="px-2 py-1.5 text-left">SPV</th><th class="px-2 py-1.5 text-left">Jumlah</th></tr></thead><tbody class="divide-y divide-gray-100">${room.batches.map(b=>`<tr><td class="px-2 py-1.5">${escHtml(b.batch_no||'-')}</td><td class="px-2 py-1.5">${escHtml(b.spv_name||'-')}</td><td class="px-2 py-1.5">${b.authorize_amt||0}</td></tr>`).join('')}</tbody></table>`:'<div class="text-gray-400 text-xs">Belum ada SPV</div>'}
    </div>
    <div class="mb-4"><h4 class="font-bold text-sm text-gray-800 mb-2">CRC</h4>
      ${room.crc?`<div class="bg-green-50 border border-green-200 rounded-xl p-3 text-xs"><div><span class="text-gray-500">File:</span> ${escHtml(room.crc.file_name||'-')}</div><div><span class="text-gray-500">Upload:</span> ${room.crc.uploaded_at||'-'}</div><div><span class="text-gray-500">Oleh:</span> ${escHtml(room.crc.input_by||'-')}</div></div>`:'<div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">CRC belum diupload</div>'}
    </div>
    <div class="mb-4"><h4 class="font-bold text-sm text-gray-800 mb-2">Berita Acara</h4>
      ${room.berita_acara&&room.berita_acara.file_id?`<div class="bg-green-50 border border-green-200 rounded-xl p-3 text-xs">Lengkap dengan PDF</div>`:'<div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">Berita Acara belum dibuat</div>'}
    </div>
    <div class="mb-4"><h4 class="font-bold text-sm text-gray-800 mb-2">Peserta (${room.participants?room.participants.length:0})</h4>
      <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-xl">
        <table class="w-full text-[11px]"><thead><tr class="bg-gray-50 font-semibold text-gray-500 text-[10px]"><th class="px-2 py-1.5 text-left sticky top-0 bg-gray-50">ID</th><th class="px-2 py-1.5 text-left sticky top-0 bg-gray-50">Status</th><th class="px-2 py-1.5 text-left sticky top-0 bg-gray-50">Mulai</th><th class="px-2 py-1.5 text-left sticky top-0 bg-gray-50">Selesai</th></tr></thead><tbody class="divide-y divide-gray-100">${room.participants?room.participants.map(p=>`<tr><td class="px-2 py-1.5 font-mono">${escHtml(p.authorize||'-')}</td><td class="px-2 py-1.5"><span class="inline-block px-1 py-0.5 rounded text-[9px] font-bold ${['7','8','9','c','C'].includes(p.statrec)?'bg-green-100 text-green-700':'bg-amber-100 text-amber-700'}">${escHtml(p.statrec)}</span></td><td class="px-2 py-1.5 text-gray-500">${p.start_time&&p.start_time!=='0000-00-00 00:00:00'?new Date(p.start_time).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}):'-'}</td><td class="px-2 py-1.5 text-gray-500">${p.end_time&&p.end_time!=='0000-00-00 00:00:00'?new Date(p.end_time).toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}):'-'}</td></tr>`).join(''):'<tr><td colspan="4" class="px-2 py-4 text-center text-gray-400">Tidak ada peserta</td></tr>'}</tbody></table></div>
    </div>
    <div class="flex gap-2">
      <a href="../cbt_ops/test_admin/index.php?search=${encodeURIComponent(room.admin_no)}" target="_blank" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition">Manage SPV</a>
      <a href="../cbt_ops/filing_system/main.php" target="_blank" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition">Upload CRC</a>
      <button onclick="closeDetail()" class="ml-auto px-4 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded-lg hover:bg-brand-primaryHover transition">Tutup</button>
    </div>
  `;
}

function closeDetail(){document.getElementById('roomDetailModal').classList.add('hidden');document.getElementById('roomDetailModal').classList.remove('flex');}

function filterTable() {
  const kw=document.getElementById('tableSearchInput').value.toLowerCase().trim();
  document.querySelectorAll('#roomTableBody tr').forEach(r=>{r.style.display=r.dataset.name.includes(kw)?'':'none'});
}

function escHtml(s){if(!s)return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

tailwind.config = {
  theme: { extend: { colors: { brand: { primary: '#1D4ED8', primaryHover: '#1E40AF', bg: '#f3f4f6' } } } }
}
</script>

<?php include BASE_PATH . '/includes/layout_footer.php'; ?>

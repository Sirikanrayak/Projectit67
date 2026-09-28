<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$isReviewer = is_admin($user) || !empty($user['instructor_of']);
$mine = $user['role'] === 'student' ? get_student_proposals($pdo, (int) $user['id']) : [];
$pendingList = $isReviewer ? get_pending_proposals_for_reviewer($pdo, $user) : [];

$pageTitle = 'ข้อเสนอโครงงาน | ' . site_setting($pdo, 'site_name');
$activeView = 'proposals';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <?php if ($user['role'] === 'student'): ?>
    <div class="panel">
      <div class="toolbar" style="justify-content:space-between">
        <h2 style="margin:0">📝 ข้อเสนอโครงงานของฉัน</h2>
        <a class="btn" href="proposal_form.php">➕ เสนอชื่อโครงงานใหม่</a>
      </div>
      <div class="table-wrap">
        <table id="myProposalsTable" class="display" style="width:100%">
          <thead><tr><th>วันที่เสนอ</th><th>ระดับ/กลุ่ม</th><th>จำนวนหัวข้อ</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
          <tbody>
            <?php foreach ($mine as $p): $items = get_proposal_items($pdo, (int) $p['id']); ?>
              <tr>
                <td data-order="<?= esc($p['created_at']) ?>"><?= thai_date(substr($p['created_at'], 0, 10)) ?></td>
                <td><?= esc($p['level']) ?>/<?= esc($p['student_group']) ?></td>
                <td><?= count($items) ?></td>
                <td><?= proposal_status_badge($p['status']) ?></td>
                <td><a class="btn ghost sm" href="proposal_review.php?id=<?= (int) $p['id'] ?>">👁️ ดูรายละเอียด</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php elseif ($isReviewer): ?>
    <div class="panel">
      <h2>📝 ข้อเสนอโครงงานที่รอพิจารณา</h2>
      <div class="table-wrap">
        <table id="reviewTable" class="display" style="width:100%">
          <thead><tr><th>วันที่เสนอ</th><th>นักเรียน</th><th>ระดับ/กลุ่ม</th><th>จำนวนหัวข้อ</th><th>พิจารณา</th></tr></thead>
          <tbody>
            <?php foreach ($pendingList as $p): $items = get_proposal_items($pdo, (int) $p['id']); ?>
              <tr>
                <td data-order="<?= esc($p['created_at']) ?>"><?= thai_date(substr($p['created_at'], 0, 10)) ?></td>
                <td><?= esc($p['student_name']) ?></td>
                <td><?= esc($p['level']) ?>/<?= esc($p['student_group']) ?></td>
                <td><?= count($items) ?></td>
                <td><a class="btn sm" href="proposal_review.php?id=<?= (int) $p['id'] ?>">🔍 พิจารณา</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php else: ?>
    <div class="panel"><p style="color:var(--muted);margin:0">คุณไม่มีข้อเสนอโครงงานที่ต้องพิจารณา</p></div>
  <?php endif; ?>
</main>
<script>jQuery('#myProposalsTable, #reviewTable').DataTable({ order: [[0, 'desc']] });</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

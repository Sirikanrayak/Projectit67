<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role($pdo, ['admin']);

$projects = get_all_projects($pdo);
$rows = [];
foreach ($projects as $p) {
    $a = $p['advisor'] ?: '-';
    $rows[$a] ??= ['n' => 0, 'progress' => 0, 'late' => 0, 'done' => 0, 'sum' => 0];
    $status = project_status($p);
    $rows[$a]['n']++;
    $rows[$a]['sum'] += project_progress($p['steps']);
    if ($status === 'done') $rows[$a]['done']++;
    elseif ($status === 'late') $rows[$a]['late']++;
    else $rows[$a]['progress']++;
}
uasort($rows, fn ($a, $b) => $b['n'] <=> $a['n']);

$pageTitle = 'ครูที่ปรึกษา | ระบบติดตามโครงงานนักเรียน';
$activeView = 'advisors';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="table-wrap">
    <table id="advisorsTable" class="display" style="width:100%">
      <thead>
        <tr><th>ครูที่ปรึกษา</th><th>จำนวนโครงงาน</th><th>กำลังดำเนินการ</th><th>เลยกำหนด</th><th>เสร็จสมบูรณ์</th><th>ความคืบหน้าเฉลี่ย</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $name => $r): $avg = (int) round($r['sum'] / $r['n']); ?>
          <tr class="clickable" onclick="window.location='projects.php?advisor=<?= urlencode($name) ?>'">
            <td><?= esc($name) ?></td>
            <td><?= $r['n'] ?></td>
            <td><?= $r['progress'] ?></td>
            <td><?= $r['late'] ? '<span class="badge late">' . $r['late'] . '</span>' : '0' ?></td>
            <td><?= $r['done'] ?></td>
            <td><span class="track mini-track"><span class="fill" style="display:block;width:<?= $avg ?>%"></span></span><?= $avg ?>%</td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<script>jQuery('#advisorsTable').DataTable({ order: [[1, 'desc']] });</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

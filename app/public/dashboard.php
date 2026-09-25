<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$list = visible_projects($pdo, $user);
$counts = ['progress' => 0, 'late' => 0, 'done' => 0, 'notstarted' => 0];
$sumProgress = 0;
foreach ($list as $p) {
    $counts[project_status($p)]++;
    $sumProgress += project_progress($p['steps']);
}
$avg = $list ? (int) round($sumProgress / count($list)) : 0;
$navLabel = is_admin($user) ? 'โครงงานทั้งหมด' : (is_teacher($user) ? 'โครงงานที่ปรึกษา' : 'โครงงานของฉัน');

$sorted = $list;
usort($sorted, fn ($a, $b) => project_progress($b['steps']) <=> project_progress($a['steps']));

$due = array_values(array_filter($list, function ($p) {
    if (project_status($p) === 'done' || !$p['due_date']) return false;
    $dl = days_left($p['due_date']);
    return $dl !== null && $dl <= 14;
}));
usort($due, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));

$stepCounts = [];
foreach (STEPS as $s) $stepCounts[$s] = 0;
$stepCounts['เสร็จสมบูรณ์'] = 0;
foreach ($list as $p) $stepCounts[project_current_step($p['steps'])]++;
$maxStepCount = max(1, ...array_values($stepCounts));

$recentLogs = [];
foreach ($list as $p) {
    foreach (get_project_logs($pdo, (int) $p['id']) as $log) {
        $recentLogs[] = $log + ['project' => $p];
    }
}
usort($recentLogs, fn ($a, $b) => strcmp($b['log_date'], $a['log_date']));
$recentLogs = array_slice($recentLogs, 0, 6);

$pendingCount = is_admin($user) ? (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE status='pending'")->fetch()['c'] : 0;

$pageTitle = 'ภาพรวม | ระบบติดตามโครงงานนักเรียน';
$activeView = 'dashboard';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="greet">
    <h2>สวัสดี, <?= esc($user['name']) ?></h2>
    <p>
      <?php if (is_admin($user)): ?>
        ภาพรวมโครงงานทั้งหมดในสาขาวิชา<?= $pendingCount ? " · มีผู้สมัครรออนุมัติ $pendingCount คน" : '' ?>
      <?php elseif (is_teacher($user)): ?>
        ภาพรวมโครงงานที่คุณเป็นครูที่ปรึกษา
      <?php else: ?>
        ภาพรวมโครงงานที่คุณเป็นสมาชิก
      <?php endif; ?>
    </p>
  </div>

  <div class="stats">
    <div class="stat"><div class="label"><?= esc($navLabel) ?></div><div class="value"><?= count($list) ?></div></div>
    <div class="stat"><div class="label">กำลังดำเนินการ</div><div class="value"><?= $counts['progress'] + $counts['notstarted'] ?></div></div>
    <div class="stat red"><div class="label">เลยกำหนดส่ง</div><div class="value"><?= $counts['late'] ?></div></div>
    <div class="stat green"><div class="label">เสร็จสมบูรณ์</div><div class="value"><?= $counts['done'] ?></div></div>
    <div class="stat amber"><div class="label">ความคืบหน้าเฉลี่ย</div><div class="value"><?= $avg ?>%</div></div>
    <?php if (is_admin($user)):
      $activeStudents = (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE role='student' AND status='active'")->fetch()['c']; ?>
      <div class="stat"><div class="label">นักเรียนในระบบ</div><div class="value"><?= $activeStudents ?></div></div>
    <?php endif; ?>
  </div>

  <div class="grid-2">
    <div class="panel">
      <h2>ความคืบหน้ารายโครงงาน</h2>
      <?php if (!$sorted): ?>
        <div class="empty"><?= is_admin($user) ? 'ยังไม่มีโครงงาน' : (is_teacher($user) ? "ยังไม่มีโครงงานที่ระบุ “{$user['name']}” เป็นครูที่ปรึกษา" : 'คุณยังไม่มีโครงงาน กด “เพิ่มโครงงาน” ในเมนูโครงงานของฉัน') ?></div>
      <?php else: foreach ($sorted as $p): $pr = project_progress($p['steps']); ?>
        <a class="bar-row" href="project_detail.php?id=<?= (int) $p['id'] ?>" title="<?= esc($p['title']) ?>">
          <span class="name"><?= esc($p['code'] ? $p['code'] . ' ' : '') . esc($p['title']) ?></span>
          <div class="track"><div class="fill <?= $pr === 100 ? 'done' : '' ?>" style="width:<?= $pr ?>%"></div></div>
          <span class="num"><?= $pr ?>%</span>
        </a>
      <?php endforeach; endif; ?>
    </div>
    <div class="panel">
      <h2>ใกล้ถึงกำหนดส่ง / เลยกำหนด</h2>
      <ul class="list-plain">
        <?php if (!$due): ?>
          <li class="empty" style="display:block">ไม่มีโครงงานที่ใกล้ถึงกำหนดภายใน 14 วัน</li>
        <?php else: foreach ($due as $p): $dl = days_left($p['due_date']); ?>
          <li><a href="project_detail.php?id=<?= (int) $p['id'] ?>">
            <div><div><?= esc($p['title']) ?></div><div class="sub">กำหนดส่ง <?= thai_date($p['due_date']) ?> · <?= esc($p['advisor']) ?></div></div>
            <span class="badge <?= $dl < 0 ? 'late' : 'soon' ?>"><?= esc(due_text($p)) ?></span>
          </a></li>
        <?php endforeach; endif; ?>
      </ul>
    </div>
  </div>

  <div class="grid-2">
    <div class="panel">
      <h2>จำนวนโครงงานตามขั้นตอนปัจจุบัน</h2>
      <?php foreach ($stepCounts as $label => $n): ?>
        <div class="bar-row">
          <span class="name" title="<?= esc($label) ?>"><?= esc($label) ?></span>
          <div class="track"><div class="fill <?= $label === 'เสร็จสมบูรณ์' ? 'done' : '' ?>" style="width:<?= $n / $maxStepCount * 100 ?>%"></div></div>
          <span class="num"><?= $n ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="panel">
      <h2>บันทึกความคืบหน้าล่าสุด</h2>
      <ul class="list-plain">
        <?php if (!$recentLogs): ?>
          <li class="empty" style="display:block">ยังไม่มีบันทึก</li>
        <?php else: foreach ($recentLogs as $log): ?>
          <li><a href="project_detail.php?id=<?= (int) $log['project']['id'] ?>">
            <div><div><?= esc($log['text']) ?></div>
            <div class="sub"><?= esc($log['project']['title']) ?> · <?= thai_date($log['log_date']) ?><?= $log['by_name'] ? ' · ' . esc($log['by_name']) : '' ?></div></div>
          </a></li>
        <?php endforeach; endif; ?>
      </ul>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

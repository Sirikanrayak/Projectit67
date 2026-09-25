<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$today = new DateTime('today');
$y = isset($_GET['y']) ? (int) $_GET['y'] : (int) $today->format('Y');
$m = isset($_GET['m']) ? (int) $_GET['m'] : (int) $today->format('n');
if ($m < 1 || $m > 12) $m = (int) $today->format('n');
$cur = DateTime::createFromFormat('Y-n-j', "$y-$m-1") ?: clone $today;
$y = (int) $cur->format('Y');
$m = (int) $cur->format('n');
$prev = (clone $cur)->modify('-1 month');
$next = (clone $cur)->modify('+1 month');

$list = visible_projects($pdo, $user);
$dueMap = [];
$startMap = [];
foreach ($list as $p) {
    if ($p['due_date']) $dueMap[$p['due_date']][] = $p;
    if ($p['start_date']) $startMap[$p['start_date']][] = $p;
}

$firstWeekday = (int) $cur->format('w');
$daysInMonth = (int) $cur->format('t');
$todayIso = $today->format('Y-m-d');

$timeline = array_values(array_filter($list, fn ($p) => $p['due_date']));
usort($timeline, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));

$pageTitle = 'ปฏิทิน | ระบบติดตามโครงงานนักเรียน';
$activeView = 'calendar';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="cal-toolbar">
    <div style="display:flex;gap:8px;align-items:center">
      <a class="btn ghost sm" href="calendar.php?y=<?= $prev->format('Y') ?>&m=<?= $prev->format('n') ?>">‹ ก่อนหน้า</a>
      <a class="btn ghost sm" href="calendar.php">เดือนนี้</a>
      <a class="btn ghost sm" href="calendar.php?y=<?= $next->format('Y') ?>&m=<?= $next->format('n') ?>">ถัดไป ›</a>
    </div>
    <h2 style="color:var(--brand-900)"><?= TH_MONTHS_FULL[$m - 1] . ' ' . ($y + 543) ?></h2>
  </div>

  <div class="cal-grid">
    <?php foreach (WEEKDAYS_TH as $w): ?><div class="cal-head"><?= esc($w) ?></div><?php endforeach; ?>
    <?php for ($i = 0; $i < $firstWeekday; $i++): ?><div class="cal-cell empty"></div><?php endfor; ?>
    <?php for ($d = 1; $d <= $daysInMonth; $d++):
      $iso = sprintf('%04d-%02d-%02d', $y, $m, $d);
      $isToday = $iso === $todayIso; ?>
      <div class="cal-cell<?= $isToday ? ' today' : '' ?>">
        <div class="cal-day"><?= $d ?></div>
        <?php foreach ($dueMap[$iso] ?? [] as $p): ?>
          <a class="cal-chip" href="project_detail.php?id=<?= (int) $p['id'] ?>" title="กำหนดส่ง: <?= esc($p['title']) ?>"><?= esc($p['title']) ?></a>
        <?php endforeach; ?>
        <?php foreach ($startMap[$iso] ?? [] as $p): ?>
          <a class="cal-chip start" href="project_detail.php?id=<?= (int) $p['id'] ?>" title="เริ่มโครงงาน: <?= esc($p['title']) ?>"><?= esc($p['title']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>

  <div class="panel" style="margin-top:20px">
    <h2>ไทม์ไลน์โครงงาน (เรียงตามกำหนดส่ง)</h2>
    <?php if (!$timeline): ?>
      <div class="empty">ยังไม่มีโครงงานที่กำหนดวันส่ง</div>
    <?php else: foreach ($timeline as $p): $pr = project_progress($p['steps']); ?>
      <a class="tl-row" href="project_detail.php?id=<?= (int) $p['id'] ?>">
        <div class="tl-name" title="<?= esc($p['title']) ?>"><?= esc($p['code'] ? $p['code'] . ' ' : '') . esc($p['title']) ?></div>
        <div class="tl-bar"><div class="tl-fill <?= $pr === 100 ? 'done' : '' ?>" style="width:<?= $pr ?>%"></div></div>
        <div class="tl-dates"><?= thai_date($p['start_date']) ?> – <?= thai_date($p['due_date']) ?></div>
        <?= badge_html($p) ?>
      </a>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

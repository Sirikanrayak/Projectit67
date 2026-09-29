<?php
require __DIR__ . '/../includes/bootstrap.php';

$grouped = [];
foreach (get_showcase_projects($pdo) as $p) {
    $lvl = $p['level'] ?: 'ไม่ระบุระดับชั้น';
    $grouped[$lvl][] = $p;
}
$orderedLevels = array_values(array_intersect(LEVELS, array_keys($grouped)));
foreach (array_keys($grouped) as $lvl) {
    if (!in_array($lvl, $orderedLevels, true)) $orderedLevels[] = $lvl;
}

$pageTitle = 'แสดงผลงานของนักเรียน | ' . site_setting($pdo, 'site_name');
require __DIR__ . '/../includes/layout/head.php';
$siteName = site_setting($pdo, 'site_name');
$siteSubtitle = site_setting($pdo, 'site_subtitle');
$logoUrl = site_logo_url($pdo);
?>
<header>
  <div class="wrap">
    <div class="head-row">
      <div class="brand">
        <?php if ($logoUrl): ?>
          <img class="logo logo-img" src="<?= esc($logoUrl) ?>" alt="<?= esc($siteName) ?>">
        <?php else: ?>
          <div class="logo"><?= esc(site_setting($pdo, 'site_logo_text')) ?></div>
        <?php endif; ?>
        <div>
          <h1><?= esc($siteName) ?></h1>
          <?php if ($siteSubtitle !== ''): ?><p><?= esc($siteSubtitle) ?></p><?php endif; ?>
        </div>
      </div>
      <div class="userbox">
        <a class="hbtn" href="login.php">🔒 เข้าสู่ระบบ</a>
      </div>
    </div>
  </div>
</header>
<main class="wrap">
  <h2 style="margin-top:20px">🎓 แสดงผลงานของนักเรียน</h2>
  <p style="color:var(--muted);margin-top:-8px">ผลงานโครงงานของนักเรียน แยกตามระดับชั้น</p>

  <?php if (!$grouped): ?>
    <div class="panel" style="margin-top:20px"><p style="color:var(--muted);margin:0">ยังไม่มีผลงานที่เปิดแสดง</p></div>
  <?php else: foreach ($orderedLevels as $lvl): ?>
    <div class="section-title" style="margin-top:28px"><?= esc($lvl) ?></div>
    <div class="projects">
      <?php foreach ($grouped[$lvl] as $p): $names = member_display_names($pdo, $p); ?>
        <div class="card">
          <?php if ($p['showcase_image']): ?>
            <img class="showcase-thumb" src="assets/showcase/<?= esc($p['showcase_image']) ?>" alt="<?= esc($p['title']) ?>">
          <?php else: ?>
            <div class="showcase-thumb showcase-thumb-placeholder">🖼️</div>
          <?php endif; ?>
          <h3><?= esc($p['title']) ?></h3>
          <?php if ($names): ?><div class="meta"><?= esc(implode(', ', $names)) ?></div><?php endif; ?>
          <?php if ($p['showcase_desc']): ?><p class="showcase-desc"><?= nl2br(esc($p['showcase_desc'])) ?></p><?php endif; ?>
          <?php if ($p['showcase_link']): ?>
            <a class="btn ghost sm" href="<?= esc($p['showcase_link']) ?>" target="_blank" rel="noopener">🔗 ดูผลงาน</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; endif; ?>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

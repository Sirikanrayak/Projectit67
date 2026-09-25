<?php
/** @var PDO $pdo */
/** @var array $user */
/** @var string $activeView */
$dueSoon = due_soon_projects($pdo, $user);
$pendingUsers = is_admin($user) ? (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE status='pending'")->fetch()['c'] : 0;
?>
<header>
  <div class="wrap">
    <div class="head-row">
      <div class="brand">
        <div class="logo">IT</div>
        <div>
          <h1>ระบบติดตามโครงงานนักเรียน</h1>
          <p>สาขาวิชาเทคโนโลยีสารสนเทศ · วิทยาลัยเทคนิคนครนายก</p>
        </div>
      </div>
      <div class="userbox">
        <div class="who"><span><?= esc($user['name']) ?></span><small><?= esc(ROLE_TEXT[$user['role']]) ?><?= $user['role'] === 'student' && $user['level'] ? ' · ' . esc($user['level']) . ($user['student_group'] ? '/' . esc($user['student_group']) : '') : '' ?></small></div>
        <div class="notif-wrap">
          <button type="button" class="hbtn" id="btnNotif" title="การแจ้งเตือนกำหนดส่ง">🔔<span class="count" id="notifCount"<?= $dueSoon ? '' : ' hidden' ?>><?= count($dueSoon) ?></span></button>
          <div class="notif-panel" id="notifPanel" hidden>
            <?php if (!$dueSoon): ?>
              <div class="empty" style="padding:16px">ไม่มีรายการใกล้ถึงกำหนดส่ง</div>
            <?php else: foreach ($dueSoon as $p): ?>
              <a class="notif-item" href="project_detail.php?id=<?= (int) $p['id'] ?>">
                <div title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></div>
                <span class="badge <?= days_left($p['due_date']) < 0 ? 'late' : 'soon' ?>"><?= esc(due_text($p)) ?></span>
              </a>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <a class="hbtn" href="profile.php">👤 บัญชีของฉัน</a>
        <a class="hbtn" href="logout.php" data-confirm="ยืนยันการออกจากระบบ?">🚪 ออกจากระบบ</a>
      </div>
    </div>
    <nav>
      <a class="<?= $activeView === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">📊 ภาพรวม</a>
      <a class="<?= $activeView === 'projects' ? 'active' : '' ?>" href="projects.php"><?= is_admin($user) ? '📁 โครงงานทั้งหมด' : (is_teacher($user) ? '📁 โครงงานที่ปรึกษา' : '📁 โครงงานของฉัน') ?></a>
      <a class="<?= $activeView === 'calendar' ? 'active' : '' ?>" href="calendar.php">📅 ปฏิทิน</a>
      <?php if (is_admin($user)): ?>
        <a class="<?= $activeView === 'advisors' ? 'active' : '' ?>" href="advisors.php">🧑‍🏫 ครูที่ปรึกษา</a>
        <a class="<?= $activeView === 'users' ? 'active' : '' ?>" href="users.php">👥 จัดการผู้ใช้<?php if ($pendingUsers): ?> <span class="count"><?= $pendingUsers ?></span><?php endif; ?></a>
      <?php endif; ?>
    </nav>
  </div>
</header>

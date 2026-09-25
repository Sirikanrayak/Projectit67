<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role($pdo, ['admin']);

$users = $pdo->query('SELECT * FROM users ORDER BY FIELD(status,"pending","active","suspended"), created_at DESC')->fetchAll();
$projectCounts = [];
foreach (get_all_projects($pdo) as $p) {
    foreach ($p['member_ids'] as $uid) $projectCounts[$uid] = ($projectCounts[$uid] ?? 0) + 1;
    foreach ($users as $u) {
        if ($u['role'] === 'teacher' && project_advises($p, $u)) $projectCounts[$u['id']] = ($projectCounts[$u['id']] ?? 0) + 1;
    }
}
$requireApproval = $pdo->query("SELECT value FROM settings WHERE `key`='require_approval'")->fetch()['value'] ?? '1';

$pageTitle = 'จัดการผู้ใช้ | ' . site_setting($pdo, 'site_name');
$activeView = 'users';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="toolbar">
    <select id="fRole">
      <option value="">ทุกบทบาท</option>
      <?php foreach (ROLE_TEXT as $t): ?><option><?= esc($t) ?></option><?php endforeach; ?>
    </select>
    <select id="fStatus">
      <option value="">ทุกสถานะ</option>
      <?php foreach (USER_STATUS_TEXT as $t): ?><option><?= esc($t) ?></option><?php endforeach; ?>
    </select>
    <a class="btn" href="user_form.php">➕ เพิ่มผู้ใช้</a>
  </div>
  <div class="toolbar" style="margin-top:-6px">
    <form method="post" action="user_action.php" id="approvalForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_approval">
      <label class="check">
        <input type="checkbox" name="require_approval" onchange="this.form.submit()" <?= $requireApproval === '1' ? 'checked' : '' ?>>
        นักเรียนที่สมัครใหม่ต้องรอผู้ดูแลระบบอนุมัติก่อนใช้งาน
      </label>
    </form>
  </div>

  <div class="table-wrap">
    <table id="usersTable" class="display" style="width:100%">
      <thead>
        <tr><th>ชื่อ-นามสกุล</th><th>อีเมล</th><th>รหัสนักเรียน</th><th>ชั้น/กลุ่ม</th><th>บทบาท</th><th>สถานะ</th><th>วันที่สมัคร</th><th>โครงงาน</th><th>จัดการ</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $user['id']; ?>
          <tr>
            <td><?= esc($u['name']) ?><?= $self ? ' <span class="chip">คุณ</span>' : '' ?></td>
            <td><?= esc($u['email']) ?></td>
            <td><?= esc($u['student_id']) ?: '-' ?></td>
            <td><?= $u['level'] ? esc($u['level']) . ($u['student_group'] ? '/' . esc($u['student_group']) : '') : '-' ?></td>
            <td><span class="chip <?= $u['role'] === 'student' ? '' : esc($u['role']) ?>"><?= esc(ROLE_TEXT[$u['role']]) ?></span></td>
            <td><span class="badge <?= USER_STATUS_BADGE[$u['status']] ?>"><?= esc(USER_STATUS_TEXT[$u['status']]) ?></span></td>
            <td data-order="<?= esc($u['created_at']) ?>"><?= thai_date(substr($u['created_at'], 0, 10)) ?></td>
            <td><?= $projectCounts[$u['id']] ?? 0 ?></td>
            <td class="actions">
              <?php if ($u['status'] === 'pending'): ?>
                <form method="post" action="user_action.php" style="display:inline">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="approve">
                  <button class="btn sm" type="submit">✅ อนุมัติ</button>
                </form>
              <?php endif; ?>
              <?php if ($u['status'] === 'active' && !$self): ?>
                <form method="post" action="user_action.php" style="display:inline" data-confirm="ระงับการใช้งานบัญชี <?= esc($u['name']) ?> (<?= esc($u['email']) ?>)?" data-confirm-button="ระงับ">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="suspend">
                  <button class="btn ghost sm" type="submit">⛔ ระงับ</button>
                </form>
              <?php endif; ?>
              <?php if ($u['status'] === 'suspended'): ?>
                <form method="post" action="user_action.php" style="display:inline">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="activate">
                  <button class="btn ghost sm" type="submit">🔓 เปิดใช้งาน</button>
                </form>
              <?php endif; ?>
              <a class="btn ghost sm" href="user_form.php?id=<?= (int) $u['id'] ?>">✏️ แก้ไข</a>
              <?php if (!$self): ?>
                <form method="post" action="user_action.php" style="display:inline" data-confirm="ยืนยันการลบบัญชี <?= esc($u['name']) ?> (<?= esc($u['email']) ?>)?" data-confirm-text="บัญชีจะถูกนำออกจากโครงงานที่เป็นสมาชิกด้วย" data-confirm-button="ลบผู้ใช้">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="delete">
                  <button class="btn danger sm" type="submit">🗑️ ลบ</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<script>
  const usersTable = jQuery('#usersTable').DataTable({ order: [] });
  jQuery('#fRole').on('change', function () { usersTable.column(4).search(this.value, false, false).draw(); });
  jQuery('#fStatus').on('change', function () { usersTable.column(5).search(this.value, false, false).draw(); });
</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

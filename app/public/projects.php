<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$list = visible_projects($pdo, $user);

$pageTitle = 'โครงงาน | ' . site_setting($pdo, 'site_name');
$activeView = 'projects';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="toolbar">
    <select id="fLevel">
      <option value="">ทุกระดับชั้น</option>
      <?php foreach (LEVELS as $lv): ?><option><?= esc($lv) ?></option><?php endforeach; ?>
    </select>
    <select id="fStatus">
      <option value="">ทุกสถานะ</option>
      <?php foreach (STATUS_TEXT as $k => $t): ?><option value="<?= esc($t) ?>"><?= esc($t) ?></option><?php endforeach; ?>
    </select>
    <a class="btn" href="project_form.php">➕ เพิ่มโครงงาน</a>
  </div>
  <div class="toolbar" style="justify-content:flex-end;margin-top:-6px">
    <a class="btn ghost sm" href="export_csv.php">📤 ส่งออก CSV</a>
    <?php if (is_admin($user)): ?>
      <a class="btn ghost sm" href="backup_json.php">💾 สำรองข้อมูล (JSON)</a>
      <a class="btn ghost sm" href="import_json.php">📥 นำเข้า JSON</a>
    <?php endif; ?>
  </div>

  <div class="table-wrap">
    <table id="projectsTable" class="display" style="width:100%">
      <thead>
        <tr><th>รหัส</th><th>ชื่อโครงงาน</th><th>ระดับชั้น</th><th>ผู้จัดทำ</th><th>ครูที่ปรึกษา</th><th>ความคืบหน้า</th><th>กำหนดส่ง</th><th>สถานะ</th><th>จัดการ</th></tr>
      </thead>
      <tbody>
        <?php foreach ($list as $p): $pr = project_progress($p['steps']); $status = project_status($p); $canEdit = project_can_edit($p, $user); ?>
          <tr class="clickable" onclick="window.location='project_detail.php?id=<?= (int) $p['id'] ?>'">
            <td><?= esc($p['code']) ?: '-' ?></td>
            <td><?= esc($p['title']) ?></td>
            <td data-order="<?= esc($p['level']) ?>" data-filter="<?= esc($p['level']) ?>"><?= esc($p['level']) ?></td>
            <td><?= esc(implode(', ', member_display_names($pdo, $p))) ?: '-' ?></td>
            <td><?= esc($p['advisor']) ?></td>
            <td data-order="<?= $pr ?>"><span class="track mini-track"><span class="fill <?= $pr === 100 ? 'done' : '' ?>" style="display:block;width:<?= $pr ?>%"></span></span><?= $pr ?>%</td>
            <td data-order="<?= esc($p['due_date'] ?? '') ?>"><?= thai_date($p['due_date']) ?></td>
            <td><?= badge_html($p) ?></td>
            <td class="actions" onclick="event.stopPropagation()">
              <?php if ($canEdit): ?>
                <a class="btn ghost sm" href="project_form.php?id=<?= (int) $p['id'] ?>">✏️ แก้ไข</a>
              <?php endif; ?>
              <?php if (project_can_delete($p, $user)): ?>
                <form method="post" action="project_delete.php" style="display:inline" data-confirm="ยืนยันการลบโครงงาน “<?= esc($p['title']) ?>”?" data-confirm-text="ข้อมูลจะไม่สามารถกู้คืนได้" data-confirm-button="ลบโครงงาน">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn danger sm">🗑️ ลบ</button>
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
  const projectsTable = jQuery('#projectsTable').DataTable({ order: [[6, 'asc']] });
  jQuery('#fLevel').on('change', function () { projectsTable.column(2).search(this.value ? '^' + this.value + '$' : '', true, false).draw(); });
  jQuery('#fStatus').on('change', function () { projectsTable.column(7).search(this.value, false, false).draw(); });
  <?php if (!empty($_GET['advisor'])): ?>
    projectsTable.search(<?= json_encode($_GET['advisor']) ?>).draw();
  <?php endif; ?>
</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$id = (int) ($_GET['id'] ?? 0);
$proposal = get_proposal($pdo, $id);
if (!$proposal) {
    flash_set('err', 'ไม่พบข้อเสนอโครงงาน');
    redirect('proposals.php');
}

$isOwner = $user['role'] === 'student' && (int) $proposal['student_id'] === (int) $user['id'];
$canReview = proposal_can_review($proposal, $user);
if (!$isOwner && !$canReview) {
    flash_set('err', 'คุณไม่มีสิทธิ์เข้าถึงข้อเสนอโครงงานนี้');
    redirect('proposals.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canReview && $proposal['status'] === 'pending' && ($_POST['action'] ?? '') === 'reject') {
    csrf_check();
    $note = trim($_POST['note'] ?? '');
    $pdo->prepare("UPDATE project_proposals SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?")
        ->execute([$user['name'], $note, $id]);
    flash_set('ok', 'ปฏิเสธข้อเสนอโครงงานแล้ว');
    redirect('proposals.php');
}

$items = get_proposal_items($pdo, $id);
$stmtStudent = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
$stmtStudent->execute([$proposal['student_id']]);
$student = $stmtStudent->fetch();

$pageTitle = 'พิจารณาข้อเสนอโครงงาน | ' . site_setting($pdo, 'site_name');
$activeView = 'proposals';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="panel">
    <h2>📝 ข้อเสนอโครงงานของ <?= esc($student['name'] ?? '-') ?></h2>
    <p style="color:var(--muted)">
      ระดับชั้น <?= esc($proposal['level']) ?>/<?= esc($proposal['student_group']) ?>
      · เสนอเมื่อ <?= thai_date(substr($proposal['created_at'], 0, 10)) ?>
      · สถานะ <?= proposal_status_badge($proposal['status']) ?>
    </p>
    <?php if ($proposal['member_emails']): ?><p>สมาชิกร่วม: <?= esc($proposal['member_emails']) ?></p><?php endif; ?>

    <?php foreach ($items as $it): ?>
      <div class="panel" style="margin:12px 0;background:var(--gray-bg, #f8f8f8)">
        <h3 style="margin:0 0 6px">
          <?= (int) $it['seq'] ?>. <?= esc($it['title']) ?>
          <?php if ($it['is_selected']): ?> <span class="badge done">เลือกแล้ว</span><?php endif; ?>
        </h3>
        <?php if ($it['method']): ?><p style="margin:4px 0"><strong>จะทำการศึกษาอย่างไร:</strong> <?= nl2br(esc($it['method'])) ?></p><?php endif; ?>
        <?php if ($it['benefit']): ?><p style="margin:4px 0"><strong>ผลที่คาดว่าจะได้รับ:</strong> <?= nl2br(esc($it['benefit'])) ?></p><?php endif; ?>
        <?php if ($canReview && $proposal['status'] === 'pending'): ?>
          <a class="btn sm" href="project_form.php?proposal_id=<?= $id ?>&amp;item_seq=<?= (int) $it['seq'] ?>" style="margin-top:8px;display:inline-block">✅ อนุมัติหัวข้อนี้ → สร้างโครงงาน</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($canReview && $proposal['status'] === 'pending'): ?>
      <form method="post" data-confirm="ยืนยันไม่อนุมัติข้อเสนอโครงงานนี้ทั้งหมด?" data-confirm-button="ไม่อนุมัติ" style="margin-top:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject">
        <label class="f full">เหตุผล/ข้อเสนอแนะ (ถ้ามี)
          <textarea name="note" rows="2"></textarea>
        </label>
        <button type="submit" class="btn danger">❌ ไม่อนุมัติทั้งหมด</button>
      </form>
    <?php endif; ?>

    <?php if ($proposal['status'] !== 'pending'): ?>
      <p style="margin-top:16px;color:var(--muted)">
        พิจารณาโดย <?= esc($proposal['reviewed_by']) ?> · <?= thai_date(substr((string) $proposal['reviewed_at'], 0, 10)) ?>
        <?= $proposal['review_note'] ? ' · ' . esc($proposal['review_note']) : '' ?>
      </p>
      <?php if ($proposal['status'] === 'approved' && $proposal['approved_project_id']): ?>
        <a class="btn" href="project_detail.php?id=<?= (int) $proposal['approved_project_id'] ?>">📁 ไปที่โครงงานที่อนุมัติ</a>
      <?php endif; ?>
    <?php endif; ?>

    <div class="form-actions"><a class="btn ghost" href="proposals.php">← กลับ</a></div>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$id = (int) ($_GET['id'] ?? 0);
$project = get_project($pdo, $id);
if (!$project || !project_can_edit($project, $user)) {
    flash_set('err', 'ไม่พบโครงงาน หรือคุณไม่มีสิทธิ์เข้าถึง');
    redirect('projects.php');
}

$editable = project_can_edit($project, $user);
$grading = project_can_grade($project, $user);
$pr = project_progress($project['steps']);
$accounts = member_accounts($pdo, $project);
$memberNames = member_display_names($pdo, $project);
$logs = get_project_logs($pdo, $id);
$files = get_project_files($pdo, $id);
$qcRows = get_qc_rows($pdo, $id);
$qcPassed = qc_all_passed($pdo, $id);
$gradingBlocked = $grading && $user['role'] !== 'admin' && !$qcPassed;
$progressRounds = get_progress_rounds($pdo, $id);
$defenseReq = get_defense_request($pdo, $id);
$canRateProgress = qc_can_sign($project, $user, 'any');
$ev = $project['evaluation'];
$evScores = $ev['scores'] ?? [];
$evTotal = array_sum(array_map(fn ($r) => (int) ($evScores[$r['key']] ?? 0), RUBRIC));
$maxesJson = esc(json_encode(array_column(RUBRIC, 'max', 'key')));

$pageTitle = esc($project['title']) . ' | ' . site_setting($pdo, 'site_name');
$activeView = 'projects';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="panel">
    <div class="head-row" style="margin-bottom:6px">
      <h2 style="color:var(--brand-900)"><?= esc($project['title']) ?></h2>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if (project_can_delete($project, $user)): ?>
          <form method="post" action="project_delete.php" data-confirm="ยืนยันการลบโครงงาน “<?= esc($project['title']) ?>”?" data-confirm-text="ข้อมูลจะไม่สามารถกู้คืนได้" data-confirm-button="ลบโครงงาน">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn danger">🗑️ ลบโครงงาน</button>
          </form>
        <?php endif; ?>
        <?php if ($editable): ?><a class="btn ghost" href="project_form.php?id=<?= $id ?>">✏️ แก้ไขข้อมูล</a><?php endif; ?>
        <a class="btn" href="projects.php">📁 กลับรายการ</a>
      </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
      <?= badge_html($project) ?>
      <span class="chip"><?= esc($project['level']) ?><?= $project['student_group'] ? '/' . esc($project['student_group']) : '' ?></span>
      <span class="chip"><?= esc($project['type']) ?></span>
      <span style="color:var(--muted);font-size:.9rem"><?= esc(due_text($project)) ?></span>
    </div>

    <dl class="info-grid">
      <?php if ($project['title_en']): ?><dt>ชื่อภาษาอังกฤษ</dt><dd><?= esc($project['title_en']) ?></dd><?php endif; ?>
      <dt>รหัสโครงงาน</dt><dd><?= esc($project['code']) ?: '-' ?></dd>
      <dt>ผู้จัดทำ</dt><dd><?= $memberNames ? implode('<br>', array_map('esc', $memberNames)) : '-' ?></dd>
      <dt>บัญชีสมาชิก</dt><dd><?= $accounts ? implode('<br>', array_map(fn ($u) => esc($u['name']) . ' <span style="color:var(--muted)">(' . esc($u['email']) . ')</span>', $accounts)) : '-' ?></dd>
      <dt>ครูที่ปรึกษา</dt><dd><?= esc($project['advisor']) ?><?= $project['co_advisor'] ? '<br><span style="color:var(--muted)">ร่วม: ' . esc($project['co_advisor']) . '</span>' : '' ?></dd>
      <dt>ระยะเวลา</dt><dd><?= thai_date($project['start_date']) ?> – <?= thai_date($project['due_date']) ?></dd>
      <?php if ($project['note']): ?><dt>รายละเอียด</dt><dd style="white-space:pre-line"><?= esc($project['note']) ?></dd><?php endif; ?>
    </dl>

    <div class="section-title">ขั้นตอนการดำเนินงาน (<?= $pr ?>%)</div>
    <div class="track" style="margin-bottom:12px"><div class="fill <?= $pr === 100 ? 'done' : '' ?>" style="width:<?= $pr ?>%"></div></div>
    <ul class="steps">
      <?php foreach (STEPS as $i => $label): ?>
        <li>
          <form method="post" action="project_step.php" style="width:100%;margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" value="<?= $id ?>">
            <input type="hidden" name="step_index" value="<?= $i ?>">
            <label style="width:100%">
              <input type="checkbox" name="value" <?= $project['steps'][$i] ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?> onchange="this.form.submit()">
              <span class="n"><?= $i + 1 ?></span><span class="t"><?= esc($label) ?></span>
            </label>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="section-title">ไฟล์แนบ</div>
    <?php if ($files): ?>
      <ul class="file-list">
        <?php foreach ($files as $f): ?>
          <li class="file-item">
            <span class="fname"><a href="file_download.php?id=<?= (int) $f['id'] ?>">📄 <?= esc($f['original_name']) ?></a></span>
            <span class="fsize"><?= fmt_bytes((int) $f['size']) ?></span>
            <span class="factions">
              <?php if ($editable): ?>
                <form method="post" action="project_file_delete.php" data-confirm="ยืนยันการลบไฟล์ “<?= esc($f['original_name']) ?>”?" data-confirm-button="ลบไฟล์">
                  <?= csrf_field() ?>
                  <input type="hidden" name="project_id" value="<?= $id ?>">
                  <input type="hidden" name="file_id" value="<?= (int) $f['id'] ?>">
                  <button type="submit" class="btn danger sm">🗑️ ลบ</button>
                </form>
              <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p style="color:var(--muted);margin:0 0 10px">ยังไม่มีไฟล์แนบ</p>
    <?php endif; ?>
    <?php if ($editable): ?>
      <form method="post" action="project_file_upload.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="project_id" value="<?= $id ?>">
        <label class="file-drop">
          📎 คลิกเพื่อแนบไฟล์ <small style="display:block">(สูงสุด <?= MAX_FILES_PER_PROJECT ?> ไฟล์ต่อโครงงาน · ไฟล์ละไม่เกิน <?= fmt_bytes(MAX_FILE_BYTES) ?>)</small>
          <input type="file" name="files[]" multiple onchange="this.form.submit()">
        </label>
      </form>
    <?php endif; ?>

    <div class="section-title">บันทึกความคืบหน้า</div>
    <?php if ($logs): ?>
      <ul class="log">
        <?php foreach ($logs as $log): ?>
          <li><div class="d"><?= thai_date($log['log_date']) ?><?= $log['by_name'] ? ' · ' . esc($log['by_name']) : '' ?></div><div><?= esc($log['text']) ?></div></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p style="color:var(--muted);margin:0">ยังไม่มีบันทึก</p>
    <?php endif; ?>
    <?php if ($editable): ?>
      <form class="log-add" method="post" action="project_log_add.php">
        <?= csrf_field() ?>
        <input type="hidden" name="project_id" value="<?= $id ?>">
        <input type="text" name="text" placeholder="เพิ่มบันทึก เช่น ปรับแก้บทที่ 2 ตามคำแนะนำ">
        <button class="btn">➕ เพิ่ม</button>
      </form>
    <?php endif; ?>

    <div class="section-title">รายงานความก้าวหน้า (รอบทางการ)</div>
    <?php for ($r = 1; $r <= 3; $r++): $pr = $progressRounds[$r] ?? null; ?>
      <div class="panel" style="margin-bottom:10px;padding:14px">
        <strong>รอบที่ <?= $r ?></strong>
        <?php if ($editable): ?>
          <form method="post" action="project_progress_submit.php" style="margin-top:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" value="<?= $id ?>">
            <input type="hidden" name="round_no" value="<?= $r ?>">
            <label class="f full" style="margin-bottom:8px">แผนงานที่จะดำเนินงาน
              <textarea name="plan" rows="2"><?= esc($pr['plan'] ?? '') ?></textarea>
            </label>
            <label class="f full" style="margin-bottom:8px">ปริมาณงานที่ส่งประเมินความก้าวหน้า
              <textarea name="submitted_work" rows="2"><?= esc($pr['submitted_work'] ?? '') ?></textarea>
            </label>
            <button type="submit" class="btn ghost sm">💾 บันทึกรอบที่ <?= $r ?></button>
            <?php if (!empty($pr['submitted_date'])): ?><small style="color:var(--muted);margin-left:8px">ส่งล่าสุด <?= thai_date($pr['submitted_date']) ?></small><?php endif; ?>
          </form>
        <?php else: ?>
          <p style="margin:6px 0"><strong>แผนงาน:</strong> <?= $pr['plan'] ? nl2br(esc($pr['plan'])) : '-' ?></p>
          <p style="margin:6px 0"><strong>ปริมาณงานที่ส่ง:</strong> <?= $pr['submitted_work'] ? nl2br(esc($pr['submitted_work'])) : '-' ?></p>
        <?php endif; ?>

        <div style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span>ผลการประเมิน:</span>
          <?php if (!empty($pr['rating'])): ?>
            <span class="badge <?= $pr['rating'] === 'ดี' ? 'done' : ($pr['rating'] === 'ปรับปรุง' ? 'late' : 'soon') ?>"><?= esc($pr['rating']) ?></span>
            <small style="color:var(--muted)"><?= esc($pr['evaluated_by']) ?> · <?= thai_date($pr['evaluated_date']) ?></small>
          <?php else: ?>
            <span class="badge notstarted">ยังไม่ประเมิน</span>
          <?php endif; ?>
          <?php if ($canRateProgress): ?>
            <form method="post" action="project_progress_rate.php" style="display:inline-flex;gap:6px;align-items:center">
              <?= csrf_field() ?>
              <input type="hidden" name="project_id" value="<?= $id ?>">
              <input type="hidden" name="round_no" value="<?= $r ?>">
              <select name="rating" style="width:auto">
                <?php foreach (PROGRESS_RATINGS as $rt): ?><option <?= ($pr['rating'] ?? '') === $rt ? 'selected' : '' ?>><?= esc($rt) ?></option><?php endforeach; ?>
              </select>
              <button type="submit" class="btn ghost sm">✅ บันทึกผล</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endfor; ?>

    <div class="panel" style="margin-bottom:16px;padding:14px">
      <strong>คำขอเสนอสอบโครงการ</strong>
      <?php if ($defenseReq && $defenseReq['requested_by']): ?>
        <p style="margin:8px 0;color:var(--green)">✅ โครงงานนี้จัดทำเสร็จสิ้นสมบูรณ์แล้ว ขอเสนอสอบโครงการ — โดย <?= esc($defenseReq['requested_by']) ?> · <?= thai_date($defenseReq['requested_date']) ?></p>
      <?php elseif (qc_can_sign($project, $user, 'advisor')): ?>
        <form method="post" action="project_defense_request.php" style="margin-top:8px" data-confirm="ยืนยันว่าโครงงานนี้เสร็จสมบูรณ์และขอเสนอสอบโครงการ?">
          <?= csrf_field() ?>
          <input type="hidden" name="project_id" value="<?= $id ?>">
          <input type="hidden" name="action" value="request">
          <button type="submit" class="btn sm">📤 ขอเสนอสอบโครงการ (ครูที่ปรึกษา)</button>
        </form>
      <?php else: ?>
        <p style="margin:8px 0;color:var(--muted)">ยังไม่มีการขอเสนอสอบโครงการ</p>
      <?php endif; ?>

      <?php if ($defenseReq && $defenseReq['instructor_note']): ?>
        <p style="margin:8px 0"><strong>ความเห็นของครูผู้สอนวิชาโครงงาน:</strong> <?= nl2br(esc($defenseReq['instructor_note'])) ?> <small style="color:var(--muted)">— <?= esc($defenseReq['instructor_by']) ?> · <?= thai_date($defenseReq['instructor_date']) ?></small></p>
      <?php elseif (qc_can_sign($project, $user, 'instructor')): ?>
        <form method="post" action="project_defense_request.php" style="margin-top:8px">
          <?= csrf_field() ?>
          <input type="hidden" name="project_id" value="<?= $id ?>">
          <input type="hidden" name="action" value="note">
          <label class="f full" style="margin:8px 0">ความเห็นของครูผู้สอนวิชาโครงงาน (ควรปรับปรุงเพิ่มเติมเรื่อง...)
            <textarea name="note" rows="2"></textarea>
          </label>
          <button type="submit" class="btn ghost sm">💾 บันทึกความเห็น</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="section-title">การกำกับคุณภาพวิชาโครงงาน</div>
    <?php if ($qcPassed): ?>
      <p class="msg ok" style="margin-bottom:10px">✅ ผ่านการกำกับคุณภาพครบทุกขั้นตอนแล้ว สามารถออกผลการประเมินได้</p>
    <?php else: ?>
      <p class="msg err" style="margin-bottom:10px">🚫 ยังผ่านการกำกับคุณภาพไม่ครบทุกขั้นตอน — ครูผู้สอนวิชาโครงการจะระงับการออกเกรดจนกว่าจะผ่านครบ</p>
    <?php endif; ?>
    <ul style="list-style:none;padding:0;margin:0 0 16px">
      <?php foreach (QC_STEPS as $i => $step):
          $row = $qcRows[$step['key']] ?? null;
          $status = $row['status'] ?? null;
          $canSign = qc_can_sign($project, $user, $step['role']);
      ?>
        <li style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--line);flex-wrap:wrap">
          <div style="flex:1 1 320px">
            <div><?= $i + 1 ?>. <?= esc($step['text']) ?></div>
            <?php if ($row): ?>
              <small style="color:var(--muted)"><?= esc($row['signed_by']) ?> · <?= thai_date($row['signed_date']) ?><?= $row['note'] ? ' · ' . esc($row['note']) : '' ?></small>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <?php if ($status === 'pass'): ?><span class="badge done">ผ่าน</span>
            <?php elseif ($status === 'fail'): ?><span class="badge late">ไม่ผ่าน</span>
            <?php else: ?><span class="badge notstarted">รอดำเนินการ</span>
            <?php endif; ?>
            <?php if ($canSign): ?>
              <form method="post" action="project_qc_update.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="step_key" value="<?= esc($step['key']) ?>">
                <input type="hidden" name="status" value="pass">
                <button type="submit" class="btn ghost sm">✅ ผ่าน</button>
              </form>
              <form method="post" action="project_qc_update.php" style="display:inline" data-confirm="ยืนยันบันทึกว่าไม่ผ่านขั้นตอนนี้?">
                <?= csrf_field() ?>
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="step_key" value="<?= esc($step['key']) ?>">
                <input type="hidden" name="status" value="fail">
                <button type="submit" class="btn danger sm">❌ ไม่ผ่าน</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="section-title">การประเมินผลโครงงาน</div>
    <?php if ($gradingBlocked): ?>
      <p style="color:var(--muted);margin:0">🚫 ยังไม่สามารถให้คะแนนได้ เนื่องจากยังผ่านการกำกับคุณภาพไม่ครบทุกขั้นตอนข้างต้น</p>
    <?php elseif ($grading): ?>
      <form id="evalForm" method="post" action="project_evaluation.php" data-maxes="<?= $maxesJson ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="project_id" value="<?= $id ?>">
        <div class="rubric-total"><span>คะแนนรวม</span><span id="evalTotalVal"><?= $evTotal ?> / 100 <span class="grade-pill"><?= esc(grade_label($evTotal)) ?></span></span></div>
        <div class="rubric">
          <?php foreach (RUBRIC as $r): ?>
            <div class="rubric-row">
              <span class="lbl"><?= esc($r['text']) ?><small>คะแนนเต็ม <?= $r['max'] ?></small></span>
              <input type="number" name="<?= esc($r['key']) ?>" min="0" max="<?= $r['max'] ?>" value="<?= esc((string) ($evScores[$r['key']] ?? '')) ?>" placeholder="0–<?= $r['max'] ?>">
            </div>
          <?php endforeach; ?>
        </div>
        <label class="f full" style="margin-bottom:10px">ความคิดเห็น/ข้อเสนอแนะ
          <textarea name="comment" rows="2"><?= esc($ev['comment'] ?? '') ?></textarea>
        </label>
        <?php if ($ev): ?><p class="eval-meta">ประเมินล่าสุดโดย <?= esc($ev['by']) ?> · <?= thai_date($ev['date']) ?></p><?php endif; ?>
        <button class="btn"><?= $ev ? '💾 บันทึกการแก้ไขคะแนน' : '💾 บันทึกผลการประเมิน' ?></button>
      </form>
    <?php elseif (!$ev): ?>
      <p style="color:var(--muted);margin:0">ยังไม่มีการประเมินผล</p>
    <?php else: ?>
      <div class="rubric-total"><span>คะแนนรวม</span><span><?= $evTotal ?> / 100 <span class="grade-pill"><?= esc(grade_label($evTotal)) ?></span></span></div>
      <dl class="info-grid"><?php foreach (RUBRIC as $r): ?><dt><?= esc($r['text']) ?></dt><dd><?= (int) ($evScores[$r['key']] ?? 0) ?> / <?= $r['max'] ?></dd><?php endforeach; ?></dl>
      <?php if (!empty($ev['comment'])): ?><p style="white-space:pre-line;margin:10px 0 0"><?= esc($ev['comment']) ?></p><?php endif; ?>
      <p class="eval-meta" style="margin-top:10px">ประเมินโดย <?= esc($ev['by']) ?> · <?= thai_date($ev['date']) ?></p>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>

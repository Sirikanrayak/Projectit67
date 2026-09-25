(() => {
  // สลับโหมดกลางวัน/กลางคืน (ค่าที่เลือกจำไว้ใน localStorage ต่อเบราว์เซอร์)
  const themeBtn = document.getElementById('btnTheme');
  if (themeBtn) {
    const root = document.documentElement;
    const syncIcon = () => { themeBtn.textContent = root.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙'; };
    syncIcon();
    themeBtn.addEventListener('click', () => {
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('theme', next); } catch (e) {}
      syncIcon();
    });
  }

  // หน้าเข้าสู่ระบบ: สลับแท็บนักเรียน/บุคลากร เปลี่ยนป้ายชื่อและตัวอย่างข้อความในช่องกรอก
  const roleTabs = document.getElementById('roleTabs');
  const loginLabel = document.getElementById('loginLabel');
  const loginInput = document.getElementById('loginInput');
  if (roleTabs && loginLabel && loginInput) {
    const roleText = {
      student: { label: '🎫 รหัสนักเรียน', placeholder: 'เช่น 66301040001' },
      staff: { label: '🎫 อีเมล', placeholder: 'you@example.com' },
    };
    roleTabs.querySelectorAll('button').forEach((btn) => {
      btn.addEventListener('click', () => {
        roleTabs.querySelectorAll('button').forEach((b) => b.classList.toggle('active', b === btn));
        const t = roleText[btn.dataset.role];
        loginLabel.textContent = t.label;
        loginInput.placeholder = t.placeholder;
        loginInput.focus();
      });
    });
  }

  // ลิงก์ "ลืมรหัสผ่าน?" หน้าเข้าสู่ระบบ
  const forgotLink = document.getElementById('forgotLink');
  if (forgotLink) {
    forgotLink.addEventListener('click', (e) => {
      e.preventDefault();
      Swal.fire({
        icon: 'info',
        title: 'ลืมรหัสผ่าน?',
        text: 'กรุณาติดต่อครูที่ปรึกษาหรือผู้ดูแลระบบเพื่อขอตั้งรหัสผ่านใหม่',
        confirmButtonText: 'เข้าใจแล้ว',
        confirmButtonColor: '#ea580c',
      });
    });
  }

  // แจ้งเตือนกำหนดส่ง: เปิด/ปิดแผงรายการ
  const notifBtn = document.getElementById('btnNotif');
  const notifPanel = document.getElementById('notifPanel');
  if (notifBtn && notifPanel) {
    notifBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      notifPanel.hidden = !notifPanel.hidden;
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.notif-wrap')) notifPanel.hidden = true;
    });
  }

  // ลิงก์/ปุ่มที่ต้องยืนยันก่อนทำงาน (เช่น ออกจากระบบ) ผ่าน data-confirm
  document.querySelectorAll('a[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const href = el.getAttribute('href');
      Swal.fire({
        title: el.dataset.confirm,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ea580c',
      }).then((r) => { if (r.isConfirmed) window.location.href = href; });
    });
  });

  // ฟอร์มที่ต้องยืนยันก่อนส่ง (เช่น ลบข้อมูล) ผ่าน data-confirm บน <form>
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (form.dataset.confirmed === '1') return;
      e.preventDefault();
      Swal.fire({
        title: form.dataset.confirm,
        text: form.dataset.confirmText || '',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#b91c1c',
      }).then((r) => {
        if (r.isConfirmed) {
          form.dataset.confirmed = '1';
          form.submit();
        }
      });
    });
  });

  // แสดงรายการเงื่อนไขรหัสผ่านแบบเรียลไทม์ใต้ช่องรหัสผ่าน (ต้องตรงกับ password_problems() ฝั่ง PHP)
  const PW_RULES = [
    { text: 'อย่างน้อย 8 ตัวอักษร', test: (pw) => pw.length >= 8 },
    { text: 'ตัวพิมพ์ใหญ่ภาษาอังกฤษ (A–Z)', test: (pw) => /[A-Z]/.test(pw) },
    { text: 'ตัวพิมพ์เล็กภาษาอังกฤษ (a–z)', test: (pw) => /[a-z]/.test(pw) },
    { text: 'ตัวเลข (0–9)', test: (pw) => /[0-9]/.test(pw) },
    { text: 'อักขระพิเศษ เช่น @ # $ % ! _ -', test: (pw) => /[!-\/:-@\[-`{-~]/.test(pw) },
    { text: 'ไม่มีช่องว่างหรือตัวอักษรภาษาไทย', test: (pw) => /^[\x21-\x7E]+$/.test(pw) },
  ];
  document.querySelectorAll('.pw-rules').forEach((box) => {
    const form = box.closest('form');
    if (!form || !form.elements.password) return;
    const render = () => {
      const pw = form.elements.password.value;
      const pw2 = form.elements.password2;
      const items = PW_RULES.map((r) => [r.test(pw), r.text]);
      if (pw2) items.push([pw !== '' && pw === pw2.value, 'รหัสผ่านทั้งสองช่องตรงกัน']);
      box.innerHTML = 'รหัสผ่านต้องประกอบด้วย · ตัวอย่าง <code>Nayok@2565</code><ul>' +
        items.map(([ok, t]) => `<li class="${ok ? 'ok' : ''}">${t}</li>`).join('') + '</ul>';
    };
    form.addEventListener('input', render);
    render();
  });

  // อัปเดตคะแนนรวมแบบสดในฟอร์มประเมินผล
  const evalForm = document.getElementById('evalForm');
  if (evalForm) {
    const totalEl = document.getElementById('evalTotalVal');
    const maxes = JSON.parse(evalForm.dataset.maxes || '{}');
    const gradeLabel = (total) => {
      if (total >= 90) return 'ดีเยี่ยม';
      if (total >= 80) return 'ดีมาก';
      if (total >= 70) return 'ดี';
      if (total >= 60) return 'พอใช้';
      return 'ควรปรับปรุง';
    };
    const recalc = () => {
      let total = 0;
      Object.keys(maxes).forEach((key) => {
        const input = evalForm.elements[key];
        const v = Math.max(0, Math.min(maxes[key], Number(input.value) || 0));
        total += v;
      });
      totalEl.innerHTML = total + ' / 100 <span class="grade-pill">' + gradeLabel(total) + '</span>';
    };
    evalForm.addEventListener('input', recalc);
  }
})();

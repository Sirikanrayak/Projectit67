<?php $flash = flash_take(); ?>
<footer>
  ©ศิริกัลยา 2565 แผนกวิชาเทคโนโลยีสารสนเทศ วิทยาลัยเทคนิคนครนายก · ข้อมูลบันทึกลงฐานข้อมูล MySQL
</footer>

<script src="assets/js/app.js"></script>
<?php if ($flash): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: <?= json_encode($flash['type'] === 'err' ? 'error' : ($flash['type'] === 'ok' ? 'success' : 'info')) ?>,
      title: <?= json_encode($flash['text']) ?>,
      showConfirmButton: false,
      timer: 3200,
      timerProgressBar: true,
    });
  });
</script>
<?php endif; ?>
</body>
</html>

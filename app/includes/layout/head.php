<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($pageTitle ?? 'ระบบติดตามโครงงานนักเรียน') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@500;600&family=Sarabun:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // ค่าเริ่มต้นภาษาไทยสำหรับทุกตาราง DataTables ในระบบ (ต้องตั้งก่อนหน้าใด ๆ เรียก .DataTable())
  jQuery.extend(true, jQuery.fn.dataTable.defaults, {
    language: {
      search: 'ค้นหา:',
      lengthMenu: 'แสดง _MENU_ แถว',
      info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
      infoEmpty: 'ไม่มีข้อมูล',
      infoFiltered: '(กรองจาก _MAX_ รายการ)',
      zeroRecords: 'ไม่พบข้อมูลที่ตรงกัน',
      emptyTable: 'ไม่มีข้อมูลในตาราง',
      paginate: { first: 'หน้าแรก', last: 'หน้าสุดท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' },
    },
    pageLength: 10,
  });
</script>
</head>
<body class="<?= isset($user) && $user && is_admin($user) ? 'is-admin' : '' ?>">

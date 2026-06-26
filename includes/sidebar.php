<?php
// includes/sidebar.php
$uri = $_SERVER['REQUEST_URI'];
function is_active(string $path): string {
    global $uri;
    return strpos($uri, $path) !== false ? 'active' : '';
}
$is_mgr   = is_manager();
$eid      = current_employee_id();
$emp_name = $_SESSION['emp_name'] ?? 'User';
$emp_role = $_SESSION['role']     ?? 'employee';
?>
<nav class="portal-sidebar">

  <!-- Brand -->
  <?php $logo = get_setting('company_logo'); ?>
  <a href="/todo/index.php" class="sidebar-brand">
    <?php if ($logo): ?>
      <img src="/uploads/logo/<?= h($logo) ?>" alt="Logo"
           style="width:32px;height:32px;object-fit:contain;border-radius:6px;flex-shrink:0">
    <?php else: ?>
      <i class="fa fa-briefcase"></i>
    <?php endif; ?>
    <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:1rem"><?= h(get_setting('company_name', 'Creative Elements')) ?></span>
  </a>

  <!-- Workspace -->
  <div class="sidebar-section">Workspace</div>
  <a href="/todo/index.php"       class="sidebar-link <?= is_active('/todo/index') ?>"><i class="fa fa-th-large"></i> Dashboard</a>
  <a href="/todo/tasks.php"       class="sidebar-link <?= is_active('/todo/tasks') ?>"><i class="fa fa-clipboard-list"></i> My Tasks</a>
  <a href="/todo/projects.php"    class="sidebar-link <?= is_active('/todo/projects') ?>"><i class="fa fa-folder-open"></i> Projects</a>
  <a href="/todo/time_track.php"  class="sidebar-link <?= is_active('/todo/time_track') ?>"><i class="fa fa-stopwatch"></i> Time Tracker</a>
  <a href="/todo/report.php"      class="sidebar-link <?= is_active('/todo/report') ?>"><i class="fa fa-file-alt"></i> Daily Report</a>
  <a href="/todo/performance.php" class="sidebar-link <?= is_active('/todo/performance') ?>"><i class="fa fa-chart-line"></i> My Performance</a>

  <!-- Leave -->
  <div class="sidebar-section">Leave</div>
  <a href="/leave/index.php"    class="sidebar-link <?= is_active('/leave/index') ?>"><i class="fa fa-calendar-check"></i> My Leave</a>
  <a href="/leave/calendar.php" class="sidebar-link <?= is_active('/leave/calendar') ?>"><i class="fa fa-calendar-alt"></i> Team Calendar</a>

  <?php if ($is_mgr): ?>
  <!-- Management -->
  <div class="sidebar-section">Management</div>
  <a href="/admin/employees.php"  class="sidebar-link <?= is_active('/admin/employees') ?>"><i class="fa fa-users"></i> Employees</a>
  <a href="/admin/attendance.php" class="sidebar-link <?= is_active('/admin/attendance') ?>"><i class="fa fa-user-clock"></i> Attendance</a>
  <a href="/admin/checklist.php"  class="sidebar-link <?= is_active('/admin/checklist') ?>"><i class="fa fa-list-check"></i> Checklist Setup</a>
  <a href="/admin/projects.php"   class="sidebar-link <?= is_active('/admin/projects') ?>"><i class="fa fa-project-diagram"></i> Manage Projects</a>
  <a href="/leave/admin.php"      class="sidebar-link <?= is_active('/leave/admin') ?>"><i class="fa fa-user-shield"></i> Leave Approvals</a>
  <a href="/leave/holidays.php"   class="sidebar-link <?= is_active('/leave/holidays') ?>"><i class="fa fa-calendar-plus"></i> Holidays</a>
  <a href="/admin/reports.php"    class="sidebar-link <?= is_active('/admin/reports') ?>"><i class="fa fa-chart-bar"></i> Reports</a>
  <?php endif; ?>
  <?php if ($_SESSION['role'] === 'admin'): ?>
  <a href="/admin/settings.php"   class="sidebar-link <?= is_active('/admin/settings') ?>"><i class="fa fa-cog"></i> Settings</a>
  <?php endif; ?>

  <!-- User footer -->
  <div class="sidebar-footer">
    <div class="sidebar-avatar"><?= strtoupper(substr($emp_name, 0, 1)) ?></div>
    <div style="flex:1; min-width:0">
      <div class="sidebar-user-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($emp_name, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="sidebar-user-role"><?= ucfirst($emp_role) ?></div>
    </div>
    <a href="/logout.php" title="Sign Out" style="color:rgba(255,255,255,.4); font-size:.95rem; transition:.2s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.4)'">
      <i class="fa fa-sign-out-alt"></i>
    </a>
  </div>

</nav>

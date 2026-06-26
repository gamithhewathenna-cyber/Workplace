<?php
// includes/sidebar.php
$uri = $_SERVER['REQUEST_URI'];
function is_active(string $path): string {
    global $uri;
    return str_contains($uri, $path) ? 'active' : '';
}
$is_mgr = is_manager();
?>
<nav class="portal-sidebar">
  <div class="sidebar-section">Workspace</div>
  <a href="/todo/index.php"        class="sidebar-link <?= is_active('/todo/index') ?>"><i class="fa fa-home"></i> Dashboard</a>
  <a href="/todo/tasks.php"        class="sidebar-link <?= is_active('/todo/tasks') ?>"><i class="fa fa-clipboard-list"></i> My Tasks</a>
  <a href="/todo/projects.php"     class="sidebar-link <?= is_active('/todo/projects') ?>"><i class="fa fa-folder-open"></i> Projects</a>
  <a href="/todo/time_track.php"   class="sidebar-link <?= is_active('/todo/time_track') ?>"><i class="fa fa-stopwatch"></i> Time Tracker</a>
  <a href="/todo/report.php"       class="sidebar-link <?= is_active('/todo/report') ?>"><i class="fa fa-file-alt"></i> Daily Report</a>
  <a href="/todo/performance.php"  class="sidebar-link <?= is_active('/todo/performance') ?>"><i class="fa fa-chart-line"></i> My Performance</a>

  <div class="sidebar-section">Leave</div>
  <a href="/leave/index.php"      class="sidebar-link <?= is_active('/leave/index') ?>"><i class="fa fa-calendar-check"></i> My Leave</a>
  <a href="/leave/calendar.php"   class="sidebar-link <?= is_active('/leave/calendar') ?>"><i class="fa fa-calendar-alt"></i> Team Calendar</a>

  <?php if ($is_mgr): ?>
  <div class="sidebar-section">Management</div>
  <a href="/admin/attendance.php"  class="sidebar-link <?= is_active('/admin/attendance') ?>"><i class="fa fa-user-clock"></i> Attendance</a>
  <a href="/admin/checklist.php"   class="sidebar-link <?= is_active('/admin/checklist') ?>"><i class="fa fa-list-check"></i> Checklist Setup</a>
  <a href="/admin/projects.php"    class="sidebar-link <?= is_active('/admin/projects') ?>"><i class="fa fa-project-diagram"></i> Manage Projects</a>
  <a href="/leave/admin.php"       class="sidebar-link <?= is_active('/leave/admin') ?>"><i class="fa fa-user-shield"></i> Leave Approvals</a>
  <a href="/leave/holidays.php"    class="sidebar-link <?= is_active('/leave/holidays') ?>"><i class="fa fa-calendar-plus"></i> Holidays</a>
  <a href="/admin/reports.php"     class="sidebar-link <?= is_active('/admin/reports') ?>"><i class="fa fa-chart-bar"></i> Reports</a>
  <?php endif; ?>
</nav>

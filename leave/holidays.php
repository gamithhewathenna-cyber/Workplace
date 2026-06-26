<?php
/**
 * leave/holidays.php
 * Manager: manage public/company holidays
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) redirect('/leave/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $date = $_POST['date'] ?? '';
        $type = $_POST['type'] ?? 'public';
        if ($name && $date) {
            db()->prepare("INSERT IGNORE INTO holidays (name,date,type) VALUES (?,?,?)")->execute([$name, $date, $type]);
            flash('success', 'Holiday added.');
        }
    }
    elseif ($action === 'delete') {
        db()->prepare("DELETE FROM holidays WHERE id=?")->execute([(int)$_POST['id']]);
        flash('success', 'Holiday removed.');
    }
    redirect('holidays.php');
}

$year      = (int)($_GET['year'] ?? date('Y'));
$holidays  = db()->prepare("SELECT * FROM holidays WHERE year=? ORDER BY date");
$holidays->execute([$year]);
$list = $holidays->fetchAll();

$success = get_flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Holidays – Employee Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/portal.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="portal-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <div class="page-header">
      <h1 class="page-title"><i class="fa fa-calendar-plus"></i> Holidays</h1>
      <div class="header-actions">
        <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
          <a href="?year=<?= $y ?>" class="btn <?= $year===$y?'btn-primary':'btn-outline' ?>"><?= $y ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

    <div class="two-col">
      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-plus"></i> Add Holiday</h2></div>
        <form method="post">
          <input type="hidden" name="action" value="add">
          <div class="form-group" style="margin-bottom:.875rem"><label>Holiday Name *</label><input type="text" name="name" class="input" required placeholder="e.g. New Year's Day"></div>
          <div class="form-group" style="margin-bottom:.875rem"><label>Date *</label><input type="date" name="date" class="input" required></div>
          <div class="form-group" style="margin-bottom:1rem">
            <label>Type</label>
            <select name="type" class="input">
              <option value="public">Public Holiday</option>
              <option value="company">Company Holiday</option>
            </select>
          </div>
          <button class="btn btn-primary"><i class="fa fa-plus"></i> Add Holiday</button>
        </form>
      </section>

      <section class="section-card">
        <div class="section-header"><h2><i class="fa fa-list"></i> <?= $year ?> Holidays (<?= count($list) ?>)</h2></div>
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>Date</th><th>Holiday</th><th>Type</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($list as $h): ?>
              <tr>
                <td><?= date('d M Y, D', strtotime($h['date'])) ?></td>
                <td><?= h($h['name']) ?></td>
                <td><span class="badge badge-<?= $h['type']==='public'?'info':'success' ?>"><?= ucfirst($h['type']) ?></span></td>
                <td>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                    <button class="btn btn-xs btn-danger" data-confirm="Remove this holiday?"><i class="fa fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$list): ?><tr><td colspan="4" class="text-center text-muted">No holidays for <?= $year ?>.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</div>
<script src="/assets/js/portal.js"></script>
</body>
</html>

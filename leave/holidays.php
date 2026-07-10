<?php
/**
 * leave/holidays.php
 * Manager: manage public/company holidays
 */
require_once __DIR__ . '/../includes/config.php';
require_login();
if (!is_manager()) redirect('/leave/index.php');

// Parses a single date string against a fixed set of unambiguous formats
// (deliberately avoids strtotime()'s locale-ambiguous slash-date guessing).
function parse_holiday_date(string $raw): ?string {
    $raw = trim($raw);
    foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd.m.Y', 'd M Y', 'j M Y', 'd F Y', 'j F Y'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $raw);
        if ($d && $d->format($fmt) === $raw) {
            return $d->format('Y-m-d');
        }
    }
    return null;
}

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
    elseif ($action === 'import') {
        $csvText = '';
        if (!empty($_FILES['csv_file']['tmp_name']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $csvText = file_get_contents($_FILES['csv_file']['tmp_name']);
        } elseif (trim($_POST['csv_text'] ?? '') !== '') {
            $csvText = $_POST['csv_text'];
        }

        if (trim($csvText) === '') {
            flash('error', 'Upload a CSV file or paste CSV rows to import.');
            redirect('holidays.php');
        }

        $ins = db()->prepare("INSERT IGNORE INTO holidays (name,date,type) VALUES (?,?,?)");
        $added = 0; $skipped = 0;

        foreach (preg_split('/\r\n|\r|\n/', trim($csvText)) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $cols = str_getcsv($line);
            $name = trim($cols[0] ?? '');
            $date = parse_holiday_date($cols[1] ?? '');
            $type = strtolower(trim($cols[2] ?? 'public'));
            if (!in_array($type, ['public', 'company'], true)) $type = 'public';

            if (!$name || !$date) { $skipped++; continue; }

            $ins->execute([$name, $date, $type]);
            if ($ins->rowCount() > 0) { $added++; } else { $skipped++; }
        }

        $msg = "Imported $added holiday(s).";
        if ($skipped) $msg .= " Skipped $skipped row(s) (duplicate dates, header row, or unrecognized format).";
        flash('success', $msg);
        redirect('holidays.php');
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
$error   = get_flash('error');
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
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <!-- CSV Import -->
    <section class="section-card">
      <div class="section-header"><h2><i class="fa fa-file-csv"></i> Import Holidays from CSV</h2></div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import">
        <div class="form-grid">
          <div class="form-group">
            <label>Upload CSV File</label>
            <input type="file" name="csv_file" class="input" accept=".csv,text/csv">
          </div>
          <div class="form-group">
            <label>…or Paste CSV Rows</label>
            <textarea name="csv_text" class="input" rows="4" style="resize:vertical" placeholder="New Year's Day,2026-01-01,public&#10;Independence Day,2026-02-04,public&#10;Office Anniversary,2026-06-15,company"></textarea>
          </div>
        </div>
        <p style="font-size:.78rem;color:var(--clr-muted);margin:.5rem 0 1rem">
          One holiday per line: <code>Name,Date,Type</code> — Type is optional (<code>public</code> or <code>company</code>, defaults to <code>public</code>).
          Dates accept <code>YYYY-MM-DD</code>, <code>DD-MM-YYYY</code>, or <code>DD/MM/YYYY</code>. Duplicate dates are skipped automatically.
        </p>
        <button class="btn btn-primary"><i class="fa fa-upload"></i> Import Holidays</button>
      </form>
    </section>

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

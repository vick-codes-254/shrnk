<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

$pdo = db();

/* base path so short links work whether installed at / or /shrnk/ */
$base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function short_url(string $origin, string $base, string $code): string
{
    return $origin . $base . '/?c=' . $code;
}

/* ---- redirect:  /?c=CODE  ---- */
$notFound = null;
if (isset($_GET['c'])) {
    $stmt = $pdo->prepare('SELECT id, url FROM links WHERE code = ?');
    $stmt->execute([$_GET['c']]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare('UPDATE links SET clicks = clicks + 1, last_visit = ? WHERE id = ?')
            ->execute([date('c'), $row['id']]);
        header('Location: ' . $row['url'], true, 302);
        exit;
    }
    http_response_code(404);
    $notFound = $_GET['c'];
}

/* ---- create:  POST url  (Post/Redirect/Get) ---- */
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = normalize_url($_POST['url'] ?? '');
    if ($url === null) {
        $error = "That doesn't look like a valid web address.";
    } else {
        $code = make_code($pdo);
        $pdo->prepare('INSERT INTO links (code, url, created_at) VALUES (?, ?, ?)')
            ->execute([$code, $url, date('c')]);
        header('Location: ' . ($base === '' ? '/' : $base . '/') . '?new=' . $code);
        exit;
    }
}
$created = $_GET['new'] ?? null;

$links       = $pdo->query('SELECT * FROM links ORDER BY id DESC LIMIT 100')->fetchAll();
$total       = (int) $pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
$totalClicks = (int) $pdo->query('SELECT COALESCE(SUM(clicks), 0) FROM links')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shrnk — tiny URL shortener</title>
<link rel="stylesheet" href="<?= h($base) ?>/style.css">
</head>
<body>
<div class="wrap">
  <header>
    <h1>🔗 Shrnk</h1>
    <p class="tag">Paste a long link, get a tiny one — with click tracking.</p>
  </header>

  <?php if ($notFound !== null): ?>
    <div class="flash err">No link found for code <b><?= h($notFound) ?></b>.</div>
  <?php endif; ?>
  <?php if ($error !== null): ?>
    <div class="flash err"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($created !== null): ?>
    <?php $su = short_url($origin, $base, (string) $created); ?>
    <div class="flash ok">
      <span>Your short link:</span>
      <a class="created" href="<?= h($su) ?>" target="_blank" rel="noopener"><?= h($su) ?></a>
      <button class="copy" data-copy="<?= h($su) ?>">Copy</button>
    </div>
  <?php endif; ?>

  <form class="shorten" method="post" action="<?= $base === '' ? '/' : h($base) . '/' ?>">
    <input type="url" name="url" placeholder="https://example.com/your/very/long/link"
           autocomplete="off" autofocus required>
    <button type="submit">Shrnk it</button>
  </form>

  <div class="stats">
    <div class="stat"><b><?= $total ?></b><span>links</span></div>
    <div class="stat"><b><?= $totalClicks ?></b><span>clicks</span></div>
  </div>

  <?php if ($links): ?>
  <table>
    <thead>
      <tr><th>Short</th><th>Destination</th><th class="num">Clicks</th><th>Created</th></tr>
    </thead>
    <tbody>
    <?php foreach ($links as $l): $su = short_url($origin, $base, $l['code']); ?>
      <tr>
        <td>
          <a href="<?= h($su) ?>" target="_blank" rel="noopener">/?c=<?= h($l['code']) ?></a>
          <button class="copy mini" data-copy="<?= h($su) ?>" title="Copy">⧉</button>
        </td>
        <td class="dest"><a href="<?= h($l['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($l['url']) ?></a></td>
        <td class="num"><?= (int) $l['clicks'] ?></td>
        <td class="when"><?= h(date('M j, H:i', strtotime($l['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p class="empty">No links yet — shorten one above to get started.</p>
  <?php endif; ?>

  <footer>Built with PHP <?= PHP_VERSION ?> + SQLite · no external dependencies</footer>
</div>

<script>
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.copy');
  if (!btn) return;
  try {
    await navigator.clipboard.writeText(btn.dataset.copy);
    const t = btn.textContent;
    btn.textContent = '✓';
    btn.classList.add('done');
    setTimeout(() => { btn.textContent = t; btn.classList.remove('done'); }, 1200);
  } catch (_) {}
});
</script>
</body>
</html>

<?php
/* ============================================================
   C.R.I. Idiomas — admin_leads.php
   Painel simples para visualizar e exportar os leads salvos.
   IMPORTANTE: Proteja este arquivo com autenticação antes
   de colocar em produção!
   ============================================================ */

// ── Autenticação básica por senha ───────────────────────────
// Altere ADMIN_PASS para uma senha forte
define('ADMIN_PASS', 'criidiomas2026');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    if ($_POST['senha'] === ADMIN_PASS) {
        $_SESSION['admin_ok'] = true;
    } else {
        $loginErro = 'Senha incorreta.';
    }
}

if (!isset($_SESSION['admin_ok'])) {
    // Tela de login
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — C.R.I. Idiomas</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #0f172a; color: #e2e8f0;
               display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px;
                      padding: 2.5rem 2rem; width: 100%; max-width: 360px; text-align: center; }
        .login-card h1 { font-size: 1.4rem; margin-bottom: 0.4rem; color: #f8fafc; }
        .login-card p  { font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.8rem; }
        .login-card input { width: 100%; padding: 0.75rem 1rem; background: #0f172a;
                            border: 1.5px solid #334155; border-radius: 8px; color: #f8fafc;
                            font-size: 0.95rem; margin-bottom: 1rem; outline: none; }
        .login-card input:focus { border-color: #38bdf8; }
        .login-card button { width: 100%; padding: 0.75rem; background: #0ea5e9;
                              border: none; border-radius: 8px; color: #fff; font-weight: 700;
                              font-size: 1rem; cursor: pointer; transition: background 0.2s; }
        .login-card button:hover { background: #0284c7; }
        .error { color: #f87171; font-size: 0.85rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>🔐 Painel de Leads</h1>
        <p>C.R.I. Idiomas</p>
        <?php if (!empty($loginErro)): ?>
            <p class="error"><?= htmlspecialchars($loginErro) ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="senha" placeholder="Senha de acesso" required autofocus>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
    <?php
    exit;
}

// ── Configurações do banco ──────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'reinodoingles');
define('DB_USER', 'root');        // Substitua pelo seu usuário
define('DB_PASS', '');            // Substitua pela sua senha

// ── Exportar CSV ────────────────────────────────────────────
if (isset($_GET['exportar'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->query("SELECT id, nome, email, whatsapp, origem, ip_origem, criado_em FROM leads ORDER BY criado_em DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die('Erro ao conectar ao banco.');
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 para Excel
    fputcsv($out, ['ID', 'Nome', 'E-mail', 'WhatsApp', 'Origem', 'IP', 'Data/Hora']);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// ── Busca e paginação ───────────────────────────────────────
$busca    = trim($_GET['busca'] ?? '');
$pagina   = max(1, (int)($_GET['pag'] ?? 1));
$porPagina = 20;
$offset   = ($pagina - 1) * $porPagina;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $where = $busca
        ? "WHERE nome LIKE :busca OR email LIKE :busca OR whatsapp LIKE :busca"
        : "";
    $params = $busca ? [':busca' => "%$busca%"] : [];

    $total = $pdo->prepare("SELECT COUNT(*) FROM leads $where");
    $total->execute($params);
    $totalRegistros = (int)$total->fetchColumn();
    $totalPaginas   = max(1, (int)ceil($totalRegistros / $porPagina));

    $stmt = $pdo->prepare("SELECT id, nome, email, whatsapp, origem, criado_em FROM leads $where ORDER BY criado_em DESC LIMIT $porPagina OFFSET $offset");
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalGeral = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    $hoje       = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE DATE(criado_em) = CURDATE()")->fetchColumn();
    $semana     = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE criado_em >= NOW() - INTERVAL 7 DAY")->fetchColumn();

} catch (PDOException $e) {
    $erroDb = 'Não foi possível conectar ao banco de dados. Verifique as configurações.';
    $leads  = [];
    $totalRegistros = 0;
    $totalPaginas   = 1;
    $totalGeral = $hoje = $semana = 0;
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Leads — C.R.I. Idiomas</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #0f172a;
            --surface:  #1e293b;
            --border:   #334155;
            --text:     #e2e8f0;
            --muted:    #94a3b8;
            --primary:  #0ea5e9;
            --accent:   #f59e0b;
            --success:  #22c55e;
            --danger:   #ef4444;
            --radius:   10px;
        }

        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg);
               color: var(--text); min-height: 100vh; }

        /* ── Topbar ── */
        .topbar { background: var(--surface); border-bottom: 1px solid var(--border);
                  padding: 0 2rem; height: 64px; display: flex; align-items: center;
                  justify-content: space-between; position: sticky; top: 0; z-index: 10; }
        .topbar-brand { font-family: 'Outfit', sans-serif; font-size: 1.3rem; color: #fff; }
        .topbar-brand span { color: var(--primary); }
        .topbar-actions { display: flex; gap: 0.75rem; align-items: center; }

        /* ── Layout ── */
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ── Cards de estatísticas ── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                      gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--surface); border: 1px solid var(--border);
                     border-radius: var(--radius); padding: 1.25rem 1.5rem; }
        .stat-card .stat-label { font-size: 0.8rem; color: var(--muted); font-weight: 600;
                                  text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.4rem; }
        .stat-card .stat-value { font-family: 'Outfit', sans-serif; font-size: 2rem;
                                  font-weight: 800; color: #fff; }
        .stat-card.accent .stat-value { color: var(--accent); }
        .stat-card.primary .stat-value { color: var(--primary); }
        .stat-card.success .stat-value { color: var(--success); }

        /* ── Toolbar ── */
        .toolbar { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;
                   margin-bottom: 1.25rem; }
        .search-input { flex: 1; min-width: 220px; padding: 0.65rem 1rem;
                        background: var(--surface); border: 1.5px solid var(--border);
                        border-radius: var(--radius); color: var(--text); font-size: 0.9rem; outline: none; }
        .search-input:focus { border-color: var(--primary); }
        .search-input::placeholder { color: var(--muted); }

        /* ── Botões ── */
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.2rem;
               border-radius: var(--radius); font-size: 0.88rem; font-weight: 600;
               border: none; cursor: pointer; transition: opacity 0.2s; text-decoration: none; }
        .btn:hover { opacity: 0.85; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-danger  { background: transparent; border: 1px solid var(--danger); color: var(--danger); }

        /* ── Tabela ── */
        .table-wrap { background: var(--surface); border: 1px solid var(--border);
                      border-radius: var(--radius); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        thead { background: rgba(255,255,255,0.04); }
        thead th { padding: 0.85rem 1rem; text-align: left; font-size: 0.78rem; font-weight: 700;
                    color: var(--muted); text-transform: uppercase; letter-spacing: 0.07em;
                    white-space: nowrap; border-bottom: 1px solid var(--border); }
        tbody tr { border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.03); }
        tbody td { padding: 0.9rem 1rem; vertical-align: middle; }
        .td-name { font-weight: 600; color: #fff; }
        .td-email a { color: var(--primary); text-decoration: none; }
        .td-email a:hover { text-decoration: underline; }
        .td-wpp a { color: var(--success); text-decoration: none; }
        .td-wpp a:hover { text-decoration: underline; }
        .badge { display: inline-block; padding: 0.2rem 0.65rem; border-radius: 20px;
                 font-size: 0.75rem; font-weight: 700; }
        .badge-guide { background: rgba(14,165,233,0.15); color: var(--primary); border: 1px solid rgba(14,165,233,0.3); }
        .td-date { color: var(--muted); white-space: nowrap; }

        /* ── Paginação ── */
        .pagination { display: flex; gap: 0.4rem; align-items: center; justify-content: center;
                      margin-top: 1.5rem; flex-wrap: wrap; }
        .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center;
                    width: 36px; height: 36px; border-radius: 8px; font-size: 0.88rem; font-weight: 600;
                    text-decoration: none; border: 1px solid var(--border); color: var(--muted); transition: all 0.15s; }
        .pagination a:hover { background: var(--surface); color: var(--text); }
        .pagination .active { background: var(--primary); border-color: var(--primary); color: #fff; }
        .pagination .dots { border: none; color: var(--muted); }

        /* ── Vazio ── */
        .empty-state { text-align: center; padding: 4rem 1rem; color: var(--muted); }
        .empty-state .empty-icon { font-size: 3rem; margin-bottom: 1rem; }

        /* ── Alert ── */
        .alert-danger { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
                        color: #fca5a5; border-radius: var(--radius); padding: 1rem 1.25rem; margin-bottom: 1.5rem; }

        /* ── Logout ── */
        .logout-link { font-size: 0.85rem; color: var(--muted); text-decoration: none; }
        .logout-link:hover { color: var(--danger); }

        @media (max-width: 640px) {
            .topbar { padding: 0 1rem; }
            .container { padding: 1.5rem 1rem; }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-brand">C.R.I. <span>Leads</span></div>
        <div class="topbar-actions">
            <a href="?exportar=1" class="btn btn-success">
                ⬇ Exportar CSV
            </a>
            <a href="?logout=1" class="logout-link">Sair</a>
        </div>
    </header>

    <?php
    // Logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: admin_leads.php');
        exit;
    }
    ?>

    <div class="container">

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total de Leads</div>
                <div class="stat-value"><?= number_format($totalGeral, 0, ',', '.') ?></div>
            </div>
            <div class="stat-card primary">
                <div class="stat-label">Esta Semana</div>
                <div class="stat-value"><?= $semana ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Hoje</div>
                <div class="stat-value"><?= $hoje ?></div>
            </div>
            <div class="stat-card accent">
                <div class="stat-label">Origem</div>
                <div class="stat-value" style="font-size:1.1rem;padding-top:0.3rem;">Guia Grátis</div>
            </div>
        </div>

        <!-- Erro de banco -->
        <?php if (!empty($erroDb)): ?>
            <div class="alert-danger">⚠️ <?= htmlspecialchars($erroDb) ?></div>
        <?php endif; ?>

        <!-- Barra de busca -->
        <form method="GET" class="toolbar">
            <input type="text" name="busca" class="search-input"
                   placeholder="🔍  Buscar por nome, e-mail ou WhatsApp…"
                   value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <?php if ($busca): ?>
                <a href="admin_leads.php" class="btn btn-danger">✕ Limpar</a>
            <?php endif; ?>
        </form>

        <!-- Tabela -->
        <div class="table-wrap">
            <?php if (empty($leads)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p><?= $busca ? 'Nenhum lead encontrado para esta busca.' : 'Nenhum lead cadastrado ainda.' ?></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>WhatsApp</th>
                            <th>Origem</th>
                            <th>Data / Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead):
                            $wppNum = preg_replace('/\D/', '', $lead['whatsapp']);
                            $wppLink = 'https://wa.me/55' . $wppNum . '?text=' . urlencode('Olá ' . explode(' ', $lead['nome'])[0] . '! Tudo bem? Sou da C.R.I. Idiomas 😊');
                        ?>
                        <tr>
                            <td class="td-date"><?= $lead['id'] ?></td>
                            <td class="td-name"><?= htmlspecialchars($lead['nome']) ?></td>
                            <td class="td-email">
                                <a href="mailto:<?= htmlspecialchars($lead['email']) ?>">
                                    <?= htmlspecialchars($lead['email']) ?>
                                </a>
                            </td>
                            <td class="td-wpp">
                                <a href="<?= $wppLink ?>" target="_blank" rel="noopener">
                                    <?= htmlspecialchars($lead['whatsapp']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-guide">
                                    <?= htmlspecialchars($lead['origem']) ?>
                                </span>
                            </td>
                            <td class="td-date">
                                <?= date('d/m/Y H:i', strtotime($lead['criado_em'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
        <div class="pagination">
            <?php
            $qs = $busca ? '&busca=' . urlencode($busca) : '';
            for ($i = 1; $i <= $totalPaginas; $i++):
                if ($i === $pagina):
            ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?pag=<?= $i . $qs ?>"><?= $i ?></a>
            <?php endif; endfor; ?>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>

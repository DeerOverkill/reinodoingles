<?php
/* ============================================================
   C.R.I. Idiomas — salvar_lead.php
   Recebe os dados do formulário via POST (AJAX ou submit)
   e salva no banco MySQL + em CSV de backup.
   ============================================================ */

// ── Configurações do banco ──────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'reinodoingles');
define('DB_USER', 'root');        // Substitua pelo seu usuário
define('DB_PASS', '');            // Substitua pela sua senha
define('DB_PORT', 3306);

// ── CORS: permite apenas o próprio domínio ──────────────────
$allowed_origin = 'https://seudominio.com.br'; // Ajuste para seu domínio
// Em desenvolvimento local pode deixar * ou o localhost
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// ── Aceitar apenas POST ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

// ── Obter e sanitizar os dados ──────────────────────────────
$nome     = trim(filter_input(INPUT_POST, 'nome',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL)         ?? '');
$whatsapp = trim(filter_input(INPUT_POST, 'whatsapp', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$origem   = trim(filter_input(INPUT_POST, 'origem',   FILTER_SANITIZE_SPECIAL_CHARS) ?? 'guia-gratis');
$ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

// ── Validações ──────────────────────────────────────────────
$erros = [];

if (empty($nome) || mb_strlen($nome) < 2) {
    $erros[] = 'Nome inválido.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'E-mail inválido.';
}
$telSoNumeros = preg_replace('/\D/', '', $whatsapp);
if (strlen($telSoNumeros) < 10 || strlen($telSoNumeros) > 13) {
    $erros[] = 'WhatsApp inválido.';
}

if (!empty($erros)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => implode(' ', $erros)]);
    exit;
}

// ── Normalizar telefone ─────────────────────────────────────
// Guarda apenas números para padronizar
$whatsappLimpo = $telSoNumeros;

// ── Conexão com o banco ─────────────────────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Não expõe detalhes do erro ao cliente
    error_log('[CRI-LEADS] Falha na conexão: ' . $e->getMessage());
    // Tenta salvar no CSV de fallback mesmo sem banco
    salvarCsv($nome, $email, $whatsapp, $origem, $ip);
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro interno. Tente novamente.']);
    exit;
}

// ── Verificar duplicata (mesmo e-mail nas últimas 24h) ──────
$stmtCheck = $pdo->prepare("
    SELECT id FROM leads
    WHERE email = :email AND criado_em >= NOW() - INTERVAL 24 HOUR
    LIMIT 1
");
$stmtCheck->execute([':email' => $email]);
if ($stmtCheck->fetch()) {
    // Retorna sucesso mesmo assim (não revela que já existe)
    echo json_encode(['ok' => true, 'msg' => 'Cadastro realizado com sucesso!']);
    exit;
}

// ── Inserir no banco ────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO leads (nome, email, whatsapp, origem, ip_origem)
        VALUES (:nome, :email, :whatsapp, :origem, :ip)
    ");
    $stmt->execute([
        ':nome'     => mb_substr($nome,     0, 150),
        ':email'    => mb_substr($email,    0, 200),
        ':whatsapp' => mb_substr($whatsappLimpo, 0, 20),
        ':origem'   => mb_substr($origem,   0, 60),
        ':ip'       => mb_substr($ip,       0, 45),
    ]);
} catch (PDOException $e) {
    error_log('[CRI-LEADS] Falha ao inserir: ' . $e->getMessage());
    salvarCsv($nome, $email, $whatsapp, $origem, $ip);
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar. Tente novamente.']);
    exit;
}

// ── Também salvar no CSV (backup) ───────────────────────────
salvarCsv($nome, $email, $whatsapp, $origem, $ip);

// ── Resposta de sucesso ─────────────────────────────────────
echo json_encode(['ok' => true, 'msg' => 'Lead cadastrado com sucesso!']);
exit;


/* ============================================================
   Função auxiliar: salva uma linha no arquivo CSV de backup
   ============================================================ */
function salvarCsv(string $nome, string $email, string $whatsapp, string $origem, string $ip): void
{
    $arquivo = __DIR__ . '/dados/leads.csv';

    // Cria a pasta /dados/ se não existir
    if (!is_dir(__DIR__ . '/dados')) {
        mkdir(__DIR__ . '/dados', 0755, true);
    }

    // Escreve cabeçalho se o arquivo for novo
    $novo = !file_exists($arquivo);
    $fp   = fopen($arquivo, 'a');
    if (!$fp) return;

    if ($novo) {
        fputcsv($fp, ['id', 'nome', 'email', 'whatsapp', 'origem', 'ip_origem', 'criado_em']);
    }

    fputcsv($fp, [
        uniqid(),
        $nome,
        $email,
        $whatsapp,
        $origem,
        $ip,
        date('Y-m-d H:i:s'),
    ]);

    fclose($fp);
}

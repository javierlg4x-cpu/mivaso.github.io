<?php
/** Mi Vaso — sesión y seguridad compartidas por el panel y su API. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/privado/editor.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self'; form-action 'self'; frame-ancestors 'self'");

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('mivaso_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (isset($_SESSION['ultimo']) && time() - $_SESSION['ultimo'] > 4 * 3600) { $_SESSION = []; session_regenerate_id(true); }
$_SESSION['ultimo'] = time();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));

$cfg = mv_config();
$logueado = !empty($_SESSION['admin']) && $cfg['admin_hash'] !== '';
$CODIGO = MV_PRIVADO . '/codigo-instalacion.php';

function csrf_ok(): bool {
    $t = (string) ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));
    return $t !== '' && hash_equals($_SESSION['csrf'] ?? '', $t);
}
function campo_csrf(): string { return '<input type="hidden" name="csrf" value="' . mv_h($_SESSION['csrf']) . '">'; }
function ir(string $q = ''): void { header('Location: ' . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/' . ($q ? '?' . $q : ''), true, 303); exit; }

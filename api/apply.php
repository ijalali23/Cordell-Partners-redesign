<?php
/**
 * Cordell Partners - candidate application endpoint.
 *
 * Receives a candidate application (multipart/form-data, including the CV) from
 * the website and pushes it straight into the relevant Atlas project pipeline:
 *
 *   1. POST /api/v1/files            upload the CV (type=resume) -> fileId
 *   2. POST /api/v1/projects/{id}/applicants   create the applicant with that
 *                                    CV, the candidate's details, and the extra
 *                                    answers (salary, location, nationality) as
 *                                    notes. Atlas parses the CV automatically.
 *
 * The Atlas token lives only on the server (config.php or the ATLAS_API_TOKEN
 * environment variable) and is never exposed to the browser.
 *
 * PHP 7.4+ with the cURL extension.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

$config = [
    'atlas_token'           => '',
    'atlas_base'            => 'https://api.recruitwithatlas.com',
    'allowed_origins'       => [],
    'max_cv_bytes'          => 8 * 1024 * 1024,
    'rate_limit_file'       => null,
    'rate_limit_max'        => 5,
    'rate_limit_window_sec' => 3600,
];

$configFile = __DIR__ . '/config.php';
if (is_file($configFile)) {
    $loaded = require $configFile;
    if (is_array($loaded)) {
        $config = array_merge($config, $loaded);
    }
}

// Environment variable wins over the config file for the token.
$envToken = getenv('ATLAS_API_TOKEN');
if (is_string($envToken) && $envToken !== '') {
    $config['atlas_token'] = $envToken;
}

// ---------------------------------------------------------------------------
// CORS
// ---------------------------------------------------------------------------

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowOrigin = in_array($origin, $config['allowed_origins'], true);

if ($allowOrigin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

/**
 * Send a JSON response and stop.
 */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log a server-side diagnostic without leaking it to the client.
 */
function logError(string $message): void
{
    error_log('[cordell-apply] ' . $message);
}

// ---------------------------------------------------------------------------
// Method guard
// ---------------------------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

if (!$config['atlas_token']) {
    logError('Atlas token is not configured.');
    respond(500, ['ok' => false, 'error' => 'The application service is not configured. Please email your CV to info@cordell-partners.com.']);
}

// ---------------------------------------------------------------------------
// Honeypot (silent spam trap)
// ---------------------------------------------------------------------------

if (!empty($_POST['company_website'])) {
    // A bot filled the hidden field. Pretend everything is fine.
    respond(200, ['ok' => true]);
}

// ---------------------------------------------------------------------------
// Rate limiting (optional, best-effort, file-based)
// ---------------------------------------------------------------------------

if (!empty($config['rate_limit_file'])) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $window = (int) $config['rate_limit_window_sec'];
    $max = (int) $config['rate_limit_max'];
    $store = [];

    $fp = @fopen($config['rate_limit_file'], 'c+');
    if ($fp !== false) {
        if (flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $store = $decoded;
            }
            // Drop stale entries and count this IP's recent hits.
            $hits = array_filter(
                $store[$ip] ?? [],
                static fn($t) => ($now - (int) $t) < $window
            );
            if (count($hits) >= $max) {
                flock($fp, LOCK_UN);
                fclose($fp);
                respond(429, ['ok' => false, 'error' => 'Too many applications from this connection. Please try again later.']);
            }
            $hits[] = $now;
            $store[$ip] = array_values($hits);
            // Garbage-collect other IPs so the file does not grow forever.
            foreach ($store as $k => $times) {
                $store[$k] = array_values(array_filter($times, static fn($t) => ($now - (int) $t) < $window));
                if (!$store[$k]) {
                    unset($store[$k]);
                }
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($store));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

// ---------------------------------------------------------------------------
// Validate input
// ---------------------------------------------------------------------------

/** Trim a posted string field. */
function field(string $name): string
{
    $v = $_POST[$name] ?? '';
    return is_string($v) ? trim($v) : '';
}

$projectId        = field('projectId');
$role             = field('role');
$fullName         = field('name');
$email            = field('email');
$phone            = field('phone');
$currentSalary    = field('currentSalary');
$expectedSalary   = field('expectedSalary');
$locationPref     = field('locationPreference');
$nationality      = field('nationality');

$errors = [];

$uuidRe = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
if (!preg_match($uuidRe, $projectId)) {
    $errors['projectId'] = 'We could not identify the role you applied for.';
}

if ($fullName === '' || mb_strlen($fullName) < 2) {
    $errors['name'] = 'Please enter your full name.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

// CV is required.
$cv = $_FILES['cv'] ?? null;
if (!$cv || !isset($cv['error']) || $cv['error'] === UPLOAD_ERR_NO_FILE) {
    $errors['cv'] = 'Please attach your CV.';
} elseif ($cv['error'] !== UPLOAD_ERR_OK) {
    $errors['cv'] = $cv['error'] === UPLOAD_ERR_INI_SIZE || $cv['error'] === UPLOAD_ERR_FORM_SIZE
        ? 'Your CV is too large.'
        : 'Your CV could not be uploaded. Please try again.';
} elseif (($cv['size'] ?? 0) <= 0) {
    $errors['cv'] = 'Your CV appears to be empty.';
} elseif (($cv['size'] ?? 0) > $config['max_cv_bytes']) {
    $errors['cv'] = 'Your CV must be ' . (int) round($config['max_cv_bytes'] / (1024 * 1024)) . ' MB or smaller.';
} else {
    // Extension + MIME check. Atlas accepts PDF, DOC and DOCX.
    $ext = strtolower(pathinfo($cv['name'] ?? '', PATHINFO_EXTENSION));
    $allowedExt = ['pdf', 'doc', 'docx'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $finfo ? (finfo_file($finfo, $cv['tmp_name']) ?: '') : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $allowedMime = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // Some servers report DOC/DOCX loosely:
        'application/octet-stream',
        'application/zip',
    ];
    if (!in_array($ext, $allowedExt, true)) {
        $errors['cv'] = 'Please attach your CV as a PDF, DOC or DOCX file.';
    } elseif ($mime !== '' && !in_array($mime, $allowedMime, true)) {
        $errors['cv'] = 'Please attach your CV as a PDF, DOC or DOCX file.';
    }
}

if ($errors) {
    respond(422, ['ok' => false, 'error' => 'Please check the highlighted fields.', 'fields' => $errors]);
}

// Split the full name into first / last for Atlas.
$nameParts = preg_split('/\s+/', $fullName, 2);
$firstName = $nameParts[0] ?? $fullName;
$lastName  = $nameParts[1] ?? '';

// ---------------------------------------------------------------------------
// Compose the notes block from the extra answers
// ---------------------------------------------------------------------------

$noteLines = ['Submitted through cordell-partners.com.'];
if ($role !== '')           { $noteLines[] = 'Role applied for: ' . $role; }
if ($currentSalary !== '')  { $noteLines[] = 'Current salary: ' . $currentSalary; }
if ($expectedSalary !== '') { $noteLines[] = 'Expected salary: ' . $expectedSalary; }
if ($locationPref !== '')   { $noteLines[] = 'Location preference: ' . $locationPref; }
if ($nationality !== '')    { $noteLines[] = 'Nationality: ' . $nationality; }
if ($phone !== '')          { $noteLines[] = 'Phone: ' . $phone; }
$notes = implode("\n", $noteLines);

// ---------------------------------------------------------------------------
// Atlas call helper
// ---------------------------------------------------------------------------

/**
 * Perform a cURL request to Atlas. Returns [httpStatus, decodedBody|null, rawBody].
 */
function atlasRequest(string $method, string $url, string $token, $body, bool $multipart): array
{
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

    if ($body !== null) {
        if ($multipart) {
            // $body is an array including a CURLFile; let cURL set the boundary.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        logError('cURL error for ' . $method . ' ' . $url . ': ' . $err);
        return [0, null, ''];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);
    return [$status, is_array($decoded) ? $decoded : null, (string) $raw];
}

$base = rtrim($config['atlas_base'], '/');
$token = $config['atlas_token'];

// ---------------------------------------------------------------------------
// Step 1 - upload the CV
// ---------------------------------------------------------------------------

$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($cv['name'] ?? 'cv'));
$curlFile = new CURLFile($cv['tmp_name'], $mime ?: 'application/octet-stream', $safeName);

[$upStatus, $upBody, $upRaw] = atlasRequest(
    'POST',
    $base . '/api/v1/files',
    $token,
    ['file' => $curlFile, 'type' => 'resume'],
    true
);

if ($upStatus === 429) {
    respond(429, ['ok' => false, 'error' => 'We are receiving a lot of applications right now. Please try again in a few minutes.']);
}
if ($upStatus < 200 || $upStatus >= 300 || !$upBody || ($upBody['status'] ?? '') !== 'ok') {
    logError('CV upload failed (' . $upStatus . '): ' . substr($upRaw, 0, 500));
    respond(502, ['ok' => false, 'error' => 'We could not process your CV. Please email it to info@cordell-partners.com and we will add you to the search.']);
}

$fileId = $upBody['data']['id'] ?? '';
if (!preg_match($uuidRe, (string) $fileId)) {
    logError('CV upload returned no file id: ' . substr($upRaw, 0, 500));
    respond(502, ['ok' => false, 'error' => 'We could not process your CV. Please email it to info@cordell-partners.com and we will add you to the search.']);
}

// ---------------------------------------------------------------------------
// Step 2 - create the applicant in the project pipeline
// ---------------------------------------------------------------------------

$applicant = [
    'fileId'    => $fileId,
    'firstName' => $firstName,
    'lastName'  => $lastName !== '' ? $lastName : null,
    'email'     => $email,
    'phone'     => $phone !== '' ? $phone : null,
    'source'    => 'form',
    'notes'     => $notes,
];

[$apStatus, $apBody, $apRaw] = atlasRequest(
    'POST',
    $base . '/api/v1/projects/' . rawurlencode($projectId) . '/applicants',
    $token,
    $applicant,
    false
);

if ($apStatus === 429) {
    respond(429, ['ok' => false, 'error' => 'We are receiving a lot of applications right now. Please try again in a few minutes.']);
}
if ($apStatus === 404) {
    logError('Applicant create: project not found ' . $projectId);
    respond(404, ['ok' => false, 'error' => 'This role is no longer open. Browse our current mandates or send your CV in confidence.']);
}
if ($apStatus < 200 || $apStatus >= 300 || !$apBody || ($apBody['status'] ?? '') !== 'ok') {
    logError('Applicant create failed (' . $apStatus . '): ' . substr($apRaw, 0, 500));
    respond(502, ['ok' => false, 'error' => 'Your CV reached us but we could not complete your application automatically. Please email info@cordell-partners.com.']);
}

// ---------------------------------------------------------------------------
// Success
// ---------------------------------------------------------------------------

respond(201, ['ok' => true]);

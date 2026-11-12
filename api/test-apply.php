<?php
/**
 * CLI smoke test for the Atlas application flow.
 *
 * This talks to the REAL Atlas Open API, so it creates a REAL applicant in the
 * project you name. Use a disposable test project, or delete the applicant in
 * Atlas afterwards.
 *
 * Usage:
 *   ATLAS_API_TOKEN=xxxxx php test-apply.php <projectId> <path-to-cv.pdf>
 *
 * It prints each step's HTTP status and response so you can confirm the token,
 * the project id, and the two endpoints before wiring up the website.
 */

declare(strict_types=1);

$token = getenv('ATLAS_API_TOKEN') ?: '';
$projectId = $argv[1] ?? '';
$cvPath = $argv[2] ?? '';
$base = getenv('ATLAS_BASE') ?: 'https://api.recruitwithatlas.com';

if ($token === '' || $projectId === '' || $cvPath === '') {
    fwrite(STDERR, "Usage: ATLAS_API_TOKEN=xxx php test-apply.php <projectId> <cv.pdf>\n");
    exit(2);
}
if (!is_file($cvPath)) {
    fwrite(STDERR, "CV file not found: $cvPath\n");
    exit(2);
}

function call(string $method, string $url, string $token, $body, bool $multipart): array
{
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    if ($body !== null) {
        if ($multipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($raw === false) {
        $raw = 'CURL ERROR: ' . curl_error($ch);
    }
    curl_close($ch);
    return [$status, $raw];
}

echo "== Step 1: upload CV ==\n";
$mime = function_exists('mime_content_type') ? (mime_content_type($cvPath) ?: 'application/pdf') : 'application/pdf';
$file = new CURLFile($cvPath, $mime, basename($cvPath));
[$s1, $r1] = call('POST', rtrim($base, '/') . '/api/v1/files', $token, ['file' => $file, 'type' => 'resume'], true);
echo "HTTP $s1\n$r1\n\n";

$fileId = '';
$d1 = json_decode($r1, true);
if (is_array($d1)) {
    $fileId = $d1['data']['id'] ?? '';
}
if ($fileId === '') {
    fwrite(STDERR, "No file id returned - stopping.\n");
    exit(1);
}
echo "fileId = $fileId\n\n";

echo "== Step 2: create applicant ==\n";
$applicant = [
    'fileId'    => $fileId,
    'firstName' => 'Test',
    'lastName'  => 'Applicant',
    'email'     => 'test.applicant+' . time() . '@example.com',
    'phone'     => '+971500000000',
    'source'    => 'form',
    'notes'     => "Submitted through cordell-partners.com (TEST).\nCurrent salary: AED 30,000\nExpected salary: AED 40,000\nLocation preference: Dubai\nNationality: Test",
];
[$s2, $r2] = call('POST', rtrim($base, '/') . '/api/v1/projects/' . rawurlencode($projectId) . '/applicants', $token, $applicant, false);
echo "HTTP $s2\n$r2\n";

exit(($s2 >= 200 && $s2 < 300) ? 0 : 1);

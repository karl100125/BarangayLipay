<?php
// paymongo_helper.php

// ✅ Check muna kung defined na ang BASE_URL bago i-define
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    // Alisin ang '/resident', '/admin', '/api' kung meron
    $basePath = preg_replace('#/(resident|admin|api|webhook)#', '', $scriptDir);
    define('BASE_URL', $protocol . $host . rtrim($basePath, '/\\'));
}

// ✅ PayMongo Keys (PALITAN MO ITO)
if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY', 'sk_test_k8NqxEZh3F5YSCwCqmn4WJyn'); //  Palitan ng test key mo
}

if (!defined('PAYMONGO_PUBLIC_KEY')) {
    define('PAYMONGO_PUBLIC_KEY', 'pk_test_zsHCbyJSarcvWHBQ8h8YwpSW-+'); //  Palitan ng test key mo
}

if (!defined('PAYMONGO_WEBHOOK_SECRET')) {
    define('PAYMONGO_WEBHOOK_SECRET', 'whsk_PuwUkb5DY9NArHqVDRsUL7Yb'); //  Palitan ng webhook secret mo
}

if (!defined('PAYMONGO_API_BASE')) {
    define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1');
}

/**
 * Gumawa ng PayMongo GCash payment link
 * 
 * @param int    $amountCentavos  Halaga sa centavos (e.g. 7500 = ₱75.00)
 * @param string $description     Paglalarawan ng bayad
 * @param string $requestId       ID ng document request para sa reference
 * @param array  $metadata        Extra data na ibabalik sa webhook
 * @return array ['success' => bool, 'checkout_url' => string, 'link_id' => string, 'error' => string]
 */
function createPayMongoPayment(int $amountCentavos, string $description, string $requestId, array $metadata = []): array
{
    // Validate amount
    if ($amountCentavos < 100) {
        return [
            'success' => false, 
            'error' => 'Minimum payment amount is ₱1.00'
        ];
    }
    
    if ($amountCentavos > 10000000) {
        return [
            'success' => false, 
            'error' => 'Maximum payment amount is ₱100,000'
        ];
    }
    
    // Check if secret key is configured
    if (empty(PAYMONGO_SECRET_KEY) || PAYMONGO_SECRET_KEY === 'sk_live_xxx') {
        error_log("PayMongo secret key is not configured properly");
        return [
            'success' => false, 
            'error' => 'Payment system is not properly configured. Please contact administrator.'
        ];
    }
    
    // Sanitize inputs
    $description = substr(trim($description), 0, 200);
    
    // Build payload with proper URLs
    $successUrl = BASE_URL . '/resident/payment-success.php?request_id=' . urlencode($requestId);
    $failedUrl = BASE_URL . '/resident/payment-failed.php?request_id=' . urlencode($requestId);
    
    $payload = [
        'data' => [
            'attributes' => [
                'amount'      => $amountCentavos,
                'currency'    => 'PHP',
                'description' => $description,
                'redirect'    => [
                    'success' => $successUrl,
                    'failed'  => $failedUrl,
                ],
                'payment_method_allowed' => ['gcash', 'card'],
                'payment_method_options' => [
                    'card' => ['request_three_d_secure' => 'any'],
                ],
                'metadata' => array_merge($metadata, [
                    'request_id' => $requestId,
                    'timestamp' => date('Y-m-d H:i:s')
                ]),
            ],
        ],
    ];

    // Initialize cURL
    $ch = curl_init(PAYMONGO_API_BASE . '/links');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    
    // Execute cURL
    $response = curl_exec($ch);
    
    if ($response === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("PayMongo cURL error (request_id={$requestId}): {$curlError}");
        
        return [
            'success' => false, 
            'error' => 'Unable to connect to payment gateway. Please try again later.'
        ];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['data']['attributes']['checkout_url'])) {
        error_log("PayMongo link created: request_id={$requestId}, link_id={$data['data']['id']}");
        
        return [
            'success'      => true,
            'checkout_url' => $data['data']['attributes']['checkout_url'],
            'link_id'      => $data['data']['id'],
        ];
    }
    
    $errorMsg = $data['errors'][0]['detail'] ?? 'Unknown error occurred';
    error_log("PayMongo API error (request_id={$requestId}): " . json_encode($data));
    
    return [
        'success' => false, 
        'error' => 'Payment processing failed: ' . $errorMsg
    ];
}

/**
 * Verify PayMongo webhook signature
 */
function verifyPayMongoWebhook(string $payload, string $signature): bool
{
    if (empty(PAYMONGO_WEBHOOK_SECRET) || PAYMONGO_WEBHOOK_SECRET === 'whsk_xxx') {
        error_log("PayMongo webhook secret is not configured");
        return false;
    }
    
    $computedSignature = hash_hmac('sha256', $payload, PAYMONGO_WEBHOOK_SECRET);
    return hash_equals($computedSignature, $signature);
}

/**
 * Get PayMongo payment status
 */
function getPayMongoPaymentStatus(string $linkId): ?array
{
    if (empty(PAYMONGO_SECRET_KEY)) {
        return null;
    }
    
    $ch = curl_init(PAYMONGO_API_BASE . "/links/{$linkId}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['data']['attributes'] ?? null;
    }
    
    return null;
}

// ============================================================
// 🆕 MGA DAGDAG NA FUNCTIONS PARA SA WEBHOOK AT DATABASE
// ============================================================

/**
 * I-update ang payment status ng document request
 * 
 * @param PDO    $conn        Database connection
 * @param int    $requestId   ID ng document request
 * @param string $status      Bagong status (paid, processing, payment_failed)
 * @param array  $paymentData Optional na data gaya ng payment_id, amount, etc.
 * @return bool
 */
function updateDocumentPaymentStatus($conn, int $requestId, string $status, array $paymentData = []): bool
{
    try {
        // Base fields na iu-update
        $fields = [
            'payment_status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Add optional fields kung meron
        if (!empty($paymentData['payment_id'])) {
            $fields['payment_id'] = $paymentData['payment_id'];
        }
        
        if (!empty($paymentData['payment_amount'])) {
            $fields['payment_amount'] = $paymentData['payment_amount'];
        }
        
        if (!empty($paymentData['payment_currency'])) {
            $fields['payment_currency'] = $paymentData['payment_currency'];
        }
        
        if (!empty($paymentData['paymongo_source_id'])) {
            $fields['paymongo_source_id'] = $paymentData['paymongo_source_id'];
        }
        
        if ($status === 'paid' || $status === 'processing') {
            $fields['payment_date'] = date('Y-m-d H:i:s');
        }
        
        // I-build ang SQL query
        $setClause = '';
        $values = [];
        foreach ($fields as $key => $value) {
            $setClause .= "$key = ?, ";
            $values[] = $value;
        }
        $setClause = rtrim($setClause, ', ');
        
        // Add request_id sa dulo ng values
        $values[] = $requestId;
        
        $sql = "UPDATE document_requests SET $setClause WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        return $stmt->execute($values);
        
    } catch (PDOException $e) {
        error_log("updateDocumentPaymentStatus error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kunin ang document request details
 * 
 * @param PDO $conn      Database connection
 * @param int $requestId ID ng document request
 * @return array|null
 */
function getDocumentRequest($conn, int $requestId): ?array
{
    try {
        $stmt = $conn->prepare("SELECT * FROM document_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getDocumentRequest error: " . $e->getMessage());
        return null;
    }
}

/**
 * I-verify kung valid ang webhook event
 * 
 * @param array $data Webhook payload data
 * @return bool
 */
function isValidWebhookEvent(array $data): bool
{
    // Check kung may event type
    if (empty($data['data']['attributes']['type'])) {
        return false;
    }
    
    // List of valid event types
    $validEvents = [
        'payment.paid',
        'source.chargeable',
        'source.failed',
        'payment.failed'
    ];
    
    return in_array($data['data']['attributes']['type'], $validEvents);
}

/**
 * Extract request ID from webhook metadata
 * 
 * @param array $data Webhook payload data
 * @return int|null
 */
function extractRequestIdFromWebhook(array $data): ?int
{
    // Try to get from metadata
    $metadata = $data['data']['attributes']['metadata'] ?? [];
    $metadata2 = $data['data']['attributes']['data']['attributes']['metadata'] ?? [];
    
    $requestId = $metadata['request_id'] ?? $metadata2['request_id'] ?? null;
    
    return $requestId ? (int)$requestId : null;
}

/**
 * Mag-log ng webhook activity
 * 
 * @param string $eventType  Uri ng event
 * @param int    $requestId  ID ng request
 * @param string $status     Status ng pag-process
 * @param string $message    Optional na mensahe
 */
function logWebhookActivity(string $eventType, int $requestId, string $status, string $message = ''): void
{
    $logEntry = sprintf(
        "[%s] Event: %s | Request ID: %d | Status: %s%s\n",
        date('Y-m-d H:i:s'),
        $eventType,
        $requestId,
        $status,
        $message ? " | Message: $message" : ''
    );
    
    error_log($logEntry);
    
    // Optional: Save to database logs table
    // Kung may logs table kayo, pwede nyo rin i-save doon
}

/**
 * Process webhook payment data
 * 
 * @param PDO   $conn    Database connection
 * @param array $data    Webhook payload
 * @param array $payment Payment data
 * @return bool
 */
function processWebhookPayment($conn, array $data, array $payment): bool
{
    $requestId = extractRequestIdFromWebhook($data);
    
    if (!$requestId) {
        logWebhookActivity('unknown', 0, 'error', 'No request ID found in webhook');
        return false;
    }
    
    // Check if request exists
    $request = getDocumentRequest($conn, $requestId);
    if (!$request) {
        logWebhookActivity('payment.paid', $requestId, 'error', 'Request not found');
        return false;
    }
    
    // Check if already paid
    if ($request['payment_status'] === 'paid') {
        logWebhookActivity('payment.paid', $requestId, 'warning', 'Already paid');
        return true;
    }
    
    // Prepare payment data
    $paymentData = [
        'payment_id' => $payment['id'] ?? null,
        'payment_amount' => ($payment['attributes']['amount'] ?? 0) / 100, // Convert from centavos
        'payment_currency' => $payment['attributes']['currency'] ?? 'PHP',
    ];
    
    // Update status
    $result = updateDocumentPaymentStatus($conn, $requestId, 'paid', $paymentData);
    
    if ($result) {
        logWebhookActivity('payment.paid', $requestId, 'success', 'Payment confirmed');
    } else {
        logWebhookActivity('payment.paid', $requestId, 'error', 'Failed to update database');
    }
    
    return $result;
}

/**
 * Process webhook source chargeable event
 * 
 * @param PDO   $conn    Database connection
 * @param array $data    Webhook payload
 * @param array $source  Source data
 * @return bool
 */
function processWebhookSource($conn, array $data, array $source): bool
{
    $requestId = extractRequestIdFromWebhook($data);
    
    if (!$requestId) {
        logWebhookActivity('unknown', 0, 'error', 'No request ID found in webhook');
        return false;
    }
    
    $sourceId = $source['id'] ?? null;
    $sourceStatus = $source['attributes']['status'] ?? '';
    
    if (!$sourceId) {
        logWebhookActivity('source.chargeable', $requestId, 'error', 'No source ID found');
        return false;
    }
    
    // Update source ID
    try {
        $stmt = $conn->prepare("UPDATE document_requests 
                               SET paymongo_source_id = ?, 
                                   updated_at = NOW() 
                               WHERE id = ?");
        $result = $stmt->execute([$sourceId, $requestId]);
        
        if ($result) {
            logWebhookActivity('source.chargeable', $requestId, 'success', "Source ID: $sourceId, Status: $sourceStatus");
        } else {
            logWebhookActivity('source.chargeable', $requestId, 'error', 'Failed to update source ID');
        }
        
        return $result;
        
    } catch (PDOException $e) {
        logWebhookActivity('source.chargeable', $requestId, 'error', $e->getMessage());
        return false;
    }
}

/**
 * Process webhook source failed event
 * 
 * @param PDO   $conn    Database connection
 * @param array $data    Webhook payload
 * @param array $source  Source data
 * @return bool
 */
function processWebhookSourceFailed($conn, array $data, array $source): bool
{
    $requestId = extractRequestIdFromWebhook($data);
    
    if (!$requestId) {
        logWebhookActivity('unknown', 0, 'error', 'No request ID found in webhook');
        return false;
    }
    
    // Check if request exists
    $request = getDocumentRequest($conn, $requestId);
    if (!$request) {
        logWebhookActivity('source.failed', $requestId, 'error', 'Request not found');
        return false;
    }
    
    // Check if already paid (para hindi ma-override)
    if ($request['payment_status'] === 'paid') {
        logWebhookActivity('source.failed', $requestId, 'warning', 'Already paid, ignoring failed webhook');
        return true;
    }
    
    // Update status to payment_failed
    $result = updateDocumentPaymentStatus($conn, $requestId, 'payment_failed');
    
    if ($result) {
        logWebhookActivity('source.failed', $requestId, 'success', 'Payment failed recorded');
    } else {
        logWebhookActivity('source.failed', $requestId, 'error', 'Failed to update database');
    }
    
    return $result;
}

/**
 * Mag-check kung ang isang request ay pwedeng i-process
 * 
 * @param array $request Document request data
 * @return bool
 */
function isRequestProcessable(array $request): bool
{
    // Hindi pwedeng i-process kung:
    // - Paid na
    // - Completed na
    // - Cancelled na
    $blockedStatuses = ['paid', 'completed', 'cancelled', 'processing'];
    
    if (in_array($request['payment_status'] ?? '', $blockedStatuses)) {
        return false;
    }
    
    return true;
}

/**
 * Get payment summary ng isang user
 * 
 * @param PDO $conn   Database connection
 * @param int $userId User ID
 * @return array
 */
function getUserPaymentSummary($conn, int $userId): array
{
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'pending_payment_online' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN payment_status = 'payment_failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN payment_status = 'paid' THEN amount_total ELSE 0 END) as total_paid_amount
            FROM document_requests 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
    } catch (PDOException $e) {
        error_log("getUserPaymentSummary error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent payments ng isang user
 * 
 * @param PDO $conn   Database connection
 * @param int $userId User ID
 * @param int $limit  Limit ng records
 * @return array
 */
function getUserRecentPayments($conn, int $userId, int $limit = 5): array
{
    try {
        $stmt = $conn->prepare("
            SELECT 
                id,
                document_type,
                amount_total,
                payment_status,
                payment_date,
                created_at
            FROM document_requests 
            WHERE user_id = ? 
            AND payment_status IN ('paid', 'pending_payment_online', 'payment_failed')
            ORDER BY updated_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } catch (PDOException $e) {
        error_log("getUserRecentPayments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate payment reference number
 * 
 * @param int $requestId Request ID
 * @return string
 */
function generatePaymentReference(int $requestId): string
{
    $year = date('Y');
    $month = date('m');
    $day = date('d');
    
    return sprintf('PAY-%s-%s-%s-%06d', $year, $month, $day, $requestId);
}

/**
 * Check kung may pending payment ang user
 * 
 * @param PDO $conn   Database connection
 * @param int $userId User ID
 * @return bool
 */
function hasPendingPayment($conn, int $userId): bool
{
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM document_requests 
            WHERE user_id = ? 
            AND payment_status = 'pending_payment_online'
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
        
    } catch (PDOException $e) {
        error_log("hasPendingPayment error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get payment status label
 * 
 * @param string $status Payment status
 * @return string
 */
function getPaymentStatusLabel(string $status): string
{
    $labels = [
        'pending_payment_online' => 'Pending Payment',
        'pending_payment_barangay' => 'Pay at Barangay',
        'paid' => 'Paid',
        'payment_failed' => 'Payment Failed',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    
    return $labels[$status] ?? $status;
}

/**
 * Get payment status badge color
 * 
 * @param string $status Payment status
 * @return string
 */
function getPaymentStatusBadgeClass(string $status): string
{
    $classes = [
        'pending_payment_online' => 'bg-yellow-100 text-yellow-800',
        'pending_payment_barangay' => 'bg-blue-100 text-blue-800',
        'paid' => 'bg-green-100 text-green-800',
        'payment_failed' => 'bg-red-100 text-red-800',
        'processing' => 'bg-purple-100 text-purple-800',
        'completed' => 'bg-gray-100 text-gray-800',
        'cancelled' => 'bg-gray-100 text-gray-800'
    ];
    
    return $classes[$status] ?? 'bg-gray-100 text-gray-800';
}
?>
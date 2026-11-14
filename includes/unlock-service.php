<?php
declare(strict_types=1);
function simulate_unlock(int $userId, int $listingId, string $method, bool $fail = false): array {
    if (!in_array($method,['gcash','maya','bank'],true)) throw new DomainException('Choose a simulated payment method.');
    $pdo=db(); $pdo->beginTransaction();
    try {
        // Serialize attempts from the same student, including requests in separate sessions.
        $student=one('SELECT role,status FROM users WHERE id=? FOR UPDATE',[$userId]);
        if (!$student || $student['role'] !== 'student' || $student['status'] !== 'active') throw new DomainException('An active student account is required.');
        $d=one('SELECT * FROM dormitories WHERE id=? FOR UPDATE',[$listingId]);
        if (!$d || !is_public($d)) throw new DomainException('This listing is no longer available.');
        $existing=one("SELECT * FROM unlock_transactions WHERE user_id=? AND dormitory_id=? AND status='success'",[$userId,$listingId]);
        if ($existing) { $pdo->commit(); return $existing; }
        $amount=decimal((string)scalar("SELECT setting_value FROM settings WHERE setting_key='unlock_fee'"));
        if (!$amount) throw new DomainException('The unlock fee is not configured correctly.');
        $reference='SIM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
        $status=$fail ? 'failed' : 'success';
        query('INSERT INTO unlock_transactions (reference,user_id,dormitory_id,payment_method,amount,status) VALUES (?,?,?,?,?,?)',[$reference,$userId,$listingId,$method,$amount,$status]);
        $transaction=(int)$pdo->lastInsertId();
        if (!$fail) query('INSERT INTO unlocked_listings (user_id,dormitory_id,transaction_id) VALUES (?,?,?)',[$userId,$listingId,$transaction]);
        query('INSERT INTO activity_logs (user_id,action,details) VALUES (?,?,?)',[$userId,'unlock.' . $status,$reference]);
        $result=one('SELECT * FROM unlock_transactions WHERE id=?',[$transaction]);
        $pdo->commit(); return $result;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

<?php
declare(strict_types=1);

namespace App\Services\Funds;

use App\Database;
use App\Services\AccountMetricsService;
use App\Services\DecimalMath;
use App\Services\Admin\AdminActivityLogger;
use PDO;
use RuntimeException;
use Throwable;

/** Virtual ledger operations shared by funding workflows and authorized Demo Admin adjustments. */
final class TransactionService
{
    private function __construct(private readonly PDO $pdo) {}
    public static function demo(): self { return new self(Database::connection()); }

    public function adjustVirtualBalance(string $accountNumber, int $adminId, string $direction, string $rawAmount, string $reason, string $idempotencyKey): array
    {
        if (!in_array($direction, ['credit','debit'], true)) throw new RuntimeException('Choose Credit or Debit Virtual Funds.');
        $amount = $this->amount($rawAmount);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) throw new RuntimeException('Enter a reason of 1–500 characters.');
        if (preg_match('/^[a-f0-9]{32}$/', $idempotencyKey) !== 1) throw new RuntimeException('Refresh the account page and submit again.');

        $this->pdo->beginTransaction();
        try {
            // Match Phase 11/13/15 lock order: lock the Demo Account before reading positions or ledger rows.
            $query = $this->pdo->prepare('SELECT d.*,u.status AS user_status,u.user_reference,u.first_name,u.last_name FROM demo_accounts d JOIN users u ON u.id=d.user_id WHERE d.account_number=? LIMIT 1 FOR UPDATE');
            $query->execute([$accountNumber]);
            $account = $query->fetch();
            if (!$account) throw new RuntimeException('Demo Account not found.',404);
            if ($account['status'] === 'closed') throw new RuntimeException('A closed Demo Account cannot be adjusted.',409);

            $existing = $this->pdo->prepare('SELECT id,amount,balance_before,balance_after,created_by_admin_id FROM transactions WHERE demo_account_id=? AND idempotency_key=? LIMIT 1');
            $existing->execute([(int)$account['id'], $idempotencyKey]);
            if ($row = $existing->fetch()) {
                $snapshot = AccountMetricsService::demo()->snapshot((int)$account['user_id'], (int)$account['id']);
                $this->pdo->commit();
                return $this->result($account, $row, $snapshot, true);
            }

            $snapshot = AccountMetricsService::demo()->snapshot((int)$account['user_id'], (int)$account['id']);
            $metrics = $snapshot['metrics'];
            $before = (string)$account['balance'];
            if ($direction === 'debit') {
                if ($metrics['free_margin'] === null) throw new RuntimeException('Debit capacity cannot be confirmed while account valuation is unavailable.');
                $capacity = DecimalMath::compare($before, (string)$metrics['free_margin']) < 0 ? $before : (string)$metrics['free_margin'];
                if (DecimalMath::compare($capacity, '0') < 0) $capacity = '0';
                if (DecimalMath::compare($amount, $capacity) > 0) throw new RuntimeException('This debit exceeds the safe Virtual Balance and Free Margin capacity.');
                $after = $this->money(DecimalMath::sub($before, $amount));
            } else {
                $after = $this->money(DecimalMath::add($before, $amount));
            }

            $update = $this->pdo->prepare('UPDATE demo_accounts SET balance=? WHERE id=? AND user_id=?');
            $update->execute([$after, (int)$account['id'], (int)$account['user_id']]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Virtual Balance could not be updated.');

            $description = mb_substr($reason, 0, 500);
            $insert = $this->pdo->prepare("INSERT INTO transactions (user_id,demo_account_id,transaction_type,amount,idempotency_key,created_by_admin_id,balance_before,balance_after,reference_type,description,status) VALUES (?,?, 'admin_adjustment', ?,?,?, ?,?, 'admin_balance_adjustment',?,'completed')");
            $insert->execute([(int)$account['user_id'], (int)$account['id'], $amount, $idempotencyKey, $adminId, $before, $after, $description]);
            $transactionId = (int)$this->pdo->lastInsertId();

            AdminActivityLogger::logOrFail(
                $adminId,
                'demo_balance_adjusted',
                'demo_account',
                (int)$account['id'],
                sprintf('Account %s · Owner %s · %s virtual funds %s %s → %s · Transaction MF-ADJ-%010d · Reason: %s', $accountNumber, (string)$account['user_reference'], strtoupper($direction), $amount, $before, $after, $transactionId, $description)
            );

            // Re-read the authoritative snapshot after changing Balance. Credit/debit never touches realized P/L.
            $updatedSnapshot = AccountMetricsService::demo()->snapshot((int)$account['user_id'], (int)$account['id']);
            $this->pdo->commit();
            return $this->result($account, [
                'id' => $transactionId,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'created_by_admin_id' => $adminId,
            ], $updatedSnapshot, false);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function result(array $account, array $transaction, array $snapshot, bool $duplicate): array
    {
        return [
            'duplicate' => $duplicate,
            'account_number' => (string)$account['account_number'],
            'owner_reference' => (string)$account['user_reference'],
            'direction' => DecimalMath::compare((string)$transaction['balance_after'], (string)$transaction['balance_before']) >= 0 ? 'credit' : 'debit',
            'amount' => (string)$transaction['amount'],
            'balance_before' => (string)$transaction['balance_before'],
            'balance_after' => (string)$transaction['balance_after'],
            'transaction_reference' => 'MF-ADJ-' . str_pad((string)$transaction['id'], 10, '0', STR_PAD_LEFT),
            'metrics' => $snapshot['metrics'],
        ];
    }

    private function amount(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{1,16})(?:\.(\d{1,2}))?$/', $raw, $match) !== 1) throw new RuntimeException('Enter a positive amount with up to two decimal places.');
        $amount = $match[1] . '.' . str_pad($match[2] ?? '', 2, '0');
        if (DecimalMath::compare($amount, '0') <= 0) throw new RuntimeException('The adjustment amount must be greater than zero.');
        return $amount;
    }

    private function money(string $value): string
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $match) !== 1) {
            throw new RuntimeException('The virtual balance could not be calculated safely.');
        }
        return $match[1] . $match[2] . '.' . str_pad(substr($match[3] ?? '', 0, 2), 2, '0');
    }
}

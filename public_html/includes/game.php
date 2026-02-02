<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensure_game_state(int $user_id): void
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT user_id FROM game_state WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user_id]);

    if ($stmt->fetchColumn() === false) {
        $insert = $pdo->prepare('INSERT INTO game_state (user_id, money, balance) VALUES (:user_id, 0, 0)');
        $insert->execute([':user_id' => $user_id]);
    }
}

function get_user_money(int $user_id): float
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT money, balance FROM game_state WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return 0.0;
    }

    $money = (float)($row['money'] ?? 0);
    $balance = (float)($row['balance'] ?? 0);

    return $money + $balance;
}

function has_player_profile(int $user_id): bool
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT 1 FROM player_profile WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user_id]);

    return (bool)$stmt->fetchColumn();
}

function ensure_player_profile(int $user_id): void
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT user_id FROM player_profile WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user_id]);

    if ($stmt->fetchColumn() === false) {
        $insert = $pdo->prepare('INSERT INTO player_profile (user_id, character_name, gender, age, country, life_goal) VALUES (:user_id, "", "", 0, "", "")');
        $insert->execute([':user_id' => $user_id]);
    }
}

function ensure_player_stats(int $user_id): void
{
    $pdo = get_pdo();

    try {
        $stmt = $pdo->prepare('SELECT user_id FROM player_stats WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $user_id]);

        if ($stmt->fetchColumn() === false) {
            $insert = $pdo->prepare('INSERT INTO player_stats (user_id, health, energy, happiness) VALUES (:user_id, 100, 100, 100)');
            $insert->execute([':user_id' => $user_id]);
        }
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1146) {
            return;
        }
        throw $e;
    }
}

function get_player_stats(int $user_id): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT health, energy, happiness FROM player_stats WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user_id]);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats === false) {
        return ['health' => 0, 'energy' => 0, 'happiness' => 0];
    }

    return [
        'health' => (int)($stats['health'] ?? 0),
        'energy' => (int)($stats['energy'] ?? 0),
        'happiness' => (int)($stats['happiness'] ?? 0),
    ];
}

function get_game_balance(int $user_id): float
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT balance FROM game_state WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user_id]);
    $balance = $stmt->fetchColumn();

    return $balance !== false ? (float)$balance : 0.0;
}

function log_transaction(int $user_id, string $type, float $amount, string $note = '', ?PDO $pdo = null): void
{
    $pdo = $pdo ?? get_pdo();
    $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, note, created_at) VALUES (:user_id, :type, :amount, :note, NOW())');
    $stmt->execute([
        ':user_id' => $user_id,
        ':type' => $type,
        ':amount' => $amount,
        ':note' => $note,
    ]);
}

function get_player_profile(int $user_id): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT user_id, character_name, gender, age, country, life_goal FROM player_profile WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user_id]);

    $profile = $stmt->fetch();

    if ($profile === false) {
        return [];
    }

    return $profile;
}

function add_money(int $user_id, int $amount, string $reason = ''): void
{
    if ($amount === 0) {
        return;
    }

    $pdo = get_pdo();
    $pdo->beginTransaction();

    try {
        ensure_game_state($user_id);

        $stmt = $pdo->prepare('UPDATE game_state SET money = money + :amount, updated_at = NOW() WHERE user_id = :user_id');
        $stmt->execute([
            ':amount' => $amount,
            ':user_id' => $user_id,
        ]);

        log_transaction($user_id, 'income', (float)$amount, $reason, $pdo);
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function spend_money(int $user_id, int $amount, string $reason = ''): bool
{
    if ($amount <= 0) {
        return true;
    }

    $pdo = get_pdo();
    $pdo->beginTransaction();

    try {
        ensure_game_state($user_id);

        $select = $pdo->prepare('SELECT money FROM game_state WHERE user_id = :user_id FOR UPDATE');
        $select->execute([':user_id' => $user_id]);
        $money = (int)$select->fetchColumn();

        if ($money < $amount) {
            $pdo->rollBack();
            return false;
        }

        $update = $pdo->prepare('UPDATE game_state SET money = money - :amount, updated_at = NOW() WHERE user_id = :user_id');
        $update->execute([
            ':amount' => $amount,
            ':user_id' => $user_id,
        ]);

        log_transaction($user_id, 'expense', -(float)$amount, $reason, $pdo);
        $pdo->commit();

        return true;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


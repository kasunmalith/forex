<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;
use Throwable;

final class PublicContentModel
{
    public static function faqs(): array
    {
        try {
            $rows = Database::connection()->query("SELECT id, category, question, answer FROM faqs WHERE status='published' ORDER BY category, display_order, id")->fetchAll();
            return ['ok'=>true,'rows'=>$rows];
        } catch (Throwable $exception) {
            error_log('Public FAQ query unavailable: ' . $exception->getMessage());
            return ['ok'=>false,'rows'=>[]];
        }
    }

    public static function saveContact(array $message): void
    {
        $statement = Database::connection()->prepare("INSERT INTO contact_messages (name,email,phone,subject,message,status) VALUES (:name,:email,:phone,:subject,:message,'new')");
        $statement->execute([
            'name'=>$message['name'],'email'=>$message['email'],'phone'=>$message['phone'] !== '' ? $message['phone'] : null,
            'subject'=>$message['subject'],'message'=>$message['message'],
        ]);
    }

    public static function defaultDemoBalance(): ?float
    {
        try {
            $statement=Database::connection()->prepare("SELECT setting_value FROM site_settings WHERE setting_key='default_demo_balance' LIMIT 1");
            $statement->execute();
            $value=$statement->fetchColumn();
            return is_numeric($value) ? (float)$value : null;
        } catch (Throwable $exception) {
            error_log('Demo account setting unavailable: ' . $exception->getMessage());
            return null;
        }
    }
}

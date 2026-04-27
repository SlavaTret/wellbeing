<?php

use yii\db\Migration;

class m260427_140000_seed_payments extends Migration
{
    public function safeUp()
    {
        $now = time();

        $users = (new \yii\db\Query())->select('id')->from('{{%user}}')->all();

        foreach ($users as $u) {
            $uid = $u['id'];

            // Skip if user already has any payments
            $exists = (new \yii\db\Query())->from('{{%payment}}')->where(['user_id' => $uid])->exists();
            if ($exists) continue;

            // Find one completed appointment to link a paid payment to
            $completedAppt = (new \yii\db\Query())
                ->from('{{%appointment}}')
                ->where(['user_id' => $uid, 'status' => 'completed'])
                ->orderBy(['appointment_date' => SORT_DESC])
                ->one();

            // Find one pending/confirmed appointment for the unpaid one
            $upcomingAppt = (new \yii\db\Query())
                ->from('{{%appointment}}')
                ->where(['user_id' => $uid])
                ->andWhere(['IN', 'status', ['confirmed', 'pending']])
                ->orderBy(['appointment_date' => SORT_ASC])
                ->one();

            if ($completedAppt) {
                $this->insert('{{%payment}}', [
                    'user_id'        => $uid,
                    'appointment_id' => $completedAppt['id'],
                    'amount'         => $completedAppt['price'] ?? 1200,
                    'currency'       => 'UAH',
                    'status'         => 'completed',
                    'payment_method' => 'card',
                    'transaction_id' => 'TXN-' . ($now - 86400),
                    'created_at'     => $now - 86400,
                    'updated_at'     => $now - 86400,
                ]);
            }

            if ($upcomingAppt) {
                $this->insert('{{%payment}}', [
                    'user_id'        => $uid,
                    'appointment_id' => $upcomingAppt['id'],
                    'amount'         => $upcomingAppt['price'] ?? 1100,
                    'currency'       => 'UAH',
                    'status'         => 'pending',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }

    public function safeDown()
    {
        // No-op
    }
}

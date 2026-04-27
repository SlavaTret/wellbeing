<?php

use yii\db\Migration;

class m260426_160000_add_specialists extends Migration
{
    public function up()
    {
        // Specialist table
        $this->createTable('{{%specialist}}', [
            'id'               => $this->primaryKey(),
            'name'             => $this->string(100)->notNull(),
            'type'             => $this->string(50)->notNull(),
            'bio'              => $this->text(),
            'experience_years' => $this->integer()->defaultValue(0),
            'rating'           => $this->decimal(3, 1)->defaultValue(5.0),
            'categories'       => $this->text(), // comma-separated
            'avatar_initials'  => $this->string(4),
            'price'            => $this->decimal(10, 2)->defaultValue(0),
            'is_active'        => $this->boolean()->defaultValue(true),
            'created_at'       => $this->integer(),
            'updated_at'       => $this->integer(),
        ]);

        // Specialist schedule: day_of_week (0=Sun..6=Sat) + time slot
        $this->createTable('{{%specialist_schedule}}', [
            'id'            => $this->primaryKey(),
            'specialist_id' => $this->integer()->notNull(),
            'day_of_week'   => $this->smallInteger()->notNull(), // 0..6
            'time_slot'     => $this->string(5)->notNull(),      // HH:MM
        ]);

        $this->addForeignKey('fk_schedule_specialist', '{{%specialist_schedule}}', 'specialist_id', '{{%specialist}}', 'id', 'CASCADE');
        $this->createIndex('idx_schedule_specialist', '{{%specialist_schedule}}', 'specialist_id');

        // Link appointments to specialists (nullable — legacy rows have no specialist record)
        $this->addColumn('{{%appointment}}', 'specialist_id', $this->integer()->null()->after('user_id'));
        $this->addForeignKey('fk_appointment_specialist', '{{%appointment}}', 'specialist_id', '{{%specialist}}', 'id', 'SET NULL');

        $now = time();

        // ---- Seed specialists ----
        $this->insert('{{%specialist}}', [
            'name' => 'Марія Іваненко', 'type' => 'Психолог',
            'bio' => 'Спеціалізується на тривожних розладах, депресії та особистісному зростанні.',
            'experience_years' => 8, 'rating' => 4.9,
            'categories' => 'Тривога та стрес,Депресія',
            'avatar_initials' => 'МІ', 'price' => 1200.00,
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $mariiaId = $this->db->getLastInsertID();

        $this->insert('{{%specialist}}', [
            'name' => 'Дмитро Сорока', 'type' => 'Коуч',
            'bio' => 'Допомагає досягати цілей, будувати кар\'єру та розвивати лідерські якості.',
            'experience_years' => 5, 'rating' => 4.7,
            'categories' => 'Розвиток та цілі,Кар\'єра',
            'avatar_initials' => 'ДС', 'price' => 1000.00,
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $dmytroId = $this->db->getLastInsertID();

        $this->insert('{{%specialist}}', [
            'name' => 'Аліна Бойко', 'type' => 'Психотерапевт',
            'bio' => 'Спеціаліст з травм, ПТСР та відновлення відносин.',
            'experience_years' => 11, 'rating' => 4.8,
            'categories' => 'Травма та ПТСР,Відносини',
            'avatar_initials' => 'АБ', 'price' => 1400.00,
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $alinaId = $this->db->getLastInsertID();

        $this->insert('{{%specialist}}', [
            'name' => 'Ірина Василенко', 'type' => 'Коуч',
            'bio' => 'Фокусується на самооцінці, мотивації та особистому розвитку.',
            'experience_years' => 4, 'rating' => 4.6,
            'categories' => 'Самооцінка,Розвиток та цілі',
            'avatar_initials' => 'ІВ', 'price' => 900.00,
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $irynaId = $this->db->getLastInsertID();

        // ---- Seed schedules (Mon=1, Tue=2, Wed=3, Thu=4, Fri=5) ----
        $mariiaSlots  = ['10:00','11:00','14:00','15:00','16:00'];
        $dmytroSlots  = ['09:00','10:30','12:00','14:00'];
        $alinaSlots   = ['11:00','13:00','15:00','17:00'];
        $irynaSlots   = ['10:00','12:00','14:30','16:00'];

        $scheduleRows = [];
        foreach ([1,2,3,4,5] as $day) {
            foreach ($mariiaSlots as $t)  $scheduleRows[] = [$mariiaId, $day, $t];
            foreach ($dmytroSlots as $t)  $scheduleRows[] = [$dmytroId, $day, $t];
            foreach ($alinaSlots as $t)   $scheduleRows[] = [$alinaId,  $day, $t];
            foreach ($irynaSlots as $t)   $scheduleRows[] = [$irynaId,  $day, $t];
        }
        foreach ($scheduleRows as $row) {
            $this->insert('{{%specialist_schedule}}', [
                'specialist_id' => $row[0],
                'day_of_week'   => $row[1],
                'time_slot'     => $row[2],
            ]);
        }

        // ---- Seed existing appointments for user ID 1 ----
        $appointments = [
            ['user_id'=>1,'specialist_id'=>$mariiaId,'specialist_name'=>'Марія Іваненко','specialist_type'=>'Психолог','appointment_date'=>'2025-04-28','appointment_time'=>'14:00','status'=>'confirmed','payment_status'=>'paid','price'=>1200.00],
            ['user_id'=>1,'specialist_id'=>$dmytroId,'specialist_name'=>'Дмитро Сорока', 'specialist_type'=>'Коуч',       'appointment_date'=>'2025-05-05','appointment_time'=>'10:30','status'=>'pending',  'payment_status'=>'unpaid','price'=>1000.00],
            ['user_id'=>1,'specialist_id'=>$mariiaId,'specialist_name'=>'Марія Іваненко','specialist_type'=>'Психолог','appointment_date'=>'2025-04-15','appointment_time'=>'14:00','status'=>'completed','payment_status'=>'paid','price'=>1200.00],
            ['user_id'=>1,'specialist_id'=>$alinaId, 'specialist_name'=>'Аліна Бойко',  'specialist_type'=>'Психотерапевт','appointment_date'=>'2025-04-03','appointment_time'=>'11:00','status'=>'cancelled','payment_status'=>'unpaid','price'=>1400.00],
            ['user_id'=>1,'specialist_id'=>$mariiaId,'specialist_name'=>'Марія Іваненко','specialist_type'=>'Психолог','appointment_date'=>'2025-03-20','appointment_time'=>'14:00','status'=>'completed','payment_status'=>'paid','price'=>1200.00],
        ];

        foreach ($appointments as $a) {
            $this->insert('{{%appointment}}', array_merge($a, ['created_at' => $now, 'updated_at' => $now]));
        }
    }

    public function down()
    {
        $this->delete('{{%appointment}}', ['user_id' => 1]);
        $this->dropForeignKey('fk_appointment_specialist', '{{%appointment}}');
        $this->dropColumn('{{%appointment}}', 'specialist_id');
        $this->dropForeignKey('fk_schedule_specialist', '{{%specialist_schedule}}');
        $this->dropTable('{{%specialist_schedule}}');
        $this->dropTable('{{%specialist}}');
    }
}

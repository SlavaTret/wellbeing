<?php

use yii\db\Migration;

class m260428_120000_add_performance_indexes extends Migration
{
    private function idx(string $name, string $table, string $cols): void
    {
        $this->execute("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$cols})");
    }

    private function trgm(string $name, string $table, string $col): void
    {
        $this->execute("CREATE INDEX IF NOT EXISTS {$name} ON {$table} USING gin({$col} gin_trgm_ops)");
    }

    public function safeUp()
    {
        $this->execute('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // ── appointment ──────────────────────────────────────────────
        $this->idx('idx_appt_status',          'appointment', 'status');
        $this->idx('idx_appt_payment_status',  'appointment', 'payment_status');
        $this->idx('idx_appt_created_at',      'appointment', 'created_at');
        $this->idx('idx_appt_specialist_name', 'appointment', 'specialist_name');
        $this->idx('idx_appt_user_status',     'appointment', 'user_id, status');

        // ── payment ──────────────────────────────────────────────────
        $this->idx('idx_pay_status',           'payment', 'status');
        $this->idx('idx_pay_created_at',       'payment', 'created_at');

        // ── user ─────────────────────────────────────────────────────
        $this->idx('idx_user_status',          '"user"', 'status');
        $this->idx('idx_user_company_id',      '"user"', 'company_id');

        // ── specialist ───────────────────────────────────────────────
        $this->idx('idx_spec_is_active',       'specialist', 'is_active');
        $this->idx('idx_spec_name',            'specialist', 'name');

        // ── specialist_review ────────────────────────────────────────
        $this->idx('idx_specrev_specialist_id', 'specialist_review', 'specialist_id');

        // ── Trigram indexes for ILIKE ────────────────────────────────
        $this->trgm('idx_trgm_user_firstname',  '"user"',     'first_name');
        $this->trgm('idx_trgm_user_lastname',   '"user"',     'last_name');
        $this->trgm('idx_trgm_user_email',      '"user"',     'email');
        $this->trgm('idx_trgm_spec_name',       'specialist', 'name');
        $this->trgm('idx_trgm_spec_categories', 'specialist', 'categories');
        $this->trgm('idx_trgm_appt_specname',   'appointment','specialist_name');
        $this->trgm('idx_trgm_cat_name',        '"category"', 'name');
    }

    public function safeDown()
    {
        foreach ([
            'idx_trgm_cat_name','idx_trgm_appt_specname','idx_trgm_spec_categories',
            'idx_trgm_spec_name','idx_trgm_user_email','idx_trgm_user_lastname','idx_trgm_user_firstname',
            'idx_specrev_specialist_id','idx_spec_name','idx_spec_is_active',
            'idx_user_company_id','idx_user_status','idx_pay_created_at','idx_pay_status',
            'idx_appt_user_status','idx_appt_specialist_name','idx_appt_created_at',
            'idx_appt_payment_status','idx_appt_status',
        ] as $idx) {
            $this->execute("DROP INDEX IF EXISTS {$idx}");
        }
    }
}

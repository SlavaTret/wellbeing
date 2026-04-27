<?php

use yii\db\Migration;

class m260427_120000_company_branding_seed extends Migration
{
    public function safeUp()
    {
        $now = time();

        // code => [name, logo_url, primary, secondary, accent]
        $companies = [
            'epam'      => ['EPAM',      'https://upload.wikimedia.org/wikipedia/commons/thumb/9/95/EPAM_Systems_logo.svg/512px-EPAM_Systems_logo.svg.png',
                            '#1E88E5', '#1565C0', '#E3F2FD'],
            'softserve' => ['SoftServe', 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/SoftServe_logo.svg/512px-SoftServe_logo.svg.png',
                            '#00A551', '#00873E', '#E8F5E9'],
            'genesis'   => ['Genesis',   null,
                            '#7C4DFF', '#651FFF', '#EDE7F6'],
            'ciklum'    => ['Ciklum',    null,
                            '#FF6B35', '#E8590C', '#FFF3E0'],
        ];

        foreach ($companies as $code => [$name, $logo, $primary, $secondary, $accent]) {
            $exists = (new \yii\db\Query())->from('{{%company}}')->where(['code' => $code])->exists();
            $row = [
                'code'            => $code,
                'name'            => $name,
                'logo_url'        => $logo,
                'primary_color'   => $primary,
                'secondary_color' => $secondary,
                'accent_color'    => $accent,
                'is_active'       => true,
                'updated_at'      => $now,
            ];
            if ($exists) {
                $this->update('{{%company}}', $row, ['code' => $code]);
            } else {
                $row['created_at'] = $now;
                $this->insert('{{%company}}', $row);
            }
        }
    }

    public function safeDown()
    {
        // No-op — keep the seeded data.
    }
}

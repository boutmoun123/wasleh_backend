<?php

return [
    // 'local' is private by default; set to 's3' after configuring the S3 disk.
    'private_disk' => env('WASLA_PRIVATE_DISK', 'local'),
    'otp_minutes' => 15,
    'max_cv_kb' => 5120,
    'skill_dictionary' => [
        'PHP' => ['php'], 'Laravel' => ['laravel'], 'MySQL' => ['mysql'],
        'JavaScript' => ['javascript', 'js'], 'React' => ['react', 'react.js'],
        'Vue' => ['vue', 'vue.js'], 'Node.js' => ['node.js', 'nodejs'],
        'Python' => ['python'], 'Django' => ['django'], 'Git' => ['git'],
        'Docker' => ['docker'], 'Linux' => ['linux'], 'REST API' => ['rest api', 'restful'],
        'Excel' => ['excel', 'إكسل'], 'Accounting' => ['accounting', 'محاسبة'],
        'Sales' => ['sales', 'مبيعات'], 'Marketing' => ['marketing', 'تسويق'],
        'Customer Service' => ['customer service', 'خدمة العملاء'],
        'Project Management' => ['project management', 'إدارة المشاريع'],
        'Time Management' => ['time management', 'إدارة الوقت'],
        'English' => ['english', 'الإنجليزية', 'اللغة الإنجليزية'],
        'Arabic' => ['arabic', 'العربية', 'اللغة العربية'],
    ],
];

<?php
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'my_integrations' => [
        'page_title'    => 'My API Keys',
        'page_subtitle' => 'Personal iLovePDF and iLoveIMG keys. If configured, they are used in Optimize PDF and Images instead of the system keys.',
        'personal_note' => 'These keys are yours only. The admin cannot see or use them. If you do not configure yours, the API method will not be available.',
        'btn_clear'     => 'Delete my keys',
        'err_empty'     => 'Enter at least one key to save.',
    ],
];

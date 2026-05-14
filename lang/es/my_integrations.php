<?php
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'my_integrations' => [
        'page_title'    => 'Mis claves API',
        'page_subtitle' => 'Claves personales de iLovePDF e iLoveIMG. Si las configuras, se usan en Optimizar PDF e Imágenes en lugar de las del sistema.',
        'personal_note' => 'Estas claves son tuyas. El administrador no puede verlas ni usarlas. Si no configuras las tuyas, el método API no estará disponible.',
        'btn_clear'     => 'Borrar mis claves',
        'err_empty'     => 'Ingresa al menos una clave para guardar.',
    ],
];

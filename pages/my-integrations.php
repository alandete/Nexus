<?php
defined('APP_ACCESS') or die('Acceso directo no permitido');
// Redirigir a la página unificada de integraciones
header('Location: ' . url('integrations'));
exit;

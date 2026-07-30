<?php
require_once __DIR__ . '/../includes/security.php';

initSecurity();
sendSecurityHeaders();

echo "开发中，敬请期待";

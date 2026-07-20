<?php

declare(strict_types=1);

return array_merge(
    require dirname(__DIR__) . '/routes/core.php',
    require dirname(__DIR__) . '/routes/users.php',
    require dirname(__DIR__) . '/routes/content.php',
    require dirname(__DIR__) . '/routes/navigation.php',
);

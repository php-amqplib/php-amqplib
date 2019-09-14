<?php

echo '# General info', PHP_EOL;
phpinfo(INFO_GENERAL);
echo PHP_EOL;

echo '# Extensions', PHP_EOL;
print_r(array_intersect(get_loaded_extensions(), ['sockets']));
echo PHP_EOL;

echo '# Constants', PHP_EOL;
$constants = get_defined_constants(true);
$socketConstant = isset($constants['sockets']) ? $constants['sockets'] : array();
foreach ($socketConstant as $name => $value) {
    echo sprintf('%-30s', $name), $value, PHP_EOL;
}


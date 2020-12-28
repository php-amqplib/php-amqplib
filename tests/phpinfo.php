<?php

echo '# General info', PHP_EOL;
phpinfo(INFO_GENERAL);
echo PHP_EOL;

echo '# Extensions', PHP_EOL;
print_r(array_intersect(get_loaded_extensions(), ['sockets', 'bcmath', 'mbstring', 'pcntl']));
echo PHP_EOL;

echo 'PHP_INT_SIZE=', PHP_INT_SIZE, PHP_EOL;
echo 'PHP_INT_MAX=', PHP_INT_MAX, PHP_EOL;

echo '# Constants', PHP_EOL;
$constants = get_defined_constants(true);
$socketConstant = isset($constants['sockets']) ? $constants['sockets'] : array();
ksort($socketConstant);
foreach ($socketConstant as $name => $value) {
    echo sprintf('%-30s', $name), $value, PHP_EOL;
}

<?php
header('Content-Type: text/plain');

echo('$_ENV[] = '); print_r($_ENV);
echo('$_SERVER[] = '); print_r($_SERVER);
echo('$_REQUEST[] = '); print_r($_REQUEST);

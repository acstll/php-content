<?php 

require 'vendor/autoload.php';

use Zend\Cache\StorageFactory;

$cache = StorageFactory::factory(array(
    'adapter' => array(
        'name'    => 'filesystem',
        'options' => array(
        	'cache_dir' => realpath('test/cache')
        ),
    ),
    'plugins' => array(
        'exception_handler' => array('throw_exceptions' => false),
        'serializer' => array()
    ),
));

$content = new Content(array(
	'directory' => realpath('test/content'),
	'cache' => $cache
));

$path = $_GET['path'];

$home = $content->get($path);
var_dump($home);
print('<br><br>');

foreach ($content as $key => $value) {
	print_r($value);
	echo '<br><br>';
}

echo 'COUNT: ' . $content->count();

?>
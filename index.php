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

$bullshit = new Content(array(
	'directory' => realpath('test/content'),
	'cache' => $cache
));

$path = $_GET['path'];

$home = $bullshit->get($path);
var_dump($home);
print('<br><br>');
$files = $bullshit->getAll();

foreach ($files as $key => $value) {
	echo $key . '<br>';
	echo $value->getRelativePathname() . '<br>';
	var_dump($value);
	echo '<br><br>';
}

?>
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

class Filter extends \FilterIterator
{
    public function accept()
    {
        $value = $this->current();
        return !!($value['meta']['title'] == 'The real bullshit');
        // return in_array('Blai', $value['meta']['names']);
    }
}

$content = new Content(array(
	'directory' => realpath('test/content'),
	'cache' => $cache,
    'filter' => Filter
));

$path = $_GET['path'];

$home = $content->get($path);
var_dump($home);
print('<br><br>');

foreach ($content as $key => $value) {
	print_r($value);
	echo '<br><br>';
}

var_dump($content->prev());

?>
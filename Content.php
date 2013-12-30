<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Michelf\MarkdownExtra;

/**
 * Content
 *
 * @author Arturo Castillo Delgado
 * @link http://github.com/acstll/bullshit-cms
 * @license http://opensource.org/licenses/MIT
 * @version 0.1.0
 */

class Content implements \IteratorAggregate, \Countable
{
	private $content; // collection of pages (array)
	private $cursor; // current iteration key
	private $keypath; // "/foo" ($key)
	private $hash; // hashed version of the keypath
	private $filepath; // absolute path to file
	private $clear = array(); // array of cache keys to flush
	private $config;
	private $defaults = array(
		'directory' => false,
		'cache' => false,
		'delimiter_regexp' => '#---(.*?)---#sm',
		'extension' => '.md'
	);

	function __construct($config)
	{
		$this->config = array_merge($this->defaults, $config);
		$this->cursor = -1;
		$directory = $this->config['directory'];
		if (!is_dir($directory) || !is_writable($directory)) {
			throw new \Exception('Content directory does not exist or is not writable.');
		}
	}

	function __destruct()
	{
		if (count($this->clear) >= 1) {
			$this->config['cache']->removeItems($this->clear);
		}
	}

	public function getIterator()
	{
		if (!($this->content instanceof \ArrayIterator)) $this->refresh();
		return $this->content;
	}

	public function count()
	{
		return iterator_count($this->getIterator());
	}

	/**
	 * Returns array with content corresponding to key.
     *
     * @param string $keypath Path built from URI
     *
     * @return mixed Array when found, false otherwise
	 */
	public function get($keypath)
	{
		if (!isset($keypath)) {
			return false; // temp
			// refresh and return $content!
		}
		if (!$this->exists($keypath)) return false;
		
		return $this->read();
	}

	/**
	 * Check if $key corresponds to file in the content folder.
     *
     * Checks for "$key.md" or "$key/index.md".
     * Sets $this->filepath and $this->keypath.
     *
     * @param string $keypath Path built from URI
     *
     * @return boolean
	 */
	public function exists($keypath) {
		$directory = $this->config['directory'] . '/';
		$ext = $this->config['extension'];
		$path = trim($keypath, '/');
		$hash = md5($keypath);
		$cache = $this->config['cache'];

		if ($path) $filepath = $directory . $path;
		else $filepath = $directory . 'index';

		if (is_dir($filepath)) $filepath = $directory . $path . '/index' . $ext;
		else $filepath .= $ext;

		if (file_exists($filepath)) {
			$this->filepath = $filepath;
			$this->keypath = $keypath;
			$this->hash = $hash;

			return true;
		}

		if ($cache && $cache->hasItem($hash)) {
			$this->clear[] = $cache->getItem($hash);
		}

		return false;
	}

	protected function refresh()
	{
		$content = array();
		$finder = new Finder();
		$ext = $this->config['extension'];
		$directory = $this->config['directory'];

		$finder
			->files()
			->in($directory)
			->name('*' . $ext)
			->notName('404.*');

		foreach ($finder as $filepath => $file) {
			$path = str_replace([$directory, 'index' . $ext, $ext], '', $filepath);
			$content[] = array(
				'path' => ($path === '/') ? $path : rtrim($path, '/'),
				'filepath' => $filepath,
				'meta' => $this->process($file->getContents(), true)['meta'],
				'depth' => substr_count($path, '/') - 1
			);
		}

		$this->content = new \ArrayIterator($content);
	}

	/**
	 * Fetches content either from filesystem or cache
	 * and sets $this->output.
	 *
     * Process it if needed.
	 */
	protected function read()
	{
		$cache = $this->config['cache'];

		if (!$cache) {
			$file_content = file_get_contents($this->filepath);
			return $this->process($file_content);
		}

		if ($cache->hasItem($this->hash)) {
			$f_mtime = filemtime($this->filepath);
			$c_mtime = $cache->getMetadata($this->hash)['mtime'];

			if ($f_mtime < $c_mtime) {
				return $cache->getItem($this->hash);
			}
		}

		$file_content = file_get_contents($this->filepath);
		$output = $this->process($file_content);
		$cache->setItem($this->hash, $output);

		return $output;
	}

	/**
	 * Extract Yaml "front-matter" and parse Markdown.
	 *
	 * @param string $file_content
	 *
	 * @return array Page processed content
	 */
	protected function process($file_content, $only_meta = false)
	{
		$result = array();
		$regexp = $this->config['delimiter_regexp'];

		$raw = preg_replace($regexp, '', $file_content);
		if ($only_meta == false) {
			$result['content'] = MarkdownExtra::defaultTransform($raw);
		}
		
		$yaml = array();
		if (preg_match($regexp, $file_content, $match)) {
			$yaml = Yaml::parse($match[1]);
		}

		$result['meta'] = $yaml;
		$result['path'] = $this->keypath;
		$result['raw'] = $raw;

		return $result;
	}
}

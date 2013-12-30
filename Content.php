<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Michelf\MarkdownExtra;

/**
 * Content
 *
 * @author Arturo Castillo Delgado
 * @link http://github.com/acstll/php-content
 * @license http://opensource.org/licenses/MIT
 * @version 0.1.0
 */

class Content implements \IteratorAggregate, \Countable
{
	private $content; // collection of pages (Iterator instance)
	private $cursor;
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

	/**
	 * @return array Next "page" relative to $this->cursor
	 */
	public function next()
	{
		if ($this->cursor < 0) {
			throw new \LogicException('You must call one of get() or exists() methods first.');
		}

		$next_key = $this->cursor + 1;
		$total = $this->count();

		if ($next_key >= $total) return false;

		$limit = new \LimitIterator($this->content, $next_key, 1);
		$limit->rewind();
		return $limit->current();
	}

	/**
	 * @return array Previous "page" relative to $this->cursor
	 */
	public function prev()
	{
		if ($this->cursor < 0) {
			throw new \LogicException('You must call one of get() or exists() methods first.');
		}

		$prev_key = $this->cursor - 1;
		$total = $this->count();

		if ($prev_key < 0 || $prev_key >= $total) return false;

		$limit = new \LimitIterator($this->content, $prev_key, 1);
		$limit->rewind();
		return $limit->current();
	}

	/**
	 * @return number $this->cursor
	 */
	public function key()
	{
		return $this->cursor;
	}

	/**
	 * Traverses content directory and sets $this->content.
	 * Also sets $this->cursor.
	 */
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
				'meta' => $this->process($file->getContents(), true),
				'depth' => substr_count($path, '/') - 1
			);
		}

		$this->content = new \ArrayIterator($content);

		foreach ($this->content as $key => $value) {
			if ($value['path'] == $this->keypath) $this->cursor = $key;
		}
	}

	/**
	 * Fetches content either from filesystem or cache.
     * Process it if needed.
     *
     * @return array Page content
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
		$yaml = array();
		$regexp = $this->config['delimiter_regexp'];

		if (preg_match($regexp, $file_content, $match)) $yaml = Yaml::parse($match[1]);
		if ($only_meta) return $yaml;
		
		$raw = preg_replace($regexp, '', $file_content);
		if ($only_meta == false) $result['content'] = MarkdownExtra::defaultTransform($raw);
		
		$result['meta'] = $yaml;
		$result['path'] = $this->keypath;
		$result['raw'] = $raw;

		return $result;
	}
}

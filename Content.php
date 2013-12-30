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

class Content
{
	public $output;

	private $keypath;
	private $hash;
	private $filepath;
	private $clear = array();	
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
		$directory = $this->config['directory'];
		if (!is_dir($directory) || !is_writable($directory)) {
			throw new \Exception('Content directory does not exist or is not writable.');
		}
	}

	function __destruct()
	{
		if (count($this->clear) >= 1) {
			$this->config['cache']->removeItems($this->clear);
			$this->clear = array(); // needed?
		}
	}

	/**
	 * Returns array with content corresponding to key.
     *
     * @param string $key Path built from URI
     *
     * @return mixed Array when found, false otherwise
	 */
	public function get($key)
	{
		if (!isset($key)) return false;
		if (!$this->exists($key)) return false;
		$this->read();

		return $this->output;
	}

	public function getAll()
	{
		$finder = new Finder();
		$finder
			->files()
			->in($this->config['directory'])
			->name('*' . $this->config['extension'])
			->notName('404.*');

		return $finder;
	}

	public function current()
	{
		if (!$this->keypath) return false;
		return $this->get($this->keypath);
	}

	/**
	 * Check if $key corresponds to file in the content folder.
     *
     * Checks for "$key.md" or "$key/index.md".
     * Sets $this->filepath and $this->keypath.
     *
     * @param string $key Path built from URI
     *
     * @return boolean
	 */
	private function exists($key) {
		$directory = $this->config['directory'] . '/';
		$ext = $this->config['extension'];
		$path = trim($key, '/');
		$hash = md5($key);
		$cache = $this->config['cache'];

		if ($path) $filepath = $directory . $path;
		else $filepath = $directory . 'index';

		if (is_dir($filepath)) $filepath = $directory . $path . '/index' . $ext;
		else $filepath .= $ext;

		if (file_exists($filepath)) {
			$this->filepath = $filepath;
			$this->keypath = $key;
			$this->hash = $hash;

			return true;
		}

		if ($cache && $cache->hasItem($hash)) {
			$this->clear[] = $cache->getItem($hash);
		}

		return false;
	}

	/**
	 * Fetches content either from filesystem or cache
	 * and sets $this->output.
	 *
     * Process it if needed.
	 */
	private function read()
	{
		$cache = $this->config['cache'];

		if (!$cache) {
			$file_content = file_get_contents($this->filepath);
			$this->output = $this->process($file_content);
			return;
		}

		if ($cache->hasItem($this->hash)) {
			$f_mtime = filemtime($this->filepath);
			$c_mtime = $cache->getMetadata($this->hash)['mtime'];

			if ($f_mtime < $c_mtime) {
				$this->output = $cache->getItem($this->hash);
				return;
			}
		}

		$file_content = file_get_contents($this->filepath);
		$output = $this->process($file_content);
		$cache->setItem($this->hash, $output);
		$this->output = $this->process($file_content);
	}

	/**
	 * Extract Yaml "front-matter" and parse Markdown.
	 *
	 * @param string $file_content
	 *
	 * @return array Page processed content
	 */
	private function process($file_content, $only_meta = false)
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

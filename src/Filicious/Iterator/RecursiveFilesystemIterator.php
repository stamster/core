<?php

/**
 * High level object oriented filesystem abstraction.
 *
 * @package filicious-core
 * @author  Tristan Lins <tristan.lins@bit3.de>
 * @author  Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author  Oliver Hoff <oliver@hofff.com>
 * @link    http://filicious.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace Filicious\Iterator;

use RecursiveIterator;

/**
 * Recursive filesystem iterator
 *
 * @package filicious-core
 * @author  Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author  Tristan Lins <tristan.lins@bit3.de>
 * @author  Oliver Hoff <oliver@hofff.com>
 */
class RecursiveFilesystemIterator
	extends FilesystemIterator
	implements RecursiveIterator
{
	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Returns if an iterator can be created for the current entry.
	 *
	 * @link http://php.net/manual/en/recursiveiterator.haschildren.php
	 * @return bool true if the current entry can be iterated over, otherwise returns false.
	 */
	public function hasChildren()
	{
		return $this->valid() &&
		$this->currentFile()->isDirectory() &&
		$this->applyGlobSearchPattern();
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Returns an iterator for the current entry.
	 *
	 * @link http://php.net/manual/en/recursiveiterator.getchildren.php
	 * @return RecursiveIterator An iterator for the current entry.
	 */
	public function getChildren()
	{
		$iterator = new RecursiveFilesystemIterator(
			$this->files[$this->keys[$this->index]],
			$this->filters,
			$this->flags
		);
		$iterator->prepareFilters($this);
		return $iterator;
	}
}

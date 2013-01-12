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

namespace Filicious\Local;

use Filicious\File;
use Filicious\Filesystem;
use Filicious\FilesystemConfig;
use Filicious\Internals\Adapter;
use Filicious\Internals\AbstractAdapter;
use Filicious\Internals\Pathname;
use Filicious\Exception\FilesystemException;
use Filicious\Exception\FilesystemOperationException;
use Filicious\Exception\DirectoryOverwriteDirectoryException;
use Filicious\Exception\DirectoryOverwriteFileException;
use Filicious\Exception\FileOverwriteDirectoryException;
use Filicious\Exception\FileOverwriteFileException;
Use Filicious\Stream\BuildInStream;

/**
 * Local filesystem adapter.
 *
 * @package filicious-core
 * @author  Tristan Lins <tristan.lins@bit3.de>
 */
class RootAdapter
	extends DelegatorAdapter
{
	/**
	 * @param string|FilesystemConfig $basepath
	 */
	public function __construct(Filesystem $fs)
	{
		$this->fs = $fs;
		$this->root = $this;
	}

	/**
	 * @param \Filicious\Internals\Adapter $delegate
	 */
	public function setDelegate($delegate)
	{
		$this->delegate = $delegate;
		return $this;
	}

	/**
	 * @return \Filicious\Internals\Adapter
	 */
	public function getDelegate()
	{
		return $this->delegate;
	}
}
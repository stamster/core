<?php

/**
 * High level object oriented filesystem abstraction.
 *
 * @package php-filesystem
 * @author  Tristan Lins <tristan.lins@bit3.de>
 * @link    http://bit3.de
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace Bit3\Filesystem\FTP;

use Bit3\Filesystem\Filesystem;
use Bit3\Filesystem\File;
use Bit3\Filesystem\PublicURLProvider;
use Bit3\Filesystem\Util;

/**
 * File from a mounted filesystem structure.
 *
 * @package php-filesystem
 * @author  Tristan Lins <tristan.lins@bit3.de>
 */
class FTPFilesystem
    implements Filesystem
{
    /**
     * @var FTPConfig
     */
    protected $config;

    /**
     * @var PublicURLProvider
     */
    protected $publicURLProvider;

    /**
     * @var resource
     */
    protected $connection;

    /**
     * @var string
     */
    protected $cacheKey;

    /**
     * @param FTPConfig $config
     */
    public function __construct(FTPConfig $config, PublicURLProvider $publicURLProvider = null)
    {
        $this->config = clone $config;
        $this->publicURLProvider = $publicURLProvider;

        $this->cacheKey = 'ftpfs:' . ($this->config->getSSL() ? 'ssl:' : '') . $this->config->getUsername() . '@' . $this->config->getHost() . ':' . $this->config->getPort() . ($this->config->getPath() ?: '/');

        if (!$this->config->getLazyConnect()) {
            $this->connect();
        }
    }

    public function __destruct()
    {
        ftp_close($this->connection);
    }

    /**
     * Get the root (/) file node.
     *
     * @return File
     */
    public function getRoot()
    {
        return new FTPFile('/', $this);
    }

    /**
     * Get a file object for the specific file.
     *
     * @param string $path
     *
     * @return File
     */
    public function getFile($path)
    {
        return new FTPFile($path, $this);
    }

    /**
     * Returns available space on filesystem or disk partition.
     *
     * @param File $path
     *
     * @return int
     */
    public function getFreeSpace(File $path = null)
    {
        return -1;
    }

    /**
     * Returns the total size of a filesystem or disk partition.
     *
     * @param File $path
     *
     * @return int
     */
    public function getTotalSpace(File $path = null)
    {
        return -1;
    }

    /**
     *
     */
    public function connect()
    {
        if ($this->connection !== null) {
            return;
        }

        if ($this->config->getSSL()) {
            $this->connection = ftp_ssl_connect(
                $this->config->getHost(),
                $this->config->getPort(),
                $this->config->getTimeout());
        }
        else {
            $this->connection = ftp_connect(
                $this->config->getHost(),
                $this->config->getPort(),
                $this->config->getTimeout());
        }

        if ($this->connection === false) {
            throw new FTPFilesystemConnectionException('Could not connect to ' . $this->config->getHost());
        }

        if ($this->config->getUsername()) {
            if (!ftp_login($this->connection,
                      $this->config->getUsername(),
                      $this->config->getPassword())) {
                throw new FTPFilesystemAuthenticationException('Could not login to ' . $this->config->getHost() . ' with username ' . $this->config->getUsername() . ':' . ($this->config->getPassword() ? '*****' : 'NOPASS'));
            }
        }

        ftp_pasv($this->connection, $this->config->getPassiveMode());

        if ($this->config->getPath()) {
            if (!ftp_chdir($this->connection, $this->config->getPath())) {
                throw new FTPFilesystemException('Could not change into directory ' . $this->config->getPath() . ' on ' . $this->config->getHost());
            }
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return \Bit3\Filesystem\FTP\FTPConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function getBasePath()
    {
        return $this->config->getPath();
    }

    public function ftpStat(FTPFile $file)
    {
        $this->connect();

        $real = $this->getBasePath() . $file->getPathname();
        $cacheKey = $this->cacheKey . ':stat:' . $real;

        $cached = $this->config->getCache()->fetch($cacheKey);

        if ($cached === null) {
            $this->ftpList($file);

            $cached = $this->config->getCache()->fetch($cacheKey);
        }

        return $cached;
    }

    public function ftpList(FTPFile $file)
    {
        $this->connect();

        $real = $this->getBasePath() . $file->getPathname();
        $cacheKey = $this->cacheKey . ':list:' . $real;

        $cached = $this->config->getCache()->fetch($cacheKey);

        if ($cached === null) {
            $cached = array();
            $list = ftp_rawlist($this->connection, '-la ' . $real);

            $isSingleFile = true;

            foreach ($list as $item) {
                if (preg_match('#^([\-ldrwxsSt]{10})\s+(\d+)\s+([\w\d]+)\s+([\w\d]+)\s+(\d+)\s+(\w{3}\s+\d{1,2}\s+(?:\d{2}:\d{2}|\d{4}))\s+(.*?)(\s+->\s+(.*))?$#s', $item, $match)) {
                    $stat = (object) array(
                        'perms'       => $match[1],
                        'mode'        => Util::string2bitMode($match[1]),
                        'type'        => (int) $match[2],
                        'isDirectory' => $match[1][0] == 'd',
                        'isFile'      => $match[1][0] != 'd',
                        'isLink'      => $match[1][0] == 'l',
                        'user'        => (int) $match[3],
                        'group'       => (int) $match[4],
                        'size'        => (int) $match[5],
                        'modified'    => strtotime($match[6]),
                        'name'        => $match[7],
                        'target'      => isset($match[9]) ? $match[9] : null
                    );

                    if ($stat->name == '.') {
                        $isSingleFile = false;
                        $directoryCacheKey = $this->cacheKey . ':stat:' . $real;
                        $this->config->getCache()->store($directoryCacheKey, $stat);
                    }
                    else if ($stat->name == '..') {
                        if (dirname($real) != $real) {
                            $directoryCacheKey = $this->cacheKey . ':stat:' . dirname($real);
                            $this->config->getCache()->store($directoryCacheKey, $stat);
                        }
                    }
                    else {
                        $fileCacheKey = $this->cacheKey . ':stat:' . $real . ($isSingleFile ? '' : '/' . $match[7]);
                        $this->config->getCache()->store($fileCacheKey, $stat);
                        $cached[] = $stat;
                    }
                }
                else {
                    throw new FTPFilesystemException('Implementation error: Could not parse list item ' . $item);
                }
            }

            if ($isSingleFile) {
                $cached = false;
            }

            $this->config->getCache()->store($cacheKey, $cached);
        }

        return $cached;
    }

    public function ftpChmod(FTPFile $file, $mode)
    {
        $this->connect();

        $stat = $this->ftpStat($file);

        if ($stat) {
            $real = $this->getBasePath() . $file->getPathname();
            return ftp_chmod($this->connection, $mode, $real);
        }

        return false;
    }

    public function ftpDelete(FTPFile $file)
    {
        $this->connect();

        $stat = $this->ftpStat($file);

        if ($stat) {
            $real = $this->getBasePath() . $file->getPathname();

            if ($stat->isDirectory) {
                if (ftp_rmdir($this->connection, $real)) {
                    $this->config->getCache()->store($this->cacheKey . ':stat:' . $real, null);
                    $this->config->getCache()->store($this->cacheKey . ':list:' . $real, null);
                    $this->config->getCache()->store($this->cacheKey . ':list:' . dirname($real), null);
                    return true;
                }
            }
            else {
                if (ftp_delete($this->connection, $real)) {
                    $this->config->getCache()->store($this->cacheKey . ':stat:' . $real, null);
                    $this->config->getCache()->store($this->cacheKey . ':list:' . dirname($real), null);
                    return true;
                }
            }
        }

        return false;
    }

    public function ftpStreamGet(FTPFile $source, $targetStream)
    {
        $this->connect();

        $stat = $this->ftpStat($source);

        if ($stat and !$stat->isDirectory) {
            $real = $this->getBasePath() . $source->getPathname();

            return ftp_fget($this->connection, $targetStream, $real, FTP_BINARY);
        }

        return false;
    }

    public function ftpStreamPut(FTPFile $target, $sourceStream)
    {
        $this->connect();

        $stat = $this->ftpStat($target);

        if (!$stat or !$stat->isDirectory) {
            $real = $this->getBasePath() . $target->getPathname();

            return ftp_fput($this->connection, $real, $sourceStream, FTP_BINARY);
        }

        return false;
    }

    public function ftpGet(FTPFile $source, File $target)
    {
        $this->connect();

        $stat = $this->ftpStat($source);

        if ($stat and !$stat->isDirectory) {
            $realSource = $this->getBasePath() . $source->getPathname();
            $realTarget = $target->getRealURL();

            return ftp_get($this->connection, $realTarget, $realSource, FTP_BINARY);
        }

        return false;
    }

    public function ftpPut(FTPFile $target, File $source)
    {
        $this->connect();

        $stat = $this->ftpStat($target);

        if (!$stat or !$stat->isDirectory) {
            $realSource = $source->getRealURL();
            $realTarget = $this->getBasePath() . $target->getPathname();

            return ftp_put($this->connection, $realTarget, $realSource, FTP_BINARY);
        }

        return false;
    }

    public function ftpMkdir(FTPFile $file)
    {
        $this->connect();

        $stat = $this->ftpStat($file);

        if (!$stat) {
            $real = $this->getBasePath() . $file->getPathname();

            return ftp_mkdir($this->connection, $real);
        }

        return false;
    }

    public function ftpRename(FTPFile $source, FTPFile $target)
    {
        $this->connect();

        $sourceStat = $this->ftpStat($source);
        $targetStat = $this->ftpStat($target);

        if ($sourceStat and (!$targetStat or (!$sourceStat['isDirectory'] and !$targetStat['isDirectory']))) {
            $realSource = $this->getBasePath() . $source->getPathname();
            $realTarget = $this->getBasePath() . $target->getPathname();

            return ftp_rename($this->connection, $realSource, $realTarget);
        }

        return false;
    }

    public function ftpRmdir(FTPFile $file)
    {
        $this->connect();

        $stat = $this->ftpStat($file);

        if ($stat && $stat->isDirectory) {
            $real = $this->getBasePath() . $file->getPathname();

            return ftp_rmdir($this->connection, $real);
        }

        return false;
    }

    /**
     * @return \Bit3\Filesystem\PublicURLProvider
     */
    public function getPublicURLProvider()
    {
        return $this->publicURLProvider;
    }
}

<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   SessionHandler
 */

/**
 * SessionHandler implementation for storage in text files.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   SessionHandler
 */
class Horde_SessionHandler_Storage_File extends Horde_SessionHandler_Storage
{
    /* File prefix. */
    const PREFIX = 'horde_sh_';

    /**
     * File stream.
     *
     * @var resource
     */
    protected $_fp;

    /**
     * Constructor.
     *
     * @param array $params  Parameters:
     * <pre>
     * path - (string) [REQUIRED] The path to save the files.
     * </pre>
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $params = array())
    {
        if (!isset($params['path'])) {
            throw new InvalidArgumentException('Missing path parameter.');
        }
        $params['path'] = rtrim($params['path'], '/');

        parent::__construct($params);
    }

    /**
     */
    public function open($save_path = null, $session_name = null)
    {
        return true;
    }

    /**
     * Open the file stream connection.
     *
     * @param string $id  The session ID.
     */
    protected function _open(string $id)
    {
        if (!empty($this->_fp)) {
            return;
        } elseif (!$this->isValidSessionID($id)) {
            return;
        }

        $filename = $this->_params['path'] . '/' . self::PREFIX . $id;

        $this->_fp = fopen($filename, 'c+');
        if ($this->_fp) {
            flock($this->_fp, LOCK_EX);
        }
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        if (!empty($this->_fp)) {
            flock($this->_fp, LOCK_UN);
            fclose($this->_fp);
            unset($this->_fp);
        }

        return true;
    }

    /**
     */
    public function read($id)
    {
        $this->_open($id);

        if (!$this->_fp) {
            return false;
        }

        $data = '';
        rewind($this->_fp);

        while (!feof($this->_fp)) {
            $data .= fread($this->_fp, 16384);
        }

        return $data;
    }

    /**
     */
    public function write($id, $session_data): bool
    {
        $this->_open($id);

        if (!$this->_fp) {
            return false;
        }

        fseek($this->_fp, 0);
        ftruncate($this->_fp, 0);
        fwrite($this->_fp, $session_data);

        return true;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function destroy($id): bool
    {
        $this->close();

        if (!$this->isValidSessionID($id)) {
            return false;
        }

        $filename = $this->_params['path'] . '/' . self::PREFIX . $id;

        return @unlink($filename);
    }

    /**
     */
    public function gc($maxlifetime = 300)
    {
        try {
            $di = new DirectoryIterator($this->_params['path']);
        } catch (UnexpectedValueException $e) {
            return false;
        }

        $expire_time = time() - $maxlifetime;

        foreach ($di as $val) {
            if ($val->isFile() &&
                (strpos($val->getFilename(), self::PREFIX) === 0) &&
                ($val->getMTime() < $expire_time)) {
                @unlink($val->getPathname());
            }
        }

        return true;
    }

    /**
     */
    public function getSessionIDs()
    {
        $ids = array();

        try {
            $di = new DirectoryIterator($this->_params['path']);
            foreach ($di as $val) {
                if ($val->isFile() &&
                    (strpos($val->getFilename(), self::PREFIX) === 0)) {
                    $ids[] = substr($val->getFilename(), strlen(self::PREFIX));
                }
            }
        } catch (UnexpectedValueException $e) {}

        return $ids;
    }

}

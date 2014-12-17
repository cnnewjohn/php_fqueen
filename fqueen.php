<?php
/**
 * PHP FILE FIFO QUEEN
 *
 * PHP version 5
 *
 * @author chuyinfeng.com
 * @link https://github.com/cnnewjohn/php_fqueen
 */
class fqueen
{
    /**
     * file queen instances
     */
    private static $_instance = array();

    /**
     * lock by sem key, otherwise by flock
     */
    private $_sem_lock_key;
    /**
     * queen file
     */
    private $_file;

    /**
     * queen index file
     */
    private $_idx;

    /**
     * queen file handle
     */
    private $_file_h;

    /**
     * queen index file handle
     */
    private $_idx_h;

    /**
     * create new queen instance by filename
     *
     * @param string $file, queen file
     */
    public static function instance($file)
    {
        if (! isset(self::$_instance[$file])) {
            self::$_instance[$file] = new self($file);
        }
        return self::$_instance[$file];
    }
    
    /**
     * init queen file and index file
     *
     * @param string $file, queen filename
     */
    public function __construct($file)
    {
        
        $this->_file = $file;
        $this->_idx = dirname($file) . DIRECTORY_SEPARATOR 
            . basename($file). '.idx';

        if (! file_exists($this->_file)) {
            file_put_contents($this->_file, NULL);
            if (file_exists($this->_idx)) {
                unlink($this->_idx);
            }
        }

        if (! file_exists($this->_idx)) {
            file_put_contents($this->_idx, NULL);
        }

        $this->_file_h = fopen($this->_file, 'rw+b');
        $this->_idx_h = fopen($this->_idx, 'rw+b');
        
        if (function_exists('ftok') && function_exists('sem_get')) {
            if ($key = ftok($this->_idx, 'm')) {
                $this->_sem_lock_key = sem_get($key);
            }    
        }
    }

    /**
     * jump to start point of the queen file
     */
    public function rewind()
    {
        $this->_lock();
        $info = $this->_get_idx();
        $info[3] = $info[4] = 0;
        $this->_set_idx($info);
        $this->_unlock();
    }

    /**
     * jump to end point of the queen file 
     */
    public function end()
    {
        $this->_lock();
        $info = $this->_get_idx();
        $info[3] = $info[1];
        $info[4] = $info[2];
        $this->_set_idx($info);
        $this->_unlock();
    }

    /**
     * reset queen file and index file
     */
    public function reset()
    {
        $this->_lock();
        ftruncate($this->_file_h, 0);
        $info = array(1 => 0, 2 => 0, 3 => 0, 4 => 0);
        $this->_set_idx($info);
        $this->_unlock();

    }

    /**
     * get queen info array
     * [1] filesize, [2] file lines, [3] current position, [4] current line
     */
    public function info()
    {
        $this->_lock();
        $info = $this->_get_idx();
        $this->_unlock();
        
        return $info;
    }

    /**
     * get the left lines of the queen line
     */
    public function len()
    {
        $info = $this->info();
        return $info[2] - $info[4];
    }

    /**
     * get current items, will return FALSE when eof
     */
    public function pop()
    {

        $this->_lock();
        $info = $this->_get_idx();
        fseek($this->_file_h, $info[3]);
        if (($data = fgets($this->_file_h, 8192)) !== FALSE) {
            $info[4] = $info[4] + 1;
        }
        $info[3] = ftell($this->_file_h);
        $this->_set_idx($info);
        $this->_unlock();

        return $data;
    }

    /**
     * push item to end of queen
     */
    public function push($data)
    {
        $data_line = substr_count($data, "\n") + 1;
        $data = "{$data}\r\n";
        $data_size = strlen($data);

        $this->_lock();
        $info = $this->_get_idx();
        fseek($this->_file_h, $info[1]);
        fwrite($this->_file_h, $data);
        fseek($this->_file_h, $info[3]);
        $info[1] += $data_size;
        $info[2] += $data_line;
        $this->_set_idx($info);
        $this->_unlock();

        return $data_line;
    }

    /**
     * block lock mutex
     */
    private function _lock()
    {
        if ($this->_sem_lock_key) {
            sem_acquire($this->_sem_lock_key);
        } else {
            flock($this->_idx_h, LOCK_EX);
        }
    }

    /**
     * unlock mutex
     */
    private function _unlock()
    {
        if ($this->_sem_lock_key) {
            sem_release($this->_sem_lock_key);
        } else {
            flock($this->_idx_h, LOCK_UN);
        }
    }

    private function _count_file_line()
    {
        $line = 0;
        while (fgets($this->_file_h, 8192) !== FALSE) {
            $line ++;
        }

        return $line;
    }

    /**
     * get curren index info
     */
    private function _get_idx()
    {
        fseek($this->_idx_h, 0);
        if ($data = fread($this->_idx_h, 16)) {
            return unpack('L4', $data);
        } else {
            $idx = array(
                1 => filesize($this->_file),
                2 => $this->_count_file_line($this->_file),
                3 => 0,
                4 => 0,
            );
            $this->_set_idx($idx);
            return $idx;
        }
    }

    private function _set_idx($idx = array(0, 0, 0, 0))
    {
        fseek($this->_idx_h, 0); 
        fwrite($this->_idx_h, pack('L4', $idx[1], $idx[2], $idx[3], $idx[4]));
    }
}

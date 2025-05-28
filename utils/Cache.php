<?php
class Cache {
    private $cacheDir;
    private $ttl;

    public function __construct($ttl = 3600) {
        $this->cacheDir = __DIR__ . '/../cache/';
        $this->ttl = $ttl;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->ttl;
        $cacheFile = $this->getCacheFile($key);
        
        $cacheData = [
            'expires' => time() + $ttl,
            'data' => $data
        ];
        
        return file_put_contents($cacheFile, serialize($cacheData));
    }

    public function get($key) {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheData = unserialize(file_get_contents($cacheFile));
        
        if ($cacheData['expires'] < time()) {
            unlink($cacheFile);
            return null;
        }

        return $cacheData['data'];
    }

    private function getCacheFile($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }

    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
?>
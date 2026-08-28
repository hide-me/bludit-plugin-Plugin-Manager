<?php
class PM_Persistence
{
    private static $dir;
    private static $manager;

    public static function init($dir, $manager)
    {
        self::$dir = $dir;
        self::$manager = $manager;
    }

    public static function run()
    {
        $uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
        if (strpos($uri, "/uninstall-plugin/") !== false) return;
        if (strpos($uri, "/install-plugin/") !== false) {
            self::restoreActivatedPlugin($uri);
            return;
        }
        self::backupSettings();
    }

    private static function restoreActivatedPlugin($uri)
    {
        $part = parse_url($uri, PHP_URL_PATH);
        if (!is_string($part)) return;
        
        $pos = strpos($part, "/install-plugin/");
        if ($pos === false) return;
        
        $name = urldecode(trim(substr($part, $pos + strlen("/install-plugin/")), "/"));
        if ($name === "" || strpos($name, "/") !== false) return;
        if (!self::valid($name) || $name === self::$manager) return;
        
        $folder = self::pluginFolder($name);
        if ($folder === false) return;
        
        $backup = self::$dir . sha1($folder) . ".php";
        if (!is_file($backup)) return;
        
        $dbDir = rtrim(PATH_PLUGINS_DATABASES, DS) . DS . $folder . DS;
        $db = $dbDir . "db.php";
        if (!is_dir($dbDir)) @mkdir($dbDir, DIR_PERMISSIONS, true);
        if (!is_dir($dbDir)) return;
        
        $contents = @file_get_contents($backup);
        if ($contents === false || trim($contents) === "") return;
        
        @file_put_contents($db, $contents, LOCK_EX);
    }

    private static function backupSettings()
    {
        if (!defined("PATH_PLUGINS_DATABASES") || !is_dir(PATH_PLUGINS_DATABASES)) return;
        
        $directories = Filesystem::listDirectories(PATH_PLUGINS_DATABASES);
        if (!is_array($directories)) return;
        
        foreach ($directories as $directory) {
            $name = basename(rtrim($directory, DS));
            if (!self::valid($name) || $name === self::$manager) continue;
            
            $file = rtrim($directory, DS) . DS . "db.php";
            if (!is_file($file)) continue;
            
            $contents = @file_get_contents($file);
            if ($contents === false || trim($contents) === "") continue;
            
            $id = sha1($name);
            if (!is_dir(self::$dir)) @mkdir(self::$dir, DIR_PERMISSIONS, true);
            
            @file_put_contents(self::$dir . $id . ".php", $contents, LOCK_EX);
            
            $map = self::loadMap();
            
            $map[$id] = $name;
            
            self::saveMap($map);
        }
    }

    private static function pluginFolder($name)
    {
        if (defined("PATH_PLUGINS")) {
            $path = rtrim(PATH_PLUGINS, DS) . DS . $name;
            if (is_dir($path)) return $name;
        }
        
        if (!defined("PATH_PLUGINS")) return false;
        
        $directories = @scandir(PATH_PLUGINS);
        if (!is_array($directories)) return false;
        
        foreach ($directories as $directory) {
            if ($directory === "." || $directory === ".." || !self::valid($directory)) continue;
            $pluginFile = rtrim(PATH_PLUGINS, DS) . DS . $directory . DS . "plugin.php";
            
            if (!is_file($pluginFile)) continue;
            $code = @file_get_contents($pluginFile);
            
            if ($code === false) continue;
            
            if (preg_match("/class\\s+(plugin[A-Za-z0-9_]+)\\s+extends\\s+Plugin/", $code, $match) && $match[1] === $name) return $directory;
        }
        return false;
    }

    public static function delete($name)
    {
        if (!self::valid($name)) return false;
        
        $id = sha1($name);
        $file = self::$dir . $id . ".php";
        $map = self::loadMap();
        
        if (is_file($file)) @unlink($file);
        
        if (isset($map[$id])) {
            unset($map[$id]);
            self::saveMap($map);
        }
        return true;
    }

    private static function loadMap()
    {
        $file = self::$dir . "index.json";
        if (!is_file($file)) return [];
        
        $data = json_decode(@file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function saveMap($map)
    {
        if (!is_dir(self::$dir)) @mkdir(self::$dir, DIR_PERMISSIONS, true);
        @file_put_contents(self::$dir . "index.json", json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public static function valid($name)
    {
        return is_string($name) && preg_match('/^[A-Za-z0-9_-]+$/', $name);
    }
}

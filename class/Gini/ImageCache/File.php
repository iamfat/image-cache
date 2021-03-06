<?php
/**
* @file File.php
* @brief 图片处理
* @author Hongjie Zhu
* @version 0.1.0
* @date 2014-10-21
 */

namespace Gini\ImageCache;

use \Intervention\Image\ImageManagerStatic as Image;

class File
{

    private static function _getRoot()
    {
        $root = \Gini\Config::get('image-cache.cache_dir');
        return $root;
    }

    private static function _getRealPath($file, $ensure_dir=true)
    {
        $root = self::_getRoot();
        $file = rtrim($root, '/') . '/' . ltrim($file, '/');
        if ($ensure_dir) {
            $dir = dirname($file);
            \Gini\File::ensureDir($dir);
        }
        return $file;
    }

    public static function hash($url, $secret)
    {
        $hash = hash_hmac('md5', $url, $secret);
        return $hash;
    }

    public static function globDelete($hash, $path=null)
    {
        $part = $hash;
        if ($path) {
            if (!preg_match('/^(?:[a-z0-9]+(?:\/)?)+$/', $path)) return;
            $part = rtrim($path, '/') . '/' . $hash;
        }
        $pattern = self::_getRealPath($part, false) . '*';
        foreach (glob($pattern) as $f) {
            \Gini\File::delete($f);
        }
        return $pattern;
    }

    public static function has($file)
    {
        $file = self::_getRealPath($file);
        return file_exists($file);
    }

    public static function fetch($url, $file, $delete_if_exists=false)
    {

        $file = self::_getRealPath($file);
        $tmpFile = tempnam(self::_getRoot(), 'image-cache.');

        if (file_exists($file)) {
            if (!$delete_if_exists) {
                return true;
            }
        }

        $ch = curl_init();
        $handler = fopen($tmpFile, 'w');
        curl_setopt($ch, CURLOPT_URL, $url);
        $curl_config = (array)\Gini\Config::get('image-cache.curl');
        if (isset($curl_config['proxy'])) {
            curl_setopt($ch, CURLOPT_PROXY, $curl_config['proxy']);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FILE, $handler);
        $timeout = isset($curl_config['timeout']) ? $curl_config['timeout'] : 5;
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $content = curl_exec($ch);
        $hasError = curl_errno($ch);
        curl_close($ch);
        fclose($handler);

        if (!$hasError) {
            rename($tmpFile, $file);
            return true;
        }

        \Gini\File::delete($tmpFile);

        return $false;
    }

    public static function resize($from, $to, $width=null, $height=null)
    {
        if (!$width && !$height) return false;
        $from = self::_getRealPath($from);
        $to = self::_getRealPath($to);
        $image = Image::make($from);
        if (!$width || !$height) {
            $image->resize($width, $height, function($constraint) {
                $constraint->aspectRatio();
            });
        }
        else {
            $image->resize($width, $height);
        }
        if (!$image->save($to)) return false;
        return true;
    }

    public static function scale($from, $to, $times)
    {
        $file = self::_getRealPath($from);

        $image = Image::make($file);
        $raw_width = $image->width();
        $raw_height = $image->height();
        $width = $raw_width * $times;
        $height = $raw_height * $times;
        return self::resize($from, $to, $width, $height);
    }

    public static function getContentType($file)
    {
        $file = self::_getRealPath($file);

        $finfo = finfo_open(FILEINFO_MIME);
        $info = finfo_file($finfo, $file);
        finfo_close($finfo);
        $type = substr($info, 0, strpos($info, ';'));
        return $type;
    }

    public static function getContent($file)
    {
        $file = self::_getRealPath($file);

        $content = file_get_contents($file);
        return $content;
    }
}

<?php

namespace Yaro\Cropp;

use Intervention\Image\Facades\Image;
use Log;


class Cropp
{
    
    private $source    = '';
    private $sourceDirectory = '';
    private $sourceFile = '';
    private $fileHash  = '';
    private $extension = '';
    private $methods   = array();
    private $isAssetWrap;
    
    private $isSkip = false;
    
    public function __construct($source, $isAssetWrap = true, $directory = null)
    {
        $this->isAssetWrap = $isAssetWrap;
        
        // If a directory is set then compile the source path
        if (isset($directory)) {
            $this->sourceFile = $source;
            $source = $directory . $source;
            $this->sourceDirectory = $directory;
        }
        
        $source = public_path(ltrim($source, '/'));
        if (!is_readable($source)) {
            $noImageSource = config('yaro.cropp.no_image_source', false);
            if (!$noImageSource) {
                $this->isSkip = true;
                return;
            }
            $source = public_path(ltrim($noImageSource, '/'));
        }
        
        $this->fileHash = md5($source);
        
        preg_match('/\.[^\.]+$/i', $source, $matches);
        if (isset($matches[0])) {
            $this->extension = $matches[0];
        }
        $this->source = $source;
    } // end __construct
    
    public static function make($source, $isAssetWrap = true, $directory = null)
    {
        $cropp = get_called_class();
        
        return new $cropp($source, $isAssetWrap, $directory);
    } // end make
    
    public function __call($name, $arguments)
    {
        $this->methods[] = compact('name', 'arguments');
        
        return $this;
    } // end __call 
    
    public function __toString()
    {
        return $this->src();
    } // end __toString
    
    /**
     * Return the cache directory for the image
     * 
     * @return string
     */
    public function getCacheDirectory()
    {
        if (!empty($this->sourceDirectory)) {
            $path = $this->sourceDirectory . config('yaro.cropp.cache_prefix', '');
        } else {
            $path = config('yaro.cropp.cache_dir', 'storage/cropp');
        }
        return trim($path, '/');
    }
    
    public function src()
    {
        if ($this->isSkip) {
            return '';
        }
        
        $quality = config('yaro.cropp.cache_quality', 90);
        $cacheStorage = $this->getCacheDirectory();
        
        $methodsHash = md5(serialize($this->methods));
        $hash = md5($this->fileHash . $methodsHash . $quality);
        
        $source = '/'. $cacheStorage .'/'. $hash . $this->extension;
        if (is_readable(public_path($source))) {
            return $this->isAssetWrap ? asset($source) : $source;
        }
        
        try {
            $image = Image::make($this->source);
            
            foreach ($this->methods as $method) {
                call_user_func_array(array($image, $method['name']), $method['arguments']);
            }
        
            // Check the directory and make it if required
            if (!is_dir(public_path($cacheStorage))) {
                if (!mkdir(public_path($cacheStorage), 493)) {
                    throw new \RuntimeException(
                        'Unable to create image cache directory ['. public_path($cacheStorage) .']'
                    );
                }
            }
            
            $res = $image->save(public_path($source), $quality);
            if (!$res) {
                throw new \RuntimeException(
                    'Unable to save image cache to ['. public_path($source) .']'
                );
            }
        } catch (\Exception $exception) {
            Log::error($exception);
            return '';
        }
        
        return $this->isAssetWrap ? asset($source) : $source;
    } // end src

}

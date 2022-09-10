<?php
namespace App\Traits;

trait CacheableAttribute {

	protected $cached_attributes = [];

    protected function cache($key, callable $callback) { 
    	if (!isset($this->cached_attributes[$key]))
			$this->cached_attributes[$key] = $callback();

		return $this->cached_attributes[$key];
    }

}
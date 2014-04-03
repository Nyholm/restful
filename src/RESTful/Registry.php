<?php

namespace RESTful;

class Registry
{
    protected $_resources = array();

    public function add($resource)
    {
        array_push($this->_resources, $resource);
    }

    public function match($uri)
    {

        foreach ($this->_resources as $resource) {
            $specs = $resource::getURISpecs();
            foreach ($specs as $spec) {
                $result = $spec->match($uri);
                if ($result == null) {
                    continue;
                }
                $result['class'] = $resource;

                return $result;
            }
        }

        return null;
    }
}

<?php

namespace RESTful;

class Page
{
    public $resource,
        $total,
        $items,
        $offset,
        $limit;

    private $_first_uri,
        $_previous_uri,
        $_next_uri,
        $_last_uri;

    public function __construct($resource, $uri, $data = null)
    {
        $this->resource = $resource;
        if ($data == null) {
            $client = $resource::getClient();
            $data = $client->get($uri)->body;
        }
        //print_r($data);
        //print_r($resource);
        $resource_name = $resource::getURISpec()->name;
        //print_r($resource_name);
        //die(22);
        if(isset($data->$resource_name))
            $this->items = array_map(
                function ($x) use ($resource) {
                    return new $resource($x);
                },
                $data->$resource_name);
        else
            $this->items = array();

        $this->total = $data->meta->total;
        $this->offset = $data->meta->offset;
        $this->limit = $data->meta->limit;
        $this->_first_uri = $data->meta->first;
        $this->_previous_uri = $data->meta->previous;
        $this->_next_uri = $data->meta->next;
        $this->_last_uri = $data->meta->last;

    }

    public function first()
    {
        return new Page($this->resource, $this->_first_uri);
    }

    public function next()
    {
        if (!$this->hasNext()) {
            return null;
        }

        return new Page($this->resource, $this->_next_uri);
    }

    public function hasNext()
    {
        return $this->_next_uri != null;
    }

    public function previous()
    {
        return new Page($this->resource, $this->_previous_uri);
    }

    public function hasPrevious()
    {
        return $this->_previous_uri != null;
    }

    public function last()
    {
        return new Page($this->resource, $this->_last_uri);
    }
}

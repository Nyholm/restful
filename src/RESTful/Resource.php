<?php

namespace RESTful;

abstract class Resource
{
    protected $_collection_uris,
              $_member_uris,
              $_unmatched_uris;

    public static function getClient()
    {
        $class = get_called_class();

        return $class::$_client;
    }

    public static function getRegistry()
    {
        $class = get_called_class();

        return $class::$_registry;
    }

    public static function getURISpec()
    {
        $class = get_called_class();

        return $class::$_uri_spec;
    }

    public function __construct($fields = null, $links = null)
    {
        if ($fields == null) {
            $fields = array();
        }
        $this->_objectify($fields, $links);
    }

    public function __get($name)
    {
        // collection uri
        if (array_key_exists($name, $this->_collection_uris)) {
            $result = $this->_collection_uris[$name];
            $this->$name = new Collection($result['class'], $result['uri']);

            return $this->$name;
        } // member uri
        elseif (array_key_exists($name, $this->_member_uris)) {
            $result = $this->_member_uris[$name];
            $response = self::getClient()->get($result['uri']);
            $class = $result['class'];
            $this->$name = new $class($response->body);

            return $this->$name;
        } elseif (array_key_exists($name, $this->_unmatched_uris)) {
            $result = $this->_unmatched_uris[$name];
            $response = self::getClient()->get($result['uri']);
            $resource_href = null;
            foreach($response->body as $key => $val) {
                if(is_array($val) && isset($val[0]->href)) {
                    $resource_href = $val[0]->href;
                    break;
                }
            }
            $result = self::getRegistry()->match($resource_href);
            if($result != null) {
                //print_r($resource_href);
                $class = $result['class'];
                $this->$name = new $class($response->body);
                return $this->$name;
            }


            //print_r($response);
            //print_r($response->body->keys);

            //die(99);
//$class = $result['class'];
            //$this->$name = new $class($response->body);

            //return $this->$name;

        }

        // unknown
        //print_r($this);
        $trace = debug_backtrace();
        trigger_error(
            sprintf('Undefined property via __get(): %s in %s on line %s', $name, $trace[0]['file'], $trace[0]['line']),
            E_USER_NOTICE
        );

        return null;
    }

    public function __isset($name)
    {
        if (array_key_exists($name, $this->_collection_uris) ||
            array_key_exists($name, $this->_member_uris) ||
            array_key_exists($name, $this->_unmatched_uris)) {
            return true;
        }

        return false;
    }

    protected function _objectify($request, $links = null)
    {
        // initialize uris
        $this->_collection_uris = array();
        $this->_member_uris = array();
        $this->_unmatched_uris = array();

        $resource_name = $this->getURISpec()->name;

        /* print_r($request); */
        /* print_r($resource_name); */
        /* print_r($request->$resource_name); */
        /* print_r(isset($resquest->$resource_name)); */
        print_r($request);

        if(isset($request->$resource_name) && $links == null) {
            //var_dump($request);
            //print_r($resource_name);
            //print_r($request);
            //print_r($links);
            $fields = $request->$resource_name;
            $fields = $fields[0];
            $links = $request->links;
        } else {
            $fields = $request;
        }

        /* print_r($fields); */
        /* $a = 'api_keys'; */
        /* print_r($fields->$a); */
        /* var_dump($this->getURISpec()); */

        //$obj = $fields[self::$_uri_spec->name];
        //print_r($obj);

        //print_r('printing fields\n');

        //print_r($fields);
        //var_dump($fields);



        /* print_r("FIELDS\n"); */
        /* print_r($fields); */

        if($fields) {
            foreach ($fields as $key => $val) {
                //if($key == 'links') continue;
                $this->$key = $val;
            }
        }
        if($links) {
            foreach($links as $key => $val) {
                // the links might include links for other resources as well
                $parts = explode('.', $key);
                if($parts[0] != $resource_name) continue;
                //if(strpos($key, $resource_name) !== 0) continue;
                $name = $parts[1];

                //print_r("Working on link $parts[1]");
                $url = preg_replace_callback(
                    '/\{(\w+)\.(\w+)\}/',
                    function($match) use ($fields) {
                        $name = $match[2];
                        if(isset($fields->$name))
                            return $fields->$name;
                        elseif(isset($fields->links->$name))
                            return $fields->links->$name;
                    },
                    $val);
                //print_r("Made url: $url\n");
                //if(isset($fields->links->$key)) {
                // we have a url for a specific item, so check if it was side loaded
                // otherwise stub it out
                $result = self::getRegistry()->match($url);
                if($result != null) {
                    $class = $result['class'];
                    if($result['collection']) {
                        $this->_collection_uris[$name] = array(
                            'class' => $class,
                            'uri'   => $url,
                        );
                    } else {
                        $this->_member_uris[$name] = array(
                            'class' => $class,
                            'uri'   => $url,
                        );
                    }
                } else {
                    $this->_unmatched_uris[$name] = array(
                        'uri' => $url
                    );
                    // TODO: remove, if a resource type isn't found then just ignore it
                    //print_r("FAILED TO FIND RESOURCE FOR $url");
                    //die(1);
                }
                /*} else {
                // this item is paged
                $result = self::getRegistry()->match($url);
                if($result != null) {
                    $class = $result['class'];
                    if(
                }
                }*/
            }
        }
        //print_r('printing self\n');
        //print_r($this);

        //if($request->api_keys)
        //    die(0);

        /*foreach ($fields as $key => $val) {
            // nested uri
            if ((strlen($key) - 3) == strrpos($key, 'uri', 0) && $key != 'uri') {
                $result = self::getRegistry()->match($val);
                if ($result != null) {
                    $name = substr($key, 0, -4);
                    $class = $result['class'];
                    if ($result['collection']) {
                        $this->_collection_uris[$name] = array(
                            'class' => $class,
                            'uri'   => $val,
                        );
                    } else {
                        $this->_member_uris[$name] = array(
                            'class' => $class,
                            'uri'   => $val,
                        );
                    }

                    continue;
                }
            } elseif (is_object($val) && property_exists($val, 'uri')) {
                // nested
                $result = self::getRegistry()->match($val->uri);
                if ($result != null) {
                    $class = $result['class'];
                    if ($result['collection']) {
                        $this->$key = new Collection($class, $val['uri'], $val);
                    } else {
                        $this->$key = new $class($val);
                    }

                    continue;
                }
            } elseif (is_array($val) && array_key_exists('uri', $val)) {
                $result = self::getRegistry()->match($val['uri']);
                if ($result != null) {
                    $class = $result['class'];
                    if ($result['collection']) {
                        $this->$key = new Collection($class, $val['uri'], $val);
                    } else {
                        $this->$key = new $class($val);
                    }

                    continue;
                }
            }

            // default
            $this->$key = $val;
            }*/

    }

    public static function query()
    {
        $uri_spec = self::getURISpec();
        if ($uri_spec == null || $uri_spec->collection_uri == null) {
            $msg = sprintf('Cannot directly query %s resources', get_called_class());
            throw new \LogicException($msg);
        }

        return new Query(get_called_class(), $uri_spec->collection_uri);
    }

    public static function get($uri)
    {
        $class = get_called_class();

        # id
        if (strncmp($uri, '/', 1)) {
            $uri_spec = self::getURISpec();
            if ($uri_spec == null || $uri_spec->collection_uri == null) {
                $msg = sprintf('Cannot get %s resources by id %s', $class, $uri);
                throw new \LogicException($msg);
            }
            $uri = $uri_spec->collection_uri . '/' . $uri;
        }

        $response = self::getClient()->get($uri);

        return new $class($response->body);
    }

    public function save()
    {
        // payload
        $payload = array();
        foreach ($this as $key => $val) {
            if($key[0] == '_') continue;
            /* if ($key[0] == '_' || is_object($val)) { */
            /*     continue; */
            /* } */
            $payload[$key] = $val;
        }

        //print_r($payload);
        //if($payload) die(10);

        // update
        if (array_key_exists('href', $payload)) {
            $response = self::getClient()->put($payload['href'], $payload);
        } else {
            // create
            $class = get_class($this);
            if ($class::$_uri_spec == null || $class::$_uri_spec->collection_uri == null) {
                $msg = sprintf('Cannot directly create %s resources', $class);
                throw new \LogicException($msg);
            }
            $response = self::getClient()->post($class::$_uri_spec->collection_uri, $payload);
        }

        // re-objectify
        /* foreach ($this as $key => $val) { */
        /*     unset($this->$key); */
        /* } */
        $this->_objectify($response->body);

        return $this;
    }

    public function delete()
    {
        $resp = self::getClient()->delete($this->href);

        if($resp->code == 200) {
            $this->_objectify($resp->body);
        }

        return $this;
    }

    public function unstore()
    {
        return $this->delete();
    }

    public function refresh()
    {
        $resp = self::getClient()->get($this->href);
        $this->_objectify($resp->body);
        return $this;
    }
}

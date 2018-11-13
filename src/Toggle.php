<?php

class Toggle
{
    /**
     * @var array
     */
    private $features = [];

    /**
     * @var bool
     */
    private $preserve = true;

    /**
     * @var array
     */
    private $preserveResult = [];

    /**
     * @var bool
     */
    private $strict = false;

    /**
     * @param array $config
     * @return static
     */
    public static function createFromArray(array $config)
    {
        $toggle = new static();

        if (empty($config)) {
            return $toggle;
        }

        foreach ($config as $name => $item) {
            $item = static::normalizeConfigItem($item);

            $toggle->create(
                $name,
                $item['processor'],
                $item['params'],
                $item['staticResult']
            );
        }

        return $toggle;
    }

    /**
     * @param array $feature
     * @throws InvalidArgumentException
     */
    private static function assertFeature(array $feature)
    {
        if (!array_key_exists('name', $feature)) {
            throw new InvalidArgumentException('Feature key `name` is not found');
        }

        if (!is_string($feature['name'])) {
            throw new InvalidArgumentException('Feature key `name` must be array');
        }

        if (!array_key_exists('processor', $feature)) {
            throw new InvalidArgumentException('Feature key `processor` is not found');
        }

        if (!is_callable($feature['processor'])) {
            throw new InvalidArgumentException('Feature key `processor` must be callable');
        }

        if (isset($feature['params']) && !is_array($feature['params'])) {
            throw new InvalidArgumentException('Feature key `params` must be array');
        }
    }

    /**
     * @param array $config
     * @return array
     */
    private static function normalizeConfigItem($config)
    {
        if (!isset($config['processor'])) {
            $config['processor'] = null;
        }

        if (!isset($config['params'])) {
            $config['params'] = [];
        }

        if (!isset($config['staticResult']) || !is_bool($config['staticResult'])) {
            $config['staticResult'] = null;
        }

        return $config;
    }

    /**
     * @param array $feature
     * @return static
     */
    public function add(array $feature)
    {
        static::assertFeature($feature);

        if ($this->has($feature['name'])) {
            throw new RuntimeException("Feature '{$feature['name']}' is exist");
        }

        $this->features[$feature['name']] = $feature;

        return $this;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->features;
    }

    /**
     * @param array $features
     * @return static
     */
    public function append(array $features)
    {
        foreach ($features as $feature) {
            $this->add($feature);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param callable|bool|null $processor
     * @param array $params
     * @param bool|null $staticResult
     * @return static
     */
    public function create($name, $processor = null, array $params = [], $staticResult = null)
    {
        // default is false
        if (null === $processor) {
            $processor = false;
        }

        if (is_bool($processor)) {
            $processor = function () use ($processor) {
                return $processor;
            };
        }

        return $this->add([
            'name' => $name,
            'processor' => $processor,
            'params' => $params,
            'staticResult' => $staticResult,
        ]);
    }

    /**
     * @param string $name
     * @return array
     * @throws RuntimeException
     */
    public function feature($name)
    {
        if (!$this->has($name)) {
            throw new RuntimeException("Feature '{$name}' is not found");
        }

        return $this->features[$name];
    }

    /**
     * @return void
     */
    public function flush()
    {
        $this->features = [];
        $this->preserveResult = [];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->features);
    }

    /**
     * @param string $name
     * @param array $context
     * @return bool
     */
    public function isActive($name, $context = [])
    {
        if (!$this->has($name)) {
            if ($this->strict) {
                throw new RuntimeException("Feature '{$name}' is not found");
            }

            return false;
        }

        $feature = $this->feature($name);

        if (isset($feature['staticResult'])) {
            return $feature['staticResult'];
        }

        if (isset($this->preserveResult[$name])) {
            return $this->preserveResult[$name];
        }

        $result = $this->process($feature, $context);

        if ($this->preserve) {
            $this->preserveResult[$name] = $result;
        }

        return $result;
    }

    /**
     * @param string $name
     */
    public function remove($name)
    {
        unset($this->features[$name], $this->preserveResult[$name]);
    }

    /**
     * Import / export result data
     *
     * @param array|null $result
     * @return array
     */
    public function result(array $result = null)
    {
        if (null === $result) {
            return $this->preserveResult;
        }

        $this->preserveResult = array_merge($this->preserveResult, $result);

        return $result;
    }

    /**
     * @param array $features
     * @return static
     */
    public function set(array $features)
    {
        $this->flush();
        $this->append($features);

        return $this;
    }

    /**
     * @param bool $preserve
     * @return static
     */
    public function setPreserve($preserve)
    {
        $this->preserve = $preserve;

        return $this;
    }

    /**
     * @param bool $strict
     * @return static
     */
    public function setStrict($strict)
    {
        $this->strict = $strict;

        return $this;
    }

    /**
     * When $feature on, then call $callable
     *
     * @param string $name
     * @param callable $callable
     * @param array $context
     *
     * @return static
     */
    public function when($name, callable $callable, array $context = [])
    {
        if ($this->isActive($name, $context)) {
            $callable($context, $this->feature($name)['params']);
        }

        return $this;
    }

    /**
     * @param array $feature
     * @param array $context
     * @return mixed
     */
    private function process(array $feature, array $context)
    {
        $result = call_user_func($feature['processor'], $context, $feature['params']);

        if (!is_bool($result)) {
            throw new InvalidArgumentException('Processed result is not valid');
        }

        return $result;
    }
}
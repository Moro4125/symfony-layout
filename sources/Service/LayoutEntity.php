<?php

namespace Moro\SymfonyLayout\Service;

use SimpleXMLElement;

/**
 * Class LayoutEntity
 * @package Layout\Service
 */
class LayoutEntity
{
	/** @var SimpleXMLElement */
	private $_layout;
	/** @var array */
	private $_attributes;
	/** @var array */
	private $_parameters;
	/** @var LayoutEntity[] */
	private $_externals;
	/** @var array */
	private $_booleans = [
		'1'     => true,
		'0'     => false,
		'true'  => true,
		'false' => false,
		'on'    => true,
		'off'   => false,
	];

	/**
	 * @param string $xml
	 */
	public function __construct(string $xml)
	{
		$this->_layout = new SimpleXMLElement($xml);
	}

	/**
	 * @param string $name
	 * @param LayoutEntity|null $extends
	 */
	public function setExternal(string $name, LayoutEntity $extends = null)
	{
		$this->_externals[$name] = $extends;
		$this->_attributes = null;
		$this->_parameters = null;
	}

	/**
	 * @return array
	 */
	public function getExternals(): array
	{
		if ($this->_externals === null) {
			$list = $this->_getExternals($this->_layout);
			$this->_externals = array_fill_keys($list, null);
		}

		return $this->_externals;
	}

	/**
	 * @param SimpleXMLElement $node
	 * @return array
	 */
	private function _getExternals(SimpleXMLElement $node): array
	{
		$result = [];

		/** @noinspection PhpUndefinedFieldInspection */
		if ($name = $node->attributes()->extends) {
			$result[] = $name;
		}

		foreach ($node->children() as $child) {
			if ($child->getName() !== 'arg') {
				$list = $this->_getExternals($child);
				$result = array_merge($result, $list);
			}
		}

		return $result;
	}

	/**
	 * @return string
	 */
	public function getTemplateName(): string
	{
		/** @noinspection PhpUndefinedFieldInspection */
		return (string)$this->_layout->attributes()->template;
	}

	/**
	 * @param array $attributes
	 * @param array $config
	 * @param bool|null $withoutNormalize
	 * @return array
	 */
	public function getTemplateParameters(array $attributes, array $config, bool $withoutNormalize = null): array
	{
		if ($this->_parameters == null) {
			$parameters = $this->_getParameters($this->_layout, $attributes, $config);
			$parameters = $this->_applyExtends($parameters, $attributes, $config);
			$this->_parameters = $parameters;
		}

		return $withoutNormalize ? $this->_parameters : $this->_normalizeUri($this->_parameters);
	}

	/**
	 * @param SimpleXMLElement $node
	 * @param array $attributes
	 * @param array $config
	 * @return array
	 */
	private function _getParameters(SimpleXMLElement $node, array $attributes, array $config): array
	{
		$items = [];
		$result = [];

		foreach ($node->attributes() as $child) {
			$result[$child->getName()] = (string)$child;
		}

		foreach ($node->children() as $child) {
			if ($child->getName() === 'arg') {
				$value = null;

				/** @noinspection PhpUndefinedFieldInspection */
				if (!isset($value) && $a = $child->attributes()->request) {
					$value = $this->_getWithDotInKey((string)$a, $attributes);
				}

				/** @noinspection PhpUndefinedFieldInspection */
				if (!isset($value) && $a = $child->attributes()->config) {
					$value = $this->_getWithDotInKey((string)$a, $config);
				}

				/** @noinspection PhpUndefinedFieldInspection */
				if (!isset($value) && $a = $child->attributes()->flag) {
					$value = $this->_booleans[strtolower((string)$a)] ?? null;
				}

				/** @noinspection PhpUndefinedFieldInspection */
				if (!isset($value) && $a = $child->attributes()->array) {
					/** @noinspection PhpUndefinedFieldInspection */
					$value = $result['args'][(string)$child->attributes()->name] ?? [];
					/** @noinspection PhpUndefinedFieldInspection */
					$list = explode(',', (string)$child->attributes()->array);
					$value = array_merge($value, array_map('trim', $list));
				}

				/** @noinspection PhpUndefinedFieldInspection */
				if ($a = $child->attributes()->value) {
					$value = (string)$a;
				}

				/** @noinspection PhpUndefinedFieldInspection */
				if (!isset($value) && $a = $child->attributes()->default) {
					$value = (string)$a;
				}

				/** @noinspection PhpUndefinedFieldInspection */
				$result['args'][(string)$child->attributes()->name] = $value;
			} elseif ($value = $this->_getParameters($child, $attributes, $config)) {
				$items[$child->getName() . 's'][] = $value;
			}
		}

		return array_merge($result, $items);
	}

	/**
	 * @param array $node
	 * @param array $attributes
	 * @param array $config
	 * @return array
	 */
	private function _applyExtends(array $node, array $attributes, array $config): array
	{
		foreach ($node as $key => $value) {
			if ($key !== 'args' && is_array($value)) {
				foreach ($value as $index => $next) {
					$node[$key][$index] = $this->_applyExtends($next, $attributes, $config);
				}
			}
		}

		if (($name = $node['extends'] ?? null) && isset($this->_externals[$name])) {
			$parent = $this->_externals[$name]->getTemplateParameters($attributes, $config, true);
			$node = $this->_mergeParameters($parent, $node);
			unset($node['extends']);
		}

		return $node;
	}

	/**
	 * @param array $prev
	 * @param array $next
	 * @return array
	 */
	private function _mergeParameters(array $prev, array $next): array
	{
		$result = $prev;

		foreach ($next as $key => $value) {
			if (ctype_digit((string)$key)) {
				$result[] = $value;
			} elseif (!isset($result[$key]) || !is_array($result[$key]) || !is_array($value)) {
				$result[$key] = $value;
			} elseif ($key === 'args') {
				$result[$key] = $this->_mergeParameters($result[$key], $value);
			} else {
				foreach ($value as $index => $item) {
					if (isset($item['id'])) {
						foreach ($result[$key] as $i) {
							if (isset($i['id']) && $i['id'] === $item['id']) {
								$result[$key][$i] = $this->_mergeParameters($result[$key][$i], $item);
								continue 2;
							}
						}
					}

					$result[$key][] = $item;
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $node
	 * @return array
	 */
	private function _normalizeUri(array $node): array
	{
		if (isset($node['uri'])) {
			$query = [];

			foreach ($node as $key => $value) {
				if ($key === 'args') {
					$query = $this->_mergeWithDotInKeys($value, $query);
					unset($node[$key]);
				} elseif (is_array($value)) {
					foreach (array_map([$this, __FUNCTION__], $value) as $v) {
						$query[$key][] = empty($v['uri']) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v['uri'];
					}

					unset($node[$key]);
				}
			}

			$node['uri'] .= $query ? ((strpos($node['uri'], '?') ? '&' : '?') . http_build_query($query)) : '';
		} else {
			foreach ($node as $key => $value) {
				if ($key === 'args') {
					$node[$key] = $this->_mergeWithDotInKeys($value, []);
				} elseif (is_array($value)) {
					$node[$key] = array_map([$this, __FUNCTION__], $value);
				}
			}
		}

		return $node;
	}

	/**
	 * @return array
	 */
	public function getUsedAttributes(): array
	{
		if ($this->_attributes !== null) {
			return $this->_attributes;
		}

		$this->_attributes = [];
		$this->_getUsedAttributes($this->_layout);

		foreach ($this->getExternals() as $external) {
			if ($external) {
				$this->_attributes = array_merge($this->_attributes, $external->getUsedAttributes());
			}
		}

		return $this->_attributes;
	}

	/**
	 * @param SimpleXMLElement $node
	 */
	private function _getUsedAttributes(SimpleXMLElement $node)
	{
		foreach ($node->children() as $child) {
			if ($child->getName() === 'arg') {
				/** @noinspection PhpUndefinedFieldInspection */
				if ($child->attributes()->request) {
					/** @noinspection PhpUndefinedFieldInspection */
					$this->_attributes[] = (string)$child->attributes()->request;
				}
			} else {
				$this->_getUsedAttributes($child);
			}
		}
	}

	/**
	 * @param string $key
	 * @param array $attributes
	 * @param array $config
	 * @return array
	 */
	public function getTemplatesEx(string $key, array $attributes, array $config): array
	{
		$parameters = $this->getTemplateParameters($attributes, $config, true);

		return $this->_getTemplatesEx($parameters, $key);
	}

	/**
	 * @param array $node
	 * @param string $attr
	 * @return array
	 */
	private function _getTemplatesEx(array $node, string $attr): array
	{
		$result = [];

		if ($template = $node[$attr] ?? null) {
			$current = ['args' => $node['args']];

			foreach ($node as $key => $value) {
				if (!is_array($value)) {
					$current[$key] = $value;
				}
			}

			$result[$template] = [$current];
		}

		foreach ($node as $key => $value) {
			if ($key !== 'args' && is_array($value)) {
				foreach ($this->_getTemplatesEx($value, $attr) as $k => $v) {
					$result[$k] = array_merge($result[$k] ?? [], $v);
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $key
	 * @param array $source
	 * @return mixed|null
	 */
	private function _getWithDotInKey(string $key, array $source)
	{
		while ($p = strpos($key, '.')) {
			$index = substr($key, 0, $p);
			$key = substr($key, $p + 1);

			if (!isset($source[$index]) || !is_array($source[$index])) {
				return null;
			}

			$source = $source[$index];
		}

		return $source[$key] ?? null;
	}

	/**
	 * @param array $source
	 * @param array $target
	 * @return array
	 */
	private function _mergeWithDotInKeys(array $source, array $target): array
	{
		foreach ($source as $key => $value) {
			$cursor = &$target;

			while ($p = strpos($key, '.')) {
				$index = substr($key, 0, $p);
				$key = substr($key, $p + 1);

				if (!isset($cursor[$index]) || !is_array($cursor[$index])) {
					$cursor[$index] = [];
				}

				$cursor = &$cursor[$index];
			}

			$cursor[$key] = $value;
			unset($cursor);
		}

		return $target;
	}
}
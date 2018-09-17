<?php

namespace Moro\SymfonyLayout;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class SymfonyLayoutConfiguration
 * @package Layout
 */
class SymfonyLayoutConfiguration implements ConfigurationInterface
{
	const ROOT = 'symfony_layout';

	const P_PATHS    = 'paths';
	const P_RENDERER = 'renderer';
	const P_OPTIONS  = 'options';

	/**
	 * @return TreeBuilder
	 */
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder();
		$rootNode = $treeBuilder->root(self::ROOT);
		$root = $rootNode->children();

		$root->arrayNode(self::P_PATHS)
			->normalizeKeys(false)
			->useAttributeAsKey(self::P_PATHS)
			->beforeNormalization()
			->always()
			->then(function ($paths) {
				$normalized = array();
				foreach ($paths as $path => $namespace) {
					if (\is_array($namespace)) {
						// xml
						$path = $namespace['value'];
						$namespace = $namespace['namespace'];
					}

					// path within the default namespace
					if (ctype_digit((string)$path)) {
						$path = $namespace;
						$namespace = null;
					}

					$normalized[$path] = $namespace;
				}

				return $normalized;
			})
			->end()
			->prototype('variable');

		$root->arrayNode(self::P_OPTIONS)
			->prototype('variable');

		$root->scalarNode(self::P_RENDERER)
			->defaultValue('esi');

		return $treeBuilder;
	}
}
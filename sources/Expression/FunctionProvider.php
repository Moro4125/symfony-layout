<?php

namespace Moro\SymfonyLayout\Expression;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Class FunctionProvider
 * @package Layout\Expression
 */
class FunctionProvider implements ExpressionFunctionProviderInterface
{
	public $from;
	public $to;

	/**
	 * @return ExpressionFunction[]
	 */
	public function getFunctions()
	{
		return [
			new ExpressionFunction('from', function ($datetime, $timezone = null) {
				$code = '(time() > (new \DateTime(%1$s, %2$s ? new \DateTimeZone(%2$s)))->getTimestamp())';

				return sprintf($code, $datetime, $timezone);
			}, function ($arguments, $datetime, $timezone = null) {
				unset($arguments);
				$datetime = new \DateTime($datetime, $timezone ? new \DateTimeZone($timezone) : null);

				$this->from = $datetime->getTimestamp();

				return time() > $datetime->getTimestamp();
			}),
			new ExpressionFunction('to', function ($datetime, $timezone = null) {
				$code = '(time() < (new \DateTime(%1$s, %2$s ? new \DateTimeZone(%2$s)))->getTimestamp())';

				return sprintf($code, $datetime, $timezone);
			}, function ($arguments, $datetime, $timezone = null) {
				unset($arguments);
				$datetime = new \DateTime($datetime, $timezone ? new \DateTimeZone($timezone) : null);

				$this->to = $datetime->getTimestamp();

				return time() < $datetime->getTimestamp();
			}),
		];
	}
}
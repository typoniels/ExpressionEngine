<?php
/**
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2019, EllisLab Corp. (https://ellislab.com)
 * @license   https://expressionengine.com/license
 */

namespace EllisLab\ExpressionEngine\Service\Dependency;

use Closure;

/**
 * Service Provider Interface
 */
interface ServiceProvider {

	public function register($name, $object);
	public function bind($name, $object);
	public function registerSingleton($name, $object);
	public function make();

}

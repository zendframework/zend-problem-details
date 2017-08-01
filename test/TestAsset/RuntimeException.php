<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\ProblemDetails\TestAsset;

use RuntimeException as BaseRuntimeException;
use Throwable;

class RuntimeException extends BaseRuntimeException
{
    /**
     * @param string $message
     * @param mixed $code Mimic PHP internal exceptions, and allow any code.
     * @param Throwable $previous
     */
    public function __construct(string $message, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->code = $code;
    }
}

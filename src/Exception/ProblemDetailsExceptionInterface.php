<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\ProblemDetails\Exception;

use JsonSerializable;

/**
 * Defines an exception type for generating Problem Details.
 */
interface ProblemDetailsExceptionInterface extends JsonSerializable
{
    public function getStatus() : int;

    public function getType() : string;

    public function getTitle() : string;

    public function getDetail() : string;

    public function getAdditionalData() : array;

    /**
     * Serialize the exception to an array of problem details.
     *
     * Likely useful for the JsonSerializable implementation, but also
     * for cases where the XML variant is desired.
     */
    public function toArray() : array;
}

<?php
/**
 * @see       https://github.com/zendframework/zend-problem-details for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-problem-details/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\ProblemDetails\Exception;

use PHPUnit\Framework\TestCase;
use Zend\ProblemDetails\Exception\CommonProblemDetailsException;
use Zend\ProblemDetails\Exception\ProblemDetailsException;

class ProblemDetailsExceptionTest extends TestCase
{
    protected $status = 403;
    protected $detail = 'You are not authorized to do that';
    protected $title = 'Unauthorized';
    protected $type = 'https://httpstatus.es/403';
    protected $additional = [
        'foo' => 'bar',
    ];

    protected function setUp() : void
    {
        $this->exception = new class (
            $this->status,
            $this->detail,
            $this->title,
            $this->type,
            $this->additional
        ) implements ProblemDetailsException {
            use CommonProblemDetailsException;

            public function __construct(int $status, string $detail, string $title, string $type, array $additional)
            {
                $this->status = $status;
                $this->detail = $detail;
                $this->title = $title;
                $this->type = $type;
                $this->additional = $additional;
            }
        };
    }

    public function testCanPullDetailsIndividually() : void
    {
        $this->assertEquals($this->status, $this->exception->getStatus());
        $this->assertEquals($this->detail, $this->exception->getDetail());
        $this->assertEquals($this->title, $this->exception->getTitle());
        $this->assertEquals($this->type, $this->exception->getType());
        $this->assertEquals($this->additional, $this->exception->getAdditionalData());
    }

    public function testCanCastDetailsToArray() : void
    {
        $this->assertEquals([
            'status' => $this->status,
            'detail' => $this->detail,
            'title'  => $this->title,
            'type'   => $this->type,
            'foo'    => 'bar',
        ], $this->exception->toArray());
    }

    public function testIsJsonSerializable() : void
    {
        $problem = json_decode(json_encode($this->exception), true);

        $this->assertEquals([
            'status' => $this->status,
            'detail' => $this->detail,
            'title'  => $this->title,
            'type'   => $this->type,
            'foo'    => 'bar',
        ], $problem);
    }
}

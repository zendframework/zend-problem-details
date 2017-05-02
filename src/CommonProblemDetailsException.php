<?php

namespace ProblemDetails;

/**
 * Common functionality for ProblemDetailsException implementations.
 *
 * Requires defining the following properties in the composing class:
 *
 * - status (int)
 * - detail (string)
 * - title (string)
 * - type (string)
 * - additional (array)
 */
trait CommonProblemDetailsException
{
    public function getStatus() : int
    {
        return $this->status;
    }

    public function getType() : string
    {
        return $this->type;
    }

    public function getTitle() : string
    {
        return $this->title;
    }

    public function getDetail() : string
    {
        return $this->detail;
    }

    public function getAdditionalData() : array
    {
        return $this->additional;
    }

    /**
    * Serialize the exception to an array of problem details.
    *
    * Likely useful for the JsonSerializable implementation, but also
    * for cases where the XML variant is desired.
    */
    public function toArray() : array
    {
        $problem = [
            'status' => $this->status,
            'detail' => $this->detail,
            'title'  => $this->title,
            'type'   => $this->type,
        ];

        if (! empty($this->additional)) {
            $problem = array_merge($this->additional, $problem);
        }

        return $problem;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
